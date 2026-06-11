@extends('layouts.v2')
@section('title', 'Agenda')

{{-- PoC V2 — agenda con el MISMO funcionamiento que /agenda de producción:
     filtros laterales (estado con contadores + asignadas a), búsqueda en vivo,
     lista agrupada por vencimiento (Vencidas/Hoy/Mañana/fecha/Sin fecha),
     check circular para completar con update optimista, crear/editar en modal,
     eliminar, polling 15s. Mismos endpoints /agenda/*. --}}

@push('styles')
<style>
.ag-root { flex: 1; display: flex; min-height: 0; }

/* Columna de filtros propia de la pantalla (convive con el sidebar global) */
.ag-filtros {
    width: 210px; flex-shrink: 0;
    border-right: 1px solid var(--v2-border);
    background: var(--v2-bg-card);
    overflow-y: auto; padding: 14px 10px;
}
.ag-filtros-title { font-size: 10.5px; font-weight: 600; color: var(--v2-text-mute); text-transform: uppercase; letter-spacing: .6px; padding: 6px 8px 4px; }
.ag-filter-btn {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 10px; border-radius: var(--v2-radius-sm); border: none;
    background: transparent; color: var(--v2-text-2); font-size: 13px;
    cursor: pointer; width: 100%; text-align: left; transition: .1s;
}
.ag-filter-btn:hover { background: var(--v2-bg-hover); color: var(--v2-text); }
.ag-filter-btn.active { background: var(--v2-accent-bg); color: var(--v2-accent); font-weight: 600; }
.ag-filter-btn .cnt { margin-left: auto; font-size: 11px; background: var(--v2-bg-hover); border-radius: 10px; padding: 0 7px; font-family: 'JetBrains Mono', monospace; }
.ag-filter-btn.active .cnt { background: var(--v2-accent); color: #fff; }
.ag-sep { border: none; border-top: 1px solid var(--v2-border); margin: 8px 0; }

/* Main */
.ag-main { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; }
.ag-toolbar {
    padding: 10px 16px; border-bottom: 1px solid var(--v2-border);
    display: flex; align-items: center; gap: 10px;
    background: var(--v2-bg-card); flex-shrink: 0;
}
.ag-list { flex: 1; overflow-y: auto; padding: 12px 16px; }

.ag-group-label {
    font-size: 10.5px; font-weight: 600; color: var(--v2-text-mute);
    text-transform: uppercase; letter-spacing: .6px;
    padding: 14px 0 6px;
}
.ag-group-label:first-child { padding-top: 0; }

/* Cards de tarea */
.ag-card {
    background: var(--v2-bg-card); border: 1px solid var(--v2-border);
    border-radius: var(--v2-radius); padding: 11px 13px;
    margin-bottom: 6px; display: flex; align-items: flex-start; gap: 12px;
    transition: border-color .12s;
}
.ag-card:hover { border-color: var(--v2-border-strong); }
.ag-card.completada { opacity: .5; }
.ag-card.vencida { border-color: var(--v2-urg); }
.ag-card.alta   { border-left: 3px solid var(--v2-accent); }
.ag-card.normal { border-left: 3px solid var(--v2-info); }
.ag-card.baja   { border-left: 3px solid var(--v2-border-strong); }

.ag-check {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2px solid var(--v2-border-strong); cursor: pointer;
    flex-shrink: 0; margin-top: 2px; transition: .12s;
    display: flex; align-items: center; justify-content: center;
}
.ag-check:hover { border-color: var(--v2-ok); }
.ag-check.done { background: var(--v2-ok); border-color: var(--v2-ok); }
.ag-check.done::after { content: '✓'; font-size: 10px; color: #fff; }

.ag-card-body { flex: 1; min-width: 0; }
.ag-titulo { font-size: 13.5px; font-weight: 500; margin-bottom: 3px; }
.ag-card.completada .ag-titulo { text-decoration: line-through; }
.ag-desc { font-size: 12px; color: var(--v2-text-2); line-height: 1.4; margin-bottom: 5px; white-space: pre-wrap; }
.ag-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ag-chip {
    font-size: 11px; padding: 1px 8px; border-radius: 10px;
    border: 1px solid var(--v2-border); color: var(--v2-text-mute);
}
.ag-chip.vence-hoy { border-color: var(--v2-warn); color: var(--v2-warn); background: var(--v2-warn-bg); }
.ag-chip.vencida   { border-color: var(--v2-urg);  color: var(--v2-urg);  background: var(--v2-urg-bg); }
.ag-chip.asig      { border-color: var(--v2-info); color: var(--v2-info); }
.ag-chip.prio-alta { border-color: var(--v2-accent); color: var(--v2-accent); background: var(--v2-accent-bg); font-weight: 600; }

.ag-actions { display: flex; gap: 4px; flex-shrink: 0; }
</style>
@endpush

@section('content')
<div class="ag-root">

    {{-- Filtros --}}
    <div class="ag-filtros">
        <div class="ag-filtros-title">Ver</div>
        <button class="ag-filter-btn active" onclick="setFiltro('pendiente',this)">
            📋 Pendientes <span class="cnt" id="cnt-pendiente">—</span>
        </button>
        <button class="ag-filter-btn" onclick="setFiltro('completada',this)">
            ✓ Completadas <span class="cnt" id="cnt-completada">—</span>
        </button>
        <button class="ag-filter-btn" onclick="setFiltro('todas',this)">
            ☰ Todas
        </button>

        <hr class="ag-sep">
        <div class="ag-filtros-title">Asignadas a</div>
        <button class="ag-filter-btn" onclick="setFiltroAsig('mias',this)">
            👤 Mis tareas
        </button>
        <button class="ag-filter-btn" onclick="setFiltroAsig(null,this)">
            👥 Todas
        </button>
        @foreach($usuarios as $u)
        <button class="ag-filter-btn" onclick="setFiltroAsig({{ $u->id }},this)">
            {{ $u->nombre_completo }}
        </button>
        @endforeach
    </div>

    {{-- Main --}}
    <div class="ag-main">
        <div class="ag-toolbar">
            <input class="v2-field" style="width:230px;margin:0;" type="text" id="search-input"
                placeholder="Buscar tarea…" oninput="renderFiltrado()">
            <button class="v2-btn primary" style="margin-left:auto;" onclick="abrirModal()">＋ Nueva tarea</button>
        </div>
        <div class="ag-list" id="ag-list">
            <div class="v2-empty">Cargando…</div>
        </div>
    </div>

</div>

{{-- Modal crear/editar --}}
<dialog class="v2-dialog" id="modal-tarea" style="width:min(480px,calc(100vw - 40px));">
    <h3 id="modal-titulo">Nueva tarea</h3>
    <input type="hidden" id="edit-id">
    <label class="v2-label" style="margin-top:4px;">Título *</label>
    <input type="text" id="f-titulo" class="v2-field" placeholder="Descripción breve de la tarea">
    <label class="v2-label">Detalle</label>
    <textarea id="f-desc" class="v2-field" placeholder="Información adicional…" style="min-height:70px;resize:vertical;"></textarea>
    <div class="v2-grid2">
        <div>
            <label class="v2-label">Asignar a</label>
            <select id="f-asig" class="v2-field">
                <option value="">Sin asignar</option>
                @foreach($usuarios as $u)
                <option value="{{ $u->id }}">{{ $u->nombre_completo }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="v2-label">Prioridad</label>
            <select id="f-prioridad" class="v2-field">
                <option value="normal">Normal</option>
                <option value="alta">Alta</option>
                <option value="baja">Baja</option>
            </select>
        </div>
    </div>
    <label class="v2-label">Vence el</label>
    <input type="datetime-local" id="f-vence" class="v2-field">
    <input type="hidden" id="f-ref-tipo">
    <input type="hidden" id="f-ref-id">
    <div id="f-ref-label" style="font-size:11px;color:var(--v2-info);margin-top:10px;display:none;"></div>
    <div class="v2-dialog-foot">
        <button class="v2-btn" onclick="cerrarModal()">Cancelar</button>
        <button class="v2-btn primary" onclick="guardarTarea()">Guardar</button>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
const { esc, get, post, patch, del } = V2;
const ME_ID = {{ auth()->id() }};

let tareas       = [];
let filtroEstado = 'pendiente';
let filtroAsig   = null;

// ── Fetch ────────────────────────────────────────────────────
async function fetchTareas() {
    const params = new URLSearchParams({ estado: filtroEstado });
    if (filtroAsig) params.set('asig', filtroAsig);
    try {
        const data = await get('/agenda/data?' + params);
        tareas = data.data || [];
        actualizarContadores();
        renderFiltrado();
    } catch (e) {}
}

async function actualizarContadores() {
    try {
        const [pend, comp] = await Promise.all([
            get('/agenda/data?estado=pendiente'),
            get('/agenda/data?estado=completada'),
        ]);
        document.getElementById('cnt-pendiente').textContent  = pend.data?.length ?? '—';
        document.getElementById('cnt-completada').textContent = comp.data?.length ?? '—';
    } catch (e) {}
}

// ── Filtros ──────────────────────────────────────────────────
function setFiltro(valor, btn) {
    filtroEstado = valor;
    document.querySelectorAll('.ag-filter-btn').forEach(b => {
        if (b.getAttribute('onclick')?.includes("setFiltro('")) b.classList.remove('active');
    });
    btn.classList.add('active');
    fetchTareas();
}

function setFiltroAsig(valor, btn) {
    filtroAsig = valor;
    document.querySelectorAll('.ag-filter-btn').forEach(b => {
        if (b.getAttribute('onclick')?.includes('setFiltroAsig')) b.classList.remove('active');
    });
    btn.classList.add('active');
    fetchTareas();
}

// ── Render: agrupado por vencimiento ─────────────────────────
function renderFiltrado() {
    const q = document.getElementById('search-input').value.trim().toLowerCase();
    let items = tareas;
    if (q) items = items.filter(t => t.titulo.toLowerCase().includes(q) || (t.descripcion || '').toLowerCase().includes(q));

    if (!items.length) {
        document.getElementById('ag-list').innerHTML = '<div class="v2-empty"><span class="ico">📅</span>Sin tareas</div>';
        return;
    }

    const grupos = {};
    for (const t of items) {
        const key = grupKey(t);
        if (!grupos[key]) grupos[key] = { label: grupLabel(t), items: [], orden: grupOrden(t) };
        grupos[key].items.push(t);
    }

    const sorted = Object.values(grupos).sort((a, b) => a.orden - b.orden);
    let html = '';
    for (const g of sorted) {
        html += `<div class="ag-group-label">${g.label}</div>`;
        html += g.items.map(renderCard).join('');
    }
    document.getElementById('ag-list').innerHTML = html;
}

function grupKey(t) {
    if (!t.vence_at) return 'sinFecha';
    if (t.vencida) return 'vencidas';
    const [d, m, y] = t.vence_at.split(' ')[0].split('/');
    const fecha = `${y}-${m}-${d}`;
    const hoy   = new Date().toISOString().slice(0, 10);
    const manna = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
    if (fecha === hoy)   return 'hoy';
    if (fecha === manna) return 'manana';
    return `futuro_${fecha}`;
}

function grupLabel(t) {
    const k = grupKey(t);
    if (k === 'sinFecha') return 'Sin fecha';
    if (k === 'vencidas') return '⚠ Vencidas';
    if (k === 'hoy')      return '📅 Hoy';
    if (k === 'manana')   return 'Mañana';
    return t.vence_at?.split(' ')[0] || 'Próximas';
}

function grupOrden(t) {
    const k = grupKey(t);
    if (k === 'vencidas') return 0;
    if (k === 'hoy')      return 1;
    if (k === 'manana')   return 2;
    if (k === 'sinFecha') return 999;
    return 10;
}

function renderCard(t) {
    const doneClass = t.estado === 'completada' ? 'done' : '';
    const cardClass = [
        'ag-card',
        t.prioridad,
        t.estado === 'completada' ? 'completada' : '',
        t.vencida ? 'vencida' : '',
    ].filter(Boolean).join(' ');

    const prioLabel = t.prioridad === 'alta'
        ? '<span class="ag-chip prio-alta">⬆ Alta</span>'
        : t.prioridad === 'baja' ? '<span class="ag-chip" style="opacity:.6">⬇ Baja</span>' : '';

    const venceChip = t.vencida
        ? `<span class="ag-chip vencida">⚠ Venció ${t.vence_at}</span>`
        : t.vence_at ? `<span class="ag-chip ${grupKey(t) === 'hoy' ? 'vence-hoy' : ''}">${grupKey(t) === 'hoy' ? 'Hoy' : ''} ${t.vence_at}</span>`
        : '';

    const asigChip = t.asig_name
        ? `<span class="ag-chip asig">👤 ${esc(t.asig_name)}</span>`
        : '';

    const refChip = t.ref_tipo
        ? `<span class="ag-chip" style="cursor:pointer;" onclick="irARef('${t.ref_tipo}',${t.ref_id})" title="Ver origen">${t.ref_tipo === 'bot' ? '🤖 BOT' : '💬 WA'} #${t.ref_id}</span>`
        : '';

    const desc = t.descripcion ? `<div class="ag-desc">${esc(t.descripcion)}</div>` : '';

    return `<div class="${cardClass}" id="tarea-${t.id}">
        <div class="ag-check ${doneClass}" onclick="toggleEstado(${t.id}, '${t.estado}')" title="${t.estado === 'completada' ? 'Marcar pendiente' : 'Marcar completada'}"></div>
        <div class="ag-card-body">
            <div class="ag-titulo">${esc(t.titulo)}</div>
            ${desc}
            <div class="ag-meta">
                ${prioLabel}${venceChip}${asigChip}${refChip}
                <span class="ag-chip" style="opacity:.5">Por ${esc(t.creada_por || '—')}</span>
            </div>
        </div>
        <div class="ag-actions">
            <button class="v2-btn sm" onclick="abrirEditar(${t.id})">✎</button>
            <button class="v2-btn sm danger" onclick="eliminar(${t.id})">✕</button>
        </div>
    </div>`;
}

// ── Acciones (mismo update optimista que producción) ─────────
async function toggleEstado(id, estadoActual) {
    const nuevo = estadoActual === 'completada' ? 'pendiente' : 'completada';
    const t = tareas.find(x => x.id === id);
    if (t) { t.estado = nuevo; renderFiltrado(); }
    try {
        await patch(`/agenda/${id}`, { estado: nuevo });
        v2toast(nuevo === 'completada' ? '✓ Completada' : 'Reabierta');
        actualizarContadores();
        if (filtroEstado !== 'todas') setTimeout(fetchTareas, 800);
    } catch (e) {
        v2toast('Error', 'err');
        if (t) { t.estado = estadoActual; renderFiltrado(); }
    }
}

async function eliminar(id) {
    if (!confirm('¿Eliminar esta tarea?')) return;
    tareas = tareas.filter(t => t.id !== id);
    renderFiltrado();
    del(`/agenda/${id}`).then(() => { v2toast('Eliminada'); actualizarContadores(); }).catch(() => v2toast('Error', 'err'));
}

function irARef(tipo, id) {
    window.open('/v2/atencion', '_blank');
}

// ── Modal ────────────────────────────────────────────────────
function abrirModal(opts = {}) {
    document.getElementById('edit-id').value = '';
    document.getElementById('modal-titulo').textContent = 'Nueva tarea';
    document.getElementById('f-titulo').value    = opts.titulo || '';
    document.getElementById('f-desc').value      = opts.descripcion || '';
    document.getElementById('f-asig').value      = opts.asig_id || ME_ID;
    document.getElementById('f-prioridad').value = opts.prioridad || 'normal';
    document.getElementById('f-vence').value     = opts.vence_iso || '';
    document.getElementById('f-ref-tipo').value  = opts.ref_tipo || '';
    document.getElementById('f-ref-id').value    = opts.ref_id || '';

    const refLabel = document.getElementById('f-ref-label');
    if (opts.ref_tipo) {
        refLabel.textContent = `Desde: ${opts.ref_tipo === 'bot' ? '🤖 BOT' : '💬 WA'} — ${opts.contacto || ''}`;
        refLabel.style.display = 'block';
    } else {
        refLabel.style.display = 'none';
    }

    document.getElementById('modal-tarea').showModal();
    setTimeout(() => document.getElementById('f-titulo').focus(), 50);
}

function abrirEditar(id) {
    const t = tareas.find(x => x.id === id);
    if (!t) return;
    document.getElementById('edit-id').value = id;
    document.getElementById('modal-titulo').textContent = 'Editar tarea';
    document.getElementById('f-titulo').value    = t.titulo;
    document.getElementById('f-desc').value      = t.descripcion || '';
    document.getElementById('f-asig').value      = t.asig_id || '';
    document.getElementById('f-prioridad').value = t.prioridad;
    document.getElementById('f-vence').value     = t.vence_iso || '';
    document.getElementById('f-ref-tipo').value  = t.ref_tipo || '';
    document.getElementById('f-ref-id').value    = t.ref_id || '';
    document.getElementById('f-ref-label').style.display = 'none';
    document.getElementById('modal-tarea').showModal();
    setTimeout(() => document.getElementById('f-titulo').focus(), 50);
}

function cerrarModal() {
    document.getElementById('modal-tarea').close();
}

async function guardarTarea() {
    const titulo = document.getElementById('f-titulo').value.trim();
    if (!titulo) { document.getElementById('f-titulo').focus(); return; }

    const editId  = document.getElementById('edit-id').value;
    const payload = {
        titulo,
        descripcion: document.getElementById('f-desc').value.trim() || null,
        asignada_a:  document.getElementById('f-asig').value || null,
        prioridad:   document.getElementById('f-prioridad').value,
        vence_at:    document.getElementById('f-vence').value || null,
        ref_tipo:    document.getElementById('f-ref-tipo').value || null,
        ref_id:      document.getElementById('f-ref-id').value || null,
    };

    try {
        let resp;
        if (editId) {
            resp = await patch(`/agenda/${editId}`, payload);
            const idx = tareas.findIndex(t => t.id === parseInt(editId));
            if (idx >= 0) tareas[idx] = resp.tarea;
        } else {
            resp = await post('/agenda', payload);
            tareas.unshift(resp.tarea);
        }
        cerrarModal();
        renderFiltrado();
        actualizarContadores();
        v2toast(editId ? 'Tarea actualizada' : 'Tarea creada');
    } catch (e) { v2toast('Error al guardar', 'err'); }
}

// Guardar con Enter en el título (Escape lo maneja <dialog> nativo)
document.getElementById('f-titulo')?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); guardarTarea(); }
});

// ── Init ─────────────────────────────────────────────────────
fetchTareas();
setInterval(fetchTareas, 15000);
</script>
@endpush
