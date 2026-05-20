// Wrappers de fetch tipados para /chat/*. Toman el shape del backend
// (ChatController.php) y devuelven los tipos de types.ts.
//
// Auth: usa el cookie de sesión Laravel (credentials: 'same-origin') más
// el CSRF token leído del meta tag. NO hay tokens custom — el chat vive
// dentro de la app autenticada.

import type {
    Canal,
    Mensaje,
    UsuarioListado,
} from './types';

function csrfToken(): string {
    const el = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    return el?.content ?? '';
}

async function request<T>(
    method: 'GET' | 'POST' | 'DELETE',
    url: string,
    body?: unknown,
): Promise<T> {
    const headers: Record<string, string> = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }
    if (method !== 'GET') {
        headers['X-CSRF-TOKEN'] = csrfToken();
    }

    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    if (!res.ok) {
        // Laravel devuelve JSON con `errors` para 422 y `message` para 4xx/5xx.
        let detalle = '';
        try { detalle = JSON.stringify(await res.json()); } catch { /* */ }
        throw new Error(`HTTP ${res.status} ${res.statusText} — ${detalle}`);
    }
    return res.json() as Promise<T>;
}

interface OkResp<T = unknown> { ok: true; data?: T; [k: string]: unknown }

export const ChatApi = {
    listarCanales: () => request<OkResp<Canal[]>>('GET', '/chat/canales')
        .then(r => r.data ?? []),

    listarArchivados: () => request<OkResp<Canal[]>>('GET', '/chat/canales/archivados')
        .then(r => r.data ?? []),

    listarMensajes: (canalId: number, since?: number) => {
        const url = since
            ? `/chat/canales/${canalId}/mensajes?since=${since}`
            : `/chat/canales/${canalId}/mensajes`;
        return request<OkResp<Mensaje[]>>('GET', url).then(r => r.data ?? []);
    },

    enviarMensaje: (canalId: number, texto: string) =>
        request<{ ok: true; id: number }>('POST', `/chat/canales/${canalId}/mensajes`, { texto }),

    marcarLeido: (canalId: number) =>
        request<{ ok: true; ultimo_leido_id: number }>('POST', `/chat/canales/${canalId}/marcar-leido`),

    cerrarCanal: (canalId: number) =>
        request<{ ok: true }>('POST', `/chat/canales/${canalId}/cerrar`),

    reabrirCanal: (canalId: number) =>
        request<{ ok: true }>('POST', `/chat/canales/${canalId}/reabrir`),

    eliminarMensaje: (msgId: number) =>
        request<{ ok: true }>('DELETE', `/chat/mensajes/${msgId}`),

    buscarEnCanal: (canalId: number, q: string) =>
        request<OkResp<Mensaje[]>>('GET', `/chat/canales/${canalId}/buscar?q=${encodeURIComponent(q)}`)
            .then(r => r.data ?? []),

    listarUsuarios: () => request<OkResp<UsuarioListado[]>>('GET', '/chat/usuarios')
        .then(r => r.data ?? []),

    abrirDm: (userId: number) =>
        request<{ ok: true; canal_id: number }>('POST', '/chat/dm', { user_id: userId }),

    noLeidos: () => request<{ ok: true; count: number; canales: unknown[] }>('GET', '/chat/no-leidos'),
};
