const HORARIO_INICIO  = parseInt(process.env.HORARIO_INICIO  || '8');
const HORARIO_FIN     = parseInt(process.env.HORARIO_FIN     || '18');
const HORARIO_SAB_FIN = parseInt(process.env.HORARIO_SAB_FIN || '13');

/**
 * Devuelve true si el momento actual está dentro del horario de atención.
 * Lunes–Viernes: 8–18hs | Sábado: 8–13hs | Domingo: cerrado
 */
function estaEnHorario() {
  const ahora = new Date();
  const dia  = ahora.getDay(); // 0=Dom, 1=Lun, ..., 6=Sab
  const hora = ahora.getHours();

  if (dia === 0) return false; // domingo

  if (dia === 6) return hora >= HORARIO_INICIO && hora < HORARIO_SAB_FIN;

  return hora >= HORARIO_INICIO && hora < HORARIO_FIN;
}

module.exports = { estaEnHorario };
