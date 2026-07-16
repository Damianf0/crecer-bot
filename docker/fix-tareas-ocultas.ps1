# Corre ELEVADO (UAC). Arregla las dos tareas que no se pudieron tocar sin admin:
#  1. CrecerTunnelWatchdog -> lanzador oculto (era la consola visible cada 2 min)
#  2. SyncAvatares -> recrear como WEEKLY SUN 05:00, SYSTEM, lanzador oculto
#     (estaba DAILY contra el disenio del propio script, y visible)
# Deja el resultado en backups\auto\fix-tareas.log para verificar desde la sesion normal.

$log = 'C:\crecer\backups\auto\fix-tareas.log'
function L($m) { Add-Content -Path $log -Value ("[{0}] {1}" -f (Get-Date -Format 'HH:mm:ss'), $m) }
Set-Content -Path $log -Value "=== fix-tareas-ocultas $(Get-Date -Format 'yyyy-MM-dd HH:mm') ==="

try {
    $a = New-ScheduledTaskAction -Execute 'wscript.exe' `
        -Argument '"C:\crecer\docker\run-hidden.vbs" "C:\crecer\docker\tunnel-watchdog.ps1"'
    Set-ScheduledTask -TaskPath '\' -TaskName 'CrecerTunnelWatchdog' -Action $a -ErrorAction Stop | Out-Null
    L 'CrecerTunnelWatchdog: OK (lanzador oculto)'
} catch { L ("CrecerTunnelWatchdog: ERROR " + $_.Exception.Message) }

try {
    $a2 = New-ScheduledTaskAction -Execute 'wscript.exe' `
        -Argument '"C:\crecer\docker\run-hidden.vbs" "C:\crecer\docker\sync-avatares.ps1"'
    $t2 = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Sunday -At 05:00
    $p2 = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
    Register-ScheduledTask -TaskPath '\Crecer\' -TaskName 'SyncAvatares' `
        -Action $a2 -Trigger $t2 -Principal $p2 -Force -ErrorAction Stop | Out-Null
    L 'SyncAvatares: OK (WEEKLY SUN 05:00, SYSTEM, oculto)'
} catch { L ("SyncAvatares: ERROR " + $_.Exception.Message) }

L 'fin'
