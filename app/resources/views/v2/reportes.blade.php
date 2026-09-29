@extends('layouts.v2')
@section('title', 'Reportes')

{{-- PoC V2 — reportes con el MISMO funcionamiento que /admin/estadisticas:
     3 tabs (Hoy / Por secretaria / Tendencias), mismos endpoints
     /admin/estadisticas/*. Cambia la piel al shell V2 y los gráficos toman
     los colores de los tokens (siguen el tema claro/oscuro). --}}

@push('styles')
<style>
.rp-tabs { display:flex; gap:6px; margin-bottom:18px; }
.rp-tab {
    padding:7px 14px; border:1px solid var(--v2-border); background:var(--v2-bg-card);
    color:var(--v2-text-2); cursor:pointer; border-radius:var(--v2-radius-sm); font-size:13px;
}
.rp-tab.active { color:var(--v2-accent); border-color:var(--v2-accent); background:var(--v2-accent-bg); font-weight:600; }
.rp-section { display:none; }
.rp-section.active { display:block; }

.rp-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(165px,1fr)); gap:12px; margin-bottom:18px; }
.rp-card { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius); padding:13px 15px; }
.rp-card .label { font-size:10.5px; text-transform:uppercase; letter-spacing:.5px; color:var(--v2-text-mute); margin-bottom:4px; }
.rp-card .val   { font-size:24px; font-weight:700; font-family:'JetBrains Mono',monospace; }
.rp-card .sub   { font-size:11px; color:var(--v2-text-mute); margin-top:2px; }
.rp-card.warn .val  { color:var(--v2-warn); }
.rp-card.error .val { color:var(--v2-urg); }
.rp-card.ok .val    { color:var(--v2-ok); }

.rp-sec-title { font-size:11px; font-weight:600; color:var(--v2-text-mute); text-transform:uppercase; letter-spacing:.6px; margin:16px 0 8px; }
.rp-block { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius); padding:16px; margin-bottom:16px; }
.rp-block h3 { font-size:11px; font-weight:600; margin:0 0 12px; color:var(--v2-text-mute); text-transform:uppercase; letter-spacing:.6px; }

.rp-table { width:100%; border-collapse:collapse; font-size:13px; }
.rp-table th, .rp-table td { text-align:left; padding:8px 10px; border-bottom:1px solid var(--v2-border); }
.rp-table th { font-weight:600; color:var(--v2-text-mute); font-size:10.5px; text-transform:uppercase; letter-spacing:.5px; }
.rp-table th.sortable { cursor:pointer; user-select:none; white-space:nowrap; }
.rp-table th.sortable:hover { color:var(--v2-text-2); }
.rp-table th.sortable .arrow { opacity:.45; font-size:9px; }
.rp-table tbody tr:hover { background:var(--v2-bg-hover); }
.rp-table td.num { text-align:right; font-variant-numeric:tabular-nums; font-family:'JetBrains Mono',monospace; font-size:12px; }

.rp-filtros { display:flex; gap:10px; align-items:center; margin-bottom:14px; flex-wrap:wrap; }
.rp-filtros .v2-field { width:auto; }

.rp-heat { display:grid; grid-template-columns:46px repeat(24, 1fr); gap:2px; font-size:10px; }
.rp-heat .h-label { color:var(--v2-text-mute); text-align:right; padding-right:6px; line-height:17px; }
.rp-heat .h-cell  { height:17px; border-radius:2px; background:var(--v2-bg-hover); }

.rp-loading { color:var(--v2-text-mute); padding:20px; text-align:center; font-size:13px; }
.rp-empty   { color:var(--v2-text-mute); padding:14px; text-align:center; font-size:12px; font-style:italic; }
</style>
@endpush

@section('content')
<div style="flex:1;overflow-y:auto;padding:20px 24px;">
<div style="max-width:1100px;margin:0 auto;">

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
        <h1 style="font-size:17px;font-weight:650;margin:0;">Reportes</h1>
        <span id="rp-updated" style="margin-left:auto;font-size:11px;color:var(--v2-text-mute);"></span>
        <button class="v2-btn" onclick="cargarHoy()" title="Refrescar datos">↻ Refrescar</button>
    </div>

    <div class="rp-tabs">
        <button class="rp-tab active" data-tab="hoy">Hoy</button>
        <button class="rp-tab"        data-tab="secretarias">Por secretaria</button>
        <button class="rp-tab"        data-tab="tendencias">Tendencias</button>
        <button class="rp-tab"        data-tab="tipos">Tipos de consulta</button>
    </div>

    {{-- ── Tab: Hoy ──────────────────────────────────────────── --}}
    <div class="rp-section active" id="sec-hoy">
        <div class="rp-loading" id="hoy-loading">Cargando…</div>
        <div id="hoy-content" style="display:none;">

            <div class="rp-sec-title" style="margin-top:0;">Estado actual</div>
            <div class="rp-cards">
                <div class="rp-card"><div class="label">Backlog</div><div class="val" id="v-backlog">–</div><div class="sub">sin tomar, no leídas</div></div>
                <div class="rp-card"><div class="label">En proceso</div><div class="val" id="v-en-proceso">–</div><div class="sub">asignadas</div></div>
                <div class="rp-card warn"><div class="label">Urgentes</div><div class="val" id="v-urgentes">–</div><div class="sub">activas</div></div>
                <div class="rp-card"><div class="label">En sala</div><div class="val" id="v-en-sala">–</div><div class="sub">esperando atención</div></div>
            </div>

            <div class="rp-sec-title">SLA del día</div>
            <div class="rp-cards">
                <div class="rp-card ok"><div class="label">Tomadas hoy</div><div class="val" id="v-sla-tomadas">–</div></div>
                <div class="rp-card" id="c-sla-5"><div class="label">% en &lt; 5 min</div><div class="val" id="v-sla-5">–</div></div>
                <div class="rp-card" id="c-sla-15"><div class="label">% en &lt; 15 min</div><div class="val" id="v-sla-15">–</div></div>
                <div class="rp-card" id="c-sla-30"><div class="label">% en &lt; 30 min</div><div class="val" id="v-sla-30">–</div></div>
                <div class="rp-card"><div class="label">Mediana</div><div class="val" id="v-sla-med">–</div><div class="sub">tiempo de toma</div></div>
            </div>

            <div class="rp-sec-title">Volumen del día</div>
            <div class="rp-cards">
                <div class="rp-card"><div class="label">Mensajes recibidos</div><div class="val" id="v-msg-in">–</div></div>
                <div class="rp-card"><div class="label">Mensajes enviados</div><div class="val" id="v-msg-out">–</div></div>
                <div class="rp-card"><div class="label">Conversaciones nuevas</div><div class="val" id="v-convs-nuevas">–</div></div>
                <div class="rp-card ok"><div class="label">Conversaciones resueltas</div><div class="val" id="v-convs-cerradas">–</div></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="rp-block"><h3>Llegadas a recepción (Tablet)</h3><div id="v-tablet">–</div></div>
                <div class="rp-block"><h3>Documentos del legajo</h3><div id="v-docs">–</div></div>
            </div>

            <div class="rp-sec-title">Cobertura LLM (resumen de conversaciones)</div>
            <div class="rp-cards">
                <div class="rp-card ok"><div class="label">Cobertura</div><div class="val" id="v-llm-cov">–</div><div class="sub">de las que ameritan resumen</div></div>
                <div class="rp-card"><div class="label">Con resumen</div><div class="val" id="v-llm-ok">–</div></div>
                <div class="rp-card error"><div class="label">Fallaron</div><div class="val" id="v-llm-no">–</div><div class="sub">ameritan y no se pudo generar</div></div>
                <div class="rp-card"><div class="label">No ameritan</div><div class="val" id="v-llm-skip">–</div><div class="sub">charlas cortas, salto deliberado</div></div>
                <div class="rp-card warn"><div class="label">Pendientes de evaluar</div><div class="val" id="v-llm-pend">–</div><div class="sub">históricas sin tocar</div></div>
                <div class="rp-card"><div class="label">Jobs en cola</div><div class="val" id="v-llm-jobs">–</div><div class="sub">queue 'resumen'</div></div>
            </div>
        </div>
    </div>

    {{-- ── Tab: Por secretaria ───────────────────────────────── --}}
    <div class="rp-section" id="sec-secretarias">
        <div class="rp-filtros">
            <label style="font-size:12px;color:var(--v2-text-mute);">Desde:</label>
            <input type="date" id="sec-from" class="v2-field">
            <label style="font-size:12px;color:var(--v2-text-mute);">Hasta:</label>
            <input type="date" id="sec-to" class="v2-field">
            <button class="v2-btn primary" onclick="cargarSecretarias()">Aplicar</button>
            <span style="font-size:11px;color:var(--v2-text-mute);margin-left:auto;">Solo se listan usuarios con actividad en el rango.</span>
        </div>
        <div class="rp-block">
            <table class="rp-table" id="sec-table">
                <thead>
                    <tr>
                        <th class="sortable" data-key="nombre">Secretaria <span class="arrow"></span></th>
                        <th class="num sortable" style="text-align:right;" data-key="tomadas">Tomadas <span class="arrow"></span></th>
                        <th class="num sortable" style="text-align:right;" data-key="resueltas">Resueltas <span class="arrow"></span></th>
                        <th class="num sortable" style="text-align:right;" data-key="delegadas">Delegadas <span class="arrow"></span></th>
                        <th class="num sortable" style="text-align:right;" data-key="reabiertas">Reabiertas <span class="arrow"></span></th>
                        <th class="num sortable" style="text-align:right;" data-key="msj_enviados">Mensajes env. <span class="arrow"></span></th>
                        <th class="num sortable" style="text-align:right;" data-key="t_resp_medio_seg">T. respuesta <span class="arrow"></span></th>
                        <th class="num sortable" style="text-align:right;" data-key="t_resol_medio_seg">T. resolución <span class="arrow"></span></th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="8" class="rp-loading">Cargando…</td></tr></tbody>
            </table>
        </div>
    </div>

    {{-- ── Tab: Tendencias ───────────────────────────────────── --}}
    <div class="rp-section" id="sec-tendencias">
        <div class="rp-filtros">
            <label style="font-size:12px;color:var(--v2-text-mute);">Desde:</label>
            <input type="date" id="ten-from" class="v2-field">
            <label style="font-size:12px;color:var(--v2-text-mute);">Hasta:</label>
            <input type="date" id="ten-to" class="v2-field">
            <button class="v2-btn primary" onclick="cargarTendencias()">Aplicar</button>
        </div>

        <div class="rp-block">
            <h3>Conversaciones nuevas por día</h3>
            <canvas id="chart-convs" height="80"></canvas>
        </div>
        <div class="rp-block">
            <h3>Mensajes recibidos vs enviados</h3>
            <canvas id="chart-msjs" height="80"></canvas>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="rp-block">
                <h3>Llegadas Tablet por motivo</h3>
                <canvas id="chart-tablet" height="160"></canvas>
            </div>
            <div class="rp-block">
                <h3>Mensajes entrantes por hora (heatmap)</h3>
                <div class="rp-heat" id="heat"></div>
                <p style="font-size:10px;color:var(--v2-text-mute);margin-top:6px;">Día de la semana × hora del día. Más oscuro = más mensajes.</p>
            </div>
        </div>
    </div>

    {{-- ── Tab: Tipos de consulta ────────────────────────────── --}}
    <div class="rp-section" id="sec-tipos">
        <div class="rp-filtros">
            <select id="tp-rango" class="v2-field">
                <option value="7">Últimos 7 días</option>
                <option value="30" selected>Últimos 30 días</option>
                <option value="90">Últimos 90 días</option>
            </select>
            <select id="tp-area" class="v2-field">
                <option value="">Todas las áreas</option>
                @foreach(\App\Models\ConversacionWA::AREAS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
            <button class="v2-btn primary" onclick="cargarTipos()">Aplicar</button>
            <span style="font-size:11px;color:var(--v2-text-mute);">Según la etiqueta que pone la IA a cada consulta. Historia desde el 26/06.</span>
        </div>
        <div class="rp-loading" id="tp-loading">Cargando…</div>
        <div id="tp-content" style="display:none;">
            <div class="rp-cards" id="tp-cards"></div>

            <div class="rp-block">
                <h3>De qué nos consultan — por semana</h3>
                <canvas id="chart-tipos-sem" height="90"></canvas>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="rp-block">
                    <h3>Por tipo (clic para ver casos)</h3>
                    <div id="tp-codigos"></div>
                </div>
                <div>
                    <div class="rp-block">
                        <h3>Por área</h3>
                        <div id="tp-areas"></div>
                    </div>
                    <div class="rp-block">
                        <h3>Tiempo hasta la primera respuesta</h3>
                        <div id="tp-tiempos"></div>
                    </div>
                    <div class="rp-block">
                        <h3>Calidad del clasificador</h3>
                        <div id="tp-calidad"></div>
                    </div>
                </div>
            </div>

            <div class="rp-block" id="tp-detalle-wrap" style="display:none;">
                <h3 id="tp-detalle-h3">Casos</h3>
                <label style="font-size:12px;display:inline-flex;gap:5px;align-items:center;margin-bottom:8px;">
                    <input type="checkbox" id="tp-sin-revisar" onchange="verCasos()"> Solo sin revisar</label>
                <div id="tp-detalle"></div>
            </div>
        </div>
    </div>

</div>
</div>
@endsection

@push('scripts')
{{-- Chart.js vendorizado (06/07): antes venía de cdn.jsdelivr.net y la tab
     Tendencias moría sin salida a internet — esto corre en LAN. --}}
<script src="/js/vendor/chart.umd.min.js?v={{ filemtime(public_path('js/vendor/chart.umd.min.js')) }}"></script>
<script>
// Colores desde los tokens V2 para que los gráficos sigan el tema.
const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
if (typeof Chart !== 'undefined') {
    Chart.defaults.color = css('--v2-text-2');
    Chart.defaults.borderColor = css('--v2-border');
}

// ── Tabs (persistentes vía hash: refrescar la página no pierde la tab) ──
function activarTab(tab) {
    const btn = document.querySelector(`.rp-tab[data-tab="${tab}"]`);
    if (!btn) return;
    document.querySelectorAll('.rp-tab').forEach(x => x.classList.remove('active'));
    document.querySelectorAll('.rp-section').forEach(x => x.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('sec-' + tab).classList.add('active');
    history.replaceState(null, '', tab === 'hoy' ? location.pathname : '#' + tab);
    if (tab === 'secretarias' && !window._secLoaded) cargarSecretarias();
    if (tab === 'tendencias' && !window._tenLoaded) cargarTendencias();
    if (tab === 'tipos' && !window._tpLoaded) cargarTipos();
}
document.querySelectorAll('.rp-tab').forEach(b => b.onclick = () => activarTab(b.dataset.tab));

// ── Hoy ───────────────────────────────────────────────
async function cargarHoy() {
    // Spinner solo en la primera carga: los auto-refresh actualizan en el
    // lugar, sin parpadeo.
    const primeraCarga = document.getElementById('hoy-content').style.display !== 'block';
    if (primeraCarga) {
        document.getElementById('hoy-loading').style.display = 'block';
        document.getElementById('hoy-content').style.display = 'none';
    }
    try {
        const r = await fetch('/admin/estadisticas/hoy');
        const d = await r.json();
        document.getElementById('v-backlog').textContent = d.vivo.backlog;
        document.getElementById('v-en-proceso').textContent = d.vivo.en_proceso;
        document.getElementById('v-urgentes').textContent = d.vivo.urgentes;
        document.getElementById('v-en-sala').textContent = d.vivo.en_sala;

        document.getElementById('v-sla-tomadas').textContent = d.sla.tomadas_total;
        pintarSla('c-sla-5',  'v-sla-5',  d.sla.pct_5min,  d.sla.tomadas_total);
        pintarSla('c-sla-15', 'v-sla-15', d.sla.pct_15min, d.sla.tomadas_total);
        pintarSla('c-sla-30', 'v-sla-30', d.sla.pct_30min, d.sla.tomadas_total);
        document.getElementById('v-sla-med').textContent = d.sla.mediana_seg !== null ? fmtSeg(d.sla.mediana_seg) : '—';

        document.getElementById('v-msg-in').textContent  = d.volumen.msg_in;
        document.getElementById('v-msg-out').textContent = d.volumen.msg_out;
        document.getElementById('v-convs-nuevas').textContent   = d.volumen.convs_nuevas;
        document.getElementById('v-convs-cerradas').textContent = d.volumen.convs_cerradas;

        document.getElementById('v-tablet').innerHTML = renderKV(d.volumen.tablet_por_motivo, 'Sin llegadas hoy');
        document.getElementById('v-docs').innerHTML   = renderKV(d.volumen.docs, 'Sin documentos hoy');

        if (d.llm) {
            document.getElementById('v-llm-cov').textContent  = d.llm.cobertura_pct + '%';
            document.getElementById('v-llm-ok').textContent   = d.llm.con_resumen;
            document.getElementById('v-llm-no').textContent   = d.llm.sin_resumen;
            document.getElementById('v-llm-skip').textContent = d.llm.no_ameritan ?? '—';
            document.getElementById('v-llm-pend').textContent = d.llm.pendientes_eval;
            document.getElementById('v-llm-jobs').textContent = d.llm.jobs_en_cola;
        }

        document.getElementById('rp-updated').textContent = 'Actualizado ' + new Date(d.updated_at).toLocaleTimeString('es-AR');
        document.getElementById('hoy-loading').style.display = 'none';
        document.getElementById('hoy-content').style.display = 'block';
    } catch (e) {
        if (primeraCarga) {
            document.getElementById('hoy-loading').textContent = 'Error cargando datos';
        } else {
            document.getElementById('rp-updated').textContent = 'Error al refrescar — reintenta en 60s';
        }
    }
}

// ── Por secretaria (ordenable por columna) ─────────────
let _secRows = [];
let _secSort = { key: 'tomadas', dir: -1 };

async function cargarSecretarias() {
    const tbody = document.querySelector('#sec-table tbody');
    tbody.innerHTML = '<tr><td colspan="8" class="rp-loading">Cargando…</td></tr>';
    const from = document.getElementById('sec-from').value;
    const to   = document.getElementById('sec-to').value;
    const qs = new URLSearchParams();
    if (from) qs.set('from', from);
    if (to)   qs.set('to', to);
    try {
        const r = await fetch('/admin/estadisticas/secretarias?' + qs);
        const d = await r.json();
        document.getElementById('sec-from').value = d.from;
        document.getElementById('sec-to').value   = d.to;
        _secRows = d.rows;
        renderSecretarias();
        window._secLoaded = true;
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" class="rp-empty">Error al cargar</td></tr>';
    }
}

function renderSecretarias() {
    const tbody = document.querySelector('#sec-table tbody');
    if (!_secRows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="rp-empty">Sin actividad en el rango seleccionado</td></tr>';
        return;
    }
    const { key, dir } = _secSort;
    const rows = [..._secRows].sort((a, b) => {
        const va = a[key], vb = b[key];
        if (va === null || va === undefined) return 1;    // nulls al final siempre
        if (vb === null || vb === undefined) return -1;
        if (typeof va === 'string') return va.localeCompare(vb, 'es') * dir;
        return (va - vb) * dir;
    });
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${escapeHtml(r.nombre)} <span style="color:var(--v2-text-mute);font-size:11px;">${r.rol}</span></td>
            <td class="num">${r.tomadas}</td>
            <td class="num">${r.resueltas}</td>
            <td class="num">${r.delegadas}</td>
            <td class="num">${r.reabiertas || '—'}</td>
            <td class="num">${r.msj_enviados}</td>
            <td class="num">${r.t_resp_medio_seg !== null ? fmtSeg(r.t_resp_medio_seg) : '—'}</td>
            <td class="num">${r.t_resol_medio_seg !== null ? fmtSeg(r.t_resol_medio_seg) : '—'}</td>
        </tr>
    `).join('');
    document.querySelectorAll('#sec-table th.sortable').forEach(th => {
        th.querySelector('.arrow').textContent = th.dataset.key === key ? (dir === -1 ? '▼' : '▲') : '';
    });
}

document.querySelectorAll('#sec-table th.sortable').forEach(th => th.onclick = () => {
    const key = th.dataset.key;
    _secSort = { key, dir: _secSort.key === key ? -_secSort.dir : (key === 'nombre' ? 1 : -1) };
    renderSecretarias();
});

// ── Tendencias ────────────────────────────────────────
let chartConvs, chartMsjs, chartTablet;

async function cargarTendencias() {
    const from = document.getElementById('ten-from').value;
    const to   = document.getElementById('ten-to').value;
    const qs = new URLSearchParams();
    if (from) qs.set('from', from);
    if (to)   qs.set('to', to);
    let d;
    try {
        const r = await fetch('/admin/estadisticas/tendencias?' + qs);
        d = await r.json();
    } catch (e) {
        document.getElementById('heat').innerHTML = '<div class="rp-empty" style="grid-column:1/-1;">Error al cargar tendencias</div>';
        return;
    }
    document.getElementById('ten-from').value = d.from;
    document.getElementById('ten-to').value   = d.to;

    const dias = unionDias(d.convs_por_dia, d.msj_in_por_dia, d.msj_out_por_dia);
    const cInfo = css('--v2-info'), cOk = css('--v2-ok'), cWarn = css('--v2-warn'),
          cAccent = css('--v2-accent'), cUrg = css('--v2-urg');

    if (chartConvs) chartConvs.destroy();
    chartConvs = new Chart(document.getElementById('chart-convs'), {
        type: 'line',
        data: { labels: dias, datasets: [{ label: 'Conversaciones', data: dias.map(d2 => d.convs_por_dia[d2] || 0), borderColor: cInfo, backgroundColor: cInfo + '22', tension: .3, fill: true }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    if (chartMsjs) chartMsjs.destroy();
    chartMsjs = new Chart(document.getElementById('chart-msjs'), {
        type: 'line',
        data: { labels: dias, datasets: [
            { label: 'Recibidos', data: dias.map(d2 => d.msj_in_por_dia[d2] || 0), borderColor: cOk, backgroundColor: cOk + '22', tension: .3, fill: true },
            { label: 'Enviados',  data: dias.map(d2 => d.msj_out_por_dia[d2] || 0), borderColor: cWarn, backgroundColor: cWarn + '22', tension: .3, fill: true },
        ] },
        options: { scales: { y: { beginAtZero: true } } }
    });

    const motivos = Object.keys(d.tablet_por_motivo);
    if (chartTablet) chartTablet.destroy();
    if (motivos.length) {
        chartTablet = new Chart(document.getElementById('chart-tablet'), {
            type: 'doughnut',
            data: { labels: motivos, datasets: [{ data: motivos.map(m => d.tablet_por_motivo[m]), backgroundColor: [cInfo, cOk, cWarn, cUrg, cAccent] }] },
        });
    }

    renderHeatmap(d.heatmap);
    window._tenLoaded = true;
}

function renderHeatmap(matrix) {
    const cont = document.getElementById('heat');
    const dias = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    const max = Math.max(1, ...matrix.flat());
    const rgb = accentRgb();
    let html = '<div></div>';
    for (let h = 0; h < 24; h++) html += `<div class="h-label" style="text-align:center;">${h}</div>`;
    for (let d = 0; d < 7; d++) {
        html += `<div class="h-label">${dias[d]}</div>`;
        for (let h = 0; h < 24; h++) {
            const v = matrix[d][h];
            const op = v / max;
            const bg = op > 0 ? `background:rgba(${rgb},${(0.12 + op * 0.83).toFixed(2)});` : '';
            html += `<div class="h-cell" style="${bg}" title="${dias[d]} ${h}h: ${v} msjs"></div>`;
        }
    }
    cont.innerHTML = html;
}

// ── Helpers ───────────────────────────────────────────
// Semáforo SLA: verde ≥80%, neutro ≥50%, rojo <50% (solo si hubo tomadas).
function pintarSla(cardId, valId, pct, total) {
    const card = document.getElementById(cardId);
    document.getElementById(valId).textContent = pct + '%';
    card.classList.remove('ok', 'warn', 'error');
    if (!total) return;                     // sin datos, sin color
    if (pct >= 80)      card.classList.add('ok');
    else if (pct < 50)  card.classList.add('error');
    else                card.classList.add('warn');
}
// Color de acento del tema como [r,g,b] — para el heatmap (antes hardcodeado
// en azul, quedaba mal en dark). Un div oculto convierte cualquier formato
// CSS a rgb() computado.
function accentRgb() {
    const probe = document.createElement('div');
    probe.style.color = 'var(--v2-accent)';
    probe.style.display = 'none';
    document.body.appendChild(probe);
    const m = getComputedStyle(probe).color.match(/\d+/g) || [26, 86, 196];
    probe.remove();
    return m.slice(0, 3).join(',');
}
function fmtSeg(s) {
    if (s === null || s === undefined) return '—';
    if (s < 60) return s + 's';
    if (s < 3600) return Math.round(s / 60) + ' min';
    return (s / 3600).toFixed(1) + ' h';
}
function renderKV(obj, vacio) {
    const ks = Object.keys(obj || {});
    if (!ks.length) return `<div class="rp-empty">${vacio}</div>`;
    return '<table class="rp-table" style="margin:0;">' + ks.map(k =>
        `<tr><td>${escapeHtml(k)}</td><td class="num">${obj[k]}</td></tr>`
    ).join('') + '</table>';
}
function unionDias(...maps) {
    const s = new Set();
    maps.forEach(m => Object.keys(m || {}).forEach(k => s.add(k)));
    return Array.from(s).sort();
}
function escapeHtml(s) { return String(s ?? '').replace(/[<>&"']/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[c])); }

// ── Tipos de consulta ─────────────────────────────────
let chartTiposSem = null, TP = null, tpCasos = null;
function tpRango() {
    const d = parseInt(document.getElementById('tp-rango').value, 10);
    const to = new Date(), from = new Date(Date.now() - d * 86400000);
    const f = x => x.toLocaleDateString('en-CA');   // YYYY-MM-DD en hora local
    return `from=${f(from)}&to=${f(to)}` + (document.getElementById('tp-area').value ? '&area=' + document.getElementById('tp-area').value : '');
}
// Color fijo por familia (TiposConsulta::FAMILIAS), en su variante del tema activo.
const famColor = f => document.documentElement.dataset.theme === 'dark' ? f.color_oscuro : f.color;
const fmtPct = p => p === null || p === undefined ? '—' : String(p).replace('.', ',') + ' %';
const fmtN = n => (n ?? 0).toLocaleString('es-AR');
function fmtMin(m) { if (m === null || m === undefined) return '—'; return m < 60 ? m + ' min' : (m / 60).toFixed(1).replace('.', ',') + ' h'; }

async function cargarTipos() {
    window._tpLoaded = true;
    document.getElementById('tp-loading').style.display = 'block';
    let d;
    try {
        const r = await fetch('/admin/estadisticas/tipos?' + tpRango(), { headers: { Accept: 'application/json' } });
        d = await r.json();
        if (!r.ok) throw new Error(d.message || 'HTTP ' + r.status);
    } catch (e) { document.getElementById('tp-loading').textContent = 'No se pudo cargar: ' + e.message; return; }
    TP = d;
    document.getElementById('tp-loading').style.display = 'none';
    document.getElementById('tp-content').style.display = 'block';

    const fams = d.familias.filter(f => f.familia !== 'ruido');
    const top = fams[0];
    const fuera = fams.reduce((a, f) => a + f.n * f.pct_fuera / 100, 0);
    const sinClas = fams.find(f => f.familia === 'sin_clasificar');
    document.getElementById('tp-cards').innerHTML = [
        ['Consultas', fmtN(d.consultas), `+ ${fmtN(d.ruido)} saludos / sin consulta`],
        ['Tipo más frecuente', top ? top.label : '—', top ? fmtPct(top.pct) + ' de las consultas' : ''],
        ['Fuera de horario', d.consultas ? (100 * fuera / d.consultas).toFixed(1).replace('.', ',') + ' %' : '—', 'noches, sábados tarde y domingos'],
        ['Sin clasificar', sinClas ? fmtPct(sinClas.pct) : '0 %', 'la IA no supo qué era'],
    ].map(([l, v, s]) => `<div class="rp-card"><div class="label">${l}</div><div class="val" style="font-size:${String(v).length > 10 ? 17 : 24}px">${escapeHtml(v)}</div><div class="sub">${s}</div></div>`).join('');

    // Semanal apilado por familia (sin ruido).
    const semanas = Object.keys(d.semanas);
    if (chartTiposSem) chartTiposSem.destroy();
    chartTiposSem = new Chart(document.getElementById('chart-tipos-sem'), {
        type: 'bar',
        data: {
            labels: semanas.map((s, i) => 'Sem. ' + s.slice(8, 10) + '/' + s.slice(5, 7) + (i === semanas.length - 1 && Date.now() - new Date(s + 'T00:00') < 7 * 86400000 ? ' (en curso)' : '')),
            datasets: fams.map(f => ({ label: f.label, backgroundColor: famColor(f), borderColor: css('--v2-bg-card'), borderWidth: 1, data: semanas.map(s => d.semanas[s][f.familia] || 0) })),
        },
        options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } },
    });

    // Por código, agrupado por familia.
    const max = Math.max(1, ...d.codigos.filter(c => c.codigo !== 'IGNORAR').map(c => c.n));
    document.getElementById('tp-codigos').innerHTML = fams.map(f => {
        const cods = d.codigos.filter(c => c.familia === f.familia);
        return `<div style="margin-bottom:10px;">
            <div style="display:flex;align-items:center;gap:6px;font-weight:600;font-size:12.5px;cursor:pointer;" onclick="verCasos('${f.familia}', null)">
                <span style="width:10px;height:10px;border-radius:2px;background:${famColor(f)};display:inline-block;"></span>${escapeHtml(f.label)}
                <span style="margin-left:auto;font-family:'JetBrains Mono',monospace;">${fmtN(f.n)} · ${fmtPct(f.pct)}</span></div>
            ${cods.length > 1 || cods[0]?.etiqueta !== f.label ? cods.map(c => `
                <div style="display:grid;grid-template-columns:1fr 110px 54px;gap:8px;align-items:center;font-size:12px;padding:2px 0 2px 16px;cursor:pointer;" onclick="verCasos(null, '${c.codigo}')" title="${c.pct_fuera} % fuera de horario">
                    <span>${escapeHtml(c.etiqueta)}</span>
                    <span style="height:8px;background:var(--v2-bg-hover);border-radius:3px;overflow:hidden;"><span style="display:block;height:100%;width:${(100 * c.n / max).toFixed(1)}%;background:${famColor(f)};"></span></span>
                    <span style="text-align:right;font-family:'JetBrains Mono',monospace;">${fmtN(c.n)}</span></div>`).join('') : ''}
        </div>`;
    }).join('') || '<div class="rp-empty">Sin datos en el período.</div>';

    // Por área: % de cada familia dentro del área.
    const areas = Object.keys(d.areas);
    document.getElementById('tp-areas').innerHTML = areas.length ? `<table class="rp-table"><thead><tr><th>Tipo</th>${areas.map(a => `<th class="num">${escapeHtml(d.area_labels[a] || a)}</th>`).join('')}</tr></thead><tbody>
        ${fams.map(f => `<tr><td>${escapeHtml(f.label)}</td>${areas.map(a => {
            const tot = Object.entries(d.areas[a]).filter(([k]) => k !== 'ruido').reduce((s, [, n]) => s + n, 0);
            const n = d.areas[a][f.familia] || 0;
            return `<td class="num">${n ? (100 * n / tot).toFixed(0) + ' %' : '–'}</td>`;
        }).join('')}</tr>`).join('')}</tbody></table>` : '<div class="rp-empty">Sin datos.</div>';

    document.getElementById('tp-tiempos').innerHTML = d.tiempos.length ? `<table class="rp-table"><thead><tr><th>Tipo</th><th class="num">Casos</th><th class="num">Mediana</th><th class="num">&lt; 1 h</th><th class="num">Sin resp. 48 h</th></tr></thead><tbody>
        ${d.tiempos.map(t => `<tr><td>${escapeHtml(t.label)}</td><td class="num">${fmtN(t.casos)}</td><td class="num">${fmtMin(t.mediana_min)}</td><td class="num">${fmtPct(t.pct_1h)}</td><td class="num">${fmtPct(t.sin_resp_48h)}</td></tr>`).join('')}
        </tbody></table><p style="font-size:10.5px;color:var(--v2-text-mute);margin:6px 0 0;">Desde que la IA clasifica hasta el primer mensaje de una persona. En la historia importada solo se conocen los casos derivados; desde el 28/09, todos.</p>`
        : '<div class="rp-empty">Todavía no hay casos asociados a conversaciones.</div>';

    const q = d.calidad;
    document.getElementById('tp-calidad').innerHTML = `<table class="rp-table"><tbody>
        <tr><td>Clasificaciones con confianza baja</td><td class="num">${fmtPct(q.pct_baja)}</td></tr>
        <tr><td>"Sin clasificar" porque la IA no respondió</td><td class="num">${fmtN(q.sin_ia)}</td></tr>
        <tr><td>"Sin clasificar" por decisión de la IA</td><td class="num">${fmtN(q.fallback)}</td></tr>
        <tr><td>Revisadas por supervisión</td><td class="num">${fmtN(q.revisadas)}</td></tr>
        <tr><td><b>Acierto de la IA</b> (sobre lo revisado)</td><td class="num"><b>${q.pct_acierto === null ? '—' : fmtPct(q.pct_acierto)}</b></td></tr>
        </tbody></table><p style="font-size:10.5px;color:var(--v2-text-mute);margin:6px 0 0;">Abrí un tipo, revisá casos y confirmá o corregí la etiqueta: con 50-100 revisados ya se sabe cuánto confiar en estos números.</p>`;

    if (tpCasos) verCasos();
}

async function verCasos(familia, codigo) {
    if (familia !== undefined || codigo !== undefined) tpCasos = { familia, codigo };
    if (!tpCasos) return;
    const wrap = document.getElementById('tp-detalle-wrap'); wrap.style.display = 'block';
    const titulo = tpCasos.codigo ? (TP.codigos.find(c => c.codigo === tpCasos.codigo)?.etiqueta || tpCasos.codigo)
                                  : (TP.fam_labels[tpCasos.familia]?.label || tpCasos.familia);
    document.getElementById('tp-detalle-h3').textContent = 'Casos: ' + titulo;
    const qs = tpRango() + (tpCasos.codigo ? '&codigo=' + tpCasos.codigo : '&familia=' + tpCasos.familia)
        + (document.getElementById('tp-sin-revisar').checked ? '&sin_revisar=1' : '');
    const r = await fetch('/admin/estadisticas/tipos/detalle?' + qs, { headers: { Accept: 'application/json' } });
    const d = await r.json();
    const opts = Object.entries(d.codigos).map(([k, v]) => `<option value="${k}">${escapeHtml(v)}</option>`).join('');
    document.getElementById('tp-detalle').innerHTML = d.filas.length ? `<table class="rp-table"><thead><tr><th>Fecha</th><th>Paciente</th><th>Qué consultó</th><th>Etiqueta</th><th></th></tr></thead><tbody>
        ${d.filas.map(f => `<tr>
            <td style="white-space:nowrap;">${f.fecha}<div style="font-size:10.5px;color:var(--v2-text-mute);">${escapeHtml(TP.area_labels[f.area] || f.area)}</div></td>
            <td><a href="/v2/atencion/${f.area}?conv=${f.conv_id}" target="_blank">${escapeHtml(f.nombre || 'Conversación')}</a></td>
            <td style="max-width:380px;font-size:12px;">${escapeHtml((f.resumen || '').slice(0, 260))}</td>
            <td><select class="v2-field" style="font-size:12px;padding:3px 6px;" onchange="corregir(${f.id}, this.value)" data-orig="${f.codigo}">
                ${opts.replace(`value="${f.corregido || f.codigo}"`, `value="${f.corregido || f.codigo}" selected`)}</select>
                ${f.corregido ? `<div style="font-size:10.5px;color:var(--v2-ok);">✓ revisada${f.corregido !== f.codigo ? ' (la IA dijo ' + escapeHtml(d.codigos[f.codigo] || f.codigo) + ')' : ''}</div>` : ''}</td>
            <td>${f.corregido ? '' : `<button class="v2-btn sm" onclick="corregir(${f.id}, '${f.codigo}')" title="La etiqueta de la IA es correcta">✓ Bien</button>`}</td>
        </tr>`).join('')}</tbody></table>`
        : '<div class="rp-empty">No hay casos asociados a una conversación en este filtro.</div>';
    wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function corregir(id, codigo) {
    const r = await fetch(`/admin/estadisticas/tipos/${id}/corregir`, { method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ codigo }) });
    if (!r.ok) { v2toast('No se pudo guardar', 'err'); return; }
    v2toast('Guardado');
    cargarTipos();
}

// Init: respetar la tab del hash (#secretarias / #tendencias) al recargar.
cargarHoy();
const hashTab = location.hash.replace('#', '');
if (hashTab === 'secretarias' || hashTab === 'tendencias' || hashTab === 'tipos') activarTab(hashTab);

// Auto-refresh de "Hoy" cada 60s (alineado con el cache del endpoint), solo
// si la pestaña del browser está visible y la tab activa es Hoy — es una
// pantalla de monitoreo, no debería requerir F5.
setInterval(() => {
    if (document.visibilityState !== 'visible') return;
    if (!document.getElementById('sec-hoy').classList.contains('active')) return;
    cargarHoy();
}, 60_000);
</script>
@endpush
