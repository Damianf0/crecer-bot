# Delta crecer vs workbench-bot — por qué uno hace 3 reinicios en 4 meses y el otro 150 por noche

> 2026-07-16 — análisis comparativo contra github.com/Damianf0/workbench-bot-whatsapp
> (instancia estable: 3 reinicios en 4 meses). Dato clave: su watchdog es un **port
> del nuestro** ("port crecer-bot" en el código) — el código de estabilidad es
> esencialmente el mismo. Lo que difiere es TODO lo de alrededor. Reemplaza la
> conclusión H5 de `EVALUACION-2026-07.md`: wwebjs no es inherentemente inestable;
> esta instalación lo es.

## Los 7 deltas, rankeados por sospecha

### D1 — El navegador (sospechoso principal)
| | crecer | workbench |
|---|---|---|
| Chromium | **Alpine Linux 147** (build comunitaria musl, `apk add chromium`) | **Chrome for Testing** empaquetado por puppeteer 24.38, nativo Windows |
| Pinneo | **flotante**: cada rebuild de la imagen toma lo que Alpine shippee ese día | pinneado por package-lock (mismo browser por 4 meses) |
| Compatibilidad | puppeteer 24.38 maneja un browser varias majors adelante de lo que fue testeado | el combo exacto que puppeteer certifica |

El browser de crecer es una build no oficial, sobre musl, de versión flotante y por
delante de lo que puppeteer/wwebjs esperan. Mapea directo a nuestra fauna de
incidentes: CDP timeouts, renderers zombie, `evaluate()` roto crónico (ovo).

### D2 — El pin de WhatsApp Web
| | crecer | workbench |
|---|---|---|
| Build | `2.3000.1041096482-alpha` (**alpha** del 09/06) | `2.3000.1042294900` (**estable**, 29/06 — 4 meses validada) |
| Cache | `type: 'remote'` → **fetch a GitHub en cada initialize** | `type: 'local'` → resuelve **offline** |

Corremos una alpha de hace 5+ semanas contra un servidor de WhatsApp que evoluciona
(drift creciente = comportamiento raro progresivo), y el boot depende de que
raw.githubusercontent.com responda — anoche, sin internet, cada reintento de
initialize arrancó por un fetch condenado.

### D3 — Cuánto lo tocan
| | crecer | workbench |
|---|---|---|
| Reinicio programado | **TODAS las noches** 02:30 (BackupFull: stop→tar→start ×3 bots) | nunca |
| Reiniciadores externos | 3 (watchdog interno, autoheal, WatchdogBot) | 0 |

365+ reinicios/año de base vs ~0. Nuestras 3 muertes de sesión de junio-julio
fueron corrupción local en reinicios; el cuelgue de hoy fue post-recreate. Cada
stop/start diario es una tirada de dados que workbench no tira. (Mitigado hoy:
anti-loop + breaker + pausa; el stop diario sigue.)

### D4 — Boot watchdog (ellos lo tienen, nosotros no)
workbench: si initialize no llega a `ready` (ni pide QR) en **4 min** → limpiar
caché de Chromium → reintentar. Solo. Crecer: el watchdog arranca recién en
`ready` → un cuelgue en initialize (como el "autenticada sin ready" de hoy, 25 min,
o las horas de `iniciando` de anoche) queda colgado PARA SIEMPRE esperando que un
actor externo lo patee. La ironía: es el fix exacto para el incidente de hoy, y es
código nuestro mejorado que hay que portar de vuelta.

### D5 — Carga entrante sobre la página
crecer: el bot es un server HTTP que recibe el tráfico de Laravel (poll del panel,
/media, /check-numero, avatares) + tarea diaria SyncAvatares vía wwebjs. workbench:
el bot **pollea él** al backend a su ritmo (nada entra a empujar la página), y el
avatar-sync por wwebjs fue **removido explícitamente** con este comentario: *"el
sync por-mensaje vía wwebjs estaba roto y **martillaba la página**"*. Nosotros
seguimos martillando (SyncAvatares diaria activa).

### D6 — Runtime
Docker + Alpine dentro de WSL2, 3 Chromiums compitiendo en 12 GB, con los quirks
documentados (port forwarding, logs congelados) vs Node nativo en Windows, 1
instancia. Factor ambiente real pero caro de cambiar; los D1-D5 se pueden cerrar
sin tocarlo.

### D7 — Volumen
~1.500-1.800 msgs/día (atención) vs decenas (incidencias IT). Ya establecido en la
evaluación: multiplica la clase "caché Chromium", no es causa raíz (ovo: mínimo
volumen, máxima inestabilidad).

## Plan de convergencia (de más barato a más caro)

1. **Pin de WA Web** → subir a la estable `2.3000.1042294900` (la misma que lleva
   4 meses validada en workbench) y cache `local` en volumen (sin dependencia de
   GitHub al boot). Es config: `WA_WEB_VERSION` ya existe como override; el type
   local requiere un cambio de 3 líneas.
2. **Portar el boot-watchdog** (BOOT_TIMEOUT 4-6 min → limpiar caché → reintentar;
   cancelado si aparece QR). Una función; cierra el modo de falla de hoy y el de
   anoche.
3. **Backup sin stop diario**: pasar el stop→tar→start a semanal (domingo 02:30);
   el resto de la semana, tar del `session-snapshot` existente (ya quiescente) sin
   tocar el bot. De 365 reinicios/año a 52.
4. **Imagen con browser bien pinneado**: probar en ovo `node:20-bookworm-slim` +
   Chrome for Testing que baja puppeteer (pinneado por lockfile) en vez del
   chromium flotante de Alpine. Si ovo mejora (es el peor caso hoy), rollear a los
   otros dos.
5. **Auditar la presión Laravel→bot**: qué golpea el panel al bot y a qué ritmo;
   evaluar retirar SyncAvatares vía wwebjs (workbench la quitó por dañina) o
   moverla a horario nocturno post-backup con rate limit.

## Qué explica cada incidente conocido

| Incidente | Delta |
|---|---|
| Caché CDP / envío colgado (recurrente) | D1 + D7 (mitigado por limpiezas) |
| Zombie pages 06-07/07 | D1 (+ D5 presión) |
| evaluate() roto crónico en ovo | D1 + D2 |
| Muertes de sesión 29/06-04/07 | D3 (reinicios diarios, apagado sucio) |
| Autenticada-sin-ready (hoy) | D4 (sin boot watchdog) + D3 (recreate) |
| `iniciando` eterno de anoche | corte de internet + D2 (fetch remoto) + D4 |
| LOGOUT ovo 12/07 | D3 (ráfaga de reinicios visible para Meta) |
| Loops de reinicio (01/07, 15/07, anoche) | gestión propia — corregido hoy |
