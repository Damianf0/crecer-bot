// Tipos compartidos del chat interno. Lo que devuelve cada endpoint de
// /chat/* está documentado en ChatController.php. Mantener sincronizado
// con el backend si se cambia el shape de las respuestas.

export type CanalTipo = 'equipo' | 'dm';

export interface UltimoMsgPreview {
    texto: string;
    user_id: number;
    hora: string | null;
    fecha: string | null;
    ts: number;
}

export interface Canal {
    id: number;
    tipo: CanalTipo;
    nombre: string;
    otro_id: number | null;
    otro_online: boolean | null;
    oculto: boolean;
    no_leidos: number;
    ultimo_msg: UltimoMsgPreview | null;
}

export interface Mensaje {
    id: number;
    user_id: number;
    autor: string;
    texto: string | null; // null si eliminado
    eliminado: boolean;
    hora: string | null;
    fecha: string | null;
    ts: number;
}

// Mensaje que aún no fue confirmado por el server. Se renderiza con opacidad
// reducida; cuando el POST responde, se reemplaza por el Mensaje real.
export interface MensajeOptimistic {
    tempId: string;         // uuid local — para identificarlo y reconciliarlo
    user_id: number;
    autor: string;
    texto: string;
    estado: 'enviando' | 'error';
    ts: number;             // timestamp local al momento de enviar
}

export interface UsuarioListado {
    id: number;
    nombre_completo: string;
    online: boolean;
}

// Evento que llega via Reverb cuando otro usuario manda un msg en un canal
// del que somos miembros. El backend lo emite via ChatMensajeEnviado.
export interface EventoMensajeEnviado {
    canal_id: number;
    mensaje: Mensaje;
}

// Idem para soft-delete.
export interface EventoMensajeEliminado {
    canal_id: number;
    mensaje_id: number;
}

// Tab del sidebar.
export type TabSidebar = 'activas' | 'archivadas';

// Modo de visualización del área principal de mensajes.
export type ModoVista = 'mensajes' | 'buscando';

export interface ChatState {
    panelAbierto: boolean;
    canales: Canal[];
    archivados: Canal[];
    archivadosLoaded: boolean;       // lazy: solo se carga la primera vez que el operador entra a la tab
    canalActivo: number | null;
    mensajes: Mensaje[];
    mensajesOptimisticos: MensajeOptimistic[];
    mensajesBusqueda: Mensaje[];     // resultados del modo busqueda (en lugar de mensajes normales)
    tab: TabSidebar;
    modo: ModoVista;
    cargandoMensajes: boolean;
    cargandoCanales: boolean;
    mostrandoModalDm: boolean;
    usuariosParaDm: UsuarioListado[];
    yo: { id: number; nombre: string } | null;
}
