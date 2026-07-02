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
 * Con reintentos: una derivación perdida = paciente que nunca entra a la cola
 * de atención. Antes era un solo POST y cualquier restart de Laravel la tiraba.
 */
const DERIVAR_ESPERA_MS = [5_000, 30_000, 120_000];

async function derivarConversacion(contacto, codigo, resumen = null) {
  if (MODO_SHADOW) { console.log(`[shadow] derivar ${contacto} → ${codigo}`); return; }
  for (let i = 0; i <= DERIVAR_ESPERA_MS.length; i++) {
    try {
      await axios.post(
        `${LARAVEL_URL}/bot/conversacion/derivar`,
        { contacto, area: BOT_AREA, codigo, resumen_llm: resumen },
        { headers: headers(), timeout: 10_000 }
      );
      console.log(`[cola] Derivado: ${contacto} → ${codigo}${i > 0 ? ` (reintento ${i})` : ''}`);
      return;
    } catch (err) {
      if (i === DERIVAR_ESPERA_MS.length) {
        console.error(`[cola] Derivación PERDIDA (${contacto} → ${codigo}) tras ${i + 1} intentos: ${err.message}`);
        return;
      }
      console.warn(`[cola] Error al derivar (${err.message}) — reintento en ${DERIVAR_ESPERA_MS[i] / 1000}s`);
      await new Promise((r) => setTimeout(r, DERIVAR_ESPERA_MS[i]));
    }
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
