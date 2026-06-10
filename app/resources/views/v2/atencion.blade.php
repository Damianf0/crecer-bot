@extends('layouts.v2')
@section('title', 'Conversaciones · ' . $areaLabel)

{{-- PoC V2 del patrón bandeja | detalle | legajo sobre los endpoints de
     producción (/atencion/{area}/items, /atencion/conversacion/{id}, POSTs de
     tomar/delegar/urgente/resolver/enviar). Solo lectura+acciones existentes:
     no agrega lógica de negocio nueva. --}}

@section('content')
<div class="v2-inbox" id="inbox">

    {{-- ── Bandeja ──────────────────────────────────────────── --}}
    <section class="v2-bandeja">
        <div class="v2-bandeja-head">
            <h1>Conversaciones <span class="count" id="b-count"></span></h1>
            <input type="search" class="v2-search" id="b-search" placeholder="Buscar por nombre o teléfono">
        </div>
        <div class="v2-vistas" id="b-vistas"></div>
        <div class="v2-cards" id="b-cards"></div>
    </section>

    {{-- ── Detalle ──────────────────────────────────────────── --}}
    <section class="v2-detalle" id="detalle">
        <div class="v2-det-empty" id="det-empty">
            <span class="ico">💬</span>
            <span>Elegí una conversación de la bandeja</span>
            <span style="font-size:11.5px;">Las acciones (tomar, responder, resolver) son las mismas que en producción.</span>
        </div>
        <div id="det-body" style="display:none;flex:1;flex-direction:column;min-height:0;"></div>
    </section>

    {{-- ── Legajo ───────────────────────────────────────────── --}}
    <aside class="v2-legajo" id="legajo">
        <h2>Legajo</h2>
        <div id="leg-body" style="color:var(--v2-text-mute);font-size:12.5px;">Sin conversación seleccionada.</div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
const AREA     = @json($area);
const CSRF     = '{{ csrf_token() }}';
const ME_ID    = {{ auth()->id() }};
const ME_NAME  = @json(auth()->user()->nombre_completo);
const USUARIOS = @json($usuarios);

let state = {
    items: [],            // bandeja unificada (nuevas + en proceso)
    vista: 'todas',       // todas | sintomar | urgentes
    q: '',
    panelId: null,
    etag: null,
    conv: null,           // datos de la conversación abierta
};

// ── Utils ─────────────────────────────────────────────────────────
function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
async function api(method, url, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(url, opts);
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
}
const get  = url => api('GET', url);
const post = (url, b) => api('POST', url, b);

function avatarHtml(url, nombre, size) {
    if (url) return `<img class="v2-av" src="${esc(url)}" width="${size}" height="${size}" alt="">`;
    const ini = esc((nombre || '?').trim().charAt(0).toUpperCase());
    return `<span class="v2-av-fb" style="width:${size}px;height:${size}px;font-size:${Math.round(size*0.42)}px;">${ini}</span>`;
}

// ── Bandeja ───────────────────────────────────────────────────────
function normalizar(data) {
    // El endpoint devuelve {nuevas, enProceso}; la bandeja V2 es una sola lista
    // con el estado como pill. Nueva = sin asignar, En proceso = asignada.
    const tag = (arr, estado) => arr.map(i => ({ ...i, _estado: estado }));
    return [...tag(data.nuevas || [], 'nueva'), ...tag(data.enProceso || [], 'proceso')]
        .sort((a, b) => (b.urgente - a.urgente) || (b.ts - a.ts));
}

function filtrados() {
    let list = state.items;
    if (state.vista === 'sintomar')  list = list.filter(i => i._estado === 'nueva');
    if (state.vista === 'urgentes')  list = list.filter(i => i.urgente);
    if (state.q) {
        const q = state.q.toLowerCase();
        list = list.filter(i => (i.contacto || '').toLowerCase().includes(q) || (i.telefono || '').includes(q));
    }
    return list;
}

