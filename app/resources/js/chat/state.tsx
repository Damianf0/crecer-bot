// Estado global del chat con Context + useReducer.
// El ChatProvider se monta una sola vez en ChatWidget y todos los componentes
// hijos consumen el estado vía useChat().

import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useReducer,
    useRef,
} from 'react';
import type { ReactNode } from 'react';
import type {
    Canal,
    ChatState,
    EventoMensajeEliminado,
    EventoMensajeEnviado,
    Mensaje,
    MensajeOptimistic,
    TabSidebar,
    UsuarioListado,
} from './types';
import { ChatApi } from './api';
import { echo } from './echo';

// ─── Identidad del usuario actual (window.__USER__) ──────────────────
// El layout principal expone window.__USER__ = { id, nombre } para que el JS
// sepa quién es el usuario logueado sin tener que pedirlo al backend.
declare global {
    interface Window {
        __USER__?: { id: number; nombre_completo: string };
    }
}

function yoFromWindow(): ChatState['yo'] {
    const u = window.__USER__;
    return u ? { id: u.id, nombre: u.nombre_completo } : null;
}

// ─── Estado inicial ─────────────────────────────────────────────────
const initialState: ChatState = {
    panelAbierto: false,
    canales: [],
    archivados: [],
    archivadosLoaded: false,
    canalActivo: null,
    mensajes: [],
    mensajesOptimisticos: [],
    mensajesBusqueda: [],
    tab: 'activas',
    modo: 'mensajes',
    cargandoMensajes: false,
    cargandoCanales: false,
    mostrandoModalDm: false,
    usuariosParaDm: [],
    yo: yoFromWindow(),
};

// ─── Acciones del reducer ───────────────────────────────────────────
type Action =
    | { type: 'PANEL_TOGGLE' }
    | { type: 'PANEL_OPEN' }
    | { type: 'PANEL_CLOSE' }
    | { type: 'SET_CANALES'; canales: Canal[] }
    | { type: 'SET_ARCHIVADOS'; archivados: Canal[] }
    | { type: 'SET_TAB'; tab: TabSidebar }
    | { type: 'SELECT_CANAL'; canalId: number }
    | { type: 'DESELECT_CANAL' }
    | { type: 'SET_CARGANDO_CANALES'; cargando: boolean }
    | { type: 'SET_CARGANDO_MENSAJES'; cargando: boolean }
    | { type: 'SET_MENSAJES'; mensajes: Mensaje[] }
    | { type: 'APPEND_MENSAJE'; mensaje: Mensaje }
    | { type: 'MARK_DELETED'; mensajeId: number }
    | { type: 'ADD_OPTIMISTIC'; mensaje: MensajeOptimistic }
    | { type: 'CONFIRM_OPTIMISTIC'; tempId: string; mensajeReal: Mensaje }
    | { type: 'FAIL_OPTIMISTIC'; tempId: string }
    | { type: 'REMOVE_OPTIMISTIC'; tempId: string }
    | { type: 'SET_MODO'; modo: 'mensajes' | 'buscando' }
    | { type: 'SET_BUSQUEDA_RESULTS'; mensajes: Mensaje[] }
    | { type: 'CANAL_OCULTAR'; canalId: number }
    | { type: 'CANAL_DESOCULTAR'; canalId: number }
    | { type: 'MARCAR_CANAL_LEIDO'; canalId: number }
    | { type: 'INCR_NO_LEIDOS'; canalId: number }
    | { type: 'TOGGLE_MODAL_DM' }
    | { type: 'SET_USUARIOS_DM'; usuarios: UsuarioListado[] };

