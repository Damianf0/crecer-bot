<div wire:poll.5s style="margin:-24px;height:calc(100vh - 52px);display:flex;overflow:hidden;">

{{-- Modal adjuntos --}}
<div id="iwa-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:9999;align-items:center;justify-content:center;"
     onclick="iwaModalCerrar()">
    <div style="position:relative;max-width:92vw;max-height:92vh;" onclick="event.stopPropagation()">
        <button onclick="iwaModalCerrar()"
                style="position:absolute;top:-34px;right:0;background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;">✕</button>
        <div id="iwa-modal-content"></div>
    </div>
</div>

{{-- Toast --}}
@if ($toastMsg)
    <div class="toast-fixed {{ $toastType }}">{{ $toastMsg }}</div>
@endif

{{-- ── SIDEBAR: lista de conversaciones ───────────────────────── --}}
<div style="width:300px;flex-shrink:0;border-right:1px solid var(--border);display:flex;flex-direction:column;background:var(--surface);">

    {{-- Header sidebar --}}
    <div style="padding:14px 12px;border-bottom:1px solid var(--border);">
        <div style="display:flex;gap:6px;margin-bottom:10px;">
            <button wire:click="$set('filtro','activa')"
                style="flex:1;padding:6px;border-radius:6px;border:none;cursor:pointer;font-size:13px;font-weight:600;
                       background:{{ $filtro==='activa' ? 'var(--accent)' : 'var(--card)' }};
                       color:{{ $filtro==='activa' ? '#fff' : 'var(--muted)' }};">
                Activas
            </button>
            <button wire:click="$set('filtro','archivada')"
                style="flex:1;padding:6px;border-radius:6px;border:none;cursor:pointer;font-size:13px;font-weight:600;
                       background:{{ $filtro==='archivada' ? 'var(--accent)' : 'var(--card)' }};
                       color:{{ $filtro==='archivada' ? '#fff' : 'var(--muted)' }};">
                Archivadas
            </button>
        </div>
        <input wire:model.live.debounce.300ms="buscar"
               placeholder="Buscar contacto..."
               style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:7px 10px;font-size:13px;font-family:inherit;margin-bottom:8px;" />
        <a href="/contactos"
           style="display:block;text-align:center;font-size:12px;color:var(--muted);text-decoration:none;
                  padding:5px;border:1px solid var(--border);border-radius:6px;">
            📋 Gestión de contactos
        </a>
    </div>

    {{-- Lista --}}
    <div style="flex:1;overflow-y:auto;">
        @forelse ($conversaciones as $c)
            <div wire:key="conv-{{ $c->id }}"
                 wire:click="seleccionar({{ $c->id }})"
                 style="padding:12px 14px;cursor:pointer;border-bottom:1px solid var(--border);transition:background 0.1s;
                        background:{{ $convId === $c->id ? 'rgba(192,39,58,0.08)' : 'transparent' }};
                        border-left:3px solid {{ $convId === $c->id ? 'var(--accent)' : 'transparent' }};">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:3px;">
                    <span style="font-size:14px;font-weight:600;color:var(--text);">{{ $c->nombre_o_telefono }}</span>
                    <div style="display:flex;align-items:center;gap:6px;">
                        @if ($c->no_leidos > 0)
                            <span style="background:var(--accent);color:#fff;border-radius:10px;padding:1px 6px;font-size:11px;font-weight:700;">
                                {{ $c->no_leidos }}
                            </span>
                        @endif
                        <span style="font-size:11px;color:var(--muted);">
                            {{ $c->ultima_actividad->format('H:i') }}
                        </span>
                    </div>
                </div>
                <div style="font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    @if ($c->ultimoMensaje)
                        @if ($c->ultimoMensaje->direccion === 'saliente') <span style="color:var(--success);">Vos: </span>
                        @elseif ($c->ultimoMensaje->direccion === 'nota_interna') <span style="color:var(--warning);">🔒 </span>
                        @endif
                        {{ $c->ultimoMensaje->snippet }}
                    @else
                        Sin mensajes
                    @endif
                </div>
            </div>
        @empty
            <div style="padding:32px;text-align:center;color:var(--muted);font-size:13px;">Sin conversaciones</div>
        @endforelse
    </div>
</div>

