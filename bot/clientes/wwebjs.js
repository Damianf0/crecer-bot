// Wrapper de whatsapp-web.js que cumple la interfaz definida en bot/cliente-wa.js.
// Toda la lógica histórica (Puppeteer, watchdog, limpieza de cache de Chromium,
// limpieza de locks, reintentos) vive acá.

const { EventEmitter } = require('events');
const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const QRCode = require('qrcode');
const fs = require('fs');
const path = require('path');

const AUTH_PATH = '/app/.wwebjs_auth';

// Borra SOLO el cache de Chromium (Cache, Code Cache, GPUCache, Service Worker/CacheStorage…)
// preservando IndexedDB / Local Storage / Cookies / Login Data → la sesión WhatsApp NO se pierde.
// El cache de WA Web se hincha muy rápido (Code Cache de V8 sobre todo) y termina saturando las
// operaciones CDP → "Runtime.callFunctionOn timed out". Llamado en cada reinicio del watchdog
// (Chromium ya está muerto ahí, los archivos están libres) para que arranque con cache vacío.
function limpiarCacheChromium() {
  const base = path.join(AUTH_PATH, 'session', 'Default');
  const dirs = ['Cache', 'Code Cache', 'GPUCache', 'DawnGraphiteCache', 'DawnWebGPUCache',
                path.join('Service Worker', 'CacheStorage'), path.join('Service Worker', 'ScriptCache')];
  let n = 0;
  for (const d of dirs) {
    const full = path.join(base, d);
    try {
      if (fs.existsSync(full)) { fs.rmSync(full, { recursive: true, force: true }); n++; }
    } catch (_) { /* best-effort */ }
  }
  if (n) console.log(`[whatsapp] Cache de Chromium limpiado (${n} carpetas)`);
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

const WATCHDOG_TIMEOUT  = parseInt(process.env.WATCHDOG_TIMEOUT  || String(20 * 60 * 1000));
const WATCHDOG_INTERVAL = parseInt(process.env.WATCHDOG_INTERVAL || String(5  * 60 * 1000));
const WATCHDOG_MAX_SIN_CONNECTED = parseInt(process.env.WATCHDOG_MAX_SIN_CONNECTED || '3');

const PUPPETEER_OPTS = {
  headless: true,
  executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium-browser',
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
    '--js-flags=--max-old-space-size=512',
  ],
};

const USER_AGENT_MODERNO = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

// Tipo nativo de whatsapp-web.js → tipo normalizado del adapter
function normalizarTipo(t) {
  if (t === 'chat')               return 'texto';
  if (t === 'ptt' || t === 'audio') return 'audio';
  if (t === 'image')              return 'imagen';
  if (t === 'video')              return 'video';
  if (t === 'document')           return 'documento';
  if (t === 'sticker')            return 'sticker';
  return null; // tipos no soportados
}

