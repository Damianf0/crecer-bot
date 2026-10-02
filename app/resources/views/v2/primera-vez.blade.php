@extends('layouts.v2')
@section('title', 'Primera vez')

{{-- Pacientes de primera vez: por qué llegan y con qué médico se les da turno.
     Reemplaza la planilla de Google que llevaba recepción. Backend:
     PrimeraVezController. Tres vistas en la misma pantalla: Registros (todo el
     equipo), Reporte y Médicos (supervisión). --}}

@push('styles')
<style>
/* .v2-main no scrollea: cada vista nativa V2 pone su propio contenedor. */
.pv-scroll { flex:1; overflow-y:auto; min-height:0; }
.pv-wrap { max-width:1200px; margin:0 auto; padding:22px 24px 40px; }
.pv-head { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
.pv-head h1 { font-size:20px; font-weight:700; margin:0; flex:1; min-width:160px; }
.pv-card { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius); padding:14px 16px; margin-bottom:16px; }
.pv-card h2 { font-size:13px; font-weight:700; margin:0 0 10px; }
.pv-form { display:grid; grid-template-columns:2fr 1fr 1fr; gap:10px 12px; }
@media (max-width:900px) { .pv-form { grid-template-columns:1fr; } }
.pv-form .full { grid-column:1 / -1; }
.pv-lbl { display:block; font-size:11px; font-weight:600; color:var(--v2-text-2); margin-bottom:4px; }
.pv-lbl .opt { font-weight:400; color:var(--v2-text-mute); }
.pv-in { width:100%; box-sizing:border-box; padding:7px 10px; border:1px solid var(--v2-border); border-radius:var(--v2-radius-sm);
         background:var(--v2-bg-app); color:var(--v2-text); font-size:13px; font-family:inherit; }
.pv-in:focus { outline:none; border-color:var(--v2-accent); }
.pv-motivos { display:flex; gap:6px; flex-wrap:wrap; }
.pv-motivos button { flex:1; min-width:140px; }
.pv-motivos button.on { background:var(--v2-accent-solid); color:#fff; border-color:var(--v2-accent-solid); font-weight:600; }
.pv-foot { display:flex; align-items:center; gap:8px; margin-top:12px; }
.pv-err { color:var(--v2-urg); font-size:12px; flex:1; }
.pv-filtros { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
.pv-filtros .cnt { font-size:12px; color:var(--v2-text-mute); margin-left:auto; }
.pv-sub { font-size:11.5px; color:var(--v2-text-mute); margin-top:2px; }
.pv-acc { white-space:nowrap; text-align:right; }
.pv-acc button { background:none; border:none; color:var(--v2-text-mute); cursor:pointer; font-size:13px; padding:2px 5px; }
.pv-acc button:hover { color:var(--v2-accent); }
.pv-acc button.del:hover { color:var(--v2-urg); }
.pv-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin-bottom:16px; }
.pv-kpi { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius); padding:12px 14px; }
.pv-kpi .l { font-size:11px; font-weight:600; color:var(--v2-text-mute); text-transform:uppercase; letter-spacing:.3px; }
.pv-kpi .v { font-size:24px; font-weight:700; margin-top:2px; }
.pv-kpi .s { font-size:11.5px; color:var(--v2-text-mute); }
.pv-num { text-align:right; font-variant-numeric:tabular-nums; }
.pv-tot { font-weight:700; }
.pv-tabla-wrap { overflow-x:auto; }
.pv-med { display:flex; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid var(--v2-border); font-size:13px; }
.pv-med:last-child { border-bottom:none; }
.pv-med.inactivo .n { text-decoration:line-through; color:var(--v2-text-mute); }
.pv-med .n { flex:1; }
</style>
@endpush

