@extends('layouts.app')
@section('title', 'Atención')

@push('styles')
<style>
.atencion-root {
    margin: -24px;
    height: calc(100vh - 52px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Header */
.at-header {
    padding: 10px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 14px;
    flex-shrink: 0;
    background: var(--surface);
}
.at-title { font-weight: 700; font-size: 15px; }
.at-counts { font-size: 12px; color: var(--muted); }

/* Chips de filtro */
.filter-chips { display: flex; gap: 6px; margin-left: 16px; }
.filter-chip {
    font-size: 12px;
    padding: 3px 10px;
    border-radius: 14px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--muted);
    cursor: pointer;
    transition: .12s;
    user-select: none;
}
.filter-chip:hover { color: var(--text); border-color: var(--text); }
.filter-chip.active {
    background: var(--info);
    color: #fff;
    border-color: var(--info);
}
.filter-chip.urg.active {
    background: var(--accent);
    border-color: var(--accent);
}

/* Botón "+ Nueva conversación" */
.btn-nueva {
    margin-left: 8px;
    font-size: 12px;
    padding: 5px 12px;
    border-radius: 6px;
    border: 1px solid var(--success);
    background: color-mix(in srgb, var(--success) 8%, transparent);
    color: var(--success);
    cursor: pointer;
    font-weight: 600;
    transition: .12s;
}
.btn-nueva:hover { background: color-mix(in srgb, var(--success) 18%, transparent); }

/* Modal genérico */
.modal-backdrop {
    position: fixed; inset: 0; z-index: 9000;
    background: rgba(0,0,0,.5);
    display: flex; align-items: center; justify-content: center;
}
.modal-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    width: min(540px, calc(100vw - 32px));
    max-height: calc(100vh - 64px);
    overflow-y: auto;
    box-shadow: 0 12px 32px rgba(0,0,0,.3);
}
.modal-head {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.modal-title { font-weight: 700; font-size: 15px; }
.modal-close {
    background: none; border: none; color: var(--muted);
    font-size: 22px; cursor: pointer; padding: 0; line-height: 1;
}
.modal-close:hover { color: var(--text); }
.modal-body { padding: 16px 18px; }
.modal-foot {
    padding: 12px 18px;
    border-top: 1px solid var(--border);
    display: flex; gap: 8px; justify-content: flex-end;
}

.modal-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 14px; }
.modal-tab {
    padding: 8px 14px; cursor: pointer; font-size: 13px;
    color: var(--muted); border-bottom: 2px solid transparent;
    background: none; border-left: none; border-right: none; border-top: none;
}
.modal-tab.active { color: var(--text); border-bottom-color: var(--info); font-weight: 600; }

.modal-label { display: block; font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin: 12px 0 4px; }
.modal-input {
    width: 100%;
    padding: 8px 10px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    font-size: 14px;
    font-family: inherit;
}
.modal-input:focus { outline: none; border-color: var(--info); }
.modal-textarea { min-height: 90px; resize: vertical; }

.modal-search-results {
    max-height: 180px; overflow-y: auto;
    border: 1px solid var(--border); border-radius: 6px;
    margin-top: 6px;
}
.modal-search-item {
    padding: 8px 10px; cursor: pointer; font-size: 13px;
    border-bottom: 1px solid var(--border);
}
.modal-search-item:last-child { border-bottom: none; }
.modal-search-item:hover { background: var(--surface); }
.modal-search-item.selected { background: color-mix(in srgb, var(--info) 12%, transparent); }
.modal-search-item .nombre { font-weight: 600; }
.modal-search-item .tel { font-size: 11px; color: var(--muted); }

.btn-modal-primary {
    padding: 8px 16px; border-radius: 6px; border: none;
    background: var(--success); color: #fff; font-weight: 600;
    cursor: pointer; font-size: 13px;
}
.btn-modal-primary:disabled { opacity: .5; cursor: not-allowed; }
.btn-modal-secondary {
    padding: 8px 16px; border-radius: 6px;
    border: 1px solid var(--border); background: transparent;
    color: var(--muted); cursor: pointer; font-size: 13px;
}
.btn-modal-secondary:hover { color: var(--text); }

/* Body */
.at-body { flex: 1; display: flex; overflow: hidden; }

/* Columnas */
.at-col {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-right: 1px solid var(--border);
}
.at-col-header {
    padding: 9px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 12px;
    font-weight: 700;
    color: var(--text);
    text-transform: uppercase;
    letter-spacing: .5px;
    background: var(--surface);
    flex-shrink: 0;
}
.at-col-list { flex: 1; overflow-y: auto; padding: 8px; }

/* Cards */
.at-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 7px;
    overflow: hidden;
    transition: border-color .15s;
    cursor: default;
}
.at-card.urgente { border-color: var(--accent); }
.at-card.selected { outline: 2px solid var(--info); }
.at-card-head {
    padding: 7px 11px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    background: var(--surface);
}
.at-card.urgente .at-card-head { background: color-mix(in srgb, var(--accent) 7%, transparent); }
.badge {
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .4px;
}
.badge-bot { background: color-mix(in srgb, var(--info) 12%, transparent); color: var(--info); border: 1px solid color-mix(in srgb, var(--info) 25%, transparent); }
.badge-wa  { background: color-mix(in srgb, var(--success) 12%, transparent); color: var(--success); border: 1px solid color-mix(in srgb, var(--success) 25%, transparent); }
.badge-urg { color: var(--accent); font-weight: 700; }
.badge-test { border: 1px solid color-mix(in srgb, var(--warning) 35%, transparent); color: var(--warning); }
.badge-unread { background: var(--accent); color: #fff; border-radius: 10px; padding: 1px 7px; }
.at-time { margin-left: auto; color: var(--muted); }

.at-card-body { padding: 9px 11px; }
.av-circle {
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    background: var(--surface);
    border: 1px solid var(--border);
}
.av-fallback {
    border-radius: 50%;
    background: var(--accent);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
    user-select: none;
}
.at-contact { font-weight: 700; font-size: 15px; margin-bottom: 3px; }
.at-resumen {
    font-size: 13px; color: var(--muted); line-height: 1.4;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.at-asig { font-size: 11px; color: var(--info); }

.at-card-foot {
    padding: 6px 11px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 4px;
    flex-wrap: nowrap;
    align-items: center;
}
.btn {
    padding: 4px 9px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--muted);
    transition: .12s;
    white-space: nowrap;
}
.btn:hover { color: var(--text); border-color: var(--text); }
.btn-ver  { border-color: color-mix(in srgb, var(--info) 30%, transparent); color: var(--info); }
.btn-ver:hover { background: color-mix(in srgb, var(--info) 10%, transparent); }
.btn-tomar { border-color: color-mix(in srgb, var(--success) 35%, transparent); color: var(--success); }
.btn-tomar:hover { background: color-mix(in srgb, var(--success) 10%, transparent); }
.btn-resolver { border-color: color-mix(in srgb, var(--success) 35%, transparent); color: var(--success); }
.btn-resolver:hover { background: rgba(26,127,55,.06); }
.btn-urg { font-size: 14px; padding: 3px 9px; }
.btn-urg.on { border-color: color-mix(in srgb, var(--accent) 45%, transparent); color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, transparent); }
.btn-del { font-size: 11px; }

/* Panel derecho */
.at-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}
.at-panel-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    font-size: 13px;
    flex-direction: column;
    gap: 8px;
}

/* Panel de conversación / detalle */
.panel-head {
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--surface);
    flex-shrink: 0;
}
.panel-head-info { flex: 1; min-width: 0; }
.panel-head-name { font-weight: 600; font-size: 15px; }
.panel-head-sub  { font-size: 12px; color: var(--muted); }
.panel-head-actions { display: flex; gap: 6px; align-items: center; flex-shrink: 0; }

/* Mensajes */
.msg-list {
    flex: 1;
    overflow-y: auto;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 7px;
}
.msg-date {
    text-align: center;
    font-size: 11px;
    color: var(--muted);
    padding: 6px 0;
}
.msg-wrap { display: flex; }
.msg-wrap.in  { justify-content: flex-start; }
.msg-wrap.out { justify-content: flex-end; }
.msg-wrap.nota { justify-content: center; }
.msg-bubble {
    max-width: 72%;
    padding: 7px 11px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.4;
}
.msg-bubble.in   { background: var(--bg); border: 1px solid var(--border); border-radius: 0 12px 12px 12px; }
.msg-bubble.out  { background: color-mix(in srgb, var(--accent) 12%, var(--card)); border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent); border-radius: 12px 0 12px 12px; }
.msg-bubble.nota { background: color-mix(in srgb, var(--warning) 10%, var(--card)); border: 1px solid color-mix(in srgb, var(--warning) 25%, transparent); color: var(--warning); border-radius: 8px; font-size: 12px; }
.msg-time { font-size: 11px; color: var(--muted); margin-top: 3px; }
.msg-time.right { text-align: right; }
.msg-transcripcion { font-size: 11px; color: var(--muted); font-style: italic; margin-top: 4px; }
audio { height: 32px; width: 210px; display: block; }

/* Input */
.panel-input {
    border-top: 1px solid var(--border);
    padding: 10px 14px;
    flex-shrink: 0;
    background: var(--surface);
}
.input-modes { display: flex; gap: 6px; margin-bottom: 7px; }
.mode-btn {
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--muted);
    cursor: pointer;
}
.mode-btn.active { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, transparent); color: var(--accent); }

