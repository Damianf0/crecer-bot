# Crecer — arquitectura técnica

Plataforma operativa de la clínica Crecer (Mar del Plata). Centraliza la atención por WhatsApp, el seguimiento de pacientes, la coordinación interna entre operadoras y la gestión clínica básica (médicos, llamador, agenda).

Sistema en producción. Single-tenant. ~10-15 usuarios concurrentes en el panel + 3 números de WhatsApp activos.

---

## 1. Visión de alto nivel

```
                            ┌─────────────────────┐
                            │  Pacientes (cel WA) │
                            └──────────┬──────────┘
                                       │  mensajes
                                       ▼
              ┌────────────────────────────────────────────┐
              │  3 BOTS WhatsApp (Node.js, 1 número c/u)   │
              │  · atención (3001)                         │
              │  · administración (3002)  ← solo este en   │
              │  · ovodonación (3003)        Baileys hoy   │
              └──────────────┬──────────────┬──────────────┘
                             │ HTTP         │ HTTP
                             ▼              ▼
                        ┌──────────────────────────┐
                        │   Laravel 11 (PHP-FPM)   │
                        │   panel + API + jobs     │
                        └──┬────────┬────────┬─────┘
                           │        │        │
                       ┌───▼──┐  ┌──▼───┐ ┌──▼──────┐
                       │MySQL │  │Reverb│ │ Ollama  │
                       │ 8.0  │  │  WS  │ │qwen2.5:3b│
                       └──────┘  └──────┘ └─────────┘
                                                ▲
                       ┌──────┐                 │ HTTP
                       │Whisper│                │
                       │  ASR  │────────────────┘
                       └───────┘
                       (audio→texto)
                                       │
                                       ▼
                            ┌─────────────────────┐
                            │  Operadoras (panel) │
                            └─────────────────────┘
```

**Idea**: los bots WA reciben/envían mensajes, los persisten en Laravel/MySQL, las operadoras los ven y responden desde el panel web. Whisper transcribe audios entrantes a texto. Ollama clasifica intent y genera resúmenes con un LLM local. Reverb maneja realtime del chat interno entre operadoras.

---

## 2. Stack por capa

| Capa | Tecnología | Notas |
|---|---|---|
| Frontend principal | Blade (Laravel) + JS plano | Polling cada 6-8s; sin SPA |
| Frontend chat interno | React 18 + TypeScript + Laravel Echo | Único módulo realtime |
| Backend web | Laravel 11 + PHP-FPM 8.x | Auth con permisos por rol |
| Queue jobs | Laravel queue (`--queue=resumen`) | Procesa `GenerarResumenLLM` |
| WebSockets | Laravel Reverb (PHP-Workerman) | Solo lo usa el chat interno |
| Bots WhatsApp | Node.js + (whatsapp-web.js OR Baileys) | 4to bot "shadow" para testing |
| Transcripción audio | openai/whisper en container | Modelo `small`, faster-whisper |
| LLM local | Ollama en el host Windows | Modelo `qwen2.5:3b` (1.8 GB) |
| BD | MySQL 8 | Una sola DB (`clinica`) |
| Reverse proxy | nginx alpine | Solo terminación HTTP local |
| Acceso remoto | ngrok free tier + watchdog | Sólo testing, no crítico |

---

## 3. Containers (docker-compose)

