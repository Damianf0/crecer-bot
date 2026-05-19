@extends('layouts.app')
@section('title', 'Historial')

@push('styles')
<style>
.hist-root { max-width: 1100px; margin: 0 auto; }

.hist-filters {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    flex-wrap: wrap;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 18px;
}
.hist-filters label { font-size: 11px; color: var(--muted); display: block; margin-bottom: 4px; letter-spacing: .4px; text-transform: uppercase; }
.hist-input {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-size: 13px;
    padding: 6px 10px;
    height: 34px;
}
.hist-input:focus { outline: none; border-color: var(--info); }
.hist-select { min-width: 110px; }
.hist-search { min-width: 180px; }
.filter-btn {
    height: 34px;
    padding: 0 16px;
    background: var(--accent);
    border: none;
    color: #fff;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
}
.filter-btn-sec {
    height: 34px;
    padding: 0 14px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
}
.filter-btn-sec:hover { color: var(--text); border-color: var(--text); }

.hist-count { font-size: 13px; color: var(--muted); margin-bottom: 12px; }

/* Tabla */
.hist-table { width: 100%; border-collapse: collapse; }
.hist-table th {
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 8px 12px;
    border-bottom: 2px solid var(--border);
}
.hist-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: top;
}
.hist-table tr:hover td { background: var(--surface); }
.hist-table tr.expanded td { background: var(--surface); }

.badge { padding: 2px 7px; border-radius: 10px; font-size: 11px; font-weight: 700; letter-spacing: .4px; }
.badge-bot   { background: rgba(5,80,174,.08);  color: var(--info);    border: 1px solid rgba(5,80,174,.2); }
.badge-wa    { background: rgba(26,127,55,.08); color: var(--success); border: 1px solid rgba(26,127,55,.2); }
.badge-tarea { background: rgba(192,39,58,.08); color: var(--accent);  border: 1px solid rgba(192,39,58,.2); }
.badge-prio-alta   { background: rgba(192,39,58,.1);  color: var(--accent); border-radius:10px; font-size:10px; font-weight:700; padding:1px 6px; }
.badge-prio-normal { background: rgba(5,80,174,.08);  color: var(--info);   border-radius:10px; font-size:10px; font-weight:700; padding:1px 6px; }
.badge-prio-baja   { background: rgba(100,100,138,.1);color: var(--muted);  border-radius:10px; font-size:10px; font-weight:700; padding:1px 6px; }
.kom-hist { padding: 5px 0; border-bottom: 1px solid var(--border); font-size:12px; display:flex; gap:8px; }
.kom-hist:last-child { border-bottom: none; }
.kom-hist-autor { font-weight:600; min-width:80px; color: var(--text); }
.kom-hist-hora  { color: var(--muted); min-width:70px; font-size:11px; padding-top:1px; }

.detail-row { display: none; }
.detail-row.open { display: table-row; }
.detail-cell {
    background: rgba(5,80,174,.03);
    border-left: 3px solid var(--info);
    padding: 14px 16px !important;
}
.detail-texto {
    font-size: 13px;
    line-height: 1.6;
    white-space: pre-wrap;
    color: var(--text);
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 10px 12px;
    max-height: 200px;
    overflow-y: auto;
    margin-top: 6px;
}
.msg-mini {
    display: flex;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    line-height: 1.4;
}
.msg-mini:last-child { border-bottom: none; }
.msg-mini-dir { font-size: 10px; font-weight: 700; min-width: 60px; padding-top: 2px; }
.dir-in  { color: var(--muted); }
.dir-out { color: var(--info); }
.dir-nota { color: var(--warning); }
.msg-mini-hora { font-size: 10px; color: var(--muted); min-width: 40px; padding-top: 2px; }
.msg-mini-audio { color: var(--muted); font-style: italic; }

/* Eventos inline en el detalle del historial — mismo estilo que el divider
   "✓ Resuelta" de /atencion (chip verde + líneas). */