/* Dropdown de respuestas rápidas — se despliega hacia ARRIBA porque el botón
   vive en el footer del panel de conversación; hacia abajo quedaría tapado
   por el borde inferior del panel. */
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
.rr-menu-item {
    padding: 8px 14px;
    font-size: 13px;
    cursor: pointer;
    color: var(--text);
    transition: background .12s;
    border-bottom: 1px solid color-mix(in srgb, var(--border) 50%, transparent);
}
.rr-menu-item:last-child { border-bottom: none; }
.rr-menu-item:hover { background: var(--bg); }
.rr-menu-item .rr-titulo  { font-weight: 600; }
.rr-menu-item .rr-preview { font-size: 11px; color: var(--muted); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rr-menu-empty { padding: 14px; font-size: 12px; color: var(--muted); text-align: center; }
.rr-menu-foot  { padding: 6px 14px; font-size: 11px; color: var(--muted); border-top: 1px solid var(--border); }
.rr-menu-foot a { color: var(--info); text-decoration: none; }
.rr-menu-foot a:hover { text-decoration: underline; }

.input-row { display: flex; gap: 8px; align-items: flex-end; }
.msg-textarea {
    flex: 1;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px 11px;
    color: var(--text);
    font-size: 13px;
    resize: none;
    min-height: 58px;
    font-family: inherit;
}
.send-btn {
    padding: 8px 16px;
    background: var(--accent);
    border: none;
    color: #fff;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    height: 40px;
    white-space: nowrap;
}
.clip-label {
    cursor: pointer;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 18px;
}
.clip-label:hover { border-color: var(--muted); }
.file-preview {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 7px 12px;
    margin-bottom: 8px;
    font-size: 12px;
}
.file-preview-name { flex: 1; word-break: break-all; color: var(--text); }
.file-preview-clear { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 16px; line-height: 1; }

/* Detalle derivación */
.der-detail { padding: 16px; overflow-y: auto; flex: 1; }
.der-detail h3 { font-size: 12px; color: var(--muted); font-weight: 600; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
.der-texto { font-size: 14px; color: var(--text); line-height: 1.6; white-space: pre-wrap; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 12px; }

/* Delegar dropdown — position:fixed para escapar el overflow:hidden de las cards.
   top/left/bottom los setea el JS según la posición del botón. */
.delegar-wrap { position: relative; display: inline-flex; }
.delegar-menu {
    position: fixed;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    min-width: 220px;
    max-height: min(60vh, 320px);
    overflow-y: auto;
    z-index: 2000;
    box-shadow: 0 8px 24px rgba(0,0,0,.18);
}
.delegar-opt {
    padding: 9px 14px;
    font-size: 14px;
    cursor: pointer;
    color: var(--text);
}
.delegar-opt:hover { background: var(--surface); }

/* Seguimiento */
.seg-strip { border-top: 1px solid var(--border); background: var(--surface); flex-shrink: 0; }
.seg-list-row { padding: 6px 12px 8px; display: flex; flex-direction: row; gap: 6px; overflow-x: auto; align-items: center; scrollbar-width: thin; }
.seg-list-row::-webkit-scrollbar { height: 5px; }
.seg-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 12px; background: var(--card); border: 1px solid var(--border); font-size: 11.5px; color: var(--muted); white-space: nowrap; flex-shrink: 0; }
.seg-chip strong { color: var(--text); font-weight: 600; }
.seg-chip-time { color: var(--muted); opacity: .75; font-size: 10.5px; }
.seg-sep { color: var(--muted); opacity: .4; flex-shrink: 0; }
/* Divider de "conversación previa resuelta" entre bloques de mensajes */
.msg-resol-divider { display: flex; align-items: center; gap: 10px; margin: 14px 4px 8px; color: var(--muted); font-size: 11.5px; }
.msg-resol-divider .line { flex: 1; height: 1px; background: color-mix(in srgb, var(--success) 40%, var(--border)); }
.msg-resol-divider .label { display: inline-flex; align-items: center; gap: 5px; padding: 2px 10px; border-radius: 10px; background: color-mix(in srgb, var(--success) 12%, var(--bg)); border: 1px solid color-mix(in srgb, var(--success) 30%, var(--border)); color: var(--success); font-weight: 600; }
/* Eventos intercalados en el hilo (tomada/delegada/urgente/etc.) — mismo
   estilo exacto que msg-resol-divider (chip verde + líneas) por pedido del
   operador: que TODOS los eventos del seguimiento se vean igual al divider
   de "✓ Resuelta el ... por X". */
.msg-evento-inline { display: flex; align-items: center; gap: 10px; margin: 14px 4px 8px; color: var(--muted); font-size: 11.5px; }
.msg-evento-inline .line { flex: 1; height: 1px; background: color-mix(in srgb, var(--success) 40%, var(--border)); }
.msg-evento-inline .label { display: inline-flex; align-items: center; gap: 5px; padding: 2px 10px; border-radius: 10px; background: color-mix(in srgb, var(--success) 12%, var(--bg)); border: 1px solid color-mix(in srgb, var(--success) 30%, var(--border)); color: var(--success); font-weight: 600; }
.msg-evento-inline strong { color: var(--success); font-weight: 700; }
.msg-evento-inline .ev-time { opacity: .7; margin-left: 6px; font-size: 10.5px; font-weight: 500; }
.msg-historial-toggle { display: block; width: 100%; text-align: center; padding: 8px; font-size: 12px; color: var(--muted); cursor: pointer; background: var(--surface); border: 1px dashed var(--border); border-radius: 6px; margin: 6px 0; }
.msg-historial-toggle:hover { color: var(--text); border-color: var(--info); }
.seg-toggle {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 14px; cursor: pointer;
    font-size: 11px; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .4px;
    user-select: none;
}
.seg-toggle:hover { color: var(--text); }
.seg-arrow { transition: transform .15s; display: inline-block; }
.seg-arrow.open { transform: rotate(90deg); }
.seg-list { padding: 0 14px 10px; display: flex; flex-direction: column; gap: 3px; overflow-y: auto; max-height: 130px; }
.seg-item { display: flex; align-items: flex-start; gap: 8px; font-size: 12px; color: var(--muted); padding: 3px 0; border-bottom: 1px solid var(--border); }
.seg-item:last-child { border-bottom: none; }
.seg-icon { font-size: 14px; flex-shrink: 0; margin-top: 1px; }
.seg-text { flex: 1; line-height: 1.4; }
.seg-text strong { color: var(--text); font-weight: 600; }
.seg-when { font-size: 11px; color: var(--muted); white-space: nowrap; margin-top: 1px; }

/* Empty state */
.col-empty { color: var(--muted); font-size: 12px; text-align: center; padding: 32px 0; }

/* Overrides tema oscuro */
html.dark .badge-bot { background: rgba(88,166,255,.12); color: #58a6ff; border-color: rgba(88,166,255,.25); }
html.dark .badge-wa  { background: rgba(63,185,80,.12);  color: #3fb950; border-color: rgba(63,185,80,.25); }
html.dark .btn-ver   { border-color: rgba(88,166,255,.3); color: #58a6ff; }
html.dark .btn-ver:hover { background: rgba(88,166,255,.1); }
html.dark .btn-tomar, html.dark .btn-resolver { border-color: rgba(63,185,80,.3); color: #3fb950; }
html.dark .btn-tomar:hover, html.dark .btn-resolver:hover { background: rgba(63,185,80,.1); }
html.dark .delegar-menu { box-shadow: 0 8px 24px rgba(0,0,0,.4); }
html.dark .seg-item { border-bottom-color: rgba(255,255,255,.06); }
html.dark .toast.ok  { background: rgba(63,185,80,.15); border-color: rgba(63,185,80,.3); }
html.dark .toast.error { background: rgba(248,81,73,.15); border-color: rgba(248,81,73,.3); }

/* Toast */
.toast {
    position: fixed;
    bottom: 90px;        /* deja espacio para el botón flotante de chat (bottom:20px) */
    right: 24px;
    z-index: 9999;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    opacity: 0;
    transform: translateY(8px);
    transition: .2s;
    pointer-events: none;
}
.toast.show { opacity: 1; transform: none; }
.toast.ok    { background: #dcfce7; color: var(--success); border: 1px solid rgba(26,127,55,.3); }
.toast.error { background: #fee2e2; color: var(--error);   border: 1px solid rgba(207,34,46,.3); }
</style>
@endpush

@section('content')
{{-- Modal de adjuntos --}}
<div id="media-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:9999;align-items:center;justify-content:center;"
     onclick="cerrarModal()">
    <div style="position:relative;max-width:92vw;max-height:92vh;" onclick="event.stopPropagation()">
        <button onclick="cerrarModal()"
                style="position:absolute;top:-34px;right:0;background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;">✕</button>
        <div id="media-modal-content"></div>
    </div>
</div>

<div class="atencion-root" id="app">

    {{-- Header --}}
    <div class="at-header">
        <span class="at-title">Atención · {{ $areaLabel ?? 'Clínica' }} <span style="font-size:12px;color:var(--muted);font-weight:400;">(WhatsApp)</span></span>

        <button class="btn-nueva" onclick="abrirModalNueva()" title="Iniciar conversación con un contacto">
            + Nueva conversación
        </button>

        <div class="filter-chips" role="tablist" aria-label="Filtros de cola">
            <button class="filter-chip active" data-filtro="todas"     onclick="setFiltro('todas')">Todas</button>
            <button class="filter-chip urg"    data-filtro="urgentes"  onclick="setFiltro('urgentes')">Urgentes</button>
            <button class="filter-chip"        data-filtro="mias"      onclick="setFiltro('mias')">Mías</button>
        </div>

        <span class="at-counts" id="counts" style="margin-left:auto;">—</span>

        <a href="/contactos"
           style="font-size:12px;padding:3px 12px;border-radius:6px;border:1px solid var(--border);
                  color:var(--muted);text-decoration:none;margin-left:4px;">
            📋 Contactos
        </a>
    </div>

    {{-- Cuerpo --}}
    <div class="at-body">

        {{-- Col Nuevas --}}
        <div class="at-col" id="col-nuevas" style="width:300px;flex-shrink:0;">
            <div class="at-col-header">Nuevas <span id="cnt-nuevas">0</span></div>
            <div class="at-col-list" id="list-nuevas">
                <div class="col-empty">Cargando…</div>
            </div>
        </div>

        {{-- Panel central --}}
        <div class="at-panel" id="panel" style="border-right:1px solid var(--border);">
            <div class="at-panel-empty" id="panel-empty">
                <span style="font-size:32px;opacity:.3;">◫</span>
                <span>Seleccioná un ítem para ver el detalle</span>
            </div>
            <div id="panel-conv" style="display:none;flex:1;flex-direction:column;overflow:hidden;">
                {{-- Llenado por JS --}}
            </div>
        </div>

        {{-- Col En proceso --}}
        <div class="at-col" id="col-proceso" style="width:300px;flex-shrink:0;border-right:none;">
            <div class="at-col-header">En proceso <span id="cnt-proceso">0</span></div>
            <div class="at-col-list" id="list-proceso">
                <div class="col-empty">Cargando…</div>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    {{-- Modal: Iniciar nueva conversación --}}
    <div id="modal-nueva" style="display:none;"></div>

    {{-- Modal: Agregar contacto desde conversación huérfana --}}
    <div id="modal-agregar-contacto" style="display:none;"></div>
</div>

<script>
const CSRF   = '{{ csrf_token() }}';
const ME_ID  = {{ auth()->id() }};
const AREA   = @json($area ?? 'atencion');

// Respuestas rápidas del área (cacheadas en JS; se cargan al inicio).
let RESPUESTAS_RAPIDAS = [];
async function cargarRespuestasRapidas() {
    try {
        const r = await fetch('/atencion/respuestas-rapidas/' + AREA, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!r.ok) return;
        const d = await r.json();
        RESPUESTAS_RAPIDAS = d.data || [];
    } catch {}
}
cargarRespuestasRapidas();
const ME_NAME= '{{ addslashes(auth()->user()->nombre_completo) }}';
const USUARIOS = @json($usuarios);

// ── Estado ──────────────────────────────────────────────────
// Datos precargados por el servidor — cero espera en el primer render
let state = {
    nuevas:          @json($itemsData['nuevas']),
    enProceso:       @json($itemsData['enProceso']),
    totalNuevas:     {{ $itemsData['total_nuevas'] ?? 0 }},
    totalProceso:    {{ $itemsData['total_proceso'] ?? 0 }},
    panelId:         null,
    panelTipo:       null,
    panelModo:       'mensaje',
    panelAsigId:     null,
    delegarOpenId:   null,
    delegarOpenTipo: null,
    filtro:          'todas',
};

// ── API helpers ──────────────────────────────────────────────
async function api(method, url, body) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(url, opts);
    if (!r.ok) {
        // Intentar leer { error: '...' } del body para dar mensaje útil al usuario.
        let serverMsg = '';
        try { serverMsg = (await r.json())?.error || ''; } catch {}
        const err = new Error(serverMsg || `HTTP ${r.status}`);
        err.status = r.status;
        err.serverMsg = serverMsg;
        throw err;
    }
    return r.json();
}
const get  = (url)       => api('GET', url);
const post = (url, body) => api('POST', url, body);

// ── Toast ────────────────────────────────────────────────────
function toast(msg, tipo = 'ok') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = `toast ${tipo} show`;
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 3000);
}

// ── Fetch & render ───────────────────────────────────────────
// Cache del último ETag para enviar If-None-Match. Si el server devuelve 304, no re-renderizamos.
let _itemsEtag = null;

async function fetchItems() {
    try {
        const headers = {
            'X-CSRF-TOKEN': CSRF,
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (_itemsEtag) headers['If-None-Match'] = _itemsEtag;

        const r = await fetch('/atencion/{{ $area ?? "atencion" }}/items', { headers });
        if (r.status === 304) return;            // sin cambios — mantener state actual
        if (!r.ok) return;

        const newEtag = r.headers.get('ETag');
        if (newEtag) _itemsEtag = newEtag;

        const data = await r.json();
        notificarSiHayUrgente(data.nuevas, state.nuevas);
        notificarSiMeDelegaron(data.enProceso);
        state.nuevas       = data.nuevas;
        state.enProceso    = data.enProceso;
        state.totalNuevas  = data.total_nuevas  ?? data.nuevas.length;
        state.totalProceso = data.total_proceso ?? data.enProceso.length;
        renderColumnas();
    } catch(e) {}
}

// ── Notificaciones de urgentes ───────────────────────────────
const _idsConocidos = new Set();
let _notifPrimera = true;

function notificarSiHayUrgente(nuevasNew, nuevasOld) {
    // Primera ejecución: solo cargar IDs sin notificar (evita ráfaga al abrir la página).
    if (_notifPrimera) {
        nuevasNew.forEach(i => _idsConocidos.add(`${i.tipo}:${i.id}:${i.urgente?1:0}`));
        _notifPrimera = false;
        return;
    }

    const nuevosUrgentes = nuevasNew.filter(i => {
        const key = `${i.tipo}:${i.id}:${i.urgente?1:0}`;
        if (_idsConocidos.has(key)) return false;
        _idsConocidos.add(key);
        return i.urgente;
    });

    if (nuevosUrgentes.length > 0) {
        sonarPing();
        nuevosUrgentes.forEach(i => mostrarNotif(i));
    }

    // Limitar el set a 500 keys para no crecer indefinidamente.
    if (_idsConocidos.size > 500) {
        const keep = Array.from(_idsConocidos).slice(-300);
        _idsConocidos.clear();
        keep.forEach(k => _idsConocidos.add(k));
    }
}

function mostrarNotif(item) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    try {
        const n = new Notification('Crecer — mensaje urgente', {
            body: `${item.contacto}: ${(item.resumen || '').slice(0, 80)}`,
            tag:  `urg-${item.tipo}-${item.id}`,
            requireInteraction: false,
        });
        n.onclick = () => { window.focus(); n.close(); };
        setTimeout(() => n.close(), 8000);
    } catch {}
}

// ── Notificación de conversaciones delegadas al usuario ──────
// Recuerda el set de IDs ya asignadas a "me" entre polls. Si aparece una
// nueva, dispara notificación del browser. La primera ejecución solo
// puebla el set sin notificar (evita ráfaga al cargar la app).
window._convsAsignadasAmi = new Set();
let _delegPrimera = true;

// Llamar cuando el usuario toma/auto-asigna manualmente una conv, para
// pre-poblar el set y evitar que el siguiente poll la note como "delegada".
window.marcarConvComoMia = function(id, tipo) {
    window._convsAsignadasAmi.add(`${tipo}:${id}`);
};

function notificarSiMeDelegaron(enProcesoNew) {
    const misAhora = (enProcesoNew || []).filter(c => parseInt(c.asig_id) === ME_ID);

    if (_delegPrimera) {
        misAhora.forEach(c => window._convsAsignadasAmi.add(`${c.tipo}:${c.id}`));
        _delegPrimera = false;
        return;
    }

    misAhora.forEach(c => {
        const key = `${c.tipo}:${c.id}`;
        if (window._convsAsignadasAmi.has(key)) return;
        window._convsAsignadasAmi.add(key);
        // Recién apareció como mía y NO la había tomado yo (porque tomarItem
        // pre-poblea el set). Entonces es una delegación de otro usuario.
        if (window.Notify) {
            window.Notify.disparar({
                titulo: 'Te delegaron una conversación',
                cuerpo: `${c.contacto}: ${(c.resumen || '').slice(0, 80)}`,
                tag: `deleg-${c.tipo}-${c.id}`,
                url: `/atencion?conv_id=${c.id}`,
            });
        }
    });

    // Limpiar IDs que ya no están asignados a mí (resueltas, delegadas a otro, etc.)
    const claves = new Set(misAhora.map(c => `${c.tipo}:${c.id}`));
    for (const k of Array.from(window._convsAsignadasAmi)) {
        if (!claves.has(k)) window._convsAsignadasAmi.delete(k);
    }
}

// Tono de aviso corto generado con WebAudio (sin descarga, sin assets).
function sonarPing() {
    try {
        const ctx = window._audioCtx ||= new (window.AudioContext || window.webkitAudioContext)();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.frequency.value = 880;
        g.gain.value = 0.0001;
        o.connect(g); g.connect(ctx.destination);
        o.start();
        g.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.04);
        g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.45);
        o.stop(ctx.currentTime + 0.5);
    } catch {}
}

// Pedir permiso al cargar (silencioso si ya está concedido o denegado).
if ('Notification' in window && Notification.permission === 'default') {
    // Esperamos un click del usuario para no asustar — el browser exige interacción.
    document.addEventListener('click', () => Notification.requestPermission(), { once: true });
}

function renderCard(item, col) {
    const sel = state.panelId === item.id && state.panelTipo === item.tipo;
    const urgClasses = item.urgente ? 'at-card urgente' : 'at-card';
    const selClass   = sel ? ' selected' : '';

    const badgeTipo = item.tipo === 'bot'
        ? '<span class="badge badge-bot">BOT</span>'
        : '<span class="badge badge-wa">WA</span>';

    const badgeUrg  = item.urgente ? '<span class="badge badge-urg">⚑ URGENTE</span>' : '';
    const badgeTest = item.es_prueba ? '<span class="badge badge-test">PRUEBA</span>' : '';
    const badgeNL   = item.no_leidos > 0 ? `<span class="badge badge-unread">${item.no_leidos}</span>` : '';

    // Botones según columna
    let btns = '';
    const verLabel = item.tipo === 'wa' ? '💬 Ver' : '🔍 Ver';
    btns += `<button class="btn btn-ver" onclick="verItem(${item.id},'${item.tipo}')">${verLabel}</button>`;

    if (col === 'nuevas') {
        btns += `<button class="btn btn-tomar" onclick="tomarItem(${item.id},'${item.tipo}',this)">Tomar</button>`;
    } else {
        btns += `<button class="btn btn-resolver" onclick="resolverItem(${item.id},'${item.tipo}',this)">✓ Resuelto</button>`;
    }

    btns += `<div class="delegar-wrap" id="dw-${item.tipo}-${item.id}">
        <button class="btn btn-del" onclick="toggleDelegar(${item.id},'${item.tipo}')">Delegar ▾</button>
    </div>`;

    btns += `<button class="btn btn-urg${item.urgente ? ' on' : ''}" title="${item.urgente ? 'Quitar urgencia' : 'Marcar urgente'}"
        onclick="toggleUrgente(${item.id},'${item.tipo}',this)">⚑</button>`;

    const asigHead = item.asig_name
        ? `<span class="at-asig">👤 ${item.asig_name}</span>`
        : '';

    return `<div class="${urgClasses}${selClass}" id="card-${item.tipo}-${item.id}">
        <div class="at-card-head">
            ${badgeTipo}${badgeUrg}
            <span style="color:var(--muted)">${item.etiqueta}</span>
            ${badgeTest}${badgeNL}
            ${asigHead}
            <span class="at-time">${item.hace}</span>
        </div>
        <div class="at-card-body" style="display:flex;gap:10px;align-items:flex-start;">
            ${avatarHtml(item.avatar_url, item.contacto, 40, item.contacto_id)}
            <div style="flex:1;min-width:0;">
                <div class="at-contact">${item.contacto}</div>
                <div class="at-resumen">${item.resumen || '—'}</div>
            </div>
        </div>
        <div class="at-card-foot">${btns}</div>
    </div>`;
}

function renderDelegarMenu(id, tipo) {
    const otros = USUARIOS.filter(u => u.id !== ME_ID);
    if (!otros.length) return '<div class="delegar-menu"><div class="delegar-opt" style="color:var(--muted)">Sin usuarios</div></div>';
    return `<div class="delegar-menu" id="dm-${tipo}-${id}">
        ${otros.map(u => `<div class="delegar-opt" onclick="delegarItem(${id},'${tipo}',${u.id},'${u.nombre_completo.replace(/'/g,"\\'")}')">
            ${u.nombre_completo}
        </div>`).join('')}
    </div>`;
}

// ── Acciones ─────────────────────────────────────────────────
async function tomarItem(id, tipo, btn) {
    // Optimista: mover la card al instante
    const idx = state.nuevas.findIndex(i => i.id === id && i.tipo === tipo);
    if (idx >= 0) {
        const item = { ...state.nuevas[idx], asig_id: ME_ID, asig_name: ME_NAME };
        state.nuevas.splice(idx, 1);
        state.enProceso.unshift(item);
        renderColumnas();
    }
    // Pre-poblar el set para que el polling no la confunda con una delegación.
    if (window.marcarConvComoMia) window.marcarConvComoMia(id, tipo);
    if (tipo === 'wa') verItem(id, tipo);

    // API en background
    try {
        await post('/atencion/tomar', { id, tipo });
        toast('Tomado');
    } catch(e) {
        // Revertir si falla
        const idxP = state.enProceso.findIndex(i => i.id === id && i.tipo === tipo);
        if (idxP >= 0) {
            const item = state.enProceso.splice(idxP, 1)[0];
            item.asig_id = null; item.asig_name = null;
            state.nuevas.unshift(item);
            renderColumnas();
        }
        toast('Error al tomar', 'error');
    }
}

async function resolverItem(id, tipo, btn) {
    // Confirmación destructiva: archivar conversación es irreversible desde la cola.
    // Para reabrir hay que ir al historial.
    if (!confirm('¿Marcar como resuelta? La conversación se archiva y sale de la cola.')) return;

    // Optimista: sacar la card al instante
    state.enProceso = state.enProceso.filter(i => !(i.id === id && i.tipo === tipo));
    state.nuevas    = state.nuevas.filter(i => !(i.id === id && i.tipo === tipo));
    if (state.panelId === id && state.panelTipo === tipo) cerrarPanel();
    renderColumnas();
    toast('Resuelto');
    post('/atencion/resolver', { id, tipo }).catch(() => toast('Error al resolver', 'error'));
}

async function toggleUrgente(id, tipo, btn) {
    // Optimista: toggle inmediato
    const item = [...state.nuevas, ...state.enProceso].find(i => i.id === id && i.tipo === tipo);
    if (item) { item.urgente = !item.urgente; renderColumnas(); }
    post('/atencion/urgente', { id, tipo }).catch(() => {
        if (item) { item.urgente = !item.urgente; renderColumnas(); } // revertir
    });
}

function cerrarMenusDelegar() {
    document.querySelectorAll('.delegar-menu').forEach(m => m.remove());
    state.delegarOpenId   = null;
    state.delegarOpenTipo = null;
}

function toggleDelegar(id, tipo) {
    const wasOpen = state.delegarOpenId === id && state.delegarOpenTipo === tipo;
    cerrarMenusDelegar();
    if (wasOpen) return;

    const wrap = document.getElementById(`dw-${tipo}-${id}`);
    if (!wrap) return;
    const btn = wrap.querySelector('.btn-del') || wrap;
    const r = btn.getBoundingClientRect();

    state.delegarOpenId   = id;
    state.delegarOpenTipo = tipo;
    // Lo colgamos del <body> (no de la card) para que el overflow:hidden de la card no lo recorte.
    document.body.insertAdjacentHTML('beforeend', renderDelegarMenu(id, tipo));
    const menu = document.querySelector('.delegar-menu');
    if (!menu) return;
    menu.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 230)) + 'px';
    if (window.innerHeight - r.bottom > 220) {
        menu.style.top = (r.bottom + 4) + 'px';      // hay lugar abajo
    } else {
        menu.style.bottom = (window.innerHeight - r.top + 4) + 'px';   // abrir hacia arriba
    }
}

