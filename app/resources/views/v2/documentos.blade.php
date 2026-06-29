@extends('layouts.v2')

{{-- Legajo de documentos del paciente — versión V2. Reusa los mismos endpoints
     de producción (/pacientes/{id}/documentos/*, /documentos/{id}/*); solo cambia
     el shell (layouts.v2 + v2Wrap) y el link de volver (→ /v2/contactos). Los
     estilos lg-* usan tokens de prod que resuelven al tema V2 por el puente de
     crecer-v2.css. --}}

@section('content')
@php
    $avatar = $contacto->avatar_path ? asset('storage/' . $contacto->avatar_path) : null;
@endphp

<style>
.lg-root { max-width: 1200px; margin: 0 auto; }

.lg-head {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 16px;
    display: flex; gap: 16px; align-items: center;
}
.lg-head-info { flex: 1; min-width: 0; }
.lg-head-name { font-size: 18px; font-weight: 700; }
.lg-head-meta { font-size: 12px; color: var(--muted); margin-top: 4px; }
.lg-head-stats { display: flex; gap: 18px; font-size: 12px; color: var(--muted); }
.lg-head-stats b { color: var(--text); font-size: 14px; display: block; }

.lg-toolbar {
    display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
    margin-bottom: 12px;
}
.lg-chip {
    padding: 5px 12px;
    border-radius: 14px;
    font-size: 12px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--muted);
    cursor: pointer;
    transition: .12s;
}
.lg-chip:hover { color: var(--text); border-color: var(--text); }
.lg-chip.active {
    background: var(--info); color: #fff; border-color: var(--info);
}
.lg-search {
    padding: 6px 12px; border-radius: 7px; border: 1px solid var(--border);
    background: var(--card); color: var(--text); font-size: 13px;
    min-width: 220px; flex: 1;
}
.lg-search:focus { outline: none; border-color: var(--info); }

.lg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
.lg-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 9px;
    overflow: hidden;
    cursor: pointer;
    transition: .15s;
    position: relative;
}
.lg-card:hover { border-color: var(--info); transform: translateY(-2px); }
.lg-card.selected { border-color: var(--info); box-shadow: 0 0 0 2px var(--info); }
.lg-card.destacado::before {
    content: '★'; position: absolute; top: 6px; right: 8px;
    color: var(--warning); font-size: 16px; z-index: 2;
    text-shadow: 0 1px 2px rgba(0,0,0,.5);
}

.lg-thumb {
    width: 100%; height: 130px; display: flex; align-items: center; justify-content: center;
    background: var(--surface); position: relative; overflow: hidden;
}
.lg-thumb img { width: 100%; height: 100%; object-fit: cover; }
.lg-thumb-icon { font-size: 42px; color: var(--muted); }

.lg-thumb-audio { padding: 12px; cursor: default; flex-direction: column; gap: 8px; }
.lg-mp { display: flex; align-items: center; gap: 10px; width: 100%; }
.lg-mp-play {
    width: 38px; height: 38px; border-radius: 50%;
    background: var(--info); color: #fff; border: none;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 16px; cursor: pointer; flex-shrink: 0; line-height: 1;
}
.lg-mp-play:hover { filter: brightness(1.1); }
.lg-mp-bar { flex: 1; height: 4px; background: var(--border); border-radius: 2px; cursor: pointer; position: relative; overflow: hidden; }
.lg-mp-bar-fill { height: 100%; background: var(--info); width: 0%; transition: width .1s linear; }
.lg-mp-time { font-size: 11px; color: var(--muted); font-variant-numeric: tabular-nums; min-width: 38px; text-align: right; }
.lg-mp-err  { font-size: 11px; color: var(--error); text-align: center; }
.lg-thumb-direccion {
    position: absolute; top: 6px; left: 8px;
    background: rgba(0,0,0,.55); color: #fff;
    padding: 2px 8px; border-radius: 10px;
    font-size: 10px; font-weight: 600;
}

.lg-body { padding: 9px 11px; }
.lg-name {
    font-size: 13px; font-weight: 600;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-bottom: 3px;
}
.lg-meta { font-size: 11px; color: var(--muted); display: flex; justify-content: space-between; }

.lg-actions { padding: 0 8px 8px; display: flex; gap: 4px; flex-wrap: wrap; }
.lg-btn {
    flex: 1; min-width: 0;
    padding: 4px 8px; border-radius: 5px; font-size: 11px;
    border: 1px solid var(--border); background: transparent;
    color: var(--muted); cursor: pointer; transition: .12s;
    white-space: nowrap;
}
.lg-btn:hover { color: var(--text); border-color: var(--text); }
.lg-btn.warn { color: var(--warning); border-color: color-mix(in srgb, var(--warning) 35%, transparent); }
.lg-btn.danger { color: var(--error); border-color: color-mix(in srgb, var(--error) 35%, transparent); }

.lg-bulk-bar {
    position: sticky; bottom: 0;
    background: var(--card); border: 1px solid var(--border);
    border-radius: 8px; padding: 10px 14px;
    margin-top: 14px;
    display: none; gap: 10px; align-items: center;
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
}
.lg-bulk-bar.show { display: flex; }

.lg-empty { padding: 60px 20px; text-align: center; color: var(--muted); font-size: 13px; }

.lg-modal {
    position: fixed; inset: 0; z-index: 9000;
    background: rgba(0,0,0,.75);
    display: none; align-items: center; justify-content: center;
}
.lg-modal.show { display: flex; }
.lg-modal-content {
    background: var(--card); border: 1px solid var(--border); border-radius: 10px;
    width: min(900px, 96vw); height: min(82vh, 800px);
    display: flex; flex-direction: column;
}
.lg-modal-head {
    padding: 12px 16px; border-bottom: 1px solid var(--border);
    display: flex; gap: 10px; align-items: center;
}
.lg-modal-body { flex: 1; overflow: hidden; background: #1a1a1a; display: flex; align-items: center; justify-content: center; }
.lg-modal-body iframe, .lg-modal-body img, .lg-modal-body video {
    width: 100%; height: 100%; border: 0; display: block;
    object-fit: contain; background: #1a1a1a;
}
.lg-modal-foot {
    padding: 10px 16px; border-top: 1px solid var(--border);
    display: flex; gap: 8px; align-items: center;
}
</style>

<div class="lg-root">

    {{-- Header con paciente --}}
    <div class="lg-head">
        @if($avatar)
            <img src="{{ $avatar }}" style="width:60px;height:60px;border-radius:50%;object-fit:cover;flex-shrink:0;">
        @else
            <div style="width:60px;height:60px;border-radius:50%;background:var(--accent);color:#fff;font-size:24px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                {{ mb_strtoupper(mb_substr($contacto->nombre, 0, 1)) }}
            </div>
        @endif
        <div class="lg-head-info">
            <div class="lg-head-name">{{ $contacto->nombre }}</div>
            <div class="lg-head-meta">
                {{ $contacto->telefono }}
                @if($contacto->dni) · DNI {{ $contacto->dni }} @endif
                · <a href="/v2/contactos" style="color:var(--info);">← Volver al directorio</a>
            </div>
        </div>
        <div class="lg-head-stats">
            <div><b id="stat-docs">—</b> documentos</div>
            <div><b id="stat-tamanio">—</b> total</div>
            <div><b id="stat-destacados">—</b> destacados</div>
        </div>
        <button onclick="abrirSubir()" style="background:var(--success);border:none;color:#fff;border-radius:7px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;">
            ↑ Subir documento
        </button>
    </div>

    {{-- Toolbar de filtros --}}
    <div class="lg-toolbar">
        <button class="lg-chip active" data-filter="tipo" data-value=""        onclick="setFiltro('tipo','')">Todos</button>
        <button class="lg-chip"        data-filter="tipo" data-value="imagen"   onclick="setFiltro('tipo','imagen')">🖼 Imágenes</button>
        <button class="lg-chip"        data-filter="tipo" data-value="documento" onclick="setFiltro('tipo','documento')">📄 PDFs</button>
        <button class="lg-chip"        data-filter="tipo" data-value="audio"    onclick="setFiltro('tipo','audio')">🎤 Audios</button>
        <button class="lg-chip"        data-filter="tipo" data-value="video"    onclick="setFiltro('tipo','video')">🎥 Videos</button>

        <span style="color:var(--muted);font-size:12px;margin-left:8px;">·</span>

        <button class="lg-chip active" data-filter="dir" data-value=""         onclick="setFiltro('dir','')">Todas</button>
        <button class="lg-chip"        data-filter="dir" data-value="entrante"  onclick="setFiltro('dir','entrante')">← Recibidos</button>
        <button class="lg-chip"        data-filter="dir" data-value="saliente"  onclick="setFiltro('dir','saliente')">→ Enviados</button>
        <button class="lg-chip"        data-filter="dir" data-value="manual"    onclick="setFiltro('dir','manual')">📤 Subidos</button>

        <span style="color:var(--muted);font-size:12px;margin-left:8px;">·</span>

        <button class="lg-chip" id="chip-destacados" onclick="toggleDestacados()">⭐ Destacados</button>

        <input class="lg-search" id="search" placeholder="Buscar por nombre, nota o contenido (OCR)..." oninput="buscarDebounced()">

        <input type="date" id="desde" onchange="aplicarFiltros()" style="padding:6px 10px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
        <input type="date" id="hasta" onchange="aplicarFiltros()" style="padding:6px 10px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;">
    </div>

    {{-- Grid de documentos --}}
    <div id="lista" class="lg-grid">
        <div class="lg-empty">Cargando…</div>
    </div>

    <div id="ver-mas-row" style="display:none;text-align:center;padding:14px 0;">
        <button onclick="cargarMas()" id="btn-ver-mas"
                style="padding:8px 22px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:13px;cursor:pointer;font-weight:600;">
            Ver más documentos
        </button>
    </div>

    {{-- Barra de acciones múltiples --}}
    <div class="lg-bulk-bar" id="bulk-bar">
        <span id="bulk-count" style="font-weight:600;">0 seleccionados</span>
        <button class="lg-btn" onclick="limpiarSeleccion()">Cancelar</button>
        <span style="margin-left:auto;"></span>
        <button class="lg-btn" style="background:var(--success);color:#fff;border-color:var(--success);font-weight:600;" onclick="reenviarSeleccionados()" id="btn-bulk-reenviar">↗ Re-enviar al paciente</button>
        <button class="lg-btn" style="background:var(--info);color:#fff;border-color:var(--info);font-weight:600;" onclick="descargarZip()">↓ Descargar ZIP</button>
        <button class="lg-btn danger" onclick="eliminarSeleccionados()">Eliminar</button>
    </div>

</div>

{{-- Modal preview --}}
<div class="lg-modal" id="modal-preview">
    <div class="lg-modal-content" onclick="event.stopPropagation()">
        <div class="lg-modal-head">
            <span style="font-weight:700;font-size:14px;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" id="prev-titulo">—</span>
            <span style="font-size:11px;color:var(--muted);" id="prev-meta">—</span>
            <button onclick="cerrarPreview()" style="background:none;border:none;color:var(--muted);font-size:24px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div class="lg-modal-body" id="prev-body"></div>
        <div class="lg-modal-foot">
            <button class="lg-btn" onclick="prevDestacar()" id="btn-prev-destacar">⭐ Destacar</button>
            <button class="lg-btn" onclick="prevNotas()">📝 Nota</button>
            <button class="lg-btn" onclick="prevReenviar()">↗ Re-enviar al paciente</button>
            <span style="margin-left:auto;"></span>
            <a id="prev-descargar" class="lg-btn" style="text-decoration:none;display:inline-block;">↓ Descargar</a>
            <button class="lg-btn danger" onclick="prevEliminar()">Eliminar</button>
        </div>
    </div>
</div>

{{-- Modal subir documento manual --}}
<div class="lg-modal" id="modal-subir">
    <div class="lg-modal-content" style="height:auto;width:min(500px,96vw);" onclick="event.stopPropagation()">
        <div class="lg-modal-head">
            <span style="font-weight:700;font-size:14px;flex:1;">Subir documento al legajo</span>
            <button onclick="cerrarSubir()" style="background:none;border:none;color:var(--muted);font-size:24px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div class="lg-modal-body" style="background:var(--card);padding:18px;overflow-y:auto;">
            <label style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;">Archivo</label>
            <input type="file" id="up-file" style="display:block;width:100%;padding:8px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px;margin-top:4px;margin-bottom:14px;">

            <label style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;">Nota (opcional)</label>
            <textarea id="up-notas" placeholder="Ej: Análisis del 12/03"
                style="display:block;width:100%;padding:8px 10px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px;font-family:inherit;resize:vertical;min-height:60px;margin-top:4px;margin-bottom:14px;"></textarea>

            <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
                <input type="checkbox" id="up-destacado"> Marcar como destacado
            </label>
        </div>
        <div class="lg-modal-foot">
            <button class="lg-btn" onclick="cerrarSubir()">Cancelar</button>
            <span style="margin-left:auto;"></span>
            <button class="lg-btn" id="btn-up" style="background:var(--success);color:#fff;border-color:var(--success);font-weight:600;" onclick="subirArchivo()">Guardar</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
const CONTACTO_ID = {{ $contacto->id }};
const CONTACTO_TIENE_WA = {{ $contacto->wa_id ? 'true' : 'false' }};

let _filtros = { tipo: '', dir: '', destacados: false, q: '', desde: '', hasta: '', page: 1 };
let _searchTimer = null;
let _docs = [];
let _seleccionados = new Set();
let _docActual = null;
let _reqSeq = 0;

function escTxt(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── Cargar listado ──────────────────────────────────────────────
async function cargar(reset = true) {
    if (reset) _filtros.page = 1;
    const seq = ++_reqSeq;

    const params = new URLSearchParams();
    if (_filtros.tipo)        params.set('tipo', _filtros.tipo);
    if (_filtros.dir)         params.set('direccion', _filtros.dir);
    if (_filtros.destacados)  params.set('destacados', '1');
    if (_filtros.q)           params.set('q', _filtros.q);
    if (_filtros.desde)       params.set('desde', _filtros.desde);
    if (_filtros.hasta)       params.set('hasta', _filtros.hasta);
    params.set('page', _filtros.page);

    try {
        const r = await fetch(`/pacientes/${CONTACTO_ID}/documentos/data?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (seq !== _reqSeq) return;
        const d = await r.json();
        if (!d.ok) return;

        if (reset) _docs = d.data;
        else _docs = _docs.concat(d.data);

        renderLista(d.has_more);
        document.getElementById('stat-docs').textContent = d.stats.total_docs;
        document.getElementById('stat-tamanio').textContent = formatBytes(d.stats.tamanio_total);
        document.getElementById('stat-destacados').textContent = d.stats.destacados;
    } catch (e) { /* silencioso */ }
}

function formatBytes(b) {
    if (b < 1024) return b + ' B';
    if (b < 1024*1024) return (b/1024).toFixed(0) + ' KB';
    if (b < 1024*1024*1024) return (b/1024/1024).toFixed(1) + ' MB';
    return (b/1024/1024/1024).toFixed(2) + ' GB';
}

function renderLista(hasMore) {
    const cont = document.getElementById('lista');
    if (!_docs.length) {
        cont.innerHTML = '<div class="lg-empty">Sin documentos para los filtros aplicados.</div>';
        document.getElementById('ver-mas-row').style.display = 'none';
        return;
    }
    cont.innerHTML = _docs.map(d => cardHtml(d)).join('');
    document.getElementById('ver-mas-row').style.display = hasMore ? 'block' : 'none';
}

function cardHtml(d) {
    const sel = _seleccionados.has(d.id) ? 'selected' : '';
    const dest = d.destacado ? 'destacado' : '';
    const thumb = d.tipo === 'imagen'
        ? `<img src="${d.preview_url}" alt="">`
        : (d.tipo === 'audio'
            ? `<div class="lg-mp" id="mp-${d.id}" data-src="${d.preview_url}" data-mime="${d.mime || 'audio/ogg'}" onclick="event.stopPropagation()">
                 <button class="lg-mp-play" onclick="mpToggle(${d.id})" type="button" aria-label="Reproducir">▶</button>
                 <div class="lg-mp-bar" onclick="mpSeek(event, ${d.id})"><div class="lg-mp-bar-fill"></div></div>
                 <span class="lg-mp-time">--:--</span>
               </div>`
            : `<span class="lg-thumb-icon">${iconoTipo(d.tipo, d.mime)}</span>`);
    const dirLabel = d.direccion === 'entrante' ? '← Recibido' : (d.direccion === 'saliente' ? '→ Enviado' : '📤 Manual');
    const autorLine = d.usuario ? ` · ${escTxt(d.usuario)}` : '';
    const cardClick = d.tipo === 'audio' ? '' : `onclick="abrirPreview(${d.id})"`;
    return `<div class="lg-card ${sel} ${dest}" ${cardClick}>
        <div class="lg-thumb ${d.tipo === 'audio' ? 'lg-thumb-audio' : ''}">
            <div class="lg-thumb-direccion">${dirLabel}</div>
            ${thumb}
        </div>
        <div class="lg-body">
            <div class="lg-name" title="${escTxt(d.nombre)}">${escTxt(d.nombre)}</div>
            <div class="lg-meta">
                <span>${d.fecha} ${d.hora}${autorLine}</span>
                <span>${d.tamanio_human}</span>
            </div>
        </div>
        <div class="lg-actions" onclick="event.stopPropagation()">
            <button class="lg-btn" onclick="toggleSeleccion(${d.id}, this)">${sel ? '✓ Sel.' : 'Seleccionar'}</button>
            <a class="lg-btn" href="/documentos/${d.id}/descargar" style="text-decoration:none;text-align:center;">↓</a>
            <button class="lg-btn warn" onclick="destacar(${d.id})" title="Destacar">${d.destacado ? '★' : '☆'}</button>
        </div>
    </div>`;
}

function iconoTipo(tipo, mime) {
    if (tipo === 'imagen') return '🖼';
    if (tipo === 'audio') return '🎤';
    if (tipo === 'video') return '🎥';
    if (mime === 'application/pdf') return '📄';
    if (mime?.includes('word')) return '📝';
    if (mime?.includes('sheet') || mime?.includes('excel')) return '📊';
    return '📎';
}

// ── Filtros ────────────────────────────────────────────────────
function setFiltro(filter, value) {
    _filtros[filter] = value;
    document.querySelectorAll(`.lg-chip[data-filter="${filter}"]`).forEach(el => {
        el.classList.toggle('active', el.dataset.value === value);
    });
    cargar();
}

function toggleDestacados() {
    _filtros.destacados = !_filtros.destacados;
    document.getElementById('chip-destacados').classList.toggle('active', _filtros.destacados);
    cargar();
}

function buscarDebounced() {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(() => {
        _filtros.q = document.getElementById('search').value.trim();
        cargar();
    }, 300);
}

function aplicarFiltros() {
    _filtros.desde = document.getElementById('desde').value;
    _filtros.hasta = document.getElementById('hasta').value;
    cargar();
}

function cargarMas() { _filtros.page++; cargar(false); }

// ── Selección múltiple ────────────────────────────────────────
function toggleSeleccion(id, btn) {
    if (_seleccionados.has(id)) _seleccionados.delete(id);
    else _seleccionados.add(id);
    btn.textContent = _seleccionados.has(id) ? '✓ Sel.' : 'Seleccionar';
    btn.closest('.lg-card').classList.toggle('selected', _seleccionados.has(id));
    actualizarBulkBar();
}

function limpiarSeleccion() {
    _seleccionados.clear();
    document.querySelectorAll('.lg-card.selected').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.lg-actions .lg-btn:first-child').forEach(b => {
        if (b.textContent.startsWith('✓')) b.textContent = 'Seleccionar';
    });
    actualizarBulkBar();
}

