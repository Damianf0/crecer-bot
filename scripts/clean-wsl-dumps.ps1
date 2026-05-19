$folder = "$env:LOCALAPPDATA\Temp\wsl-crashes"
if (-not (Test-Path $folder)) { exit 0 }

$dumps = Get-ChildItem -Path $folder -Filter "wsl-crash-*.dmp" -ErrorAction SilentlyContinue
if (-not $dumps) { exit 0 }

$totalGB = [math]::Round(($dumps | Measure-Object Length -Sum).Sum / 1GB, 2)
$count = $dumps.Count
$dumps | Remove-Item -Force -ErrorAction SilentlyContinue

$log = "$env:LOCALAPPDATA\Temp\wsl-crashes\clean.log"
"$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  borrados $count dumps ($totalGB GB)" | Out-File -Append -FilePath $log -Encoding utf8
