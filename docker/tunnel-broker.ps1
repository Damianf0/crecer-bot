# Broker HTTP local para gestionar el túnel ngrok.
#
# Expone HTTP en 127.0.0.1:9091 (loopback puro, no accesible desde la red) con
# tres endpoints autenticados por Bearer token:
#
#   GET  /status -> { running, url, started_at, uptime_seconds, log_tail[], backend }
#   POST /start  -> arranca ngrok, espera la URL pública, devuelve el mismo objeto que /status.
#   POST /stop   -> mata el proceso de ngrok y borra el estado.
#
# El authtoken de ngrok se lee de bot/.env (NGROK_AUTHTOKEN). La URL pública
# es estática (dominio reservado de la cuenta ngrok), así que no cambia entre reinicios.
#
# AL ARRANCAR el broker mata cualquier ngrok huérfano y levanta el túnel solo, así
# después de un reinicio del host (o del broker) el acceso remoto vuelve sin intervención.
#
# Se arranca como shortcut en Startup de Windows (CrecerTunnelBroker.lnk). El proceso
# de ngrok es hijo de este broker. El estado vive en backups/auto/tunnel-state.json.
#
# (Antes soportaba también cloudflared Quick Tunnel — se sacó: CF Quick Tunnel quedó
#  caído indefinidamente y no se usa. Si alguna vez vuelve, revivir desde el historial git.)

$ErrorActionPreference = 'Continue'
$ProgressPreference    = 'SilentlyContinue'

# === CONFIG ===
$ListenPrefix    = 'http://127.0.0.1:9091/'
$NgrokExe        = 'C:\Users\usuario\.local\bin\ngrok.exe'
$TargetPort      = 80                          # nginx del docker-compose
$StateDir        = 'C:\crecer\backups\auto'
$StateFile       = Join-Path $StateDir 'tunnel-state.json'
$LogFile         = Join-Path $StateDir 'tunnel-ngrok.log'
$BrokerLog       = Join-Path $StateDir 'tunnel-broker.log'
$TokenFile       = Join-Path $StateDir 'tunnel-broker-token.txt'
$BotEnvFile      = 'C:\crecer\bot\.env'

New-Item -ItemType Directory -Path $StateDir -Force | Out-Null

