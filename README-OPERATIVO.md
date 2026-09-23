# Plataforma Operativa Crecer — Guía operativa

Chuleta para operar el sistema y para atender un incidente. Revisada el 23/09/2026.
La referencia completa (funcionalidad, arquitectura, backups, historia, roadmap) es
[`docs/DOCUMENTACION-GENERAL.md`](docs/DOCUMENTACION-GENERAL.md); el restore paso a paso,
[`docker/README-RESTAURAR.md`](docker/README-RESTAURAR.md).

> Esta guía reemplaza a la versión de mayo, que describía Baileys como backend de
> WhatsApp y la interfaz V1. Baileys se abandonó el 16/06 (hoy solo queda un experimento
> apagado por default) y la V1 se retiró el 03/08.

---

## 1. Qué corre

| Servicio | Puerto host | Qué es |
|---|---|---|
| `nginx` | 80 | Entrada web → PHP-FPM |
| `web` | — | Laravel 12 (PHP 8.2 + OPcache sin revalidación) |
| `reverb` | 8080 | WebSocket: chat interno, colas WA y recepción en tiempo real |
| `queue-worker` | — | Cola `resumen` (resúmenes LLM); se recicla solo cada hora |
| `mysql` | — | Base `clinica`, solo red interna |
| `bot` | 3001 | WhatsApp **Atención** (el número de cada área lo muestra su `/status`) |
| `bot-administracion` | 3002 | WhatsApp **Administración** |
| `bot-ovodonacion` | 3003 | WhatsApp **Ovodonación** |
| `bot-baileys-test` | 3009 | Experimento Baileys (profile `baileys`, no arranca con `up -d`, no escribe en la base) |
| `autoheal` | — | Reinicia contenedores `unhealthy` con label `autoheal=true` |
| `whisper` | — | Transcripción de audios |
| `ollama` | 11434 | LLM local (qwen2.5:3b en GPU): clasificación y resúmenes |

Los 3 bots son whatsapp-web.js sobre Chrome for Testing 146 pinneado
(`docker/node-chrome`). Comparten código (`./bot`) y `bot/.env`; el área y el puerto
salen del compose.

**Volúmenes que importan**: `mysql-data`, `wa-session` (atención),
`wa-session-administracion`, `wa-session-ovodonacion`. Borrar un `wa-session*` = volver
a escanear el QR con el celular de esa área.

## 2. Ante un incidente

1. **¿Los bots están bien?** `http://localhost:3001/status` (y 3002, 3003), sin token.
   `listo` = bien · `esperando_qr` = hay que escanear · `iniciando` = esperar (hasta 5-7 min).
2. **No diagnosticar con `docker logs`**: se congela tras eventos de WSL. Los bots
   escriben a `bot/logs/bot-<area>.log`, pero desde Windows ese archivo puede verse
   atrasado: leerlo desde adentro, `docker exec crecer-bot-1 tail -n 80 /app/logs/bot-atencion.log`.
3. **Watchdog**: `backups/auto/watchdog.log` y `watchdog-state.json`. Si vas a operar a
   mano, crear `backups/auto/watchdog-pause` (observa pero no reinicia) y borrarlo al terminar.
4. **`esperando_qr`**: no reiniciar (solo regenera el QR). Escanear desde `/v2/admin` con
   el celular del área (WhatsApp → Dispositivos vinculados).
5. **Sin internet en el host**: no reiniciar nada; los bots se reconectan solos.
6. **Error 500 en la web**: `app/storage/logs/laravel-AAAA-MM-DD.log`. Si el error es
   `could not be opened in append mode`, un archivo quedó con dueño root:
   `docker exec crecer-web-1 chown -R www-data:www-data /var/www/html/storage`.
7. **Después de un `wsl --shutdown`** los puertos publicados quedan mudos:
   `docker compose restart nginx reverb bot bot-administracion bot-ovodonacion`.

## 3. Aplicar cambios

| Qué cambió | Qué hacer |
|---|---|
| PHP / Blade / config | `docker compose restart web queue-worker` (502 unos segundos: OPcache no revalida) |
| JS / CSS de `public/` | Nada (van con `?v=filemtime`) |
| `resources/js` (chat React) | `npm run build` en un contenedor `node:20-alpine` |
| Código del bot o `bot/.env` | Reiniciar el bot, **con el celular del área a mano**. Si nadie lo hace, lo toma el ciclo del domingo 02:30 |
| `docker-compose.yml` / `.env` raíz | `docker compose up -d <servicio>` |

Antes de reiniciar: `docker exec crecer-web-1 php -l <archivo>`, `node --check <archivo>`
y `docker exec -u www-data crecer-web-1 php artisan test` (SQLite en memoria y log nulo:
no toca la base ni el log de producción).

