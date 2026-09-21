// Cliente Laravel Echo apuntado a nuestro container Reverb (ws://host:8080).
// El frontend (React) lo importa de aquí para suscribirse a canales privados
// de chat. La auth de canal va por /broadcasting/auth con el cookie de
// sesión Laravel, ver routes/channels.php.

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Pusher.js es el cliente que Echo usa por debajo; Reverb implementa el mismo
// protocolo, así que no necesitamos un cliente especial.
(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

// Las env vars vienen de Vite. .env de Crecer tiene VITE_REVERB_* mapeadas
// a REVERB_*. Si alguna falta, el constructor de Echo tira en runtime.
const KEY  = import.meta.env.VITE_REVERB_APP_KEY  as string;
// Host: el mismo con el que se abrió el panel. VITE_REVERB_HOST=localhost
// quedaba compilado en el bundle y desde cualquier PC que no fuera el server
// el socket apuntaba a la propia PC — el tiempo real del chat interno solo
// andaba en el server (19/05 → 21/09). Un valor explícito distinto de
// localhost en .env sigue mandando.
const HOST_ENV = (import.meta.env.VITE_REVERB_HOST as string) || '';
const HOST = HOST_ENV && HOST_ENV !== 'localhost' ? HOST_ENV : window.location.hostname;
const PORT = Number(import.meta.env.VITE_REVERB_PORT) || 8080;
const TLS  = import.meta.env.VITE_REVERB_SCHEME === 'https';

export const echo = new Echo({
    broadcaster: 'reverb',
    key: KEY,
    wsHost: HOST,
    wsPort: PORT,
    wssPort: PORT,
    forceTLS: TLS,
    enabledTransports: ['ws', 'wss'],
});

// Para debug en consola del browser.
(window as unknown as { Echo: typeof echo }).Echo = echo;