function actualizarBulkBar() {
    const bar = document.getElementById('bulk-bar');
    bar.classList.toggle('show', _seleccionados.size > 0);
    document.getElementById('bulk-count').textContent = _seleccionados.size + ' seleccionado' + (_seleccionados.size !== 1 ? 's' : '');
}

async function descargarZip() {
    if (!_seleccionados.size) return;
    const ids = Array.from(_seleccionados);
    const fd = new FormData();
    fd.append('_token', CSRF);
    ids.forEach(id => fd.append('ids[]', id));

    try {
        const r = await fetch(`/pacientes/${CONTACTO_ID}/documentos/zip`, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!r.ok) throw new Error('zip');
        const blob = await r.blob();
        const url  = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = r.headers.get('Content-Disposition')?.match(/filename="(.+?)"/)?.[1] ?? 'legajo.zip';
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
    } catch { v2toast('No se pudo generar el ZIP.', 'err'); }
}

async function eliminarSeleccionados() {
    if (!_seleccionados.size) return;
    if (!confirm(`¿Eliminar ${_seleccionados.size} documento(s)? No se puede deshacer.`)) return;
    for (const id of _seleccionados) {
        await fetch(`/documentos/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } });
    }
    limpiarSeleccion();
    cargar();
}

async function reenviarSeleccionados() {
    if (!_seleccionados.size) return;
    if (!CONTACTO_TIENE_WA) { v2toast('El contacto no tiene WhatsApp resuelto.', 'err'); return; }
    const total = _seleccionados.size;
    if (!confirm(`Re-enviar ${total} documento(s) al paciente por WhatsApp?\n\nSe envían uno a uno con un breve intervalo para no saturar el bot.`)) return;

    const btn = document.getElementById('btn-bulk-reenviar');
    btn.disabled = true;
    let ok = 0, fail = 0, errores = [];

    for (const id of _seleccionados) {
        btn.textContent = `Enviando… (${ok + fail + 1}/${total})`;
        try {
            const r = await fetch(`/documentos/${id}/reenviar`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            });
            const d = await r.json();
            if (d.ok) ok++;
            else { fail++; errores.push(`#${id}: ${d.error || 'error'}`); }
        } catch (e) {
            fail++;
            errores.push(`#${id}: error de red`);
        }
        await new Promise(r => setTimeout(r, 800));
    }

    btn.disabled = false;
    btn.textContent = '↗ Re-enviar al paciente';
    limpiarSeleccion();
    if (fail === 0) alert(`✓ ${ok} documento(s) re-enviado(s) por WhatsApp.`);
    else alert(`Enviados: ${ok}\nFallidos: ${fail}\n\n` + errores.slice(0, 5).join('\n'));
}