function renderVistas() {
    const counts = {
        todas:    state.items.length,
        sintomar: state.items.filter(i => i._estado === 'nueva').length,
        urgentes: state.items.filter(i => i.urgente).length,
    };
    const defs = [['todas','Todas'],['sintomar','Sin tomar'],['urgentes','Urgentes']];
    document.getElementById('b-vistas').innerHTML = defs.map(([k, lbl]) =>
        `<button class="v2-vista ${state.vista === k ? 'active' : ''}" onclick="setVista('${k}')">${lbl}<span class="n">${counts[k]}</span></button>`
    ).join('');
    document.getElementById('b-count').textContent = counts.todas;
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
        const sel = state.panelId === i.id;
        const pill = i._estado === 'nueva'
            ? `<span class="v2-pill nueva">Nueva${i.no_leidos > 0 ? ' · ' + i.no_leidos : ''}</span>`
            : `<span class="v2-pill proceso">En proceso</span>`;
        const urg = i.urgente ? `<span class="v2-pill urgente">Urgente</span>` : '';
        const who = i.asig_name
            ? `<span class="who">● ${esc(i.asig_name.split(' ')[0])} la tiene</span>`
            : `<span class="who libre">○ Sin tomar</span>`;
        return `<div class="v2-card ${i.urgente ? 'urgente' : ''} ${sel ? 'selected' : ''}" onclick="abrir(${i.id})">
            <div class="v2-card-l1">${pill}${urg}<span class="tipo">WhatsApp</span><span class="ago ${i.urgente ? 'urg' : ''}">${esc(i.hace || '')}</span></div>
            <div class="v2-card-l2">${avatarHtml(i.avatar_url, i.contacto, 26)}<span class="nombre">${esc(i.contacto)}</span></div>
            <div class="resumen">${esc(i.resumen || '—')}</div>
            <div class="v2-card-foot">${who}</div>
        </div>`;
    }).join('');
}

document.getElementById('b-search').addEventListener('input', e => {
    state.q = e.target.value.trim();
    renderBandeja();
});

// ── Poll de la bandeja (mismo ETag/304 que producción) ───────────
async function pollItems() {
    try {
        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        if (state.etag) headers['If-None-Match'] = state.etag;
        const r = await fetch(`/atencion/${AREA}/items`, { headers });
        if (r.status === 304) return;
        if (r.status === 401 || r.redirected) { location.href = '/login'; return; }
        state.etag = r.headers.get('ETag');
        state.items = normalizar(await r.json());
        renderBandeja();
    } catch (e) {}
}

// ── Detalle ───────────────────────────────────────────────────────
async function abrir(id) {
    state.panelId = id;
    renderBandeja();
    document.getElementById('det-empty').style.display = 'none';
    const body = document.getElementById('det-body');
    body.style.display = 'flex';
    body.innerHTML = `<div class="v2-det-empty"><span>Cargando…</span></div>`;
    try {
        const d = await get(`/atencion/conversacion/${id}`);
        state.conv = d;
        renderDetalle(d);
        renderLegajo(d);
    } catch (e) {
        body.innerHTML = `<div class="v2-det-empty"><span>No se pudo cargar la conversación.</span></div>`;
    }
}

function bubbleContenido(m) {
    let inner = '';
    if (m.tipo === 'imagen' && m.archivo_url) {
        inner += `<img src="${esc(m.archivo_url)}" onclick="window.open('${esc(m.archivo_url)}','_blank')" alt="Imagen">`;
        if (m.contenido) inner += `<div style="margin-top:5px;">${esc(m.contenido)}</div>`;
    } else if (m.tipo === 'audio' && m.archivo_url) {
        inner += `<audio controls src="${esc(m.archivo_url)}"></audio>`;
        if (m.contenido) inner += `<div class="transcripcion">"${esc(m.contenido)}"</div>`;
    } else if ((m.tipo === 'documento' || m.tipo === 'video') && m.archivo_url) {
        inner += `<a class="doc-link" href="${esc(m.archivo_url)}" target="_blank">📄 ${esc(m.contenido || 'Archivo adjunto')}</a>`;
    } else {
        inner += esc(m.contenido || '');
    }
    return inner;
}

