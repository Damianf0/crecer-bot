// Logging a archivo en volumen — independiente de la captura de stdout de Docker.
//
// Motivo (26/06/2026): tras un evento WSL / reinicio del daemon, Docker Desktop
// reanuda el container pero NO vuelve a enganchar el stream de stdout: el proceso
// sigue vivo y atendiendo, pero `docker logs` queda congelado en la última línea
// previa al evento. Resultado real: atención y ovo con logs muertos 9 días
// mientras funcionaban perfecto. Escribir a un archivo propio (en /app/logs, que
// por el bind-mount ./bot:/app cae en C:\crecer\bot\logs en el host) hace que el
// log sobreviva a esos hipos. Un archivo por área (BOT_AREA) para no colisionar
// entre los 3 bots que comparten el mismo /app.

const fs = require('fs');
const path = require('path');

const AREA      = process.env.BOT_AREA || 'bot';
const LOG_DIR   = process.env.BOT_LOG_DIR || path.join(__dirname, 'logs');
const LOG_FILE  = path.join(LOG_DIR, `bot-${AREA}.log`);
const MAX_BYTES = Number(process.env.BOT_LOG_MAX_BYTES) || 10 * 1024 * 1024; // 10 MB
const MAX_FILES = Number(process.env.BOT_LOG_MAX_FILES) || 5;                // bot-x.log + .1..(.4)

let stream = null;
let bytes  = 0;
let roto   = false; // si el logging a archivo falla, dejamos de intentar (no rompemos el bot)

function abrir() {
  try {
    fs.mkdirSync(LOG_DIR, { recursive: true });
    bytes  = fs.existsSync(LOG_FILE) ? fs.statSync(LOG_FILE).size : 0;
    stream = fs.createWriteStream(LOG_FILE, { flags: 'a' });
    stream.on('error', () => { roto = true; }); // disco lleno / permisos → no crashear
  } catch (_) {
    roto = true;
  }
}

// Rotación sincrónica por tamaño: bot-x.log → .1 → .2 ... y se descarta el más viejo.
function rotar() {
  try {
    if (stream) stream.end();
    for (let i = MAX_FILES - 1; i >= 1; i--) {
      const viejo = i === 1 ? LOG_FILE : `${LOG_FILE}.${i - 1}`;
      const nuevo = `${LOG_FILE}.${i}`;
      if (fs.existsSync(viejo)) {
        try { fs.renameSync(viejo, nuevo); } catch (_) {}
      }
    }
  } catch (_) {
    // ignoramos: peor caso, el archivo sigue creciendo
  }
  bytes = 0;
  abrir();
}

// Timestamp local (respeta TZ del container; formato sv-SE = "YYYY-MM-DD HH:MM:SS").
function ts() {
  try {
    return new Date().toLocaleString('sv-SE', { timeZone: process.env.TZ || 'UTC' });
  } catch (_) {
    return new Date().toISOString();
  }
}

abrir();

function logToFile(linea) {
  if (roto || !stream) return;
  const out = `${ts()} ${linea}\n`;
  try {
    stream.write(out);
    bytes += Buffer.byteLength(out);
    if (bytes >= MAX_BYTES) rotar();
  } catch (_) {
    roto = true;
  }
}

module.exports = { logToFile, LOG_FILE };
