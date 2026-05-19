const axios = require('axios');
const { BOT_AREA } = require('./area');

const LARAVEL_URL   = process.env.LARAVEL_URL   || 'http://web/api';
const LARAVEL_TOKEN = process.env.LARAVEL_TOKEN || '';
const MODO_SHADOW   = BOT_AREA === 'test';

const headers = () => ({
  Authorization: `Bearer ${LARAVEL_TOKEN}`,
  'Content-Type': 'application/json',
});

/**
 * Notifica a Laravel que la conversación requiere atención humana.
 * Agrega nota interna con clasificación y resumen LLM.
 */
async function derivarConversacion(contacto, codigo, resumen = null) {
  if (MODO_SHADOW) { console.log(`[shadow] derivar ${contacto} → ${codigo}`); return; }
  try {
    await axios.post(
      `${LARAVEL_URL}/bot/conversacion/derivar`,
      { contacto, area: BOT_AREA, codigo, resumen_llm: resumen },
      { headers: headers(), timeout: 10_000 }
    );
    console.log(`[cola] Derivado: ${contacto} → ${codigo}`);
  } catch (err) {
    console.error('[cola] Error al derivar conversación:', err.message);
  }
}

/**
 * Recupera el historial LLM persistido para un contacto.
 */
async function obtenerHistorial(contacto) {
  if (MODO_SHADOW) return '';
  try {
    const resp = await axios.get(
      `${LARAVEL_URL}/bot/conversacion/historial`,
      { params: { contacto, area: BOT_AREA }, headers: headers(), timeout: 5_000 }
    );
    return resp.data?.historial || '';
  } catch (_) {
    return '';
  }
}

/**
 * Persiste el historial LLM actualizado para un contacto.
 */
async function guardarHistorial(contacto, historial) {
  if (MODO_SHADOW) return;
  try {
    await axios.patch(
      `${LARAVEL_URL}/bot/conversacion/historial`,
      { contacto, area: BOT_AREA, historial },
      { headers: headers(), timeout: 5_000 }
    );
  } catch (_) {
    // no crítico
  }
}

async function marcarLeido(contacto) {
  if (MODO_SHADOW) return;
  try {
    await axios.post(
      `${LARAVEL_URL}/bot/mensajes/marcar-leido`,
      { contacto, area: BOT_AREA },
      { headers: headers(), timeout: 5_000 }
    );
  } catch (_) {}
}

module.exports = { derivarConversacion, obtenerHistorial, guardarHistorial, marcarLeido };