function renderMsg(m) {
    if (m.direccion === 'nota_interna') {
        return `<div class="v2-msg" style="justify-content:center;">
            <div class="v2-bubble nota"><span class="nota-tag">NOTA INTERNA · no la ve el paciente</span>${bubbleContenido(m)}</div>
        </div>`;
    }
    const dir  = m.direccion === 'entrante' ? 'in' : 'out';
    const meta = dir === 'out'
        ? `${esc(m.usuario || 'Bot')} · ${esc(m.hora)}`
        : esc(m.hora);
    return `<div class="v2-msg ${dir}"><div style="max-width:70%;min-width:0;">
        <div class="v2-bubble">${bubbleContenido(m)}</div>
        <div class="v2-msg-meta">${meta}</div>
    </div></div>`;
}

const EVENTO_LBL = {
    tomada: 'tomó la conversación', delegada: 'la delegó', resuelta: 'la resolvió',
    reabierta: 'la reabrió', urgente_on: 'la marcó urgente', urgente_off: 'le sacó urgente',
    iniciada: 'inició la conversación', derivada_area: 'la derivó de área', reenviada: 'la reenvió',
};
function renderEvento(e) {
    const lbl = EVENTO_LBL[e.tipo] || e.tipo;
    const dest = e.destino ? ` a <b>${esc(e.destino)}</b>` : '';
    return `<div class="v2-evento"><b>${esc(e.usuario || 'Sistema')}</b> ${lbl}${dest} · ${esc(e.hora)}</div>`;
}

function renderTimeline(d) {
    // Merge cronológico de mensajes + eventos con separadores de fecha.
    const items = [
        ...d.mensajes.map(m => ({ ts: m.ts, fecha: m.fecha, html: renderMsg(m) })),
        ...(d.eventos || []).map(e => ({ ts: e.ts, fecha: (e.fecha || '').split(' ')[0], html: renderEvento(e) })),
    ].sort((a, b) => a.ts - b.ts);

    let html = '', lastFecha = null;
    for (const it of items) {
        if (it.fecha && it.fecha !== lastFecha) {
            html += `<div class="v2-msg-date">${esc(it.fecha)}</div>`;
            lastFecha = it.fecha;
        }
        html += it.html;
    }
    return html || `<div class="v2-empty">Sin mensajes</div>`;
}

function renderDetalle(d) {
    const c = d.conv;
    const esMia = c.asig_id === ME_ID;
    const acciones = [];
    if (!c.asig_id) acciones.push(`<button class="v2-btn primary" onclick="accion('tomar')">Tomar</button>`);
    else if (!esMia) acciones.push(`<button class="v2-btn" onclick="accion('tomar')" title="Asignada a ${esc(c.asig_name || '')}">Tomarla yo</button>`);
    acciones.push(`<button class="v2-btn" onclick="menuDelegar(event)">Delegar ▾</button>`);
    acciones.push(`<button class="v2-btn" onclick="accion('urgente')" title="Marcar / desmarcar urgente">⚑</button>`);
    acciones.push(`<button class="v2-btn accent" onclick="accion('resolver')">Resolver</button>`);

    const asig = c.asig_name
        ? `<span class="v2-pill ${esMia ? 'proceso' : 'espera'}">${esMia ? 'La tenés vos' : esc(c.asig_name.split(' ')[0]) + ' la tiene'}</span>`
        : `<span class="v2-pill nueva">Sin tomar</span>`;

    document.getElementById('det-body').innerHTML = `
        <div class="v2-det-head">
            ${avatarHtml(c.avatar_url, c.contacto, 36)}
            <div class="info">
                <div class="nombre">${esc(c.contacto)}</div>
                <div class="sub">${esc(c.telefono)}</div>
            </div>
            ${asig}
            <div class="acciones">${acciones.join('')}</div>
        </div>
        ${c.resumen ? `<div class="v2-resumen"><span class="tag">Resumen IA</span>${esc(c.resumen)}</div>` : ''}
        <div class="v2-msgs" id="msgs">${renderTimeline(d)}</div>
        <div class="v2-compose">
            <div class="v2-compose-modos">
                <button class="v2-compose-modo active" id="modo-msg" onclick="setModo('mensaje')">Responder</button>
                <button class="v2-compose-modo" id="modo-nota" onclick="setModo('nota')">Nota interna</button>
            </div>
            <div class="v2-compose-row">
                <textarea id="compose" placeholder="Escribí tu respuesta…"></textarea>
                <button class="v2-btn primary" id="btn-enviar" onclick="enviar()">Enviar</button>
            </div>
            <div class="hint">Ctrl+Enter para enviar · la nota interna no le llega al paciente</div>
        </div>`;

    const list = document.getElementById('msgs');
    list.scrollTop = list.scrollHeight;
    document.getElementById('compose').addEventListener('keydown', e => {
        if (e.ctrlKey && e.key === 'Enter') enviar();
    });
}

