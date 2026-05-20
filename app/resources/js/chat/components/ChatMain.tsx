// Área principal del panel: mensajes + composer.
// Incluye SearchBar, ChatMensaje (in/out, eliminado, optimistic) y Composer
// (textarea + send + Ctrl+Enter) en este archivo por simplicidad.

import { useCallback, useEffect, useRef, useState } from 'react';
import { useChat } from '../state';
import type { Mensaje, MensajeOptimistic } from '../types';

export function ChatMain() {
    const { state, salirBusqueda } = useChat();

    if (state.canalActivo === null) {
        return (
            <div className="chat-main">
                <div className="chat-empty-main">Seleccioná una conversación</div>
            </div>
        );
    }

    const canal = state.canales.find(c => c.id === state.canalActivo)
        ?? state.archivados.find(c => c.id === state.canalActivo);
    const nombreCanal = canal?.nombre ?? '—';

    return (
        <div className="chat-main">
            <ChatHead nombre={nombreCanal} />
            {state.modo === 'buscando' ? (
                <BusquedaResultados onSalir={salirBusqueda} />
            ) : (
                <MensajesLista />
            )}
            <Composer />
        </div>
    );
}

function ChatHead({ nombre }: { nombre: string }) {
    const { state, cerrarSeleccion, dispatch } = useChat();
    return (
        <div className="chat-main-head">
            <b>{nombre}</b>
            <div style={{ display: 'flex', gap: 6 }}>
                <button
                    type="button"
                    className="chat-head-btn"
                    onClick={() => dispatch({ type: 'SET_MODO', modo: state.modo === 'buscando' ? 'mensajes' : 'buscando' })}
                    title="Buscar en este chat"
                    aria-label="Buscar"
                >
                    🔍
                </button>
                <button
                    type="button"
                    className="close"
                    onClick={cerrarSeleccion}
                    title="Cerrar conversación"
                    aria-label="Cerrar"
                >
                    ×
                </button>
            </div>
        </div>
    );
}

function BusquedaResultados({ onSalir }: { onSalir: () => void }) {
    const { state, buscar } = useChat();
    const [q, setQ] = useState('');

    // Debounce 300ms del input al fetch.
    useEffect(() => {
        const t = setTimeout(() => { void buscar(q); }, 300);
        return () => clearTimeout(t);
    }, [q, buscar]);

    return (
        <div className="chat-busqueda">
            <div className="chat-busqueda-bar">
                <input
                    type="text"
                    autoFocus
                    placeholder="Buscar en el historial…"
                    value={q}
                    onChange={e => setQ(e.target.value)}
                />
                <button type="button" onClick={onSalir} aria-label="Salir del modo búsqueda">×</button>
            </div>
            <div className="chat-msgs">
                {q.length < 2 ? (
                    <div className="chat-msg-empty">Escribí al menos 2 caracteres</div>
                ) : state.mensajesBusqueda.length === 0 ? (
                    <div className="chat-msg-empty">Sin resultados para "{q}"</div>
                ) : (
                    state.mensajesBusqueda.map(m => <MensajeBubble key={m.id} mensaje={m} />)
                )}
            </div>
        </div>
    );
}

function MensajesLista() {
    const { state } = useChat();
    const listRef = useRef<HTMLDivElement | null>(null);

    // Auto-scroll al fondo cuando cambian los mensajes (entrada normal) o cuando
    // el operador agrega uno optimista (lo recién tipeado).
    useEffect(() => {
        const el = listRef.current;
        if (!el) return;
        el.scrollTop = el.scrollHeight;
    }, [state.mensajes.length, state.mensajesOptimisticos.length]);

    const mostrarVacio =
        !state.cargandoMensajes &&
        state.mensajes.length === 0 &&
        state.mensajesOptimisticos.length === 0;

    return (
        <div className="chat-msgs" ref={listRef}>
            {state.cargandoMensajes && state.mensajes.length === 0 && (
                <div className="chat-msg-empty">Cargando…</div>
            )}
            {mostrarVacio && <div className="chat-msg-empty">Aún no hay mensajes</div>}
            {state.mensajes.map(m => <MensajeBubble key={m.id} mensaje={m} />)}
            {state.mensajesOptimisticos.map(m => <MensajeOptBubble key={m.tempId} mensaje={m} />)}
        </div>
    );
}

function MensajeBubble({ mensaje }: { mensaje: Mensaje }) {
    const { state, eliminarMensaje } = useChat();
    const propio = state.yo?.id === mensaje.user_id;
    const cls = `chat-msg ${propio ? 'out' : 'in'}${mensaje.eliminado ? ' eliminado' : ''}`;

    const onEliminar = () => {
        if (!confirm('¿Eliminar este mensaje?')) return;
        void eliminarMensaje(mensaje.id);
    };

    return (
        <div className={cls}>
            {!propio && !mensaje.eliminado && (
                <div className="chat-msg-autor">{mensaje.autor}</div>
            )}
            <div>{mensaje.eliminado ? 'Mensaje eliminado' : mensaje.texto}</div>
            <div className="chat-msg-time">{mensaje.hora}</div>
            {propio && !mensaje.eliminado && (
                <button
                    type="button"
                    className="chat-msg-borrar"
                    onClick={onEliminar}
                    title="Eliminar"
                    aria-label="Eliminar"
                >
                    ✕
                </button>
            )}
        </div>
    );
}

function MensajeOptBubble({ mensaje }: { mensaje: MensajeOptimistic }) {
    const fallo = mensaje.estado === 'error';
    const cls = `chat-msg out ${fallo ? 'opt-error' : 'opt-enviando'}`;
    return (
        <div className={cls}>
            <div>{mensaje.texto}</div>
            <div className="chat-msg-time">
                {fallo ? 'no se pudo enviar ✗' : 'enviando…'}
            </div>
        </div>
    );
}

function Composer() {
    const { enviar } = useChat();
    const [val, setVal] = useState('');
    const inputRef = useRef<HTMLTextAreaElement | null>(null);

    const onSubmit = useCallback(async () => {
        const txt = val.trim();
        if (!txt) return;
        setVal('');
        await enviar(txt);
        inputRef.current?.focus();
    }, [val, enviar]);

    const onKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            void onSubmit();
        }
    };

    return (
        <div className="chat-input-row">
            <textarea
                ref={inputRef}
                className="chat-input"
                placeholder="Escribir mensaje… (Ctrl+Enter para enviar)"
                value={val}
                onChange={e => setVal(e.target.value)}
                onKeyDown={onKeyDown}
            />
            <button
                type="button"
                className="chat-send"
                onClick={() => void onSubmit()}
                disabled={!val.trim()}
            >
                Enviar
            </button>
        </div>
    );
}