{{-- ── THREAD: conversación abierta ───────────────────────────── --}}
@if ($convActiva)
<div style="flex:1;display:flex;flex-direction:column;min-width:0;">

    {{-- Header thread --}}
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;flex-shrink:0;background:var(--surface);">
        @if ($editandoNombre)
            <input wire:model="nombreEditar"
                   wire:keydown.enter="editarNombre"
                   wire:keydown.escape="$set('editandoNombre',false)"
                   autofocus
                   style="background:var(--card);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:5px 10px;font-size:15px;font-family:inherit;min-width:180px;">
            <button wire:click="editarNombre" style="background:var(--accent);border:none;color:#fff;border-radius:6px;padding:5px 12px;font-size:13px;cursor:pointer;">OK</button>
            <button wire:click="$set('editandoNombre',false)" style="background:var(--card);border:1px solid var(--border);color:var(--muted);border-radius:6px;padding:5px 10px;font-size:13px;cursor:pointer;">✕</button>
        @else
            <div style="flex:1;min-width:0;">
                <div style="font-size:16px;font-weight:600;display:flex;align-items:center;gap:8px;">
                    {{ $convActiva->nombre_o_telefono }}
                    <button wire:click="abrirEditarNombre"
                            style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:13px;padding:0;" title="Editar nombre">✏️</button>
                </div>
                @if ($convActiva->nombre)
                    <div style="font-size:12px;color:var(--muted);">{{ $convActiva->telefono }}</div>
                @endif
            </div>
        @endif

        @if ($convActiva->resumen_llm)
            <span onclick="iwaToggleResumen(this)"
                  data-resumen="{{ $convActiva->resumen_llm }}"
                  style="background:rgba(88,166,255,.12);border:1px solid rgba(88,166,255,.28);color:var(--info);
                         border-radius:10px;padding:3px 9px;font-size:11px;font-weight:600;cursor:pointer;
                         white-space:nowrap;user-select:none;position:relative;">💡 IA</span>
        @endif

        <button wire:click="$set('mostrarTareas', !$mostrarTareas)"
                style="background:{{ $mostrarTareas ? 'rgba(88,166,255,0.15)' : 'var(--card)' }};border:1px solid {{ $mostrarTareas ? 'rgba(88,166,255,0.3)' : 'var(--border)' }};color:{{ $mostrarTareas ? 'var(--info)' : 'var(--muted)' }};border-radius:6px;padding:6px 12px;font-size:13px;cursor:pointer;">
            ☑ Tareas
            @if ($tareas->where('estado','pendiente')->count() > 0)
                <span style="background:var(--accent);color:#fff;border-radius:8px;padding:1px 5px;font-size:11px;margin-left:4px;">
                    {{ $tareas->where('estado','pendiente')->count() }}
                </span>
            @endif
        </button>

        @if ($filtro === 'activa')
            <button wire:click="archivar({{ $convActiva->id }})"
                    style="background:var(--card);border:1px solid var(--border);color:var(--muted);border-radius:6px;padding:6px 10px;font-size:13px;cursor:pointer;" title="Archivar">📦</button>
        @else
            <button wire:click="desarchivar({{ $convActiva->id }})"
                    style="background:var(--card);border:1px solid var(--border);color:var(--muted);border-radius:6px;padding:6px 10px;font-size:13px;cursor:pointer;" title="Restaurar">↩</button>
        @endif
    </div>

    {{-- Mensajes --}}
    <div id="messages-container" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:8px;">
        @php $fechaAnterior = null; @endphp

        @foreach ($mensajes->reject(fn($m) => $m->direccion === 'saliente' && !$m->usuario_id) as $m)
            @php $fechaMsg = $m->created_at->format('d/m/Y'); @endphp

            @if ($fechaMsg !== $fechaAnterior)
                <div style="text-align:center;margin:8px 0;">
                    <span style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:3px 12px;font-size:11px;color:var(--muted);">
                        {{ $m->created_at->isToday() ? 'Hoy' : ($m->created_at->isYesterday() ? 'Ayer' : $fechaMsg) }}
                    </span>
                </div>
                @php $fechaAnterior = $fechaMsg; @endphp
            @endif

            @if ($m->es_nota)
                {{-- Nota interna --}}
                <div wire:key="msg-{{ $m->id }}" style="display:flex;justify-content:center;">
                    <div style="background:rgba(210,153,34,0.1);border:1px solid rgba(210,153,34,0.25);border-radius:8px;padding:8px 14px;max-width:80%;text-align:center;">
                        <div style="font-size:11px;color:var(--warning);font-weight:600;margin-bottom:3px;">
                            🔒 Nota interna · {{ $m->usuario?->nombre_completo ?? 'Sistema' }}
                        </div>
                        <div style="font-size:14px;color:var(--text);">{{ $m->contenido }}</div>
                        <div style="font-size:11px;color:var(--muted);margin-top:3px;">{{ $m->created_at->format('H:i') }}</div>
                    </div>
                </div>

            @elseif ($m->es_entrante)
                {{-- Mensaje entrante --}}
                <div wire:key="msg-{{ $m->id }}" style="display:flex;justify-content:flex-start;">
                    <div style="background:var(--card);border:1px solid var(--border);border-radius:12px 12px 12px 2px;padding:10px 14px;max-width:70%;">
                        @if ($m->tipo === 'audio')
                            <div style="margin-bottom:4px;">
                                <audio controls style="width:220px;height:32px;accent-color:var(--accent);">
                                    <source src="{{ $m->archivo_url }}">
                                </audio>
                            </div>
                            @if($m->contenido)
                                <div style="font-size:12px;color:var(--muted);font-style:italic;margin-top:4px;">🎙 {{ $m->contenido }}</div>
                            @endif
                        @elseif ($m->tipo === 'imagen' && $m->archivo_url)
                            <img src="{{ $m->archivo_url }}" alt="Imagen"
                                 style="max-width:220px;max-height:180px;border-radius:6px;display:block;cursor:zoom-in;"
                                 onclick="iwaModalAbrir('{{ $m->archivo_url }}','imagen')">
                            @if($m->contenido)
                                <div style="font-size:12px;color:var(--muted);margin-top:4px;">{{ $m->contenido }}</div>
                            @endif
                        @elseif ($m->tipo === 'video' && $m->archivo_url)
                            <video src="{{ $m->archivo_url }}"
                                   style="max-width:220px;max-height:160px;border-radius:6px;display:block;cursor:zoom-in;"
                                   onclick="iwaModalAbrir('{{ $m->archivo_url }}','video')"></video>
                            <div style="font-size:11px;color:var(--muted);margin-top:4px;">▶ Clic para reproducir</div>
                        @elseif ($m->tipo === 'documento' && $m->archivo_url)
                            <div style="display:flex;align-items:center;gap:8px;cursor:pointer;"
                                 onclick="iwaModalAbrir('{{ $m->archivo_url }}','documento','{{ addslashes($m->contenido ?? 'Documento') }}')">
                                <span style="font-size:24px;">📄</span>
                                <span style="color:var(--info);text-decoration:underline;font-size:13px;word-break:break-all;">{{ $m->contenido ?? 'Documento' }}</span>
                            </div>
                        @else
                            <div style="font-size:15px;color:var(--text);line-height:1.5;word-break:break-word;">{{ $m->contenido }}</div>
                        @endif
                        <div style="font-size:11px;color:var(--muted);margin-top:4px;text-align:right;">{{ $m->created_at->format('H:i') }}</div>
                    </div>
                </div>

            @else
                {{-- Mensaje saliente --}}
                <div wire:key="msg-{{ $m->id }}" style="display:flex;justify-content:flex-end;">
                    <div style="background:rgba(192,39,58,0.15);border:1px solid rgba(192,39,58,0.25);border-radius:12px 12px 2px 12px;padding:10px 14px;max-width:70%;">
                        @if ($m->usuario_id)
                            <div style="font-size:11px;color:var(--accent);font-weight:600;margin-bottom:3px;">
                                {{ $m->usuario?->nombre_completo ?? 'Secretaria' }}
                            </div>
                        @else
                            <div style="font-size:11px;color:rgba(192,39,58,0.5);font-weight:600;margin-bottom:3px;">
                                🤖 Bot
                            </div>
                        @endif
                        <div style="font-size:15px;color:var(--text);line-height:1.5;word-break:break-word;">{{ $m->contenido }}</div>
                        <div style="font-size:11px;color:var(--muted);margin-top:4px;text-align:right;">{{ $m->created_at->format('H:i') }}</div>
                    </div>
                </div>
            @endif
        @endforeach

        <div id="messages-end"></div>
    </div>

    {{-- Input --}}
    <div style="border-top:1px solid var(--border);padding:12px 16px;flex-shrink:0;background:var(--surface);">
        <div style="display:flex;gap:6px;margin-bottom:8px;">
            <button wire:click="$set('modo','mensaje')"
                    style="padding:5px 14px;border-radius:6px;border:none;cursor:pointer;font-size:13px;font-weight:600;
                           background:{{ $modo==='mensaje' ? 'var(--accent)' : 'var(--card)' }};
                           color:{{ $modo==='mensaje' ? '#fff' : 'var(--muted)' }};">
                💬 Mensaje
            </button>
            <button wire:click="$set('modo','nota')"
                    style="padding:5px 14px;border-radius:6px;border:none;cursor:pointer;font-size:13px;font-weight:600;
                           background:{{ $modo==='nota' ? 'rgba(210,153,34,0.2)' : 'var(--card)' }};
                           color:{{ $modo==='nota' ? 'var(--warning)' : 'var(--muted)' }};
                           border:1px solid {{ $modo==='nota' ? 'rgba(210,153,34,0.3)' : 'transparent' }};">
                🔒 Nota interna
            </button>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;">
            <textarea wire:model="texto"
                      wire:keydown.ctrl.enter="enviar"
                      placeholder="{{ $modo === 'nota' ? 'Nota interna (solo visible para el equipo)...' : 'Escribí un mensaje... (Ctrl+Enter para enviar)' }}"
                      rows="2"
                      style="flex:1;background:var(--card);border:1px solid {{ $modo==='nota' ? 'rgba(210,153,34,0.4)' : 'var(--border)' }};border-radius:8px;color:var(--text);padding:10px;font-size:15px;font-family:inherit;resize:none;"></textarea>
            <button wire:click="enviar"
                    style="background:{{ $modo==='nota' ? 'rgba(210,153,34,0.2)' : 'var(--accent)' }};border:none;border-radius:8px;color:{{ $modo==='nota' ? 'var(--warning)' : '#fff' }};padding:10px 18px;font-size:18px;cursor:pointer;align-self:flex-end;height:48px;">
                {{ $modo === 'nota' ? '💾' : '➤' }}
            </button>
        </div>
    </div>