@section('content')
<div class="pv-scroll">
<div class="pv-wrap">

    <div class="pv-head">
        <h1>Pacientes de primera vez</h1>
        <div class="v2-vistas" id="pv-tabs" style="padding:0;"></div>
    </div>

    {{-- ── Registros (todo el equipo) ─────────────────────────── --}}
    <div id="vista-registros">
        <div class="pv-card">
            <h2 id="f-titulo-form">Registrar paciente de primera vez</h2>
            <div class="pv-form">
                <div>
                    <label class="pv-lbl">Nombre y apellido</label>
                    <input class="pv-in" id="f-nombre" maxlength="160" autocomplete="off">
                </div>
                <div>
                    <label class="pv-lbl">Teléfono <span class="opt">(opcional)</span></label>
                    <input class="pv-in" id="f-telefono" maxlength="30" autocomplete="off">
                </div>
                <div>
                    <label class="pv-lbl">DNI <span class="opt">(opcional)</span></label>
                    <input class="pv-in" id="f-dni" maxlength="15" inputmode="numeric" autocomplete="off">
                </div>
                <div class="full">
                    <label class="pv-lbl">¿Cómo llegó?</label>
                    <div class="pv-motivos" id="f-motivos"></div>
                </div>
                <div>
                    <label class="pv-lbl" id="f-deriv-lbl">Quién la deriva o recomienda <span class="opt">(opcional)</span></label>
                    <input class="pv-in" id="f-derivante" maxlength="160" list="pv-derivantes" autocomplete="off"
                           placeholder="Ej: Dra. Pérez, una amiga, Instagram…">
                    <datalist id="pv-derivantes"></datalist>
                </div>
                <div>
                    <label class="pv-lbl">Médico asignado</label>
                    <select class="pv-in" id="f-medico"></select>
                </div>
                <div>
                    <label class="pv-lbl">Fecha</label>
                    <input class="pv-in" id="f-fecha" type="date">
                </div>
                <div class="full">
                    <label class="pv-lbl">Comentario <span class="opt">(opcional)</span></label>
                    <input class="pv-in" id="f-detalle" maxlength="500" autocomplete="off">
                </div>
            </div>
            <div class="pv-foot">
                <span class="pv-err" id="f-err"></span>
                <button class="v2-btn" id="f-cancelar" style="display:none;" onclick="limpiarForm()">Cancelar</button>
                <button class="v2-btn primary" id="f-guardar" onclick="guardar()">Registrar</button>
            </div>
        </div>

        <div class="pv-filtros">
            <input class="pv-in" id="fl-mes" type="month" style="width:170px;" onchange="cargar()">
            <input class="v2-search" id="fl-q" type="search" placeholder="Buscar por nombre, DNI o derivante…"
                   style="max-width:300px;" oninput="cargarDebounced()">
            <span class="cnt" id="fl-cnt"></span>
        </div>
        <div class="pv-tabla-wrap" id="lista"><div class="v2-empty">Cargando…</div></div>
    </div>

    {{-- ── Reporte (supervisión) ──────────────────────────────── --}}
    <div id="vista-reporte" style="display:none;">
        <div class="pv-filtros">
            <label class="pv-lbl" style="margin:0;">Desde</label>
            <input class="pv-in" id="rp-desde" type="month" style="width:170px;">
            <label class="pv-lbl" style="margin:0;">Hasta</label>
            <input class="pv-in" id="rp-hasta" type="month" style="width:170px;">
            <button class="v2-btn primary" onclick="cargarReporte()">Aplicar</button>
        </div>
        <div id="reporte"><div class="v2-empty">Cargando…</div></div>
    </div>

    {{-- ── Médicos (supervisión) ──────────────────────────────── --}}
    <div id="vista-medicos" style="display:none;">
        <div class="pv-card" style="max-width:560px;">
            <h2>Médicos que reciben primeras veces</h2>
            <p class="pv-sub" style="margin:0 0 10px;">Son los que aparecen para elegir al registrar. Sacar a uno de la lista no borra lo que ya tiene cargado.</p>
            <div id="medicos"></div>
            <div style="display:flex;gap:8px;margin-top:12px;">
                <input class="pv-in" id="m-nuevo" maxlength="80" placeholder="Agregar médico (como querés que se vea)" autocomplete="off">
                <button class="v2-btn primary" onclick="agregarMedico()">Agregar</button>
            </div>
        </div>
    </div>

</div>
</div>
@endsection

