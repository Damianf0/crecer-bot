# Backup FULL de la plataforma Crecer — junta TODO lo necesario para reconstruir
# en una sola carpeta (C:\crecer\backups\full), pensada para que una copia externa
# (directorio compartido / NAS) la levante entera. Guia de recuperacion:
# backups\full\README-RESTAURAR.md
#
# Que incluye:
#   mysql\        dump fresco comprimido (retiene 3)
#   sesiones-wa\  tar de los volumenes de sesion WhatsApp de los 3 bots
#                 (stop -> tar -> start por bot, ~1 min de corte c/u; sin cache Chromium)
#   media\        espejo de bot\media (archivos de pacientes)
#   storage\      espejo de app\storage (sin framework: cache regenerable)
#   config\       app.env / bot.env / root.env + docker-compose.yml (NO estan en git)
#   repo\         git bundle con la historia completa del repo
#
# Programacion sugerida (fuera de horario clinica 8-17; antes del BackupMySQL 03:00
# y del CleanBotCache 04:00 para no pisarse):
#   schtasks /Create /SC DAILY /ST 02:30 /TN "Crecer\BackupFull" `
#     /TR "powershell -NoProfile -ExecutionPolicy Bypass -File C:\crecer\docker\backup-full.ps1" /F /RU SYSTEM
#
# La copia al directorio compartido conviene programarla >= 05:30 (carpeta ya estable).

$ErrorActionPreference = 'Continue'

$Root    = 'C:\crecer'
$Dest    = "$Root\backups\full"
$LogFile = "$Dest\backup-full.log"
$Stamp   = Get-Date -Format 'yyyyMMdd-HHmmss'
$Fallas  = @()

foreach ($d in 'mysql', 'sesiones-wa', 'media', 'storage', 'config', 'repo') {
    New-Item -ItemType Directory -Path "$Dest\$d" -Force | Out-Null
}

function Log {
    param([string]$msg)
    $line = '[' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '] ' + $msg
    Write-Output $line
    Add-Content -Path $LogFile -Value $line
}

# Credenciales desde .env (no hardcodear passwords en scripts — ver backup-mysql.ps1 viejo)
function LeerEnv {
    param([string]$archivo, [string]$clave)
    $linea = Get-Content $archivo -ErrorAction SilentlyContinue |
        Where-Object { $_ -match "^$clave=" } | Select-Object -First 1
    if ($linea) { return ($linea -split '=', 2)[1].Trim() }
    return $null
}

Log "================== INICIO backup full =================="

# Guia de recuperacion siempre fresca dentro de la carpeta (master en el repo)
Copy-Item "$Root\docker\README-RESTAURAR.md" -Destination "$Dest\README-RESTAURAR.md" -Force -ErrorAction SilentlyContinue

# ── 1. Dump MySQL fresco ─────────────────────────────────────────────
$RootPwd = LeerEnv "$Root\.env" 'DB_ROOT_PASSWORD'
if (-not $RootPwd) {
    Log 'ERROR: DB_ROOT_PASSWORD no encontrada en C:\crecer\.env'
    $Fallas += 'mysql'
} else {
    $DumpFile = "$Dest\mysql\clinica-full-$Stamp.sql.gz"
    $tmpErr = [System.IO.Path]::GetTempFileName()
    # Redireccion en cmd.exe para preservar los bytes binarios del gzip
    $cmd = "docker exec -e MYSQL_PWD=$RootPwd crecer-mysql-1 sh -c `"mysqldump --single-transaction --quick --routines --triggers -uroot clinica 2>/dev/null | gzip`""
    cmd /c "$cmd > `"$DumpFile`" 2> `"$tmpErr`""
    Remove-Item $tmpErr -ErrorAction SilentlyContinue

    if ((Test-Path $DumpFile) -and (Get-Item $DumpFile).Length -gt 100KB) {
        $mb = [math]::Round((Get-Item $DumpFile).Length / 1MB, 1)
        Log "mysql: OK $DumpFile ($mb MB)"
        # Retener los 3 dumps mas nuevos
        Get-ChildItem "$Dest\mysql" -Filter '*.sql.gz' | Sort-Object LastWriteTime -Descending |
            Select-Object -Skip 3 | ForEach-Object { Remove-Item $_.FullName -Force; Log "mysql: rotado $($_.Name)" }
    } else {
        Log 'mysql: ERROR dump vacio o inexistente'
        $Fallas += 'mysql'
    }
}

# ── 2. Media y storage (espejo incremental) ──────────────────────────
# robocopy: exit 0-7 = OK (con/sin copias), >=8 = error real
robocopy "$Root\bot\media" "$Dest\media" /MIR /R:1 /W:1 /NFL /NDL /NJH /NP | Out-Null
if ($LASTEXITCODE -ge 8) { Log "media: ERROR robocopy exit $LASTEXITCODE"; $Fallas += 'media' }
else { Log "media: OK espejo actualizado (robocopy exit $LASTEXITCODE)" }

