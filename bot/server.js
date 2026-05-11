const express = require('express');
const fs = require('fs');
const path = require('path');
const {
  estado,
  addLogListener, removeLogListener,
  addClasificacionListener, removeClasificacionListener,
  getUptime,
} = require('./estado-bot');
const { procesarConversacion, generarResumen } = require('./ollama');
const { BOT_AREA } = require('./area');
const axios = require('axios');

const PORT = parseInt(process.env.PORT || '3001', 10);
const ENV_PATH = path.join(__dirname, '.env');
const TEXTOS_PATH = path.join(__dirname, 'textos.json');

const MEDIA_DIR = path.join(__dirname, 'media');
if (!fs.existsSync(MEDIA_DIR)) fs.mkdirSync(MEDIA_DIR, { recursive: true });

// Auth e ingress: leemos del .env propio del bot al arrancar
const BOT_INGRESS_TOKEN = (() => {
  try {
    const env = fs.readFileSync(ENV_PATH, 'utf-8');
    const m = env.split('\n').find(l => l.trim().startsWith('BOT_INGRESS_TOKEN='));
    return m ? m.split('=').slice(1).join('=').trim() : '';
  } catch { return ''; }
})();

const ALLOWED_ORIGINS = (() => {
  try {
    const env = fs.readFileSync(ENV_PATH, 'utf-8');
    const m = env.split('\n').find(l => l.trim().startsWith('ALLOWED_ORIGINS='));
    return m ? m.split('=').slice(1).join('=').trim().split(',').map(s => s.trim()).filter(Boolean) : [];
  } catch { return []; }
})();

if (!BOT_INGRESS_TOKEN) {
  console.warn('[server] ⚠ BOT_INGRESS_TOKEN no configurado — endpoints quedan abiertos. Configurar en .env');
}

const app = express();

// CORS con whitelist — solo permite orígenes declarados en ALLOWED_ORIGINS
app.use((req, res, next) => {
  const origin = req.headers.origin;
  if (origin && ALLOWED_ORIGINS.includes(origin)) {
    res.setHeader('Access-Control-Allow-Origin', origin);
    res.setHeader('Vary', 'Origin');
  }
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PATCH, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  next();
});

app.use(express.json({ limit: '20mb' }));

// Servir audios y media estáticos (sin auth — son URLs públicas que se mandan en mensajes)
app.use('/media', express.static(MEDIA_DIR));

// Middleware de auth: exige Bearer token o ?token= en query (para SSE).
// Excluye /status (healthcheck público) y /media (servido arriba).
const PUBLIC_PATHS = new Set(['/status']);

function requireToken(req, res, next) {
  if (!BOT_INGRESS_TOKEN) return next();         // si no hay token configurado, no exigimos (evita lockout)
  if (PUBLIC_PATHS.has(req.path)) return next();
  if (req.path.startsWith('/media/')) return next();

  const auth = req.headers.authorization || '';
  const bearer = auth.startsWith('Bearer ') ? auth.slice(7) : '';
  const queryTok = req.query.token || '';
  const token = bearer || queryTok;

  if (token && token === BOT_INGRESS_TOKEN) return next();
  return res.status(401).json({ ok: false, error: 'Unauthorized' });
}

app.use(requireToken);

// ── Status ────────────────────────────────────────────────
app.get('/status', (req, res) => {
  res.json({
    status: estado.status,
    qrDataUrl: estado.qrDataUrl,
    phone: estado.phone,
    uptime: getUptime(),
    area: BOT_AREA,
  });
});

// ── Logs (SSE) ────────────────────────────────────────────
app.get('/logs', (req, res) => {
  res.setHeader('Content-Type', 'text/event-stream');
  res.setHeader('Cache-Control', 'no-cache');
  res.setHeader('Connection', 'keep-alive');
  res.flushHeaders();

  // Enviar buffer histórico
  for (const line of estado.logBuffer) {
    res.write(`data: ${line}\n\n`);
  }

  const onLine = (line) => res.write(`data: ${line}\n\n`);
  addLogListener(onLine);

  req.on('close', () => removeLogListener(onLine));
});

