// Botón flotante (FAB) que abre/cierra el panel del chat. Muestra badge
// rojo con el total de no leídos cuando hay mensajes pendientes.
// Punto de entrada único del chat tras sacar el ícono del navbar.

import { useChat } from '../state';

export function ChatFab() {
    const { state, togglePanel, totalNoLeidos } = useChat();
    return (
        <button
            type="button"
            className="chat-fab"
            onClick={togglePanel}
            aria-label="Abrir chat interno"
            title={state.panelAbierto ? 'Cerrar chat' : 'Chat interno'}
        >
            💬
            {totalNoLeidos > 0 && !state.panelAbierto && (
                <span className="chat-fab-badge">{totalNoLeidos > 99 ? '99+' : totalNoLeidos}</span>
            )}
        </button>
    );
}
