const fs = require('fs');
const path = require('path');

const TEXTOS_PATH = path.join(__dirname, 'textos.json');

let TEXTOS = cargarTextos();

function cargarTextos() {
  return JSON.parse(fs.readFileSync(TEXTOS_PATH, 'utf-8'));
}

// Recargar automáticamente cuando la clínica edita textos.json desde el panel
fs.watchFile(TEXTOS_PATH, { interval: 2000 }, () => {
  try {
    TEXTOS = cargarTextos();
    console.log('[respuestas] Textos recargados desde textos.json');
  } catch (err) {
    console.error('[respuestas] Error recargando textos:', err.message);
  }
});

/**
 * Devuelve el texto del código de acción, con el aviso de fuera de horario si corresponde.
 */
function obtenerRespuesta(codigo, { enHorario = true } = {}) {
  let texto = TEXTOS[codigo];
  if (!texto) return null;

  const fueraHorarioMsg = TEXTOS['FUERA_HORARIO'] || '';

  if (!enHorario) {
    texto = texto.replace('{{FUERA_HORARIO}}', fueraHorarioMsg);
  } else {
    texto = texto.replace('\n\n{{FUERA_HORARIO}}', '').replace('{{FUERA_HORARIO}}', '');
  }

  return texto.trim();
}

async function enviarRespuesta(client, contacto, texto) {
  if (!texto) return;
  const { markSent } = require('./whatsapp');
  const sent = await client.sendMessage(contacto, texto);
  markSent(sent?.id?._serialized);
}

module.exports = { obtenerRespuesta, enviarRespuesta };
