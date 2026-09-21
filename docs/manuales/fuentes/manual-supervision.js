// Manual de supervisión y administración de la plataforma (acceso de gestión).
const { P, H1, H2, H3, bullets, pasos, recuadro, tabla, espacio, portada, construir } = require('./lib-manual');

const INDICE = [
  'Para quién es este manual', 'Los bots de WhatsApp (estado y QR)', 'Usuarios y permisos',
  'Checklist de recepción por obra social', 'Archivar conversaciones', 'Respuestas rápidas',
  'El bot: textos y modo prueba', 'Médicos', 'Procedimientos', 'Reportes', 'Legajo de documentos',
  'Herramientas técnicas', 'Respaldo y soporte', 'Glosario',
];

const cap1 = [
  H1('1. Para quién es este manual'),
  P('Este manual explica las funciones de **gestión** de la plataforma: las que configuran cómo trabaja el resto del equipo. Las tareas del día a día (responder WhatsApp, recibir pacientes) están en los manuales de cada puesto.'),
  H2('1.1 Quién tiene acceso'),
  P('Las pantallas de este manual requieren el permiso **Administración (panel)**. Lo tienen por defecto estos roles:'),
  tabla(['Rol', 'Qué hace', 'Acceso de gestión'], [
    ['Supervisora', 'Coordina el equipo, define reglas y contenidos', 'Sí'],
    ['Administrativo', 'Gestión administrativa de la clínica', 'Sí'],
    ['Técnico WORKBENCH IT', 'Soporte técnico de la plataforma', 'Sí'],
    ['Secretaria', 'Atención de WhatsApp y recepción', 'No'],
    ['Médico', 'Su consultorio y su lista de pacientes', 'No'],
  ], [2400, 4426, 2200]),
  espacio(),
  P('Con ese permiso aparecen en el menú lateral la sección **Supervisión** (Reportes y Admin) y, dentro de Recepción, **Checklist por obra social**.'),
  H2('1.2 Mapa de las pantallas de gestión'),
  tabla(['Pantalla', 'Dónde está', 'Para qué sirve'], [
    ['Estado de los bots', 'Admin (inicio)', 'Ver si cada WhatsApp está conectado y escanear el QR'],
    ['Usuarios', 'Admin → Usuarios', 'Altas, bajas, roles y contraseñas del equipo'],
    ['Checklist por obra social', 'Recepción → Checklist por obra social', 'Qué pedirle a cada paciente según obra social y práctica'],
    ['Archivar conversaciones', 'Admin → Archivar', 'Limpiar las colas de WhatsApp en lote, con deshacer'],
    ['Respuestas rápidas', 'Admin → Respuestas rápidas', 'Mensajes predefinidos que usa el equipo al responder'],
    ['Textos del bot', 'Admin → Textos', 'Los mensajes automáticos del bot'],
    ['Pruebas', 'Admin → Pruebas', 'Encender o apagar el modo prueba del bot'],
    ['Médicos', 'Admin → Médicos', 'Profesionales, su nombre en Omnia y su consultorio'],
    ['Procedimientos', 'Menú → Procedimientos', 'Base de conocimiento del equipo (alta y edición)'],
    ['Reportes', 'Supervisión → Reportes', 'Actividad de WhatsApp y de recepción'],
    ['Legajo', 'Admin → Legajo', 'Configuración del archivo de documentos'],
    ['Logs y Túnel', 'Admin', 'Herramientas técnicas (ver capítulo 12)'],
  ], [2500, 2900, 3626]),
];

