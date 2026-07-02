# Backup automático de MySQL (clinica) — Crecer
# Genera dump + rota retenciones: 7 diarios, 4 semanales (domingo), 12 mensuales (1ro de mes)
#
# Para programarlo en Windows:
#   schtasks /Create /SC DAILY /ST 03:00 /TN "Crecer\BackupMySQL" `
#     /TR "powershell -NoProfile -ExecutionPolicy Bypass -File C:\crecer\docker\backup-mysql.ps1" /F /RU SYSTEM

$BackupRoot = 'C:\crecer\backups\auto'
$Today      = Get-Date -Format 'yyyyMMdd-HHmmss'
$DowName    = (Get-Date).DayOfWeek.ToString().ToLower()
$Day        = (Get-Date).Day

New-Item -ItemType Directory -Path "$BackupRoot\daily","$BackupRoot\weekly","$BackupRoot\monthly" -Force | Out-Null

$DailyFile = "$BackupRoot\daily\clinica-$Today.sql.gz"

# Password leída de C:\crecer\.env (no hardcodear credenciales en scripts).
$RootPwd = (Get-Content 'C:\crecer\.env' -ErrorAction SilentlyContinue |
    Where-Object { $_ -match '^DB_ROOT_PASSWORD=' } |
    ForEach-Object { ($_ -split '=', 2)[1].Trim() }) | Select-Object -First 1
if (-not $RootPwd) {
    Write-Output '[backup] ERROR: DB_ROOT_PASSWORD no encontrada en C:\crecer\.env'
    exit 1
}

# Dump comprimido — usa MYSQL_PWD via -e para evitar warning de password en CLI.
# Redirección hecha en cmd.exe (no PowerShell) para preservar bytes binarios del gzip.
$tmpErr = [System.IO.Path]::GetTempFileName()
$cmd = "docker exec -e MYSQL_PWD=$RootPwd crecer-mysql-1 sh -c `"mysqldump --single-transaction --quick --routines --triggers -uroot clinica 2>/dev/null | gzip`""
cmd /c "$cmd > `"$DailyFile`" 2> `"$tmpErr`""

$dumpStderr = Get-Content $tmpErr -Raw -ErrorAction SilentlyContinue
Remove-Item $tmpErr -ErrorAction SilentlyContinue

if (-not (Test-Path $DailyFile) -or (Get-Item $DailyFile).Length -lt 1024) {
    Write-Output "[backup] ERROR: dump fallido o vacío"
    if ($dumpStderr) { Write-Output "[backup] stderr: $dumpStderr" }
    exit 1
}

$Size = [math]::Round((Get-Item $DailyFile).Length / 1KB, 1)
Write-Output "[backup] OK $DailyFile ($Size KB)"

# Copia semanal (domingos)
if ($DowName -eq 'sunday') {
    Copy-Item $DailyFile -Destination "$BackupRoot\weekly\clinica-$Today.sql.gz"
    Write-Output "[backup] Copia semanal generada"
}

# Copia mensual (día 1)
if ($Day -eq 1) {
    Copy-Item $DailyFile -Destination "$BackupRoot\monthly\clinica-$Today.sql.gz"
    Write-Output "[backup] Copia mensual generada"
}

# Rotación: diarios=7, semanales=4, mensuales=12
function Rotate($dir, $keep) {
    Get-ChildItem $dir -Filter '*.sql.gz' |
        Sort-Object LastWriteTime -Descending |
        Select-Object -Skip $keep |
        ForEach-Object {
            Remove-Item $_.FullName -Force
            Write-Output "[backup] Eliminado por rotación: $($_.Name)"
        }
}

Rotate "$BackupRoot\daily"   7
Rotate "$BackupRoot\weekly"  4
Rotate "$BackupRoot\monthly" 12

Write-Output "[backup] Finalizado $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
