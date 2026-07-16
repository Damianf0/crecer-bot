# Watchdog de los bots WhatsApp - Crecer (multi-area)
# Tarea Windows que cada 5 min verifica:
#   1. Que Docker Desktop este corriendo (si no, lo levanta).
#   2. Que CADA bot (atencion 3001, administracion 3002, ovodonacion 3003)
#      responda HTTP en /status y este "listo".
# Si un bot esta caido sostenido (>10 min), hace docker restart de SU container
# (rate-limited a 1 cada 5 min por bot). EXCEPCION: estado "esperando_qr" -- la
# sesion se perdio y hace falta escanear QR a mano; reiniciar solo regenera el
# QR en loop (incidente 01/07). En ese caso NO reinicia, solo alerta.
# Notifica por WhatsApp a Damian al INICIO del incidente sostenido y al
# recuperarse, indicando el area. Como el bot caido puede ser justamente el
# emisor, intenta enviar por atencion -> administracion -> ovodonacion.
#
# Historia: hasta 01/07 solo vigilaba atencion (3001) -- por eso el freeze de
# administracion de junio duro 10 dias sin que nadie se entere.
#
# Programado en Windows (cada 5 min):
#   schtasks /Create /SC MINUTE /MO 5 /TN "Crecer\WatchdogBot" `
#     /TR "powershell -NoProfile -ExecutionPolicy Bypass -File C:\crecer\docker\watchdog-bot.ps1" /F /RU SYSTEM

$ErrorActionPreference = 'Continue'

# === CONFIG ===
# Token leido desde bot/.env (no commitear creds en este script).
$BotIngressToken = (Get-Content 'C:\crecer\bot\.env' -ErrorAction SilentlyContinue |
    Where-Object { $_ -match '^BOT_INGRESS_TOKEN=' } |
    ForEach-Object { ($_ -split '=', 2)[1].Trim() }) | Select-Object -First 1
$NotifyToJid   = '5492235594007@c.us'
$DockerExe     = 'C:\Program Files\Docker\Docker\Docker Desktop.exe'
$IncidenteMin  = 10
$RestartMinGap = 5
# Anti-loop (evaluacion 16/07): initialize de wwebjs puede tardar 3-6 min en
# condiciones malas; reiniciar un container recien nacido garantiza el loop
# (anoche: ~150 restarts inutiles). Ademas, mas de 2 restarts/hora nunca arreglo
# nada historicamente: a partir del 3ro solo se alerta (proteger la sesion).
$MinUptimeMin      = 10
$MaxRestartsPorHora = 2
# Modo mantenimiento: si existe este archivo, el watchdog observa y loguea pero NO
# reinicia nada (crear al operar a mano; hoy 11:00 el watchdog piso una recuperacion
# manual dos veces). Borrarlo al terminar.
$PauseFile = 'C:\crecer\backups\auto\watchdog-pause'

$Bots = @(
    @{ Area = 'atencion';       Port = 3001; Ctr = 'crecer-bot-1' },
    @{ Area = 'administracion'; Port = 3002; Ctr = 'crecer-bot-administracion-1' },
    @{ Area = 'ovodonacion';    Port = 3003; Ctr = 'crecer-bot-ovodonacion-1' }
)

$StateFile = 'C:\crecer\backups\auto\watchdog-state.json'
$LogFile   = 'C:\crecer\backups\auto\watchdog.log'
New-Item -ItemType Directory -Path (Split-Path $LogFile) -Force | Out-Null

function Log {
    param([string]$msg)
    $line = '[' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '] ' + $msg
    Add-Content -Path $LogFile -Value $line
}

# Estado por area: { last_listo_at, incident_start_at, last_restart_at, last_reason, incident_notified }
function CargarEstado {
    $raw = $null
    if (Test-Path $StateFile) {
        try { $raw = Get-Content $StateFile -Raw | ConvertFrom-Json } catch { }
    }
    $state = @{}
    foreach ($b in $Bots) {
        $a = $b.Area
        $prev = $null
        if ($raw) {
            if ($raw.PSObject.Properties.Name -contains $a) {
                $prev = $raw.$a
            } elseif ($a -eq 'atencion' -and $raw.PSObject.Properties.Name -contains 'incident_start_at') {
                # Migracion del formato viejo (single-bot plano) -> clave atencion
                $prev = $raw
            }
        }
        $state[$a] = @{
            last_listo_at     = if ($prev) { $prev.last_listo_at }     else { $null }
            incident_start_at = if ($prev) { $prev.incident_start_at } else { $null }
            last_restart_at   = if ($prev) { $prev.last_restart_at }   else { $null }
            last_reason       = if ($prev) { $prev.last_reason }       else { $null }
            incident_notified = if ($prev -and $prev.PSObject.Properties.Name -contains 'incident_notified') { [bool]$prev.incident_notified } else { $false }
            restart_times     = if ($prev -and $prev.PSObject.Properties.Name -contains 'restart_times') { @($prev.restart_times) } else { @() }
        }
    }
    return $state
}