function crearClienteWwebjs() {
  const emitter = new EventEmitter();

  // Tracking interno de mensajes enviados por nosotros (sendText/sendMedia)
  // para que 'message_outgoing' no dispare sobre ellos. TTL 5 min.
  const _recentlySent = new Map();
  function marcarEnviado(waId) {
    if (!waId) return;
    _recentlySent.set(waId, Date.now());
    const now = Date.now();
    for (const [k, t] of _recentlySent) if (now - t > 5 * 60_000) _recentlySent.delete(k);
  }
  function fueEnviadoPorNosotros(waId) {
    return waId && _recentlySent.has(waId);
  }

  let client = null;
  let ultimaActividad = Date.now();
  let watchdogTimer  = null;
  let destruido      = false;
  let chequeosSinConnected = 0;

  function resetActividad() { ultimaActividad = Date.now(); }

  function detenerWatchdog() {
    if (watchdogTimer) { clearInterval(watchdogTimer); watchdogTimer = null; }
  }

  async function reiniciarPorWatchdog(motivo) {
    console.warn(`[watchdog] Cliente colgado (${motivo}) — reiniciando WhatsApp...`);
    destruido = true;
    detenerWatchdog();
    emitter.emit('disconnected', `watchdog:${motivo}`);
    try { await client.destroy(); } catch (_) {}
    limpiarCacheChromium();
    setTimeout(iniciar, 5_000);
  }

  async function verificarSalud() {
    if (destruido) return;
    const inactivo = Date.now() - ultimaActividad;

    let estadoReal = null;
    try {
      estadoReal = await Promise.race([
        client.getState(),
        new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 30_000)),
      ]);
    } catch (err) {
      console.warn(`[watchdog] No se pudo obtener estado: ${err.message}`);
    }

    if (estadoReal === 'CONNECTED') chequeosSinConnected = 0;
    else chequeosSinConnected++;

    console.log(`[watchdog] Estado: ${estadoReal ?? 'desconocido'} | Inactivo: ${Math.round(inactivo / 60000)}m | sin CONNECTED: ${chequeosSinConnected}`);

    if (chequeosSinConnected >= WATCHDOG_MAX_SIN_CONNECTED) {
      return reiniciarPorWatchdog(`${chequeosSinConnected} chequeos seguidos sin CONNECTED (Chromium congelado)`);
    }
    if (inactivo > WATCHDOG_TIMEOUT && estadoReal !== 'CONNECTED') {
      return reiniciarPorWatchdog(`${Math.round(inactivo/60000)}m sin actividad`);
    }
  }

  // Resuelve el bloque "quoted" cuando el msg es reply. Asíncrono porque
  // wwebjs trae el original via getQuotedMessage() que ejecuta dentro de
  // Chromium. Timeout corto: si tarda >2s o falla, devolvemos null sin
  // bloquear el flow de entrada.
  async function extraerQuoted(msg) {
    if (!msg.hasQuotedMsg) return null;
    try {
      const quoted = await Promise.race([
        msg.getQuotedMessage(),
        new Promise((_, rej) => setTimeout(() => rej(new Error('quoted timeout')), 2000)),
      ]);
      if (!quoted) return null;
      const tipoQ = normalizarTipo(quoted.type) || 'texto';
      let preview = quoted.body || '';
      if (!preview) {
        if (tipoQ === 'audio')     preview = '🎤 Audio';
        else if (tipoQ === 'imagen')   preview = '🖼️ Imagen';
        else if (tipoQ === 'video')    preview = '🎬 Video';
        else if (tipoQ === 'documento') preview = '📄 Documento';
        else if (tipoQ === 'sticker')   preview = '😀 Sticker';
      }
      return {
        wa_id:   quoted.id?._serialized || '',
        autor:   null,
        preview: preview.slice(0, 280),
      };
    } catch (_) {
      return null;
    }
  }

  async function envolverMensaje(msg) {
    const tipo = normalizarTipo(msg.type);
    if (!tipo) return null;
    let body = null;
    if (tipo === 'texto')   body = msg.body || null;
    else                    body = msg.body || null; // caption (imagen/video/doc) o null
    const quoted = await extraerQuoted(msg);
    return {
      from: msg.from,
      to:   msg.to,
      fromMe: !!msg.fromMe,
      type: tipo,
      body,
      wa_id: msg.id?._serialized || '',
      quoted,
      timestamp: new Date((msg.timestamp || Date.now()/1000) * 1000),
      downloadMedia: async () => {
        try {
          const media = await msg.downloadMedia();
          if (!media) return null;
          return { mimetype: media.mimetype, data: media.data };
        } catch (_) { return null; }
      },
    };
  }

  function iniciar() {
    limpiarLocks();
    destruido = false;
    chequeosSinConnected = 0;

    client = new Client({
      authStrategy: new LocalAuth({ dataPath: AUTH_PATH }),
      puppeteer: PUPPETEER_OPTS,
      userAgent: USER_AGENT_MODERNO,
    });

    client.on('qr', async (qr) => {
      console.log('[whatsapp] QR recibido — esperando escaneo');
      resetActividad();
      try {
        const dataUrl = await QRCode.toDataURL(qr, { width: 300 });
        emitter.emit('qr', dataUrl);
      } catch (err) {
        console.error('[whatsapp] Error generando QR:', err.message);
      }
    });

    client.on('authenticated', () => {
      console.log('[whatsapp] Sesión autenticada.');
      resetActividad();
    });

    client.on('ready', async () => {
      const info = client.info;
      const phone = info?.wid?.user || null;
      resetActividad();
      console.log(`[whatsapp] Cliente listo. Número: ${phone}`);
      emitter.emit('ready', { phone });
      detenerWatchdog();
      watchdogTimer = setInterval(verificarSalud, WATCHDOG_INTERVAL);
    });

    client.on('auth_failure', (msg) => {
      console.error('[whatsapp] Fallo de autenticación:', msg);
      detenerWatchdog();
      emitter.emit('disconnected', `auth_failure:${msg}`);
      process.exit(1);
    });

    client.on('disconnected', async (reason) => {
      if (destruido) return;
      destruido = true;
      console.warn('[whatsapp] Desconectado:', reason);
      detenerWatchdog();
      emitter.emit('disconnected', reason);
      try { await client.destroy(); } catch (_) {}
      console.log('[whatsapp] Reiniciando en 10 segundos...');
      setTimeout(iniciar, 10_000);
    });

    client.on('message', async (msg) => {
      if (msg.isGroupMsg || msg.fromMe) return;
      if (msg.from === 'status@broadcast' || msg.from?.endsWith('@broadcast')) return;
      resetActividad();
      const m = await envolverMensaje(msg);
      if (m) emitter.emit('message', m);
    });

    client.on('message_ack',     resetActividad);
    client.on('contact_changed', resetActividad);

    client.on('message_create', async (msg) => {
      resetActividad();
      if (!msg.fromMe) return;
      if (msg.isGroupMsg) return;
      if (msg.to === 'status@broadcast') return;
      const waId = msg.id?._serialized;
      if (fueEnviadoPorNosotros(waId)) return; // lo mandó el bot por sendText/sendMedia
      console.log(`[whatsapp] saliente externo (celular) → ${msg.to}`);
      const m = await envolverMensaje(msg);
      if (m) emitter.emit('message_outgoing', m);
    });

    client.initialize().catch(async (err) => {
      if (destruido) return;
      destruido = true;
      console.error('[whatsapp] Error en initialize:', err.message);
      detenerWatchdog();
      emitter.emit('disconnected', `initialize:${err.message}`);
      try { await client.destroy(); } catch (_) {}
      console.log('[whatsapp] Reintentando en 15 segundos...');
      setTimeout(iniciar, 15_000);
    });
  }

  iniciar();

  // ── Métodos expuestos por la interfaz ────────────────────
  emitter.sendText = async (jid, texto, opts = {}) => {
    const sendOpts = {};
    // wwebjs acepta el wa_id serializado del original directamente; resuelve
    // adentro la búsqueda en su store de Chromium. Si el original ya no está,
    // descarta silenciosamente el quote (el mensaje igual se envía).
    if (opts.quoted?.wa_id) sendOpts.quotedMessageId = opts.quoted.wa_id;
    const sent = await client.sendMessage(jid, texto, sendOpts);
    const waId = sent?.id?._serialized || '';
    marcarEnviado(waId);
    return { wa_id: waId };
  };

  emitter.sendMedia = async (jid, { mimetype, base64, filename, caption }) => {
    const media = new MessageMedia(mimetype, base64, filename || 'archivo');
    const opts = caption ? { caption } : {};
    const sent = await client.sendMessage(jid, media, opts);
    const waId = sent?.id?._serialized || '';
    marcarEnviado(waId);
    return { wa_id: waId };
  };

  emitter.checkNumber = async (digits) => {
    const id = await client.getNumberId(String(digits).replace(/\D/g, ''));
    if (!id) return { registered: false, normalizedId: null };
    return {
      registered: true,
      normalizedId: id._serialized || `${id.user}@${id.server}`,
    };
  };

  emitter.resolveContact = async (jid) => {
    const c = await client.getContactById(jid);
    return {
      numero: c?.number ? String(c.number).replace(/\D/g, '') : null,
      name:   c?.pushname || c?.name || null,
    };
  };

  // NOTA: al 2026-05-03, WhatsApp Web tiene un bug que rompe todas las APIs
  // de profile pic con "Cannot read properties of undefined (reading
  // 'isNewsletter')". Probamos varios paths y devolvemos null si todos
  // fallan — el frontend cae al fallback con la inicial.
  emitter.getProfilePicUrl = async (jid) => {
    let url = null;
    try { url = await client.getProfilePicUrl(jid); } catch {}
    if (!url) {
      try {
        const contact = await client.getContactById(jid);
        if (contact && typeof contact.getProfilePicUrl === 'function') {
          url = await contact.getProfilePicUrl();
        }
      } catch {}
    }
    if (!url) {
      try {
        url = await client.pupPage.evaluate(async (jid) => {
          try {
            const wid = window.Store?.WidFactory?.createWid
              ? window.Store.WidFactory.createWid(jid)
              : (window.WWebJS?.getWid ? window.WWebJS.getWid(jid) : null);
            if (!wid) return null;
            const pp = window.Store?.ProfilePic;
            if (!pp) return null;
            for (const m of ['profilePicFind', 'requestProfilePicFromServer', 'profilePicResync']) {
              if (typeof pp[m] === 'function') {
                try {
                  const r = await pp[m](wid);
                  if (r && (r.eurl || r.imgFull || r.url)) return r.eurl || r.imgFull || r.url;
                } catch {}
              }
            }
            return null;
          } catch { return null; }
        }, jid);
      } catch {}
    }
    return url || null;
  };

  emitter.getState = () => {
    if (destruido) return 'disconnected';
    return 'unknown'; // estado real es asincrónico vía client.getState() — no lo bloqueamos acá
  };

  emitter.destroy = async () => {
    destruido = true;
    detenerWatchdog();
    try { await client.destroy(); } catch (_) {}
  };

  return emitter;
}

module.exports = { crearClienteWwebjs };
