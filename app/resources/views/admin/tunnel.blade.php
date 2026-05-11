@extends('layouts.app')
@section('title', 'Admin · Acceso remoto')

@section('content')
@include('admin._nav')

<style>
.tun-wrap { max-width: 880px; }
.tun-info { font-size: 12px; color: var(--muted); margin-bottom: 18px; line-height: 1.5; }
.tun-info code { background: var(--surface); padding: 1px 6px; border-radius: 4px; font-size: 11px; }

.tun-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 18px;
}

.tun-status {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 20px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-bottom: 14px;
}
.tun-light {
    width: 12px; height: 12px; border-radius: 50%;
    background: var(--muted);
    flex-shrink: 0;
    box-shadow: 0 0 0 0 transparent;
    transition: background .2s, box-shadow .2s;
}
.tun-light.on  { background: var(--success); box-shadow: 0 0 0 4px color-mix(in srgb, var(--success) 20%, transparent); }
.tun-light.err { background: var(--error);   box-shadow: 0 0 0 4px color-mix(in srgb, var(--error) 20%, transparent); }
.tun-state-text { font-size: 14px; font-weight: 600; }
.tun-state-sub  { font-size: 12px; color: var(--muted); margin-top: 2px; }

.tun-url-row {
    display: flex; align-items: center; gap: 8px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 14px;
}
.tun-url {
    flex: 1; min-width: 0;
    font-family: monospace;
    font-size: 13px;
    overflow-x: auto;
    white-space: nowrap;
    color: var(--info);
}
.tun-url-empty { color: var(--muted); font-style: italic; font-family: inherit; }

.tun-actions { display: flex; gap: 8px; }

.btn-on  { background: var(--success); border-color: var(--success); color: #fff; }
.btn-off { background: var(--error);   border-color: var(--error);   color: #fff; }
.btn-on:hover  { filter: brightness(.92); }
.btn-off:hover { filter: brightness(.92); }
.btn-ghost {
    background: var(--surface); border: 1px solid var(--border); color: var(--text);
}
.btn-ghost:hover { background: var(--border); }

.tun-log {
    font-family: monospace;
    font-size: 11px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 12px;
    max-height: 220px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
    color: var(--muted);
    line-height: 1.5;
}
.tun-log-empty { color: var(--muted); font-style: italic; }

.tun-warn {
    background: color-mix(in srgb, var(--warning) 8%, transparent);
    border: 1px solid color-mix(in srgb, var(--warning) 35%, transparent);
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 14px;
    font-size: 12px;
    color: var(--warning);
}
</style>

<div class="tun-wrap">
    <h2 style="font-size:18px;font-weight:700;margin-bottom:6px;">
        Acceso remoto <span id="tun-backend-badge" style="font-size:11px;font-weight:600;vertical-align:middle;padding:2px 8px;border-radius:10px;background:color-mix(in srgb, var(--info) 12%, transparent);color:var(--info);">túnel</span>
    </h2>
    <div class="tun-info">
        Expone temporalmente el panel a internet a través de un túnel (ngrok o Cloudflare, según
        configuración). Generamos una URL pública que el equipo remoto puede abrir desde cualquier lugar.
        El login de Laravel sigue siendo obligatorio. El backend se elige en <code>bot/.env</code>
        (<code>TUNNEL_BACKEND</code> / <code>NGROK_AUTHTOKEN</code>); el broker corre en la host como
        tarea programada <code>Crecer\TunnelBroker</code>.
    </div>

    <div class="tun-warn">
        ⚠ El túnel expone el panel sin autenticación adicional (solo el login). No compartas la URL
        en grupos abiertos ni la dejes encendida más tiempo del necesario.
    </div>

    <div class="tun-card">
        <div class="tun-status">
            <div class="tun-light" id="tun-light"></div>
            <div style="flex:1;min-width:0;">
                <div class="tun-state-text" id="tun-state">Cargando…</div>
                <div class="tun-state-sub"  id="tun-sub">&nbsp;</div>
            </div>
        </div>

        <div class="tun-url-row">
            <span class="tun-url" id="tun-url">—</span>
            <button class="btn btn-ghost" id="btn-copy" onclick="copiarUrl()" disabled style="padding:6px 12px;font-size:12px;">Copiar</button>
            <a class="btn btn-ghost" id="btn-open" target="_blank" rel="noopener" style="padding:6px 12px;font-size:12px;text-decoration:none;display:none;">Abrir ↗</a>
        </div>

        <div class="tun-actions">
            <button class="btn btn-on"  id="btn-start" onclick="iniciar()">Iniciar túnel</button>
            <button class="btn btn-off" id="btn-stop"  onclick="detener()" style="display:none;">Detener túnel</button>
            <button class="btn btn-ghost" onclick="refrescar()" style="margin-left:auto;">Refrescar</button>
        </div>
    </div>

    <div class="tun-card">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--muted);letter-spacing:.5px;margin-bottom:10px;" id="tun-log-titulo">
            Log del túnel
        </div>
        <div class="tun-log" id="tun-log"><span class="tun-log-empty">Sin actividad reciente.</span></div>
    </div>
</div>

<script>
const $ = (id) => document.getElementById(id);

function _getCookie(name) {
    return document.cookie.split('; ').reduce((acc, c) => {
        const [k, v] = c.split('=');
        return k === name ? decodeURIComponent(v) : acc;
    }, null);
}
async function call(url, opts = {}) {
    const xsrf = _getCookie('XSRF-TOKEN');
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const tok = xsrf || csrf;
    return fetch(url, {
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': tok,
            'X-CSRF-TOKEN': tok,
            ...(opts.headers || {}),
        },
        ...opts,
    }).then(r => r.json().catch(() => ({})));
}

