# Limpieza nocturna del cache de Chromium de los bots WhatsApp — Crecer
# Borra solo cache (Cache, Code Cache, GPUCache, Service Worker/CacheStorage, etc.)
# preservando IndexedDB / Local Storage / Cookies / Login Data → la sesión WA NO se pierde.
#
# Causa: WhatsApp Web acumula cache muy rápido (Code Cache de V8 sobre todo). Cuando se hincha,
# las operaciones CDP en Chromium se cuelgan ("Runtime.callFunctionOn timed out") → sendMessage /
# check-numero / getState timeoutean. Ver project_bug_envio_bot.md.
# (El bot también tiene un watchdog que limpia el cache y reinicia si detecta el cuelgue — ver
#  bot/whatsapp.js — pero esta limpieza nocturna es la medida proactiva.)
#
# Cubre los 3 bots por área: bot (atención), bot-administracion, bot-ovodonacion.
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

# Cada bot: { Vol = volumen docker de la sesión WA ; Ctr = nombre del contenedor ; Port = puerto HTTP }
$Bots = @(
    @{ Vol = 'crecer_wa-session';                Ctr = 'crecer-bot-1';                Port = 3001 },
    @{ Vol = 'crecer_wa-session-administracion'; Ctr = 'crecer-bot-administracion-1'; Port = 3002 },
    @{ Vol = 'crecer_wa-session-ovodonacion';    Ctr = 'crecer-bot-ovodonacion-1';    Port = 3003 }
)

$cleanCmd = 'cd /data/session/Default 2>/dev/null && rm -rf Cache "Code Cache" GPUCache DawnGraphiteCache DawnWebGPUCache "Service Worker/CacheStorage" "Service Worker/ScriptCache"; du -sh /data/session 2>/dev/null'
$tmpErr = [System.IO.Path]::GetTempFileName()

Log "=== Inicio limpieza cache bots ==="

foreach ($b in $Bots) {
    Log "--- $($b.Ctr) (vol $($b.Vol)) ---"
    $antes = cmd /c "docker run --rm -v $($b.Vol):/data alpine du -sh /data/session 2>$tmpErr"
    Log "  Antes: $antes"
    cmd /c "docker stop $($b.Ctr) > nul 2>&1"
    $despues = cmd /c "docker run --rm -v $($b.Vol):/data alpine sh -c `"$cleanCmd`" 2>$tmpErr"
    Log "  Despues: $despues"
    cmd /c "docker start $($b.Ctr) > nul 2>&1"
}

Remove-Item $tmpErr -ErrorAction SilentlyContinue

# Esperar a que reconecten y verificar status
Start-Sleep -Seconds 40
foreach ($b in $Bots) {
    try {
        $r = Invoke-WebRequest -Uri "http://localhost:$($b.Port)/status" -UseBasicParsing -TimeoutSec 10
        Log "  $($b.Ctr) status: $($r.Content)"
    } catch {
        Log "  ATENCION: $($b.Ctr) no responde post-reinicio: $($_.Exception.Message)"
    }
}

# Rotar log si pasa de 1 MB
if ((Test-Path $LogFile) -and (Get-Item $LogFile).Length -gt 1MB) {
    Move-Item $LogFile "$LogFile.old" -Force
    Log "Log rotado"
}

Log "=== Fin limpieza ==="
