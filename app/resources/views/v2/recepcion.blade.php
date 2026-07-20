@extends('layouts.v2')

{{-- Recepción en el shell V2. Reescribe a JS+endpoints los Livewire
     ColaSecretaria (/secretaria) y ColaBot (/cola-bot). Dos tabs:
       · Sala de espera  → cola_atencion (check-in, checklist, liberar)
       · Mensajes del bot → derivaciones (clasificación, resolver)
     InboxWA no se porta: lo cubre /v2/atencion (mismas tablas). --}}

@push('styles')
<style>
.rec-shell { display: flex; flex-direction: column; height: 100%; min-height: 0; }
.rec-tabs { display: flex; gap: 4px; padding: 12px 18px 0; border-bottom: 1px solid var(--v2-border); flex-shrink: 0; }
.rec-tab { padding: 8px 16px; font-size: 13px; font-weight: 600; color: var(--v2-text-2); cursor: pointer; border: none; background: none; border-bottom: 2px solid transparent; display: flex; align-items: center; gap: 7px; }
.rec-tab:hover { color: var(--v2-text); }
.rec-tab.active { color: var(--v2-accent); border-bottom-color: var(--v2-accent); }
.rec-tab .badge { background: var(--v2-bg-active); color: var(--v2-text); border-radius: 9px; padding: 0 7px; font-size: 11px; }
.rec-tab.active .badge { background: var(--v2-accent); color: #fff; }

.rec-pane { flex: 1; min-height: 0; display: none; }
.rec-pane.active { display: flex; }
.rec-cols { flex: 1; min-height: 0; display: grid; grid-template-columns: minmax(0,1fr) 400px; gap: 16px; padding: 16px 18px; }
.rec-cols.no-ficha { grid-template-columns: minmax(0,1fr); }
@media (max-width: 1050px) { .rec-cols { grid-template-columns: 1fr; } }
.rec-list { overflow-y: auto; min-height: 0; }
.rec-list-head { display: flex; align-items: center; gap: 10px; font-size: 11px; font-weight: 700; color: var(--v2-text-mute); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px; }
.rec-list-head .cnt { background: var(--v2-bg-card); border: 1px solid var(--v2-border); border-radius: 10px; padding: 1px 8px; font-size: 10px; }

/* Tarjeta de paciente / derivación */
.rec-card { background: var(--v2-bg-card); border: 1px solid var(--v2-border); border-radius: var(--v2-radius); padding: 12px 14px; margin-bottom: 8px; cursor: pointer; }
.rec-card:hover { border-color: var(--v2-border-strong); }
.rec-card.sel { border-color: var(--v2-accent); box-shadow: 0 0 0 1px var(--v2-accent); }
.rec-card.alerta { border-color: var(--v2-urg); background: var(--v2-urg-bg); }
.rec-card.atendiendo { background: var(--v2-info-bg); }
.rec-card-head { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.rec-card-name { font-size: 14px; font-weight: 700; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rec-card-meta { font-size: 12px; color: var(--v2-text-mute); display: flex; gap: 6px; flex-wrap: wrap; }
.rec-card-meta b { color: var(--v2-text); font-weight: 600; }
.rec-flags { display: flex; gap: 4px; flex-wrap: wrap; margin: 6px 0; }
.rec-flag { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: .3px; }
.rec-flag.yellow { background: var(--v2-warn-bg); color: var(--v2-warn); }
.rec-flag.red    { background: var(--v2-urg-bg);  color: var(--v2-urg); }
.rec-flag.green  { background: var(--v2-ok-bg);   color: var(--v2-ok); }
.rec-flag.orange { background: var(--v2-warn-bg); color: var(--v2-warn); }
.rec-flag.blue   { background: var(--v2-info-bg); color: var(--v2-info); }
.rec-espera { font-weight: 700; }
.rec-espera.warn { color: var(--v2-urg); }
.rec-reorder { display: flex; flex-direction: column; gap: 2px; }
.rec-reorder button { width: 22px; height: 16px; line-height: 1; padding: 0; font-size: 10px; border: 1px solid var(--v2-border); background: var(--v2-bg-app); color: var(--v2-text-2); border-radius: 3px; cursor: pointer; }
.rec-reorder button:hover:not(:disabled) { border-color: var(--v2-accent); color: var(--v2-accent); }
.rec-reorder button:disabled { opacity: .3; cursor: default; }

/* Ficha lateral */
.rec-ficha { background: var(--v2-bg-card); border: 1px solid var(--v2-border); border-radius: var(--v2-radius); padding: 16px; overflow-y: auto; min-height: 0; }
.rec-ficha-head { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 12px; }
.rec-ficha-title { flex: 1; min-width: 0; }
.rec-ficha-title .n { font-size: 16px; font-weight: 700; }
.rec-ficha-title .s { font-size: 12px; color: var(--v2-text-mute); margin-top: 2px; }
.rec-ficha-close { background: none; border: none; color: var(--v2-text-mute); font-size: 18px; cursor: pointer; line-height: 1; }
.rec-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 14px; margin-bottom: 14px; }
.rec-grid .k { font-size: 10px; color: var(--v2-text-mute); text-transform: uppercase; letter-spacing: .3px; }
.rec-grid .v { font-size: 13px; font-weight: 600; }
.rec-sub { font-size: 11px; font-weight: 700; color: var(--v2-text-mute); text-transform: uppercase; letter-spacing: .4px; margin: 14px 0 8px; }
.rec-check { display: flex; align-items: center; gap: 9px; padding: 8px 10px; border: 1px solid var(--v2-border); border-radius: var(--v2-radius-sm); margin-bottom: 6px; cursor: pointer; font-size: 13px; }
.rec-check:hover { background: var(--v2-bg-hover); }
.rec-check .box { width: 18px; height: 18px; border: 1.5px solid var(--v2-border-strong); border-radius: 4px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #fff; }
.rec-check.done .box { background: var(--v2-ok); border-color: var(--v2-ok); }
.rec-check.done .lbl { text-decoration: line-through; color: var(--v2-text-mute); }
.rec-check .oblig { color: var(--v2-urg); font-weight: 700; }
.rec-msg { background: var(--v2-bg-app); border: 1px solid var(--v2-border); border-radius: var(--v2-radius-sm); padding: 10px 12px; font-size: 13px; max-height: 220px; overflow-y: auto; white-space: pre-wrap; word-break: break-word; }
.rec-ta { width: 100%; padding: 9px 11px; border: 1px solid var(--v2-border); border-radius: var(--v2-radius-sm); background: var(--v2-bg-app); color: var(--v2-text); font-size: 13px; font-family: inherit; min-height: 70px; resize: vertical; }
.rec-ta:focus { outline: none; border-color: var(--v2-accent); }
.rec-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
.rec-empty { text-align: center; padding: 30px 20px; color: var(--v2-text-mute); font-size: 13px; }
.rec-ficha-empty { display: flex; align-items: center; justify-content: center; color: var(--v2-text-mute); font-size: 13px; text-align: center; padding: 30px; }
.rec-toggle { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--v2-text-2); cursor: pointer; margin-left: auto; }
</style>
@endpush

@section('content')
<div class="rec-shell">
    <div class="rec-tabs">
        <button class="rec-tab active" data-tab="sala" onclick="recTab('sala')">🪑 Sala de espera <span class="badge" id="badge-sala">0</span></button>
        <button class="rec-tab" data-tab="bot" onclick="recTab('bot')">💬 Mensajes del bot <span class="badge" id="badge-bot">0</span></button>
    </div>

    {{-- ── TAB: Sala de espera ── --}}
    <div class="rec-pane active" id="pane-sala">
        <div class="rec-cols no-ficha" id="cols-sala">
            <div class="rec-list">
                <div class="rec-list-head">Cola de recepción <span class="cnt" id="cnt-sala">0</span> <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--v2-text-mute);">actualiza cada 6s</span></div>
                <div id="lista-sala"><div class="rec-empty">Cargando…</div></div>
            </div>
            <div class="rec-ficha" id="ficha-sala" style="display:none;"></div>
        </div>
    </div>

    {{-- ── TAB: Mensajes del bot ── --}}
    <div class="rec-pane" id="pane-bot">
        <div class="rec-cols no-ficha" id="cols-bot">
            <div class="rec-list">
                <div class="rec-list-head">
                    Derivaciones del bot <span class="cnt" id="cnt-bot">0</span>
                    <label class="rec-toggle"><input type="checkbox" id="chk-prueba" checked onchange="cargarBot()"> mostrar pruebas</label>
                </div>
                <div id="lista-bot"><div class="rec-empty">Cargando…</div></div>
            </div>
            <div class="rec-ficha" id="ficha-bot" style="display:none;"></div>
        </div>
    </div>
</div>
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
function postJSON(url, body) {
    // _token también en el body: Laravel lo acepta de input('_token') y así el
    // CSRF sobrevive aunque un intermediario (AV con web-shield, proxy) pele
    // el header X-CSRF-TOKEN — visto 20/07 en la PC de recepción.
    const tok = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    return call(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ ...(body||{}), _token: tok }) });
}

