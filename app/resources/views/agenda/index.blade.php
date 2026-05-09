@extends('layouts.app')
@section('title', 'Agenda')

@push('styles')
<style>
.ag-root { display: flex; gap: 0; height: calc(100vh - 52px); margin: -24px; overflow: hidden; }

/* Sidebar filtros */
.ag-sidebar {
    width: 220px;
    flex-shrink: 0;
    border-right: 1px solid var(--border);
    background: var(--surface);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    padding: 16px 12px;
    gap: 4px;
}
.ag-sidebar-title { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; padding: 6px 8px 4px; }
.ag-filter-btn {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 10px; border-radius: 6px; border: none;
    background: transparent; color: var(--muted); font-size: 13px;
    cursor: pointer; width: 100%; text-align: left; transition: .1s;
}
.ag-filter-btn:hover { background: var(--card); color: var(--text); }
.ag-filter-btn.active { background: rgba(192,39,58,.12); color: var(--accent); font-weight: 600; }
.ag-filter-btn .cnt { margin-left: auto; font-size: 11px; background: var(--border); border-radius: 10px; padding: 0 7px; }
.ag-filter-btn.active .cnt { background: rgba(192,39,58,.25); }
.ag-sep { border: none; border-top: 1px solid var(--border); margin: 8px 0; }

/* Main */
.ag-main { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; }

.ag-toolbar {
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
    background: var(--surface); flex-shrink: 0;
}
.ag-search {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 6px; color: var(--text); font-size: 13px;
    padding: 5px 10px; width: 220px; height: 32px;
}
.ag-search:focus { outline: none; border-color: var(--info); }
.btn-nueva {
    height: 32px; padding: 0 14px;
    background: var(--accent); border: none; color: #fff;
    border-radius: 6px; font-size: 13px; cursor: pointer;
    margin-left: auto;
}

/* Lista */
.ag-list { flex: 1; overflow-y: auto; padding: 12px 16px; }

/* Grupos de fecha */
.ag-group-label {
    font-size: 11px; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .5px;
    padding: 14px 0 6px;
}
.ag-group-label:first-child { padding-top: 0; }

/* Cards de tarea */
.ag-card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 8px; padding: 12px 14px;
    margin-bottom: 6px; display: flex; align-items: flex-start; gap: 12px;
    cursor: default; transition: border-color .12s;
}
.ag-card:hover { border-color: var(--muted); }
.ag-card.completada { opacity: .5; }
.ag-card.vencida { border-color: rgba(248,81,73,.4); }
.ag-card.alta { border-left: 3px solid var(--accent); }
.ag-card.normal { border-left: 3px solid var(--info); }
.ag-card.baja { border-left: 3px solid var(--border); }

