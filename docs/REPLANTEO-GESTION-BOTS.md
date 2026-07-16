# Replanteo del manejo de los bots WhatsApp (y del concepto de manejo en general)

> 2026-07-16 — disparador: el corte de internet del 15/07 (dos vigilantes reiniciando
> en loop bots sanos) y el cuelgue "autenticada sin ready" del 16/07 que requirió
> restauración manual de snapshot. Este doc plantea primero el **concepto** de manejo
> (qué protegemos, qué toleramos, quién decide), después el inventario de lo que hay,
> y recién al final los caminos de implementación.

## 0. El concepto — qué significa "manejar" este sistema

### 0.1 El manejo actual protege lo equivocado

Todo el edificio de vigilancia (watchdog interno, autoheal, WatchdogBot) existe para
mantener vivos **procesos**. Pero el proceso es lo descartable: un container se recrea
en 2 minutos desde la imagen. Lo irreemplazable es el **pareo de WhatsApp** (la sesión):
perderlo requiere un humano con el celular del área, y el servidor de Meta puede
revocarlo si ve comportamiento raro — como reinicios en ráfaga. Los hechos: el LOGOUT
de ovo (12/07) vino después de un reinicio automático; el 15/07 dos vigilantes
reiniciaron en loop bots sanos; el 01/07 autoheal reinició 6 horas un bot que solo
necesitaba un QR. **El manejo actual arriesga la joya para salvar lo descartable.**

### 0.2 El modo degradado real es tolerable (y eso cambia todo)

Cuando un bot cae, **el canal no se cae**: el celular del área sigue recibiendo y las
secretarias pueden atender desde el teléfono, como siempre pudieron. Lo que se pierde
es visibilidad en el panel, clasificación y registro — molesto, pero tolerable por
horas. No hay pacientes sin atender.

Consecuencia directa: la urgencia de "revivir YA el proceso a cualquier costo" es
falsa. **Una alerta clara a los 5 minutos vale más que seis reinicios automáticos**,
porque el costo real de un reinicio de más no es CPU: es la probabilidad de convertir
"2 horas de panel ciego" en "sesión revocada + re-pareo manual + N contactos con
mensajes ilegibles".

### 0.3 Principios propuestos para el manejo (aplican hoy con wwebjs y mañana con lo que sea)

1. **Jerarquía de activos explícita.** Sesión/pareo > datos (DB/media) > proceso.
   Ninguna acción automática puede arriesgar un activo superior para salvar uno
   inferior. (Regla concreta: máx 2 reinicios por bot por hora; el tercero es una
   alerta, no un reinicio.)
2. **Un solo cerebro.** Separar plano de datos (los bots mueven mensajes, tontos, sin
   decisiones de ciclo de vida) de plano de control (UN supervisor que observa, decide
   y actúa, con estado explícito, journal e historia). Hoy hay tres cerebros parciales
   que no se conocen entre sí.
