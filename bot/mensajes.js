const fs   = require('fs');
const path = require('path');
const { procesarConversacion } = require('./ollama');
const { obtenerRespuesta, enviarRespuesta } = require('./respuestas');
const { derivarConversacion, obtenerHistorial, guardarHistorial, marcarLeido } = require('./cola');
const { estaEnHorario } = require('./horario');
const { estado: estadoBot, pushClasificacion } = require('./estado-bot');
const { guardarMensajeSaliente, transcribirAudio } = require('./mensajesApi');

const MEDIA_DIR = '/app/media';

const ESPERA_MENSAJES    = parseInt(process.env.ESPERA_MENSAJES    || '8000');
const ESPERA_MAXIMA      = parseInt(process.env.ESPERA_MAXIMA      || '45000');
const RESET_CONVERSACION = parseInt(process.env.RESET_CONVERSACION || '1800000');

const CODIGOS_DERIVACION = new Set([
  'TURNO_DGP', 'TURNO_PRESERVACION', 'TURNO_PRESUPUESTO',
  'CONSULTA_CLINICA', 'DERIVAR_SECRETARIA', 'FALLBACK', 'RESULTADO_BETA',
]);

const MAX_HISTORIAL = 3000; // caracteres máximos de historial

// Estado por contacto: { textos, timer, timerMax, ultimoMensaje, historial, historialCargado }
const conversaciones = new Map();

async function recibirMensaje(client, contacto, msg) {
  const texto = await extraerTexto(msg);
  if (!texto) return;

  console.log(`[mensajes] ${contacto}: "${texto.slice(0, 60)}"`);

  if (!conversaciones.has(contacto)) {
    conversaciones.set(contacto, {
      textos: [], timer: null, timerMax: null,
      ultimoMensaje: null, historial: '', historialCargado: false,
    });
  }

  const s = conversaciones.get(contacto);

  if (s.ultimoMensaje && Date.now() - s.ultimoMensaje > RESET_CONVERSACION) {
    limpiarTimers(s);
    s.textos = [];
    s.historial = '';
    s.historialCargado = false;
    console.log(`[mensajes] Conversación reseteada para ${contacto}`);
  }

  s.ultimoMensaje = Date.now();
  s.textos.push(texto);

  if (s.timer) clearTimeout(s.timer);
  s.timer = setTimeout(() => procesarYResponder(client, contacto), ESPERA_MENSAJES);

  if (!s.timerMax) {
    s.timerMax = setTimeout(() => {
      if (s.timer) clearTimeout(s.timer);
      procesarYResponder(client, contacto);
    }, ESPERA_MAXIMA);
  }
}

async function procesarYResponder(client, contacto) {
  const s = conversaciones.get(contacto);
  if (!s || s.textos.length === 0) return;

  limpiarTimers(s);

  // Cargar historial desde DB si es la primera vez (bot recién reiniciado)
  if (!s.historialCargado) {
    s.historial = await obtenerHistorial(contacto);
    s.historialCargado = true;
  }

  const textosNuevos   = s.textos.map(t => `[Paciente] ${t}`).join('\n');
  const textoCombinado = s.historial
    ? `${s.historial}\n${textosNuevos}`
    : textosNuevos;

  s.textos    = [];
  s.historial = textoCombinado.slice(-MAX_HISTORIAL);

  console.log(`[mensajes] Procesando: "${textosNuevos.slice(0, 80)}" (historial: ${s.historial.length} chars)`);

  const enHorario  = estaEnHorario();
  const { codigo, confianza, resumen } = await procesarConversacion(textoCombinado);
  const modoPrueba = estadoBot.modoPrueba;

  console.log(`[mensajes] ${modoPrueba ? '[PRUEBA] ' : ''}${codigo} (${confianza})`);

  if (codigo === 'IGNORAR') {
    console.log(`[mensajes] Mensaje ignorado: "${textosNuevos.slice(0, 60)}"`);
    guardarHistorial(contacto, s.historial).catch(() => {});
    return;
  }

  const respuesta = obtenerRespuesta(codigo, { enHorario });

  // Registrar clasificación para el panel Electron
  pushClasificacion({
    ts: new Date().toISOString(),
    contacto, texto: textoCombinado, codigo, confianza,
    respuesta: respuesta || null, enHorario, enviado: !modoPrueba,
  });

  if (!modoPrueba && respuesta) {
    await enviarRespuesta(client, contacto, respuesta);
    guardarMensajeSaliente(contacto, respuesta).catch(() => {});
    s.historial = (s.historial + `\n[Bot] ${respuesta}`).slice(-MAX_HISTORIAL);
  }

  if (CODIGOS_DERIVACION.has(codigo)) {
    // Notifica a Laravel directamente sobre la conversación WA — sin tabla derivaciones.
    // derivarConversacion ya loguea y reintenta adentro; el catch es solo red de seguridad.
    derivarConversacion(contacto, codigo, resumen).catch((e) => console.error('[mensajes] derivar falló:', e.message));
  } else if (!modoPrueba && respuesta) {
    marcarLeido(contacto).catch(() => {});
  }

  // Persistir historial actualizado
  guardarHistorial(contacto, s.historial).catch(() => {});
}

async function extraerTexto(msg) {
  if (msg.type === 'texto' && msg.body) return msg.body.trim();
  if (msg.type === 'audio') {
    try {
      const media = await msg.downloadMedia();
      if (media) {
        const ext      = media.mimetype?.includes('ogg') ? 'ogg' : 'mp3';
        const filename = `${Date.now()}_${msg.from.replace('@c.us', '')}.${ext}`;
        const filePath = path.join(MEDIA_DIR, filename);
        fs.writeFileSync(filePath, Buffer.from(media.data, 'base64'));
        const transcripcion = await transcribirAudio(filePath);
        if (transcripcion) return transcripcion;
      }
    } catch (e) {
      console.warn('[mensajes] No se pudo transcribir audio:', e.message);
    }
    return '[audio sin transcripción]';
  }
  return null;
}

function limpiarTimers(s) {
  if (s.timer)    { clearTimeout(s.timer);    s.timer    = null; }
  if (s.timerMax) { clearTimeout(s.timerMax); s.timerMax = null; }
}

module.exports = { recibirMensaje };
