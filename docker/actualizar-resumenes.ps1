# Corrige los resumenes de las conversaciones de los ultimos 7 dias (23/10):
# borra los de consultas anteriores y encola los nuevos, espaciados.
# Va despues del reinicio del web (codigo nuevo cargado). Se programo una vez:
#   tarea Crecer\ActualizarResumenes, 23/10/2026 21:15
$ErrorActionPreference = 'Continue'
$Log = 'C:\crecer\backups\auto\actualizar-resumenes.log'
function Log($m) { Add-Content -Path $Log -Value ('[' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '] ' + $m) -Encoding UTF8 }

Log '===== actualizar resumenes ====='
$out = docker exec -u www-data crecer-web-1 php artisan conversaciones:actualizar-resumenes --apply 2>&1
$out | ForEach-Object { Log $_ }
Log ("exit: {0}" -f $LASTEXITCODE)
