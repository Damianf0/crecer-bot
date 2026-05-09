const { Client, LocalAuth } = require('whatsapp-web.js');
const QRCode = require('qrcode');
const fs = require('fs');
const path = require('path');
const { setStatus, setQR, setPhone } = require('./estado-bot');
const { recibirMensaje } = require('./mensajes');
const { guardarMensajeEntrante } = require('./mensajesApi');
const { registrarCliente } = require('./server');

const AUTH_PATH = '/app/.wwebjs_auth';

// Tracking de mensajes salientes que ENVIAMOS nosotros mismos (vía /enviar
// o respuestas automáticas). Los marcamos al crearlos para que el handler
// `message_create` no los duplique en el inbox como si fueran del celular.
// Map: wa_id => timestamp ms. Se limpia automáticamente tras 5 min.
const _recentlySent = new Map();
function markSent(waId) {
  if (!waId) return;
  _recentlySent.set(waId, Date.now());
  // Limpieza lazy
  const now = Date.now();
  for (const [k, t] of _recentlySent) {
    if (now - t > 5 * 60_000) _recentlySent.delete(k);
  }
}
function wasOurSent(waId) {
  return waId && _recentlySent.has(waId);
}
module.exports.markSent = markSent;
module.exports.wasOurSent = wasOurSent;

// Watchdog: si el cliente está "listo" pero no recibió nada en WATCHDOG_TIMEOUT ms, reinicia
const WATCHDOG_TIMEOUT  = parseInt(process.env.WATCHDOG_TIMEOUT  || String(45 * 60 * 1000)); // 45 min
const WATCHDOG_INTERVAL = parseInt(process.env.WATCHDOG_INTERVAL || String(5  * 60 * 1000)); // chequea c/5 min

const PUPPETEER_OPTS = {
  headless: true,
  executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium-browser',
  // Default de Puppeteer es 30s — corto para sendMessage en horarios con WhatsApp Web lento.
  // Subido a 180s para evitar "Runtime.callFunctionOn timed out" en envíos esporádicos.
  protocolTimeout: 180_000,
  args: [
    '--no-sandbox',
    '--disable-setuid-sandbox',
    '--disable-dev-shm-usage',
    '--disable-gpu',
    '--disable-extensions',
    '--disable-background-networking',
    '--disable-default-apps',
    '--mute-audio',
    '--no-first-run',
    // RAM del V8 en Chromium: 256 MB era ajustado para WhatsApp Web (que es pesado).
    // Subido a 512 MB. El container tiene >7 GB disponibles.
    '--js-flags=--max-old-space-size=512',
  ],
};

// User-agent moderno: el default de whatsapp-web.js es Chrome 101 (2022) y WhatsApp Web
// puede servir una versión legacy con esa UA, rompiendo los selectors que usa el evaluate().
const USER_AGENT_MODERNO = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

function crearCliente() {
  return new Client({
    authStrategy: new LocalAuth({ dataPath: AUTH_PATH }),
    puppeteer: PUPPETEER_OPTS,
    userAgent: USER_AGENT_MODERNO,
  });
}

function limpiarLocks() {
  try {
    const archivos = fs.readdirSync(AUTH_PATH, { recursive: true })
      .filter((f) => f.endsWith('SingletonLock') || f.endsWith('SingletonCookie'));
    for (const f of archivos) {
      const full = path.join(AUTH_PATH, f);
      fs.rmSync(full, { force: true });
      console.log(`[whatsapp] Lock eliminado: ${full}`);
    }
  } catch (_) {}
}

