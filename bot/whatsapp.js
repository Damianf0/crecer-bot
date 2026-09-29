// Shell delgada: instancia el adapter (wwebjs o baileys según BOT_WA_CLIENT)
// y conecta sus eventos a la lógica del bot (estado, persistencia, clasificación).
//
// Toda la lógica de WhatsApp (Puppeteer/Chromium para wwebjs, WebSocket para
// baileys, tracking de salientes, watchdog) vive en bot/clientes/*.

const { crearCliente } = require('./cliente-wa');
const { setStatus, setQR, setPhone } = require('./estado-bot');
const { recibirMensaje } = require('./mensajes');
const { guardarMensajeEntrante, guardarMensajeSalienteExterno, registrarAck } = require('./mensajesApi');
const { registrarCliente } = require('./server');
const { BOT_AREA } = require('./area');
const { esDifusion } = require('./difusion');

let _cliente = null; // para el apagado limpio desde index.js (SIGTERM)

function obtenerCliente() {
  return _cliente;
}

async function iniciarWhatsApp() {
  setStatus('iniciando');
  const cliente = crearCliente();
  _cliente = cliente;

  cliente.on('qr', (dataUrl) => {
    setQR(dataUrl);
  });

  cliente.on('ready', ({ phone }) => {
    setPhone(phone);
    setStatus('listo');
    registrarCliente(cliente);
  });

  cliente.on('disconnected', (reason) => {
    console.warn('[whatsapp] Desconectado:', reason);
    setStatus('desconectado');
  });

  cliente.on('message', async (msg) => {
    // Shadow sobre un número productivo: el bot real ya clasifica y deriva
    // estos mensajes; acá solo interesa ver que entran.
    if (BOT_AREA === 'test') {
      console.log(`[shadow] entrante ${msg.from}${msg.numero ? ` (${msg.numero})` : ''} ${msg.type} ${msg.wa_id}: ${(msg.body || '').slice(0, 60)}`);
      return;
    }
    try {
      // Guardar en inbox primero (no bloqueante)
      guardarMensajeEntrante(msg).catch(e => console.error('[mensajesApi] Error guardando:', e.message));
      // Número de difusiones: las respuestas van a su cola y las atiende una
      // persona. Sin clasificador ni autorespuesta (los textos del bot son de
      // atención); las bajas ("BAJA") las detecta Laravel al guardar.
      if (BOT_AREA === 'difusion') return;
      // Procesar para clasificación y respuesta automática
      await recibirMensaje(cliente, msg.from, msg);
    } catch (err) {
      console.error(`[whatsapp] Error procesando mensaje de ${msg.from}:`, err.message);
    }
  });

  // Acuses para las estadísticas de difusiones: el número de difusiones los
  // reporta todos; los de las áreas, solo los de mensajes de una campaña
  // (ver difusion.js). Nada para el shadow.
  if (BOT_AREA !== 'test') {
    cliente.on('ack', ({ wa_id, ack }) => {
      if (BOT_AREA !== 'difusion' && !esDifusion(wa_id)) return;
      registrarAck(wa_id, ack).catch(() => {});   // postConReintentos ya loguea
    });
  }

  cliente.on('message_outgoing', async (msg) => {
    // Saliente desde el celular pareado (no enviado por sendText/sendMedia).
    // El adapter ya filtró los que mandamos nosotros, así que estos siempre
    // son mensajes reales del usuario respondiendo a mano.
    console.log(`[whatsapp] saliente externo (celular) → ${msg.to}`);
    try {
      await guardarMensajeSalienteExterno(msg);
    } catch (err) {
      console.error('[whatsapp] Error guardando saliente externo:', err.message);
    }
  });
}

module.exports = { iniciarWhatsApp, obtenerCliente };
