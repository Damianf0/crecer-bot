@extends('layouts.app')
@section('title', 'Admin · Pruebas')

@section('content')
@include('admin._nav')

<style>
.pr-wrap { max-width: 980px; }
.pr-toggle {
    display: flex; align-items: center; gap: 14px;
    background: var(--card); border: 1px solid var(--border);
    border-radius: 10px; padding: 16px 20px; margin-bottom: 18px;
}
.pr-switch {
    width: 50px; height: 28px; border-radius: 14px; padding: 3px;
    background: var(--border); cursor: pointer; transition: .2s;
    flex-shrink: 0;
}
.pr-switch.on { background: var(--success); }
.pr-switch::after {
    content: ''; display: block; width: 22px; height: 22px;
    border-radius: 50%; background: #fff; transition: .2s;
}
.pr-switch.on::after { transform: translateX(22px); }
.pr-toggle-info { flex: 1; }
.pr-toggle-info b { font-size: 14px; }
.pr-toggle-info p { font-size: 12px; color: var(--muted); margin: 4px 0 0; }

.pr-stream {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 10px; padding: 14px;
    max-height: 60vh; overflow-y: auto;
}
.pr-stream-row {
    border-bottom: 1px solid var(--border);
    padding: 10px 6px;
    display: grid; grid-template-columns: 110px 1fr 130px;
    gap: 10px; font-size: 13px;
}
.pr-stream-row:last-child { border-bottom: none; }
.pr-codigo { font-family: monospace; font-size: 11px; color: var(--info); font-weight: 600; }
.pr-mensaje { color: var(--text); white-space: pre-wrap; word-break: break-word; }
.pr-meta { font-size: 11px; color: var(--muted); text-align: right; }
.pr-empty { padding: 30px; text-align: center; color: var(--muted); font-size: 13px; }
.pr-prio-alta  { color: var(--success); }
.pr-prio-media { color: var(--warning); }
.pr-prio-baja  { color: var(--muted); }
</style>

<div class="pr-wrap">
    <h2 style="font-size:16px;font-weight:700;margin-bottom:6px;">Pruebas de clasificación</h2>
    <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">
        En modo prueba el bot clasifica los mensajes que recibe pero <strong>no responde ni deriva</strong>.
        Útil para validar nuevas plantillas o probar sin afectar pacientes reales.
    </p>

    <div class="pr-toggle">
        <div class="pr-switch" id="switch" onclick="toggleModo()"></div>
        <div class="pr-toggle-info">
            <b id="modo-txt">Modo prueba: cargando…</b>
            <p id="modo-sub">Estado del modo de prueba</p>
        </div>
    </div>

    <h3 style="font-size:13px;font-weight:700;margin-bottom:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">
        Stream de clasificaciones en vivo
    </h3>
    <div class="pr-stream" id="stream"><div class="pr-empty">Esperando mensajes…</div></div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
let _modo = false;
let _stream = null;
let _filas = [];

function escTxt(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;'); }

function pintarSwitch() {
    const sw = document.getElementById('switch');
    sw.classList.toggle('on', _modo);
    document.getElementById('modo-txt').textContent = 'Modo prueba: ' + (_modo ? 'ACTIVO' : 'apagado');
    document.getElementById('modo-sub').textContent = _modo
        ? 'El bot clasifica pero NO envía respuestas a pacientes.'
        : 'El bot está respondiendo normalmente a pacientes.';
}

async function toggleModo() {
    const r = await fetch('/admin/pruebas/modo', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ modoPrueba: !_modo }),
    });
    const d = await r.json();
    if (d.ok !== false) {
        _modo = d.modoPrueba;
        pintarSwitch();
    } else {
        alert('No se pudo cambiar el modo: ' + (d.error || 'error'));
    }
}

function pintarStream() {
    const cont = document.getElementById('stream');
    if (_filas.length === 0) {
        cont.innerHTML = '<div class="pr-empty">Esperando mensajes…</div>';
        return;
    }
    cont.innerHTML = _filas.slice(-100).reverse().map(f => `
        <div class="pr-stream-row">
            <div>
                <div class="pr-codigo">${escTxt(f.codigo || f.classification || '?')}</div>
                <div class="pr-prio-${escTxt(f.prioridad || 'baja')}" style="font-size:11px;font-weight:600;text-transform:uppercase;">
                    ${escTxt(f.prioridad || '')}
                </div>
            </div>
            <div class="pr-mensaje">${escTxt(f.mensaje || f.texto || '')}</div>
            <div class="pr-meta">${escTxt(f.contacto || '')}<br>${escTxt(f.timestamp || '')}</div>
        </div>
    `).join('');
}

function conectarStream() {
    if (_stream) _stream.close();
    _stream = new EventSource('/admin/pruebas/stream');
    _stream.addEventListener('clasificacion', (e) => {
        try {
            _filas.push(JSON.parse(e.data));
            if (_filas.length > 200) _filas = _filas.slice(-200);
            pintarStream();
        } catch {}
    });
    _stream.addEventListener('modo', (e) => {
        try { _modo = JSON.parse(e.data).modoPrueba; pintarSwitch(); } catch {}
    });
    _stream.onerror = () => {
        // EventSource reconecta solo
    };
}

conectarStream();
</script>
@endsection
