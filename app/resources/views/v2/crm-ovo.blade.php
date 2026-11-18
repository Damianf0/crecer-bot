@extends('layouts.v2')
@section('title', 'Donantes y receptoras')

{{-- CRM de ovodonación (reemplaza Zoho Bigin). Backend: OvoCrmController.
     Tableros y etapas iguales a los de Bigin (OvoFicha::PROCESOS); las fichas
     se mueven arrastrando la tarjeta o desde la ficha. --}}

@push('styles')
<style>
.oc-page { flex:1; display:flex; flex-direction:column; min-height:0; }
.oc-head { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:16px 20px 10px; }
.oc-head h1 { font-size:19px; font-weight:700; margin:0; }
.oc-head .sp { flex:1; }
.oc-tabs .v2-vista .n { display:inline-block; min-width:16px; padding:0 5px; margin-left:5px; border-radius:9px; font-size:10.5px;
                        background:var(--v2-urg-bg); color:var(--v2-urg); font-weight:700; }
.oc-board { flex:1; min-height:0; display:flex; gap:10px; overflow-x:auto; padding:4px 20px 20px; align-items:flex-start; }
.oc-col { flex:0 0 250px; background:var(--v2-bg-hover); border-radius:var(--v2-radius); display:flex; flex-direction:column; max-height:100%; }
.oc-col.terminal { flex-basis:210px; opacity:.92; }
.oc-col.drop { outline:2px dashed var(--v2-accent); outline-offset:-2px; }
.oc-col-h { padding:9px 11px 7px; font-size:12px; font-weight:700; display:flex; gap:6px; align-items:center; }
.oc-col-h .c { margin-left:auto; font-weight:600; color:var(--v2-text-mute); }
.oc-col-b { overflow-y:auto; padding:0 7px 8px; display:flex; flex-direction:column; gap:6px; min-height:40px; }
.oc-col-more { font-size:11px; color:var(--v2-text-mute); text-align:center; padding:4px; }
.oc-card { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius-sm); padding:8px 9px; cursor:pointer; font-size:12.5px; }
.oc-card:hover { border-color:var(--v2-border-strong); }
.oc-card.dragging { opacity:.4; }
.oc-card .n { font-weight:650; }
.oc-card .s { font-size:11px; color:var(--v2-text-mute); margin-top:2px; }
.oc-card .av { display:flex; flex-wrap:wrap; gap:3px; margin-top:5px; }
.oc-card .v2-pill { font-size:10.5px; }
.oc-plegar { background:none; border:none; cursor:pointer; color:var(--v2-text-mute); font-size:11px; padding:0; }

dialog.oc-dlg { width:min(860px, calc(100vw - 32px)); max-height:calc(100vh - 40px); padding:0; }
dialog.oc-dlg.chico { width:min(440px, calc(100vw - 32px)); }
.oc-dlg-h { display:flex; align-items:center; gap:8px; padding:14px 18px; border-bottom:1px solid var(--v2-border); position:sticky; top:0; background:var(--v2-bg-card); z-index:1; }
.oc-dlg-h h3 { margin:0; flex:1; font-size:15px; }
.oc-dlg-b { padding:6px 18px 16px; }
.oc-dlg-f { display:flex; gap:8px; align-items:center; padding:12px 18px; border-top:1px solid var(--v2-border); position:sticky; bottom:0; background:var(--v2-bg-card); }
.oc-dlg-f .err { color:var(--v2-urg); font-size:12px; flex:1; }
.oc-sec { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--v2-text-mute); margin:16px 0 6px; }
.oc-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:8px 12px; }
.oc-grid .w2 { grid-column:span 2; } .oc-grid .w3 { grid-column:1 / -1; }
@media (max-width:700px) { .oc-grid { grid-template-columns:1fr; } .oc-grid .w2 { grid-column:auto; } }
.oc-lbl { display:block; font-size:11px; font-weight:600; color:var(--v2-text-2); margin-bottom:3px; }
.oc-in { width:100%; box-sizing:border-box; padding:6px 9px; border:1px solid var(--v2-border); border-radius:var(--v2-radius-sm);
         background:var(--v2-bg-app); color:var(--v2-text); font-size:13px; font-family:inherit; }
