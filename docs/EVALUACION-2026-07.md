# Evaluación general del sistema — julio 2026 (con datos)

> 2026-07-16 — respuesta a la pregunta: "¿los problemas vienen de otro lado, o del
> volumen? ¿hace falta rediseño general o empezar desde cero?". Fuentes: logs del
> watchdog (12/06→hoy), base de datos (63.720 mensajes), docker stats, .wslconfig,
> y la cronología de incidentes abril→julio.

## 1. Hallazgos

### H1 — El churn es constante y vive TODO en una sola capa: wwebjs/Chromium

- **Junio** (log viejo del watchdog): el bot de atención cayó a `desconectado`
  **4-8 veces por día, todos los días** — 138 incidentes "desconectado" en un mes,
  139 `docker restart` sobre atención solamente. Eso es lo NORMAL de wwebjs, no lo
  excepcional: el cliente se desconecta constantemente y se auto-recupera.
- **Anoche (15→16/07)**: los 3 bots en falla toda la noche durante el corte de
  internet — ovo 770 min, atención 330, admin 325. El watchdog ejecutó **~150
  reinicios sin efecto alguno**; todo volvió solo a las 08:00 cuando volvió la
  conectividad. (El fix offline-aware se desplegó hoy 10:30 — para la próxima.)
- **Mismo período, resto de la plataforma**: Laravel, MySQL, reverb, ollama, whisper,
  queue-worker: **cero incidentes de servicio**. (El "unhealthy" de reverb es un
  falso negativo cosmético del healthcheck.)

### H2 — La gestión actual no solo no ayuda: interfiere activamente

- Anoche: 150 reinicios inútiles (ver H1) — puro riesgo de sesión sin beneficio.
- **Hoy 11:00 y 11:05**: WatchdogBot reinició administración DOS VECES mientras se
  la estaba recuperando a mano (limpieza de caché 10:52 → sus restarts pisaron el
  initialize en curso → recién levantó cuando la restauración de snapshot cayó fuera
  de su ventana). La conclusión de la mañana ("la limpieza de caché no alcanzó")
  quedó contaminada por esta interferencia.
- **Defecto estructural detectado**: `RestartMinGap` (5 min) es MENOR que el tiempo
  de initialize de wwebjs en condiciones malas (3-6 min) → el watchdog garantiza
  loops: reinicia, el bot no llega a `listo` a tiempo, reinicia de nuevo.
- Históricos de la misma clase: loop de QR 6 horas (01/07), reinicios previos al
  LOGOUT de ovo (12/07), loop del corte (15/07).

### H3 — El volumen NO es la causa raíz

- Crecimiento mensual: abr 3.762 → may 18.987 → jun 31.152 → jul en curso ~31.000
  proyectado. **Meseta**, no explosión.
- Carga real: ~1.500-1.800 msgs/día hábil (85% atención). `mensajes_wa` pesa 24 MB
  (ínfimo). RAM WSL: 6 GB libres tras el ajuste a 12 GB. `bot/media` 2.9 GB (archivo
  histórico, no carga operativa).
- La correlación existe pero es parcial: atención (mayor volumen) acumula caché de
  Chromium más rápido → más incidentes clase "caché CDP". El volumen **multiplica**
  una clase de falla de wwebjs; no la crea. Contraejemplo definitivo: ovodonación,
  el área de MENOR volumen, tuvo los peores incidentes recientes (LOGOUT 12/07,
  churn crónico, 102 restarts en 3 días).

### H4 — No medimos lo que importa (el hallazgo más incómodo)

Del 09 al 12/07 la ingesta cayó a 20-60 msgs/día contra un baseline de ~1.500.
Resultó ser tráfico real (feriado 9 de Julio + puente + finde)… pero para
distinguir "feriado largo" de "sistema roto 4 días" hubo que razonar con el
calendario argentino a mano. **Ninguna capa mide el flujo end-to-end contra un
baseline.** Las páginas zombie del 06-07/07 (atención 21h y ovo 31h mudas con
/status diciendo `listo`) son el mismo agujero: todo lo que vigilamos son proxies
(proceso vivo, status, healthcheck) — nada vigila el hecho que le importa a la
clínica: *¿están fluyendo mensajes como se espera?*

### H5 — Atribución de causa raíz (todos los incidentes conocidos, abr→jul)

