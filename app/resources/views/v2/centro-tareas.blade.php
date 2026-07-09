@extends('layouts.v2')
@section('title', 'Tareas')

{{-- PoC V2 — centro de tareas: bandeja | detalle sobre /tareas/data y
     /centro-tareas/derivaciones. Alta con <dialog> nativo (concepto §4),
     chips para prioridad, un solo campo required. --}}

@section('content')
<div class="v2-inbox no-legajo" id="inbox">

    <section class="v2-bandeja">
        <div class="v2-bandeja-head">
            <h1 style="display:flex;align-items:center;">Tareas <span class="count" id="b-count"></span>
                <button class="v2-btn primary sm" style="margin-left:auto;" onclick="abrirNueva()">+ Nueva</button>
            </h1>
            <input type="search" class="v2-search" id="b-search" placeholder="Buscar por título">
        </div>
        <div class="v2-vistas" id="b-vistas"></div>
        <div class="v2-vistas" id="b-ambito"></div>
        <div class="v2-cards" id="b-cards"></div>
    </section>

    <section class="v2-detalle" id="detalle">
        <div class="v2-det-empty" id="det-empty">
            <span class="ico">✓</span>
            <span>Tus tareas y las derivaciones del bot que tomaste</span>
            <span style="font-size:11.5px;">Elegí una de la bandeja para ver el detalle y los comentarios.</span>
        </div>
        <div id="det-body" style="display:none;flex:1;flex-direction:column;min-height:0;"></div>
    </section>
</div>

{{-- Alta / edición de tarea (mismo dialog; _editId decide POST o PATCH) --}}
<dialog class="v2-dialog" id="dlg-nueva">
    <form method="dialog" onsubmit="return false;">
        <h3 id="nt-h3">Nueva tarea</h3>
        <label class="v2-label">Título *</label>
        <input class="v2-field" id="nt-titulo" maxlength="255" placeholder="Qué hay que hacer">
        <label class="v2-label">Descripción</label>
        <textarea class="v2-field" id="nt-desc" rows="3" placeholder="Detalle opcional"></textarea>
        <div class="v2-grid2">
            <div>
                <label class="v2-label">Asignar a</label>
                <select class="v2-field" id="nt-asig">
                    <option value="{{ auth()->id() }}">Yo ({{ explode(' ', auth()->user()->nombre_completo)[0] }})</option>
                    @foreach($usuarios as $u)@if($u->id !== auth()->id())
                    <option value="{{ $u->id }}">{{ $u->nombre_completo }}</option>
                    @endif @endforeach
                </select>
            </div>
            <div>
                <label class="v2-label">Vence</label>
                <input class="v2-field" id="nt-vence" type="datetime-local">
            </div>
        </div>
        <label class="v2-label">Prioridad</label>
        <div class="v2-chips" id="nt-prio">
            <button type="button" class="v2-vista" data-v="baja">Baja</button>
            <button type="button" class="v2-vista active" data-v="normal">Normal</button>
            <button type="button" class="v2-vista" data-v="alta">Alta</button>
        </div>
        <div class="v2-dialog-foot">
            <button type="button" class="v2-btn" onclick="document.getElementById('dlg-nueva').close()">Cancelar</button>
            <button type="button" class="v2-btn primary" id="nt-submit" onclick="guardarTarea()">Crear tarea</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
const { esc, get, post, patch, del, avatarHtml } = V2;
const ME_ID = {{ auth()->id() }};

let state = { tareas: [], derivaciones: [], vista: 'activas', q: '', sel: null /* {tipo:'tarea'|'der', id} */,
              ambito: 'mias' /* mias | asignadas | creadas | todas */, vencidas: false };

const PRIO_PILL = {
    alta:   '<span class="v2-pill urgente">Alta</span>',
    normal: '<span class="v2-pill nueva">Normal</span>',
    baja:   '<span class="v2-pill neutral">Baja</span>',
};
const ESTADO_LBL = { pendiente: 'Pendiente', en_progreso: 'En progreso', completada: 'Completada' };

// ── Carga ─────────────────────────────────────────────────────────
async function fetchAll() {
    const estado = state.vista === 'completadas' ? 'completadas' : 'activas';
    try {
        const [t, d] = await Promise.all([
            get(`/tareas/data?filtro=${state.ambito}&estado=${estado}${state.vencidas ? '&vencidas=1' : ''}`),
            get('/centro-tareas/derivaciones'),
        ]);
        state.tareas = t.data || [];
        state.derivaciones = d.data || [];
    } catch (e) {}
}

