# Plataforma Operativa Crecer — Guía operativa

> Documentación consolidada al **2026-04-29** (última actualización mayor 2026-05-19: migración a Baileys).
> Para contexto histórico y propósito del proyecto: `CLAUDE-clinica.md`.

---

## 1. Stack y arquitectura

### Containers Docker (`docker-compose.yml`)

| Servicio | Imagen | Puerto host | Función |
|---|---|---|---|
| `nginx` | nginx:alpine | 80 | Reverse proxy a PHP-FPM |
| `web` | php:8.2-fpm + OPcache + JIT | — | Laravel 11, app principal |
| `mysql` | mysql:8.0 | (cerrado) | DB `clinica`, accesible solo dentro de la red Docker |
| `bot`, `bot-administracion`, `bot-ovodonacion` | node:20-alpine + Chromium + git + tzdata | 3001/3002/3003 | Bot WhatsApp (Baileys WebSocket por default; whatsapp-web.js + Chromium queda como fallback). Selección por env `BOT_WA_CLIENT=baileys\|wwebjs` |
| `whisper` | onerahmet/openai-whisper-asr-webservice | — | Transcripción de audios WA (modelo `small`, faster_whisper, español) |
| `ollama` | ollama/ollama (GPU NVIDIA) | 11434 | LLM local para clasificación (qwen3:4b) |

Todos con `restart: unless-stopped` y healthchecks reales (wget/curl al endpoint de cada servicio).

### Volúmenes persistentes

- `crecer_mysql-data` — datos MySQL.
- `crecer_wa-baileys-atencion` / `wa-baileys-administracion` / `wa-baileys-ovodonacion` — sesiones Baileys activas de los 3 bots. **Crítico**: si se borran hay que escanear QR de nuevo desde el cel del área.
- `crecer_wa-session` / `wa-session-administracion` / `wa-session-ovodonacion` — sesiones whatsapp-web.js legacy. Preservadas como fallback. Si querés hacer rollback de un bot a wwebjs, comentás `BOT_WA_CLIENT=baileys` en su servicio y la sesión sigue viva. Cuando la estabilización con Baileys quede confirmada, estos volúmenes se borran (Fase 10 del plan migración).
- `crecer_wa-baileys-test` — sesión del container `bot-test` (shadow para testing con número personal). Sólo se levanta on-demand con `docker compose up -d bot-test`.
- `crecer_ollama-data` — modelos LLM descargados.
- Bind mounts: `./app` → `/var/www/html`, `./bot` → `/app`, `./docker/nginx/default.conf` → nginx config.

### Performance

- **OPcache validate_timestamps=0 + JIT tracing + preload** (~2500 archivos al arrancar).
- **Cualquier cambio PHP/Blade requiere `docker restart crecer-web-1`** para que OPcache lo tome. `view:clear` solo no alcanza.
- **`CACHE_STORE=database`** (no `file`). Razón: bind mount NTFS + Docker Desktop Windows ignoran `chown`, los directorios creados por root quedaban inalcanzables para www-data.

### Timezone

`America/Argentina/Buenos_Aires` aplicado en `web`, `mysql` y `bot` (este último necesita `tzdata` instalado en su Dockerfile, no alcanza con `TZ=` env).

---

## 2. Mapa de URLs

### Para todas las secretarias

| Ruta | Función |
|---|---|
| `/login` | Login |
| `/declarar-colas` | Selección de colas activas al iniciar turno |
| `/secretaria` | Cola de recepción (pacientes en sala) |
| `/atencion` | **Gestión unificada WhatsApp**: nuevas, en proceso, panel conv, filtros (Todas/Urgentes/Mías) |
| `/mis-tareas` | Mis conversaciones asignadas + tareas |
| `/contactos` | Directorio: búsqueda paginada, alta/edición, importación CSV Omnia, "Chatear" en cada fila |
| `/historial` | Conversaciones archivadas con paginación |
| `/tablet` | Pantalla de check-in (pública, sin auth) |

### Solo con `permiso:admin` (rol `tecnico` y `supervisora` por default)

