<div wire:poll.6s="revisarAlertas">

{{-- Toast --}}
@if ($toastMsg)
    <div class="toast-fixed {{ $toastType }}">{{ $toastMsg }}</div>
@endif

<div style="display:flex;gap:20px;align-items:flex-start;">

    {{-- ── Cola ─────────────────────────────────────── --}}
    <div style="flex:1;min-width:0;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="font-size:22px;font-weight:600;">Cola de recepción</h1>
            <span style="font-size:14px;color:var(--muted);">
                {{ $cola->count() }} {{ $cola->count() === 1 ? 'paciente' : 'pacientes' }} · actualiza cada 6s
            </span>
        </div>

        <div id="cola-list">
        @forelse ($cola as $p)
            <div wire:key="{{ $p->id }}"
                 data-id="{{ $p->id }}"
                 style="background:var(--card);border:1px solid {{ $p->alerta_espera ? 'rgba(248,81,73,0.4)' : 'var(--border)' }};border-radius:8px;padding:16px;margin-bottom:10px;transition:border-color 0.15s;display:flex;align-items:stretch;gap:0;">

                {{-- Drag handle --}}
                <div class="drag-handle"
                     style="display:flex;align-items:center;padding:0 12px 0 0;color:var(--muted);font-size:20px;cursor:grab;flex-shrink:0;user-select:none;"
                     title="Arrastrar para reordenar">⠿</div>

                {{-- Card body --}}
                <div style="flex:1;min-width:0;cursor:pointer;" wire:click="abrirFicha({{ $p->id }})">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                <span style="font-weight:600;font-size:18px;">{{ $p->nombre_completo }}</span>
                                @foreach ($p->getFlags() as $flag)
                                    <span title="{{ $flag['label'] }}" style="font-size:16px;">{{ $flag['icon'] }}</span>
                                @endforeach
                            </div>
                            <div style="font-size:15px;color:var(--muted);">
                                {{ $p->practica ?? match($p->motivo) {
                                    'turnos'   => 'Turnos',
                                    'recetas'  => 'Recetas',
                                    'muestras' => 'Muestras',
                                    default    => 'Sin turno',
                                } }}
                                @if ($p->profesional) · {{ $p->profesional }} @endif
                                @if ($p->turno_hora) · {{ \Carbon\Carbon::parse($p->turno_hora)->format('H:i') }} @endif
                            </div>
                        </div>

                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-size:16px;font-weight:600;color:{{ $p->alerta_espera ? 'var(--error)' : 'var(--muted)' }};">
                                {{ $p->minutos_espera }} min
                            </div>
                            <div style="font-size:13px;color:var(--muted);">
                                {{ $p->hora_llegada->format('H:i') }}
                            </div>
                        </div>
                    </div>

                    @if ($p->estado === 'en_atencion')
                        <div style="margin-top:8px;">
                            <span style="background:rgba(88,166,255,0.1);color:var(--info);border:1px solid rgba(88,166,255,0.2);padding:3px 10px;border-radius:10px;font-size:13px;font-weight:500;">
                                En atención
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div style="text-align:center;padding:60px;color:var(--muted);">
                <div style="font-size:40px;margin-bottom:12px;">✓</div>
                Cola vacía
            </div>
        @endforelse
        </div>
    </div>

    {{-- ── Ficha ────────────────────────────────────── --}}
    @if ($ficha)
    <div style="width:420px;flex-shrink:0;">
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
                <div>
                    <div style="font-size:20px;font-weight:600;">{{ $ficha->nombre_completo }}</div>
                    <div style="font-size:14px;color:var(--muted);margin-top:2px;">DNI {{ $ficha->dni }}</div>
                </div>
                <button wire:click="cerrarFicha"
                        style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;line-height:1;">✕</button>
            </div>

            {{-- Datos --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;font-size:14px;">
                <div>
                    <div style="color:var(--muted);">Obra social</div>
                    <div style="font-weight:500;">{{ $ficha->obra_social ?? '—' }}</div>
                </div>
                <div>
                    <div style="color:var(--muted);">Plan</div>
                    <div style="font-weight:500;">{{ $ficha->plan ?? '—' }}</div>
                </div>
                <div>
                    <div style="color:var(--muted);">Práctica</div>
                    <div style="font-weight:500;">{{ $ficha->practica ?? '—' }}</div>
                </div>
                <div>
                    <div style="color:var(--muted);">Profesional</div>
                    <div style="font-weight:500;">{{ $ficha->profesional ?? '—' }}</div>
                </div>
                <div>
                    <div style="color:var(--muted);">Llegada</div>
                    <div style="font-weight:500;">{{ $ficha->hora_llegada->format('H:i') }}</div>
                </div>
                <div>
                    <div style="color:var(--muted);">Espera</div>
                    <div style="font-weight:500;color:{{ $ficha->alerta_espera ? 'var(--error)' : 'inherit' }};">
                        {{ $ficha->minutos_espera }} min
                    </div>
                </div>
            </div>

            {{-- Flags --}}
            @if (count($ficha->getFlags()))
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;">
                @foreach ($ficha->getFlags() as $flag)
                    <span style="background:var(--surface);border:1px solid var(--border);padding:4px 12px;border-radius:10px;font-size:14px;">
                        {{ $flag['icon'] }} {{ $flag['label'] }}
                    </span>
                @endforeach
            </div>
            @endif

            {{-- Checklist --}}
            <div style="margin-bottom:16px;">
                <div style="font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:10px;">
                    Checklist de recepción
                </div>
                @foreach ($ficha->checklist ?? [] as $item)
                <label style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);cursor:pointer;font-size:15px;">
                    <input type="checkbox"
                           {{ $item['done'] ? 'checked' : '' }}
                           wire:click="toggleChecklist({{ $ficha->id }}, '{{ $item['id'] }}')"
                           style="accent-color:var(--accent);width:18px;height:18px;cursor:pointer;">
                    <span style="{{ $item['done'] ? 'color:var(--muted);text-decoration:line-through;' : '' }}">
                        {{ $item['label'] }}
                        @if ($item['obligatorio'])<span style="color:var(--error);margin-left:2px;">*</span>@endif
                    </span>
                </label>
                @endforeach
            </div>

            {{-- Nota --}}
            <div style="margin-bottom:16px;">
                <div style="font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:8px;">
                    Nota interna
                </div>
                <textarea wire:model="nota" placeholder="Agregar nota..."
                    style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:10px;font-size:15px;font-family:inherit;resize:none;height:80px;"></textarea>
                <button wire:click="guardarNota({{ $ficha->id }})"
                        style="margin-top:6px;background:var(--surface);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:6px 14px;font-size:14px;cursor:pointer;">
                    Guardar nota
                </button>
            </div>

            {{-- Acciones --}}
            <div style="display:flex;flex-direction:column;gap:8px;">
                <button wire:click="liberarASala({{ $ficha->id }})"
                        style="background:var(--accent);border:none;border-radius:6px;color:#fff;padding:13px;font-size:16px;font-weight:600;cursor:pointer;width:100%;">
                    Liberar a sala →
                </button>
                <button wire:click="resolverSinLiberar({{ $ficha->id }})"
                        style="background:var(--card);border:1px solid var(--border);border-radius:6px;color:var(--muted);padding:10px;font-size:15px;cursor:pointer;width:100%;">
                    Resolver sin liberar
                </button>
            </div>

            <div style="font-size:13px;color:var(--muted);margin-top:8px;">
                * Ítems obligatorios — requeridos para liberar
            </div>
        </div>
    </div>
    @endif

</div>

@script
<script>
    let _sortable = null;

    const bootSortable = () => {
        const list = document.getElementById('cola-list');
        if (!list || !window.Sortable) return;
        if (_sortable) { _sortable.destroy(); _sortable = null; }
        _sortable = Sortable.create(list, {
            handle: '.drag-handle',
            animation: 120,
            ghostClass: 'sortable-ghost',
            onEnd() {
                const ids = [...list.querySelectorAll('[data-id]')].map(el => +el.dataset.id);
                $wire.reordenar(ids);
            }
        });
    };

    bootSortable();

    document.addEventListener('livewire:updated', bootSortable);
</script>
@endscript

</div>