function GuardarEstado {
    param($state)
    $state | ConvertTo-Json -Depth 3 | Out-File -FilePath $StateFile -Encoding utf8 -Force
}

function NotificarWA {
    param([string]$texto)
    # El bot caido puede ser el mismo por el que notificamos: probar los 3 en orden.
    $emisores = @('http://localhost:3001', 'http://localhost:3002', 'http://localhost:3003')
    $body = @{ contacto = $NotifyToJid; texto = $texto } | ConvertTo-Json
    $headers = @{ 'Authorization' = 'Bearer ' + $BotIngressToken; 'Content-Type' = 'application/json' }
    foreach ($base in $emisores) {
        try {
            $r = Invoke-RestMethod -Uri ($base + '/enviar') -Method POST -Headers $headers -Body $body -TimeoutSec 10
            Log ('[notif] enviado via ' + $base + ': ' + $texto)
            return $true
        } catch {
            Log ('[notif] fallo via ' + $base + ': ' + $_.Exception.Message)
        }
    }
    Log '[notif] FALLO al notificar por los 3 bots'
    return $false
}

function DockerCorriendo {
    try {
        $null = & docker version --format '{{.Server.Version}}' 2>$null
        return $LASTEXITCODE -eq 0
    } catch { return $false }
}

# TCP a web.whatsapp.com:443 (lo que el bot necesita para funcionar). Si no hay
# salida a internet, un bot "en falla" NO esta roto: reiniciarlo no arregla nada
# y cada restart arriesga la sesion (corte de internet 15/07: este watchdog +
# autoheal reiniciaron en loop bots sanos). DNS roto tambien cuenta como sin internet.
function InternetOk {
    try {
        $c = New-Object Net.Sockets.TcpClient
        $ar = $c.BeginConnect('web.whatsapp.com', 443, $null, $null)
        $ok = $ar.AsyncWaitHandle.WaitOne(5000) -and $c.Connected
        $c.Close()
        return [bool]$ok
    } catch { return $false }
}

# === MAIN ===
$state = CargarEstado
$now   = Get-Date

# 1) Verificar Docker Desktop (una sola vez por corrida)
if (-not (DockerCorriendo)) {
    Log 'Docker no responde - intentando levantar Docker Desktop'
    if (Test-Path $DockerExe) {
        Start-Process -FilePath $DockerExe -WindowStyle Hidden
        Log 'Docker Desktop lanzado. Esperando 90s para que arranque...'
        Start-Sleep -Seconds 90
        if (-not (DockerCorriendo)) {
            Log 'Docker sigue sin responder tras 90s. Saliendo, proxima corrida reintenta.'
            GuardarEstado $state
            return
        }
        Log 'Docker arriba.'
    } else {
        Log ('ERROR: no encuentro Docker Desktop en ' + $DockerExe)
        return
    }
}

# 2) Conectividad del host (una vez por corrida): gobierna si tiene sentido reiniciar
$internetOk = InternetOk
if (-not $internetOk) { Log 'SIN INTERNET en el host (web.whatsapp.com:443 inalcanzable) - no se reinicia ningun bot esta corrida' }

