@extends('layouts.v2')

{{-- Checklist de recepción por obra social (lógica del 08/10): cada obra
     social tiene su propia lista de pasos y hay una General para las que no
     tienen la suya. Lo que se carga acá es, tal cual, lo que ve recepción en
     la ficha. Backend: RecepcionReglasController; qué lista le toca a cada
     paciente: App\Services\ChecklistRecepcion. --}}

@push('styles')
<style>
.ck-shell { padding: 18px 22px 40px; max-width: 1180px; overflow-y: auto; height: 100%; box-sizing: border-box; }
.ck-intro { font-size: 13px; color: var(--v2-text-2); margin: 0 0 18px; max-width: 820px; line-height: 1.5; }
.ck-grid { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: 18px; align-items: start; }
@media (max-width: 900px) { .ck-grid { grid-template-columns: 1fr; } }
.ck-card { background: var(--v2-bg-card); border: 1px solid var(--v2-border); border-radius: var(--v2-radius); padding: 14px 16px; }
.ck-card h2 { font-size: 13px; font-weight: 700; margin: 0 0 10px; display: flex; align-items: center; gap: 8px; }
.ck-card h2 .cnt { font-size: 11px; font-weight: 600; color: var(--v2-text-mute); }
.ck-card h2 .v2-btn { margin-left: auto; }
.ck-in { width: 100%; box-sizing: border-box; padding: 7px 10px; border: 1px solid var(--v2-border); border-radius: var(--v2-radius-sm); background: var(--v2-bg-app); color: var(--v2-text); font: inherit; font-size: 13px; }
.ck-in:focus { outline: none; border-color: var(--v2-accent); }
.ck-lbl { display: block; font-size: 11px; font-weight: 600; color: var(--v2-text-2); margin: 12px 0 4px; }
.ck-help { font-size: 12px; color: var(--v2-text-mute); line-height: 1.45; margin: 4px 0 0; }

