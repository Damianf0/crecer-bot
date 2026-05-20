// Modal para iniciar un DM nuevo con otro usuario activo del sistema.
// Lista los usuarios con presencia (dot verde si online).

import { useChat } from '../state';

export function NuevoDmModal() {
    const { state, cerrarModalDm, iniciarDm } = useChat();
    if (!state.mostrandoModalDm) return null;

    return (
        <div className="chat-dm-modal open" onClick={cerrarModalDm}>
            <div className="chat-dm-card" onClick={e => e.stopPropagation()}>
                <div className="chat-dm-card-head">
                    <span>Nuevo mensaje directo</span>
                    <button type="button" onClick={cerrarModalDm} aria-label="Cerrar">×</button>
                </div>
                <div className="chat-dm-card-list">
                    {state.usuariosParaDm.length === 0 ? (
                        <div className="chat-dm-empty">Sin otros usuarios activos</div>
                    ) : (
                        state.usuariosParaDm.map(u => (
                            <button
                                key={u.id}
                                type="button"
                                className="chat-dm-user"
                                onClick={() => void iniciarDm(u.id)}
                            >
                                <span className="chat-dm-user-nombre">{u.nombre_completo}</span>
                                {u.online && <span className="chat-dm-user-online">●</span>}
                            </button>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}
