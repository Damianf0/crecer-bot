@extends('layouts.app')
@section('title', 'Mis tareas')

@push('styles')
<style>
/* ── Layout ── */
.mt-root   { height: calc(100vh - 100px); display: flex; flex-direction: column; }
.mt-header { display: flex; align-items: center; gap: 12px; margin-bottom: 0; flex-shrink: 0; padding-bottom: 10px; }
.mt-title  { font-size: 17px; font-weight: 700; }
.mt-layout { display: flex; flex: 1; min-height: 0; overflow: hidden; }

/* ── Tabs ── */
.mt-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; margin-bottom: 0; gap: 0; }
.mt-tab  {
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 500;
    color: var(--muted);
    cursor: pointer;
    border: none;
    border-bottom: 2px solid transparent;
    background: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: color .12s, border-color .12s;
}
.mt-tab:hover  { color: var(--text); }
.mt-tab.active { color: var(--text); border-bottom-color: var(--accent); }
.tab-badge {
    background: var(--accent);
    color: #fff;
    border-radius: 10px;
    padding: 1px 7px;
    font-size: 11px;
    font-weight: 700;
}
.tab-badge.info { background: var(--info); }

/* ── Tab content wrapper ── */
.tab-content { display: none; flex: 1; min-height: 0; overflow: hidden; }
.tab-content.active { display: flex; }

/* ── Lista ── */
.mt-list { width: 300px; flex-shrink: 0; overflow-y: auto; padding-right: 10px; }
.mt-empty { text-align: center; padding: 60px 0; color: var(--muted); font-size: 14px; }