const cap2 = [
  H1('2. Los bots de WhatsApp'),
  P('La clínica tiene **un número de WhatsApp por área**, y cada uno tiene su propio bot conectado a la plataforma. Todo lo que entra por esos números aparece en las colas de conversaciones del panel.'),
  tabla(['Área', 'Número', 'Cola en el panel'], [
    ['Atención', '+54 9 223 599-7247', 'WhatsApp → Atención'],
    ['Administración', '+54 9 223 346-9767', 'WhatsApp → Administración'],
    ['Ovodonación', '+54 9 223 456-5067', 'WhatsApp → Ovodonación'],
  ], [2400, 3000, 3626]),
  espacio(),
  H2('2.1 Cómo ver el estado'),
  P('En **Admin** (la pantalla de inicio de gestión) hay una tarjeta por bot con un punto de color:'),
  ...bullets([
    '**Verde, "listo":** conectado y recibiendo. Muestra el número con el que está vinculado.',
    '**Amarillo, "esperando QR":** perdió la vinculación y hay que volver a escanear (ver 2.2).',
    '**Rojo o "desconectado" / "iniciando" por más de unos minutos:** avisar a soporte (capítulo 13).',
  ]),
  P('Arriba a la derecha de todas las pantallas también figura un indicador general (**Bots ok**).'),
  H2('2.2 Escanear el QR'),
  P('Cuando un bot queda **esperando QR**, el código aparece en su tarjeta. Se escanea desde el celular de ese número:'),
  ...pasos([
    'Tené a mano el **celular del número de esa área** (ver la tabla de arriba).',
    'En ese celular abrí WhatsApp → **Ajustes** → **Dispositivos vinculados** → **Vincular un dispositivo**.',
    'Apuntá la cámara al QR de la tarjeta de ese bot en Admin.',
    'Esperá a que la tarjeta pase a verde y confirmá que el número que muestra es el correcto.',
  ]),
  ...recuadro('Muy importante', [
    'Cada QR se escanea **solo con el celular de su área**. Si se escanea el QR de Atención con el celular de Administración, el bot de Atención queda recibiendo los mensajes del número equivocado.',
    'Si al terminar la tarjeta muestra un número distinto al esperado, avisá a soporte antes de seguir.',
  ], 'ojo'),
  P('La vinculación queda guardada: no hace falta volver a escanear salvo que WhatsApp la cierre. Pasa, por ejemplo, si alguien la quita desde "Dispositivos vinculados" en el celular o si el celular estuvo mucho tiempo sin conexión.'),
  ...recuadro('Nunca desvincules desde el celular', [
    'En **Dispositivos vinculados** del celular de cada área figura la sesión de la plataforma. No la cierres: si se cierra, el bot deja de recibir mensajes hasta que se vuelva a escanear.',
  ]),
];

const cap3 = [
  H1('3. Usuarios y permisos'),
  P('En **Admin → Usuarios** se gestiona quién entra a la plataforma y qué puede hacer.'),
  H2('3.1 Dar de alta a alguien'),
  ...pasos([
    'Tocá **+ Nuevo usuario**.',
    'Completá **Nombre completo** y **Email** (es el usuario para entrar).',
    'Elegí el **Rol**. Cada rol trae sus permisos; se ven en **Permisos efectivos**.',
    'Poné una **contraseña inicial** y pasásela a la persona por un canal privado.',
    'Dejá marcado **Activo** y tocá **Guardar**.',
  ]),
  H2('3.2 Reglas de las contraseñas'),
  ...bullets([
    '**Al menos 10 caracteres.**',
    'No puede ser solo números ni una contraseña común (tipo "password123" o "admin123"); el sistema las rechaza.',
    'Después de **5 intentos fallidos seguidos**, la cuenta se **bloquea 15 minutos**. Pasado ese tiempo se puede volver a intentar.',
  ]),
  H2('3.3 Cuando alguien deja la clínica'),
  P('**Desmarcá "Activo"** en vez de borrar al usuario. Así deja de poder entrar, pero su historial (conversaciones atendidas, notas, presentes dados) sigue identificado con su nombre.'),
  H2('3.4 Permisos'),
  tabla(['Permiso', 'Qué habilita'], [
    ['Cola de recepción', 'Pantalla de Recepción: sala de espera y mensajes del bot'],
    ['Atención y mis tareas', 'Colas de WhatsApp, Mis conversaciones y Tareas'],
    ['Contactos', 'Directorio de pacientes'],
    ['Agenda', 'Agenda del equipo'],
    ['Ver historial', 'Historial de conversaciones cerradas'],
    ['Administración (panel)', 'Todo lo de este manual'],
  ], [3000, 6026]),
];

