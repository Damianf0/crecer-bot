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
# Flujo por bot:
#   1. Stop → clean cache → start
#   2. Poll /status hasta 90s buscando status==listo && phone!=null
#   3. Si no llega: SEGUNDO intento (stop → clean → start → poll 90s)
#   4. Si tampoco: marcar ERROR en el log con el status final para diagnóstico
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

$cleanCmd = 'cd /data/session/Default 2>/dev/null && rm -rf Cache "Code Cache" GPUCache DawnGraphiteCache DawnWebGPUCache "Service Worker/CacheStorage" "Service Worker/ScriptCache"'

# Hace stop → clean cache → start sobre un bot. Devuelve el tamaño antes/después.
function Invoke-CleanCache($Bot) {
    $antes = (cmd /c "docker run --rm -v $($Bot.Vol):/data alpine du -sh /data/session 2>nul").Trim()
    cmd /c "docker stop $($Bot.Ctr) > nul 2>&1"
    cmd /c "docker run --rm -v $($Bot.Vol):/data alpine sh -c `"$cleanCmd`" > nul 2>&1"
    $despues = (cmd /c "docker run --rm -v $($Bot.Vol):/data alpine du -sh /data/session 2>nul").Trim()
    cmd /c "docker start $($Bot.Ctr) > nul 2>&1"
    return @{ Antes = $antes; Despues = $despues }
}

# Pollea /status hasta que el bot reporta status==listo && phone!=null. Devuelve hashtable con
# Ready (bool), Phone, Status, ElapsedSec y LastBody (para diagnóstico).
function Wait-BotListo($Bot, [int]$TimeoutSec = 90, [int]$PollSec = 4) {
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $lastBody = '(sin respuesta)'
    $status   = '(desconocido)'
    $phone    = $null

    while ($sw.Elapsed.TotalSeconds -lt $TimeoutSec) {
        try {
            $r = Invoke-WebRequest -Uri "http://localhost:$($Bot.Port)/status" `
                                   -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
            $lastBody = $r.Content
            $j = $r.Content | ConvertFrom-Json
            $status = $j.status
            $phone  = $j.phone
            if ($status -eq 'listo' -and $phone) {
                return @{
                    Ready      = $true
                    Phone      = $phone
                    Status     = $status
                    ElapsedSec = [int]$sw.Elapsed.TotalSeconds
                    LastBody   = $lastBody
                }
            }
        } catch {
            $lastBody = "(error: $($_.Exception.Message))"
        }
        Start-Sleep -Seconds $PollSec
    }
    return @{
        Ready      = $false
        Phone      = $phone
        Status     = $status
        ElapsedSec = [int]$sw.Elapsed.TotalSeconds
        LastBody   = $lastBody
    }
}

Log "================== INICIO limpieza cache bots =================="

$resumen = @()

foreach ($b in $Bots) {
    Log "--- $($b.Ctr) ---"

    # Primer intento
    $sizes = Invoke-CleanCache $b
    Log ("  Intento 1: cache {0} -> {1}" -f $sizes.Antes, $sizes.Despues)
    Log "  Esperando que reconecte (hasta 180s)..."
    $check = Wait-BotListo -Bot $b -TimeoutSec 180 -PollSec 4

    if ($check.Ready) {
        Log ("  OK: listo en {0}s, phone={1}" -f $check.ElapsedSec, $check.Phone)
        $resumen += @{ Ctr = $b.Ctr; Estado = "OK"; Detalle = "listo en $($check.ElapsedSec)s ($($check.Phone))" }
        continue
    }

    Log ("  Primer intento NO levanto (status='{0}' phone='{1}'). Reintentando..." -f $check.Status, $check.Phone)

    # Segundo intento
    $sizes2 = Invoke-CleanCache $b
    Log ("  Intento 2: cache {0} -> {1}" -f $sizes2.Antes, $sizes2.Despues)
    Log "  Esperando que reconecte (hasta 180s)..."
    $check2 = Wait-BotListo -Bot $b -TimeoutSec 180 -PollSec 4

    if ($check2.Ready) {
        Log ("  OK (al 2do intento): listo en {0}s, phone={1}" -f $check2.ElapsedSec, $check2.Phone)
        $resumen += @{ Ctr = $b.Ctr; Estado = "OK_2INT"; Detalle = "listo al 2do intento en $($check2.ElapsedSec)s ($($check2.Phone))" }
        continue
    }

    Log ("  ERROR: $($b.Ctr) sigue sin levantar despues de 2 intentos. status='{0}' phone='{1}'" -f $check2.Status, $check2.Phone)
    Log ("  Ultima respuesta /status: $($check2.LastBody)")
    $resumen += @{ Ctr = $b.Ctr; Estado = "ERROR"; Detalle = "status='$($check2.Status)' phone='$($check2.Phone)'" }
}

# Resumen final claro para que cualquier observador vea el resultado de un vistazo
$okCount  = ($resumen | Where-Object { $_.Estado -like "OK*" }).Count
$errCount = ($resumen | Where-Object { $_.Estado -eq "ERROR" }).Count

Log ""
Log "------------------ RESUMEN ------------------"
foreach ($r in $resumen) {
    $tag = if ($r.Estado -eq "ERROR") { "[ERROR]" } elseif ($r.Estado -eq "OK_2INT") { "[OK*]" } else { "[OK]  " }
    Log ("{0} {1,-32} {2}" -f $tag, $r.Ctr, $r.Detalle)
}
Log ("Resultado final: {0}/{1} bots listos, {2} con ERROR" -f $okCount, $Bots.Count, $errCount)

# Rotar log si pasa de 1 MB
if ((Test-Path $LogFile) -and (Get-Item $LogFile).Length -gt 1MB) {
    Move-Item $LogFile "$LogFile.old" -Force
    Log "Log rotado"
}

Log "================== FIN limpieza =================="

# Exit code útil para el scheduler de Windows: 0 si todo OK, 1 si hay errores.
# La plataforma de observabilidad puede leer el último log o el exit code.
if ($errCount -gt 0) { exit 1 } else { exit 0 }
