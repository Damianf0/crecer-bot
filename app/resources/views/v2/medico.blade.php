@extends('layouts.v2')

{{-- Mi consultorio en el shell V2. Reusa los endpoints de producción /medico/*
     (data por polling cada 6s + llamar/rellamar/atendido + crear tarea). El
     llamador (TV) sincroniza por la columna cola_atencion.llamado_consultorio_at,
     así que llamar desde acá funciona igual que desde la UI vieja. --}}

@push('styles')
<style>
.med-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 16px; align-items: start; padding: 18px; overflow-y: auto; }
@media (max-width: 1100px) { .med-wrap { grid-template-columns: 1fr; } }

.med-header {
    grid-column: 1 / -1;
    background: var(--v2-bg-card); border: 1px solid var(--v2-border); border-radius: var(--v2-radius);
    padding: 14px 18px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
}
.med-name { font-size: 18px; font-weight: 700; }
.med-spec { font-size: 12px; color: var(--v2-text-mute); margin-top: 2px; }
.med-stats { display: flex; gap: 12px; margin-left: auto; flex-wrap: wrap; }
.med-stat {
    background: var(--v2-bg-app); border: 1px solid var(--v2-border); border-radius: var(--v2-radius-sm);
    padding: 6px 14px; min-width: 88px; text-align: center;
}
.med-stat-num { font-size: 20px; font-weight: 700; line-height: 1; }
.med-stat-lbl { font-size: 10px; color: var(--v2-text-mute); text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; }

.med-section {
    background: var(--v2-bg-card); border: 1px solid var(--v2-border); border-radius: var(--v2-radius);
    padding: 16px; margin-bottom: 14px;
}
.med-section-title {
    font-size: 11px; font-weight: 700; color: var(--v2-text-mute); text-transform: uppercase;
    letter-spacing: .5px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
}
.med-count { background: var(--v2-bg-app); color: var(--v2-text); border-radius: 10px; padding: 1px 8px; font-size: 10px; font-weight: 700; }

.pac-card {
    background: var(--v2-bg-app); border: 1px solid var(--v2-border); border-radius: var(--v2-radius-sm);
    padding: 12px 14px; margin-bottom: 8px;
}
.pac-card:last-child { margin-bottom: 0; }
.pac-card.llamado { border-color: var(--v2-info); background: var(--v2-info-bg); }
.pac-card.atendido { opacity: .55; }
.pac-head { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; flex-wrap: wrap; }
.pac-name { font-size: 15px; font-weight: 700; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pac-meta-row { display: flex; gap: 8px; flex-wrap: wrap; font-size: 12px; color: var(--v2-text-mute); margin-bottom: 8px; }
.pac-meta-row b { color: var(--v2-text); font-weight: 600; }
.pac-flags { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 8px; }
.pac-flag { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: .3px; }
.flag-prim   { background: var(--v2-warn-bg); color: var(--v2-warn); }
.flag-sint   { background: var(--v2-urg-bg);  color: var(--v2-urg); }
.flag-wapp   { background: var(--v2-ok-bg);   color: var(--v2-ok); }
.flag-espera { background: var(--v2-warn-bg); color: var(--v2-warn); }
.flag-info   { background: var(--v2-info-bg); color: var(--v2-info); }

.pac-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.pac-consultorio-input {
    width: 60px; padding: 6px 8px; border-radius: var(--v2-radius-sm); font-size: 12px;
    border: 1px solid var(--v2-border); background: var(--v2-bg-card); color: var(--v2-text); text-align: center;
}
.med-empty { text-align: center; padding: 26px 20px; color: var(--v2-text-mute); font-size: 13px; }

.med-agenda-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.med-agenda-table th { text-align: left; font-size: 10px; color: var(--v2-text-mute); text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--v2-border); padding: 6px 8px; }
.med-agenda-table td { padding: 6px 8px; border-bottom: 1px solid var(--v2-border); }
.med-1ravez { font-size: 9px; background: var(--v2-info-bg); color: var(--v2-info); padding: 1px 5px; border-radius: 3px; margin-left: 4px; text-transform: uppercase; font-weight: 700; }

/* Atajos de chat a secretarias */
.chat-side { background: var(--v2-bg-card); border: 1px solid var(--v2-border); border-radius: var(--v2-radius); padding: 16px; display: flex; flex-direction: column; gap: 8px; }
.chat-shortcut { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: var(--v2-bg-app); border: 1px solid var(--v2-border); border-radius: var(--v2-radius-sm); cursor: pointer; font-size: 13px; }
.chat-shortcut:hover { border-color: var(--v2-accent); }
.chat-shortcut .av { width: 26px; height: 26px; border-radius: 50%; background: var(--v2-accent-solid); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.chat-shortcut .who { flex: 1; min-width: 0; }
.chat-shortcut .who-name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.chat-shortcut .who-sub  { font-size: 11px; color: var(--v2-text-mute); margin-top: 1px; }
.chat-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.chat-dot.on  { background: var(--v2-ok); }
.chat-dot.off { background: var(--v2-text-mute); opacity: .5; }

/* Modal nueva tarea */
.med-modal-bg { display: none; position: fixed; inset: 0; z-index: 6000; background: rgba(0,0,0,.55); align-items: center; justify-content: center; padding: 20px; }
.med-modal-bg.open { display: flex; }
.med-modal { background: var(--v2-bg-card); border: 1px solid var(--v2-border); border-radius: var(--v2-radius); width: 100%; max-width: 520px; padding: 22px; box-shadow: 0 12px 40px rgba(0,0,0,.4); }
.med-modal h3 { font-size: 16px; font-weight: 700; margin-bottom: 14px; }
.med-field { margin-bottom: 10px; }
.med-field label { display: block; font-size: 11px; font-weight: 600; color: var(--v2-text-mute); text-transform: uppercase; letter-spacing: .3px; margin-bottom: 4px; }
.med-field input, .med-field textarea, .med-field select {
    width: 100%; padding: 9px 11px; border: 1px solid var(--v2-border); border-radius: var(--v2-radius-sm);
    background: var(--v2-bg-app); color: var(--v2-text); font-size: 13px; font-family: inherit;
}
.med-field input:focus, .med-field textarea:focus, .med-field select:focus { outline: none; border-color: var(--v2-accent); }
.med-field textarea { min-height: 76px; resize: vertical; }
.med-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.med-modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 14px; }
</style>
@endpush

@section('content')
@php
    $tienePrefijo = (bool) preg_match('/^Dr[ae\/]?\.?\s/i', $medico->nombre_completo);
@endphp
<div class="med-wrap">
    <div class="med-header">
        <div>
            <div class="med-name">{{ $tienePrefijo ? '' : 'Dr/a. ' }}{{ $medico->nombre_completo }}</div>
            <div class="med-spec">
                {{ $medico->especialidad ?: 'Sin especialidad cargada' }}
                @if($medico->consultorio)
                    · Consultorio {{ $medico->consultorio }}@if($medico->planta) (planta {{ $medico->planta }})@endif
                @endif
            </div>
        </div>
        <div class="med-stats">
            <div class="med-stat"><div class="med-stat-num" id="stat-sala">0</div><div class="med-stat-lbl">En sala</div></div>
            <div class="med-stat"><div class="med-stat-num" id="stat-llamados">0</div><div class="med-stat-lbl">Llamados</div></div>
            <div class="med-stat"><div class="med-stat-num" id="stat-atendidos">0</div><div class="med-stat-lbl">Atendidos hoy</div></div>
        </div>
    </div>

    <div>
        @if(!empty($medico->omnia_id))
        <div class="med-section">
            <div class="med-section-title">
                🗓️ Mi agenda de hoy (Omnia)
                <span class="med-count" id="cnt-agenda">0</span>
                <span style="margin-left:auto;font-size:10px;color:var(--v2-text-mute);font-weight:400;text-transform:none;letter-spacing:0;">solo pendientes · actualiza cada 60s</span>
            </div>
            <div id="lista-agenda"><div class="med-empty">Cargando…</div></div>
        </div>
        @endif

        <div class="med-section">
            <div class="med-section-title">🪑 En sala de espera <span class="med-count" id="cnt-sala">0</span></div>
            <div id="lista-sala"><div class="med-empty">Cargando…</div></div>
        </div>

        <div class="med-section">
            <div class="med-section-title">✓ Llamados / Atendidos hoy <span class="med-count" id="cnt-llamados">0</span></div>
            <div id="lista-llamados"><div class="med-empty">Cargando…</div></div>
        </div>

        <div class="med-section">
            <div class="med-section-title">
                📋 Tareas que delegué <span class="med-count" id="cnt-tareas">0</span>
                <button class="v2-btn primary" onclick="abrirModalTarea()" style="margin-left:auto;">+ Nueva tarea</button>
            </div>
            <div id="lista-tareas"><div class="med-empty">Cargando…</div></div>
        </div>
    </div>

    <aside class="chat-side">
        <div class="med-section-title" style="margin-bottom:4px;">💬 Comunicarme con</div>
        <div id="chat-shortcuts"><div class="med-empty" style="padding:18px;">Cargando…</div></div>
        <div style="font-size:11px;color:var(--v2-text-mute);text-align:center;border-top:1px solid var(--v2-border);padding-top:8px;">
            Hacé clic en un nombre para chatear. El historial queda guardado.
        </div>
    </aside>
</div>

{{-- Modal nueva tarea --}}
<div class="med-modal-bg" id="tarea-modal" onclick="if(event.target===this) cerrarModalTarea()">
    <div class="med-modal">
        <h3>Encargar tarea a una secretaria</h3>
        <div class="med-field">
            <label>Título *</label>
            <input type="text" id="tarea-titulo" placeholder="Llamar a paciente Pérez para confirmar turno">
        </div>
        <div class="med-field">
            <label>Detalle</label>
            <textarea id="tarea-desc" placeholder="Información adicional, contacto, urgencia…"></textarea>
        </div>
        <div class="med-row-2">
            <div class="med-field">
                <label>Asignar a *</label>
                <select id="tarea-dest">
                    @foreach($destinatarios as $d)
                        <option value="{{ $d['id'] }}">{{ $d['nombre'] }} ({{ $d['rol'] }})</option>
                    @endforeach
                </select>
            </div>
            <div class="med-field">
                <label>Prioridad</label>
                <select id="tarea-prio">
                    <option value="normal">Normal</option>
                    <option value="alta">Alta</option>
                    <option value="baja">Baja</option>
                </select>
            </div>
        </div>
        <div class="med-field">
            <label>Vence (opcional)</label>
            <input type="datetime-local" id="tarea-vence">
        </div>
        <div class="med-modal-actions">
            <button class="v2-btn" onclick="cerrarModalTarea()">Cancelar</button>
            <button class="v2-btn primary" onclick="guardarTarea()">Crear tarea</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const $ = (id) => document.getElementById(id);
const DEFAULT_CONSULTORIO = @json($medico->consultorio);
const DESTINATARIOS = @json($destinatarios);

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// Token CRUDO de la sesión (meta del layout). NO usar la cookie XSRF-TOKEN (viene cifrada → 419).
async function call(url, opts = {}) {
    const tok = document.querySelector('meta[name=csrf-token]')?.content ?? '';
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
        const det = d.errors ? Object.values(d.errors).flat().join('\n') : (d.message || '');
        return { ok: false, _err: 'Datos inválidos: ' + det };
    }
    if (r.status === 403) return { ok: false, _err: 'No tenés permiso: ' + (d.error || d.message || '403') };
    if (!r.ok) return { ok: false, _err: d.error || d.message || ('HTTP ' + r.status) };
    return d;
}

// ── Render: en sala ──────────────────────────────────────────────────
function renderSala(items) {
    const c = $('lista-sala');
    $('cnt-sala').textContent = items.length;
    $('stat-sala').textContent = items.length;
    if (!items.length) {
        c.innerHTML = '<div class="med-empty">Sin pacientes en sala. La secretaria los libera acá cuando terminan el check-in.</div>';
        return;
    }
    c.innerHTML = items.map(p => `
        <div class="pac-card" data-id="${p.id}">
            <div class="pac-head">
                <div class="pac-name">${esc(p.nombre)}</div>
                <span class="pac-flag flag-info">DNI ${esc(p.dni)}</span>
            </div>
            <div class="pac-flags">
                ${p.primera_vez  ? '<span class="pac-flag flag-prim">Primera vez</span>' : ''}
                ${p.sin_turno    ? '<span class="pac-flag flag-sint">Sin turno</span>' : ''}
                ${p.derivado_bot ? '<span class="pac-flag flag-wapp">WhatsApp</span>' : ''}
                ${p.minutos_espera > 20 ? `<span class="pac-flag flag-espera">Espera ${p.minutos_espera}m</span>` : ''}
            </div>
            <div class="pac-meta-row">
                ${p.turno_hora  ? `<span>Turno <b>${esc(p.turno_hora)}</b></span>` : ''}
                ${p.practica    ? `<span>· ${esc(p.practica)}</span>` : ''}
                ${p.obra_social ? `<span>· ${esc(p.obra_social)}</span>` : ''}
            </div>
            ${p.nota ? `<div style="font-size:12px;color:var(--v2-text-mute);font-style:italic;margin-bottom:8px;">📝 ${esc(p.nota)}</div>` : ''}
            <div class="pac-actions">
                <input type="number" min="1" max="99" class="pac-consultorio-input" id="consult-${p.id}"
                       placeholder="Box" value="${p.consultorio ?? DEFAULT_CONSULTORIO ?? ''}">
                <button class="v2-btn primary" onclick="llamar(${p.id})">Llamar al consultorio</button>
            </div>
        </div>`).join('');
}

// ── Render: llamados / atendidos ─────────────────────────────────────
function renderLlamados(items) {
    const c = $('lista-llamados');
    $('cnt-llamados').textContent = items.length;
    const sinAtender = items.filter(p => !p.atendido_at).length;
    const atendidos  = items.filter(p =>  p.atendido_at).length;
    $('stat-llamados').textContent  = sinAtender;
    $('stat-atendidos').textContent = atendidos;
    if (!items.length) {
        c.innerHTML = '<div class="med-empty">Todavía no llamaste a nadie hoy.</div>';
        return;
    }
    c.innerHTML = items.map(p => `
        <div class="pac-card ${p.atendido_at ? 'atendido' : 'llamado'}" data-id="${p.id}">
            <div class="pac-head">
                <div class="pac-name">${esc(p.nombre)}</div>
                <span class="pac-flag flag-info">Consultorio ${p.consultorio ?? '—'}</span>
                ${p.atendido_at
                    ? `<span class="pac-flag flag-wapp">Atendido ${esc(p.atendido_at)}</span>`
                    : `<span class="pac-flag flag-info">Llamado ${esc(p.llamado_at)}</span>`}
            </div>
            <div class="pac-meta-row">
                <span>DNI <b>${esc(p.dni)}</b></span>
                ${p.practica ? `<span>· ${esc(p.practica)}</span>` : ''}
            </div>
            ${!p.atendido_at ? `
                <div class="pac-actions">
                    <button class="v2-btn" onclick="rellamar(${p.id})">↻ Re-llamar</button>
                    <button class="v2-btn accent" onclick="atendido(${p.id})">✓ Marcar atendido</button>
                </div>` : ''}
        </div>`).join('');
}

// ── Render: agenda Omnia ─────────────────────────────────────────────
function renderAgenda(items) {
    const wrap = $('lista-agenda');
    if (!wrap) return;
    const cnt = $('cnt-agenda');
    if (cnt) cnt.textContent = items.length;
    if (!items.length) { wrap.innerHTML = '<div class="med-empty">Sin turnos pendientes hoy.</div>'; return; }
    const rows = items.map(t => `
        <tr>
            <td style="font-weight:700;">${esc(t.hora)}</td>
            <td>${esc(t.paciente)}${t.primera_vez ? '<span class="med-1ravez">1ra vez</span>' : ''}</td>
            <td style="color:var(--v2-text-mute);">${esc(t.dni || '—')}</td>
            <td>${esc(t.practica || t.servicio || '')}</td>
            <td style="color:var(--v2-text-mute);">${esc(t.obra_social || '—')}</td>
        </tr>`).join('');
    wrap.innerHTML = `<table class="med-agenda-table">
        <thead><tr><th style="width:60px;">Hora</th><th>Paciente</th><th style="width:100px;">DNI</th><th>Práctica</th><th style="width:90px;">Obra Social</th></tr></thead>
        <tbody>${rows}</tbody></table>`;
}

// ── Render: tareas delegadas ─────────────────────────────────────────
function renderTareas(items) {
    const c = $('lista-tareas');
    $('cnt-tareas').textContent = items.length;
    if (!items.length) { c.innerHTML = '<div class="med-empty">Todavía no encargaste ninguna tarea.</div>'; return; }
    c.innerHTML = items.map(t => `
        <div class="pac-card ${t.completada ? 'atendido' : (t.vencida ? 'llamado' : '')}" data-id="${t.id}">
            <div class="pac-head">
                <div class="pac-name">${esc(t.titulo)}</div>
                ${t.completada ? '<span class="pac-flag flag-wapp">Completada</span>'
                  : t.vencida ? '<span class="pac-flag flag-sint">Vencida</span>'
                  : (t.prioridad === 'alta' ? '<span class="pac-flag flag-prim">Alta</span>' : '')}
                ${t.estado === 'en_progreso' ? '<span class="pac-flag flag-info">En progreso</span>' : ''}
            </div>
            <div class="pac-meta-row">
                <span>Para: <b>${esc(t.asignada_a_nombre || '—')}</b></span>
                ${t.vence_at ? `<span>· vence ${esc(t.vence_at)}</span>` : ''}
                <span>· ${esc(t.creada)}</span>
            </div>
            ${t.descripcion ? `<div style="font-size:12px;color:var(--v2-text-mute);margin-top:4px;">${esc(t.descripcion)}</div>` : ''}
        </div>`).join('');
}

// ── Acciones ─────────────────────────────────────────────────────────
async function llamar(id) {
    const consultorio = parseInt($(`consult-${id}`).value) || null;
    if (!consultorio) { v2toast('Indicá el número de consultorio.', 'err'); return; }
    const j = await call(`/medico/${id}/llamar`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ consultorio }),
    });
    if (!j.ok) { v2toast('No se pudo llamar: ' + (j._err || 'error'), 'err'); return; }
    v2toast('Paciente llamado al consultorio ' + consultorio, 'ok');
    refrescar();
}

