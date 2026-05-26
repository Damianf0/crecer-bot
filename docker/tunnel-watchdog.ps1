# Watchdog del tunnel-broker.
#
# Lo ejecuta una tarea programada cada 2 minutos. Si el broker no esta
# escuchando en 127.0.0.1:9091 lo levanta de nuevo en background. Idempotente:
# si el broker ya esta vivo no hace nada.

$ErrorActionPreference = 'Continue'
$ProgressPreference    = 'SilentlyContinue'

$BrokerScript = 'C:\crecer\docker\tunnel-broker.ps1'
$LogFile      = 'C:\crecer\backups\auto\tunnel-watchdog.log'

function Write-Log {
    param([string]$Msg)
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Msg"
    Add-Content -Path $LogFile -Value $line -ErrorAction SilentlyContinue
}

$listening = Get-NetTCPConnection -LocalPort 9091 -State Listen -ErrorAction SilentlyContinue
if ($listening) {
    # Broker vivo, salir silenciosamente (no loggeamos cada chequeo para no
    # inflar el log con miles de lineas "OK" que despues nadie lee).
    exit 0
}

Write-Log "Broker NO escucha en 9091 - relanzando"

# Matar cualquier ngrok huerfano antes de relanzar el broker.
Get-Process ngrok -ErrorAction SilentlyContinue | ForEach-Object {
    Write-Log "Matando ngrok huerfano PID=$($_.Id)"
    Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
}

try {
    Start-Process powershell `
        -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-File',$BrokerScript `
        -WindowStyle Hidden
    Write-Log "Broker relanzado en background"
} catch {
    $msg = $_.Exception.Message
    Write-Log "ERROR relanzando broker: $msg"
    exit 1
}
