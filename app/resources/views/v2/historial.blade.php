@extends('layouts.v2')
@section('title', 'Historial')

{{-- PoC V2 — historial con el MISMO funcionamiento que /historial de
     producción (filtros GET, tabla densa, detalle expandible por fila con
     "Ver ▾", reabrir, paginación server-side), renderizado en el shell V2.
     La query vive en AtencionController::historial (via V2Controller). --}}

@push('styles')
<style>
/* Estilos específicos de esta pantalla (detalle expandible y mini-timeline) */
.detail-row { display: none; }
.detail-row.open { display: table-row; }
.detail-cell {
    background: var(--v2-info-bg) !important;
    border-left: 3px solid var(--v2-info);
    padding: 14px 16px !important;
}
.detail-texto {
    font-size: 13px; line-height: 1.6; white-space: pre-wrap;
    color: var(--v2-text); background: var(--v2-bg-card);
    border: 1px solid var(--v2-border); border-radius: var(--v2-radius-sm);
    padding: 10px 12px; max-height: 200px; overflow-y: auto; margin-top: 6px;
}
.msg-mini { display: flex; gap: 8px; padding: 5px 0; border-bottom: 1px solid var(--v2-border); font-size: 13px; line-height: 1.4; }
.msg-mini:last-child { border-bottom: none; }
.msg-mini-dir { font-size: 10px; font-weight: 700; min-width: 60px; padding-top: 2px; }
.dir-in  { color: var(--v2-text-mute); }
.dir-out { color: var(--v2-info); }
.dir-nota { color: var(--v2-warn); }
.msg-mini-hora { font-size: 10px; color: var(--v2-text-mute); min-width: 40px; padding-top: 2px; font-family: 'JetBrains Mono', monospace; }
.msg-mini-audio { color: var(--v2-text-mute); font-style: italic; }
.msg-mini-evt { display: flex; align-items: center; gap: 10px; padding: 8px 0; color: var(--v2-text-mute); font-size: 11px; border-bottom: 1px solid var(--v2-border); }
.msg-mini-evt:last-child { border-bottom: none; }
.msg-mini-evt .line { flex: 1; height: 1px; background: var(--v2-border-strong); }
.msg-mini-evt .label { display: inline-flex; align-items: center; gap: 5px; padding: 2px 10px; border-radius: 9px; background: var(--v2-ok-bg); color: var(--v2-ok); font-weight: 600; }
.msg-mini-evt strong { color: var(--v2-ok); font-weight: 700; }
.msg-mini-evt .ev-time { opacity: .7; margin-left: 6px; font-size: 10.5px; font-weight: 500; }
.kom-hist { padding: 5px 0; border-bottom: 1px solid var(--v2-border); font-size: 12px; display: flex; gap: 8px; }
.kom-hist:last-child { border-bottom: none; }
.kom-hist-autor { font-weight: 600; min-width: 80px; color: var(--v2-text); }
.kom-hist-hora  { color: var(--v2-text-mute); min-width: 70px; font-size: 11px; padding-top: 1px; font-family: 'JetBrains Mono', monospace; }
.hist-label-mini { font-size: 10px; font-weight: 700; color: var(--v2-text-mute); letter-spacing: .5px; margin-bottom: 5px; text-transform: uppercase; }
</style>
@endpush

