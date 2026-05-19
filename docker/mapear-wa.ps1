# Sincronización diaria del wa_id de contactos sin resolver — Crecer
# Resuelve el JID real (@c.us o @lid) llamando al bot por cada teléfono
# normalizado. Se corre antes que sync-avatares para que esa tarea pueda
# tomar los avatares de los recién resueltos.
#
# Programado en Windows:
#   schtasks /Create /SC DAILY /ST 04:30 /TN "Crecer\MapearWA" `
#     /TR "powershell -NoProfile -ExecutionPolicy Bypass -File C:\crecer\docker\mapear-wa.ps1" /F

$LogFile = 'C:\crecer\backups\auto\mapear-wa.log'
New-Item -ItemType Directory -Path (Split-Path $LogFile) -Force | Out-Null

function Log($msg) {
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $msg"
    Write-Output $line
    Add-Content -Path $LogFile -Value $line
}

Log "=== Inicio mapear-wa ==="

$tmpErr = [System.IO.Path]::GetTempFileName()
# --limit=300 + --max-errors=10: corrida acotada para no pisar horario laboral
# si hay backlog grande, y abortar si el bot atención está colgado (evita el
# círculo vicioso del 19/05 donde el mapeo bombardeaba el bot y lo colgaba).
# Si quedan pendientes, los toma la corrida del día siguiente.
$out = cmd /c "docker exec crecer-web-1 php //var/www/html/artisan contactos:mapear-wa --limit=300 --max-errors=10 2>$tmpErr"
$out | ForEach-Object { Log $_ }

$err = Get-Content $tmpErr -Raw -ErrorAction SilentlyContinue
if ($err) { Log "stderr: $err" }
Remove-Item $tmpErr -ErrorAction SilentlyContinue

# Rotación de log si pasa de 1 MB
if ((Test-Path $LogFile) -and (Get-Item $LogFile).Length -gt 1MB) {
    Move-Item $LogFile "$LogFile.old" -Force
    Log "Log rotado"
}

Log "=== Fin mapear-wa ==="
