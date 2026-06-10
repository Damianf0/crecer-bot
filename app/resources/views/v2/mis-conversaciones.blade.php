@extends('layouts.v2')
@section('title', 'Mis conversaciones')

{{-- PoC V2 — mismas tres columnas que /v2/atencion pero con la bandeja
     alimentada por /mis-conversaciones/data (lo asignado a mí). --}}

@section('content')
<div class="v2-inbox" id="inbox">

    <section class="v2-bandeja">
        <div class="v2-bandeja-head">
            <h1>Mis conversaciones <span class="count" id="b-count"></span></h1>
            <input type="search" class="v2-search" id="b-search" placeholder="Buscar por nombre o teléfono">
        </div>
        <div class="v2-vistas" id="b-vistas"></div>
        <div class="v2-cards" id="b-cards"></div>
    </section>

    <section class="v2-detalle" id="detalle">
        <div class="v2-det-empty" id="det-empty">
            <span class="ico">👤</span>
            <span>Tus conversaciones asignadas, en todas las áreas</span>
            <span style="font-size:11.5px;">Elegí una de la bandeja para trabajarla.</span>
        </div>
        <div id="det-body" style="display:none;flex:1;flex-direction:column;min-height:0;"></div>
    </section>

    <aside class="v2-legajo" id="legajo">
        <h2>Legajo</h2>
        <div id="leg-body" style="color:var(--v2-text-mute);font-size:12.5px;">Sin conversación seleccionada.</div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
const { esc, get, avatarHtml } = V2;
const AREA_LBL = @json(\App\Models\ConversacionWA::AREAS);

V2Conv.init({
    usuarios: @json($usuarios),
    meId: {{ auth()->id() }},
    onChanged: tipo => {
        // Resolver/delegar la saca de "mis conversaciones" — refrescar y cerrar si corresponde.
        fetchItems().then(() => {
            if (V2Conv.panelId && !state.items.find(i => i.id === V2Conv.panelId)) V2Conv.cerrar();
            renderBandeja();
        });
    },
});

let state = { items: @json($items), vista: 'todas', q: '' };

function filtrados() {
    let list = state.items;
    if (state.vista === 'urgentes')  list = list.filter(i => i.urgente);
    if (state.vista === 'noleidas')  list = list.filter(i => i.no_leidos > 0);
    if (state.q) {
        const q = state.q.toLowerCase();
        list = list.filter(i => (i.contacto || '').toLowerCase().includes(q) || (i.telefono || '').includes(q));
    }
    return list;
}

function renderVistas() {
    const counts = {
        todas:    state.items.length,
        noleidas: state.items.filter(i => i.no_leidos > 0).length,
        urgentes: state.items.filter(i => i.urgente).length,
    };
    document.getElementById('b-vistas').innerHTML = [['todas','Todas'],['noleidas','Con mensajes nuevos'],['urgentes','Urgentes']]
        .map(([k, lbl]) => `<button class="v2-vista ${state.vista === k ? 'active' : ''}" onclick="setVista('${k}')">${lbl}<span class="n">${counts[k]}</span></button>`)
        .join('');
    document.getElementById('b-count').textContent = counts.todas;
}
function setVista(v) { state.vista = v; renderBandeja(); }

function renderBandeja() {
    renderVistas();
    const list = filtrados();
    const cont = document.getElementById('b-cards');
    if (!list.length) {
        cont.innerHTML = `<div class="v2-empty"><span class="ico">🎉</span>No tenés conversaciones asignadas${state.q || state.vista !== 'todas' ? ' con ese filtro' : ''}.</div>`;
        return;
    }
    cont.innerHTML = list.map(i => {
        const sel = V2Conv.panelId === i.id;
        const urg = i.urgente ? `<span class="v2-pill urgente">Urgente</span>` : '';
        const nl  = i.no_leidos > 0 ? `<span class="v2-pill nueva">${i.no_leidos} sin leer</span>` : '';
        return `<div class="v2-card ${i.urgente ? 'urgente' : ''} ${sel ? 'selected' : ''}" onclick="abrirItem(${i.id})">
            <div class="v2-card-l1">${urg}${nl}<span class="tipo">${esc(AREA_LBL[i.area] || i.area || 'WhatsApp')}</span><span class="ago ${i.urgente ? 'urg' : ''}">${esc(i.hace || '')}</span></div>
            <div class="v2-card-l2">${avatarHtml(i.avatar_url, i.contacto, 26)}<span class="nombre">${esc(i.contacto)}</span></div>
            <div class="resumen">${esc(i.resumen || '—')}</div>
        </div>`;
    }).join('');
}

async function abrirItem(id) {
    await V2Conv.abrir(id);
    renderBandeja();
}

document.getElementById('b-search').addEventListener('input', e => {
    state.q = e.target.value.trim();
    renderBandeja();
});

async function fetchItems() {
    try {
        const d = await get('/mis-conversaciones/data');
        state.items = d.data || [];
    } catch (e) {}
}

renderBandeja();
setInterval(async () => { await fetchItems(); renderBandeja(); }, 8000);
setInterval(() => V2Conv.refrescar(), 10000);
</script>
@endpush
