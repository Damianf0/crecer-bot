@extends('layouts.v2')

{{-- Gestión del checklist de recepción: qué se le pide a cada paciente según
     obra social, plan y práctica. Backend: RecepcionReglasController; la
     resolución (qué regla gana) vive en App\Services\ChecklistRecepcion y es
     la misma que usa la ficha de /v2/recepcion. --}}

@push('styles')
<style>
.rr-shell { padding: 18px 22px 40px; max-width: 1200px; overflow-y: auto; height: 100%; box-sizing: border-box; }
.rr-intro { font-size: 13px; color: var(--v2-text-2); margin: 0 0 18px; max-width: 820px; line-height: 1.5; }
.rr-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 18px; align-items: start; }
@media (max-width: 1050px) { .rr-grid { grid-template-columns: 1fr; } }
.rr-card { background: var(--v2-bg-card); border: 1px solid var(--v2-border); border-radius: var(--v2-radius); padding: 14px 16px; margin-bottom: 18px; }
.rr-card h2 { font-size: 13px; font-weight: 700; margin: 0 0 4px; display: flex; align-items: center; gap: 8px; }
.rr-card h2 .cnt { font-size: 11px; font-weight: 600; color: var(--v2-text-mute); }
.rr-card h2 .v2-btn { margin-left: auto; }
.rr-help { font-size: 12px; color: var(--v2-text-mute); margin: 0 0 12px; line-height: 1.45; }
.rr-in, .rr-sel, .rr-ta { width: 100%; box-sizing: border-box; padding: 7px 10px; border: 1px solid var(--v2-border); border-radius: var(--v2-radius-sm); background: var(--v2-bg-app); color: var(--v2-text); font-size: 13px; font-family: inherit; }
.rr-in:focus, .rr-sel:focus, .rr-ta:focus { outline: none; border-color: var(--v2-accent); }
.rr-ta { min-height: 64px; resize: vertical; }
.rr-lbl { display: block; font-size: 11px; font-weight: 600; color: var(--v2-text-2); margin: 10px 0 4px; }
.rr-lbl .opt { font-weight: 400; color: var(--v2-text-mute); }
.rr-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

