@extends('layouts.app')
@section('title', 'Centro de tareas')

@push('styles')
<style>
.ct-root   { height: calc(100vh - 100px); display: flex; flex-direction: column; }
.ct-header { display: flex; align-items: center; gap: 12px; flex-shrink: 0; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.ct-title  { font-size: 17px; font-weight: 700; }

.ct-subheader { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 0; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.ct-filters   { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
.ct-filter    { font-size: 11px; padding: 3px 10px; border-radius: 12px; border: 1px solid var(--border); background: transparent; color: var(--muted); cursor: pointer; }
.ct-filter.active { border-color: var(--accent); background: rgba(192,39,58,.1); color: var(--accent); }
.ct-filter.vencidas.active { border-color: var(--error); background: color-mix(in srgb, var(--error) 12%, transparent); color: var(--error); }
.ct-sep { width: 1px; background: var(--border); align-self: stretch; margin: 0 4px; }
.btn-new   { padding: 5px 14px; background: var(--accent); color: #fff; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 600; margin-left: auto; }

.ct-body { display: flex; flex: 1; min-height: 0; overflow: hidden; padding-top: 12px; gap: 16px; }
.ct-list { width: 340px; flex-shrink: 0; overflow-y: auto; padding-right: 6px; }
.ct-empty { text-align: center; padding: 40px 0; color: var(--muted); font-size: 13px; }

/* ── Sección encabezados ── */
.ct-section-head { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; padding: 8px 4px 6px; display: flex; align-items: center; gap: 6px; }
.ct-section-head .cnt { background: var(--info); color: #fff; border-radius: 10px; padding: 0 7px; font-size: 10px; font-weight: 700; }
.ct-section-head.deriv .cnt { background: var(--accent); }

/* ── Cards de tarea ── */
.tk-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-left: 3px solid var(--border);
    border-radius: 8px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: box-shadow .12s;
}
.tk-card:hover    { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.tk-card.selected { border-left-color: var(--info); background: rgba(88,166,255,.04); }
.tk-card.completada .tk-titulo { text-decoration: line-through; opacity: .5; }
.tk-card.prio-alta   { border-left-color: var(--accent); }
.tk-card.prio-normal { border-left-color: var(--info); }
.tk-card.prio-baja   { border-left-color: var(--border); }
.tk-card.vencida { background: color-mix(in srgb, var(--error) 5%, var(--card)); }
.tk-card-head { padding: 6px 12px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 6px; font-size: 11px; }
.tk-card-body { padding: 9px 12px; }
.tk-titulo    { font-weight: 600; font-size: 13px; margin-bottom: 3px; }
.tk-desc-prev { font-size: 12px; color: var(--muted); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; }
.tk-meta-line { font-size: 11px; color: var(--muted); margin-top: 4px; }
.tk-time      { margin-left: auto; color: var(--muted); font-size: 11px; }

.prio-badge { font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 10px; text-transform: uppercase; letter-spacing: .3px; }
.prio-badge.alta   { background: color-mix(in srgb, var(--accent) 15%, transparent); color: var(--accent); }
.prio-badge.normal { background: color-mix(in srgb, var(--info)   15%, transparent); color: var(--info); }
.prio-badge.baja   { background: color-mix(in srgb, var(--muted)  15%, transparent); color: var(--muted); }
.tk-vencida { color: var(--error); font-size: 11px; font-weight: 700; }

/* ── Cards de derivación ── */
.dv-card {
    background: var(--card);
    border: 1px solid color-mix(in srgb, var(--accent) 30%, var(--border));
    border-left: 3px solid var(--accent);
    border-radius: 8px;
    margin-bottom: 8px;
    padding: 9px 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.dv-head { display: flex; align-items: center; gap: 6px; font-size: 11px; }
.dv-tag  { background: var(--accent); color: #fff; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 10px; text-transform: uppercase; letter-spacing: .3px; }
.dv-tel  { color: var(--muted); font-weight: 600; font-size: 12px; }
.dv-when { margin-left: auto; color: var(--muted); font-size: 11px; }
.dv-body { font-size: 12px; color: var(--text); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.dv-link { font-size: 12px; color: var(--info); text-decoration: none; align-self: flex-end; padding: 2px 0; }
.dv-link:hover { text-decoration: underline; }

/* ── Panel ── */
.ct-panel {
    flex: 1;
    min-width: 0;
    border-left: 1px solid var(--border);
    padding-left: 16px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.ct-panel-empty { flex: 1; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 14px; flex-direction: column; gap: 10px; }
.panel-head { padding: 10px 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.panel-head-name { font-weight: 600; font-size: 15px; }

.btn { padding: 5px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; border: 1px solid var(--border); background: transparent; color: var(--muted); white-space: nowrap; transition: .12s; display: inline-flex; align-items: center; gap: 4px; }
.btn:hover         { color: var(--text); border-color: var(--text); }
.btn-ok            { border-color: color-mix(in srgb, var(--success) 35%, transparent); color: var(--success); }
.btn-ok:hover      { background: color-mix(in srgb, var(--success) 12%, transparent); }
.btn-danger        { border-color: color-mix(in srgb, var(--error) 35%, transparent); color: var(--error); }
.btn-danger:hover  { background: color-mix(in srgb, var(--error) 12%, transparent); }

.tarea-scroll { flex: 1; overflow-y: auto; padding: 14px 0; }
.tarea-titulo-detail { font-size: 17px; font-weight: 700; margin-bottom: 8px; line-height: 1.3; }
.tarea-titulo-detail.completada { text-decoration: line-through; opacity: .5; }
.tarea-desc-detail { font-size: 13px; color: var(--text); line-height: 1.6; white-space: pre-wrap; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; }
.tarea-meta-grid { display: grid; grid-template-columns: 110px 1fr; gap: 6px 12px; font-size: 12px; margin-bottom: 14px; }
.tarea-meta-grid .lbl { color: var(--muted); font-weight: 600; text-transform: uppercase; font-size: 10px; letter-spacing: .4px; display: flex; align-items: center; }
.tarea-meta-grid .val { color: var(--text); display: flex; align-items: center; gap: 6px; }

.seg-strip  { border-top: 1px solid var(--border); flex-shrink: 0; }
.seg-toggle { display: flex; align-items: center; gap: 6px; padding: 7px 0; font-size: 11px; font-weight: 600; color: var(--muted); cursor: pointer; user-select: none; letter-spacing: .4px; text-transform: uppercase; }
.seg-toggle:hover { color: var(--text); }
.seg-arrow  { transition: transform .2s; font-size: 10px; }
.seg-arrow.open { transform: rotate(90deg); }

.kom-list { padding: 6px 0 10px; }
.kom-item { padding: 8px 0; border-bottom: 1px solid var(--border); }
.kom-item:last-child { border-bottom: none; }
.kom-head { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.kom-autor { font-size: 12px; font-weight: 600; color: var(--text); }
.kom-time  { font-size: 11px; color: var(--muted); }
.kom-del   { margin-left: auto; background: none; border: none; color: var(--muted); cursor: pointer; font-size: 13px; padding: 0; opacity: .5; }
.kom-del:hover { opacity: 1; color: var(--error); }
.kom-body  { font-size: 13px; color: var(--text); line-height: 1.5; white-space: pre-wrap; }
.kom-input-area { border-top: 1px solid var(--border); padding-top: 10px; margin-top: 4px; flex-shrink: 0; }
.kom-textarea { width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 8px 11px; color: var(--text); font-size: 13px; resize: none; min-height: 50px; font-family: inherit; }
.kom-textarea:focus { outline: none; border-color: var(--info); }
.kom-send { margin-top: 6px; padding: 5px 14px; background: var(--accent); color: #fff; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; }

.tk-form { flex: 1; overflow-y: auto; padding: 14px 0; }
.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 5px; }
.form-input, .form-select, .form-textarea {
    width: 100%;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px 11px;
    color: var(--text);
    font-size: 13px;
    font-family: inherit;
}
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--info); }
.form-textarea { resize: none; min-height: 72px; }
.prio-btns { display: flex; gap: 6px; }
.prio-btn { padding: 5px 14px; border-radius: 12px; border: 1px solid var(--border); background: transparent; color: var(--muted); cursor: pointer; font-size: 12px; font-weight: 500; }
.prio-btn.active.alta   { border-color: var(--accent); background: rgba(192,39,58,.1); color: var(--accent); }
.prio-btn.active.normal { border-color: var(--info); background: color-mix(in srgb, var(--info) 10%, transparent); color: var(--info); }
.prio-btn.active.baja   { border-color: var(--muted); background: color-mix(in srgb, var(--muted) 10%, transparent); color: var(--muted); }

.refresh-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--success); display: inline-block; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

.toast { position:fixed;bottom:90px;right:24px;z-index:9999;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500;opacity:0;transform:translateY(8px);transition:.2s;pointer-events:none; }
.toast.show { opacity:1;transform:none; }
.toast.ok    { background:rgba(63,185,80,.15);color:var(--success);border:1px solid rgba(63,185,80,.3); }
.toast.error { background:rgba(248,81,73,.15);color:var(--error);border:1px solid rgba(248,81,73,.3); }
</style>
@endpush

@section('content')
<div class="ct-root">

    <div class="ct-header">
        <span class="ct-title">Centro de tareas</span>
        <span class="refresh-dot" title="Se actualiza automáticamente"></span>
        <a href="/mis-conversaciones" style="margin-left:auto;font-size:12px;color:var(--muted);text-decoration:none;padding:5px 10px;border:1px solid var(--border);border-radius:6px;">→ Mis conversaciones</a>
    </div>

    <div class="ct-subheader">
        <div class="ct-filters">
            <button class="ct-filter active" id="filt-mias"      onclick="setFiltro('mias')">Todas mías</button>
            <button class="ct-filter"         id="filt-asignadas" onclick="setFiltro('asignadas')">Asignadas a mí</button>
            <button class="ct-filter"         id="filt-creadas"   onclick="setFiltro('creadas')">Creadas por mí</button>
            <button class="ct-filter"         id="filt-todas"     onclick="setFiltro('todas')">Todas</button>
            <div class="ct-sep"></div>
            <button class="ct-filter active"  id="filt-activas"     onclick="setEstado('activas')">Pendientes</button>
            <button class="ct-filter"         id="filt-completadas" onclick="setEstado('completadas')">Completadas</button>
            <div class="ct-sep"></div>
            <button class="ct-filter vencidas" id="filt-vencidas"   onclick="toggleVencidas()" title="Solo vencidas">⚑ Vencidas</button>
        </div>
        <button class="btn-new" onclick="nuevaTarea()">+ Nueva tarea</button>
    </div>

    <div class="ct-body">
        <div class="ct-list" id="ct-list"></div>
        <div class="ct-panel" id="ct-panel">
            <div class="ct-panel-empty" id="panel-empty">
                <span style="font-size:32px;opacity:.25;">✓</span>
                <span>Seleccioná una tarea o</span>
                <button onclick="nuevaTarea()" style="font-size:13px;color:var(--info);background:none;border:none;cursor:pointer;text-decoration:underline;">creá una nueva</button>
            </div>
            <div id="panel-content" style="display:none;flex:1;flex-direction:column;overflow:hidden;"></div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CSRF           = '{{ csrf_token() }}';
const ME_ID          = {{ auth()->id() }};
const USUARIOS       = @json($usuarios);
const CONVERSACIONES = @json($conversaciones);

const PRIO_LABEL   = { alta: 'Alta', normal: 'Normal', baja: 'Baja' };
const ESTADO_LABEL = { pendiente: 'Pendiente', en_progreso: 'En progreso', completada: 'Completada' };

let state = {
    tareas:        [],
    derivaciones:  [],
    tareaId:       null,
    filtro:        'mias',        // mias | asignadas | creadas | todas
    estado:        'activas',     // activas | completadas
    vencidas:      false,
    komOpen:       true,
};

// ── API ──────────────────────────────────────────────────────────
async function api(method, url, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(url, opts);
    if (!r.ok) throw new Error(r.status);
    return r.json();
}
const get   = url      => api('GET', url);
const post  = (url, b) => api('POST', url, b);
const patch = (url, b) => api('PATCH', url, b);
const del   = url      => api('DELETE', url);

function toast(msg, tipo = 'ok') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = `toast ${tipo} show`;
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 3000);
}

// ── Fetch ────────────────────────────────────────────────────────
async function fetchTareas() {
    try {
        const params = new URLSearchParams({
            filtro:   state.filtro,
            estado:   state.estado,
            vencidas: state.vencidas ? '1' : '0',
        });
        const data = await get(`/tareas/data?${params}`);
        state.tareas = data.data || [];
        renderLista();
        if (state.tareaId && state.tareaId !== 'nueva' && !state.tareas.find(t => t.id === state.tareaId)) {
            cerrarPanel();
        }
    } catch(e) {}
}

async function fetchDerivaciones() {
    try {
        const data = await get('/centro-tareas/derivaciones');
        state.derivaciones = data.data || [];
        renderLista();
    } catch(e) {}
}

// ── Lista ────────────────────────────────────────────────────────
function renderLista() {
    const list = document.getElementById('ct-list');

    // Las derivaciones solo se muestran si el filtro está en "mias" o "asignadas" y estado activo.
    const mostrarDeriv = state.derivaciones.length > 0
        && state.estado === 'activas'
        && (state.filtro === 'mias' || state.filtro === 'asignadas')
        && !state.vencidas;

    let html = '';

    if (mostrarDeriv) {
        html += `<div class="ct-section-head deriv">Derivaciones del bot <span class="cnt">${state.derivaciones.length}</span></div>`;
        html += state.derivaciones.map(d => `
            <div class="dv-card">
                <div class="dv-head">
                    <span class="dv-tag">${d.urgente ? 'Urgente' : 'Derivación'}</span>
                    <span class="dv-tel">${esc(d.telefono)}</span>
                    <span class="dv-when">${esc(d.hace || '')}</span>
                </div>
                <div style="font-size:11px;color:var(--muted);font-weight:600;">${esc(d.etiqueta || '')}</div>
                <div class="dv-body">${esc(d.resumen || d.texto || '—')}</div>
                <a href="/atencion" class="dv-link">Atender en /atencion →</a>
            </div>
        `).join('');
    }

    if (mostrarDeriv && state.tareas.length) {
        html += `<div class="ct-section-head">Tareas <span class="cnt">${state.tareas.length}</span></div>`;
    }

    if (!state.tareas.length && !mostrarDeriv) {
        html = `<div class="ct-empty">${state.estado === 'completadas' ? 'Sin tareas completadas' : (state.vencidas ? 'Sin tareas vencidas 🎉' : 'Sin tareas pendientes 🎉')}</div>`;
    } else if (!state.tareas.length) {
        html += `<div class="ct-empty" style="padding:20px 0;font-size:12px;">— sin tareas —</div>`;
    } else {
        html += state.tareas.map(t => {
            const sel     = state.tareaId === t.id;
            const classes = ['tk-card', `prio-${t.prioridad}`, t.estado === 'completada' ? 'completada' : '', sel ? 'selected' : '', t.vencida ? 'vencida' : ''].filter(Boolean).join(' ');
            const vencidaBadge = t.vencida ? '<span class="tk-vencida">⚑ VENCIDA</span>' : '';
            const kom = t.comentarios.length > 0 ? `· 💬 ${t.comentarios.length}` : '';
            const asig = t.asignado_nombre ? `→ ${esc(t.asignado_nombre)}` : (t.creado_nombre ? `de ${esc(t.creado_nombre)}` : '');
            const vence = t.vence_fmt ? `· ${esc(t.vence_fmt)}` : '';

            return `<div class="${classes}" onclick="verTarea(${t.id})">
                <div class="tk-card-head">
                    <span class="prio-badge ${t.prioridad}">${PRIO_LABEL[t.prioridad]}</span>
                    ${vencidaBadge}
                    <span class="tk-time">${esc(t.hace)}</span>
                </div>
                <div class="tk-card-body">
                    <div class="tk-titulo">${esc(t.titulo)}</div>
                    ${t.descripcion ? `<div class="tk-desc-prev">${esc(t.descripcion)}</div>` : ''}
                    <div class="tk-meta-line">${[asig, vence, kom].filter(Boolean).join(' ')}</div>
                </div>
            </div>`;
        }).join('');
    }

    list.innerHTML = html;
}

// ── Detalle ──────────────────────────────────────────────────────
async function verTarea(id) {
    state.tareaId = id;
    renderLista();
    const t = state.tareas.find(t => t.id === id);
    if (!t) return;
    mostrarPanel();
    renderDetalle(t);
}

function cerrarPanel() {
    state.tareaId = null;
    document.getElementById('panel-empty').style.display   = '';
    document.getElementById('panel-content').style.display = 'none';
    renderLista();
}

function mostrarPanel() {
    document.getElementById('panel-empty').style.display   = 'none';
    document.getElementById('panel-content').style.display = 'flex';
}

function renderDetalle(t) {
    const pc = document.getElementById('panel-content');

    const completada   = t.estado === 'completada';
    const vencidaClass = t.vencida ? 'tk-vencida' : '';
    const descHtml = t.descripcion ? `<div class="tarea-desc-detail">${esc(t.descripcion)}</div>` : '';

    let meta = `
        <div class="lbl">Asignada a</div>
        <div class="val">${esc(t.asignado_nombre || '— sin asignar —')}</div>
        <div class="lbl">Creada por</div>
        <div class="val">${esc(t.creado_nombre || '—')}</div>
        <div class="lbl">Estado</div>
        <div class="val">${esc(ESTADO_LABEL[t.estado] || t.estado)}</div>
        <div class="lbl">Prioridad</div>
        <div class="val"><span class="prio-badge ${t.prioridad}">${PRIO_LABEL[t.prioridad]}</span></div>
    `;
    if (t.vence_fmt) meta += `<div class="lbl">Vence</div><div class="val ${vencidaClass}">${esc(t.vence_fmt)}${t.vencida ? ' ⚑' : ''}</div>`;
    if (t.ref_id)    meta += `<div class="lbl">Conversación</div><div class="val"><a href="/mis-conversaciones?conv_id=${t.ref_id}" style="color:var(--info);">Ver conversación →</a></div>`;

    const komItems = t.comentarios.map(c => `
        <div class="kom-item">
            <div class="kom-head">
                <span class="kom-autor">${esc(c.usuario || '—')}</span>
                <span class="kom-time">${esc(c.hora)}</span>
                ${c.user_id === ME_ID ? `<button class="kom-del" onclick="borrarComentario(${t.id}, ${c.id})" title="Eliminar">✕</button>` : ''}
            </div>
            <div class="kom-body">${linkify(c.contenido)}</div>
        </div>`).join('');

    const komArrow = state.komOpen ? 'open' : '';
    const komList  = state.komOpen ? '' : 'display:none';

    pc.innerHTML = `
        <div class="panel-head">
            <button onclick="cerrarPanel()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:0 4px;">←</button>
            <div style="flex:1;min-width:0;overflow:hidden;">
                <div class="panel-head-name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(t.titulo)}</div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0;">
                <button class="btn" onclick="editarTarea(${t.id})">✏ Editar</button>
                <button class="btn ${completada ? '' : 'btn-ok'}" onclick="toggleCompletar(${t.id})">${completada ? '↩ Reabrir' : '✓ Completar'}</button>
                <button class="btn btn-danger" onclick="eliminarTarea(${t.id})">✕</button>
            </div>
        </div>
        <div class="tarea-scroll">
            <h2 class="tarea-titulo-detail ${completada ? 'completada' : ''}">${esc(t.titulo)}</h2>
            ${descHtml}
            <div class="tarea-meta-grid">${meta}</div>

            <div class="seg-strip">
                <div class="seg-toggle" onclick="toggleKom()">
                    <span class="seg-arrow ${komArrow}" id="kom-arrow">▶</span>
                    COMENTARIOS (${t.comentarios.length})
                </div>
                <div class="kom-list" id="kom-list" style="${komList}">${komItems || '<div style="font-size:12px;color:var(--muted);">Sin comentarios aún.</div>'}</div>
            </div>
        </div>
        <div class="kom-input-area">
            <textarea class="kom-textarea" id="kom-input" placeholder="Agregar comentario..." onkeydown="if(event.ctrlKey&&event.key==='Enter')agregarComentario(${t.id})"></textarea>
            <button class="kom-send" onclick="agregarComentario(${t.id})">Comentar</button>
            <div style="font-size:10px;color:var(--muted);margin-top:3px;">Ctrl+Enter para comentar</div>
        </div>`;
}

function toggleKom() {
    state.komOpen = !state.komOpen;
    const list  = document.getElementById('kom-list');
    const arrow = document.getElementById('kom-arrow');
    if (list)  list.style.display = state.komOpen ? '' : 'none';
    if (arrow) arrow.classList.toggle('open', state.komOpen);
}

// ── Comentarios ──────────────────────────────────────────────────
async function agregarComentario(tareaId) {
    const inp     = document.getElementById('kom-input');
    const contenido = inp?.value?.trim();
    if (!contenido) return;
    inp.value = '';
    try {
        const res = await post(`/tareas/${tareaId}/comentario`, { contenido });
        const t = state.tareas.find(t => t.id === tareaId);
        if (t) {
            t.comentarios.push(res.data);
            renderDetalle(t);
            setTimeout(() => {
                const ni = document.getElementById('kom-input');
                if (ni) ni.focus();
                const kl = document.getElementById('kom-list');
                if (kl) kl.scrollTop = kl.scrollHeight;
            }, 50);
        }
        toast('Comentario agregado');
    } catch(e) { toast('Error al comentar', 'error'); }
}

async function borrarComentario(tareaId, komId) {
    try {
        await del(`/tareas/comentario/${komId}`);
        const t = state.tareas.find(t => t.id === tareaId);
        if (t) {
            t.comentarios = t.comentarios.filter(c => c.id !== komId);
            renderDetalle(t);
        }
        toast('Comentario eliminado');
    } catch(e) { toast('Error', 'error'); }
}

// ── Acciones ─────────────────────────────────────────────────────
async function toggleCompletar(id) {
    const t = state.tareas.find(t => t.id === id);
    if (!t) return;
    const nuevoEstado = t.estado === 'completada' ? 'pendiente' : 'completada';
    try {
        await patch(`/tareas/${id}`, { estado: nuevoEstado });
        t.estado  = nuevoEstado;
        t.vencida = false;
        renderDetalle(t);
        renderLista();
        toast(nuevoEstado === 'completada' ? 'Completada ✓' : 'Reabierta');
    } catch(e) { toast('Error', 'error'); }
}

async function eliminarTarea(id) {
    if (!confirm('¿Eliminar esta tarea?')) return;
    try {
        await del(`/tareas/${id}`);
        state.tareas = state.tareas.filter(t => t.id !== id);
        cerrarPanel();
        renderLista();
        toast('Tarea eliminada');
    } catch(e) { toast('Error al eliminar', 'error'); }
}

// ── Filtros ──────────────────────────────────────────────────────
function setFiltro(f) {
    state.filtro = f;
    ['mias','asignadas','creadas','todas'].forEach(v => {
        document.getElementById(`filt-${v}`).classList.toggle('active', v === f);
    });
    fetchTareas();
}
function setEstado(e) {
    state.estado = e;
    document.getElementById('filt-activas').classList.toggle('active', e === 'activas');
    document.getElementById('filt-completadas').classList.toggle('active', e === 'completadas');
    fetchTareas();
}
function toggleVencidas() {
    state.vencidas = !state.vencidas;
    document.getElementById('filt-vencidas').classList.toggle('active', state.vencidas);
    fetchTareas();
}

// ── Formulario ───────────────────────────────────────────────────
let _formPrio   = 'normal';
let _formEditId = null;

function nuevaTarea() {
    _formEditId = null;
    _formPrio   = 'normal';
    state.tareaId = 'nueva';
    renderLista();
    mostrarPanel();
    renderForm(null);
}

function editarTarea(id) {
    const t = state.tareas.find(t => t.id === id);
    if (!t) return;
    _formEditId = id;
    _formPrio   = t.prioridad || 'normal';
    renderForm(t);
}

function renderForm(t) {
    const pc = document.getElementById('panel-content');
    const titulo = t ? esc(t.titulo) : '';
    const desc   = t ? esc(t.descripcion || '') : '';
    const vence  = t?.vence_at || '';

    const usrOpts = USUARIOS.map(u =>
        `<option value="${u.id}" ${t?.asignada_a === u.id ? 'selected' : ''}>${esc(u.nombre_completo)}</option>`
    ).join('');

    const convOpts = CONVERSACIONES.map(c =>
        `<option value="${c.id}" ${t?.ref_id === c.id ? 'selected' : ''}>${esc(c.label)}</option>`
    ).join('');

    pc.innerHTML = `
        <div class="panel-head">
            <button onclick="${t ? `verTarea(${t.id})` : 'cerrarPanel()'}" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:0 4px;">←</button>
            <div style="flex:1;"><span style="font-weight:600;font-size:15px;">${t ? 'Editar tarea' : 'Nueva tarea'}</span></div>
            <button class="btn btn-ok" onclick="guardarTarea()" style="font-size:13px;">💾 Guardar</button>
        </div>
        <div class="tk-form">
            <div class="form-group">
                <label class="form-label">Título *</label>
                <input type="text" id="tf-titulo" class="form-input" placeholder="Describir la tarea..." value="${titulo}">
            </div>
            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea id="tf-desc" class="form-textarea" placeholder="Detalles opcionales...">${desc}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Asignada a</label>
                <select id="tf-asignada" class="form-select">
                    <option value="">— Sin asignar —</option>
                    ${usrOpts}
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Vencimiento</label>
                <input type="datetime-local" id="tf-vence" class="form-input" value="${vence}">
            </div>
            <div class="form-group">
                <label class="form-label">Prioridad</label>
                <div class="prio-btns">
                    <button id="pbtn-baja"   class="prio-btn baja   ${_formPrio==='baja'   ? 'active' : ''}" onclick="setPrio('baja')">Baja</button>
                    <button id="pbtn-normal" class="prio-btn normal ${_formPrio==='normal' ? 'active' : ''}" onclick="setPrio('normal')">Normal</button>
                    <button id="pbtn-alta"   class="prio-btn alta   ${_formPrio==='alta'   ? 'active' : ''}" onclick="setPrio('alta')">Alta</button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Vincular conversación <span style="font-weight:400;text-transform:none;">(opcional)</span></label>
                <select id="tf-ref" class="form-select">
                    <option value="">— ninguna —</option>
                    ${convOpts}
                </select>
            </div>
        </div>`;
}

function setPrio(p) {
    _formPrio = p;
    ['baja', 'normal', 'alta'].forEach(v => {
        const b = document.getElementById(`pbtn-${v}`);
        if (b) b.classList.toggle('active', v === p);
    });
}

async function guardarTarea() {
    const titulo = document.getElementById('tf-titulo')?.value?.trim();
    if (!titulo) { toast('El título es obligatorio', 'error'); return; }

    const payload = {
        titulo,
        descripcion: document.getElementById('tf-desc')?.value?.trim() || null,
        asignada_a:  document.getElementById('tf-asignada')?.value || null,
        vence_at:    document.getElementById('tf-vence')?.value || null,
        prioridad:   _formPrio,
        ref_tipo:    document.getElementById('tf-ref')?.value ? 'wa' : null,
        ref_id:      document.getElementById('tf-ref')?.value || null,
    };

    try {
        if (_formEditId) {
            await patch(`/tareas/${_formEditId}`, payload);
            toast('Tarea actualizada');
            await fetchTareas();
            const t = state.tareas.find(t => t.id === _formEditId);
            if (t) { state.tareaId = _formEditId; renderDetalle(t); }
        } else {
            const res = await post('/tareas', payload);
            toast('Tarea creada ✓');
            state.tareas.unshift(res.data);
            state.tareaId = res.data.id;
            renderLista();
            renderDetalle(res.data);
        }
    } catch(e) { toast('Error al guardar', 'error'); }
}

// ── Helpers ──────────────────────────────────────────────────────
function esc(s) { if (s === null || s === undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function linkify(s) {
    if (!s) return '';
    return s.split(/(https?:\/\/[^\s<>"]+)/g).map((p, i) => i % 2 === 1
        ? `<a href="${esc(p)}" target="_blank" rel="noopener" style="color:var(--info);text-decoration:underline;word-break:break-all;">${esc(p)}</a>`
        : esc(p)
    ).join('');
}

// ── Init ──────────────────────────────────────────────────────────
fetchTareas();
fetchDerivaciones();
setInterval(fetchTareas, 15000);
setInterval(fetchDerivaciones, 20000);

// Deep-link: /centro-tareas?tarea_id=N
(function() {
    const m = window.location.search.match(/[?&]tarea_id=(\d+)/);
    if (!m) return;
    const tid = parseInt(m[1]);
    const wait = setInterval(() => {
        if (state.tareas?.length) {
            clearInterval(wait);
            verTarea(tid);
        }
    }, 200);
    setTimeout(() => clearInterval(wait), 5000);
})();
</script>
@endsection
