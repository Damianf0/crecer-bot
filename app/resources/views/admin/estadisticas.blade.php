@extends('layouts.app')
@section('title', 'Admin · Estadísticas')

@section('content')
@include('admin._nav')

<style>
.st-tabs { display:flex; gap:6px; margin-bottom:18px; }
.st-tab { padding:8px 14px; border:1px solid var(--border); background:var(--surface);
          color:var(--muted); cursor:pointer; border-radius:7px; font-size:13px; }
.st-tab.active { background:var(--card); color:var(--text); border-color:var(--info); font-weight:600; }
.st-section { display:none; }
.st-section.active { display:block; }

.st-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin-bottom:20px; }
.st-card { background:var(--card); border:1px solid var(--border); border-radius:9px; padding:14px 16px; }
.st-card .label { font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:var(--muted); margin-bottom:4px; }
.st-card .val   { font-size:26px; font-weight:700; }
.st-card .sub   { font-size:11px; color:var(--muted); margin-top:2px; }
.st-card.warn .val  { color:var(--warning); }
.st-card.error .val { color:var(--error); }
.st-card.ok .val    { color:var(--success); }

.st-block { background:var(--card); border:1px solid var(--border); border-radius:9px; padding:16px; margin-bottom:16px; }
.st-block h3 { font-size:13px; font-weight:600; margin:0 0 12px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; }

.st-table { width:100%; border-collapse:collapse; font-size:13px; }
.st-table th, .st-table td { text-align:left; padding:8px 10px; border-bottom:1px solid var(--border); }
.st-table th { font-weight:600; color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.4px; cursor:pointer; user-select:none; }
.st-table tbody tr:hover { background:color-mix(in srgb, var(--info) 5%, transparent); }
.st-table td.num { text-align:right; font-variant-numeric:tabular-nums; }

