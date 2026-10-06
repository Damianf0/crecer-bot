// Área que atiende esta instancia del bot.
//
// Cada contenedor fija BOT_AREA via docker-compose `environment:` — NO en bot/.env,
// porque las 3 instancias (atención / administración / ovodonación) montan el mismo
// directorio ./bot y comparten ese archivo. PORT y BOT_PUBLIC_URL van por el mismo lado.
// 'test' habilita el container shadow `bot-test` para validar Baileys con un
// número personal sin tocar producción. Si LARAVEL_URL está vacío, los wrappers
// de mensajesApi no guardan nada en BD (no-op), así el shadow no ensucia la
// prod. Ver project_migracion_baileys.md.
// 'difusion': 4º número, envíos masivos; las respuestas van a su propia cola.
// 'criopreservacion': 5º número (consultorio externo); misma conducta que las
// tres áreas, pero en el panel solo lo ve quien tiene el permiso (app/config/lineas.php).
const AREAS_VALIDAS = ['atencion', 'administracion', 'ovodonacion', 'difusion', 'criopreservacion', 'test'];

const raw = (process.env.BOT_AREA || 'atencion').trim().toLowerCase();
const BOT_AREA = AREAS_VALIDAS.includes(raw) ? raw : 'atencion';

if (raw !== BOT_AREA) {
  console.warn(`[area] BOT_AREA="${raw}" no reconocida — usando 'atencion'`);
}

module.exports = { BOT_AREA, AREAS_VALIDAS };
