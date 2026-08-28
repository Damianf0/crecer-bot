@extends('layouts.v2')
@section('title', 'Procedimientos')

{{-- Base de conocimiento (M3 del brief): procedimientos de atención.
     Etapa 1 = solo lectura. Buscador + filtro por área en el listado, y
     detalle con los pasos numerados. El alta/edición llega en la etapa 3.

     El contenido de cada paso es HTML y se inyecta con innerHTML: viene
     sanitizado del servidor por App\Services\HtmlSeguro al guardarse. Todo
     el resto se escapa con esc(). --}}

@push('styles')
<style>
/* .v2-main es flex column SIN overflow: cada vista nativa V2 pone su propio
   scroll (ver v2/contactos.blade.php). Sin este contenedor, todo lo que pasa
   del alto de la ventana queda cortado y la pantalla no scrollea. */
.pr-scroll { flex:1; overflow-y:auto; min-height:0; }
.pr-wrap { max-width:1100px; margin:0 auto; padding:24px; }
.pr-head { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.pr-head h1 { font-size:20px; font-weight:700; margin:0; flex:1; min-width:180px; }
.pr-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:12px; }
.pr-card { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius);
           padding:14px 16px; cursor:pointer; transition:border-color .12s, transform .12s; }
.pr-card:hover { border-color:var(--v2-accent); transform:translateY(-1px); }
.pr-card h3 { margin:0 0 6px; font-size:15px; font-weight:600; line-height:1.3; }
.pr-card p { margin:0 0 10px; font-size:12.5px; color:var(--v2-text-2); line-height:1.45; }
.pr-card-foot { display:flex; align-items:center; gap:6px; flex-wrap:wrap; font-size:11px; color:var(--v2-text-mute); }
.pr-detalle { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius); padding:24px 28px; }
.pr-det-head { border-bottom:1px solid var(--v2-border); padding-bottom:16px; margin-bottom:20px; }
.pr-det-head h2 { margin:0 0 8px; font-size:22px; font-weight:700; line-height:1.25; }
.pr-det-head .meta { font-size:11.5px; color:var(--v2-text-mute); display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.pr-paso { display:flex; gap:14px; padding:16px 0; border-bottom:1px solid var(--v2-border); }
.pr-paso:last-child { border-bottom:0; }
.pr-paso-n { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:var(--v2-accent-bg); color:var(--v2-accent);
             display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12.5px; }
.pr-paso-body { flex:1; min-width:0; }
.pr-paso-body h4 { margin:2px 0 8px; font-size:14.5px; font-weight:600; }
.pr-cont { font-size:13.5px; line-height:1.6; color:var(--v2-text); }
.pr-cont p { margin:0 0 8px; }
.pr-cont ul, .pr-cont ol { margin:0 0 8px; padding-left:22px; }
.pr-cont li { margin-bottom:3px; }
.pr-cont a { color:var(--v2-accent); }
.pr-wa { margin-top:10px; background:var(--v2-bg-app); border:1px solid var(--v2-border); border-left:3px solid var(--v2-ok);
         border-radius:var(--v2-radius-sm); padding:10px 12px; }
.pr-wa-lbl { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--v2-ok); margin-bottom:5px;
             display:flex; align-items:center; justify-content:space-between; gap:8px; }
.pr-wa-txt { font-size:13px; line-height:1.5; white-space:pre-wrap; color:var(--v2-text-2); }
.pr-loading { text-align:center; padding:40px; color:var(--v2-text-mute); font-size:13px; }

/* Las imágenes dentro del contenido de un paso */
.pr-cont img { max-width:100%; display:block; margin:8px 0; border-radius:var(--v2-radius-sm);
               border:1px solid var(--v2-border); cursor:zoom-in; }

