@php
    $esUrgente = $item['urgente'];
    $esBOT     = $item['tipo'] === 'bot';
    $esWA      = $item['tipo'] === 'wa';
    $esAbierta = isset($convAbiertaId) && $convAbiertaId === $item['id'] && $esWA;
@endphp

<div style="
    background:var(--card);
    border:1px solid {{ $esUrgente ? 'var(--accent)' : 'var(--border)' }};
    border-radius:8px;
    margin-bottom:8px;
    overflow:hidden;
    {{ $esAbierta ? 'outline:2px solid var(--accent);' : '' }}
">
    {{-- Header de card --}}
    <div style="
        padding:8px 12px;
        border-bottom:1px solid {{ $esUrgente ? 'rgba(192,39,58,0.3)' : 'var(--border)' }};
        background:{{ $esUrgente ? 'rgba(192,39,58,0.1)' : 'var(--surface)' }};
        display:flex;align-items:center;gap:8px;
    ">
        {{-- Badge tipo --}}
        <span style="
            font-size:10px;font-weight:700;letter-spacing:.5px;
            padding:2px 7px;border-radius:10px;
            background:{{ $esBOT ? 'rgba(88,166,255,0.12)' : 'rgba(63,185,80,0.12)' }};
            color:{{ $esBOT ? 'var(--info)' : 'var(--success)' }};
            border:1px solid {{ $esBOT ? 'rgba(88,166,255,0.25)' : 'rgba(63,185,80,0.25)' }};
        ">{{ $esBOT ? 'BOT' : 'WA' }}</span>

        {{-- Urgente badge --}}
        @if($esUrgente)
        <span style="font-size:10px;font-weight:700;color:var(--accent);letter-spacing:.5px;">⚑ URGENTE</span>
        @endif

        {{-- Etiqueta --}}
        <span style="font-size:11px;color:var(--muted);">{{ $item['etiqueta'] }}</span>

        {{-- Prueba badge --}}
        @if(isset($item['es_prueba']) && $item['es_prueba'])
        <span style="font-size:10px;color:var(--warning);border:1px solid rgba(210,153,34,0.3);padding:1px 6px;border-radius:8px;">PRUEBA</span>
        @endif

        {{-- No leídos --}}
        @if(isset($item['no_leidos']) && $item['no_leidos'] > 0)
        <span style="background:var(--accent);color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700;">{{ $item['no_leidos'] }}</span>
        @endif

        <span style="margin-left:auto;font-size:11px;color:var(--muted);">{{ $item['hace'] }}</span>
    </div>

    {{-- Cuerpo --}}
    <div style="padding:10px 12px;">
        <div style="font-weight:600;font-size:14px;margin-bottom:4px;">{{ $item['contacto'] }}</div>
        <div style="font-size:12px;color:var(--muted);line-height:1.4;">{{ $item['resumen'] }}</div>

        @if(isset($item['asignada_a']) && $item['asignada_a'])
        <div style="font-size:11px;color:var(--info);margin-top:6px;">
            👤 {{ $item['asignada_a'] }}
        </div>
        @endif
    </div>

    {{-- Acciones --}}
    <div style="padding:8px 12px;border-top:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap;">
        @if($columna === 'nuevas')
            <button wire:click="tomar({{ $item['id'] }},'{{ $item['tipo'] }}')"
                style="padding:4px 12px;background:rgba(88,166,255,0.1);border:1px solid rgba(88,166,255,0.25);color:var(--info);border-radius:6px;font-size:12px;cursor:pointer;">
                Tomar
            </button>
            <button wire:click="abrirDelegar({{ $item['id'] }},'{{ $item['tipo'] }}')"
                style="padding:4px 12px;background:transparent;border:1px solid var(--border);color:var(--muted);border-radius:6px;font-size:12px;cursor:pointer;">
                Delegar
            </button>
        @else
            @if($esWA)
            <button wire:click="abrirConv({{ $item['id'] }})"
                style="padding:4px 12px;background:rgba(63,185,80,0.1);border:1px solid rgba(63,185,80,0.25);color:var(--success);border-radius:6px;font-size:12px;cursor:pointer;">
                {{ $esAbierta ? '← Cerrar' : '💬 Ver chat' }}
            </button>
            @endif
            <button wire:click="abrirDelegar({{ $item['id'] }},'{{ $item['tipo'] }}')"
                style="padding:4px 12px;background:transparent;border:1px solid var(--border);color:var(--muted);border-radius:6px;font-size:12px;cursor:pointer;">
                Delegar
            </button>
            <button wire:click="resolver({{ $item['id'] }},'{{ $item['tipo'] }}')"
                style="padding:4px 12px;background:rgba(63,185,80,0.08);border:1px solid rgba(63,185,80,0.2);color:var(--success);border-radius:6px;font-size:12px;cursor:pointer;">
                ✓ Resolver
            </button>
        @endif

        {{-- Toggle urgente (disponible siempre) --}}
        <button wire:click="toggleUrgente({{ $item['id'] }},'{{ $item['tipo'] }}')"
            style="padding:4px 10px;background:{{ $esUrgente ? 'rgba(192,39,58,0.15)' : 'transparent' }};border:1px solid {{ $esUrgente ? 'rgba(192,39,58,0.4)' : 'var(--border)' }};color:{{ $esUrgente ? 'var(--accent)' : 'var(--muted)' }};border-radius:6px;font-size:12px;cursor:pointer;"
            title="{{ $esUrgente ? 'Quitar urgencia' : 'Marcar urgente' }}">
            ⚑
        </button>
    </div>
</div>
