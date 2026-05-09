@extends('layouts.app')
@section('title', 'Admin · Usuarios')

@section('content')
@include('admin._nav')

<style>
.us-wrap { max-width: 1100px; }
.us-table { width: 100%; border-collapse: collapse; font-size: 14px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
.us-table th { text-align: left; padding: 10px 14px; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; border-bottom: 1px solid var(--border); background: var(--surface); }
.us-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); }
.us-table tr:last-child td { border-bottom: none; }
.us-rol-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: color-mix(in srgb, var(--info) 12%, transparent); color: var(--info); font-weight: 600; }
.us-inactivo { opacity: .5; }
.us-btn-edit { padding: 4px 10px; border-radius: 5px; border: 1px solid var(--border); background: transparent; color: var(--muted); cursor: pointer; font-size: 12px; }
.us-btn-edit:hover { color: var(--text); }
.us-btn-add { padding: 8px 18px; border-radius: 7px; border: none; background: var(--success); color: #fff; cursor: pointer; font-size: 13px; font-weight: 600; }

/* Modal */
.us-modal { position: fixed; inset: 0; z-index: 9000; background: rgba(0,0,0,.5); display: none; align-items: center; justify-content: center; }
.us-modal.show { display: flex; }
.us-card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; width: min(540px, 94vw); max-height: 90vh; overflow-y: auto; }
.us-card-head { padding: 14px 18px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.us-card-body { padding: 16px 18px; }
.us-card-foot { padding: 12px 18px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; }
.us-label { display: block; font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin: 12px 0 4px; }
.us-input, .us-select { width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); font-size: 14px; font-family: inherit; }
.us-input:focus, .us-select:focus { outline: none; border-color: var(--info); }
.us-permisos { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.us-permiso-chk { display: flex; align-items: center; gap: 6px; font-size: 13px; padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; }
.us-permiso-chk:hover { border-color: var(--info); }
.us-permiso-chk input { margin: 0; }
</style>

<div class="us-wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <h2 style="font-size:16px;font-weight:700;">Usuarios del sistema</h2>
        <button class="us-btn-add" onclick="abrirModal()">+ Nuevo usuario</button>
    </div>

    <table class="us-table" id="tabla">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tbody"><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:40px;">Cargando…</td></tr></tbody>
    </table>
</div>

<div class="us-modal" id="modal">
    <div class="us-card" onclick="event.stopPropagation()">
        <div class="us-card-head">
            <span style="font-weight:700;font-size:15px;" id="modal-titulo">Nuevo usuario</span>
            <button onclick="cerrarModal()" style="background:none;border:none;color:var(--muted);font-size:22px;cursor:pointer;">&times;</button>
        </div>
        <div class="us-card-body">
            <label class="us-label">Nombre completo</label>
            <input class="us-input" id="f-nombre">

            <label class="us-label">Email</label>
            <input class="us-input" id="f-email" type="email">

            <label class="us-label">Contraseña <span style="text-transform:none;font-size:10px;">(en blanco = no cambia)</span></label>
            <input class="us-input" id="f-pass" type="password" placeholder="Mín 10 caracteres, sin diccionario común">
            <div style="font-size:11px;color:var(--muted);margin-top:4px;">
                Mínimo 10 caracteres · No puede ser solo números · No puede contener el nombre o email del usuario
            </div>

            <label class="us-label">Rol</label>
            <select class="us-select" id="f-rol" onchange="aplicarPermisosDefault()"></select>

            <label class="us-label">Activo</label>
            <select class="us-select" id="f-activo">
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>

            <label class="us-label">Permisos efectivos</label>
            <div class="us-permisos" id="f-permisos"></div>
            <div style="font-size:11px;color:var(--muted);margin-top:6px;">
                Si está vacío, se usan los permisos por defecto del rol.
            </div>
        </div>
        <div class="us-card-foot">
            <button onclick="cerrarModal()" style="padding:8px 14px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-size:13px;">Cancelar</button>
            <button class="us-btn-add" id="btn-save" onclick="guardar()">Guardar</button>
        </div>
    </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
let _editId = null;
let _meta = { roles: {}, permisos_labels: {}, permisos_default: {} };

function escTxt(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;'); }

async function cargar() {
    const r = await fetch('/admin/usuarios/data', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const d = await r.json();
    if (!d.ok) return;
    _meta = { roles: d.roles, permisos_labels: d.permisos_labels, permisos_default: d.permisos_default };
    pintarRoles();
    pintarLista(d.data);
}

function pintarRoles() {
    const sel = document.getElementById('f-rol');
    sel.innerHTML = Object.entries(_meta.roles).map(([k, v]) => `<option value="${k}">${escTxt(v)}</option>`).join('');
}

function pintarLista(users) {
    const tbody = document.getElementById('tbody');
    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:40px;">Sin usuarios</td></tr>';
        return;
    }
    tbody.innerHTML = users.map(u => `
        <tr class="${u.activo ? '' : 'us-inactivo'}">
            <td style="font-weight:600;">${escTxt(u.nombre_completo)}</td>
            <td style="color:var(--muted);font-size:13px;">${escTxt(u.email)}</td>
            <td><span class="us-rol-badge">${escTxt(_meta.roles[u.rol] ?? u.rol)}</span></td>
            <td style="font-size:12px;color:${u.activo ? 'var(--success)' : 'var(--error)'};">
                ${u.activo ? '● Activo' : '○ Inactivo'}
            </td>
            <td style="text-align:right;">
                <button class="us-btn-edit" onclick='editar(${JSON.stringify(u)})'>Editar</button>
            </td>
        </tr>
    `).join('');
}

function abrirModal() {
    _editId = null;
    document.getElementById('modal-titulo').textContent = 'Nuevo usuario';
    document.getElementById('f-nombre').value = '';
    document.getElementById('f-email').value  = '';
    document.getElementById('f-pass').value   = '';
    document.getElementById('f-rol').value    = 'secretaria';
    document.getElementById('f-activo').value = '1';
    aplicarPermisosDefault();
    document.getElementById('modal').classList.add('show');
}

function editar(u) {
    _editId = u.id;
    document.getElementById('modal-titulo').textContent = 'Editar: ' + u.nombre_completo;
    document.getElementById('f-nombre').value = u.nombre_completo;
    document.getElementById('f-email').value  = u.email;
    document.getElementById('f-pass').value   = '';
    document.getElementById('f-rol').value    = u.rol;
    document.getElementById('f-activo').value = u.activo ? '1' : '0';
    pintarPermisos(u.permisos || _meta.permisos_default[u.rol] || []);
    document.getElementById('modal').classList.add('show');
}

function aplicarPermisosDefault() {
    const rol = document.getElementById('f-rol').value;
    pintarPermisos(_meta.permisos_default[rol] || []);
}

function pintarPermisos(activos) {
    const set = new Set(activos);
    document.getElementById('f-permisos').innerHTML = Object.entries(_meta.permisos_labels).map(([k, v]) => `
        <label class="us-permiso-chk">
            <input type="checkbox" value="${k}" ${set.has(k) ? 'checked' : ''}>
            ${escTxt(v)}
        </label>
    `).join('');
}

function cerrarModal() {
    document.getElementById('modal').classList.remove('show');
}

async function guardar() {
    const permisos = Array.from(document.querySelectorAll('#f-permisos input:checked')).map(c => c.value);
    const data = {
        nombre_completo: document.getElementById('f-nombre').value.trim(),
        email:           document.getElementById('f-email').value.trim(),
        rol:             document.getElementById('f-rol').value,
        activo:          document.getElementById('f-activo').value === '1',
        permisos:        permisos,
    };
    const pass = document.getElementById('f-pass').value;
    if (pass) data.password = pass;

    const url = _editId ? `/admin/usuarios/${_editId}/save` : '/admin/usuarios/save';
    const btn = document.getElementById('btn-save');
    btn.disabled = true; btn.textContent = 'Guardando…';

    try {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data),
        });
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Error');
        cerrarModal();
        await cargar();
    } catch (e) {
        alert('No se pudo guardar: ' + e.message);
    } finally {
        btn.disabled = false; btn.textContent = 'Guardar';
    }
}

document.getElementById('modal').addEventListener('click', (e) => {
    if (e.target.id === 'modal') cerrarModal();
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrarModal(); });

cargar();
</script>
@endsection
