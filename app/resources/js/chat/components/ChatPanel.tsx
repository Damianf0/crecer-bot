// Contenedor del panel del chat: sidebar + main + modal de nuevo DM.
// Visible cuando state.panelAbierto es true.

import { useChat } from '../state';
import { ChatSidebar } from './ChatSidebar';
import { ChatMain } from './ChatMain';
import { NuevoDmModal } from './NuevoDmModal';

export function ChatPanel() {
    const { state } = useChat();
    if (!state.panelAbierto) return null;

    return (
        <div className="chat-panel">
            <ChatSidebar />
            <ChatMain />
            <NuevoDmModal />
        </div>
    );
}
