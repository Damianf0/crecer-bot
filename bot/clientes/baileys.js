// Wrapper de Baileys v7 (@whiskeysockets/baileys 7.0.0-rc14) que cumple la
// interfaz de bot/cliente-wa.js. Protocolo Multi-Device directo por WebSocket:
// sin Chromium, sin Puppeteer, sin páginas zombie.
//
// Historia: el wrapper 6.7 se abandonó el 16/06 y se borró el 06/07 (3e7606e).
// Se retoma el 21/09 sobre v7, que rehízo el manejo de @lid, con el bot de
// workbench-bot-whatsapp (v7 desde 20/07) como referencia. Hasta validar el
// caso que nos rompió — salientes a DMs con historial ("Esperando mensaje") —
// corre SOLO como shadow (servicio bot-baileys-test, BOT_AREA=test).
//
// Diferencias con el wrapper 6.7 que vale la pena conocer:
//  - wa_id en formato wwebjs ("<fromMe>_<jid @c.us|@lid>_<id>"): la BD, la
//    dedup del backfill y los replies ya guardan ese formato; un cutover con
//    ids pelados rompería el quote de todo el historial.
//  - store de mensajes (entrantes y salientes, 24 h) para getMessage: es lo que
//    le permite a Baileys retransmitir cuando el receptor pide retry. El 6.7
//    tenía 10 min; los retries de un celular que estuvo apagado llegan tarde.
//  - sin presenceSubscribe/assertSessions antes de enviar: no arreglaron nada
//    en mayo y v7 resuelve PN→LID adentro de relayMessage.
//  - detector de sesión "sorda" (issue #2491): socket abierto sin eventos de
//    WhatsApp por BAILEYS_SORDA_MIN → reconexión (barata: segundos, sin QR).
//  - diagnóstico [baileys-diag]: acks de cada saliente y cada getMessage, para
//    medir en el shadow si los receptores descifran.
//
// Sesión: /app/.baileys_auth (multi-file). Volumen propio, separado del
// .wwebjs_auth — pueden convivir como dos dispositivos vinculados del número.

const { EventEmitter } = require('events');
const QRCode = require('qrcode');
const dns = require('dns');
const fs = require('fs');

const AUTH_PATH = '/app/.baileys_auth';
const SORDA_MS = parseInt(process.env.BAILEYS_SORDA_MIN || '30', 10) * 60_000;
const STORE_TTL_MS = 24 * 60 * 60_000;
const STORE_MAX = 5000;

// Baileys pide un logger "pino-like"; su verbosidad no nos sirve en los logs.
const silentLogger = {
  level: 'silent',
  trace() {}, debug() {}, info() {}, warn() {}, error() {}, fatal() {},
  child() { return silentLogger; },
};

// ── JIDs ───────────────────────────────────────────────────
// Afuera del adapter los usuarios van con el sufijo histórico @c.us (BD y
// Laravel lo asumen). Adentro, Baileys habla @s.whatsapp.net. @lid y @g.us
// pasan sin cambio. Se descarta el sufijo de dispositivo (":12").
function aExterno(jid) {
  if (!jid) return jid;
  const [user, server] = jid.split('@');
  const u = user.split(':')[0];
  return server === 's.whatsapp.net' ? `${u}@c.us` : `${u}@${server}`;
}

function aInterno(jid) {
  if (!jid) return jid;
  return jid.replace('@c.us', '@s.whatsapp.net');
}

// wa_id con el mismo formato que serializa wwebjs.
function serializarId(key) {
  return `${!!key.fromMe}_${aExterno(key.remoteJid)}_${key.id}`;
}

// Inversa: acepta el formato wwebjs o un id pelado.
function parsearId(waId) {
  const m = /^(true|false)_(.+)_([^_]+)$/.exec(waId || '');
  if (!m) return { fromMe: false, remoteJid: null, id: waId };
  return { fromMe: m[1] === 'true', remoteJid: aInterno(m[2]), id: m[3] };
}

function numeroDe(ts) {
  if (ts == null) return Math.floor(Date.now() / 1000);
  if (typeof ts === 'number') return ts;
  if (typeof ts.toNumber === 'function') return ts.toNumber();
  return Number(ts) || Math.floor(Date.now() / 1000);
}

// ── Contenido ──────────────────────────────────────────────
function desempaquetar(message) {
  if (!message) return null;
  const envuelto = message.ephemeralMessage || message.viewOnceMessage
    || message.viewOnceMessageV2 || message.viewOnceMessageV2Extension
    || message.documentWithCaptionMessage || message.editedMessage;
  if (envuelto?.message) return desempaquetar(envuelto.message);
  return message;
}

