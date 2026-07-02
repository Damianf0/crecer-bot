const axios    = require('axios');
const FormData = require('form-data');
const fs       = require('fs');
const path     = require('path');

const { BOT_AREA } = require('./area');

const LARAVEL_URL    = process.env.LARAVEL_URL    || 'http://web/api';
const LARAVEL_TOKEN  = process.env.LARAVEL_TOKEN  || '';
const WHISPER_URL    = process.env.WHISPER_URL    || 'http://whisper:9000';
const BOT_PUBLIC_URL = process.env.BOT_PUBLIC_URL || ('http://localhost:' + (process.env.PORT || '3001'));
const MEDIA_DIR      = '/app/media';

// Modo shadow: el container `bot-test` arranca con BOT_AREA=test para validar
// Baileys con un número personal sin tocar la BD de producción. Cuando estamos
// en ese modo, los wrappers que guardan en Laravel hacen no-op (los mensajes
// se loggean pero no se persisten). Ver project_migracion_baileys.md.
const MODO_SHADOW = BOT_AREA === 'test';
if (MODO_SHADOW) {
  console.log('[mensajesApi] MODO SHADOW (area=test) — los mensajes NO se persisten en Laravel');
}

if (!fs.existsSync(MEDIA_DIR)) {
  fs.mkdirSync(MEDIA_DIR, { recursive: true });
}

const api = axios.create({
  baseURL: LARAVEL_URL,
  headers: { Authorization: `Bearer ${LARAVEL_TOKEN}` },
  timeout: 8000,
});

// POST a Laravel con reintentos. Antes un solo intento con timeout de 8s:
// cualquier restart del stack (OPcache, wsl --shutdown, deploy) tiraba el
// mensaje en silencio y el panel quedaba desincronizado con el celular.
// 4 intentos espaciados cubren ~3 min de Laravel caído. _pendientes acota la
// memoria si Laravel se cae con tráfico alto: pasado el tope se vuelve al
// comportamiento viejo (un intento y error logueado).
const REINTENTOS_ESPERA_MS = [5_000, 30_000, 120_000];
const MAX_PENDIENTES = 200;
let _pendientes = 0;

async function postConReintentos(url, payload, etiqueta) {
  try {
    await api.post(url, payload);
    return;
  } catch (err) {
    if (_pendientes >= MAX_PENDIENTES) {
      console.error(`[mensajesApi] ${etiqueta}: fallo y cola de reintentos llena (${_pendientes}) — descartado: ${err.message}`);
      throw err;
    }
    console.warn(`[mensajesApi] ${etiqueta}: fallo (${err.message}) — reintentando`);
  }

  _pendientes++;
  try {
    for (let i = 0; i < REINTENTOS_ESPERA_MS.length; i++) {
      await new Promise((r) => setTimeout(r, REINTENTOS_ESPERA_MS[i]));
      try {
        await api.post(url, payload);
        console.log(`[mensajesApi] ${etiqueta}: guardado al reintento ${i + 1}`);
        return;
      } catch (err) {
        if (i === REINTENTOS_ESPERA_MS.length - 1) {
          console.error(`[mensajesApi] ${etiqueta}: PERDIDO tras ${REINTENTOS_ESPERA_MS.length + 1} intentos: ${err.message}`);
          throw err;
        }
      }
    }
  } finally {
    _pendientes--;
  }
}

// Persiste el archivo de un mensaje multimedia y devuelve su URL pública.
// `msg` viene en el shape normalizado del adapter (type, downloadMedia(), etc).
async function persistirMedia(msg, fallbackExt) {
  const media = await msg.downloadMedia();
  if (!media) return null;
  const ext      = mimeExt(media.mimetype, fallbackExt);
  const contacto = (msg.from || msg.to || 'unknown').replace('@c.us', '').replace('@lid', 'lid').replace('@g.us', 'g');
  const filename = `${Date.now()}_${contacto}.${ext}`;
  const filePath = path.join(MEDIA_DIR, filename);
  fs.writeFileSync(filePath, Buffer.from(media.data, 'base64'));
  return { archivo_url: `${BOT_PUBLIC_URL}/media/${filename}`, filePath };
}

/**
 * Guarda un mensaje entrante en el inbox de Laravel.
 * Se llama al instante cuando el adapter emite 'message'.
 */
