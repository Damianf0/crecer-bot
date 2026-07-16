# Orquestador — diseño (cerebro + plugins)

> 2026-07-16 — continuación de `REPLANTEO-GESTION-BOTS.md`: acordado explorar un
> **cerebro principal que coordina y decide**, con todo lo demás como **plugins**.
> Cierra un ciclo histórico: el panel Electron original *veía*; `/admin` web lo
> reemplazó como ventana; el orquestador es la pieza que faltó siempre — **el que
> actúa**, con `/admin` como su cara.

## 1. La idea en una frase

Un servicio chico (container `orquestador`) que cada N segundos **observa** todo lo
que corre, **diagnostica** contra el catálogo de fallas conocidas, **decide** según
políticas centrales (jerarquía de activos, circuit breakers, hechos de entorno) y
**actúa o alerta** — dejando TODO en un journal. Cada cosa observada es un plugin
que declara sus sondas, sus firmas de falla y sus remediaciones; el core es genérico
y no sabe qué es un bot ni una base de datos.

```
                    ┌─────────────────────────────────────────┐
                    │              ORQUESTADOR                │
                    │                                         │
  hechos de entorno │  ┌────────┐  ┌──────────┐  ┌─────────┐  │
  (internet, docker,│  │ motor  │  │ políticas│  │ journal │  │
  disco, RAM, WSL)──┼─▶│ sondas │─▶│ + decide │─▶│ SQLite  │  │
                    │  └────────┘  └────┬─────┘  └─────────┘  │
                    │                   │ actúa/alerta        │
                    └───────────────────┼─────────────────────┘
                          ┌─────────────┼───────────────┐
                     docker.sock   HTTP a bots      notificador
                     (restart,     (/status,        (WA via bots +
                      exec)        /accion/*)        fallback)
        ┌──────────┬──────────┬─────┴────┬─────────┬──────────┐
        │ plugin   │ plugin   │ plugin   │ plugin  │ plugin   │
        │ bot-wa×3 │ mysql    │ reverb   │ ollama  │ backups  │ ...
        └──────────┴──────────┴──────────┴─────────┴──────────┘
```

## 2. El core (lo único que decide)

- **Motor de sondas**: corre `observar()` de cada plugin en su intervalo; timeouts
  duros; nunca lo tumba un plugin colgado.
- **Hechos de entorno de primera clase**: internet (TCP a web.whatsapp.com:443 — la
  lección del 15/07 generalizada), docker vivo, disco, RAM WSL, port-forwarding
  post-`wsl --shutdown`. Los plugins y las políticas los consultan; la regla
  "sin internet → ninguna acción de red" vive UNA vez, en el core.
- **Políticas centrales** (inviolables por los plugins):
  - Jerarquía de activos: acciones clase `sesion` **nunca** se ejecutan solas — solo
    alertan. Clase `datos` requiere firma de falla conocida. Clase `proceso` es libre
    dentro del circuit breaker.
  - Circuit breaker: máx 2 acciones sobre el mismo objeto por hora; la 3ª es alerta.
  - Cooldown post-acción: verificar resultado antes de considerar el paso siguiente
    de la escalera (nada de ráfagas).
  - Amnesia prohibida: toda decisión consulta la historia del journal (¿esta firma es
    crónica? ¿ya intenté esto hoy?).
- **Journal** (SQLite en volumen propio — deliberadamente NO MySQL: el observador no
  puede depender de lo observado): transiciones de estado, hechos, diagnósticos,
  acciones con resultado, alertas. Append-only, consultable por API.
- **API HTTP** (interna): estados actuales, timeline, disparo manual de acciones
  (con la misma política de riesgo). `/admin` la consume vía proxy Laravel
  (`permiso:admin`) — misma mecánica que el `bot-pulso` existente.
- **Notificador**: WA vía los 3 bots en orden (mecánica actual de watchdog-bot.ps1) +
  reintento hasta entrega + badge en /admin. Canal independiente de fallback: abierto
  (ver §7).

## 3. Contrato de plugin

```js
// Un plugin = un objeto observado. Declarativo: el core ejecuta, el plugin describe.
module.exports = {
  id: 'bot-wa:atencion',            // tipo:instancia
  intervalo: 30_000,

  // 1. OBSERVAR — junta hechos crudos, no opina
  async observar(ctx) {
    return { fase: 'listo', phone: '549...', last_msg_at, sonda_browser: 'ok', ... };
  },

  // 2. DIAGNOSTICAR — hechos + historia + entorno → firma del catálogo (o null=sano,
  //    o 'desconocida'=alertar sin actuar)
  diagnosticar(hechos, historia, entorno) {
    if (hechos.fase === 'esperando_qr' && historia.markerValido) return 'qr_con_sesion_valida';
    if (hechos.fase === 'autenticado' && historia.minutosEnFase > 10) return 'autenticada_sin_ready';
    ...
  },

  // 3. ACCIONES — cada una declara su clase de riesgo; el core decide si corre
  acciones: {
    restart_container:  { riesgo: 'proceso', run: (ctx) => ctx.docker.restart('crecer-bot-1') },
    limpiar_cache:      { riesgo: 'proceso', run: ... },   // stop→rm caches→start
    restaurar_snapshot: { riesgo: 'datos',   run: ... },   // validada 16/07
    restaurar_tar:      { riesgo: 'datos',   run: ... },   // playbook 05/07
    escanear_qr:        { riesgo: 'sesion',  run: null },  // humana: solo alerta con instrucciones
  },

  // 4. ESCALERAS — firma → secuencia de acciones (el core verifica entre pasos)
  escaleras: {
    pagina_zombie:          ['restart_container'],
    cache_cdp:              ['limpiar_cache'],
    autenticada_sin_ready:  ['limpiar_cache', 'restaurar_snapshot'],   // lo del 16/07, encodeado
    qr_con_sesion_valida:   ['restaurar_snapshot', 'restaurar_tar', 'escanear_qr'],
    logout_definitivo:      ['escanear_qr'],
    desconocida:            [],                                        // = solo alertar
  },
};
```