// ── Bandeja ───────────────────────────────────────────────────────
function renderVistas() {
    const counts = {
        activas:      state.tareas.filter(t => t.estado !== 'completada').length,
        derivaciones: state.derivaciones.length,
        completadas:  state.vista === 'completadas' ? state.tareas.length : null,
    };
    document.getElementById('b-vistas').innerHTML = [
        ['activas', 'Activas', counts.activas],
        ['derivaciones', 'Derivaciones del bot', counts.derivaciones],
        ['completadas', 'Completadas', counts.completadas],
    ].map(([k, lbl, n]) =>
        `<button class="v2-vista ${state.vista === k ? 'active' : ''}" onclick="setVista('${k}')">${lbl}${n !== null ? `<span class="n">${n}</span>` : ''}</button>`
    ).join('');
    document.getElementById('b-count').textContent = state.vista === 'derivaciones' ? state.derivaciones.length : state.tareas.length;

    // Fila de ámbito (paridad V1): a quién pertenecen las tareas + toggle vencidas.
    // No aplica a la vista de derivaciones del bot.
    const amb = document.getElementById('b-ambito');
    if (state.vista === 'derivaciones') { amb.style.display = 'none'; return; }
    amb.style.display = '';
    amb.innerHTML = [['mias', 'Mías'], ['asignadas', 'Asignadas a mí'], ['creadas', 'Creadas por mí'], ['todas', 'Todas']]
        .map(([k, lbl]) => `<button class="v2-vista ${state.ambito === k ? 'active' : ''}" onclick="setAmbito('${k}')">${lbl}</button>`)
        .join('')
        + `<button class="v2-vista ${state.vencidas ? 'active' : ''}" style="margin-left:auto;" title="Solo tareas vencidas sin completar" onclick="toggleVencidas()">⚠ Vencidas</button>`;
}
async function setVista(v) { state.vista = v; await fetchAll(); renderBandeja(); }
async function setAmbito(a) { state.ambito = a; await fetchAll(); renderBandeja(); }
async function toggleVencidas() { state.vencidas = !state.vencidas; await fetchAll(); renderBandeja(); }

function renderBandeja() {
    renderVistas();
    const cont = document.getElementById('b-cards');
    const q = state.q.toLowerCase();

    if (state.vista === 'derivaciones') {
        const list = state.derivaciones.filter(d => !q || (d.contacto || '').toLowerCase().includes(q) || (d.telefono || '').includes(q));
        cont.innerHTML = list.length ? list.map(d => {
            const sel = state.sel?.tipo === 'der' && state.sel.id === d.id;
            return `<div class="v2-card ${d.urgente ? 'urgente' : ''} ${sel ? 'selected' : ''}" onclick="abrirDer(${d.id})">
                <div class="v2-card-l1"><span class="v2-pill espera">${esc(d.etiqueta || 'Derivación')}</span>${d.urgente ? '<span class="v2-pill urgente">Urgente</span>' : ''}<span class="ago">${esc(d.hace || '')}</span></div>
                <div class="v2-card-l2"><span class="nombre">${esc(d.telefono || d.contacto)}</span></div>
                <div class="resumen">${esc(d.resumen || d.texto || '—')}</div>
            </div>`;
        }).join('') : `<div class="v2-empty"><span class="ico">🤖</span>No tenés derivaciones del bot tomadas.</div>`;
        return;
    }

    const list = state.tareas.filter(t => !q || (t.titulo || '').toLowerCase().includes(q));
    cont.innerHTML = list.length ? list.map(t => {
        const sel = state.sel?.tipo === 'tarea' && state.sel.id === t.id;
        const vence = t.vence_fmt
            ? `<span class="ago ${t.vencida ? 'urg' : ''}" title="Vence">${t.vencida ? '⚠ ' : ''}${esc(t.vence_fmt)}</span>`
            : `<span class="ago">${esc(t.hace || '')}</span>`;
        return `<div class="v2-card ${t.vencida ? 'urgente' : ''} ${sel ? 'selected' : ''}" onclick="abrirTarea(${t.id})">
            <div class="v2-card-l1">${PRIO_PILL[t.prioridad] || ''}<span class="v2-pill neutral">${ESTADO_LBL[t.estado] || t.estado}</span>${vence}</div>
            <div class="v2-card-l2"><span class="nombre">${esc(t.titulo)}</span></div>
            ${t.descripcion ? `<div class="resumen">${esc(t.descripcion)}</div>` : ''}
            <div class="v2-card-foot"><span class="who">→ ${esc((t.asignado_nombre || 'Sin asignar').split(' ')[0])}</span>${t.comentarios.length ? `<span>💬 ${t.comentarios.length}</span>` : ''}</div>
        </div>`;
    }).join('') : `<div class="v2-empty"><span class="ico">✨</span>Sin tareas ${state.vista === 'completadas' ? 'completadas' : 'activas'}.</div>`;
}

document.getElementById('b-search').addEventListener('input', e => { state.q = e.target.value.trim(); renderBandeja(); });

