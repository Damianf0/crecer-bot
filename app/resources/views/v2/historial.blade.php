@extends('layouts.v2')
@section('title', 'Historial')

{{-- PoC V2 — historial de resueltos (conversaciones WA, derivaciones bot y
     tareas) sobre GET /historial (JSON). Las conversaciones se abren read-only
     con el módulo compartido; "Reabrir" las devuelve a la cola. --}}

@section('content')
<div class="v2-inbox" id="inbox">

    <section class="v2-bandeja" style="min-width:0;">
        <div class="v2-bandeja-head">
            <h1>Historial <span class="count" id="b-count"></span></h1>
            <input type="search" class="v2-search" id="b-search" placeholder="Buscar por contacto o título (Enter)">
            <div style="display:flex;gap:6px;margin-top:8px;">
                <input type="date" class="v2-field" id="f-desde" title="Desde" style="margin:0;font-size:12px;">
                <input type="date" class="v2-field" id="f-hasta" title="Hasta" style="margin:0;font-size:12px;">
            </div>
        </div>
        <div class="v2-vistas" id="b-vistas"></div>
        <div class="v2-cards" id="b-cards"></div>
    </section>

    <section class="v2-detalle" id="detalle">
        <div class="v2-det-empty" id="det-empty">
            <span class="ico">🕘</span>
            <span>Todo lo resuelto, en un solo lugar</span>
            <span style="font-size:11.5px;">Las conversaciones se abren en modo lectura — podés reabrirlas si hace falta.</span>
        </div>
        <div id="det-body" style="display:none;flex:1;flex-direction:column;min-height:0;"></div>
    </section>

    <aside class="v2-legajo" id="legajo">
        <h2>Legajo</h2>
        <div id="leg-body" style="color:var(--v2-text-mute);font-size:12.5px;">Sin selección.</div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
const { esc, get, avatarHtml } = V2;

V2Conv.init({
    usuarios: @json($usuarios),
    meId: {{ auth()->id() }},
    onChanged: () => fetchItems(),
});

let state = { items: [], tipo: 'todos', q: '', page: 1, pages: 1, total: 0, sel: null };

const TIPO_PILL = {
    wa:    '<span class="v2-pill proceso">WhatsApp</span>',
    bot:   '<span class="v2-pill espera">Bot</span>',
    tarea: '<span class="v2-pill nueva">Tarea</span>',
};

async function fetchItems() {
    const p = new URLSearchParams({ tipo: state.tipo, q: state.q, page: state.page, per_page: 50 });
    const desde = document.getElementById('f-desde').value;
    const hasta = document.getElementById('f-hasta').value;
    if (desde) p.set('desde', desde);
    if (hasta) p.set('hasta', hasta);
    try {
        const d = await get('/historial?' + p.toString());
        state.items = d.data || [];
        state.pages = d.pages || 1;
        state.total = d.total || 0;
        renderBandeja();
    } catch (e) {}
}

function renderVistas() {
    document.getElementById('b-vistas').innerHTML = [['todos','Todos'],['wa','WhatsApp'],['bot','Bot'],['tarea','Tareas']]
        .map(([k, lbl]) => `<button class="v2-vista ${state.tipo === k ? 'active' : ''}" onclick="setTipo('${k}')">${lbl}</button>`)
        .join('');
    document.getElementById('b-count').textContent = state.total;
}
async function setTipo(t) { state.tipo = t; state.page = 1; await fetchItems(); }

function renderBandeja() {
    renderVistas();
    const cont = document.getElementById('b-cards');
    if (!state.items.length) {
        cont.innerHTML = `<div class="v2-empty"><span class="ico">🗂</span>Nada resuelto con estos filtros.</div>`;
        return;
    }
    cont.innerHTML = state.items.map((i, idx) => {
        const sel = state.sel === idx;
        return `<div class="v2-card ${sel ? 'selected' : ''}" onclick="abrirItem(${idx})">
            <div class="v2-card-l1">${TIPO_PILL[i.tipo] || ''}${i.area_label ? `<span class="tipo">${esc(i.area_label)}</span>` : ''}<span class="ago">${esc(i.resuelto_at || '')}</span></div>
            <div class="v2-card-l2"><span class="nombre">${esc(i.contacto)}</span></div>
            <div class="resumen">${esc(i.resumen || '—')}</div>
            ${i.asig_name ? `<div class="v2-card-foot"><span class="who">● Resolvió ${esc(i.asig_name.split(' ')[0])}</span></div>` : ''}
        </div>`;
    }).join('') + (state.page < state.pages
        ? `<button class="more" onclick="masPagina()">Cargar más (página ${state.page + 1} de ${state.pages})</button>` : '');
}