.pr-adjuntos { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
.pr-thumb { width:110px; height:80px; object-fit:cover; border-radius:var(--v2-radius-sm);
            border:1px solid var(--v2-border); cursor:zoom-in; transition:transform .12s; }
.pr-thumb:hover { transform:scale(1.04); border-color:var(--v2-accent); }
.pr-archivo { display:inline-flex; align-items:center; gap:7px; font-size:12.5px; text-decoration:none;
              background:var(--v2-bg-app); border:1px solid var(--v2-border); border-radius:var(--v2-radius-sm);
              padding:7px 11px; color:var(--v2-text); }
.pr-archivo:hover { border-color:var(--v2-accent); }
.pr-archivo .baja { color:var(--v2-text-mute); font-size:11px; }

#pr-zoom { position:fixed; inset:0; background:rgba(0,0,0,.85); display:none;
           align-items:center; justify-content:center; z-index:9999; cursor:zoom-out; }
#pr-zoom img { max-width:92vw; max-height:92vh; border-radius:8px; }

/* ── Editor ── */
.pr-edit { display:grid; grid-template-columns:290px 1fr; gap:18px; align-items:start; }
@media (max-width:1000px) { .pr-edit { grid-template-columns:1fr; } }
.pr-edit-meta { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius);
                padding:16px; position:sticky; top:12px; }
.pr-casos { max-height:210px; overflow-y:auto; border:1px solid var(--v2-border); border-radius:var(--v2-radius-sm); padding:8px; }
.pr-casos label { display:flex; align-items:center; gap:7px; font-size:12.5px; padding:3px 2px; cursor:pointer; }
.pr-casos label:hover { color:var(--v2-accent); }
.pr-edit-paso { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius);
                padding:14px 16px; margin-bottom:12px; }
.pr-edit-paso-head { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.pr-edit-paso-head .n { flex-shrink:0; width:24px; height:24px; border-radius:50%; background:var(--v2-accent-bg);
                        color:var(--v2-accent); display:flex; align-items:center; justify-content:center;
                        font-weight:700; font-size:12px; }
.pr-edit-paso-head input { flex:1; }
.pr-edit-paso details { margin-top:10px; }
.pr-edit-paso summary { font-size:12px; color:var(--v2-text-2); cursor:pointer; padding:4px 0; }
.pr-edit-paso summary:hover { color:var(--v2-accent); }
</style>
@endpush

@section('content')
<div class="pr-scroll">
<div class="pr-wrap">

    {{-- ── Listado ────────────────────────────────────────── --}}
    <div id="vista-lista">
        <div class="pr-head">
            <h1>Procedimientos</h1>
            <input id="q" class="v2-search" type="search" placeholder="Buscar procedimiento..."
                   style="max-width:280px;" oninput="cargarDebounced()">
            <button class="v2-btn primary" id="btn-nuevo" style="display:none;"
                    onclick="nuevoProcedimiento()">+ Nuevo</button>
        </div>

        <div class="v2-vistas" id="chips-area" style="margin-bottom:16px;"></div>

        <div id="lista"><div class="pr-loading">Cargando...</div></div>
    </div>

    {{-- ── Detalle ────────────────────────────────────────── --}}
    <div id="vista-detalle" style="display:none;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
            <button class="v2-btn sm" onclick="volver()">← Volver</button>
            <button class="v2-btn sm" style="margin-left:auto;" onclick="copiarLink()"
                    title="Copiar el link de este procedimiento">🔗 Copiar link</button>
            <button class="v2-btn sm primary" id="btn-editar" style="display:none;"
                    onclick="editarActual()">Editar</button>
        </div>
        <div id="detalle"></div>
    </div>

    {{-- ── Editor (solo permiso:admin) ─────────────────────── --}}
    <div id="vista-editor" style="display:none;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
            <button class="v2-btn sm" onclick="cancelarEdicion()">← Salir</button>
            <span id="ed-sucio" style="font-size:12px;color:var(--v2-warn);display:none;">Cambios sin guardar</span>
            <button class="v2-btn sm danger" style="margin-left:auto;" onclick="eliminarProc()">Eliminar</button>
            <button class="v2-btn sm primary" onclick="guardar()">Guardar cambios</button>
        </div>

        <div class="pr-edit">
            <div class="pr-edit-meta">
                <label class="v2-label">Título</label>
                <input class="v2-field" id="f-titulo" maxlength="160" oninput="marcarSucio()">

                <label class="v2-label" style="margin-top:10px;">Resumen</label>
                <textarea class="v2-field" id="f-resumen" rows="3" maxlength="300"
                          placeholder="Una línea que diga de qué se trata" oninput="marcarSucio()"></textarea>

                <label class="v2-label" style="margin-top:10px;">Área</label>
                <select class="v2-field" id="f-area" onchange="marcarSucio()"></select>

                <label class="v2-label" style="margin-top:10px;">Estado</label>
                <select class="v2-field" id="f-estado" onchange="marcarSucio()">
                    <option value="borrador">Borrador — solo lo ve supervisión</option>
                    <option value="publicado">Publicado — lo ve todo el equipo</option>
                </select>

                <label class="v2-label" style="margin-top:10px;">Casos que cubre</label>
                <div id="f-casos" class="pr-casos"></div>

                <label style="display:flex;align-items:center;gap:7px;margin-top:12px;font-size:12.5px;cursor:pointer;">
                    <input type="checkbox" id="f-revisar" onchange="marcarSucio()">
                    Marcar como revisado hoy
                </label>
                <div id="f-revisado" style="font-size:11.5px;color:var(--v2-text-mute);margin-top:5px;"></div>
            </div>

            <div class="pr-edit-pasos">
                <div id="lista-pasos"></div>
                <button class="v2-btn" style="margin-top:12px;" onclick="agregarPaso()">+ Agregar paso</button>
            </div>
        </div>
    </div>

</div>

{{-- Lightbox de capturas: fuera del contenedor con scroll, va position:fixed --}}
<div id="pr-zoom" onclick="this.style.display='none'"><img src="" alt=""></div>
</div>
@endsection

@push('scripts')
<script src="/js/crecer-editor.js?v={{ filemtime(public_path('js/crecer-editor.js')) }}"></script>
<script>
const { esc, get } = V2;
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// api local en vez de V2.post: manda _token TAMBIÉN en el body. Hay una PC del
// equipo desde la que el header X-CSRF-TOKEN llega vacío (commit d83b8a5).
async function api(method, url, body) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF,
                   'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    };
    if (body || method !== 'GET') opts.body = JSON.stringify(Object.assign({ _token: CSRF }, body || {}));
    const r = await fetch(url, opts);
    const data = await r.json().catch(() => null);
    if (!r.ok) throw new Error((data && (data.error || data.message)) || ('HTTP ' + r.status));
    return data;
}

