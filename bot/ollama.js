const axios = require('axios');

const OLLAMA_URL   = process.env.OLLAMA_URL   || 'http://host.docker.internal:11434';
const OLLAMA_MODEL = process.env.OLLAMA_MODEL || 'qwen3:4b';

const SYSTEM_PROMPT = `Sos el clasificador de mensajes del bot de WhatsApp de Crecer Reproducción, un centro de reproducción y genética humana.

Tu única tarea es clasificar el mensaje del paciente y devolver UN SOLO código de acción en formato JSON.
NUNCA redactes respuestas para el paciente.
NUNCA expliques tu razonamiento.
SOLO devolvé el JSON.

Códigos válidos:
PRIMERA_CONSULTA, TURNO_PORTAL, TURNO_ECO_CON_CUENTA, TURNO_ECO_SIN_CUENTA,
TURNO_DGP, TURNO_PRESERVACION, TURNO_PRESUPUESTO, RESULTADO_BETA,
RESULTADO_OTROS, MEDICACION_INSTRUCTIVO, ORDEN_MDP, ORDEN_OTRA_CIUDAD,
CONSULTA_CLINICA, DERIVAR_SECRETARIA, FALLBACK, IGNORAR

Usá IGNORAR cuando:
- El mensaje es un saludo genérico sin consulta ("hola", "buenas", "gracias", "perfecto")
- El mensaje es un fragmento sin contexto claro que no permite identificar ninguna intención
- El mensaje no parece provenir de un paciente (spam, publicidad, estados de WhatsApp)
- El hilo de mensajes no es coherente o no tiene relación con una clínica de reproducción

IMPORTANTE: Cualquier consulta sobre diagnósticos, medicación específica, resultados médicos
o síntomas → CONSULTA_CLINICA siempre, sin excepción.

Cuando el código requiere atención humana (TURNO_DGP, TURNO_PRESERVACION, TURNO_PRESUPUESTO,
RESULTADO_BETA, CONSULTA_CLINICA, DERIVAR_SECRETARIA, FALLBACK), incluí además un campo
"resumen" con una oración breve en español que describa el motivo de consulta del paciente.
Para el resto de códigos, omitir el campo resumen.

Formato de respuesta:
{"codigo": "CODIGO", "confianza": "alta|media|baja", "resumen": "opcional"}`;

const CODIGOS_VALIDOS = new Set([
  'PRIMERA_CONSULTA', 'TURNO_PORTAL', 'TURNO_ECO_CON_CUENTA', 'TURNO_ECO_SIN_CUENTA',
  'TURNO_DGP', 'TURNO_PRESERVACION', 'TURNO_PRESUPUESTO', 'RESULTADO_BETA',
  'RESULTADO_OTROS', 'MEDICACION_INSTRUCTIVO', 'ORDEN_MDP', 'ORDEN_OTRA_CIUDAD',
  'CONSULTA_CLINICA', 'DERIVAR_SECRETARIA', 'FALLBACK', 'IGNORAR',
]);

async function procesarConversacion(texto) {
  try {
    const response = await axios.post(
      `${OLLAMA_URL}/api/chat`,
      {
        model: OLLAMA_MODEL,
        stream: false,
        think: false,          // deshabilita razonamiento interno (qwen3) — ~2s vs ~50s
        format: 'json',        // fuerza JSON puro en content, sin texto extra
        messages: [
          { role: 'system', content: SYSTEM_PROMPT },
          { role: 'user',   content: `/no_think\n${texto}` },
        ],
        options: {
          temperature: 0,      // determinístico — clasificador, no generador creativo
        },
      },
      { timeout: 90_000 }
    );

    const contenido = response.data?.message?.content || '';
    return parsearRespuesta(contenido);
  } catch (err) {
    console.error('[ollama] Error al llamar a Ollama:', err.message);
    return { codigo: 'FALLBACK', confianza: 'baja' };
  }
}

function parsearRespuesta(contenido) {
  try {
    // Buscar todos los bloques {...} y quedarse con el último JSON válido
    // (el modelo puede razonar antes de responder, el JSON viene al final)
    const matches = [...contenido.matchAll(/\{[^{}]+\}/g)];
    let parsed = null;
    for (const m of [...matches].reverse()) {
      try { parsed = JSON.parse(m[0]); break; } catch (_) {}
    }
    if (!parsed) throw new Error('Sin JSON válido en respuesta');

    const codigo = parsed.codigo?.trim().toUpperCase();
    const confianza = ['alta', 'media', 'baja'].includes(parsed.confianza)
      ? parsed.confianza
      : 'baja';
    const resumen = parsed.resumen?.trim() || null;

    if (!CODIGOS_VALIDOS.has(codigo)) {
      console.warn(`[ollama] Código desconocido: "${codigo}" → FALLBACK`);
      return { codigo: 'FALLBACK', confianza: 'baja', resumen: null };
    }

    return { codigo, confianza, resumen };
  } catch (err) {
    console.warn('[ollama] No se pudo parsear respuesta:', contenido.slice(0, 100));
    return { codigo: 'FALLBACK', confianza: 'baja', resumen: null };
  }
}

/**
 * Genera un resumen breve (1-2 frases) del intercambio paciente↔bot.
 * Usado para que la secretaria sepa de qué viene la conversación sin leerla entera.
 * Usa el mismo Ollama pero con prompt distinto al clasificador. Texto plano, no JSON.
 */
async function generarResumen(texto) {
  const prompt = `Sos asistente de la secretaria de Crecer Reproducción.
Resumí esta conversación en una sola oración breve en español rioplatense, indicando el motivo principal de la consulta y cualquier dato útil (fecha, profesional, estudio, urgencia).
NO uses comillas ni markdown. NO uses encabezados. Solo la oración.

Conversación:
${texto.slice(0, 4000)}

Resumen:`;

  try {
    const response = await axios.post(
      `${OLLAMA_URL}/api/generate`,
      {
        model:  OLLAMA_MODEL,
        prompt: prompt,
        stream: false,
        think:  false,
        options: { temperature: 0.2, num_predict: 120 },
      },
      { timeout: 60_000 }
    );
    let resumen = (response.data?.response || '').trim();
    // Limpieza: sacar comillas, markdown leve
    resumen = resumen.replace(/^["'`*_]+|["'`*_]+$/g, '').trim();
    if (!resumen || resumen.length < 5) return null;
    if (resumen.length > 280) resumen = resumen.slice(0, 280) + '…';
    return resumen;
  } catch (err) {
    console.error('[ollama] Error en generarResumen:', err.message);
    return null;
  }
}

module.exports = { procesarConversacion, generarResumen };