const cap4 = [
  H1('4. Checklist de recepción por obra social'),
  P('Cuando un paciente se anuncia en el tablet de la sala, su ficha en Recepción muestra un **checklist** con lo que hay que pedirle o verificar. Ese checklist se arma solo a partir de las reglas que se cargan en esta pantalla, según **la obra social del turno, el plan y la práctica**.'),
  ...recuadro('Mientras no haya reglas', [
    'Hasta que se cargue la primera regla, recepción sigue usando el checklist fijo de siempre: obra social verificada, copago gestionado, orden médica validada y datos del paciente completos.',
  ]),
  H2('4.1 Los dos conceptos'),
  ...bullets([
    '**Requisito:** algo que se le puede pedir a un paciente. Por ejemplo: orden médica, autorización previa, credencial, consentimiento firmado, bono de copago. Tiene un nombre y, opcionalmente, una instrucción de qué revisar.',
    '**Regla:** dice **cuándo** se pide un requisito. Combina requisito + obra social + plan + práctica, y un modo.',
  ]),
  H3('Los modos de una regla'),
  tabla(['Modo', 'Qué ve la recepcionista'], [
    ['Obligatorio', 'El ítem aparece marcado con * y, si no se tilda, se avisa al dar el presente'],
    ['Opcional', 'El ítem aparece, pero no genera aviso'],
    ['No pedir', 'El ítem no aparece. Sirve para exceptuar un caso de una regla más general'],
  ], [2400, 6626]),
  espacio(),
  H2('4.2 Cuál regla gana'),
  P('En una regla, **dejar un campo vacío significa "todos"**. Una regla sin obra social vale para todas las obras sociales; una sin práctica, para todas las prácticas.'),
  P('Si dos reglas hablan del **mismo requisito**, gana la más específica, en este orden:'),
  ...pasos([
    '**Obra social + práctica** (la más específica).',
    '**Obra social** sola.',
    '**Práctica** sola.',
    '**General** (sin obra social ni práctica).',
  ]),
  P('Dentro de una misma obra social, una regla que además fija el **plan** le gana a la que no lo fija. Si dos reglas quedan empatadas, gana la que se editó por última vez.'),
  H3('Ejemplos'),
  P('En los ejemplos las obras sociales van abreviadas. En la pantalla figuran con su nombre completo de Omnia: por ejemplo, IOMA es "Instituto de Obra Médica Asistencial" y OSDE es "Osde Binario".', { spacing: { after: 120 } }),
  tabla(['Si están cargadas…', '…para este paciente', 'Resultado'], [
    ['"Consulta: No pedir autorización" y "IOMA: Autorización obligatoria"', 'IOMA, Consulta Médica', 'Pide autorización (la obra social le gana a la práctica)'],
    ['Las mismas dos reglas', 'OSDE, Consulta Médica', 'No pide autorización (a OSDE solo le aplica la de la práctica)'],
    ['"OSDE: Orden obligatoria" y "OSDE + Consulta: No pedir orden"', 'OSDE, Consulta Médica', 'No pide orden'],
    ['Las mismas dos reglas', 'OSDE, Ecografía Transvaginal', 'Pide orden'],
    ['"OSDE: Autorización obligatoria" y "OSDE plan 410: Autorización opcional"', 'OSDE plan 410', 'Autorización opcional'],
  ], [3500, 2426, 3100]),
  espacio(),
  H2('4.3 Cargar requisitos y reglas'),
  H3('Primero, los requisitos'),
  ...pasos([
    'En el panel derecho **Requisitos**, tocá **+ Nuevo**.',
    'Poné un **Nombre** claro ("Autorización previa") y, si ayuda, una **Instrucción** de qué revisar ("firmada y sellada por la obra social").',
    'El **Orden en la lista** define en qué posición aparece en la ficha (dentro de obligatorios y opcionales).',
    'Tocá **Guardar**.',
  ]),
  H3('Después, las reglas'),
  ...pasos([
    'Tocá **+ Nueva regla**.',
    'Elegí el **Requisito**.',
    'Elegí la **Obra social** y la **Práctica** de la lista, o dejalas vacías para "todas". La lista trae los nombres **exactos** que usa Omnia, junto con cuántos turnos tuvo cada una en los últimos meses.',
    'Si hace falta, completá el **Plan** (solo junto con una obra social).',
    'Elegí el **Modo** y, si sirve, una **Nota para recepción** ("original + 1 copia", "vigencia 30 días").',
    'Tocá **Guardar**.',
  ]),
  ...recuadro('Usá siempre los nombres de la lista', [
    'Las reglas se comparan letra por letra con lo que manda Omnia. Si escribís "OSDE" a mano y en Omnia figura "Osde Binario", la regla no se va a aplicar nunca. La pantalla avisa cuando el nombre no está en la lista.',
  ]),
  H2('4.4 Probar antes de confiar'),
  P('El recuadro **Probar un caso** muestra exactamente lo que va a ver la recepcionista. Elegí una obra social, un plan y una práctica, y revisá la lista. Dejá la práctica vacía para ver qué se le pide a un paciente **sin turno**.'),
  P('Conviene probar cada combinación importante después de cargar o cambiar reglas.'),
  H2('4.5 Lo que pasa en recepción'),
  ...bullets([
    'El checklist se arma **cuando el paciente se anuncia** en el tablet. Si cambiás una regla después, la recepcionista puede tocar **↻ actualizar** en la ficha para rearmarlo sin perder lo que ya tildó.',
    'La obra social que se usa es la **del turno**, no necesariamente la de la ficha del paciente: alguien con obra social puede tener un turno como Particular.',
    'El botón **Dar presente y liberar a sala** nunca queda bloqueado. Si faltan obligatorios, se le muestran en rojo y se le pide confirmación.',
    'Cada presente queda registrado con **quién lo dio, a qué hora y qué obligatorios faltaron**.',
  ]),
  ...recuadro('El presente todavía no llega a Omnia', [
    'Hoy el presente queda registrado en la plataforma, pero **no** marca el turno como "recepcionado" en Omnia: la integración de Omnia no permite cambiar el estado de un turno. Si hace falta que figure allá, se sigue marcando a mano en Omnia.',
  ], 'ojo'),
  H2('4.6 Mantenimiento'),
  ...bullets([
    'Para dejar de usar un requisito, **desactivalo**. Solo se puede borrar si no tiene reglas.',
    'Una regla desactivada queda en la lista (en gris) y no se aplica. Sirve para pausar algo sin perder cómo estaba.',
    'No se pueden cargar dos reglas para el mismo requisito y el mismo caso: la pantalla pide editar la que ya existe.',
    'Cada regla muestra quién la editó por última vez y cuándo.',
  ]),
];

