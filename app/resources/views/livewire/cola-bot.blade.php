<div wire:poll.8s>

{{-- Toast --}}
@if ($toastMsg)
    <div class="toast-fixed {{ $toastType }}">{{ $toastMsg }}</div>
@endif

<div style="display:flex;gap:20px;align-items:flex-start;">

    {{-- ── Cola ─────────────────────────────────────── --}}
    <div style="flex:1;min-width:0;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="font-size:22px;font-weight:600;">Mensajes WhatsApp</h1>
            <div style="display:flex;align-items:center;gap:12px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);cursor:pointer;user-select:none;">
                    <input type="checkbox" wire:model.live="mostrarPrueba"
                           style="accent-color:var(--accent);width:14px;height:14px;cursor:pointer;">
                    Mostrar pruebas
                </label>
                <span style="font-size:14px;color:var(--muted);">
                    {{ $cola->count() }} {{ $cola->count() === 1 ? 'mensaje' : 'mensajes' }} · actualiza cada 8s
                </span>
            </div>
        </div>

        @forelse ($cola as $d)
            <div wire:key="{{ $d->id }}"
                 style="background:var(--card);border:1px solid {{ $d->estado === 'en_atencion' ? 'rgba(88,166,255,0.35)' : ($d->es_prueba ? 'rgba(139,148,158,0.2)' : 'var(--border)') }};border-radius:8px;padding:16px;margin-bottom:10px;cursor:pointer;transition:border-color 0.15s;{{ $d->es_prueba ? 'opacity:0.75;' : '' }}"
                 wire:click="abrirFicha({{ $d->id }})">

                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                            <span style="font-weight:600;font-size:18px;">{{ $d->telefono }}</span>

                            @php
                                $bgColor = match(true) {
                                    str_starts_with($d->codigo, 'TURNO_')    => 'rgba(88,166,255,0.12)',
                                    $d->codigo === 'RESULTADO_BETA'          => 'rgba(210,153,34,0.15)',
                                    $d->codigo === 'CONSULTA_CLINICA'        => 'rgba(63,185,80,0.12)',
                                    $d->codigo === 'DERIVAR_SECRETARIA'      => 'rgba(192,39,58,0.15)',
                                    default                                  => 'rgba(139,148,158,0.15)',
                                };
                                $txtColor = match(true) {
                                    str_starts_with($d->codigo, 'TURNO_')    => 'var(--info)',
                                    $d->codigo === 'RESULTADO_BETA'          => 'var(--warning)',
                                    $d->codigo === 'CONSULTA_CLINICA'        => 'var(--success)',
                                    $d->codigo === 'DERIVAR_SECRETARIA'      => 'var(--accent)',
                                    default                                  => 'var(--muted)',
                                };
                            @endphp

                            <span style="background:{{ $bgColor }};color:{{ $txtColor }};border-radius:10px;padding:3px 10px;font-size:13px;font-weight:600;">
                                {{ $d->etiqueta }}
                            </span>

                            @if ($d->es_prueba)
                                <span style="background:rgba(139,148,158,0.1);color:var(--muted);border:1px solid rgba(139,148,158,0.2);border-radius:10px;padding:2px 8px;font-size:12px;">
                                    🧪 prueba
                                </span>
                            @endif
                        </div>
                        <div style="font-size:14px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:480px;">
                            {{ Str::limit($d->texto, 120) }}
                        </div>
                    </div>

                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:13px;color:var(--muted);">{{ $d->bot_at?->diffForHumans() }}</div>
                        @if ($d->estado === 'en_atencion')
                            <div style="margin-top:4px;">
                                <span style="background:rgba(88,166,255,0.1);color:var(--info);border:1px solid rgba(88,166,255,0.2);padding:2px 8px;border-radius:10px;font-size:12px;font-weight:500;">
                                    En atención
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div style="text-align:center;padding:60px;color:var(--muted);">
                <div style="font-size:40px;margin-bottom:12px;">✓</div>
                Sin mensajes pendientes
            </div>
        @endforelse
    </div>

    {{-- ── Ficha ────────────────────────────────────── --}}
    @if ($ficha)
    <div style="width:420px;flex-shrink:0;">
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
                <div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="font-size:20px;font-weight:600;">{{ $ficha->telefono }}</div>
                        @if ($ficha->es_prueba)
                            <span style="background:rgba(139,148,158,0.1);color:var(--muted);border:1px solid rgba(139,148,158,0.2);border-radius:10px;padding:2px 8px;font-size:12px;">
                                🧪 prueba
                            </span>
                        @endif
                    </div>
                    <div style="font-size:14px;color:var(--muted);margin-top:2px;">
                        {{ $ficha->bot_at?->format('d/m/Y H:i') }} · {{ $ficha->bot_at?->diffForHumans() }}
                    </div>
                </div>
                <button wire:click="cerrarFicha"
                        style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;line-height:1;">✕</button>
            </div>

            {{-- Clasificación --}}
            <div style="margin-bottom:16px;">
                <div style="font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:6px;">Clasificación</div>
                @php
                    $bgFicha = match(true) {
                        str_starts_with($ficha->codigo, 'TURNO_')    => 'rgba(88,166,255,0.12)',
                        $ficha->codigo === 'RESULTADO_BETA'          => 'rgba(210,153,34,0.15)',
                        $ficha->codigo === 'CONSULTA_CLINICA'        => 'rgba(63,185,80,0.12)',
                        $ficha->codigo === 'DERIVAR_SECRETARIA'      => 'rgba(192,39,58,0.15)',
                        default                                      => 'rgba(139,148,158,0.15)',
                    };
                    $txtFicha = match(true) {
                        str_starts_with($ficha->codigo, 'TURNO_')    => 'var(--info)',
                        $ficha->codigo === 'RESULTADO_BETA'          => 'var(--warning)',
                        $ficha->codigo === 'CONSULTA_CLINICA'        => 'var(--success)',
                        $ficha->codigo === 'DERIVAR_SECRETARIA'      => 'var(--accent)',
                        default                                      => 'var(--muted)',
                    };
                @endphp
                <span style="background:{{ $bgFicha }};color:{{ $txtFicha }};border-radius:10px;padding:4px 14px;font-size:14px;font-weight:600;">
                    {{ $ficha->etiqueta }}
                </span>
                @if (!$ficha->en_horario)
                    <span style="margin-left:8px;background:rgba(248,81,73,0.1);color:var(--error);border-radius:10px;padding:4px 10px;font-size:13px;">
                        Fuera de horario
                    </span>
                @endif
            </div>

            {{-- Mensaje --}}
            <div style="margin-bottom:16px;">
                <div style="font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:8px;">Mensaje del paciente</div>
                <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;font-size:15px;line-height:1.6;max-height:200px;overflow-y:auto;color:var(--text);">
                    {{ $ficha->texto }}
                </div>
            </div>

            {{-- Nota --}}
            <div style="margin-bottom:16px;">
                <div style="font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:8px;">Nota interna</div>
                <textarea wire:model="nota" placeholder="Agregar nota..."
                    style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:10px;font-size:15px;font-family:inherit;resize:none;height:80px;"></textarea>
                <button wire:click="guardarNota({{ $ficha->id }})"
                        style="margin-top:6px;background:var(--surface);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:6px 14px;font-size:14px;cursor:pointer;">
                    Guardar nota
                </button>
            </div>

            {{-- Acción --}}
            <button wire:click="resolver({{ $ficha->id }})"
                    style="background:var(--accent);border:none;border-radius:6px;color:#fff;padding:13px;font-size:16px;font-weight:600;cursor:pointer;width:100%;">
                Marcar como resuelto ✓
            </button>
        </div>
    </div>
    @endif

</div>
</div>
