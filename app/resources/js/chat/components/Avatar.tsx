// Avatar pequeño: ícono Equipo para canal=equipo, inicial coloreada para DMs.
// Si el otro usuario está online, muestra un punto verde abajo a la derecha.

interface Props {
    nombre: string;
    esEquipo: boolean;
    size?: number;
    online?: boolean | null;
}

const COLORES = ['#1a56c4', '#00875a', '#c96a00', '#8C1B29', '#5e2ca5', '#0d8073'];

function hashColor(nombre: string): string {
    const sum = [...nombre].reduce((a, c) => a + c.charCodeAt(0), 0);
    return COLORES[sum % COLORES.length];
}

export function Avatar({ nombre, esEquipo, size = 30, online }: Props) {
    if (esEquipo) {
        return (
            <div style={{ position: 'relative', width: size, height: size, flexShrink: 0 }}>
                <div
                    style={{
                        width: size, height: size, borderRadius: '50%',
                        background: 'var(--accent)', color: '#fff',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        fontSize: Math.floor(size / 2),
                    }}
                    title="Canal Equipo"
                >
                    ⛬
                </div>
            </div>
        );
    }
    const inicial = (nombre || '?').trim().charAt(0).toUpperCase();
    return (
        <div style={{ position: 'relative', width: size, height: size, flexShrink: 0 }}>
            <div
                style={{
                    width: size, height: size, borderRadius: '50%',
                    background: hashColor(nombre), color: '#fff',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontWeight: 700, fontSize: Math.floor(size / 2.4),
                }}
            >
                {inicial}
            </div>
            {online && <span className="chat-online-dot" />}
        </div>
    );
}