</div>

{{-- ── PANEL TAREAS ────────────────────────────────────────────── --}}
@if ($mostrarTareas)
<div style="width:280px;flex-shrink:0;border-left:1px solid var(--border);display:flex;flex-direction:column;background:var(--surface);">
    <div style="padding:12px 14px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:14px;font-weight:600;">Tareas</span>
        <button wire:click="$set('formTarea',!$formTarea)"
                style="background:var(--accent);border:none;color:#fff;border-radius:6px;padding:4px 10px;font-size:13px;cursor:pointer;">
            + Nueva
        </button>
    </div>

    @if ($formTarea)
    <div style="padding:12px;border-bottom:1px solid var(--border);background:var(--card);">
        <input wire:model="tituloTarea" placeholder="Título de la tarea *"
               style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:7px 10px;font-size:13px;font-family:inherit;margin-bottom:6px;">
        <textarea wire:model="descTarea" placeholder="Descripción (opcional)" rows="2"
               style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:7px 10px;font-size:13px;font-family:inherit;resize:none;margin-bottom:6px;"></textarea>
        <select wire:model="asignadoA"
               style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:7px 10px;font-size:13px;font-family:inherit;margin-bottom:6px;">
            <option value="">Sin asignar</option>
            @foreach ($usuarios as $u)
                <option value="{{ $u->id }}">{{ $u->nombre_completo }}</option>
            @endforeach
        </select>
        <input type="date" wire:model="venceAt"
               style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:7px 10px;font-size:13px;font-family:inherit;margin-bottom:8px;">
        <div style="display:flex;gap:6px;">
            <button wire:click="crearTarea"
                    style="flex:1;background:var(--accent);border:none;color:#fff;border-radius:6px;padding:7px;font-size:13px;font-weight:600;cursor:pointer;">Crear</button>
            <button wire:click="$set('formTarea',false)"
                    style="background:var(--card);border:1px solid var(--border);color:var(--muted);border-radius:6px;padding:7px 12px;font-size:13px;cursor:pointer;">✕</button>
        </div>
    </div>
    @endif

    <div style="flex:1;overflow-y:auto;padding:8px;">
        @forelse ($tareas as $t)
            <div wire:key="tarea-{{ $t->id }}"
                 style="background:var(--card);border:1px solid {{ $t->vencida ? 'rgba(248,81,73,0.3)' : 'var(--border)' }};border-radius:8px;padding:10px;margin-bottom:8px;opacity:{{ $t->completada ? '0.55' : '1' }};">
                <div style="display:flex;align-items:flex-start;gap:8px;">
                    <input type="checkbox"
                           wire:click="toggleTarea({{ $t->id }})"
                           {{ $t->completada ? 'checked' : '' }}
                           style="margin-top:3px;accent-color:var(--accent);width:15px;height:15px;cursor:pointer;flex-shrink:0;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;{{ $t->completada ? 'text-decoration:line-through;color:var(--muted);' : '' }}">
                            {{ $t->titulo }}
                        </div>
                        @if ($t->descripcion)
                            <div style="font-size:12px;color:var(--muted);margin-top:2px;">{{ $t->descripcion }}</div>
                        @endif
                        <div style="font-size:11px;margin-top:4px;display:flex;flex-wrap:wrap;gap:4px;">
                            @if ($t->asignadoA)
                                <span style="color:var(--info);">👤 {{ $t->asignadoA->nombre_completo }}</span>
                            @endif
                            @if ($t->vence_at)
                                <span style="color:{{ $t->vencida ? 'var(--error)' : 'var(--muted)' }};">
                                    📅 {{ $t->vence_at->format('d/m') }}{{ $t->vencida ? ' ⚠' : '' }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <button wire:click="eliminarTarea({{ $t->id }})"
                            style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:14px;padding:0;flex-shrink:0;" title="Eliminar">✕</button>
                </div>
            </div>
        @empty
            <div style="text-align:center;padding:24px;color:var(--muted);font-size:13px;">Sin tareas</div>
        @endforelse
    </div>
</div>
@endif

@else
{{-- Sin conversación seleccionada --}}
<div style="flex:1;display:flex;align-items:center;justify-content:center;color:var(--muted);">
    <div style="text-align:center;">
        <div style="font-size:48px;margin-bottom:12px;">💬</div>
        <div style="font-size:16px;">Seleccioná una conversación</div>
        <div style="font-size:13px;margin-top:6px;opacity:0.6;">{{ $conversaciones->count() }} conversaciones activas</div>
    </div>
</div>
@endif

</div>

@script
<script>
    function iwaToggleResumen(el) {
        let pop = document.getElementById('iwa-resumen-pop');
        if (pop) { pop.remove(); return; }
        pop = document.createElement('div');
        pop.id = 'iwa-resumen-pop';
        pop.style.cssText = `position:absolute;top:100%;right:0;margin-top:6px;
            background:var(--card);border:1px solid rgba(88,166,255,.3);border-radius:8px;
            padding:12px 14px;max-width:320px;font-size:12px;color:var(--text);line-height:1.5;
            z-index:200;box-shadow:0 6px 20px rgba(0,0,0,.4);white-space:pre-wrap;word-break:break-word;`;
        pop.textContent = el.dataset.resumen;
        el.style.position = 'relative';
        el.appendChild(pop);
        setTimeout(() => document.addEventListener('click', () => { pop.remove(); }, { once: true }), 0);
    }

    function iwaModalAbrir(url, tipo, nombre) {
        const content = document.getElementById('iwa-modal-content');
        if (tipo === 'imagen') {
            content.innerHTML = `<img src="${url}" style="max-width:90vw;max-height:88vh;border-radius:8px;display:block;">`;
        } else if (tipo === 'video') {
            content.innerHTML = `<video controls autoplay src="${url}" style="max-width:90vw;max-height:88vh;border-radius:8px;display:block;"></video>`;
        } else {
            const ext = url.split('.').pop().split('?')[0].toLowerCase();
            if (ext === 'pdf') {
                content.innerHTML = `<iframe src="${url}" style="width:82vw;height:88vh;border:none;border-radius:8px;background:#fff;"></iframe>`;
            } else {
                const n = nombre || 'Documento';
                content.innerHTML = `<div style="background:var(--card);padding:40px 48px;border-radius:12px;text-align:center;">
                    <div style="font-size:52px;margin-bottom:14px;">📄</div>
                    <div style="color:var(--text);font-size:14px;margin-bottom:20px;max-width:260px;word-break:break-all;">${n}</div>
                    <a href="${url}" download style="background:var(--accent);color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-size:14px;">⬇ Descargar</a>
                </div>`;
            }
        }
        document.getElementById('iwa-modal').style.display = 'flex';
        document.addEventListener('keydown', _iwaEsc);
    }

    function iwaModalCerrar() {
        document.getElementById('iwa-modal').style.display = 'none';
        document.getElementById('iwa-modal-content').innerHTML = '';
        document.removeEventListener('keydown', _iwaEsc);
    }

    function _iwaEsc(e) { if (e.key === 'Escape') iwaModalCerrar(); }

    const scrollBottom = () => {
        const container = document.getElementById('messages-container');
        const end = document.getElementById('messages-end');
        if (!container || !end) return;
        const nearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 120;
        if (nearBottom) end.scrollIntoView({ behavior: 'smooth' });
    };

    scrollBottom();
    Livewire.on('conversacion-abierta', () => {
        setTimeout(() => {
            const end = document.getElementById('messages-end');
            end?.scrollIntoView({ behavior: 'instant' });
        }, 50);
    });
    document.addEventListener('livewire:updated', scrollBottom);
</script>
@endscript
