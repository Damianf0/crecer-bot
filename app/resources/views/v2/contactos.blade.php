@extends('layouts.v2')
@section('title', 'Contactos')

{{-- PoC V2 — directorio de contactos: bandeja | ficha sobre /contactos/data.
     Edición inline (PATCH /contactos/{id}): se edita donde se mira, sin
     pantalla "editar". Soporta deep-link ?sel=ID desde el legajo de una conv. --}}

@section('content')
<div class="v2-inbox no-legajo" id="inbox">

    <section class="v2-bandeja">
        <div class="v2-bandeja-head">
            <h1>Contactos <span class="count" id="b-count"></span></h1>
            <input type="search" class="v2-search" id="b-search" placeholder="Buscar por nombre, teléfono, DNI o email">
        </div>
        <div class="v2-cards" id="b-cards" style="padding-top:8px;"></div>
    </section>

    <section class="v2-detalle" id="detalle">
        <div class="v2-det-empty" id="det-empty">
            <span class="ico">📇</span>
            <span>Elegí un contacto para ver su ficha</span>
            <span style="font-size:11.5px;">Los campos se editan en el lugar: click en el valor, Enter para guardar.</span>
        </div>
        <div id="det-body" style="display:none;flex:1;flex-direction:column;min-height:0;overflow-y:auto;"></div>
    </section>
</div>
@endsection

@push('scripts')
<script>
const { esc, get, patch, avatarHtml } = V2;

let state = { items: [], q: '', page: 1, hasMore: false, total: 0, selId: null };
let debounce = null;

async function fetchItems(append = false) {
    const p = new URLSearchParams({ q: state.q, page: state.page });
    try {
        const d = await get('/contactos/data?' + p.toString());
        state.items = append ? [...state.items, ...(d.data || [])] : (d.data || []);
        state.hasMore = !!d.has_more;
        state.total = d.total || 0;
        renderBandeja();
    } catch (e) {}
}

function renderBandeja() {
    document.getElementById('b-count').textContent = state.total;
    const cont = document.getElementById('b-cards');
    if (!state.items.length) {
        cont.innerHTML = `<div class="v2-empty"><span class="ico">🔍</span>Sin resultados${state.q ? ' para esa búsqueda' : ''}.</div>`;
        return;
    }
    cont.innerHTML = state.items.map(c => `
        <div class="v2-card ${state.selId === c.id ? 'selected' : ''}" onclick="abrirFicha(${c.id})" style="padding:8px 11px;">
            <div class="v2-card-l2" style="margin:0;">
                ${avatarHtml(c.avatar_url, c.nombre, 30)}
                <div style="min-width:0;">
                    <div class="nombre">${esc(c.nombre)}</div>
                    <div style="font-size:11px;color:var(--v2-text-mute);font-family:'JetBrains Mono',monospace;">${esc(c.telefono || c.wa_id || '—')}${c.dni ? ' · DNI ' + esc(c.dni) : ''}</div>
                </div>
            </div>
        </div>`).join('') + (state.hasMore
        ? `<button class="more" onclick="state.page++; fetchItems(true)">Cargar más (${state.items.length} de ${state.total})</button>` : '');
}

// ── Ficha con edición inline ──────────────────────────────────────
const CAMPOS = [
    ['nombre',   'Nombre',     'text'],
    ['telefono', 'Teléfono',   'text'],
    ['dni',      'DNI',        'text'],
    ['email',    'Email',      'email'],
    ['notas',    'Notas',      'text'],
];

function abrirFicha(id) {
    state.selId = id;
    renderBandeja();
    const c = state.items.find(x => x.id === id);
    if (!c) return;

    document.getElementById('det-empty').style.display = 'none';
    const body = document.getElementById('det-body');
    body.style.display = 'flex';

    body.innerHTML = `
        <div class="v2-det-head">
            ${avatarHtml(c.avatar_url, c.nombre, 40)}
            <div class="info">
                <div class="nombre">${esc(c.nombre)}</div>
                <div class="sub">${esc(c.wa_id || 'sin WhatsApp resuelto')}</div>
            </div>
        </div>
        <div style="padding:16px;max-width:620px;">
            <div class="v2-leg-tabla">
                ${CAMPOS.map(([k, lbl, type]) => `
                <div class="v2-leg-row" style="align-items:center;">
                    <span class="k">${lbl}</span>
                    <input class="v2-inline-edit ${k === 'telefono' || k === 'dni' ? 'mono' : ''}" data-campo="${k}" data-id="${c.id}"
                           type="${type}" value="${esc(c[k] || '')}" placeholder="—"
                           title="Editar y Enter para guardar">
                </div>`).join('')}
                ${c.fecha_nacimiento ? `<div class="v2-leg-row"><span class="k">Nacimiento</span><span class="v mono">${esc(String(c.fecha_nacimiento).split('T')[0])}</span></div>` : ''}
                ${c.omnia_patient_id ? `<div class="v2-leg-row"><span class="k">Omnia ID</span><span class="v mono">${esc(String(c.omnia_patient_id))}</span></div>` : ''}
            </div>
            <div style="margin-top:14px;display:flex;flex-direction:column;gap:2px;">
                <a class="v2-leg-link" href="/pacientes/${c.id}/documentos" title="Abre en la UI actual">📄 Documentos del paciente ↗</a>
            </div>
            <div style="margin-top:10px;font-size:11px;color:var(--v2-text-mute);">Editás en el lugar: cambiá el valor y Enter (o click afuera) guarda solo.</div>
        </div>`;

    // Edición inline: Enter o blur → PATCH con todos los campos (el endpoint pide nombre).
    body.querySelectorAll('.v2-inline-edit').forEach(inp => {
        inp.dataset.orig = inp.value;
        inp.addEventListener('keydown', e => { if (e.key === 'Enter') inp.blur(); if (e.key === 'Escape') { inp.value = inp.dataset.orig; inp.blur(); } });
        inp.addEventListener('blur', () => guardarCampo(inp));
    });
}

async function guardarCampo(inp) {
    if (inp.value === inp.dataset.orig) return;
    const id = parseInt(inp.dataset.id);
    const c  = state.items.find(x => x.id === id);
    if (!c) return;

    const payload = {};
    for (const [k] of CAMPOS) payload[k] = c[k] || null;
    payload[inp.dataset.campo] = inp.value.trim() || null;
    if (!payload.nombre) { v2toast('El nombre no puede quedar vacío', 'err'); inp.value = inp.dataset.orig; return; }

    try {
        const r = await patch(`/contactos/${id}`, payload);
        Object.assign(c, r.contacto || payload);
        inp.dataset.orig = inp.value;
        v2toast('Guardado');
        renderBandeja();
    } catch (e) {
        v2toast('No se pudo guardar (¿duplicado?)', 'err');
        inp.value = inp.dataset.orig;
    }
}

// ── Búsqueda con debounce ─────────────────────────────────────────
document.getElementById('b-search').addEventListener('input', e => {
    clearTimeout(debounce);
    debounce = setTimeout(() => { state.q = e.target.value.trim(); state.page = 1; fetchItems(); }, 350);
});

// ── Init (+ deep-link ?sel=ID desde el legajo de una conversación) ─
(async () => {
    await fetchItems();
    const sel = new URLSearchParams(location.search).get('sel');
    if (sel) {
        const id = parseInt(sel);
        if (!state.items.find(c => c.id === id)) {
            try {
                const d = await get(`/contactos/${id}`);
                if (d.contacto) state.items.unshift(d.contacto);
            } catch (e) {}
        }
        abrirFicha(id);
    }
})();
</script>
@endpush
