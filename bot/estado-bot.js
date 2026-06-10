// Estado compartido entre whatsapp.js, mensajes.js y server.js

const fs = require('fs');
const path = require('path');
const { BOT_AREA } = require('./area');

const startTime = Date.now();

// modoPrueba define si el bot manda autorespuestas (false) o solo clasifica y
// deriva (true). Persistido en disco porque es comportamiento productivo: si
// vive solo en memoria, cada restart del container (watchdog de Chromium
// incluido) lo resetea en silencio. Un archivo por área porque los containers
// comparten el bind mount ./bot.
const MODO_PRUEBA_PATH = path.join(__dirname, `modo-prueba.${BOT_AREA}.json`);

function cargarModoPrueba() {
  try {
    const j = JSON.parse(fs.readFileSync(MODO_PRUEBA_PATH, 'utf-8'));
    if (typeof j.modoPrueba === 'boolean') return j.modoPrueba;
  } catch (_) {}
  return true; // sin archivo: no autorespondemos (default seguro)
}

const estado = {
  status: 'iniciando', // iniciando | esperando_qr | autenticado | listo | desconectado | error
  qrDataUrl: null,
  phone: null,
  logBuffer: [],
  logListeners: new Set(),

  modoPrueba: cargarModoPrueba(),

  // Buffer de clasificaciones para el panel de pruebas
  clasificaciones: [],
  clasificacionListeners: new Set(),
};

console.log(`[estado-bot] modoPrueba=${estado.modoPrueba} (${fs.existsSync(MODO_PRUEBA_PATH) ? 'persistido' : 'default'})`);

function setStatus(s) {
  estado.status = s;
  if (s !== 'esperando_qr') estado.qrDataUrl = null;
}

function setModoPrueba(v) {
  estado.modoPrueba = !!v;
  try {
    fs.writeFileSync(MODO_PRUEBA_PATH, JSON.stringify({ modoPrueba: estado.modoPrueba }) + '\n', 'utf-8');
  } catch (err) {
    console.error('[estado-bot] No se pudo persistir modoPrueba:', err.message);
  }
}

function setQR(dataUrl) {
  estado.qrDataUrl = dataUrl;
  estado.status = 'esperando_qr';
}

function setPhone(phone) {
  estado.phone = phone;
}

function pushLog(line) {
  const ts = new Date().toLocaleTimeString('es-AR', { hour12: false });
  const entry = `[${ts}] ${line}`;
  estado.logBuffer.push(entry);
  if (estado.logBuffer.length > 500) estado.logBuffer.shift();
  for (const fn of estado.logListeners) {
    try { fn(entry); } catch (_) {}
  }
}

function addLogListener(fn) { estado.logListeners.add(fn); }
function removeLogListener(fn) { estado.logListeners.delete(fn); }

/**
 * Registra una clasificación de Ollama en el buffer y notifica listeners.
 */
function pushClasificacion(entry) {
  estado.clasificaciones.push(entry);
  if (estado.clasificaciones.length > 100) estado.clasificaciones.shift();
  for (const fn of estado.clasificacionListeners) {
    try { fn(entry); } catch (_) {}
  }
}

function addClasificacionListener(fn) { estado.clasificacionListeners.add(fn); }
function removeClasificacionListener(fn) { estado.clasificacionListeners.delete(fn); }

function getUptime() {
  const ms = Date.now() - startTime;
  const h = Math.floor(ms / 3600000);
  const m = Math.floor((ms % 3600000) / 60000);
  const s = Math.floor((ms % 60000) / 1000);
  return `${h}h ${m}m ${s}s`;
}

module.exports = {
  estado,
  setStatus, setQR, setPhone, setModoPrueba,
  pushLog, addLogListener, removeLogListener,
  pushClasificacion, addClasificacionListener, removeClasificacionListener,
  getUptime,
};
