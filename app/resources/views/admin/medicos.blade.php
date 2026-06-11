@extends($layout ?? 'layouts.app')
@section('title', 'Admin · Médicos')

@section('content')
@include('admin._nav')

<style>
.med-wrap { max-width: 980px; }
.med-info { font-size: 12px; color: var(--muted); margin-bottom: 16px; line-height: 1.5; }

.med-table {
    width: 100%; border-collapse: collapse; font-size: 13px;
    background: var(--card); border: 1px solid var(--border); border-radius: 8px;
    overflow: hidden;
}
.med-table th {
    background: var(--surface); padding: 10px 12px; text-align: left;
    font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px;
    border-bottom: 1px solid var(--border);
}
.med-table td { padding: 10px 12px; border-top: 1px solid var(--border); vertical-align: middle; }
.med-table tr:hover td { background: var(--bg); }
.med-table .acciones { text-align: right; white-space: nowrap; }

.btn-sm { padding: 5px 10px; border-radius: 5px; font-size: 11px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: var(--card); color: var(--text); }
.btn-sm:hover { border-color: var(--accent); }
.btn-sm.danger { color: var(--error); }
.btn-sm.danger:hover { border-color: var(--error); }

.badge-inactivo {
    background: color-mix(in srgb, var(--muted) 15%, transparent);
    color: var(--muted); border: 1px solid var(--border);
    padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700;
}
.badge-user {
    background: color-mix(in srgb, var(--info) 12%, transparent);
    color: var(--info); border: 1px solid color-mix(in srgb, var(--info) 35%, transparent);
    padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 600;
}

.med-form-card {
    background: var(--card); border: 1px solid var(--border); border-radius: 8px;
    padding: 18px; margin-top: 16px;
}
.med-form-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
}
.med-field { display: flex; flex-direction: column; gap: 4px; }
.med-field label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .3px; }
.med-field input, .med-field select {
    padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px;
    background: var(--bg); color: var(--text); font-size: 13px;
    font-family: inherit;
}
.med-field input:focus, .med-field select:focus { outline: none; border-color: var(--accent); }