.rr-table-wrap { overflow-x: auto; }
.rr-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rr-table th { text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--v2-text-mute); padding: 6px 8px; border-bottom: 1px solid var(--v2-border); white-space: nowrap; }
.rr-table td { padding: 8px; border-bottom: 1px solid var(--v2-border); vertical-align: top; }
.rr-table tr:last-child td { border-bottom: none; }
.rr-table tr.inactiva td { opacity: .5; }
.rr-todas { color: var(--v2-text-mute); font-style: italic; }
.rr-nota { font-size: 12px; color: var(--v2-text-2); margin-top: 2px; }
.rr-meta { font-size: 11px; color: var(--v2-text-mute); white-space: nowrap; }
.rr-acc { white-space: nowrap; text-align: right; }
.rr-acc button { background: none; border: none; color: var(--v2-text-mute); cursor: pointer; font-size: 13px; padding: 2px 5px; }
.rr-acc button:hover { color: var(--v2-accent); }
.rr-acc button.del:hover { color: var(--v2-urg); }
.rr-modo { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px; text-transform: uppercase; letter-spacing: .3px; white-space: nowrap; }
.rr-modo.obligatorio { background: var(--v2-urg-bg); color: var(--v2-urg); }
.rr-modo.opcional { background: var(--v2-info-bg); color: var(--v2-info); }
.rr-modo.no_pedir { background: var(--v2-bg-active); color: var(--v2-text-2); }
.rr-filtros { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.rr-filtros .rr-in { flex: 1; min-width: 180px; }

.rr-req { display: flex; align-items: flex-start; gap: 8px; padding: 8px 0; border-bottom: 1px solid var(--v2-border); }
.rr-req:last-child { border-bottom: none; }
.rr-req .n { font-weight: 600; font-size: 13px; }
.rr-req .d { font-size: 12px; color: var(--v2-text-mute); margin-top: 2px; }
.rr-req.inactivo .n { text-decoration: line-through; color: var(--v2-text-mute); }
.rr-req .acc { margin-left: auto; }

.rr-res { margin-top: 12px; }
.rr-res-item { display: flex; gap: 8px; align-items: flex-start; padding: 7px 9px; border: 1px solid var(--v2-border); border-radius: var(--v2-radius-sm); margin-bottom: 6px; font-size: 13px; }
.rr-res-item .oblig { color: var(--v2-urg); font-weight: 700; }
.rr-res-empty { font-size: 12px; color: var(--v2-text-mute); padding: 8px 0; }

dialog.rr-dlg { border: 1px solid var(--v2-border); border-radius: var(--v2-radius); background: var(--v2-bg-card); color: var(--v2-text); padding: 18px 20px; width: min(520px, 92vw); }
dialog.rr-dlg::backdrop { background: rgba(0,0,0,.35); }
dialog.rr-dlg h3 { margin: 0 0 6px; font-size: 15px; }
.rr-dlg-foot { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }
.rr-err { color: var(--v2-urg); font-size: 12px; margin-top: 8px; min-height: 1em; }
.rr-chk { display: flex; align-items: center; gap: 7px; font-size: 13px; margin-top: 12px; }
</style>
@endpush

@section('content')
<div class="rr-shell">
    <p class="rr-intro">
        Acá se define qué tiene que pedir recepción según la <b>obra social</b>, el <b>plan</b> y la <b>práctica</b> del turno.
        Cuando el paciente se anuncia en el tablet, su ficha arma el checklist con estas reglas.
        Un campo vacío vale para todos, y si dos reglas hablan del mismo requisito <b>gana la más específica</b>:
        obra social + práctica &gt; obra social &gt; práctica &gt; general. Con <i>No pedir</i> se exceptúa un caso de una regla general.
    </p>

    <div class="rr-grid">
        <div>
            <div class="rr-card">
                <h2>Reglas <span class="cnt" id="cnt-reglas"></span>
                    <button class="v2-btn primary" onclick="nuevaRegla()">+ Nueva regla</button></h2>
                <div class="rr-filtros">
                    <input class="rr-in" id="f-texto" placeholder="Buscar por requisito, obra social o práctica…" oninput="renderReglas()">
                </div>
                <div class="rr-table-wrap">
                    <table class="rr-table">
                        <thead><tr><th>Requisito</th><th>Obra social</th><th>Plan</th><th>Práctica</th><th>Modo</th><th>Editada</th><th></th></tr></thead>
                        <tbody id="tb-reglas"><tr><td colspan="7" class="rr-res-empty">Cargando…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <div class="rr-card">
                <h2>Probar un caso</h2>
                <p class="rr-help">Lo mismo que va a ver la recepcionista en la ficha.</p>
                <label class="rr-lbl">Obra social</label>
                <input class="rr-in" id="p-fin" list="dl-fin" placeholder="Ej: Osde Binario" oninput="probarLuego()">
                <div class="rr-row2">
                    <div><label class="rr-lbl">Plan <span class="opt">(opcional)</span></label>
                        <input class="rr-in" id="p-plan" oninput="probarLuego()"></div>
                    <div><label class="rr-lbl">Práctica</label>
                        <input class="rr-in" id="p-prac" list="dl-prac" placeholder="Vacío = sin turno" oninput="probarLuego()"></div>
                </div>
                <div class="rr-res" id="p-res"></div>
            </div>

            <div class="rr-card">
                <h2>Requisitos <span class="cnt" id="cnt-req"></span>
                    <button class="v2-btn" onclick="editarRequisito()">+ Nuevo</button></h2>
                <p class="rr-help">Lo que se le puede pedir a un paciente. Las reglas dicen cuándo.</p>
                <div id="lista-req"></div>
            </div>
        </div>
    </div>
</div>

<datalist id="dl-fin"></datalist>
<datalist id="dl-prac"></datalist>

<dialog class="rr-dlg" id="dlg-regla">
    <h3 id="dr-titulo">Nueva regla</h3>
    <p class="rr-help">Dejá vacío lo que aplica a todos.</p>
    <input type="hidden" id="dr-id">
    <label class="rr-lbl">Requisito</label>
    <select class="rr-sel" id="dr-req"></select>
    <label class="rr-lbl">Obra social <span class="opt">(vacío = todas)</span></label>
    <input class="rr-in" id="dr-fin" list="dl-fin" autocomplete="off">
    <div class="rr-row2">
        <div><label class="rr-lbl">Plan <span class="opt">(vacío = todos)</span></label>
            <input class="rr-in" id="dr-plan" autocomplete="off"></div>
        <div><label class="rr-lbl">Modo</label><select class="rr-sel" id="dr-modo"></select></div>
    </div>
    <label class="rr-lbl">Práctica <span class="opt">(vacío = todas)</span></label>
    <input class="rr-in" id="dr-prac" list="dl-prac" autocomplete="off">
    <label class="rr-lbl">Nota para recepción <span class="opt">(opcional)</span></label>
    <input class="rr-in" id="dr-nota" maxlength="255" placeholder="Ej: original + 1 copia, vigencia 30 días">
    <label class="rr-chk"><input type="checkbox" id="dr-activo" checked> Activa</label>
    <div class="rr-err" id="dr-err"></div>
    <div class="rr-dlg-foot">
        <button class="v2-btn" onclick="document.getElementById('dlg-regla').close()">Cancelar</button>
        <button class="v2-btn primary" onclick="guardarRegla()">Guardar</button>
    </div>
</dialog>

<dialog class="rr-dlg" id="dlg-req">
    <h3 id="dq-titulo">Nuevo requisito</h3>
    <input type="hidden" id="dq-id">
    <label class="rr-lbl">Nombre</label>
    <input class="rr-in" id="dq-nombre" maxlength="120" placeholder="Ej: Autorización previa">
    <label class="rr-lbl">Instrucción <span class="opt">(opcional — qué revisar)</span></label>
    <textarea class="rr-ta" id="dq-instr" maxlength="2000" placeholder="Ej: verificar que esté firmada y sellada por la obra social"></textarea>
    <div class="rr-row2">
        <div><label class="rr-lbl">Vigencia en días <span class="opt">(opcional)</span></label>
            <input class="rr-in" id="dq-vig" type="number" min="1" max="3650"></div>
        <div><label class="rr-lbl">Orden en la lista</label>
            <input class="rr-in" id="dq-orden" type="number" min="0" max="999" value="0"></div>
    </div>
    <label class="rr-chk"><input type="checkbox" id="dq-activo" checked> Activo</label>
    <div class="rr-err" id="dq-err"></div>
    <div class="rr-dlg-foot">
        <button class="v2-btn danger" id="dq-borrar" onclick="borrarRequisito()" style="margin-right:auto;">Borrar</button>
        <button class="v2-btn" onclick="document.getElementById('dlg-req').close()">Cancelar</button>
        <button class="v2-btn primary" onclick="guardarRequisito()">Guardar</button>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
const $ = (id) => document.getElementById(id);
function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function call(url, opts = {}) {
    const tok = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const r = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': tok, ...(opts.headers||{}) },
        ...opts,
    });
    const d = await r.json().catch(() => ({}));
    if (r.status === 419) return { ok:false, _err:'Sesión expirada — recargá (F5).' };
    if (r.status === 422) return { ok:false, _err: d.error || (d.errors ? Object.values(d.errors).flat().join(' ') : 'Datos inválidos') };
    if (!r.ok) return { ok:false, _err: d.error || d.message || ('HTTP '+r.status) };
    return d;
}
// _token también en el body: en la PC de recepción un intermediario pela el
// header X-CSRF-TOKEN (ver recepcion.blade.php).
function postJSON(url, body) {
    const tok = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    return call(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ ...(body||{}), _token: tok }) });
}