.ck-lista { display: grid; gap: 2px; max-height: 62vh; overflow-y: auto; margin-top: 10px; }
.ck-os { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; background: none; border: 1px solid transparent; border-radius: var(--v2-radius-sm); padding: 8px 9px; cursor: pointer; color: var(--v2-text); font: inherit; font-size: 13px; }
.ck-os:hover { background: var(--v2-bg-hover); }
.ck-os.sel { background: var(--v2-bg-active); border-color: var(--v2-border); font-weight: 600; }
.ck-os .nom { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ck-os .n { font-size: 11px; color: var(--v2-text-mute); white-space: nowrap; font-weight: 400; }
.ck-os.inactivo .nom { text-decoration: line-through; color: var(--v2-text-mute); }
.ck-tag { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: .3px; background: var(--v2-info-bg); color: var(--v2-info); }

.ck-paso { display: grid; grid-template-columns: 22px minmax(0, 1fr) auto; gap: 8px; align-items: start; padding: 8px 0; border-bottom: 1px solid var(--v2-border); }
.ck-paso .num { font-size: 12px; color: var(--v2-text-mute); padding-top: 8px; text-align: right; }
.ck-paso .campos { display: grid; gap: 5px; min-width: 0; }
.ck-paso .fila2 { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.ck-paso .fila2 .ck-in { flex: 1; min-width: 180px; font-size: 12px; padding: 5px 9px; }
.ck-oblig { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: var(--v2-text-2); white-space: nowrap; cursor: pointer; }
.ck-paso .acc { display: flex; gap: 2px; padding-top: 3px; }
.ck-paso .acc button { background: none; border: none; color: var(--v2-text-mute); cursor: pointer; font-size: 13px; padding: 4px 6px; border-radius: 4px; }
.ck-paso .acc button:hover:not(:disabled) { color: var(--v2-accent); background: var(--v2-bg-hover); }
.ck-paso .acc button.del:hover { color: var(--v2-urg); }
.ck-paso .acc button:disabled { opacity: .3; cursor: default; }
.ck-vacio { font-size: 12.5px; color: var(--v2-text-mute); padding: 14px 0; }
.ck-pie { display: flex; gap: 8px; align-items: center; margin-top: 14px; flex-wrap: wrap; }
.ck-pie .esp { flex: 1; }
.ck-meta { font-size: 11px; color: var(--v2-text-mute); }
.ck-err { color: var(--v2-urg); font-size: 12px; min-height: 1em; margin-top: 8px; }
.ck-activo { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; margin-top: 10px; cursor: pointer; }
</style>
@endpush

@section('content')
<div class="ck-shell">
    <p class="ck-intro">
        Cada <b>obra social</b> tiene su propia lista de pasos para recepción. Lo que cargás acá es lo que aparece en la ficha
        cuando el paciente se anuncia. Si su obra social no tiene una lista propia, se usa la <b>General</b>.
        El nombre tiene que ser el mismo con el que llega de Omnia: <i>OSDE</i> y <i>Osde Binario</i> son dos obras sociales distintas.
    </p>

    <div class="ck-grid">
        <div class="ck-card">
            <h2>Obras sociales <span class="cnt" id="cnt-os"></span>
                <button class="v2-btn primary" onclick="nuevo()">+ Nueva</button></h2>
            <input class="ck-in" id="f-os" placeholder="Buscar…" oninput="renderLista()">
            <div class="ck-lista" id="lista"><div class="ck-vacio">Cargando…</div></div>
        </div>

        <div class="ck-card" id="editor"><div class="ck-vacio">Elegí una obra social de la lista o creá una nueva.</div></div>
    </div>
</div>
<datalist id="dl-fin"></datalist>
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

// D: lo que vino del servidor. E: el checklist que se está editando (copia de trabajo).
let D = { checklists: [], financiadores: [] };
let E = null;
let sucio = false;

const clave = (s) => String(s ?? '').trim().replace(/\s+/g, ' ').toLowerCase();

async function cargar(seleccionarId = null) {
    const j = await call('/v2/recepcion/reglas/data');
    if (!j.ok) { $('lista').innerHTML = `<div class="ck-vacio">${esc(j._err || 'No se pudo cargar')}</div>`; return; }
    D = j;
    renderLista();
    const id = seleccionarId ?? E?.id ?? null;
    const c = id ? D.checklists.find(x => x.id === id) : null;
    if (c) abrir(c.id, true); else if (!E) abrir(D.checklists.find(x => x.general)?.id, true);
}

function renderLista() {
    const q = clave($('f-os').value);
    const propias = D.checklists.filter(c => !c.general);
    $('cnt-os').textContent = propias.length ? `${propias.length} con lista propia` : '';
    const visibles = D.checklists.filter(c => c.general || !q || clave(c.financiador).includes(q));
    $('lista').innerHTML = visibles.map(c => `
        <button type="button" class="ck-os ${E && E.id === c.id ? 'sel' : ''} ${c.activo ? '' : 'inactivo'}" onclick="abrir(${c.id})">
            <span class="nom" title="${esc(c.general ? 'General' : c.financiador)}">${c.general ? 'General' : esc(c.financiador)}</span>
            ${c.general ? '<span class="ck-tag">Para el resto</span>' : ''}
            <span class="n">${c.items.length} ${c.items.length === 1 ? 'paso' : 'pasos'}</span>
        </button>`).join('') || '<div class="ck-vacio">Ninguna obra social con ese nombre.</div>';
}

function hayCambios() { return sucio && !confirm('Hay cambios sin guardar en este checklist. ¿Descartarlos?'); }

function abrir(id, sinPreguntar = false) {
    if (!sinPreguntar && hayCambios()) return;
    const c = D.checklists.find(x => x.id === id);
    if (!c) return;
    E = JSON.parse(JSON.stringify(c));
    sucio = false;
    renderLista(); renderEditor();
}

function nuevo() {
    if (hayCambios()) return;
    E = { id: null, general: false, financiador: '', activo: true, items: [] };
    sucio = false;
    renderLista(); renderEditor();
    $('e-nombre').focus();
}

function renderEditor() {
    if (!E) return;
    const conLista = new Set(D.checklists.filter(c => !c.general && c.id !== E.id).map(c => clave(c.financiador)));
    $('dl-fin').innerHTML = D.financiadores.filter(f => !conLista.has(clave(f.nombre)))
        .map(f => `<option value="${esc(f.nombre)}">${f.turnos ? f.turnos + ' turnos' : ''}</option>`).join('');
    const otras = D.checklists.filter(c => c.id !== E.id && c.items.length);

    $('editor').innerHTML = `
        <h2>${E.general ? 'Checklist General' : (E.id ? 'Checklist de ' + esc(E.financiador) : 'Nuevo checklist')}</h2>
        ${E.general
            ? '<p class="ck-help" style="margin-top:0;">Se usa cuando la obra social del paciente no tiene una lista propia: particulares, pacientes sin turno o sin obra social cargada.</p>'
            : `<label class="ck-lbl" style="margin-top:0;">Obra social</label>
               <input class="ck-in" id="e-nombre" list="dl-fin" maxlength="191" value="${esc(E.financiador)}" placeholder="Elegila de la lista o escribila como llega de Omnia" oninput="E.financiador = this.value; sucio = true;">
               <p class="ck-help">La lista sugiere los nombres que ya aparecieron en los turnos de Omnia y todavía no tienen checklist.</p>
               <label class="ck-activo"><input type="checkbox" id="e-activo" ${E.activo ? 'checked' : ''} onchange="E.activo = this.checked; sucio = true;"> Activo <span class="ck-meta">(apagado, esa obra social usa el General)</span></label>`}

        <label class="ck-lbl">Pasos, en el orden en que los ve recepción</label>
        <div id="pasos"></div>
        <div class="ck-pie">
            <button type="button" class="v2-btn" onclick="agregarPaso()">+ Agregar paso</button>
            ${otras.length ? `<select class="ck-in" id="e-copiar" style="width:auto;max-width:260px;" onchange="copiarDe(this.value); this.value='';">
                <option value="">Copiar los pasos de…</option>
                ${otras.map(c => `<option value="${c.id}">${c.general ? 'General' : esc(c.financiador)}</option>`).join('')}
            </select>` : ''}
            <span class="esp"></span>
            ${E.id && !E.general ? '<button type="button" class="v2-btn danger" onclick="borrar()">Eliminar</button>' : ''}
            <button type="button" class="v2-btn primary" id="e-guardar" onclick="guardar()">Guardar</button>
        </div>
        <div class="ck-err" id="e-err"></div>
        ${E.editado ? `<div class="ck-meta">Última edición: ${esc(E.editado)}${E.editado_por ? ' · ' + esc(E.editado_por) : ''}</div>` : ''}`;
    renderPasos();
}

function renderPasos() {
    const n = E.items.length;
    $('pasos').innerHTML = n ? E.items.map((p, i) => `
        <div class="ck-paso">
            <span class="num">${i + 1}</span>
            <div class="campos">
                <input class="ck-in" id="paso-${i}" maxlength="160" value="${esc(p.texto)}" placeholder="Qué hay que hacer o pedir" oninput="E.items[${i}].texto = this.value; sucio = true;">
                <div class="fila2">
                    <label class="ck-oblig" title="Aparece marcado como obligatorio en la ficha de recepción"><input type="checkbox" ${p.obligatorio ? 'checked' : ''} onchange="E.items[${i}].obligatorio = this.checked; sucio = true;"> Obligatorio</label>
                    <input class="ck-in" maxlength="255" value="${esc(p.nota || '')}" placeholder="Aclaración (opcional)" oninput="E.items[${i}].nota = this.value; sucio = true;">
                </div>
            </div>
            <div class="acc">
                <button type="button" title="Subir" ${i === 0 ? 'disabled' : ''} onclick="mover(${i}, -1)">▲</button>
                <button type="button" title="Bajar" ${i === n - 1 ? 'disabled' : ''} onclick="mover(${i}, 1)">▼</button>
                <button type="button" class="del" title="Quitar este paso" onclick="quitar(${i})">✕</button>
            </div>
        </div>`).join('')
        : '<div class="ck-vacio">Todavía no tiene pasos. ' + (E.general ? 'Sin pasos, la ficha muestra la lista fija de siempre.' : 'Sin pasos, esta obra social usa el General.') + '</div>';
}

function agregarPaso() {
    E.items.push({ texto: '', obligatorio: true, nota: '' });
    sucio = true; renderPasos();
    $('paso-' + (E.items.length - 1)).focus();
}
function quitar(i) { E.items.splice(i, 1); sucio = true; renderPasos(); }
function mover(i, d) {
    const j = i + d; if (j < 0 || j >= E.items.length) return;
    [E.items[i], E.items[j]] = [E.items[j], E.items[i]];
    sucio = true; renderPasos();
}
function copiarDe(id) {
    const c = D.checklists.find(x => x.id === Number(id));
    if (!c) return;
    if (E.items.length && !confirm('Se reemplazan los pasos de este checklist por los de ' + (c.general ? 'General' : c.financiador) + '. ¿Seguir?')) return;
    E.items = JSON.parse(JSON.stringify(c.items));
    sucio = true; renderPasos();
}

async function guardar() {
    $('e-err').textContent = '';
    const items = E.items.map(p => ({ texto: (p.texto || '').trim(), obligatorio: !!p.obligatorio, nota: (p.nota || '').trim() || null }));
    if (!E.general && !clave(E.financiador)) { $('e-err').textContent = 'Falta el nombre de la obra social.'; $('e-nombre').focus(); return; }
    const vacio = items.findIndex(p => !p.texto);
    if (vacio >= 0) { $('e-err').textContent = `El paso ${vacio + 1} está vacío: escribilo o quitalo.`; $('paso-' + vacio).focus(); return; }
    $('e-guardar').disabled = true;
    const j = await postJSON('/v2/recepcion/reglas/checklist', { id: E.id, financiador: E.general ? null : E.financiador, activo: E.activo, items });
    $('e-guardar').disabled = false;
    if (!j.ok) { $('e-err').textContent = j._err || 'No se pudo guardar'; return; }
    sucio = false; E = { id: j.checklist.id };
    v2toast('Checklist guardado');
    cargar(j.checklist.id);
}

async function borrar() {
    if (!confirm(`Se elimina el checklist de ${E.financiador}. Esa obra social pasa a usar el General. ¿Eliminar?`)) return;
    const j = await postJSON(`/v2/recepcion/reglas/checklist/${E.id}/borrar`);
    if (!j.ok) { $('e-err').textContent = j._err || 'No se pudo eliminar'; return; }
    sucio = false; E = null;
    v2toast('Checklist eliminado');
    cargar();
}

window.addEventListener('beforeunload', (e) => { if (sucio) { e.preventDefault(); e.returnValue = ''; } });
cargar();
</script>
@endpush