async function iniciarWhatsApp() {
  limpiarLocks();
  setStatus('iniciando');
  const client = crearCliente();

  let ultimaActividad = Date.now();
  let watchdogTimer   = null;
  let destruido        = false;

  function resetActividad() {
    ultimaActividad = Date.now();
  }

  function detenerWatchdog() {
    if (watchdogTimer) { clearInterval(watchdogTimer); watchdogTimer = null; }
  }

  async function verificarSalud() {
    if (destruido) return;
    const inactivo = Date.now() - ultimaActividad;

    // Chequear estado real via Puppeteer.
    // Timeout 30s: getState() puede tardar mientras hay un sendMessage en vuelo.
    let estadoReal = null;
    try {
      estadoReal = await Promise.race([
        client.getState(),
        new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 30_000)),
      ]);
    } catch (err) {
      console.warn(`[watchdog] No se pudo obtener estado: ${err.message}`);
    }

    console.log(`[watchdog] Estado: ${estadoReal ?? 'desconocido'} | Inactivo: ${Math.round(inactivo / 60000)}m`);

    // Solo reiniciar si pasó MUCHO tiempo sin actividad Y el estado no es CONNECTED.
    // Antes con OR el watchdog mataba el cliente apenas getState fallaba (destruyendo
    // sendMessage en vuelo). Ahora exigimos ambas condiciones: bot realmente colgado.
    const colgado = inactivo > WATCHDOG_TIMEOUT && estadoReal !== 'CONNECTED';

    if (colgado) {
      console.warn(`[watchdog] Cliente colgado (${Math.round(inactivo/60000)}m sin actividad) — reiniciando WhatsApp...`);
      destruido = true;
      detenerWatchdog();
      setStatus('reiniciando');
      try { await client.destroy(); } catch (_) {}
      setTimeout(() => iniciarWhatsApp(), 5_000);
    }
  }

  client.on('qr', async (qr) => {
    console.log('[whatsapp] QR recibido — esperando escaneo');
    resetActividad();
    try {
      const dataUrl = await QRCode.toDataURL(qr, { width: 300 });
      setQR(dataUrl);
    } catch (err) {
      console.error('[whatsapp] Error generando QR:', err.message);
    }
  });

  client.on('authenticated', () => {
    console.log('[whatsapp] Sesión autenticada.');
    resetActividad();
    setStatus('autenticado');
  });

  client.on('ready', async () => {
    const info = client.info;
    const phone = info?.wid?.user || null;
    setPhone(phone);
    setStatus('listo');
    registrarCliente(client);
    resetActividad();
    console.log(`[whatsapp] Cliente listo. Número: ${phone}`);

    // Arrancar watchdog
    detenerWatchdog();
    watchdogTimer = setInterval(verificarSalud, WATCHDOG_INTERVAL);
  });

  client.on('auth_failure', (msg) => {
    console.error('[whatsapp] Fallo de autenticación:', msg);
    detenerWatchdog();
    setStatus('error');
    process.exit(1);
  });

  client.on('disconnected', async (reason) => {
    if (destruido) return;
    destruido = true;
    console.warn('[whatsapp] Desconectado:', reason);
    detenerWatchdog();
    setStatus('desconectado');
    // Destruir el cliente actual antes de programar uno nuevo, para que no
    // queden dos instancias de Puppeteer en paralelo (causa de race condition
    // con eventos `authenticated` duplicados y ProtocolError en `Client.inject`).
    try { await client.destroy(); } catch (_) {}
    console.log('[whatsapp] Reiniciando en 10 segundos...');
    setTimeout(() => iniciarWhatsApp(), 10_000);
  });

  client.on('message', async (msg) => {
    if (msg.isGroupMsg || msg.fromMe) return;
    if (msg.from === 'status@broadcast') return;
    resetActividad();
    try {
      // Guardar en inbox primero (no bloqueante)
      guardarMensajeEntrante(msg).catch(e => console.error('[mensajesApi] Error guardando:', e.message));
      // Procesar para clasificación y respuesta automática
      await recibirMensaje(client, msg.from, msg);
    } catch (err) {
      console.error(`[whatsapp] Error procesando mensaje de ${msg.from}:`, err.message);
    }
  });

  // También resetear en eventos de presencia/ACK para no reiniciar en horas tranquilas
  client.on('message_ack',          resetActividad);
  client.on('contact_changed',      resetActividad);

  // message_create: dispara para TODOS los mensajes salientes (los que enviamos
  // nosotros via /enviar y los que se envían desde otro dispositivo del mismo
  // número, ej: el celular de la secretaria respondiendo a mano). Para los
  // nuestros (markSent al hacer sendMessage), saltamos la persistencia para
  // evitar duplicar lo que ya guardó Laravel. Los del celular sí se guardan.
  client.on('message_create', async (msg) => {
    resetActividad();
    if (!msg.fromMe) return;
    if (msg.isGroupMsg) return;
    if (msg.to === 'status@broadcast') return;
    const waId = msg.id?._serialized;
    if (wasOurSent(waId)) return;   // lo enviamos nosotros, ya está en BD
    console.log(`[whatsapp] saliente externo (celular) → ${msg.to}`);
    try {
      const { guardarMensajeSalienteExterno } = require('./mensajesApi');
      await guardarMensajeSalienteExterno(msg);
    } catch (err) {
      console.error('[whatsapp] Error guardando saliente externo:', err.message);
    }
  });

  // initialize() puede tirar ProtocolError si Chromium pierde el contexto durante
  // `Client.inject` (típico tras LOGOUT seguido de re-escaneo). Si eso pasa, en
  // vez de matar el proceso programamos un reintento limpio.
  try {
    await client.initialize();
  } catch (err) {
    if (destruido) return; // ya hay un reinicio programado vía `disconnected`
    destruido = true;
    console.error('[whatsapp] Error en initialize:', err.message);
    detenerWatchdog();
    setStatus('error');
    try { await client.destroy(); } catch (_) {}
    console.log('[whatsapp] Reintentando en 15 segundos...');
    setTimeout(() => iniciarWhatsApp(), 15_000);
  }
}

module.exports = { iniciarWhatsApp };