function reducer(state: ChatState, action: Action): ChatState {
    switch (action.type) {
        case 'PANEL_TOGGLE':
            return { ...state, panelAbierto: !state.panelAbierto };
        case 'PANEL_OPEN':
            return { ...state, panelAbierto: true };
        case 'PANEL_CLOSE':
            return { ...state, panelAbierto: false };

        case 'SET_CANALES':
            return { ...state, canales: action.canales, cargandoCanales: false };
        case 'SET_ARCHIVADOS':
            return { ...state, archivados: action.archivados, archivadosLoaded: true };

        case 'SET_TAB':
            return { ...state, tab: action.tab };

        case 'SELECT_CANAL':
            // Al seleccionar canal: reseteamos mensajes y modo a 'mensajes'
            return {
                ...state,
                canalActivo: action.canalId,
                mensajes: [],
                mensajesOptimisticos: [],
                mensajesBusqueda: [],
                modo: 'mensajes',
                cargandoMensajes: true,
            };
        case 'DESELECT_CANAL':
            return {
                ...state,
                canalActivo: null,
                mensajes: [],
                mensajesOptimisticos: [],
                mensajesBusqueda: [],
                modo: 'mensajes',
            };

        case 'SET_CARGANDO_CANALES':
            return { ...state, cargandoCanales: action.cargando };
        case 'SET_CARGANDO_MENSAJES':
            return { ...state, cargandoMensajes: action.cargando };

        case 'SET_MENSAJES':
            return { ...state, mensajes: action.mensajes, cargandoMensajes: false };

        case 'APPEND_MENSAJE': {
            // Idempotente: si ya tenemos el mensaje por id, no duplicar
            if (state.mensajes.some(m => m.id === action.mensaje.id)) return state;
            return { ...state, mensajes: [...state.mensajes, action.mensaje] };
        }

        case 'MARK_DELETED':
            return {
                ...state,
                mensajes: state.mensajes.map(m =>
                    m.id === action.mensajeId ? { ...m, texto: null, eliminado: true } : m
                ),
            };

        case 'ADD_OPTIMISTIC':
            return { ...state, mensajesOptimisticos: [...state.mensajesOptimisticos, action.mensaje] };

        case 'CONFIRM_OPTIMISTIC':
            return {
                ...state,
                mensajesOptimisticos: state.mensajesOptimisticos.filter(m => m.tempId !== action.tempId),
                // Agregamos el mensaje real al final si no estaba ya
                mensajes: state.mensajes.some(m => m.id === action.mensajeReal.id)
                    ? state.mensajes
                    : [...state.mensajes, action.mensajeReal],
            };

        case 'FAIL_OPTIMISTIC':
            return {
                ...state,
                mensajesOptimisticos: state.mensajesOptimisticos.map(m =>
                    m.tempId === action.tempId ? { ...m, estado: 'error' } : m
                ),
            };

        case 'REMOVE_OPTIMISTIC':
            return {
                ...state,
                mensajesOptimisticos: state.mensajesOptimisticos.filter(m => m.tempId !== action.tempId),
            };

        case 'SET_MODO':
            return { ...state, modo: action.modo, mensajesBusqueda: action.modo === 'mensajes' ? [] : state.mensajesBusqueda };
        case 'SET_BUSQUEDA_RESULTS':
            return { ...state, mensajesBusqueda: action.mensajes };

        case 'CANAL_OCULTAR':
            return {
                ...state,
                canales: state.canales.filter(c => c.id !== action.canalId),
                canalActivo: state.canalActivo === action.canalId ? null : state.canalActivo,
                // Si tenemos los archivados cargados, sumamos el que se cerró
                archivados: state.archivadosLoaded
                    ? [
                        ...state.archivados,
                        ...state.canales.filter(c => c.id === action.canalId).map(c => ({ ...c, oculto: true })),
                    ]
                    : state.archivados,
            };

        case 'CANAL_DESOCULTAR':
            return {
                ...state,
                archivados: state.archivados.filter(c => c.id !== action.canalId),
                canales: state.archivados.some(c => c.id === action.canalId)
                    ? [
                        ...state.canales,
                        ...state.archivados
                            .filter(c => c.id === action.canalId)
                            .map(c => ({ ...c, oculto: false })),
                    ]
                    : state.canales,
            };

        case 'MARCAR_CANAL_LEIDO':
            return {
                ...state,
                canales: state.canales.map(c =>
                    c.id === action.canalId ? { ...c, no_leidos: 0 } : c
                ),
            };

        case 'INCR_NO_LEIDOS':
            return {
                ...state,
                canales: state.canales.map(c =>
                    c.id === action.canalId ? { ...c, no_leidos: c.no_leidos + 1 } : c
                ),
            };

        case 'TOGGLE_MODAL_DM':
            return { ...state, mostrandoModalDm: !state.mostrandoModalDm };
        case 'SET_USUARIOS_DM':
            return { ...state, usuariosParaDm: action.usuarios };

        default:
            return state;
    }
}