.st-filtros { display:flex; gap:10px; align-items:center; margin-bottom:14px; flex-wrap:wrap; }
.st-filtros input[type=date] { padding:6px 10px; border:1px solid var(--border); background:var(--surface); color:var(--text); border-radius:6px; font-size:13px; }
.st-filtros button { padding:6px 14px; background:var(--info); color:#fff; border:none; border-radius:6px; font-size:13px; cursor:pointer; }

.st-heat { display:grid; grid-template-columns:60px repeat(24, 1fr); gap:2px; font-size:10px; }
.st-heat .h-label { color:var(--muted); text-align:right; padding-right:6px; line-height:18px; }
.st-heat .h-cell  { height:18px; border-radius:2px; background:var(--surface); }

.st-loading { color:var(--muted); padding:20px; text-align:center; font-size:13px; }
.st-empty   { color:var(--muted); padding:14px; text-align:center; font-size:12px; font-style:italic; }
</style>

<h2 style="font-size:16px;font-weight:700;margin-bottom:14px;">Estadísticas</h2>

<div class="st-tabs">
    <button class="st-tab active" data-tab="hoy">Hoy</button>
    <button class="st-tab"        data-tab="secretarias">Por secretaria</button>
    <button class="st-tab"        data-tab="tendencias">Tendencias</button>
</div>

{{-- ── Tab: Hoy ──────────────────────────────────────────── --}}
<div class="st-section active" id="sec-hoy">
    <div class="st-loading" id="hoy-loading">Cargando…</div>
    <div id="hoy-content" style="display:none;">

        <h3 style="margin:0 0 8px;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;">Estado actual</h3>
        <div class="st-cards">
            <div class="st-card"><div class="label">Backlog</div><div class="val" id="v-backlog">–</div><div class="sub">sin tomar, no leídas</div></div>
            <div class="st-card"><div class="label">En proceso</div><div class="val" id="v-en-proceso">–</div><div class="sub">asignadas</div></div>
            <div class="st-card warn"><div class="label">Urgentes</div><div class="val" id="v-urgentes">–</div><div class="sub">activas</div></div>
            <div class="st-card"><div class="label">En sala</div><div class="val" id="v-en-sala">–</div><div class="sub">esperando atención</div></div>
        </div>

        <h3 style="margin:14px 0 8px;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;">SLA del día</h3>
        <div class="st-cards">
            <div class="st-card ok"><div class="label">Tomadas hoy</div><div class="val" id="v-sla-tomadas">–</div></div>
            <div class="st-card"><div class="label">% en &lt; 5 min</div><div class="val" id="v-sla-5">–</div></div>
            <div class="st-card"><div class="label">% en &lt; 15 min</div><div class="val" id="v-sla-15">–</div></div>
            <div class="st-card"><div class="label">% en &lt; 30 min</div><div class="val" id="v-sla-30">–</div></div>
            <div class="st-card"><div class="label">Mediana</div><div class="val" id="v-sla-med">–</div><div class="sub">tiempo de toma</div></div>
        </div>

        <h3 style="margin:14px 0 8px;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;">Volumen del día</h3>
        <div class="st-cards">
            <div class="st-card"><div class="label">Mensajes recibidos</div><div class="val" id="v-msg-in">–</div></div>
            <div class="st-card"><div class="label">Mensajes enviados</div><div class="val" id="v-msg-out">–</div></div>
            <div class="st-card"><div class="label">Conversaciones nuevas</div><div class="val" id="v-convs-nuevas">–</div></div>
            <div class="st-card ok"><div class="label">Conversaciones resueltas</div><div class="val" id="v-convs-cerradas">–</div></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="st-block"><h3>Llegadas a recepción (Tablet)</h3><div id="v-tablet">–</div></div>
            <div class="st-block"><h3>Documentos del legajo</h3><div id="v-docs">–</div></div>
        </div>

        <h3 style="margin:14px 0 8px;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;">Cobertura LLM (resumen de conversaciones)</h3>
        <div class="st-cards">
            <div class="st-card ok"><div class="label">Cobertura</div><div class="val" id="v-llm-cov">–</div><div class="sub">de las que ameritan resumen</div></div>
            <div class="st-card"><div class="label">Con resumen</div><div class="val" id="v-llm-ok">–</div></div>
            <div class="st-card"><div class="label">Sin resumen</div><div class="val" id="v-llm-no">–</div><div class="sub">evaluadas, no procesadas / fallaron</div></div>
            <div class="st-card warn"><div class="label">Pendientes de evaluar</div><div class="val" id="v-llm-pend">–</div><div class="sub">históricas sin tocar</div></div>
            <div class="st-card"><div class="label">Jobs en cola</div><div class="val" id="v-llm-jobs">–</div><div class="sub">queue 'resumen'</div></div>
        </div>

        <p style="font-size:11px;color:var(--muted);text-align:right;">Actualizado <span id="v-updated">–</span> · refresh manual abajo</p>
        <p style="text-align:center;"><button onclick="cargarHoy()" style="padding:6px 14px;background:var(--info);color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;">↻ Refrescar</button></p>
    </div>
</div>

{{-- ── Tab: Por secretaria ───────────────────────────────── --}}
<div class="st-section" id="sec-secretarias">
    <div class="st-filtros">
        <label style="font-size:12px;color:var(--muted);">Desde:</label>
        <input type="date" id="sec-from">
        <label style="font-size:12px;color:var(--muted);">Hasta:</label>
        <input type="date" id="sec-to">
        <button onclick="cargarSecretarias()">Aplicar</button>
        <span style="font-size:11px;color:var(--muted);margin-left:auto;">Solo se listan usuarios con actividad en el rango.</span>
    </div>
    <div class="st-block">
        <table class="st-table" id="sec-table">
            <thead>
                <tr>
                    <th>Secretaria</th>
                    <th class="num">Tomadas</th>
                    <th class="num">Resueltas</th>
                    <th class="num">Delegadas</th>
                    <th class="num">Reabiertas</th>
                    <th class="num">Mensajes env.</th>
                    <th class="num">T. respuesta</th>
                    <th class="num">T. resolución</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="8" class="st-loading">Cargando…</td></tr></tbody>
        </table>
    </div>
</div>

{{-- ── Tab: Tendencias ───────────────────────────────────── --}}
<div class="st-section" id="sec-tendencias">
    <div class="st-filtros">
        <label style="font-size:12px;color:var(--muted);">Desde:</label>
        <input type="date" id="ten-from">
        <label style="font-size:12px;color:var(--muted);">Hasta:</label>
        <input type="date" id="ten-to">
        <button onclick="cargarTendencias()">Aplicar</button>
    </div>

    <div class="st-block">
        <h3>Conversaciones nuevas por día</h3>
        <canvas id="chart-convs" height="80"></canvas>
    </div>
    <div class="st-block">
        <h3>Mensajes recibidos vs enviados</h3>
        <canvas id="chart-msjs" height="80"></canvas>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="st-block">
            <h3>Llegadas Tablet por motivo</h3>
            <canvas id="chart-tablet" height="160"></canvas>
        </div>
        <div class="st-block">
            <h3>Mensajes entrantes por hora (heatmap)</h3>
            <div class="st-heat" id="heat"></div>
            <p style="font-size:10px;color:var(--muted);margin-top:6px;">Día de la semana × hora del día. Más oscuro = más mensajes.</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Tabs ──────────────────────────────────────────────
document.querySelectorAll('.st-tab').forEach(b => b.onclick = () => {
    document.querySelectorAll('.st-tab').forEach(x => x.classList.remove('active'));
    document.querySelectorAll('.st-section').forEach(x => x.classList.remove('active'));
    b.classList.add('active');
    const tab = b.dataset.tab;
    document.getElementById('sec-' + tab).classList.add('active');
    if (tab === 'secretarias' && !window._secLoaded) cargarSecretarias();
    if (tab === 'tendencias' && !window._tenLoaded) cargarTendencias();
});

// ── Hoy ───────────────────────────────────────────────
async function cargarHoy() {
    document.getElementById('hoy-loading').style.display = 'block';
    document.getElementById('hoy-content').style.display = 'none';
    try {
        const r = await fetch('/admin/estadisticas/hoy');
        const d = await r.json();
        document.getElementById('v-backlog').textContent = d.vivo.backlog;
        document.getElementById('v-en-proceso').textContent = d.vivo.en_proceso;
        document.getElementById('v-urgentes').textContent = d.vivo.urgentes;
        document.getElementById('v-en-sala').textContent = d.vivo.en_sala;

        document.getElementById('v-sla-tomadas').textContent = d.sla.tomadas_total;
        document.getElementById('v-sla-5').textContent  = d.sla.pct_5min  + '%';
        document.getElementById('v-sla-15').textContent = d.sla.pct_15min + '%';
        document.getElementById('v-sla-30').textContent = d.sla.pct_30min + '%';
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
            document.getElementById('v-llm-pend').textContent = d.llm.pendientes_eval;
            document.getElementById('v-llm-jobs').textContent = d.llm.jobs_en_cola;
        }

        document.getElementById('v-updated').textContent = new Date(d.updated_at).toLocaleTimeString('es-AR');
        document.getElementById('hoy-loading').style.display = 'none';
        document.getElementById('hoy-content').style.display = 'block';
    } catch (e) {
        document.getElementById('hoy-loading').textContent = 'Error cargando datos';
    }
}