const cap5 = [
  H1('5. Archivar conversaciones'),
  P('Las conversaciones de WhatsApp que nadie cierra se acumulan en las colas. **Admin → Archivar** permite archivarlas en lote, con vista previa y deshacer.'),
  H2('5.1 Qué hace "archivar"'),
  ...bullets([
    'Es lo mismo que apretar **Resolver** en cada conversación: sale de la cola y queda sin asignar.',
    '**No se borra nada.** La conversación y sus mensajes siguen en el historial.',
    'Si el paciente vuelve a escribir, **la conversación se reabre sola** y vuelve a la cola.',
  ]),
  H2('5.2 Paso a paso'),
  ...pasos([
    'Elegí el criterio: **Sin actividad hace más de N días**, o un **rango de fechas** (desde / hasta).',
    'Elegí el **Área** o dejala en todas.',
    'Dejá marcado **No tocar las asignadas** si no querés sacarle conversaciones a quien las está atendiendo.',
    'Tocá **Previsualizar**: muestra cuántas son, por área, cuántas tienen mensajes sin leer y una muestra de las más recientes.',
    'Si está bien, tocá **Archivar N conversaciones**.',
  ]),
  H2('5.3 Deshacer'),
  P('El **Historial de archivados** guarda los últimos lotes. **Deshacer** devuelve a la cola las conversaciones de ese lote. Las que el paciente ya reabrió escribiendo de nuevo se dejan como están.'),
];

const cap6 = [
  H1('6. Respuestas rápidas'),
  P('Son mensajes predefinidos que el equipo inserta con un clic al responder un WhatsApp (saludo inicial, indicaciones frecuentes, etc.). Se gestionan en **Admin → Respuestas rápidas**, **por área**: cada número tiene las suyas.'),
  ...pasos([
    'Elegí el área arriba.',
    'Tocá **+ Nueva respuesta**.',
    'Poné un **Título** corto (es lo que ve el equipo en el menú) y el **Texto del mensaje** completo.',
    'El **Orden** define la posición en el menú.',
    'Tocá **Guardar**.',
  ]),
  P('Los cambios quedan disponibles enseguida. Quien tenga una conversación abierta desde antes puede necesitar recargar la página (F5) para verlos.'),
];

