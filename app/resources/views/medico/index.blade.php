@extends('layouts.app')
@section('title', 'Mi consultorio')

@section('content')
<style>
.med-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 16px; align-items: start; }
@media (max-width: 1100px) { .med-wrap { grid-template-columns: 1fr; } }

.med-header {
    background: var(--card); border: 1px solid var(--border); border-radius: 10px;
    padding: 14px 18px; margin-bottom: 14px;
    display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
}
.med-name { font-size: 18px; font-weight: 700; }
.med-spec { font-size: 12px; color: var(--muted); margin-top: 2px; }
.med-stats { display: flex; gap: 14px; margin-left: auto; flex-wrap: wrap; }
.med-stat {
    background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
    padding: 6px 12px; min-width: 90px; text-align: center;
}
.med-stat-num { font-size: 20px; font-weight: 700; line-height: 1; }
.med-stat-lbl { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; }

.med-section {
    background: var(--card); border: 1px solid var(--border); border-radius: 10px;
    padding: 16px; margin-bottom: 14px;
}
.med-section-title {
    font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;
    letter-spacing: .5px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
}
.med-count {
    background: var(--surface); color: var(--text); border-radius: 10px; padding: 1px 8px;
    font-size: 10px; font-weight: 700;
}

.pac-card {
    background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
    padding: 12px 14px; margin-bottom: 8px; transition: border-color .15s;
}
.pac-card:last-child { margin-bottom: 0; }
.pac-card.llamado { border-color: var(--info); background: color-mix(in srgb, var(--info) 6%, var(--bg)); }
.pac-card.atendido { opacity: .55; }

