# Instala la tarea programada CrecerTunnelWatchdog. Requiere elevación.
# Triggers: cada 2 min + al login del usuario + al boot del host.
# Acción: corre tunnel-watchdog.ps1 que chequea broker:9091 y revive si está caído.

$ErrorActionPreference = 'Stop'
$taskName = 'CrecerTunnelWatchdog'
$user     = "$env:USERDOMAIN\$env:USERNAME"

if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
    Write-Host "Tarea existente, eliminando para reinstalar..."
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
}

$action   = New-ScheduledTaskAction -Execute 'powershell' `
              -Argument '-NoProfile -ExecutionPolicy Bypass -File C:\crecer\docker\tunnel-watchdog.ps1'

$trigRecurrente = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) `
                    -RepetitionInterval (New-TimeSpan -Minutes 2)
$trigLogon      = New-ScheduledTaskTrigger -AtLogOn -User $user
$trigBoot       = New-ScheduledTaskTrigger -AtStartup

$settings = New-ScheduledTaskSettingsSet `
              -AllowStartIfOnBatteries `
              -DontStopIfGoingOnBatteries `
              -StartWhenAvailable `
              -MultipleInstances IgnoreNew `
              -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

$principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger @($trigRecurrente, $trigLogon, $trigBoot) `
    -Settings $settings `
    -Principal $principal `
    -Description 'Revive el tunnel-broker (ngrok) si murió. Corre cada 2 min + al login + al boot. Idempotente: solo actúa si el puerto 9091 no escucha.'

Write-Host ""
Write-Host "OK: Tarea '$taskName' registrada." -ForegroundColor Green
Get-ScheduledTask -TaskName $taskName | Select-Object TaskName, State, @{n='Triggers';e={$_.Triggers.Count}} | Format-Table -AutoSize
Write-Host "Probando manualmente..."
Start-ScheduledTask -TaskName $taskName
Start-Sleep -Seconds 3
Get-ScheduledTaskInfo -TaskName $taskName | Select-Object LastRunTime, LastTaskResult, NumberOfMissedRuns | Format-List
Write-Host ""
Write-Host "Listo. Cerrá esta ventana." -ForegroundColor Green
Read-Host "Enter para cerrar"