const state = {
    area: '',
    q: '',
    puedeEditar: false,
    areas: {},
};

let _reqSeq = 0;
let _debounce = null;

function cargarDebounced() {
    clearTimeout(_debounce);
    _debounce = setTimeout(cargar, 250);
}

async function cargar() {
    const seq = ++_reqSeq;
    state.q = document.getElementById('q').value.trim();

    const params = new URLSearchParams();
    if (state.q)    params.set('q', state.q);
    if (state.area) params.set('area', state.area);

    let r;
    try {
        r = await get('/procedimientos/data?' + params.toString());
    } catch (e) {
        if (seq === _reqSeq) document.getElementById('lista').innerHTML =
            '<div class="v2-empty"><span class="ico">⚠️</span>No se pudieron cargar los procedimientos.</div>';
        return;
    }

    // Descartar respuestas que llegaron fuera de orden (mismo guard que contactos)
    if (seq !== _reqSeq) return;

    state.puedeEditar = r.puede_editar;
    document.getElementById('btn-nuevo').style.display = r.puede_editar ? '' : 'none';
    if (!Object.keys(state.areas).length) {
        state.areas = r.areas;
        renderChips();
    }
    renderLista(r.data);
}

function renderChips() {
    const chips = ['<button class="v2-vista' + (state.area === '' ? ' active' : '') +
                   '" onclick="setArea(\'\')">Todas</button>'];
    for (const [k, label] of Object.entries(state.areas)) {
        chips.push('<button class="v2-vista' + (state.area === k ? ' active' : '') +
                   '" onclick="setArea(\'' + esc(k) + '\')">' + esc(label) + '</button>');
    }
    document.getElementById('chips-area').innerHTML = chips.join('');
}

function setArea(a) {
    state.area = a;
    renderChips();
    cargar();
}

