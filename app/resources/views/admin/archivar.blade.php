@extends($layout ?? 'layouts.v2')
@section('title', 'Admin · Archivar conversaciones')

@section('content')
@include('admin._nav')

<style>
.arc-wrap { max-width: 980px; }
.arc-info { font-size: 12px; color: var(--muted); margin-bottom: 16px; line-height: 1.55; }

.arc-card {
    background: var(--card); border: 1px solid var(--border); border-radius: 8px;
    padding: 18px; margin-bottom: 16px;
}
.arc-card h3 {
    font-size: 11px; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px;
}

/* Selector de modo */
.arc-modos { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.arc-modo {
    flex: 1; min-width: 220px; text-align: left; cursor: pointer;
    border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px;
    background: var(--bg); transition: .15s;
}
.arc-modo:hover { border-color: var(--accent); }
.arc-modo.sel {
    border-color: var(--accent);
    background: color-mix(in srgb, var(--accent) 6%, var(--bg));
}
.arc-modo .t { font-size: 13px; font-weight: 700; margin-bottom: 3px; }
.arc-modo .d { font-size: 11px; color: var(--muted); line-height: 1.4; }

.arc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; align-items: end; }
.arc-field { display: flex; flex-direction: column; gap: 4px; }
.arc-field label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .3px; }
.arc-field input, .arc-field select {
    padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px;
    background: var(--bg); color: var(--text); font-size: 13px; font-family: inherit;
}
.arc-field input:focus, .arc-field select:focus { outline: none; border-color: var(--accent); }
.arc-check { font-size: 12px; color: var(--text); display: flex; align-items: center; gap: 6px; margin-top: 14px; }
.arc-check .hint { color: var(--muted); font-size: 11px; }

.btn { padding: 9px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; font-family: inherit; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { filter: brightness(.92); }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; filter: none; }
.btn-ghost { background: var(--surface); border: 1px solid var(--border); color: var(--text); }
.btn-ghost:hover { background: var(--border); }
.btn-sm { padding: 5px 10px; border-radius: 5px; font-size: 11px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: var(--card); color: var(--text); }
.btn-sm:hover { border-color: var(--accent); }
.btn-sm:disabled { opacity: .45; cursor: not-allowed; }

.arc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.arc-table th {
    background: var(--surface); padding: 9px 12px; text-align: left;
    font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px;
    border-bottom: 1px solid var(--border);
}
.arc-table td { padding: 9px 12px; border-top: 1px solid var(--border); vertical-align: middle; }
.arc-table td.num, .arc-table th.num { text-align: right; }
.arc-table tfoot td { font-weight: 700; border-top: 2px solid var(--border); }