@push('scripts')
<script>
const { esc } = V2;
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// api local: manda _token también en el body. Hay una PC del equipo desde la
// que el header X-CSRF-TOKEN llega vacío (commit d83b8a5).
async function api(method, url, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF,
                                      'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } };
    if (method !== 'GET') opts.body = JSON.stringify(Object.assign({ _token: CSRF }, body || {}));
    const r = await fetch(url, opts);
    const data = await r.json().catch(() => null);
    if (!r.ok) {
        const primerError = data && data.errors ? Object.values(data.errors)[0][0] : null;
        throw new Error(primerError || (data && (data.error || data.message)) || ('HTTP ' + r.status));
    }
    return data;
}

const hoy = () => { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); };

const state = { tab: 'registros', esAdmin: false, yo: null, motivos: {}, medicos: [], items: [], motivo: 'turno', editando: null };

// ── Pestañas ────────────────────────────────────────────────
function renderTabs() {
    const tabs = [['registros', 'Registros']];
    if (state.esAdmin) tabs.push(['reporte', 'Reporte'], ['medicos', 'Médicos']);
    document.getElementById('pv-tabs').innerHTML = tabs.length < 2 ? '' : tabs.map(([k, l]) =>
        '<button class="v2-vista' + (state.tab === k ? ' active' : '') + '" onclick="irA(\'' + k + '\')">' + l + '</button>').join('');
}

function irA(tab) {
    state.tab = tab;
    ['registros', 'reporte', 'medicos'].forEach(t =>
        document.getElementById('vista-' + t).style.display = t === tab ? '' : 'none');
    renderTabs();
    if (tab === 'reporte') cargarReporte();
    if (tab === 'medicos') renderMedicos();
}

// ── Formulario ──────────────────────────────────────────────
function renderMotivos() {
    document.getElementById('f-motivos').innerHTML = Object.entries(state.motivos).map(([k, l]) =>
        '<button type="button" class="v2-btn' + (state.motivo === k ? ' on' : '') + '" onclick="setMotivo(\'' + k + '\')">' + esc(l) + '</button>').join('');
    // Al elegir "Derivado" lo que importa es saber quién deriva.
    document.getElementById('f-deriv-lbl').innerHTML = state.motivo === 'derivado'
        ? 'Quién la deriva'
        : 'Quién la deriva o recomienda <span class="opt">(opcional)</span>';
}

function setMotivo(k) {
    state.motivo = k;
    renderMotivos();
    if (k === 'derivado') document.getElementById('f-derivante').focus();
}

function renderSelectMedicos(actual) {
    const activos = state.medicos.filter(m => m.activo).map(m => m.nombre);
    // Si se edita un registro de un médico que ya no está en la lista, se conserva.
    if (actual && !activos.includes(actual)) activos.push(actual);
    document.getElementById('f-medico').innerHTML = '<option value="">— Sin asignar —</option>' +
        activos.map(n => '<option value="' + esc(n) + '">' + esc(n) + '</option>').join('');
    document.getElementById('f-medico').value = actual || '';
}

function limpiarForm() {
    state.editando = null;
    state.motivo = 'turno';
    ['nombre', 'telefono', 'dni', 'derivante', 'detalle'].forEach(c => document.getElementById('f-' + c).value = '');
    document.getElementById('f-fecha').value = hoy();
    document.getElementById('f-fecha').max = hoy();
    document.getElementById('f-err').textContent = '';
    document.getElementById('f-titulo-form').textContent = 'Registrar paciente de primera vez';
    document.getElementById('f-guardar').textContent = 'Registrar';
    document.getElementById('f-cancelar').style.display = 'none';
    renderMotivos();
    renderSelectMedicos('');
}

function editar(id) {
    const p = state.items.find(x => x.id === id);
    if (!p) return;
    state.editando = id;
    state.motivo = p.motivo;
    document.getElementById('f-nombre').value    = p.nombre || '';
    document.getElementById('f-telefono').value  = p.telefono || '';
    document.getElementById('f-dni').value       = p.dni || '';
    document.getElementById('f-derivante').value = p.derivante || '';
    document.getElementById('f-detalle').value   = p.detalle || '';
    // Lo importado de la planilla solo tiene el mes: la fecha queda vacía salvo que la completen.
    document.getElementById('f-fecha').value     = p.solo_mes ? '' : p.fecha;
    document.getElementById('f-err').textContent = '';
    document.getElementById('f-titulo-form').textContent = 'Corregir registro';
    document.getElementById('f-guardar').textContent = 'Guardar cambios';
    document.getElementById('f-cancelar').style.display = '';
    renderMotivos();
    renderSelectMedicos(p.medico);
    document.querySelector('.pv-scroll').scrollTo({ top: 0, behavior: 'smooth' });
    document.getElementById('f-nombre').focus();
}

