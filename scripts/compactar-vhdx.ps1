# Compacta docker_data.vhdx (Docker Desktop + WSL2). Pensado para correr de noche
# como tarea programada con privilegios elevados. Deja log en scripts\compactar-vhdx.log.
# Secuencia validada (ver memoria compactar-vhdx): prune -> compose stop -> matar Docker
# Desktop -> wsl --shutdown -> diskpart compact -> relanzar Docker -> compose up -d.

$ErrorActionPreference = 'Continue'
$repo = 'C:\crecer'
$vhdx = "$env:LOCALAPPDATA\Docker\wsl\disk\docker_data.vhdx"
$log = Join-Path $repo 'scripts\compactar-vhdx.log'
$dockerExe = 'C:\Program Files\Docker\Docker\Docker Desktop.exe'

function Log($m) { "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $m" | Out-File $log -Append -Encoding utf8 }

Set-Location $repo
Log '=== inicio ==='
Log ("vhdx antes: {0:N2} GB | C: libre {1:N1} GB" -f ((Get-Item $vhdx).Length/1GB), ((Get-PSDrive C).Free/1GB))

$svc = Get-Service com.docker.service -ErrorAction SilentlyContinue
$startTypeOriginal = if ($svc) { $svc.StartType } else { $null }

try {
    docker builder prune -f 2>&1 | Out-Null
    Log 'docker compose stop -t 60'
    docker compose stop -t 60 2>&1 | ForEach-Object { Log "  $_" }

    if ($svc) {
        sc.exe config com.docker.service start= disabled | Out-Null
        Stop-Service com.docker.service -Force -ErrorAction SilentlyContinue
    }
    'Docker Desktop', 'com.docker.backend', 'com.docker.build', 'docker-sandbox', 'wslhost', 'wslrelay' |
        ForEach-Object { Get-Process $_ -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue }
    Start-Sleep 5
    wsl --shutdown
    Start-Sleep 15

    $dp = Join-Path $env:TEMP 'compactar-vhdx.txt'
    @("select vdisk file=`"$vhdx`"", 'attach vdisk readonly', 'compact vdisk', 'detach vdisk', 'exit') |
        Set-Content $dp -Encoding ascii
    Log 'diskpart compact...'
    diskpart /s $dp 2>&1 | ForEach-Object { Log "  $_" }
    # por si quedó montado
    @("select vdisk file=`"$vhdx`"", 'detach vdisk', 'exit') | Set-Content $dp -Encoding ascii
    diskpart /s $dp 2>&1 | Out-Null
    Remove-Item $dp -ErrorAction SilentlyContinue
}
catch { Log "ERROR: $_" }
finally {
    Log ("vhdx despues: {0:N2} GB | C: libre {1:N1} GB" -f ((Get-Item $vhdx).Length/1GB), ((Get-PSDrive C).Free/1GB))
    if ($svc) {
        $t = if ($startTypeOriginal -eq 'Automatic') { 'auto' } elseif ($startTypeOriginal -eq 'Disabled') { 'disabled' } else { 'demand' }
        sc.exe config com.docker.service start= $t | Out-Null
    }
    Log 'relanzando Docker Desktop'
    Start-Process $dockerExe
    $ok = $false
    for ($i = 0; $i -lt 60; $i++) {
        Start-Sleep 10
        docker info *> $null
        if ($LASTEXITCODE -eq 0) { $ok = $true; break }
    }
    Log "docker listo: $ok"
    for ($n = 1; $n -le 3; $n++) {
        docker compose up -d 2>&1 | ForEach-Object { Log "  $_" }
        if ($LASTEXITCODE -eq 0) { break }
        Start-Sleep 30
    }
    Start-Sleep 180
    docker ps --format '{{.Names}}  {{.Status}}' 2>&1 | ForEach-Object { Log "  $_" }
    Log '=== fin ==='
}