.pac-head { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.pac-name { font-size: 15px; font-weight: 700; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pac-meta-row { display: flex; gap: 8px; flex-wrap: wrap; font-size: 12px; color: var(--muted); margin-bottom: 8px; }
.pac-meta-row b { color: var(--text); font-weight: 600; }
.pac-flags { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 8px; }
.pac-flag {
    display: inline-block; font-size: 10px; font-weight: 700;
    padding: 1px 6px; border-radius: 4px;
    text-transform: uppercase; letter-spacing: .3px;
}
.flag-prim   { background: color-mix(in srgb, var(--warning) 12%, transparent); color: var(--warning); border: 1px solid color-mix(in srgb, var(--warning) 35%, transparent); }
.flag-sint   { background: color-mix(in srgb, var(--error)   12%, transparent); color: var(--error);   border: 1px solid color-mix(in srgb, var(--error)   35%, transparent); }
.flag-wapp   { background: color-mix(in srgb, var(--success) 12%, transparent); color: var(--success); border: 1px solid color-mix(in srgb, var(--success) 35%, transparent); }
.flag-espera { background: color-mix(in srgb, var(--warning) 18%, transparent); color: var(--warning); border: 1px solid color-mix(in srgb, var(--warning) 50%, transparent); }
.flag-info   { background: color-mix(in srgb, var(--info)    12%, transparent); color: var(--info);    border: 1px solid color-mix(in srgb, var(--info)    35%, transparent); }

.pac-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.btn-action {
    padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600;
    cursor: pointer; border: 1px solid var(--border); background: var(--card); color: var(--text);
    transition: .15s;
}
.btn-action:hover { border-color: var(--accent); }
.btn-action.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
.btn-action.primary:hover { filter: brightness(.92); }
.btn-action.ghost { background: transparent; }
.btn-action:disabled { opacity: .5; cursor: not-allowed; }

.pac-consultorio-input {
    width: 56px; padding: 6px 8px; border-radius: 6px; font-size: 12px;
    border: 1px solid var(--border); background: var(--bg); color: var(--text);
    text-align: center;
}

.empty-state { text-align: center; padding: 30px 20px; color: var(--muted); font-size: 13px; }

/* Mini chat embebido (lista de DMs a secretarias) */
.chat-side-embed {
    background: var(--card); border: 1px solid var(--border); border-radius: 10px;
    padding: 16px; display: flex; flex-direction: column; gap: 8px;
}
.chat-shortcuts { display: flex; flex-direction: column; gap: 6px; margin-bottom: 8px; }
.chat-shortcut {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; background: var(--bg); border: 1px solid var(--border);
    border-radius: 8px; cursor: pointer; transition: .15s; font-size: 13px;
}
.chat-shortcut:hover { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 5%, var(--bg)); }
.chat-shortcut .av { width: 26px; height: 26px; border-radius: 50%; background: var(--accent); color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; }
.chat-shortcut .who { flex: 1; min-width: 0; }
.chat-shortcut .who-name { font-weight: 600; }
.chat-shortcut .who-sub  { font-size: 11px; color: var(--muted); margin-top: 1px; }
.chat-shortcut .unread {
    background: var(--accent); color: #fff; border-radius: 10px; padding: 1px 7px;
    font-size: 10px; font-weight: 700;
}
.chat-online { width: 8px; height: 8px; border-radius: 50%; background: var(--success); flex-shrink: 0; }
.chat-offline { width: 8px; height: 8px; border-radius: 50%; background: var(--muted); flex-shrink: 0; opacity: .4; }
</style>

<div class="med-header">
    <div>
        @php
            // Si el nombre ya empieza con "Dr.", "Dra.", "Dr/a.", no duplicamos prefijo.
            $tienePrefijo = (bool) preg_match('/^Dr[ae\/]?\.?\s/i', $medico->nombre_completo);
        @endphp
        <div class="med-name">{{ $tienePrefijo ? '' : 'Dr/a. ' }}{{ $medico->nombre_completo }}</div>
        <div class="med-spec">
            {{ $medico->especialidad ?: 'Sin especialidad cargada' }}
            @if($medico->consultorio)
                · Consultorio {{ $medico->consultorio }}@if($medico->planta) (planta {{ $medico->planta }})@endif
            @endif
        </div>
    </div>

    <div class="med-stats">
        <div class="med-stat">
            <div class="med-stat-num" id="stat-sala">{{ $enSala->count() }}</div>
            <div class="med-stat-lbl">En sala</div>
        </div>
        <div class="med-stat">
            <div class="med-stat-num" id="stat-llamados">{{ $llamados->where('atendido_at', null)->count() }}</div>
            <div class="med-stat-lbl">Llamados</div>
        </div>
        <div class="med-stat">
            <div class="med-stat-num" id="stat-atendidos">{{ $llamados->whereNotNull('atendido_at')->count() }}</div>
            <div class="med-stat-lbl">Atendidos hoy</div>
        </div>
    </div>

</div>

<div class="med-wrap">
    <div>
        <div class="med-section">
            <div class="med-section-title">
                🪑 En sala de espera
                <span class="med-count" id="cnt-sala">{{ $enSala->count() }}</span>
            </div>
            <div id="lista-sala"><div class="empty-state">Cargando…</div></div>
        </div>

        <div class="med-section">
            <div class="med-section-title">
                ✓ Llamados / Atendidos hoy
                <span class="med-count" id="cnt-llamados">{{ $llamados->count() }}</span>
            </div>
            <div id="lista-llamados"><div class="empty-state">Cargando…</div></div>
        </div>

        <div class="med-section">
            <div class="med-section-title" style="justify-content:flex-start;">
                📋 Tareas que delegué
                <span class="med-count" id="cnt-tareas">{{ $tareasDelegadas->count() }}</span>
                <button class="btn-action primary" onclick="abrirModalTarea()" style="margin-left:auto;font-size:12px;padding:6px 14px;">+ Nueva tarea</button>
            </div>
            <div id="lista-tareas"><div class="empty-state">Cargando…</div></div>
        </div>
    </div>

    <aside class="chat-side-embed">
        <div class="med-section-title" style="margin-bottom: 4px;">💬 Comunicarme con</div>
        <div id="chat-shortcuts" class="chat-shortcuts">
            <div class="empty-state" style="padding:20px;">Cargando…</div>
        </div>
        <div style="font-size:11px;color:var(--muted);text-align:center;border-top:1px solid var(--border);padding-top:8px;">
            Hacé clic en un nombre para chatear. El historial queda guardado.
        </div>
    </aside>
</div>

<script>
const $ = (id) => document.getElementById(id);

function getCookie(name) {
    return document.cookie.split('; ').reduce((acc, c) => {
        const [k, v] = c.split('=');
        return k === name ? decodeURIComponent(v) : acc;
    }, null);
}

async function call(url, opts = {}) {
    // Token CRUDO de la sesión (meta del layout o fallback blade-rendered). El meta sirve
    // por toda la vida de la sesión (SESSION_LIFETIME) — no caduca al ratito.
    // OJO: NO usar la cookie XSRF-TOKEN como `X-CSRF-TOKEN` — viene CIFRADA y Laravel
    // no la descifra en ese header → 419 "sesión expirada". (Era el bug del auto-CSRF cookie.)
    const tok = document.querySelector('meta[name=csrf-token]')?.content
        ?? document.querySelector('input[name=_token]')?.value ?? '{{ csrf_token() }}';
    const r = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': tok,
            ...(opts.headers || {}),
        },
        ...opts,
    });
    const d = await r.json().catch(() => ({}));
    if (r.status === 419) return { ok: false, _err: 'Sesión expirada — recargá la página (F5).' };
    if (r.status === 422) {
        const detalle = d.errors ? Object.values(d.errors).flat().join('\n') : (d.message || '');
        return { ok: false, _err: 'Datos inválidos:\n' + detalle };
    }
    if (r.status === 403) {
        return { ok: false, _err: 'No tenés permiso: ' + (d.error || d.message || '403') };
    }
    if (!r.ok) return { ok: false, _err: d.error || d.message || ('HTTP ' + r.status) };
    return d;
}