async function guardar() {
    const err = document.getElementById('f-err');
    const body = {
        nombre:    document.getElementById('f-nombre').value.trim(),
        telefono:  document.getElementById('f-telefono').value.trim() || null,
        dni:       document.getElementById('f-dni').value.trim() || null,
        motivo:    state.motivo,
        medico:    document.getElementById('f-medico').value || null,
        derivante: document.getElementById('f-derivante').value.trim() || null,
        detalle:   document.getElementById('f-detalle').value.trim() || null,
        fecha:     document.getElementById('f-fecha').value || null,
    };
    if (!body.nombre) { err.textContent = 'Falta el nombre.'; document.getElementById('f-nombre').focus(); return; }

    const btn = document.getElementById('f-guardar');
    btn.disabled = true;
    try {
        await api('POST', state.editando ? '/primera-vez/' + state.editando : '/primera-vez', body);
    } catch (e) {
        err.textContent = e.message;
        btn.disabled = false;
        return;
    }
    btn.disabled = false;
    v2toast(state.editando ? 'Registro corregido' : 'Primera vez registrada');

    // Mostrar el mes del registro recién cargado, para que se vea en la lista.
    if (body.fecha) document.getElementById('fl-mes').value = body.fecha.slice(0, 7);
    limpiarForm();
    await cargar();
    document.getElementById('f-nombre').focus();
}

// Enter en cualquier campo del formulario registra (se carga con el teléfono en la mano).
document.querySelector('.pv-form').addEventListener('keydown', ev => {
    if (ev.key === 'Enter' && ev.target.tagName === 'INPUT') { ev.preventDefault(); guardar(); }
});

// ── Lista ───────────────────────────────────────────────────
let _seq = 0, _deb = null;
function cargarDebounced() { clearTimeout(_deb); _deb = setTimeout(cargar, 250); }

async function cargar() {
    const seq = ++_seq;
    const params = new URLSearchParams();
    const mes = document.getElementById('fl-mes').value;
    const q   = document.getElementById('fl-q').value.trim();
    if (mes) params.set('mes', mes);
    if (q)   params.set('q', q);

    let r;
    try { r = await api('GET', '/primera-vez/data?' + params.toString()); }
    catch (e) {
        if (seq === _seq) document.getElementById('lista').innerHTML =
            '<div class="v2-empty"><span class="ico">⚠️</span>No se pudieron cargar los registros.</div>';
        return;
    }
    if (seq !== _seq) return;

    const primera = !Object.keys(state.motivos).length;
    state.esAdmin = r.es_admin;
    state.yo      = r.yo;
    state.motivos = r.motivos;
    state.medicos = r.medicos;
    state.items   = r.items;
    document.getElementById('fl-mes').value = r.mes;
    document.getElementById('pv-derivantes').innerHTML = r.derivantes.map(d => '<option value="' + esc(d) + '">').join('');

    if (primera) { renderTabs(); limpiarForm(); }
    renderLista();
}