let D = { requisitos: [], reglas: [], financiadores: [], practicas: [], modos: {} };

async function cargar() {
    const j = await call('/v2/recepcion/reglas/data');
    if (!j.ok) { v2toast(j._err || 'No se pudo cargar', 'err'); return; }
    D = j;
    $('dl-fin').innerHTML  = D.financiadores.map(f => `<option value="${esc(f.nombre)}">${f.turnos} turnos</option>`).join('');
    $('dl-prac').innerHTML = D.practicas.map(p => `<option value="${esc(p.nombre)}">${p.turnos} turnos</option>`).join('');
    renderRequisitos();
    renderReglas();
    probar();
}

const vacio = (v) => v ? esc(v) : '<span class="rr-todas">todas</span>';

function renderReglas() {
    const q = $('f-texto').value.trim().toLowerCase();
    const filas = D.reglas.filter(r => !q || [r.requisito, r.financiador, r.practica, r.plan, r.nota].some(v => (v||'').toLowerCase().includes(q)));
    $('cnt-reglas').textContent = D.reglas.length ? `${D.reglas.length}` : '';
    if (!D.requisitos.length) {
        $('tb-reglas').innerHTML = `<tr><td colspan="7" class="rr-res-empty">Primero cargá los requisitos (panel de la derecha).</td></tr>`;
        return;
    }
    if (!filas.length) {
        $('tb-reglas').innerHTML = `<tr><td colspan="7" class="rr-res-empty">${D.reglas.length ? 'Ninguna regla coincide con la búsqueda.' : 'Todavía no hay reglas. Mientras tanto la recepción usa el checklist fijo de siempre.'}</td></tr>`;
        return;
    }
    $('tb-reglas').innerHTML = filas.map(r => `
        <tr class="${r.activo ? '' : 'inactiva'}">
            <td><b>${esc(r.requisito)}</b>${r.nota ? `<div class="rr-nota">${esc(r.nota)}</div>` : ''}</td>
            <td>${vacio(r.financiador)}</td>
            <td>${r.plan ? esc(r.plan) : '<span class="rr-todas">todos</span>'}</td>
            <td>${vacio(r.practica)}</td>
            <td><span class="rr-modo ${esc(r.modo)}">${esc(D.modos[r.modo] || r.modo)}</span>${r.activo ? '' : ' <span class="rr-meta">(inactiva)</span>'}</td>
            <td class="rr-meta">${esc(r.editado || '')}${r.editado_por ? `<br>${esc(r.editado_por.split(' ')[0])}` : ''}</td>
            <td class="rr-acc">
                <button title="Editar" onclick="editarRegla(${r.id})">✏️</button>
                <button title="Duplicar" onclick="editarRegla(${r.id}, true)">⧉</button>
                <button class="del" title="Borrar" onclick="borrarRegla(${r.id})">🗑</button>
            </td>
        </tr>`).join('');
}

