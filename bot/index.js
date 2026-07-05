require('dotenv').config();

const { pushLog } = require('./estado-bot');
const { logToFile } = require('./logger');
const { iniciarServidor } = require('./server');
const { iniciarWhatsApp, obtenerCliente } = require('./whatsapp');

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

// Apagado limpio: node es PID1 en el container, así que `docker stop` manda
// SIGTERM directo acá. Sin este handler, node moría al instante y Chromium
// quedaba huérfano escribiendo LevelDB a medias — ESA era la corrupción que
// mataba la sesión de atención en los reinicios nocturnos (29/06, 01/07,
// 04/07). Cerrar el cliente flushea la sesión y dispara el snapshot.
// docker stop usa -t 30 en el backup; acá nos autolimitamos a 25s.
let _apagando = false;
async function apagadoLimpio(senal) {
  if (_apagando) return;
  _apagando = true;
  console.log(`[crecer-bot] ${senal} recibido — cerrando cliente WhatsApp limpio...`);
  const cliente = obtenerCliente();
  if (cliente && typeof cliente.destroy === 'function') {
    try {
      await Promise.race([
        cliente.destroy(),
        new Promise((r) => setTimeout(r, 25_000)),
      ]);
    } catch (e) {
      console.warn('[crecer-bot] Error cerrando cliente:', e.message);
    }
  }
  console.log('[crecer-bot] Apagado limpio completo.');
  process.exit(0);
}

process.on('SIGTERM', () => apagadoLimpio('SIGTERM'));
process.on('SIGINT',  () => apagadoLimpio('SIGINT'));

async function main() {
  console.log('[crecer-bot] Iniciando...');
  iniciarServidor();
  await iniciarWhatsApp();
}

main().catch((err) => {
  console.error('[crecer-bot] Error fatal:', err.message);
  process.exit(1);
});
