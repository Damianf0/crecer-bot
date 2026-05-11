# Broker HTTP local para gestionar el túnel (cloudflared / ngrok).
#
# Expone HTTP en localhost:9091 (loopback puro, no accesible desde la red)
# con tres endpoints autenticados por Bearer token:
#
#   GET  /status -> { running, url, started_at, uptime_seconds, log_tail[], backend }
#   POST /start  -> arranca el túnel del backend configurado, espera a que aparezca
#                   la URL pública, y devuelve el mismo objeto que /status.
#   POST /stop   -> mata el proceso y borra el estado.
#
# Backend configurable vía bot/.env:
#   TUNNEL_BACKEND=ngrok       (default si NGROK_AUTHTOKEN está seteado)
#   TUNNEL_BACKEND=cloudflared (default si no hay authtoken)
#
# Se arranca como shortcut en Startup de Windows (CrecerTunnelBroker.lnk).
# El proceso del túnel es hijo de este broker y muere si el broker termina.
# El estado vive en backups/auto/tunnel-state.json para sobrevivir restarts.

$ErrorActionPreference = 'Continue'
$ProgressPreference    = 'SilentlyContinue'

# === CONFIG ===
$ListenPrefix    = 'http://127.0.0.1:9091/'
$CloudflaredExe  = 'C:\Users\usuario\.local\bin\cloudflared.exe'
$NgrokExe        = 'C:\Users\usuario\.local\bin\ngrok.exe'
$TargetUrl       = 'http://localhost:80'   # nginx del docker-compose
$TargetPort      = 80
$StateDir        = 'C:\crecer\backups\auto'
$StateFile       = Join-Path $StateDir 'tunnel-state.json'
$LogFile         = Join-Path $StateDir 'tunnel-cloudflared.log'
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

function Get-Backend {
    $env = Get-EnvValue 'TUNNEL_BACKEND'
    if ($env -and $env -in @('ngrok','cloudflared')) { return $env }
    $tok = Get-EnvValue 'NGROK_AUTHTOKEN'
    if ($tok) { return 'ngrok' }
    return 'cloudflared'
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
$script:Process  = $null   # System.Diagnostics.Process del túnel
$script:Backend  = 'cloudflared'
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
        backend    = $script:Backend
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

function Get-Status {
    $running = Is-Running
    $uptime  = if ($running -and $script:Started) {
        [int]((Get-Date) - [datetime]$script:Started).TotalSeconds
    } else { 0 }

    # Auto-resolver URL de ngrok si está corriendo y no la tenemos.
    # Cubre el caso donde el polling inicial fue muy corto.
    if ($running -and -not $script:Url -and $script:Backend -eq 'ngrok') {
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
            if ($all.Length -gt 25) {
                $tail = $all[($all.Length - 25)..($all.Length - 1)]
            } else {
                $tail = $all
            }
        } catch {}
    }
    return @{
        running        = $running
        backend        = $script:Backend
        url            = $script:Url
        started_at     = $script:Started
        uptime_seconds = $uptime
        log_tail       = $tail
    }
}

# ── Backend: cloudflared (Quick Tunnel) ─────────────────────────────
function Start-Cloudflared {
    if (-not (Test-Path $CloudflaredExe)) {
        return @{ error = "cloudflared no instalado en $CloudflaredExe" }
    }
    Set-Content -Path $LogFile -Value '' -Encoding UTF8

    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName  = $CloudflaredExe
    $psi.Arguments = "tunnel --url $TargetUrl --no-autoupdate"
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

    Write-BrokerLog "cloudflared start PID=$($proc.Id)"

    $deadline = (Get-Date).AddSeconds(30)
    while ((Get-Date) -lt $deadline) {
        Start-Sleep -Milliseconds 500
        if ($proc.HasExited) {
            Write-BrokerLog "cloudflared salio antes de generar URL (exit=$($proc.ExitCode))"
            break
        }
        if (Test-Path $LogFile) {
            $content = Get-Content $LogFile -Raw -ErrorAction SilentlyContinue
            if ($content -match 'https://[a-z0-9\-]+\.trycloudflare\.com') {
                $script:Url = $Matches[0]
                Write-BrokerLog "cloudflared URL: $($script:Url)"
                break
            }
        }
    }
    return Get-Status
}

# ── Backend: ngrok ──────────────────────────────────────────────────
function Start-Ngrok {
    if (-not (Test-Path $NgrokExe)) {
        return @{ error = "ngrok no instalado en $NgrokExe" }
    }
    $authtoken = Get-EnvValue 'NGROK_AUTHTOKEN'
    if (-not $authtoken) {
        return @{ error = "NGROK_AUTHTOKEN no esta en bot/.env" }
    }

    # Aplicar authtoken al config de ngrok (idempotente: lo sobrescribe cada vez).
    $configResult = & $NgrokExe config add-authtoken $authtoken 2>&1
    Write-BrokerLog "ngrok config: $configResult"

    Set-Content -Path $LogFile -Value '' -Encoding UTF8

    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName  = $NgrokExe
    # --log=stdout para capturar; sin --inspect=false porque queremos la API local en :4040.
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
        } catch {
            # API todavía no levanto, retry
        }
    }
    return Get-Status
}

function Start-Tunnel {
    if (Is-Running) { return Get-Status }
    $script:Backend = Get-Backend
    Write-BrokerLog "Iniciando backend=$script:Backend"
    $r = switch ($script:Backend) {
        'ngrok'       { Start-Ngrok }
        'cloudflared' { Start-Cloudflared }
        default       { @{ error = "backend desconocido: $script:Backend" } }
    }
    Save-State
    if ($r.error) { return @{ error = $r.error } + (Get-Status) }
    return Get-Status
}

function Stop-Tunnel {
    if (-not (Is-Running)) {
        Clear-State
        return Get-Status
    }
    try {
        Write-BrokerLog "Tunnel stop PID=$($script:Process.Id)"
        # Stop-Process -Force mata por taskkill /T (kill tree) — funciona en PS 5.1
Stop-Process -Id $script:Process.Id -Force -ErrorAction SilentlyContinue
# También por si hay child cloudflared/ngrok colgado bindeando puertos
Get-Process -Name 'ngrok','cloudflared' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
        $script:Process.WaitForExit(5000) | Out-Null
    } catch {
        Write-BrokerLog "Error matando proceso: $_"
    }
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

Write-BrokerLog "Broker arrancado en $ListenPrefix (backend default: $(Get-Backend))"
$script:Backend = Get-Backend

# Cleanup
$exitHandler = {
    try { if ($script:Process -and -not $script:Process.HasExited) { # Stop-Process -Force mata por taskkill /T (kill tree) — funciona en PS 5.1
Stop-Process -Id $script:Process.Id -Force -ErrorAction SilentlyContinue
# También por si hay child cloudflared/ngrok colgado bindeando puertos
Get-Process -Name 'ngrok','cloudflared' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue } } catch {}
    try { $listener.Stop(); $listener.Close() } catch {}
    Write-BrokerLog 'Broker terminado'
}
# CancelKeyPress requiere consola. El broker corre headless (-WindowStyle Hidden),
# así que no lo registramos. El exitHandler corre vía el `finally` del loop.

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

        $path = $req.Url.AbsolutePath.ToLower()
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
