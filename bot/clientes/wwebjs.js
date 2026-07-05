// Wrapper de whatsapp-web.js que cumple la interfaz definida en bot/cliente-wa.js.
// Toda la lógica histórica (Puppeteer, watchdog, limpieza de cache de Chromium,
// limpieza de locks, reintentos) vive acá.

const { EventEmitter } = require('events');
const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const QRCode = require('qrcode');
const fs = require('fs');
const dns = require('dns');
const path = require('path');

const AUTH_PATH = '/app/.wwebjs_auth';

// ── Snapshot de sesión + auto-restauración ────────────────────────────
// Diagnóstico 05/07: las "muertes" de sesión (29/06, 01/07, 04/07) eran
// corrupción LOCAL de IndexedDB/LevelDB por apagados sucios de Chromium —
// el servidor de WhatsApp nunca invalidó el pareo (restaurar un tar de un
// día antes autenticó al primer intento). Estrategia:
//   1. Al apagar limpio (SIGTERM → emitter.destroy) con sesión que llegó a
//      'ready' en esta corrida: copiar la sesión (quiescente y sana) a un
//      snapshot dentro del mismo volumen.
//   2. Si al arrancar aparece QR pero el marker dice que había sesión válida:
//      restaurar el snapshot y reintentar (máx 2 veces) antes de rendirse.
const SESSION_DIR = path.join(AUTH_PATH, 'session');
const SNAP_DIR    = path.join(AUTH_PATH, 'session-snapshot');
const MARKER_FILE = path.join(AUTH_PATH, 'sesion-valida.json');

// Igual que el tar del backup (restauración probada 05/07): la sesión real
// vive en IndexedDB/Local Storage/Cookies; caches y Service Worker son
// regenerables y pesados.
const SNAP_EXCLUIR = new Set(['Cache', 'Code Cache', 'GPUCache',
  'DawnGraphiteCache', 'DawnWebGPUCache', 'Service Worker']);

function copiarSesion(desde, hacia) {
  fs.rmSync(hacia, { recursive: true, force: true });
  fs.cpSync(desde, hacia, {
    recursive: true,
    filter: (src) => {
      const base = path.basename(src);
      if (base.startsWith('Singleton')) return false;
      return !SNAP_EXCLUIR.has(base);
    },
  });
}

function leerMarker() {
  try { return JSON.parse(fs.readFileSync(MARKER_FILE, 'utf8')); } catch (_) { return null; }
}

function escribirMarker(obj) {
  try { fs.writeFileSync(MARKER_FILE, JSON.stringify(obj)); } catch (e) {
    console.warn('[whatsapp] No se pudo escribir marker de sesión:', e.message);
  }
}

function hacerSnapshot() {
  if (!fs.existsSync(SESSION_DIR)) return false;
  copiarSesion(SESSION_DIR, SNAP_DIR);
  return true;
}

function restaurarSnapshot() {
  if (!fs.existsSync(SNAP_DIR)) return false;
  fs.rmSync(SESSION_DIR, { recursive: true, force: true });
  copiarSesion(SNAP_DIR, SESSION_DIR);
  return true;
}

