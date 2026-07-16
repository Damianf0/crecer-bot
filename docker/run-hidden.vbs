' Lanzador invisible para tareas programadas (16/07).
' Las tareas Crecer quedaron registradas como usuario/Interactive: cada corrida
' abria una consola PowerShell visible (TunnelWatchdog cada 2 min, WatchdogBot
' cada 5). wscript + Run con estilo 0 = completamente oculto, sin flash.
' Uso:  wscript.exe "C:\crecer\docker\run-hidden.vbs" "C:\ruta\script.ps1"
Set sh = CreateObject("WScript.Shell")
sh.Run "powershell -NoProfile -ExecutionPolicy Bypass -File """ & WScript.Arguments(0) & """", 0, False
