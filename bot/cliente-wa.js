// Interfaz unificada del cliente de WhatsApp. Aísla al resto del bot del
// wrapper concreto (hoy: solo whatsapp-web.js — Baileys se abandonó el
// 2026-06-16 y su wrapper se eliminó el 2026-07-06; historia en git y en
// project_migracion_baileys.md).
//
// Contrato que cumple el wrapper:
//
//   Eventos (EventEmitter):
//     'qr'                (dataUrl string PNG)
//     'ready'             ({ phone: string })
//     'disconnected'      (reason)
//     'message'           (msg normalizado)   ← entrante, !fromMe
//     'message_outgoing'  (msg normalizado)   ← saliente desde celular pareado,
//                                              YA filtrado: nunca dispara para
//                                              mensajes enviados por sendText/sendMedia
//
//   Métodos:
//     async sendText(jid, texto, opts?) → { wa_id }
//        opts.quoted: { wa_id, fromMe, preview } | undefined
//           Cuando está presente, el mensaje se manda como reply al wa_id.
//           Baileys arma el contextInfo a partir de los 3 campos; wwebjs solo
//           usa wa_id (quotedMessageId).
//     async sendMedia(jid, { mimetype, base64, filename, caption }) → { wa_id }
//     async checkNumber(digits) → { registered, normalizedId }
//     async resolveContact(jid) → { numero, name }
//     async getProfilePicUrl(jid) → url | null
//     getState() → 'connected' | 'disconnected' | 'connecting' | 'unknown'
//     async destroy()
//
//   Shape del msg normalizado (eventos):
//     {
//       from,                 // siempre @c.us, @lid o @g.us (Baileys → @c.us)
//       to,                   // idem
//       fromMe: bool,
//       type: 'texto' | 'audio' | 'imagen' | 'video' | 'documento' | 'sticker',
//       body: string | null,  // texto o caption; null para media sin caption
//       wa_id: string,        // ID estable serializado, sirve para tracking/dedup
//       timestamp: Date,
//       downloadMedia: async () => ({ mimetype, data:base64 }) | null
//     }
//
// Convención JID: el exterior del adapter siempre usa el sufijo histórico
// '@c.us' para usuarios (y '@lid' / '@g.us' sin cambio). El wrapper Baileys
// traduce a '@s.whatsapp.net' adentro. Esto evita tocar BD y código Laravel.

const TIPOS_VALIDOS = new Set(['texto', 'audio', 'imagen', 'video', 'documento', 'sticker']);

function asegurarShape(msg) {
  if (!msg || typeof msg !== 'object') throw new Error('msg normalizado inválido');
  if (typeof msg.from !== 'string')     throw new Error('msg.from requerido');
  if (typeof msg.fromMe !== 'boolean')  throw new Error('msg.fromMe requerido');
  if (!TIPOS_VALIDOS.has(msg.type))     throw new Error(`msg.type inválido: ${msg.type}`);
  if (typeof msg.wa_id !== 'string')    throw new Error('msg.wa_id requerido');
  return msg;
}

function crearCliente() {
  const tipo = (process.env.BOT_WA_CLIENT || 'wwebjs').trim().toLowerCase();
  if (tipo !== 'wwebjs') {
    console.warn(`[cliente-wa] BOT_WA_CLIENT="${tipo}" ya no está soportado (Baileys eliminado 2026-07-06) — usando 'wwebjs'`);
  }
  const { crearClienteWwebjs } = require('./clientes/wwebjs');
  console.log('[cliente-wa] Backend: wwebjs');
  return crearClienteWwebjs();
}

module.exports = { crearCliente, asegurarShape };