# Lee una key del bot/.env (formato KEY=value), devuelve string o $null.
function Get-EnvValue {
    param([string]$Key)
    if (-not (Test-Path $BotEnvFile)) { return $null }
    $line = Get-Content $BotEnvFile -ErrorAction SilentlyContinue |
        Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if (-not $line) { return $null }
    return ($line -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

# Token: se genera al primer arranque y se persiste para que Laravel lo lea.
if (-not (Test-Path $TokenFile)) {
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $tok = -join ($bytes | ForEach-Object { $_.ToString('x2') })
    Set-Content -Path $TokenFile -Value $tok -Encoding ASCII -NoNewline
}
$Token = (Get-Content $TokenFile -Raw).Trim()

# === Estado en memoria ===
$script:Process  = $null   # System.Diagnostics.Process de ngrok
$script:Url      = $null
$script:Started  = $null

function Write-BrokerLog {
    param([string]$msg)
    $line = '[' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '] ' + $msg
    Add-Content -Path $BrokerLog -Value $line
}

function Save-State {
    $obj = @{
        pid        = if ($script:Process -and -not $script:Process.HasExited) { $script:Process.Id } else { $null }
        backend    = 'ngrok'
        url        = $script:Url
        started_at = $script:Started
    }
    $obj | ConvertTo-Json | Set-Content -Path $StateFile -Encoding UTF8
}

function Clear-State {
    $script:Process = $null
    $script:Url     = $null
    $script:Started = $null
    if (Test-Path $StateFile) { Remove-Item $StateFile -Force }
}

function Is-Running {
    return ($script:Process -ne $null) -and (-not $script:Process.HasExited)
}

function Kill-NgrokOrphans {
    Get-Process -Name 'ngrok' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
}

function Get-Status {
    $running = Is-Running
    $uptime  = if ($running -and $script:Started) {
        [int]((Get-Date) - [datetime]$script:Started).TotalSeconds
    } else { 0 }

    # Si está corriendo pero todavía no resolvimos la URL (polling inicial corto),
    # intentar sacarla de la API local de ngrok.
    if ($running -and -not $script:Url) {
        try {
            $r = Invoke-RestMethod -Uri 'http://127.0.0.1:4040/api/tunnels' -TimeoutSec 2 -UseBasicParsing -ErrorAction Stop
            $publicTunnel = $r.tunnels | Where-Object { $_.proto -eq 'https' } | Select-Object -First 1
            if ($publicTunnel) {
                $script:Url = $publicTunnel.public_url
                Save-State
            }
        } catch {}
    }

    $tail = @()
    if (Test-Path $LogFile) {
        try {
            $all = [System.IO.File]::ReadAllLines($LogFile)
            if ($all.Length -gt 25) { $tail = $all[($all.Length - 25)..($all.Length - 1)] }
            else { $tail = $all }
        } catch {}
    }
    return @{
        running        = $running
        backend        = 'ngrok'
        url            = $script:Url
        started_at     = $script:Started
        uptime_seconds = $uptime
        log_tail       = $tail
    }
}

function Start-Tunnel {
    if (Is-Running) { return Get-Status }

    if (-not (Test-Path $NgrokExe)) {
        return @{ error = "ngrok no instalado en $NgrokExe" } + (Get-Status)
    }
    $authtoken = Get-EnvValue 'NGROK_AUTHTOKEN'
    if (-not $authtoken) {
        return @{ error = "NGROK_AUTHTOKEN no esta en bot/.env" } + (Get-Status)
    }

    # Por las dudas: matar cualquier ngrok huérfano antes de levantar uno nuevo
    # (si no, choca el puerto :4040 del inspector).
    Kill-NgrokOrphans
    Start-Sleep -Milliseconds 300

    # Aplicar authtoken al config de ngrok (idempotente).
    $configResult = & $NgrokExe config add-authtoken $authtoken 2>&1
    Write-BrokerLog "ngrok config: $configResult"

    Set-Content -Path $LogFile -Value '' -Encoding UTF8

    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName  = $NgrokExe
    $psi.Arguments = "http $TargetPort --log=stdout --log-format=json"
    $psi.UseShellExecute        = $false
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError  = $true
    $psi.CreateNoWindow         = $true

    $proc = New-Object System.Diagnostics.Process
    $proc.StartInfo = $psi
    $proc.EnableRaisingEvents = $true
    $append = { param($s,$e) if ($null -ne $e.Data) { Add-Content -Path $LogFile -Value $e.Data } }
    Register-ObjectEvent -InputObject $proc -EventName OutputDataReceived -Action $append | Out-Null
    Register-ObjectEvent -InputObject $proc -EventName ErrorDataReceived  -Action $append | Out-Null
    $null = $proc.Start()
    $proc.BeginOutputReadLine()
    $proc.BeginErrorReadLine()

    $script:Process = $proc
    $script:Started = (Get-Date).ToString('o')
    $script:Url     = $null

    Write-BrokerLog "ngrok start PID=$($proc.Id)"

    # ngrok expone su API local en :4040 con la URL pública. Polling.
    $deadline = (Get-Date).AddSeconds(20)
    while ((Get-Date) -lt $deadline) {
        Start-Sleep -Milliseconds 500
        if ($proc.HasExited) {
            Write-BrokerLog "ngrok salio antes de levantar (exit=$($proc.ExitCode))"
            break
        }
        try {
            $r = Invoke-RestMethod -Uri 'http://127.0.0.1:4040/api/tunnels' -TimeoutSec 2 -UseBasicParsing -ErrorAction Stop
            $publicTunnel = $r.tunnels | Where-Object { $_.proto -eq 'https' } | Select-Object -First 1
            if ($publicTunnel) {
                $script:Url = $publicTunnel.public_url
                Write-BrokerLog "ngrok URL: $($script:Url)"
                break
            }
        } catch {}
    }

    Save-State
    return Get-Status
}

function Stop-Tunnel {
    if (Is-Running) {
        try {
            Write-BrokerLog "Tunnel stop PID=$($script:Process.Id)"
            Stop-Process -Id $script:Process.Id -Force -ErrorAction SilentlyContinue
            $script:Process.WaitForExit(5000) | Out-Null
        } catch {
            Write-BrokerLog "Error matando proceso: $_"
        }
    }
    # Por si quedó algún ngrok huérfano bindeando puertos.
    Kill-NgrokOrphans
    Clear-State
    return Get-Status
}

# === HTTP listener ===
$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add($ListenPrefix)
try {
    $listener.Start()
} catch {
    Write-BrokerLog "No se pudo bindear $ListenPrefix : $_"
    exit 1
}

Write-BrokerLog "Broker arrancado en $ListenPrefix (backend: ngrok)"

# Auto-arranque del túnel: matar huérfanos y levantar ngrok. Así, tras un reinicio
# del host (o del broker), el acceso remoto vuelve sin que nadie apriete nada.
Kill-NgrokOrphans
$autoStart = Start-Tunnel
if ($autoStart.error) { Write-BrokerLog "Auto-arranque del tunel fallo: $($autoStart.error)" }
else { Write-BrokerLog "Auto-arranque OK: $($autoStart.url)" }

# Cleanup
$exitHandler = {
    try { if ($script:Process -and -not $script:Process.HasExited) { Stop-Process -Id $script:Process.Id -Force -ErrorAction SilentlyContinue } } catch {}
    try { Get-Process -Name 'ngrok' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue } catch {}
    try { $listener.Stop(); $listener.Close() } catch {}
    Write-BrokerLog 'Broker terminado'
}

try {
    while ($listener.IsListening) {
        $ctx = $listener.GetContext()
        $req = $ctx.Request
        $res = $ctx.Response

        $hdr = $req.Headers['Authorization']
        if (-not $hdr -or $hdr -ne ('Bearer ' + $Token)) {
            $res.StatusCode = 401
            $bytes = [Text.Encoding]::UTF8.GetBytes('{"error":"unauthorized"}')
            $res.ContentType = 'application/json'
            $res.OutputStream.Write($bytes, 0, $bytes.Length)
            $res.Close()
            continue
        }

        $path   = $req.Url.AbsolutePath.ToLower()
        $method = $req.HttpMethod.ToUpper()
        $payload = $null

        try {
            switch ("$method $path") {
                'GET /status' { $payload = Get-Status }
                'POST /start' { $payload = Start-Tunnel }
                'POST /stop'  { $payload = Stop-Tunnel }
                default {
                    $res.StatusCode = 404
                    $payload = @{ error = "not found: $method $path" }
                }
            }
        } catch {
            Write-BrokerLog "Error en handler: $_"
            $res.StatusCode = 500
            $payload = @{ error = "$_" }
        }

        $json = $payload | ConvertTo-Json -Depth 5 -Compress
        $bytes = [Text.Encoding]::UTF8.GetBytes($json)
        $res.ContentType = 'application/json'
        $res.OutputStream.Write($bytes, 0, $bytes.Length)
        $res.Close()
    }
} finally {
    & $exitHandler
}
