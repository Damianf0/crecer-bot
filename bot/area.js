// Área que atiende esta instancia del bot.
//
// Cada contenedor fija BOT_AREA via docker-compose `environment:` — NO en bot/.env,
// porque las 3 instancias (atención / administración / ovodonación) montan el mismo
// directorio ./bot y comparten ese archivo. PORT y BOT_PUBLIC_URL van por el mismo lado.
const AREAS_VALIDAS = ['atencion', 'administracion', 'ovodonacion'];

const raw = (process.env.BOT_AREA || 'atencion').trim().toLowerCase();
const BOT_AREA = AREAS_VALIDAS.includes(raw) ? raw : 'atencion';

if (raw !== BOT_AREA) {
  console.warn(`[area] BOT_AREA="${raw}" no reconocida — usando 'atencion'`);
}

module.exports = { BOT_AREA, AREAS_VALIDAS };
