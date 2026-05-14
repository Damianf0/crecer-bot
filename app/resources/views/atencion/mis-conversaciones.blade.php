@extends('layouts.app')
@section('title', 'Mis conversaciones')

@push('styles')
<style>
.mc-root   { height: calc(100vh - 100px); display: flex; flex-direction: column; }
.mc-header { display: flex; align-items: center; gap: 12px; flex-shrink: 0; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.mc-title  { font-size: 17px; font-weight: 700; }
.mc-count  { background: var(--accent); color: #fff; border-radius: 10px; padding: 1px 8px; font-size: 12px; font-weight: 700; }
.mc-layout { display: flex; flex: 1; min-height: 0; overflow: hidden; padding-top: 12px; }

.mc-list  { width: 320px; flex-shrink: 0; overflow-y: auto; padding-right: 10px; }
.mc-empty { text-align: center; padding: 60px 0; color: var(--muted); font-size: 14px; }

.mc-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-left: 3px solid transparent;
    border-radius: 8px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: border-color .12s, box-shadow .12s;
}
.mc-card:hover           { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.mc-card.urgente         { border-left-color: var(--accent); }
.mc-card.selected        { border-left-color: var(--info); background: rgba(88,166,255,.04); }
.mc-card.selected.urgente { border-left-color: var(--accent); background: rgba(192,39,58,.04); }
.mc-card-head { padding: 7px 12px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 6px; font-size: 11px; }
.badge-urg  { color: var(--accent); font-weight: 700; font-size: 11px; }
.badge-nl   { background: var(--accent); color: #fff; border-radius: 10px; padding: 1px 6px; font-size: 11px; font-weight: 700; }
.mc-time    { margin-left: auto; color: var(--muted); font-size: 11px; }
.mc-card-body { padding: 10px 12px; }
.mc-contact { font-weight: 600; font-size: 14px; margin-bottom: 3px; }
.mc-preview { font-size: 12px; color: var(--muted); line-height: 1.4; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.mc-preview.resumen { color: var(--info); font-style: italic; }

.mc-panel {
    flex: 1;
    min-width: 0;
    border-left: 1px solid var(--border);
    padding-left: 16px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.mc-panel-empty {
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

.btn { padding: 5px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; border: 1px solid var(--border); background: transparent; color: var(--muted); white-space: nowrap; transition: .12s; display: inline-flex; align-items: center; gap: 4px; }
.btn:hover         { color: var(--text); border-color: var(--text); }
.btn-urg           { border-color: color-mix(in srgb, var(--accent) 35%, transparent); color: var(--accent); }
.btn-urg:hover     { background: color-mix(in srgb, var(--accent) 12%, transparent); }
.btn-urg.on        { background: color-mix(in srgb, var(--accent) 15%, transparent); }
.btn-ok            { border-color: color-mix(in srgb, var(--success) 35%, transparent); color: var(--success); }
.btn-ok:hover      { background: color-mix(in srgb, var(--success) 12%, transparent); }
.btn-del           { border-color: color-mix(in srgb, var(--info) 35%, transparent); color: var(--info); position: relative; }
.btn-del:hover     { background: color-mix(in srgb, var(--info) 12%, transparent); }

.del-dropdown { position: absolute; top: calc(100% + 4px); right: 0; background: var(--card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 6px 20px rgba(0,0,0,.12); min-width: 180px; z-index: 100; display: none; }
.del-dropdown.open { display: block; }
.del-opt { padding: 8px 14px; font-size: 13px; cursor: pointer; color: var(--text); transition: background .1s; }
.del-opt:first-child { border-radius: 8px 8px 0 0; }
.del-opt:last-child  { border-radius: 0 0 8px 8px; }
.del-opt:hover       { background: var(--bg); }

.resumen-banner { padding: 8px 12px; background: color-mix(in srgb, var(--info) 7%, transparent); border-bottom: 1px solid color-mix(in srgb, var(--info) 18%, transparent); font-size: 12px; color: var(--text); flex-shrink: 0; line-height: 1.4; }
.resumen-banner span { font-size: 10px; font-weight: 700; color: var(--info); letter-spacing: .5px; margin-right: 8px; }

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

.panel-input { border-top: 1px solid var(--border); padding: 10px 0 0; flex-shrink: 0; }
.mode-btn { font-size: 11px; padding: 3px 10px; border-radius: 12px; border: 1px solid var(--border); background: transparent; color: var(--muted); cursor: pointer; }
.mode-btn.active { border-color: var(--accent); background: rgba(192,39,58,.1); color: var(--accent); }

/* Dropdown de respuestas rápidas */
.rr-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    min-width: 260px;
    max-width: 380px;
    max-height: 320px;
    overflow-y: auto;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,.18);
    z-index: 50;
    padding: 4px 0;
}
.rr-menu-item { padding: 8px 14px; font-size: 13px; cursor: pointer; color: var(--text); transition: background .12s; border-bottom: 1px solid color-mix(in srgb, var(--border) 50%, transparent); }
.rr-menu-item:last-child { border-bottom: none; }
.rr-menu-item:hover { background: var(--bg); }
.rr-menu-item .rr-titulo  { font-weight: 600; }
.rr-menu-item .rr-preview { font-size: 11px; color: var(--muted); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rr-menu-empty { padding: 14px; font-size: 12px; color: var(--muted); text-align: center; }
.rr-menu-foot  { padding: 6px 14px; font-size: 11px; color: var(--muted); border-top: 1px solid var(--border); }
.rr-menu-foot a { color: var(--info); text-decoration: none; }
.rr-menu-foot a:hover { text-decoration: underline; }
.input-row { display: flex; gap: 8px; align-items: flex-end; }
.msg-textarea { flex: 1; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 8px 11px; color: var(--text); font-size: 13px; resize: none; min-height: 50px; font-family: inherit; }
.msg-textarea:focus { outline: none; border-color: var(--info); }
.send-btn { padding: 0 16px; background: var(--accent); border: none; color: #fff; border-radius: 8px; font-size: 13px; cursor: pointer; height: 38px; }

.refresh-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--success); display: inline-block; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

.toast { position:fixed;bottom:90px;right:24px;z-index:9999;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500;opacity:0;transform:translateY(8px);transition:.2s;pointer-events:none; }
.toast.show { opacity:1;transform:none; }
.toast.ok    { background:rgba(63,185,80,.15);color:var(--success);border:1px solid rgba(63,185,80,.3); }
.toast.error { background:rgba(248,81,73,.15);color:var(--error);border:1px solid rgba(248,81,73,.3); }
</style>
@endpush

@section('content')
<div class="mc-root">

    <div class="mc-header">
        <span class="mc-title">Mis conversaciones</span>
        <span class="mc-count" id="cnt-conv">{{ count($items) }}</span>
        <span class="refresh-dot" title="Se actualiza automáticamente"></span>
        <a href="/centro-tareas" style="margin-left:auto;font-size:12px;color:var(--muted);text-decoration:none;padding:5px 10px;border:1px solid var(--border);border-radius:6px;">→ Centro de tareas</a>
        <a href="/atencion" style="font-size:12px;color:var(--muted);text-decoration:none;padding:5px 10px;border:1px solid var(--border);border-radius:6px;">↗ Ver atención</a>
    </div>

    <div class="mc-layout">
        <div class="mc-list" id="mc-list"></div>
        <div class="mc-panel" id="mc-panel">
            <div class="mc-panel-empty" id="panel-empty">
                <span style="font-size:32px;opacity:.25;">◫</span>
                <span>Seleccioná una conversación</span>
            </div>
            <div id="panel-conv" style="display:none;flex:1;flex-direction:column;overflow:hidden;"></div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CSRF     = '{{ csrf_token() }}';
const ME_ID    = {{ auth()->id() }};
const USUARIOS = @json($usuarios);

let state = {
    items:    @json($items),
    panelId:  null,
    panelArea: null,    // área de la conv abierta — usada por el dropdown 📋
    modo:     'mensaje',
    segOpen:  true,
};

// Cache por área para no traer la misma lista varias veces.
const RR_CACHE = {};
async function cargarRespuestasRapidas(area) {
    if (!area) return [];
    if (RR_CACHE[area]) return RR_CACHE[area];
    try {
        const r = await fetch('/atencion/respuestas-rapidas/' + area, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!r.ok) return [];
        const d = await r.json();
        RR_CACHE[area] = d.data || [];
        return RR_CACHE[area];
    } catch { return []; }
}

// ── API ──────────────────────────────────────────────────────────
async function api(method, url, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(url, opts);
    if (!r.ok) throw new Error(r.status);
    return r.json();
}
const get  = url      => api('GET', url);
const post = (url, b) => api('POST', url, b);

function toast(msg, tipo = 'ok') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = `toast ${tipo} show`;
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 3000);
}

// ── Polling ──────────────────────────────────────────────────────
async function fetchConversaciones() {
    try {
        const data   = await get('/mis-conversaciones/data');
        const nuevos = data.data || [];
        if (state.panelId && !nuevos.find(i => i.id === state.panelId)) cerrarPanel();
        state.items = nuevos;
        renderLista();
    } catch(e) {}
}

// ── Lista ────────────────────────────────────────────────────────
let _lastHash = '';
function renderLista() {
    const hash = state.items.map(i => `${i.id}${i.urgente}${i.no_leidos}${state.panelId === i.id}`).join('|');
    if (hash === _lastHash) return;
    _lastHash = hash;

    document.getElementById('cnt-conv').textContent = state.items.length;
    const list = document.getElementById('mc-list');

    if (!state.items.length) {
        list.innerHTML = '<div class="mc-empty">No tenés conversaciones asignadas 🎉</div>';
        return;
    }

    list.innerHTML = state.items.map(item => {
        const sel      = state.panelId === item.id;
        const classes  = ['mc-card', item.urgente ? 'urgente' : '', sel ? 'selected' : ''].filter(Boolean).join(' ');
        const badgeUrg = item.urgente ? '<span class="badge-urg">⚑</span>' : '';
        const badgeNL  = item.no_leidos > 0 ? `<span class="badge-nl">${item.no_leidos}</span>` : '';
        const isResumen = !!item.resumen && item.resumen !== '—';
        const preview   = esc(item.resumen && item.resumen !== '—' ? item.resumen : 'Sin mensajes');
        const previewClass = isResumen ? 'mc-preview resumen' : 'mc-preview';

        return `<div class="${classes}" onclick="verItem(${item.id})">
            <div class="mc-card-head">
                ${badgeUrg}
                <span style="color:var(--muted);font-size:11px;">💬 WhatsApp</span>
                ${badgeNL}
                <span class="mc-time">${esc(item.hace)}</span>
            </div>
            <div class="mc-card-body">
                <div class="mc-contact">${esc(item.contacto)}</div>
                <div class="${previewClass}">${preview}</div>
            </div>
        </div>`;
    }).join('');
}

// ── Panel ────────────────────────────────────────────────────────
async function verItem(id) {
    state.panelId = id;
    const item = state.items.find(i => i.id === id);
    state.panelArea = item?.area || null;
    // Prefetch las respuestas del área para que el dropdown abra al instante
    if (state.panelArea) cargarRespuestasRapidas(state.panelArea);
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
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px;position:relative;">
                <div style="display:flex;gap:6px;">
                    <button class="mode-btn ${state.modo==='mensaje'?'active':''}" onclick="setModo('mensaje')">Mensaje</button>
                    <button class="mode-btn ${state.modo==='nota'?'active':''}" onclick="setModo('nota')">Nota interna</button>
                </div>
                <div style="display:flex;gap:6px;align-items:center;position:relative;${state.modo==='nota'?'display:none':''}">
                    <button id="btn-rr" onclick="rrToggle(event)" title="Respuestas rápidas" style="font-size:12px;padding:3px 10px;border-radius:6px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;">📋 Respuestas ▾</button>
                    <button id="btn-adjuntar" onclick="document.getElementById('mc-file-input').click()" style="font-size:12px;padding:3px 10px;border-radius:6px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;">📎 Adjuntar</button>
                    <input type="file" id="mc-file-input" style="display:none" accept="image/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt" onchange="onFileChange(event)">
                    <div id="rr-menu" class="rr-menu" style="display:none;"></div>
                </div>
            </div>
            <div id="mc-file-preview" style="display:none;align-items:center;gap:8px;padding:6px 10px;background:var(--card);border:1px solid var(--border);border-radius:6px;margin-bottom:6px;font-size:12px;">
                <span id="mc-file-name" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                <button onclick="limpiarArchivo()" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px;padding:0;">✕</button>
            </div>
            <div class="input-row">
                <textarea class="msg-textarea" id="msg-input"
                    placeholder="${state.modo==='nota'?'Nota interna (no se envía al paciente)':'Escribir mensaje...'}"
                    onkeydown="if(event.ctrlKey&&event.key==='Enter')enviarMC(${id})"></textarea>
                <button class="send-btn" onclick="enviarMC(${id})">${state.modo==='nota'?'Guardar':'Enviar'}</button>
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

// ── Acciones ─────────────────────────────────────────────────────
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

// ── Input ────────────────────────────────────────────────────────
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

let mcArchivo = null;
function onFileChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    mcArchivo = file;
    const prev = document.getElementById('mc-file-preview');
    const name = document.getElementById('mc-file-name');
    if (prev) prev.style.display = 'flex';
    if (name) name.textContent = file.name;
}
function limpiarArchivo() {
    mcArchivo = null;
    const inp  = document.getElementById('mc-file-input');
    const prev = document.getElementById('mc-file-preview');
    if (inp)  inp.value = '';
    if (prev) prev.style.display = 'none';
}

async function enviarMC(id) {
    if (mcArchivo) { await enviarArchivoMC(id); return; }
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

async function enviarArchivoMC(id) {
    if (!mcArchivo || !id) return;
    const inp     = document.getElementById('msg-input');
    const caption = inp?.value?.trim() || '';
    const fd = new FormData();
    fd.append('conv_id', id);
    fd.append('archivo', mcArchivo);
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

// Auto-refresh del hilo abierto en el panel (cada 8s si pestaña visible).
// Sin esto, los mensajes entrantes a una conv abierta no aparecen hasta que
// el operador haga algo manual.
async function refrescarConvAbierta() {
    if (document.hidden || !state.panelId) return;

    // Si el usuario está escribiendo en el textarea, abortar — sino se le
    // borra lo que está tipeando cuando recargamos el panel (bug 14/05).
    const inpActual = document.getElementById('msg-input');
    const tieneFoco = inpActual && document.activeElement === inpActual;
    const textoActual = inpActual?.value ?? '';
    if (tieneFoco && textoActual.length > 0) return;

    const list = document.getElementById('msg-list');
    const estabaAlFondo = list
        ? (list.scrollHeight - list.scrollTop - list.clientHeight) < 80
        : true;
    try { await cargarConversacion(state.panelId); } catch (e) { return; }

    // Defensa adicional: si alcanzó a tipear algo durante el fetch, restaurarlo.
    const inpNuevo = document.getElementById('msg-input');
    if (inpNuevo && textoActual && !inpNuevo.value) inpNuevo.value = textoActual;

    const listNew = document.getElementById('msg-list');
    if (listNew && estabaAlFondo) listNew.scrollTop = listNew.scrollHeight;
}
setInterval(refrescarConvAbierta, 8000);

// ── Respuestas rápidas — dropdown 📋 ─────────────────────────────
async function rrToggle(ev) {
    if (ev) ev.stopPropagation();
    const menu = document.getElementById('rr-menu');
    if (!menu) return;
    if (menu.style.display === 'block') { menu.style.display = 'none'; return; }
    await rrRender();
    menu.style.display = 'block';
    setTimeout(() => document.addEventListener('click', rrCerrarFuera, { once: true }), 0);
}
function rrCerrarFuera(e) {
    const menu = document.getElementById('rr-menu');
    if (!menu) return;
    if (!menu.contains(e.target) && e.target.id !== 'btn-rr') menu.style.display = 'none';
    else setTimeout(() => document.addEventListener('click', rrCerrarFuera, { once: true }), 0);
}
async function rrRender() {
    const menu = document.getElementById('rr-menu');
    if (!menu) return;
    const items = await cargarRespuestasRapidas(state.panelArea);
    if (!items.length) {
        menu.innerHTML = `<div class="rr-menu-empty">Sin respuestas rápidas para esta área.</div>
            <div class="rr-menu-foot"><a href="/admin/respuestas-rapidas" target="_blank">+ Agregar</a></div>`;
        return;
    }
    menu.innerHTML = items.map(r =>
        `<div class="rr-menu-item" onclick="rrInsertar(${r.id})">
            <div class="rr-titulo">${esc(r.titulo)}</div>
            <div class="rr-preview">${esc(r.texto.slice(0, 80))}</div>
        </div>`
    ).join('')
    + `<div class="rr-menu-foot"><a href="/admin/respuestas-rapidas" target="_blank">Administrar respuestas</a></div>`;
}
function rrInsertar(id) {
    const items = RR_CACHE[state.panelArea] || [];
    const r = items.find(x => x.id === id);
    if (!r) return;
    const inp = document.getElementById('msg-input');
    if (!inp) return;
    inp.value = r.texto;
    inp.focus();
    document.getElementById('rr-menu').style.display = 'none';
}

// Deep-link: /mis-conversaciones?conv_id=N abre la conversación
(function() {
    const m = window.location.search.match(/[?&]conv_id=(\d+)/);
    if (!m) return;
    const cid = parseInt(m[1]);
    if (state.items.find(i => i.id === cid)) verItem(cid);
})();
</script>
@endsection
