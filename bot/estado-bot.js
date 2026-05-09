// Estado compartido entre whatsapp.js, mensajes.js y server.js

const startTime = Date.now();

const estado = {
  status: 'iniciando', // iniciando | esperando_qr | autenticado | listo | desconectado | error
  qrDataUrl: null,
  phone: null,
  logBuffer: [],
  logListeners: new Set(),

  // Modo prueba — siempre activo hasta que se habilite producción
  modoPrueba: true,

  // Buffer de clasificaciones para el panel de pruebas
  clasificaciones: [],
  clasificacionListeners: new Set(),
};

function setStatus(s) {
  estado.status = s;
  if (s !== 'esperando_qr') estado.qrDataUrl = null;
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
  setStatus, setQR, setPhone,
  pushLog, addLogListener, removeLogListener,
  pushClasificacion, addClasificacionListener, removeClasificacionListener,
  getUptime,
};