function pintarEstado(j) {
    const running = !!j.running;
    const url     = j.url || null;
    const error   = j.error || null;
    const backend = j.backend || '—';

    const badge = $('tun-backend-badge');
    if (badge) badge.textContent = backend === '—' ? 'túnel' : backend;
    const logTit = $('tun-log-titulo');
    if (logTit) logTit.textContent = backend === '—' ? 'Log del túnel' : ('Log de ' + backend);

    $('tun-light').classList.toggle('on',  running && !error);
    $('tun-light').classList.toggle('err', !!error);

    if (error) {
        $('tun-state').textContent = 'Error';
        $('tun-sub').textContent   = error;
    } else if (running) {
        $('tun-state').textContent = 'Túnel activo';
        const sec = j.uptime_seconds || 0;
        const mins = Math.floor(sec / 60);
        const horas = Math.floor(mins / 60);
        $('tun-sub').textContent = horas > 0 ? `Encendido hace ${horas}h ${mins % 60}m` : `Encendido hace ${mins}m ${sec % 60}s`;
    } else {
        $('tun-state').textContent = 'Túnel detenido';
        $('tun-sub').textContent   = 'Hacé clic en "Iniciar túnel" para generar una URL pública.';
    }

    const urlEl  = $('tun-url');
    const btnCp  = $('btn-copy');
    const btnOp  = $('btn-open');
    if (url) {
        urlEl.textContent  = url;
        urlEl.classList.remove('tun-url-empty');
        btnCp.disabled = false;
        btnOp.style.display = '';
        btnOp.href = url;
    } else {
        urlEl.textContent  = running ? 'Generando URL...' : '— sin URL —';
        urlEl.classList.add('tun-url-empty');
        btnCp.disabled = true;
        btnOp.style.display = 'none';
    }

    $('btn-start').style.display = running ? 'none' : '';
    $('btn-stop').style.display  = running ? '' : 'none';

    const log = (j.log_tail || []);
    const logEl = $('tun-log');
    if (log.length === 0) {
        logEl.innerHTML = '<span class="tun-log-empty">Sin actividad reciente.</span>';
    } else {
        logEl.textContent = log.join('\n');
        logEl.scrollTop = logEl.scrollHeight;
    }
}

async function refrescar() {
    const j = await call('/admin/tunnel/status');
    pintarEstado(j);
}

async function iniciar() {
    $('btn-start').disabled = true;
    $('tun-state').textContent = 'Iniciando túnel...';
    $('tun-sub').textContent   = 'Esperando que el túnel levante (puede tardar hasta 30s)…';
    try {
        const j = await call('/admin/tunnel/start', { method: 'POST' });
        pintarEstado(j);
        if (!j.url && !j.running) {
            alert('No se pudo levantar el túnel.\n\n' + (j.error || 'El túnel no devolvió una URL. Ver el log abajo.'));
        }
    } finally {
        $('btn-start').disabled = false;
    }
}

async function detener() {
    if (!confirm('Detener el túnel y dejar de exponer el panel a internet?')) return;
    $('btn-stop').disabled = true;
    try {
        const j = await call('/admin/tunnel/stop', { method: 'POST' });
        pintarEstado(j);
    } finally {
        $('btn-stop').disabled = false;
    }
}

async function copiarUrl() {
    const url = $('tun-url').textContent.trim();
    if (!url || url === '—' || url.includes('—')) return;
    try {
        await navigator.clipboard.writeText(url);
        const btn = $('btn-copy');
        const orig = btn.textContent;
        btn.textContent = '✓ Copiado';
        setTimeout(() => btn.textContent = orig, 1500);
    } catch (e) { alert('No se pudo copiar al portapapeles'); }
}

refrescar();
setInterval(refrescar, 10000);
</script>
@endsection
