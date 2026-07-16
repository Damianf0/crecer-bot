#!/bin/sh
# Healthcheck offline-aware de los bots WA (los 3 containers comparten ./bot:/app).
# exit 0 = healthy, exit 1 = unhealthy (autoheal reinicia el container).
#
# Regla: un bot que no está "listo" pero SIN salida a internet no está roto —
# reiniciarlo no arregla nada y cada restart arriesga la sesión (corte de
# internet 15/07: autoheal + watchdog reiniciaron en loop un bot sano).
# esperando_qr también es healthy: requiere escaneo humano, reiniciar solo
# regenera el QR en loop (incidente 01/07).

PORT="${PORT:-3001}"

# Node muerto/frozen (no responde ni HTTP local) → sí reiniciar
s=$(wget -qO- -T 4 "http://127.0.0.1:${PORT}/status" 2>/dev/null) || exit 1

echo "$s" | grep -qE '"status":"(listo|esperando_qr)"' && exit 0

# No está listo: ¿se puede llegar a WhatsApp? (DNS + TCP, sin TLS)
if node -e "const s=require('net').connect({host:'web.whatsapp.com',port:443,timeout:4000},()=>{s.destroy();process.exit(0)});s.on('error',()=>process.exit(1));s.on('timeout',()=>{s.destroy();process.exit(1)})" 2>/dev/null; then
  exit 1   # hay internet y el bot está mal → problema real, que autoheal actúe
else
  exit 0   # sin internet: esperar; el bot se reconecta solo cuando vuelva
fi
