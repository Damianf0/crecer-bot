// Shell delgada: instancia el adapter (wwebjs o baileys según BOT_WA_CLIENT)
// y conecta sus eventos a la lógica del bot (estado, persistencia, clasificación).
//
// Toda la lógica de WhatsApp (Puppeteer/Chromium para wwebjs, WebSocket para
// baileys, tracking de salientes, watchdog) vive en bot/clientes/*.

const { crearCliente } = require('./cliente-wa');
const { setStatus, setQR, setPhone } = require('./estado-bot');
const { recibirMensaje } = require('./mensajes');
const { guardarMensajeEntrante, guardarMensajeSalienteExterno } = require('./mensajesApi');
const { registrarCliente } = require('./server');

async function iniciarWhatsApp() {
  setStatus('iniciando');
  const cliente = crearCliente();

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
    try {
      // Guardar en inbox primero (no bloqueante)
      guardarMensajeEntrante(msg).catch(e => console.error('[mensajesApi] Error guardando:', e.message));
      // Procesar para clasificación y respuesta automática
      await recibirMensaje(cliente, msg.from, msg);
    } catch (err) {
      console.error(`[whatsapp] Error procesando mensaje de ${msg.from}:`, err.message);
    }
  });

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

module.exports = { iniciarWhatsApp };
