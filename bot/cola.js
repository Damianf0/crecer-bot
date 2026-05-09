const axios = require('axios');

const LARAVEL_URL   = process.env.LARAVEL_URL   || 'http://web/api';
const LARAVEL_TOKEN = process.env.LARAVEL_TOKEN || '';

const headers = () => ({
  Authorization: `Bearer ${LARAVEL_TOKEN}`,
  'Content-Type': 'application/json',
});

/**
 * Notifica a Laravel que la conversación requiere atención humana.
 * Agrega nota interna con clasificación y resumen LLM.
 */
async function derivarConversacion(contacto, codigo, resumen = null) {
  try {
    await axios.post(
      `${LARAVEL_URL}/bot/conversacion/derivar`,
      { contacto, codigo, resumen_llm: resumen },
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
  try {
    const resp = await axios.get(
      `${LARAVEL_URL}/bot/conversacion/historial`,
      { params: { contacto }, headers: headers(), timeout: 5_000 }
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
  try {
    await axios.patch(
      `${LARAVEL_URL}/bot/conversacion/historial`,
      { contacto, historial },
      { headers: headers(), timeout: 5_000 }
    );
  } catch (_) {
    // no crítico
  }
}

async function marcarLeido(contacto) {
  try {
    await axios.post(
      `${LARAVEL_URL}/bot/mensajes/marcar-leido`,
      { contacto },
      { headers: headers(), timeout: 5_000 }
    );
  } catch (_) {}
}

module.exports = { derivarConversacion, obtenerHistorial, guardarHistorial, marcarLeido };