function renderLista(items) {
    const cont = document.getElementById('lista');

    if (!items.length) {
        cont.innerHTML = '<div class="v2-empty"><span class="ico">📘</span>' +
            (state.q ? 'Ningún procedimiento coincide con la búsqueda.'
                     : 'Todavía no hay procedimientos cargados.') + '</div>';
        return;
    }

    cont.innerHTML = '<div class="pr-grid">' + items.map(p => {
        const pills = [];
        pills.push('<span class="v2-pill neutral">' + esc(state.areas[p.area] ?? p.area) + '</span>');
        if (p.estado !== 'publicado') pills.push('<span class="v2-pill espera">Borrador</span>');
        // Solo se avisa de la falta de revisión a quien puede hacer algo al respecto
        if (p.sin_revisar && state.puedeEditar) pills.push('<span class="v2-pill urgente">Sin revisar</span>');
        return '<div class="pr-card" onclick="abrir(' + p.id + ')">' +
                 '<h3>' + esc(p.titulo) + '</h3>' +
                 (p.resumen ? '<p>' + esc(p.resumen) + '</p>' : '') +
                 '<div class="pr-card-foot">' + pills.join('') +
                   '<span>' + p.pasos_count + (p.pasos_count === 1 ? ' paso' : ' pasos') + '</span>' +
                 '</div>' +
               '</div>';
    }).join('') + '</div>';
}

let procActual = null;   // id del procedimiento abierto en el detalle

/**
 * Cada procedimiento tiene URL propia (/v2/procedimientos/{id}) aunque la
 * pantalla sea una sola: así se puede linkear desde una tarea, desde otro
 * procedimiento, o pasarle el link a alguien. pushState evita recargar.
 */
function urlDetalle(id) {
    const url = id ? '/v2/procedimientos/' + id : '/v2/procedimientos';
    if (window.location.pathname !== url) history.pushState({ id: id || null }, '', url);
}

// Botones atrás/adelante del navegador. Se lee el id de la URL y no de
// ev.state: la primera entrada del historial es la carga inicial de la página,
// que no tiene state, y ahí el atrás dejaba la URL en /2 mostrando el listado.
window.addEventListener('popstate', () => {
    const m = window.location.pathname.match(/^\/v2\/procedimientos\/(\d+)$/);
    if (m) abrir(parseInt(m[1], 10), true); else volver(true);
});

async function abrir(id, sinUrl) {
    procActual = id;
    if (!sinUrl) urlDetalle(id);
    document.getElementById('vista-lista').style.display    = 'none';
    document.getElementById('vista-detalle').style.display  = '';
    document.getElementById('btn-editar').style.display     = state.puedeEditar ? '' : 'none';
    document.getElementById('detalle').innerHTML = '<div class="pr-loading">Cargando...</div>';

    let r;
    try {
        r = await get('/procedimientos/' + id);
    } catch (e) {
        document.getElementById('detalle').innerHTML =
            '<div class="v2-empty"><span class="ico">⚠️</span>No se pudo abrir el procedimiento.</div>';
        return;
    }

    renderDetalle(r.procedimiento);
}

