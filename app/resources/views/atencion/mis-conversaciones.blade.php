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
/* Eventos intercalados — mismo formato que el divider de "✓ Resuelta" */
.msg-evento-inline { display: flex; align-items: center; gap: 10px; margin: 14px 4px 8px; color: var(--muted); font-size: 11.5px; }
.msg-evento-inline .line { flex: 1; height: 1px; background: color-mix(in srgb, var(--success) 40%, var(--border)); }
.msg-evento-inline .label { display: inline-flex; align-items: center; gap: 5px; padding: 2px 10px; border-radius: 10px; background: color-mix(in srgb, var(--success) 12%, var(--bg)); border: 1px solid color-mix(in srgb, var(--success) 30%, var(--border)); color: var(--success); font-weight: 600; }
.msg-evento-inline strong { color: var(--success); font-weight: 700; }
.msg-evento-inline .ev-time { opacity: .7; margin-left: 6px; font-size: 10.5px; font-weight: 500; }
.msg-bubble { max-width: 72%; padding: 7px 11px; border-radius: 12px; font-size: 13px; line-height: 1.45; }
.msg-bubble.in   { background: var(--bg); border: 1px solid var(--border); border-radius: 0 12px 12px 12px; }
.msg-bubble.out  { background: color-mix(in srgb, var(--accent) 18%, transparent); border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent); border-radius: 12px 0 12px 12px; }
.msg-bubble.nota { background: color-mix(in srgb, var(--warning) 12%, transparent); border: 1px solid color-mix(in srgb, var(--warning) 28%, transparent); color: var(--warning); border-radius: 8px; font-size: 12px; max-width: 80%; }
.msg-time  { font-size: 11px; color: var(--muted); margin-top: 3px; }
.msg-time.right { text-align: right; }
.msg-quoted {
    background: color-mix(in srgb, var(--text) 6%, transparent);
    border-left: 3px solid var(--accent);
    border-radius: 4px;
    padding: 4px 8px;
    margin-bottom: 5px;
    font-size: 12px;
    line-height: 1.35;
    max-width: 100%;
    overflow: hidden;
}
.msg-bubble.in  .msg-quoted { border-left-color: var(--info); }
.msg-bubble.out .msg-quoted { border-left-color: var(--success, #4ade80); background: color-mix(in srgb, var(--accent) 10%, transparent); }
.msg-quoted-autor   { font-weight: 600; color: var(--accent); margin-bottom: 1px; }
.msg-bubble.in  .msg-quoted .msg-quoted-autor { color: var(--info); }
.msg-quoted-preview { color: var(--muted); overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }

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

/* Dropdown de respuestas rápidas — abre hacia ARRIBA (el botón está en el
   footer del panel; hacia abajo quedaría tapado por el borde del panel). */
.rr-menu {
    position: absolute;
    bottom: calc(100% + 6px);
    right: 0;
    min-width: 260px;
    max-width: 380px;
    max-height: 320px;
    overflow-y: auto;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 -8px 24px rgba(0,0,0,.18);
    z-index: 200;
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
    const datos = await get(`/atencion/conversacion/${id}`);
    _renderConversacion(id, datos);
}

// Render puro: separa el fetch del replace para que refrescarConvAbierta()
// pueda capturar el estado del composer (textarea, foco, cursor) JUSTO antes
// del replace — sino se pierde lo que el usuario tipeó durante el fetch.
function _renderConversacion(id, datos) {
    const { conv, mensajes, eventos } = datos;
    const item = state.items.find(i => i.id === id) || {};
    const panelConv = document.getElementById('panel-conv');

    const resumenHtml = conv.resumen
        ? `<div class="resumen-banner"><span>RESUMEN IA</span>${esc(conv.resumen)}</div>` : '';

    // Merge cronológico de mensajes y eventos: cada evento (tomada/delegada/
    // resuelta/urgente/etc.) se renderiza como "system message" inline en su
    // posición temporal, en lugar de aparecer todos juntos abajo. Ver
    // renderEventoInline() definida más abajo.
    const items = mergearMensajesEventos(mensajes || [], eventos || []);
    let lastFecha = null;
    const msgHtml = items.map(it => {
        let out = '';
        if (it.__k === 'evt') return renderEventoInline(it);
        const m = it;
        if (m.fecha !== lastFecha) { out += `<div class="msg-date">${m.fecha}</div>`; lastFecha = m.fecha; }
        const citado = renderQuoted(m);
        if (m.direccion === 'nota_interna') {
            out += `<div class="msg-wrap nota"><div class="msg-bubble nota">📝 ${linkify(m.contenido)}</div></div>`;
        } else if (m.direccion === 'entrante') {
            out += `<div class="msg-wrap in"><div><div class="msg-bubble in">${citado}${renderMsgCuerpo(m)}</div><div class="msg-time">${m.hora}</div></div></div>`;
        } else {
            out += `<div class="msg-wrap out"><div><div class="msg-bubble out">${citado}${renderMsgCuerpo(m)}</div><div class="msg-time right">${m.hora}</div></div></div>`;
        }
        return out;
    }).join('');

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
                <button class="btn" onclick="abrirModalReenvio(${id})" title="Reenviar el hilo a otro contacto y archivar">📤 Reenviar</button>
                <button class="btn btn-ok" onclick="resolver(${id})">✓ Resuelto</button>
            </div>
        </div>
        ${resumenHtml}
        <div class="msg-list" id="msg-list">${msgHtml || '<div style="text-align:center;color:var(--muted);padding:32px;font-size:13px;">Sin mensajes aún</div>'}</div>
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

// ── Eventos intercalados en el hilo ──────────────────────────────
// Renderiza un evento como "system message" inline en el msg-list, en su
// posición cronológica. Reemplaza a la tira de chips (renderSeguimiento) que
// quedó como legacy y ya no se incrusta en el panel.
function renderEventoInline(e) {
    const TIPOS = {
        tomada:        { icon: '🟢', label: (e) => `Tomó <strong>${esc(e.usuario||'—')}</strong>` },
        delegada:      { icon: '📤', label: (e) => `<strong>${esc(e.usuario||'—')}</strong> delegó a <strong>${esc(e.destino||'—')}</strong>` },
        resuelta:      { icon: '✅', label: (e) => `Resolvió <strong>${esc(e.usuario||'—')}</strong>` },
        reabierta:     { icon: '🔁', label: (e) => `Reabrió <strong>${esc(e.usuario||'—')}</strong>` },
        urgente_on:    { icon: '⚑',  label: (e) => `<strong>${esc(e.usuario||'—')}</strong> marcó urgente` },
        urgente_off:   { icon: '⚐',  label: (e) => `<strong>${esc(e.usuario||'—')}</strong> sacó urgencia` },
        reenviada:     { icon: '🔁', label: (e) => `<strong>${esc(e.usuario||'—')}</strong> reenvió y archivó` },
        derivada_area: { icon: '↗',  label: (e) => `<strong>${esc(e.usuario||'—')}</strong> derivó a otra área` },
    };
    const t = TIPOS[e.tipo] || { icon: '•', label: () => esc(e.tipo) };
    return `<div class="msg-evento-inline" title="${esc(e.fecha||'')}"><div class="line"></div><div class="label">${t.icon} ${t.label(e)}<span class="ev-time">${esc(e.hora||'')}</span></div><div class="line"></div></div>`;
}

// Combina mensajes y eventos en una sola lista ordenada por timestamp.
function mergearMensajesEventos(mensajes, eventos) {
    const items = [];
    for (const m of mensajes) items.push(Object.assign({}, m, { __k: 'msg' }));
    for (const e of eventos)  items.push(Object.assign({}, e, { __k: 'evt' }));
    items.sort((a, b) => (a.ts || 0) - (b.ts || 0));
    return items;
}

// ── Seguimiento (LEGACY) ────────────────────────────────────────
// Tira de chips horizontal que el panel ya no muestra (los eventos van
// inline ahora). La función queda hasta que /centro-tareas se migre
// también — ahí sigue usándose.
const TIPO_SEG = {
    tomada:      { icon: '🟢', label: u => `Tomada por ${u}` },
    delegada:    { icon: '📤', label: (u, d) => `Delegada por ${u} → ${d}` },
    resuelta:    { icon: '✅', label: u => `Resuelta por ${u}` },
    reabierta:   { icon: '🔁', label: u => `Reabierta por ${u}` },
    urgente_on:  { icon: '⚑',  label: u => `Urgente activado por ${u}` },
    urgente_off: { icon: '⚐',  label: u => `Urgente desactivado por ${u}` },
    reenviada:   { icon: '🔁', label: u => `Reenviada y archivada por ${u}` },
    derivada_area: { icon: '↗', label: u => `Derivada a otra área por ${u}` },
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

function renderQuoted(m) {
    if (!m.quoted || !m.quoted.preview) return '';
    const autor = m.quoted.autor ? esc(m.quoted.autor) : 'Mensaje citado';
    const preview = esc(m.quoted.preview).slice(0, 180);
    return `<div class="msg-quoted">
        <div class="msg-quoted-autor">${autor}</div>
        <div class="msg-quoted-preview">${preview}</div>
    </div>`;
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

    // Fetch primero, sin tocar DOM. El operador puede seguir tipeando.
    const idAlFetch = state.panelId;
    let datos;
    try {
        datos = await get(`/atencion/conversacion/${idAlFetch}`);
    } catch (e) { return; }
    if (state.panelId !== idAlFetch) return;  // cambió de panel mientras esperaba

    // Captura JUSTO antes del replace: incluye lo que el operador tipeó
    // durante el fetch.
    const inpAntes = document.getElementById('msg-input');
    const textoAntes = inpAntes?.value ?? '';
    const teniaFoco = inpAntes && document.activeElement === inpAntes;
    const cursorAntes = (inpAntes && teniaFoco)
        ? { start: inpAntes.selectionStart, end: inpAntes.selectionEnd }
        : null;

    const list = document.getElementById('msg-list');
    const scrollAntes   = list ? list.scrollTop : 0;
    const estabaAlFondo = list
        ? (list.scrollHeight - list.scrollTop - list.clientHeight) < 80
        : true;

    _renderConversacion(idAlFetch, datos);

    // Restaurar composer
    const inpNuevo = document.getElementById('msg-input');
    if (inpNuevo && textoAntes) {
        inpNuevo.value = textoAntes;
        if (teniaFoco) {
            inpNuevo.focus();
            if (cursorAntes) {
                try { inpNuevo.setSelectionRange(cursorAntes.start, cursorAntes.end); } catch (_) {}
            }
        }
    }

    // Si hay un archivo adjunto, su preview se perdió con el replace. Restaurarlo.
    if (typeof _archivoSeleccionado !== 'undefined' && _archivoSeleccionado) {
        const prev = document.getElementById('file-preview');
        const nameEl = document.getElementById('file-preview-name');
        const iconEl = document.getElementById('file-preview-icon');
        if (prev && nameEl && iconEl) {
            const f = _archivoSeleccionado;
            const icons = { 'image': '🖼️', 'video': '🎬', 'audio': '🎵', 'application/pdf': '📕' };
            iconEl.textContent = icons[f.type.split('/')[0]] || icons[f.type] || '📄';
            nameEl.textContent = f.name;
            prev.style.display = 'flex';
        }
    }

    // Si estaba al fondo, ir al fondo (sigue leyendo nuevos). Si no, restaurar
    // la posición exacta para no perder la línea de lectura.
    const listNew = document.getElementById('msg-list');
    if (listNew) {
        if (estabaAlFondo) {
            listNew.scrollTop = listNew.scrollHeight;
        } else {
            listNew.scrollTop = scrollAntes;
        }
    }
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

// ── Reenvío de conversación a contacto externo ─────────────────────────
let _rxConvId = null;
let _rxContacto = null;
let _rxBuscarTimer = null;

function abrirModalReenvio(convId) {
    _rxConvId = convId;
    _rxContacto = null;
    document.getElementById('rx-buscar').value = '';
    document.getElementById('rx-comentario').value = '';
    document.getElementById('rx-resultados').style.display = 'none';
    document.getElementById('rx-seleccionado').style.display = 'none';
    document.getElementById('rx-confirmar').disabled = true;
    document.getElementById('modal-reenvio').style.display = 'flex';
    setTimeout(() => document.getElementById('rx-buscar').focus(), 50);
}

function cerrarModalReenvio() {
    document.getElementById('modal-reenvio').style.display = 'none';
}

function rxBuscarDebounced() {
    clearTimeout(_rxBuscarTimer);
    _rxBuscarTimer = setTimeout(rxBuscar, 250);
}

async function rxBuscar() {
    const q = document.getElementById('rx-buscar').value.trim();
    const cont = document.getElementById('rx-resultados');
    if (q.length < 2) { cont.style.display = 'none'; return; }
    try {
        const r = await fetch('/atencion/contactos/buscar?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const d = await r.json();
        const items = d.data || [];
        if (!items.length) {
            cont.innerHTML = '<div style="padding:10px 14px;font-size:12px;color:var(--muted);">Sin resultados</div>';
            cont.style.display = 'block';
            return;
        }
        cont.innerHTML = items.map(c => `
            <div onclick="rxElegir(${c.id}, ${JSON.stringify(c.nombre).replace(/"/g, '&quot;')}, ${JSON.stringify(c.telefono || '').replace(/"/g, '&quot;')}, ${c.es_grupo ? 1 : 0})"
                 style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--border);font-size:13px;"
                 onmouseover="this.style.background='var(--surface)'"
                 onmouseout="this.style.background='transparent'">
                <div style="font-weight:600;">${esc(c.nombre)}${c.es_grupo ? ' <span style="font-size:9px;background:color-mix(in srgb,var(--accent) 18%,transparent);color:var(--accent);padding:1px 5px;border-radius:3px;text-transform:uppercase;font-weight:700;">grupo</span>' : ''}</div>
                <div style="font-size:11px;color:var(--muted);">${esc(c.telefono || c.wa_id || '—')}</div>
            </div>`).join('');
        cont.style.display = 'block';
    } catch {
        cont.innerHTML = '<div style="padding:10px;color:var(--error);">Error</div>';
        cont.style.display = 'block';
    }
}

function rxElegir(id, nombre, telefono, esGrupo) {
    _rxContacto = { id, nombre, telefono, es_grupo: !!esGrupo };
    document.getElementById('rx-resultados').style.display = 'none';
    document.getElementById('rx-buscar').value = '';
    const sel = document.getElementById('rx-seleccionado');
    sel.style.display = 'block';
    document.getElementById('rx-sel-nombre').textContent = nombre + (esGrupo ? ' (grupo)' : '');
    document.getElementById('rx-sel-tel').textContent = telefono || '';
    document.getElementById('rx-confirmar').disabled = false;
}

async function confirmarReenvio() {
    if (!_rxConvId || !_rxContacto) return;
    const btn = document.getElementById('rx-confirmar');
    btn.disabled = true;
    btn.textContent = 'Reenviando...';
    try {
        const r = await fetch(`/atencion/conversacion/${_rxConvId}/reenviar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ contacto_id: _rxContacto.id, comentario: document.getElementById('rx-comentario').value.trim() || null }),
        });
        const d = await r.json();
        if (!r.ok || !d.ok) throw new Error(d.error || ('HTTP ' + r.status));
        toast(`Reenviado a ${d.destino} ✓`);
        cerrarModalReenvio();
        state.items = state.items.filter(i => i.id !== _rxConvId);
        cerrarPanel();
        renderLista();
    } catch (e) {
        toast('Error al reenviar: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Reenviar y archivar';
    }
}
</script>

{{-- Modal "Reenviar conversación a otro contacto" --}}
<div id="modal-reenvio" style="display:none;position:fixed;inset:0;z-index:9100;background:rgba(0,0,0,.6);align-items:center;justify-content:center;" onclick="cerrarModalReenvio()">
    <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:22px;width:min(520px,calc(100vw - 32px));max-height:calc(100vh - 64px);overflow-y:auto;" onclick="event.stopPropagation()">
        <div style="font-weight:700;font-size:15px;margin-bottom:6px;">📤 Reenviar conversación</div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:14px;line-height:1.5;">
            Envía el hilo completo a un contacto y archiva esta conversación. El destinatario recibe el resumen como un mensaje WA común; no entra al sistema.
        </div>

        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px;">Buscar contacto destino</div>
        <input type="text" id="rx-buscar" placeholder="Escribí nombre o teléfono…" autocomplete="off"
            oninput="rxBuscarDebounced()"
            style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:8px 11px;color:var(--text);font-size:13px;">
        <div id="rx-resultados" style="margin-top:6px;max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:6px;background:var(--bg);display:none;"></div>

        <div id="rx-seleccionado" style="margin-top:10px;padding:9px 12px;background:color-mix(in srgb,var(--info) 8%,transparent);border:1px solid color-mix(in srgb,var(--info) 30%,transparent);border-radius:6px;display:none;">
            <div style="font-size:11px;color:var(--info);text-transform:uppercase;letter-spacing:.4px;font-weight:700;margin-bottom:2px;">Destino</div>
            <div id="rx-sel-nombre" style="font-weight:600;font-size:13px;"></div>
            <div id="rx-sel-tel" style="font-size:12px;color:var(--muted);"></div>
        </div>

        <div style="margin-top:14px;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px;">
            Comentario (opcional)
        </div>
        <textarea id="rx-comentario" placeholder="Ej: Te paso esto que necesita tu visto bueno"
            style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:8px 11px;color:var(--text);font-size:13px;resize:vertical;min-height:60px;font-family:inherit;"></textarea>

        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
            <button class="btn" onclick="cerrarModalReenvio()">Cancelar</button>
            <button class="btn btn-ok" id="rx-confirmar" onclick="confirmarReenvio()" disabled>Reenviar y archivar</button>
        </div>
    </div>
</div>
@endsection