// ── Acciones por doc ──────────────────────────────────────────
async function destacar(id) {
    const r = await fetch(`/documentos/${id}/destacar`, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } });
    if ((await r.json()).ok) cargar();
}

// ── Modal preview ─────────────────────────────────────────────
function abrirPreview(id) {
    _docActual = _docs.find(d => d.id === id);
    if (!_docActual) return;
    const d = _docActual;

    document.getElementById('prev-titulo').textContent = d.nombre;
    document.getElementById('prev-meta').textContent = `${d.fecha} ${d.hora} · ${d.tamanio_human} · ${d.tipo}`;
    document.getElementById('prev-descargar').href = d.descarga_url;
    document.getElementById('btn-prev-destacar').textContent = d.destacado ? '★ Destacado' : '☆ Destacar';

    const body = document.getElementById('prev-body');
    if (d.tipo === 'imagen')        body.innerHTML = `<img src="${d.preview_url}">`;
    else if (d.tipo === 'video')    body.innerHTML = `<video src="${d.preview_url}" controls></video>`;
    else if (d.tipo === 'audio')    body.innerHTML = `<div style="padding:30px;width:100%;display:flex;justify-content:center;align-items:center;"><audio controls preload="metadata" style="width:520px;max-width:100%;"><source src="${d.preview_url}" type="${d.mime || 'audio/ogg'}">Tu navegador no puede reproducir este audio. <a href="${d.descarga_url}" style="color:#5af;">Descargar</a></audio></div>`;
    else if (d.mime === 'application/pdf') body.innerHTML = `<iframe src="${d.preview_url}"></iframe>`;
    else body.innerHTML = `<div style="color:#aaa;padding:30px;text-align:center;">Vista previa no disponible. <a href="${d.descarga_url}" style="color:#5af;">Descargar</a></div>`;

    document.getElementById('modal-preview').classList.add('show');
}