function renderDetalle(p) {
    const meta = [];
    meta.push('<span class="v2-pill neutral">' + esc(p.area_label) + '</span>');
    // Un procedimiento puede cubrir varios casos del clasificador
    (p.casos || []).forEach(c => meta.push('<span class="v2-pill accent">' + esc(c) + '</span>'));
    if (p.estado !== 'publicado') meta.push('<span class="v2-pill espera">Borrador</span>');
    if (p.actualizado) {
        meta.push('<span>Actualizado ' + esc(p.actualizado) +
                  (p.actualizado_por ? ' por ' + esc(p.actualizado_por) : '') + '</span>');
    }

    // Aviso de revisión vencida: un procedimiento clínico desactualizado que
    // alguien sigue al pie de la letra es peor que no tener nada escrito.
    let aviso = '';
    if (p.sin_revisar) {
        const detalle = p.revisado
            ? 'Última revisión: ' + esc(p.revisado) + ' (hace ' + p.meses_sin_revisar + ' meses).'
            : 'Todavía nadie lo marcó como revisado.';
        aviso = '<div style="background:var(--v2-warn-bg);border:1px solid var(--v2-warn);border-radius:var(--v2-radius-sm);' +
                'padding:9px 12px;margin-bottom:16px;font-size:12.5px;color:var(--v2-text);">' +
                '⚠️ <strong>Conviene revisar este procedimiento.</strong> ' + detalle + '</div>';
    }

    let html = '<div class="pr-detalle">' + aviso +
        '<div class="pr-det-head">' +
            '<h2>' + esc(p.titulo) + '</h2>' +
            (p.resumen ? '<p style="margin:0 0 10px;font-size:13.5px;color:var(--v2-text-2);line-height:1.5;">' + esc(p.resumen) + '</p>' : '') +
            '<div class="meta">' + meta.join('') + '</div>' +
        '</div>';

    if (!p.pasos.length) {
        html += '<div class="v2-empty"><span class="ico">📄</span>Este procedimiento todavía no tiene pasos.</div>';
    } else {
        html += p.pasos.map((paso, i) => {
            let body = '<div class="pr-paso"><div class="pr-paso-n">' + (i + 1) + '</div><div class="pr-paso-body">';
            if (paso.titulo) body += '<h4>' + esc(paso.titulo) + '</h4>';
            // contenido: HTML sanitizado en el servidor, por eso va sin esc()
            if (paso.contenido) body += '<div class="pr-cont">' + paso.contenido + '</div>';
            if (paso.respuesta_wa) {
                body += '<div class="pr-wa">' +
                          '<div class="pr-wa-lbl"><span>Respuesta para enviar</span>' +
                            '<button class="v2-btn sm" onclick="copiarWa(this)">Copiar</button></div>' +
                          '<div class="pr-wa-txt">' + esc(paso.respuesta_wa) + '</div>' +
                        '</div>';
            }
            if (paso.adjuntos && paso.adjuntos.length) body += renderAdjuntos(paso.adjuntos);
            return body + '</div></div>';
        }).join('');
    }

    document.getElementById('detalle').innerHTML = html + '</div>';
}

// El panel se sirve por http://192.168.1.115, que NO es secure context:
// navigator.clipboard es undefined ahí. Por eso el camino principal es el
// textarea + execCommand('copy'), que funciona sobre HTTP plano.
/** Adjuntos en la vista de lectura: miniaturas para imágenes, fila para PDFs. */
function renderAdjuntos(adjuntos) {
    const imgs  = adjuntos.filter(a => a.imagen);
    const otros = adjuntos.filter(a => !a.imagen);
    let html = '<div class="pr-adjuntos">';

    imgs.forEach(a => {
        html += '<img src="' + a.url + '" alt="' + esc(a.nombre) + '" class="pr-thumb" ' +
                'onclick="ampliar(this.src)" title="' + esc(a.nombre) + '">';
    });

    otros.forEach(a => {
        html += '<a class="pr-archivo" href="' + a.url + '" target="_blank">' +
                  '📄 <span>' + esc(a.nombre) + '</span>' +
                  '<span class="baja">descargar</span>' +
                '</a>';
    });

    return html + '</div>';
}

function ampliar(src) {
    const zoom = document.getElementById('pr-zoom');
    zoom.querySelector('img').src = src;
    zoom.style.display = 'flex';
}

function copiarWa(btn) {
    const txt = btn.closest('.pr-wa').querySelector('.pr-wa-txt').textContent;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(txt)
            .then(() => v2toast('Respuesta copiada'))
            .catch(() => copiarFallback(txt));
        return;
    }
    copiarFallback(txt);
}

function copiarLink() {
    if (!procActual) return;
    copiarFallback(window.location.origin + '/v2/procedimientos/' + procActual);
}

function copiarFallback(txt) {
    const ta = document.createElement('textarea');
    ta.value = txt;
    ta.setAttribute('readonly', '');
    ta.style.cssText = 'position:fixed;top:-1000px;opacity:0;';
    document.body.appendChild(ta);
    ta.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(ta);
    v2toast(ok ? 'Respuesta copiada' : 'No se pudo copiar', ok ? 'ok' : 'err');
}

