@extends('layouts.v2')
@section('title', 'Difusiones')

{{-- Difusiones: cada campaña elige su canal (un número con QR o un proveedor,
     config/difusion.php 'canales'). Datos: /difusiones/*, ver DifusionController. --}}

@push('styles')
<style>
.df-wrap { flex:1; overflow-y:auto; padding:20px 24px; }
.df-in { max-width:1100px; margin:0 auto; }
.df-head { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
.df-head h1 { font-size:17px; font-weight:650; margin:0; }
.df-canal { display:flex; align-items:center; gap:8px; padding:9px 12px; border:1px solid var(--v2-border); border-radius:var(--v2-radius); background:var(--v2-bg-card); font-size:12.5px; margin-bottom:14px; }
.df-canal.warn { border-color:var(--v2-warn); background:var(--v2-warn-bg); }
.df-canal .sp { flex:1; }
.df-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:16px; }
.df-card { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius); padding:11px 13px; }
.df-card .l { font-size:10.5px; text-transform:uppercase; letter-spacing:.5px; color:var(--v2-text-mute); }
.df-card .v { font-size:22px; font-weight:700; font-family:'JetBrains Mono',monospace; margin-top:2px; }
.df-card .s { font-size:11px; color:var(--v2-text-mute); }
.df-tabs { display:flex; gap:6px; margin-bottom:14px; }
.df-tab { padding:7px 14px; border:1px solid var(--v2-border); background:var(--v2-bg-card); color:var(--v2-text-2); cursor:pointer; border-radius:var(--v2-radius-sm); font-size:13px; }
.df-tab.active { color:var(--v2-accent); border-color:var(--v2-accent); background:var(--v2-accent-bg); font-weight:600; }
.df-sec { display:none; } .df-sec.active { display:block; }
.df-block { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius); padding:16px; margin-bottom:14px; }
.df-block h3 { font-size:11px; font-weight:600; margin:0 0 12px; color:var(--v2-text-mute); text-transform:uppercase; letter-spacing:.6px; }
.df-embudo { display:flex; flex-direction:column; gap:6px; }
.df-bar { display:grid; grid-template-columns:120px 1fr 90px; align-items:center; gap:10px; font-size:12.5px; }
.df-bar .t { height:16px; border-radius:3px; background:var(--v2-bg-hover); overflow:hidden; }
.df-bar .t span { display:block; height:100%; background:var(--v2-accent-solid); }
.df-bar .n { text-align:right; font-family:'JetBrains Mono',monospace; font-size:12px; }
.df-mini { display:flex; height:6px; border-radius:3px; overflow:hidden; background:var(--v2-bg-hover); min-width:120px; }
.df-mini i { display:block; height:100%; }
.df-row-click { cursor:pointer; }
.df-row-click:hover td { background:var(--v2-bg-hover); }
.df-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media (max-width: 800px) { .df-grid2 { grid-template-columns:1fr; } }
.df-hint { font-size:11.5px; color:var(--v2-text-mute); margin:3px 0 10px; }
.df-prev { white-space:pre-wrap; background:var(--v2-bg-hover); border-radius:10px; padding:10px 12px; font-size:13px; line-height:1.45; min-height:60px; }
.df-aud-res { font-size:13px; margin-top:8px; }
.df-aud-res b { font-size:18px; font-family:'JetBrains Mono',monospace; }
.df-chk { display:flex; align-items:center; gap:6px; font-size:13px; margin:6px 0; }
.df-acc { display:flex; gap:6px; flex-wrap:wrap; }
.df-filtros { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px; }
.df-muted { color:var(--v2-text-mute); font-size:12px; }
.v2-chips { display:flex; gap:5px; flex-wrap:wrap; }
</style>
@endpush

@section('content')
<div class="df-wrap"><div class="df-in">

    <div class="df-head">
        <h1>Difusiones</h1>
    </div>

    <div class="df-canal" id="df-canal"><span class="v2-dot warn"></span><span>Cargando…</span></div>

    <div class="df-cards" id="df-cards"></div>

    <div class="df-tabs">
        <button class="df-tab active" data-tab="campanias">Campañas</button>
        <button class="df-tab" data-tab="nueva">Nueva campaña</button>
        <button class="df-tab" data-tab="plantillas">Plantillas</button>
        <button class="df-tab" data-tab="bajas">Bajas</button>
    </div>

    {{-- ── Campañas ───────────────────────────────────────── --}}
    <section class="df-sec active" id="sec-campanias">
        <div id="df-lista"></div>
        <div id="df-detalle" style="display:none;"></div>
    </section>

    {{-- ── Nueva campaña ──────────────────────────────────── --}}
    <section class="df-sec" id="sec-nueva">
        <div class="df-grid2">
            <div class="df-block">
                <h3>1 · Mensaje</h3>
                <label class="v2-label">Nombre de la campaña *</label>
                <input class="v2-field" id="nc-nombre" maxlength="160" placeholder="Ej: Recordatorio controles octubre">
                <label class="v2-label">Partir de una plantilla</label>
                <select class="v2-field" id="nc-plantilla"><option value="">— Mensaje nuevo —</option></select>
                <label class="v2-label">Texto *</label>
                <textarea class="v2-field" id="nc-texto" rows="7" maxlength="4000" placeholder="Hola {nombre}, …"></textarea>
                <div class="df-hint"><code>{nombre}</code> se reemplaza por el primer nombre del paciente. Conviene cerrar con: <i>Si no querés recibir más estos mensajes respondé BAJA.</i></div>
                <label class="v2-label">Adjunto (imagen, PDF o video, hasta 15 MB)</label>
                <input class="v2-field" id="nc-adjunto" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4">
                <label class="df-chk" id="nc-usar-adj-wrap" style="display:none;"><input type="checkbox" id="nc-usar-adj" checked> Usar el adjunto de la plantilla (<span id="nc-adj-pl"></span>)</label>
                <label class="v2-label" style="margin-top:10px;">Vista previa</label>
                <div class="df-prev" id="nc-prev"></div>
            </div>

            <div class="df-block">
                <h3>2 · Audiencia</h3>
                <div class="v2-chips" id="nc-origen" style="margin-bottom:10px;">
                    <button type="button" class="v2-vista active" data-v="contactos">Desde contactos</button>
                    <button type="button" class="v2-vista" data-v="lista">Pegar una lista</button>
                </div>
                <div id="nc-aud-contactos">
                    <label class="v2-label">Pacientes que escribieron a</label>
                    <select class="v2-field" id="nc-area">
                        <option value="">Cualquier área</option>
                        @foreach($areas as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                    <label class="df-chk"><input type="checkbox" id="nc-solo-conv" checked> Solo quienes alguna vez nos escribieron por WhatsApp</label>
                    <div class="df-hint">Recomendado: mandarle a quien nunca escribió es lo que más reportes de spam genera (y con QR, el mayor riesgo de bloqueo del número).</div>
                    <div class="v2-grid2">
                        <div><label class="v2-label">Con actividad en los últimos (días)</label><input class="v2-field" id="nc-activos" type="number" min="1" placeholder="—"></div>
                        <div><label class="v2-label">Sin actividad hace más de (días)</label><input class="v2-field" id="nc-inactivos" type="number" min="1" placeholder="—"></div>
                    </div>
                </div>
                <div id="nc-aud-lista" style="display:none;">
                    <label class="v2-label">Un número por línea (opcional: <code>número, nombre</code>)</label>
                    <textarea class="v2-field" id="nc-lista" rows="8" placeholder="2235551234, Carolina&#10;+54 9 223 555-9876"></textarea>
                </div>
                <label class="v2-label">No repetir a quien recibió una difusión en los últimos (días)</label>
                <input class="v2-field" id="nc-recientes" type="number" min="1" value="7">
                <div class="df-hint">Siempre se excluyen las bajas y los números inválidos.</div>
                <button class="v2-btn" id="nc-calc">Calcular audiencia</button>
                <div class="df-aud-res" id="nc-aud-res"></div>

                <h3 style="margin-top:18px;">3 · Envío</h3>
                <label class="v2-label">Enviar desde *</label>
                <select class="v2-field" id="nc-canal"></select>
                <div class="df-hint" id="nc-canal-info"></div>
                <label class="v2-label">Programar para (vacío = ahora)</label>
                <input class="v2-field" id="nc-prog" type="datetime-local">
                <div class="df-hint" id="nc-ritmo"></div>
                <div class="df-acc" style="margin-top:10px;">
                    <button class="v2-btn" id="nc-borrador">Guardar borrador</button>
                    <button class="v2-btn primary" id="nc-enviar">Enviar campaña</button>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Plantillas ─────────────────────────────────────── --}}
    <section class="df-sec" id="sec-plantillas">
        <div style="margin-bottom:10px;"><button class="v2-btn primary" onclick="abrirPlantilla()">+ Nueva plantilla</button></div>
        <div id="df-plantillas"></div>
    </section>

    {{-- ── Bajas ──────────────────────────────────────────── --}}
    <section class="df-sec" id="sec-bajas">
        <div class="df-block">
            <h3>Agregar baja a mano</h3>
            <div class="df-acc">
                <input class="v2-field" id="bj-tel" placeholder="Número" style="max-width:220px;">
                <input class="v2-field" id="bj-det" placeholder="Motivo (opcional)" style="max-width:320px;">
                <button class="v2-btn" onclick="agregarBaja()">Dar de baja</button>
            </div>
            <div class="df-hint">Las respuestas "BAJA", "STOP" o "NO MÁS" al número de difusiones se dan de baja solas.</div>
        </div>
        <div id="df-bajas"></div>
    </section>

</div></div>

<dialog class="v2-dialog" id="dlg-plantilla" style="width:min(520px,calc(100vw - 40px));">
    <form method="dialog" onsubmit="return false;">
        <h3 id="pl-h3">Plantilla</h3>
        <input type="hidden" id="pl-id">
        <label class="v2-label">Nombre *</label>
        <input class="v2-field" id="pl-nombre" maxlength="120">
        <label class="v2-label">Texto *</label>
        <textarea class="v2-field" id="pl-texto" rows="8" maxlength="4000"></textarea>
        <label class="v2-label">Adjunto</label>
        <input class="v2-field" id="pl-adjunto" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4">
        <label class="df-chk" id="pl-quitar-wrap" style="display:none;"><input type="checkbox" id="pl-quitar"> Quitar el adjunto actual (<span id="pl-adj-actual"></span>)</label>
        <div class="v2-dialog-foot">
            <button type="button" class="v2-btn" onclick="document.getElementById('dlg-plantilla').close()">Cancelar</button>
            <button type="button" class="v2-btn primary" onclick="guardarPlantilla()">Guardar</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script src="/js/vendor/chart.umd.min.js?v={{ filemtime(public_path('js/vendor/chart.umd.min.js')) }}"></script>
<script>
const { esc, get, post, del } = V2;
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const $ = id => document.getElementById(id);
let D = null;              // último /difusiones/data
let detalleId = null, detalleFiltro = '', detallePag = 1, chartCurva = null;

const PILL = { borrador:'neutral', programada:'nueva', enviando:'proceso', pausada:'espera', terminada:'accent', cancelada:'neutral' };
const EST_DEST = { pendiente:'Pendiente', enviado:'Enviado', entregado:'Entregado', leido:'Leído', fallido:'Falló', omitido:'Omitido' };
const pct = v => v === null || v === undefined ? '—' : v + ' %';
const num = v => (v ?? 0).toLocaleString('es-AR');

async function enviarForm(url, fd) {
    const r = await fetch(url, { method:'POST', body:fd, headers:{ 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' } });
    const d = await r.json().catch(() => null);
    if (!r.ok) throw new Error((d && (d.error || d.message)) || ('HTTP ' + r.status));
    return d;
}

// ── Tabs ────────────────────────────────────────────────
document.querySelectorAll('.df-tab').forEach(b => b.onclick = () => {
    document.querySelectorAll('.df-tab').forEach(x => x.classList.toggle('active', x === b));
    document.querySelectorAll('.df-sec').forEach(s => s.classList.toggle('active', s.id === 'sec-' + b.dataset.tab));
});
function irATab(t) { document.querySelector(`.df-tab[data-tab="${t}"]`).click(); }

// ── Carga general ───────────────────────────────────────
async function cargar() {
    try { D = await get('/difusiones/data'); } catch (e) { v2toast(e.message, 'err'); return; }
    // Estado de los canales habilitados (números con QR y proveedores).
    $('df-canal').className = 'df-canal';
    $('df-canal').innerHTML = `<b style="margin-right:4px;">Canales:</b>` + D.canales.map(c =>
        `<span title="${esc(c.detalle)}" style="display:inline-flex;align-items:center;gap:5px;margin-right:12px;">
            <span class="v2-dot ${c.ok ? 'ok' : 'warn'}"></span>${esc(c.nombre)}</span>`).join('')
        + `<span class="sp"></span><span class="df-muted">Cola "Difusiones": ${D.cola_activa ? 'activa' : 'apagada'}</span>`;
    llenarSelectCanal();

    const R = D.resumen;
    $('df-cards').innerHTML = [
        ['Campañas', num(R.campanias), 'últimos 90 días'],
        ['Mensajes enviados', num(R.enviados), ''],
        ['Entregados', pct(R.tasa_entrega), 'de los enviados'],
        ['Leídos', pct(R.tasa_lectura), 'de los enviados'],
        ['Respondieron', pct(R.tasa_respuesta), 'dentro de 72 h'],
        ['Bajas', num(R.bajas), 'pedidas por respuesta'],
    ].map(([l, v, s]) => `<div class="df-card"><div class="l">${l}</div><div class="v">${v}</div><div class="s">${s}</div></div>`).join('');

    pintarLista(); pintarPlantillas(); pintarBajas(); llenarSelectPlantillas();
    if (detalleId) verDetalle(detalleId, false);
}

// ── Lista de campañas ───────────────────────────────────
function barraMini(m) {
    if (!m.total) return '';
    const w = n => (100 * n / m.total).toFixed(1) + '%';
    return `<div class="df-mini" title="Leídos ${m.leidos} · Entregados ${m.entregados} · Enviados ${m.enviados} · Fallidos/omitidos ${m.fallidos + m.omitidos} · Pendientes ${m.pendientes}">
        <i style="width:${w(m.leidos)};background:var(--v2-ok)"></i>
        <i style="width:${w(m.entregados - m.leidos)};background:var(--v2-info)"></i>
        <i style="width:${w(m.enviados - m.entregados)};background:var(--v2-text-mute)"></i>
        <i style="width:${w(m.fallidos + m.omitidos)};background:var(--v2-urg)"></i></div>`;
}
function pintarLista() {
    $('df-lista').style.display = detalleId ? 'none' : '';
    if (!D.campanias.length) {
        $('df-lista').innerHTML = `<div class="v2-empty"><span class="ico">📣</span>Todavía no hay campañas.<br><br><button class="v2-btn primary" onclick="irATab('nueva')">Crear la primera</button></div>`;
        return;
    }
    $('df-lista').innerHTML = `<table class="v2-table"><thead><tr>
        <th>Campaña</th><th>Estado</th><th>Destinatarios</th><th>Progreso</th><th>Leídos</th><th>Respuestas</th><th>Creada</th></tr></thead><tbody>
        ${D.campanias.map(c => `<tr class="df-row-click" onclick="verDetalle(${c.id})">
            <td><b>${esc(c.nombre)}</b><div class="df-muted">${c.proveedor === 'simulado' ? '<span class="v2-pill neutral">simulada</span>' : '📤 ' + esc(c.canal_nombre)}</div></td>
            <td><span class="v2-pill ${PILL[c.estado]}">${esc(c.estado_label)}</span>${c.estado === 'programada' ? `<div class="df-muted">${esc(c.programada_para)}</div>` : ''}</td>
            <td>${num(c.m.total)}</td>
            <td>${barraMini(c.m)}</td>
            <td>${pct(c.m.tasa_lectura)}</td>
            <td>${num(c.m.respondieron)}${c.m.bajas ? ` <span class="df-muted">· ${c.m.bajas} bajas</span>` : ''}</td>
            <td class="df-muted">${esc(c.creada)}<br>${esc(c.autor || '')}</td></tr>`).join('')}
        </tbody></table>`;
}

// ── Detalle ─────────────────────────────────────────────
async function verDetalle(id, reset = true) {
    if (reset) { detalleFiltro = ''; detallePag = 1; }
    detalleId = id;
    let d;
    try { d = await get(`/difusiones/campanias/${id}?page=${detallePag}${detalleFiltro ? '&estado=' + detalleFiltro : ''}`); }
    catch (e) { v2toast(e.message, 'err'); return; }
    const c = d.campania, m = c.m;
    $('df-lista').style.display = 'none';
    const el = $('df-detalle'); el.style.display = '';

    const acciones = {
        borrador:   [['iniciar', 'Enviar ahora', 'primary'], ['borrar', 'Borrar', 'danger']],
        programada: [['pausar', 'Pausar', ''], ['cancelar', 'Cancelar', 'danger']],
        enviando:   [['pausar', 'Pausar', ''], ['cancelar', 'Cancelar', 'danger']],
        pausada:    [['reanudar', 'Reanudar', 'primary'], ['cancelar', 'Cancelar', 'danger']],
    }[c.estado] || [];

    const base = Math.max(1, m.total);
    const barras = [
        ['Destinatarios', m.total], ['Enviados', m.enviados], ['Entregados', m.entregados],
        ['Leídos', m.leidos], ['Respondieron', m.respondieron],
    ].map(([l, n]) => `<div class="df-bar"><span>${l}</span><div class="t"><span style="width:${(100 * n / base).toFixed(1)}%"></span></div><span class="n">${num(n)}</span></div>`).join('');

    const filtros = [['', 'Todos'], ['pendiente', 'Pendientes'], ['leido', 'Leídos'], ['entregado', 'Entregados'], ['enviado', 'Solo enviados'],
        ['respondieron', 'Respondieron'], ['fallido', 'Fallidos'], ['omitido', 'Omitidos'], ['bajas', 'Bajas']];
    const P = d.destinatarios;

    el.innerHTML = `
        <div class="df-acc" style="align-items:center;margin-bottom:12px;">
            <button class="v2-btn sm" onclick="volverLista()">← Campañas</button>
            <b style="font-size:15px;">${esc(c.nombre)}</b>
            <span class="v2-pill ${PILL[c.estado]}">${esc(c.estado_label)}</span>
            <span style="flex:1"></span>
            <button class="v2-btn sm" onclick="probar(${c.id})">Enviarme una prueba</button>
            ${acciones.map(([a, l, k]) => `<button class="v2-btn sm ${k}" onclick="accion(${c.id}, '${a}')">${l}</button>`).join('')}
        </div>
        <div class="df-cards">
            ${[['Entrega', pct(m.tasa_entrega)], ['Lectura', pct(m.tasa_lectura)], ['Respuesta', pct(m.tasa_respuesta)],
               ['Fallidos', num(m.fallidos)], ['Sin WhatsApp / omitidos', num(m.omitidos)], ['Bajas', num(m.bajas)]]
              .map(([l, v]) => `<div class="df-card"><div class="l">${l}</div><div class="v">${v}</div></div>`).join('')}
        </div>
        <div class="df-grid2">
            <div class="df-block"><h3>Embudo</h3><div class="df-embudo">${barras}</div>
                <div class="df-hint" style="margin-top:10px;">
                    ${c.iniciada_at ? 'Inició ' + esc(c.iniciada_at) : ''}${c.terminada_at ? ' · terminó ' + esc(c.terminada_at) : ''}
                    · sale por: <b>${esc(c.canal_nombre)}</b>
                    ${['borrador', 'pausada', 'programada'].includes(c.estado) ? `<select class="v2-field" style="margin-top:6px;font-size:12px;" onchange="cambiarCanal(${c.id}, this.value)">
                        ${D.canales.map(k => `<option value="${k.clave}" ${k.clave === c.canal ? 'selected' : ''}>${esc(k.nombre)}${k.ok ? '' : ' (no listo)'}</option>`).join('')}</select>` : ''}</div></div>
            <div class="df-block"><h3>Mensaje</h3><div class="df-prev">${esc(c.texto)}</div>
                ${c.adjunto_nombre ? `<div class="df-hint">📎 <a href="/difusiones/adjunto/campania/${c.id}" target="_blank">${esc(c.adjunto_nombre)}</a></div>` : ''}
                <div class="df-hint">Audiencia: ${esc(describirAudiencia(c.audiencia))}</div></div>
        </div>
        <div class="df-block" ${Object.keys(d.curva).length ? '' : 'style="display:none"'}><h3>Por hora desde el inicio</h3><canvas id="df-curva" height="70"></canvas></div>
        <div class="df-block"><h3>Destinatarios</h3>
            <div class="df-filtros">${filtros.map(([v, l]) => `<button class="v2-vista ${detalleFiltro === v ? 'active' : ''}" onclick="filtrarDetalle('${v}')">${l}</button>`).join('')}</div>
            <table class="v2-table"><thead><tr><th>Nombre</th><th>Número</th><th>Estado</th><th>Enviado</th><th>Leído</th><th>Respondió</th></tr></thead><tbody>
            ${P.data.map(x => `<tr><td>${esc(x.nombre || '—')}${x.baja_at ? ' <span class="v2-pill urgente">baja</span>' : ''}</td><td>${esc(x.telefono)}</td>
                <td>${EST_DEST[x.estado] || esc(x.estado)}${x.error ? `<div class="df-muted">${esc(x.error)}</div>` : ''}</td>
                <td class="df-muted">${fh(x.enviado_at)}</td><td class="df-muted">${fh(x.leido_at)}</td><td class="df-muted">${fh(x.respondio_at)}</td></tr>`).join('')
              || '<tr><td colspan="6" class="df-muted">Nadie en este filtro.</td></tr>'}
            </tbody></table>
            ${P.last_page > 1 ? `<div class="df-acc" style="margin-top:8px;align-items:center;">
                <button class="v2-btn sm" ${P.current_page <= 1 ? 'disabled' : ''} onclick="pagDetalle(-1)">‹</button>
                <span class="df-muted">Página ${P.current_page} de ${P.last_page} (${num(P.total)})</span>
                <button class="v2-btn sm" ${P.current_page >= P.last_page ? 'disabled' : ''} onclick="pagDetalle(1)">›</button></div>` : ''}
        </div>`;
    pintarCurva(d.curva);
}
function fh(s) { return s ? new Date(s).toLocaleString('es-AR', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' }) : ''; }
function describirAudiencia(a) {
    if (!a) return '—';
    if (a.origen === 'lista') return 'lista pegada ' + (a.lista || '');
    const p = ['contactos'];
    if (a.area) p.push('que escribieron a ' + a.area);
    else if (a.solo_con_conversacion !== false) p.push('que alguna vez escribieron');
    if (a.activos_dias) p.push(`activos en ${a.activos_dias} días`);
    if (a.inactivos_dias) p.push(`sin actividad hace ${a.inactivos_dias}+ días`);
    if (a.excluir_recientes_dias) p.push(`sin difusión en ${a.excluir_recientes_dias} días`);
    return p.join(', ');
}
function pintarCurva(curva) {
    const cv = $('df-curva'); if (!cv) return;
    if (chartCurva) { chartCurva.destroy(); chartCurva = null; }
    const hs = Object.keys(curva).map(Number); if (!hs.length) return;
    const max = Math.max(...hs), labels = [], env = [], lei = [], res = [];
    for (let h = 0; h <= max; h++) { labels.push('+' + h + ' h'); env.push(curva[h]?.enviados || 0); lei.push(curva[h]?.leidos || 0); res.push(curva[h]?.respuestas || 0); }
    const css = getComputedStyle(document.documentElement);
    const col = v => css.getPropertyValue(v).trim();
    chartCurva = new Chart(cv, { type:'bar', data:{ labels, datasets:[
        { label:'Enviados', data:env, backgroundColor:col('--v2-text-mute') },
        { label:'Leídos', data:lei, backgroundColor:col('--v2-ok') },
        { label:'Respuestas', data:res, backgroundColor:col('--v2-accent-solid') } ] },
        options:{ plugins:{ legend:{ labels:{ color:col('--v2-text-2') } } }, scales:{ x:{ ticks:{ color:col('--v2-text-mute') } }, y:{ beginAtZero:true, ticks:{ color:col('--v2-text-mute'), precision:0 } } } } });
}
function volverLista() { detalleId = null; $('df-detalle').style.display = 'none'; pintarLista(); }
function filtrarDetalle(v) { detalleFiltro = v; detallePag = 1; verDetalle(detalleId, false); }
function pagDetalle(d) { detallePag += d; verDetalle(detalleId, false); }

async function accion(id, a) {
    if (a === 'borrar') {
        if (!confirm('¿Borrar este borrador?')) return;
        try { await del(`/difusiones/campanias/${id}`); v2toast('Borrador eliminado'); volverLista(); cargar(); } catch (e) { v2toast(e.message, 'err'); }
        return;
    }
    const txt = { iniciar:'¿Enviar la campaña ahora?', cancelar:'¿Cancelar la campaña? Los pendientes no se van a enviar.' }[a];
    if (txt && !confirm(txt)) return;
    try { await post(`/difusiones/campanias/${id}/${a}`); v2toast('Listo'); cargar(); } catch (e) { v2toast(e.message, 'err'); }
}
async function probar(id) {
    const tel = prompt('Número al que mandar la prueba (el tuyo):'); if (!tel) return;
    try { await post(`/difusiones/campanias/${id}/prueba`, { telefono: tel }); v2toast('Prueba enviada'); } catch (e) { v2toast(e.message, 'err'); }
}

// ── Nueva campaña ───────────────────────────────────────
let origen = 'contactos', audOk = false;
document.querySelectorAll('#nc-origen .v2-vista').forEach(b => b.onclick = () => {
    origen = b.dataset.v;
    document.querySelectorAll('#nc-origen .v2-vista').forEach(x => x.classList.toggle('active', x === b));
    $('nc-aud-contactos').style.display = origen === 'contactos' ? '' : 'none';
    $('nc-aud-lista').style.display = origen === 'lista' ? '' : 'none';
    invalidarAud();
});
['nc-area', 'nc-solo-conv', 'nc-activos', 'nc-inactivos', 'nc-lista', 'nc-recientes'].forEach(i => $(i).addEventListener('input', invalidarAud));
function invalidarAud() { audOk = false; $('nc-aud-res').innerHTML = '<span class="df-muted">Calculá la audiencia antes de enviar.</span>'; }
function filtrosAud() {
    const n = v => v === '' ? null : parseInt(v, 10);
    return origen === 'lista'
        ? { origen, lista: $('nc-lista').value, excluir_recientes_dias: n($('nc-recientes').value) }
        : { origen, area: $('nc-area').value || null, solo_con_conversacion: $('nc-solo-conv').checked,
            activos_dias: n($('nc-activos').value), inactivos_dias: n($('nc-inactivos').value), excluir_recientes_dias: n($('nc-recientes').value) };
}
$('nc-calc').onclick = async () => {
    $('nc-aud-res').textContent = 'Calculando…';
    try {
        const r = await post('/difusiones/audiencia/preview', { audiencia: filtrosAud() });
        const d = r.descartados;
        audOk = r.total > 0;
        $('nc-aud-res').innerHTML = `<b>${num(r.total)}</b> destinatarios
            <div class="df-muted">Descartados: ${d.bajas} bajas · ${d.recientes} recibieron hace poco · ${d.invalidos} números inválidos · ${d.repetidos} repetidos</div>
            ${r.muestra.length ? `<div class="df-muted" style="margin-top:4px;">Ej.: ${r.muestra.map(m => esc(m.nombre || m.telefono)).join(', ')}…</div>` : ''}`;
    } catch (e) { $('nc-aud-res').textContent = e.message; }
};
function llenarSelectCanal() {
    const sel = $('nc-canal'), v = sel.value || D.canal_por_defecto;
    sel.innerHTML = D.canales.map(c => `<option value="${c.clave}">${esc(c.nombre)}${c.ok ? '' : ' — no listo'}</option>`).join('');
    sel.value = D.canales.some(c => c.clave === v) ? v : (D.canales[0]?.clave || '');
    infoCanal();
}
function infoCanal() {
    const c = D.canales.find(x => x.clave === $('nc-canal').value);
    if (!c) { $('nc-canal-info').textContent = ''; $('nc-ritmo').textContent = ''; return; }
    const L = D.limites;
    $('nc-canal-info').innerHTML = `<span class="v2-dot ${c.ok ? 'ok' : 'warn'}"></span> ${esc(c.detalle)}`
        + (c.riesgo ? `<div style="color:var(--v2-urg);margin-top:4px;">⚠️ ${esc(c.riesgo)}</div>` : '')
        + (c.tipo === 'wwebjs' && c.area ? `<div>Las respuestas entran a la cola de ${esc(c.area === 'difusion' ? 'Difusiones' : c.nombre.replace('Número de ', ''))}.</div>` : '');
    $('nc-ritmo').textContent = c.tipo === 'simulado' ? 'Simulado: se "envía" rápido para probar.'
        : c.tipo === 'wwebjs' ? `Ritmo: 1 mensaje cada ${L.pausa[0]}-${L.pausa[1]} s · tope ${L.tope}/día por número (hoy ${c.enviados_hoy}) · ${L.horario[0]}-${L.horario[1]}, sin domingos.`
        : `Horario ${L.horario[0]}-${L.horario[1]}, sin domingos · tope ${L.tope}/día (hoy ${c.enviados_hoy}).`;
}
$('nc-canal').onchange = infoCanal;
async function cambiarCanal(id, canal) {
    try { await post(`/difusiones/campanias/${id}/canal`, { canal }); v2toast('Canal cambiado'); cargar(); } catch (e) { v2toast(e.message, 'err'); }
}
function llenarSelectPlantillas() {
    const sel = $('nc-plantilla'), v = sel.value;
    sel.innerHTML = '<option value="">— Mensaje nuevo —</option>' + D.plantillas.map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('');
    sel.value = v;
}
$('nc-plantilla').onchange = () => {
    const p = D.plantillas.find(x => x.id == $('nc-plantilla').value);
    if (p) { $('nc-texto').value = p.texto; if (!$('nc-nombre').value) $('nc-nombre').value = p.nombre; }
    $('nc-usar-adj-wrap').style.display = p && p.adjunto_nombre ? '' : 'none';
    $('nc-adj-pl').textContent = p?.adjunto_nombre || '';
    previa();
};
function previa() { $('nc-prev').textContent = ($('nc-texto').value || '').replaceAll('{nombre}', 'Carolina'); }
$('nc-texto').addEventListener('input', previa);

async function crear(accion) {
    if (!$('nc-nombre').value.trim() || !$('nc-texto').value.trim()) { v2toast('Poné nombre y texto', 'err'); return; }
    if (!audOk) { v2toast('Calculá la audiencia primero', 'err'); return; }
    const canal = D.canales.find(x => x.clave === $('nc-canal').value);
    if (accion === 'enviar' && !confirm(($('nc-prog').value ? '¿Programar la campaña' : '¿Enviar la campaña ahora') + ` desde ${canal?.nombre || '?'}?`
        + (canal?.riesgo ? '\n\n⚠️ ' + canal.riesgo : ''))) return;
    const fd = new FormData();
    fd.append('nombre', $('nc-nombre').value.trim());
    fd.append('texto', $('nc-texto').value);
    if ($('nc-plantilla').value) { fd.append('plantilla_id', $('nc-plantilla').value); fd.append('usar_adjunto_plantilla', $('nc-usar-adj').checked ? '1' : '0'); }
    if ($('nc-adjunto').files[0]) fd.append('adjunto', $('nc-adjunto').files[0]);
    if ($('nc-prog').value) fd.append('programada_para', $('nc-prog').value);
    fd.append('accion', accion);
    fd.append('canal', $('nc-canal').value);
    fd.append('audiencia_json', JSON.stringify(filtrosAud()));
    for (const b of ['nc-borrador', 'nc-enviar']) $(b).disabled = true;
    try {
        const r = await enviarForm('/difusiones/campanias', fd);
        v2toast(accion === 'enviar' ? 'Campaña en marcha' : 'Borrador guardado');
        ['nc-nombre', 'nc-texto', 'nc-prog', 'nc-adjunto', 'nc-lista'].forEach(i => $(i).value = '');
        $('nc-plantilla').value = ''; previa(); invalidarAud();
        irATab('campanias'); await cargar(); verDetalle(r.id);
    } catch (e) { v2toast(e.message, 'err'); }
    finally { for (const b of ['nc-borrador', 'nc-enviar']) $(b).disabled = false; }
}
$('nc-borrador').onclick = () => crear('borrador');
$('nc-enviar').onclick = () => crear('enviar');

// ── Plantillas ──────────────────────────────────────────
function pintarPlantillas() {
    $('df-plantillas').innerHTML = D.plantillas.length ? `<table class="v2-table"><thead><tr><th>Nombre</th><th>Texto</th><th>Adjunto</th><th></th></tr></thead><tbody>
        ${D.plantillas.map(p => `<tr><td><b>${esc(p.nombre)}</b></td><td style="max-width:460px;white-space:pre-wrap;">${esc(p.texto.length > 220 ? p.texto.slice(0, 220) + '…' : p.texto)}</td>
            <td>${p.adjunto_nombre ? `<a href="/difusiones/adjunto/plantilla/${p.id}" target="_blank">📎 ${esc(p.adjunto_nombre)}</a>` : ''}</td>
            <td style="white-space:nowrap;"><button class="v2-btn sm" onclick="abrirPlantilla(${p.id})">Editar</button>
                <button class="v2-btn sm danger" onclick="borrarPlantilla(${p.id})">Borrar</button></td></tr>`).join('')}
        </tbody></table>` : '<div class="v2-empty"><span class="ico">📝</span>Sin plantillas. Sirven para no reescribir los mensajes que se repiten.</div>';
}
function abrirPlantilla(id) {
    const p = D.plantillas.find(x => x.id === id);
    $('pl-h3').textContent = p ? 'Editar plantilla' : 'Nueva plantilla';
    $('pl-id').value = p?.id || ''; $('pl-nombre').value = p?.nombre || ''; $('pl-texto').value = p?.texto || '';
    $('pl-adjunto').value = ''; $('pl-quitar').checked = false;
    $('pl-quitar-wrap').style.display = p?.adjunto_nombre ? '' : 'none'; $('pl-adj-actual').textContent = p?.adjunto_nombre || '';
    $('dlg-plantilla').showModal();
}
async function guardarPlantilla() {
    const fd = new FormData();
    fd.append('nombre', $('pl-nombre').value.trim()); fd.append('texto', $('pl-texto').value);
    if ($('pl-adjunto').files[0]) fd.append('adjunto', $('pl-adjunto').files[0]);
    if ($('pl-quitar').checked) fd.append('quitar_adjunto', '1');
    try {
        await enviarForm('/difusiones/plantillas' + ($('pl-id').value ? '/' + $('pl-id').value : ''), fd);
        $('dlg-plantilla').close(); v2toast('Plantilla guardada'); cargar();
    } catch (e) { v2toast(e.message, 'err'); }
}
async function borrarPlantilla(id) {
    if (!confirm('¿Borrar la plantilla? Las campañas ya creadas no cambian.')) return;
    try { await del('/difusiones/plantillas/' + id); cargar(); } catch (e) { v2toast(e.message, 'err'); }
}

// ── Bajas ───────────────────────────────────────────────
function pintarBajas() {
    $('df-bajas').innerHTML = D.bajas.length ? `<table class="v2-table"><thead><tr><th>Número</th><th>Origen</th><th>Detalle</th><th>Fecha</th><th></th></tr></thead><tbody>
        ${D.bajas.map(b => `<tr><td>${esc(b.telefono)}</td><td>${b.origen === 'respuesta' ? 'Respondió' : 'Manual'}</td><td>${esc(b.detalle || '')}</td>
            <td class="df-muted">${fh(b.created_at)}</td><td><button class="v2-btn sm" onclick="quitarBaja(${b.id})">Quitar</button></td></tr>`).join('')}
        </tbody></table>` : '<div class="v2-empty">Nadie pidió la baja.</div>';
}
async function agregarBaja() {
    try { await post('/difusiones/bajas', { telefono: $('bj-tel').value, detalle: $('bj-det').value }); $('bj-tel').value = $('bj-det').value = ''; v2toast('Dado de baja'); cargar(); }
    catch (e) { v2toast(e.message, 'err'); }
}
async function quitarBaja(id) {
    if (!confirm('¿Volver a incluir este número en las difusiones?')) return;
    try { await del('/difusiones/bajas/' + id); cargar(); } catch (e) { v2toast(e.message, 'err'); }
}

invalidarAud(); previa(); cargar();
// Mientras haya campañas en curso, refresco cada 20 s.
setInterval(() => { if (D && D.campanias.some(c => ['enviando', 'programada'].includes(c.estado))) cargar(); }, 20000);
</script>
@endpush
