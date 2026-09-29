# Reinicia los bots de WhatsApp DE A UNO, esperando que cada uno quede "listo"
# antes de pasar al siguiente (si uno no vuelve, no se tocan los demas).
# Uso: cuando hay codigo nuevo del bot que aplicar fuera de horario.
#   powershell -NoProfile -ExecutionPolicy Bypass -File C:\crecer\docker\reiniciar-bots.ps1
# Una vez, programado (29/09 para el parche de envio de adjuntos):
#   schtasks /Create /TN "Crecer\ReiniciarBots" /SC ONCE /SD 29/09/2026 /ST 21:00 /TR "wscript.exe \"C:\crecer\docker\run-hidden.vbs\" \"C:\crecer\docker\reiniciar-bots.ps1\"" /F
#
# El arranque puede tardar hasta ~7 min: con la version nueva de WA Web la
# primera carga suele trabarse 6 min hasta que el boot-watchdog del bot limpia
# la cache (visto el 28/09). Por eso la espera por bot es de 12 min.
# El watchdog externo no pisa: no reinicia containers con < 10 min de vida.

$ErrorActionPreference = 'Continue'
$Log = 'C:\crecer\backups\auto\reiniciar-bots.log'
$Bots = @(
    @{ Servicio = 'bot-ovodonacion';    Puerto = 3003 },   # el de menos trafico primero
    @{ Servicio = 'bot-administracion'; Puerto = 3002 },
    @{ Servicio = 'bot';                Puerto = 3001 }
)
$EsperaMaxSeg = 720

function Log($m) {
    $l = '[' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '] ' + $m
    Add-Content -Path $Log -Value $l -Encoding UTF8
}

function Estado($puerto) {
    try { return (Invoke-RestMethod -Uri "http://127.0.0.1:$puerto/status" -TimeoutSec 4).status }
    catch { return 'sin respuesta' }
}

Log '===== reinicio de bots ====='
Set-Location C:\crecer
foreach ($b in $Bots) {
    $antes = Estado $b.Puerto
    Log ("{0}: estado antes = {1}; reiniciando" -f $b.Servicio, $antes)
    docker compose restart $b.Servicio 2>&1 | Out-Null
    $t0 = Get-Date
    $st = ''
    do {
        Start-Sleep -Seconds 10
        $st = Estado $b.Puerto
    } while ($st -ne 'listo' -and ((Get-Date) - $t0).TotalSeconds -lt $EsperaMaxSeg)
    $seg = [int]((Get-Date) - $t0).TotalSeconds
    if ($st -ne 'listo') {
        Log ("{0}: NO volvio a 'listo' en {1} s (estado: {2}). Corto aca: los demas no se tocan." -f $b.Servicio, $seg, $st)
        exit 1
    }
    Log ("{0}: listo en {1} s" -f $b.Servicio, $seg)
}
Log 'OK: los 3 bots listos con el codigo nuevo'
exit 0