function cerrarPreview() {
    document.getElementById('modal-preview').classList.remove('show');
    document.getElementById('prev-body').innerHTML = '';
    _docActual = null;
}

async function prevDestacar() {
    if (!_docActual) return;
    await fetch(`/documentos/${_docActual.id}/destacar`, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } });
    cerrarPreview();
    cargar();
}

async function prevNotas() {
    if (!_docActual) return;
    const txt = prompt('Nota para este documento:', _docActual.notas ?? '');
    if (txt === null) return;
    await fetch(`/documentos/${_docActual.id}/notas`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ notas: txt }),
    });
    cerrarPreview();
    cargar();
}

async function prevReenviar() {
    if (!_docActual) return;
    if (!CONTACTO_TIENE_WA) { v2toast('El contacto no tiene WhatsApp resuelto.', 'err'); return; }
    if (!confirm(`Re-enviar "${_docActual.nombre}" al paciente por WhatsApp?`)) return;
    const r = await fetch(`/documentos/${_docActual.id}/reenviar`, {
        method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const d = await r.json();
    if (d.ok) { cerrarPreview(); v2toast('Documento re-enviado por WhatsApp.'); }
    else v2toast(d.error || 'No se pudo re-enviar.', 'err');
}

async function prevEliminar() {
    if (!_docActual) return;
    if (!confirm(`¿Eliminar "${_docActual.nombre}"? No se puede deshacer.`)) return;
    await fetch(`/documentos/${_docActual.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } });
    cerrarPreview();
    cargar();
}

// ── Subir manual ──────────────────────────────────────────────
function abrirSubir() {
    document.getElementById('up-file').value = '';
    document.getElementById('up-notas').value = '';
    document.getElementById('up-destacado').checked = false;
    document.getElementById('modal-subir').classList.add('show');
}

function cerrarSubir() {
    document.getElementById('modal-subir').classList.remove('show');
}

async function subirArchivo() {
    const f = document.getElementById('up-file').files[0];
    if (!f) { v2toast('Elegí un archivo.', 'err'); return; }

    const fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('archivo', f);
    fd.append('notas', document.getElementById('up-notas').value);
    fd.append('destacado', document.getElementById('up-destacado').checked ? '1' : '0');

    const btn = document.getElementById('btn-up');
    btn.disabled = true; btn.textContent = 'Subiendo…';
    try {
        const r = await fetch(`/pacientes/${CONTACTO_ID}/documentos/upload`, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Error');
        cerrarSubir();
        v2toast('Documento subido');
        cargar();
    } catch (e) {
        v2toast(e.message || 'No se pudo subir.', 'err');
    } finally {
        btn.disabled = false; btn.textContent = 'Guardar';
    }
}

// Click fuera cierra modales
document.getElementById('modal-preview').addEventListener('click', (e) => { if (e.target.id === 'modal-preview') cerrarPreview(); });
document.getElementById('modal-subir').addEventListener('click', (e) => { if (e.target.id === 'modal-subir') cerrarSubir(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { cerrarPreview(); cerrarSubir(); } });

// ── Mini-player de audio (custom) ────────────────────────────────────
const _mp = {};
let _mpActivo = null;

function mpToggle(id) {
    const cont = document.getElementById('mp-' + id);
    if (!cont) return;
    let st = _mp[id];

    if (!st) {
        const audio = new Audio();
        audio.preload = 'metadata';
        audio.src = cont.dataset.src;
        st = {
            audio,
            btn:  cont.querySelector('.lg-mp-play'),
            fill: cont.querySelector('.lg-mp-bar-fill'),
            time: cont.querySelector('.lg-mp-time'),
            playing: false,
        };
        _mp[id] = st;
        audio.addEventListener('loadedmetadata', () => { st.time.textContent = mpFmt(audio.duration); });
        audio.addEventListener('timeupdate', () => {
            const dur = audio.duration || 0;
            if (dur > 0) st.fill.style.width = ((audio.currentTime / dur) * 100) + '%';
            st.time.textContent = mpFmt(audio.currentTime) + (dur > 0 ? ' / ' + mpFmt(dur) : '');
        });
        audio.addEventListener('ended', () => { st.btn.textContent = '▶'; st.playing = false; st.fill.style.width = '0%'; });
        audio.addEventListener('error', () => {
            st.btn.disabled = true; st.btn.textContent = '!';
            st.btn.title = 'No se pudo cargar el audio';
            cont.insertAdjacentHTML('afterend', '<div class="lg-mp-err">Error al cargar el audio. <a href="' + cont.dataset.src.replace('/preview', '/descargar') + '" style="color:var(--info);">Descargar</a></div>');
        });
    }

    if (st.playing) {
        st.audio.pause();
        st.btn.textContent = '▶';
        st.playing = false;
    } else {
        if (_mpActivo && _mpActivo !== id && _mp[_mpActivo]) {
            _mp[_mpActivo].audio.pause();
            _mp[_mpActivo].btn.textContent = '▶';
            _mp[_mpActivo].playing = false;
        }
        _mpActivo = id;
        st.audio.play().then(() => {
            st.btn.textContent = '⏸';
            st.playing = true;
        }).catch(err => {
            console.error('mp play error', err);
            st.btn.textContent = '!';
            st.btn.title = 'No se pudo reproducir: ' + err.message;
        });
    }
}

function mpSeek(ev, id) {
    const st = _mp[id];
    if (!st || !st.audio.duration) return;
    const bar = ev.currentTarget;
    const rect = bar.getBoundingClientRect();
    const pct = Math.max(0, Math.min(1, (ev.clientX - rect.left) / rect.width));
    st.audio.currentTime = pct * st.audio.duration;
}

function mpFmt(s) {
    if (!isFinite(s)) return '--:--';
    const m = Math.floor(s / 60);
    const ss = Math.floor(s % 60).toString().padStart(2, '0');
    return m + ':' + ss;
}

cargar();
</script>
@endpush
