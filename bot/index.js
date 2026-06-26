require('dotenv').config();

const { pushLog } = require('./estado-bot');
const { logToFile } = require('./logger');
const { iniciarServidor } = require('./server');
const { iniciarWhatsApp } = require('./whatsapp');

// Redirigir console a 3 destinos: stdout (docker logs, cuando WSL no lo rompe),
// el buffer en memoria (panel) y un archivo en volumen (./bot/logs, sobrevive a
// los hipos de WSL que congelan docker logs — ver logger.js).
const _log   = console.log.bind(console);
const _error = console.error.bind(console);
const _warn  = console.warn.bind(console);

console.log   = (...a) => { const m = a.join(' '); _log(...a);   pushLog(m);            logToFile(m); };
console.error = (...a) => { const m = a.join(' '); _error(...a); pushLog('[ERROR] ' + m); logToFile('[ERROR] ' + m); };
console.warn  = (...a) => { const m = a.join(' '); _warn(...a);  pushLog('[WARN] '  + m); logToFile('[WARN] '  + m); };

// Handlers globales: loggean antes de morir para que el incidente quede
// en docker logs en lugar de un exit silencioso. Caso real 04/05/2026:
// ProtocolError en Client.inject mataba el proceso sin trace claro y el
// container reiniciaba. Ahora el bot intenta seguir; si el error es
// realmente fatal, restart unless-stopped lo levanta de nuevo.
process.on('unhandledRejection', (reason) => {
  const msg = reason instanceof Error ? reason.stack || reason.message : String(reason);
  console.error('[crecer-bot] unhandledRejection:', msg);
});

process.on('uncaughtException', (err) => {
  console.error('[crecer-bot] uncaughtException:', err.stack || err.message);
  // No process.exit acá — preferimos que docker reinicie si la cosa quedó
  // realmente rota (los reintentos internos de whatsapp.js cubren el caso normal).
});

async function main() {
  console.log('[crecer-bot] Iniciando...');
  iniciarServidor();
  await iniciarWhatsApp();
}

main().catch((err) => {
  console.error('[crecer-bot] Error fatal:', err.message);
  process.exit(1);
});
