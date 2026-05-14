{{--
    Widget de chat interno (Equipo + DMs).
    Reusable: solo requiere User autenticado y rutas /chat/*.
    Para portar al repo light: copiar este archivo + ChatController.php + modelos + migraciones + rutas /chat.
--}}
@auth
<style>
.chat-fab {
    position: fixed; right: 20px; bottom: 110px; z-index: 9990;
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--accent); color: #fff;
    border: none; cursor: pointer;
    box-shadow: 0 6px 18px rgba(0,0,0,.25);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; line-height: 1;
    transition: transform .15s;
}
.chat-fab:hover { transform: scale(1.06); }
.chat-fab .badge {
    position: absolute; top: -3px; right: -3px;
    background: #fff; color: var(--accent);
    border-radius: 12px; padding: 1px 6px;
    font-size: 11px; font-weight: 700;
    min-width: 18px; text-align: center;
    box-shadow: 0 2px 6px rgba(0,0,0,.2);
}

.chat-panel {
    position: fixed; right: 20px; bottom: 180px; z-index: 9991;
    width: min(640px, calc(100vw - 40px));
    height: min(560px, calc(100vh - 220px));
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0,0,0,.3);
    display: none;
    overflow: hidden;
}
.chat-panel.open { display: flex; }

.chat-side {
    width: 220px; flex-shrink: 0;
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    overflow: hidden;
}
.chat-side-head {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.chat-side-head b { font-size: 13px; }
.chat-side-head button {
    background: none; border: none; cursor: pointer;
    color: var(--info); font-size: 18px; padding: 0;
}
.chat-side-list { flex: 1; overflow-y: auto; padding: 6px; }
.chat-canal-item {
    padding: 9px 10px;
    border-radius: 7px;
    cursor: pointer;
    margin-bottom: 3px;
    transition: .12s;
    position: relative;
}
.chat-canal-item:hover { background: var(--card); }
.chat-canal-item.active { background: color-mix(in srgb, var(--info) 12%, transparent); }
.chat-canal-nombre { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 2px; display:flex; align-items:center; gap:5px; }
.chat-canal-tipo {
    font-size: 9px; text-transform: uppercase; padding: 0 5px; border-radius: 4px;
    background: color-mix(in srgb, var(--info) 18%, transparent);
    color: var(--info); letter-spacing: .3px;
}
.chat-canal-preview { font-size: 11px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-canal-noleidos {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    background: var(--accent); color: #fff;
    border-radius: 10px; padding: 1px 6px;
    font-size: 10px; font-weight: 700;
}
.chat-canal-cerrar {
    position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
    background: var(--surface); border: 1px solid var(--border);
    color: var(--muted); border-radius: 50%;
    width: 20px; height: 20px; padding: 0;
    cursor: pointer; font-size: 14px; line-height: 1;
    display: none; align-items: center; justify-content: center;
}
.chat-canal-item:hover .chat-canal-cerrar { display: inline-flex; }
.chat-canal-item:hover .chat-canal-noleidos { display: none; }
.chat-online-dot {
    width: 9px; height: 9px; border-radius: 50%;
    background: var(--success);
    box-shadow: 0 0 0 2px var(--surface);
    position: absolute; right: -2px; bottom: -1px;
}
.chat-msg.eliminado {
    background: transparent !important;
    border: 1px dashed var(--border) !important;
    color: var(--muted) !important;
    font-style: italic;
}
.chat-msg-borrar {
    position: absolute; top: 2px; right: 4px;
    background: none; border: none; cursor: pointer;
    color: var(--muted); font-size: 12px; padding: 2px 4px;
    line-height: 1; opacity: 0; transition: opacity .15s;
}
.chat-msg.out { position: relative; }
.chat-msg.out:hover .chat-msg-borrar { opacity: 1; }
.chat-msg-borrar:hover { color: var(--error); }

.chat-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.chat-main-head {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.chat-main-head b { font-size: 13px; }
.chat-main-head .close { background: none; border: none; cursor: pointer; color: var(--muted); font-size: 20px; padding: 0; line-height: 1; }
.chat-msgs { flex: 1; overflow-y: auto; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px; }
.chat-msg-empty { color: var(--muted); font-size: 12px; text-align: center; padding: 30px 0; }

.chat-msg { max-width: 78%; padding: 7px 11px; border-radius: 12px; font-size: 13px; line-height: 1.4; }
.chat-msg.in  { background: var(--bg); border: 1px solid var(--border); align-self: flex-start; border-bottom-left-radius: 4px; }
.chat-msg.out { background: color-mix(in srgb, var(--info) 18%, var(--card)); align-self: flex-end; border-bottom-right-radius: 4px; color: var(--text); }
.chat-msg-autor { font-size: 11px; color: var(--info); margin-bottom: 2px; font-weight: 600; }
.chat-msg-time  { font-size: 10px; color: var(--muted); margin-top: 2px; text-align: right; }

.chat-input-row {
    padding: 10px 12px;
    border-top: 1px solid var(--border);
    display: flex; gap: 8px;
    flex-shrink: 0;
}
.chat-input {
    flex: 1; resize: none;
    padding: 8px 10px;
    border-radius: 7px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text);
    font-size: 13px;
    font-family: inherit;
    min-height: 36px;
    max-height: 100px;
}
.chat-input:focus { outline: none; border-color: var(--info); }
.chat-send {
    padding: 0 16px;
    border: none; border-radius: 7px;
    background: var(--info); color: #fff;
    cursor: pointer; font-size: 13px; font-weight: 600;
    flex-shrink: 0;
}
.chat-send:disabled { opacity: .5; cursor: not-allowed; }

.chat-empty-main { flex: 1; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 13px; padding: 20px; }

/* Selector de usuario para nuevo DM */
.chat-dm-modal {
    position: absolute; inset: 0; z-index: 10;
    background: rgba(0,0,0,.6);
    display: none; align-items: center; justify-content: center;
}
.chat-dm-modal.open { display: flex; }
.chat-dm-card {
    background: var(--card); border: 1px solid var(--border); border-radius: 10px;
    width: 80%; max-width: 360px; max-height: 80%;
    overflow: hidden; display: flex; flex-direction: column;
}
.chat-dm-card-head { padding: 12px 14px; border-bottom: 1px solid var(--border); font-weight: 700; font-size: 13px; display:flex; justify-content:space-between; align-items:center; }
.chat-dm-card-list { flex: 1; overflow-y: auto; padding: 6px; }
.chat-dm-user {
    padding: 8px 10px; border-radius: 6px; cursor: pointer; font-size: 13px;
}
.chat-dm-user:hover { background: var(--surface); }
</style>

<button class="chat-fab" id="chat-fab" onclick="ChatWidget.toggle()" title="Chat interno" aria-label="Chat">
    💬
    <span class="badge" id="chat-fab-badge" style="display:none;">0</span>
</button>

<div class="chat-panel" id="chat-panel">
    <div class="chat-side">
        <div class="chat-side-head">
            <b>Conversaciones</b>
            <button onclick="ChatWidget.abrirNuevoDm()" title="Nuevo mensaje directo" aria-label="Nuevo DM">+</button>
        </div>
        <div class="chat-side-list" id="chat-canales">
            <div style="padding:20px;text-align:center;color:var(--muted);font-size:12px;">Cargando…</div>
        </div>
    </div>

    <div class="chat-main">
        <div class="chat-main-head">
            <b id="chat-titulo">—</b>
            <div style="display:flex;gap:4px;align-items:center;">
                <button id="chat-btn-buscar" onclick="ChatWidget.toggleBuscador()" title="Buscar en este chat" aria-label="Buscar"
                    style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:15px;padding:2px 6px;line-height:1;display:none;">🔍</button>
                <button class="close" onclick="ChatWidget.toggle()" aria-label="Cerrar panel">&times;</button>
            </div>
        </div>
        {{-- Barra de búsqueda (oculta hasta que se abra) --}}
        <div id="chat-buscador" style="display:none;padding:8px 12px;border-bottom:1px solid var(--border);background:var(--surface);">
            <input id="chat-buscar-input" type="text" placeholder="Buscar en el historial…"
                oninput="ChatWidget.buscarDebounced()"
                style="width:100%;padding:6px 10px;border-radius:6px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;font-family:inherit;">
        </div>
        <div class="chat-msgs" id="chat-msgs">
            <div class="chat-empty-main">Seleccioná una conversación</div>
        </div>
        <div class="chat-input-row" id="chat-input-row" style="display:none;">
            <textarea class="chat-input" id="chat-input"
                placeholder="Escribir mensaje… (Ctrl+Enter envía)"
                onkeydown="if(event.ctrlKey && event.key==='Enter') ChatWidget.enviar()"></textarea>
            <button class="chat-send" id="chat-send" onclick="ChatWidget.enviar()">Enviar</button>
        </div>
    </div>

    {{-- Modal nuevo DM (overlay sobre el panel) --}}
    <div class="chat-dm-modal" id="chat-dm-modal">
        <div class="chat-dm-card">
            <div class="chat-dm-card-head">
                Nuevo mensaje directo
                <button onclick="ChatWidget.cerrarNuevoDm()" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:20px;line-height:1;">&times;</button>
            </div>
            <div class="chat-dm-card-list" id="chat-dm-users">Cargando…</div>
        </div>
    </div>
</div>

<script>
(function() {
    const CSRF = '{{ csrf_token() }}';
    const ME   = {{ auth()->id() }};

    const Chat = {
        canalActivo: null,
        ultimoMsgId: 0,
        canales:     [],
        polling:     null,
        pollingMsg:  null,
        primeraCarga: true,
    };
    window.ChatWidget = Chat;

    async function api(method, url, body) {
        const opts = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        };
        if (body) opts.body = JSON.stringify(body);
        const r = await fetch(url, opts);
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    }

    function escTxt(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    Chat.toggle = function() {
        const p = document.getElementById('chat-panel');
        p.classList.toggle('open');
        if (p.classList.contains('open')) Chat.cargarCanales();
    };

    Chat.cargarCanales = async function() {
        try {
            const r = await api('GET', '/chat/canales');
            Chat.canales = r.data || [];
            Chat.pintarCanales();
            actualizarBadge();
        } catch (e) {
            document.getElementById('chat-canales').innerHTML =
                '<div style="padding:20px;text-align:center;color:var(--error);font-size:12px;">Error al cargar</div>';
        }
    };

    Chat.pintarCanales = function() {
        const cont = document.getElementById('chat-canales');
        if (!Chat.canales.length) {
            cont.innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted);font-size:12px;">Sin conversaciones</div>';
            return;
        }
        cont.innerHTML = Chat.canales.map(c => {
            const tipo = c.tipo === 'equipo'
                ? '<span class="chat-canal-tipo">Equipo</span>'
                : '<span class="chat-canal-tipo" style="background:color-mix(in srgb,var(--success) 18%,transparent);color:var(--success);">DM</span>';
            const noleidos = c.no_leidos > 0
                ? `<span class="chat-canal-noleidos">${c.no_leidos}</span>` : '';
            const previewTxt = c.ultimo_msg ? escTxt(c.ultimo_msg.texto) : '<i style="opacity:.6;">sin mensajes</i>';
            const active = Chat.canalActivo === c.id ? 'active' : '';
            const av = chatAvatar(c.nombre, c.tipo === 'equipo', 30, c.otro_online);
            return `<div class="chat-canal-item ${active}" onclick="ChatWidget.abrirCanal(${c.id})" style="display:flex;gap:9px;align-items:center;">
                ${av}
                <div style="flex:1;min-width:0;">
                    <div class="chat-canal-nombre">${escTxt(c.nombre)} ${tipo}</div>
                    <div class="chat-canal-preview">${previewTxt}</div>
                </div>
                ${noleidos}
                <button class="chat-canal-cerrar" onclick="event.stopPropagation();ChatWidget.cerrarCanal(${c.id})" title="Cerrar conversación" aria-label="Cerrar">&times;</button>
            </div>`;
        }).join('');
    };

    // Avatar para chat interno: inicial sobre fondo de color (deterministic por nombre).
    // El canal "Equipo" tiene un ícono especial; los DMs muestran inicial del otro usuario.
    // Si el otro está online, muestra dot verde abajo a la derecha del avatar.
    function chatAvatar(nombre, esEquipo, size, online) {
        const wrapStart = `<div style="position:relative;width:${size}px;height:${size}px;flex-shrink:0;">`;
        const onlineDot = online ? '<span class="chat-online-dot"></span>' : '';
        if (esEquipo) {
            return `${wrapStart}<div style="width:${size}px;height:${size}px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:${Math.floor(size/2)}px;" title="Canal Equipo">⛬</div></div>`;
        }
        const inicial = (nombre || '?').trim().charAt(0).toUpperCase();
        const colores = ['#1a56c4', '#00875a', '#c96a00', '#8C1B29', '#5e2ca5', '#0d8073'];
        const hash = [...(nombre || '')].reduce((a,c) => a + c.charCodeAt(0), 0);
        const bg = colores[hash % colores.length];
        return `${wrapStart}<div style="width:${size}px;height:${size}px;border-radius:50%;background:${bg};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:${Math.floor(size/2.4)}px;">${inicial}</div>${onlineDot}</div>`;
    }

    Chat.cerrarCanal = async function(canalId) {
        if (!confirm('¿Cerrar esta conversación? El historial no se borra; la conversación reaparece sola si llega un mensaje nuevo.')) return;
        try {
            await api('POST', '/chat/canales/' + canalId + '/cerrar');
            // Si era el canal activo, cerramos el panel principal
            if (Chat.canalActivo === canalId) {
                Chat.canalActivo = null;
                document.getElementById('chat-titulo').textContent = '—';
                document.getElementById('chat-msgs').innerHTML = '<div class="chat-empty-main">Seleccioná una conversación</div>';
                document.getElementById('chat-input-row').style.display = 'none';
                document.getElementById('chat-btn-buscar').style.display = 'none';
                document.getElementById('chat-buscador').style.display = 'none';
            }
            await Chat.cargarCanales();
        } catch {
            alert('No se pudo cerrar.');
        }
    };

    Chat.eliminarMensaje = async function(msgId) {
        if (!confirm('¿Eliminar este mensaje?')) return;
        try {
            await api('DELETE', '/chat/mensajes/' + msgId);
            await Chat.cargarMensajes(true);  // re-render del canal
        } catch {
            alert('No se pudo eliminar.');
        }
    };

    let _buscarTimer = null;
    Chat.toggleBuscador = function() {
        const b = document.getElementById('chat-buscador');
        const visible = b.style.display !== 'none';
        b.style.display = visible ? 'none' : 'block';
        if (!visible) {
            document.getElementById('chat-buscar-input').value = '';
            setTimeout(() => document.getElementById('chat-buscar-input').focus(), 50);
        } else {
            // Cerrar buscador → recargar mensajes normales del canal
            if (Chat.canalActivo) Chat.cargarMensajes(true);
        }
    };

    Chat.buscarDebounced = function() {
        clearTimeout(_buscarTimer);
        _buscarTimer = setTimeout(() => Chat.buscar(), 300);
    };

    Chat.buscar = async function() {
        if (!Chat.canalActivo) return;
        const q = document.getElementById('chat-buscar-input').value.trim();
        const cont = document.getElementById('chat-msgs');
        if (q.length < 2) {
            cont.innerHTML = '<div class="chat-msg-empty">Escribí al menos 2 letras para buscar…</div>';
            return;
        }
        cont.innerHTML = '<div class="chat-msg-empty">Buscando…</div>';
        try {
            const r = await api('GET', '/chat/canales/' + Chat.canalActivo + '/buscar?q=' + encodeURIComponent(q));
            const hits = r.data || [];
            if (!hits.length) {
                cont.innerHTML = '<div class="chat-msg-empty">Sin resultados para "' + escTxt(q) + '".</div>';
                return;
            }
            cont.innerHTML = `<div style="padding:8px 14px;color:var(--muted);font-size:11px;">${hits.length} resultado${hits.length !== 1 ? 's' : ''}</div>`
                + hits.reverse().map(m => msgHtml({...m, eliminado: false})).join('');
            cont.scrollTop = cont.scrollHeight;
        } catch {
            cont.innerHTML = '<div class="chat-msg-empty" style="color:var(--error);">Error al buscar.</div>';
        }
    };

    Chat.abrirCanal = async function(canalId) {
        Chat.canalActivo = canalId;
        Chat.ultimoMsgId = 0;
        const canal = Chat.canales.find(c => c.id === canalId);
        document.getElementById('chat-titulo').textContent = canal?.nombre ?? '—';
        document.getElementById('chat-input-row').style.display = 'flex';
        document.getElementById('chat-btn-buscar').style.display = 'inline-block';
        document.getElementById('chat-buscador').style.display = 'none';
        document.getElementById('chat-buscar-input').value = '';
        document.getElementById('chat-msgs').innerHTML = '<div class="chat-msg-empty">Cargando mensajes…</div>';
        Chat.pintarCanales();

        await Chat.cargarMensajes(true);
        await Chat.marcarLeido();
    };

    // Lock para que no haya dos cargarMensajes en vuelo a la vez —
    // si pasa, dos GETs con el mismo since= duplican mensajes al appendear.
    let _cargandoMensajes = false;

    Chat.cargarMensajes = async function(reset) {
        if (!Chat.canalActivo) return;
        if (_cargandoMensajes && !reset) return;   // reset siempre prevalece
        _cargandoMensajes = true;
        const canalAlInicio = Chat.canalActivo;
        try {
            const url = '/chat/canales/' + Chat.canalActivo + '/mensajes' + (reset ? '' : '?since=' + Chat.ultimoMsgId);
            const r = await api('GET', url);
            // Si el usuario cambió de canal mientras volaba el request, descartamos.
            if (Chat.canalActivo !== canalAlInicio) return;
            const msgs = r.data || [];
            const cont = document.getElementById('chat-msgs');

            if (reset) {
                if (!msgs.length) {
                    cont.innerHTML = '<div class="chat-msg-empty">Todavía no hay mensajes. Empezá vos.</div>';
                } else {
                    cont.innerHTML = msgs.map(m => msgHtml(m)).join('');
                    cont.scrollTop = cont.scrollHeight;
                }
            } else {
                if (msgs.length) {
                    cont.querySelectorAll('.chat-msg-empty').forEach(e => e.remove());
                    let appended = 0;
                    msgs.forEach(m => {
                        // Dedup: si ya está en el DOM, lo salteamos. Cubre el caso
                        // del poller corriendo en paralelo con un envío manual.
                        if (cont.querySelector(`[data-msg-id="${m.id}"]`)) return;
                        cont.insertAdjacentHTML('beforeend', msgHtml(m));
                        appended++;
                    });
                    if (appended > 0) cont.scrollTop = cont.scrollHeight;
                    // Sonido si llegó algo de otra persona y efectivamente se appendeó
                    if (appended > 0 && msgs.some(m => m.user_id !== ME)) sonarPing();
                }
            }
            if (msgs.length) Chat.ultimoMsgId = Math.max(Chat.ultimoMsgId, msgs[msgs.length - 1].id);
        } catch {} finally {
            _cargandoMensajes = false;
        }
    };

    function msgHtml(m) {
        const mio = m.user_id === ME;
        if (m.eliminado) {
            return `<div class="chat-msg ${mio ? 'out' : 'in'} eliminado" data-msg-id="${m.id}">
                ${mio ? '' : `<div class="chat-msg-autor">${escTxt(m.autor)}</div>`}
                <div>Mensaje eliminado</div>
                <div class="chat-msg-time">${escTxt(m.hora)}</div>
            </div>`;
        }
        const borrar = mio
            ? `<button class="chat-msg-borrar" onclick="ChatWidget.eliminarMensaje(${m.id})" title="Eliminar mensaje" aria-label="Eliminar">&times;</button>`
            : '';
        return `<div class="chat-msg ${mio ? 'out' : 'in'}" data-msg-id="${m.id}">
            ${mio ? '' : `<div class="chat-msg-autor">${escTxt(m.autor)}</div>`}
            <div>${escTxt(m.texto)}</div>
            <div class="chat-msg-time">${escTxt(m.hora)}</div>
            ${borrar}
        </div>`;
    }

    Chat.marcarLeido = async function() {
        if (!Chat.canalActivo) return;
        try {
            await api('POST', '/chat/canales/' + Chat.canalActivo + '/marcar-leido');
            // Refrescar lista para que el badge baje a 0
            await Chat.cargarCanales();
        } catch {}
    };

    Chat.enviar = async function() {
        const inp = document.getElementById('chat-input');
        const texto = inp.value.trim();
        if (!texto || !Chat.canalActivo) return;

        const btn = document.getElementById('chat-send');
        btn.disabled = true;
        try {
            await api('POST', '/chat/canales/' + Chat.canalActivo + '/mensajes', { texto });
            inp.value = '';
            await Chat.cargarMensajes(false);
            await Chat.cargarCanales();
        } catch (e) {
            alert('No se pudo enviar el mensaje.');
        } finally {
            btn.disabled = false;
            inp.focus();
        }
    };

    Chat.abrirNuevoDm = async function() {
        const modal = document.getElementById('chat-dm-modal');
        modal.classList.add('open');
        const cont = document.getElementById('chat-dm-users');
        cont.innerHTML = 'Cargando…';
        try {
            const r = await api('GET', '/chat/usuarios');
            if (!r.data.length) {
                cont.innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted);font-size:12px;">No hay otros usuarios</div>';
                return;
            }
            cont.innerHTML = r.data.map(u => {
                const dot = u.online
                    ? '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--success);margin-right:6px;vertical-align:middle;" title="En línea"></span>'
                    : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--muted);opacity:.4;margin-right:6px;vertical-align:middle;" title="Desconectado"></span>';
                return `<div class="chat-dm-user" onclick="ChatWidget.crearDm(${u.id})">${dot}${escTxt(u.nombre_completo)}</div>`;
            }).join('');
        } catch {
            cont.innerHTML = '<div style="padding:20px;color:var(--error);">Error</div>';
        }
    };

    Chat.cerrarNuevoDm = function() {
        document.getElementById('chat-dm-modal').classList.remove('open');
    };

    Chat.crearDm = async function(userId) {
        try {
            const r = await api('POST', '/chat/dm', { user_id: userId });
            Chat.cerrarNuevoDm();
            await Chat.cargarCanales();
            Chat.abrirCanal(r.canal_id);
        } catch {
            alert('No se pudo abrir el chat.');
        }
    };

    // Tracking de últimos IDs por canal para detectar mensajes nuevos entre polls.
    // Se inicializa con los valores actuales del primer poll, así no notifica
    // de un golpe los pendientes acumulados antes de cargar la app.
    const _ultimoIdPorCanal = {};
    let _primerPollNoLeidos = true;

    // ── Badge & polling global ──────────────────────────────────
    // Guardamos el título original una sola vez para poder restaurarlo cuando
    // no hay mensajes sin leer. Si otro script lo cambia más tarde, le pegamos
    // arriba con `(N)` igual — esto es OK porque queremos que el usuario lo vea.
    const _tituloOriginal = document.title.replace(/^\(\d+\+?\)\s*/, '');

    // Favicon dinámico: dibuja el logo base + un dot rojo con el contador.
    // Cacheamos por valor de N para no regenerar en cada poll si el número no cambió.
    const _faviconBaseHref = (document.getElementById('favicon')?.href) || '/favicon.ico';
    const _faviconCache = {};  // { '0': hrefBase, '3': dataURL, '99+': dataURL }
    let   _faviconKeyActual = null;

    function _generarFaviconConBadge(label) {
        // Favicon "alerta": círculo verde sólido con el contador en blanco,
        // ocupando todo el favicon. Reemplaza el logo cuando hay mensajes
        // sin leer para que sea evidente de un vistazo en la solapa.
        const size = 64;
        const c = document.createElement('canvas');
        c.width = size; c.height = size;
        const ctx = c.getContext('2d');

        // Círculo verde lleno
        ctx.beginPath();
        ctx.arc(size/2, size/2, size/2 - 1, 0, Math.PI*2);
        ctx.fillStyle = '#00875a';   // var(--success) tema claro
        ctx.fill();

        // Número en blanco, centrado. Tamaño según ancho del label.
        const txt = String(label);
        const fontPx = txt.length === 1 ? 44 : (txt.length === 2 ? 36 : 26);
        ctx.fillStyle = '#fff';
        ctx.font = 'bold ' + fontPx + 'px Arial, Helvetica, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(txt, size/2, size/2 + 2);

        return c.toDataURL('image/png');
    }

    function actualizarFavicon(count) {
        const key = count > 0 ? (count > 99 ? '99+' : String(count)) : '0';
        if (key === _faviconKeyActual) return;   // sin cambios, no tocar el DOM
        _faviconKeyActual = key;

        if (!(key in _faviconCache)) {
            _faviconCache[key] = (key === '0')
                ? _faviconBaseHref
                : _generarFaviconConBadge(key);
        }

        // Chrome (y otros) cachean el favicon agresivamente y a veces no
        // detectan el cambio de href en un mismo <link>. Trick conocido:
        // remover el <link> y agregar uno nuevo fuerza al browser a re-leer.
        document.querySelectorAll('link[rel~="icon"]').forEach(el => el.remove());
        const link = document.createElement('link');
        link.id   = 'favicon';
        link.rel  = 'icon';
        link.type = key === '0' ? 'image/x-icon' : 'image/png';
        link.href = _faviconCache[key];
        document.head.appendChild(link);
    }

    async function actualizarBadge() {
        try {
            const r = await api('GET', '/chat/no-leidos');
            const total = r.count > 0 ? (r.count > 99 ? '99+' : r.count) : '';

            // Badge del FAB flotante
            const badge = document.getElementById('chat-fab-badge');
            if (r.count > 0) {
                badge.textContent = total;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }

            // Badge del botón en la navbar (junto al nombre de usuario)
            const nb = document.getElementById('navbar-chat-badge');
            if (nb) {
                if (r.count > 0) {
                    nb.textContent = total;
                    nb.style.display = '';
                } else {
                    nb.style.display = 'none';
                }
            }

            // Título de la pestaña del browser: "(3) Crecer — Panel"
            // Quita cualquier prefijo "(N)" previo y lo reemplaza por el actual.
            const base = document.title.replace(/^\(\d+\+?\)\s*/, '');
            document.title = r.count > 0 ? `(${total}) ${base || _tituloOriginal}` : (base || _tituloOriginal);

            // Favicon dinámico con dot rojo + contador
            actualizarFavicon(r.count);

            // Detectar canales con mensaje nuevo desde el último poll y notificar.
            for (const c of (r.canales || [])) {
                const previo = _ultimoIdPorCanal[c.canal_id] ?? 0;
                if (!_primerPollNoLeidos && c.ultimo_id > previo) {
                    // No notificar si el chat está abierto en ese canal con la pestaña visible.
                    const enEseCanal = Chat.canalActivo === c.canal_id && !document.hidden;
                    if (!enEseCanal && window.Notify) {
                        const titulo = c.tipo === 'equipo' ? 'Equipo · ' + c.autor : c.autor;
                        window.Notify.disparar({
                            titulo,
                            cuerpo: c.texto,
                            tag: 'chat-' + c.canal_id,
                            soloOculto: true,    // si la pestaña está activa, asumimos que ya está mirando
                        });
                    }
                }
                _ultimoIdPorCanal[c.canal_id] = c.ultimo_id;
            }
            _primerPollNoLeidos = false;

            // Si está abierto un canal y el panel está visible: refrescar mensajes
            const panelOpen = document.getElementById('chat-panel').classList.contains('open');
            if (panelOpen && Chat.canalActivo) {
                await Chat.cargarMensajes(false);
                // Re-marcar como leído si la pestaña tiene foco
                if (!document.hidden) await Chat.marcarLeido();
            }
        } catch {}
    }

    function sonarPing() {
        try {
            const ctx = window._chatAudioCtx ||= new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.frequency.value = 660;
            g.gain.value = 0.0001;
            o.connect(g); g.connect(ctx.destination);
            o.start();
            g.gain.exponentialRampToValueAtTime(0.12, ctx.currentTime + 0.03);
            g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
            o.stop(ctx.currentTime + 0.4);
        } catch {}
    }

    // Polling cada 6s
    setInterval(actualizarBadge, 6000);
    // Llamada inicial al cargar
    actualizarBadge();
})();
</script>
@endauth