// Click afuera del menú (o del botón "Delegar") → cerrar.
document.addEventListener('click', (e) => {
    if (!e.target.closest('.delegar-menu') && !e.target.closest('.btn-del')) cerrarMenusDelegar();
});

async function delegarItem(id, tipo, userId, userName) {
    // Optimista
    const idxN = state.nuevas.findIndex(i => i.id === id && i.tipo === tipo);
    if (idxN >= 0) {
        const item = { ...state.nuevas.splice(idxN, 1)[0], asig_id: userId, asig_name: userName };
        state.enProceso.unshift(item);
    } else {
        const item = state.enProceso.find(i => i.id === id && i.tipo === tipo);
        if (item) { item.asig_id = userId; item.asig_name = userName; }
    }
    document.querySelectorAll('.delegar-menu').forEach(m => m.remove());
    state.delegarOpenId = null;
    state.delegarOpenTipo = null;
    renderColumnas();
    toast(`Delegado a ${userName}`);
    post('/atencion/delegar', { id, tipo, user_id: userId }).catch(() => toast('Error al delegar', 'error'));
}

// Delegar desde el panel de conversación (cuando estás leyéndola pero NO la tomaste).
function delegarPanel(convId) {
    cerrarMenusDelegar();
    const btn = document.getElementById('panel-delegar-btn');
    if (!btn) return;
    const r = btn.getBoundingClientRect();
    const otros = USUARIOS.filter(u => u.id !== ME_ID);
    const opts = otros.length
        ? otros.map(u => `<div class="delegar-opt" onclick="delegarDesdePanel(${convId},${u.id},'${u.nombre_completo.replace(/'/g,"\\'")}')">${u.nombre_completo}</div>`).join('')
        : '<div class="delegar-opt" style="color:var(--muted)">Sin otros usuarios</div>';
    document.body.insertAdjacentHTML('beforeend', `<div class="delegar-menu" id="panel-delegar-menu">${opts}</div>`);
    const menu = document.getElementById('panel-delegar-menu');
    if (!menu) return;
    menu.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 230)) + 'px';
    if (window.innerHeight - r.bottom > 220) menu.style.top = (r.bottom + 4) + 'px';
    else menu.style.bottom = (window.innerHeight - r.top + 4) + 'px';
}
async function delegarDesdePanel(convId, userId, userName) {
    cerrarMenusDelegar();
    // Optimista en la lista
    const item = [...state.nuevas, ...state.enProceso].find(i => i.id === convId && i.tipo === 'wa');
    if (item) { item.asig_id = userId; item.asig_name = userName;
        const idxN = state.nuevas.findIndex(i => i.id === convId && i.tipo === 'wa');
        if (idxN >= 0) { state.enProceso.unshift(state.nuevas.splice(idxN,1)[0]); }
        renderColumnas();
    }
    cerrarPanel();
    toast(`Delegado a ${userName}`);
    try { await post('/atencion/delegar', { id: convId, tipo: 'wa', user_id: userId }); }
    catch (e) { toast(e.serverMsg || 'Error al delegar', 'error'); fetchItems(); }
}