// El incidente del 04/07 arrancó con ERR_NAME_NOT_RESOLVED: el container
// levanta antes de que el DNS de Docker esté usable y Chromium navega al
// vacío. Mejor esperar acá que lanzar un initialize condenado.
async function esperarDns() {
  for (let i = 0; i < 6; i++) {
    try { await dns.promises.lookup('web.whatsapp.com'); return true; }
    catch (e) {
      console.warn(`[whatsapp] DNS aún no resuelve web.whatsapp.com (${e.code}) — espero 5s`);
      await new Promise((r) => setTimeout(r, 5000));
    }
  }
  return false;
}

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
  // 6 min: la primera carga de WA Web con perfil limpio (sin cache, render
  // por software) puede superar los 3 min y abortar initialize().
  protocolTimeout: 360_000,
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
    // Anti-congelamiento de pestaña (05/07): Chromium moderno degrada la
    // prioridad del renderer "de fondo" (STAT SN) y le congela los timers —
    // en ovo eso dejaba el CDP sin responder (evaluate/getState timeout)
    // con el proceso idle. La página de WA Web debe correr SIEMPRE como
    // primer plano.
    '--disable-renderer-backgrounding',
    '--disable-backgrounding-occluded-windows',
    '--disable-features=IntensiveWakeUpThrottling,TabFreezing',
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
  let reintentosSeguidos   = 0;
  let listoEnEstaCorrida   = false; // llegó a 'ready' → la sesión en disco es válida

  function resetActividad() { ultimaActividad = Date.now(); }

  function programarReinicio(ms) {
    setTimeout(() => {
      iniciar().catch((e) => console.error('[whatsapp] Error en iniciar():', e.message));
    }, ms);
  }

  // Backoff exponencial para los reintentos de iniciar(): 15s, 30s, 60s… hasta
  // 5 min. Cada ciclo de initialize levanta un Chromium entero (~300 MB y CPU
  // alta); si el problema no es transitorio, ciclar cada 10-15s fijo castiga
  // la RAM de WSL sin mejorar nada. Se resetea al llegar a 'ready'.
  function delayReintento() {
    const ms = Math.min(15_000 * 2 ** reintentosSeguidos, 5 * 60_000);
    reintentosSeguidos++;
    return ms;
  }

  // Envuelve las llamadas al cliente con timeout. Si CDP está trabado, la
  // promesa de wwebjs no resuelve nunca y el endpoint HTTP que la espera queda
  // colgado acumulando sockets. Mejor fallar rápido y que el watchdog actúe.
  function conTimeout(promise, ms, etiqueta) {
    return Promise.race([
      promise,
      new Promise((_, rej) => setTimeout(() => rej(new Error(`${etiqueta}: timeout ${ms / 1000}s (Chromium sin responder)`)), ms)),
    ]);
  }

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
    programarReinicio(5_000);
  }

  async function verificarSalud() {
    if (destruido) return;
    const inactivo = Date.now() - ultimaActividad;

    // Sonda de vida CDP pura: evaluate(()=>1) solo prueba que Chromium responde
    // al protocolo, sin depender de los internals de WA Web. Necesario porque
    // getState() se cuelga (timeout 30s) en algunas cuentas/builds AUNQUE el
    // cliente funcione perfecto — falso positivo que reiniciaba ovo cada 15
    // min el 05/07 con la regla vieja de "3 sin CONNECTED".
    let cdpVivo = false;
    try {
      await Promise.race([
        client.pupPage.evaluate(() => 1),
        new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 30_000)),
      ]);
      cdpVivo = true;
    } catch (err) {
      console.warn(`[watchdog] CDP sin respuesta: ${err.message}`);
    }

    // Estado WA: informativo. Solo cuenta como señal de zombie si es un estado
    // explícito distinto de CONNECTED (null/timeout con CDP vivo NO es señal).
    let estadoReal = null;
    if (cdpVivo) {
      try {
        estadoReal = await Promise.race([
          client.getState(),
          new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 15_000)),
        ]);
      } catch (_) { /* getState roto con CDP vivo no es cuelgue */ }
    }

    if (cdpVivo) {
      chequeosSinConnected = 0;
    } else if (inactivo < WATCHDOG_INTERVAL) {
      // Hubo mensajes/acks hace menos de un intervalo: el cliente FUNCIONA
      // aunque el CDP no conteste la sonda (renderer throttleado). No
      // reiniciar lo que demostrablemente anda.
      console.log('[watchdog] CDP sin respuesta pero con actividad reciente — no cuenta strike');
    } else {
      chequeosSinConnected++;
    }

    console.log(`[watchdog] CDP: ${cdpVivo ? 'ok' : 'SIN RESPUESTA'} | Estado WA: ${estadoReal ?? 'desconocido'} | Inactivo: ${Math.round(inactivo / 60000)}m | strikes: ${chequeosSinConnected}`);

    if (chequeosSinConnected >= WATCHDOG_MAX_SIN_CONNECTED) {
      return reiniciarPorWatchdog(`${chequeosSinConnected} chequeos seguidos sin respuesta CDP (Chromium congelado)`);
    }
    // Zombie real: CDP vivo pero WA reporta un estado malo sostenido sin actividad
    if (inactivo > WATCHDOG_TIMEOUT && estadoReal && estadoReal !== 'CONNECTED') {
      return reiniciarPorWatchdog(`${Math.round(inactivo/60000)}m sin actividad y estado WA ${estadoReal}`);
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

  async function iniciar() {
    if (!(await esperarDns())) {
      const espera = delayReintento();
      console.error(`[whatsapp] Sin DNS tras 30s — no lanzo Chromium. Reintento en ${Math.round(espera / 1000)}s`);
      programarReinicio(espera);
      return;
    }
    limpiarLocks();
    destruido = false;
    chequeosSinConnected = 0;

    client = new Client({
      authStrategy: new LocalAuth({ dataPath: AUTH_PATH }),
      puppeteer: PUPPETEER_OPTS,
      userAgent: USER_AGENT_MODERNO,
      // Pin de versión de WA Web (12/06: WhatsApp rolleó una build que cuelga
      // initialize() — Runtime.callFunctionOn sin respuesta. Se fija una build
      // del 09/06 conocida-buena via el repo wa-version de wppconnect).
      // Override por env si hay que cambiarla sin tocar código.
      webVersion: process.env.WA_WEB_VERSION || '2.3000.1041096482-alpha',
      webVersionCache: {
        type: 'remote',
        remotePath: 'https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/{version}.html',
      },
    });

    client.on('qr', async (qr) => {
      // QR con marker de sesión válida = corrupción local, NO logout del
      // servidor (probado 05/07). Restaurar el snapshot antes de rendirse.
      const marker = leerMarker();
      if (marker) {
        const intentos = marker.intentos_restore || 0;
        if (intentos < 2 && fs.existsSync(SNAP_DIR)) {
          marker.intentos_restore = intentos + 1;
          escribirMarker(marker);
          console.warn(`[whatsapp] QR pese a sesión previa válida (${marker.phone}) — restauro snapshot (intento ${marker.intentos_restore}/2)`);
          destruido = true;
          detenerWatchdog();
          try { await client.destroy(); } catch (_) {}
          try {
            if (restaurarSnapshot()) { programarReinicio(3000); return; }
            console.error('[whatsapp] No hay snapshot para restaurar');
          } catch (e) {
            console.error('[whatsapp] Falló la restauración del snapshot:', e.message);
          }
        } else {
          // Se agotaron los intentos (o no hay snapshot): rendirse al QR y
          // avisar fuerte. Borrar el marker corta el loop de restauración.
          try { fs.rmSync(MARKER_FILE, { force: true }); } catch (_) {}
          console.error('[whatsapp] Sesión perdida DEFINITIVA (restauración automática agotada). Opciones: escanear QR desde /admin o restaurar backups/full/sesiones-wa/*.tar.gz.prev en el volumen.');
        }
      }
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
      reintentosSeguidos = 0;
      listoEnEstaCorrida = true;
      escribirMarker({ ts: new Date().toISOString(), phone, intentos_restore: 0 });
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
      const espera = delayReintento();
      console.log(`[whatsapp] Reiniciando en ${Math.round(espera / 1000)} segundos...`);
      programarReinicio(espera);
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
      const espera = delayReintento();
      console.log(`[whatsapp] Reintentando en ${Math.round(espera / 1000)} segundos...`);
      programarReinicio(espera);
    });
  }

  iniciar().catch((e) => console.error('[whatsapp] Error en iniciar():', e.message));

  // ── Métodos expuestos por la interfaz ────────────────────
  emitter.sendText = async (jid, texto, opts = {}) => {
    const sendOpts = {};
    // wwebjs acepta el wa_id serializado del original directamente; resuelve
    // adentro la búsqueda en su store de Chromium. Si el original ya no está,
    // descarta silenciosamente el quote (el mensaje igual se envía).
    if (opts.quoted?.wa_id) sendOpts.quotedMessageId = opts.quoted.wa_id;
    const sent = await conTimeout(client.sendMessage(jid, texto, sendOpts), 45_000, 'sendText');
    const waId = sent?.id?._serialized || '';
    marcarEnviado(waId);
    return { wa_id: waId };
  };

  emitter.sendMedia = async (jid, { mimetype, base64, filename, caption }) => {
    const media = new MessageMedia(mimetype, base64, filename || 'archivo');
    const opts = caption ? { caption } : {};
    const sent = await conTimeout(client.sendMessage(jid, media, opts), 90_000, 'sendMedia');
    const waId = sent?.id?._serialized || '';
    marcarEnviado(waId);
    return { wa_id: waId };
  };

  emitter.checkNumber = async (digits) => {
    const id = await conTimeout(client.getNumberId(String(digits).replace(/\D/g, '')), 15_000, 'checkNumber');
    if (!id) return { registered: false, normalizedId: null };
    return {
      registered: true,
      normalizedId: id._serialized || `${id.user}@${id.server}`,
    };
  };

  emitter.resolveContact = async (jid) => {
    const c = await conTimeout(client.getContactById(jid), 15_000, 'resolveContact');
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

  // Apagado limpio (lo llama el handler de SIGTERM en index.js): cerrar
  // Chromium con destroy() flushea LevelDB entero; recién ahí la sesión en
  // disco es confiable y vale la pena snapshotearla.
  emitter.destroy = async () => {
    destruido = true;
    detenerWatchdog();
    try {
      await client.destroy();
    } catch (e) {
      console.warn('[whatsapp] destroy() falló durante apagado:', e.message);
      return; // Chromium no cerró limpio → NO snapshotear (posible estado a medias)
    }
    if (listoEnEstaCorrida) {
      try {
        hacerSnapshot();
        console.log('[whatsapp] Snapshot de sesión guardado (apagado limpio)');
      } catch (e) {
        console.warn('[whatsapp] Snapshot de sesión falló:', e.message);
      }
    }
  };

  return emitter;
}

module.exports = { crearClienteWwebjs };
