<div>
@php
    $tienePendientes = $conversaciones->isNotEmpty() || $tareas->isNotEmpty();
    $iconos = [
        'recepcion'=>'🏥','turnos'=>'📅','ordenes'=>'📋','facturacion'=>'💳','coordinacion'=>'🤝',
        'atencion'=>'💬','administracion'=>'🗂️','ovodonacion'=>'🥚',
    ];
    $primerNombre = explode(' ', trim($usuario->nombre_completo))[0] ?: $usuario->nombre_completo;
@endphp

<style>
    body { align-items: flex-start; padding-top: 32px; }
    .wrap { max-width: 1180px !important; }

    .dc-greet { text-align: center; margin-bottom: 6px; font-size: 19px; font-weight: 700; }
    .dc-sub   { text-align: center; font-size: 13px; color: var(--muted); margin-bottom: 18px; }
    .dc-err   { max-width: 640px; margin: 0 auto 16px; }

    .dc-blocks {
        display: flex; flex-wrap: wrap; gap: 16px;
        align-items: flex-start; justify-content: center; margin-bottom: 18px;
    }
    .dc-block {
        flex: 1 1 300px; min-width: 280px; max-width: 400px;
        background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px;
    }
    .dc-block-title {
        font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;
        letter-spacing: .5px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
    }
    .dc-hint { font-size: 11px; color: var(--muted); margin-top: 10px; line-height: 1.4; }
    .dc-actions { display: flex; flex-direction: column; gap: 10px; align-items: center; max-width: 340px; margin: 4px auto 0; }

    /* Tareas / conversaciones pendientes (bloque 3) */
    .pend-sub { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin: 4px 0 8px; display: flex; align-items: center; gap: 6px; }
    .pend-sub + .pend-sub, .pend-card + .pend-sub { margin-top: 14px; }
    .pend-count { background: var(--surface); color: var(--text); border-radius: 10px; padding: 1px 8px; font-size: 10px; font-weight: 700; letter-spacing: 0; }
    .pend-card {
        display: block; text-decoration: none; color: inherit;
        background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
        padding: 9px 11px; margin-bottom: 6px; transition: border-color .15s, background .15s;
    }
    .pend-card:hover { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 5%, var(--bg)); }
    .pend-head { display: flex; align-items: center; gap: 6px; margin-bottom: 3px; }
    .pend-name { font-size: 13px; font-weight: 600; color: var(--text); flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pend-meta { font-size: 11px; color: var(--muted); white-space: nowrap; }
    .pend-preview { font-size: 12px; color: var(--muted); line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pend-badge { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: .3px; }
    .badge-urgente, .badge-vencida { background: color-mix(in srgb, var(--error) 12%, transparent); color: var(--error); border: 1px solid color-mix(in srgb, var(--error) 35%, transparent); }
    .badge-alta { background: color-mix(in srgb, var(--warning) 12%, transparent); color: var(--warning); border: 1px solid color-mix(in srgb, var(--warning) 35%, transparent); }
    .badge-unread { background: var(--accent); color: #fff; border-radius: 10px; padding: 1px 7px; font-size: 10px; font-weight: 700; }
</style>

<div class="dc-greet">Hola, {{ $primerNombre }} 👋</div>
<div class="dc-sub">{{ \App\Models\User::ROLES[$usuario->rol] ?? $usuario->rol }} · marcá qué atendés en este turno</div>

@if ($error)
    <div class="error-msg dc-err">{{ $error }}</div>
@endif

<div class="dc-blocks">
    {{-- ── Bloque 1: Teléfonos / áreas ─────────────────── --}}
    @if (!empty($areas ?? []))
    <div class="dc-block">
        <div class="dc-block-title">📞 Teléfonos / áreas</div>
        <div class="colas-grid">
            @foreach ($areas as $key => $label)
                <div class="cola-item {{ in_array($key, $colasSeleccionadas) ? 'selected' : '' }}"
                     wire:click="toggleCola('{{ $key }}')">
                    <span class="cola-icon">{{ $iconos[$key] ?? '•' }}</span>
                    {{ $label }}
                </div>
            @endforeach
        </div>
        <div class="dc-hint">Si no marcás ninguno, ves las conversaciones de todos los números.</div>
    </div>
    @endif

    {{-- ── Bloque 2: Tipo de consulta (colas) ──────────── --}}
    <div class="dc-block">
        <div class="dc-block-title">🗂️ Tipo de consulta</div>
        <div class="colas-grid">
            @foreach ($todasLasColas as $key => $label)
                <div class="cola-item {{ in_array($key, $colasSeleccionadas) ? 'selected' : '' }}"
                     wire:click="toggleCola('{{ $key }}')">
                    <span class="cola-icon">{{ $iconos[$key] ?? '•' }}</span>
                    {{ $label }}
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Bloque 3: Lo que tenés pendiente ────────────── --}}
    @if($tienePendientes)
    <div class="dc-block">
        <div class="dc-block-title">📋 Lo que tenés pendiente</div>

        @if($conversaciones->isNotEmpty())
            <div class="pend-sub">💬 Conversaciones a tu cargo <span class="pend-count">{{ $conversaciones->count() }}</span></div>
            @foreach($conversaciones as $c)
                <a class="pend-card" href="/atencion?conv_id={{ $c['id'] }}">
                    <div class="pend-head">
                        <span class="pend-name">{{ $c['nombre'] }}</span>
                        @if($c['urgente'])<span class="pend-badge badge-urgente">Urgente</span>@endif
                        @if($c['no_leidos'] > 0)<span class="badge-unread">{{ $c['no_leidos'] }}</span>@endif
                        <span class="pend-meta">{{ $c['hace'] }}</span>
                    </div>
                    <div class="pend-preview">{{ $c['preview'] }}</div>
                </a>
            @endforeach
        @endif

        @if($tareas->isNotEmpty())
            <div class="pend-sub">📌 Tareas pendientes <span class="pend-count">{{ $tareas->count() }}</span></div>
            @foreach($tareas as $t)
                <a class="pend-card" href="/centro-tareas?tarea_id={{ $t['id'] }}">
                    <div class="pend-head">
                        <span class="pend-name">{{ $t['titulo'] }}</span>
                        @if($t['vencida'])
                            <span class="pend-badge badge-vencida">Vencida</span>
                        @elseif($t['prioridad'] === 'alta')
                            <span class="pend-badge badge-alta">Alta</span>
                        @endif
                        @if($t['vence_at'])<span class="pend-meta">vence {{ $t['vence_at'] }}</span>@endif
                    </div>
                    @if($t['descripcion'])
                        <div class="pend-preview">{{ $t['descripcion'] }}</div>
                    @elseif($t['creada_por'])
                        <div class="pend-preview">creada por {{ $t['creada_por'] }}</div>
                    @endif
                </a>
            @endforeach
        @endif
    </div>
    @endif
</div>

<div class="dc-actions">
    <button class="btn" wire:click="confirmar" wire:loading.attr="disabled"
            {{ empty($colasSeleccionadas) ? 'disabled' : '' }}>
        <span wire:loading.remove>Empezar turno →</span>
        <span wire:loading>Guardando...</span>
    </button>
    <button class="btn btn-ghost" onclick="window.location='/logout'">Cerrar sesión</button>
</div>
</div>