function volver(sinUrl) {
    procActual = null;
    if (!sinUrl) urlDetalle(null);
    document.getElementById('vista-detalle').style.display = 'none';
    document.getElementById('vista-editor').style.display  = 'none';
    document.getElementById('vista-lista').style.display   = '';
    cargar();
}

// ══ Editor ═══════════════════════════════════════════════════

let edit = null;   // { id, pasos: [{id, titulo, ed, respuesta_wa}] }
let sucio = false;

function marcarSucio() {
    sucio = true;
    document.getElementById('ed-sucio').style.display = '';
}

async function nuevoProcedimiento() {
    try {
        const r = await api('POST', '/procedimientos', { titulo: 'Procedimiento sin título' });
        abrirEditor(r.id);
    } catch (e) { v2toast(e.message, 'err'); }
}

function editarActual() {
    if (procActual) abrirEditor(procActual);
}

async function abrirEditor(id) {
    let r;
    try { r = await get('/procedimientos/' + id); }
    catch (e) { v2toast('No se pudo abrir el editor', 'err'); return; }

    const p = r.procedimiento;

    document.getElementById('vista-lista').style.display    = 'none';
    document.getElementById('vista-detalle').style.display  = 'none';
    document.getElementById('vista-editor').style.display   = '';

    edit  = { id: p.id, pasos: [] };
    sucio = false;
    document.getElementById('ed-sucio').style.display = 'none';

    document.getElementById('f-titulo').value  = p.titulo;
    document.getElementById('f-resumen').value = p.resumen || '';
    document.getElementById('f-estado').value  = p.estado;
    document.getElementById('f-revisar').checked = false;
    document.getElementById('f-revisado').textContent =
        p.revisado ? 'Última revisión: ' + p.revisado : 'Nunca se marcó como revisado.';

    // Área
    const selArea = document.getElementById('f-area');
    selArea.innerHTML = Object.entries(r.areas)
        .map(([k, v]) => '<option value="' + esc(k) + '">' + esc(v) + '</option>').join('');
    selArea.value = p.area;

    // Casos del clasificador
    document.getElementById('f-casos').innerHTML = Object.entries(r.codigos_bot).map(([k, v]) =>
        '<label><input type="checkbox" value="' + esc(k) + '"' +
        (p.codigos.includes(k) ? ' checked' : '') + ' onchange="marcarSucio()"> ' + esc(v) + '</label>'
    ).join('');

    // Pasos
    document.getElementById('lista-pasos').innerHTML = '';
    if (!p.pasos.length) {
        agregarPaso();
    } else {
        p.pasos.forEach(paso => pintarPaso(paso));
    }
}

