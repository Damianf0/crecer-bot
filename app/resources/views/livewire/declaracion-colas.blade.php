<div>
@php $tienePendientes = $conversaciones->isNotEmpty() || $tareas->isNotEmpty(); @endphp

@if($tienePendientes)
    <style>
        body { align-items: flex-start; padding-top: 40px; }
        .wrap { max-width: 920px !important; }
        .colas-layout {
            display: grid;
            grid-template-columns: 440px 440px;
            gap: 20px;
            align-items: start;
            justify-content: center;
        }
        @media (max-width: 920px) {
            .colas-layout { grid-template-columns: 1fr; }
        }
    </style>
@endif

<style>
    .pend-col { display: flex; flex-direction: column; gap: 16px; }
    .pend-section {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 18px 18px 14px;
    }
    .pend-title {
        font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;
        letter-spacing: .5px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
    }
    .pend-count {
        background: var(--surface); color: var(--text); border-radius: 10px; padding: 1px 8px;
        font-size: 10px; font-weight: 700; letter-spacing: 0;
    }
    .pend-card {
        display: block; text-decoration: none; color: inherit;
        background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
        padding: 10px 12px; margin-bottom: 6px;
        transition: border-color .15s, background .15s;
    }
    .pend-card:hover {
        border-color: var(--accent);
        background: color-mix(in srgb, var(--accent) 5%, var(--bg));
    }
    .pend-card:last-child { margin-bottom: 0; }
    .pend-head { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
    .pend-name {
        font-size: 13px; font-weight: 600; color: var(--text); flex: 1; min-width: 0;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .pend-meta { font-size: 11px; color: var(--muted); white-space: nowrap; }
    .pend-preview {
        font-size: 12px; color: var(--muted); line-height: 1.4;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .pend-badge {
        display: inline-block; font-size: 10px; font-weight: 700;
        padding: 1px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: .3px;
    }
    .badge-urgente, .badge-vencida {
        background: color-mix(in srgb, var(--error) 12%, transparent);
        color: var(--error);
        border: 1px solid color-mix(in srgb, var(--error) 35%, transparent);
    }
    .badge-alta {
        background: color-mix(in srgb, var(--warning) 12%, transparent);
        color: var(--warning);
        border: 1px solid color-mix(in srgb, var(--warning) 35%, transparent);
    }
    .badge-unread {
        background: var(--accent); color: #fff; border-radius: 10px;
        padding: 1px 7px; font-size: 10px; font-weight: 700;
    }
</style>

@if($tienePendientes)
    <div class="colas-layout">
        <div class="card">
            @include('livewire.partials.colas-selector')
        </div>

        <div class="pend-col">
            @if($conversaciones->isNotEmpty())
                <div class="pend-section">
                    <div class="pend-title">
                        💬 Conversaciones a tu cargo
                        <span class="pend-count">{{ $conversaciones->count() }}</span>
                    </div>
                    @foreach($conversaciones as $c)
                        <a class="pend-card" href="/atencion?conv_id={{ $c['id'] }}">
                            <div class="pend-head">
                                <span class="pend-name">{{ $c['nombre'] }}</span>
                                @if($c['urgente'])
                                    <span class="pend-badge badge-urgente">Urgente</span>
                                @endif
                                @if($c['no_leidos'] > 0)
                                    <span class="badge-unread">{{ $c['no_leidos'] }}</span>
                                @endif
                                <span class="pend-meta">{{ $c['hace'] }}</span>
                            </div>
                            <div class="pend-preview">{{ $c['preview'] }}</div>
                        </a>
                    @endforeach
                </div>
            @endif

            @if($tareas->isNotEmpty())
                <div class="pend-section">
                    <div class="pend-title">
                        📋 Tareas pendientes
                        <span class="pend-count">{{ $tareas->count() }}</span>
                    </div>
                    @foreach($tareas as $t)
                        <a class="pend-card" href="/mis-tareas?tarea_id={{ $t['id'] }}">
                            <div class="pend-head">
                                <span class="pend-name">{{ $t['titulo'] }}</span>
                                @if($t['vencida'])
                                    <span class="pend-badge badge-vencida">Vencida</span>
                                @elseif($t['prioridad'] === 'alta')
                                    <span class="pend-badge badge-alta">Alta</span>
                                @endif
                                @if($t['vence_at'])
                                    <span class="pend-meta">vence {{ $t['vence_at'] }}</span>
                                @endif
                            </div>
                            @if($t['descripcion'])
                                <div class="pend-preview">{{ $t['descripcion'] }}</div>
                            @elseif($t['creada_por'])
                                <div class="pend-preview">creada por {{ $t['creada_por'] }}</div>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@else
    <div class="card">
        @include('livewire.partials.colas-selector')
    </div>
@endif
</div>