function tipoYBody(message) {
  const m = desempaquetar(message);
  if (!m) return { tipo: null, body: null };
  if (m.conversation)              return { tipo: 'texto', body: m.conversation };
  if (m.extendedTextMessage?.text) return { tipo: 'texto', body: m.extendedTextMessage.text };
  if (m.audioMessage)              return { tipo: 'audio', body: null };
  if (m.imageMessage)              return { tipo: 'imagen', body: m.imageMessage.caption || null };
  if (m.videoMessage)              return { tipo: 'video', body: m.videoMessage.caption || null };
  if (m.documentMessage)           return { tipo: 'documento',
                                            body: m.documentMessage.caption || m.documentMessage.fileName || null };
  if (m.stickerMessage)            return { tipo: 'sticker', body: null };
  return { tipo: null, body: null }; // reacciones, protocolMessage, encuestas…
}

function mimetypeDe(message) {
  const m = desempaquetar(message);
  return m?.audioMessage?.mimetype || m?.imageMessage?.mimetype || m?.videoMessage?.mimetype
    || m?.documentMessage?.mimetype || m?.stickerMessage?.mimetype || 'application/octet-stream';
}

function previewDe(message) {
  const { tipo, body } = tipoYBody(message);
  if (body) return body.slice(0, 280);
  return { audio: '🎤 Audio', imagen: '🖼️ Imagen', video: '🎬 Video',
           documento: '📄 Documento', sticker: '😀 Sticker' }[tipo] || '';
}

// contextInfo.stanzaId es el id pelado del original; lo devolvemos serializado
// como wwebjs para que matchee contra mensajes_wa.wa_id.
function extraerQuoted(message, remoteJid, propios = []) {
  const m = desempaquetar(message);
  if (!m) return null;
  const ci = [m.extendedTextMessage, m.imageMessage, m.videoMessage, m.audioMessage,
              m.documentMessage, m.stickerMessage].map(x => x?.contextInfo).find(c => c?.stanzaId);
  if (!ci) return null;
  // En un DM, participant es el autor del citado: nosotros (PN o LID propio)
  // o el contacto.
  const fromMe = !!ci.participant && propios.includes(aExterno(ci.participant));
  return {
    wa_id: serializarId({ fromMe, remoteJid, id: ci.stanzaId }),
    autor: null,
    preview: previewDe(ci.quotedMessage),
  };
}

function contenidoMedia({ mimetype, base64, filename, caption }) {
  const buffer = Buffer.from(base64, 'base64');
  if (mimetype?.startsWith('image/')) return { image: buffer, mimetype, caption };
  if (mimetype?.startsWith('video/')) return { video: buffer, mimetype, caption };
  if (mimetype?.startsWith('audio/')) {
    // .ogg = nota de voz: WhatsApp exige 'codecs=opus' + ptt o lo descarta sin error.
    if (/ogg/i.test(mimetype)) return { audio: buffer, mimetype: 'audio/ogg; codecs=opus', ptt: true };
    return { audio: buffer, mimetype, ptt: false };
  }
  return { document: buffer, mimetype, fileName: filename || 'archivo', caption };
}

async function esperarDns() {
  for (let i = 0; i < 6; i++) {
    try { await dns.promises.lookup('web.whatsapp.com'); return true; }
    catch (e) {
      console.warn(`[baileys] DNS aún no resuelve web.whatsapp.com (${e.code}) — espero 5s`);
      await new Promise((r) => setTimeout(r, 5000));
    }
  }
  return false;
}