.msg-mini-evt { display: flex; align-items: center; gap: 10px; padding: 8px 0; color: var(--muted); font-size: 11px; border-bottom: 1px solid var(--border); }
.msg-mini-evt:last-child { border-bottom: none; }
.msg-mini-evt .line { flex: 1; height: 1px; background: color-mix(in srgb, var(--success) 40%, var(--border)); }
.msg-mini-evt .label { display: inline-flex; align-items: center; gap: 5px; padding: 2px 10px; border-radius: 9px; background: color-mix(in srgb, var(--success) 12%, var(--bg)); border: 1px solid color-mix(in srgb, var(--success) 30%, var(--border)); color: var(--success); font-weight: 600; }
.msg-mini-evt strong { color: var(--success); font-weight: 700; }
.msg-mini-evt .ev-time { opacity: .7; margin-left: 6px; font-size: 10.5px; font-weight: 500; }

.reabrir-btn {
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 5px;
    border: 1px solid rgba(63,185,80,.3);
    color: var(--success);
    background: transparent;
    cursor: pointer;
}
.reabrir-btn:hover { background: rgba(63,185,80,.1); }

.empty-state { text-align: center; padding: 60px; color: var(--muted); font-size: 14px; }
</style>
@endpush

@section('content')
<div class="hist-root">

    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
        <h1 style="font-size:18px;font-weight:700;">Historial</h1>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="/historial" class="hist-filters">
        <div>
            <label>Desde</label>
            <input type="date" name="desde" class="hist-input"
                value="{{ $desde ? $desde->format('Y-m-d') : '' }}">
        </div>
        <div>
            <label>Hasta</label>
            <input type="date" name="hasta" class="hist-input"
                value="{{ $hasta ? $hasta->format('Y-m-d') : '' }}">
        </div>
        <div>
            <label>Tipo</label>
            <select name="tipo" class="hist-input hist-select">
                <option value="todos"  {{ $tipo === 'todos' ? 'selected' : '' }}>Todos</option>
                <option value="bot"    {{ $tipo === 'bot'   ? 'selected' : '' }}>BOT</option>
                <option value="wa"     {{ $tipo === 'wa'    ? 'selected' : '' }}>WhatsApp</option>
                <option value="tarea"  {{ $tipo === 'tarea' ? 'selected' : '' }}>Tareas</option>
            </select>
        </div>
        <div>
            <label>Área</label>
            <select name="area" class="hist-input hist-select">
                <option value="todas" {{ ($area ?? 'todas') === 'todas' ? 'selected' : '' }}>Todas</option>
                @foreach(\App\Models\ConversacionWA::AREAS as $k => $v)
                    <option value="{{ $k }}" {{ ($area ?? 'todas') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Buscar</label>
            <input type="text" name="q" class="hist-input hist-search"
                placeholder="Contacto / título…" value="{{ $q }}">
        </div>
        <div style="display:flex;gap:8px;padding-bottom:1px;">
            <button type="submit" class="filter-btn">Filtrar</button>
            <a href="/historial" class="filter-btn-sec" style="display:inline-flex;align-items:center;text-decoration:none;">Limpiar</a>
        </div>
    </form>

    <div class="hist-count">
        {{ $total }} resultado{{ $total !== 1 ? 's' : '' }}
        @if($pages > 1)
            <span style="color:var(--muted);">· página {{ $page }} de {{ $pages }}</span>
        @endif
        @if($desde || $hasta)
            <span style="color:var(--info);">
                · {{ $desde?->format('d/m/Y') ?? '…' }} → {{ $hasta?->format('d/m/Y') ?? 'hoy' }}
            </span>
        @endif
    </div>

    @if($items->isEmpty())
        <div class="empty-state">Sin resultados para los filtros aplicados.</div>
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
    <table class="hist-table">
        <thead>
            <tr>
                <th style="width:90px;">Fecha</th>
                <th style="width:70px;">Tipo</th>
                <th style="width:180px;">Contacto / Título</th>
                <th>Resumen / Descripción</th>
                <th style="width:130px;">Responsable</th>
                <th style="width:120px;">Área</th>
                <th style="width:110px;"></th>
            </tr>
        </thead>
        <tbody id="tbody">
        @foreach($items as $item)
        <tr class="item-row" id="row-{{ $item['tipo'] }}-{{ $item['id'] }}">
            <td style="color:var(--muted);white-space:nowrap;">{{ $item['resuelto_at'] }}</td>
            <td>
                @if($item['tipo'] === 'bot')
                    <span class="badge badge-bot">BOT</span>
                @elseif($item['tipo'] === 'wa')
                    <span class="badge badge-wa">WA</span>
                @else
                    <span class="badge badge-tarea">Tarea</span>
                @endif
            </td>
            <td style="font-weight:600;">
                {{ $item['contacto'] }}
                @if($item['tipo'] === 'tarea' && ($item['prioridad'] ?? 'normal') !== 'normal')
                    <span class="badge-prio-{{ $item['prioridad'] }}">{{ ucfirst($item['prioridad']) }}</span>
                @endif
            </td>
            <td style="color:var(--muted);max-width:340px;">{{ $item['resumen'] }}</td>
            <td style="color:var(--muted);">{{ $item['asig_name'] ?? '—' }}</td>
            <td style="color:var(--muted);">{{ $item['area_label'] ?? '—' }}</td>
            <td style="white-space:nowrap;display:flex;gap:6px;">
                <button class="reabrir-btn" onclick="toggleDetalle('{{ $item['tipo'] }}', {{ $item['id'] }}, {{ json_encode($item) }})">
                    Ver ▾
                </button>
                @if($item['tipo'] !== 'tarea')
                <button class="reabrir-btn" onclick="reabrir('{{ $item['tipo'] }}', {{ $item['id'] }}, this)">
                    ↩ Reabrir
                </button>
                @endif
            </td>
        </tr>
        <tr class="detail-row" id="detail-{{ $item['tipo'] }}-{{ $item['id'] }}">
            <td colspan="7" class="detail-cell">
                <div id="detail-content-{{ $item['tipo'] }}-{{ $item['id'] }}" style="color:var(--muted);font-size:12px;">
                    Cargando…
                </div>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>

    @if($pages > 1)
    <div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:18px;font-size:13px;">
        @if($page > 1)
            <a href="?{{ $qsBase }}&page={{ $page - 1 }}" class="filter-btn-sec" style="text-decoration:none;display:inline-flex;align-items:center;">← Anterior</a>
        @else
            <span class="filter-btn-sec" style="opacity:.4;cursor:not-allowed;">← Anterior</span>
        @endif

        <span style="color:var(--muted);padding:0 10px;">Página {{ $page }} / {{ $pages }}</span>

        @if($page < $pages)
            <a href="?{{ $qsBase }}&page={{ $page + 1 }}" class="filter-btn-sec" style="text-decoration:none;display:inline-flex;align-items:center;">Siguiente →</a>
        @else
            <span class="filter-btn-sec" style="opacity:.4;cursor:not-allowed;">Siguiente →</span>
        @endif
    </div>
    @endif

    @endif

</div>

<div id="toast-hist" style="position:fixed;bottom:90px;right:24px;z-index:9999;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500;opacity:0;transition:.2s;pointer-events:none;"></div>

<script>
const CSRF = '{{ csrf_token() }}';

async function api(method, url, body) {
    const opts = { method, headers: { 'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest' } };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(url, opts);
    if (!r.ok) throw new Error(r.status);
    return r.json();
}

function toast(msg, ok = true) {
    const el = document.getElementById('toast-hist');
    el.textContent = msg;
    el.style.background = ok ? 'rgba(63,185,80,.15)' : 'rgba(248,81,73,.15)';
    el.style.color = ok ? '#3fb950' : '#f85149';
    el.style.border = ok ? '1px solid rgba(63,185,80,.3)' : '1px solid rgba(248,81,73,.3)';
    el.style.opacity = '1';
    clearTimeout(el._t);
    el._t = setTimeout(() => el.style.opacity = '0', 3000);
}

const _loaded = {};

async function toggleDetalle(tipo, id, item) {
    const row = document.getElementById(`detail-${tipo}-${id}`);
    const isOpen = row.classList.contains('open');

    // Cerrar todos
    document.querySelectorAll('.detail-row.open').forEach(r => r.classList.remove('open'));

    if (isOpen) return;
    row.classList.add('open');

    if (_loaded[`${tipo}-${id}`]) return;
    _loaded[`${tipo}-${id}`] = true;

    const el = document.getElementById(`detail-content-${tipo}-${id}`);

    if (tipo === 'tarea') {
        const PRIO = { alta: 'Alta', normal: 'Normal', baja: 'Baja' };
        const desc = item.resumen && item.resumen !== '—'
            ? `<div style="margin-bottom:12px;"><div style="font-size:10px;font-weight:700;color:var(--muted);letter-spacing:.5px;margin-bottom:5px;">DESCRIPCIÓN</div><div class="detail-texto">${esc(item.resumen)}</div></div>` : '';
        const meta = `<div style="display:grid;grid-template-columns:110px 1fr;gap:5px 12px;font-size:12px;margin-bottom:12px;">
            <span style="color:var(--muted);font-weight:700;text-transform:uppercase;font-size:10px;">Asignada a</span><span>${esc(item.asig_name || '— sin asignar —')}</span>
            <span style="color:var(--muted);font-weight:700;text-transform:uppercase;font-size:10px;">Creada por</span><span>${esc(item.creado_por || '—')}</span>
            <span style="color:var(--muted);font-weight:700;text-transform:uppercase;font-size:10px;">Prioridad</span><span class="badge-prio-${esc(item.prioridad)}">${esc(PRIO[item.prioridad] || item.prioridad)}</span>
        </div>`;
        const koms = item.comentarios && item.comentarios.length
            ? `<div style="font-size:10px;font-weight:700;color:var(--muted);letter-spacing:.5px;margin-bottom:5px;">COMENTARIOS (${item.comentarios.length})</div>
               <div style="max-height:180px;overflow-y:auto;">${item.comentarios.map(c =>
                   `<div class="kom-hist"><span class="kom-hist-autor">${esc(c.usuario||'—')}</span><span class="kom-hist-hora">${esc(c.hora)}</span><span>${esc(c.contenido)}</span></div>`
               ).join('')}</div>`
            : `<div style="font-size:12px;color:var(--muted);">Sin comentarios.</div>`;
        el.innerHTML = desc + meta + koms;
        return;
    }

    try {
        if (tipo === 'bot') {
            const d = await api('GET', `/atencion/derivacion/${id}`);
            el.innerHTML = `
                ${d.resumen ? `<div style="margin-bottom:10px;"><span style="font-size:10px;font-weight:700;color:var(--info);letter-spacing:.5px;">RESUMEN</span><div style="margin-top:4px;font-size:13px;color:var(--text);">${esc(d.resumen)}</div></div>` : ''}
                <div style="font-size:10px;font-weight:700;color:var(--muted);letter-spacing:.5px;margin-bottom:5px;">CONVERSACIÓN</div>
                <div class="detail-texto">${esc(d.texto || '—')}</div>`;
        } else {
            const data = await api('GET', `/atencion/conversacion/${id}`);
            const msgs     = data.mensajes || [];
            const eventos  = data.eventos  || [];

            // Mezcla cronológica de mensajes + eventos. Cada evento (tomada /
            // delegada / resuelta / etc.) se renderiza como chip inline en su
            // posición temporal, en lugar de aparecer en un bloque separado
            // al final sin contexto.
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

            const itemsHtml = items.length
                ? `<div style="max-height:220px;overflow-y:auto;">` + items.map(renderItem).join('') + `</div>`
                : '<span style="color:var(--muted);font-size:12px;">Sin mensajes ni acciones registradas</span>';

            el.innerHTML = itemsHtml;
        }
    } catch(e) { el.textContent = 'Error al cargar detalle.'; }
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function reabrir(tipo, id, btn) {
    btn.disabled = true;
    btn.textContent = '…';
    try {
        await api('POST', '/atencion/reabrir', { id, tipo });
        toast('Reabierto — aparece en Atención');
        const row = document.getElementById(`row-${tipo}-${id}`);
        const det = document.getElementById(`detail-${tipo}-${id}`);
        row.style.opacity = '.4';
        det.classList.remove('open');
        btn.textContent = '✓';
    } catch(e) {
        toast('Error al reabrir', false);
        btn.disabled = false;
        btn.textContent = '↩ Reabrir';
    }
}
</script>
@endsection