/* ── Cards de conversación ── */
.mt-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-left: 3px solid transparent;
    border-radius: 8px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: border-color .12s, box-shadow .12s;
}
.mt-card:hover        { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.mt-card.urgente      { border-left-color: var(--accent); }
.mt-card.selected     { border-left-color: var(--info); background: rgba(88,166,255,.04); }
.mt-card.selected.urgente { border-left-color: var(--accent); background: rgba(192,39,58,.04); }
.mt-card-head { padding: 7px 12px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 6px; font-size: 11px; }
.badge-urg  { color: var(--accent); font-weight: 700; font-size: 11px; }
.badge-nl   { background: var(--accent); color: #fff; border-radius: 10px; padding: 1px 6px; font-size: 11px; font-weight: 700; }
.mt-time    { margin-left: auto; color: var(--muted); font-size: 11px; }
.mt-card-body { padding: 10px 12px; }
.mt-contact { font-weight: 600; font-size: 14px; margin-bottom: 3px; }
.mt-preview { font-size: 12px; color: var(--muted); line-height: 1.4; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.mt-preview.resumen { color: var(--info); font-style: italic; }

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
.tk-card:hover   { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.tk-card.selected { border-left-color: var(--info); background: rgba(88,166,255,.04); }
.tk-card.completada .tk-titulo { text-decoration: line-through; opacity: .5; }
.tk-card.prio-alta   { border-left-color: var(--accent); }
.tk-card.prio-normal { border-left-color: var(--info); }
.tk-card.prio-baja   { border-left-color: var(--border); }
.tk-card-head { padding: 6px 12px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 6px; font-size: 11px; }
.tk-card-body { padding: 9px 12px; }
.tk-titulo    { font-weight: 600; font-size: 13px; margin-bottom: 3px; }
.tk-desc-prev { font-size: 12px; color: var(--muted); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; }
.tk-meta-line { font-size: 11px; color: var(--muted); margin-top: 4px; }

/* Badges de prioridad */
.prio-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: .3px;
}
.prio-badge.alta   { background: color-mix(in srgb, var(--accent) 15%, transparent); color: var(--accent); }
.prio-badge.normal { background: color-mix(in srgb, var(--info)   15%, transparent); color: var(--info); }
.prio-badge.baja   { background: color-mix(in srgb, var(--muted)  15%, transparent); color: var(--muted); }
.tk-vencida { color: var(--error); font-size: 11px; font-weight: 700; }

/* ── Panel (compartido) ── */
.mt-panel {
    flex: 1;
    min-width: 0;
    border-left: 1px solid var(--border);
    padding-left: 16px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.mt-panel-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    font-size: 14px;
    flex-direction: column;
    gap: 10px;
}
.panel-head {
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.panel-head-name { font-weight: 600; font-size: 15px; }
.panel-head-sub  { font-size: 12px; color: var(--muted); }

/* ── Botones de acción ── */
.btn { padding: 5px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; border: 1px solid var(--border); background: transparent; color: var(--muted); white-space: nowrap; transition: .12s; display: inline-flex; align-items: center; gap: 4px; }
.btn:hover         { color: var(--text); border-color: var(--text); }
.btn-urg           { border-color: color-mix(in srgb, var(--accent) 35%, transparent); color: var(--accent); }
.btn-urg:hover     { background: color-mix(in srgb, var(--accent) 12%, transparent); }
.btn-urg.on        { background: color-mix(in srgb, var(--accent) 15%, transparent); }
.btn-ok            { border-color: color-mix(in srgb, var(--success) 35%, transparent); color: var(--success); }
.btn-ok:hover      { background: color-mix(in srgb, var(--success) 12%, transparent); }
.btn-del           { border-color: color-mix(in srgb, var(--info) 35%, transparent); color: var(--info); position: relative; }
.btn-del:hover     { background: color-mix(in srgb, var(--info) 12%, transparent); }
.btn-danger        { border-color: color-mix(in srgb, var(--error) 35%, transparent); color: var(--error); }
.btn-danger:hover  { background: color-mix(in srgb, var(--error) 12%, transparent); }

/* ── Dropdown delegación ── */
.del-dropdown { position: absolute; top: calc(100% + 4px); right: 0; background: var(--card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 6px 20px rgba(0,0,0,.12); min-width: 180px; z-index: 100; display: none; }
.del-dropdown.open { display: block; }
.del-opt { padding: 8px 14px; font-size: 13px; cursor: pointer; color: var(--text); transition: background .1s; }
.del-opt:first-child { border-radius: 8px 8px 0 0; }
.del-opt:last-child  { border-radius: 0 0 8px 8px; }
.del-opt:hover       { background: var(--bg); }

/* ── Resumen banner ── */
.resumen-banner { padding: 8px 12px; background: color-mix(in srgb, var(--info) 7%, transparent); border-bottom: 1px solid color-mix(in srgb, var(--info) 18%, transparent); font-size: 12px; color: var(--text); flex-shrink: 0; line-height: 1.4; }
.resumen-banner span { font-size: 10px; font-weight: 700; color: var(--info); letter-spacing: .5px; margin-right: 8px; }

/* ── Mensajes ── */
.msg-list { flex: 1; overflow-y: auto; padding: 12px 0; display: flex; flex-direction: column; gap: 6px; }
.msg-date   { text-align: center; font-size: 11px; color: var(--muted); padding: 4px 0; }
.msg-wrap   { display: flex; }
.msg-wrap.in   { justify-content: flex-start; }
.msg-wrap.out  { justify-content: flex-end; }
.msg-wrap.nota { justify-content: center; }
.msg-bubble { max-width: 72%; padding: 7px 11px; border-radius: 12px; font-size: 13px; line-height: 1.45; }
.msg-bubble.in   { background: var(--bg); border: 1px solid var(--border); border-radius: 0 12px 12px 12px; }
.msg-bubble.out  { background: color-mix(in srgb, var(--accent) 18%, transparent); border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent); border-radius: 12px 0 12px 12px; }
.msg-bubble.nota { background: color-mix(in srgb, var(--warning) 12%, transparent); border: 1px solid color-mix(in srgb, var(--warning) 28%, transparent); color: var(--warning); border-radius: 8px; font-size: 12px; max-width: 80%; }
.msg-time  { font-size: 11px; color: var(--muted); margin-top: 3px; }
.msg-time.right { text-align: right; }

/* ── Seguimiento ── */
.seg-strip  { border-top: 1px solid var(--border); flex-shrink: 0; }
.seg-toggle { display: flex; align-items: center; gap: 6px; padding: 7px 0; font-size: 11px; font-weight: 600; color: var(--muted); cursor: pointer; user-select: none; letter-spacing: .4px; text-transform: uppercase; }
.seg-toggle:hover { color: var(--text); }
.seg-arrow  { transition: transform .2s; font-size: 10px; }
.seg-arrow.open { transform: rotate(90deg); }
.seg-list   { padding-bottom: 8px; }
.seg-item   { display: flex; align-items: baseline; gap: 8px; padding: 3px 0; }
.seg-icon   { font-size: 13px; flex-shrink: 0; }
.seg-text   { font-size: 12px; color: var(--text); flex: 1; }
.seg-when   { font-size: 11px; color: var(--muted); white-space: nowrap; }

/* ── Input WA ── */
.panel-input { border-top: 1px solid var(--border); padding: 10px 0 0; flex-shrink: 0; }
.input-modes { display: flex; gap: 6px; margin-bottom: 7px; }
.mode-btn { font-size: 11px; padding: 3px 10px; border-radius: 12px; border: 1px solid var(--border); background: transparent; color: var(--muted); cursor: pointer; }
.mode-btn.active { border-color: var(--accent); background: rgba(192,39,58,.1); color: var(--accent); }
.input-row { display: flex; gap: 8px; align-items: flex-end; }
.msg-textarea { flex: 1; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 8px 11px; color: var(--text); font-size: 13px; resize: none; min-height: 50px; font-family: inherit; }
.msg-textarea:focus { outline: none; border-color: var(--info); }
.send-btn { padding: 0 16px; background: var(--accent); border: none; color: #fff; border-radius: 8px; font-size: 13px; cursor: pointer; height: 38px; }

/* ── Subheader de tareas ── */
.tk-subheader { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.tk-filters   { display: flex; gap: 4px; align-items: center; }
.tk-filter    { font-size: 11px; padding: 3px 10px; border-radius: 12px; border: 1px solid var(--border); background: transparent; color: var(--muted); cursor: pointer; }
.tk-filter.active { border-color: var(--accent); background: rgba(192,39,58,.1); color: var(--accent); }
.btn-new-tk   { margin-left: auto; padding: 5px 14px; background: var(--accent); color: #fff; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 600; }

/* ── Detalle de tarea ── */
.tarea-scroll { flex: 1; overflow-y: auto; padding: 14px 0; }
.tarea-titulo-detail { font-size: 17px; font-weight: 700; margin-bottom: 8px; line-height: 1.3; }
.tarea-titulo-detail.completada { text-decoration: line-through; opacity: .5; }
.tarea-desc-detail { font-size: 13px; color: var(--text); line-height: 1.6; white-space: pre-wrap; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; }
.tarea-meta-grid { display: grid; grid-template-columns: 110px 1fr; gap: 6px 12px; font-size: 12px; margin-bottom: 14px; }
.tarea-meta-grid .lbl { color: var(--muted); font-weight: 600; text-transform: uppercase; font-size: 10px; letter-spacing: .4px; display: flex; align-items: center; }
.tarea-meta-grid .val { color: var(--text); display: flex; align-items: center; gap: 6px; }

/* ── Comentarios ── */
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

/* ── Formulario tarea ── */
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

/* ── Refresh dot ── */
.refresh-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--success); display: inline-block; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

/* ── Toast ── */
.toast { position:fixed;bottom:90px;right:24px;z-index:9999;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500;opacity:0;transform:translateY(8px);transition:.2s;pointer-events:none; }
.toast.show { opacity:1;transform:none; }
.toast.ok    { background:rgba(63,185,80,.15);color:var(--success);border:1px solid rgba(63,185,80,.3); }
.toast.error { background:rgba(248,81,73,.15);color:var(--error);border:1px solid rgba(248,81,73,.3); }
</style>
@endpush

@section('content')
<div class="mt-root">

    {{-- Header --}}
    <div class="mt-header">
        <span class="mt-title">Mis tareas</span>
        <span class="refresh-dot" title="Se actualiza automáticamente"></span>
        <a href="/atencion" style="margin-left:auto;font-size:12px;color:var(--muted);text-decoration:none;padding:5px 10px;border:1px solid var(--border);border-radius:6px;">↗ Ver atención</a>
    </div>

    {{-- Tabs --}}
    <div class="mt-tabs">
        <button class="mt-tab active" id="tab-btn-conv" onclick="setTab('conversaciones')">
            💬 Conversaciones
            <span class="tab-badge" id="cnt-conv">{{ count($items) }}</span>
        </button>
        <button class="mt-tab" id="tab-btn-tareas" onclick="setTab('tareas')">
            ✓ Tareas
            <span class="tab-badge info" id="cnt-tk">—</span>
        </button>
    </div>

    {{-- Body --}}
    <div class="mt-layout">

        {{-- ═══ TAB: CONVERSACIONES ═══ --}}
        <div class="tab-content active" id="tab-conv">
            <div class="mt-list" id="mt-list"></div>
            <div class="mt-panel" id="mt-panel">
                <div class="mt-panel-empty" id="panel-empty">
                    <span style="font-size:32px;opacity:.25;">◫</span>
                    <span>Seleccioná una conversación</span>
                </div>
                <div id="panel-conv" style="display:none;flex:1;flex-direction:column;overflow:hidden;"></div>
            </div>
        </div>

        {{-- ═══ TAB: TAREAS ═══ --}}
        <div class="tab-content" id="tab-tareas" style="flex-direction:column;">

            {{-- Filtros + Nueva tarea --}}
            <div class="tk-subheader">
                <div class="tk-filters">
                    <button class="tk-filter active" id="filt-mias"       onclick="setFiltro('mias')">Mis tareas</button>
                    <button class="tk-filter"         id="filt-todas"      onclick="setFiltro('todas')">Todas</button>
                    <div style="width:1px;background:var(--border);margin:0 4px;align-self:stretch;"></div>
                    <button class="tk-filter active" id="filt-activas"     onclick="setEstadoFiltro('activas')">Pendientes</button>
                    <button class="tk-filter"         id="filt-completadas" onclick="setEstadoFiltro('completadas')">Completadas</button>
                </div>
                <button class="btn-new-tk" onclick="nuevaTarea()">+ Nueva tarea</button>
            </div>

            {{-- Lista + Panel --}}
            <div style="display:flex;flex:1;min-height:0;overflow:hidden;margin-top:12px;">
                <div class="mt-list" id="tk-list"></div>
                <div class="mt-panel" id="tk-panel">
                    <div class="mt-panel-empty" id="tk-panel-empty">
                        <span style="font-size:32px;opacity:.25;">✓</span>
                        <span>Seleccioná una tarea o</span>
                        <button onclick="nuevaTarea()" style="font-size:13px;color:var(--info);background:none;border:none;cursor:pointer;text-decoration:underline;">creá una nueva</button>
                    </div>
                    <div id="tk-panel-content" style="display:none;flex:1;flex-direction:column;overflow:hidden;"></div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CSRF          = '{{ csrf_token() }}';
const ME_ID         = {{ auth()->id() }};
const USUARIOS      = @json($usuarios);
const CONVERSACIONES = @json($conversaciones);

const PRIO_LABEL = { alta: 'Alta', normal: 'Normal', baja: 'Baja' };
const ESTADO_LABEL = { pendiente: 'Pendiente', en_progreso: 'En progreso', completada: 'Completada' };

let state = {
    // Conversaciones
    items:      @json($items),
    panelId:    null,
    modo:       'mensaje',
    segOpen:    true,
    // Tareas
    tareas:     [],
    tareaId:    null,
    tkFiltro:   'mias',
    tkEstado:   'activas',
    tkKomOpen:  true,
    // Tab
    tab:        'conversaciones',
};

// ── API ──────────────────────────────────────────────────────────────────
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

// ════════════════════════════════════════════════════════════════
// ── TABS
// ════════════════════════════════════════════════════════════════
function setTab(tab) {
    state.tab = tab;
    document.getElementById('tab-conv').classList.toggle('active', tab === 'conversaciones');
    document.getElementById('tab-tareas').classList.toggle('active', tab === 'tareas');
    document.getElementById('tab-btn-conv').classList.toggle('active', tab === 'conversaciones');
    document.getElementById('tab-btn-tareas').classList.toggle('active', tab === 'tareas');
    if (tab === 'tareas') fetchTareas();
}

// ════════════════════════════════════════════════════════════════
// ── CONVERSACIONES — polling
// ════════════════════════════════════════════════════════════════
async function fetchConversaciones() {
    try {
        const data = await get('/mis-tareas/data');
        const nuevos = data.data || [];
        if (state.panelId && !nuevos.find(i => i.id === state.panelId)) cerrarPanel();
        state.items = nuevos;
        renderLista();
    } catch(e) {}
}

// ── Lista de conversaciones ──────────────────────────────────────
let _lastHashConv = '';
function renderLista() {
    const hash = state.items.map(i => `${i.id}${i.urgente}${i.no_leidos}${state.panelId === i.id}`).join('|');
    if (hash === _lastHashConv) return;
    _lastHashConv = hash;

    document.getElementById('cnt-conv').textContent = state.items.length;
    const list = document.getElementById('mt-list');

    if (!state.items.length) {
        list.innerHTML = '<div class="mt-empty">No tenés conversaciones asignadas 🎉</div>';
        return;
    }

    list.innerHTML = state.items.map(item => {
        const sel      = state.panelId === item.id;
        const classes  = ['mt-card', item.urgente ? 'urgente' : '', sel ? 'selected' : ''].filter(Boolean).join(' ');
        const badgeUrg = item.urgente ? '<span class="badge-urg">⚑</span>' : '';
        const badgeNL  = item.no_leidos > 0 ? `<span class="badge-nl">${item.no_leidos}</span>` : '';
        const isResumen = !!item.resumen && item.resumen !== '—';
        const preview   = esc(item.resumen && item.resumen !== '—' ? item.resumen : 'Sin mensajes');
        const previewClass = isResumen ? 'mt-preview resumen' : 'mt-preview';

        return `<div class="${classes}" id="mcard-${item.id}" onclick="verItem(${item.id})">
            <div class="mt-card-head">
                ${badgeUrg}
                <span style="color:var(--muted);font-size:11px;">💬 WhatsApp</span>
                ${badgeNL}
                <span class="mt-time">${esc(item.hace)}</span>
            </div>
            <div class="mt-card-body">
                <div class="mt-contact">${esc(item.contacto)}</div>
                <div class="${previewClass}">${preview}</div>
            </div>
        </div>`;
    }).join('');
}

// ── Panel de conversación ────────────────────────────────────────
async function verItem(id) {
    state.panelId = id;
    renderLista();
    document.getElementById('panel-empty').style.display = 'none';
    const panelConv = document.getElementById('panel-conv');
    panelConv.style.display = 'flex';
    panelConv.innerHTML = '<div style="flex:1;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:13px;">Cargando…</div>';
    await cargarConversacion(id);
}

function cerrarPanel() {
    state.panelId = null;
    document.getElementById('panel-empty').style.display = '';
    document.getElementById('panel-conv').style.display  = 'none';
    renderLista();
}

async function cargarConversacion(id) {
    const { conv, mensajes, eventos } = await get(`/atencion/conversacion/${id}`);
    const item = state.items.find(i => i.id === id) || {};
    const panelConv = document.getElementById('panel-conv');

    const resumenHtml = conv.resumen
        ? `<div class="resumen-banner"><span>RESUMEN IA</span>${esc(conv.resumen)}</div>` : '';

    let lastFecha = null;
    const msgHtml = mensajes.map(m => {
        let out = '';
        if (m.fecha !== lastFecha) { out += `<div class="msg-date">${m.fecha}</div>`; lastFecha = m.fecha; }
        if (m.direccion === 'nota_interna') {
            out += `<div class="msg-wrap nota"><div class="msg-bubble nota">📝 ${linkify(m.contenido)}</div></div>`;
        } else if (m.direccion === 'entrante') {
            out += `<div class="msg-wrap in"><div><div class="msg-bubble in">${renderMsgCuerpo(m)}</div><div class="msg-time">${m.hora}</div></div></div>`;
        } else {
            out += `<div class="msg-wrap out"><div><div class="msg-bubble out">${renderMsgCuerpo(m)}</div><div class="msg-time right">${m.hora}</div></div></div>`;
        }
        return out;
    }).join('');

    const segHtml = renderSeguimiento(eventos);

    const delOpts = USUARIOS
        .filter(u => u.id !== ME_ID)
        .map(u => `<div class="del-opt" onclick="delegarA(${id}, ${u.id}, '${esc(u.nombre_completo)}')">${esc(u.nombre_completo)}</div>`)
        .join('');

    const urgente = item.urgente;

    panelConv.innerHTML = `
        <div class="panel-head">
            <button onclick="cerrarPanel()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:0 4px;">←</button>
            <div style="flex:1;min-width:0;">
                <div class="panel-head-name">${esc(conv.contacto)}</div>
                <div class="panel-head-sub">${esc(conv.telefono)}</div>
            </div>
            <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                <button class="btn btn-urg ${urgente ? 'on' : ''}" id="btn-urg" onclick="toggleUrgente(${id})" title="${urgente ? 'Quitar urgente' : 'Marcar urgente'}">⚑ Urgente</button>
                <div style="position:relative;">
                    <button class="btn btn-del" onclick="toggleDelDropdown()" id="btn-del">↗ Delegar</button>
                    <div class="del-dropdown" id="del-dropdown">${delOpts}</div>
                </div>
                <button class="btn btn-ok" onclick="resolver(${id})">✓ Resuelto</button>
            </div>
        </div>
        ${resumenHtml}
        <div class="msg-list" id="msg-list">${msgHtml || '<div style="text-align:center;color:var(--muted);padding:32px;font-size:13px;">Sin mensajes aún</div>'}</div>
        ${segHtml}
        <div class="panel-input">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px;">
                <div style="display:flex;gap:6px;">
                    <button class="mode-btn ${state.modo==='mensaje'?'active':''}" onclick="setModo('mensaje')">Mensaje</button>
                    <button class="mode-btn ${state.modo==='nota'?'active':''}" onclick="setModo('nota')">Nota interna</button>
                </div>
                <div>
                    <button id="btn-adjuntar" onclick="document.getElementById('mt-file-input').click()" style="font-size:12px;padding:3px 10px;border-radius:6px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;${state.modo==='nota'?'display:none':''}">📎 Adjuntar</button>
                    <input type="file" id="mt-file-input" style="display:none" accept="image/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt" onchange="onFileChange(event)">
                </div>
            </div>
            <div id="mt-file-preview" style="display:none;align-items:center;gap:8px;padding:6px 10px;background:var(--card);border:1px solid var(--border);border-radius:6px;margin-bottom:6px;font-size:12px;">
                <span id="mt-file-name" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                <button onclick="limpiarArchivo()" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px;padding:0;">✕</button>
            </div>
            <div class="input-row">
                <textarea class="msg-textarea" id="msg-input"
                    placeholder="${state.modo==='nota'?'Nota interna (no se envía al paciente)':'Escribir mensaje...'}"
                    onkeydown="if(event.ctrlKey&&event.key==='Enter')enviarMT(${id})"></textarea>
                <button class="send-btn" onclick="enviarMT(${id})">${state.modo==='nota'?'Guardar':'Enviar'}</button>
            </div>
            <div style="font-size:10px;color:var(--muted);margin-top:4px;">Ctrl+Enter para enviar</div>
        </div>`;

    scrollBottom();
    setTimeout(() => { document.addEventListener('click', closeDel, { once: true }); }, 0);
}

function closeDel(e) {
    const dd = document.getElementById('del-dropdown');
    if (dd && !dd.contains(e.target)) dd.classList.remove('open');
}
function toggleDelDropdown() {
    const dd = document.getElementById('del-dropdown');
    if (!dd) return;
    dd.classList.toggle('open');
    event.stopPropagation();
}

// ── Seguimiento ──────────────────────────────────────────────────
const TIPO_SEG = {
    tomada:      { icon: '🟢', label: u => `Tomada por ${u}` },
    delegada:    { icon: '📤', label: (u, d) => `Delegada por ${u} → ${d}` },
    resuelta:    { icon: '✅', label: u => `Resuelta por ${u}` },
    reabierta:   { icon: '🔁', label: u => `Reabierta por ${u}` },
    urgente_on:  { icon: '⚑',  label: u => `Urgente activado por ${u}` },
    urgente_off: { icon: '⚐',  label: u => `Urgente desactivado por ${u}` },
};

function renderSeguimiento(eventos) {
    const items = eventos.length
        ? eventos.map(e => {
            const def   = TIPO_SEG[e.tipo] || { icon: '•', label: u => e.tipo + ' ' + u };
            const label = def.label(e.usuario || '—', e.destino || '—');
            return `<div class="seg-item"><span class="seg-icon">${def.icon}</span><span class="seg-text">${esc(label)}</span><span class="seg-when">${esc(e.fecha)}</span></div>`;
        }).join('')
        : '<div style="font-size:12px;color:var(--muted);padding:4px 0;">Sin acciones registradas aún.</div>';

    const arrowClass = state.segOpen ? 'open' : '';
    const listStyle  = state.segOpen ? '' : 'display:none';

    return `<div class="seg-strip">
        <div class="seg-toggle" onclick="toggleSeg()">
            <span class="seg-arrow ${arrowClass}" id="seg-arrow">▶</span>
            SEGUIMIENTO
        </div>
        <div class="seg-list" id="seg-list" style="${listStyle}">${items}</div>
    </div>`;
}

function toggleSeg() {
    state.segOpen = !state.segOpen;
    const list  = document.getElementById('seg-list');
    const arrow = document.getElementById('seg-arrow');
    if (list)  list.style.display  = state.segOpen ? '' : 'none';
    if (arrow) arrow.classList.toggle('open', state.segOpen);
}

// ── Acciones WA ──────────────────────────────────────────────────
async function resolver(id) {
    if (!confirm('¿Marcar como resuelta? La conversación se archiva y sale de tus tareas.')) return;
    state.items = state.items.filter(i => i.id !== id);
    cerrarPanel();
    renderLista();
    toast('Resuelto ✓');
    try { await post('/atencion/resolver', { id, tipo: 'wa' }); }
    catch(e) { toast('Error al resolver', 'error'); }
}

async function toggleUrgente(id) {
    const item = state.items.find(i => i.id === id);
    if (!item) return;
    item.urgente = !item.urgente;
    renderLista();
    const btn = document.getElementById('btn-urg');
    if (btn) btn.classList.toggle('on', item.urgente);
    try { await post('/atencion/urgente', { id, tipo: 'wa' }); }
    catch(e) { toast('Error', 'error'); }
}

async function delegarA(id, userId, nombre) {
    const dd = document.getElementById('del-dropdown');
    if (dd) dd.classList.remove('open');
    try {
        await post('/atencion/delegar', { id, tipo: 'wa', user_id: userId });
        state.items = state.items.filter(i => i.id !== id);
        cerrarPanel();
        renderLista();
        toast(`Delegado a ${nombre}`);
    } catch(e) { toast('Error al delegar', 'error'); }
}

// ── Input WA ─────────────────────────────────────────────────────
function setModo(modo) {
    state.modo = modo;
    document.querySelectorAll('.mode-btn').forEach(b => {
        b.classList.toggle('active', b.textContent.trim().toLowerCase().startsWith(modo === 'nota' ? 'nota' : 'mens'));
    });
    const inp = document.getElementById('msg-input');
    if (inp) inp.placeholder = modo === 'nota' ? 'Nota interna (no se envía al paciente)' : 'Escribir mensaje...';
    const sb = document.querySelector('.send-btn');
    if (sb) sb.textContent = modo === 'nota' ? 'Guardar' : 'Enviar';
    const adjBtn = document.getElementById('btn-adjuntar');
    if (adjBtn) adjBtn.style.display = modo === 'nota' ? 'none' : '';
    if (modo === 'nota') limpiarArchivo();
}

let mtArchivo = null;
function onFileChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    mtArchivo = file;
    const prev = document.getElementById('mt-file-preview');
    const name = document.getElementById('mt-file-name');
    if (prev) prev.style.display = 'flex';
    if (name) name.textContent = file.name;
}
function limpiarArchivo() {
    mtArchivo = null;
    const inp  = document.getElementById('mt-file-input');
    const prev = document.getElementById('mt-file-preview');
    if (inp)  inp.value = '';
    if (prev) prev.style.display = 'none';
}

async function enviarMT(id) {
    if (mtArchivo) { await enviarArchivoMT(id); return; }
    const inp   = document.getElementById('msg-input');
    const texto = inp?.value?.trim();
    if (!texto || !id) return;
    inp.value = '';
    try {
        await post('/atencion/enviar', { conv_id: id, texto, modo: state.modo });
        await cargarConversacion(id);
        toast(state.modo === 'nota' ? 'Nota guardada' : 'Enviado ✓');
    } catch(e) { toast('Error al enviar', 'error'); }
}

async function enviarArchivoMT(id) {
    if (!mtArchivo || !id) return;
    const inp     = document.getElementById('msg-input');
    const caption = inp?.value?.trim() || '';
    const fd = new FormData();
    fd.append('conv_id', id);
    fd.append('archivo', mtArchivo);
    if (caption) fd.append('caption', caption);
    fd.append('_token', CSRF);
    try {
        const r    = await fetch('/atencion/enviar-archivo', { method: 'POST', body: fd });
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Error');
        if (inp) inp.value = '';
        limpiarArchivo();
        await cargarConversacion(id);
        toast('Archivo enviado ✓');
    } catch(e) { toast('Error al enviar archivo', 'error'); }
}

// ── Render mensajes WA ───────────────────────────────────────────
function renderMsgCuerpo(m) {
    if (m.tipo === 'audio') {
        const trans  = m.contenido
            ? `<div style="font-size:12px;line-height:1.5;">${linkify(m.contenido)}</div>`
            : `<div style="font-size:11px;color:var(--muted);font-style:italic;">Sin transcripción</div>`;
        const player = m.archivo_url
            ? `<div style="margin-top:7px;padding-top:7px;border-top:1px solid rgba(128,128,128,.15);"><audio controls src="${m.archivo_url}" style="height:32px;width:200px;display:block;"></audio></div>` : '';
        return `<div style="font-size:10px;font-weight:700;color:var(--muted);margin-bottom:5px;">🎤 AUDIO · TRANSCRIPCIÓN</div>${trans}${player}`;
    }
    if (m.tipo === 'imagen') {
        return `<img src="${m.archivo_url}" style="max-width:200px;max-height:160px;border-radius:6px;cursor:pointer;display:block;" onclick="window.open('${m.archivo_url}','_blank')">
                ${m.contenido ? `<div style="font-size:12px;margin-top:4px;">${linkify(m.contenido)}</div>` : ''}`;
    }
    if (m.tipo === 'documento') {
        return `<a href="${m.archivo_url}" target="_blank" style="display:flex;align-items:center;gap:8px;color:var(--info);text-decoration:none;">
                    <span style="font-size:22px;">📄</span><span style="text-decoration:underline;font-size:13px;">${esc(m.contenido||'Documento')}</span></a>`;
    }
    return linkify(m.contenido);
}

// ════════════════════════════════════════════════════════════════
// ── TAREAS
// ════════════════════════════════════════════════════════════════

async function fetchTareas() {
    try {
        const data = await get(`/tareas/data?filtro=${state.tkFiltro}&estado=${state.tkEstado}`);
        state.tareas = data.data || [];
        renderTareaLista();
        // Si la tarea abierta ya no existe (fue completada/borrada por otro), cerrar panel
        if (state.tareaId && state.tareaId !== 'nueva' && !state.tareas.find(t => t.id === state.tareaId)) {
            cerrarTarea();
        }
    } catch(e) {}
}

// ── Lista de tareas ──────────────────────────────────────────────
let _lastHashTk = '';
function renderTareaLista() {
    const hash = state.tareas.map(t => `${t.id}${t.estado}${state.tareaId === t.id}`).join('|');
    if (hash === _lastHashTk && state.tareaId !== 'nueva') { _lastHashTk = hash; }
    _lastHashTk = hash;

    const cnt  = state.tareas.filter(t => t.estado !== 'completada').length;
    document.getElementById('cnt-tk').textContent = state.tkEstado === 'activas' ? cnt : state.tareas.length;

    const list = document.getElementById('tk-list');

    if (!state.tareas.length) {
        list.innerHTML = `<div class="mt-empty">${state.tkEstado === 'completadas' ? 'Sin tareas completadas' : 'Sin tareas pendientes 🎉'}</div>`;
        return;
    }

    list.innerHTML = state.tareas.map(t => {
        const sel     = state.tareaId === t.id;
        const classes = ['tk-card', `prio-${t.prioridad}`, t.estado === 'completada' ? 'completada' : '', sel ? 'selected' : ''].filter(Boolean).join(' ');
        const vencidaBadge = t.vencida ? '<span class="tk-vencida">⚑ VENCIDA</span>' : '';
        const kom = t.comentarios.length > 0 ? `· 💬 ${t.comentarios.length}` : '';
        const asig = t.asignado_nombre ? `→ ${esc(t.asignado_nombre)}` : (t.creado_nombre ? `de ${esc(t.creado_nombre)}` : '');
        const vence = t.vence_fmt ? `· ${esc(t.vence_fmt)}` : '';

        return `<div class="${classes}" onclick="verTarea(${t.id})">
            <div class="tk-card-head">
                <span class="prio-badge ${t.prioridad}">${PRIO_LABEL[t.prioridad]}</span>
                ${vencidaBadge}
                <span class="mt-time">${esc(t.hace)}</span>
            </div>
            <div class="tk-card-body">
                <div class="tk-titulo">${esc(t.titulo)}</div>
                ${t.descripcion ? `<div class="tk-desc-prev">${esc(t.descripcion)}</div>` : ''}
                <div class="tk-meta-line">${[asig, vence, kom].filter(Boolean).join(' ')}</div>
            </div>
        </div>`;
    }).join('');
}

// ── Ver tarea ────────────────────────────────────────────────────
async function verTarea(id) {
    state.tareaId = id;
    renderTareaLista();
    const t = state.tareas.find(t => t.id === id);
    if (!t) return;
    showTareaPanel();
    renderTareaDetalle(t);
}

function cerrarTarea() {
    state.tareaId = null;
    document.getElementById('tk-panel-empty').style.display   = '';
    document.getElementById('tk-panel-content').style.display = 'none';
    renderTareaLista();
}

function showTareaPanel() {
    document.getElementById('tk-panel-empty').style.display   = 'none';
    document.getElementById('tk-panel-content').style.display = 'flex';
}

// ── Detalle de tarea ─────────────────────────────────────────────
function renderTareaDetalle(t) {
    const pc = document.getElementById('tk-panel-content');

    const completada = t.estado === 'completada';
    const vencidaClass = t.vencida ? 'tk-vencida' : '';
    const descHtml = t.descripcion
        ? `<div class="tarea-desc-detail">${esc(t.descripcion)}</div>` : '';

    // Meta grid
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
    if (t.ref_id)    meta += `<div class="lbl">Conversación</div><div class="val"><a href="#" onclick="irConvWA(${t.ref_id})" style="color:var(--info);">Ver conversación →</a></div>`;

    // Comentarios
    const komItems = t.comentarios.map(c => `
        <div class="kom-item">
            <div class="kom-head">
                <span class="kom-autor">${esc(c.usuario || '—')}</span>
                <span class="kom-time">${esc(c.hora)}</span>
                ${c.user_id === ME_ID ? `<button class="kom-del" onclick="borrarComentario(${t.id}, ${c.id})" title="Eliminar">✕</button>` : ''}
            </div>
            <div class="kom-body">${linkify(c.contenido)}</div>
        </div>`).join('');

    const komArrow = state.tkKomOpen ? 'open' : '';
    const komList  = state.tkKomOpen ? '' : 'display:none';

    pc.innerHTML = `
        <div class="panel-head">
            <button onclick="cerrarTarea()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:0 4px;">←</button>
            <div style="flex:1;min-width:0;overflow:hidden;">
                <div class="panel-head-name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(t.titulo)}</div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0;">
                <button class="btn" onclick="editarTarea(${t.id})">✏ Editar</button>
                <button class="btn ${completada ? '' : 'btn-ok'}" onclick="toggleCompletar(${t.id})">
                    ${completada ? '↩ Reabrir' : '✓ Completar'}
                </button>
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
    state.tkKomOpen = !state.tkKomOpen;
    const list  = document.getElementById('kom-list');
    const arrow = document.getElementById('kom-arrow');
    if (list)  list.style.display = state.tkKomOpen ? '' : 'none';
    if (arrow) arrow.classList.toggle('open', state.tkKomOpen);
}

// ── Ir a conversación WA vinculada ───────────────────────────────
function irConvWA(convId) {
    setTab('conversaciones');
    verItem(convId);
}

// ── Comentarios ──────────────────────────────────────────────────
async function agregarComentario(tareaId) {
    const inp     = document.getElementById('kom-input');
    const contenido = inp?.value?.trim();
    if (!contenido) return;
    inp.value = '';
    try {
        const res = await post(`/tareas/${tareaId}/comentario`, { contenido });
        // Agregar al state
        const t = state.tareas.find(t => t.id === tareaId);
        if (t) {
            t.comentarios.push(res.data);
            renderTareaDetalle(t);
            // Restaurar foco en el input
            setTimeout(() => {
                const ni = document.getElementById('kom-input');
                if (ni) ni.focus();
                // Scroll al final del kom-list
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
            renderTareaDetalle(t);
        }
        toast('Comentario eliminado');
    } catch(e) { toast('Error', 'error'); }
}

// ── Completar / Reabrir ──────────────────────────────────────────
async function toggleCompletar(id) {
    const t = state.tareas.find(t => t.id === id);
    if (!t) return;
    const nuevoEstado = t.estado === 'completada' ? 'pendiente' : 'completada';
    try {
        await patch(`/tareas/${id}`, { estado: nuevoEstado });
        t.estado  = nuevoEstado;
        t.vencida = false;
        renderTareaDetalle(t);
        renderTareaLista();
        toast(nuevoEstado === 'completada' ? 'Completada ✓' : 'Reabierta');
        // Si estamos en filtro "activas" y la completamos, en el próximo fetch desaparecerá
    } catch(e) { toast('Error', 'error'); }
}

// ── Eliminar tarea ───────────────────────────────────────────────
async function eliminarTarea(id) {
    if (!confirm('¿Eliminár esta tarea?')) return;
    try {
        await del(`/tareas/${id}`);
        state.tareas = state.tareas.filter(t => t.id !== id);
        cerrarTarea();
        renderTareaLista();
        toast('Tarea eliminada');
    } catch(e) { toast('Error al eliminar', 'error'); }
}

// ── Filtros ──────────────────────────────────────────────────────
function setFiltro(f) {
    state.tkFiltro = f;
    document.getElementById('filt-mias').classList.toggle('active', f === 'mias');
    document.getElementById('filt-todas').classList.toggle('active', f === 'todas');
    fetchTareas();
}

function setEstadoFiltro(e) {
    state.tkEstado = e;
    document.getElementById('filt-activas').classList.toggle('active', e === 'activas');
    document.getElementById('filt-completadas').classList.toggle('active', e === 'completadas');
    fetchTareas();
}

// ════════════════════════════════════════════════════════════════
// ── FORMULARIO DE TAREA (nuevo / editar)
// ════════════════════════════════════════════════════════════════
let _formPrio   = 'normal';
let _formEditId = null;

function nuevaTarea() {
    _formEditId = null;
    _formPrio   = 'normal';
    state.tareaId = 'nueva';
    renderTareaLista();
    showTareaPanel();
    renderTareaForm(null);
}

function editarTarea(id) {
    const t = state.tareas.find(t => t.id === id);
    if (!t) return;
    _formEditId = id;
    _formPrio   = t.prioridad || 'normal';
    renderTareaForm(t);
}

function renderTareaForm(t) {
    const pc     = document.getElementById('tk-panel-content');
    const titulo  = t ? esc(t.titulo) : '';
    const desc    = t ? esc(t.descripcion || '') : '';
    const vence   = t?.vence_at || '';

    const usrOpts = USUARIOS.map(u =>
        `<option value="${u.id}" ${t?.asignada_a === u.id ? 'selected' : ''}>${esc(u.nombre_completo)}</option>`
    ).join('');

    const convOpts = CONVERSACIONES.map(c =>
        `<option value="${c.id}" ${t?.ref_id === c.id ? 'selected' : ''}>${esc(c.label)}</option>`
    ).join('');

    pc.innerHTML = `
        <div class="panel-head">
            <button onclick="${t ? `verTarea(${t.id})` : 'cerrarTarea()'}" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:0 4px;">←</button>
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
            if (t) { state.tareaId = _formEditId; renderTareaDetalle(t); }
        } else {
            const res = await post('/tareas', payload);
            toast('Tarea creada ✓');
            state.tareas.unshift(res.data);
            state.tareaId = res.data.id;
            renderTareaLista();
            renderTareaDetalle(res.data);
        }
    } catch(e) { toast('Error al guardar', 'error'); }
}

// ════════════════════════════════════════════════════════════════
// ── HELPERS
// ════════════════════════════════════════════════════════════════
function esc(s) { if (!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function linkify(s) {
    if (!s) return '';
    return s.split(/(https?:\/\/[^\s<>"]+)/g).map((p, i) => i % 2 === 1
        ? `<a href="${esc(p)}" target="_blank" rel="noopener" style="color:var(--info);text-decoration:underline;word-break:break-all;">${esc(p)}</a>`
        : esc(p)
    ).join('');
}
function scrollBottom() { const el = document.getElementById('msg-list'); if (el) requestAnimationFrame(() => el.scrollTop = el.scrollHeight); }

// ── Init ──────────────────────────────────────────────────────────
renderLista();
setTimeout(fetchConversaciones, 5000);
setInterval(fetchConversaciones, 10000);
setInterval(() => { if (state.tab === 'tareas') fetchTareas(); }, 15000);

// Deep-link: /mis-tareas?tarea_id=N abre el tab Tareas y muestra el detalle.
(function() {
    const m = window.location.search.match(/[?&]tarea_id=(\d+)/);
    if (!m) return;
    const tid = parseInt(m[1]);
    setTab('tareas');
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
