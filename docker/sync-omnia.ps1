# Sincronización diaria de contactos desde Omnia — Crecer
#
# Trae los pacientes de los turnos de la ventana [hoy-10d, hoy+60d] y completa
# el directorio de contactos: crea los que tengan celular normalizable y rellena
# campos vacíos (dni/email/nacimiento) de los que ya existen. Nunca pisa datos
# cargados a mano; los conflictos los reporta y los saltea.
#
# Corre ANTES que MapearWA (04:30) a propósito: los contactos que crea entran
# al mapeo de wa_id de esa misma madrugada, y de ahí a sync-avatares.
#
# Programado en Windows:
#   schtasks /Create /SC DAILY /ST 04:00 /TN "Crecer\SyncOmnia" `
#     /TR "powershell -NoProfile -ExecutionPolicy Bypass -File C:\crecer\docker\sync-omnia.ps1" /F

# La salida de artisan viene en UTF-8; sin esto PowerShell la decodifica con la
# codepage OEM de la consola y los acentos llegan rotos al log.
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$LogFile = 'C:\crecer\backups\auto\sync-omnia.log'
New-Item -ItemType Directory -Path (Split-Path $LogFile) -Force | Out-Null

function Log($msg) {
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $msg"
    Write-Output $line
    # -Encoding UTF8: la salida del container viene en UTF-8; sin esto los
    # acentos quedan como mojibake en el log.
    Add-Content -Path $LogFile -Value $line -Encoding UTF8
}

Log "=== Inicio sync-omnia ==="

# -u www-data en TODOS los artisan: como root, el primer warning del día crea
# el log de Laravel con dueño root y la web ya no puede escribirlo (23/09).
#
# Sonda previa: si Omnia no responde no tiene sentido seguir, y queremos que el
# motivo quede escrito (credenciales vencidas, ambiente caído, red).
$status = cmd /c "docker exec -u www-data crecer-web-1 php //var/www/html/artisan omnia:status 2>&1"
$statusOk = ($LASTEXITCODE -eq 0)
$status | ForEach-Object { Log "  $_" }

if (-not $statusOk) {
    Log "ALERTA: Omnia no responde — se saltea la sincronización de hoy."
    Log "=== Fin sync-omnia (sin ejecutar) ==="
    exit 1
}

# Ventana corta: los turnos recién agendados y los próximos 2 meses. Alcanza
# para que un paciente nuevo esté en el directorio antes de su turno, y evita
# los rangos largos que Omnia tarda ~110s por cada 6 meses.
$desde = (Get-Date).AddDays(-10).ToString('yyyy-MM-dd')
$hasta = (Get-Date).AddDays(60).ToString('yyyy-MM-dd')
Log "Ventana: $desde → $hasta"

$tmpErr = [System.IO.Path]::GetTempFileName()
$out = cmd /c "docker exec -u www-data crecer-web-1 php //var/www/html/artisan contactos:sync-omnia --desde=$desde --hasta=$hasta --apply --muestra=0 2>$tmpErr"
$syncExit = $LASTEXITCODE
$out | ForEach-Object { Log $_ }

$err = Get-Content $tmpErr -Raw -ErrorAction SilentlyContinue
if ($err) { Log "stderr: $err" }
Remove-Item $tmpErr -ErrorAction SilentlyContinue

# Exit 1 del comando = Omnia rechazó algún día del rango (el 10/09/2025 lo hace
# siempre). El resto del rango sí se sincronizó: es un aviso, no una falla.
if ($syncExit -ne 0) {
    Log "AVISO: hubo días que Omnia rechazó — ver 'Días que Omnia rechazó' arriba."
}

# Catálogo de financiadores y prácticas para las reglas del checklist de
# recepción (/v2/recepcion/reglas). Últimos 30 días alcanza para sumar lo nuevo:
# el comando nunca borra lo que ya estaba.
$cat = cmd /c "docker exec -u www-data crecer-web-1 php //var/www/html/artisan omnia:catalogo --dias=30 2>&1"
$catExit = $LASTEXITCODE
$cat | ForEach-Object { Log "  catálogo: $_" }
if ($catExit -ne 0) { Log "AVISO: el catálogo quedó incompleto (Omnia rechazó algún tramo); se completa mañana." }

# Rotación de log si pasa de 1 MB
if ((Test-Path $LogFile) -and (Get-Item $LogFile).Length -gt 1MB) {
    Move-Item $LogFile "$LogFile.old" -Force
    Log "Log rotado"
}

Log "=== Fin sync-omnia ==="