**Todo `artisan` por `docker exec` va con `-u www-data`** (y `-e HOME=/tmp` para
`tinker`). Como root, los archivos que crea (el log del día, avatares, carpetas del
legajo) quedan inescribibles para la web: así se perdieron 84 mensajes entre julio y agosto.

## 4. Tareas programadas (Windows)

| Tarea | Cuándo | Qué hace |
|---|---|---|
| `Crecer\BackupFull` | 02:30 | Backup total a `backups/full/`; los domingos reinicia cada bot (~1 min c/u) |
| `Crecer\BackupMySQL` | 03:00 | Dump con retención 7 diarios / 4 semanales / 12 mensuales |
| `Crecer\SyncOmnia` | 04:00 | Contactos nuevos desde Omnia + catálogo del checklist |
| `Crecer\MapearWA` | 04:30 | Resuelve `wa_id` de hasta 300 contactos |
| `Crecer\SyncAvatares` | domingos 05:00 | Fotos de perfil vencidas (tope 500) |
| `Crecer\WatchdogBot` | cada 5 min | Vigila los 3 bots, reinicia con freno, avisa por WhatsApp (y por mail cuando esté configurado) |
| `CrecerTunnelWatchdog` | cada 2 min | Revive el broker del túnel ngrok (acceso remoto de soporte) |
| `CrecerCleanWSLDumps` | cada 30 min | Borra dumps de crash de WSL |
| `Crecer\CleanBotCache` | deshabilitada | Reemplazada por el ciclo del domingo |

El tablero de `/v2/admin` muestra si cada una corrió a tiempo.

## 5. Comandos frecuentes

```powershell
# Omnia: ¿responde?  / sync en seco con la ventana nocturna
docker exec -u www-data crecer-web-1 php artisan omnia:status
docker exec -u www-data crecer-web-1 php artisan contactos:sync-omnia --desde=2026-09-13 --hasta=2026-11-22

# Archivar conversaciones inactivas (dry-run; --apply ejecuta y deja un lote deshacible en /admin/archivar)
docker exec -u www-data crecer-web-1 php artisan conversaciones:archivar-inactivas --dias=7

# Jobs fallidos (cola resumen)
docker exec -u www-data crecer-web-1 php artisan queue:failed
docker exec -u www-data crecer-web-1 php artisan queue:retry all

# MySQL (el 3306 no está publicado)
docker exec -it crecer-mysql-1 mysql -ucrecer -p clinica
```

**Recuperar mensajes de un período sin bot** (`/backfill` del bot, idempotente por
`wa_id`, con la fecha real): `POST http://localhost:300X/backfill` con Bearer
`BOT_INGRESS_TOKEN` y `{"desde":"2026-09-14T00:00:00-03:00","dryRun":true}`; seguir con
`GET /backfill/estado`. Pesa sobre el bot: una área por vez y fuera de hora pico.

## 6. Versión de WhatsApp Web

Está pineada (`WA_WEB_VERSION` en `bot/clientes/wwebjs.js`) y cada versión dura unos
3 meses. Una sesión ya vinculada sigue andando con una versión vencida; lo que falla es
el re-pareo y posiblemente el reinicio. El watchdog chequea una vez por día que la
configurada siga vigente. Para actualizar: bajar el HTML con
`curl -sL -o bot/.wwebjs_cache/<VER>.html https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/<VER>.html`,
cambiar la versión y reiniciar los bots de a uno, con los celulares a mano.

## 7. Trampas conocidas

- **OPcache**: un cambio PHP no existe hasta reiniciar `web`. Un error de sintaxis en un
  controller tumba el contenedor al reiniciar (queda `Exited`).
- **`CACHE_STORE=database`**, nunca `file`: el bind mount de Windows no respeta `chown`.
- **Nunca llamar al bot en bucle desde Laravel**: cada llamada es un `evaluate` en
  Chromium y una ráfaga lo cuelga (incidente 19/05). Todo lo masivo va con `--limit`.
- **Los grupos de WhatsApp entran a la cola** (ovo usa sus grupos internos desde el
  panel). El filtro que parecía excluirlos nunca funcionó; sacarlos es decisión de producto.
- **`navigator.clipboard` no existe en el panel** (se sirve por HTTP, no es contexto
  seguro): copiar al portapapeles necesita el fallback con `textarea`.
- **Scripts `.ps1` con acentos**: UTF-8 **con BOM**, o PowerShell 5.1 los lee mal.
- **Los `.env` no están en git**: se respaldan cada noche en `backups/full/config/`.