| Servicio | Imagen / build | Puerto host | Función |
|---|---|---|---|
| `nginx` | `nginx:alpine` | 80 | Reverse proxy a `web` |
| `web` | build `docker/php` | — | Laravel + PHP-FPM 8.x |
| `mysql` | `mysql:8.0` | — | DB; `--binlog-expire-logs-seconds=259200` (3 días) |
| `reverb` | build `docker/php` | 8080 | `php artisan reverb:start` para WebSockets |
| `queue-worker` | build `docker/php` | — | `queue:work --queue=resumen --max-time=3600` (restart c/1h por diseño) |
| `bot` | build `docker/node` | 3001 | Bot WA atención (wwebjs hoy) |
| `bot-administracion` | build `docker/node` | 3002 | Bot WA administración (**Baileys** hoy) |
| `bot-ovodonacion` | build `docker/node` | 3003 | Bot WA ovodonación (wwebjs hoy) |
| `bot-test` | build `docker/node` | 3009 | Shadow para testing (Baileys, número personal del dev) |
| `whisper` | `onerahmet/openai-whisper-asr-webservice` | — | Transcripción audio entrante |
| `ollama` | `ollama/ollama` | — | **Exited; el Ollama productivo corre en el host Windows**, no acá |
| `warmup` | `curlimages/curl` | — | One-shot al start, prepara endpoints |

Todos en la red `clinica-net`. Volúmenes nombrados para `mysql-data`, `ollama-data` (no usado), y uno por sesión WA de cada bot (separados wwebjs y Baileys para permitir rollback).

---

## 4. Bots WhatsApp en detalle

Cada bot es un proceso Node.js independiente con su propio número, su propia sesión, y un endpoint HTTP que Laravel consume.

**Arquitectura interna del bot**:
```
bot/
├── server.js              Express: /status, /enviar, /enviar-archivo,
│                          /check-numero, /resolve-jid, /profile-pic,
│                          /pruebas/stream (SSE), /logs, /textos, /usuarios
├── clientes/
│   ├── cliente-wa.js      adapter común
│   ├── wwebjs.js          implementación whatsapp-web.js (Puppeteer)
│   └── baileys.js         implementación Baileys (WebSocket Signal)
├── mensajes.js            procesamiento de entrantes + clasificación Ollama
├── cola.js                derivación a colas internas
├── ollama.js              cliente HTTP de Ollama
├── whisper.js             cliente de Whisper
├── mensajesApi.js         persistencia hacia Laravel
└── watchdog.js            auto-reinicio si Puppeteer se cuelga (wwebjs)
```

**Auth HTTP**: header `Authorization: Bearer <BOT_INGRESS_TOKEN>` (en `bot/.env`). Fail-closed: sin token configurado, todo se deniega (503) salvo `/status`. El healthcheck `/status` es público pero **no incluye el QR** (solo `has_qr`); el QR sale por `/qr` con token — evita que cualquier dispositivo de la LAN capture el QR durante un pareo. `/media` (audios/imágenes de pacientes) también exige token: el panel lo consume via Laravel `/wa-media/{filename}` con auth de sesión (lee el bind mount `/bot-media`; `MensajeWA::archivo_url` reescribe las URLs históricas al leer).

**Modo prueba**: persistido en `bot/modo-prueba.<area>.json` (gitignored) — sobrevive reinicios del container. `true` = el bot clasifica y deriva pero NO autoresponde. Toggle desde `/admin/pruebas`.

**Selección del cliente WA**: env `BOT_WA_CLIENT=baileys` activa Baileys; sin ese env usa wwebjs (default).

**Diferencias prácticas wwebjs vs Baileys**:
| | wwebjs | Baileys |
|---|---|---|
| RAM | ~1.3 GB | ~60 MB |
| Tecnología | Chromium headless + Puppeteer | WebSocket directo a WA |
| Estabilidad | Cuelgues periódicos (watchdog) | Estable, pero issues de identity key tras re-pareo |
| QR | Una sola vez | Una sola vez (revoca si está mucho sin uso) |

**Estado actual**: administración con Baileys, atención y ovo con wwebjs (rollback por bug del 20/05 que después diagnosticamos como identity key change, no del fix de `getMessage` que aplicamos).

---

## 5. Flujo de un mensaje entrante