function renderListaSala(items) {
    const c = $('lista-sala');
    $('cnt-sala').textContent = items.length;
    $('stat-sala').textContent = items.length;
    if (!items.length) {
        c.innerHTML = '<div class="empty-state">Sin pacientes en sala. La secretaria los libera acá cuando terminan el check-in.</div>';
        return;
    }
    c.innerHTML = items.map(p => `
        <div class="pac-card" data-id="${p.id}">
            <div class="pac-head">
                <div class="pac-name">${esc(p.nombre)}</div>
                <span class="pac-flag flag-info">DNI ${esc(p.dni)}</span>
            </div>
            <div class="pac-flags">
                ${p.primera_vez   ? '<span class="pac-flag flag-prim">Primera vez</span>' : ''}
                ${p.sin_turno     ? '<span class="pac-flag flag-sint">Sin turno</span>' : ''}
                ${p.derivado_bot  ? '<span class="pac-flag flag-wapp">WhatsApp</span>' : ''}
                ${p.minutos_espera > 20 ? `<span class="pac-flag flag-espera">Espera ${p.minutos_espera}m</span>` : ''}
            </div>
            <div class="pac-meta-row">
                ${p.turno_hora ? `<span>Turno <b>${esc(p.turno_hora)}</b></span>` : ''}
                ${p.practica  ? `<span>· ${esc(p.practica)}</span>` : ''}
                ${p.obra_social ? `<span>· ${esc(p.obra_social)}</span>` : ''}
            </div>
            ${p.nota ? `<div style="font-size:12px;color:var(--muted);font-style:italic;margin-bottom:8px;">📝 ${esc(p.nota)}</div>` : ''}
            <div class="pac-actions">
                <input type="number" min="1" max="99" class="pac-consultorio-input"
                       id="consult-${p.id}" placeholder="Box"
                       value="${p.consultorio ?? @json($medico->consultorio) ?? ''}">
                <button class="btn-action primary" onclick="llamar(${p.id})">Llamar al consultorio</button>
            </div>
        </div>
    `).join('');
}

function renderListaLlamados(items) {
    const c = $('lista-llamados');
    $('cnt-llamados').textContent = items.length;
    const sinAtender = items.filter(p => !p.atendido_at).length;
    const atendidos  = items.filter(p =>  p.atendido_at).length;
    $('stat-llamados').textContent  = sinAtender;
    $('stat-atendidos').textContent = atendidos;
    if (!items.length) {
        c.innerHTML = '<div class="empty-state">Todavía no llamaste a nadie hoy.</div>';
        return;
    }
    c.innerHTML = items.map(p => `
        <div class="pac-card ${p.atendido_at ? 'atendido' : 'llamado'}" data-id="${p.id}">
            <div class="pac-head">
                <div class="pac-name">${esc(p.nombre)}</div>
                <span class="pac-flag flag-info">Consultorio ${p.consultorio ?? '—'}</span>
                ${p.atendido_at ? `<span class="pac-flag flag-wapp">Atendido ${esc(p.atendido_at)}</span>` : `<span class="pac-flag flag-info">Llamado ${esc(p.llamado_at)}</span>`}
            </div>
            <div class="pac-meta-row">
                <span>DNI <b>${esc(p.dni)}</b></span>
                ${p.practica  ? `<span>· ${esc(p.practica)}</span>` : ''}
            </div>
            ${!p.atendido_at ? `
                <div class="pac-actions">
                    <button class="btn-action ghost" onclick="rellamar(${p.id})">↻ Re-llamar</button>
                    <button class="btn-action" onclick="atendido(${p.id})">✓ Marcar atendido</button>
                </div>
            ` : ''}
        </div>
    `).join('');
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function llamar(id) {
    const consultorio = parseInt($(`consult-${id}`).value) || null;
    if (!consultorio) { alert('Indicá el número de consultorio.'); return; }
    const j = await call(`/medico/${id}/llamar`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ consultorio }),
    });
    if (!j.ok) { alert('No se pudo llamar al paciente:\n' + (j._err || j.error || 'Error desconocido')); return; }
    await refrescar();
}

