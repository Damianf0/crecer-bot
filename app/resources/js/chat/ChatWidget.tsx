// Componente raíz del chat interno.
// Monta el Provider del estado y renderiza el FAB + Panel.

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
