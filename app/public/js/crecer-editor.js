/**
 * CrecerEditor — editor de texto con formato para los procedimientos.
 *
 * Vanilla, sin dependencias ni build step, igual que el resto del panel.
 * Guarda HTML, que el servidor vuelve a limpiar con App\Services\HtmlSeguro:
 * lo de acá es comodidad para quien escribe, la autoridad es siempre el server.
 *
 * Uso:
 *   const ed = CrecerEditor.crear(divHost, { html: '<p>...</p>', onChange: fn });
 *   ed.getHTML();  ed.setHTML(html);  ed.focus();  ed.destruir();
 */
window.CrecerEditor = (function () {

    // Espejo de HtmlSeguro::PERMITIDOS. Si allá cambia, acá también.
    const TAGS_OK   = ['P','BR','STRONG','EM','U','UL','OL','LI','A','IMG'];
    const RENOMBRAR = { B:'STRONG', I:'EM', DIV:'P', INS:'U',
                        H1:'P', H2:'P', H3:'P', H4:'P', H5:'P', H6:'P' };
    const ELIMINAR  = ['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','FORM','INPUT',
                       'TEXTAREA','SELECT','BUTTON','SVG','MATH','LINK','META','BASE',
                       'NOSCRIPT','TEMPLATE','TITLE','HEAD','AUDIO','VIDEO','SOURCE',
                       'TRACK','CANVAS','APPLET','FRAME','FRAMESET','MARQUEE'];

    const VETADOS = ['javascript:','data:','vbscript:','file:','about:','blob:','jar:','view-source:'];

    /** Espejo de HtmlSeguro::hrefValido. Sirve para avisar antes de guardar. */
    function hrefValido(href) {
        const orig = String(href || '').trim();
        if (!orig) return null;

        // Decodifica entidades y saca caracteres de control, como el server:
        // "jav&#x09;ascript:" lo ejecuta el navegador igual.
        const tmp = document.createElement('textarea');
        tmp.innerHTML = orig;
        const probe = (tmp.value || '').replace(/[\x00-\x20\x7F\xA0]+/g, '').toLowerCase();

        if (VETADOS.some(v => probe.startsWith(v))) return null;
        if (probe.startsWith('//')) return null;

        if (probe.startsWith('http://') || probe.startsWith('https://')) {
            try { return new URL(orig).host ? orig : null; } catch (e) { return null; }
        }
        if (probe.startsWith('mailto:')) return /^mailto:[^@\s]+@[^@\s]+\.[^@\s]+$/.test(probe) ? orig : null;
        if (/^tel:\+?[0-9\-\s()]{5,25}$/.test(probe)) return orig;
        if (/^\/v2\/procedimientos\/\d+$/.test(probe)) return orig;
        if (/^\/procedimientos\/adjunto\/\d+(\/descargar)?$/.test(probe)) return orig;
        if (/^#[a-z0-9_-]{1,50}$/.test(probe)) return orig;

        return null;
    }

    /**
     * Limpia lo que se pega. DOMParser trabaja sobre un documento desacoplado:
     * no ejecuta scripts ni dispara la carga de imágenes. Nunca asignar el HTML
     * pegado a un elemento del DOM vivo.
     */
    function limpiarPegado(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        limpiarNodo(doc.body);
        return doc.body.innerHTML;
    }

    function limpiarNodo(nodo) {
        // Snapshot: quitar nodos mientras se itera la lista viva saltea elementos.
        Array.from(nodo.childNodes).forEach(hijo => {
            if (hijo.nodeType === Node.TEXT_NODE) return;
            if (hijo.nodeType !== Node.ELEMENT_NODE) { hijo.remove(); return; }

            const orig = hijo.tagName;
            const tag  = RENOMBRAR[orig] || orig;

            if (ELIMINAR.includes(tag) || ELIMINAR.includes(orig)) { hijo.remove(); return; }

            if (!TAGS_OK.includes(tag)) { limpiarNodo(hijo); desenvolver(hijo); return; }

            let el = hijo;
            if (tag !== orig) {
                el = document.createElement(tag);
                while (hijo.firstChild) el.appendChild(hijo.firstChild);
                hijo.replaceWith(el);
            }

            // Fuera todo atributo salvo href en <a> y src/alt en <img>
            const attrsOk = el.tagName === 'A' ? ['href'] : (el.tagName === 'IMG' ? ['src','alt'] : []);
            Array.from(el.attributes).forEach(a => {
                if (!attrsOk.includes(a.name.toLowerCase())) el.removeAttribute(a.name);
            });

            if (el.tagName === 'A') {
                const ok = hrefValido(el.getAttribute('href'));
                if (!ok) { limpiarNodo(el); desenvolver(el); return; }
                el.setAttribute('href', ok);
            }

            // Solo adjuntos propios: al pegar desde una web, las imágenes
            // externas se descartan en vez de dejar que el server las borre
            // después y parezca que "se perdieron".
            if (el.tagName === 'IMG') {
                if (!/^\/procedimientos\/adjunto\/\d+$/.test(el.getAttribute('src') || '')) {
                    el.remove();
                    return;
                }
            }

            limpiarNodo(el);
        });
    }

    function desenvolver(el) {
        const padre = el.parentNode;
        if (!padre) return;
        while (el.firstChild) padre.insertBefore(el.firstChild, el);
        padre.removeChild(el);
    }

    // ── CSS, inyectado una sola vez ───────────────────────────
    function inyectarCss() {
        if (document.getElementById('crecer-editor-css')) return;
        const s = document.createElement('style');
        s.id = 'crecer-editor-css';
        s.textContent = `
.ed-wrap { border:1px solid var(--v2-border); border-radius:var(--v2-radius-sm); background:var(--v2-bg-card); }
.ed-wrap:focus-within { border-color:var(--v2-accent); }
.ed-toolbar { display:flex; align-items:center; gap:2px; flex-wrap:wrap; padding:5px 6px;
              border-bottom:1px solid var(--v2-border); background:var(--v2-bg-app);
              border-radius:var(--v2-radius-sm) var(--v2-radius-sm) 0 0; }
.ed-toolbar button { background:transparent; border:1px solid transparent; border-radius:4px;
                     min-width:28px; height:26px; padding:0 6px; cursor:pointer; font-size:12.5px;
                     color:var(--v2-text-2); line-height:1; }
.ed-toolbar button:hover { background:var(--v2-bg-hover); color:var(--v2-text); }
.ed-toolbar button.active { background:var(--v2-accent-bg); color:var(--v2-accent); border-color:var(--v2-accent); }
.ed-sep { width:1px; height:16px; background:var(--v2-border); margin:0 4px; }
.ed-area { min-height:110px; max-height:420px; overflow-y:auto; padding:10px 12px;
           font-size:13.5px; line-height:1.6; color:var(--v2-text); outline:none; }
.ed-area p { margin:0 0 8px; }
.ed-area ul, .ed-area ol { margin:0 0 8px; padding-left:22px; }
.ed-area li { margin-bottom:3px; }
.ed-area a { color:var(--v2-accent); }
.ed-area img { max-width:100%; display:block; margin:8px 0; border-radius:var(--v2-radius-sm);
               border:1px solid var(--v2-border); }
.ed-area:empty::before { content:attr(data-placeholder); color:var(--v2-text-mute); }
.ed-link { display:none; gap:6px; align-items:center; padding:7px 8px; border-top:1px solid var(--v2-border);
           background:var(--v2-bg-app); flex-wrap:wrap; }
.ed-link.abierto { display:flex; }
.ed-link input { flex:1; min-width:160px; font-size:12.5px; padding:5px 8px;
                 border:1px solid var(--v2-border); border-radius:4px;
                 background:var(--v2-bg-card); color:var(--v2-text); }
.ed-link .err { color:var(--v2-urg); font-size:11.5px; width:100%; display:none; }
.ed-link .err.visible { display:block; }
`;
        document.head.appendChild(s);
    }

    const BOTONES = [
        { cmd:'bold',                 txt:'<b>B</b>',  title:'Negrita (Ctrl+B)' },
        { cmd:'italic',               txt:'<i>I</i>',  title:'Cursiva (Ctrl+I)' },
        { cmd:'underline',            txt:'<u>U</u>',  title:'Subrayado (Ctrl+U)' },
        { sep:true },
        { cmd:'insertUnorderedList',  txt:'• Lista',   title:'Lista' },
        { cmd:'insertOrderedList',    txt:'1. Lista',  title:'Lista numerada' },
        { sep:true },
        { accion:'link',              txt:'🔗',        title:'Insertar link' },
        { cmd:'unlink',               txt:'⛓',         title:'Quitar link' },
        { cmd:'removeFormat',         txt:'🧹',        title:'Limpiar formato' },
        { accion:'imagen',            txt:'🖼',        title:'Insertar imagen', requiereSubida:true },
    ];

    function crear(host, opts) {
        opts = opts || {};
        inyectarCss();

        host.innerHTML = '';
        const wrap = document.createElement('div');
        wrap.className = 'ed-wrap';

        // Toolbar
        const toolbar = document.createElement('div');
        toolbar.className = 'ed-toolbar';
        BOTONES.forEach(b => {
            // El botón de imagen solo existe si quien usa el editor sabe subir
            // archivos (necesita un procedimiento ya creado que la sostenga).
            if (b.requiereSubida && !opts.onSubirImagen) return;
            if (b.sep) { const s = document.createElement('span'); s.className = 'ed-sep'; toolbar.appendChild(s); return; }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.innerHTML = b.txt;
            btn.title = b.title;
            if (b.cmd)    btn.dataset.cmd = b.cmd;
            if (b.accion) btn.dataset.accion = b.accion;
            toolbar.appendChild(btn);
        });

        // Área editable
        const area = document.createElement('div');
        area.className = 'ed-area';
        area.contentEditable = 'true';
        area.spellcheck = true;
        area.dataset.placeholder = opts.placeholder || 'Escribí el paso...';
        area.innerHTML = opts.html || '';

        // Panel de link
        const panel = document.createElement('div');
        panel.className = 'ed-link';
        panel.innerHTML =
            '<input type="url" placeholder="https://... o /v2/procedimientos/3">' +
            '<button type="button" class="v2-btn sm primary" data-link="ok">Aplicar</button>' +
            '<button type="button" class="v2-btn sm" data-link="no">Cancelar</button>' +
            '<span class="err">Ese link no está permitido. Solo http(s), mailto, tel o un link interno del panel.</span>';

        wrap.appendChild(toolbar);
        wrap.appendChild(area);
        wrap.appendChild(panel);
        host.appendChild(wrap);

        // ── Config de execCommand ─────────────────────────────
        // styleWithCSS=false es CRÍTICO: sin esto Chrome emite
        // <span style="font-weight:bold"> en vez de <b>, y como el sanitizador
        // del server borra span y style, la negrita desaparecería al guardar
        // SIN ningún mensaje de error. No sacar estas tres líneas.
        try {
            document.execCommand('styleWithCSS', false, false);
            document.execCommand('defaultParagraphSeparator', false, 'p');
            document.execCommand('enableObjectResizing', false, false);
        } catch (e) { /* navegadores viejos: se sigue igual */ }

        let rangoGuardado = null;
        const avisarCambio = () => { if (opts.onChange) opts.onChange(); };

        // ── Toolbar: mousedown, NO click ──────────────────────
        // Con 'click' el botón toma foco en el mousedown, el contenteditable
        // pierde la selección y execCommand no hace nada. Es el bug clásico.
        toolbar.addEventListener('mousedown', ev => {
            const btn = ev.target.closest('button');
            if (!btn) return;
            ev.preventDefault();

            if (btn.dataset.accion === 'link')   { abrirPanelLink(); return; }
            if (btn.dataset.accion === 'imagen') { pedirImagen(); return; }
            if (!btn.dataset.cmd) return;

            document.execCommand(btn.dataset.cmd, false, null);
            area.focus();
            refrescarEstado();
            avisarCambio();
        });

        // ── Panel de link ─────────────────────────────────────
        function abrirPanelLink() {
            const sel = document.getSelection();
            if (sel && sel.rangeCount && area.contains(sel.anchorNode)) {
                rangoGuardado = sel.getRangeAt(0).cloneRange();
            }
            panel.classList.add('abierto');
            panel.querySelector('.err').classList.remove('visible');
            const input = panel.querySelector('input');
            input.value = '';
            input.focus();
        }

        function cerrarPanelLink() {
            panel.classList.remove('abierto');
            area.focus();
        }

        panel.addEventListener('mousedown', ev => {
            // Evita que el panel robe la selección del área al hacer click
            if (ev.target.tagName !== 'INPUT') ev.preventDefault();
        });

        panel.addEventListener('click', ev => {
            const b = ev.target.closest('button');
            if (!b) return;
            if (b.dataset.link === 'no') { cerrarPanelLink(); return; }

            const input = panel.querySelector('input');
            const url   = hrefValido(input.value);

            if (!url) { panel.querySelector('.err').classList.add('visible'); input.focus(); return; }

            if (rangoGuardado) {
                const sel = document.getSelection();
                sel.removeAllRanges();
                sel.addRange(rangoGuardado);
            }
            document.execCommand('createLink', false, url);
            marcarLinksExternos();
            cerrarPanelLink();
            avisarCambio();
        });

        panel.querySelector('input').addEventListener('keydown', ev => {
            if (ev.key === 'Enter')  { ev.preventDefault(); panel.querySelector('[data-link="ok"]').click(); }
            if (ev.key === 'Escape') { ev.preventDefault(); cerrarPanelLink(); }
        });

        function marcarLinksExternos() {
            area.querySelectorAll('a[href]').forEach(a => {
                if (/^https?:\/\//i.test(a.getAttribute('href'))) {
                    a.setAttribute('target', '_blank');
                    a.setAttribute('rel', 'noopener noreferrer nofollow');
                } else {
                    a.removeAttribute('target');
                    a.removeAttribute('rel');
                }
            });
        }

        // ── Imágenes ──────────────────────────────────────────
        function guardarRango() {
            const sel = document.getSelection();
            if (sel && sel.rangeCount && area.contains(sel.anchorNode)) {
                rangoGuardado = sel.getRangeAt(0).cloneRange();
            }
        }

        function restaurarRango() {
            if (!rangoGuardado) { area.focus(); return; }
            const sel = document.getSelection();
            sel.removeAllRanges();
            sel.addRange(rangoGuardado);
        }

        function pedirImagen() {
            // Abrir el selector de archivos mata la selección: hay que guardarla
            // antes y reponerla cuando vuelve la subida.
            guardarRango();

            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/jpeg,image/png,image/gif,image/webp';
            input.onchange = () => { if (input.files && input.files[0]) subirEInsertar(input.files[0]); };
            input.click();
        }

        async function subirEInsertar(file) {
            let r;
            try { r = await opts.onSubirImagen(file); }
            catch (e) { if (window.v2toast) v2toast(e.message || 'No se pudo subir la imagen', 'err'); return; }

            if (!r || !r.url) return;

            restaurarRango();
            area.focus();
            document.execCommand('insertHTML', false,
                '<img src="' + r.url + '" alt="">');
            avisarCambio();
        }

        // ── Pegado ────────────────────────────────────────────
        area.addEventListener('paste', ev => {
            ev.preventDefault();
            const dt = ev.clipboardData;
            if (!dt) return;

            // Captura de pantalla pegada directo: es el caso de uso central
            // para documentar "hacé clic acá" del portal de turnos.
            const img = Array.from(dt.files || []).find(f => f.type.startsWith('image/'));
            if (img && opts.onSubirImagen) {
                guardarRango();
                subirEInsertar(img);
                return;
            }

            const html = dt.getData('text/html');
            if (html) {
                document.execCommand('insertHTML', false, limpiarPegado(html));
            } else {
                document.execCommand('insertText', false, dt.getData('text/plain'));
            }
            avisarCambio();
        });

        // Arrastrar y soltar queda fuera: un camino menos por donde entra
        // markup sin pasar por limpiarPegado().
        area.addEventListener('drop', ev => ev.preventDefault());

        area.addEventListener('input', avisarCambio);

        // ── Estado activo de los botones ──────────────────────
        let rafPendiente = false;
        function refrescarEstado() {
            toolbar.querySelectorAll('button[data-cmd]').forEach(btn => {
                let on = false;
                try { on = document.queryCommandState(btn.dataset.cmd); } catch (e) { on = false; }
                btn.classList.toggle('active', !!on);
            });
        }

        function onSelectionChange() {
            const sel = document.getSelection();
            if (!sel || !sel.anchorNode || !area.contains(sel.anchorNode)) return;
            if (rafPendiente) return;
            rafPendiente = true;
            requestAnimationFrame(() => { rafPendiente = false; refrescarEstado(); });
        }
        document.addEventListener('selectionchange', onSelectionChange);

        // ── API ───────────────────────────────────────────────
        return {
            getHTML() {
                let h = area.innerHTML;
                // El contenteditable deja <br> y &nbsp; sueltos al final
                h = h.replace(/(<br\s*\/?>|\s|&nbsp;)+$/i, '');
                if (!area.textContent.trim()) return '';
                return h;
            },
            setHTML(html) { area.innerHTML = html || ''; },
            focus() { area.focus(); },
            destruir() {
                document.removeEventListener('selectionchange', onSelectionChange);
                host.innerHTML = '';
            },
        };
    }

    return { crear, hrefValido, limpiarPegado };
})();