// ── Detalle: tarea ────────────────────────────────────────────────
function abrirTarea(id) {
    state.sel = { tipo: 'tarea', id };
    renderBandeja();
    const t = state.tareas.find(x => x.id === id);
    if (!t) return;

    document.getElementById('det-empty').style.display = 'none';
    const body = document.getElementById('det-body');
    body.style.display = 'flex';

    const acciones = [];
    if (t.estado !== 'completada') {
        if (t.estado === 'pendiente') acciones.push(`<button class="v2-btn" onclick="estadoTarea(${t.id}, 'en_progreso')">Empezar</button>`);
        acciones.push(`<button class="v2-btn accent" onclick="estadoTarea(${t.id}, 'completada')">Completar</button>`);
    } else {
        acciones.push(`<button class="v2-btn" onclick="estadoTarea(${t.id}, 'pendiente')">Reabrir</button>`);
    }
    acciones.push(`<button class="v2-btn" onclick="abrirEditar(${t.id})" title="Editar título, asignación, vencimiento o prioridad">✏ Editar</button>`);
    acciones.push(`<button class="v2-btn danger" onclick="borrarTarea(${t.id})">Eliminar</button>`);

    body.innerHTML = `
        <div class="v2-det-head">
            <div class="info">
                <div class="nombre">${esc(t.titulo)}</div>
                <div class="sub">creada por ${esc(t.creado_nombre || '—')} · ${esc(t.hace || '')}</div>
            </div>
            ${PRIO_PILL[t.prioridad] || ''}
            <div class="acciones">${acciones.join('')}</div>
        </div>
        <div class="v2-msgs" style="gap:12px;">
            <div class="v2-leg-tabla" style="max-width:560px;">
                <div class="v2-leg-row"><span class="k">Estado</span><span class="v">${ESTADO_LBL[t.estado] || t.estado}</span></div>
                <div class="v2-leg-row"><span class="k">Asignada a</span><span class="v">${esc(t.asignado_nombre || 'Sin asignar')}</span></div>
                ${t.vence_fmt ? `<div class="v2-leg-row"><span class="k">Vence</span><span class="v mono" ${t.vencida ? 'style="color:var(--v2-urg);"' : ''}>${esc(t.vence_fmt)}${t.vencida ? ' · vencida' : ''}</span></div>` : ''}
            </div>
            ${t.descripcion ? `<div class="v2-bubble" style="max-width:560px;">${esc(t.descripcion)}</div>` : ''}
            <div style="max-width:560px;">
                <div class="v2-label" style="margin-bottom:6px;">Comentarios (${t.comentarios.length})</div>
                <div id="coms">${t.comentarios.map(c => `
                    <div class="v2-leg-row" style="border:none;padding:5px 0;align-items:baseline;">
                        <span><b>${esc((c.usuario || '—').split(' ')[0])}</b> · <span style="color:var(--v2-text-2);">${esc(c.contenido)}</span></span>
                        <span class="v mono" style="flex-shrink:0;">${esc(c.hace || c.hora)}</span>
                    </div>`).join('') || '<div style="font-size:12px;color:var(--v2-text-mute);">Sin comentarios.</div>'}
                </div>
            </div>
        </div>
        <div class="v2-compose">
            <div class="v2-compose-row">
                <textarea id="compose-com" placeholder="Agregar un comentario…" style="min-height:42px;"></textarea>
                <button class="v2-btn primary" onclick="comentar(${t.id})">Comentar</button>
            </div>
        </div>`;
}

async function estadoTarea(id, estado) {
    try {
        await patch(`/tareas/${id}`, { estado });
        v2toast(estado === 'completada' ? 'Tarea completada' : 'Actualizada');
        await fetchAll(); renderBandeja();
        if (estado === 'completada' && state.vista !== 'completadas') cerrarDetalle();
        else abrirTarea(id);
    } catch (e) { v2toast('No se pudo actualizar', 'err'); }
}

async function borrarTarea(id) {
    if (!confirm('¿Eliminar esta tarea? No se puede deshacer.')) return;
    try {
        await del(`/tareas/${id}`);
        v2toast('Tarea eliminada');
        cerrarDetalle();
        await fetchAll(); renderBandeja();
    } catch (e) { v2toast('No se pudo eliminar', 'err'); }
}

async function comentar(id) {
    const ta = document.getElementById('compose-com');
    const contenido = ta.value.trim();
    if (!contenido) return;
    try {
        await post(`/tareas/${id}/comentario`, { contenido });
        await fetchAll();
        abrirTarea(id);
    } catch (e) { v2toast('No se pudo comentar', 'err'); }
}

