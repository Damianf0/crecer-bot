// Interfaz unificada del cliente de WhatsApp. Aísla al resto del bot de qué
// librería se usa por debajo (whatsapp-web.js / Baileys). La elección la
// hace BOT_WA_CLIENT en docker-compose por servicio: default 'wwebjs' para
// no romper los bots existentes, 'baileys' para el bot que se está migrando.
//
// Contrato que cumplen ambos wrappers:
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
//     async sendText(jid, texto) → { wa_id }
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

  if (tipo === 'baileys') {
    const { crearClienteBaileys } = require('./clientes/baileys');
    console.log('[cliente-wa] Backend: baileys');
    return crearClienteBaileys();
  }

  if (tipo !== 'wwebjs') {
    console.warn(`[cliente-wa] BOT_WA_CLIENT="${tipo}" no reconocido — usando 'wwebjs'`);
  }
  const { crearClienteWwebjs } = require('./clientes/wwebjs');
  console.log('[cliente-wa] Backend: wwebjs');
  return crearClienteWwebjs();
}

module.exports = { crearCliente, asegurarShape };
