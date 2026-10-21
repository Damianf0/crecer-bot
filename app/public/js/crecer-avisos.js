// ── Avisos del panel (06/10) ────────────────────────────────────
// Cartel + sonido + número en el título de la pestaña cuando a la persona le
// delegan una conversación, le asignan una tarea, le escribe el chat interno,
// escribe un favorito o entra una urgente. Funciona en cualquier pantalla.
//
// Notificación de Windows (15/10): si el navegador está minimizado o tapado,
// el aviso sale además como notificación del sistema. Chrome solo las permite
// en sitios seguros y el panel va por http://192.168.1.115, así que en cada PC
// hay que pasar una vez /chrome-panel-seguro.reg (se baja desde "Mis avisos"). Sin
// eso, todo sigue como antes: cartel + sonido + título.
//
// Chat interno y favoritos son importantes: el cartel y la notificación de
// Windows quedan hasta que la persona hace clic (pedido de Damián, 15/10).
//
// De dónde salen: /bot-pulso trae lo que hoy está "en" la persona (layout V2,
// cada 15 s y al instante con cada cambio de una cola); acá se compara contra
// lo que ya se conocía y se avisa solo lo nuevo. El chat interno avisa con el
// evento de documento `crecer:aviso` (resources/js/chat/state.tsx).
window.Avisos = (function () {
    const CLAVE_CONOCIDO = 'avisos.conocido.v1';   // sessionStorage: por pestaña
    const PREFIJO_DEDUP  = 'avisos.dedup.';         // localStorage: entre pestañas
    let prefs = { chat: true, conv_delegada: true, tarea_asignada: true, favorito: true, urgente: true, sonido: true };
    const IMPORTANTES = ['chat', 'favorito'];
    let pendientesTitulo = 0;
    const tituloBase = document.title;

    // ── Lo que ya se conocía (sobrevive a navegar entre pantallas) ──
    function leerConocido() {
        try { return JSON.parse(sessionStorage.getItem(CLAVE_CONOCIDO) || 'null'); } catch { return null; }
    }
    function guardarConocido(c) {
        try { sessionStorage.setItem(CLAVE_CONOCIDO, JSON.stringify(c)); } catch {}
    }

    // Varias pestañas abiertas reciben el mismo pulso: suena una sola.
    function primeraVez(clave) {
        try {
            const k = PREFIJO_DEDUP + clave, ahora = Date.now();
            const antes = parseInt(localStorage.getItem(k) || '0', 10);
            if (ahora - antes < 60000) return false;
            localStorage.setItem(k, String(ahora));
            return true;
        } catch { return true; }
    }

    // ── Sonido: tono corto con WebAudio (sin archivos) ──
    let ctx = null;
    function audio() {
        try { ctx ||= new (window.AudioContext || window.webkitAudioContext)(); } catch { ctx = null; }
        return ctx;
    }
    // Chrome no deja sonar hasta que la persona tocó algo en la página.
    // El permiso de notificaciones de Windows también se pide con un gesto.
    document.addEventListener('pointerdown', () => {
        const a = audio(); if (a && a.state === 'suspended') a.resume().catch(() => {});
        if (sistemaDisponible() && Notification.permission === 'default') Notification.requestPermission().catch(() => {});
    }, { capture: true });
    function sonar() {
        const a = audio(); if (!a) return;
        try {
            const o = a.createOscillator(), g = a.createGain();
            o.frequency.value = 880; g.gain.value = 0.0001;
            o.connect(g); g.connect(a.destination); o.start();
            g.gain.exponentialRampToValueAtTime(0.18, a.currentTime + 0.04);
            g.gain.exponentialRampToValueAtTime(0.0001, a.currentTime + 0.45);
            o.stop(a.currentTime + 0.5);
        } catch {}
    }

    // ── Cartel: pila abajo a la derecha, clic para ir ──
    function pila() {
        let p = document.getElementById('v2-avisos');
        if (!p) {
            p = document.createElement('div');
            p.id = 'v2-avisos';
            p.setAttribute('aria-live', 'polite');
            p.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:3000;display:flex;flex-direction:column;gap:8px;max-width:min(280px,calc(100vw - 36px));';
            document.body.appendChild(p);
        }
        return p;
    }
    /** fijo: queda hasta que la persona lo toca (importantes); si no, se va a los 9 s. */
    function cartel(titulo, texto, url, fijo = false) {
        const el = document.createElement(url ? 'a' : 'div');
        if (url) el.href = url;
        el.style.cssText = 'position:relative;display:block;text-decoration:none;background:var(--v2-bg-card);color:var(--v2-text);border:1px solid var(--v2-border);' +
            'border-left:3px solid var(--v2-accent);border-radius:var(--v2-radius,8px);padding:10px 12px;box-shadow:0 6px 20px rgba(0,0,0,.18);' +
            'font-size:13px;line-height:1.4;cursor:pointer;' + (fijo ? 'padding-right:30px;' : '');
        const t = document.createElement('div'); t.style.cssText = 'font-weight:650;margin-bottom:2px;'; t.textContent = titulo;
        el.appendChild(t);
        if (texto) { const d = document.createElement('div'); d.style.color = 'var(--v2-text-2)'; d.textContent = texto; el.appendChild(d); }
        if (fijo) {
            const x = document.createElement('span');
            x.textContent = '×'; x.title = 'Cerrar';
            x.style.cssText = 'position:absolute;top:4px;right:8px;font-size:17px;color:var(--v2-text-2);';
            x.addEventListener('click', (e) => e.preventDefault());   // cerrar sin ir a la conversación
            el.appendChild(x);
        }
        el.addEventListener('click', () => el.remove());
        pila().appendChild(el);
        if (!fijo) setTimeout(() => el.remove(), 9000);
    }

    // ── Notificación de Windows (solo si el navegador no está a la vista) ──
    function sistemaDisponible() {
        return 'Notification' in window && window.isSecureContext;
    }
    function notificacionSistema(titulo, texto, url, clave, fijo) {
        if (!sistemaDisponible() || Notification.permission !== 'granted') return;
        if (!document.hidden && document.hasFocus()) return;   // la está mirando: alcanza el cartel
        try {
            const n = new Notification(titulo, {
                body: texto || '', tag: clave || undefined, icon: '/favicon.ico',
                requireInteraction: fijo, silent: true,   // el sonido ya lo pone sonar()
            });
            n.onclick = () => { window.focus(); if (url) window.location.href = url; n.close(); };
        } catch {}
    }

    // ── Título de la pestaña con el número de avisos sin ver ──
    function marcarTitulo() {
        if (!document.hidden) return;
        pendientesTitulo++;
        document.title = `(${pendientesTitulo}) ${tituloBase}`;
    }
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) { pendientesTitulo = 0; document.title = tituloBase; }
    });

    /** Un aviso: respeta lo que la persona tiene prendido. */
    function aviso({ tipo, clave, titulo, texto, url }) {
        if (prefs[tipo] === false) return;
        if (clave && !primeraVez(clave)) return;
        const fijo = IMPORTANTES.includes(tipo);
        cartel(titulo, texto, url, fijo);
        notificacionSistema(titulo, texto, url, clave, fijo);
        if (prefs.sonido !== false) sonar();
        marcarTitulo();
    }

    const abierta = (id) => window.V2Conv && window.V2Conv.panelId === id;
    const urlConv = (c) => `/v2/atencion/${c.area}?conv=${c.id}`;

    /** Llamado por el pulso del layout con `avisos` de /bot-pulso. */
    function pulso(d) {
        if (!d) return;
        if (d.prefs) prefs = Object.assign(prefs, d.prefs);
        const ahora = {
            convs:  (d.mis_convs || []).map(c => c.id),
            tareas: (d.mis_tareas || []).map(t => t.id),
            favs:   Object.fromEntries((d.favoritos || []).map(c => [c.id, c.no_leidos || 0])),
            urg:    (d.urgentes || []).map(c => c.id),
        };
        const antes = leerConocido();
        guardarConocido(ahora);
        if (!antes) return;   // primera vez de esta pestaña: solo registra, no avisa

        (d.mis_convs || []).forEach(c => {
            if (antes.convs.includes(c.id) || abierta(c.id)) return;
            aviso({ tipo: 'conv_delegada', clave: `deleg-${c.id}`, titulo: 'Te delegaron una conversación',
                    texto: `${c.contacto}${c.resumen ? ': ' + c.resumen : ''}`, url: urlConv(c) });
        });
        (d.mis_tareas || []).forEach(t => {
            if (antes.tareas.includes(t.id) || !t.de) return;   // sin "de": me la asigné yo
            aviso({ tipo: 'tarea_asignada', clave: `tarea-${t.id}`, titulo: `${t.de} te asignó una tarea`, texto: t.titulo, url: '/v2/centro-tareas' });
        });
        (d.favoritos || []).forEach(c => {
            if ((c.no_leidos || 0) <= (antes.favs[c.id] || 0) || abierta(c.id)) return;
            aviso({ tipo: 'favorito', clave: `fav-${c.id}-${c.no_leidos}`, titulo: `★ Escribió ${c.contacto}`, texto: c.resumen, url: urlConv(c) });
        });
        (d.urgentes || []).forEach(c => {
            if (antes.urg.includes(c.id)) return;
            aviso({ tipo: 'urgente', clave: `urg-${c.id}`, titulo: 'Entró una consulta urgente', texto: `${c.contacto}${c.resumen ? ': ' + c.resumen : ''}`, url: urlConv(c) });
        });
    }

    // Avisos que no vienen del pulso (chat interno).
    document.addEventListener('crecer:aviso', (ev) => { if (ev.detail) aviso(ev.detail); });

    function estadoSistema() {
        if (!('Notification' in window)) return 'Notificaciones de Windows: este navegador no las tiene.';
        if (!window.isSecureContext) return 'Notificaciones de Windows: <b>no habilitadas en esta PC</b> (falta pasarle el archivo de configuración de Chrome). Los avisos se ven solo con el panel a la vista. ' +
            '<a href="/chrome-panel-seguro.reg" download style="color:var(--v2-accent);font-weight:600;">Descargar la configuración</a>: abrirla, aceptar (pide administrador) y reiniciar Chrome.';
        if (Notification.permission === 'denied') return 'Notificaciones de Windows: <b>bloqueadas</b>. Habilitalas desde el candado de la barra de direcciones → Notificaciones → Permitir.';
        if (Notification.permission === 'default') return 'Notificaciones de Windows: tocá cualquier lugar del panel y aceptá el permiso que pide Chrome.';
        return 'Notificaciones de Windows: <b>activas</b>. Con el navegador minimizado, los avisos salen abajo a la derecha de la pantalla. "Probar aviso" manda una a los 5 s.';
    }

    // ── "Mis avisos" ──
    async function abrirPreferencias() {
        let r;
        try {
            r = await fetch('/mis-avisos', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(x => x.json());
        } catch { window.v2toast?.('No se pudieron cargar tus avisos', 'err'); return; }
        document.getElementById('v2-mis-avisos')?.remove();
        const fondo = document.createElement('div');
        fondo.id = 'v2-mis-avisos';
        fondo.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:3100;display:flex;align-items:center;justify-content:center;padding:16px;';
        const caja = document.createElement('form');
        caja.style.cssText = 'background:var(--v2-bg-card);color:var(--v2-text);border:1px solid var(--v2-border);border-radius:var(--v2-radius,10px);padding:20px 22px;width:min(420px,100%);display:grid;gap:10px;';
        caja.innerHTML = '<div style="font-weight:650;font-size:15px;">Mis avisos</div>' +
            '<div style="font-size:12.5px;color:var(--v2-text-2);line-height:1.45;">Aparecen abajo a la derecha en cualquier pantalla del panel. Si estás en otra pestaña, el título muestra cuántos llegaron. Los del chat interno y los favoritos quedan hasta que los tocás.</div>' +
            `<div style="font-size:12.5px;line-height:1.45;padding:7px 9px;border-radius:6px;background:var(--v2-bg-soft,rgba(0,0,0,.04));">${estadoSistema()}</div>`;
        Object.entries(r.etiquetas || {}).forEach(([k, l]) => {
            const lab = document.createElement('label');
            lab.style.cssText = 'display:flex;align-items:center;gap:9px;font-size:13.5px;cursor:pointer;' + (k === 'sonido' ? 'border-top:1px solid var(--v2-border);padding-top:10px;' : '');
            const cb = document.createElement('input');
            cb.type = 'checkbox'; cb.id = 'aviso-' + k; cb.name = k; cb.checked = r.prefs?.[k] !== false;
            lab.appendChild(cb); lab.appendChild(document.createTextNode(l));
            caja.appendChild(lab);
        });
        const pie = document.createElement('div');
        pie.style.cssText = 'display:flex;gap:8px;justify-content:flex-end;margin-top:6px;flex-wrap:wrap;';
        pie.innerHTML = '<button type="button" class="v2-btn" data-a="probar" style="margin-right:auto;">Probar aviso</button>' +
            '<button type="button" class="v2-btn" data-a="cerrar">Cancelar</button><button type="submit" class="v2-btn primary">Guardar</button>';
        caja.appendChild(pie);
        fondo.appendChild(caja);
        document.body.appendChild(fondo);

        const leer = () => Object.fromEntries(Array.from(caja.querySelectorAll('input[type=checkbox]')).map(c => [c.name, c.checked]));
        fondo.addEventListener('click', (e) => {
            if (e.target === fondo || e.target.dataset.a === 'cerrar') fondo.remove();
            if (e.target.dataset.a === 'probar') {
                const p = leer();
                cartel('Así se ve un aviso', 'Los avisos reales llevan a la conversación o a la tarea al hacer clic.', null);
                if (p.sonido) sonar();
                // La de Windows sale a los 5 s, con tiempo de minimizar el navegador.
                if (sistemaDisponible() && Notification.permission === 'granted') {
                    setTimeout(() => {
                        try {
                            const n = new Notification('Así se ve un aviso del panel', { body: 'Clic para volver al panel.', icon: '/favicon.ico', silent: true });
                            n.onclick = () => { window.focus(); n.close(); };
                        } catch {}
                    }, 5000);
                }
            }
        });
        caja.addEventListener('submit', async (e) => {
            e.preventDefault();
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            try {
                const res = await fetch('/mis-avisos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                    // _token también en el body: hay una PC donde el header llega vacío (commit d83b8a5).
                    body: JSON.stringify(Object.assign({ _token: csrf }, leer())),
                });
                if (!res.ok) throw new Error();
                prefs = Object.assign(prefs, (await res.json()).prefs || {});
                fondo.remove();
                window.v2toast?.('Avisos guardados');
            } catch { window.v2toast?.('No se pudieron guardar los avisos', 'err'); }
        });
    }

    return { pulso, aviso, abrirPreferencias };
})();
