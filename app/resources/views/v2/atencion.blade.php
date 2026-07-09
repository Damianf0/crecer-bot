@extends('layouts.v2')
@section('title', 'Conversaciones · ' . $areaLabel)

{{-- PoC V2 — bandeja | detalle | legajo sobre los endpoints de producción.
     El detalle/legajo/acciones viven en public/js/crecer-v2.js (V2Conv). --}}

@section('content')
<div class="v2-inbox" id="inbox">

    <section class="v2-bandeja">
        <div class="v2-bandeja-head">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <h1>Conversaciones <span class="count" id="b-count"></span></h1>
                <button class="v2-btn primary" onclick="V2Conv.modalNueva()" title="Iniciar una conversación de WhatsApp con un contacto o número nuevo">+ Nueva</button>
            </div>
            <input type="search" class="v2-search" id="b-search" placeholder="Buscar por nombre o teléfono">
        </div>
        <div class="v2-vistas" id="b-vistas"></div>
        <div class="v2-cards" id="b-cards"></div>
    </section>

    <section class="v2-detalle" id="detalle">
        <div class="v2-det-empty" id="det-empty">
            <span class="ico">💬</span>
            <span>Elegí una conversación de la bandeja</span>
            <span style="font-size:11.5px;">Las acciones (tomar, responder, resolver) son las mismas que en producción.</span>
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
const AREA = @json($area);
const { esc, avatarHtml } = V2;

V2Conv.init({
    usuarios: @json($usuarios),
    meId: {{ auth()->id() }},
    area: AREA,
    onChanged: () => { state.etag = null; pollItems(); },
});

let state = { items: [], vista: 'espera', q: '', etag: null };

function normalizar(data) {
    const tag = (arr, estado) => arr.map(i => ({ ...i, _estado: estado }));
    return [...tag(data.nuevas || [], 'nueva'), ...tag(data.enProceso || [], 'proceso')]
        .sort((a, b) => (b.urgente - a.urgente) || (b.ts - a.ts));
}

function filtrados() {
    let list = state.items;
    if (state.vista === 'espera')   list = list.filter(i => i._estado === 'nueva');
    if (state.vista === 'proceso')  list = list.filter(i => i._estado === 'proceso');
    if (state.vista === 'urgentes') list = list.filter(i => i.urgente);
    if (state.q) {
        const q = state.q.toLowerCase();
        list = list.filter(i => (i.contacto || '').toLowerCase().includes(q) || (i.telefono || '').includes(q));
    }
    return list;
}

function renderVistas() {
    const counts = {
        espera:   state.items.filter(i => i._estado === 'nueva').length,
        proceso:  state.items.filter(i => i._estado === 'proceso').length,
        urgentes: state.items.filter(i => i.urgente).length,
    };
    document.getElementById('b-vistas').innerHTML = [['espera','En espera'],['proceso','En proceso'],['urgentes','Urgentes']]
        .map(([k, lbl]) => `<button class="v2-vista ${state.vista === k ? 'active' : ''}" onclick="setVista('${k}')">${lbl}<span class="n">${counts[k]}</span></button>`)
        .join('');
    document.getElementById('b-count').textContent = state.items.length;
}
function setVista(v) { state.vista = v; renderBandeja(); }

