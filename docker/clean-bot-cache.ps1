# Limpieza nocturna del cache de Chromium del bot — Crecer
# Borra solo cache (Cache, Code Cache, GPUCache, Service Worker/CacheStorage, etc.)
# preservando IndexedDB / Local Storage / Cookies / Login Data → la sesión WA NO se pierde.
#
# Causa: WhatsApp Web acumula cache muy rápido. Si pasa de ~800 MB, sendMessage timeoutea
# por saturación de operaciones CDP en Chromium. Ver project_bug_envio_bot.md
#
# Programado en Windows:
#   schtasks /Create /SC DAILY /ST 04:00 /TN "Crecer\CleanBotCache" `
#     /TR "powershell -NoProfile -ExecutionPolicy Bypass -File C:\crecer\docker\clean-bot-cache.ps1" /F /RU SYSTEM

$LogFile = 'C:\crecer\backups\auto\clean-cache.log'
New-Item -ItemType Directory -Path (Split-Path $LogFile) -Force | Out-Null

function Log($msg) {
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $msg"
    Write-Output $line
    Add-Content -Path $LogFile -Value $line
}

Log "=== Inicio limpieza cache bot ==="

# Tamaño antes
$tmpErr = [System.IO.Path]::GetTempFileName()
$antes = cmd /c "docker run --rm -v crecer_wa-session:/data alpine du -sh /data/session/Default 2>$tmpErr"
Log "Antes: $antes"

# Detener bot, limpiar, arrancar
Log "Deteniendo crecer-bot-1..."
cmd /c "docker stop crecer-bot-1 > nul 2>&1"

Log "Borrando cache..."
$cleanCmd = 'cd /data/session/Default && rm -rf Cache "Code Cache" GPUCache DawnGraphiteCache DawnWebGPUCache "Service Worker/CacheStorage" "Service Worker/ScriptCache"'
cmd /c "docker run --rm -v crecer_wa-session:/data alpine sh -c `"$cleanCmd`" 2>$tmpErr"

Log "Arrancando crecer-bot-1..."
cmd /c "docker start crecer-bot-1 > nul 2>&1"

# Tamaño después
$despues = cmd /c "docker run --rm -v crecer_wa-session:/data alpine du -sh /data/session/Default 2>$tmpErr"
Log "Despues: $despues"

Remove-Item $tmpErr -ErrorAction SilentlyContinue

# Esperar a que el bot reconecte y verificar status
Start-Sleep -Seconds 35
try {
    $r = Invoke-WebRequest -Uri http://localhost:3001/status -UseBasicParsing -TimeoutSec 10
    Log "Status post-reinicio: $($r.Content)"
} catch {
    Log "ATENCION: bot no responde post-reinicio: $($_.Exception.Message)"
}

# Rotar log si pasa de 1 MB
if ((Test-Path $LogFile) -and (Get-Item $LogFile).Length -gt 1MB) {
    Move-Item $LogFile "$LogFile.old" -Force
    Log "Log rotado"
}

Log "=== Fin limpieza ==="