@section('content')
<div style="flex:1;overflow-y:auto;padding:20px 24px;">
<div style="max-width:1100px;margin:0 auto;">

    <h1 style="font-size:17px;font-weight:650;margin-bottom:16px;">Historial</h1>

    {{-- Filtros (GET, igual que producción) --}}
    <form method="GET" action="/v2/historial" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;background:var(--v2-bg-card);border:1px solid var(--v2-border);border-radius:var(--v2-radius);padding:14px 16px;margin-bottom:16px;">
        <div>
            <label class="v2-label" style="margin-top:0;">Desde</label>
            <input type="date" name="desde" class="v2-field" style="height:34px;" value="{{ $desde ? $desde->format('Y-m-d') : '' }}">
        </div>
        <div>
            <label class="v2-label" style="margin-top:0;">Hasta</label>
            <input type="date" name="hasta" class="v2-field" style="height:34px;" value="{{ $hasta ? $hasta->format('Y-m-d') : '' }}">
        </div>
        <div>
            <label class="v2-label" style="margin-top:0;">Tipo</label>
            <select name="tipo" class="v2-field" style="height:34px;min-width:110px;">
                <option value="todos"  {{ $tipo === 'todos' ? 'selected' : '' }}>Todos</option>
                <option value="bot"    {{ $tipo === 'bot'   ? 'selected' : '' }}>Bot</option>
                <option value="wa"     {{ $tipo === 'wa'    ? 'selected' : '' }}>WhatsApp</option>
                <option value="tarea"  {{ $tipo === 'tarea' ? 'selected' : '' }}>Tareas</option>
            </select>
        </div>
        <div>
            <label class="v2-label" style="margin-top:0;">Área</label>
            <select name="area" class="v2-field" style="height:34px;min-width:130px;">
                <option value="todas" {{ ($area ?? 'todas') === 'todas' ? 'selected' : '' }}>Todas</option>
                @foreach(\App\Models\ConversacionWA::AREAS as $k => $v)
                    <option value="{{ $k }}" {{ ($area ?? 'todas') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="v2-label" style="margin-top:0;">Buscar</label>
            <input type="text" name="q" class="v2-field" style="height:34px;min-width:180px;" placeholder="Contacto / título…" value="{{ $q }}">
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="v2-btn primary" style="height:34px;">Filtrar</button>
            <a href="/v2/historial" class="v2-btn" style="height:34px;text-decoration:none;">Limpiar</a>
        </div>
    </form>

    <div style="font-size:12.5px;color:var(--v2-text-mute);margin-bottom:10px;">
        {{ $total }} resultado{{ $total !== 1 ? 's' : '' }}
        @if($pages > 1)<span> · página {{ $page }} de {{ $pages }}</span>@endif
        @if($desde || $hasta)
            <span style="color:var(--v2-info);"> · {{ $desde?->format('d/m/Y') ?? '…' }} → {{ $hasta?->format('d/m/Y') ?? 'hoy' }}</span>
        @endif
    </div>

    @if($items->isEmpty())
        <div class="v2-empty" style="background:var(--v2-bg-card);border:1px solid var(--v2-border);border-radius:var(--v2-radius);"><span class="ico">🗂</span>Sin resultados para los filtros aplicados.</div>
    @else

    @php
        $qsBase = http_build_query(array_filter([
            'desde' => $desde?->format('Y-m-d'),
            'hasta' => $hasta?->format('Y-m-d'),
            'tipo'  => $tipo !== 'todos' ? $tipo : null,
            'area'  => ($area ?? 'todas') !== 'todas' ? $area : null,
            'q'     => $q ?: null,
        ]));
    @endphp
    <table class="v2-table">
        <thead>
            <tr>
                <th style="width:95px;">Fecha</th>
                <th style="width:75px;">Tipo</th>
                <th style="width:180px;">Contacto / Título</th>
                <th>Resumen / Descripción</th>
                <th style="width:130px;">Responsable</th>
                <th style="width:115px;">Área</th>
                <th style="width:130px;"></th>
            </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
        <tr class="item-row" id="row-{{ $item['tipo'] }}-{{ $item['id'] }}">
            <td style="color:var(--v2-text-mute);white-space:nowrap;font-family:'JetBrains Mono',monospace;font-size:11.5px;">{{ $item['resuelto_at'] }}</td>
            <td>
                @if($item['tipo'] === 'bot')
                    <span class="v2-pill nueva">Bot</span>
                @elseif($item['tipo'] === 'wa')
                    <span class="v2-pill proceso">WA</span>
                @else
                    <span class="v2-pill accent">Tarea</span>
                @endif
            </td>
            <td style="font-weight:600;">
                {{ $item['contacto'] }}
                @if($item['tipo'] === 'tarea' && ($item['prioridad'] ?? 'normal') !== 'normal')
                    <span class="v2-pill {{ $item['prioridad'] === 'alta' ? 'urgente' : 'neutral' }}" style="font-size:10px;">{{ ucfirst($item['prioridad']) }}</span>
                @endif
            </td>
            <td style="color:var(--v2-text-2);max-width:340px;">{{ $item['resumen'] }}</td>
            <td style="color:var(--v2-text-2);">{{ $item['asig_name'] ?? '—' }}</td>
            <td style="color:var(--v2-text-2);">{{ $item['area_label'] ?? '—' }}</td>
            <td style="white-space:nowrap;">
                <button class="v2-btn sm" onclick="toggleDetalle('{{ $item['tipo'] }}', {{ $item['id'] }}, {{ json_encode($item) }})">Ver ▾</button>
                @if($item['tipo'] !== 'tarea')
                <button class="v2-btn sm accent" onclick="reabrir('{{ $item['tipo'] }}', {{ $item['id'] }}, this)">↩ Reabrir</button>
                @endif
            </td>
        </tr>
        <tr class="detail-row" id="detail-{{ $item['tipo'] }}-{{ $item['id'] }}">
            <td colspan="7" class="detail-cell">
                <div id="detail-content-{{ $item['tipo'] }}-{{ $item['id'] }}" style="color:var(--v2-text-mute);font-size:12px;">Cargando…</div>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>

    @if($pages > 1)
    <div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:16px;font-size:13px;">
        @if($page > 1)
            <a href="?{{ $qsBase }}&page={{ $page - 1 }}" class="v2-btn" style="text-decoration:none;">← Anterior</a>
        @else
            <span class="v2-btn" style="opacity:.4;cursor:not-allowed;">← Anterior</span>
        @endif
        <span style="color:var(--v2-text-mute);padding:0 10px;font-family:'JetBrains Mono',monospace;font-size:11.5px;">Página {{ $page }} / {{ $pages }}</span>
        @if($page < $pages)
            <a href="?{{ $qsBase }}&page={{ $page + 1 }}" class="v2-btn" style="text-decoration:none;">Siguiente →</a>
        @else
            <span class="v2-btn" style="opacity:.4;cursor:not-allowed;">Siguiente →</span>
        @endif
    </div>
    @endif

    @endif
</div>
</div>
@endsection

@push('scripts')
<script>
// Mismo comportamiento que /historial de producción: detalle expandible por
// fila, cargado on-demand y cacheado; reabrir con feedback inline.
const { esc, get, post } = V2;
const _loaded = {};

async function toggleDetalle(tipo, id, item) {
    const row = document.getElementById(`detail-${tipo}-${id}`);
    const isOpen = row.classList.contains('open');

    document.querySelectorAll('.detail-row.open').forEach(r => r.classList.remove('open'));
    document.querySelectorAll('.item-row.expanded').forEach(r => r.classList.remove('expanded'));

    if (isOpen) return;
    row.classList.add('open');
    document.getElementById(`row-${tipo}-${id}`).classList.add('expanded');

    if (_loaded[`${tipo}-${id}`]) return;
    _loaded[`${tipo}-${id}`] = true;

    const el = document.getElementById(`detail-content-${tipo}-${id}`);

    if (tipo === 'tarea') {
        const PRIO = { alta: 'Alta', normal: 'Normal', baja: 'Baja' };
        const desc = item.resumen && item.resumen !== '—'
            ? `<div style="margin-bottom:12px;"><div class="hist-label-mini">Descripción</div><div class="detail-texto">${esc(item.resumen)}</div></div>` : '';
        const meta = `<div style="display:grid;grid-template-columns:110px 1fr;gap:5px 12px;font-size:12px;margin-bottom:12px;">
            <span class="hist-label-mini" style="margin:0;">Asignada a</span><span>${esc(item.asig_name || '— sin asignar —')}</span>
            <span class="hist-label-mini" style="margin:0;">Creada por</span><span>${esc(item.creado_por || '—')}</span>
            <span class="hist-label-mini" style="margin:0;">Prioridad</span><span>${esc(PRIO[item.prioridad] || item.prioridad || 'Normal')}</span>
        </div>`;
        const koms = item.comentarios && item.comentarios.length
            ? `<div class="hist-label-mini">Comentarios (${item.comentarios.length})</div>
               <div style="max-height:180px;overflow-y:auto;">${item.comentarios.map(c =>
                   `<div class="kom-hist"><span class="kom-hist-autor">${esc(c.usuario||'—')}</span><span class="kom-hist-hora">${esc(c.hora)}</span><span>${esc(c.contenido)}</span></div>`
               ).join('')}</div>`
            : `<div style="font-size:12px;color:var(--v2-text-mute);">Sin comentarios.</div>`;
        el.innerHTML = desc + meta + koms;
        return;
    }

    try {
        if (tipo === 'bot') {
            const d = await get(`/atencion/derivacion/${id}`);
            el.innerHTML = `
                ${d.resumen ? `<div style="margin-bottom:10px;"><span class="hist-label-mini" style="color:var(--v2-info);">Resumen</span><div style="margin-top:4px;font-size:13px;color:var(--v2-text);">${esc(d.resumen)}</div></div>` : ''}
                <div class="hist-label-mini">Conversación</div>
                <div class="detail-texto">${esc(d.texto || '—')}</div>`;
        } else {
            const data = await get(`/atencion/conversacion/${id}`);
            const msgs    = data.mensajes || [];
            const eventos = data.eventos  || [];

            const TIPOS_SEG = {
                tomada:        { icon: '🟢', label: (e) => `Tomó <strong>${esc(e.usuario||'—')}</strong>` },
                delegada:      { icon: '📤', label: (e) => `<strong>${esc(e.usuario||'—')}</strong> delegó a <strong>${esc(e.destino||'—')}</strong>` },
                resuelta:      { icon: '✅', label: (e) => `Resolvió <strong>${esc(e.usuario||'—')}</strong>` },
                reabierta:     { icon: '🔁', label: (e) => `Reabrió <strong>${esc(e.usuario||'—')}</strong>` },
                urgente_on:    { icon: '⚑',  label: (e) => `<strong>${esc(e.usuario||'—')}</strong> marcó urgente` },
                urgente_off:   { icon: '⚐',  label: (e) => `<strong>${esc(e.usuario||'—')}</strong> sacó urgencia` },
                reenviada:     { icon: '🔁', label: (e) => `<strong>${esc(e.usuario||'—')}</strong> reenvió y archivó` },
                derivada_area: { icon: '↗',  label: (e) => `<strong>${esc(e.usuario||'—')}</strong> derivó a otra área` },
            };

            const items = [];
            for (const m of msgs)    items.push(Object.assign({}, m, { __k: 'msg' }));
            for (const e of eventos) items.push(Object.assign({}, e, { __k: 'evt' }));
            items.sort((a, b) => (a.ts || 0) - (b.ts || 0));

            const renderItem = (it) => {
                if (it.__k === 'msg') {
                    const dirClass = it.direccion === 'entrante' ? 'dir-in' : it.direccion === 'saliente' ? 'dir-out' : 'dir-nota';
                    const dirLabel = it.direccion === 'entrante' ? 'Paciente' : it.direccion === 'saliente' ? 'Clínica' : 'Nota';
                    const cuerpo = it.tipo === 'audio'
                        ? `<span class="msg-mini-audio">🎤 ${esc(it.contenido || 'Audio sin transcripción')}</span>`
                        : esc(it.contenido || '');
                    return `<div class="msg-mini">
                        <span class="msg-mini-dir ${dirClass}">${dirLabel}</span>
                        <span class="msg-mini-hora">${it.hora}</span>
                        <span>${cuerpo}</span>
                    </div>`;
                }
                const t = TIPOS_SEG[it.tipo] || { icon: '•', label: () => esc(it.tipo) };
                return `<div class="msg-mini-evt" title="${esc(it.fecha||'')}"><div class="line"></div><div class="label">${t.icon} ${t.label(it)}<span class="ev-time">${esc(it.hora||'')}</span></div><div class="line"></div></div>`;
            };

            el.innerHTML = items.length
                ? `<div style="max-height:220px;overflow-y:auto;">` + items.map(renderItem).join('') + `</div>`
                : '<span style="color:var(--v2-text-mute);font-size:12px;">Sin mensajes ni acciones registradas</span>';
        }
    } catch(e) { el.textContent = 'Error al cargar detalle.'; }
}

async function reabrir(tipo, id, btn) {
    btn.disabled = true;
    btn.textContent = '…';
    try {
        await post('/atencion/reabrir', { id, tipo });
        v2toast('Reabierto — aparece en Atención');
        const row = document.getElementById(`row-${tipo}-${id}`);
        const det = document.getElementById(`detail-${tipo}-${id}`);
        row.style.opacity = '.4';
        det.classList.remove('open');
        btn.textContent = '✓';
    } catch(e) {
        v2toast('Error al reabrir', 'err');
        btn.disabled = false;
        btn.textContent = '↩ Reabrir';
    }
}
</script>
@endpush