function renderRequisitos() {
    $('cnt-req').textContent = D.requisitos.length ? `${D.requisitos.length}` : '';
    $('lista-req').innerHTML = D.requisitos.length ? D.requisitos.map(q => `
        <div class="rr-req ${q.activo ? '' : 'inactivo'}">
            <div>
                <div class="n">${esc(q.nombre)}</div>
                <div class="d">${q.reglas_count} ${q.reglas_count === 1 ? 'regla' : 'reglas'}${q.vigencia_dias ? ` · vigencia ${q.vigencia_dias} días` : ''}${q.activo ? '' : ' · inactivo'}</div>
            </div>
            <button class="v2-btn acc" onclick="editarRequisito(${q.id})">Editar</button>
        </div>`).join('') : '<div class="rr-res-empty">Sin requisitos todavía. Ejemplos: orden médica, autorización previa, credencial, consentimiento firmado, bono de copago.</div>';
}

// ── Probar ───────────────────────────────────────────────
let _probarT = null;
function probarLuego() { clearTimeout(_probarT); _probarT = setTimeout(probar, 250); }
async function probar() {
    const qs = new URLSearchParams({ financiador: $('p-fin').value, plan: $('p-plan').value, practica: $('p-prac').value });
    const j = await call('/v2/recepcion/reglas/probar?' + qs);
    if (!j.ok) return;
    const items = j.checklist || [];
    const sinReglas = !D.reglas.length;
    $('p-res').innerHTML = items.length ? items.map(i => `
        <div class="rr-res-item">
            <span>${i.obligatorio ? '<span class="oblig">●</span>' : '○'}</span>
            <div><b>${esc(i.label)}</b>${i.obligatorio ? ' <span class="oblig">*</span>' : ''}
                ${i.nota ? `<div class="rr-nota">${esc(i.nota)}</div>` : ''}
                ${i.instruccion ? `<div class="rr-nota" style="color:var(--v2-text-mute)">${esc(i.instruccion)}</div>` : ''}</div>
        </div>`).join('') + (sinReglas ? '<div class="rr-res-empty">(checklist fijo: todavía no hay reglas cargadas)</div>' : '')
        : '<div class="rr-res-empty">No se le pide nada en este caso.</div>';
}