.ag-check {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2px solid var(--border); cursor: pointer;
    flex-shrink: 0; margin-top: 2px; transition: .12s;
    display: flex; align-items: center; justify-content: center;
}
.ag-check:hover { border-color: var(--success); }
.ag-check.done { background: var(--success); border-color: var(--success); }
.ag-check.done::after { content: '✓'; font-size: 10px; color: #fff; }

.ag-card-body { flex: 1; min-width: 0; }
.ag-titulo { font-size: 14px; font-weight: 500; color: var(--text); margin-bottom: 3px; }
.ag-card.completada .ag-titulo { text-decoration: line-through; }
.ag-desc { font-size: 12px; color: var(--muted); line-height: 1.4; margin-bottom: 5px; white-space: pre-wrap; }
.ag-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.ag-chip {
    font-size: 11px; padding: 1px 8px; border-radius: 10px;
    border: 1px solid var(--border); color: var(--muted);
}
.ag-chip.vence-hoy  { border-color: rgba(210,153,34,.4); color: var(--warning); background: rgba(210,153,34,.08); }
.ag-chip.vencida    { border-color: rgba(248,81,73,.4);  color: var(--error);   background: rgba(248,81,73,.08); }
.ag-chip.asig       { border-color: rgba(88,166,255,.3); color: var(--info); }
.ag-chip.ref        { border-color: var(--border); color: var(--muted); cursor: pointer; }
.ag-chip.ref:hover  { color: var(--info); border-color: var(--info); }
.ag-prioridad-alta  { color: var(--accent); font-weight: 700; }

.ag-actions { display: flex; gap: 4px; flex-shrink: 0; }
.ag-btn {
    padding: 3px 9px; border-radius: 5px; border: 1px solid var(--border);
    background: transparent; color: var(--muted); font-size: 11px; cursor: pointer;
}
.ag-btn:hover { color: var(--text); border-color: var(--text); }
.ag-btn.del:hover { color: var(--error); border-color: var(--error); }

.ag-empty { text-align: center; padding: 60px; color: var(--muted); font-size: 14px; }

/* Modal */
.modal-bg {
    position: fixed; inset: 0; background: rgba(0,0,0,.6);
    z-index: 1000; display: flex; align-items: center; justify-content: center;
}
.modal-bg.hidden { display: none; }
.modal {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 10px; padding: 24px; width: 480px; max-width: 96vw;
}
.modal h2 { font-size: 15px; font-weight: 700; margin-bottom: 18px; }
.field { margin-bottom: 14px; }
.field label { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; display: block; margin-bottom: 5px; }
.field input, .field textarea, .field select {
    width: 100%; background: var(--surface); border: 1px solid var(--border);
    border-radius: 6px; color: var(--text); font-size: 13px; padding: 7px 10px;
    font-family: inherit;
}
.field input:focus, .field textarea:focus, .field select:focus { outline: none; border-color: var(--info); }
.field textarea { min-height: 70px; resize: vertical; }
.field-row { display: flex; gap: 12px; }
.field-row .field { flex: 1; }
.modal-btns { display: flex; gap: 8px; justify-content: flex-end; margin-top: 18px; }
.btn-cancel { padding: 7px 16px; border-radius: 6px; border: 1px solid var(--border); background: transparent; color: var(--muted); font-size: 13px; cursor: pointer; }
.btn-save   { padding: 7px 16px; border-radius: 6px; background: var(--accent); border: none; color: #fff; font-size: 13px; cursor: pointer; font-weight: 500; }

.prio-alta   { color: var(--accent); }
.prio-normal { color: var(--info); }
.prio-baja   { color: var(--muted); }

.toast { position:fixed;bottom:90px;right:24px;z-index:9999;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500;opacity:0;transform:translateY(8px);transition:.2s;pointer-events:none; }
.toast.show { opacity:1;transform:none; }
.toast.ok    { background:rgba(63,185,80,.15);color:var(--success);border:1px solid rgba(63,185,80,.3); }
.toast.error { background:rgba(248,81,73,.15);color:var(--error);border:1px solid rgba(248,81,73,.3); }
</style>
@endpush

@section('content')
<div class="ag-root">

    {{-- Sidebar --}}
    <div class="ag-sidebar">
        <div class="ag-sidebar-title">Ver</div>
        <button class="ag-filter-btn active" onclick="setFiltro('estado','pendiente',this)">
            📋 Pendientes <span class="cnt" id="cnt-pendiente">—</span>
        </button>
        <button class="ag-filter-btn" onclick="setFiltro('estado','completada',this)">
            ✓ Completadas <span class="cnt" id="cnt-completada">—</span>
        </button>
        <button class="ag-filter-btn" onclick="setFiltro('estado','todas',this)">
            ☰ Todas
        </button>

        <hr class="ag-sep">
        <div class="ag-sidebar-title">Asignadas a</div>
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
            <input class="ag-search" type="text" id="search-input"
                placeholder="Buscar tarea…" oninput="renderFiltrado()">
            <button class="btn-nueva" onclick="abrirModal()">＋ Nueva tarea</button>
        </div>
        <div class="ag-list" id="ag-list">
            <div class="ag-empty">Cargando…</div>
        </div>
    </div>

</div>

{{-- Modal crear/editar --}}
<div class="modal-bg hidden" id="modal-bg" onclick="if(event.target===this)cerrarModal()">
    <div class="modal">
        <h2 id="modal-titulo">Nueva tarea</h2>
        <input type="hidden" id="edit-id">
        <div class="field">
            <label>Título *</label>
            <input type="text" id="f-titulo" placeholder="Descripción breve de la tarea" autofocus>
        </div>
        <div class="field">
            <label>Detalle</label>
            <textarea id="f-desc" placeholder="Información adicional…"></textarea>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Asignar a</label>
                <select id="f-asig">
                    <option value="">Sin asignar</option>
                    @foreach($usuarios as $u)
                    <option value="{{ $u->id }}">{{ $u->nombre_completo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Prioridad</label>
                <select id="f-prioridad">
                    <option value="normal">Normal</option>
                    <option value="alta">Alta</option>
                    <option value="baja">Baja</option>
                </select>
            </div>
        </div>
        <div class="field">
            <label>Vence el</label>
            <input type="datetime-local" id="f-vence">
        </div>
        <input type="hidden" id="f-ref-tipo">
        <input type="hidden" id="f-ref-id">
        <div id="f-ref-label" style="font-size:11px;color:var(--info);margin-bottom:12px;display:none;"></div>
        <div class="modal-btns">
            <button class="btn-cancel" onclick="cerrarModal()">Cancelar</button>
            <button class="btn-save" onclick="guardarTarea()">Guardar</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CSRF   = '{{ csrf_token() }}';
const ME_ID  = {{ auth()->id() }};
const ME_NAME = '{{ addslashes(auth()->user()->nombre_completo) }}';

let tareas    = [];
let filtroEstado = 'pendiente';
let filtroAsig   = null;

// ── API ──────────────────────────────────────────────────────
async function api(method, url, body) {
    const opts = { method, headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'} };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(url, opts);
    if (!r.ok) throw new Error(r.status);
    return r.json();
}
const get    = url       => api('GET', url);
const post   = (url, b)  => api('POST', url, b);
const patch  = (url, b)  => api('PATCH', url, b);
const del    = url       => api('DELETE', url);

function toast(msg, tipo='ok') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = `toast ${tipo} show`;
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 3000);
}

// ── Fetch ────────────────────────────────────────────────────
async function fetchTareas() {
    const params = new URLSearchParams({ estado: filtroEstado });
    if (filtroAsig) params.set('asig', filtroAsig);
    try {
        const data = await get('/agenda/data?' + params);
        tareas = data.data || [];
        actualizarContadores();
        renderFiltrado();
    } catch(e) {}
}

async function actualizarContadores() {
    try {
        const [pend, comp] = await Promise.all([
            get('/agenda/data?estado=pendiente'),
            get('/agenda/data?estado=completada'),
        ]);
        document.getElementById('cnt-pendiente').textContent = pend.data?.length ?? '—';
        document.getElementById('cnt-completada').textContent = comp.data?.length ?? '—';
    } catch(e) {}
}

// ── Filtros ──────────────────────────────────────────────────
function setFiltro(clave, valor, btn) {
    filtroEstado = valor;
    document.querySelectorAll('.ag-filter-btn').forEach(b => {
        if (['pendiente','completada','todas'].some(v => b.getAttribute('onclick')?.includes(`'${v}'`))) {
            b.classList.remove('active');
        }
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

// ── Render ───────────────────────────────────────────────────
function renderFiltrado() {
    const q = document.getElementById('search-input').value.trim().toLowerCase();
    let items = tareas;
    if (q) items = items.filter(t => t.titulo.toLowerCase().includes(q) || (t.descripcion||'').toLowerCase().includes(q));

    if (!items.length) {
        document.getElementById('ag-list').innerHTML = '<div class="ag-empty">Sin tareas</div>';
        return;
    }

    // Agrupar por fecha de vencimiento
    const grupos = {};
    for (const t of items) {
        const key = grupKey(t);
        if (!grupos[key]) grupos[key] = { label: grupLabel(t), items: [], orden: grupOrden(t) };
        grupos[key].items.push(t);
    }

    const sorted = Object.values(grupos).sort((a,b) => a.orden - b.orden);
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
    const hoy   = new Date().toISOString().slice(0,10);
    const manna = new Date(Date.now() + 86400000).toISOString().slice(0,10);
    if (fecha === hoy)   return 'hoy';
    if (fecha === manna) return 'manana';
    return `futuro_${fecha}`;
}

function grupLabel(t) {
    const k = grupKey(t);
    if (k === 'sinFecha')  return 'Sin fecha';
    if (k === 'vencidas')  return '⚠ Vencidas';
    if (k === 'hoy')       return '📅 Hoy';
    if (k === 'manana')    return 'Mañana';
    return t.vence_at?.split(' ')[0] || 'Próximas';
}

function grupOrden(t) {
    const k = grupKey(t);
    if (k === 'vencidas')  return 0;
    if (k === 'hoy')       return 1;
    if (k === 'manana')    return 2;
    if (k === 'sinFecha')  return 999;
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
        : t.vence_at ? `<span class="ag-chip ${grupKey(t)==='hoy'?'vence-hoy':''}">${grupKey(t)==='hoy'?'Hoy':''} ${t.vence_at}</span>`
        : '';

    const asigChip = t.asig_name
        ? `<span class="ag-chip asig">👤 ${esc(t.asig_name)}</span>`
        : '';

    const refChip = t.ref_tipo
        ? `<span class="ag-chip ref" onclick="irARef('${t.ref_tipo}',${t.ref_id})" title="Ver origen">${t.ref_tipo === 'bot' ? '🤖 BOT' : '💬 WA'} #${t.ref_id}</span>`
        : '';

    const desc = t.descripcion
        ? `<div class="ag-desc">${esc(t.descripcion)}</div>`
        : '';

    return `<div class="${cardClass}" id="tarea-${t.id}">
        <div class="ag-check ${doneClass}" onclick="toggleEstado(${t.id}, '${t.estado}')" title="${t.estado==='completada'?'Marcar pendiente':'Marcar completada'}"></div>
        <div class="ag-card-body">
            <div class="ag-titulo">${esc(t.titulo)}</div>
            ${desc}
            <div class="ag-meta">
                ${prioLabel}${venceChip}${asigChip}${refChip}
                <span class="ag-chip" style="opacity:.5">Por ${esc(t.creada_por||'—')}</span>
            </div>
        </div>
        <div class="ag-actions">
            <button class="ag-btn" onclick="abrirEditar(${t.id})">✎</button>
            <button class="ag-btn del" onclick="eliminar(${t.id})">✕</button>
        </div>
    </div>`;
}

// ── Acciones ─────────────────────────────────────────────────
async function toggleEstado(id, estadoActual) {
    const nuevo = estadoActual === 'completada' ? 'pendiente' : 'completada';
    const t = tareas.find(x => x.id === id);
    if (t) { t.estado = nuevo; t.completada = nuevo === 'completada'; renderFiltrado(); }
    try {
        await patch(`/agenda/${id}`, { estado: nuevo });
        toast(nuevo === 'completada' ? '✓ Completada' : 'Reabierta');
        actualizarContadores();
        if (filtroEstado !== 'todas') setTimeout(fetchTareas, 800);
    } catch(e) { toast('Error', 'error'); if (t) { t.estado = estadoActual; renderFiltrado(); } }
}

async function eliminar(id) {
    tareas = tareas.filter(t => t.id !== id);
    renderFiltrado();
    del(`/agenda/${id}`).then(() => { toast('Eliminada'); actualizarContadores(); }).catch(() => toast('Error','error'));
}

function irARef(tipo, id) {
    window.open(`/atencion`, '_blank');
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

    document.getElementById('modal-bg').classList.remove('hidden');
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
    document.getElementById('modal-bg').classList.remove('hidden');
    setTimeout(() => document.getElementById('f-titulo').focus(), 50);
}

function cerrarModal() {
    document.getElementById('modal-bg').classList.add('hidden');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });

async function guardarTarea() {
    const titulo = document.getElementById('f-titulo').value.trim();
    if (!titulo) { document.getElementById('f-titulo').focus(); return; }

    const editId   = document.getElementById('edit-id').value;
    const payload  = {
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
        toast(editId ? 'Tarea actualizada' : 'Tarea creada');
    } catch(e) { toast('Error al guardar', 'error'); }
}

// Guardar con Enter en el campo título
document.getElementById('f-titulo')?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); guardarTarea(); }
});

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Init ─────────────────────────────────────────────────────
fetchTareas();
setInterval(fetchTareas, 15000);
</script>
@endsection