// ── Por secretaria ────────────────────────────────────
async function cargarSecretarias() {
    const tbody = document.querySelector('#sec-table tbody');
    tbody.innerHTML = '<tr><td colspan="8" class="st-loading">Cargando…</td></tr>';
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
        if (!d.rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="st-empty">Sin actividad en el rango seleccionado</td></tr>';
            return;
        }
        tbody.innerHTML = d.rows.map(r => `
            <tr>
                <td>${escapeHtml(r.nombre)} <span style="color:var(--muted);font-size:11px;">${r.rol}</span></td>
                <td class="num">${r.tomadas}</td>
                <td class="num">${r.resueltas}</td>
                <td class="num">${r.delegadas}</td>
                <td class="num">${r.reabiertas || '—'}</td>
                <td class="num">${r.msj_enviados}</td>
                <td class="num">${r.t_resp_medio_seg !== null ? fmtSeg(r.t_resp_medio_seg) : '—'}</td>
                <td class="num">${r.t_resol_medio_seg !== null ? fmtSeg(r.t_resol_medio_seg) : '—'}</td>
            </tr>
        `).join('');
        window._secLoaded = true;
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" class="st-empty">Error al cargar</td></tr>';
    }
}

// ── Tendencias ────────────────────────────────────────
let chartConvs, chartMsjs, chartTablet;