async function rellamar(id) {
    const j = await call(`/medico/${id}/rellamar`, { method: 'POST' });
    if (!j.ok) { v2toast('No se pudo re-llamar: ' + (j._err || 'error'), 'err'); return; }
    v2toast('Re-llamado', 'ok');
    refrescar();
}

async function atendido(id) {
    if (!confirm('¿Marcar este paciente como atendido?')) return;
    const j = await call(`/medico/${id}/atendido`, { method: 'POST' });
    if (!j.ok) { v2toast('No se pudo marcar como atendido: ' + (j._err || 'error'), 'err'); return; }
    v2toast('Marcado como atendido', 'ok');
    refrescar();
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
function cerrarModalTarea() { $('tarea-modal').classList.remove('open'); }

async function guardarTarea() {
    const body = {
        titulo:      $('tarea-titulo').value.trim(),
        descripcion: $('tarea-desc').value.trim() || null,
        asignada_a:  parseInt($('tarea-dest').value) || null,
        prioridad:   $('tarea-prio').value,
        vence_at:    $('tarea-vence').value || null,
    };
    if (!body.titulo)     { v2toast('Poné un título.', 'err'); return; }
    if (!body.asignada_a) { v2toast('Elegí a quién asignársela.', 'err'); return; }
    const j = await call('/medico/tareas', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    if (!j.ok) { v2toast('No se pudo crear: ' + (j._err || 'error'), 'err'); return; }
    cerrarModalTarea();
    v2toast('Tarea creada', 'ok');
    refrescar();
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && $('tarea-modal').classList.contains('open')) cerrarModalTarea();
});