function crearClienteBaileys() {
  const emitter = new EventEmitter();
  let B = null;              // módulo Baileys (ESM, se carga con import())
  let sock = null;
  let destruido = false;
  let estado = 'connecting'; // connecting | open | close
  let reintentos = 0;
  let ultimoEvento = Date.now();
  let sordaTimer = null;

  // Salientes propios: message_outgoing no debe disparar sobre ellos.
  const enviados = new Map(); // id pelado → ts
  let enVuelo = 0;            // sendMessage en curso (ver procesar)
  function marcarEnviado(id) {
    const ahora = Date.now();
    enviados.set(id, ahora);
    for (const [k, t] of enviados) if (ahora - t > 10 * 60_000) enviados.delete(k);
  }

  // Store para getMessage (retries) y para citar con el mensaje real.
  const store = new Map(); // id pelado → { key, message, ts }
  function guardar(key, message) {
    if (!key?.id || !message) return;
    store.set(key.id, { key, message, ts: Date.now() });
    if (store.size > STORE_MAX) {
      const corte = Date.now() - STORE_TTL_MS;
      for (const [k, v] of store) if (v.ts < corte || store.size > STORE_MAX) store.delete(k);
    }
  }
  function leer(id) {
    const e = store.get(id);
    if (!e) return null;
    if (Date.now() - e.ts > STORE_TTL_MS) { store.delete(id); return null; }
    return e;
  }

  const vistos = new Map(); // dedup de messages.upsert (reconexiones reentregan)
  function yaVisto(id) {
    if (vistos.has(id)) return true;
    vistos.set(id, Date.now());
    if (vistos.size > 2000) vistos.delete(vistos.keys().next().value);
    return false;
  }

  function actividad() { ultimoEvento = Date.now(); }

  function programarReinicio(motivo) {
    if (destruido) return;
    const ms = Math.min(5_000 * 2 ** reintentos, 5 * 60_000);
    reintentos++;
    console.log(`[baileys] Reconectando en ${Math.round(ms / 1000)}s (${motivo})`);
    setTimeout(() => iniciar().catch((e) => console.error('[baileys] Error reiniciando:', e.message)), ms);
  }

  // Sesión sorda (#2491): WebSocket vivo, keepalive OK, pero WhatsApp deja de
  // entregar. Cualquier evento del socket cuenta como señal de vida (acks,
  // presencias, receipts), así que en horario hábil el umbral no se toca solo.
  function vigilarSordera() {
    if (sordaTimer) clearInterval(sordaTimer);
    sordaTimer = setInterval(() => {
      if (destruido || estado !== 'open') return;
      const mudo = Date.now() - ultimoEvento;
      if (mudo < SORDA_MS) return;
      console.warn(`[baileys] Sesión sorda: ${Math.round(mudo / 60_000)} min sin eventos con el socket abierto — fuerzo reconexión`);
      actividad();
      try { sock?.end?.(new Error('sesion sorda')); } catch (_) {}
    }, 60_000);
  }

  async function iniciar() {
    if (destruido) return;
    if (!(await esperarDns())) return programarReinicio('sin DNS');
    if (!B) B = await import('@whiskeysockets/baileys');
    const makeWASocket = B.default?.default || B.default || B.makeWASocket;
    if (!fs.existsSync(AUTH_PATH)) fs.mkdirSync(AUTH_PATH, { recursive: true });

    const { state, saveCreds } = await B.useMultiFileAuthState(AUTH_PATH);
    const { version } = await B.fetchLatestBaileysVersion().catch(() => ({ version: undefined }));
    console.log(`[baileys] Arrancando (protocolo WA ${version ? version.join('.') : 'default'})`);

    const s = makeWASocket({
      version,
      auth: state,
      logger: silentLogger,
      browser: B.Browsers.ubuntu('Chrome'),
      markOnlineOnConnect: false,  // no robarle las notificaciones al celular
      syncFullHistory: false,
      enableAutoSessionRecreation: true,
      enableRecentMessageCache: true,
      getMessage: async (key) => {
        const e = leer(key?.id);
        console.log(`[baileys-diag] getMessage ${key?.id} → ${e ? 'servido' : 'NO está en el store'}`);
        return e?.message;
      },
    });
    sock = s;

    s.ev.on('creds.update', saveCreds);

    s.ev.on('connection.update', async ({ connection, lastDisconnect, qr }) => {
      if (destruido || s !== sock) return;
      actividad();
      if (qr) {
        try { emitter.emit('qr', await QRCode.toDataURL(qr, { width: 300 })); }
        catch (e) { console.error('[baileys] Error generando QR:', e.message); }
        console.log('[baileys] QR recibido — esperando escaneo');
      }
      if (connection === 'open') {
        estado = 'open';
        reintentos = 0;
        const phone = s.user?.id ? s.user.id.split(':')[0].split('@')[0] : null;
        console.log(`[baileys] Cliente listo. Número: ${phone} (lid ${s.user?.lid || '?'})`);
        emitter.emit('ready', { phone });
        vigilarSordera();
      }
      if (connection === 'close') {
        estado = 'close';
        const code = lastDisconnect?.error?.output?.statusCode;
        const reason = `${code || '?'} ${lastDisconnect?.error?.message || ''}`.trim();
        console.warn('[baileys] Conexión cerrada:', reason);
        emitter.emit('disconnected', reason);
        if (code === B.DisconnectReason.loggedOut) {
          console.error('[baileys] Sesión revocada (logged out) — borrar el volumen de .baileys_auth y reescanear el QR');
          return;
        }
        // 440 = otra instancia con las MISMAS credenciales tomó la conexión.
        // Reconectar solo arma un ping-pong entre las dos.
        if (code === B.DisconnectReason.connectionReplaced) {
          console.error('[baileys] Conexión reemplazada por otra instancia con esta sesión — no reconecto');
          return;
        }
        if (code === B.DisconnectReason.restartRequired) reintentos = 0; // normal tras parear
        programarReinicio(reason);
      }
    });

    s.ev.on('lid-mapping.update', (m) => {
      actividad();
      console.log(`[baileys-diag] lid-mapping ${m?.lid} ↔ ${m?.pn}`);
    });

    // Acks de los salientes: server(2)=salió, delivery(3)=el celu lo recibió,
    // read(4)=lo abrió. Un saliente que queda en 2 para siempre, o un
    // getMessage que no se sirve, es el síntoma de "Esperando mensaje".
    s.ev.on('messages.update', (updates) => {
      actividad();
      for (const u of updates) {
        if (!u.key?.fromMe || u.update?.status == null) continue;
        if (!enviados.has(u.key.id) && !leer(u.key.id)) continue;
        console.log(`[baileys-diag] ack ${u.key.id} → ${aExterno(u.key.remoteJid)} status=${u.update.status}`);
      }
    });
    s.ev.on('message-receipt.update', actividad);
    s.ev.on('presence.update', actividad);
    s.ev.on('chats.update', actividad);

    s.ev.on('messages.upsert', async ({ messages, type }) => {
      actividad();
      for (const raw of messages) {
        if (raw.key?.id && raw.message) guardar(raw.key, raw.message);
        // 'notify' = en vivo. 'append' trae historial al reconectar Y los
        // salientes desde el celular; de ese solo tomamos lo reciente y propio.
        if (type !== 'notify') {
          const reciente = Date.now() / 1000 - numeroDe(raw.messageTimestamp) < 300;
          if (!(type === 'append' && raw.key?.fromMe && reciente)) continue;
        }
        try { await procesar(raw); }
        catch (e) { console.error('[baileys] Error procesando mensaje:', e.message); }
      }
    });
  }

  async function procesar(raw) {
    const key = raw.key || {};
    const remoteJid = key.remoteJid;
    if (!remoteJid || !key.id) return;
    if (remoteJid.endsWith('@broadcast') || remoteJid.endsWith('@g.us') || remoteJid.endsWith('@newsletter')) return;
    // Baileys emite el upsert del mensaje propio antes de que sendMessage
    // devuelva el id: mientras haya envíos en vuelo, esperar antes de decidir.
    if (key.fromMe) {
      const limite = Date.now() + 20_000;
      while (!enviados.has(key.id) && enVuelo > 0 && Date.now() < limite) {
        await new Promise((r) => setTimeout(r, 250));
      }
      if (enviados.has(key.id)) return;
    }
    if (yaVisto(key.id)) return;

    const { tipo, body } = tipoYBody(raw.message);
    if (!tipo) return;

    const propio = sock.user?.id ? aExterno(sock.user.id) : null;
    const chat = aExterno(remoteJid);
    // Número real si WhatsApp lo manda al lado del @lid (campo extra: el resto
    // del bot lo ignora, pero sirve para mapear @lid ↔ contacto).
    const alt = key.remoteJidAlt ? aExterno(key.remoteJidAlt) : null;

    const msg = {
      from: key.fromMe ? propio : chat,
      to: key.fromMe ? chat : propio,
      fromMe: !!key.fromMe,
      type: tipo,
      body,
      wa_id: serializarId(key),
      numero: alt && alt.endsWith('@c.us') ? alt.split('@')[0] : null,
      pushname: raw.pushName || null,
      quoted: extraerQuoted(raw.message, remoteJid, [propio, aExterno(sock.user?.lid)].filter(Boolean)),
      timestamp: new Date(numeroDe(raw.messageTimestamp) * 1000),
      downloadMedia: async () => {
        try {
          const buf = await B.downloadMediaMessage(raw, 'buffer', {},
            { logger: silentLogger, reuploadRequest: sock.updateMediaMessage });
          return buf ? { mimetype: mimetypeDe(raw.message), data: Buffer.from(buf).toString('base64') } : null;
        } catch (e) {
          console.warn('[baileys] downloadMedia falló:', e.message);
          return null;
        }
      },
    };

    if (msg.fromMe) emitter.emit('message_outgoing', msg);
    else emitter.emit('message', msg);
  }

  function exigirConexion() {
    if (!sock || estado !== 'open') throw new Error('Cliente WhatsApp no conectado');
  }

  async function enviar(jid, contenido, opts = {}) {
    exigirConexion();
    const dest = aInterno(jid);
    enVuelo++;
    let sent;
    try { sent = await sock.sendMessage(dest, contenido, opts); } finally { enVuelo--; }
    if (!sent?.key?.id) throw new Error('sendMessage no devolvió key');
    marcarEnviado(sent.key.id);
    guardar(sent.key, sent.message);
    console.log(`[baileys-diag] enviado ${sent.key.id} → ${aExterno(sent.key.remoteJid)} (pedido a ${jid})`);
    return { wa_id: serializarId(sent.key) };
  }

  // Para citar, Baileys necesita { key, message }. Si el original está en el
  // store va el real; si no, uno mínimo con el preview (el celular que todavía
  // lo tiene lo renderiza por key.id).
  function armarQuoted(quoted, jid) {
    if (!quoted?.wa_id) return null;
    const p = parsearId(quoted.wa_id);
    const guardado = leer(p.id);
    if (guardado) return guardado;
    return {
      key: { id: p.id, remoteJid: p.remoteJid || aInterno(jid), fromMe: quoted.fromMe ?? p.fromMe },
      message: { conversation: quoted.preview || ' ' },
    };
  }

  emitter.sendText = async (jid, texto, opts = {}) => {
    const q = armarQuoted(opts.quoted, jid);
    return enviar(jid, { text: texto }, q ? { quoted: q } : {});
  };

  emitter.sendMedia = async (jid, media) => enviar(jid, contenidoMedia(media));

  // Devuelve @c.us igual que wwebjs.getNumberId: Laravel guarda ese JID como
  // contacto, y v7 lo pasa a @lid adentro al cifrar.
  emitter.checkNumber = async (digits) => {
    exigirConexion();
    const num = String(digits).replace(/\D/g, '');
    const [res] = (await sock.onWhatsApp(num)) || [];
    if (!res?.exists) return { registered: false, normalizedId: null };
    return { registered: true, normalizedId: aExterno(res.jid) };
  };

  // Sin store de contactos: el número sale del JID o del mapa LID↔PN que v7
  // arma con lo que WhatsApp revela. El nombre llega solo con los mensajes.
  emitter.resolveContact = async (jid) => {
    exigirConexion();
    const interno = aInterno(jid);
    let pn = interno.endsWith('@s.whatsapp.net') ? interno : null;
    if (!pn && interno.endsWith('@lid')) {
      try { pn = await sock.signalRepository.lidMapping.getPNForLID(interno); } catch (_) {}
    }
    return { numero: pn ? pn.split('@')[0].split(':')[0].replace(/\D/g, '') : null, name: null };
  };

  // Timeout corto: Laravel pollea avatares y una respuesta lenta de WA
  // satura los workers FPM.
  emitter.getProfilePicUrl = async (jid) => {
    if (!sock || estado !== 'open') return null;
    try {
      return await Promise.race([
        sock.profilePictureUrl(aInterno(jid), 'image'),
        new Promise((r) => setTimeout(() => r(null), 3000)),
      ]);
    } catch (_) {
      return null;
    }
  };

  emitter.getState = () => {
    if (destruido) return 'disconnected';
    return estado === 'open' ? 'connected' : estado === 'close' ? 'disconnected' : 'connecting';
  };

  // Sin fetchHistory todavía: Baileys solo puede pedirle historial al celular
  // (fetchMessageHistory, asíncrono vía messaging-history.set). Pendiente
  // antes del cutover; /backfill responde 503 mientras tanto.

  emitter.destroy = async () => {
    destruido = true;
    if (sordaTimer) clearInterval(sordaTimer);
    try { sock?.end?.(undefined); } catch (_) {}
  };

  iniciar().catch((e) => {
    console.error('[baileys] Error en init:', e.message);
    emitter.emit('disconnected', `init:${e.message}`);
    programarReinicio('init');
  });

  return emitter;
}

module.exports = { crearClienteBaileys, _test: { aExterno, aInterno, serializarId, parsearId, tipoYBody, extraerQuoted } };