let modo = 'mensaje';
function setModo(m) {
    modo = m;
    document.getElementById('modo-msg').className  = 'v2-compose-modo' + (m === 'mensaje' ? ' active' : '');
    document.getElementById('modo-nota').className = 'v2-compose-modo' + (m === 'nota' ? ' active nota' : '');
    document.getElementById('compose').placeholder = m === 'nota' ? 'Nota interna (no se envía al paciente)…' : 'Escribí tu respuesta…';
    document.getElementById('btn-enviar').textContent = m === 'nota' ? 'Guardar nota' : 'Enviar';
}

async function enviar() {
    const ta = document.getElementById('compose');
    const texto = ta.value.trim();
    if (!texto || !state.panelId) return;
    const btn = document.getElementById('btn-enviar');
    btn.disabled = true;
    try {
        await post('/atencion/enviar', { conv_id: state.panelId, texto, modo });
        ta.value = '';
        v2toast(modo === 'nota' ? 'Nota guardada' : 'Enviado');
        await refrescarConv();
    } catch (e) {
        v2toast('No se pudo enviar — ¿bot conectado?', 'err');
    } finally {
        btn.disabled = false;
    }
}

async function accion(tipo) {
    if (!state.panelId) return;
    try {
        if (tipo === 'tomar')    await post('/atencion/tomar',    { id: state.panelId, tipo: 'wa' });
        if (tipo === 'urgente')  await post('/atencion/urgente',  { id: state.panelId, tipo: 'wa' });
        if (tipo === 'resolver') {
            await post('/atencion/resolver', { id: state.panelId, tipo: 'wa' });
            v2toast('Resuelta y archivada');
            state.panelId = null; state.conv = null;
            document.getElementById('det-body').style.display = 'none';
            document.getElementById('det-empty').style.display = '';
            document.getElementById('leg-body').innerHTML = 'Sin conversación seleccionada.';
            state.etag = null; await pollItems();
            return;
        }
        v2toast('Listo');
        state.etag = null;
        await Promise.all([refrescarConv(), pollItems()]);
    } catch (e) { v2toast('No se pudo aplicar la acción', 'err'); }
}

function menuDelegar(ev) {
    ev.stopPropagation();
    document.querySelectorAll('.v2-menu').forEach(m => m.remove());
    const menu = document.createElement('div');
    menu.className = 'v2-menu';
    menu.innerHTML = USUARIOS.map(u => `<div class="opt" data-id="${u.id}">${esc(u.nombre_completo)}</div>`).join('');
    document.body.appendChild(menu);
    const r = ev.currentTarget.getBoundingClientRect();
    menu.style.top = (r.bottom + 4) + 'px';
    menu.style.left = Math.min(r.left, innerWidth - 220) + 'px';
    menu.onclick = async e => {
        const id = e.target.dataset.id;
        if (!id) return;
        menu.remove();
        try {
            await post('/atencion/delegar', { id: state.panelId, tipo: 'wa', user_id: parseInt(id) });
            v2toast('Delegada');
            state.etag = null;
            await Promise.all([refrescarConv(), pollItems()]);
        } catch { v2toast('No se pudo delegar', 'err'); }
    };
    setTimeout(() => document.addEventListener('click', () => menu.remove(), { once: true }), 0);
}