.btn { padding: 9px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { filter: brightness(.92); }
.btn-ghost { background: var(--surface); border: 1px solid var(--border); color: var(--text); }
.btn-ghost:hover { background: var(--border); }

.detectados {
    display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px;
}
.detectado-chip {
    background: var(--bg); border: 1px dashed var(--border); border-radius: 6px;
    padding: 4px 10px; font-size: 12px; cursor: pointer;
    transition: .15s;
}
.detectado-chip:hover { border-color: var(--accent); border-style: solid; background: color-mix(in srgb, var(--accent) 5%, var(--bg)); }
</style>

<div class="med-wrap">
    <h2 style="font-size:18px;font-weight:700;margin-bottom:6px;">Médicos</h2>
    <div class="med-info">
        Registro de profesionales que usan el panel <code>/medico</code>. Para que un médico
        pueda loguearse, hace falta vincularlo con un usuario activo (rol = "Médico" recomendado).
        El nombre completo tiene que coincidir <b>exactamente</b> con lo que aparece en Omnia (campo
        <code>profesional</code> en la cola de atención), si no, los pacientes no le aparecen.
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">
            <span id="cnt-medicos">0</span> registrados
        </div>
        <button class="btn btn-primary" onclick="abrirForm()">+ Nuevo médico</button>
    </div>

    <table class="med-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Especialidad</th>
                <th>Consultorio</th>
                <th>Usuario vinculado</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="med-tbody">
            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:20px;">Cargando…</td></tr>
        </tbody>
    </table>

    <div id="detectados-box" style="margin-top:16px;display:none;">
        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
            Detectados en cola de atención (no registrados)
        </div>
        <div class="detectados" id="detectados-list"></div>
    </div>

    <div class="med-form-card" id="form-card" style="display:none;">
        <div style="font-size:14px;font-weight:700;margin-bottom:14px;" id="form-title">Nuevo médico</div>
        <input type="hidden" id="f-id">
        <div class="med-form-grid">
            <div class="med-field" style="grid-column:1/-1;">
                <label>Nombre completo *</label>
                <input type="text" id="f-nombre" placeholder="Dr. Elena Casanova">
            </div>
            <div class="med-field">
                <label>Especialidad</label>
                <input type="text" id="f-esp" placeholder="Ginecología">
            </div>
            <div class="med-field">
                <label>Nombre en Omnia (opcional)</label>
                <input type="text" id="f-omnia" placeholder="Ej: Ignacio Cruz">
                <div style="font-size:11px;color:var(--muted);margin-top:4px;line-height:1.4;">
                    Nombre tal como aparece en Omnia (campo <code>NombreDelProfesional</code> del reporte). Se usa para mostrar la agenda del día en <code>/medico</code>. Sin "Dr."/"Dra.".
                </div>
            </div>
            <div class="med-field">
                <label>Planta default</label>
                <select id="f-planta">
                    <option value="">(sin definir)</option>
                    <option value="baja">Baja</option>
                    <option value="alta">Alta</option>
                </select>
            </div>
            <div class="med-field">
                <label>Consultorio default</label>
                <input type="number" id="f-consult" min="1" max="99" placeholder="3">
            </div>
            <div class="med-field" style="grid-column:1/-1;">
                <label>Usuario vinculado (login)</label>
                <select id="f-user">
                    <option value="">— sin vincular —</option>
                </select>
            </div>
            <div class="med-field" style="grid-column:1/-1;">
                <label><input type="checkbox" id="f-activo" checked> Activo</label>
            </div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
            <button class="btn btn-ghost" onclick="cerrarForm()">Cancelar</button>
            <button class="btn btn-primary" onclick="guardar()">Guardar</button>
        </div>
    </div>
</div>

<script>
const $ = (id) => document.getElementById(id);
let state = { medicos: [], detectados: [], users: [] };

function _getCookie(name) {
    return document.cookie.split('; ').reduce((acc, c) => {
        const [k, v] = c.split('=');
        return k === name ? decodeURIComponent(v) : acc;
    }, null);
}

async function api(method, url, body) {
    // Token CRUDO de la sesión. NO usar la cookie XSRF-TOKEN como X-CSRF-TOKEN: viene cifrada
    // y Laravel no la descifra en ese header → 419. (Bug del "auto-CSRF via cookie".)
    const tok = document.querySelector('meta[name=csrf-token]')?.content
        ?? document.querySelector('input[name=_token]')?.value ?? '{{ csrf_token() }}';
    const opts = {
        method,
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': tok, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    };
    if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify({ ...body, _token: tok }); }
    const r = await fetch(url, opts);
    const d = await r.json().catch(() => ({}));
    if (r.status === 419) return { ok: false, _err: 'Sesión expirada — recargá la página (F5).' };
    if (r.status === 422) {
        const detalle = d.errors ? Object.entries(d.errors).map(([k, vs]) => `• ${k}: ${vs.join(', ')}`).join('\n') : (d.message || '');
        return { ok: false, _err: 'Datos inválidos:\n' + detalle };
    }
    if (!r.ok) return { ok: false, _err: d.error || d.message || `HTTP ${r.status}` };
    return d;
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function render() {
    const tb = $('med-tbody');
    if (!state.medicos.length) {
        tb.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:20px;">No hay médicos registrados todavía.</td></tr>';
    } else {
        tb.innerHTML = state.medicos.map(m => `
            <tr>
                <td>
                    ${esc(m.nombre_completo)}
                    ${!m.activo ? '<span class="badge-inactivo">Inactivo</span>' : ''}
                </td>
                <td>${esc(m.especialidad ?? '—')}</td>
                <td>${m.consultorio ?? '—'}${m.planta ? ` (${esc(m.planta)})` : ''}</td>
                <td>${m.user ? `<span class="badge-user">${esc(m.user.nombre_completo)}</span>` : '<span style="color:var(--muted);">— sin vincular —</span>'}</td>
                <td class="acciones">
                    <button class="btn-sm" onclick="editar(${m.id})">Editar</button>
                    <button class="btn-sm danger" onclick="eliminar(${m.id})">×</button>
                </td>
            </tr>
        `).join('');
    }
    $('cnt-medicos').textContent = state.medicos.length;

    if (state.detectados.length) {
        $('detectados-box').style.display = '';
        $('detectados-list').innerHTML = state.detectados.map(n => `
            <span class="detectado-chip" onclick='nuevoConNombre(${JSON.stringify(n)})'>${esc(n)} +</span>
        `).join('');
    } else {
        $('detectados-box').style.display = 'none';
    }
}

async function cargar() {
    const j = await api('GET', '/admin/medicos/data');
    if (!j.ok) return;
    state = { medicos: j.medicos || [], detectados: j.detectados || [], users: j.users || [] };
    render();
}

function abrirForm(med = null) {
    $('form-title').textContent = med ? 'Editar médico' : 'Nuevo médico';
    $('f-id').value      = med?.id || '';
    $('f-nombre').value  = med?.nombre_completo || '';
    $('f-esp').value     = med?.especialidad || '';
    $('f-omnia').value   = med?.omnia_id || '';
    $('f-planta').value  = med?.planta || '';
    $('f-consult').value = med?.consultorio || '';
    $('f-activo').checked = med ? !!med.activo : true;

    // Poblar users disponibles + el ya vinculado (si está editando)
    const sel = $('f-user');
    sel.innerHTML = '<option value="">— sin vincular —</option>';
    const linked = med?.user;
    const opciones = [...state.users];
    if (linked && !opciones.find(u => u.id === linked.id)) opciones.unshift(linked);
    opciones.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id; opt.textContent = u.nombre_completo + (u.email ? ` (${u.email})` : '');
        sel.appendChild(opt);
    });
    sel.value = linked?.id || '';

    $('form-card').style.display = '';
    $('f-nombre').focus();
}

function cerrarForm() {
    $('form-card').style.display = 'none';
}

function nuevoConNombre(nombre) {
    abrirForm();
    $('f-nombre').value = nombre;
}

function editar(id) {
    const m = state.medicos.find(x => x.id === id);
    if (m) abrirForm(m);
}

async function guardar() {
    const body = {
        id:              parseInt($('f-id').value) || null,
        nombre_completo: $('f-nombre').value.trim(),
        especialidad:    $('f-esp').value.trim() || null,
        omnia_id:        $('f-omnia').value.trim() || null,
        planta:          $('f-planta').value || null,
        consultorio:     parseInt($('f-consult').value) || null,
        activo:          $('f-activo').checked,
        user_id:         parseInt($('f-user').value) || null,
    };
    if (!body.nombre_completo) { alert('Nombre completo obligatorio'); return; }

    const j = await api('POST', '/admin/medicos/save', body);
    if (!j.ok) { alert('No se pudo guardar:\n' + (j._err || 'Error')); return; }
    cerrarForm();
    await cargar();
}

async function eliminar(id) {
    const m = state.medicos.find(x => x.id === id);
    if (!confirm(`Eliminar a "${m?.nombre_completo}"? Si tiene un user vinculado, se desvincula.`)) return;
    const j = await api('DELETE', `/admin/medicos/${id}`);
    if (!j.ok) { alert('No se pudo eliminar:\n' + (j._err || 'Error')); return; }
    await cargar();
}

cargar();
</script>
@endsection
