// ── Notificaciones del navegador (Notification API) ─────────────
// Módulo global window.Notify para el layout V2. Mismo contrato que el inline
// de layouts/app.blade.php (V1); cuando V1 se retire, este queda como único.
// Por defecto activado: pide permiso al primer gesto del usuario y dispara
// notificaciones (delegaciones, urgentes) desde las páginas que lo usen.
window.Notify = (function () {
    const STORAGE_OFF = 'notify_off';   // por si más adelante agregamos toggle
    let permisoPedido = false;

    function activado() {
        return localStorage.getItem(STORAGE_OFF) !== '1';
    }

    async function pedirPermiso() {
        if (!('Notification' in window)) return false;
        if (Notification.permission === 'granted') return true;
        if (Notification.permission === 'denied') return false;
        if (permisoPedido) return false;
        permisoPedido = true;
        try {
            const p = await Notification.requestPermission();
            return p === 'granted';
        } catch { return false; }
    }

    /**
     * Dispara una notificación.
     * @param {object} opts
     * @param {string} opts.titulo
     * @param {string} opts.cuerpo
     * @param {string} [opts.tag]   id estable para reemplazar la notif anterior del mismo tema
     * @param {string} [opts.url]   click → navegar acá
     * @param {boolean} [opts.soloOculto=false]  si true, no notifica cuando la pestaña está visible
     */
    function disparar({ titulo, cuerpo, tag, url, soloOculto = false }) {
        if (!activado()) return;
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        if (soloOculto && !document.hidden) return;

        try {
            const n = new Notification(titulo, {
                body: cuerpo,
                tag: tag || undefined,
                renotify: !!tag,
                silent: true,
            });
            n.onclick = () => {
                window.focus();
                if (url) window.location.href = url;
                n.close();
            };
            // Auto-cerrar a los 8s para no acumular
            setTimeout(() => n.close(), 8000);
        } catch {}
    }

    return { activado, pedirPermiso, disparar };
})();

// Al cargar, pedir permiso si todavía no se decidió. La API exige llamarlo
// desde un user gesture en algunos browsers; si falla en el load, se reintenta
// al primer click.
document.addEventListener('DOMContentLoaded', () => {
    if (window.Notify.activado()) {
        window.Notify.pedirPermiso();
        document.addEventListener('click', () => window.Notify.pedirPermiso(), { once: true });
    }
});