async function guardarMensajeEntrante(msg) {
  if (MODO_SHADOW) { console.log(`[shadow] entrante ${msg.from} (${msg.type}): ${(msg.body || '').slice(0, 60)}`); return; }
  try {
    const contacto = msg.from;

    // Ignorar estados de WhatsApp (no son conversaciones reales)
    if (contacto === 'status@broadcast' || contacto?.endsWith('@broadcast')) return;

    let tipo        = msg.type;
    let contenido   = msg.body;
    let archivo_url = null;

    if (tipo === 'audio') {
      try {
        const persisted = await persistirMedia(msg, 'ogg');
        if (persisted) {
          archivo_url = persisted.archivo_url;
          contenido   = await transcribirAudio(persisted.filePath).catch(() => null);
        }
      } catch (e) {
        console.warn('[mensajesApi] Audio no descargado:', e.message);
      }
    } else if (tipo === 'imagen' || tipo === 'sticker') {
      try {
        const persisted = await persistirMedia(msg, 'jpg');
        if (persisted) archivo_url = persisted.archivo_url;
      } catch (e) {
        console.warn('[mensajesApi] Imagen no descargada:', e.message);
      }
    } else if (tipo === 'documento' || tipo === 'video') {
      try {
        const persisted = await persistirMedia(msg, tipo === 'video' ? 'mp4' : 'bin');
        if (persisted) archivo_url = persisted.archivo_url;
      } catch (e) {
        console.warn('[mensajesApi] Documento no descargado:', e.message);
      }
    } else if (tipo !== 'texto') {
      return; // tipo no soportado
    }

    await postConReintentos('/bot/mensajes', {
      contacto,
      area:      BOT_AREA,
      tipo,
      contenido,
      archivo_url,
      wa_id:     msg.wa_id || null,
      // Reply: si el mensaje cita otro, mandamos el wa_id + preview del original
      // para que el panel pueda mostrar el bubble citado arriba del mensaje.
      quoted_wa_id:   msg.quoted?.wa_id   || null,
      quoted_autor:   msg.quoted?.autor   || null,
      quoted_preview: msg.quoted?.preview || null,
      timestamp: (msg.timestamp || new Date()).toISOString(),
    }, `entrante ${contacto}`);

  } catch (err) {
    console.error('[mensajesApi] Error guardando entrante:', err.message);
  }
}

/**
 * Guarda la respuesta automática del bot como mensaje saliente.
 */
async function guardarMensajeSaliente(contacto, texto, waId = null) {
  if (MODO_SHADOW) { console.log(`[shadow] saliente ${contacto}: ${(texto || '').slice(0, 60)}`); return; }
  try {
    await postConReintentos('/bot/mensajes/saliente', {
      contacto,
      area:       BOT_AREA,
      contenido:  texto,
      wa_id:      waId,
      timestamp: new Date().toISOString(),
    }, `saliente ${contacto}`);
  } catch (err) {
    console.error('[mensajesApi] Error guardando saliente:', err.message);
  }
}

/**
 * Guarda un mensaje saliente recibido desde el celular pareado (fromMe=true,
 * NO enviado por sendText/sendMedia — el adapter ya filtra esos).
 * El backend deduplica por wa_id.
 */
async function guardarMensajeSalienteExterno(msg) {
  if (MODO_SHADOW) { console.log(`[shadow] saliente externo ${msg.to} (${msg.type}): ${(msg.body || '').slice(0, 60)}`); return; }
  try {
    const contacto = msg.to;
    const tipo     = msg.type;
    let contenido  = msg.body;
    let archivo_url = null;

    // Para audio/imagen/documento del celular, downloadMedia puede fallar si
    // ya se purgó del cache. No bloqueamos el guardado por eso — Laravel guarda
    // texto/contenido y, si vino con archivo, queda registrado el tipo.
    if (tipo !== 'texto') {
      try {
        const persisted = await persistirMedia(msg, 'bin');
        if (persisted) archivo_url = persisted.archivo_url;
      } catch (e) {
        console.warn('[mensajesApi] downloadMedia falló para saliente externo:', e.message);
      }
    }

    if (!['texto', 'audio', 'imagen', 'video', 'documento', 'sticker'].includes(tipo)) return;

    await postConReintentos('/bot/mensajes/saliente', {
      contacto,
      area:    BOT_AREA,
      tipo,
      contenido,
      archivo_url,
      wa_id:   msg.wa_id || null,
      timestamp: (msg.timestamp || new Date()).toISOString(),
    }, `saliente externo ${contacto}`);
  } catch (err) {
    console.error('[mensajesApi] Error guardando saliente externo:', err.message);
  }
}

function mimeExt(mimetype, fallback = 'bin') {
  const map = {
    'audio/ogg': 'ogg', 'audio/mpeg': 'mp3', 'audio/mp4': 'mp4',
    'image/jpeg': 'jpg', 'image/jpg': 'jpg', 'image/png': 'png',
    'image/webp': 'webp', 'image/gif': 'gif',
    'video/mp4': 'mp4', 'video/webm': 'webm',
    'application/pdf': 'pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
    'application/msword': 'doc',
  };
  const base = mimetype?.split(';')[0]?.trim();
  return map[base] || base?.split('/')[1] || fallback;
}

/**
 * Envía un archivo de audio al servicio Whisper y devuelve la transcripción.
 */
async function transcribirAudio(filePath) {
  try {
    const form = new FormData();
    form.append('audio_file', fs.createReadStream(filePath), {
      filename: path.basename(filePath),
      contentType: 'audio/ogg',
    });

    const resp = await axios.post(
      `${WHISPER_URL}/asr?task=transcribe&language=es&output=txt`,
      form,
      { headers: form.getHeaders(), timeout: 120_000 }
    );

    const texto = (typeof resp.data === 'string' ? resp.data : resp.data?.text || '')
      .trim()
      .replace(/\n+/g, ' ');

    if (texto) console.log(`[whisper] Transcripción: "${texto.slice(0, 80)}…"`);
    return texto || null;
  } catch (err) {
    console.warn('[whisper] No se pudo transcribir:', err.message);
    return null;
  }
}

module.exports = { guardarMensajeEntrante, guardarMensajeSaliente, guardarMensajeSalienteExterno, transcribirAudio };