// ── Config (.env) ─────────────────────────────────────────
app.get('/config', (req, res) => {
  try {
    const contenido = fs.readFileSync(ENV_PATH, 'utf-8');
    res.json({ ok: true, data: parsearEnv(contenido) });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

app.post('/config', (req, res) => {
  try {
    const contenido = fs.readFileSync(ENV_PATH, 'utf-8');
    const actualizado = actualizarEnv(contenido, req.body);
    fs.writeFileSync(ENV_PATH, actualizado, 'utf-8');
    res.json({ ok: true, mensaje: 'Guardado. Reiniciá el bot para aplicar los cambios.' });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// ── Textos (textos.json) ──────────────────────────────────
app.get('/textos', (req, res) => {
  try {
    const data = JSON.parse(fs.readFileSync(TEXTOS_PATH, 'utf-8'));
    res.json({ ok: true, data });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

app.post('/textos', (req, res) => {
  try {
    fs.writeFileSync(TEXTOS_PATH, JSON.stringify(req.body, null, 2), 'utf-8');
    res.json({ ok: true, mensaje: 'Textos guardados. Los cambios se aplican de inmediato.' });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// ── Helpers .env ──────────────────────────────────────────
function parsearEnv(contenido) {
  const result = {};
  for (const line of contenido.split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const idx = trimmed.indexOf('=');
    if (idx < 0) continue;
    result[trimmed.slice(0, idx).trim()] = trimmed.slice(idx + 1).trim();
  }
  return result;
}

function actualizarEnv(contenido, cambios) {
  return contenido
    .split('\n')
    .map((line) => {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith('#')) return line;
      const idx = trimmed.indexOf('=');
      if (idx < 0) return line;
      const key = trimmed.slice(0, idx).trim();
      return key in cambios ? `${key}=${cambios[key]}` : line;
    })
    .join('\n');
}

// ── Usuarios (proxy a Laravel) ────────────────────────────
const LARAVEL_URL   = process.env.LARAVEL_URL   || 'http://web/api';
const LARAVEL_TOKEN = process.env.LARAVEL_TOKEN || '';

const laravelHeaders = {
  Authorization: `Bearer ${LARAVEL_TOKEN}`,
  'Content-Type': 'application/json',
};

app.get('/usuarios', async (req, res) => {
  try {
    const r = await axios.get(`${LARAVEL_URL}/usuarios`, { headers: laravelHeaders });
    res.json(r.data);
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

app.post('/usuarios', async (req, res) => {
  try {
    const r = await axios.post(`${LARAVEL_URL}/usuarios`, req.body, { headers: laravelHeaders });
    res.status(r.status).json(r.data);
  } catch (err) {
    const status = err.response?.status || 500;
    res.status(status).json(err.response?.data || { ok: false, error: err.message });
  }
});

app.patch('/usuarios/:id', async (req, res) => {
  try {
    const r = await axios.patch(`${LARAVEL_URL}/usuarios/${req.params.id}`, req.body, { headers: laravelHeaders });
    res.json(r.data);
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// ── Pruebas ───────────────────────────────────────────────
// GET /pruebas — historial en buffer
app.get('/pruebas', (req, res) => {
  res.json({ ok: true, modoPrueba: estado.modoPrueba, data: estado.clasificaciones });
});

// GET /pruebas/stream — SSE con clasificaciones en vivo
app.get('/pruebas/stream', (req, res) => {
  res.setHeader('Content-Type', 'text/event-stream');
  res.setHeader('Cache-Control', 'no-cache');
  res.setHeader('Connection', 'keep-alive');
  res.flushHeaders();

  // Enviar modo actual al conectar
  res.write(`event: modo\ndata: ${JSON.stringify({ modoPrueba: estado.modoPrueba })}\n\n`);

  // Enviar historial
  for (const entry of estado.clasificaciones) {
    res.write(`event: clasificacion\ndata: ${JSON.stringify(entry)}\n\n`);
  }

  const onEntry = (entry) =>
    res.write(`event: clasificacion\ndata: ${JSON.stringify(entry)}\n\n`);
  addClasificacionListener(onEntry);
  req.on('close', () => removeClasificacionListener(onEntry));
});

// POST /pruebas/modo — toggle o set explícito { modoPrueba: true|false }
app.post('/pruebas/modo', (req, res) => {
  const { modoPrueba } = req.body;
  estado.modoPrueba = typeof modoPrueba === 'boolean' ? modoPrueba : !estado.modoPrueba;
  console.log(`[server] Modo prueba: ${estado.modoPrueba}`);
  res.json({ ok: true, modoPrueba: estado.modoPrueba });
});

// ── Clasificar texto (debug / panel Electron) ─────────────
app.post('/clasificar', async (req, res) => {
  const { texto } = req.body;
  if (!texto) return res.status(400).json({ ok: false, error: 'texto requerido' });
  const resultado = await procesarConversacion(texto);
  res.json({ ok: true, ...resultado });
});

// Genera un resumen de un intercambio paciente↔bot. Llamado por Laravel desde el job
// asincrónico GenerarResumenLLM (vía queue worker). NO bloquea el path crítico del bot
// porque solo corre cuando Laravel lo pide explícitamente.
// Body: { texto: "Paciente: ...\nBot: ..." }
// Devuelve: { ok, resumen } o { ok: false } si Ollama falló.
app.post('/resumir', async (req, res) => {
  const { texto } = req.body;
  if (!texto) return res.status(400).json({ ok: false, error: 'texto requerido' });
  const resumen = await generarResumen(texto);
  if (!resumen) return res.status(502).json({ ok: false, error: 'Ollama no devolvió resumen' });
  res.json({ ok: true, resumen });
});

// ── Enviar mensaje WhatsApp (llamado desde Laravel/web) ───
let _waClient = null;

function registrarCliente(client) {
  _waClient = client;
}

// Verificar si un número está registrado en WhatsApp.
// Body: { numero: "5491155667788" } (sin @c.us, solo dígitos)
// Devuelve: { ok, registered, normalizedId } — normalizedId es "549...@c.us"
app.post('/check-numero', async (req, res) => {
  const { numero } = req.body;
  if (!numero) {
    return res.status(400).json({ ok: false, error: 'numero requerido' });
  }
  if (!_waClient) {
    return res.status(503).json({ ok: false, error: 'Cliente WhatsApp no disponible' });
  }
  try {
    const digits = String(numero).replace(/\D/g, '');
    const numberId = await _waClient.getNumberId(digits);
    if (!numberId) {
      return res.json({ ok: true, registered: false, normalizedId: null });
    }
    res.json({
      ok: true,
      registered: true,
      normalizedId: numberId._serialized || `${numberId.user}@${numberId.server}`,
    });
  } catch (err) {
    console.error('[server] Error en check-numero:', err.message);
    res.status(500).json({ ok: false, error: err.message });
  }
});

// Resolver un JID a su número telefónico real.
// Útil cuando ya tenés un @lid en BD pero no sabés a qué número corresponde.
// Body: { jid: "13254403313885@lid" } o "549...@c.us"
// Devuelve: { ok, numero, name } — numero en formato "549..." sin @
app.post('/resolve-jid', async (req, res) => {
  const { jid } = req.body;
  if (!jid) {
    return res.status(400).json({ ok: false, error: 'jid requerido' });
  }
  if (!_waClient) {
    return res.status(503).json({ ok: false, error: 'Cliente WhatsApp no disponible' });
  }
  try {
    const contact = await _waClient.getContactById(jid);
    // .number puede venir vacío si WA no expone el teléfono real.
    const numero = (contact && contact.number) ? String(contact.number).replace(/\D/g, '') : null;
    const name   = contact?.pushname || contact?.name || null;
    res.json({ ok: true, numero, name });
  } catch (err) {
    console.error('[server] Error en resolve-jid:', err.message);
    res.status(500).json({ ok: false, error: err.message });
  }
});

// Devuelve la URL temporal de la foto de perfil de un contacto.
// Body: { jid }
// Devuelve: { ok, url } — url puede ser null si el contacto oculta la foto o no tiene.
// La URL expira en horas — el caller debe descargarla y cachear local si la quiere persistir.
app.post('/profile-pic', async (req, res) => {
  const { jid } = req.body;
  if (!jid) return res.status(400).json({ ok: false, error: 'jid requerido' });
  if (!_waClient) return res.status(503).json({ ok: false, error: 'Cliente WhatsApp no disponible' });

  // NOTA: al 2026-05-03, WhatsApp Web tiene un bug que rompe todas las APIs de profile pic
  // con "Cannot read properties of undefined (reading 'isNewsletter')". Probamos varios paths
  // y devolvemos url:null si todos fallan — el frontend cae al fallback con la inicial.
  // Cuando WA Web lo arregle, las fotos van a aparecer sin tocar este código.
  let url = null;

  try { url = await _waClient.getProfilePicUrl(jid); } catch {}

  if (!url) {
    try {
      const contact = await _waClient.getContactById(jid);
      if (contact && typeof contact.getProfilePicUrl === 'function') {
        url = await contact.getProfilePicUrl();
      }
    } catch {}
  }

  if (!url) {
    try {
      url = await _waClient.pupPage.evaluate(async (jid) => {
        try {
          const wid = window.Store?.WidFactory?.createWid
            ? window.Store.WidFactory.createWid(jid)
            : (window.WWebJS?.getWid ? window.WWebJS.getWid(jid) : null);
          if (!wid) return null;
          const pp = window.Store?.ProfilePic;
          if (!pp) return null;
          for (const m of ['profilePicFind', 'requestProfilePicFromServer', 'profilePicResync']) {
            if (typeof pp[m] === 'function') {
              try {
                const r = await pp[m](wid);
                if (r && (r.eurl || r.imgFull || r.url)) return r.eurl || r.imgFull || r.url;
              } catch {}
            }
          }
          return null;
        } catch { return null; }
      }, jid);
    } catch {}
  }

  res.json({ ok: true, url: url || null });
});

app.post('/enviar', async (req, res) => {
  const { contacto, texto } = req.body;
  if (!contacto || !texto) {
    return res.status(400).json({ ok: false, error: 'contacto y texto requeridos' });
  }
  if (!_waClient) {
    return res.status(503).json({ ok: false, error: 'Cliente WhatsApp no disponible' });
  }
  try {
    const { markSent } = require('./whatsapp');
    const sent = await _waClient.sendMessage(contacto, texto);
    markSent(sent?.id?._serialized);   // evita que el handler `message_create` lo guarde como saliente externo
    console.log(`[server] Mensaje enviado a ${contacto}`);
    res.json({ ok: true, wa_id: sent?.id?._serialized || null });
  } catch (err) {
    console.error('[server] Error enviando mensaje:', err.message);
    res.status(500).json({ ok: false, error: err.message });
  }
});

app.post('/enviar-archivo', async (req, res) => {
  const { contacto, base64, mimetype, filename, caption } = req.body;
  if (!contacto || !base64 || !mimetype) {
    return res.status(400).json({ ok: false, error: 'contacto, base64 y mimetype requeridos' });
  }
  if (!_waClient) {
    return res.status(503).json({ ok: false, error: 'Cliente WhatsApp no disponible' });
  }
  try {
    const { MessageMedia } = require('whatsapp-web.js');
    const { markSent }     = require('./whatsapp');
    const media = new MessageMedia(mimetype, base64, filename || 'archivo');
    const opts  = caption ? { caption } : {};
    const sent  = await _waClient.sendMessage(contacto, media, opts);
    markSent(sent?.id?._serialized);
    console.log(`[server] Archivo enviado a ${contacto}: ${filename}`);
    res.json({ ok: true, wa_id: sent?.id?._serialized || null });
  } catch (err) {
    console.error('[server] Error enviando archivo:', err.message);
    res.status(500).json({ ok: false, error: err.message });
  }
});

// ── Arranque ──────────────────────────────────────────────
function iniciarServidor() {
  app.listen(PORT, '0.0.0.0', () => {
    console.log(`[server] HTTP escuchando en puerto ${PORT} (área: ${BOT_AREA})`);
  });
}

module.exports = { iniciarServidor, registrarCliente };