function pintarPaso(paso) {
    const idx  = edit.pasos.length;
    const cont = document.getElementById('lista-pasos');

    const card = document.createElement('div');
    card.className = 'pr-edit-paso';
    card.innerHTML =
        '<div class="pr-edit-paso-head">' +
            '<span class="n"></span>' +
            '<input class="v2-field" placeholder="Título del paso (opcional)" maxlength="160">' +
            '<button class="v2-btn sm" title="Subir">↑</button>' +
            '<button class="v2-btn sm" title="Bajar">↓</button>' +
            '<button class="v2-btn sm danger" title="Borrar paso">🗑</button>' +
        '</div>' +
        '<div class="pr-ed-host"></div>' +
        '<details><summary>Respuesta lista para enviar por WhatsApp</summary>' +
            '<textarea class="v2-field" rows="4" maxlength="4000" style="margin-top:6px;"' +
            ' placeholder="Texto que la secretaria copia y manda al paciente"></textarea>' +
        '</details>' +
        '<details class="pr-adj"><summary>Archivos adjuntos</summary>' +
            '<div class="pr-adj-lista"></div>' +
            '<input type="file" class="v2-field" style="margin-top:6px;font-size:12px;"' +
            ' accept="image/jpeg,image/png,image/gif,image/webp,application/pdf">' +
            '<div style="font-size:11px;color:var(--v2-text-mute);margin-top:4px;">' +
            'Imágenes o PDF, hasta 8 MB. Las imágenes también se pueden pegar directo en el texto.</div>' +
        '</details>';

    cont.appendChild(card);

    const inputTitulo = card.querySelector('.pr-edit-paso-head input');
    const textareaWa  = card.querySelector('textarea');
    inputTitulo.value = paso.titulo || '';
    textareaWa.value  = paso.respuesta_wa || '';
    inputTitulo.addEventListener('input', marcarSucio);
    textareaWa.addEventListener('input', marcarSucio);

    const ref = { id: paso.id || null, card, inputTitulo, textareaWa, ed: null,
                  adjuntos: (paso.adjuntos || []).slice() };

    const ed = CrecerEditor.crear(card.querySelector('.pr-ed-host'), {
        html: paso.contenido || '',
        onChange: marcarSucio,
        // Subir una imagen necesita que el paso ya exista en la base para
        // colgarla de él. Si el paso es nuevo, se guarda primero.
        onSubirImagen: async (file) => {
            if (!ref.id) {
                v2toast('Guardá el procedimiento antes de insertar imágenes', 'err');
                return null;
            }
            return await subirAdjunto(file, ref);
        },
    });
    ref.ed = ed;

    edit.pasos.push(ref);
    pintarAdjuntos(ref);

    card.querySelector('.pr-adj input[type=file]').addEventListener('change', async ev => {
        const file = ev.target.files && ev.target.files[0];
        if (!file) return;
        ev.target.value = '';
        if (!ref.id) { v2toast('Guardá el procedimiento antes de adjuntar archivos', 'err'); return; }
        const adj = await subirAdjunto(file, ref);
        if (adj) { ref.adjuntos.push(adj); pintarAdjuntos(ref); }
    });

    const [btnUp, btnDown, btnDel] = card.querySelectorAll('.pr-edit-paso-head button');
    btnUp.onclick   = () => moverPaso(ref, -1);
    btnDown.onclick = () => moverPaso(ref, 1);
    btnDel.onclick  = () => borrarPaso(ref);

    renumerar();
}

function agregarPaso() {
    pintarPaso({});
    marcarSucio();
}

// ── Adjuntos ─────────────────────────────────────────────────

/** Sube un archivo y lo cuelga del paso. Devuelve el adjunto o null. */
async function subirAdjunto(file, ref) {
    const fd = new FormData();
    fd.append('archivo', file);
    fd.append('paso_id', ref.id);
    fd.append('_token', CSRF);   // también en el body, ver el comentario de api()

    let r;
    try {
        const resp = await fetch('/procedimientos/' + edit.id + '/adjuntos', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd,
        });
        r = await resp.json().catch(() => null);
        if (!resp.ok) throw new Error((r && (r.error || r.message)) || ('HTTP ' + resp.status));
    } catch (e) {
        v2toast(e.message || 'No se pudo subir el archivo', 'err');
        return null;
    }

    v2toast('Archivo subido');
    return r.adjunto;
}

function pintarAdjuntos(ref) {
    const cont = ref.card.querySelector('.pr-adj-lista');
    if (!ref.adjuntos.length) {
        cont.innerHTML = '<div style="font-size:12px;color:var(--v2-text-mute);">Sin archivos.</div>';
        return;
    }
    cont.innerHTML = ref.adjuntos.map(a =>
        '<div style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:12.5px;">' +
            '<span>' + (a.imagen ? '🖼' : '📄') + '</span>' +
            '<a href="' + a.url + '" target="_blank" style="flex:1;color:var(--v2-accent);">' + esc(a.nombre) + '</a>' +
            '<button class="v2-btn sm danger" onclick="quitarAdjunto(' + a.id + ')">Quitar</button>' +
        '</div>'
    ).join('');
}

async function quitarAdjunto(id) {
    try { await api('DELETE', '/procedimientos/adjunto/' + id); }
    catch (e) { v2toast(e.message, 'err'); return; }

    edit.pasos.forEach(p => {
        const antes = p.adjuntos.length;
        p.adjuntos = p.adjuntos.filter(a => a.id !== id);
        if (p.adjuntos.length !== antes) pintarAdjuntos(p);
    });
    v2toast('Archivo eliminado');
}

