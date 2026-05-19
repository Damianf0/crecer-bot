# Polling del /status del bot-test para refrescar el archivo qr-shadow.png con el
# QR vigente. Los QR de WhatsApp expiran cada ~20s y Baileys los regenera, por
# eso una sola captura no sirve para escanear. Este script sobrescribe la imagen
# cada 5s mientras el bot esté en "esperando_qr". Cuando llega a "listo",
# termina solo.

$OutFile = 'C:\crecer\qr-shadow.png'
$Url     = 'http://localhost:3009/status'
$Opened  = $false

Write-Host "Polling $Url ..."

while ($true) {
    try {
        $resp = Invoke-WebRequest $Url -UseBasicParsing -TimeoutSec 3
        $j = $resp.Content | ConvertFrom-Json

        if ($j.status -eq 'listo') {
            Write-Host "✓ LISTO — phone=$($j.phone). Fin del polling."
            break
        }

        if ($j.qrDataUrl) {
            $base64 = $j.qrDataUrl -replace '^data:image/png;base64,', ''
            [IO.File]::WriteAllBytes($OutFile, [Convert]::FromBase64String($base64))
            if (-not $Opened) {
                Start-Process $OutFile
                $Opened = $true
                Write-Host "QR abierto. Refrescando cada 5s. Volvé a abrir la imagen si tu visor no actualiza."
            } else {
                Write-Host "[$(Get-Date -Format HH:mm:ss)] QR actualizado (status=$($j.status))"
            }
        } else {
            Write-Host "[$(Get-Date -Format HH:mm:ss)] status=$($j.status) sin QR"
        }
    } catch {
        Write-Host "[$(Get-Date -Format HH:mm:ss)] error polling: $($_.Exception.Message)"
    }

    Start-Sleep -Seconds 5
}