async function rellamar(id) {
    const j = await call(`/medico/${id}/rellamar`, { method: 'POST' });
    if (!j.ok) { alert('No se pudo re-llamar:\n' + (j._err || j.error || 'Error desconocido')); return; }
    await refrescar();
}

async function atendido(id) {
    if (!confirm('Marcar este paciente como atendido?')) return;
    const j = await call(`/medico/${id}/atendido`, { method: 'POST' });
    if (!j.ok) { alert('No se pudo marcar como atendido:\n' + (j._err || j.error || 'Error desconocido')); return; }
    await refrescar();
}

async function refrescar() {
    const j = await call('/medico/data');
    if (!j.ok) return;
    renderListaSala(j.en_sala || []);
    renderListaLlamados(j.llamados || []);
}

// ── Shortcuts de chat: lista de secretarias con presencia online ────
async function cargarShortcuts() {
    try {
        const r = await fetch('/chat/usuarios', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const j = await r.json();
        const users = j.data || [];
        const c = $('chat-shortcuts');
        if (!users.length) {
            c.innerHTML = '<div class="empty-state" style="padding:14px;">No hay otros usuarios cargados.</div>';
            return;
        }
        c.innerHTML = users.map(u => `
            <div class="chat-shortcut" onclick="abrirDM(${u.id})">
                <div class="${u.online ? 'chat-online' : 'chat-offline'}"></div>
                <div class="av">${esc((u.nombre_completo || '?')[0].toUpperCase())}</div>
                <div class="who">
                    <div class="who-name">${esc(u.nombre_completo || '—')}</div>
                    <div class="who-sub">${u.online ? 'En línea' : 'Sin conexión'}</div>
                </div>
            </div>`).join('');
    } catch (e) { /* silent */ }
}

function abrirDM(userId) {
    // Usa el widget flotante global: crea/reabre el DM y lo deja abierto.
    if (window.ChatWidget?.crearDm) {
        // Asegurar que el panel esté abierto
        const panel = document.getElementById('chat-panel');
        if (panel && !panel.classList.contains('open')) {
            window.ChatWidget.toggle();
        }
        window.ChatWidget.crearDm(userId);
    }
}

// ── Tareas delegadas ────────────────────────────────────────────────
const DESTINATARIOS = @json($destinatarios);

function renderListaTareas(items) {
    const c = $('lista-tareas');
    $('cnt-tareas').textContent = items.length;
    if (!items.length) {
        c.innerHTML = '<div class="empty-state">Todavía no encargaste ninguna tarea.</div>';
        return;
    }
    c.innerHTML = items.map(t => {
        const completada = t.completada;
        const venc = t.vencida;
        return `
        <div class="pac-card ${completada ? 'atendido' : (venc ? 'llamado' : '')}" data-id="${t.id}">
            <div class="pac-head">
                <div class="pac-name">${esc(t.titulo)}</div>
                ${completada ? '<span class="pac-flag flag-wapp">Completada</span>'
                  : venc ? '<span class="pac-flag flag-sint">Vencida</span>'
                  : (t.prioridad === 'alta' ? '<span class="pac-flag flag-prim">Alta</span>' : '')}
                ${t.estado === 'en_progreso' ? '<span class="pac-flag flag-info">En progreso</span>' : ''}
            </div>
            <div class="pac-meta-row">
                <span>Para: <b>${esc(t.asignada_a_nombre || '—')}</b></span>
                ${t.vence_at ? `<span>· vence ${esc(t.vence_at)}</span>` : ''}
                <span>· ${esc(t.creada)}</span>
            </div>
            ${t.descripcion ? `<div style="font-size:12px;color:var(--muted);margin-top:4px;">${esc(t.descripcion)}</div>` : ''}
        </div>`;
    }).join('');
}

// ── Modal nueva tarea ────────────────────────────────────────────────
function abrirModalTarea() {
    $('tarea-titulo').value = '';
    $('tarea-desc').value = '';
    $('tarea-vence').value = '';
    $('tarea-prio').value = 'normal';
    $('tarea-dest').value = DESTINATARIOS[0]?.id || '';
    $('tarea-modal').classList.add('open');
    setTimeout(() => $('tarea-titulo').focus(), 50);
}

function cerrarModalTarea() {
    $('tarea-modal').classList.remove('open');
}

async function guardarTarea() {
    const body = {
        titulo:      $('tarea-titulo').value.trim(),
        descripcion: $('tarea-desc').value.trim() || null,
        asignada_a:  parseInt($('tarea-dest').value) || null,
        prioridad:   $('tarea-prio').value,
        vence_at:    $('tarea-vence').value || null,
    };
    if (!body.titulo) { alert('Poné un título.'); return; }
    if (!body.asignada_a) { alert('Elegí a quién asignársela.'); return; }

    const j = await call('/medico/tareas', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    if (!j.ok) {
        const det = j.errors ? Object.values(j.errors).flat().join('\n') : (j.error || j.message || 'Error');
        alert('No se pudo crear:\n' + det);
        return;
    }
    cerrarModalTarea();
    await refrescar();
}

// Esc para cerrar
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && $('tarea-modal').classList.contains('open')) cerrarModalTarea();
});