function renderLista() {
    const cont = document.getElementById('lista');
    document.getElementById('fl-cnt').textContent = state.items.length === 1 ? '1 registro' : state.items.length + ' registros';
    if (!state.items.length) {
        cont.innerHTML = '<div class="v2-empty"><span class="ico">🌱</span>No hay primeras veces registradas en este mes.</div>';
        return;
    }
    const pill = { turno: 'neutral', derivado: 'accent', espontaneo: 'proceso' };
    cont.innerHTML = '<table class="v2-table"><thead><tr>' +
        '<th>Fecha</th><th>Paciente</th><th>Cómo llegó</th><th>Médico</th><th>Deriva / comentario</th><th>Cargó</th><th></th>' +
        '</tr></thead><tbody>' + state.items.map(p => {
            const puede = state.esAdmin || p.por_id === state.yo;
            const sub = [p.dni ? 'DNI ' + esc(p.dni) : '', p.telefono ? esc(p.telefono) : '',
                         p.contacto_id ? 'ya está en Contactos' : ''].filter(Boolean).join(' · ');
            return '<tr>' +
                '<td style="white-space:nowrap;">' + esc(p.fecha_txt) + '</td>' +
                '<td><span style="font-weight:600;">' + esc(p.nombre) + '</span>' +
                    (sub ? '<div class="pv-sub">' + sub + '</div>' : '') + '</td>' +
                '<td><span class="v2-pill ' + (pill[p.motivo] || 'neutral') + '">' + esc(state.motivos[p.motivo] || p.motivo) + '</span></td>' +
                '<td>' + esc(p.medico || '—') + '</td>' +
                '<td>' + esc(p.derivante || '') + (p.detalle ? '<div class="pv-sub">' + esc(p.detalle) + '</div>' : '') + '</td>' +
                '<td class="pv-sub">' + esc(p.por || (p.origen === 'planilla' ? 'Planilla' : '—')) + '</td>' +
                '<td class="pv-acc">' + (puede
                    ? '<button title="Corregir" onclick="editar(' + p.id + ')">✎</button>' +
                      '<button class="del" title="Borrar" onclick="borrar(' + p.id + ')">🗑</button>' : '') + '</td>' +
            '</tr>';
        }).join('') + '</tbody></table>';
}

async function borrar(id) {
    const p = state.items.find(x => x.id === id);
    if (!p || !confirm('¿Borrar el registro de ' + p.nombre + '?')) return;
    try { await api('POST', '/primera-vez/' + id + '/borrar'); }
    catch (e) { v2toast(e.message, 'err'); return; }
    if (state.editando === id) limpiarForm();
    v2toast('Registro borrado');
    cargar();
}

// ── Reporte ─────────────────────────────────────────────────
const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
const mesTxt = ym => MESES[parseInt(ym.slice(5), 10) - 1] + ' ' + ym.slice(0, 4);
const pct = (n, t) => t ? Math.round(n * 100 / t) + ' %' : '—';

async function cargarReporte() {
    const cont = document.getElementById('reporte');
    const params = new URLSearchParams();
    const desde = document.getElementById('rp-desde').value, hasta = document.getElementById('rp-hasta').value;
    if (desde) params.set('desde', desde);
    if (hasta) params.set('hasta', hasta);

    let r;
    try { r = await api('GET', '/primera-vez/reporte?' + params.toString()); }
    catch (e) { cont.innerHTML = '<div class="v2-empty"><span class="ico">⚠️</span>No se pudo armar el reporte.</div>'; return; }

    document.getElementById('rp-desde').value = r.desde;
    document.getElementById('rp-hasta').value = r.hasta;

    if (!r.total) { cont.innerHTML = '<div class="v2-empty"><span class="ico">📊</span>No hay primeras veces en ese período.</div>'; return; }

    const mk = Object.keys(r.motivos);
    let html = '<div class="pv-kpis">' +
        '<div class="pv-kpi"><div class="l">Primeras veces</div><div class="v">' + r.total + '</div><div class="s">' + mesTxt(r.desde) + ' a ' + mesTxt(r.hasta) + '</div></div>' +
        mk.map(k => '<div class="pv-kpi"><div class="l">' + esc(r.motivos[k]) + '</div><div class="v">' + (r.por_motivo[k] || 0) +
            '</div><div class="s">' + pct(r.por_motivo[k] || 0, r.total) + ' del total</div></div>').join('') +
        '<div class="pv-kpi"><div class="l">Vinieron a la clínica</div><div class="v">' +
            (r.asistencia.con_dni ? r.asistencia.vinieron + ' de ' + r.asistencia.con_dni : '—') +
            '</div><div class="s">' + (r.asistencia.con_dni ? pct(r.asistencia.vinieron, r.asistencia.con_dni) + ' de las cargadas con DNI'
                                                            : 'Solo se puede saber de las cargadas con DNI') + '</div></div>' +
        '</div>';

    // Por mes y médico (la primera tabla dinámica de la planilla)
    html += '<div class="pv-card"><h2>Por mes y médico</h2><div class="pv-tabla-wrap"><table class="v2-table"><thead><tr><th>Mes</th>' +
        r.medicos.map(m => '<th class="pv-num">' + esc(m) + '</th>').join('') + '<th class="pv-num">Total</th></tr></thead><tbody>' +
        r.por_mes.map(f => '<tr><td>' + mesTxt(f.mes) + '</td>' +
            r.medicos.map(m => '<td class="pv-num">' + (f.medicos[m] || '') + '</td>').join('') +
            '<td class="pv-num pv-tot">' + f.total + '</td></tr>').join('') +
        '</tbody></table></div></div>';

    // Por médico y motivo (la segunda)
    html += '<div class="pv-card"><h2>Por médico y cómo llegó</h2><div class="pv-tabla-wrap"><table class="v2-table"><thead><tr><th>Médico</th>' +
        mk.map(k => '<th class="pv-num">' + esc(r.motivos[k]) + '</th>').join('') + '<th class="pv-num">Total</th></tr></thead><tbody>' +
        r.por_medico.map(f => '<tr><td>' + esc(f.medico) + '</td>' +
            mk.map(k => '<td class="pv-num">' + (f.motivos[k] || '') + '</td>').join('') +
            '<td class="pv-num pv-tot">' + f.total + '</td></tr>').join('') +
        '</tbody></table></div></div>';

    html += '<div class="pv-card" style="max-width:560px;"><h2>Quiénes derivan o recomiendan</h2>' +
        (r.derivantes.length
            ? '<table class="v2-table"><tbody>' + r.derivantes.map(d =>
                '<tr><td>' + esc(d.derivante) + '</td><td class="pv-num pv-tot">' + d.n + '</td></tr>').join('') + '</tbody></table>'
            : '<div class="pv-sub">Todavía no hay derivantes cargados en este período.</div>') + '</div>';

    cont.innerHTML = html;
}

