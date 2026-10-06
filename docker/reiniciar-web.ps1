# Reinicia el contenedor web (toma el codigo PHP y las vistas nuevas) y espera
# a que el panel vuelva a responder. Corte de 10 a 40 s: fuera de horario.
#   powershell -NoProfile -ExecutionPolicy Bypass -File C:\crecer\docker\reiniciar-web.ps1
# Una vez, programado (06/10 para el contador "en proceso" del menu):
#   schtasks /Create /TN "Crecer\ReiniciarWeb" /SC ONCE /SD 06/10/2026 /ST 19:05 /TR "wscript.exe \"C:\crecer\docker\run-hidden.vbs\" \"C:\crecer\docker\reiniciar-web.ps1\"" /F

$ErrorActionPreference = 'Continue'
$Log = 'C:\crecer\backups\auto\reiniciar-web.log'

function Log($m) {
    $l = '[' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '] ' + $m
    Add-Content -Path $Log -Value $l -Encoding UTF8
}

Log '===== reinicio del web ====='
docker restart crecer-web-1 | Out-Null
$t0 = Get-Date
$ok = $false
do {
    Start-Sleep -Seconds 3
    try { $ok = ((Invoke-WebRequest -UseBasicParsing -TimeoutSec 5 http://localhost/login).StatusCode -eq 200) } catch { $ok = $false }
} while (-not $ok -and ((Get-Date) - $t0).TotalSeconds -lt 180)
$seg = [int]((Get-Date) - $t0).TotalSeconds
if ($ok) { Log "OK: el panel responde 200 a los $seg s"; exit 0 }
Log "ERROR: el panel no responde 200 despues de $seg s"
exit 1
