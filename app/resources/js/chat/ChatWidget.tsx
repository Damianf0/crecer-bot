// Componente raíz del chat interno.
// Monta el Provider del estado y renderiza el FAB flotante + Panel.
// El ícono del navbar fue removido por pedido del operador: el FAB es el
// único punto de entrada para abrir/cerrar el chat.

import { ChatProvider } from './state';
import { ChatFab } from './components/ChatFab';
import { ChatPanel } from './components/ChatPanel';

export function ChatWidget() {
    return (
        <ChatProvider>
            <ChatFab />
            <ChatPanel />
        </ChatProvider>
    );
}