| Capa | Evidencia | Peso |
|---|---|---|
| **wwebjs/Chromium** (desconexiones, caché CDP, zombies, auth-sin-ready, corrupción de sesión, build WA mala) | 138 desconexiones/mes, todos los cuelgues, las 4 muertes de sesión | **~85% de los eventos** |
| **Gestión propia** (loops de QR/internet, gap<initialize, interferencia con operador, restarts pre-LOGOUT) | H2 | los 4-5 **peores desenlaces** |
| **WSL/Windows** (port forwarding, logs congelados, dumps, reinicios nocturnos) | fricción operativa semanal | molestia, no caídas |
| **Volumen** | multiplicador de la clase caché | secundario |
| **App Laravel/MySQL/panel** | cero incidentes propios (el "bombardeo CDP" de mayo fue Laravel *golpeando al bot* — otra vez la frontera wwebjs; corregido) | **~0** |

## 2. Respuestas directas

- **"¿Los problemas vienen de otro lado?"** No: vienen de UNA capa concreta y
  conocida (wwebjs/Chromium), con la gestión propia amplificando los peores casos.
  El resto de la plataforma está demostrablemente sana.
- **"¿Es el volumen?"** No como causa (H3); sí como multiplicador de la clase
  "caché". La meseta actual (~31k/mes) está lejos de estresar DB/Laravel/host.
- **"¿Rediseño general?"** El panel/plataforma NO lo necesita — cero incidentes,
  performance medida bien, V2 recién migrada con éxito. Rediseñar lo sano es tirar
  trabajo que funciona.
- **"¿Empezar desde cero?"** Depende QUÉ:
  - Reescribir *nuestro* código del bot manteniendo wwebjs → conserva el 85% del
    problema. No.
  - "Desde cero" = SaaS de inbox (Wati, SleekFlow, etc.) → son resellers de la misma
    Cloud API + un inbox que YA tenemos construido y mejor integrado (tareas, legajos,
    LLM local, chat interno). Además WORKBENCH vende esta plataforma: comprar el
    corazón la vacía. No.
  - "Desde cero" **en el canal**: WhatsApp Cloud API oficial → elimina la capa que
    genera el 85% de los eventos (sin Chromium, sin sesión local, sin QR). **Este es
    el único "desde cero" que la evidencia justifica.**

## 3. Consecuencia sobre la idea del orquestador

La evaluación **retira la propuesta del orquestador** (`ORQUESTADOR-DISENO.md` queda
como plan B archivado): construiría un excelente sistema de manejo para una capa que
hay que *reemplazar*, y el resto de la plataforma no lo necesita (cero incidentes que
gestionar). Era más del mismo concepto que ya venía fallando — la objeción era
correcta.

## 4. Plan que la evidencia sí sostiene

1. **Ya (hecho hoy, commits 5fce7ab + este):** offline-aware en healthcheck y
   watchdog + tres reglas anti-interferencia en el watchdog EXISTENTE (~20 líneas,
   sin piezas nuevas):
   - no reiniciar un container con menos de 10 min de vida (gap > tiempo de
     initialize — mata los loops);
   - circuit breaker: máx 2 restarts por bot por hora; el 3° es solo alerta;
   - modo mantenimiento (`watchdog-pause` flag) para que no pise recuperaciones
     manuales.
2. **Esta semana — medidor de flujo end-to-end (H4):** ingesta del día vs baseline
   del mismo día de semana; alerta si cae por debajo del 20% en día hábil. Es la
   métrica que faltó en TODOS los incidentes silenciosos. Una query + una tarea.
3. **El movimiento principal — piloto Cloud API** (sube de "futuro" a prioridad):
   verificación Meta + coexistence en ovodonación (menor volumen, peor historial).
   Si valida → migrar atención, que es el 85% del problema. Cada área migrada
   retira sus watchdogs para siempre.
4. **Mientras tanto, wwebjs queda como está** (con los fixes de hoy): estabilizado
   "suficiente", sin más inversión — es la capa saliente.

## 5. Qué NO hacer (decisiones negativas explícitas)

- No construir el orquestador (plan B si Cloud API fracasa).
- No reescribir el bot ni el panel.
- No migrar de host/OS por ahora (la fricción WSL es molestia, no caídas; se
  reevalúa si el piloto Cloud API exige ingress que convenga hostear afuera).
- No comprar SaaS de inbox.