.arc-aviso {
    border-radius: 6px; padding: 10px 12px; font-size: 12px; line-height: 1.5;
    margin-bottom: 12px; border: 1px solid;
}
.arc-aviso.warn {
    background: color-mix(in srgb, var(--warning, #d97706) 10%, transparent);
    border-color: color-mix(in srgb, var(--warning, #d97706) 40%, transparent);
}
.arc-aviso.info {
    background: color-mix(in srgb, var(--info) 10%, transparent);
    border-color: color-mix(in srgb, var(--info) 35%, transparent);
}
.arc-aviso.ok {
    background: color-mix(in srgb, var(--success, #16a34a) 12%, transparent);
    border-color: color-mix(in srgb, var(--success, #16a34a) 40%, transparent);
}

.arc-corte { font-size: 12px; color: var(--muted); margin-bottom: 10px; }
.arc-total { font-size: 22px; font-weight: 700; }
.arc-muestra { max-height: 280px; overflow-y: auto; border: 1px solid var(--border); border-radius: 6px; margin-top: 12px; }
.badge-rev {
    background: color-mix(in srgb, var(--muted) 15%, transparent); color: var(--muted);
    border: 1px solid var(--border); padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700;
}
.arc-vacio { text-align: center; color: var(--muted); padding: 20px; font-size: 13px; }
</style>

<div class="arc-wrap">
    <h2 style="font-size:18px;font-weight:700;margin-bottom:6px;">Archivar conversaciones</h2>
    <div class="arc-info">
        Limpieza masiva de las colas de WhatsApp. Archivar <b>no borra nada</b>: la conversación y todos
        sus mensajes quedan en el historial, solo sale de la cola. Si el paciente vuelve a escribir,
        el mensaje entrante la reabre automáticamente con sus no leídos intactos.
        Toda corrida se puede deshacer desde el historial de abajo.
    </div>

    <div class="arc-card">
        <h3>1 · Qué archivar</h3>

        <div class="arc-modos">
            <div class="arc-modo sel" id="modo-dias" onclick="setModo('dias')">
                <div class="t">Por inactividad</div>
                <div class="d">Todo lo que no tiene actividad hace más de N días. Es la limpieza de rutina.</div>
            </div>
            <div class="arc-modo" id="modo-rango" onclick="setModo('rango')">
                <div class="t">Por rango de fechas</div>
                <div class="d">Todo lo que quedó dentro de un período. Para saltear semanas sin atención (vacaciones, feriados, bot caído).</div>
            </div>
        </div>

        <div class="arc-grid" id="campos-dias">
            <div class="arc-field">
                <label>Sin actividad hace más de</label>
                <select id="f-dias">
                    <option value="7" selected>7 días</option>
                    <option value="15">15 días</option>
                    <option value="30">30 días</option>
                    <option value="60">60 días</option>
                    <option value="90">90 días</option>
                    <option value="180">180 días</option>
                    <option value="365">1 año</option>
                </select>
            </div>
            <div class="arc-field">
                <label>Área</label>
                <select id="f-area-dias">
                    <option value="">Todas (los 3 números)</option>
                    @foreach($areas as $k => $label)
                        <option value="{{ $k }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="arc-field">
                <label class="arc-check">
                    <input type="checkbox" id="f-excl-dias" checked>
                    <span>No tocar las asignadas <span class="hint">(alguien las está atendiendo)</span></span>
                </label>
            </div>
        </div>

        <div class="arc-grid" id="campos-rango" style="display:none;">
            <div class="arc-field">
                <label>Desde</label>
                <input type="date" id="f-desde">
            </div>
            <div class="arc-field">
                <label>Hasta</label>
                <input type="date" id="f-hasta">
            </div>
            <div class="arc-field">
                <label>Área</label>
                <select id="f-area-rango">
                    <option value="">Todas (los 3 números)</option>
                    @foreach($areas as $k => $label)
                        <option value="{{ $k }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="arc-field" style="grid-column:1/-1;">
                <label class="arc-check">
                    <input type="checkbox" id="f-excl-rango" checked>
                    <span>No tocar las asignadas <span class="hint">(alguien las está atendiendo)</span></span>
                </label>
            </div>
        </div>

        <div style="margin-top:16px;">
            <button class="btn btn-primary" id="btn-prev" onclick="previsualizar()">Previsualizar</button>
        </div>
    </div>

    <div class="arc-card" id="card-prev" style="display:none;">
        <h3>2 · Qué se va a archivar</h3>
        <div id="prev-body"></div>
    </div>

    <div class="arc-card">
        <h3>Historial de archivados</h3>
        <div id="lotes-body"><div class="arc-vacio">Cargando…</div></div>
    </div>
</div>

<script>
const $ = (id) => document.getElementById(id);
let modo = 'dias';
let ultimoPreview = null;

function _tok() {
    return document.querySelector('meta[name=csrf-token]')?.content
        ?? document.querySelector('input[name=_token]')?.value ?? '{{ csrf_token() }}';
}

async function api(method, url, body) {
    // Token crudo de la sesión, y además en el body: hay PCs donde un intermediario
    // pela el header X-CSRF-TOKEN y el POST llegaba con 419 (ver commit d83b8a5).
    const tok  = _tok();
    const opts = {
        method,
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': tok, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    };
    if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify({ ...body, _token: tok }); }
    const r = await fetch(url, opts);
    const d = await r.json().catch(() => ({}));
    if (r.status === 419) return { ok: false, _err: 'Sesión expirada — recargá la página (F5).' };
    if (r.status === 422) {
        const detalle = d.errors ? Object.entries(d.errors).map(([k, vs]) => `• ${vs.join(', ')}`).join('\n') : (d.error || d.message || '');
        return { ok: false, _err: detalle || 'Datos inválidos' };
    }
    if (!r.ok) return { ok: false, _err: d.error || d.message || `HTTP ${r.status}` };
    return d;
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function setModo(m) {
    modo = m;
    $('modo-dias').classList.toggle('sel', m === 'dias');
    $('modo-rango').classList.toggle('sel', m === 'rango');
    $('campos-dias').style.display  = m === 'dias'  ? '' : 'none';
    $('campos-rango').style.display = m === 'rango' ? '' : 'none';
    $('card-prev').style.display = 'none';
    ultimoPreview = null;
}

function criterio() {
    return modo === 'dias'
        ? { modo: 'dias', dias: parseInt($('f-dias').value),
            area: $('f-area-dias').value || null, excluir_asignadas: $('f-excl-dias').checked }
        : { modo: 'rango', desde: $('f-desde').value, hasta: $('f-hasta').value,
            area: $('f-area-rango').value || null, excluir_asignadas: $('f-excl-rango').checked };
}

async function previsualizar() {
    const c = criterio();
    if (c.modo === 'rango' && (!c.desde || !c.hasta)) { alert('Elegí las dos fechas del rango.'); return; }

    $('btn-prev').disabled = true;
    const j = await api('POST', '/admin/archivar/preview', c);
    $('btn-prev').disabled = false;
    if (!j.ok) { alert('No se pudo previsualizar:\n' + (j._err || 'Error')); return; }

    ultimoPreview = j;
    $('card-prev').style.display = '';

    if (!j.total) {
        $('prev-body').innerHTML = `<div class="arc-corte">${esc(j.corte)}</div>
            <div class="arc-vacio">No hay conversaciones activas que cumplan ese criterio.</div>`;
        return;
    }

    const filas = j.por_area.map(a => `
        <tr>
            <td>${esc(a.area_label)}</td>
            <td class="num">${a.total}</td>
            <td class="num">${a.con_no_leidos}</td>
            <td class="num">${a.asignadas || '—'}</td>
        </tr>`).join('');

    const muestra = j.muestra.map(m => `
        <tr>
            <td>${esc(m.nombre)}</td>
            <td style="color:var(--muted);">${esc(m.area)}</td>
            <td style="color:var(--muted);">${esc(m.ultima_actividad || '—')}</td>
            <td class="num">${m.no_leidos || ''}</td>
        </tr>`).join('');

    $('prev-body').innerHTML = `
        ${j.aviso ? `<div class="arc-aviso warn">⚠ ${esc(j.aviso)}</div>` : ''}
        <div class="arc-corte">${esc(j.corte)}</div>
        <div class="arc-total">${j.total} <span style="font-size:13px;font-weight:400;color:var(--muted);">conversaciones se van a archivar</span></div>

        <table class="arc-table" style="margin-top:14px;">
            <thead><tr><th>Área</th><th class="num">A archivar</th><th class="num">Con no leídos</th><th class="num">Asignadas</th></tr></thead>
            <tbody>${filas}</tbody>
        </table>

        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin:16px 0 0;">
            Muestra (las de actividad más reciente)
        </div>
        <div class="arc-muestra">
            <table class="arc-table">
                <thead><tr><th>Contacto</th><th>Área</th><th>Última actividad</th><th class="num">Sin leer</th></tr></thead>
                <tbody>${muestra}</tbody>
            </table>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
            <button class="btn btn-ghost" onclick="document.getElementById('card-prev').style.display='none'">Cancelar</button>
            <button class="btn btn-primary" id="btn-aplicar" onclick="aplicar()">Archivar ${j.total} conversaciones</button>
        </div>`;
}

async function aplicar() {
    if (!ultimoPreview) return;
    if (!confirm(`Se van a archivar ${ultimoPreview.total} conversaciones.\n\n${ultimoPreview.corte}\n\nSe puede deshacer desde el historial. ¿Confirmás?`)) return;

    $('btn-aplicar').disabled = true;
    $('btn-aplicar').textContent = 'Archivando…';
    const j = await api('POST', '/admin/archivar/aplicar', criterio());
    if (!j.ok) {
        $('btn-aplicar').disabled = false;
        $('btn-aplicar').textContent = 'Reintentar';
        alert('No se pudo archivar:\n' + (j._err || 'Error'));
        return;
    }

    $('prev-body').innerHTML = `<div class="arc-aviso ok">✓ Se archivaron <b>${j.total}</b> conversaciones. Quedó registrado como lote #${j.lote} — abajo lo podés deshacer.</div>`;
    ultimoPreview = null;
    await cargarLotes();
}

async function cargarLotes() {
    const j = await api('GET', '/admin/archivar/lotes');
    if (!j.ok) { $('lotes-body').innerHTML = '<div class="arc-vacio">No se pudo cargar el historial.</div>'; return; }

    if (!j.lotes.length) {
        $('lotes-body').innerHTML = '<div class="arc-vacio">Todavía no se hizo ningún archivado masivo.</div>';
        return;
    }

    $('lotes-body').innerHTML = `
        <table class="arc-table">
            <thead><tr><th>Fecha</th><th>Quién</th><th>Criterio</th><th class="num">Archivadas</th><th></th></tr></thead>
            <tbody>${j.lotes.map(l => `
                <tr>
                    <td style="white-space:nowrap;">${esc(l.fecha)}</td>
                    <td>${esc(l.usuario)}${l.origen === 'consola' ? ' <span class="badge-rev">consola</span>' : ''}</td>
                    <td style="font-size:12px;color:var(--muted);">${esc(l.criterio)}</td>
                    <td class="num">${l.total}</td>
                    <td style="text-align:right;white-space:nowrap;">
                        ${l.revertido
                            ? `<span class="badge-rev" title="${esc(l.revertido_txt)}">deshecho</span>`
                            : `<button class="btn-sm" onclick="revertir(${l.id}, ${l.total})">Deshacer</button>`}
                    </td>
                </tr>`).join('')}
            </tbody>
        </table>`;
}

async function revertir(id, total) {
    if (!confirm(`Deshacer el lote #${id}: hasta ${total} conversaciones vuelven a la cola.\n\nLas que el paciente ya reabrió escribiendo de nuevo se dejan como están. ¿Confirmás?`)) return;
    const j = await api('POST', `/admin/archivar/lotes/${id}/revertir`);
    if (!j.ok) { alert('No se pudo deshacer:\n' + (j._err || 'Error')); return; }
    alert(`Volvieron a la cola ${j.revertidas} conversaciones.` +
          (j.reabiertas ? `\n${j.reabiertas} se saltearon porque ya estaban reabiertas.` : ''));
    await cargarLotes();
}

cargarLotes();
</script>
@endsection
