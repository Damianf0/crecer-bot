@extends('layouts.app')
@section('title', 'Admin · Respuestas rápidas')

@section('content')
@include('admin._nav')

<style>
.rr-wrap { max-width: 1100px; }
.rr-info { font-size: 12px; color: var(--muted); margin-bottom: 16px; line-height: 1.5; }
.rr-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--border); margin-bottom: 16px; }
.rr-tab  {
    padding: 8px 16px; font-size: 13px; font-weight: 500; color: var(--muted);
    cursor: pointer; border: none; border-bottom: 2px solid transparent; background: none;
    display: inline-flex; align-items: center; gap: 7px; transition: color .12s, border-color .12s;
}
.rr-tab:hover { color: var(--text); }
.rr-tab.active { color: var(--text); border-bottom-color: var(--accent); }
.rr-tab-count { background: var(--info); color: #fff; border-radius: 10px; padding: 1px 7px; font-size: 11px; font-weight: 700; }

.rr-actions { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.btn-primary { padding: 7px 14px; background: var(--accent); color: #fff; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }

.rr-table {
    width: 100%; border-collapse: collapse; font-size: 13px;
    background: var(--card); border: 1px solid var(--border); border-radius: 8px; overflow: hidden;
}
.rr-table th {
    background: var(--surface); padding: 10px 12px; text-align: left;
    font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px;
    border-bottom: 1px solid var(--border);
}
.rr-table td { padding: 10px 12px; border-top: 1px solid var(--border); vertical-align: top; }
.rr-table tr:hover td { background: var(--bg); }
.rr-titulo { font-weight: 600; }
.rr-texto  { color: var(--muted); white-space: pre-wrap; max-width: 600px; }
.rr-orden  { color: var(--muted); width: 60px; text-align: center; }
.rr-acciones { text-align: right; white-space: nowrap; width: 130px; }

.btn-sm { padding: 5px 10px; border-radius: 5px; font-size: 11px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: var(--card); color: var(--text); }
.btn-sm:hover { border-color: var(--accent); }
.btn-sm.danger { color: var(--error); }
.btn-sm.danger:hover { border-color: var(--error); }

.rr-empty { padding: 40px 20px; text-align: center; color: var(--muted); font-size: 13px; }

/* Modal */
.rr-modal { position: fixed; inset: 0; z-index: 800; background: rgba(0,0,0,.55); display: none; align-items: center; justify-content: center; padding: 24px; }
.rr-modal.open { display: flex; }
.rr-modal-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 22px; width: min(560px, 100%); max-height: calc(100vh - 48px); overflow-y: auto; }
.rr-modal-head { font-weight: 700; font-size: 15px; margin-bottom: 14px; }
.rr-form-group { margin-bottom: 12px; }
.rr-form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 5px; }
.rr-form-group input, .rr-form-group select, .rr-form-group textarea {
    width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 6px;
    padding: 8px 10px; color: var(--text); font-size: 13px; font-family: inherit;
}
.rr-form-group input:focus, .rr-form-group select:focus, .rr-form-group textarea:focus {
    outline: none; border-color: var(--info);
}
.rr-form-group textarea { resize: vertical; min-height: 110px; }
.rr-modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }
.btn-cancel { padding: 7px 14px; background: var(--surface); color: var(--muted); border: 1px solid var(--border); border-radius: 6px; font-size: 12px; cursor: pointer; }

.rr-toast { position: fixed; bottom: 24px; right: 24px; padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 500; opacity: 0; transition: .2s; pointer-events: none; z-index: 9000; }
.rr-toast.show { opacity: 1; }
.rr-toast.ok    { background: rgba(63,185,80,.15); color: var(--success); border: 1px solid rgba(63,185,80,.3); }
.rr-toast.error { background: rgba(248,81,73,.15); color: var(--error);   border: 1px solid rgba(248,81,73,.3); }
</style>