function renderBandeja() {
    renderVistas();
    const list = filtrados();
    const cont = document.getElementById('b-cards');
    if (!list.length) {
        cont.innerHTML = `<div class="v2-empty"><span class="ico">📭</span>Nada por acá${state.q ? ' con esa búsqueda' : ''}.</div>`;
        return;
    }
    cont.innerHTML = list.map(i => {
        const sel = V2Conv.panelId === i.id;
        const pill = i._estado === 'nueva'
            ? `<span class="v2-pill nueva">En espera${i.no_leidos > 0 ? ' · ' + i.no_leidos : ''}</span>`
            : `<span class="v2-pill proceso">En proceso</span>`;
        const urg = i.urgente ? `<span class="v2-pill urgente">Urgente</span>` : '';
        const who = i.asig_name
            ? `<span class="who">● ${esc(i.asig_name.split(' ')[0])} la tiene</span>`
            : `<span class="who libre">○ Sin tomar</span>`;
        return `<div class="v2-card ${i.urgente ? 'urgente' : ''} ${sel ? 'selected' : ''}" onclick="abrirItem(${i.id})">
            <div class="v2-card-l1">${pill}${urg}<span class="tipo">WhatsApp</span><span class="ago ${i.urgente ? 'urg' : ''}">${esc(i.hace || '')}</span></div>
            <div class="v2-card-l2">${avatarHtml(i.avatar_url, i.contacto, 26)}<span class="nombre">${esc(i.contacto)}</span></div>
            <div class="resumen">${esc(i.resumen || '—')}</div>
            <div class="v2-card-foot">${who}</div>
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

async function pollItems() {
    try {
        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        if (state.etag) headers['If-None-Match'] = state.etag;
        const r = await fetch(`/atencion/${AREA}/items`, { headers });
        if (r.status === 304) return;
        if (r.status === 401 || r.redirected) { location.href = '/login'; return; }
        state.etag = r.headers.get('ETag');
        state.items = normalizar(await r.json());
        detectarNotifs(state.items);
        renderBandeja();
    } catch (e) {}
}

// ── Notificaciones browser (paridad V1): urgente nueva + delegada a mí ──
// La primera pasada solo puebla los sets (evita la ráfaga al abrir la página).
const ME_ID = {{ auth()->id() }};
const _notifUrg = new Set();
const _misConvs = new Set();
let _notifPrimera = true;

function detectarNotifs(items) {
    const urgentes = items.filter(i => i._estado === 'nueva' && i.urgente);
    const mias     = items.filter(i => parseInt(i.asig_id) === ME_ID);

    if (_notifPrimera) {
        urgentes.forEach(i => _notifUrg.add(i.id));
        mias.forEach(i => _misConvs.add(i.id));
        _notifPrimera = false;
        return;
    }

    urgentes.forEach(i => {
        if (_notifUrg.has(i.id)) return;
        _notifUrg.add(i.id);
        sonarPing();
        window.Notify?.disparar({
            titulo: 'Crecer — mensaje urgente',
            cuerpo: `${i.contacto}: ${(i.resumen || '').slice(0, 80)}`,
            tag: `urg-${i.id}`,
        });
    });

    mias.forEach(i => {
        if (_misConvs.has(i.id)) return;
        _misConvs.add(i.id);
        // Si es la conv abierta en mi panel, la acabo de tomar yo: no avisar.
        if (V2Conv.panelId === i.id) return;
        window.Notify?.disparar({
            titulo: 'Te delegaron una conversación',
            cuerpo: `${i.contacto}: ${(i.resumen || '').slice(0, 80)}`,
            tag: `deleg-${i.id}`,
        });
    });

    // Limpiar las que dejaron de ser mías (resueltas / delegadas a otro) para
    // que una re-delegación futura vuelva a avisar; cap del set de urgentes.
    const clavesMias = new Set(mias.map(i => i.id));
    for (const k of Array.from(_misConvs)) if (!clavesMias.has(k)) _misConvs.delete(k);
    if (_notifUrg.size > 500) {
        const keep = Array.from(_notifUrg).slice(-300);
        _notifUrg.clear();
        keep.forEach(k => _notifUrg.add(k));
    }
}

// Tono de aviso corto generado con WebAudio (sin assets), igual que V1.
function sonarPing() {
    try {
        const ctx = window._audioCtx ||= new (window.AudioContext || window.webkitAudioContext)();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.frequency.value = 880;
        g.gain.value = 0.0001;
        o.connect(g); g.connect(ctx.destination);
        o.start();
        g.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.04);
        g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.45);
        o.stop(ctx.currentTime + 0.5);
    } catch {}
}

state.items = normalizar(@json($itemsData));
detectarNotifs(state.items);
renderBandeja();
setInterval(pollItems, 8000);
setInterval(() => V2Conv.refrescar(), 10000);
</script>
@endpush