// ─── Context ────────────────────────────────────────────────────────
interface ChatContextValue {
    state: ChatState;
    dispatch: React.Dispatch<Action>;
    // Acciones de alto nivel (efectos + dispatch)
    abrirPanel: () => void;
    cerrarPanel: () => void;
    togglePanel: () => void;
    cargarCanales: () => Promise<void>;
    cargarArchivados: () => Promise<void>;
    cambiarTab: (tab: TabSidebar) => void;
    seleccionarCanal: (canalId: number) => Promise<void>;
    cerrarSeleccion: () => void;
    enviar: (texto: string) => Promise<void>;
    cerrarCanal: (canalId: number) => Promise<void>;
    reabrirCanal: (canalId: number) => Promise<void>;
    eliminarMensaje: (msgId: number) => Promise<void>;
    buscar: (q: string) => Promise<void>;
    salirBusqueda: () => void;
    abrirModalDm: () => Promise<void>;
    cerrarModalDm: () => void;
    iniciarDm: (userId: number) => Promise<void>;
    totalNoLeidos: number;
}

const ChatContext = createContext<ChatContextValue | null>(null);

export function useChat(): ChatContextValue {
    const ctx = useContext(ChatContext);
    if (!ctx) throw new Error('useChat debe usarse dentro de <ChatProvider>');
    return ctx;
}