3. **Degradación conocida > recuperación heroica.** El modo degradado ("atender desde
   el celular, panel ciego") se documenta, se avisa a las secretarias cuando se entra
   y se sale, y se entrena. La automatización solo recupera lo que es *seguro*
   recuperar; el resto alerta con playbook linkeado.
4. **Diagnóstico antes que acción.** Cada clase de falla conocida (ver §1, catálogo de
   12) tiene una firma observable y una remediación específica. Reiniciar "a ver si
   se arregla" fue causa de incidentes, no solución: sin diagnóstico → alertar, no
   actuar.
5. **Historia obligatoria.** Toda transición de estado y toda acción del plano de
   control queda en un journal consultable. Las decisiones usan historia ("es el 2º
   restart en 30 min" / "esta sonda falla crónico desde el martes") — hoy cada chequeo
   decide amnésico.
6. **Proporcionalidad.** Esto es una clínica con una máquina y 13 usuarios, no un
   datacenter: el plano de control es UN servicio chico + UN journal + UN canal de
   alertas. Anti-objetivos explícitos: Kubernetes, stack Prometheus/Grafana, VPS-fleet.
7. **Inversión descartable en tecnología condenada.** wwebjs tiene techo estructural
   (§2). Todo lo que se construya para manejarlo debe ser barato y tirable — el valor
   duradero está en el concepto (principios 1-6), que sobrevive al cambio de
   tecnología de mensajería.

### 0.4 Alcance: ¿solo bots, o el manejo de toda la plataforma?

El problema conceptual no es exclusivo de los bots — ya hay **seis** mini-planos de
control dispersos: autoheal, WatchdogBot, tunnel-watchdog, healthchecks de compose,
backups nocturnos con verificación propia, y el watchdog interno de cada bot. MySQL,
reverb (healthcheck con falso negativo conocido), ollama y el túnel tienen hoy la
misma gestión "cada uno se cuida como puede".

Propuesta de alcance: el **concepto** (principios 0.3, supervisor, journal, alertas)
se diseña para la plataforma entera; la **implementación** arranca por los bots porque
son el 90% de los incidentes, y los demás servicios se suman como observados baratos
(su remediación es trivial: restart sin riesgo — no tienen "joya").

### 0.5 Qué es "sólido" (objetivos medibles, a acordar)

- Detección de cualquier falla de bot: **< 5 min** (hoy: entre 5 min y 10 días según
  la falla).
- Recuperación automática solo de fallas con diagnóstico conocido y acción segura;
  resto: alerta accionable < 5 min con playbook.
- **Cero** acciones automáticas que puedan costar un pareo.
- Un incidente cualquiera se puede reconstruir después desde el journal sin arqueología
  de `docker logs`.
- El modo degradado por área está escrito y las secretarias lo conocen.

## 1. Qué hay hoy (inventario real, relevado 16/07)

La "gestión" de los bots está repartida en **quince piezas, cuatro lenguajes y tres
niveles**, y hay **tres actores distintos con poder de reinicio**:

### Dentro de cada proceso bot (Node)
| Pieza | Qué hace | Origen |
|---|---|---|
| Adapter wwebjs (`clientes/wwebjs.js`, 544 líneas) | Chromium headless vía Puppeteer/CDP; pin de versión WA Web; protocolTimeout 6 min; espera DNS al boot; limpieza de locks | acumulado |
| Watchdog interno v3 | sonda browser (3 strikes) + sonda página × inactividad (umbral por área) + bringToFront preventivo; reinicia el cliente in-process con limpieza de caché | páginas zombie 06-07/07 |
| Backoff exponencial | reintentos de initialize 15s→5min | churn de Chromium |
| Snapshot + marker + auto-restore | copia la sesión sana al apagar limpio; si aparece QR con marker válido restaura (máx 2) | muertes de sesión 29/06–04/07 |
| Timeouts en llamadas al cliente | falla rápido si CDP está trabado | sockets colgados |

### Nivel container
| Pieza | Qué hace | Origen |
|---|---|---|
| `bot/healthcheck.sh` (16/07) | /status + **sonda de internet**: sin salida a web.whatsapp.com no marca unhealthy | corte 15/07 |
| autoheal (sidecar) | reinicia containers unhealthy | admin frozen 10 días (jun) |
| `restart: unless-stopped` | reinicia si el proceso sale | base |

### Nivel host (Windows)
| Pieza | Qué hace | Origen |
|---|---|---|
| `WatchdogBot` (cada 5 min) | Docker Desktop vivo; /status por bot; `docker restart` tras 10 min en falla (ahora offline-aware); notifica por WA | freeze de admin |
| `BackupFull` 02:30 | limpia caché, tar de sesiones (+.prev), mysql, media | restauración sin QR |
| `BackupMySQL` 03:00, `MapearWA`, `SyncAvatares` | funcionales | — |
| Playbooks **manuales** | limpiar caché / restaurar snapshot / restaurar tar / re-pareo QR | cada incidente |

### Catálogo de modos de falla conocidos (cada uno agregó una capa)
1. Caché Chromium hinchada → CDP timeouts (bug de envío, recurrente)
2. Página zombie: browser vivo, renderer congelado (atención 21h / ovo 31h mudas)
3. Chromium congelado total (admin 10 días sin que nada lo levante)
4. **Autenticada sin ready** post-recreate (16/07: la limpieza de caché no alcanzó; lo destrabó restaurar el snapshot)
5. Corrupción local de IndexedDB por apagado sucio (29/06, 01/07, 04/07)
6. LOGOUT server-side real (ovo 12/07) — solo lo arregla QR; reiniciar lo empeora
7. `esperando_qr` + reinicio = loop de QR regenerado (01/07: 6 horas de reinicios inútiles)
8. Build mala de WA Web rolleada por Meta (12/06) → pin de versión
9. DNS de Docker no listo al boot → initialize condenado
10. WSL: `wsl --shutdown` rompe port forwarding; `docker logs` se congela; RAM (atención ~1.5 GB)
11. Corte de internet → loop de reinicios de bots sanos (15/07)
12. Sonda de página "sin respuesta" crónica = perfil Chromium podrido → pide re-pareo preventivo

## 2. El problema de fondo (dos, en realidad)

**a) Arquitectónico nuestro:** no hay un cerebro. Tres actores reinician por su cuenta
(watchdog interno, autoheal, WatchdogBot), las reglas viven dispersas en JS, sh, ps1 y
YAML, no hay máquina de estados única ni journal de transiciones, y la **escalera de
remediación** (restart → limpiar caché → snapshot → tar → QR) existe solo como playbook
manual en la memoria/docs. Cada incidente nuevo = parche nuevo en un lugar nuevo.
Los propios vigilantes ya causaron incidentes (reinicios que precedieron al LOGOUT de
ovo; loops de QR; loops por falta de internet).

**b) Estructural de la tecnología:** wwebjs **es un navegador headless simulando ser
WhatsApp Web**. Las clases de falla 1-8 son inherentes: Chromium pesado y congelable,
sesión = perfil de navegador frágil, Meta cambia WA Web cuando quiere, y siempre existe
el riesgo de logout/ban por uso no oficial. Por mejor que gestionemos, **ese techo no
se levanta gestionando** — solo cambiando de tecnología.

