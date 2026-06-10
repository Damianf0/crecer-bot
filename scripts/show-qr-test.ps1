# Polling del bot-test para refrescar el archivo qr-shadow.png con el QR
# vigente. Los QR de WhatsApp expiran cada ~20s y Baileys los regenera, por
# eso una sola captura no sirve para escanear. Este script sobrescribe la imagen
# cada 5s mientras el bot esté en "esperando_qr". Cuando llega a "listo",
# termina solo.
#
# El QR ya no viene en /status (público): se pide a /qr con el BOT_INGRESS_TOKEN
# leído de bot\.env.

$OutFile   = 'C:\crecer\qr-shadow.png'
$StatusUrl = 'http://localhost:3009/status'
$QrUrl     = 'http://localhost:3009/qr'
$Opened    = $false

$tokenLine = Select-String -Path 'C:\crecer\bot\.env' -Pattern '^BOT_INGRESS_TOKEN='
$Token     = if ($tokenLine) { $tokenLine.Line -replace '^BOT_INGRESS_TOKEN=', '' } else { '' }
if (-not $Token) { Write-Host "⚠ No se pudo leer BOT_INGRESS_TOKEN de bot\.env"; exit 1 }
$Headers   = @{ Authorization = "Bearer $Token" }

Write-Host "Polling $StatusUrl ..."

while ($true) {
    try {
        $j = Invoke-RestMethod $StatusUrl -TimeoutSec 3

        if ($j.status -eq 'listo') {
            Write-Host "✓ LISTO — phone=$($j.phone). Fin del polling."
            break
        }

        if ($j.has_qr) {
            $q = Invoke-RestMethod $QrUrl -Headers $Headers -TimeoutSec 3
            if ($q.qrDataUrl) {
                $base64 = $q.qrDataUrl -replace '^data:image/png;base64,', ''
                [IO.File]::WriteAllBytes($OutFile, [Convert]::FromBase64String($base64))
                if (-not $Opened) {
                    Start-Process $OutFile
                    $Opened = $true
                    Write-Host "QR abierto. Refrescando cada 5s. Volvé a abrir la imagen si tu visor no actualiza."
                } else {
                    Write-Host "[$(Get-Date -Format HH:mm:ss)] QR actualizado (status=$($j.status))"
                }
            }
        } else {
            Write-Host "[$(Get-Date -Format HH:mm:ss)] status=$($j.status) sin QR"
        }
    } catch {
        Write-Host "[$(Get-Date -Format HH:mm:ss)] error polling: $($_.Exception.Message)"
    }

    Start-Sleep -Seconds 5
}