// ── Atajos de chat a secretarias ─────────────────────────────────────
async function cargarShortcuts() {
    try {
        const r = await fetch('/chat/usuarios', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const j = await r.json();
        const users = j.data || [];
        const c = $('chat-shortcuts');
        if (!users.length) { c.innerHTML = '<div class="med-empty" style="padding:14px;">No hay otros usuarios cargados.</div>'; return; }
        c.innerHTML = users.map(u => `
            <div class="chat-shortcut" onclick="abrirDM(${u.id})">
                <div class="chat-dot ${u.online ? 'on' : 'off'}"></div>
                <div class="av">${esc((u.nombre_completo || '?')[0].toUpperCase())}</div>
                <div class="who">
                    <div class="who-name">${esc(u.nombre_completo || '—')}</div>
                    <div class="who-sub">${u.online ? 'En línea' : 'Sin conexión'}</div>
                </div>
            </div>`).join('');
    } catch (e) { /* silent */ }
}
function abrirDM(userId) {
    if (window.ChatWidget?.crearDm) {
        const panel = document.getElementById('chat-panel');
        if (panel && !panel.classList.contains('open')) window.ChatWidget.toggle();
        window.ChatWidget.crearDm(userId);
    }
}

// ── Polling ──────────────────────────────────────────────────────────
async function refrescar() {
    const j = await call('/medico/data');
    if (!j.ok) return;
    renderSala(j.en_sala || []);
    renderLlamados(j.llamados || []);
    renderTareas(j.tareas_delegadas || []);
    renderAgenda(j.agenda || []);
}

refrescar();
cargarShortcuts();
setInterval(refrescar, 6000);
setInterval(cargarShortcuts, 12000);
</script>
@endpush
