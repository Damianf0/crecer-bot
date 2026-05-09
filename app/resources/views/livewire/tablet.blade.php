<div style="display:contents;">

{{-- ── PASO: inicio ──────────────────────────────────────── --}}
{{-- El DNI vive en Alpine (cliente) para evitar un round-trip Livewire por
     cada dígito tipeado en el keypad. Solo al "Confirmar" hay 1 POST con el
     DNI completo y se llama a Omnia. --}}
@if ($paso === 'inicio')
<div class="two-col" x-data="{ dni: '', buscando: false }" wire:key="paso-inicio">
    <div class="col-left">
        <p class="step-title">¡Bienvenidos!</p>
        <p class="step-sub">Ingresá tu número de DNI para anunciarte</p>
        <div class="dni-display" :class="{ empty: !dni }">
            <span x-text="dni || '· · · · · · · ·'"></span>
        </div>
        <p class="error-msg">{{ $error ?: '' }}</p>
    </div>
    <div class="col-right">
        <div class="keypad">
            @foreach (['1','2','3','4','5','6','7','8','9','0'] as $d)
                @if ($d === '0')
                    <button class="key vacio" tabindex="-1"></button>
                @endif
                <button type="button" class="key" @click="if (dni.length < 8) dni += '{{ $d }}'">{{ $d }}</button>
            @endforeach
            <button type="button" class="key borrar" @click="dni = dni.slice(0, -1)">⌫</button>
        </div>
        <button type="button" class="btn btn-primary"
            @click="buscando = true; $wire.buscarDni(dni)"
            :disabled="dni.length < 7 || buscando"
            x-text="buscando ? 'Buscando…' : 'Confirmar →'">
        </button>
    </div>
</div>
@endif

{{-- ── PASO: turno ───────────────────────────────────────── --}}
@if ($paso === 'turno')
<div class="two-col">
    <div class="col-left">
        @if ($paciente['primera_vez'] ?? false)
            <div class="primera-vez-badge">⭐ Primera consulta</div>
        @endif
        <p class="paciente-nombre">
            {{ $paciente['apellido'] ? $paciente['apellido'].', '.$paciente['nombre'] : $paciente['nombre'] }}
        </p>
        <p class="paciente-os">
            {{ $paciente['obra_social'] }}{{ $paciente['plan'] ? ' · '.$paciente['plan'] : '' }}
        </p>

        @if (count($turnos) === 1)
            <p class="step-sub" style="margin-bottom:12px;">Tu turno de hoy:</p>
            <div class="turno-card selected">
                <div class="turno-hora">{{ $turnos[0]['hora'] }}</div>
                <div class="turno-practica">{{ $turnos[0]['practica'] }}</div>
                <div class="turno-profesional">{{ $turnos[0]['profesional'] }}</div>
            </div>
        @else
            <p class="step-sub" style="margin-bottom:12px;">Tenés {{ count($turnos) }} turnos hoy. ¿Para cuál venís?</p>
            @foreach ($turnos as $i => $t)
                <div class="turno-card {{ $turnoSeleccionado && $turnoSeleccionado['id'] === $t['id'] ? 'selected' : '' }}"
                     wire:click="seleccionarTurno({{ $i }})">
                    <div class="turno-hora">{{ $t['hora'] }}</div>
                    <div class="turno-practica">{{ $t['practica'] }}</div>
                    <div class="turno-profesional">{{ $t['profesional'] }}</div>
                </div>
            @endforeach
        @endif
    </div>
    <div class="col-right">
        <button class="btn btn-primary"
            wire:click="confirmarLlegada"
            {{ count($turnos) > 1 && !$turnoSeleccionado ? 'disabled' : '' }}>
            Confirmar llegada →
        </button>
        <button class="btn btn-secondary" wire:click="reset2">Volver</button>
    </div>
</div>
@endif

{{-- ── PASO: sin_turno ───────────────────────────────────── --}}
{{-- Selección de motivo en Alpine: feedback visual instantáneo, 1 solo POST al confirmar. --}}
@if ($paso === 'sin_turno')
<div class="two-col" x-data="{ motivo: '', confirmando: false }" wire:key="paso-sin-turno">
    <div class="col-left">
        <p class="paciente-nombre">
            {{ $paciente['apellido'] ? $paciente['apellido'].', '.$paciente['nombre'] : $paciente['nombre'] }}
        </p>
        <p class="step-sub">No encontramos turnos para hoy.<br>¿Por qué venís?</p>
    </div>
    <div class="col-right">
        <button type="button" class="motivo-btn" :class="{ selected: motivo === 'turnos' }"
                @click="motivo = 'turnos'">📅 Turnos</button>
        <button type="button" class="motivo-btn" :class="{ selected: motivo === 'recetas' }"
                @click="motivo = 'recetas'">📋 Recetas</button>
        <button type="button" class="motivo-btn" :class="{ selected: motivo === 'muestras' }"
                @click="motivo = 'muestras'">🧪 Muestras</button>
        <button type="button" class="btn btn-primary"
            @click="confirmando = true; $wire.confirmarSinTurno(motivo)"
            :disabled="!motivo || confirmando"
            x-text="confirmando ? 'Confirmando…' : 'Confirmar →'">
        </button>
        <button type="button" class="btn btn-secondary" wire:click="reset2">Volver</button>
    </div>
</div>
@endif

{{-- ── PASO: confirmado ──────────────────────────────────── --}}
@if ($paso === 'confirmado')
<div class="full-step">
    <div class="check-icon">✓</div>
    <p class="step-title" style="font-size:26px;">¡Listo! Ya te anotamos.</p>
    <p class="step-sub">Tomá asiento en la sala de espera.<br>Te llamamos enseguida.</p>
    @if ($planta)
        <div class="planta-badge">Sala planta {{ $planta }}</div>
    @endif
    <p class="countdown" id="countdown">Volviendo al inicio en 15s...</p>
</div>
@endif

{{-- ── PASO: acercarse ───────────────────────────────────── --}}
@if ($paso === 'acercarse')
<div class="full-step">
    <div style="font-size:56px;margin-bottom:16px;">👋</div>
    <p class="step-title">Acercate al mostrador</p>
    <p class="step-sub">No encontramos tu DNI en el sistema.<br>Una de nuestras secretarias te va a ayudar.</p>
    <button class="btn btn-secondary" style="max-width:260px;margin-top:24px;" wire:click="reset2">Volver al inicio</button>
</div>
@endif

</div>
