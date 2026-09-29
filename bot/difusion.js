// Mensajes enviados como parte de una difusión (Laravel manda `difusion: true`
// en /enviar). Los acuses (entregado/leído) de estos se reportan a Laravel
// para las estadísticas de la campaña. En los números de las áreas solo se
// reportan estos: el resto de la conversación diaria no interesa y serían
// varias llamadas por cada mensaje. El bot del área 'difusion' reporta todo.
//
// En memoria y acotado: tras un reinicio se pierden los acuses tardíos de lo
// enviado antes (la campaña queda con "enviado" en esos, no con "leído").

const MAX = 20000;
const ids = new Set();

function marcarDifusion(waId) {
  if (!waId) return;
  ids.add(waId);
  if (ids.size > MAX) ids.delete(ids.values().next().value);   // el más viejo
}

function esDifusion(waId) {
  return ids.has(waId);
}

module.exports = { marcarDifusion, esDifusion };