# /XD framework: cache/vistas compiladas/sesiones de Laravel — regenerables
robocopy "$Root\app\storage" "$Dest\storage" /MIR /XD framework /R:1 /W:1 /NFL /NDL /NJH /NP | Out-Null
if ($LASTEXITCODE -ge 8) { Log "storage: ERROR robocopy exit $LASTEXITCODE"; $Fallas += 'storage' }
else { Log "storage: OK espejo actualizado (robocopy exit $LASTEXITCODE)" }

# ── 3. Config no versionada (.env x3 + compose) ──────────────────────
$copias = @(
    @{ De = "$Root\.env";              A = "$Dest\config\root.env" },
    @{ De = "$Root\app\.env";          A = "$Dest\config\app.env" },
    @{ De = "$Root\bot\.env";          A = "$Dest\config\bot.env" },
    @{ De = "$Root\docker-compose.yml"; A = "$Dest\config\docker-compose.yml" }
)
foreach ($c in $copias) {
    try {
        Copy-Item $c.De -Destination $c.A -Force -ErrorAction Stop
    } catch {
        Log "config: ERROR copiando $($c.De): $($_.Exception.Message)"
        $Fallas += 'config'
    }
}
Log 'config: OK .env x3 + docker-compose.yml'

# ── 4. Bundle git (historia completa del repo) ───────────────────────
$bundleOut = git -C $Root bundle create "$Dest\repo\crecer.bundle" --all 2>&1
if ($LASTEXITCODE -eq 0) {
    $mb = [math]::Round((Get-Item "$Dest\repo\crecer.bundle").Length / 1MB, 1)
    Log "repo: OK crecer.bundle ($mb MB)"
} else {
    Log "repo: ERROR git bundle: $bundleOut"
    $Fallas += 'repo'
}

# ── 5. Sesiones WhatsApp (al final: unica parte con corte de servicio) ─
# tar con el bot PARADO = copia consistente. Se excluye el cache de Chromium
# (regenerable y pesado); la sesion en si es IndexedDB/Local Storage/Cookies.
$Bots = @(
    @{ Vol = 'crecer_wa-session';                Ctr = 'crecer-bot-1';                Nombre = 'atencion' },
    @{ Vol = 'crecer_wa-session-administracion'; Ctr = 'crecer-bot-administracion-1'; Nombre = 'administracion' },
    @{ Vol = 'crecer_wa-session-ovodonacion';    Ctr = 'crecer-bot-ovodonacion-1';    Nombre = 'ovodonacion' }
)
$tarExcl = "--exclude='./session/Default/Cache' --exclude='./session/Default/Code Cache' " +
           "--exclude='./session/Default/GPUCache' --exclude='./session/Default/Service Worker' " +
           "--exclude='./session/Default/DawnGraphiteCache' --exclude='./session/Default/DawnWebGPUCache'"

foreach ($b in $Bots) {
    $tarFile = "wa-session-$($b.Nombre).tar.gz"
    Log "sesion $($b.Nombre): deteniendo bot..."
    cmd /c "docker stop -t 30 $($b.Ctr) > nul 2>&1"

    # Rotar: la copia anterior queda como .prev
    if (Test-Path "$Dest\sesiones-wa\$tarFile") {
        Move-Item "$Dest\sesiones-wa\$tarFile" "$Dest\sesiones-wa\$tarFile.prev" -Force
    }

    cmd /c "docker run --rm -v $($b.Vol):/data -v `"$Dest\sesiones-wa`":/backup alpine sh -c `"cd /data && tar czf /backup/$tarFile $tarExcl .`" > nul 2>&1"

    cmd /c "docker start $($b.Ctr) > nul 2>&1"

    if ((Test-Path "$Dest\sesiones-wa\$tarFile") -and (Get-Item "$Dest\sesiones-wa\$tarFile").Length -gt 10KB) {
        $mb = [math]::Round((Get-Item "$Dest\sesiones-wa\$tarFile").Length / 1MB, 1)
        Log "sesion $($b.Nombre): OK $tarFile ($mb MB), bot rearrancado"
    } else {
        Log "sesion $($b.Nombre): ERROR tar vacio o inexistente (bot rearrancado igual)"
        # Restaurar el .prev para no quedar sin ninguna copia
        if (Test-Path "$Dest\sesiones-wa\$tarFile.prev") {
            Move-Item "$Dest\sesiones-wa\$tarFile.prev" "$Dest\sesiones-wa\$tarFile" -Force
        }
        $Fallas += "sesion-$($b.Nombre)"
    }
}

# ── Resumen ──────────────────────────────────────────────────────────
$totalMB = [math]::Round(((Get-ChildItem $Dest -Recurse -File | Measure-Object Length -Sum).Sum / 1MB), 0)
if ($Fallas.Count -eq 0) {
    Log "RESULTADO: OK completo — carpeta $Dest ($totalMB MB)"
} else {
    Log ("RESULTADO: CON ERRORES en: " + ($Fallas -join ', ') + " — carpeta $Dest ($totalMB MB)")
}

# Rotar log si pasa 1 MB
if ((Test-Path $LogFile) -and (Get-Item $LogFile).Length -gt 1MB) {
    Move-Item $LogFile "$LogFile.old" -Force
    Log 'Log rotado'
}

Log "================== FIN backup full =================="
if ($Fallas.Count -gt 0) { exit 1 } else { exit 0 }
