@extends('layouts.app')
@section('title', 'Admin · Estado bot')

@section('content')
@include('admin._nav')

<style>
.bot-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 24px;
    max-width: 720px;
}
.bot-row { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
.bot-dot {
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--muted);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--muted) 18%, transparent);
}
.bot-dot.ok    { background: var(--success); box-shadow: 0 0 0 4px color-mix(in srgb, var(--success) 22%, transparent); }
.bot-dot.warn  { background: var(--warning); box-shadow: 0 0 0 4px color-mix(in srgb, var(--warning) 22%, transparent); }
.bot-dot.err   { background: var(--error);   box-shadow: 0 0 0 4px color-mix(in srgb, var(--error)   22%, transparent); }
.bot-status { font-size: 18px; font-weight: 700; }
.bot-detail { display: grid; grid-template-columns: 130px 1fr; gap: 8px 16px; font-size: 13px; }
.bot-detail dt { color: var(--muted); }
.bot-detail dd { color: var(--text); margin: 0; }
.qr-wrap {
    margin-top: 18px;
    padding: 16px;
    background: #fff;
    border-radius: 8px;
    text-align: center;
}
.qr-wrap img { max-width: 280px; height: auto; }
.qr-warn { font-size: 12px; color: var(--warning); margin-bottom: 10px; font-weight: 600; }
</style>

<h2 style="font-size:16px;font-weight:700;margin-bottom:14px;">Estado del bot WhatsApp</h2>

<div class="bot-card" id="card">
    <div class="bot-row">
        <span class="bot-dot" id="dot"></span>
        <div>
            <div class="bot-status" id="status-txt">Cargando…</div>
            <div style="font-size:11px;color:var(--muted);margin-top:2px;" id="last-update"></div>
        </div>
    </div>

    <dl class="bot-detail">
        <dt>Número conectado</dt><dd id="phone">—</dd>
        <dt>Uptime</dt><dd id="uptime">—</dd>
        <dt>Endpoint</dt><dd style="font-family:monospace;font-size:12px;">{{ config('app.bot_url') }}</dd>
    </dl>

    <div id="qr-section" style="display:none;">
        <div class="qr-warn">⚠ Bot esperando escaneo de QR — abrí WhatsApp en el celular y escaneá:</div>
        <div class="qr-wrap"><img id="qr-img" src=""></div>
    </div>
</div>

<h2 style="font-size:16px;font-weight:700;margin:28px 0 14px;">Tareas programadas</h2>
<div class="bot-card" style="padding:16px 20px;">
    <table id="tareas-tabla" style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">
                <th style="padding:8px 6px;text-align:center;width:36px;"></th>
                <th style="padding:8px 6px;text-align:left;">Tarea</th>
                <th style="padding:8px 6px;text-align:left;">Hora diaria</th>
                <th style="padding:8px 6px;text-align:left;">Última corrida</th>
                <th style="padding:8px 6px;text-align:left;">Artefacto</th>
            </tr>
        </thead>
        <tbody id="tareas-tbody">
            <tr><td colspan="5" style="padding:14px;color:var(--muted);text-align:center;">Cargando…</td></tr>
        </tbody>
    </table>
</div>

<script>
async function refreshStatus() {
    try {
        const r = await fetch('/admin/bot/status', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Error');

        const dot       = document.getElementById('dot');
        const statusTxt = document.getElementById('status-txt');
        const qrSection = document.getElementById('qr-section');
        const qrImg     = document.getElementById('qr-img');

        // Estados conocidos: 'iniciando', 'autenticado', 'listo', 'desconectado', 'error'
        dot.className = 'bot-dot';
        if (d.status === 'listo') {
            dot.classList.add('ok');
            statusTxt.textContent = 'Conectado y listo';
        } else if (d.status === 'iniciando' || d.status === 'autenticado') {
            dot.classList.add('warn');
            statusTxt.textContent = 'Iniciando…';
        } else if (d.qrDataUrl) {
            dot.classList.add('warn');
            statusTxt.textContent = 'Esperando QR';
        } else {
            dot.classList.add('err');
            statusTxt.textContent = 'Desconectado';
        }

        document.getElementById('phone').textContent  = d.phone ?? '—';
        document.getElementById('uptime').textContent = d.uptime ?? '—';
        document.getElementById('last-update').textContent = 'Actualizado ' + new Date().toLocaleTimeString();

        if (d.qrDataUrl) {
            qrImg.src = d.qrDataUrl;
            qrSection.style.display = '';
        } else {
            qrSection.style.display = 'none';
        }
    } catch (e) {
        document.getElementById('dot').className = 'bot-dot err';
        document.getElementById('status-txt').textContent = 'Sin contacto con el bot';
        document.getElementById('last-update').textContent = 'Reintentando…';
    }
}

refreshStatus();
setInterval(refreshStatus, 5000);

// ── Tareas programadas ──────────────────────────────────────
function fmtRelativo(iso) {
    if (!iso) return '<span style="color:var(--muted);">nunca</span>';
    const d = new Date(iso);
    const seg = (Date.now() - d.getTime()) / 1000;
    if (seg < 60)        return `hace ${Math.round(seg)}s`;
    if (seg < 3600)      return `hace ${Math.round(seg/60)} min`;
    if (seg < 86400)     return `hace ${Math.round(seg/3600)} h`;
    return `hace ${Math.round(seg/86400)} d (${d.toLocaleString('es-AR', {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})})`;
}
function fmtBytes(n) {
    if (!n && n !== 0) return '—';
    if (n < 1024)      return n + ' B';
    if (n < 1048576)   return (n/1024).toFixed(1) + ' KB';
    return (n/1048576).toFixed(1) + ' MB';
}
async function refreshTareas() {
    try {
        const r = await fetch('/admin/tareas', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Error');
        document.getElementById('tareas-tbody').innerHTML = (d.tareas || []).map(t => {
            const cls = { ok: 'ok', atrasado: 'err', nunca_corrio: 'err' }[t.estado] || 'warn';
            const titulo = { ok: 'Ejecutada en horario', atrasado: 'Atrasada — revisar', nunca_corrio: 'Nunca corrió' }[t.estado] ?? t.estado;
            return `<tr style="border-top:1px solid var(--border);">
                <td style="padding:9px 6px;text-align:center;"><span class="bot-dot ${cls}" style="width:10px;height:10px;display:inline-block;" title="${titulo}"></span></td>
                <td style="padding:9px 6px;font-weight:600;">${t.titulo}</td>
                <td style="padding:9px 6px;color:var(--muted);">${t.hora_diaria}</td>
                <td style="padding:9px 6px;color:var(--muted);">${fmtRelativo(t.ultimo_run)}</td>
                <td style="padding:9px 6px;color:var(--muted);font-family:monospace;font-size:12px;">
                    ${t.artefacto ? `${t.artefacto} <span style="opacity:.6;">(${fmtBytes(t.tamanio_bytes)})</span>` : '—'}
                </td>
            </tr>`;
        }).join('') || '<tr><td colspan="5" style="padding:14px;color:var(--muted);text-align:center;">Sin datos</td></tr>';
    } catch (e) {
        document.getElementById('tareas-tbody').innerHTML =
            '<tr><td colspan="5" style="padding:14px;color:var(--error);text-align:center;">No se pudo leer el directorio de backups.</td></tr>';
    }
}
refreshTareas();
setInterval(refreshTareas, 60000);   // refresh cada 1 min
</script>
@endsection