// Cerrar delegar al click fuera
document.addEventListener('click', e => {
    if (state.delegarOpenId && !e.target.closest('.delegar-wrap')) {
        document.querySelectorAll('.delegar-menu').forEach(m => m.remove());
        state.delegarOpenId   = null;
        state.delegarOpenTipo = null;
    }
});

// ── Panel de detalle ─────────────────────────────────────────
async function verItem(id, tipo) {
    // Marcar seleccionado
    state.panelId   = id;
    state.panelTipo = tipo;
    renderColumnas();

    document.getElementById('panel-empty').style.display = 'none';
    const panelConv = document.getElementById('panel-conv');
    panelConv.style.display = 'flex';
    panelConv.innerHTML = '<div style="flex:1;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:13px;">Cargando…</div>';

    if (tipo === 'wa') {
        await cargarConversacion(id);
    } else {
        await cargarDerivacion(id);
    }
}

function cerrarPanel() {
    state.panelId   = null;
    state.panelTipo = null;
    document.getElementById('panel-empty').style.display = '';
    document.getElementById('panel-conv').style.display  = 'none';
    renderColumnas();
}

// ── Tag resumen IA ────────────────────────────────────────────
function resumenTag(texto) {
    if (!texto) return '';
    const safe = esc(texto).replace(/'/g, '&#39;');
    return `<span id="ia-tag" onclick="toggleResumen(this,'${safe}')"
        style="background:rgba(88,166,255,.12);border:1px solid rgba(88,166,255,.28);color:var(--info);
               border-radius:10px;padding:3px 9px;font-size:11px;font-weight:600;cursor:pointer;
               white-space:nowrap;user-select:none;">💡 IA</span>`;
}

function toggleResumen(el, texto) {
    let pop = document.getElementById('ia-popup');
    if (pop) { pop.remove(); return; }
    pop = document.createElement('div');
    pop.id = 'ia-popup';
    pop.style.cssText = `position:absolute;top:100%;right:0;margin-top:6px;
        background:var(--card);border:1px solid rgba(5,80,174,.2);border-radius:8px;
        padding:12px 14px;max-width:340px;font-size:12px;color:var(--text);line-height:1.5;
        z-index:200;box-shadow:0 6px 20px rgba(0,0,0,.1);white-space:pre-wrap;word-break:break-word;`;
    pop.textContent = texto.replace(/&#39;/g, "'");
    el.parentElement.style.position = 'relative';
    el.parentElement.appendChild(pop);
    setTimeout(() => document.addEventListener('click', () => { pop.remove(); }, { once: true }), 0);
}

// Expande/colapsa el bloque de mensajes anteriores a la última "resuelta".
function toggleHistorialPrevios(btn) {
    const block = document.getElementById('msg-historial-previos');
    if (!block) return;
    if (!btn.dataset.lbl) btn.dataset.lbl = btn.textContent;
    const open = block.style.display === 'none';
    block.style.display = open ? '' : 'none';
    btn.textContent = open ? '↓ Ocultar historial anterior' : btn.dataset.lbl;
}

// ── Eventos intercalados en el hilo ───────────────────────────
// Render de un evento (tomada/delegada/resuelta/etc.) como "system message"
// inline en el msg-list, en su posición cronológica entre los mensajes. Reemplaza
// a la tira de chips horizontal que estaba debajo del hilo y mostraba los eventos
// fuera de contexto temporal.
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
// Cada item lleva __k='msg'|'evt' para que el renderer sepa qué dibujar.
function mergearMensajesEventos(mensajes, eventos) {
    const items = [];
    for (const m of mensajes) items.push(Object.assign({}, m, { __k: 'msg' }));
    for (const e of eventos)  items.push(Object.assign({}, e, { __k: 'evt' }));
    items.sort((a, b) => (a.ts || 0) - (b.ts || 0));
    return items;
}

// ── Seguimiento ───────────────────────────────────────────────
// (LEGACY) Tira de chips horizontal con los eventos del ciclo actual. Se sacó
// del panel principal de /atencion el 19/05 porque mostraba los eventos fuera
// de contexto temporal (todos abajo, después del último mensaje). Ahora los
// eventos van inline vía renderEventoInline(). La función queda para que
// /mis-conversaciones y /centro-tareas la sigan usando hasta que las migren.
function renderSeguimiento(eventos) {
    const TIPOS = {
        tomada:      { icon: '🟢', label: (e) => `Tomó <strong>${esc(e.usuario||'—')}</strong>` },
        delegada:    { icon: '📤', label: (e) => `<strong>${esc(e.usuario||'—')}</strong> → <strong>${esc(e.destino||'—')}</strong>` },
        resuelta:    { icon: '✅', label: (e) => `Resolvió <strong>${esc(e.usuario||'—')}</strong>` },
        reabierta:   { icon: '🔁', label: (e) => `Reabrió <strong>${esc(e.usuario||'—')}</strong>` },
        urgente_on:  { icon: '⚑',  label: (e) => `Urgente: <strong>${esc(e.usuario||'—')}</strong>` },
        urgente_off: { icon: '⚐',  label: (e) => `Sin urgencia: <strong>${esc(e.usuario||'—')}</strong>` },
        reenviada:   { icon: '🔁', label: (e) => `Reenvió y archivó <strong>${esc(e.usuario||'—')}</strong>` },
        derivada_area: { icon: '↗', label: (e) => `Derivó a otra área <strong>${esc(e.usuario||'—')}</strong>` },
    };

    const hayEventos = eventos && eventos.length > 0;
    if (!hayEventos) {
        return `<div class="seg-strip"><div class="seg-list-row" style="color:var(--muted);font-size:11.5px;">Sin acciones registradas aún.</div></div>`;
    }

    const chips = eventos.map((e, i) => {
        const t = TIPOS[e.tipo] || { icon: '•', label: () => esc(e.tipo) };
        const sep = i < eventos.length - 1 ? '<span class="seg-sep">·</span>' : '';
        return `<span class="seg-chip" title="${esc(e.fecha)} (${esc(e.hace)})">
                ${t.icon} ${t.label(e)}
                <span class="seg-chip-time">${esc(e.hora||'')}</span>
            </span>${sep}`;
    }).join('');

    return `<div class="seg-strip"><div class="seg-list-row">${chips}</div></div>`;
}

// ── Panel WA ─────────────────────────────────────────────────
async function cargarConversacion(id) {
    const datos = await get(`/atencion/conversacion/${id}`);
    _renderConversacion(id, datos);
}

// Render puro: toma los datos ya fetchados y reemplaza el panel. Separado
// del fetch para que refrescarConvAbierta() pueda capturar el estado del
// composer (textarea, foco, cursor) JUSTO antes del replace — si capturara
// antes del fetch, los keystrokes del usuario durante esos ~200-500ms se
// perderían cuando el textarea viejo se destruye al asignar innerHTML.
function _renderConversacion(id, datos) {
    const { conv, mensajes, eventos, has_older } = datos;
    state.panelAsigId = conv.asig_id;
    state.panelHasOlder = !!has_older;
    state.panelConv = conv;       // para el modal "Agregar a contactos"
    const esMio    = parseInt(conv.asig_id) === ME_ID;
    const tomado   = !!conv.asig_id;
    const panelConv = document.getElementById('panel-conv');

    let headBtns = !tomado
        ? `<button class="btn btn-tomar" onclick="tomarYAbrir(${id})">Tomar</button>
           <button class="btn btn-del" id="panel-delegar-btn" onclick="delegarPanel(${id})">Delegar ▾</button>`
        : esMio
            ? `<button class="btn" onclick="abrirModalReenvio(${id})" title="Reenviar el hilo a otro contacto y archivar">📤 Reenviar</button>
               <button class="btn btn-resolver" onclick="resolverPanel()">✓ Resuelto</button>`
            : `<span style="font-size:11px;color:var(--muted);">Con: ${conv.asig_name}</span>`;

    // Si la conversación viene de un número que no está en el directorio,
    // ofrecer agregarlo en un click. Útil para números que escribieron con @lid.
    if (conv.es_huerfana) {
        headBtns = `<button class="btn" onclick="abrirModalAgregarContacto()" title="Este número no está en el directorio"
            style="border-color:color-mix(in srgb,var(--info) 35%,transparent);color:var(--info);font-weight:600;">+ Agregar contacto</button>` + headBtns;
    } else if (conv.contacto_id) {
        // Atajo al legajo del paciente
        headBtns = `<a class="btn" href="/pacientes/${conv.contacto_id}/documentos" target="_blank" title="Ver documentos del paciente"
            style="text-decoration:none;border-color:color-mix(in srgb,var(--info) 35%,transparent);color:var(--info);">📁 Legajo</a>` + headBtns;
    }
    // Derivar a otra área (otro número de WhatsApp)
    headBtns += ` <button class="btn" onclick="derivarAreaPrompt(${id})" title="Derivar a otra área (otro número de WhatsApp)"
        style="border-color:color-mix(in srgb,var(--info) 35%,transparent);color:var(--info);">↪ Derivar área</button>`;

    // Mensajes — se omiten solo las respuestas automáticas del bot.
    // Las identificamos como saliente sin usuario y sin wa_id (el bot no guarda
    // wa_id en sus respuestas auto). Los salientes desde el celular de la
    // secretaria también vienen sin usuario, pero SÍ traen wa_id, así que se ven.
    const msgsVisibles = mensajes.filter(m => !(m.direccion === 'saliente' && !m.usuario && !m.wa_id));

    // Si hay una resolución previa con mensajes nuevos posteriores, dividimos:
    // por default mostramos solo lo nuevo, con botón para expandir lo resuelto.
    // La comparación se hace por timestamp (ts).
    const ultResol = (eventos || []).filter(e => e.tipo === 'resuelta').slice(-1)[0] || null;
    const haySegundaParte = ultResol && msgsVisibles.some(m => m.ts > ultResol.ts);

    // Render de un segmento del hilo (lista de items mensajes+eventos mezclados
    // cronológicamente). Cada item lleva __k para que sepamos qué dibujar.
    function renderItemsSegmento(items) {
        let lastFecha = null;
        return items.map(it => {
            let out = '';
            if (it.__k === 'msg') {
                if (it.fecha !== lastFecha) {
                    out += `<div class="msg-date">${it.fecha}</div>`;
                    lastFecha = it.fecha;
                }
                out += renderMsgWrap(it, true);
            } else {
                out += renderEventoInline(it);
            }
            return out;
        }).join('');
    }

    let msgHtml = '';
    if (haySegundaParte) {
        // Partimos por la última resolución: lo previo va al bloque colapsado,
        // lo nuevo va abierto. La última resolución NO se duplica como inline
        // (es el divider explícito). El resto de eventos sí va inline.
        const previos        = msgsVisibles.filter(m => m.ts <= ultResol.ts);
        const nuevos         = msgsVisibles.filter(m => m.ts >  ultResol.ts);
        const eventosPrevios = (eventos || []).filter(e => e.ts <  ultResol.ts);
        const eventosNuevos  = (eventos || []).filter(e => e.ts >  ultResol.ts);

        const itemsPrevios = mergearMensajesEventos(previos, eventosPrevios);
        const itemsNuevos  = mergearMensajesEventos(nuevos,  eventosNuevos);

        const dividerLabel = `✓ Resuelta el ${esc(ultResol.fecha)} por ${esc(ultResol.usuario || '—')}`;
        const previosBloque = `<div id="msg-historial-previos" style="display:none;">${renderItemsSegmento(itemsPrevios)}</div>`;
        const toggle = `<button class="msg-historial-toggle" onclick="toggleHistorialPrevios(this)">↑ Ver historial completo (${previos.length} mensajes anteriores · cerrados)</button>`;
        const divider = `<div class="msg-resol-divider"><div class="line"></div><div class="label">${dividerLabel}</div><div class="line"></div></div>`;
        msgHtml = toggle + previosBloque + divider + renderItemsSegmento(itemsNuevos);
    } else {
        msgHtml = renderItemsSegmento(mergearMensajesEventos(msgsVisibles, eventos || []));
    }

    const inputHtml = `
        <div class="panel-input" id="panel-input-area">
            <div id="file-preview" style="display:none" class="file-preview">
                <span id="file-preview-icon" style="font-size:20px;">📄</span>
                <span class="file-preview-name" id="file-preview-name"></span>
                <button class="file-preview-clear" onclick="limpiarArchivo()">✕</button>
            </div>
            <div class="input-modes" style="position:relative;">
                <button class="mode-btn ${state.panelModo === 'mensaje' ? 'active' : ''}" onclick="setModo('mensaje')">Mensaje</button>
                <button class="mode-btn ${state.panelModo === 'nota' ? 'active' : ''}" onclick="setModo('nota')">Nota interna</button>
                ${state.panelModo !== 'nota' ? `<div style="margin-left:auto;display:flex;gap:6px;align-items:center;position:relative;">
                    <button class="mode-btn" id="btn-rr" onclick="rrToggle(event)" title="Respuestas rápidas">📋 Respuestas ▾</button>
                    <button class="mode-btn" onclick="document.getElementById('file-input').click()">📎 Adjuntar archivo</button>
                </div>
                <input type="file" id="file-input" style="display:none" accept="image/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt" onchange="onFileChange(event)">` : ''}
                <div id="rr-menu" class="rr-menu" style="display:none;"></div>
            </div>
            <div class="input-row">
                <textarea class="msg-textarea" id="msg-input"
                    placeholder="${state.panelModo === 'nota' ? 'Nota interna (no se envía)' : 'Escribir mensaje...'}"
                    onkeydown="if(event.ctrlKey && event.key === 'Enter') enviarConArchivo()"></textarea>
                <button class="send-btn" id="btn-enviar" onclick="enviarConArchivo()">${state.panelModo === 'nota' ? 'Guardar' : 'Enviar'}</button>
            </div>
            <div style="font-size:10px;color:var(--muted);margin-top:4px;">Ctrl+Enter para enviar</div>
        </div>`;

    panelConv.innerHTML = `
        <div class="panel-head">
            <button onclick="cerrarPanel()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;">←</button>
            ${avatarHtml(conv.avatar_url, conv.contacto, 44, conv.contacto_id)}
            <div class="panel-head-info">
                <div class="panel-head-name">${esc(conv.contacto)}</div>
                <div class="panel-head-sub">${esc(conv.telefono)}</div>
            </div>
            <div class="panel-head-actions">${resumenTag(conv.resumen)} ${headBtns}</div>
        </div>
        <div class="msg-list" id="msg-list" onscroll="onMsgScroll()">${state.panelHasOlder ? '<div id="msg-load-older" style="text-align:center;padding:8px;font-size:11px;color:var(--muted);cursor:pointer;" onclick="cargarMensajesAnteriores()">Ver mensajes anteriores</div>' : ''}${msgHtml || '<div class="col-empty">Sin mensajes</div>'}</div>
        ${inputHtml}
    `;

    scrollBottom();
}

async function tomarYAbrir(id) {
    await post('/atencion/tomar', { id, tipo: 'wa' });
    const idx = state.nuevas.findIndex(i => i.id === id && i.tipo === 'wa');
    if (idx >= 0) {
        const item = { ...state.nuevas[idx], asig_id: ME_ID, asig_name: ME_NAME };
        state.nuevas.splice(idx, 1);
        state.enProceso.unshift(item);
    }
    renderColumnas();
    toast('Tomado');
    await cargarConversacion(id);
}

function resolverPanel() {
    resolverItem(state.panelId, state.panelTipo, { disabled: false, textContent: '' });
}

// Paginación de mensajes: cargar anteriores cuando el usuario llega arriba o
// hace click en "Ver mensajes anteriores".
let _cargandoOlder = false;
async function cargarMensajesAnteriores() {
    if (_cargandoOlder || !state.panelId || !state.panelHasOlder) return;
    _cargandoOlder = true;

    const list = document.getElementById('msg-list');
    const loader = document.getElementById('msg-load-older');
    if (loader) loader.textContent = 'Cargando…';

    // Tomar el id del primer mensaje actual (más antiguo) para usar como cursor.
    const primerMsg = list?.querySelector('[data-msg-id]');
    const beforeId = primerMsg?.dataset.msgId;
    if (!beforeId) { _cargandoOlder = false; return; }

    const scrollAntes = list.scrollHeight;

    try {
        const r = await get(`/atencion/conversacion/${state.panelId}?before_id=${beforeId}`);
        state.panelHasOlder = !!r.has_older;

        const html = r.mensajes
            .filter(m => !(m.direccion === 'saliente' && !m.usuario && !m.wa_id))
            .map(m => renderMsgWrap(m, true))
            .join('');

        if (loader) {
            loader.insertAdjacentHTML('afterend', html);
            if (state.panelHasOlder) {
                loader.textContent = 'Ver mensajes anteriores';
            } else {
                loader.remove();
            }
        }
        // Mantener la posición visual al insertar arriba
        list.scrollTop = list.scrollHeight - scrollAntes;
    } catch (e) {
        if (loader) loader.textContent = 'Error — reintentar';
    } finally {
        _cargandoOlder = false;
    }
}

function onMsgScroll() {
    const list = document.getElementById('msg-list');
    if (list && list.scrollTop < 60 && state.panelHasOlder) cargarMensajesAnteriores();
}

function setModo(modo) {
    state.panelModo = modo;
    if (modo === 'nota') limpiarArchivo();
    const inp = document.getElementById('msg-input');
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.toggle('active', b.textContent.toLowerCase().startsWith(modo === 'nota' ? 'nota' : 'mens')));
    if (inp) inp.placeholder = modo === 'nota' ? 'Nota interna (no se envía)' : 'Escribir mensaje...';
    // Ocultar/mostrar botón clip según modo
    const clip = document.querySelector('.clip-label');
    const fileInp = document.getElementById('file-input');
    if (clip) clip.style.display = modo === 'nota' ? 'none' : 'flex';
    if (fileInp) fileInp.style.display = 'none';
    const btn = document.querySelector('.send-btn');
    if (btn) btn.textContent = modo === 'nota' ? 'Guardar' : 'Enviar';
}

let _archivoSeleccionado = null;

function onFileChange(e) {
    const f = e.target.files[0];
    if (!f) return;
    _archivoSeleccionado = f;
    const icons = { 'image': '🖼️', 'video': '🎬', 'audio': '🎵', 'application/pdf': '📕' };
    const icon = icons[f.type.split('/')[0]] || icons[f.type] || '📄';
    document.getElementById('file-preview-icon').textContent = icon;
    document.getElementById('file-preview-name').textContent = f.name;
    document.getElementById('file-preview').style.display = 'flex';
    const inp = document.getElementById('msg-input');
    if (inp) inp.placeholder = 'Leyenda opcional...';
}

function limpiarArchivo() {
    _archivoSeleccionado = null;
    const fi = document.getElementById('file-input');
    if (fi) fi.value = '';
    document.getElementById('file-preview').style.display = 'none';
    const inp = document.getElementById('msg-input');
    if (inp) inp.placeholder = state.panelModo === 'nota' ? 'Nota interna (no se envía)' : 'Escribir mensaje...';
}

async function enviarConArchivo() {
    if (_archivoSeleccionado) {
        await enviarArchivo();
    } else {
        await enviar();
    }
}

async function enviarArchivo() {
    if (!_archivoSeleccionado || !state.panelId) return;
    const inp    = document.getElementById('msg-input');
    const caption = inp?.value?.trim() || '';
    const btn    = document.querySelector('.send-btn');
    if (btn) { btn.disabled = true; btn.textContent = '…'; }
    try {
        const fd = new FormData();
        fd.append('conv_id', state.panelId);
        fd.append('caption', caption);
        fd.append('archivo', _archivoSeleccionado);
        fd.append('_token', CSRF);
        const r = await fetch('/atencion/enviar-archivo', { method: 'POST', body: fd });
        if (!r.ok) throw new Error(r.status);
        if (inp) inp.value = '';
        limpiarArchivo();
        await cargarConversacion(state.panelId);
    } catch(e) {
        toast('Error al enviar archivo', 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Enviar'; }
    }
}

async function enviar() {
    const inp = document.getElementById('msg-input');
    const texto = inp?.value?.trim();
    if (!texto || !state.panelId) return;

    // Indicador de envío + bloqueo del input mientras la request está en vuelo.
    const btn = document.getElementById('btn-enviar');
    const txtOriginal = btn?.textContent;
    if (btn) { btn.disabled = true; btn.textContent = 'Enviando…'; }
    inp.disabled = true;

    try {
        await post('/atencion/enviar', { conv_id: state.panelId, texto, modo: state.panelModo });
        inp.value = '';
        await cargarConversacion(state.panelId);
    } catch(e) {
        // No llegó al destinatario — restauramos el texto para reintentar.
        toast(e.serverMsg || 'No se pudo enviar', 'error');
    } finally {
        inp.disabled = false;
        if (btn) { btn.disabled = false; btn.textContent = txtOriginal || 'Enviar'; }
        inp.focus();
    }
}

async function enviarConArchivoDerivacion(convId) {
    if (_archivoSeleccionado) {
        state.panelId = convId;
        await enviarArchivo();
    } else {
        await enviarDerivacion(convId);
    }
}

async function enviarDerivacion(convId) {
    const inp = document.getElementById('msg-input');
    const texto = inp?.value?.trim();
    if (!texto) return;
    inp.value = '';
    try {
        await post('/atencion/enviar', { conv_id: convId, texto, modo: state.panelModo });
        toast('Enviado');
    } catch(e) { toast('Error al enviar', 'error'); }
}

// ── Panel Derivación ─────────────────────────────────────────
async function cargarDerivacion(id) {
    const d = await get(`/atencion/derivacion/${id}`);
    const panelConv = document.getElementById('panel-conv');

    // ¿Está en proceso?
    const enProceso = state.enProceso.find(i => i.id === id && i.tipo === 'bot');
    const esMio     = parseInt(enProceso?.asig_id) === ME_ID;
    const tomado    = !!enProceso;

    const headBtns = !tomado
        ? `<button class="btn btn-tomar" onclick="tomarItem(${id},'bot',this)">Tomar</button>`
        : esMio
            ? `<button class="btn btn-resolver" onclick="resolverPanel()">✓ Resuelto</button>`
            : `<span style="font-size:11px;color:var(--muted);">Con: ${enProceso.asig_name}</span>`;

    const testBadge = d.es_prueba ? '<span class="badge badge-test">PRUEBA</span>' : '';
    const horBadge  = d.en_horario ? '' : '<span style="font-size:11px;color:var(--warning);">⚠ Fuera de horario</span>';

    // Si hay conversación WA asociada, mostrar el hilo completo
    let bodyHtml = '';
    let convCargada = false;
    let resumenConv = d.resumen || null;
    if (d.conv_id) {
        try {
            const { conv: convData, mensajes } = await get(`/atencion/conversacion/${d.conv_id}`);
            if (!resumenConv) resumenConv = convData.resumen;
            let lastFecha = null;
            const msgHtml = mensajes
                .filter(m => !(m.direccion === 'saliente' && !m.usuario && !m.wa_id))
                .map(m => {
                    let out = '';
                    if (m.fecha !== lastFecha) {
                        out += `<div class="msg-date">${m.fecha}</div>`;
                        lastFecha = m.fecha;
                    }
                    out += renderMsgWrap(m);
                    return out;
                }).join('');

            bodyHtml = `<div class="msg-list" id="msg-list">${msgHtml || '<div class="col-empty">Sin mensajes</div>'}</div>`;
            convCargada = true;
        } catch(e) {}
    }

    // Fallback si no hay conversación WA: mostrar texto plano de la derivación
    if (!convCargada) {
        bodyHtml = `<div class="der-detail">
            <h3>Conversación</h3>
            <div class="der-texto">${linkify(d.texto)}</div>
        </div>`;
    }

    const inputHtml = d.conv_id && (esMio || !tomado) ? `
        <div class="panel-input" id="der-input">
            <div id="file-preview" style="display:none" class="file-preview">
                <span id="file-preview-icon" style="font-size:20px;">📄</span>
                <span class="file-preview-name" id="file-preview-name"></span>
                <button class="file-preview-clear" onclick="limpiarArchivo()">✕</button>
            </div>
            <div class="input-modes">
                <button class="mode-btn ${state.panelModo==='mensaje'?'active':''}" onclick="setModo('mensaje')">Mensaje</button>
                <button class="mode-btn ${state.panelModo==='nota'?'active':''}" onclick="setModo('nota')">Nota interna</button>
                ${state.panelModo!=='nota'?`<button class="mode-btn" onclick="document.getElementById('file-input-der').click()" style="margin-left:auto;">📎 Adjuntar archivo</button>
                <input type="file" id="file-input-der" style="display:none" accept="image/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt" onchange="onFileChange(event)">` : ''}
            </div>
            <div class="input-row">
                <textarea class="msg-textarea" id="msg-input"
                    placeholder="${state.panelModo==='nota'?'Nota interna (no se envía)':'Escribir mensaje...'}"
                    onkeydown="if(event.ctrlKey&&event.key==='Enter')enviarConArchivoDerivacion(${d.conv_id})"></textarea>
                <button class="send-btn" onclick="enviarConArchivoDerivacion(${d.conv_id})">${state.panelModo==='nota'?'Guardar':'Enviar'}</button>
            </div>
            <div style="font-size:10px;color:var(--muted);margin-top:4px;">Ctrl+Enter para enviar</div>
        </div>` : '';

    panelConv.innerHTML = `
        <div class="panel-head">
            <button onclick="cerrarPanel()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;">←</button>
            <div class="panel-head-info">
                <div class="panel-head-name">${esc(d.contacto)} ${testBadge}</div>
                <div class="panel-head-sub">${esc(d.etiqueta)} · ${d.hace} ${horBadge}</div>
            </div>
            <div class="panel-head-actions">${resumenTag(resumenConv)} ${headBtns}</div>
        </div>
        ${bodyHtml}
        ${inputHtml}
    `;

    if (convCargada) scrollBottom();
}

// ── Modal de adjuntos ────────────────────────────────────────
function abrirModal(url, tipo, nombre) {
    const content = document.getElementById('media-modal-content');
    if (tipo === 'imagen') {
        content.innerHTML = `<img src="${url}" style="max-width:90vw;max-height:88vh;border-radius:8px;display:block;">`;
    } else if (tipo === 'video') {
        content.innerHTML = `<video controls autoplay src="${url}" style="max-width:90vw;max-height:88vh;border-radius:8px;display:block;"></video>`;
    } else {
        // Documentos: PDF en iframe, resto como descarga
        const ext = url.split('.').pop().split('?')[0].toLowerCase();
        if (ext === 'pdf') {
            content.innerHTML = `<iframe src="${url}" style="width:82vw;height:88vh;border:none;border-radius:8px;background:#fff;"></iframe>`;
        } else {
            const n = esc(nombre || 'Documento');
            content.innerHTML = `<div style="background:var(--card);padding:40px 48px;border-radius:12px;text-align:center;">
                <div style="font-size:52px;margin-bottom:14px;">📄</div>
                <div style="color:var(--text);font-size:14px;margin-bottom:20px;max-width:260px;word-break:break-all;">${n}</div>
                <a href="${url}" download style="background:var(--accent);color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-size:14px;">⬇ Descargar</a>
            </div>`;
        }
    }
    document.getElementById('media-modal').style.display = 'flex';
    document.addEventListener('keydown', _modalEsc);
}

function cerrarModal() {
    document.getElementById('media-modal').style.display = 'none';
    document.getElementById('media-modal-content').innerHTML = '';
    document.removeEventListener('keydown', _modalEsc);
}

function _modalEsc(e) { if (e.key === 'Escape') cerrarModal(); }

// ── Render cuerpo de mensaje según tipo ──────────────────────
// Render de un mensaje envuelto. omitDate=true cuando la separación de fechas
// la maneja el caller (cargarConversacion). Cada wrapper lleva data-msg-id para
// que la paginación encuentre el mensaje más antiguo.
function renderMsgWrap(m) {
    const cuerpo = renderMsgCuerpo(m);
    if (m.direccion === 'nota_interna') {
        const autor = m.usuario ? `<span style="font-size:10px;opacity:.7;"> — ${esc(m.usuario)}</span>` : '';
        return `<div class="msg-wrap nota" data-msg-id="${m.id}">
            <div class="msg-bubble nota">📝 ${linkify(m.contenido)}${autor}</div>
        </div>`;
    }
    if (m.direccion === 'entrante') {
        return `<div class="msg-wrap in" data-msg-id="${m.id}"><div>
            <div class="msg-bubble in">${cuerpo}</div>
            <div class="msg-time">${m.hora}</div>
        </div></div>`;
    }
    const autor = m.usuario ? `<div style="font-size:10px;color:rgba(255,255,255,.5);margin-bottom:2px;">${esc(m.usuario)}</div>` : '';
    return `<div class="msg-wrap out" data-msg-id="${m.id}"><div>
        ${autor}<div class="msg-bubble out">${cuerpo}</div>
        <div class="msg-time right">${m.hora}</div>
    </div></div>`;
}

function renderMsgCuerpo(m) {
    if (m.tipo === 'audio') {
        const transcripcion = m.contenido
            ? `<div style="font-size:13px;color:var(--text);line-height:1.5;">${linkify(m.contenido)}</div>`
            : `<div style="font-size:12px;color:var(--muted);font-style:italic;">Sin transcripción disponible</div>`;
        const player = m.archivo_url
            ? `<div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.08);">
                   <div style="font-size:10px;font-weight:600;color:var(--muted);letter-spacing:.4px;margin-bottom:5px;">AUDIO ORIGINAL</div>
                   <audio controls src="${m.archivo_url}" style="height:34px;width:220px;display:block;"></audio>
               </div>`
            : '';
        return `<div style="font-size:11px;font-weight:600;color:var(--muted);letter-spacing:.4px;margin-bottom:6px;">🎤 MENSAJE DE VOZ</div>
                <div style="font-size:10px;font-weight:600;color:var(--muted);letter-spacing:.4px;margin-bottom:4px;">TRANSCRIPCIÓN</div>
                ${transcripcion}
                ${player}`;
    }
    if (m.tipo === 'imagen') {
        return `<img src="${m.archivo_url}" alt="Imagen"
                    style="max-width:240px;max-height:200px;border-radius:6px;display:block;cursor:zoom-in;"
                    onclick="abrirModal('${m.archivo_url}','imagen')">
                ${m.contenido ? `<div style="font-size:12px;margin-top:5px;">${linkify(m.contenido)}</div>` : ''}`;
    }
    if (m.tipo === 'documento') {
        const nombre = esc(m.contenido || 'Documento');
        return `<div style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:4px 0;"
                     onclick="abrirModal('${m.archivo_url}','documento','${nombre}')">
                    <span style="font-size:26px;line-height:1;">📄</span>
                    <span style="color:var(--info);text-decoration:underline;font-size:13px;word-break:break-all;">${nombre}</span>
                </div>`;
    }
    if (m.tipo === 'video') {
        return `<video controls src="${m.archivo_url}"
                    style="max-width:260px;max-height:180px;border-radius:6px;display:block;cursor:zoom-in;"
                    onclick="this.paused&&abrirModal('${m.archivo_url}','video')"></video>
                ${m.contenido ? `<div style="font-size:12px;margin-top:5px;">${linkify(m.contenido)}</div>` : ''}`;
    }
    return linkify(m.contenido);
}

// ── Diff de listas (no re-renderizar si no cambió nada) ───────
let _hashNuevas = '', _hashProceso = '';

function hashItems(items) {
    return items.map(i => `${i.id}${i.tipo}${i.urgente}${i.asig_id}${i.no_leidos}${state.panelId===i.id&&state.panelTipo===i.tipo}`).join('|');
}

function aplicarFiltro(items) {
    const f = state.filtro || 'todas';
    if (f === 'urgentes') return items.filter(i => i.urgente);
    if (f === 'mias')     return items.filter(i => i.asig_id === ME_ID);
    return items;
}

function setFiltro(f) {
    state.filtro = f;
    document.querySelectorAll('.filter-chip').forEach(el => {
        el.classList.toggle('active', el.dataset.filtro === f);
    });
    // Forzar re-render aunque no haya cambiado el set crudo.
    _hashNuevas = '__force__'; _hashProceso = '__force__';
    renderColumnas();
}

function renderColumnas() {
    const nuevas    = aplicarFiltro(state.nuevas);
    const enProceso = aplicarFiltro(state.enProceso);

    const hN = hashItems(nuevas);
    const hP = hashItems(enProceso);
    const changed = hN !== _hashNuevas || hP !== _hashProceso;
    _hashNuevas  = hN;
    _hashProceso = hP;

    // Si hay filtro activo (urgentes/mias), mostrar la cuenta filtrada (post-filtro).
    // Si no hay filtro, mostrar el TOTAL real (puede superar el limit de 100 cargado).
    const filtroActivo = (state.filtro && state.filtro !== 'todas');
    const cntNuevas    = filtroActivo ? nuevas.length    : (state.totalNuevas  ?? nuevas.length);
    const cntProceso   = filtroActivo ? enProceso.length : (state.totalProceso ?? enProceso.length);

    // Mostrar "287 (mostrando 100)" si el limit cortó la lista.
    const fmtCount = (filtrado, total, mostrados) => {
        if (filtrado) return String(total);
        if (total > mostrados) return `${total} (mostrando ${mostrados})`;
        return String(total);
    };

    document.getElementById('cnt-nuevas').textContent  = fmtCount(filtroActivo, cntNuevas,  nuevas.length);
    document.getElementById('cnt-proceso').textContent = fmtCount(filtroActivo, cntProceso, enProceso.length);
    document.getElementById('counts').textContent =
        `${cntNuevas} nuevas · ${cntProceso} en proceso`;

    if (!changed) return; // nada cambió, no tocar el DOM

    document.getElementById('list-nuevas').innerHTML =
        nuevas.length ? nuevas.map(i => renderCard(i, 'nuevas')).join('') :
        '<div class="col-empty">Sin elementos nuevos</div>';

    document.getElementById('list-proceso').innerHTML =
        enProceso.length ? enProceso.map(i => renderCard(i, 'proceso')).join('') :
        '<div class="col-empty">Nada en proceso</div>';
}

// ── Utilidades ───────────────────────────────────────────────
// Reemplaza un <img> que falló al cargar por el div con la inicial. Disparado desde onerror.
function _avFallback(img) {
    const size = parseInt(img.dataset.size) || 36;
    const cid  = parseInt(img.dataset.cid)  || 0;
    const ini  = img.dataset.ini || '?';
    img.outerHTML = _avFallbackHtml(ini, size, cid);
}

function _avFallbackHtml(inicial, size, contactoId) {
    const fontSize = Math.max(10, Math.floor(size / 2.4));
    const cursor   = contactoId ? 'cursor:pointer;' : '';
    const onclickA = contactoId ? ` onclick="event.stopPropagation();abrirFicha(${contactoId})"` : '';
    const titleA   = contactoId ? ' title="Ver ficha del contacto"' : '';
    return `<div class="av-fallback" style="width:${size}px;height:${size}px;font-size:${fontSize}px;${cursor}"${onclickA}${titleA}>${esc(inicial)}</div>`;
}

// Avatar circular: si hay url, lo intenta cargar con fallback a inicial. Si no, fallback directo.
// Si se pasa contactoId, el avatar es clickeable y abre la ficha del contacto.
function avatarHtml(url, nombre, size = 36, contactoId = null) {
    const inicial = (nombre || '?').trim().charAt(0).toUpperCase();
    if (!url) return _avFallbackHtml(inicial, size, contactoId);
    const cursor   = contactoId ? 'cursor:pointer;' : '';
    const onclickA = contactoId ? ` onclick="event.stopPropagation();abrirFicha(${contactoId})"` : '';
    const titleA   = contactoId ? ' title="Ver ficha del contacto"' : '';
    return `<img class="av-circle" src="${esc(url)}" alt="${esc(nombre)}"
        style="width:${size}px;height:${size}px;${cursor}"${onclickA}${titleA}
        data-size="${size}" data-cid="${contactoId || ''}" data-ini="${esc(inicial)}"
        onerror="_avFallback(this)">`;
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function linkify(s) {
    if (!s) return '';
    // Dividir ANTES de escapar para no romper URLs con &
    return s.split(/(https?:\/\/[^\s<>"]+)/g).map((part, i) => {
        if (i % 2 === 1) {
            const href = esc(part);
            return `<a href="${href}" target="_blank" rel="noopener" style="color:var(--info);text-decoration:underline;word-break:break-all;">${href}</a>`;
        }
        return esc(part);
    }).join('');
}

function scrollBottom() {
    const el = document.getElementById('msg-list');
    if (el) el.scrollTop = el.scrollHeight;
}

// ── Modal "Nueva conversación" ───────────────────────────────
const PLANTILLAS = [
    { titulo: 'En blanco', texto: '' },
    { titulo: 'Recordatorio de turno',  texto: 'Hola! Te recordamos tu turno en Crecer Reproducción. Cualquier consulta, escribinos por acá. Saludos!' },
    { titulo: 'Confirmar receta lista', texto: 'Hola! Tu receta ya está lista para retirar. Te esperamos en el horario habitual. Saludos!' },
    { titulo: 'Solicitar muestra',      texto: 'Hola! Necesitamos coordinar una nueva toma de muestra. ¿Cuándo te queda cómodo pasar? Saludos!' },
    { titulo: 'Pedido de información',  texto: 'Hola! Soy de Crecer Reproducción. Necesitaríamos consultarte algunos datos. ¿Podés responderme cuando puedas? Gracias!' },
];

let _nuevaModo = 'contacto';      // contacto | manual
let _nuevaContactoSel = null;
let _nuevaSearchTimer = null;

function abrirModalNueva() {
    _nuevaModo = 'contacto';
    _nuevaContactoSel = null;
    const m = document.getElementById('modal-nueva');
    m.style.display = 'flex';
    m.className = 'modal-backdrop';
    m.innerHTML = `
        <div class="modal-card" onclick="event.stopPropagation()">
            <div class="modal-head">
                <span class="modal-title">Iniciar nueva conversación</span>
                <button class="modal-close" onclick="cerrarModalNueva()" aria-label="Cerrar">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="modal-tab active" id="tab-contacto" onclick="setModoNueva('contacto')">Buscar contacto</button>
                    <button class="modal-tab"        id="tab-manual"   onclick="setModoNueva('manual')">Número manual</button>
                </div>

                <div id="seccion-contacto">
                    <label class="modal-label">Nombre, teléfono o DNI</label>
                    <input class="modal-input" id="nueva-search" placeholder="Empezá a tipear…" oninput="buscarContactoNueva(this.value)" autofocus>
                    <div class="modal-search-results" id="nueva-resultados" style="display:none;"></div>
                </div>

                <div id="seccion-manual" style="display:none;">
                    <label class="modal-label">Número de WhatsApp</label>
                    <input class="modal-input" id="nueva-telefono" placeholder="ej: 1123456789 o 5491123456789" inputmode="numeric">
                    <div style="font-size:11px;color:var(--muted);margin-top:4px;">Argentina · 10 dígitos con código de área (sin 0 ni 15)</div>
                </div>

                <label class="modal-label">Plantilla rápida</label>
                <select class="modal-input" onchange="aplicarPlantillaNueva(this.value)">
                    ${PLANTILLAS.map((p,i) => `<option value="${i}">${esc(p.titulo)}</option>`).join('')}
                </select>

                <label class="modal-label">Mensaje inicial</label>
                <textarea class="modal-input modal-textarea" id="nueva-texto" placeholder="Escribí el primer mensaje…"></textarea>
            </div>
            <div class="modal-foot">
                <button class="btn-modal-secondary" onclick="cerrarModalNueva()">Cancelar</button>
                <button class="btn-modal-primary" id="nueva-enviar" onclick="enviarNuevaConversacion()">Iniciar y enviar</button>
            </div>
        </div>
    `;
    m.onclick = (e) => { if (e.target === m) cerrarModalNueva(); };
    document.addEventListener('keydown', escNueva);
}

function escNueva(e) { if (e.key === 'Escape') cerrarModalNueva(); }

function cerrarModalNueva() {
    const m = document.getElementById('modal-nueva');
    m.style.display = 'none';
    m.innerHTML = '';
    document.removeEventListener('keydown', escNueva);
}

// ── Modal "Agregar a contactos" desde conversación huérfana ──────
function abrirModalAgregarContacto() {
    const conv = state.panelConv;
    if (!conv) return;
    const m = document.getElementById('modal-agregar-contacto');
    m.style.display = 'flex';
    m.className = 'modal-backdrop';
    m.innerHTML = `
        <div class="modal-card" onclick="event.stopPropagation()">
            <div class="modal-head">
                <span class="modal-title">Agregar al directorio</span>
                <button class="modal-close" onclick="cerrarModalAgregarContacto()" aria-label="Cerrar">×</button>
            </div>
            <div class="modal-body">
                <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">
                    Esta conversación es de un número que no está en tu directorio. Agregalo en un click para que aparezca con su nombre la próxima vez.
                </div>

                <label class="modal-label">Nombre completo *</label>
                <input class="modal-input" id="ac-nombre" placeholder="Ej: Juan Pérez" autofocus>

                <label class="modal-label">Teléfono *</label>
                <input class="modal-input" id="ac-telefono" value="${esc(conv.telefono_sugerido || '')}" placeholder="549...">
                ${conv.jid && conv.jid.endsWith('@lid') && !conv.telefono_sugerido ? `
                    <div style="font-size:11px;color:var(--warning);margin-top:4px;">
                        ⚠ El JID es @lid y no se pudo resolver el número real automáticamente. Ingresá manualmente.
                    </div>` : ''}

                <label class="modal-label">DNI (opcional)</label>
                <input class="modal-input" id="ac-dni" placeholder="Sin puntos">

                <div style="font-size:11px;color:var(--muted);margin-top:10px;">
                    El JID de WhatsApp <code style="font-family:monospace;color:var(--info);">${esc(conv.jid)}</code> queda vinculado automáticamente.
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn-modal-secondary" onclick="cerrarModalAgregarContacto()">Cancelar</button>
                <button class="btn-modal-primary" id="ac-guardar" onclick="guardarContactoDesdeConv()">Guardar contacto</button>
            </div>
        </div>
    `;
    m.onclick = (e) => { if (e.target === m) cerrarModalAgregarContacto(); };
}

function cerrarModalAgregarContacto() {
    const m = document.getElementById('modal-agregar-contacto');
    m.style.display = 'none';
    m.innerHTML = '';
}

async function guardarContactoDesdeConv() {
    const nombre   = document.getElementById('ac-nombre').value.trim();
    const telefono = document.getElementById('ac-telefono').value.trim();
    const dni      = document.getElementById('ac-dni').value.trim();
    if (!nombre || !telefono) { toast('Nombre y teléfono son obligatorios', 'error'); return; }

    const btn = document.getElementById('ac-guardar');
    btn.disabled = true; btn.textContent = 'Guardando…';

    try {
        await post(`/atencion/conversacion/${state.panelId}/agregar-contacto`, { nombre, telefono, dni });
        cerrarModalAgregarContacto();
        toast('Contacto agregado y vinculado');
        await cargarConversacion(state.panelId);
        await fetchItems();
    } catch (e) {
        toast(e.serverMsg || 'No se pudo agregar', 'error');
        btn.disabled = false; btn.textContent = 'Guardar contacto';
    }
}

function setModoNueva(modo) {
    _nuevaModo = modo;
    _nuevaContactoSel = null;
    document.getElementById('tab-contacto').classList.toggle('active', modo === 'contacto');
    document.getElementById('tab-manual').classList.toggle('active', modo === 'manual');
    document.getElementById('seccion-contacto').style.display = modo === 'contacto' ? '' : 'none';
    document.getElementById('seccion-manual').style.display   = modo === 'manual'   ? '' : 'none';
}

function aplicarPlantillaNueva(idx) {
    const p = PLANTILLAS[parseInt(idx)];
    if (p) document.getElementById('nueva-texto').value = p.texto;
}

async function buscarContactoNueva(q) {
    clearTimeout(_nuevaSearchTimer);
    const cont = document.getElementById('nueva-resultados');
    if (!q || q.length < 2) {
        cont.style.display = 'none'; cont.innerHTML = ''; return;
    }
    _nuevaSearchTimer = setTimeout(async () => {
        try {
            const r = await get('/contactos/data?q=' + encodeURIComponent(q));
            const items = (r.data || []).filter(c => c.telefono).slice(0, 10);
            if (items.length === 0) {
                cont.style.display = 'block';
                cont.innerHTML = '<div class="modal-search-item" style="cursor:default;color:var(--muted);">Sin resultados con teléfono</div>';
                return;
            }
            cont.style.display = 'block';
            cont.innerHTML = items.map(c => `
                <div class="modal-search-item" onclick="seleccionarContactoNueva(${c.id}, '${esc(c.nombre).replace(/'/g, "\\'")}', '${esc(c.telefono)}')">
                    <div class="nombre">${esc(c.nombre)}</div>
                    <div class="tel">${esc(c.telefono)}${c.dni ? ' · DNI ' + esc(c.dni) : ''}</div>
                </div>
            `).join('');
        } catch (e) { /* silencioso */ }
    }, 250);
}

function seleccionarContactoNueva(id, nombre, telefono) {
    _nuevaContactoSel = { id, nombre, telefono };
    const cont = document.getElementById('nueva-resultados');
    cont.innerHTML = `<div class="modal-search-item selected">
        <div class="nombre">✓ ${esc(nombre)}</div>
        <div class="tel">${esc(telefono)}</div>
    </div>`;
    document.getElementById('nueva-search').value = nombre;
}

async function enviarNuevaConversacion() {
    const texto = document.getElementById('nueva-texto').value.trim();
    if (!texto) { toast('Falta el mensaje', 'error'); return; }

    const body = { texto, area: @json($area ?? 'atencion') };
    if (_nuevaModo === 'contacto') {
        if (!_nuevaContactoSel) { toast('Seleccioná un contacto', 'error'); return; }
        body.contacto_id = _nuevaContactoSel.id;
    } else {
        const tel = document.getElementById('nueva-telefono').value.trim();
        if (!tel) { toast('Falta el número', 'error'); return; }
        body.telefono = tel;
    }

    const btn = document.getElementById('nueva-enviar');
    btn.disabled = true;
    btn.textContent = 'Verificando…';

    try {
        const r = await post('/atencion/iniciar', body);
        cerrarModalNueva();
        toast(r.reusada ? 'Conversación reabierta' : 'Conversación creada y mensaje enviado');
        await fetchItems();
        // Abrir el panel con la nueva conversación
        if (r.conv_id) {
            setTimeout(() => verItem(r.conv_id, 'wa'), 200);
        }
    } catch (e) {
        toast(e.serverMsg || 'No se pudo iniciar la conversación', 'error');
        btn.disabled = false;
        btn.textContent = 'Iniciar y enviar';
    }
}

// ── Derivar a otra área ──────────────────────────────────────
function derivarAreaPrompt(convId) {
    const AREAS_MENU = { atencion: 'Clínica', administracion: 'Administración', ovodonacion: 'Ovodonación' };
    const old = document.getElementById('derivar-area-modal');
    if (old) old.remove();
    const opciones = Object.entries(AREAS_MENU).map(([k, l]) =>
        `<button class="btn" style="margin:4px;" onclick="confirmarDerivarArea(${convId}, '${k}')">${l}</button>`
    ).join('');
    const div = document.createElement('div');
    div.id = 'derivar-area-modal';
    div.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:2000;display:flex;align-items:center;justify-content:center;';
    div.innerHTML = `<div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;width:min(440px,92vw);">
        <div style="font-weight:600;font-size:15px;margin-bottom:8px;">Derivar a otra área</div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:14px;line-height:1.5;">Se le avisa al paciente por el número actual que va a tener respuesta desde el número del área elegida, y la conversación pasa a esa cola.</div>
        <div style="display:flex;flex-wrap:wrap;">${opciones}</div>
        <div style="text-align:right;margin-top:14px;"><button class="btn" onclick="document.getElementById('derivar-area-modal').remove()">Cancelar</button></div>
    </div>`;
    div.onclick = (e) => { if (e.target === div) div.remove(); };
    document.body.appendChild(div);
}

async function confirmarDerivarArea(convId, area) {
    const m = document.getElementById('derivar-area-modal');
    if (m) m.querySelectorAll('button').forEach(b => b.disabled = true);
    try {
        const r = await post(`/atencion/conversacion/${convId}/derivar-area`, { area });
        if (m) m.remove();
        toast(r.mensaje || 'Derivada ✓');
        cerrarPanel();
        await fetchItems();
    } catch (e) {
        if (m) m.querySelectorAll('button').forEach(b => b.disabled = false);
        toast(e.serverMsg || 'No se pudo derivar', 'error');
    }
}

// ── Polling ──────────────────────────────────────────────────
// Render inmediato con datos del servidor, luego polling cada 8s
renderColumnas();
setTimeout(fetchItems, 2000);          // primer refresh a los 2s
setInterval(fetchItems, 8000);

// Auto-refresh de la conversación abierta en el panel: sin esto el hilo
// queda congelado hasta que el operador haga algo (enviar, cerrar/reabrir).
// Pollea cada 8s solo si hay panel abierto y la pestaña está visible.
// Antes de re-render guarda si el usuario estaba al fondo del scroll, para
// no romperle la lectura si está mirando un mensaje anterior.
async function refrescarConvAbierta() {
    if (document.hidden) return;
    if (!state.panelId || !state.panelTipo) return;
    if (state.panelTipo !== 'wa') return;   // las derivaciones del bot no tienen polling útil

    // Fetch primero, SIN tocar el DOM. El usuario puede seguir tipeando
    // tranquilo durante esta espera (~200-500 ms).
    const idAlFetch = state.panelId;
    let datos;
    try {
        datos = await get(`/atencion/conversacion/${idAlFetch}`);
    } catch (e) { return; }

    // Si entre el inicio y el fin del fetch el operador cambió de panel,
    // descartamos: pisaríamos un panel distinto al que pidió.
    if (state.panelId !== idAlFetch) return;

    // Captura del estado del composer JUSTO antes del replace. Esto incluye
    // cualquier cosa que el operador haya tipeado durante el fetch (que el
    // viejo `textoActual` capturado pre-fetch se perdía con el replace).
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

    // Si el operador desplegó el bloque de "Ver historial completo" (mensajes
    // previos a una resolución anterior), preservar ese estado: el render
    // siempre genera el bloque con display:none y el toggle vuelve al label
    // inicial, así que sin esto el polling cada 8s lo cerraba solo.
    const historialAntes = document.getElementById('msg-historial-previos');
    const historialEstabaAbierto = historialAntes && historialAntes.style.display !== 'none';

    // Replace
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

    // Restaurar histórico expandido si lo estaba antes del replace
    if (historialEstabaAbierto) {
        const historialNuevo = document.getElementById('msg-historial-previos');
        const toggleBtn = document.querySelector('.msg-historial-toggle');
        if (historialNuevo && toggleBtn) {
            if (!toggleBtn.dataset.lbl) toggleBtn.dataset.lbl = toggleBtn.textContent;
            historialNuevo.style.display = '';
            toggleBtn.textContent = '↓ Ocultar historial anterior';
        }
    }

    // Si el operador tiene un archivo adjunto (en la variable _archivoSeleccionado),
    // el preview se borró con el replace. Lo volvemos a mostrar.
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

    // Restaurar scroll: si estaba al fondo, ir al fondo (sigue leyendo nuevos
    // mensajes). Si estaba leyendo más arriba (típicamente histórico expandido),
    // restaurar la posición exacta para no perder la línea de lectura.
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
function rrToggle(ev) {
    if (ev) ev.stopPropagation();
    const menu = document.getElementById('rr-menu');
    if (!menu) return;
    if (menu.style.display === 'block') { menu.style.display = 'none'; return; }
    rrRender();
    menu.style.display = 'block';
    setTimeout(() => document.addEventListener('click', rrCerrarFuera, { once: true }), 0);
}
function rrCerrarFuera(e) {
    const menu = document.getElementById('rr-menu');
    if (!menu) return;
    if (!menu.contains(e.target) && e.target.id !== 'btn-rr') menu.style.display = 'none';
    else setTimeout(() => document.addEventListener('click', rrCerrarFuera, { once: true }), 0);
}
function rrRender() {
    const menu = document.getElementById('rr-menu');
    if (!menu) return;
    if (!RESPUESTAS_RAPIDAS.length) {
        menu.innerHTML = `<div class="rr-menu-empty">Sin respuestas rápidas para esta área.</div>
            <div class="rr-menu-foot"><a href="/admin/respuestas-rapidas" target="_blank">+ Agregar</a></div>`;
        return;
    }
    menu.innerHTML = RESPUESTAS_RAPIDAS.map(r =>
        `<div class="rr-menu-item" onclick="rrInsertar(${r.id})">
            <div class="rr-titulo">${esc(r.titulo)}</div>
            <div class="rr-preview">${esc(r.texto.slice(0, 80))}</div>
        </div>`
    ).join('')
    + `<div class="rr-menu-foot"><a href="/admin/respuestas-rapidas" target="_blank">Administrar respuestas</a></div>`;
}
function rrInsertar(id) {
    const r = RESPUESTAS_RAPIDAS.find(x => x.id === id);
    if (!r) return;
    const inp = document.getElementById('msg-input');
    if (!inp) return;
    inp.value = r.texto;   // reemplaza el texto actual; el operador puede editar antes de enviar
    inp.focus();
    inp.dispatchEvent(new Event('input'));
    document.getElementById('rr-menu').style.display = 'none';
}

// Auto-abrir conversación si viene ?conv_id=N en la URL (deep-link desde Contactos)
(function() {
    const m = window.location.search.match(/[?&]conv_id=(\d+)/);
    if (m) setTimeout(() => verItem(parseInt(m[1]), 'wa'), 800);
})();

// ── Ficha de contacto (modal read-only abierto desde click en avatar) ──
async function abrirFicha(id) {
    if (!id) return;
    const m = document.getElementById('ficha-modal');
    const body = document.getElementById('ficha-body');
    body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--muted);font-size:13px;">Cargando…</div>';
    m.style.display = 'flex';
    try {
        const r = await fetch('/contactos/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const d = await r.json();
        renderFicha(d.contacto);
    } catch (e) {
        body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--error);font-size:13px;">No se pudo cargar la ficha.</div>';
    }
}

function cerrarFicha() {
    document.getElementById('ficha-modal').style.display = 'none';
}

function renderFicha(c) {
    const inicial = (c.nombre || '?').trim().charAt(0).toUpperCase();
    const avatar = c.avatar_url
        ? `<img src="${esc(c.avatar_url)}" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:1px solid var(--border);cursor:zoom-in;" onclick="verAvatarGrande('${esc(c.avatar_url)}')" title="Click para agrandar">`
        : `<div style="width:120px;height:120px;border-radius:50%;background:var(--surface);border:1px solid var(--border);display:inline-flex;align-items:center;justify-content:center;font-size:46px;font-weight:700;color:var(--muted);">${esc(inicial)}</div>`;

    const fila = (label, val) => val
        ? `<div style="display:flex;gap:10px;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;">
             <span style="color:var(--muted);min-width:110px;">${label}</span>
             <span style="color:var(--text);">${esc(val)}</span>
           </div>` : '';

    const fNac = c.fecha_nacimiento ? String(c.fecha_nacimiento).slice(0, 10) : '';

    document.getElementById('ficha-body').innerHTML = `
        <div style="text-align:center;margin-bottom:18px;">${avatar}</div>
        <div style="text-align:center;margin-bottom:14px;">
            <div style="font-size:17px;font-weight:700;">${esc(c.nombre)}</div>
            ${c.omnia_patient_id ? '<span style="margin-top:4px;display:inline-block;font-size:10px;background:rgba(26,86,196,.15);color:var(--info);border-radius:4px;padding:2px 7px;">Omnia</span>' : ''}
        </div>
        ${fila('Teléfono', c.telefono)}
        ${fila('DNI', c.dni)}
        ${fila('Email', c.email)}
        ${fila('Nacimiento', fNac)}
        ${c.notas ? `<div style="padding:10px 0;font-size:12px;color:var(--muted);"><div style="text-transform:uppercase;letter-spacing:.4px;font-size:10px;margin-bottom:4px;">Notas</div><div style="color:var(--text);font-size:13px;white-space:pre-wrap;">${esc(c.notas)}</div></div>` : ''}
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px;padding-top:14px;border-top:1px solid var(--border);">
            <a href="/pacientes/${c.id}/documentos" target="_blank"
               style="text-decoration:none;background:none;border:1px solid color-mix(in srgb,var(--info) 35%,transparent);color:var(--info);border-radius:6px;padding:6px 14px;font-size:13px;font-weight:600;">📁 Legajo</a>
            <a href="/contactos" target="_blank"
               style="text-decoration:none;background:var(--surface);border:1px solid var(--border);color:var(--muted);border-radius:6px;padding:6px 14px;font-size:13px;">Editar</a>
            <button onclick="cerrarFicha()"
                    style="background:var(--accent);border:none;color:#fff;border-radius:6px;padding:6px 14px;font-size:13px;font-weight:600;cursor:pointer;">Cerrar</button>
        </div>
    `;
}

function verAvatarGrande(url) {
    document.getElementById('avatar-zoom-img').src = url;
    document.getElementById('avatar-zoom').style.display = 'flex';
}
</script>

{{-- Modal ficha de contacto (read-only) --}}
<div id="ficha-modal" onclick="if(event.target===this)cerrarFicha()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9100;align-items:center;justify-content:center;">
    <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:22px;width:440px;max-width:94vw;max-height:90vh;overflow-y:auto;">
        <div id="ficha-body"></div>
    </div>
</div>

{{-- Lightbox del avatar --}}
<div id="avatar-zoom" onclick="this.style.display='none'"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9200;align-items:center;justify-content:center;cursor:zoom-out;">
    <img id="avatar-zoom-img" alt="" style="max-width:90vw;max-height:90vh;border-radius:10px;box-shadow:0 16px 48px rgba(0,0,0,.5);">
</div>

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
            <button class="btn btn-resolver" id="rx-confirmar" onclick="confirmarReenvio()" disabled>Reenviar y archivar</button>
        </div>
    </div>
</div>

<script>
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
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                contacto_id: _rxContacto.id,
                comentario: document.getElementById('rx-comentario').value.trim() || null,
            }),
        });
        const d = await r.json();
        if (!r.ok || !d.ok) throw new Error(d.error || ('HTTP ' + r.status));
        toast(`Reenviado a ${d.destino} ✓`);
        cerrarModalReenvio();
        cerrarPanel();
        await fetchItems();
    } catch (e) {
        toast('Error al reenviar: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Reenviar y archivar';
    }
}
</script>

@endsection