async function masPagina() {
    state.page++;
    const p = new URLSearchParams({ tipo: state.tipo, q: state.q, page: state.page, per_page: 50 });
    const desde = document.getElementById('f-desde').value, hasta = document.getElementById('f-hasta').value;
    if (desde) p.set('desde', desde);
    if (hasta) p.set('hasta', hasta);
    try {
        const d = await get('/historial?' + p.toString());
        state.items = [...state.items, ...(d.data || [])];
        renderBandeja();
    } catch (e) {}
}

async function abrirItem(idx) {
    state.sel = idx;
    renderBandeja();
    const i = state.items[idx];
    const leg = document.getElementById('leg-body');

    if (i.tipo === 'wa') {
        await V2Conv.abrir(i.id, { readOnly: true });
        return;
    }

    // bot / tarea: ficha estática
    document.getElementById('det-empty').style.display = 'none';
    const body = document.getElementById('det-body');
    body.style.display = 'flex';
    leg.innerHTML = 'Sin legajo para este tipo.';

    if (i.tipo === 'bot') {
        body.innerHTML = `
            <div class="v2-det-head">
                <div class="info"><div class="nombre">${esc(i.contacto)}</div><div class="sub">derivación del bot · resuelta ${esc(i.resuelto_at || '')}</div></div>
                <span class="v2-pill espera">${esc(i.etiqueta || 'Bot')}</span>
            </div>
            <div class="v2-msgs" style="gap:12px;">
                ${i.resumen ? `<div class="v2-resumen" style="margin:0;max-width:560px;"><span class="tag">Resumen IA</span>${esc(i.resumen)}</div>` : ''}
                <div class="v2-bubble" style="max-width:560px;white-space:pre-wrap;">${esc(i.texto || '—')}</div>
                ${i.asig_name ? `<div class="v2-evento">Resuelta por <b>${esc(i.asig_name)}</b></div>` : ''}
            </div>`;
    } else {
        const coms = (i.comentarios || []).map(c => `
            <div class="v2-leg-row" style="border:none;padding:5px 0;">
                <span><b>${esc((c.usuario || '—').split(' ')[0])}</b> · <span style="color:var(--v2-text-2);">${esc(c.contenido)}</span></span>
                <span class="v mono">${esc(c.hora || '')}</span>
            </div>`).join('');
        body.innerHTML = `
            <div class="v2-det-head">
                <div class="info"><div class="nombre">${esc(i.contacto)}</div><div class="sub">tarea completada · ${esc(i.resuelto_at || '')}</div></div>
                <span class="v2-pill nueva">Tarea</span>
            </div>
            <div class="v2-msgs" style="gap:12px;">
                <div class="v2-leg-tabla" style="max-width:560px;">
                    ${i.creado_por ? `<div class="v2-leg-row"><span class="k">Creada por</span><span class="v">${esc(i.creado_por)}</span></div>` : ''}
                    ${i.asig_name ? `<div class="v2-leg-row"><span class="k">Asignada a</span><span class="v">${esc(i.asig_name)}</span></div>` : ''}
                    ${i.prioridad ? `<div class="v2-leg-row"><span class="k">Prioridad</span><span class="v">${esc(i.prioridad)}</span></div>` : ''}
                </div>
                ${i.resumen && i.resumen !== '—' ? `<div class="v2-bubble" style="max-width:560px;white-space:pre-wrap;">${esc(i.resumen)}</div>` : ''}
                ${coms ? `<div style="max-width:560px;"><div class="v2-label" style="margin-bottom:4px;">Comentarios</div>${coms}</div>` : ''}
            </div>`;
    }
}

document.getElementById('b-search').addEventListener('keydown', e => {
    if (e.key === 'Enter') { state.q = e.target.value.trim(); state.page = 1; fetchItems(); }
});
document.getElementById('f-desde').addEventListener('change', () => { state.page = 1; fetchItems(); });
document.getElementById('f-hasta').addEventListener('change', () => { state.page = 1; fetchItems(); });

fetchItems();
</script>
@endpush
