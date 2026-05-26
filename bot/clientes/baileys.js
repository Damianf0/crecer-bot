// Wrapper de @whiskeysockets/baileys que cumple la interfaz definida en
// bot/cliente-wa.js. Habla directo con el protocolo Multi-Device de WhatsApp
// vía WebSocket — sin Chromium, sin Puppeteer.
//
// Sesión persistida en /app/.baileys_auth (multi-file: creds.json + keys/*).
// Volumen 'wa-baileys-{area}' separado del 'wa-session-{area}' de wwebjs.

const { EventEmitter } = require('events');
const QRCode = require('qrcode');
const fs = require('fs');
const path = require('path');

const AUTH_PATH = '/app/.baileys_auth';

// ── Logger silencioso compatible con pino ────────────────────
// Baileys requiere algo "pino-like". No queremos su verbosity en nuestros logs.
const silentLogger = {
  level: 'silent',
  trace: () => {}, debug: () => {}, info: () => {}, warn: () => {}, error: () => {}, fatal: () => {},
  child: () => silentLogger,
};

// ── JID translation ─────────────────────────────────────────
// Toda entrada al adapter usa el formato histórico @c.us para que Laravel/BD
// no se entere de Baileys. Adentro hablamos @s.whatsapp.net.
//
// '5491155667788@s.whatsapp.net' ↔ '5491155667788@c.us'
// '13254403313885@lid'               (sin cambio en ambos lados)
// '120363xxxx@g.us'                  (sin cambio en ambos lados)
function aExterno(jid) {
  if (!jid) return jid;
  return jid.replace('@s.whatsapp.net', '@c.us');
}

function aInterno(jid) {
  if (!jid) return jid;
  return jid.replace('@c.us', '@s.whatsapp.net');
}

// ── Tipo nativo de Baileys → tipo normalizado ───────────────
// Desempaca también ephemeralMessage / viewOnceMessage si están envueltos.
function desempaquetar(message) {
  if (!message) return null;
  if (message.ephemeralMessage?.message) return desempaquetar(message.ephemeralMessage.message);
  if (message.viewOnceMessage?.message)  return desempaquetar(message.viewOnceMessage.message);
  if (message.viewOnceMessageV2?.message) return desempaquetar(message.viewOnceMessageV2.message);
  return message;
}

function tipoYBody(message) {
  const m = desempaquetar(message);
  if (!m) return { tipo: null, body: null };

  if (m.conversation)                    return { tipo: 'texto', body: m.conversation };
  if (m.extendedTextMessage?.text)       return { tipo: 'texto', body: m.extendedTextMessage.text };
  if (m.audioMessage)                    return { tipo: 'audio', body: null };
  if (m.imageMessage)                    return { tipo: 'imagen', body: m.imageMessage.caption || null };
  if (m.videoMessage)                    return { tipo: 'video',  body: m.videoMessage.caption || null };
  if (m.documentMessage)                 return { tipo: 'documento',
                                                  body: m.documentMessage.caption || m.documentMessage.fileName || null };
  if (m.documentWithCaptionMessage?.message?.documentMessage) {
    const d = m.documentWithCaptionMessage.message.documentMessage;
    return { tipo: 'documento', body: d.caption || d.fileName || null };
  }
  if (m.stickerMessage)                  return { tipo: 'sticker', body: null };
  return { tipo: null, body: null };
}

// Extrae el bloque "quoted" cuando un mensaje es un reply.
// WhatsApp pone la info en contextInfo, presente en varios sub-tipos (texto
// extendido, imagen, video, audio, documento). Devuelve null si no aplica.
//   contextInfo.stanzaId   — wa_id del original
//   contextInfo.participant — JID del autor original
//   contextInfo.quotedMessage — el msg original (al menos preview)
function extraerQuoted(message) {
  const m = desempaquetar(message);
  if (!m) return null;
  const cands = [
    m.extendedTextMessage?.contextInfo,
    m.imageMessage?.contextInfo,
    m.videoMessage?.contextInfo,
    m.audioMessage?.contextInfo,
    m.documentMessage?.contextInfo,
    m.stickerMessage?.contextInfo,
  ];
  const ci = cands.find(c => c && c.stanzaId);
  if (!ci) return null;

  const { tipo, body } = tipoYBody(ci.quotedMessage || null);
  // Preview corto, normalizado para guardar en BD.
  let preview = body || '';
  if (!preview) {
    if (tipo === 'audio')     preview = '🎤 Audio';
    else if (tipo === 'imagen')   preview = '🖼️ Imagen';
    else if (tipo === 'video')    preview = '🎬 Video';
    else if (tipo === 'documento') preview = '📄 Documento';
    else if (tipo === 'sticker')   preview = '😀 Sticker';
  }
  return {
    wa_id:   ci.stanzaId,
    autor:   null,  // El nombre real se resuelve al recibir contra el contacts cache; null si no se conoce.
    preview: preview.slice(0, 280),
  };
}

