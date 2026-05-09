# Watchdog del bot WhatsApp - Crecer
# Tarea Windows que cada 5 min verifica:
#   1. Que Docker Desktop este corriendo (si no, lo levanta).
#   2. Que el bot responda HTTP en :3001/status.
#   3. Que su estado sea "listo" (no quedo colgado en "iniciando" o "autenticado").
# Si detecta caida sostenida (>10 min), hace 'docker restart crecer-bot-1'
# (rate-limited a 1 cada 5 min). Cuando el bot vuelve a "listo" despues de
# un incidente, manda un WhatsApp a Damian con el resumen.

$ErrorActionPreference = 'Continue'

# === CONFIG ===
$BotUrl          = 'http://localhost:3001'
# Token leido desde bot/.env (no commitear creds en este script).
$BotIngressToken = (Get-Content 'C:\crecer\bot\.env' -ErrorAction SilentlyContinue |
    Where-Object { $_ -match '^BOT_INGRESS_TOKEN=' } |
    ForEach-Object { ($_ -split '=', 2)[1].Trim() }) | Select-Object -First 1
$BotContainer    = 'crecer-bot-1'
$NotifyToJid     = '5492235594007@c.us'
$DockerExe       = 'C:\Program Files\Docker\Docker\Docker Desktop.exe'
$IncidenteMin    = 10
$RestartMinGap   = 5

$StateFile = 'C:\crecer\backups\auto\watchdog-state.json'
$LogFile   = 'C:\crecer\backups\auto\watchdog.log'
New-Item -ItemType Directory -Path (Split-Path $LogFile) -Force | Out-Null

function Log {
    param([string]$msg)
    $line = '[' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '] ' + $msg
    Add-Content -Path $LogFile -Value $line
}

function CargarEstado {
    if (Test-Path $StateFile) {
        try { return Get-Content $StateFile -Raw | ConvertFrom-Json } catch { }
    }
    return [pscustomobject]@{
        last_listo_at     = $null
        incident_start_at = $null
        last_restart_at   = $null
        last_reason       = $null
    }
}

function GuardarEstado {
    param($state)
    $state | ConvertTo-Json | Out-File -FilePath $StateFile -Encoding utf8 -Force
}

function NotificarWA {
    param([string]$texto)
    try {
        $body = @{ contacto = $NotifyToJid; texto = $texto } | ConvertTo-Json
        $headers = @{ 'Authorization' = 'Bearer ' + $BotIngressToken; 'Content-Type' = 'application/json' }
        $r = Invoke-RestMethod -Uri ($BotUrl + '/enviar') -Method POST -Headers $headers -Body $body -TimeoutSec 10
        Log ('[notif] enviado: ' + $texto)
        return $true
    } catch {
        Log ('[notif] FALLO al notificar: ' + $_.Exception.Message)
        return $false
    }
}

function DockerCorriendo {
    try {
        $null = & docker version --format '{{.Server.Version}}' 2>$null
        return $LASTEXITCODE -eq 0
    } catch { return $false }
}

# === MAIN ===
$state = CargarEstado
$now   = Get-Date

# 1) Verificar Docker Desktop
if (-not (DockerCorriendo)) {
    Log 'Docker no responde - intentando levantar Docker Desktop'
    if (Test-Path $DockerExe) {
        Start-Process -FilePath $DockerExe -WindowStyle Hidden
        Log 'Docker Desktop lanzado. Esperando 90s para que arranque...'
        Start-Sleep -Seconds 90
        if (-not (DockerCorriendo)) {
            Log 'Docker sigue sin responder tras 90s. Saliendo, proxima corrida reintenta.'
            return
        }
        Log 'Docker arriba.'
    } else {
        Log ('ERROR: no encuentro Docker Desktop en ' + $DockerExe)
        return
    }
}

# 2) Pegar al bot /status
$status = $null
$httpOk = $false
try {
    $resp = Invoke-RestMethod -Uri ($BotUrl + '/status') -TimeoutSec 8 -ErrorAction Stop
    $httpOk = $true
    $status = $resp.status
} catch {
    $httpOk = $false
}

if (-not $httpOk) {
    $reason = 'Bot no responde HTTP en :3001/status'
} elseif ($status -ne 'listo') {
    $reason = 'Bot en estado: ' + $status
} else {
    $reason = $null
}

# Log de heartbeat (1 linea por corrida, util para confirmar que la tarea programada esta corriendo)
if ($null -eq $reason) {
    Log ('OK status=' + $status)
} else {
    Log ('ALERTA: ' + $reason)
}

# 3) Maquina de estados
if ($null -eq $reason) {
    if ($state.incident_start_at) {
        $start = [DateTime]$state.incident_start_at
        $durMin = [int][Math]::Round(($now - $start).TotalMinutes)
        $reasonPrev = $state.last_reason
        $msg = 'Bot recuperado. Estuvo en falla ' + $durMin + ' min. Motivo: ' + $reasonPrev + '.'
        Log ('RECUPERADO: ' + $msg)
        NotificarWA ('Watchdog Crecer: ' + $msg) | Out-Null
        $state.incident_start_at = $null
        $state.last_reason       = $null
    }
    $state.last_listo_at = $now.ToString('o')
} else {
    if (-not $state.incident_start_at) {
        $state.incident_start_at = $now.ToString('o')
        $state.last_reason       = $reason
        Log ('INICIO INCIDENTE: ' + $reason)
    } else {
        $start = [DateTime]$state.incident_start_at
        $durMin = ($now - $start).TotalMinutes
        $durStr = [int]$durMin
        Log ('INCIDENTE en curso (' + $durStr + ' min): ' + $reason)

        if ($durMin -ge $IncidenteMin) {
            $puedeRestart = $true
            if ($state.last_restart_at) {
                $sinceRestart = ($now - [DateTime]$state.last_restart_at).TotalMinutes
                if ($sinceRestart -lt $RestartMinGap) { $puedeRestart = $false }
            }
            if ($puedeRestart) {
                Log ('Ejecutando: docker restart ' + $BotContainer)
                & docker restart $BotContainer 2>&1 | ForEach-Object { Log ('  ' + $_) }
                $state.last_restart_at = $now.ToString('o')
                $state.last_reason     = $reason + ' (restart automatico)'
            } else {
                Log ('Esperando ventana entre restarts (' + $RestartMinGap + ' min)')
            }
        }
    }
}

GuardarEstado $state

# Rotar log si pasa 1 MB
if ((Test-Path $LogFile) -and (Get-Item $LogFile).Length -gt 1MB) {
    Move-Item $LogFile ($LogFile + '.old') -Force
    Log 'Log rotado'
}
