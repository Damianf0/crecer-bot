// Manual del área de Administración de la clínica (uso diario de la plataforma).
const { P, H1, H2, H3, bullets, pasos, recuadro, tabla, espacio, portada, construir } = require('./lib-manual');

const INDICE = [
  'Antes de empezar',
  'Tu cola de WhatsApp',
  'Atender una conversación',
  'Pasar una conversación a otra persona o área',
  'Escribirle primero a un paciente',
  'El legajo del contacto',
  'Tareas, llamadas y agenda',
  'Mi día y Mis conversaciones',
  'Chat interno y avisos',
  'Procedimientos',
  'Buenas prácticas',
  'Si algo no funciona',
];

const contenido = [
  ...portada({
    cliente: 'Clínica Crecer Reproducción',
    titulo: 'Manual del área de Administración',
    subtitulo: 'WhatsApp de Administración y herramientas del día a día',
    para: 'personal del área de Administración de la clínica',
    version: '1.0, septiembre de 2026',
    indice: INDICE,
  }),

  H1('1. Antes de empezar'),
  P('La plataforma reúne en una sola pantalla los mensajes que llegan al **WhatsApp de Administración**, las tareas del equipo, la agenda y el directorio de pacientes. Se usa desde el navegador de cualquier PC de la clínica.'),
  H2('1.1 Entrar'),
  ...pasos([
    'Abrí el navegador en la dirección de la plataforma que te pasó la supervisora.',
    'Entrá con tu **email** y tu **contraseña**.',
    'Si aparece la pantalla para **declarar tus colas**, marcá **Administración** (y las otras áreas solo si hoy también las atendés). Así el sistema sabe qué conversaciones mostrarte.',
    'Vas a caer en **Mi día**, tu resumen de la jornada (capítulo 8).',
  ]),
  ...recuadro('Contraseña', [
    'Tiene que tener **al menos 10 caracteres** y no puede ser solo números.',
    'Después de **5 intentos fallidos** la cuenta se bloquea **15 minutos**. Si te olvidaste la contraseña, pedile a la supervisora que te la cambie.',
    'No la compartas: todo lo que hacés en la plataforma queda registrado con tu nombre.',
  ]),
  H2('1.2 La pantalla'),
  ...bullets([
    '**Menú lateral (izquierda):** tus accesos. En **WhatsApp** están las colas de cada área; la tuya es **Administración**. El número rojo al lado indica cuántas conversaciones esperan respuesta.',
    '**Arriba a la derecha:** el indicador **Bots ok** (los WhatsApp conectados) y el botón para salir.',
    '**Burbuja roja (abajo a la derecha):** el chat interno con el equipo (capítulo 9).',
  ]),

  H1('2. Tu cola de WhatsApp'),
  P('Todo lo que un paciente le escribe al **WhatsApp de Administración (+54 9 223 346-9767)** aparece en **WhatsApp → Administración**. Una conversación es el hilo completo con ese paciente, con todos sus mensajes.'),
  H2('2.1 Las vistas de la cola'),
  tabla(['Vista', 'Qué muestra'], [
    ['En espera', 'Conversaciones con mensajes sin responder que **nadie tomó todavía**'],
    ['En proceso', 'Conversaciones que ya **tiene asignadas alguien** del equipo (se ve quién)'],
    ['Urgentes', 'Las marcadas como urgentes; además aparecen siempre primero'],
  ], [2400, 6626]),
  espacio(),
  P('Cada tarjeta muestra el nombre o número del paciente, hace cuánto escribió y un resumen del motivo. El buscador de arriba filtra por nombre o teléfono.'),
  ...recuadro('Se actualiza sola', [
    'La cola se actualiza **al instante** cuando entra un mensaje o alguien del equipo toma, responde o resuelve una conversación. No hace falta recargar la página.',
  ]),
  H2('2.2 El bot no responde por su cuenta'),
  P('Hoy el bot de WhatsApp está en **modo prueba**: lee y clasifica los mensajes, pero **no le contesta al paciente**. Todas las respuestas las da el equipo desde esta cola.'),

  H1('3. Atender una conversación'),
  H2('3.1 Tomarla'),
  ...pasos([
    'Tocá la tarjeta en **En espera**: se abre la conversación en el centro y el legajo del paciente a la derecha.',
    'Tocá **Tomar**. Pasa a **En proceso** a tu nombre, y el resto del equipo ve que la estás atendiendo.',
  ]),
  P('Tomar la conversación antes de responder evita que dos personas le contesten lo mismo al paciente. Cuando la tenés vos, la píldora dice **La tenés vos**.'),
  P('Si ya la tomó otra persona, el botón dice **Tomarla yo**: usalo solo si esa persona no está o te la pasó.'),
  H2('3.2 Responder'),
  ...bullets([
    'Escribí en el cuadro de abajo y tocá **Enviar**. El mensaje sale desde el WhatsApp de Administración.',
    '**📋 Respuestas:** inserta una respuesta rápida ya redactada (saludos, indicaciones frecuentes). Podés editarla antes de enviar.',
    '**📎 Adjuntar:** manda imágenes, PDF u otros documentos. Por seguridad no se aceptan programas ni archivos ejecutables.',
    '**↩ Responder a un mensaje:** pasá el mouse sobre un mensaje y tocá la flecha para contestarlo citado, como en WhatsApp.',
  ]),
  H2('3.3 Nota interna'),
  P('Con **Nota interna** (al lado de "Responder", arriba del cuadro de texto) escribís algo que **queda en la conversación pero no se le envía al paciente**. Sirve para dejarle contexto a una compañera: "ya le pedí la autorización a la obra social", "llamar el jueves".'),
  H2('3.4 Urgente'),
  P('La bandera **⚑** marca o desmarca la conversación como urgente. Las urgentes se ven primero en la cola y en la vista **Urgentes**.'),
  H2('3.5 Resolver'),
  P('Cuando el tema está terminado, tocá **Resolver**. La conversación sale de la cola, pero **no se borra**: queda en el historial.'),
  ...recuadro('Si el paciente vuelve a escribir', [
    'Una conversación resuelta **se reabre sola** cuando el paciente manda un mensaje nuevo, y vuelve a la cola. Si hace falta retomarla antes, está el botón **Reabrir**.',
  ]),
  H2('3.6 Mensajes enviados desde el celular'),
  P('Si alguien responde directamente desde el celular de Administración, ese mensaje **también queda registrado** en la conversación del panel. Igual conviene responder desde el panel: así queda claro quién contestó y la conversación se puede tomar y resolver.'),

  H1('4. Pasar una conversación a otra persona o área'),
  tabla(['Botón', 'Qué hace', 'Cuándo usarlo'], [
    ['**Delegar ▾**', 'Asigna la conversación a otra persona del equipo', 'Una compañera sigue el tema (vacaciones, especialidad)'],
    ['**↪ Área**', 'La pasa a la cola de otra área: Atención u Ovodonación', 'El paciente escribió a Administración por un tema de otra área'],
    ['**📤 Reenviar y archivar**', 'Le reenvía el hilo a otro contacto por WhatsApp y archiva la conversación', 'Hay que pasarle el caso a alguien de afuera (un profesional, un proveedor)'],
  ], [2300, 3600, 3126]),
  espacio(),
  ...recuadro('Al pasar a otra área, se le avisa al paciente', [
    'La plataforma le manda automáticamente un mensaje desde el número de Administración: le dice que su consulta la sigue el equipo de la otra área y **desde qué número le van a escribir**. A partir de ahí, le responden desde el número de esa área.',
    'Si ese aviso no se puede enviar, **no se deriva nada** y la pantalla lo informa. Si hace falta, dejá una **nota interna** explicando por qué la pasaste.',
  ]),
  P('Cuando alguien te delega una conversación, te llega un aviso en el navegador (capítulo 9).'),

  H1('5. Escribirle primero a un paciente'),
  P('Para iniciar una conversación nueva (por ejemplo, avisarle a alguien que su trámite está listo):'),
  ...pasos([
    'En la cola de Administración, tocá **+ Nueva**.',
    '**Buscar contacto:** escribí el nombre o el teléfono de alguien que ya está en el directorio. **Número manual:** cargá un número que no está (con característica, por ejemplo 2235123456).',
    'Escribí el **primer mensaje**.',
    'Tocá **Iniciar y enviar**. Antes de mandar, la plataforma verifica que el número tenga WhatsApp; si no lo tiene, avisa y no crea nada.',
  ]),
  P('También se puede iniciar desde **Mi día** con el botón de conversación nueva.'),

  H1('6. El legajo del contacto'),
  P('El panel derecho de una conversación abierta es el **legajo** del paciente:'),
  ...bullets([
    '**Datos del contacto:** nombre, teléfono, DNI y lo que figure en el directorio.',
    '**Lo de esta conversación:** tareas, llamadas y citas vinculadas a este hilo.',
    '**Actividad:** lo que pasó con el paciente (tomas, derivaciones, resoluciones).',
  ]),
  ...recuadro('"No está en contactos"', [
    'Si el paciente todavía no está en el directorio, el legajo lo indica y muestra **+ Agregar a contactos**. Cargalo con nombre y, si lo tenés, DNI: así la próxima vez se lo reconoce por su nombre.',
  ]),

  H1('7. Tareas, llamadas y agenda'),
  P('Desde una conversación abierta podés dejar pendientes sin salir de la pantalla:'),
  tabla(['Botón', 'Para qué'], [
    ['**📋 Tarea**', 'Algo que hay que hacer: "pedir autorización a IOMA", "mandar presupuesto"'],
    ['**📞 Llamada**', 'Una llamada a hacerle al paciente'],
    ['**🗓 Agendar**', 'Un compromiso con fecha y hora en la agenda'],
  ], [2400, 6626]),
  espacio(),
  P('Cada tarea tiene **título**, **detalle**, **prioridad** (baja, normal, alta o urgente), **fecha de vencimiento** y a quién se asigna. Queda vinculada al paciente y a la conversación.'),
  ...bullets([
    '**Tareas** (menú Trabajo): el centro de tareas del equipo, con lo pendiente, lo vencido y lo hecho.',
    '**Agenda** (menú Trabajo): los compromisos con fecha.',
    'Si una tarea tiene un **procedimiento** asociado, desde la tarea se abre el paso a paso para resolverla (capítulo 10).',
  ]),

  H1('8. Mi día y Mis conversaciones'),
  H2('8.1 Mi día'),
  P('Es la pantalla de inicio. Resume lo tuyo para la jornada:'),
  ...bullets([
    '**Mis conversaciones:** las que tenés asignadas.',
    '**Tareas pendientes:** las tuyas, con las que vencen hoy destacadas.',
    '**Sin asignar en colas:** cuántas conversaciones esperan que alguien las tome.',
    'Accesos rápidos para crear una **tarea** o **iniciar una conversación**, y para ir a la agenda.',
  ]),
  H2('8.2 Mis conversaciones'),
  P('En **WhatsApp → Mis conversaciones** están todas las conversaciones asignadas a vos, **de cualquier área**. Es la forma más rápida de retomar lo que dejaste en curso.'),

  H1('9. Chat interno y avisos'),
  H2('9.1 Chat interno'),
  P('La **burbuja roja** abajo a la derecha abre el chat del equipo: un canal general y mensajes directos entre compañeras. Es para coordinar internamente; **nunca le llega al paciente**. Los mensajes aparecen al instante.'),
  H2('9.2 Avisos del navegador'),
  P('La plataforma puede mostrar avisos en la PC aunque estés en otra ventana. Avisa cuando:'),
  ...bullets([
    'Entra una conversación **urgente** nueva.',
    'Alguien te **delega** una conversación.',
    'Te escriben por el **chat interno**.',
  ]),
  P('La primera vez, el navegador pregunta si permitís las notificaciones: tocá **Permitir**.'),

  H1('10. Procedimientos'),
  P('**Procedimientos** (menú lateral) es la base de conocimiento de la clínica: cómo se hace cada gestión, paso a paso, con capturas o instructivos. Antes de preguntar cómo se hace un trámite, conviene buscarlo ahí.'),
  ...bullets([
    'Cada procedimiento tiene su propio link: podés pasárselo a una compañera por el chat interno.',
    'Los carga y actualiza la supervisora. Si encontrás algo desactualizado, avisale.',
  ]),

  H1('11. Buenas prácticas'),
  ...bullets([
    '**Tomá antes de responder**, para que no conteste dos veces la misma persona.',
    '**Resolvé cuando termines.** Una cola limpia deja ver rápido lo que de verdad está pendiente.',
    '**Usá notas internas** para dejar contexto. La compañera del turno siguiente las va a leer.',
    '**Si no es de tu área, pasala con ↪ Área** en vez de responder "escribí al otro número".',
    '**Cargá a los pacientes en contactos** cuando aparezcan como "No está en contactos".',
    '**No compartas tu usuario.** Cada acción queda registrada con el nombre de quien la hizo.',
  ]),
  ...recuadro('El celular de Administración', [
    'El WhatsApp de Administración está vinculado a la plataforma. En el celular, **no toques "Dispositivos vinculados"** ni cierres esa sesión: si se cierra, la cola deja de recibir mensajes hasta que se vuelva a vincular.',
    'Mantené el celular **cargado y con conexión**: si pasa mucho tiempo sin internet, WhatsApp puede desvincular la plataforma.',
  ], 'ojo'),

  H1('12. Si algo no funciona'),
  tabla(['Lo que pasa', 'Qué hacer'], [
    ['La cola no recibe mensajes nuevos y el indicador no dice "Bots ok"', 'Avisale a la supervisora: puede que haya que volver a vincular el WhatsApp'],
    ['Aparece "Sesión expirada, recargá (F5)"', 'Recargá la página con F5 y volvé a intentar'],
    ['No se envía un mensaje o un adjunto', 'Probá de nuevo en un minuto; si sigue, avisale a la supervisora con la hora y el paciente'],
    ['No podés entrar (cuenta bloqueada)', 'Esperá 15 minutos o pedile a la supervisora que te cambie la contraseña'],
    ['Algo se ve raro después de un cambio en el sistema', 'Recargá con F5'],
  ], [4300, 4726]),
  espacio(),
  P('La supervisora deriva los problemas técnicos a **WORKBENCH IT**. Para que se resuelvan rápido, anotá **qué pantalla**, **qué estabas haciendo** y **a qué hora** pasó.'),
];

construir({
  titulo: 'Manual del área de Administración — Crecer',
  cabecera: 'Crecer · Manual del área de Administración',
  contenido,
  salida: process.argv[2] || 'Manual-Area-Administracion.docx',
});
