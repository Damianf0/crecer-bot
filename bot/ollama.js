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
 * Quita emojis y símbolos no-letra del texto antes de pasarlo al LLM.
 * Razón: qwen3:4b asocia emojis con contextos multilingues y hace drift
 * de idioma (sobre todo a portugués) cuando el input los contiene en abundancia.
 * Los emojis no aportan al "motivo de consulta" del paciente.
 */
function stripEmojis(texto) {
  // Strip de bloques Unicode emoji/símbolos típicos sin tocar letras acentuadas.
  return texto
    .replace(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{1F1E6}-\u{1F1FF}\u{FE0F}\u{200D}]/gu, '')
    .replace(/[ \t]{2,}/g, ' ')
    .replace(/\n{3,}/g, '\n\n');
}

/**
 * Detecta si el texto está (parcial o totalmente) en portugués.
 * Heurística simple — qwen3:4b a veces droppea a portugués; usamos esto para
 * descartar el resumen y dejar que se regenere más tarde (resumen_intento_at
 * marca el throttle de 10 min en Laravel).
 */
function pareceEnPortugues(texto) {
  const t = texto.toLowerCase();
  // Marcadores fuertes: caracteres ç ã õ son inusuales en castellano
  if (/[ãõç]/i.test(texto)) return true;
  // Sufijos ortográficos portugueses típicos
  if (/\b\w+ção\b|\b\w+ções\b|\b\w+ões\b/.test(t)) return true;
  // Palabras funcionales portuguesas frecuentes
  const palabras = [' não ', ' você ', ' está ', ' obrigad', ' uma ', ' já ', ' são ', ' foi ', ' este ', ' essa '];
  let hits = 0;
  for (const p of palabras) if (t.includes(p)) hits++;
  return hits >= 2;
}

/**
 * Genera un resumen breve (1-2 frases) del intercambio paciente↔bot.
 * Usado para que la secretaria sepa de qué viene la conversación sin leerla entera.
 * Usa el mismo Ollama pero con prompt distinto al clasificador. Texto plano, no JSON.
 */
const SYSTEM_PROMPT_RESUMEN = `Sos asistente de la secretaria de Crecer Reproducción Humana. Tu tarea es resumir conversaciones de pacientes.

REGLAS ESTRICTAS:
1. Respondé SIEMPRE en castellano rioplatense (Argentina). NUNCA uses portugués, inglés u otro idioma — aunque el paciente haya escrito en otro idioma, vos resumís en castellano.
2. Devolvé SOLO una oración breve (máx 30 palabras) con el motivo de la consulta y datos útiles (fecha, profesional, estudio, urgencia).
3. NUNCA expliques tu razonamiento. NUNCA uses "Okay", "Let's", "The user", "Aqui". Empezá directo con el resumen.
4. NO uses comillas, markdown, encabezados, emojis ni saltos de línea.`;

async function generarResumen(texto) {
  const textoLimpio = stripEmojis(texto).slice(0, 4000);

  try {
    const response = await axios.post(
      `${OLLAMA_URL}/api/chat`,
      {
        model:   OLLAMA_MODEL,
        stream:  false,
        think:   false,
        format:  'json',   // fuerza JSON puro, igual que el clasificador → corta el "thinking out loud" de qwen3
        messages: [
          { role: 'system', content: SYSTEM_PROMPT_RESUMEN + '\n\nFormato de respuesta (JSON estricto):\n{"resumen": "oración en castellano rioplatense"}' },
          { role: 'user',   content: `/no_think\nConversación:\n${textoLimpio}` },
        ],
        options: { temperature: 0.2, num_predict: 200 },
      },
      { timeout: 60_000 }
    );

    const raw = response.data?.message?.content || '';
    let resumen = null;
    try {
      const parsed = JSON.parse(raw);
      resumen = (parsed.resumen || '').trim();
    } catch (_) {
      // Fallback: intentar extraer { ... } del texto
      const m = raw.match(/\{[^{}]*"resumen"[^{}]*\}/);
      if (m) {
        try { resumen = (JSON.parse(m[0]).resumen || '').trim(); } catch (_) {}
      }
    }
    if (!resumen) return null;

    // Limpieza: comillas, markdown, emojis residuales
    resumen = resumen.replace(/^["'`*_\-]+|["'`*_\-]+$/g, '').trim();
    resumen = stripEmojis(resumen).trim();
    if (resumen.length < 5) return null;

    // Guard contra drift de idioma: si salió en portugués (entero o mixto),
    // devolvemos null para que el throttle de 10min de Laravel reintente.
    if (pareceEnPortugues(resumen)) {
      console.warn('[ollama] Resumen descartado por idioma (portugués):', resumen.slice(0, 100));
      return null;
    }

    if (resumen.length > 280) resumen = resumen.slice(0, 280) + '…';
    return resumen;
  } catch (err) {
    console.error('[ollama] Error en generarResumen:', err.message);
    return null;
  }
}

module.exports = { procesarConversacion, generarResumen };