.oc-in:focus { outline:none; border-color:var(--v2-accent); }
textarea.oc-in { resize:vertical; min-height:54px; }
.oc-req { display:flex; gap:8px; align-items:baseline; font-size:12.5px; padding:3px 0; }
.oc-req .i { width:18px; text-align:center; }
.oc-req .d { color:var(--v2-text-mute); }
.oc-dos { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
@media (max-width:700px) { .oc-dos { grid-template-columns:1fr; } }
.oc-item { font-size:12.5px; padding:6px 0; border-bottom:1px solid var(--v2-border); }
.oc-item:last-child { border-bottom:none; }
.oc-item .m { font-size:11px; color:var(--v2-text-mute); }
.oc-item .t { white-space:pre-wrap; }
.oc-mover { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; background:var(--v2-bg-hover); border-radius:var(--v2-radius-sm); padding:10px; }
.oc-mover > div { flex:1; min-width:150px; }
</style>
@endpush

@section('content')
<div class="oc-page">
    <div class="oc-head">
        <h1>Donantes y receptoras</h1>
        <div class="v2-vistas oc-tabs" id="oc-tabs" style="padding:0;"></div>
        <span class="sp"></span>
        <input class="v2-search" id="oc-q" type="search" placeholder="Buscar nombre, DNI, teléfono o código…" style="max-width:280px;" oninput="buscarDeb()">
        <button class="v2-btn primary" onclick="nuevaFicha()">+ Nueva ficha</button>
    </div>
    <div class="oc-board" id="oc-board"><div class="v2-empty">Cargando…</div></div>
</div>

{{-- Ficha (alta y edición) --}}
<dialog class="v2-dialog oc-dlg" id="dlg-ficha">
    <div class="oc-dlg-h"><h3 id="fi-titulo">Ficha</h3><span id="fi-etapa"></span>
        <button class="v2-btn sm" onclick="cerrar('dlg-ficha')">✕</button></div>
    <div class="oc-dlg-b" id="fi-cuerpo"></div>
    <div class="oc-dlg-f">
        <span class="err" id="fi-err"></span>
        <button class="v2-btn" id="fi-borrar" style="display:none;" onclick="borrarFicha()">Borrar</button>
        <button class="v2-btn" onclick="cerrar('dlg-ficha')">Cerrar</button>
        <button class="v2-btn primary" id="fi-guardar" onclick="guardarFicha()">Guardar</button>
    </div>
</dialog>

{{-- Mover de etapa: pide lo que la etapa necesita (motivo, turno, punción) --}}
<dialog class="v2-dialog oc-dlg chico" id="dlg-mover">
    <div class="oc-dlg-h"><h3 id="mv-titulo">Mover</h3></div>
    <div class="oc-dlg-b" id="mv-cuerpo"></div>
    <div class="oc-dlg-f">
        <span class="err" id="mv-err"></span>
        <button class="v2-btn" onclick="cerrar('dlg-mover')">Cancelar</button>
        <button class="v2-btn primary" onclick="confirmarMover()">Mover</button>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
const { esc } = V2;
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// _token también en el body: hay una PC desde la que el header llega vacío (d83b8a5).
async function api(method, url, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF,
                                      'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } };
    if (method !== 'GET') opts.body = JSON.stringify(Object.assign({ _token: CSRF }, body || {}));
    const r = await fetch(url, opts);
    const data = await r.json().catch(() => null);
    if (!r.ok || (data && data.ok === false)) {
        const primerError = data && data.errors ? Object.values(data.errors)[0][0] : null;
        const e = new Error(primerError || (data && (data.error || data.message)) || ('HTTP ' + r.status));
        e.data = data;
        throw e;
    }
    return data;
}

const S = { proceso: 'donantes', procesos: null, columnas: [], alertas: {}, conTurno: [], responsables: [],
            ficha: null, plegadas: {}, mover: null };
try { S.proceso = localStorage.getItem('crmovo.proceso') || 'donantes'; } catch (_) {}

const fecha = s => s ? s.slice(8, 10) + '/' + s.slice(5, 7) + (s.length > 10 ? ' ' + s.slice(11, 16) : '') : '';
function cerrar(id) { document.getElementById(id).close(); }

// ── Tablero ─────────────────────────────────────────────────
let _seq = 0, _deb = null;
function buscarDeb() { clearTimeout(_deb); _deb = setTimeout(cargar, 250); }

async function cargar() {
    const seq = ++_seq;
    const p = new URLSearchParams({ proceso: S.proceso });
    const q = document.getElementById('oc-q').value.trim();
    if (q) p.set('q', q);
    let r;
    try { r = await api('GET', '/crm-ovo/tablero?' + p); }
    catch (e) {
        if (seq === _seq) document.getElementById('oc-board').innerHTML = '<div class="v2-empty"><span class="ico">⚠️</span>No se pudo cargar el tablero.</div>';
        return;
    }
    if (seq !== _seq) return;
    Object.assign(S, { proceso: r.proceso, procesos: r.procesos, columnas: r.columnas, alertas: r.alertas,
                       conTurno: r.etapas_con_turno, responsables: r.responsables });
    renderTabs();
    renderBoard();
}

function renderTabs() {
    document.getElementById('oc-tabs').innerHTML = Object.entries(S.procesos).map(([k, d]) =>
        '<button class="v2-vista' + (S.proceso === k ? ' active' : '') + '" onclick="irA(\'' + k + '\')">' + esc(d.label) +
        (S.alertas[k] ? '<span class="n" title="Fichas con avisos">' + S.alertas[k] + '</span>' : '') + '</button>').join('');
}

function irA(p) {
    S.proceso = p;
    try { localStorage.setItem('crmovo.proceso', p); } catch (_) {}
    cargar();
}

function renderBoard() {
    const b = document.getElementById('oc-board');
    b.innerHTML = S.columnas.map(c => {
        const plegada = c.terminal && !S.plegadas[S.proceso + c.etapa];
        const cards = plegada ? '' : c.fichas.map(tarjeta).join('') +
            (c.total > c.fichas.length ? '<div class="oc-col-more">y ' + (c.total - c.fichas.length) + ' más (usá el buscador)</div>' : '');
        return '<div class="oc-col' + (c.terminal ? ' terminal' : '') + '" data-etapa="' + c.etapa + '">' +
            '<div class="oc-col-h">' + esc(c.label) + '<span class="c">' + c.total + '</span>' +
            (c.terminal ? ' <button class="oc-plegar" onclick="plegar(\'' + c.etapa + '\')">' + (plegada ? 'ver' : 'ocultar') + '</button>' : '') +
            '</div><div class="oc-col-b">' + cards + '</div></div>';
    }).join('');

    b.querySelectorAll('.oc-card').forEach(el => {
        el.addEventListener('dragstart', ev => { ev.dataTransfer.setData('text/plain', el.dataset.id); el.classList.add('dragging'); });
        el.addEventListener('dragend', () => el.classList.remove('dragging'));
    });
    b.querySelectorAll('.oc-col').forEach(col => {
        col.addEventListener('dragover', ev => { ev.preventDefault(); col.classList.add('drop'); });
        col.addEventListener('dragleave', () => col.classList.remove('drop'));
        col.addEventListener('drop', ev => {
            ev.preventDefault(); col.classList.remove('drop');
            const id = parseInt(ev.dataTransfer.getData('text/plain'), 10);
            const f = buscarTarjeta(id);
            if (f && f.etapa !== col.dataset.etapa) pedirMover(f, S.proceso, col.dataset.etapa);
        });
    });
}

function plegar(etapa) { S.plegadas[S.proceso + etapa] = !S.plegadas[S.proceso + etapa]; renderBoard(); }
function buscarTarjeta(id) { for (const c of S.columnas) { const f = c.fichas.find(x => x.id === id); if (f) return f; } return null; }

function tarjeta(f) {
    const sub = [f.edad ? f.edad + ' años' : '', f.codigo_donante ? 'Cód. ' + esc(f.codigo_donante) : '',
                 f.dias_en_etapa !== null ? f.dias_en_etapa + ' d en la etapa' : ''].filter(Boolean).join(' · ');
    const extra = [f.proximo_turno ? '📅 ' + fecha(f.proximo_turno) : '', f.fecha_puncion ? '💉 ' + fecha(f.fecha_puncion) : ''].filter(Boolean).join('  ');
    return '<div class="oc-card" draggable="true" data-id="' + f.id + '" onclick="abrirFicha(' + f.id + ')">' +
        '<div class="n">' + esc(f.nombre) + '</div>' +
        (sub ? '<div class="s">' + sub + '</div>' : '') +
        (extra ? '<div class="s">' + extra + '</div>' : '') +
        (f.motivo ? '<div class="s">' + esc(f.motivo) + '</div>' : '') +
        (f.avisos.length ? '<div class="av">' + f.avisos.map(a => '<span class="v2-pill ' + a.nivel + '">' + esc(a.texto) + '</span>').join('') + '</div>' : '') +
        '</div>';
}

// ── Mover ───────────────────────────────────────────────────
function pedirMover(f, proceso, etapa) {
    const pr = S.procesos[proceso];
    const necesitaMotivo = etapa === 'cancelado' || etapa === 'no_cumple';
    const pideTurno = S.conTurno.includes(etapa);
    const pidePuncion = etapa === 'puncion';
    const pideRecontacto = etapa === 'esperar' || (proceso === 'tatuaje' && etapa === 'esperando');
    S.mover = { id: f.id, proceso, etapa };
    // Movidas sin datos extra: directo, sin diálogo.
    if (!necesitaMotivo && !pideTurno && !pidePuncion && !pideRecontacto && etapa !== 'vuelve') return confirmarMover();

    document.getElementById('mv-titulo').textContent = f.nombre + ' → ' + pr.etapas[etapa];
    document.getElementById('mv-err').textContent = '';
    let h = '';
    if (etapa === 'vuelve') h += '<p class="pv-sub" style="margin:8px 0;">La ficha vuelve sola a <b>Seguimiento donantes › Primer contacto</b>.</p>';
    if (pideTurno) h += '<label class="oc-lbl" style="margin-top:10px;">Próximo turno <span style="font-weight:400;">(si ya lo tiene)</span></label><input class="oc-in" id="mv-turno" type="datetime-local">';
    if (pidePuncion) h += '<label class="oc-lbl" style="margin-top:10px;">Fecha de punción</label><input class="oc-in" id="mv-puncion" type="date">';
    if (pideRecontacto) h += '<label class="oc-lbl" style="margin-top:10px;">Recontactar desde <span style="font-weight:400;">(opcional)</span></label><input class="oc-in" id="mv-recontacto" type="date">';
    h += '<label class="oc-lbl" style="margin-top:10px;">' + (necesitaMotivo ? 'Motivo' : 'Comentario <span style="font-weight:400;">(opcional)</span>') + '</label>' +
         '<input class="oc-in" id="mv-motivo" maxlength="255" autocomplete="off">';
    document.getElementById('mv-cuerpo').innerHTML = h;
    document.getElementById('dlg-mover').showModal();
    (document.getElementById('mv-turno') || document.getElementById('mv-puncion') || document.getElementById('mv-motivo')).focus();
}

async function confirmarMover() {
    const m = S.mover; if (!m) return;
    const val = id => { const el = document.getElementById(id); return el && document.getElementById('dlg-mover').open ? (el.value || null) : null; };
    const body = { proceso: m.proceso, etapa: m.etapa, motivo: val('mv-motivo'), proximo_turno: val('mv-turno'),
                   fecha_puncion: val('mv-puncion'), recontactar_desde: val('mv-recontacto') };
    try { await api('POST', '/crm-ovo/ficha/' + m.id + '/mover', body); }
    catch (e) {
        if (document.getElementById('dlg-mover').open) document.getElementById('mv-err').textContent = e.message;
        else v2toast(e.message, 'err');
        return;
    }
    if (document.getElementById('dlg-mover').open) cerrar('dlg-mover');
    S.mover = null;
    await cargar();
    if (S.ficha && S.ficha.id === m.id && document.getElementById('dlg-ficha').open) abrirFicha(m.id);
}

// ── Ficha ───────────────────────────────────────────────────
function nuevaFicha() {
    const tipo = S.procesos[S.proceso].tipo;
    S.ficha = { id: null, tipo, proceso: S.proceso, notas: [], movimientos: [], requisitos: [], avisos: [] };
    renderFicha();
    document.getElementById('dlg-ficha').showModal();
    document.getElementById('f-nombre').focus();
}

async function abrirFicha(id) {
    let r;
    try { r = await api('GET', '/crm-ovo/ficha/' + id); } catch (e) { v2toast(e.message, 'err'); return; }
    S.ficha = r.ficha;
    renderFicha();
    if (!document.getElementById('dlg-ficha').open) document.getElementById('dlg-ficha').showModal();
}

const campo = (k, label, tipo = 'text', cls = '', attrs = '') => {
    const v = S.ficha[k] ?? '';
    const input = tipo === 'textarea'
        ? '<textarea class="oc-in" id="f-' + k + '" ' + attrs + '>' + esc(v) + '</textarea>'
        : '<input class="oc-in" id="f-' + k + '" type="' + tipo + '" value="' + esc(String(v)) + '" ' + attrs + '>';
    return '<div class="' + cls + '"><label class="oc-lbl">' + label + '</label>' + input + '</div>';
};
const siNo = (k, label) => {
    const v = S.ficha[k];
    const sel = x => (x === v ? ' selected' : '');
    return '<div><label class="oc-lbl">' + label + '</label><select class="oc-in" id="f-' + k + '">' +
        '<option value=""' + (v === null || v === undefined ? ' selected' : '') + '>—</option>' +
        '<option value="1"' + sel(true) + '>Sí</option><option value="0"' + sel(false) + '>No</option></select></div>';
};

function renderFicha() {
    const f = S.ficha, nueva = !f.id, don = f.tipo === 'donante';
    const pr = S.procesos[f.proceso];
    document.getElementById('fi-titulo').textContent = nueva ? (don ? 'Nueva donante' : 'Nueva receptora') : f.nombre_completo;
    document.getElementById('fi-etapa').innerHTML = nueva ? '' :
        '<span class="v2-pill accent">' + esc(pr.label + ' › ' + pr.etapas[f.etapa]) + '</span>';
    document.getElementById('fi-err').textContent = '';
    document.getElementById('fi-borrar').style.display = nueva ? 'none' : '';
    document.getElementById('fi-guardar').textContent = nueva ? 'Crear ficha' : 'Guardar';

    let h = '';
    if (!nueva && f.avisos.length) h += '<div class="av" style="display:flex;gap:4px;flex-wrap:wrap;margin-top:10px;">' +
        f.avisos.map(a => '<span class="v2-pill ' + a.nivel + '">' + esc(a.texto) + '</span>').join('') + '</div>';

    if (!nueva) {
        const opts = Object.entries(S.procesos).filter(([, d]) => d.tipo === f.tipo).map(([k, d]) =>
            '<optgroup label="' + esc(d.label) + '">' + Object.entries(d.etapas).map(([e, l]) =>
                '<option value="' + k + '|' + e + '"' + (k === f.proceso && e === f.etapa ? ' selected' : '') + '>' + esc(l) + '</option>').join('') + '</optgroup>').join('');
        h += '<div class="oc-sec">Etapa</div><div class="oc-mover"><div><label class="oc-lbl">Mover a</label><select class="oc-in" id="f-destino">' + opts + '</select></div>' +
             '<button class="v2-btn" onclick="moverDesdeFicha()">Mover</button>' +
             (f.proceso === 'donantes' && f.etapa === 'completada' ? '<button class="v2-btn" onclick="pedirMover(S.ficha, \'ya_donaron\', \'esperar\')">Pasar a Ya donaron</button>' : '') +
             '</div><div class="pv-sub" style="font-size:11px;color:var(--v2-text-mute);margin-top:4px;">En esta etapa desde el ' + esc(f.etapa_desde || '') + '</div>';
        if (f.conversacion) h += '<div style="margin-top:8px;font-size:12.5px;">💬 <a href="' + f.conversacion.url + '" target="_blank">Conversación en WhatsApp de Ovodonación</a>' +
            (f.conversacion.ultima ? ' <span style="color:var(--v2-text-mute);">(último mensaje ' + esc(f.conversacion.ultima) + ')</span>' : '') + '</div>';
    }

    h += '<div class="oc-sec">Datos personales</div><div class="oc-grid">' +
        campo('nombre', 'Nombre', 'text', '', 'maxlength="80"') + campo('apellido', 'Apellido', 'text', '', 'maxlength="80"') +
        campo('dni', 'DNI', 'text', '', 'maxlength="15" inputmode="numeric"') +
        campo('telefono', 'Teléfono', 'text', '', 'maxlength="30"') + campo('email', 'Mail', 'email', '', 'maxlength="120"') +
        campo('fecha_nacimiento', 'Fecha de nacimiento', 'date') +
        campo('edad', 'Edad <span style="font-weight:400;">(si no hay fecha)</span>', 'number', '', 'min="14" max="70"') +
        campo('fuente', 'Fuente', 'text', '', 'maxlength="60" placeholder="Instagram, recomendación…"') +
        '<div><label class="oc-lbl">Responsable</label><select class="oc-in" id="f-responsable_id"><option value="">—</option>' +
            S.responsables.map(u => '<option value="' + u.id + '"' + (u.id === f.responsable_id ? ' selected' : '') + '>' + esc(u.nombre) + '</option>').join('') +
        '</select></div></div>';

    if (don) {
        h += '<div class="oc-sec">Cuestionario de ingreso</div><div class="oc-grid">' +
            campo('peso', 'Peso (kg)', 'number', '', 'step="0.1" min="30" max="250"') + campo('altura', 'Altura (cm)', 'number', '', 'min="100" max="220"') +
            '<div><label class="oc-lbl">IMC</label><div class="oc-in" id="f-imc" style="background:none;border-style:dashed;">' + (f.imc ?? '—') + '</div></div>' +
            siNo('tiene_hijos', '¿Tiene hijos?') + siNo('amamantando', '¿Está amamantando?') + siNo('planea_tatuaje', '¿Planea hacerse un tatuaje o piercing?') +
            siNo('tatuaje_reciente', '¿Tatuaje o piercing en los últimos 6 meses?') + campo('fecha_tatuaje', '¿Cuándo?', 'date') +
            campo('tatuaje_detalle', '¿Cuál?', 'text', '', 'maxlength="160"') +
            campo('medicacion', 'Medicación diaria', 'text', 'w2', 'maxlength="255"') + campo('codigo_donante', 'Código de donante', 'text', '', 'maxlength="30"') +
            '</div>';
        if (!nueva && f.requisitos.length) {
            const ico = { ok: '✅', falla: '⛔', sin_dato: '❔' };
            h += '<div class="oc-sec">Requisitos de ingreso</div>' + f.requisitos.map(r =>
                '<div class="oc-req"><span class="i">' + ico[r.estado] + '</span><b>' + esc(r.label) + '</b><span class="d">' + esc(r.detalle) + '</span></div>').join('');
        } else if (nueva) {
            h += '<div style="font-size:11.5px;color:var(--v2-text-mute);margin-top:8px;">Al crearla se revisan los requisitos de ingreso: si no cumple IMC, edad o altura queda en <b>No cumple</b>; si tiene un tatuaje reciente o lo planea, pasa al tablero <b>Tatuaje</b>.</div>';
        }
    } else {
        h += '<div class="oc-sec">Tratamiento</div><div class="oc-grid">' +
            campo('medico', 'Médico', 'text', '', 'maxlength="80"') + campo('cobertura', 'Cobertura', 'text', '', 'maxlength="80"') +
            campo('fecha_ingreso', 'Ingreso', 'date') + campo('fecha_orden_entregada', 'Orden entregada', 'date') +
            campo('vence_autorizacion', 'Vence autorización', 'date') + campo('fecha_transferencia', 'Transferencia', 'date') + '</div>';
    }

    h += '<div class="oc-sec">Seguimiento</div><div class="oc-grid">' +
        campo('proximo_turno', 'Próximo turno', 'datetime-local') +
        (don ? campo('fecha_puncion', 'Fecha de punción', 'date') : '') +
        campo('recontactar_desde', 'Recontactar desde', 'date') +
        campo('estudios', 'Estudios', 'textarea', 'w3') + campo('descripcion', 'Descripción', 'textarea', 'w3') +
        (f.motivo_cancelacion ? '<div class="w3"><label class="oc-lbl">Motivo de cancelación</label><div>' + esc(f.motivo_cancelacion) + '</div></div>' : '') +
        (f.motivo_no_cumple ? '<div class="w3"><label class="oc-lbl">No cumple</label><div>' + esc(f.motivo_no_cumple) + '</div></div>' : '') +
        '</div>';

    if (!nueva) {
        h += '<div class="oc-dos"><div><div class="oc-sec">Notas</div>' +
            '<div style="display:flex;gap:6px;"><textarea class="oc-in" id="f-nota" placeholder="Agregar nota…" style="min-height:38px;"></textarea>' +
            '<button class="v2-btn" onclick="agregarNota()">Agregar</button></div>' +
            (f.notas.length ? f.notas.map(n => '<div class="oc-item"><div class="m">' + esc(n.fecha) + ' · ' + esc(n.por || '—') + '</div><div class="t">' + esc(n.texto) + '</div></div>').join('')
                            : '<div class="oc-item m" style="color:var(--v2-text-mute);">Sin notas.</div>') +
            '</div><div><div class="oc-sec">Historial de etapas</div>' +
            f.movimientos.map(m => '<div class="oc-item"><div class="m">' + esc(m.fecha) + ' · ' + esc(m.automatico ? 'automático' : (m.por || '—')) + '</div>' +
                '<div>' + (m.desde ? esc(m.desde) + ' → ' : '') + '<b>' + esc(m.hasta) + '</b></div>' +
                (m.motivo ? '<div class="m">' + esc(m.motivo) + '</div>' : '') + '</div>').join('') +
            '</div></div>';
    }

    document.getElementById('fi-cuerpo').innerHTML = h;
    ['peso', 'altura'].forEach(k => { const el = document.getElementById('f-' + k); if (el) el.addEventListener('input', imcEnVivo); });
}

function imcEnVivo() {
    const p = parseFloat(document.getElementById('f-peso').value), a = parseInt(document.getElementById('f-altura').value, 10);
    document.getElementById('f-imc').textContent = p && a ? (p / ((a / 100) ** 2)).toFixed(1) : '—';
}

const CAMPOS = ['nombre', 'apellido', 'dni', 'telefono', 'email', 'fecha_nacimiento', 'edad', 'fuente', 'responsable_id',
    'peso', 'altura', 'tiene_hijos', 'amamantando', 'planea_tatuaje', 'tatuaje_reciente', 'fecha_tatuaje', 'tatuaje_detalle',
    'medicacion', 'codigo_donante', 'medico', 'cobertura', 'fecha_ingreso', 'fecha_orden_entregada', 'vence_autorizacion',
    'fecha_transferencia', 'proximo_turno', 'fecha_puncion', 'recontactar_desde', 'estudios', 'descripcion'];
const BOOL = ['tiene_hijos', 'amamantando', 'planea_tatuaje', 'tatuaje_reciente'];

async function guardarFicha() {
    const f = S.ficha, err = document.getElementById('fi-err');
    const body = {};
    CAMPOS.forEach(k => {
        const el = document.getElementById('f-' + k);
        if (!el) return;
        let v = el.value.trim();
        if (BOOL.includes(k)) v = v === '' ? null : v === '1';
        else if (v === '') v = null;
        body[k] = v;
    });
    if (!body.nombre) { err.textContent = 'Falta el nombre.'; document.getElementById('f-nombre').focus(); return; }
    if (!f.id) { body.tipo = f.tipo; body.proceso = f.proceso; }

    const btn = document.getElementById('fi-guardar');
    btn.disabled = true;
    let r;
    try { r = await api('POST', f.id ? '/crm-ovo/ficha/' + f.id : '/crm-ovo/ficha', body); }
    catch (e) {
        btn.disabled = false;
        err.innerHTML = esc(e.message) + (e.data && e.data.ficha_id ? ' <a href="#" onclick="abrirFicha(' + e.data.ficha_id + ');return false;">Abrir esa ficha</a>' : '');
        return;
    }
    btn.disabled = false;
    S.ficha = r.ficha;
    const pr = S.procesos[r.ficha.proceso];
    v2toast(f.id ? 'Ficha guardada' : 'Ficha creada en ' + pr.label + ' › ' + pr.etapas[r.ficha.etapa]);
    renderFicha();
    cargar();
}

function moverDesdeFicha() {
    const [proceso, etapa] = document.getElementById('f-destino').value.split('|');
    const f = S.ficha;
    if (proceso === f.proceso && etapa === f.etapa) return;
    pedirMover({ id: f.id, nombre: f.nombre_completo, etapa: f.etapa }, proceso, etapa);
}

async function agregarNota() {
    const el = document.getElementById('f-nota');
    const texto = el.value.trim();
    if (!texto) { el.focus(); return; }
    try { const r = await api('POST', '/crm-ovo/ficha/' + S.ficha.id + '/nota', { texto }); S.ficha = r.ficha; }
    catch (e) { v2toast(e.message, 'err'); return; }
    renderFicha();
}

async function borrarFicha() {
    if (!confirm('¿Borrar la ficha de ' + S.ficha.nombre_completo + '? Se puede recuperar pidiéndolo a soporte.')) return;
    try { await api('POST', '/crm-ovo/ficha/' + S.ficha.id + '/borrar'); } catch (e) { v2toast(e.message, 'err'); return; }
    cerrar('dlg-ficha');
    v2toast('Ficha borrada');
    cargar();
}

cargar();
</script>
@endpush