```
1. Paciente manda WhatsApp al número del bot (ej. atención 5492235997247)
2. Bot lo recibe en clientes/wwebjs.js o baileys.js
3. mensajes.js lo procesa:
   - Si es audio → llama a Whisper (HTTP, container `whisper`)
   - Llama a Ollama (qwen2.5:3b en el host) para clasificar:
     IGNORAR / TURNO_PRESERVACION / TURNO_DGP / FALLBACK / etc.
4. mensajesApi.js POST a Laravel /api/bot/mensaje con todo el payload
5. Laravel guarda en `mensajes_wa`, actualiza `conversaciones_wa`,
   actualiza `contactos` si hace falta
6. Si la clasificación dispara un evento (ej. derivación) → cola.js
   y/o Laravel encolan job `GenerarResumenLLM`
7. queue-worker lo procesa: pide a Ollama un resumen del hilo,
   lo guarda como nota interna
8. El panel atención hace polling cada 8s a /atencion/{area}/items
   → ve el mensaje nuevo
```

## 6. Flujo de un mensaje saliente

```
1. Operadora escribe en el panel y click "Enviar"
2. POST a /atencion/enviar con conversación + texto
3. Laravel POST a http://bot:3001/enviar (con Bearer token)
4. Bot llama a clientes/[wwebjs|baileys].sendText(jid, texto)
5. Mensaje sale por WhatsApp
6. Bot devuelve wa_id, Laravel guarda en `mensajes_wa`
7. Polling refresca el panel, operadora ve la confirmación
```

---

## 7. Tareas programadas (Windows Task Scheduler)

| Tarea | Cadencia | Qué hace |
|---|---|---|
| `WatchdogBot` | 5 min | Reinicia container del bot si lo ve unhealthy o el proceso colgado |
| `MapearWA` | 4:30 AM | `php artisan contactos:mapear-wa --limit=300 --max-errors=10` (resuelve `@lid` ↔ contactos) |
| `SyncAvatares` | diaria | Refresca fotos de perfil WA de contactos |
| `BackupMySQL` | diaria | Dump completo de la DB → Iperius lo sube a offsite |
| `CleanBotCache` | diaria | Limpia `.wwebjs_cache` (Chromium acumula GB sin esto) |
| `CrecerCleanWSLDumps` | 30 min | Borra `core.NN` dumps de WSL (1-2 GB c/u, se generan con cada Chromium crash) |
| `CrecerTunnelWatchdog` | 5 min | Revive el broker ngrok si murió |

---

## 8. Modelo de datos (tablas principales)

| Tabla | Filas aprox | Descripción |
|---|---|---|
| `contactos` | 25.000 | Pacientes y todo número conocido. Columna `wa_id` mapea @lid de Baileys |
| `mensajes_wa` | 25.000 | Mensajes entrantes y salientes con `tipo`, `wa_id`, `medio`, `transcripcion` |
| `conversaciones_wa` | 1.400 | Conversación = paciente + área. Estado abierto/cerrado, delegada, urgente |
| `conversacion_eventos` | 3.000 | Auditoría: derivaciones, notas internas, "reenviada", tomas, resoluciones |
| `documentos_paciente` | 1.000 | PDFs y archivos adjuntos al paciente |
| `derivaciones` | 200 | Casos derivados entre áreas con seguimiento |
| `tareas` | 18 | Tareas para médicos |
| `chat_canales`, `chat_canal_user`, `chat_mensajes` | varios | Chat interno entre operadoras (Equipo + DMs) |
| `cache`, `sessions`, `jobs`, `failed_jobs` | varios | Internos de Laravel |
| `users` | 12 | Operadoras + admin |

---

## 9. Acceso y autenticación

- **Panel web**: `http://192.168.1.125/` (red local) o `https://crazed-eggplant-unrated.ngrok-free.dev` (remoto, sólo testing). Login con usuario+password. Rate limit + lockout 5 intentos × 15 min. `PasswordSegura` valida ≥10 chars, sin diccionario.
- **API bot**: HTTP local entre containers, Bearer token (`BOT_INGRESS_TOKEN`).
- **MySQL**: solo desde la red `clinica-net`, sin puerto publicado al host.
- **Reverb**: 8080 publicado al host (para que el chat interno se conecte desde el browser).

