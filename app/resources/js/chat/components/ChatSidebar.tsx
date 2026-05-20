// Sidebar con tabs Activas / Archivadas y lista de canales.
// El item de cada canal tiene la X SIEMPRE visible (no solo hover) — pedido
// explícito del operador.

import { useChat } from '../state';
import type { Canal } from '../types';
import { Avatar } from './Avatar';

export function ChatSidebar() {
    const { state, cambiarTab, abrirModalDm } = useChat();
    const items = state.tab === 'activas' ? state.canales : state.archivados;

    return (
        <div className="chat-sidebar">
            <div className="chat-sidebar-tabs">
                <button
                    type="button"
                    className={`chat-tab ${state.tab === 'activas' ? 'active' : ''}`}
                    onClick={() => cambiarTab('activas')}
                >
                    Activas {state.canales.length > 0 && <span className="chat-tab-count">{state.canales.length}</span>}
                </button>
                <button
                    type="button"
                    className={`chat-tab ${state.tab === 'archivadas' ? 'active' : ''}`}
                    onClick={() => cambiarTab('archivadas')}
                >
                    Archivadas {state.archivadosLoaded && state.archivados.length > 0 && (
                        <span className="chat-tab-count">{state.archivados.length}</span>
                    )}
                </button>
            </div>

            <div className="chat-sidebar-list">
                {state.cargandoCanales && items.length === 0 ? (
                    <div className="chat-sidebar-empty">Cargando…</div>
                ) : items.length === 0 ? (
                    <div className="chat-sidebar-empty">
                        {state.tab === 'activas' ? 'Sin conversaciones' : 'Sin conversaciones archivadas'}
                    </div>
                ) : (
                    items.map(c => <CanalItem key={c.id} canal={c} archivado={state.tab === 'archivadas'} />)
                )}
            </div>

            {state.tab === 'activas' && (
                <button type="button" className="chat-nuevo-dm" onClick={abrirModalDm}>
                    + Nuevo DM
                </button>
            )}
        </div>
    );
}

function CanalItem({ canal, archivado }: { canal: Canal; archivado: boolean }) {
    const { state, seleccionarCanal, cerrarCanal, reabrirCanal } = useChat();
    const activo = state.canalActivo === canal.id;
    const tipo = canal.tipo === 'equipo' ? 'Equipo' : 'DM';
    const preview = canal.ultimo_msg ? canal.ultimo_msg.texto : 'sin mensajes';

    const onClick = () => {
        if (archivado) {
            void reabrirCanal(canal.id);
        } else {
            void seleccionarCanal(canal.id);
        }
    };

    const onCerrar = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (canal.tipo === 'equipo') return; // no se puede cerrar el equipo
        const ok = confirm(
            archivado
                ? '¿Eliminar esta conversación de Archivadas? Reaparece si recibís un mensaje.'
                : '¿Cerrar esta conversación? El historial no se borra. Va a Archivadas.'
        );
        if (!ok) return;
        void cerrarCanal(canal.id);
    };

    return (
        <div className={`chat-canal-item ${activo ? 'active' : ''}`} onClick={onClick}>
            <Avatar
                nombre={canal.nombre}
                esEquipo={canal.tipo === 'equipo'}
                online={canal.otro_online}
            />
            <div className="chat-canal-body">
                <div className="chat-canal-nombre">
                    {canal.nombre}
                    <span className={`chat-canal-tipo tipo-${canal.tipo}`}>{tipo}</span>
                </div>
                <div className="chat-canal-preview">{preview}</div>
            </div>
            <div className="chat-canal-actions">
                {canal.no_leidos > 0 && !archivado && (
                    <span className="chat-canal-noleidos">{canal.no_leidos}</span>
                )}
                {canal.tipo !== 'equipo' && (
                    <button
                        type="button"
                        className="chat-canal-cerrar"
                        onClick={onCerrar}
                        title={archivado ? 'Quitar de archivadas' : 'Cerrar conversación'}
                        aria-label="Cerrar"
                    >
                        ✕
                    </button>
                )}
            </div>
        </div>
    );
}