## 4. Plugins iniciales

| Plugin | Sondas | Acciones (riesgo) | Nota |
|---|---|---|---|
| `bot-wa` ×3 | /status enriquecido, edad del último mensaje vs DB | escalera completa (proceso/datos/sesion) | el 90% del valor |
| `mysql` | ping, conexiones, tamaño binlogs | restart (proceso) | trivial |
| `reverb` | fsockopen 8080 | restart (proceso) | mata el falso negativo actual |
| `ollama` | /api/tags, VRAM | restart (proceso) | |
| `whisper` / `nginx` / `queue-worker` | http/proceso | restart (proceso) | |
| `tunnel` | broker + URL pública | restart broker (proceso) | absorbe tunnel-watchdog |
| `backups` | ¿corrió anoche? ¿tamaños razonables? ¿hay .prev? | — (solo alerta) | verificación que hoy no existe |
| `entorno` | internet, disco, RAM WSL, port-forwarding | compose restart puertos (proceso) | absorbe la clase WSL |

Los bots necesitan una adaptación menor: el watchdog interno v3 **deja de reiniciar**
y pasa a ser sondas puras expuestas en `/status` (browser, página, inactividad) +
endpoints de acción (`/accion/reiniciar-cliente`, `/accion/limpiar-cache`) con el
token existente. La lógica ya está escrita — se reacomoda, no se reescribe.

## 5. Qué absorbe / qué muere

| Pieza actual | Destino |
|---|---|
| autoheal | **muere** (lo reemplaza el core + plugin bot-wa) |
| watchdog-bot.ps1 | **se reduce a meta-watchdog** (~20 líneas: "¿Docker vivo? ¿orquestador vivo? → levantar y alertar"; tarea programada cada 5 min) |
| watchdog interno v3 | sondas puras + endpoints de acción (decide el orquestador) |
| healthchecks de compose | quedan informativos (`docker ps` legible); ya no disparan reinicios |
| tunnel-watchdog.ps1 | plugin `tunnel` |
| verificación implícita de backups | plugin `backups` (hoy nadie verifica que BackupFull haya corrido bien) |
| snapshot/restore en wwebjs.js | el mecanismo queda en el bot; el **disparo** pasa al orquestador |

El orquestador es un SPOF nuevo, y está bien que lo sea: si muere, **nada reinicia
nada** = exactamente el modo degradado que acordamos tolerable (los celulares siguen
vivos), y el meta-watchdog host lo detecta en ≤5 min. Fail-safe por diseño: su muerte
no puede romper nada porque no hay acción pendiente que quede "a medias" sin journal.

## 6. Fases

- **F1 — Core + journal + modo sombra** (1-2 sesiones): core genérico, plugin bot-wa
  solo-observación. Corre en paralelo a lo actual UNA SEMANA journaleando qué *habría*
  hecho; se compara contra lo que autoheal/WatchdogBot hicieron de verdad. Cero riesgo.
- **F2 — Cutover bots** (1 sesión): se activan las escaleras; muere autoheal;
  WatchdogBot → meta-watchdog; watchdog interno → sondas. El tag de rollback es
  volver a subir autoheal (una línea de compose).
- **F3 — Plugins triviales** (1 sesión): mysql, reverb, ollama, tunnel, backups, entorno.
- **F4 — /admin** (1 sesión): timeline del journal, estado por plugin, botones de
  acción manual (riesgo `sesion` incluido: ahí SÍ es humano decidiendo).
- **F5 — futuro**: adapter `cloudapi` como nuevo tipo de plugin de canal; las áreas
  migradas usan un plugin sin clase `sesion` (no hay nada que perder) — el orquestador
  no cambia, se le simplifica un plugin.

## 7. Decisiones abiertas

1. **Canal de alerta independiente**: hoy las alertas van por WA usando los propios
   bots (circular: si caen los 3, no avisa nadie — el meta-watchdog host cubriría ese
   caso). ¿Sumamos Telegram (gratis, API trivial, independiente de WhatsApp) o
   alcanza con WA-vía-bots + meta-watchdog?
2. **Dónde corren las acciones docker**: docker.sock montado en el orquestador
   (estándar, es lo que hace autoheal hoy) — implica confiarle el socket. Alternativa
   más contenida: un helper mínimo en el host. Propuesta: socket, con journal como
   auditoría.
3. **Nombre**: `orquestador` como servicio compose; ¿o le ponemos nombre propio?
4. **Duración del modo sombra**: propongo 7 días o 3 incidentes capturados, lo que
   pase primero.