function crearClienteBaileys() {
  // Carga lazy: solo cuando este wrapper se elige.
  const baileysMod = require('@whiskeysockets/baileys');
  const makeWASocket =
    baileysMod.default || baileysMod.makeWASocket;
  const {
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
    DisconnectReason,
    downloadMediaMessage,
  } = baileysMod;

  if (!fs.existsSync(AUTH_PATH)) fs.mkdirSync(AUTH_PATH, { recursive: true });

  const emitter = new EventEmitter();

  // Tracking interno: wa_ids enviados por nosotros (sendText/sendMedia)
  // para que message_outgoing NO dispare sobre ellos. TTL 5 min.
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

  let sock = null;
  let destruido = false;
  let estadoConexion = 'connecting'; // connecting | open | close
  let phone = null;

  async function iniciar() {
    if (destruido) return;
    const { state, saveCreds } = await useMultiFileAuthState(AUTH_PATH);
    const { version } = await fetchLatestBaileysVersion().catch(() => ({ version: undefined }));

    sock = makeWASocket({
      version,
      auth: state,
      printQRInTerminal: false,
      logger: silentLogger,
      browser: ['Crecer Bot', 'Chrome', '1.0'],
      syncFullHistory: false,
      markOnlineOnConnect: true,
      // Cuando un receptor no pudo descifrar un mensaje nuestro, WA le pide
      // al sender que lo reenvíe. Sin getMessage definido, Baileys no responde
      // y el receptor puede quedar mostrando "Esperando mensaje..." hasta
      // que WA caduque el ciclo. Devolviendo undefined explícito, WA descarta
      // el reintento más rápido. No mantenemos store local de mensajes
      // enviados así que no podemos retransmitir el contenido real.
      getMessage: async (_key) => undefined,
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
      if (destruido) return;
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        try {
          const dataUrl = await QRCode.toDataURL(qr, { width: 300 });
          console.log('[baileys] QR recibido — esperando escaneo');
          emitter.emit('qr', dataUrl);
        } catch (err) {
          console.error('[baileys] Error generando QR:', err.message);
        }
      }

      if (connection === 'open') {
        estadoConexion = 'open';
        phone = sock.user?.id ? sock.user.id.split(':')[0].split('@')[0] : null;
        console.log(`[baileys] Cliente listo. Número: ${phone}`);
        // Marcamos presencia explícitamente. Sin esto, WA puede tratar al cliente
        // como "offline" aunque connection esté open, y los primeros envíos
        // pueden llegar al destinatario como "Esperando mensaje..." porque
        // las claves libsignal no se negocian a tiempo.
        try { await sock.sendPresenceUpdate('available'); } catch (_) {}
        emitter.emit('ready', { phone });
      }

      if (connection === 'close') {
        estadoConexion = 'close';
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const reason = statusCode ? `${statusCode} ${lastDisconnect?.error?.message || ''}` : 'desconocido';
        console.warn('[baileys] Conexión cerrada:', reason);
        emitter.emit('disconnected', reason);

        if (statusCode === DisconnectReason.loggedOut) {
          console.error('[baileys] Sesión revocada (logged out) — borrá /app/.baileys_auth y reescaneá QR');
          return; // no reconectar
        }
        // Reconectar para todos los otros cierres (timeout, reset, etc.)
        console.log('[baileys] Reconectando en 5s...');
        setTimeout(() => iniciar().catch(e => console.error('[baileys] Error reiniciando:', e.message)), 5_000);
      }
    });

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
      if (type !== 'notify') return; // ignorar históricos al reconectar
      for (const raw of messages) {
        try {
          await procesarMensaje(raw);
        } catch (err) {
          console.error('[baileys] Error procesando mensaje:', err.message);
        }
      }
    });
  }

  async function procesarMensaje(raw) {
    const remoteJid = raw.key?.remoteJid;
    if (!remoteJid) return;
    if (remoteJid === 'status@broadcast' || remoteJid.endsWith('@broadcast')) return;
    if (remoteJid.endsWith('@g.us')) return; // sin grupos

    const fromMe = !!raw.key?.fromMe;
    const waId = raw.key?.id;
    if (!waId) return;

    // Filtrar mensajes que enviamos nosotros via sendText/sendMedia
    if (fromMe && fueEnviadoPorNosotros(waId)) return;

    const { tipo, body } = tipoYBody(raw.message);
    if (!tipo) return; // tipo no soportado (reaction, protocolMessage, etc.)

    const quoted = extraerQuoted(raw.message);

    const ts = typeof raw.messageTimestamp === 'number'
      ? raw.messageTimestamp
      : Number(raw.messageTimestamp?.toNumber?.() || raw.messageTimestamp || Date.now() / 1000);

    const msg = {
      from: fromMe ? aExterno(sock.user?.id?.split(':')[0] + '@s.whatsapp.net') : aExterno(remoteJid),
      to:   fromMe ? aExterno(remoteJid) : aExterno(sock.user?.id?.split(':')[0] + '@s.whatsapp.net'),
      fromMe,
      type: tipo,
      body,
      wa_id: waId,
      quoted,  // { wa_id, autor, preview } | null — info del mensaje citado si éste es un reply
      timestamp: new Date(ts * 1000),
      downloadMedia: async () => {
        try {
          const buf = await downloadMediaMessage(
            raw,
            'buffer',
            {},
            { logger: silentLogger, reuploadRequest: sock.updateMediaMessage }
          );
          if (!buf) return null;
          // mimetype del nodo correspondiente
          const m = desempaquetar(raw.message);
          const mimetype = m?.audioMessage?.mimetype
                        || m?.imageMessage?.mimetype
                        || m?.videoMessage?.mimetype
                        || m?.documentMessage?.mimetype
                        || m?.stickerMessage?.mimetype
                        || 'application/octet-stream';
          return { mimetype, data: buf.toString('base64') };
        } catch (err) {
          console.warn('[baileys] downloadMedia falló:', err.message);
          return null;
        }
      },
    };

    if (fromMe) emitter.emit('message_outgoing', msg);
    else        emitter.emit('message', msg);
  }

  // Tipo nuestro → estructura para sock.sendMessage
  function construirContenidoMedia({ mimetype, base64, filename, caption }) {
    const buffer = Buffer.from(base64, 'base64');
    if (mimetype?.startsWith('image/')) {
      return { image: buffer, mimetype, caption };
    }
    if (mimetype?.startsWith('video/')) {
      return { video: buffer, mimetype, caption };
    }
    if (mimetype?.startsWith('audio/')) {
      // WhatsApp distingue dos modos para audio:
      //   - PTT (push-to-talk, "nota de voz"): mimetype debe ser
      //     'audio/ogg; codecs=opus' y ptt=true. Es el formato natural de los
      //     audios grabados en WhatsApp. Si llega un .ogg sin codecs declarado,
      //     WA lo descarta silenciosamente.
      //   - Archivo de audio (mp3/mp4/wav): ptt=false, mimetype tal cual.
      const esOgg = /ogg/i.test(mimetype);
      if (esOgg) {
        return { audio: buffer, mimetype: 'audio/ogg; codecs=opus', ptt: true };
      }
      return { audio: buffer, mimetype, ptt: false };
    }
    // documento / fallback
    return { document: buffer, mimetype, fileName: filename || 'archivo', caption };
  }

  // Resuelve el JID destino vía sock.onWhatsApp. WhatsApp puede tener el
  // usuario indexado en @s.whatsapp.net o en @lid según su modo de privacidad;
  // mandar al JID incorrecto resulta en "Esperando mensaje..." del lado del
  // receptor porque la sesión libsignal está atada al otro JID.
  //
  // IMPORTANTE: si onWhatsApp devuelve info.lid, ese es el JID preferido del
  // destinatario y al que su sesión libsignal está vinculada. Mandar a
  // info.jid (siempre @s.whatsapp.net) en ese caso resulta en paquete cifrado
  // que el receptor no puede descifrar. Este fue el bug del rollback del
  // 18/05 — wwebjs hacía esta resolución automáticamente vía contact lookup;
  // Baileys no, y por eso teníamos que leer info.lid explícitamente.
  // Cache de 10 min para evitar llamar onWhatsApp en cada envío.
  const _jidCache = new Map();
  async function resolverJidEnvio(jid) {
    const interno = aInterno(jid);
    // Para @g.us (grupos) y @lid (linked id) usamos el JID tal cual.
    if (!interno.endsWith('@s.whatsapp.net')) return interno;

    const num = interno.split('@')[0];
    const cached = _jidCache.get(num);
    if (cached && Date.now() - cached.ts < 10 * 60_000) return cached.jid;

    try {
      const [info] = await sock.onWhatsApp(num);
      if (info?.lid) {
        console.log(`[baileys] onWhatsApp(${num}) → @lid ${info.lid}`);
        _jidCache.set(num, { jid: info.lid, ts: Date.now() });
        return info.lid;
      }
      if (info?.jid) {
        console.log(`[baileys] onWhatsApp(${num}) → @s ${info.jid}`);
        _jidCache.set(num, { jid: info.jid, ts: Date.now() });
        return info.jid;
      }
    } catch (err) {
      console.warn(`[baileys] onWhatsApp falló para ${num}: ${err.message}`);
    }
    return interno;
  }

  // Asegura que la sesión libsignal con el destinatario esté negociada antes
  // del primer envío. Sin esto, el primer mensaje a un contacto nuevo llega
  // como "Esperando mensaje..." porque el destinatario no puede descifrarlo
  // (no recibió las prekeys del sender).
  async function asegurarSesion(dest) {
    try {
      // Subscribir a presencia: fuerza al server a refrescar las identity keys
      // del destinatario en nuestra sesión antes del envío.
      await sock.presenceSubscribe(dest);
    } catch (_) {}
    try {
      await sock.assertSessions([dest], true);
    } catch (err) {
      console.warn(`[baileys] assertSessions falló para ${dest}: ${err.message}`);
    }
  }

  // ── Métodos expuestos ────────────────────────────────────
  // Reconstruye el msg "quoted" que necesita sock.sendMessage. Baileys exige
  // { key, message } completo; no guardamos un store local de mensajes, así
  // que armamos un fake mínimo: key.id matchea el wa_id original y el message
  // lleva el preview como conversation. Si el receptor todavía tiene el msg
  // original en su historial local, WhatsApp lo renderea con el contenido real
  // (match por key.id); si no, muestra el preview que mandamos acá.
  function construirQuotedFake(quoted, remoteJid) {
    if (!quoted?.wa_id) return null;
    return {
      key: {
        id:        quoted.wa_id,
        remoteJid,
        fromMe:    !!quoted.fromMe,
      },
      message: { conversation: quoted.preview || ' ' },
    };
  }

  emitter.sendText = async (jid, texto, opts = {}) => {
    const dest = await resolverJidEnvio(jid);
    await asegurarSesion(dest);
    const sendOpts = {};
    const quotedFake = construirQuotedFake(opts.quoted, dest);
    if (quotedFake) sendOpts.quoted = quotedFake;
    const sent = await sock.sendMessage(dest, { text: texto }, sendOpts);
    const waId = sent?.key?.id || '';
    marcarEnviado(waId);
    return { wa_id: waId };
  };

  emitter.sendMedia = async (jid, { mimetype, base64, filename, caption }) => {
    const dest = await resolverJidEnvio(jid);
    await asegurarSesion(dest);
    const contenido = construirContenidoMedia({ mimetype, base64, filename, caption });
    const sent = await sock.sendMessage(dest, contenido);
    const waId = sent?.key?.id || '';
    marcarEnviado(waId);
    return { wa_id: waId };
  };

  emitter.checkNumber = async (digits) => {
    const num = String(digits).replace(/\D/g, '');
    const [res] = await sock.onWhatsApp(num);
    if (!res || !res.exists) return { registered: false, normalizedId: null };
    // Preferimos @lid si existe — es el JID canónico del destinatario para
    // libsignal (cuando tiene privacy mode). wwebjs ya hace esto via contact
    // lookup interno; replicamos para que el contrato del adapter sea idéntico.
    return {
      registered: true,
      normalizedId: res.lid || aExterno(res.jid),
    };
  };

  emitter.resolveContact = async (jid) => {
    const interno = aInterno(jid);
    // Baileys mantiene sock.store solo si lo bindeás manualmente. Sin store,
    // dependemos del nombre que viene en los notify de mensajes. Para el
    // número: si es @s.whatsapp.net, los dígitos son la parte previa al @.
    // Si es @lid, no podemos resolver el teléfono sin un store de mapping.
    let numero = null;
    if (interno.endsWith('@s.whatsapp.net')) {
      numero = interno.split('@')[0].replace(/\D/g, '');
    }
    return { numero, name: null };
  };

  emitter.getProfilePicUrl = async (jid) => {
    // Timeout 3s — si el server WA está lento o el destinatario oculta la
    // foto, no queremos colgar el endpoint del bot (Laravel pollea profile
    // pics y un timeout largo satura los workers FPM).
    try {
      return await Promise.race([
        sock.profilePictureUrl(aInterno(jid), 'image'),
        new Promise((resolve) => setTimeout(() => resolve(null), 3000)),
      ]);
    } catch (_) {
      return null;
    }
  };

  emitter.getState = () => {
    if (destruido) return 'disconnected';
    return estadoConexion === 'open' ? 'connected' : estadoConexion === 'close' ? 'disconnected' : 'connecting';
  };

  emitter.destroy = async () => {
    destruido = true;
    try { sock?.end?.(new Error('destroy')); } catch (_) {}
  };

  // Fire-and-forget: el factory devuelve el emitter inmediatamente y los
  // eventos (qr, ready, message...) se emiten cuando iniciar() avanza.
  iniciar().catch(err => {
    console.error('[baileys] Error en init:', err.message);
    emitter.emit('disconnected', `init:${err.message}`);
    setTimeout(() => iniciar().catch(e => console.error('[baileys] Reintento falló:', e.message)), 10_000);
  });

  return emitter;
}

module.exports = { crearClienteBaileys };