---

## 10. Host y runtime

- **Hardware**: i7-8700K, 16 GB RAM, GTX 1660 4 GB, 232 GB SSD (~70% usado).
- **OS**: Windows 11 Pro.
- **Runtime**: Docker Desktop sobre WSL2 (vmmemWSL ocupa ~6 GB stable).
- **UPS**: comprada, pendiente de conectar.
- **Backup offsite**: Iperius respalda diariamente.
- **Observabilidad**: plataforma propia monitorea (no Prometheus/Grafana).
- **Túnel remoto**: ngrok free + watchdog. Sólo se usa para testing externo.

---

## 11. Decisiones de arquitectura relevantes

1. **Polling vs WebSockets**: el panel atención polléa cada 6-8s. El chat interno sí usa Reverb. La razón es histórica (Blade clásico, simplicidad). Migrar el panel a realtime sería refactor grande.
2. **3 bots separados, no uno multi-cuenta**: cada bot es un proceso independiente con su sesión. Facilita rollback, debugging, y aislar fallas. Cuesta más RAM.
3. **Bot atención todavía en wwebjs**: queda como deuda por el bug de identity key Baileys (ver más abajo).
4. **Shadow bot (`bot-test`)**: pareado con el número personal del dev. Sirve para validar Baileys sin tocar producción. `BOT_AREA=test` activa modo shadow (no persiste en BD).
5. **Ollama en el host, no en container**: aprovecha la GPU del host (GTX 1660). El container `ollama` existe pero está `Exited`.
6. **Healthchecks reportan `listo` sólo si `/status` devuelve `"status":"listo"`**: detecta zombies (HTTP responde OK pero el cliente WA está muerto).

---

## 12. Issues conocidos relevantes para entender el sistema

- **`wsl --shutdown` rompe port forwarding**: Docker reanuda containers pero los puertos publicados no responden hasta `docker compose restart`. Aplica también cuando WSL reinicia solo tras reboot inesperado del host.
- **Re-parear Baileys revoca sesiones de contactos viejos**: el primer mensaje del bot al receptor llega como "Esperando mensaje..." porque la identity key cambió y la sesión Signal cacheada no aplica. Workaround documentado pero no trivial.
- **wwebjs flaky**: el bot atención se reinicia cada 5-10 min cuando Chromium se cuelga. `WatchdogBot` lo maneja, pero genera ruido en métricas.
- **Reverb healthcheck usa `nc` que no está en la imagen**: reporta `unhealthy` aunque funcione. Falso positivo, sólo cosmético.
- **Ollama no se auto-recupera tras reboot del host**: el shortcut en Startup folder no siempre se ejecuta. Hay que verificar `Get-NetTCPConnection -LocalPort 11434` tras un reboot.

---

## 13. Repositorio y deploy

- Repo único `crecer/` en Git, branch `main`. Sin CI/CD: deploy = `docker compose up -d` desde el host.
- `app/` Laravel, `bot/` Node, `docker/` Dockerfiles, `scripts/` housekeeping PowerShell.
- `app/.env`, `bot/.env`, `.env` raíz (compose) — todos gitignored.
- `BRIEF-UX-2026-06-02.md` para discusiones de UX.
- `README-OPERATIVO.md` para troubleshooting día-a-día.
- `CLAUDE-clinica.md` para contexto histórico.

---

## 14. En un par de líneas

> Laravel monolito + 3 bots Node.js con su número WA cada uno, todo dockerizado en una workstation Windows que corre Docker sobre WSL2. Mensajes WA entran al bot, se procesan con Whisper (audio) + Ollama (clasificación), se persisten en MySQL, y se ven en un panel Blade con polling. Realtime sólo en el chat interno entre operadoras (Reverb). Acceso remoto opcional vía ngrok.
