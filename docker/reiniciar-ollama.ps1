# Reinicio nocturno de Ollama (22/10). El llama-server de qwen3.5:4b acumula
# memoria sin devolverla: el 22/10 llegó a 8,3 GB en 46 h (el modelo pesa 3,1),
# la máquina se quedó sin RAM ni swap y el panel dejó de andar. Reiniciarlo
# libera todo; la precarga del compose vuelve a dejar el modelo listo.
# Mientras recarga (~1 min) los bots clasifican como FALLBACK.
#   powershell -NoProfile -ExecutionPolicy Bypass -File C:\crecer\docker\reiniciar-ollama.ps1
# Programado todos los días 03:30 (entre BackupMySQL 03:00 y SyncOmnia 04:00):
#   schtasks /Create /TN "Crecer\ReiniciarOllama" /SC DAILY /ST 03:30 /TR "wscript.exe \"C:\crecer\docker\run-hidden.vbs\" \"C:\crecer\docker\reiniciar-ollama.ps1\"" /F

$ErrorActionPreference = 'Continue'
$Log = 'C:\crecer\backups\auto\reiniciar-ollama.log'
$Modelo = 'qwen3.5:4b'

function Log($m) {
    $l = '[' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '] ' + $m
    Add-Content -Path $Log -Value $l -Encoding UTF8
}

Log '===== reinicio de ollama ====='
$antes = (docker stats --no-stream --format '{{.MemUsage}}' crecer-ollama-1) 2>$null
Log "memoria antes: $antes"

docker restart crecer-ollama-1 | Out-Null
$t0 = Get-Date
$ok = $false
do {
    Start-Sleep -Seconds 5
    $ps = (docker exec crecer-ollama-1 ollama ps) 2>$null
    $ok = ($ps -match [regex]::Escape($Modelo))
} while (-not $ok -and ((Get-Date) - $t0).TotalSeconds -lt 300)
$seg = [int]((Get-Date) - $t0).TotalSeconds
$despues = (docker stats --no-stream --format '{{.MemUsage}}' crecer-ollama-1) 2>$null
if ($ok) { Log "OK: $Modelo cargado a los $seg s; memoria despues: $despues"; exit 0 }
Log "ERROR: $Modelo no aparece cargado despues de $seg s"
exit 1