// ── Reglas ───────────────────────────────────────────────
function abrirDlgRegla(r, titulo) {
    $('dr-titulo').textContent = titulo;
    $('dr-req').innerHTML = D.requisitos.filter(q => q.activo || q.id === r?.requisito_id)
        .map(q => `<option value="${q.id}">${esc(q.nombre)}</option>`).join('');
    $('dr-modo').innerHTML = Object.entries(D.modos).map(([k, v]) => `<option value="${k}">${esc(v)}</option>`).join('');
    $('dr-id').value    = r?.id || '';
    $('dr-req').value   = r?.requisito_id || D.requisitos.find(q => q.activo)?.id || '';
    $('dr-fin').value   = r?.financiador || '';
    $('dr-plan').value  = r?.plan || '';
    $('dr-prac').value  = r?.practica || '';
    $('dr-modo').value  = r?.modo || 'obligatorio';
    $('dr-nota').value  = r?.nota || '';
    $('dr-activo').checked = r ? !!r.activo : true;
    $('dr-err').textContent = '';
    $('dlg-regla').showModal();
}
function nuevaRegla() {
    if (!D.requisitos.some(q => q.activo)) { v2toast('Primero cargá al menos un requisito', 'err'); editarRequisito(); return; }
    abrirDlgRegla(null, 'Nueva regla');
}
function editarRegla(id, duplicar = false) {
    const r = D.reglas.find(x => x.id === id);
    if (!r) return;
    abrirDlgRegla(duplicar ? { ...r, id: null } : r, duplicar ? 'Duplicar regla' : 'Editar regla');
}
async function guardarRegla() {
    const body = {
        id: $('dr-id').value || null,
        requisito_id: parseInt($('dr-req').value),
        financiador: $('dr-fin').value, plan: $('dr-plan').value, practica: $('dr-prac').value,
        modo: $('dr-modo').value, nota: $('dr-nota').value, activo: $('dr-activo').checked,
    };
    // Aviso suave: un nombre fuera del catálogo de Omnia no va a matchear nunca.
    const fueraFin  = body.financiador && !D.financiadores.some(f => f.nombre === body.financiador);
    const fueraPrac = body.practica && !D.practicas.some(p => p.nombre === body.practica);
    if ((fueraFin || fueraPrac) && !confirm(`${fueraFin ? `"${body.financiador}"` : `"${body.practica}"`} no figura en los turnos de Omnia de los últimos meses, así que la regla podría no aplicarse nunca. ¿Guardar igual?`)) return;
    const j = await postJSON('/v2/recepcion/reglas/regla', body);
    if (!j.ok) { $('dr-err').textContent = j._err || 'No se pudo guardar'; return; }
    $('dlg-regla').close();
    v2toast('Regla guardada', 'ok');
    cargar();
}
async function borrarRegla(id) {
    const r = D.reglas.find(x => x.id === id);
    if (!r || !confirm(`¿Borrar la regla "${r.requisito}" (${r.financiador || 'todas las obras sociales'} · ${r.practica || 'todas las prácticas'})?`)) return;
    const j = await postJSON(`/v2/recepcion/reglas/regla/${id}/borrar`);
    if (!j.ok) { v2toast(j._err || 'No se pudo borrar', 'err'); return; }
    v2toast('Regla borrada', 'ok');
    cargar();
}

// ── Requisitos ───────────────────────────────────────────
function editarRequisito(id) {
    const q = id ? D.requisitos.find(x => x.id === id) : null;
    $('dq-titulo').textContent = q ? 'Editar requisito' : 'Nuevo requisito';
    $('dq-id').value     = q?.id || '';
    $('dq-nombre').value = q?.nombre || '';
    $('dq-instr').value  = q?.instruccion || '';
    $('dq-vig').value    = q?.vigencia_dias || '';
    $('dq-orden').value  = q?.orden ?? 0;
    $('dq-activo').checked = q ? !!q.activo : true;
    $('dq-borrar').style.display = q ? '' : 'none';
    $('dq-err').textContent = '';
    $('dlg-req').showModal();
    setTimeout(() => $('dq-nombre').focus(), 50);
}
async function guardarRequisito() {
    const j = await postJSON('/v2/recepcion/reglas/requisito', {
        id: $('dq-id').value || null,
        nombre: $('dq-nombre').value,
        instruccion: $('dq-instr').value || null,
        vigencia_dias: $('dq-vig').value ? parseInt($('dq-vig').value) : null,
        orden: parseInt($('dq-orden').value || '0'),
        activo: $('dq-activo').checked,
    });
    if (!j.ok) { $('dq-err').textContent = j._err || 'No se pudo guardar'; return; }
    $('dlg-req').close();
    v2toast('Requisito guardado', 'ok');
    cargar();
}
async function borrarRequisito() {
    const id = $('dq-id').value;
    if (!id || !confirm('¿Borrar este requisito?')) return;
    const j = await postJSON(`/v2/recepcion/reglas/requisito/${id}/borrar`);
    if (!j.ok) { $('dq-err').textContent = j._err || 'No se pudo borrar'; return; }
    $('dlg-req').close();
    v2toast('Requisito borrado', 'ok');
    cargar();
}

cargar();
</script>
@endpush
