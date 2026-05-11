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

if (!fs.existsSync(MEDIA_DIR)) {
  fs.mkdirSync(MEDIA_DIR, { recursive: true });
}

const api = axios.create({
  baseURL: LARAVEL_URL,
  headers: { Authorization: `Bearer ${LARAVEL_TOKEN}` },
  timeout: 8000,
});

/**
 * Guarda un mensaje entrante en el inbox de Laravel.
 * Se llama al instante cuando WhatsApp recibe el mensaje.
 */
async function guardarMensajeEntrante(msg) {
  try {
    const contacto = msg.from;

    // Ignorar estados de WhatsApp (no son conversaciones reales)
    if (contacto === 'status@broadcast' || contacto?.endsWith('@broadcast')) return;
    let tipo        = 'texto';
    let contenido   = null;
    let archivo_url = null;

    if (msg.type === 'chat') {
      tipo      = 'texto';
      contenido = msg.body;
    } else if (msg.type === 'ptt' || msg.type === 'audio') {
      tipo = 'audio';
      try {
        const media = await msg.downloadMedia();
        if (media) {
          const ext      = mimeExt(media.mimetype, 'ogg');
          const filename = `${Date.now()}_${contacto.replace('@c.us', '')}.${ext}`;
          const filePath = path.join(MEDIA_DIR, filename);
          fs.writeFileSync(filePath, Buffer.from(media.data, 'base64'));
          archivo_url = `${BOT_PUBLIC_URL}/media/${filename}`;
          contenido   = await transcribirAudio(filePath).catch(() => null);
        }
      } catch (e) {
        console.warn('[mensajesApi] Audio no descargado:', e.message);
      }
    } else if (msg.type === 'image' || msg.type === 'sticker') {
      tipo = 'imagen';
      try {
        const media = await msg.downloadMedia();
        if (media) {
          const ext      = mimeExt(media.mimetype, 'jpg');
          const filename = `${Date.now()}_${contacto.replace('@c.us', '')}.${ext}`;
          fs.writeFileSync(path.join(MEDIA_DIR, filename), Buffer.from(media.data, 'base64'));
          archivo_url = `${BOT_PUBLIC_URL}/media/${filename}`;
          contenido   = msg.body || null; // caption si tiene
        }
      } catch (e) {
        console.warn('[mensajesApi] Imagen no descargada:', e.message);
      }
    } else if (msg.type === 'document' || msg.type === 'video') {
      tipo = msg.type === 'video' ? 'video' : 'documento';
      try {
        const media = await msg.downloadMedia();
        if (media) {
          const ext      = mimeExt(media.mimetype, 'bin');
          const filename = `${Date.now()}_${contacto.replace('@c.us', '')}.${ext}`;
          fs.writeFileSync(path.join(MEDIA_DIR, filename), Buffer.from(media.data, 'base64'));
          archivo_url = `${BOT_PUBLIC_URL}/media/${filename}`;
          contenido   = msg.body || msg._data?.filename || filename;
        }
      } catch (e) {
        console.warn('[mensajesApi] Documento no descargado:', e.message);
      }
    } else {
      return; // tipo no soportado
    }

    await api.post('/bot/mensajes', {
      contacto,
      area:      BOT_AREA,
      tipo,
      contenido,
      archivo_url,
      wa_id:     msg.id?._serialized || null,
      timestamp: new Date().toISOString(),
    });

  } catch (err) {
    console.error('[mensajesApi] Error guardando entrante:', err.message);
  }
}

/**
 * Guarda la respuesta automática del bot como mensaje saliente.
 */
async function guardarMensajeSaliente(contacto, texto, waId = null) {
  try {
    await api.post('/bot/mensajes/saliente', {
      contacto,
      area:       BOT_AREA,
      contenido:  texto,
      wa_id:      waId,
      timestamp: new Date().toISOString(),
    });
  } catch (err) {
    console.error('[mensajesApi] Error guardando saliente:', err.message);
  }
}

/**
 * Guarda un mensaje saliente recibido vía `message_create` con fromMe=true,
 * típicamente cuando alguien respondió desde otro dispositivo (celular).
 * El backend deduplica por wa_id (si Laravel ya lo guardó al enviar via /enviar,
 * skip).
 */
async function guardarMensajeSalienteExterno(msg) {
  try {
    const contacto = msg.to;
    const waId     = msg.id?._serialized || null;
    let tipo       = 'texto';
    let contenido  = null;
    let archivo_url = null;

    if (msg.type === 'chat') {
      tipo = 'texto';
      contenido = msg.body;
    } else if (msg.type === 'ptt' || msg.type === 'audio') {
      tipo = 'audio';
    } else if (msg.type === 'image' || msg.type === 'sticker') {
      tipo = 'imagen';
      contenido = msg.body || null;
    } else if (msg.type === 'document' || msg.type === 'video') {
      tipo = msg.type === 'video' ? 'video' : 'documento';
      contenido = msg.body || msg._data?.filename || null;
    } else {
      return;  // tipo no soportado
    }

    // Para audio/imagen/documento del celular, downloadMedia puede fallar si
    // ya se purgó del cache. No bloqueamos el guardado por eso — Laravel guarda
    // texto/contenido y, si vino con archivo, queda registrado el tipo.
    if (tipo !== 'texto') {
      try {
        const media = await msg.downloadMedia();
        if (media) {
          const ext      = mimeExt(media.mimetype, 'bin');
          const filename = `${Date.now()}_${contacto.replace('@c.us', '')}.${ext}`;
          fs.writeFileSync(path.join(MEDIA_DIR, filename), Buffer.from(media.data, 'base64'));
          archivo_url = `${BOT_PUBLIC_URL}/media/${filename}`;
        }
      } catch (e) {
        console.warn('[mensajesApi] downloadMedia falló para saliente externo:', e.message);
      }
    }

    await api.post('/bot/mensajes/saliente', {
      contacto,
      area:    BOT_AREA,
      tipo,
      contenido,
      archivo_url,
      wa_id: waId,
      timestamp: new Date().toISOString(),
    });
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