## 3. Caminos de implementación (subordinados al concepto de §0)

### A. Supervisor único (consolidar, seguir en wwebjs)
Extraer TODA la decisión de ciclo de vida a **un** servicio supervisor (container Node
liviano con acceso a docker.sock). Los bots solo **reportan** estado rico (fase,
last_ready_at, last_msg_at, resultado de sondas, salud de sesión — el 80% ya existe);
el supervisor **decide** con una máquina de estados explícita:

- Escalera de remediación automática, en orden y con verificación entre pasos:
  restart container → limpiar caché → **restaurar snapshot** → restaurar tar del backup
  → declarar `esperando_qr` y alertar. (Hoy los pasos 2-4 son manuales; el 16/07 los
  ejecuté a mano en ese orden exacto.)
- Circuit breaker: máx N reinicios por ventana por bot → parar y alertar (proteger la
  sesión es más importante que insistir).
- Journal append-only de transiciones/acciones por bot → decidir con historia y
  diagnosticar incidentes sin arqueología de logs.
- Excepciones de primera clase: `esperando_qr` (nunca reiniciar), sin internet
  (esperar), LOGOUT real (solo alertar).
- **Se eliminan**: autoheal, el grueso de WatchdogBot (queda "Docker vivo + supervisor
  vivo"), y el watchdog interno se reduce a sondas puras que reporta.

Pro: un solo cerebro testeable; encodea los playbooks ya validados; sin costo mensual;
sin cambio operativo. Contra: el techo de wwebjs sigue ahí — es construir un excelente
hospital para un paciente crónico.

### B. WhatsApp Business Platform (Cloud API) — el fix definitivo
Ya identificada en la evaluación de consolidación como "el único pendiente real".
Elimina de raíz las clases de falla 1-8: sin Chromium, sin sesión local, sin QR,
sin watchdogs. Webhooks entrantes + HTTP saliente, estado en Meta.

Datos verificados (jul-2026):
- **Costo**: desde jul-2025 se cobra **por mensaje de template** entregado. Las
  **conversaciones de servicio son gratis**: paciente escribe → ventana de 24h en la
  que todas las respuestas son gratuitas (se renueva con cada mensaje del paciente).
  Para una clínica que mayormente **responde** consultas, el costo recurrente es ~0.
  Se paga solo por salientes fuera de ventana (recordatorios de turno = template
  "utility", del orden de centavos de dólar por mensaje).
- **Coexistence** (desde may-2025): el mismo número puede estar en la **app WhatsApp
  Business del celular y en la API a la vez**, sincronizados en ambos sentidos, con
  hasta 6 meses de historial importado. Las secretarias seguirían usando el teléfono
  como hoy (lo que cubre el flujo `message_create`/saliente externo). Requisitos: el
  número debe estar en la app **WhatsApp Business** (no WhatsApp común) y abrir la app
  al menos cada 14 días. Se pierden: mensajes efímeros, view-once, live location.
- **Requisitos**: verificación del negocio en Meta, templates aprobados para salientes,
  y un **endpoint HTTPS público para webhooks** — hoy no hay ingress público (ngrok es
  de testing). Opciones: Cloudflare Tunnel con dominio propio (gratis), VPS chico como
  relay (~USD 5/mes), o receptor en el hosting workbench (Ferozo, con su WAF).
- Los grupos no están soportados por la Cloud API — no nos afecta: el bot ya filtra
  `isGroupMsg` hoy.

Contra: dependencia de Meta/trámites, cambio de modelo (webhooks), y el ingress público
es una pieza nueva de infra a diseñar con cuidado (auth, replay, cola local).

### C. Híbrido gradual ← **recomendada**
La arquitectura ya tiene el seam perfecto: `cliente-wa.js` es un factory de adapters
(wwebjs/baileys — baileys se fue, pero la interfaz quedó: `sendText`, `sendMedia`,
eventos `ready/message/disconnected`...). Un adapter `cloudapi` encaja ahí **sin que
Laravel se entere**.

1. **Corto plazo**: Opción A acotada — supervisor único + escalera automática +
   journal. Deja de sangrar y encodea lo aprendido. (1-2 sesiones)
2. **Mediano**: spike de Cloud API — verificación Meta, número de prueba, medir costos
   reales AR, decidir ingress. (1 sesión + trámites)
3. **Piloto**: adapter `cloudapi` + UN área con coexistence (candidata: ovodonación,
   la de menor tráfico y peor historial de sesión).
4. **Decisión final**: migrar el resto o convivir. Cada área migrada retira sus capas
   wwebjs (watchdogs, snapshots, QR).

### D. Mover los bots a un VPS Linux
Ataca la clase de falla 10 (WSL/Docker Desktop/port forwarding/logs congelados), no
las 1-8. Contra: datos de pacientes fuera del site, latencia contra MySQL local, costo,
y ya se invirtió mucho en estabilizar lo local. Variante útil: usar un VPS **solo como
relay de webhooks** para la opción B (no toca datos en reposo).

## 4. Comparación

| | A. Supervisor | B. Cloud API | C. Híbrido | D. VPS |
|---|---|---|---|---|
| Solidez alcanzable | media-alta (techo wwebjs) | **alta** | alta al final | media |
| Costo mensual | $0 | ~$0 servicio + centavos por recordatorio | ídem B al migrar | USD 5-20 |
| Esfuerzo | 1-2 sesiones | trámites + ingress + adapter | escalonado | migración infra |
| Riesgo operativo | nulo | medio (trámites, cambio de flujo) | **bajo** (piloto acotado) | medio |
| Reversibilidad | total | media | **alta** (por área) | media |
| Elimina watchdogs | no (los ordena) | **sí** | por área | no |

## 5. Preguntas abiertas (para decidir)

1. ¿Los celulares de las 3 áreas usan **WhatsApp Business** (app) o WhatsApp común?
   Coexistence requiere la app Business — si usan la común, hay una migración de app
   previa (conserva número e historial, trámite menor).
2. ¿Hay cuenta Meta Business de la clínica, y quién la administra? (verificación)
3. Ingress para webhooks: ¿dominio propio disponible para un Cloudflare Tunnel, o
   preferís VPS relay / Ferozo?
4. ¿Presupuesto mensual aceptable si los recordatorios salientes crecen? (orden: 1000
   utility/mes ≈ USD 5-30 según tarifa AR — a medir en el spike)

## 6. Estado

- [x] Concepto explorado (16/07): orquestador cerebro+plugins — diseño en
      `ORQUESTADOR-DISENO.md`
- [x] **Evaluación con datos (16/07, `EVALUACION-2026-07.md`): la evidencia retiró
      la propuesta del orquestador** — 85% de los incidentes nace en wwebjs (capa a
      reemplazar, no a gestionar) y el resto de la plataforma tiene 0 incidentes.
      Queda como plan B.
- [x] Mitigaciones mínimas aplicadas al watchdog existente (offline-aware, anti-loop
      uptime, circuit breaker, modo mantenimiento)
- [ ] Medidor de flujo end-to-end (ingesta vs baseline)
- [ ] **Piloto Cloud API** (el movimiento principal — ver EVALUACION-2026-07 §4)

Fuentes (verificadas 16/07/2026): pricing per-message y ventana de servicio gratuita
([Uptail](https://www.uptail.ai/blog/whatsapp-business-api-pricing-2026-what-it-costs-and-how-billing-works),
[Blueticks](https://blueticks.co/blog/whatsapp-business-api-pricing-2026)); coexistence
([Whautomate](https://whautomate.com/whatsapp-coexistence),
[YCloud](https://www.ycloud.com/blog/whatsapp-business-app-coexistence-meta-update),
[Wati](https://support.wati.io/en/articles/11822402-introducing-whatsapp-coexistence)).
