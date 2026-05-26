# Watchdog del tunnel-broker.
#
# Lo ejecuta una tarea programada cada 2 minutos. Si el broker no responde
# en 127.0.0.1:9091 lo levanta de nuevo en background. Idempotente.
#
# Check FUNCIONAL (no estructural): hace GET /status sin token. El broker
# responde 401 cuando esta vivo (porque falta token), o cualquier otro
# codigo HTTP. Si llega CUALQUIER respuesta HTTP -> broker vivo. Si no
# llega respuesta -> muerto, hay que relanzar.
#
# Por que NO chequear "puerto 9091 listening": HTTP.sys mantiene la urlacl
# reservada bajo PID=4 (System) aun despues de que el broker muere. El
# puerto sigue "LISTENING" pero nadie maneja las requests. Paso el 25/05.

$ErrorActionPreference = 'Continue'
$ProgressPreference    = 'SilentlyContinue'

$BrokerScript = 'C:\crecer\docker\tunnel-broker.ps1'
$LogFile      = 'C:\crecer\backups\auto\tunnel-watchdog.log'

function Write-Log {
    param([string]$Msg)
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Msg"
    Add-Content -Path $LogFile -Value $line -ErrorAction SilentlyContinue
}

# Probe funcional: si el broker responde HTTP (cualquier status) -> vivo.
function Test-BrokerAlive {
    try {
        $r = Invoke-WebRequest -Uri 'http://127.0.0.1:9091/status' `
                -TimeoutSec 3 -UseBasicParsing -ErrorAction Stop
        return $true
    } catch [System.Net.WebException] {
        # 401, 403, 404, etc tambien indican que algo del otro lado contesto.
        if ($_.Exception.Response) { return $true }
        return $false
    } catch {
        return $false
    }
}

if (Test-BrokerAlive) {
    # Vivo, salir silencioso. No loggeamos cada chequeo para no inflar el log.
    exit 0
}

Write-Log "Broker NO responde HTTP - relanzando"

# Matar restos: proceso powershell que estaba corriendo el broker y ngrok huerfano.
# El proceso broker viejo puede estar zombie con HTTP.sys ya liberado.
Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -match 'tunnel-broker\.ps1' } |
    ForEach-Object {
        Write-Log "Matando broker zombie PID=$($_.ProcessId)"
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
    }

Get-Process ngrok -ErrorAction SilentlyContinue | ForEach-Object {
    Write-Log "Matando ngrok huerfano PID=$($_.Id)"
    Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
}

Start-Sleep -Seconds 1

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