| Ruta | Función |
|---|---|
| `/admin` | Estado del bot WA en vivo (status, QR, uptime, número) |
| `/admin/textos` | Editor de respuestas automáticas (`textos.json`). Aplica al instante. |
| `/admin/pruebas` | Toggle modo prueba + stream SSE de clasificaciones |
| `/admin/logs` | Logs en vivo del bot (SSE proxied) |
| `/admin/usuarios` | CRUD de usuarios + permisos efectivos |

### Endpoints internos (no UI)

- `GET /bot-pulso` — estado del bot para badge del navbar (cualquier usuario).
- `POST /atencion/iniciar` — inicia conversación nueva con un contacto o número.
- `POST /atencion/conversacion/{id}/agregar-contacto` — alta de contacto desde una conv huérfana.
- `GET /chat/*` — chat interno entre usuarios (Equipo + DMs).

### Bot (puerto 3001, requiere Bearer)

| Endpoint | Función |
|---|---|
| `GET  /status` | Estado (público, lo usa healthcheck y badge navbar) |
| `POST /enviar` | Mandar texto a un contacto WA |
| `POST /enviar-archivo` | Mandar archivo (mimetype whitelisteado) |
| `POST /check-numero` | Verificar si un número está en WA + obtener JID real |
| `POST /resolve-jid` | Dado un `@lid`, resolver al número telefónico real |
| `GET  /logs` (SSE) | Stream de logs |
| `GET  /pruebas/stream` (SSE) | Clasificaciones en vivo |
| `POST /textos` | Reescribir `textos.json` |

Endpoints administrativos (config, textos, usuarios, modo prueba) requieren `BOT_INGRESS_TOKEN` en el header.

---

## 3. Roles y permisos

| Rol | Permisos por default |
|---|---|
| `secretaria` | secretaria, atencion, contactos |
| `supervisora` | secretaria, atencion, contactos, agenda, historial, **admin** |
| `admin` (rol) | secretaria, atencion, contactos, agenda, historial, admin |
| `tecnico` | todos los anteriores + admin |

Los permisos se sobrescriben individualmente desde `/admin/usuarios` (campo `permisos` JSON en `users`). Si está NULL, aplica el default del rol.

---

## 4. Operaciones rutinarias

### Aplicar cambios de código

```bash
# Cambios PHP/Blade
docker exec crecer-web-1 php /var/www/html/artisan config:clear
docker exec crecer-web-1 php /var/www/html/artisan view:clear
docker restart crecer-web-1
# Esperar ~10s — puede dar 502 transitorio mientras FPM hace preload

# Cambios bot/server.js o whatsapp.js
docker restart crecer-bot-1
# Espera ~30s, reconecta sesión WA sin pedir QR
```

**Antes de hacer restart**, validar sintaxis si tocaste código:
```bash
docker exec crecer-web-1 php -l /var/www/html/app/...
docker exec crecer-bot-1 node -c /app/server.js
```
Un parse error en un controller con OPcache preload tumba el container — queda en `Exited`.

### Backups

#### Automático (programado)
- **Tarea Windows `Crecer\BackupMySQL`** — diaria 03:00 AM. Script `C:\crecer\docker\backup-mysql.ps1`.
- Output: `C:\crecer\backups\auto\{daily,weekly,monthly}\` con retención 7/4/12.

#### Manual (antes de un cambio grande)
```powershell
$ts = Get-Date -Format "yyyyMMdd-HHmmss"
$bk = "C:\crecer\backups\$ts"
mkdir "$bk\db", "$bk\volumes" -Force

# DB
docker exec crecer-mysql-1 sh -c 'mysqldump --single-transaction -uroot -p${DB_ROOT_PASSWORD} clinica' > "$bk\db\clinica.sql"