// ── Tabs ─────────────────────────────────────────────────────────────
let TAB = 'sala';
function recTab(t) {
    TAB = t;
    document.querySelectorAll('.rec-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === t));
    document.querySelectorAll('.rec-pane').forEach(p => p.classList.toggle('active', p.id === 'pane-'+t));
    if (t === 'sala') cargarSala(); else cargarBot();
}

// ════════════════════════ SALA DE ESPERA ════════════════════════
let SALA = [];          // cola actual
let selPac = null;      // paciente seleccionado

async function cargarSala() {
    const j = await call('/v2/recepcion/cola');
    if (!j.ok) return;
    SALA = j.cola || [];
    $('badge-sala').textContent = j.stats?.total ?? SALA.length;
    $('cnt-sala').textContent = SALA.length;
    renderSala();
    // Si hay ficha abierta, refrescarla con datos frescos (o cerrarla si ya salió de la cola).
    if (selPac != null) {
        const p = SALA.find(x => x.id === selPac);
        if (p) renderFichaSala(p); else cerrarFichaSala();
    }
}

function renderSala() {
    const c = $('lista-sala');
    if (!SALA.length) { c.innerHTML = '<div class="rec-empty">Sin pacientes en cola. Aparecen acá al hacer check-in.</div>'; return; }
    c.innerHTML = SALA.map((p, i) => `
        <div class="rec-card ${p.id===selPac?'sel':''} ${p.alerta_espera?'alerta':''} ${p.estado==='en_atencion'?'atendiendo':''}" onclick="abrirPac(${p.id})">
            <div class="rec-card-head">
                <div class="rec-reorder" onclick="event.stopPropagation()">
                    <button onclick="mover(${p.id},-1)" ${i===0?'disabled':''} title="Subir">▲</button>
                    <button onclick="mover(${p.id},1)" ${i===SALA.length-1?'disabled':''} title="Bajar">▼</button>
                </div>
                <div class="rec-card-name">${esc(p.nombre)}</div>
                ${p.estado==='en_atencion' ? '<span class="rec-flag blue">En atención</span>' : ''}
                <span class="rec-espera ${p.alerta_espera?'warn':''}">${p.minutos_espera}m</span>
            </div>
            ${p.flags.length ? `<div class="rec-flags">${p.flags.map(f=>`<span class="rec-flag ${f.color}">${f.icon} ${esc(f.label)}</span>`).join('')}</div>` : ''}
            <div class="rec-card-meta">
                ${p.practica ? `<span>${esc(p.practica)}</span>` : ''}
                ${p.profesional ? `<span>· ${esc(p.profesional)}</span>` : ''}
                ${p.turno_hora ? `<span>· Turno <b>${esc(p.turno_hora)}</b></span>` : ''}
            </div>
        </div>`).join('');
}

async function abrirPac(id) {
    selPac = id;
    const j = await postJSON(`/v2/recepcion/cola/${id}/abrir`);
    if (!j.ok) { v2toast(j._err || 'Error', 'err'); return; }
    // refrescar lista (puede haber pasado a en_atencion) y pintar ficha
    await cargarSala();
    renderFichaSala(j.paciente);
}

function renderFichaSala(p) {
    $('cols-sala').classList.remove('no-ficha');
    const f = $('ficha-sala'); f.style.display = 'block';
    const checklist = p.checklist || [];
    const completo = checklist.filter(i => i.obligatorio).every(i => i.done);
    f.innerHTML = `
        <div class="rec-ficha-head">
            <div class="rec-ficha-title"><div class="n">${esc(p.nombre)}</div><div class="s">DNI ${esc(p.dni || '—')}</div></div>
            <button class="rec-ficha-close" onclick="cerrarFichaSala()">✕</button>
        </div>
        <div class="rec-grid">
            <div><div class="k">Obra social</div><div class="v">${esc(p.obra_social || '—')}</div></div>
            <div><div class="k">Plan</div><div class="v">${esc(p.plan || '—')}</div></div>
            <div><div class="k">Práctica</div><div class="v">${esc(p.practica || '—')}</div></div>
            <div><div class="k">Profesional</div><div class="v">${esc(p.profesional || '—')}</div></div>
            <div><div class="k">Llegada</div><div class="v">${esc(p.hora_llegada || '—')}</div></div>
            <div><div class="k">Espera</div><div class="v">${p.minutos_espera}m</div></div>
        </div>
        ${p.flags.length ? `<div class="rec-flags">${p.flags.map(fl=>`<span class="rec-flag ${fl.color}">${fl.icon} ${esc(fl.label)}</span>`).join('')}</div>` : ''}

        <div class="rec-sub">Checklist de recepción</div>
        ${checklist.length ? checklist.map(it => `
            <div class="rec-check ${it.done?'done':''}" onclick="toggleCheck(${p.id}, '${esc(it.id)}')">
                <div class="box">${it.done?'✓':''}</div>
                <span class="lbl">${esc(it.label)}${it.obligatorio?' <span class="oblig">*</span>':''}</span>
            </div>`).join('') : '<div class="rec-empty" style="padding:10px;">Sin checklist.</div>'}

        <div class="rec-sub">Nota interna</div>
        <textarea class="rec-ta" id="nota-pac" placeholder="Observaciones de recepción…">${esc(p.nota || '')}</textarea>
        <div class="rec-actions">
            <button class="v2-btn" onclick="guardarNotaPac(${p.id})">Guardar nota</button>
        </div>
        <div class="rec-actions">
            <button class="v2-btn primary" onclick="liberar(${p.id})" ${completo?'':'disabled title="Faltan ítems obligatorios"'}>Liberar a sala →</button>
            <button class="v2-btn" onclick="resolverPac(${p.id})">Resolver sin liberar</button>
        </div>`;
}

function cerrarFichaSala() {
    selPac = null;
    $('ficha-sala').style.display = 'none';
    $('cols-sala').classList.add('no-ficha');
    renderSala();
}

let _movePend = false;
async function mover(id, dir) {
    if (_movePend) return;
    const i = SALA.findIndex(p => p.id === id);
    const j = i + dir;
    if (i < 0 || j < 0 || j >= SALA.length) return;
    [SALA[i], SALA[j]] = [SALA[j], SALA[i]];
    renderSala();
    _movePend = true;
    await postJSON('/v2/recepcion/cola/reordenar', { ids: SALA.map(p => p.id) });
    _movePend = false;
}

async function toggleCheck(id, itemId) {
    const j = await postJSON(`/v2/recepcion/cola/${id}/checklist`, { item_id: itemId });
    if (!j.ok) { v2toast(j._err || 'Error', 'err'); return; }
    const p = SALA.find(x => x.id === id);
    if (p) { p.checklist = j.checklist; renderFichaSala(p); }
}

async function guardarNotaPac(id) {
    const nota = $('nota-pac').value;
    const j = await postJSON(`/v2/recepcion/cola/${id}/nota`, { nota });
    if (!j.ok) { v2toast(j._err || 'Error', 'err'); return; }
    const p = SALA.find(x => x.id === id); if (p) p.nota = nota;
    v2toast('Nota guardada', 'ok');
}

async function liberar(id) {
    const j = await postJSON(`/v2/recepcion/cola/${id}/liberar`);
    if (!j.ok) { v2toast(j._err || 'No se pudo liberar', 'err'); return; }
    v2toast('Paciente liberada a sala', 'ok');
    cerrarFichaSala();
    cargarSala();
}

async function resolverPac(id) {
    if (!confirm('¿Resolver sin liberar a sala?')) return;
    const j = await postJSON(`/v2/recepcion/cola/${id}/resolver`);
    if (!j.ok) { v2toast(j._err || 'Error', 'err'); return; }
    v2toast('Caso resuelto', 'ok');
    cerrarFichaSala();
    cargarSala();
}

// ════════════════════════ MENSAJES DEL BOT ════════════════════════
let BOT = [];
let selDeriv = null;

async function cargarBot() {
    const prueba = $('chk-prueba').checked ? 1 : 0;
    const j = await call(`/v2/recepcion/bot?prueba=${prueba}`);
    if (!j.ok) return;
    BOT = j.cola || [];
    $('badge-bot').textContent = j.total ?? BOT.length;
    $('cnt-bot').textContent = BOT.length;
    renderBot();
    if (selDeriv != null) {
        const d = BOT.find(x => x.id === selDeriv);
        if (d) renderFichaBot(d); else cerrarFichaBot();
    }
}

function renderBot() {
    const c = $('lista-bot');
    if (!BOT.length) { c.innerHTML = '<div class="rec-empty">Sin derivaciones pendientes.</div>'; return; }
    c.innerHTML = BOT.map(d => `
        <div class="rec-card ${d.id===selDeriv?'sel':''} ${d.estado==='en_atencion'?'atendiendo':''}" onclick="abrirDeriv(${d.id})">
            <div class="rec-card-head">
                <div class="rec-card-name">${esc(d.telefono)}</div>
                ${d.estado==='en_atencion' ? '<span class="rec-flag blue">En atención</span>' : ''}
                <span class="rec-card-meta">${esc(d.hace || '')}</span>
            </div>
            <div class="rec-flags">
                <span class="rec-flag blue">${esc(d.etiqueta)}</span>
                ${d.es_prueba ? '<span class="rec-flag">🧪 prueba</span>' : ''}
                ${!d.en_horario ? '<span class="rec-flag orange">Fuera de horario</span>' : ''}
            </div>
            <div class="rec-card-meta">${esc((d.texto || '').slice(0, 120))}</div>
        </div>`).join('');
}

async function abrirDeriv(id) {
    selDeriv = id;
    const j = await postJSON(`/v2/recepcion/bot/${id}/abrir`);
    if (!j.ok) { v2toast(j._err || 'Error', 'err'); return; }
    await cargarBot();
    renderFichaBot(j.derivacion);
}

function renderFichaBot(d) {
    $('cols-bot').classList.remove('no-ficha');
    const f = $('ficha-bot'); f.style.display = 'block';
    f.innerHTML = `
        <div class="rec-ficha-head">
            <div class="rec-ficha-title"><div class="n">${esc(d.telefono)}</div><div class="s">${esc(d.fecha || '')}</div></div>
            <button class="rec-ficha-close" onclick="cerrarFichaBot()">✕</button>
        </div>
        <div class="rec-flags">
            <span class="rec-flag blue">${esc(d.etiqueta)}</span>
            ${d.es_prueba ? '<span class="rec-flag">🧪 prueba</span>' : ''}
            ${!d.en_horario ? '<span class="rec-flag orange">Fuera de horario</span>' : ''}
        </div>
        <div class="rec-sub">Mensaje del paciente</div>
        <div class="rec-msg">${esc(d.texto || '—')}</div>
        ${d.resumen_llm ? `<div class="rec-sub">Resumen IA</div><div class="rec-msg">${esc(d.resumen_llm)}</div>` : ''}
        <div class="rec-sub">Nota interna</div>
        <textarea class="rec-ta" id="nota-deriv" placeholder="Notas de la gestión…">${esc(d.nota || '')}</textarea>
        <div class="rec-actions">
            <button class="v2-btn" onclick="guardarNotaDeriv(${d.id})">Guardar nota</button>
            <button class="v2-btn primary" onclick="resolverDeriv(${d.id})">Marcar resuelto ✓</button>
        </div>`;
}

function cerrarFichaBot() {
    selDeriv = null;
    $('ficha-bot').style.display = 'none';
    $('cols-bot').classList.add('no-ficha');
    renderBot();
}

async function guardarNotaDeriv(id) {
    const nota = $('nota-deriv').value;
    const j = await postJSON(`/v2/recepcion/bot/${id}/nota`, { nota });
    if (!j.ok) { v2toast(j._err || 'Error', 'err'); return; }
    const d = BOT.find(x => x.id === id); if (d) d.nota = nota;
    v2toast('Nota guardada', 'ok');
}

async function resolverDeriv(id) {
    const nota = $('nota-deriv')?.value || '';
    const j = await postJSON(`/v2/recepcion/bot/${id}/resolver`, { nota });
    if (!j.ok) { v2toast(j._err || 'Error', 'err'); return; }
    v2toast('Derivación resuelta', 'ok');
    cerrarFichaBot();
    cargarBot();
}

// ── Arranque + polling del tab activo ────────────────────────────────
cargarSala();
cargarBot();
setInterval(() => { if (TAB === 'sala') cargarSala(); else cargarBot(); }, 6000);
</script>
@endpush
