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

    <div style="margin-bottom:20px;">
        <h1 style="font-size:20px;font-weight:650;margin:0;">{{ $saludo }}, {{ $nombre }} {{ $emoji }}</h1>
        <div style="font-size:13px;color:var(--v2-text-mute);margin-top:3px;">{{ ucfirst($fecha) }}</div>
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
    @else
    <div class="md-card"><div class="md-empty">Tu usuario no tiene módulos de atención asignados. Usá el menú de la izquierda para ir a tu sección.</div></div>
    @endif

</div>
</div>
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
</script>
@endpush
