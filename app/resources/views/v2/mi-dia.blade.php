@extends('layouts.v2')
@section('title', 'Mi día')

{{-- Home "Mi día" (concepto V2 §6): saludo contextual + KPIs clickeables +
     los pendientes accionables del usuario. Sin cards de navegación que
     dupliquen el sidebar — las listas son contenido, los KPIs son atajos.
     Completar una tarea pega al PATCH /tareas/{id} real (update optimista). --}}

@push('styles')
<style>
.md-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin-bottom:22px; }
.md-kpi {
    display:block; text-decoration:none; color:var(--v2-text);
    background:var(--v2-bg-card); border:1px solid var(--v2-border);
    border-radius:var(--v2-radius); padding:14px 16px; transition:border-color .12s;
}
.md-kpi:hover { border-color:var(--v2-border-strong); }
.md-kpi .n { font-size:26px; font-weight:700; line-height:1.1; font-family:'JetBrains Mono',monospace; }
.md-kpi .l { font-size:12px; font-weight:600; color:var(--v2-text-2); margin-top:2px; }
.md-kpi .sub { font-size:11px; color:var(--v2-text-mute); margin-top:3px; min-height:14px; }

.md-col-title { font-size:11px; font-weight:600; color:var(--v2-text-mute); text-transform:uppercase; letter-spacing:.6px; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
.md-col-title a { margin-left:auto; font-size:11px; text-transform:none; letter-spacing:0; color:var(--v2-accent); text-decoration:none; font-weight:600; }
.md-card { background:var(--v2-bg-card); border:1px solid var(--v2-border); border-radius:var(--v2-radius); overflow:hidden; }

.md-tarea { display:flex; align-items:center; gap:10px; padding:10px 14px; border-bottom:1px solid var(--v2-border); transition:opacity .25s; }
.md-tarea:last-child { border-bottom:none; }
.md-tarea.done { opacity:.35; }
.md-tarea.done .tt { text-decoration:line-through; }
.md-check {
    width:20px; height:20px; border-radius:50%; flex-shrink:0;
    border:1.5px solid var(--v2-border-strong); background:transparent;
    cursor:pointer; transition:.12s; display:flex; align-items:center; justify-content:center;
    color:transparent; font-size:11px; line-height:1;
}
.md-check:hover { border-color:var(--v2-ok); color:var(--v2-ok); }
.md-tarea .tt { font-size:13px; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.md-tarea .meta { margin-left:auto; flex-shrink:0; font-size:11px; color:var(--v2-text-mute); font-family:'JetBrains Mono',monospace; }

.md-conv { display:flex; align-items:center; gap:10px; padding:10px 14px; border-bottom:1px solid var(--v2-border); text-decoration:none; color:var(--v2-text); }
.md-conv:last-child { border-bottom:none; }
.md-conv:hover { background:var(--v2-bg-hover); }
.md-conv .quien { font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px; flex-shrink:0; }
.md-conv .resumen { font-size:12px; color:var(--v2-text-mute); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; }
.md-conv .hace { font-size:11px; color:var(--v2-text-mute); flex-shrink:0; }
.md-badge-nl { background:var(--v2-accent); color:#fff; border-radius:10px; padding:0 7px; font-size:11px; font-weight:700; flex-shrink:0; }

.md-empty { padding:26px 14px; text-align:center; font-size:13px; color:var(--v2-text-mute); }

/* Acciones rápidas (header derecho) */
.md-quick { display:flex; gap:8px; flex-shrink:0; }
.md-qbtn {
    display:inline-flex; align-items:center; gap:5px; padding:8px 13px;
    border:1px solid var(--v2-border); background:var(--v2-bg-card); color:var(--v2-text);
    border-radius:var(--v2-radius-sm); font-size:13px; font-weight:600; cursor:pointer; transition:.12s;
}
.md-qbtn:hover { border-color:var(--v2-border-strong); background:var(--v2-bg-hover); }
.md-qbtn.primary { background:var(--v2-accent); border-color:var(--v2-accent); color:#fff; }
.md-qbtn.primary:hover { filter:brightness(.94); }

/* Pulso del equipo (franja inferior) */
.md-pulso { margin-top:22px; }
.md-pulso-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; }
.md-pulso-area {
    display:flex; flex-direction:column; gap:2px; text-decoration:none; color:var(--v2-text);
    background:var(--v2-bg-card); border:1px solid var(--v2-border);
    border-radius:var(--v2-radius); padding:11px 14px; transition:border-color .12s;
}
.md-pulso-area:hover { border-color:var(--v2-border-strong); }
.md-pulso-area .pa-label { font-size:12px; font-weight:600; color:var(--v2-text-2); }
.md-pulso-area .pa-n { font-size:20px; font-weight:700; font-family:'JetBrains Mono',monospace; line-height:1.1; }
.md-pulso-area .pa-n small { font-size:11px; font-weight:500; color:var(--v2-text-mute); margin-left:4px; font-family:inherit; }
.md-pulso-area .pa-urg { font-size:11px; color:var(--v2-urg); font-weight:600; margin-top:2px; }
.md-online { margin-top:12px; font-size:12px; color:var(--v2-text-2); display:flex; align-items:center; gap:7px; }

/* Búsqueda de contacto dentro del modal de nueva conversación */
.md-tabs { display:flex; gap:6px; margin-bottom:4px; }
.md-tab { flex:1; padding:7px; border:1px solid var(--v2-border); background:transparent; color:var(--v2-text-2); border-radius:var(--v2-radius-sm); font-size:12.5px; font-weight:600; cursor:pointer; }
.md-tab.active { background:var(--v2-accent-bg); color:var(--v2-accent); border-color:var(--v2-accent); }
.md-results { border:1px solid var(--v2-border); border-radius:var(--v2-radius-sm); margin-top:6px; max-height:180px; overflow-y:auto; }
.md-result { padding:8px 11px; cursor:pointer; border-bottom:1px solid var(--v2-border); }
.md-result:last-child { border-bottom:none; }
.md-result:hover { background:var(--v2-bg-hover); }
.md-result.selected { background:var(--v2-accent-bg); }
.md-result .rn { font-size:13px; font-weight:600; }
.md-result .rt { font-size:11px; color:var(--v2-text-mute); }
</style>
@endpush

@section('content')
@php
    $u = auth()->user();
    $nombre = explode(' ', $u->nombre_completo ?? '')[0];
    $h = (int) now()->format('G');
    [$saludo, $emoji] = $h >= 5 && $h < 13 ? ['Buen día', '☀️'] : ($h < 20 ? ['Buenas tardes', '🌤️'] : ['Buenas noches', '🌙']);
    $dias  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
    $meses = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $fecha = $dias[now()->dayOfWeek] . ' ' . now()->day . ' de ' . $meses[now()->month];
@endphp

<div style="flex:1;overflow-y:auto;padding:24px;">
<div style="max-width:980px;margin:0 auto;">

    <div style="margin-bottom:20px;display:flex;align-items:flex-start;gap:12px;">
        <div style="flex:1;min-width:0;">
            <h1 style="font-size:20px;font-weight:650;margin:0;">{{ $saludo }}, {{ $nombre }} {{ $emoji }}</h1>
            <div style="font-size:13px;color:var(--v2-text-mute);margin-top:3px;">{{ ucfirst($fecha) }}</div>
        </div>
        <div class="md-quick">
            @if($tieneAtencion)
            <button class="md-qbtn" onclick="abrirNuevaConv()" title="Iniciar una conversación de WhatsApp">💬 Conversación</button>
            @endif
            <button class="md-qbtn primary" onclick="abrirNuevaTarea()" title="Crear una tarea rápida">+ Tarea</button>
        </div>
    </div>

    @if($tieneAtencion)
    <div class="md-kpis">
        <a class="md-kpi" href="/v2/mis-conversaciones">
            <div class="n">{{ $convTotal }}</div>
            <div class="l">Mis conversaciones</div>
            <div class="sub">
                @if($convUrgentes > 0)
                <span style="color:var(--v2-urg);font-weight:600;">{{ $convUrgentes }} urgente{{ $convUrgentes !== 1 ? 's' : '' }}</span>{{ $convNoLeidos > 0 ? ' · ' : '' }}
                @endif
                {{ $convNoLeidos > 0 ? $convNoLeidos . ' sin leer' : ($convUrgentes === 0 ? 'al día' : '') }}
            </div>
        </a>
        <a class="md-kpi" href="/v2/centro-tareas">
            <div class="n">{{ $tareasPend }}</div>
            <div class="l">Tareas pendientes</div>
            <div class="sub">{{ count($tareasHoy) > 0 ? count($tareasHoy) . ' vence' . (count($tareasHoy) !== 1 ? 'n' : '') . ' hoy o antes' : 'nada vence hoy' }}</div>
        </a>
        <a class="md-kpi" href="/v2/atencion">
            <div class="n">{{ $sinAsignar }}</div>
            <div class="l">Sin asignar en colas</div>
            <div class="sub">{{ $sinAsignar > 0 ? 'esperando que alguien las tome' : 'colas al día' }}</div>
        </a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start;">

        <div>
            <div class="md-col-title">⏰ Para hoy
                @if($tieneAgenda)<a href="/v2/agenda">Ver agenda →</a>@endif
            </div>
            <div class="md-card" id="md-tareas">
                @forelse($tareasHoy as $t)
                <div class="md-tarea" id="md-tarea-{{ $t['id'] }}">
                    <button class="md-check" title="Marcar completada" onclick="completar({{ $t['id'] }})">✓</button>
                    @if($t['prioridad'] === 'alta')<span class="v2-dot err" title="Prioridad alta" style="flex-shrink:0;"></span>@endif
                    <span class="tt">{{ $t['titulo'] }}</span>
                    <span class="meta">
                        @if($t['vencida'])<span style="color:var(--v2-urg);font-weight:600;">venció {{ $t['fecha'] }}</span>
                        @else{{ $t['hora'] === '00:00' ? 'hoy' : $t['hora'] }}@endif
                    </span>
                </div>
                @empty
                <div class="md-empty">Nada vence hoy 🎉</div>
                @endforelse
            </div>
        </div>

        <div>
            <div class="md-col-title">💬 Mis conversaciones
                <a href="/v2/mis-conversaciones">Ver todas →</a>
            </div>
            <div class="md-card">
                @forelse($misConvs as $c)
                <a class="md-conv" href="/v2/mis-conversaciones">
                    @if($c['urgente'])<span class="v2-pill urgente" style="flex-shrink:0;">Urgente</span>@endif
                    <span class="quien">{{ $c['contacto'] }}</span>
                    <span class="resumen">{{ $c['resumen'] }}</span>
                    @if($c['no_leidos'] > 0)<span class="md-badge-nl">{{ $c['no_leidos'] }}</span>@endif
                    <span class="hace">{{ $c['hace'] }}</span>
                </a>
                @empty
                <div class="md-empty">No tenés conversaciones asignadas</div>
                @endforelse
            </div>
        </div>

    </div>

    <div class="md-pulso">
        <div class="md-col-title">📊 Pulso del equipo</div>
        <div class="md-pulso-row">
            @foreach($pulso as $p)
            <a class="md-pulso-area" href="/v2/atencion/{{ $p['key'] }}">
                <span class="pa-label">{{ $p['label'] }}</span>
                <span class="pa-n">{{ $p['abiertas'] }}<small>abiertas</small></span>
                @if($p['urgentes'] > 0)<span class="pa-urg">{{ $p['urgentes'] }} urgente{{ $p['urgentes'] !== 1 ? 's' : '' }} sin tomar</span>@else<span style="font-size:11px;color:var(--v2-text-mute);margin-top:2px;">sin urgentes pendientes</span>@endif
            </a>
            @endforeach
        </div>
        <div class="md-online">
            <span class="v2-dot ok"></span>
            @if(count($enLinea))
                En línea: {{ collect($enLinea)->map(fn($n, $id) => $id === auth()->id() ? 'vos' : explode(' ', $n)[0])->implode(' · ') }}
            @else
                Nadie conectado en los últimos 5 minutos
            @endif
        </div>
    </div>
    @else
    <div class="md-card"><div class="md-empty">Tu usuario no tiene módulos de atención asignados. Usá el menú de la izquierda para ir a tu sección.</div></div>
    @endif

</div>
</div>

{{-- Modal: nueva tarea rápida (mismos campos y endpoint /agenda que la pantalla Agenda) --}}
<dialog class="v2-dialog" id="md-modal-tarea" style="width:min(460px,calc(100vw - 40px));">
    <h3>Nueva tarea</h3>
    <label class="v2-label" style="margin-top:4px;">Título *</label>
    <input type="text" id="mt-titulo" class="v2-field" placeholder="Descripción breve">
    <label class="v2-label">Detalle</label>
    <textarea id="mt-desc" class="v2-field" placeholder="Información adicional…" style="min-height:64px;resize:vertical;"></textarea>
    <div class="v2-grid2">
        <div>
            <label class="v2-label">Asignar a</label>
            <select id="mt-asig" class="v2-field">
                <option value="">Sin asignar</option>
                @foreach($usuarios as $usr)
                <option value="{{ $usr->id }}">{{ $usr->nombre_completo }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="v2-label">Prioridad</label>
            <select id="mt-prioridad" class="v2-field">
                <option value="normal">Normal</option>
                <option value="alta">Alta</option>
                <option value="baja">Baja</option>
            </select>
        </div>
    </div>
    <label class="v2-label">Vence el</label>
    <input type="datetime-local" id="mt-vence" class="v2-field">
    <div class="v2-dialog-foot">
        <button class="v2-btn" onclick="document.getElementById('md-modal-tarea').close()">Cancelar</button>
        <button class="v2-btn primary" id="mt-guardar" onclick="guardarTareaRapida()">Crear tarea</button>
    </div>
</dialog>

@if($tieneAtencion)
{{-- Modal: iniciar conversación WA (reusa POST /atencion/iniciar de producción) --}}
<dialog class="v2-dialog" id="md-modal-conv" style="width:min(480px,calc(100vw - 40px));">
    <h3>Iniciar conversación</h3>
    <label class="v2-label" style="margin-top:4px;">Área</label>
    <select id="mc-area" class="v2-field">
        @foreach($areas as $k => $label)
        <option value="{{ $k }}">{{ $label }}</option>
        @endforeach
    </select>

    <div class="md-tabs" style="margin-top:10px;">
        <button type="button" class="md-tab active" id="mc-tab-contacto" onclick="mcModo('contacto')">Buscar contacto</button>
        <button type="button" class="md-tab" id="mc-tab-manual" onclick="mcModo('manual')">Número manual</button>
    </div>

    <div id="mc-sec-contacto">
        <input type="text" id="mc-search" class="v2-field" placeholder="Nombre, teléfono o DNI…" oninput="mcBuscar(this.value)">
        <div class="md-results" id="mc-resultados" style="display:none;"></div>
    </div>
    <div id="mc-sec-manual" style="display:none;">
        <input type="text" id="mc-telefono" class="v2-field" placeholder="ej: 2231234567" inputmode="numeric">
        <div style="font-size:11px;color:var(--v2-text-mute);margin-top:4px;">Argentina · 10 dígitos con código de área (sin 0 ni 15)</div>
    </div>

    <label class="v2-label">Mensaje inicial</label>
    <textarea id="mc-texto" class="v2-field" placeholder="Escribí el primer mensaje…" style="min-height:70px;resize:vertical;"></textarea>

    <div class="v2-dialog-foot">
        <button class="v2-btn" onclick="document.getElementById('md-modal-conv').close()">Cancelar</button>
        <button class="v2-btn primary" id="mc-enviar" onclick="enviarNuevaConv()">Iniciar y enviar</button>
    </div>
</dialog>
@endif
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// Completar tarea: update optimista (tacha y desvanece) + PATCH real.
async function completar(id) {
    const row = document.getElementById(`md-tarea-${id}`);
    row.classList.add('done');
    try {
        const r = await fetch(`/tareas/${id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ estado: 'completada' }),
        });
        if (!r.ok) throw 0;
        v2toast('Tarea completada');
        setTimeout(() => {
            row.remove();
            const cont = document.getElementById('md-tareas');
            if (!cont.querySelector('.md-tarea')) {
                cont.innerHTML = '<div class="md-empty">Nada vence hoy 🎉</div>';
            }
        }, 600);
    } catch (e) {
        row.classList.remove('done');
        v2toast('No se pudo completar', 'err');
    }
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

// ── Nueva tarea rápida ─────────────────────────────────────────
function abrirNuevaTarea() {
    document.getElementById('mt-titulo').value = '';
    document.getElementById('mt-desc').value = '';
    document.getElementById('mt-asig').value = '{{ auth()->id() }}';
    document.getElementById('mt-prioridad').value = 'normal';
    document.getElementById('mt-vence').value = '';
    document.getElementById('md-modal-tarea').showModal();
    setTimeout(() => document.getElementById('mt-titulo').focus(), 50);
}

async function guardarTareaRapida() {
    const titulo = document.getElementById('mt-titulo').value.trim();
    if (!titulo) { document.getElementById('mt-titulo').focus(); return; }
    const btn = document.getElementById('mt-guardar');
    btn.disabled = true; btn.textContent = 'Creando…';
    try {
        const r = await fetch('/agenda', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                titulo,
                descripcion: document.getElementById('mt-desc').value.trim() || null,
                asignada_a:  document.getElementById('mt-asig').value || null,
                prioridad:   document.getElementById('mt-prioridad').value,
                vence_at:    document.getElementById('mt-vence').value || null,
            }),
        });
        if (!r.ok) throw 0;
        document.getElementById('md-modal-tarea').close();
        v2toast('Tarea creada');
        setTimeout(() => location.reload(), 600);
    } catch (e) {
        v2toast('No se pudo crear la tarea', 'err');
        btn.disabled = false; btn.textContent = 'Crear tarea';
    }
}

// ── Nueva conversación WA ──────────────────────────────────────
let _mcModo = 'contacto', _mcSel = null, _mcTimer = null, _mcResults = [];

function abrirNuevaConv() {
    _mcSel = null;
    document.getElementById('mc-search').value = '';
    document.getElementById('mc-telefono').value = '';
    document.getElementById('mc-texto').value = '';
    const res = document.getElementById('mc-resultados');
    res.style.display = 'none'; res.innerHTML = '';
    mcModo('contacto');
    document.getElementById('md-modal-conv').showModal();
    setTimeout(() => document.getElementById('mc-search').focus(), 50);
}

function mcModo(modo) {
    _mcModo = modo; _mcSel = null;
    document.getElementById('mc-tab-contacto').classList.toggle('active', modo === 'contacto');
    document.getElementById('mc-tab-manual').classList.toggle('active', modo === 'manual');
    document.getElementById('mc-sec-contacto').style.display = modo === 'contacto' ? '' : 'none';
    document.getElementById('mc-sec-manual').style.display = modo === 'manual' ? '' : 'none';
}

function mcBuscar(q) {
    clearTimeout(_mcTimer);
    const cont = document.getElementById('mc-resultados');
    if (!q || q.length < 2) { cont.style.display = 'none'; cont.innerHTML = ''; return; }
    _mcTimer = setTimeout(async () => {
        try {
            const r = await fetch('/contactos/data?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json();
            _mcResults = (j.data || []).filter(c => c.telefono).slice(0, 10);
            cont.style.display = 'block';
            if (!_mcResults.length) { cont.innerHTML = '<div class="md-result" style="cursor:default;color:var(--v2-text-mute);">Sin resultados con teléfono</div>'; return; }
            cont.innerHTML = _mcResults.map((c, i) =>
                `<div class="md-result" onclick="mcSel(${i})">
                    <div class="rn">${escHtml(c.nombre)}</div>
                    <div class="rt">${escHtml(c.telefono)}${c.dni ? ' · DNI ' + escHtml(c.dni) : ''}</div>
                </div>`
            ).join('');
        } catch (e) { /* silencioso */ }
    }, 250);
}

function mcSel(i) {
    const c = _mcResults[i];
    if (!c) return;
    _mcSel = { id: c.id, nombre: c.nombre, telefono: c.telefono };
    document.getElementById('mc-resultados').innerHTML =
        `<div class="md-result selected"><div class="rn">✓ ${escHtml(c.nombre)}</div><div class="rt">${escHtml(c.telefono)}</div></div>`;
    document.getElementById('mc-search').value = c.nombre;
}

async function enviarNuevaConv() {
    const texto = document.getElementById('mc-texto').value.trim();
    if (!texto) { v2toast('Falta el mensaje', 'err'); return; }
    const body = { texto, area: document.getElementById('mc-area').value };
    if (_mcModo === 'contacto') {
        if (!_mcSel) { v2toast('Seleccioná un contacto', 'err'); return; }
        body.contacto_id = _mcSel.id;
    } else {
        const tel = document.getElementById('mc-telefono').value.trim();
        if (!tel) { v2toast('Falta el número', 'err'); return; }
        body.telefono = tel;
    }
    const btn = document.getElementById('mc-enviar');
    btn.disabled = true; btn.textContent = 'Verificando…';
    try {
        const r = await fetch('/atencion/iniciar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(j.message || j.error || '');
        document.getElementById('md-modal-conv').close();
        v2toast(j.reusada ? 'Conversación reabierta' : 'Conversación creada');
        setTimeout(() => location.href = '/v2/atencion/' + body.area, 400);
    } catch (e) {
        v2toast(e.message || 'No se pudo iniciar la conversación', 'err');
        btn.disabled = false; btn.textContent = 'Iniciar y enviar';
    }
}
</script>
@endpush
