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

<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:14px;gap:12px;flex-wrap:wrap;">
    <h2 style="font-size:16px;font-weight:700;">Bots de WhatsApp <span style="font-weight:400;color:var(--muted);font-size:13px;">— uno por área (número)</span></h2>
    <span style="font-size:11px;color:var(--muted);" id="bots-update"></span>
</div>

<div id="bots-container" style="display:flex;flex-wrap:wrap;gap:16px;">
    <div style="color:var(--muted);font-size:13px;">Cargando…</div>
</div>

<p style="font-size:12px;color:var(--muted);margin-top:10px;line-height:1.5;">
    Cuando un bot esté <strong>esperando QR</strong> aparece el código acá — escaneá con WhatsApp del celular de ese número
    (Ajustes → Dispositivos vinculados → Vincular un dispositivo). La sesión queda guardada; no hay que re-escanear salvo que se cierre.
</p>

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
function escHtml(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }

async function refreshStatus() {
    const cont = document.getElementById('bots-container');
    try {
        const r = await fetch('/admin/bot/status', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Error');
        const labels = d.labels || {};
        const areas = Object.keys(labels);
        cont.innerHTML = areas.map(area => {
            const b = (d.bots && d.bots[area]) || { ok: false };
            let dotCls, statusTxt, qrHtml = '';
            if (!b.ok) {
                dotCls = 'err'; statusTxt = b.error || 'Sin contacto con el bot';
            } else if (b.status === 'listo') {
                dotCls = 'ok'; statusTxt = 'Conectado' + (b.phone ? ' · +' + escHtml(b.phone) : '');
            } else if (b.status === 'iniciando' || b.status === 'autenticado') {
                dotCls = 'warn'; statusTxt = 'Iniciando…';
            } else if (b.qrDataUrl) {
                dotCls = 'warn'; statusTxt = 'Esperando escaneo de QR';
                qrHtml = `<div class="qr-warn" style="margin-top:14px;">⚠ Escaneá con el celular de este número:</div>
                          <div class="qr-wrap"><img src="${b.qrDataUrl}" style="max-width:210px;width:100%;height:auto;"></div>`;
            } else {
                dotCls = 'err'; statusTxt = escHtml(b.status || 'Desconectado');
            }
            return `<div class="bot-card" style="flex:1 1 280px;min-width:280px;max-width:340px;">
                <div class="bot-row" style="margin-bottom:14px;">
                    <span class="bot-dot ${dotCls}"></span>
                    <div>
                        <div class="bot-status" style="font-size:15px;">${escHtml(labels[area])}</div>
                        <div style="font-size:12px;color:var(--muted);margin-top:2px;">${statusTxt}</div>
                    </div>
                </div>
                <dl class="bot-detail">
                    <dt>Número</dt><dd>${b.phone ? '+' + escHtml(b.phone) : '—'}</dd>
                    <dt>Uptime</dt><dd>${escHtml(b.uptime) || '—'}</dd>
                    <dt>Área</dt><dd style="font-family:monospace;font-size:12px;">${escHtml(area)}</dd>
                </dl>
                ${qrHtml}
            </div>`;
        }).join('') || '<div style="color:var(--muted);font-size:13px;">Sin bots configurados.</div>';
        document.getElementById('bots-update').textContent = 'Actualizado ' + new Date().toLocaleTimeString();
    } catch (e) {
        cont.innerHTML = '<div style="color:var(--error);font-size:13px;">Sin contacto con el panel del bot. Reintentando…</div>';
        document.getElementById('bots-update').textContent = '';
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