// ── Detalle: derivación del bot ──────────────────────────────────
function abrirDer(id) {
    state.sel = { tipo: 'der', id };
    renderBandeja();
    const d = state.derivaciones.find(x => x.id === id);
    if (!d) return;

    document.getElementById('det-empty').style.display = 'none';
    const body = document.getElementById('det-body');
    body.style.display = 'flex';
    body.innerHTML = `
        <div class="v2-det-head">
            <div class="info">
                <div class="nombre">${esc(d.telefono || d.contacto)}</div>
                <div class="sub">derivación del bot · ${esc(d.creada_fmt || '')}</div>
            </div>
            <span class="v2-pill espera">${esc(d.etiqueta || 'Derivación')}</span>
            <div class="acciones"><button class="v2-btn accent" onclick="resolverDer(${d.id})">Resolver</button></div>
        </div>
        <div class="v2-msgs" style="gap:12px;">
            ${d.resumen ? `<div class="v2-resumen" style="margin:0;max-width:560px;"><span class="tag">Resumen IA</span>${esc(d.resumen)}</div>` : ''}
            <div class="v2-bubble" style="max-width:560px;white-space:pre-wrap;">${esc(d.texto || '—')}</div>
        </div>`;
}

async function resolverDer(id) {
    try {
        await post('/atencion/resolver', { id, tipo: 'bot' });
        v2toast('Derivación resuelta');
        cerrarDetalle();
        await fetchAll(); renderBandeja();
    } catch (e) { v2toast('No se pudo resolver', 'err'); }
}

function cerrarDetalle() {
    state.sel = null;
    document.getElementById('det-body').style.display = 'none';
    document.getElementById('det-empty').style.display = '';
}

// ── Alta / edición (mismo dialog; _editId decide POST o PATCH) ────
let _editId = null;

document.getElementById('nt-prio').addEventListener('click', e => {
    if (!e.target.dataset.v) return;
    document.querySelectorAll('#nt-prio .v2-vista').forEach(b => b.classList.toggle('active', b === e.target));
});

function setPrioChips(prio) {
    document.querySelectorAll('#nt-prio .v2-vista').forEach(b => b.classList.toggle('active', b.dataset.v === prio));
}

function abrirNueva() {
    _editId = null;
    document.getElementById('nt-h3').textContent = 'Nueva tarea';
    document.getElementById('nt-submit').textContent = 'Crear tarea';
    document.getElementById('nt-titulo').value = '';
    document.getElementById('nt-desc').value = '';
    document.getElementById('nt-asig').value = String(ME_ID);
    document.getElementById('nt-vence').value = '';
    setPrioChips('normal');
    document.getElementById('dlg-nueva').showModal();
    document.getElementById('nt-titulo').focus();
}

function abrirEditar(id) {
    const t = state.tareas.find(x => x.id === id);
    if (!t) return;
    _editId = id;
    document.getElementById('nt-h3').textContent = 'Editar tarea';
    document.getElementById('nt-submit').textContent = 'Guardar cambios';
    document.getElementById('nt-titulo').value = t.titulo || '';
    document.getElementById('nt-desc').value = t.descripcion || '';
    document.getElementById('nt-asig').value = t.asignada_a ? String(t.asignada_a) : String(ME_ID);
    document.getElementById('nt-vence').value = t.vence_at || '';
    setPrioChips(t.prioridad || 'normal');
    document.getElementById('dlg-nueva').showModal();
    document.getElementById('nt-titulo').focus();
}

async function guardarTarea() {
    const titulo = document.getElementById('nt-titulo').value.trim();
    if (!titulo) { v2toast('El título es obligatorio', 'err'); return; }
    const payload = {
        titulo,
        descripcion: document.getElementById('nt-desc').value.trim() || null,
        asignada_a:  parseInt(document.getElementById('nt-asig').value),
        vence_at:    document.getElementById('nt-vence').value || null,
        prioridad:   document.querySelector('#nt-prio .active')?.dataset.v || 'normal',
    };
    try {
        if (_editId) {
            await patch(`/tareas/${_editId}`, payload);
            v2toast('Tarea actualizada');
        } else {
            await post('/tareas', payload);
            v2toast('Tarea creada');
        }
        document.getElementById('dlg-nueva').close();
        await fetchAll(); renderBandeja();
        if (_editId) {
            // Re-pinta el detalle; si la edición la sacó del filtro activo
            // (ej: reasignada mirando "Asignadas a mí"), cerrar el detalle.
            if (state.tareas.some(x => x.id === _editId)) abrirTarea(_editId);
            else cerrarDetalle();
        }
    } catch (e) { v2toast(_editId ? 'No se pudo actualizar' : 'No se pudo crear', 'err'); }
}

// ── Init ──────────────────────────────────────────────────────────
(async () => { await fetchAll(); renderBandeja(); })();
setInterval(async () => { await fetchAll(); renderBandeja(); }, 15000);
</script>
@endpush