const cap7 = [
  H1('7. El bot: textos y modo prueba'),
  H2('7.1 Modo prueba'),
  P('En **Admin → Pruebas** está el interruptor del **modo prueba**. Con el modo prueba **activo**, el bot lee y clasifica cada mensaje que entra, pero **no le contesta al paciente ni deriva por su cuenta**: todo lo atiende el equipo desde las colas.'),
  ...recuadro('Hoy el modo prueba está ACTIVO a propósito', [
    'Es la configuración acordada mientras se valida el funcionamiento. No lo apagues sin coordinarlo antes con WORKBENCH IT: al apagarlo, el bot empieza a responder solo a los pacientes con los textos del punto 7.2.',
  ], 'ojo'),
  H2('7.2 Textos de respuestas automáticas'),
  P('En **Admin → Textos** se editan los mensajes que manda el bot cuando responde solo (con el modo prueba apagado): bienvenida, primera consulta, turnos, resultados, medicación, órdenes, fuera de horario y los mensajes de derivación a secretaría.'),
  ...bullets([
    'Editá el texto y tocá **Guardar cambios**. **Descartar** vuelve a lo que estaba guardado.',
    'Mantené el tono y los datos (horarios, direcciones, links) al día: el paciente los recibe tal cual.',
  ]),
];

const cap8 = [
  H1('8. Médicos'),
  P('En **Admin → Médicos** se da de alta a cada profesional. Es lo que permite que cada médico vea en **Mi consultorio** su lista de pacientes del día.'),
  tabla(['Campo', 'Para qué sirve'], [
    ['Nombre completo', 'Cómo se lo muestra en la plataforma'],
    ['Especialidad', 'Informativo'],
    ['Nombre en Omnia', 'Tiene que coincidir **exactamente** con cómo figura el profesional en Omnia; es lo que vincula sus turnos'],
    ['Usuario vinculado', 'La cuenta con la que ese médico entra a la plataforma'],
    ['Consultorio y planta por defecto', 'Dónde atiende habitualmente'],
  ], [3000, 6026]),
  espacio(),
  ...recuadro('Si un médico no ve sus pacientes', [
    'Casi siempre es el **Nombre en Omnia**: revisá que esté escrito igual que en Omnia (acentos y orden de nombre y apellido incluidos).',
  ]),
];

const cap9 = [
  H1('9. Procedimientos'),
  P('**Procedimientos** es la base de conocimiento del equipo: cómo se hace cada gestión, paso a paso, con capturas o instructivos en PDF. **Todo el equipo puede consultarla**; solo quien tiene acceso de gestión puede **crear y editar**.'),
  ...bullets([
    'Cada procedimiento tiene su propio link, para compartirlo en el chat interno.',
    'Se pueden adjuntar imágenes y PDF.',
    'Una tarea puede vincularse al procedimiento que la resuelve.',
    'Si dos personas editan el mismo procedimiento a la vez, el sistema avisa antes de pisar los cambios del otro.',
  ]),
  ...recuadro('Revisión pendiente', [
    'Los primeros procedimientos cargados son borradores de arranque. La supervisora tiene que revisarlos, en especial los que fijan criterios clínicos, antes de que el equipo los use como referencia.',
  ]),
];

const cap10 = [
  H1('10. Reportes'),
  P('**Supervisión → Reportes** resume la actividad para seguir el volumen de trabajo:'),
  ...bullets([
    'Mensajes recibidos contra enviados, y conversaciones nuevas por día.',
    'Mapa de calor de mensajes entrantes por hora, para ver los horarios pico.',
    'Llegadas a recepción por el tablet, y por motivo (turno, recetas, muestras).',
    'Documentos del legajo.',
  ]),
];

const cap11 = [
  H1('11. Legajo de documentos'),
  P('En **Admin → Legajo** se configura dónde se guardan los documentos de los pacientes y se ve cuántos hay indexados. Es una configuración que se toca muy poco. **Restaurar default** vuelve a la ubicación original; ante la duda, consultá a soporte antes de cambiarla.'),
];

