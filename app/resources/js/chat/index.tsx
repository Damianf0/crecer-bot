// Entry point del bundle del chat interno (React).
// El partial Blade resources/views/chat/_widget.blade.php incluye este bundle
// via @vite() y monta <ChatWidget /> en <div id="chat-root">.

import { createRoot } from 'react-dom/client';
import { ChatWidget } from './ChatWidget';
import './styles.css';
import './echo'; // import-for-side-effect: registra window.Echo

const mountEl = document.getElementById('chat-root');
if (mountEl) {
    createRoot(mountEl).render(<ChatWidget />);
} else {
    console.warn('[chat] No se encontró <div id="chat-root">; el chat no se monta.');
}
