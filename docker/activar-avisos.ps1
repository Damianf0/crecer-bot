# Activa los avisos del panel (05/10/2026): compila el chat React y reinicia el
# web para que tome el PHP y el layout nuevos. Programado una vez, fuera de
# horario, después del reinicio escalonado de los bots de las 19:05:
#   \Crecer\ActivarAvisos, 05/10/2026 19:45
# Si la compilación falla, NO reinicia el web (queda todo como estaba).

$ErrorActionPreference = 'Continue'
$Log = 'C:\crecer\backups\auto\activar-avisos.log'

function Log($m) {
    $l = '[' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '] ' + $m
    Add-Content -Path $Log -Value $l -Encoding UTF8
}

function Estado($puerto) {
    try { return (Invoke-RestMethod -Uri "http://127.0.0.1:$puerto/status" -TimeoutSec 4).status }
    catch { return 'sin respuesta' }
}

Log 'Inicio'

# No pisarse con el reinicio de los bots: esperar (hasta 30 min) a que los 3 estén listos.
$limite = (Get-Date).AddMinutes(30)
do {
    $estados = @(3001, 3002, 3003) | ForEach-Object { Estado $_ }
    if (($estados | Where-Object { $_ -ne 'listo' }).Count -eq 0) { break }
    Start-Sleep -Seconds 30
} while ((Get-Date) -lt $limite)
Log ('Bots: ' + ($estados -join ', '))

# 1) Compilar el chat (resources/js) en un contenedor descartable.
$salida = docker run --rm -v C:\crecer\app:/app -w /app node:20-alpine npm run build 2>&1
$codigo = $LASTEXITCODE
($salida | Select-Object -Last 8) | ForEach-Object { Log ('  build: ' + $_) }
if ($codigo -ne 0) {
    Log "La compilacion fallo (codigo $codigo): NO se reinicia el web. Revisar a mano."
    exit 1
}

# 2) Reiniciar el web y esperar a que responda.
docker restart crecer-web-1 | Out-Null
$inicio = Get-Date
$ok = $false
for ($i = 0; $i -lt 60; $i++) {
    Start-Sleep -Seconds 3
    try {
        $r = Invoke-WebRequest -Uri 'http://127.0.0.1/login' -UseBasicParsing -TimeoutSec 5
        if ($r.StatusCode -eq 200) { $ok = $true; break }
    } catch {}
}
$seg = [int]((Get-Date) - $inicio).TotalSeconds
if ($ok) { Log "Web reiniciado: responde 200 a los $seg s. Avisos activos." }
else     { Log "ATENCION: el web no respondio 200 en $seg s. Revisar a mano." }
