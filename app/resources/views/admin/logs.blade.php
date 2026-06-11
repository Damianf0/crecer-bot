@extends($layout ?? 'layouts.app')
@section('title', 'Admin · Logs')

@section('content')
@include('admin._nav')

<style>
.lg-wrap { max-width: 1100px; }
.lg-toolbar {
    display: flex; gap: 10px; align-items: center;
    margin-bottom: 12px; flex-wrap: wrap;
}
.lg-btn {
    padding: 6px 12px; border-radius: 6px;
    border: 1px solid var(--border); background: var(--card);
    color: var(--muted); cursor: pointer; font-size: 12px;
}
.lg-btn:hover { color: var(--text); }
.lg-btn.active { color: var(--text); border-color: var(--info); background: color-mix(in srgb, var(--info) 8%, transparent); }
.lg-search {
    flex: 1; min-width: 180px;
    padding: 6px 10px; border-radius: 6px;
    border: 1px solid var(--border); background: var(--card);
    color: var(--text); font-size: 12px; font-family: inherit;
}
.lg-status {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11px; color: var(--muted);
}
.lg-status .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--success); }
.lg-status.off .dot { background: var(--error); }

.lg-viewer {
    background: #0a0a14; color: #d0d0e0;
    border: 1px solid var(--border);
    border-radius: 8px;
    height: calc(100vh - 240px);
    overflow-y: scroll;
    padding: 12px;
    font-family: ui-monospace, monospace;
    font-size: 12px;
    line-height: 1.55;
}
html:not(.dark) .lg-viewer { background: #1a1a24; }
.lg-line { white-space: pre-wrap; word-break: break-word; padding: 1px 0; }
.lg-line.warn  { color: #ffab40; }
.lg-line.err   { color: #ff5252; }
.lg-line.ok    { color: #00e676; }
.lg-line.match { background: rgba(255,200,0,.15); }
</style>

<div class="lg-wrap">
    <h2 style="font-size:16px;font-weight:700;margin-bottom:14px;">Logs en vivo del bot</h2>

    <div class="lg-toolbar">
        <button class="lg-btn active" id="btn-pause" onclick="togglePause()">⏸ Pausar</button>
        <button class="lg-btn" onclick="limpiar()">🗑 Limpiar</button>
        <input class="lg-search" id="search" placeholder="Filtrar líneas (ej: ERROR, mensaje, watchdog)" oninput="filtrar()">
        <span class="lg-status" id="conn-status"><span class="dot"></span><span id="conn-txt">Conectado</span></span>
    </div>

    <div class="lg-viewer" id="viewer"></div>
</div>

<script>
let _stream = null;
let _paused = false;
let _autoScroll = true;
let _filas = [];

const VIEWER_LIMIT = 1000;

function clasificarLinea(s) {
    const low = s.toLowerCase();
    if (low.includes('error') || low.includes('fallo')) return 'err';
    if (low.includes('warn') || low.includes('colgado') || low.includes('reiniciando')) return 'warn';
    if (low.includes('listo') || low.includes('autenticado') || low.includes('enviado')) return 'ok';
    return '';
}

function escTxt(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function pintarLinea(s) {
    if (_paused) return;
    if (_filas.length >= VIEWER_LIMIT) _filas.shift();
    _filas.push(s);
    appendDom(s);
    if (_autoScroll) {
        const v = document.getElementById('viewer');
        v.scrollTop = v.scrollHeight;
    }
}

function appendDom(s) {
    const v = document.getElementById('viewer');
    const filtro = document.getElementById('search').value.trim().toLowerCase();
    if (filtro && !s.toLowerCase().includes(filtro)) return;
    const div = document.createElement('div');
    div.className = 'lg-line ' + clasificarLinea(s);
    if (filtro) div.classList.add('match');
    div.textContent = s;
    v.appendChild(div);
    while (v.children.length > VIEWER_LIMIT) v.removeChild(v.firstChild);
}

function filtrar() {
    const v = document.getElementById('viewer');
    v.innerHTML = '';
    _filas.forEach(s => appendDom(s));
    if (_autoScroll) v.scrollTop = v.scrollHeight;
}

function togglePause() {
    _paused = !_paused;
    document.getElementById('btn-pause').textContent = _paused ? '▶ Reanudar' : '⏸ Pausar';
    document.getElementById('btn-pause').classList.toggle('active', !_paused);
}

function limpiar() {
    _filas = [];
    document.getElementById('viewer').innerHTML = '';
}

function setEstadoConn(ok) {
    const el = document.getElementById('conn-status');
    el.classList.toggle('off', !ok);
    document.getElementById('conn-txt').textContent = ok ? 'Conectado' : 'Reconectando…';
}

function conectar() {
    if (_stream) _stream.close();
    _stream = new EventSource('/admin/logs/stream');
    _stream.onmessage = (e) => pintarLinea(e.data);
    _stream.onopen    = () => setEstadoConn(true);
    _stream.onerror   = () => setEstadoConn(false);
}

// Detectar scroll manual para desactivar auto-scroll
document.getElementById('viewer').addEventListener('scroll', (e) => {
    const v = e.target;
    _autoScroll = (v.scrollHeight - v.scrollTop - v.clientHeight) < 30;
});

conectar();
</script>
@endsection
