<div style="margin:-24px;height:calc(100vh - 52px);display:flex;flex-direction:column;overflow:hidden;">

    {{-- ── Header ────────────────────────────────────────────── --}}
    <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:16px;flex-shrink:0;background:var(--surface);">
        <span style="font-weight:700;font-size:15px;">Gestión de Atención</span>

        {{-- Filtros --}}
        <div style="display:flex;gap:4px;margin-left:8px;">
            @foreach(['todos'=>'Todos','bot'=>'Bot','wa'=>'WhatsApp'] as $key=>$label)
            <button wire:click="$set('filtro','{{ $key }}')"
                style="padding:4px 14px;border-radius:20px;border:1px solid {{ $filtro===$key ? 'var(--accent)' : 'var(--border)' }};
                       background:{{ $filtro===$key ? 'rgba(192,39,58,0.15)' : 'transparent' }};
                       color:{{ $filtro===$key ? 'var(--accent)' : 'var(--muted)' }};font-size:12px;cursor:pointer;">
                {{ $label }}
            </button>
            @endforeach
        </div>

        <div style="margin-left:auto;font-size:12px;color:var(--muted);">
            <span style="color:var(--text);">{{ $nuevas->count() }}</span> nuevas ·
            <span style="color:var(--text);">{{ $enProceso->count() }}</span> en proceso
        </div>
    </div>

    {{-- ── Cuerpo ─────────────────────────────────────────────── --}}
    <div style="flex:1;display:flex;overflow:hidden;">

        {{-- ── Columna NUEVAS ───────────────────────────────── --}}
        <div style="width:{{ $convAbiertaId ? '300px' : '50%' }};border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;transition:width 0.2s;">
            <div style="padding:10px 16px;border-bottom:1px solid var(--border);font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;background:var(--surface);">
                Nuevas · {{ $nuevas->count() }}
            </div>
            <div style="flex:1;overflow-y:auto;padding:10px;">
                @forelse($nuevas as $item)
                    @include('livewire.partials.gestion-card', ['item' => $item, 'columna' => 'nuevas'])
                @empty
                    <div style="color:var(--muted);font-size:13px;text-align:center;padding:40px 0;">Sin elementos nuevos</div>
                @endforelse
            </div>
        </div>

        {{-- ── Columna EN PROCESO o CHAT ────────────────────── --}}
        @if($convAbiertaId && $convAbierta)
            {{-- Chat WA --}}
            <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
                {{-- Header chat --}}
                <div style="padding:10px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;background:var(--surface);flex-shrink:0;">
                    <button wire:click="cerrarConv" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;line-height:1;">←</button>
                    <div>
                        <div style="font-weight:600;font-size:14px;">{{ $convAbierta->nombreOTelefono }}</div>
                        <div style="font-size:11px;color:var(--muted);">{{ $convAbierta->telefono }}</div>
                    </div>
                    <span style="margin-left:auto;font-size:11px;color:var(--muted);">
                        {{ \App\Models\ConversacionWA::AREAS[$convAbierta->area] ?? $convAbierta->area }}@if($convAbierta->asignada_a) · {{ $convAbierta->asignadaA?->nombre_completo ?? '' }}@endif
                    </span>
                    <button wire:click="abrirDerivarArea({{ $convAbiertaId }})" title="Derivar a otra área (otro número)"
                        style="padding:4px 12px;background:rgba(124,154,255,0.12);border:1px solid rgba(124,154,255,0.3);color:var(--info);border-radius:6px;font-size:12px;cursor:pointer;">
                        ↪ Área
                    </button>
                    <button wire:click="resolver({{ $convAbiertaId }},'wa')"
                        style="padding:4px 12px;background:rgba(63,185,80,0.1);border:1px solid rgba(63,185,80,0.3);color:var(--success);border-radius:6px;font-size:12px;cursor:pointer;">
                        ✓ Resolver
                    </button>
                </div>

                {{-- Mensajes --}}
                <div id="chat-messages" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:8px;">
                    @php $fechaAnterior = null; @endphp
                    @foreach($mensajesAbierta as $msg)
                        @php $fecha = $msg->created_at->format('d/m/Y'); @endphp
                        @if($fecha !== $fechaAnterior)
                            <div style="text-align:center;font-size:11px;color:var(--muted);padding:8px 0;">{{ $fecha }}</div>
                            @php $fechaAnterior = $fecha; @endphp
                        @endif

                        @if($msg->direccion === 'nota_interna')
                            <div style="align-self:center;background:rgba(210,153,34,0.12);border:1px solid rgba(210,153,34,0.25);color:#d29922;border-radius:8px;padding:6px 12px;font-size:12px;max-width:70%;text-align:center;">
                                📝 {{ $msg->contenido }}
                            </div>
                        @elseif($msg->direccion === 'entrante')
                            <div style="align-self:flex-start;max-width:72%;">
                                <div style="background:var(--card);border:1px solid var(--border);border-radius:0 12px 12px 12px;padding:8px 12px;font-size:13px;">
                                    @if($msg->tipo === 'audio')
                                        <audio controls src="{{ $msg->archivo_url }}" style="height:32px;width:200px;display:block;"></audio>
                                        @if($msg->contenido)
                                            <div style="font-size:11px;color:var(--muted);margin-top:5px;font-style:italic;">🎙 {{ $msg->contenido }}</div>
                                        @endif
                                    @elseif($msg->tipo === 'imagen' && $msg->archivo_url)
                                        <img src="{{ $msg->archivo_url }}" style="max-width:240px;max-height:200px;border-radius:6px;display:block;cursor:pointer;" onclick="window.open('{{ $msg->archivo_url }}')">
                                        @if($msg->contenido)
                                            <div style="font-size:12px;margin-top:5px;">{{ $msg->contenido }}</div>
                                        @endif
                                    @elseif($msg->tipo === 'video' && $msg->archivo_url)
                                        <video controls src="{{ $msg->archivo_url }}" style="max-width:240px;max-height:180px;border-radius:6px;display:block;"></video>
                                        @if($msg->contenido)
                                            <div style="font-size:12px;margin-top:5px;">{{ $msg->contenido }}</div>
                                        @endif
                                    @elseif($msg->tipo === 'documento' && $msg->archivo_url)
                                        <a href="{{ $msg->archivo_url }}" target="_blank"
                                           style="display:flex;align-items:center;gap:8px;color:var(--accent2);text-decoration:none;">
                                            <span style="font-size:22px;">📄</span>
                                            <span style="word-break:break-all;font-size:13px;">{{ $msg->contenido }}</span>
                                        </a>
                                    @else
                                        {{ $msg->contenido }}
                                    @endif
                                </div>
                                <div style="font-size:10px;color:var(--muted);margin-top:2px;padding-left:4px;">{{ $msg->created_at->format('H:i') }}</div>
                            </div>
                        @else
                            <div style="align-self:flex-end;max-width:72%;">
                                <div style="background:rgba(192,39,58,0.18);border:1px solid rgba(192,39,58,0.3);color:var(--text);border-radius:12px 0 12px 12px;padding:8px 12px;font-size:13px;">
                                    @if($msg->tipo === 'imagen' && $msg->archivo_url)
                                        <img src="{{ $msg->archivo_url }}" style="max-width:240px;max-height:200px;border-radius:6px;display:block;cursor:pointer;" onclick="window.open('{{ $msg->archivo_url }}')">
                                        @if($msg->contenido)
                                            <div style="font-size:12px;margin-top:5px;">{{ $msg->contenido }}</div>
                                        @endif
                                    @elseif($msg->tipo === 'video' && $msg->archivo_url)
                                        <video controls src="{{ $msg->archivo_url }}" style="max-width:240px;max-height:180px;border-radius:6px;display:block;"></video>
                                        @if($msg->contenido)
                                            <div style="font-size:12px;margin-top:5px;">{{ $msg->contenido }}</div>
                                        @endif
                                    @elseif($msg->tipo === 'documento' && $msg->archivo_url)
                                        <a href="{{ $msg->archivo_url }}" target="_blank"
                                           style="display:flex;align-items:center;gap:8px;color:#7dd3fc;text-decoration:none;">
                                            <span style="font-size:22px;">📄</span>
                                            <span style="word-break:break-all;font-size:13px;">{{ $msg->contenido }}</span>
                                        </a>
                                    @else
                                        {{ $msg->contenido }}
                                    @endif
                                </div>
                                <div style="font-size:10px;color:var(--muted);margin-top:2px;text-align:right;padding-right:4px;">{{ $msg->created_at->format('H:i') }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Input --}}
                <div style="border-top:1px solid var(--border);padding:12px 16px;flex-shrink:0;background:var(--surface);">

                    {{-- Preview de archivo seleccionado (server-side, sin Alpine) --}}
                    @if($archivoChat)
                    <div style="display:flex;align-items:center;gap:10px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:8px 12px;margin-bottom:8px;">
                        <span style="font-size:20px;">📄</span>
                        <span style="font-size:12px;color:var(--text);flex:1;word-break:break-all;">{{ $archivoChat->getClientOriginalName() }}</span>
                        <button wire:click="$set('archivoChat', null)"
                            style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;line-height:1;">✕</button>
                    </div>
                    @endif

                    <div style="display:flex;gap:4px;margin-bottom:8px;">
                        @foreach(['mensaje'=>'Mensaje','nota'=>'Nota interna'] as $m=>$l)
                        <button wire:click="$set('modoChat','{{ $m }}')"
                            style="font-size:11px;padding:3px 10px;border-radius:12px;border:1px solid {{ $modoChat===$m ? 'var(--accent)' : 'var(--border)' }};background:{{ $modoChat===$m ? 'rgba(192,39,58,0.15)' : 'transparent' }};color:{{ $modoChat===$m ? 'var(--accent)' : 'var(--muted)' }};cursor:pointer;">
                            {{ $l }}
                        </button>
                        @endforeach
                    </div>

                    <div style="display:flex;gap:8px;align-items:flex-end;">

                        {{-- Clip: label sobre input hidden, sin JS --}}
                        @if($modoChat === 'mensaje')
                        <label style="cursor:pointer;flex-shrink:0;">
                            <span style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;background:var(--card);border:1px solid var(--border);border-radius:8px;font-size:18px;" title="Adjuntar archivo">📎</span>
                            <input type="file"
                                wire:model="archivoChat"
                                accept="image/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt"
                                style="display:none;">
                        </label>
                        @endif

                        <textarea wire:model="textoChat"
                            placeholder="{{ $archivoChat ? 'Leyenda opcional...' : ($modoChat === 'nota' ? 'Nota interna (no se envía al paciente)' : 'Escribir mensaje...') }}"
                            style="flex:1;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text);font-size:13px;resize:none;min-height:60px;font-family:inherit;"
                            wire:keydown.ctrl.enter="{{ $archivoChat ? 'enviarArchivo' : 'enviarMensaje' }}"></textarea>

                        @if($archivoChat)
                        <button wire:click="enviarArchivo"
                            style="padding:8px 16px;background:var(--accent);border:none;color:#fff;border-radius:8px;font-size:13px;cursor:pointer;height:40px;">
                            Enviar
                        </button>
                        @else
                        <button wire:click="{{ $modoChat === 'nota' ? 'enviarMensaje' : 'enviarMensaje' }}"
                            style="padding:8px 16px;background:var(--accent);border:none;color:#fff;border-radius:8px;font-size:13px;cursor:pointer;height:40px;">
                            {{ $modoChat === 'nota' ? 'Guardar' : 'Enviar' }}
                        </button>
                        @endif
                    </div>
                    <div style="font-size:10px;color:var(--muted);margin-top:4px;">Ctrl+Enter para enviar</div>
                </div>
            </div>

        @else
            {{-- Columna EN PROCESO --}}
            <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
                <div style="padding:10px 16px;border-bottom:1px solid var(--border);font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;background:var(--surface);">
                    En proceso · {{ $enProceso->count() }}
                </div>
                <div style="flex:1;overflow-y:auto;padding:10px;">
                    @forelse($enProceso as $item)
                        @include('livewire.partials.gestion-card', ['item' => $item, 'columna' => 'proceso'])
                    @empty
                        <div style="color:var(--muted);font-size:13px;text-align:center;padding:40px 0;">Nada en proceso</div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    {{-- ── Modal Delegar ──────────────────────────────────────── --}}
    @if($mostrarDelegar)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;display:flex;align-items:center;justify-content:center;">
        <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;width:340px;">
            <div style="font-weight:600;font-size:15px;margin-bottom:16px;">Delegar atención</div>
            <select wire:model="delegarUsuario"
                style="width:100%;background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;margin-bottom:16px;">
                <option value="">Seleccioná una secretaria…</option>
                @foreach($usuarios as $u)
                    @if($u->id !== auth()->id())
                    <option value="{{ $u->id }}">{{ $u->nombre_completo }}</option>
                    @endif
                @endforeach
            </select>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button wire:click="cancelarDelegar" style="padding:6px 16px;border:1px solid var(--border);background:none;color:var(--muted);border-radius:6px;cursor:pointer;font-size:13px;">Cancelar</button>
                <button wire:click="confirmarDelegar" style="padding:6px 16px;background:var(--accent);border:none;color:#fff;border-radius:6px;cursor:pointer;font-size:13px;">Delegar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Modal Derivar a otra área ──────────────────────── --}}
    @if($mostrarDerivarArea)
    @php $convDer = $derivarAreaConvId ? \App\Models\ConversacionWA::find($derivarAreaConvId) : null; @endphp
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;display:flex;align-items:center;justify-content:center;">
        <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;width:400px;">
            <div style="font-weight:600;font-size:15px;margin-bottom:6px;">Derivar a otra área</div>
            <div style="font-size:12px;color:var(--muted);margin-bottom:16px;line-height:1.5;">
                Se le avisa al paciente por el número actual
                (<strong>{{ \App\Models\ConversacionWA::AREAS[$convDer?->area] ?? $convDer?->area }}</strong>)
                que va a tener respuesta desde el número del área elegida, y la conversación pasa a la cola de esa área.
            </div>
            <select wire:model="derivarAreaDestino"
                style="width:100%;background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;margin-bottom:16px;">
                <option value="">Elegí el área…</option>
                @foreach(\App\Models\ConversacionWA::AREAS as $key => $label)
                    @if($key !== $convDer?->area)
                    <option value="{{ $key }}">{{ $label }}</option>
                    @endif
                @endforeach
            </select>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button wire:click="cancelarDerivarArea" style="padding:6px 16px;border:1px solid var(--border);background:none;color:var(--muted);border-radius:6px;cursor:pointer;font-size:13px;">Cancelar</button>
                <button wire:click="confirmarDerivarArea" wire:loading.attr="disabled" wire:target="confirmarDerivarArea"
                    style="padding:6px 16px;background:var(--info);border:none;color:#fff;border-radius:6px;cursor:pointer;font-size:13px;">
                    <span wire:loading.remove wire:target="confirmarDerivarArea">Derivar</span>
                    <span wire:loading wire:target="confirmarDerivarArea">Derivando…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Toast --}}
    @if($toast)
    <div class="toast-fixed {{ $toastTipo === 'ok' ? 'ok' : 'error' }}">{{ $toast }}</div>
    @endif

    @script
    <script>
        const scrollBottom = () => {
            const el = document.getElementById('chat-messages');
            if (el) el.scrollTop = el.scrollHeight;
        };
        document.addEventListener('livewire:updated', scrollBottom);
        Livewire.on('scroll-bottom', scrollBottom);
    </script>
    @endscript
</div>