# 3) Chequear cada bot y correr su maquina de estados
foreach ($b in $Bots) {
    $area = $b.Area
    $s    = $state[$area]

    $status = $null
    $httpOk = $false
    try {
        $resp = Invoke-RestMethod -Uri ('http://localhost:' + $b.Port + '/status') -TimeoutSec 8 -ErrorAction Stop
        $httpOk = $true
        $status = $resp.status
    } catch {
        $httpOk = $false
    }

    if (-not $httpOk) {
        $reason = 'Bot ' + $area + ' no responde HTTP en :' + $b.Port + '/status'
    } elseif ($status -ne 'listo') {
        $reason = 'Bot ' + $area + ' en estado: ' + $status
    } else {
        $reason = $null
    }

    # Heartbeat (1 linea por bot por corrida)
    if ($null -eq $reason) {
        Log ('OK [' + $area + '] status=' + $status)
    } else {
        Log ('ALERTA [' + $area + ']: ' + $reason)
    }

    if ($null -eq $reason) {
        if ($s.incident_start_at) {
            $start = [DateTime]$s.incident_start_at
            $durMin = [int][Math]::Round(($now - $start).TotalMinutes)
            $msg = 'Bot ' + $area + ' recuperado. Estuvo en falla ' + $durMin + ' min. Motivo: ' + $s.last_reason + '.'
            Log ('RECUPERADO [' + $area + ']: ' + $msg)
            # Solo notificar la recuperacion si el incidente habia sido notificado
            # (>=10 min). Los blips cortos (reinicio del watchdog interno, ~13s,
            # cazados por mala suerte del muestreo) quedan solo en el log — eran
            # el grueso de las notificaciones molestas del 07-08/07.
            if ($s.incident_notified) {
                NotificarWA ('Watchdog Crecer: ' + $msg) | Out-Null
            }
            $s.incident_start_at = $null
            $s.last_reason       = $null
            $s.incident_notified = $false
        }
        $s.last_listo_at = $now.ToString('o')
    } else {
        if (-not $s.incident_start_at) {
            $s.incident_start_at = $now.ToString('o')
            $s.last_reason       = $reason
            Log ('INICIO INCIDENTE [' + $area + ']: ' + $reason)
        } else {
            $start = [DateTime]$s.incident_start_at
            $durMin = ($now - $start).TotalMinutes
            $durStr = [int]$durMin
            Log ('INCIDENTE en curso [' + $area + '] (' + $durStr + ' min): ' + $reason)

            if ($durMin -ge $IncidenteMin) {
                # Avisar UNA vez al cruzar el umbral. Solo marcar notificado si el
                # envio salio de verdad: sin internet los 3 emisores fallan, y asi
                # el aviso se reintenta cada corrida hasta que vuelva la conectividad.
                if (-not $s.incident_notified) {
                    $msg = 'Watchdog Crecer: bot ' + $area + ' en falla hace ' + $durStr + ' min. Motivo: ' + $reason + '.'
                    if ($status -eq 'esperando_qr') { $msg += ' Sesion perdida: hay que escanear QR desde /admin con el celular del area.' }
                    if (-not $internetOk) { $msg += ' Host sin salida a internet: no se reinicia, se espera reconexion.' }
                    if (NotificarWA $msg) { $s.incident_notified = $true }
                }

                # esperando_qr: reiniciar no sirve (regenera el QR en loop) -- solo esperar el escaneo.
                if ($status -eq 'esperando_qr') {
                    Log ('Estado esperando_qr [' + $area + ']: NO se reinicia (requiere escaneo manual de QR)')
                } elseif (-not $internetOk) {
                    Log ('Sin internet [' + $area + ']: NO se reinicia (el bot no esta roto, falta conectividad; se reconecta solo al volver)')
                } elseif (Test-Path $PauseFile) {
                    Log ('PAUSADO [' + $area + ']: existe ' + $PauseFile + ' (mantenimiento manual) - se observa pero no se actua')
                } else {
                    # Poda del historial de restarts a la ultima hora
                    $s.restart_times = @($s.restart_times | Where-Object { $_ -and ($now - [DateTime]$_).TotalMinutes -lt 60 })

                    # Uptime del container: no reiniciar uno recien nacido (initialize
                    # de wwebjs tarda 3-6 min en condiciones malas; gap < initialize = loop)
                    $uptimeMin = $null
                    try {
                        $startedRaw = (& docker inspect --format '{{.State.StartedAt}}' $b.Ctr 2>$null) | Select-Object -First 1
                        if ($startedRaw) {
                            $started = [DateTime]::Parse(($startedRaw -replace '\.\d+Z$', 'Z')).ToLocalTime()
                            $uptimeMin = ($now - $started).TotalMinutes
                        }
                    } catch { }

                    $puedeRestart = $true
                    $motivoSkip   = $null
                    if ($null -ne $uptimeMin -and $uptimeMin -lt $MinUptimeMin) {
                        $puedeRestart = $false
                        $motivoSkip = 'container con ' + [int]$uptimeMin + ' min de vida (< ' + $MinUptimeMin + '): initialize en curso, no pisar'
                    } elseif (@($s.restart_times).Count -ge $MaxRestartsPorHora) {
                        $puedeRestart = $false
                        $motivoSkip = 'circuit breaker: ya hubo ' + @($s.restart_times).Count + ' restarts en la ultima hora y no arreglaron nada - solo alerta, revisar a mano'
                    } elseif ($s.last_restart_at) {
                        $sinceRestart = ($now - [DateTime]$s.last_restart_at).TotalMinutes
                        if ($sinceRestart -lt $RestartMinGap) {
                            $puedeRestart = $false
                            $motivoSkip = 'ventana entre restarts (' + $RestartMinGap + ' min)'
                        }
                    }

                    if ($puedeRestart) {
                        Log ('Ejecutando: docker restart ' + $b.Ctr)
                        & docker restart $b.Ctr 2>&1 | ForEach-Object { Log ('  ' + $_) }
                        $s.last_restart_at = $now.ToString('o')
                        $s.restart_times   = @($s.restart_times) + $now.ToString('o')
                        $s.last_reason     = $reason + ' (restart automatico)'
                    } else {
                        Log ('NO se reinicia [' + $area + ']: ' + $motivoSkip)
                    }
                }
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
