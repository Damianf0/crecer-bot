# Sincronización semanal de fotos de perfil de WhatsApp — Crecer
# Refresca avatares de contactos con wa_id resuelto que tengan TTL vencido (7 días por default).
#
# Programado en Windows (correr como admin):
#   schtasks /Create /SC WEEKLY /D SUN /ST 05:00 /TN "Crecer\SyncAvatares" `
#     /TR "powershell -NoProfile -ExecutionPolicy Bypass -File C:\crecer\docker\sync-avatares.ps1" /F /RU SYSTEM

$LogFile = 'C:\crecer\backups\auto\sync-avatares.log'
New-Item -ItemType Directory -Path (Split-Path $LogFile) -Force | Out-Null

function Log($msg) {
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $msg"
    Write-Output $line
    Add-Content -Path $LogFile -Value $line
}

Log "=== Inicio sync avatares ==="

$tmpErr = [System.IO.Path]::GetTempFileName()
$out = cmd /c "docker exec crecer-web-1 php //var/www/html/artisan contactos:sync-avatares 2>$tmpErr"
$out | ForEach-Object { Log $_ }

$err = Get-Content $tmpErr -Raw -ErrorAction SilentlyContinue
if ($err) { Log "stderr: $err" }
Remove-Item $tmpErr -ErrorAction SilentlyContinue

# Rotación de log si pasa de 1 MB
if ((Test-Path $LogFile) -and (Get-Item $LogFile).Length -gt 1MB) {
    Move-Item $LogFile "$LogFile.old" -Force
    Log "Log rotado"
}

Log "=== Fin sync avatares ==="