export function ChatProvider({ children }: { children: ReactNode }) {
    const [state, dispatch] = useReducer(reducer, initialState);

    const cargarCanales = useCallback(async () => {
        dispatch({ type: 'SET_CARGANDO_CANALES', cargando: true });
        try {
            const canales = await ChatApi.listarCanales();
            dispatch({ type: 'SET_CANALES', canales });
        } catch (e) {
            console.error('[chat] cargarCanales:', e);
            dispatch({ type: 'SET_CARGANDO_CANALES', cargando: false });
        }
    }, []);

    const cargarArchivados = useCallback(async () => {
        try {
            const archivados = await ChatApi.listarArchivados();
            dispatch({ type: 'SET_ARCHIVADOS', archivados });
        } catch (e) {
            console.error('[chat] cargarArchivados:', e);
        }
    }, []);

    const cambiarTab = useCallback((tab: TabSidebar) => {
        dispatch({ type: 'SET_TAB', tab });
        if (tab === 'archivadas' && !state.archivadosLoaded) {
            void cargarArchivados();
        }
    }, [state.archivadosLoaded, cargarArchivados]);

    const seleccionarCanal = useCallback(async (canalId: number) => {
        dispatch({ type: 'SELECT_CANAL', canalId });
        try {
            const mensajes = await ChatApi.listarMensajes(canalId);
            dispatch({ type: 'SET_MENSAJES', mensajes });
            await ChatApi.marcarLeido(canalId);
            dispatch({ type: 'MARCAR_CANAL_LEIDO', canalId });
        } catch (e) {
            console.error('[chat] seleccionarCanal:', e);
            dispatch({ type: 'SET_CARGANDO_MENSAJES', cargando: false });
        }
    }, []);

    const cerrarSeleccion = useCallback(() => {
        dispatch({ type: 'DESELECT_CANAL' });
    }, []);

    const enviar = useCallback(async (texto: string) => {
        if (!state.canalActivo || !state.yo || !texto.trim()) return;
        const tempId = `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        const canalId = state.canalActivo;
        dispatch({
            type: 'ADD_OPTIMISTIC',
            mensaje: {
                tempId,
                user_id: state.yo.id,
                autor: state.yo.nombre,
                texto: texto.trim(),
                estado: 'enviando',
                ts: Math.floor(Date.now() / 1000),
            },
        });
        try {
            const resp = await ChatApi.enviarMensaje(canalId, texto.trim());
            // Re-fetch del mensaje real para tener ts/hora/fecha consistentes con el backend
            const recientes = await ChatApi.listarMensajes(canalId, resp.id - 1);
            const real = recientes.find(m => m.id === resp.id);
            if (real) {
                dispatch({ type: 'CONFIRM_OPTIMISTIC', tempId, mensajeReal: real });
            } else {
                dispatch({ type: 'REMOVE_OPTIMISTIC', tempId });
            }
        } catch (e) {
            console.error('[chat] enviar:', e);
            dispatch({ type: 'FAIL_OPTIMISTIC', tempId });
        }
    }, [state.canalActivo, state.yo]);

    const cerrarCanalAccion = useCallback(async (canalId: number) => {
        try {
            await ChatApi.cerrarCanal(canalId);
            dispatch({ type: 'CANAL_OCULTAR', canalId });
        } catch (e) {
            console.error('[chat] cerrarCanal:', e);
        }
    }, []);

    const reabrirCanalAccion = useCallback(async (canalId: number) => {
        try {
            await ChatApi.reabrirCanal(canalId);
            dispatch({ type: 'CANAL_DESOCULTAR', canalId });
            // Cambiar a tab activas y seleccionar
            dispatch({ type: 'SET_TAB', tab: 'activas' });
            await seleccionarCanal(canalId);
        } catch (e) {
            console.error('[chat] reabrirCanal:', e);
        }
    }, [seleccionarCanal]);

    const eliminarMensajeAccion = useCallback(async (msgId: number) => {
        try {
            await ChatApi.eliminarMensaje(msgId);
            dispatch({ type: 'MARK_DELETED', mensajeId: msgId });
        } catch (e) {
            console.error('[chat] eliminarMensaje:', e);
        }
    }, []);

    const buscar = useCallback(async (q: string) => {
        if (!state.canalActivo || q.trim().length < 2) {
            dispatch({ type: 'SET_BUSQUEDA_RESULTS', mensajes: [] });
            return;
        }
        try {
            const res = await ChatApi.buscarEnCanal(state.canalActivo, q.trim());
            dispatch({ type: 'SET_MODO', modo: 'buscando' });
            dispatch({ type: 'SET_BUSQUEDA_RESULTS', mensajes: res });
        } catch (e) {
            console.error('[chat] buscar:', e);
        }
    }, [state.canalActivo]);

    const salirBusqueda = useCallback(() => {
        dispatch({ type: 'SET_MODO', modo: 'mensajes' });
    }, []);

    const abrirModalDm = useCallback(async () => {
        try {
            const usuarios = await ChatApi.listarUsuarios();
            dispatch({ type: 'SET_USUARIOS_DM', usuarios });
            dispatch({ type: 'TOGGLE_MODAL_DM' });
        } catch (e) {
            console.error('[chat] abrirModalDm:', e);
        }
    }, []);

    const cerrarModalDm = useCallback(() => {
        if (state.mostrandoModalDm) dispatch({ type: 'TOGGLE_MODAL_DM' });
    }, [state.mostrandoModalDm]);

    const iniciarDm = useCallback(async (userId: number) => {
        try {
            const resp = await ChatApi.abrirDm(userId);
            dispatch({ type: 'TOGGLE_MODAL_DM' });
            await cargarCanales();
            await seleccionarCanal(resp.canal_id);
        } catch (e) {
            console.error('[chat] iniciarDm:', e);
        }
    }, [cargarCanales, seleccionarCanal]);

    // Total de no leídos (para el badge del FAB).
    const totalNoLeidos = useMemo(
        () => state.canales.reduce((acc, c) => acc + (c.no_leidos || 0), 0),
        [state.canales]
    );

    // Carga inicial al primer render.
    useEffect(() => {
        void cargarCanales();
    }, [cargarCanales]);

    // ─── Reverb: subscribir a cada canal activo del usuario ─────────
    // Cada vez que cambia la lista de canales (canales que agregamos o
    // ocultamos), recomputamos las subscripciones. La key compuesta evita
    // re-subscribir si solo cambió un metadata interno (preview, no_leidos)
    // pero la membresía sigue igual.
    const canalIdsKey = useMemo(
        () => state.canales.map(c => c.id).sort((a, b) => a - b).join(','),
        [state.canales]
    );

    // Refs estables para evitar resubscripciones cuando cambia un closure.
    const canalActivoRef = useRef(state.canalActivo);
    const yoRef          = useRef(state.yo);
    useEffect(() => { canalActivoRef.current = state.canalActivo; }, [state.canalActivo]);
    useEffect(() => { yoRef.current = state.yo; }, [state.yo]);

    useEffect(() => {
        if (!canalIdsKey) return;
        const ids = canalIdsKey.split(',').filter(Boolean).map(Number);
        const subs = ids.map(id => {
            const channel = echo.private(`chat.canal.${id}`);

            channel.listen('.ChatMensajeEnviado', (ev: EventoMensajeEnviado) => {
                // Ignorar nuestros propios mensajes (los emitimos con ->toOthers
                // del lado server, pero por las dudas filtramos por user_id).
                if (yoRef.current && ev.mensaje.user_id === yoRef.current.id) return;

                if (canalActivoRef.current === ev.canal_id) {
                    // Canal abierto: append directo + marcar leído.
                    dispatch({ type: 'APPEND_MENSAJE', mensaje: ev.mensaje });
                    void ChatApi.marcarLeido(ev.canal_id);
                } else {
                    // Canal no abierto: incrementar badge.
                    dispatch({ type: 'INCR_NO_LEIDOS', canalId: ev.canal_id });
                    // Notificación browser si la pestaña no está enfocada y hay permiso.
                    notificarMensajeEntrante(ev);
                }
            });

            channel.listen('.ChatMensajeEliminado', (ev: EventoMensajeEliminado) => {
                if (canalActivoRef.current === ev.canal_id) {
                    dispatch({ type: 'MARK_DELETED', mensajeId: ev.mensaje_id });
                }
            });

            return id;
        });

        return () => {
            // Limpieza: desuscribirse de cada canal al desmontar o cambiar
            // de lista. echo.leave() resuelve a no-op si no estamos subscritos.
            for (const id of subs) echo.leave(`chat.canal.${id}`);
        };
    }, [canalIdsKey]);

    const value = useMemo<ChatContextValue>(() => ({
        state,
        dispatch,
        abrirPanel:    () => dispatch({ type: 'PANEL_OPEN' }),
        cerrarPanel:   () => dispatch({ type: 'PANEL_CLOSE' }),
        togglePanel:   () => dispatch({ type: 'PANEL_TOGGLE' }),
        cargarCanales,
        cargarArchivados,
        cambiarTab,
        seleccionarCanal,
        cerrarSeleccion,
        enviar,
        cerrarCanal:    cerrarCanalAccion,
        reabrirCanal:   reabrirCanalAccion,
        eliminarMensaje: eliminarMensajeAccion,
        buscar,
        salirBusqueda,
        abrirModalDm,
        cerrarModalDm,
        iniciarDm,
        totalNoLeidos,
    }), [
        state, cargarCanales, cargarArchivados, cambiarTab, seleccionarCanal,
        cerrarSeleccion, enviar, cerrarCanalAccion, reabrirCanalAccion,
        eliminarMensajeAccion, buscar, salirBusqueda, abrirModalDm, cerrarModalDm,
        iniciarDm, totalNoLeidos,
    ]);

    return <ChatContext.Provider value={value}>{children}</ChatContext.Provider>;
}

// ─── Notificación browser para DM entrante ─────────────────────────
// Se dispara cuando llega un mensaje en un canal que NO es el activo y la
// pestaña no está enfocada. Si nunca se pidió permiso, lo pedimos en el
// primer caso (no en boot, para no molestar antes de que el operador
// abra el chat).
let _permisoSolicitado = false;

function notificarMensajeEntrante(ev: EventoMensajeEnviado) {
    // Si la pestaña está enfocada o no soporta Notification, no molestamos.
    if (document.hasFocus()) return;
    if (typeof Notification === 'undefined') return;

    if (Notification.permission === 'default' && !_permisoSolicitado) {
        _permisoSolicitado = true;
        Notification.requestPermission().catch(() => {});
        return;  // primer evento: solo pedimos permiso, no notificamos
    }
    if (Notification.permission !== 'granted') return;

    try {
        const n = new Notification(ev.mensaje.autor || 'Chat interno', {
            body: ev.mensaje.texto || '',
            tag: `chat-${ev.canal_id}`,         // coalescing: una sola notif por canal
            silent: false,
        });
        n.onclick = () => { window.focus(); n.close(); };
        // Auto-cerrar tras 6s si el operador no la toca.
        setTimeout(() => { try { n.close(); } catch {} }, 6000);
    } catch (e) {
        console.warn('[chat] notification error:', e);
    }
}
