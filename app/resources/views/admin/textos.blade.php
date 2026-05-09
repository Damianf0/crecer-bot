@extends('layouts.app')
@section('title', 'Admin · Textos')

@section('content')
@include('admin._nav')

<style>
.txt-wrap { max-width: 880px; }
.txt-info { font-size: 12px; color: var(--muted); margin-bottom: 16px; }
.txt-key {
    font-family: monospace; font-size: 12px;
    color: var(--info); font-weight: 600;
    margin-bottom: 4px; display: flex; align-items: center; gap: 6px;
}
.txt-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 14px;
}
.txt-area {
    width: 100%; min-height: 90px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 10px 12px;
    color: var(--text);
    font-size: 13px;
    font-family: inherit;
    resize: vertical;
    line-height: 1.5;
}
.txt-area:focus { outline: none; border-color: var(--info); }
.txt-toolbar {
    position: sticky; bottom: 0;
    background: var(--surface);
    border-top: 1px solid var(--border);
    margin: 24px -24px -24px;
    padding: 12px 24px;
    display: flex; gap: 10px; align-items: center;
}
.btn-guardar {
    background: var(--success); color: #fff;
    border: none; padding: 9px 20px; border-radius: 7px;
    font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-guardar:disabled { opacity: .5; cursor: not-allowed; }
.btn-cancelar {
    background: transparent; border: 1px solid var(--border);
    color: var(--muted); padding: 9px 16px; border-radius: 7px;
    font-size: 13px; cursor: pointer;
}
.txt-saved { font-size: 12px; color: var(--success); margin-left: auto; opacity: 0; transition: .3s; }
.txt-saved.show { opacity: 1; }
</style>

<div class="txt-wrap">
    <h2 style="font-size:16px;font-weight:700;margin-bottom:6px;">Textos de respuestas automáticas</h2>
    <div class="txt-info">
        Estos son los mensajes que el bot envía a los pacientes. Los cambios se aplican <strong>al instante</strong> sin reiniciar el bot.
        Si un texto incluye <code style="color:var(--info);">@{{FUERA_HORARIO}}</code>, se reemplaza por el aviso de fuera de horario solo cuando corresponde.
    </div>

    <div id="lista-textos">Cargando…</div>

    <div class="txt-toolbar">
        <button class="btn-guardar" id="btn-guardar" onclick="guardar()">Guardar cambios</button>
        <button class="btn-cancelar" onclick="cargar(true)">Descartar</button>
        <span class="txt-saved" id="saved">✓ Guardado</span>
    </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
let _original = {};

function escAttr(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }

async function cargar(forzar = false) {
    if (!forzar && Object.keys(_original).length && !confirm('¿Recargar y perder cambios?')) return;
    const r = await fetch('/admin/textos/data', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const d = await r.json();
    if (!d.ok) {
        document.getElementById('lista-textos').innerHTML = '<div style="color:var(--error);">No se pudieron cargar los textos.</div>';
        return;
    }
    _original = JSON.parse(JSON.stringify(d.data));
    render(d.data);
}

function render(textos) {
    const cont = document.getElementById('lista-textos');
    cont.innerHTML = Object.keys(textos).map(k => `
        <div class="txt-card">
            <div class="txt-key">${escAttr(k)}</div>
            <textarea class="txt-area" data-key="${escAttr(k)}">${escAttr(textos[k])}</textarea>
        </div>
    `).join('');
}

async function guardar() {
    const btn = document.getElementById('btn-guardar');
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    const data = {};
    document.querySelectorAll('.txt-area').forEach(t => {
        data[t.dataset.key] = t.value;
    });

    try {
        const r = await fetch('/admin/textos/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data),
        });
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Error');
        _original = JSON.parse(JSON.stringify(data));
        const saved = document.getElementById('saved');
        saved.classList.add('show');
        setTimeout(() => saved.classList.remove('show'), 2000);
    } catch (e) {
        alert('No se pudo guardar: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar cambios';
    }
}

cargar(true);
</script>
@endsection
