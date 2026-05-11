<div class="card-title">¿Qué colas atendés hoy?</div>

<div class="usuario-badge">
    <div class="usuario-avatar">{{ strtoupper(substr($usuario->nombre_completo, 0, 1)) }}</div>
    <div>
        <div class="usuario-nombre">{{ $usuario->nombre_completo }}</div>
        <div class="usuario-rol">{{ \App\Models\User::ROLES[$usuario->rol] ?? $usuario->rol }}</div>
    </div>
</div>

@if ($error)
    <div class="error-msg" style="margin-bottom:16px;">{{ $error }}</div>
@endif

@php
    $iconos = [
        'recepcion'      => '🏥',
        'turnos'         => '📅',
        'ordenes'        => '📋',
        'facturacion'    => '💳',
        'coordinacion'   => '🤝',
        'atencion'       => '💬',
        'administracion' => '🗂️',
        'ovodonacion'    => '🥚',
    ];
@endphp

@if (!empty($areas ?? []))
    <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin:4px 0 8px;">
        Números / áreas que atendés
    </div>
    <div class="colas-grid">
        @foreach ($areas as $key => $label)
            <div class="cola-item {{ in_array($key, $colasSeleccionadas) ? 'selected' : '' }}"
                 wire:click="toggleCola('{{ $key }}')">
                <span class="cola-icon">{{ $iconos[$key] ?? '•' }}</span>
                {{ $label }}
            </div>
        @endforeach
    </div>
    <div style="font-size:11px;color:var(--muted);margin:8px 0 16px;">Si no marcás ninguna, ves las conversaciones de todas las áreas.</div>

    <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin:4px 0 8px;">
        Tipo de consulta
    </div>
@endif

<div class="colas-grid">
    @foreach ($todasLasColas as $key => $label)
        <div class="cola-item {{ in_array($key, $colasSeleccionadas) ? 'selected' : '' }}"
             wire:click="toggleCola('{{ $key }}')">
            <span class="cola-icon">{{ $iconos[$key] ?? '•' }}</span>
            {{ $label }}
        </div>
    @endforeach
</div>

<button class="btn" wire:click="confirmar" wire:loading.attr="disabled"
        {{ empty($colasSeleccionadas) ? 'disabled' : '' }}>
    <span wire:loading.remove>Empezar turno →</span>
    <span wire:loading>Guardando...</span>
</button>

<button class="btn btn-ghost" onclick="window.location='/logout'">
    Cerrar sesión
</button>