// Refresco de la conv abierta preservando lo tipeado en el composer.
async function refrescarConv() {
    if (!state.panelId) return;
    try {
        const d = await get(`/atencion/conversacion/${state.panelId}`);
        const ta = document.getElementById('compose');
        const guard = ta ? { v: ta.value, focus: document.activeElement === ta, s: ta.selectionStart } : null;
        state.conv = d;
        renderDetalle(d);
        renderLegajo(d);
        if (guard) {
            const nta = document.getElementById('compose');
            nta.value = guard.v;
            if (guard.focus) { nta.focus(); nta.setSelectionRange(guard.s, guard.s); }
        }
    } catch (e) {}
}

// ── Legajo ────────────────────────────────────────────────────────
async function renderLegajo(d) {
    const c = d.conv;
    const leg = document.getElementById('leg-body');

    let contacto = null;
    if (c.contacto_id) {
        try { contacto = (await get(`/contactos/${c.contacto_id}`)).contacto; } catch (e) {}
    }

    const rows = [];
    rows.push(['Teléfono', `<span class="mono">${esc(c.telefono || '—')}</span>`]);
    if (contacto) {
        if (contacto.dni)   rows.push(['DNI', `<span class="mono">${esc(contacto.dni)}</span>`]);
        if (contacto.email) rows.push(['Email', esc(contacto.email)]);
        if (contacto.fecha_nacimiento) rows.push(['Nacimiento', esc(contacto.fecha_nacimiento.split('T')[0])]);
        if (contacto.notas) rows.push(['Notas', esc(contacto.notas)]);
    }

    const eventos = (d.eventos || []).slice(-8).reverse();

    leg.innerHTML = `
        <div class="v2-leg-id">
            ${avatarHtml(c.avatar_url, c.contacto, 38)}
            <div style="min-width:0;">
                <div class="nombre">${esc(c.contacto)}</div>
                ${c.es_huerfana ? '<div class="dni" style="color:var(--v2-warn);">No está en contactos</div>' : (contacto?.dni ? `<div class="dni">DNI ${esc(contacto.dni)}</div>` : '')}
            </div>
        </div>
        <div class="v2-leg-tabla">${rows.map(([k, v]) => `<div class="v2-leg-row"><span class="k">${k}</span><span class="v">${v}</span></div>`).join('')}</div>
        ${c.resumen ? `<div class="v2-leg-tabla" style="padding:9px 11px;font-size:12px;line-height:1.5;"><span style="font-size:10px;font-weight:700;color:var(--v2-accent);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:3px;">Lo de esta conversación</span>${esc(c.resumen)}</div>` : ''}
        <button class="v2-leg-acc" onclick="toggleAcc(this)">
            <span>Actividad</span><span class="n">${(d.eventos || []).length}</span><span class="chev">›</span>
        </button>
        <div class="v2-leg-panel" style="display:none;">
            ${eventos.length ? eventos.map(e => `<div class="ev"><span><b>${esc((e.usuario || 'Sistema').split(' ')[0])}</b> ${esc(EVENTO_LBL[e.tipo] || e.tipo)}</span><span class="t">${esc(e.hace || e.fecha)}</span></div>`).join('') : '<div style="padding:6px 0;">Sin actividad registrada.</div>'}
        </div>
        ${c.contacto_id ? `
            <a class="v2-leg-link" href="/pacientes/${c.contacto_id}/documentos" title="Abre en la UI actual">📄 Documentos del paciente ↗</a>
            <a class="v2-leg-link" href="/contactos" title="Abre en la UI actual">📇 Ficha completa en Contactos ↗</a>` : `
            <div style="font-size:11.5px;color:var(--v2-text-mute);padding:6px 2px;">Para ver legajo completo, agregalo a contactos desde la UI actual.</div>`}
    `;
}
function toggleAcc(btn) {
    btn.classList.toggle('open');
    const panel = btn.nextElementSibling;
    panel.style.display = panel.style.display === 'none' ? '' : 'none';
}

// ── Init ──────────────────────────────────────────────────────────
state.items = normalizar(@json($itemsData));
renderBandeja();
setInterval(pollItems, 8000);
setInterval(refrescarConv, 10000);
</script>
@endpush