<div class="rr-wrap">

    <div class="rr-info">
        Plantillas de mensaje que aparecen en el dropdown <b>📋 Respuestas</b> dentro de la ventana de conversación.
        Cada área tiene su propio listado. Los operadores las usan como punto de partida y pueden editar el texto antes de enviarlo.
    </div>

    <div class="rr-tabs" id="rr-tabs"></div>

    <div class="rr-actions">
        <div style="font-size:12px;color:var(--muted);" id="rr-cnt">—</div>
        <button class="btn-primary" onclick="rrNueva()">+ Nueva respuesta</button>
    </div>

    <div id="rr-listado"></div>
</div>

<div class="rr-modal" id="rr-modal">
    <div class="rr-modal-card" onclick="event.stopPropagation()">
        <div class="rr-modal-head" id="rr-modal-titulo">Nueva respuesta</div>

        <div class="rr-form-group">
            <label>Área</label>
            <select id="rr-f-area"></select>
        </div>
        <div class="rr-form-group">
            <label>Título <span style="color:var(--muted);text-transform:none;font-weight:400;">(lo que ve el operador en el dropdown)</span></label>
            <input type="text" id="rr-f-titulo" placeholder="Ej: Saludo inicial">
        </div>
        <div class="rr-form-group">
            <label>Texto del mensaje</label>
            <textarea id="rr-f-texto" placeholder="Hola, te escribimos desde Crecer Reproducción Humana..."></textarea>
        </div>
        <div class="rr-form-group">
            <label>Orden <span style="color:var(--muted);text-transform:none;font-weight:400;">(número, menor = primero)</span></label>
            <input type="number" id="rr-f-orden" value="0" min="0" max="9999">
        </div>

        <div class="rr-modal-actions">
            <button class="btn-cancel" onclick="rrCerrarModal()">Cancelar</button>
            <button class="btn-primary" onclick="rrGuardar()">Guardar</button>
        </div>
    </div>
</div>

<div class="rr-toast" id="rr-toast"></div>

<script>
const CSRF    = '{{ csrf_token() }}';
const AREAS   = @json($areas);
const AREA_LABELS = { atencion: 'Atención (clínica)', administracion: 'Administración', ovodonacion: 'Ovodonación' };

let state = {
    area:    Object.keys(AREAS)[0],
    items:   [],          // todos los registros
    editId:  null,
};

async function api(method, url, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(url, opts);
    if (!r.ok) {
        let msg = 'HTTP ' + r.status;
        try { const j = await r.json(); if (j?.message) msg += ' — ' + j.message; } catch {}
        throw new Error(msg);
    }
    return r.json();
}

function toast(msg, tipo = 'ok') {
    const el = document.getElementById('rr-toast');
    el.textContent = msg;
    el.className = `rr-toast ${tipo} show`;
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 3000);
}

function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function cargar() {
    try {
        const r = await api('GET', '/admin/respuestas-rapidas/data');
        state.items = r.data || [];
        renderTabs();
        renderLista();
    } catch (e) { toast('Error al cargar: ' + e.message, 'error'); }
}

function renderTabs() {
    const cont = document.getElementById('rr-tabs');
    cont.innerHTML = Object.keys(AREAS).map(a => {
        const cnt = state.items.filter(i => i.area === a).length;
        const cls = state.area === a ? 'rr-tab active' : 'rr-tab';
        return `<button class="${cls}" onclick="rrSetArea('${a}')">${esc(AREA_LABELS[a] || a)}${cnt > 0 ? `<span class="rr-tab-count">${cnt}</span>` : ''}</button>`;
    }).join('');
}

function rrSetArea(a) {
    state.area = a;
    renderTabs();
    renderLista();
}