const cap12 = [
  H1('12. Herramientas técnicas'),
  P('Estas pantallas existen para el soporte técnico. No hace falta usarlas en el trabajo diario:'),
  ...bullets([
    '**Logs:** lo que va registrando el bot en vivo. Sirve para diagnosticar problemas junto con soporte.',
    '**Túnel:** acceso remoto de prueba a la plataforma desde fuera de la clínica. Es solo para pruebas técnicas: no lo dejes encendido.',
    '**Tareas programadas** (en el inicio de Admin): los procesos automáticos nocturnos (backup, sincronización con Omnia, mantenimiento) y cuándo corrieron por última vez.',
  ]),
];

const cap13 = [
  H1('13. Respaldo y soporte'),
  H2('13.1 Backup automático'),
  P('Todas las noches, a las **02:30**, se hace un backup completo de la plataforma:'),
  ...bullets([
    'La base de datos completa (conversaciones, pacientes, recepción, reglas, usuarios).',
    'Los archivos que mandan los pacientes (imágenes, audios, documentos).',
    'La configuración y las sesiones de WhatsApp de los bots.',
  ]),
  ...recuadro('Recomendación', [
    'Hoy el backup queda en el mismo equipo que la plataforma. Para estar cubiertos ante una falla del equipo, conviene sumar una copia fuera de él (otro disco, un NAS o la nube). Consultalo con WORKBENCH IT.',
  ], 'ojo'),
  H2('13.2 Sincronización con Omnia'),
  P('Todas las madrugadas la plataforma trae de Omnia los pacientes con turnos recientes y próximos (para completar el directorio de contactos) y actualiza la lista de obras sociales y prácticas que usa el checklist de recepción.'),
  H2('13.3 Cuándo llamar a soporte'),
  ...bullets([
    'Un bot figura **desconectado** o **iniciando** por más de 10 minutos.',
    'Después de escanear un QR, la tarjeta muestra un **número distinto** al del área.',
    'Las colas de WhatsApp no reciben mensajes nuevos en horario de atención.',
    'Recepción no muestra a los pacientes que se anuncian en el tablet.',
    'Aparecen errores al guardar en cualquier pantalla.',
  ]),
  P('Al reportar, ayuda mucho indicar **qué pantalla**, **qué se estaba haciendo** y **a qué hora** pasó.'),
  P('**Soporte técnico:** WORKBENCH IT.'),
];

const glosario = [
  H1('14. Glosario'),
  tabla(['Término', 'Significado'], [
    ['Área', 'Cada uno de los tres números de WhatsApp: Atención, Administración, Ovodonación'],
    ['Cola', 'Lista de conversaciones pendientes de un área'],
    ['Archivar / Resolver', 'Sacar una conversación de la cola sin borrarla; se reabre si el paciente escribe'],
    ['QR', 'Código para vincular un número de WhatsApp con su bot'],
    ['Modo prueba', 'El bot clasifica los mensajes pero no responde ni deriva solo'],
    ['Omnia', 'El sistema de turnos de la clínica'],
    ['Financiador', 'La obra social o prepaga con la que se dio el turno (o Particular)'],
    ['Requisito', 'Algo que recepción le puede pedir al paciente'],
    ['Regla', 'Cuándo se pide un requisito: obra social, plan, práctica y modo'],
    ['Presente', 'Registro de que el paciente llegó y fue recibido, con lo que faltó'],
    ['Tablet', 'La pantalla de la sala donde el paciente se anuncia con su DNI'],
  ], [2600, 6426]),
];


construir({
  titulo: 'Manual de supervisión y administración — Crecer',
  cabecera: 'Crecer · Manual de supervisión y administración',
  contenido: [
    ...portada({
      cliente: 'Clínica Crecer Reproducción',
      titulo: 'Manual de supervisión y administración',
      subtitulo: 'Configuración y gestión de la plataforma',
      para: 'supervisoras y personal administrativo con acceso de gestión',
      version: '1.1, septiembre de 2026',
      indice: INDICE,
    }),
    ...cap1, ...cap2, ...cap3, ...cap4, ...cap5, ...cap6, ...cap7, ...cap8, ...cap9, ...cap10, ...cap11, ...cap12, ...cap13, ...glosario,
  ],
  salida: process.argv[2] || 'Manual-Supervision.docx',
});
