// Componente raíz del chat interno.
//
// FASE 3 (actual): stub "Hola desde React" como un toast pequeño abajo-derecha
// para validar que el bundle se monta correctamente sin romper el chat viejo
// (que sigue funcionando con su JS plano hasta que Fase 4 lo reemplace).
//
// FASE 4 (próxima): este archivo crece a ser el root real con ChatProvider
// (Context + reducer) + ChatFab + ChatPanel.

export function ChatWidget() {
    return (
        <div
            style={{
                position: 'fixed',
                bottom: 14,
                left: 14,
                zIndex: 9000,
                padding: '6px 10px',
                background: 'rgba(0, 0, 0, 0.7)',
                color: '#0f0',
                fontFamily: 'monospace',
                fontSize: 10,
                borderRadius: 4,
                pointerEvents: 'none',
            }}
        >
            chat-react ready
        </div>
    );
}
