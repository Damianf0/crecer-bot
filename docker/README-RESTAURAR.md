# Recuperación de la plataforma Crecer desde este backup

Esta carpeta contiene TODO lo necesario para reconstruir la plataforma en una
máquina nueva (Windows + Docker Desktop, o Linux con Docker). Generada por
`C:\crecer\docker\backup-full.ps1` (diaria 02:30).

## Contenido

| Carpeta | Qué es |
|---|---|
| `mysql/` | Dump completo de la DB `clinica` (3 más recientes, `.sql.gz`) |
| `sesiones-wa/` | Sesiones WhatsApp de los 3 bots (tar del volumen, + `.prev` del día anterior) |
| `media/` | Archivos de mensajes WA de pacientes (`bot/media`) |
| `storage/` | `app/storage` de Laravel (documentos de pacientes, logs) |
| `config/` | `root.env`, `app.env`, `bot.env`, `docker-compose.yml` — **⚠ contienen credenciales** |
| `repo/crecer.bundle` | Historia git completa del código (alternativa: github.com/Damianf0/crecer-bot) |

## Restauración completa (máquina nueva)

1. **Código**: `git clone crecer.bundle C:\crecer` (o clonar de GitHub y `git pull` del bundle).
2. **Config**: copiar `config/root.env → C:\crecer\.env`, `config/app.env → C:\crecer\app\.env`,
   `config/bot.env → C:\crecer\bot\.env`.
3. **Levantar solo MySQL**: `docker compose up -d mysql`, esperar healthy.
4. **DB**: descomprimir el dump más nuevo y cargarlo:
   `cmd /c "docker exec -i crecer-mysql-1 sh -c ""exec mysql -uroot -p<DB_ROOT_PASSWORD> clinica"" < clinica-full-XXXX.sql"`
   (el `.gz` se descomprime antes, p.ej. con 7-Zip o `gzip -d`).
5. **Sesiones WA** (evita re-escanear QR): con los bots SIN arrancar, por cada área:
   ```
   docker volume create crecer_wa-session
   docker run --rm -v crecer_wa-session:/data -v <esta_carpeta>\sesiones-wa:/backup alpine sh -c "cd /data && tar xzf /backup/wa-session-atencion.tar.gz"
   ```
   (ídem `crecer_wa-session-administracion` y `crecer_wa-session-ovodonacion`).
   Si una sesión restaurada no autentica (WhatsApp la puede invalidar tras mucho
   tiempo offline), el bot cae a `esperando_qr`: escanear desde `/admin` con el
   celular del área.
6. **Media/storage**: copiar `media/ → C:\crecer\bot\media` y `storage/ → C:\crecer\app\storage`
   (recrear `app\storage\framework\{cache,sessions,views}` vacíos si Laravel se queja).
7. **Stack completo**: `docker compose up -d`. Ollama descarga `qwen3:4b` solo
   (`docker exec crecer-ollama-1 ollama pull qwen3:4b` si no).
8. Verificar: `/status` de los puertos 3001/3002/3003 → `listo`, web en `:80`.

## Restauraciones parciales frecuentes

- **Solo una sesión WA corrupta**: paso 5 de esa área sola (parar el bot antes,
  borrar y recrear el volumen, restaurar, arrancar).
- **Solo la DB**: pasos 3-4.
- **Un archivo de paciente borrado**: buscarlo directo en `media/` o `storage/`.

## Notas

- Los tar de sesión NO incluyen el cache de Chromium (regenerable; el bot lo
  reconstruye al arrancar).
- Las tareas programadas de Windows (backup, watchdog, clean-cache) se recrean
  con los `schtasks` documentados en el encabezado de cada script en `C:\crecer\docker\*.ps1`.
- Esta carpeta contiene credenciales (`config/`) y datos de pacientes: el
  directorio compartido de destino debe tener acceso restringido.