// Extender refrescar para incluir tareas
const _refrescarOrig = refrescar;
refrescar = async function() {
    const j = await call('/medico/data');
    if (!j.ok) return;
    renderListaSala(j.en_sala || []);
    renderListaLlamados(j.llamados || []);
    renderListaTareas(j.tareas_delegadas || []);
};

refrescar();
cargarShortcuts();
setInterval(refrescar, 6000);
setInterval(cargarShortcuts, 12000);
</script>

{{-- Modal de nueva tarea --}}
<style>
.tarea-modal-bg {
    display: none;
    position: fixed; inset: 0; z-index: 6000;
    background: rgba(0,0,0,.55); align-items: center; justify-content: center;
    padding: 20px;
}
.tarea-modal-bg.open { display: flex; }
.tarea-modal {
    background: var(--card); border: 1px solid var(--border); border-radius: 12px;
    width: 100%; max-width: 520px; padding: 22px;
    box-shadow: 0 12px 40px rgba(0,0,0,.4);
}
.tarea-modal h3 { font-size: 16px; font-weight: 700; margin-bottom: 14px; }
.tarea-field { margin-bottom: 10px; }
.tarea-field label { display: block; font-size: 11px; font-weight: 600; color: var(--muted);
    text-transform: uppercase; letter-spacing: .3px; margin-bottom: 4px; }
.tarea-field input, .tarea-field textarea, .tarea-field select {
    width: 100%; padding: 9px 11px; border: 1px solid var(--border);
    border-radius: 6px; background: var(--bg); color: var(--text);
    font-size: 13px; font-family: inherit;
}
.tarea-field input:focus, .tarea-field textarea:focus, .tarea-field select:focus {
    outline: none; border-color: var(--accent);
}
.tarea-field textarea { min-height: 76px; resize: vertical; }
.tarea-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.tarea-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 14px; }
</style>

<div class="tarea-modal-bg" id="tarea-modal" onclick="if(event.target===this) cerrarModalTarea()">
    <div class="tarea-modal">
        <h3>Encargar tarea a una secretaria</h3>
        <div class="tarea-field">
            <label>Título *</label>
            <input type="text" id="tarea-titulo" placeholder="Llamar a paciente Pérez para confirmar turno">
        </div>
        <div class="tarea-field">
            <label>Detalle</label>
            <textarea id="tarea-desc" placeholder="Información adicional, contacto, urgencia…"></textarea>
        </div>
        <div class="tarea-row-2">
            <div class="tarea-field">
                <label>Asignar a *</label>
                <select id="tarea-dest">
                    @foreach($destinatarios as $d)
                        <option value="{{ $d['id'] }}">{{ $d['nombre'] }} ({{ $d['rol'] }})</option>
                    @endforeach
                </select>
            </div>
            <div class="tarea-field">
                <label>Prioridad</label>
                <select id="tarea-prio">
                    <option value="normal">Normal</option>
                    <option value="alta">Alta</option>
                    <option value="baja">Baja</option>
                </select>
            </div>
        </div>
        <div class="tarea-field">
            <label>Vence (opcional)</label>
            <input type="datetime-local" id="tarea-vence">
        </div>
        <div class="tarea-actions">
            <button class="btn-action ghost" onclick="cerrarModalTarea()">Cancelar</button>
            <button class="btn-action primary" onclick="guardarTarea()">Crear tarea</button>
        </div>
    </div>
</div>
@endsection