function borrarPaso(ref) {
    if (edit.pasos.length === 1) { v2toast('Un procedimiento necesita al menos un paso', 'err'); return; }
    ref.ed.destruir();
    ref.card.remove();
    edit.pasos = edit.pasos.filter(p => p !== ref);
    renumerar();
    marcarSucio();
}

// Reordenar con botones y no con drag & drop: arrastrar tarjetas que contienen
// un contenteditable pelea con la selección de texto.
function moverPaso(ref, delta) {
    const i = edit.pasos.indexOf(ref);
    const j = i + delta;
    if (j < 0 || j >= edit.pasos.length) return;

    const cont  = document.getElementById('lista-pasos');
    const otro  = edit.pasos[j];
    edit.pasos[i] = otro;
    edit.pasos[j] = ref;

    // Se reinsertan las tarjetas en el orden nuevo. Mover el nodo conserva el
    // editor vivo con su contenido: no hay que recrearlo.
    cont.innerHTML = '';
    edit.pasos.forEach(p => cont.appendChild(p.card));

    renumerar();
    marcarSucio();
}

function renumerar() {
    edit.pasos.forEach((p, i) => { p.card.querySelector('.n').textContent = i + 1; });
}

async function guardar() {
    const payload = {
        titulo:  document.getElementById('f-titulo').value.trim(),
        resumen: document.getElementById('f-resumen').value.trim() || null,
        area:    document.getElementById('f-area').value,
        estado:  document.getElementById('f-estado').value,
        revisar: document.getElementById('f-revisar').checked,
        codigos: Array.from(document.querySelectorAll('#f-casos input:checked')).map(c => c.value),
        pasos:   edit.pasos.map(p => ({
            id:           p.id,
            titulo:       p.inputTitulo.value.trim() || null,
            contenido:    p.ed.getHTML(),
            respuesta_wa: p.textareaWa.value.trim() || null,
        })),
    };

    if (!payload.titulo) { v2toast('Falta el título', 'err'); return; }

    let r;
    try { r = await api('POST', '/procedimientos/' + edit.id, payload); }
    catch (e) { v2toast(e.message, 'err'); return; }

    // El server devuelve el HTML YA sanitizado y se recarga en el editor. Sin
    // esto, lo que la persona ve queda divergido de lo guardado (pegó algo con
    // formato raro, se guardó limpio, pero en pantalla sigue viéndose el
    // original hasta recargar) y lo reporta como "se perdió lo que escribí".
    r.procedimiento.pasos.forEach((paso, i) => {
        if (edit.pasos[i]) {
            edit.pasos[i].id = paso.id;
            edit.pasos[i].ed.setHTML(paso.contenido || '');
        }
    });

    sucio = false;
    document.getElementById('ed-sucio').style.display = 'none';
    document.getElementById('f-revisar').checked = false;
    if (r.procedimiento.revisado) {
        document.getElementById('f-revisado').textContent = 'Última revisión: ' + r.procedimiento.revisado;
    }
    v2toast('Procedimiento guardado');
}

async function eliminarProc() {
    if (!confirm('¿Eliminar este procedimiento? Se puede recuperar desde la base de datos.')) return;
    try {
        await api('DELETE', '/procedimientos/' + edit.id);
        v2toast('Procedimiento eliminado');
        salirDelEditor();
        volver();
    } catch (e) { v2toast(e.message, 'err'); }
}

function cancelarEdicion() {
    if (sucio && !confirm('Hay cambios sin guardar. ¿Salir igual?')) return;
    salirDelEditor();
    volver();
}

function salirDelEditor() {
    if (edit) edit.pasos.forEach(p => p.ed.destruir());
    edit  = null;
    sucio = false;
}

window.addEventListener('beforeunload', ev => {
    if (sucio) { ev.preventDefault(); ev.returnValue = ''; }
});

// ── Arranque ─────────────────────────────────────────────────
// Si la URL trae un id (/v2/procedimientos/3) se abre ese detalle directo. El
// listado se carga igual, porque de ahí salen state.areas y state.puedeEditar.
const PROC_INICIAL = {{ $procId ?? 'null' }};

(async () => {
    await cargar();
    if (PROC_INICIAL) abrir(PROC_INICIAL, true);
})();
</script>
@endpush