function renderLista() {
    const cont = document.getElementById('rr-listado');
    const filas = state.items.filter(i => i.area === state.area);
    document.getElementById('rr-cnt').textContent = `${filas.length} respuesta${filas.length !== 1 ? 's' : ''} en esta área`;

    if (!filas.length) {
        cont.innerHTML = `<div class="rr-empty">Esta área todavía no tiene respuestas rápidas. Agregá una con <b>+ Nueva respuesta</b>.</div>`;
        return;
    }

    cont.innerHTML = `<table class="rr-table">
        <thead><tr>
            <th style="width:60px;text-align:center;">Orden</th>
            <th>Título</th>
            <th>Texto</th>
            <th class="rr-acciones">Acciones</th>
        </tr></thead>
        <tbody>
            ${filas.map(r => `<tr>
                <td class="rr-orden">${r.orden}</td>
                <td class="rr-titulo">${esc(r.titulo)}</td>
                <td class="rr-texto">${esc(r.texto)}</td>
                <td class="rr-acciones">
                    <button class="btn-sm" onclick="rrEditar(${r.id})">Editar</button>
                    <button class="btn-sm danger" onclick="rrEliminar(${r.id})">Borrar</button>
                </td>
            </tr>`).join('')}
        </tbody>
    </table>`;
}

function rrNueva() {
    state.editId = null;
    document.getElementById('rr-modal-titulo').textContent = 'Nueva respuesta';
    llenarSelect(state.area);
    document.getElementById('rr-f-titulo').value = '';
    document.getElementById('rr-f-texto').value  = '';
    document.getElementById('rr-f-orden').value  = '0';
    abrirModal();
}

function rrEditar(id) {
    const r = state.items.find(x => x.id === id);
    if (!r) return;
    state.editId = id;
    document.getElementById('rr-modal-titulo').textContent = 'Editar respuesta';
    llenarSelect(r.area);
    document.getElementById('rr-f-titulo').value = r.titulo;
    document.getElementById('rr-f-texto').value  = r.texto;
    document.getElementById('rr-f-orden').value  = r.orden;
    abrirModal();
}

async function rrEliminar(id) {
    const r = state.items.find(x => x.id === id);
    if (!r) return;
    if (!confirm(`¿Eliminar la respuesta "${r.titulo}"?`)) return;
    try {
        await api('DELETE', `/admin/respuestas-rapidas/${id}`);
        state.items = state.items.filter(x => x.id !== id);
        renderTabs(); renderLista();
        toast('Eliminada');
    } catch (e) { toast('Error: ' + e.message, 'error'); }
}

function llenarSelect(areaSeleccionada) {
    const sel = document.getElementById('rr-f-area');
    sel.innerHTML = Object.keys(AREAS).map(a =>
        `<option value="${a}" ${a === areaSeleccionada ? 'selected' : ''}>${esc(AREA_LABELS[a] || a)}</option>`
    ).join('');
}

function abrirModal() { document.getElementById('rr-modal').classList.add('open'); }
function rrCerrarModal() { document.getElementById('rr-modal').classList.remove('open'); }

async function rrGuardar() {
    const payload = {
        area:   document.getElementById('rr-f-area').value,
        titulo: document.getElementById('rr-f-titulo').value.trim(),
        texto:  document.getElementById('rr-f-texto').value.trim(),
        orden:  parseInt(document.getElementById('rr-f-orden').value || '0') || 0,
    };
    if (!payload.titulo) { toast('Falta el título', 'error'); return; }
    if (!payload.texto)  { toast('Falta el texto', 'error'); return; }

    try {
        const url = state.editId ? `/admin/respuestas-rapidas/${state.editId}` : '/admin/respuestas-rapidas';
        const r = await api('POST', url, payload);
        if (state.editId) {
            const idx = state.items.findIndex(x => x.id === state.editId);
            if (idx >= 0) state.items[idx] = r.data;
        } else {
            state.items.push(r.data);
        }
        state.area = payload.area;
        renderTabs(); renderLista();
        rrCerrarModal();
        toast('Guardada');
    } catch (e) { toast('Error: ' + e.message, 'error'); }
}

// Cerrar modal con click en backdrop
document.getElementById('rr-modal').addEventListener('click', rrCerrarModal);

cargar();
</script>
@endsection