async function cargarTendencias() {
    const from = document.getElementById('ten-from').value;
    const to   = document.getElementById('ten-to').value;
    const qs = new URLSearchParams();
    if (from) qs.set('from', from);
    if (to)   qs.set('to', to);
    const r = await fetch('/admin/estadisticas/tendencias?' + qs);
    const d = await r.json();
    document.getElementById('ten-from').value = d.from;
    document.getElementById('ten-to').value   = d.to;

    const dias = unionDias(d.convs_por_dia, d.msj_in_por_dia, d.msj_out_por_dia);

    if (chartConvs) chartConvs.destroy();
    chartConvs = new Chart(document.getElementById('chart-convs'), {
        type: 'line',
        data: { labels: dias, datasets: [{ label: 'Conversaciones', data: dias.map(d2 => d.convs_por_dia[d2] || 0), borderColor: '#1a56c4', backgroundColor: '#1a56c422', tension: .3, fill: true }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    if (chartMsjs) chartMsjs.destroy();
    chartMsjs = new Chart(document.getElementById('chart-msjs'), {
        type: 'line',
        data: { labels: dias, datasets: [
            { label: 'Recibidos', data: dias.map(d2 => d.msj_in_por_dia[d2] || 0), borderColor: '#00875a', backgroundColor: '#00875a22', tension: .3, fill: true },
            { label: 'Enviados',  data: dias.map(d2 => d.msj_out_por_dia[d2] || 0), borderColor: '#c96a00', backgroundColor: '#c96a0022', tension: .3, fill: true },
        ] },
        options: { scales: { y: { beginAtZero: true } } }
    });

    const motivos = Object.keys(d.tablet_por_motivo);
    if (chartTablet) chartTablet.destroy();
    if (motivos.length) {
        chartTablet = new Chart(document.getElementById('chart-tablet'), {
            type: 'doughnut',
            data: { labels: motivos, datasets: [{ data: motivos.map(m => d.tablet_por_motivo[m]), backgroundColor: ['#1a56c4', '#00875a', '#c96a00', '#cc1f2e', '#7e57c2'] }] },
        });
    }

    renderHeatmap(d.heatmap);
    window._tenLoaded = true;
}

function renderHeatmap(matrix) {
    const cont = document.getElementById('heat');
    const dias = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    const max = Math.max(1, ...matrix.flat());
    let html = '<div></div>';
    for (let h = 0; h < 24; h++) html += `<div class="h-label" style="text-align:center;">${h}</div>`;
    for (let d = 0; d < 7; d++) {
        html += `<div class="h-label">${dias[d]}</div>`;
        for (let h = 0; h < 24; h++) {
            const v = matrix[d][h];
            const op = v / max;
            const bg = op > 0 ? `background:rgba(26,86,196,${0.1 + op * 0.85});` : '';
            html += `<div class="h-cell" style="${bg}" title="${dias[d]} ${h}h: ${v} msjs"></div>`;
        }
    }
    cont.innerHTML = html;
}

// ── Helpers ───────────────────────────────────────────
function fmtSeg(s) {
    if (s === null || s === undefined) return '—';
    if (s < 60) return s + 's';
    if (s < 3600) return Math.round(s / 60) + ' min';
    return (s / 3600).toFixed(1) + ' h';
}
function renderKV(obj, vacio) {
    const ks = Object.keys(obj || {});
    if (!ks.length) return `<div class="st-empty">${vacio}</div>`;
    return '<table class="st-table" style="margin:0;">' + ks.map(k =>
        `<tr><td>${escapeHtml(k)}</td><td class="num">${obj[k]}</td></tr>`
    ).join('') + '</table>';
}
function unionDias(...maps) {
    const s = new Set();
    maps.forEach(m => Object.keys(m || {}).forEach(k => s.add(k)));
    return Array.from(s).sort();
}
function escapeHtml(s) { return String(s ?? '').replace(/[<>&"']/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[c])); }

// Init
cargarHoy();
</script>
@endsection