# Volumen sesión WA (si vas a tocar el bot a fondo)
docker run --rm -v crecer_wa-session:/data -v "$bk\volumes:/backup" alpine tar czf /backup/wa-session.tar.gz -C /data .
```

### Limpieza automática de cache del bot (whatsapp-web.js solamente)

- **Tarea Windows `Crecer\CleanBotCache`** — diaria 04:00 AM. Script `C:\crecer\docker\clean-bot-cache.ps1`.
- **Aplica solo a bots con `BOT_WA_CLIENT=wwebjs`.** Baileys no usa Chromium así que el script no hace nada útil sobre volúmenes Baileys.
- Borra Cache, Code Cache, GPUCache, Service Worker/CacheStorage del Chromium del bot **sin tocar IndexedDB ni Cookies** (mantiene la sesión WA).
- Cuando los 3 bots queden estables en Baileys (Fase 10 del plan migración), eliminar esta tarea programada y el script.

### Stack WhatsApp: Baileys vs whatsapp-web.js

Desde 2026-05-19 los 3 bots de prod corren con **Baileys** (WebSocket directo al protocolo Multi-Device de WhatsApp), seleccionado por env `BOT_WA_CLIENT=baileys` en `docker-compose.yml`. La implementación wwebjs queda como fallback.

| Backend | RAM/bot | Chromium | Reconexión | Cuelgues |
|---|---|---|---|---|
| wwebjs (legacy) | 400-1300 MB | Sí, requiere `apk add chromium` | Vía watchdog + matar Chromium | Frecuentes en sesión grande |
| **Baileys (activo)** | **40-80 MB** | **No** | **Automática (515 Stream Errored se resuelve solo)** | **Raros** |

**Rollback de un bot a wwebjs** (si Baileys falla con algún destinatario o feature):
1. Editar `docker-compose.yml`, comentar `BOT_WA_CLIENT=baileys` del servicio del bot
2. `docker compose up -d <servicio>` — el bot arranca con wwebjs usando el volumen `wa-session-*` que se preservó intacto
3. La sesión wwebjs sigue viva en su volumen — no requiere QR de nuevo (salvo que haya sido invalidada por la operadora desde el cel)

**Reactivar Baileys** después de rollback: descomentar el env y `docker compose up -d <servicio>`. El volumen `wa-baileys-*` sigue ahí.

### Container shadow `bot-test`

Servicio definido pero **detenido por default** en `docker-compose.yml`. Sirve para validar el adapter Baileys con un número personal antes de tocar prod (puerto 3009, `BOT_AREA=test`, `MODO_SHADOW` activo → no escribe en Laravel/BD).

- Levantar: `docker compose up -d bot-test`
- Ver QR para escanear: `powershell -File C:\crecer\scripts\show-qr-test.ps1` (abre/refresca `C:\crecer\qr-shadow.png`)
- Detener: `docker compose stop bot-test`
- Volumen `wa-baileys-test` persiste entre arranques.

### Mapeo de contactos / WhatsApp

- **`contactos:mapear-wa`** — para cada contacto sin `wa_id`, consulta al bot y guarda el JID real (`@c.us` o `@lid`). Después vincula conversaciones huérfanas (las que llegaron como `@lid` y no se vincularon con un contacto).
  - `--solo-contactos` o `--solo-conversaciones` para correr una sola fase.
  - **`--limit=N`** acota la cantidad procesada por corrida. **`--max-errors=N`** (default 10) aborta si hay N timeouts seguidos = bot caído.
  - El cron diario (Crecer\MapearWA, 4:30 AM) pasa `--limit=300 --max-errors=10` desde 2026-05-19 para evitar correr +6 hs y bombardear el bot durante horario laboral (incidente del 19/05).
  - Throttle 150ms entre llamadas al bot.
- **`contactos:auditar-telefonos`** — clasifica los contactos sin `wa_id` en `sin_telefono`, `formato_invalido`, `no_es_whatsapp`. Reintenta resolver mientras audita.
  - `--csv=/var/www/html/storage/logs/audit.csv` exporta lista detallada.

```bash
docker exec -d crecer-web-1 php /var/www/html/artisan contactos:mapear-wa > /var/www/html/storage/logs/mapear-wa.log 2>&1
docker exec    crecer-web-1 php /var/www/html/artisan contactos:auditar-telefonos --csv=/var/www/html/storage/logs/audit.csv
```

### Acceso a MySQL

El puerto 3306 está cerrado al host por seguridad. Acceso administrativo:
```bash
docker exec -it crecer-mysql-1 mysql -ucrecer -p${DB_PASSWORD} clinica
```

---

## 5. Troubleshooting

### "500 Server Error" / `Permission denied` en cache
**Síntoma:** logs de Laravel muestran `file_put_contents(.../cache/data/...): Permission denied`.
**Causa:** `CACHE_STORE=file` con bind mount NTFS. Docker Desktop Windows ignora `chown`.
**Fix:** confirmar `CACHE_STORE=database` en `app/.env`. `php artisan config:clear` + restart.

### Bot conectado pero `sendMessage` timeoutea (whatsapp-web.js solamente)
**Aplica a:** bots corriendo con `BOT_WA_CLIENT=wwebjs`. Baileys no usa CDP así que este síntoma no existe.
**Síntoma:** logs `Runtime.callFunctionOn timed out`. `/status` devuelve `listo` pero los envíos fallan.
**Causa:** cache de Chromium acumulado satura las operaciones CDP de Puppeteer.
**Fix:** correr el script de limpieza manualmente:
```powershell
docker stop crecer-bot-1
docker run --rm -v crecer_wa-session:/data alpine sh -c 'cd /data/session/Default && rm -rf Cache "Code Cache" GPUCache DawnGraphiteCache DawnWebGPUCache "Service Worker/CacheStorage" "Service Worker/ScriptCache"'
docker start crecer-bot-1
```
Si ya está la tarea programada `Crecer\CleanBotCache` activa, esto pasa solo cada noche.

### Watchdog del bot reinicia el cliente (whatsapp-web.js solamente)
**Aplica a:** bots con `BOT_WA_CLIENT=wwebjs`. Baileys maneja reconexión a nivel WebSocket, sin watchdog.
**Síntoma:** logs `[watchdog] Cliente colgado — reiniciando WhatsApp...`.
**Estado actual:** defaults conservadores en `bot/clientes/wwebjs.js` (5min/3/20min: 15 min sin CONNECTED para matar). Si querés ajustar para un área, env overrides `WATCHDOG_INTERVAL`/`WATCHDOG_MAX_SIN_CONNECTED`/`WATCHDOG_TIMEOUT` en el servicio del compose.

### Mensajes salientes desde Baileys llegan como "Esperando mensaje..."
**Aplica a:** bots con `BOT_WA_CLIENT=baileys`.
**Síntoma:** `sendText`/`sendMedia` devuelven `wa_id` válido pero el destinatario ve "Esperando mensaje..." en el chat.
**Causa:** el destinatario tiene Lid mode y el adapter no usa su `@lid` (envía a `@s.whatsapp.net`).
**Estado actual:** ya corregido en `bot/clientes/baileys.js` — `resolverJidEnvio()` y `checkNumber()` leen `info.lid` con prioridad. Si volvés a verlo, probablemente sea un caso nuevo de cifrado E2E roto en el receptor; mirar logs por `[baileys] onWhatsApp(...) → @lid` para confirmar que estamos usando el JID correcto.

### Audio del bot Baileys llega como archivo en vez de nota de voz
**Aplica a:** bots con `BOT_WA_CLIENT=baileys`.
**Estado actual:** ya corregido — los .ogg se envían con `mimetype: 'audio/ogg; codecs=opus'` + `ptt: true`. Si tu integración envía mp3/mp4 va a llegar como audio file normal (no PTT) — comportamiento esperado.

### 502 Bad Gateway tras restart del web
Normal: PHP-FPM hace preload de ~2500 archivos al arrancar. Esperar 8-15 segundos.

### Modal de "Textos" no carga ni edita
Era un bug de Blade ya resuelto: `{{ '{{...}}' }}` → `@{{...}}`. Si volvés a ver vista admin que no carga, mirar `php artisan tinker --execute="view('admin.X')->render()"` para ver el error de parse de Blade.

### Conversación de WA aparece sin nombre aunque el paciente está en agenda
Hoy WhatsApp identifica usuarios con dos formatos:
- `549...@c.us` — derivado del número (formato legacy)
- `XXX@lid` — Linked Device ID, anónimo

`contactos.wa_id` guarda el JID real. Si la conv tiene `@lid` y el contacto no tiene `wa_id` resuelto, no matchea. Solución: correr `contactos:mapear-wa` o usar el botón **"+ Agregar contacto"** del panel de conv (alta + vinculación en un click).

---

## 6. Seguridad

### Tokens

- **`BOT_INGRESS_TOKEN`** (Laravel ↔ bot, 256 bits): `app/.env` + `bot/.env` + `panel/preload.js`. Si rota, actualizar los 3 lugares + restart bot y web.
- **`BOT_TOKEN`** (bot ↔ Laravel): `config/app.php` lo usa el middleware `BotTokenAuth`.

### Auth en endpoints del bot

`server.js` exige Bearer en todos excepto `/status` y `/media/*` (públicos para healthchecks y URLs en mensajes WA). EventSource (logs, pruebas/stream) acepta `?token=` por query (browser no manda headers en SSE).

### CORS

Whitelist en `bot/.env` → `ALLOWED_ORIGINS=http://localhost,http://192.168.1.125,http://nginx`. No `*`.

### Mimetypes en uploads

`AtencionController::MIMETYPES_PERMITIDOS` define qué se acepta. `EXTENSIONES_BLOQUEADAS` (exe, bat, php, etc.) bloquea aunque el mimetype haya pasado.

### Confirmaciones destructivas

- "Resolver" conversación: confirm() antes de archivar.
- "Logout": confirm() antes de cerrar sesión.

---

## 7. Mantenimiento programado

| Tarea Windows | Frecuencia | Hora | Script |
|---|---|---|---|
| `Crecer\BackupMySQL` | Diaria | 03:00 | `C:\crecer\docker\backup-mysql.ps1` |
| `Crecer\CleanBotCache` | Diaria | 04:00 | `C:\crecer\docker\clean-bot-cache.ps1` |
| `Crecer\MapearWA` | Diaria | 04:30 | `C:\crecer\docker\mapear-wa.ps1` |
| `Crecer\SyncAvatares` | Diaria | 05:00 | `C:\crecer\docker\sync-avatares.ps1` |

Verificar:
```powershell
schtasks /Query /TN "Crecer\BackupMySQL" /V /FO LIST
schtasks /Query /TN "Crecer\CleanBotCache" /V /FO LIST
schtasks /Query /TN "Crecer\MapearWA" /V /FO LIST
schtasks /Query /TN "Crecer\SyncAvatares" /V /FO LIST
```

**Estado en vivo:** el dashboard `/admin` (sección "Tareas programadas") muestra
cuándo corrió cada una y si están al día. Lee los artefactos en `C:\crecer\backups\auto\`
montados read-only en el container web.

---

## 8. Estado de los datos (snapshot 2026-04-29)

- **Contactos en directorio**: 9346
  - Con `wa_id` resuelto: ~7297 (78%, todos `@lid`)
  - Sin resolver: ~2049 (correr `contactos:auditar-telefonos` para clasificar)
- **Conversaciones WA**: 318+ activas (317 nuevas, 1 en proceso al cierre de la sesión)
- **Conversaciones huérfanas remanentes**: ~53 (`@lid` sin match en directorio)
- **Usuarios activos**: Soporte, Laura, Melisa, Jazmin

---

## 9. Performance — números actuales

| Endpoint | Latencia | Observación |
|---|---|---|
| `/atencion/items` cache miss | ~450 ms | Una vez cada 3s |
| `/atencion/items` cache hit | ~20 ms | El resto |
| `/atencion/items` ETag 304 | ~13 ms · 0 bytes | Cuando no hay cambios reales |
| `/contactos/data` (sin query) | ~420 ms · 27 KB | 100 filas de 9346 |
| `/contactos/data?q=...` | ~30 ms · varía | LIKE en columna indexada |
| `/atencion/conversacion/{id}` | ~30 ms | Últimos 100 mensajes |
| `/bot-pulso` | ~50 ms | Proxy al bot |

Polling actual: `/atencion/items` cada 8s, `/bot-pulso` cada 15s, `/chat/no-leidos` cada 6s.

---

## 10. Pendientes conocidos

- **Etapa 3.4** — Streaming uploads en `enviarArchivo` (sacar `base64` en memoria). Riesgo medio, beneficio chico.
- **Etapa 4** — Reverb/WebSockets para reemplazar polling. Recomendado cuando lleguen a 5+ secretarias simultáneas.
- **Etapa 5.6** — Auditoría de paleta visual `--accent` vs `--error`. Polish UI.
- **Etapa 6** — Compliance:
  - Política de contraseñas + lockout
  - 2FA opcional para roles con acceso a datos sensibles
  - Cifrado en reposo de `storage/wa-media` y backups
  - Export "Mis datos" (Habeas Data Ley 25.326 AR)
  - Auditoría de lecturas/exports
- **Soft-cleanup Electron** — quitar tabs ya migradas a `/admin` después de 1-2 semanas de uso real con la web.

---

## 11. Estructura del repositorio

```
C:\crecer\
├── app\                    # Laravel 11 (bind mount → /var/www/html)
│   ├── app\Http\Controllers\
│   │   ├── AtencionController.php   # Cola, conversaciones, iniciar, agregar contacto
│   │   ├── BotController.php        # Webhooks del bot (mensaje entrante, derivar)
│   │   ├── ContactoController.php   # Directorio + import CSV
│   │   ├── AdminController.php      # Panel admin web (proxy al bot)
│   │   └── ChatController.php       # Chat interno Equipo + DMs
│   ├── app\Models\
│   ├── app\Livewire\                # Tablet, ColaSecretaria, Login, etc.
│   ├── resources\views\
│   │   ├── atencion\                # index, mis-tareas, historial
│   │   ├── contactos\
│   │   ├── admin\                   # dashboard, textos, pruebas, logs, usuarios
│   │   └── chat\_widget.blade.php   # Widget reusable de chat interno
│   ├── routes\web.php, api.php, console.php
│   └── database\migrations\
├── bot\                    # Node + whatsapp-web.js (bind mount → /app)
│   ├── server.js           # HTTP API (Express)
│   ├── whatsapp.js         # Cliente WA (Puppeteer + watchdog)
│   ├── ollama.js           # Clasificación LLM
│   ├── mensajes.js         # Acumulador + procesamiento
│   ├── textos.json         # Respuestas automáticas (editable desde /admin/textos)
│   └── .env                # BOT_INGRESS_TOKEN, ALLOWED_ORIGINS, OLLAMA_URL, etc.
├── panel\                  # Electron (gestión local de Docker/Ollama)
├── docker\
│   ├── nginx\default.conf
│   ├── php\Dockerfile + opcache.ini + www.conf
│   ├── node\Dockerfile     # Con tzdata aplicado
│   ├── backup-mysql.ps1    # Backup automático
│   └── clean-bot-cache.ps1 # Limpieza de cache Chromium
├── backups\
│   ├── 20260427-100037\    # Backup manual pre-hardening
│   └── auto\               # Rotación automática (daily/weekly/monthly)
├── docker-compose.yml
├── CLAUDE-clinica.md       # Contexto histórico y propósito del proyecto
└── README-OPERATIVO.md     # Este archivo
```

---

## 12. Quien quiera entender más

- **Memoria del agente**: `C:\Users\usuario\.claude\projects\C--crecer\memory\` — un archivo `project_*.md` por feature mayor con detalle de implementación, decisiones y comandos específicos.
- **Manual del bot para usuarios**: `C:\crecer\manual.html` — onboarding para secretarias.
- **Brochure comercial**: `C:\atencion-bot\brochure-manual.html` — versión comercial del producto (repo light separado).