// ── Médicos ─────────────────────────────────────────────────
function renderMedicos() {
    const cont = document.getElementById('medicos');
    if (!state.medicos.length) { cont.innerHTML = '<div class="pv-sub">Todavía no hay médicos cargados.</div>'; return; }
    cont.innerHTML = state.medicos.map(m =>
        '<div class="pv-med' + (m.activo ? '' : ' inactivo') + '"><span class="n">' + esc(m.nombre) + '</span>' +
        '<button class="v2-btn sm" onclick="renombrarMedico(' + m.id + ')">Cambiar nombre</button>' +
        '<button class="v2-btn sm" onclick="alternarMedico(' + m.id + ')">' + (m.activo ? 'Sacar de la lista' : 'Volver a la lista') + '</button></div>').join('');
}

async function guardarMedico(body) {
    try {
        const r = await api('POST', '/primera-vez/medicos', body);
        state.medicos = r.medicos;
        renderMedicos();
        renderSelectMedicos(document.getElementById('f-medico').value);
        return true;
    } catch (e) { v2toast(e.message, 'err'); return false; }
}

async function agregarMedico() {
    const inp = document.getElementById('m-nuevo');
    const nombre = inp.value.trim();
    if (!nombre) { inp.focus(); return; }
    if (await guardarMedico({ nombre, activo: true })) { inp.value = ''; v2toast('Médico agregado'); }
}

async function alternarMedico(id) {
    const m = state.medicos.find(x => x.id === id);
    if (m) await guardarMedico({ id, nombre: m.nombre, activo: !m.activo });
}

async function renombrarMedico(id) {
    const m = state.medicos.find(x => x.id === id);
    if (!m) return;
    const nombre = (prompt('Nombre del médico (se corrige también en lo ya cargado):', m.nombre) || '').trim();
    if (!nombre || nombre === m.nombre) return;
    if (await guardarMedico({ id, nombre, activo: m.activo })) { v2toast('Nombre actualizado'); cargar(); }
}

document.getElementById('m-nuevo').addEventListener('keydown', ev => { if (ev.key === 'Enter') agregarMedico(); });

// ── Arranque ────────────────────────────────────────────────
document.getElementById('f-fecha').value = hoy();
cargar();
</script>
@endpush
