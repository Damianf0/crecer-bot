@extends('layouts.v2')
@section('title', 'Contactos')

{{-- PoC V2 — directorio de contactos con el MISMO funcionamiento que
     /contactos de producción: búsqueda debounced + spinner, tabla con
     Ver más, alta/edición en modal (avatar con lightbox, wa_id read-only),
     eliminar, importación CSV de Omnia en 3 pasos, e iniciar chat WA con
     plantillas. Mismos endpoints; solo cambia la piel al shell V2. --}}

@push('styles')
<style>
.c-avatar { width:32px; height:32px; border-radius:50%; object-fit:cover; background:var(--v2-bg-hover);
            display:inline-flex; align-items:center; justify-content:center; font-weight:700; color:var(--v2-text-2);
            font-size:13px; border:1px solid var(--v2-border); vertical-align:middle; margin-right:10px; cursor:pointer;
            flex-shrink:0; transition:transform .12s; }
.c-avatar:hover { transform:scale(1.08); border-color:var(--v2-accent); }
.c-avatar-big { width:110px; height:110px; border-radius:50%; object-fit:cover; background:var(--v2-bg-hover);
                display:inline-flex; align-items:center; justify-content:center; font-weight:700; color:var(--v2-text-2);
                font-size:42px; border:1px solid var(--v2-border); cursor:zoom-in; }
.c-avatar-big.no-photo { cursor:default; }
.c-zoom { position:fixed; inset:0; background:rgba(0,0,0,.85); display:none; align-items:center; justify-content:center; z-index:9999; cursor:zoom-out; }
.c-zoom img { max-width:90vw; max-height:90vh; border-radius:10px; box-shadow:0 16px 48px rgba(0,0,0,.5); }
.c-mini-badge { margin-left:5px; font-size:10px; border-radius:4px; padding:1px 5px; font-weight:600; }
.import-step { flex:1; text-align:center; font-size:12px; font-weight:600; padding:6px; border-bottom:2px solid var(--v2-border); color:var(--v2-text-mute); }
.import-step.on { border-bottom-color:var(--v2-accent); color:var(--v2-accent); }
.import-chip { background:var(--v2-bg-app); border:1px solid var(--v2-border); border-radius:var(--v2-radius-sm); padding:8px 14px; text-align:center; }
.import-chip .n { font-size:20px; font-weight:700; }
.import-chip .l { font-size:11px; color:var(--v2-text-mute); }
</style>
@endpush

@section('content')
<div style="flex:1;overflow-y:auto;padding:20px 24px;">
<div style="max-width:980px;margin:0 auto;">

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
        <h1 style="font-size:17px;font-weight:650;margin:0;">Directorio de contactos</h1>
        <button class="v2-btn" style="margin-left:auto;" onclick="abrirImport()">↑ Importar CSV</button>
        <button class="v2-btn primary" onclick="abrirForm()">+ Agregar</button>
    </div>

    <div style="position:relative;margin-bottom:8px;">
        <input id="buscar" class="v2-field" placeholder="Buscar por nombre, teléfono, DNI o ID WhatsApp…"
               oninput="cargarDebounced()" style="padding-right:38px;">
        <span id="buscar-spinner" style="display:none;position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--v2-text-mute);">…</span>
    </div>
    <div id="info-listado" style="font-size:12px;color:var(--v2-text-mute);margin-bottom:10px;min-height:18px;">—</div>

    <div id="lista"></div>

    <div id="ver-mas-row" style="display:none;text-align:center;padding:14px 0;">
        <button class="v2-btn" id="btn-ver-mas" onclick="cargarMas()">Ver más contactos</button>
    </div>
</div>
</div>

{{-- Lightbox del avatar --}}
<div id="avatar-zoom" class="c-zoom" onclick="this.style.display='none'"><img id="avatar-zoom-img" alt=""></div>

{{-- Modal contacto (crear/editar) --}}
<dialog class="v2-dialog" id="modal-contacto" style="width:min(440px,calc(100vw - 40px));">
    <h3 id="modal-titulo">Agregar contacto</h3>

    <div id="modal-avatar-row" style="display:none;text-align:center;margin:6px 0 14px;">
        <div id="modal-avatar"></div>
    </div>

    <label class="v2-label" style="margin-top:4px;">Datos de contacto</label>
    <input id="f-telefono" class="v2-field" placeholder="Teléfono WhatsApp (solo números, opcional)" style="margin-bottom:8px;">
    <input id="f-nombre" class="v2-field" placeholder="Nombre completo *" style="margin-bottom:8px;">
    <input id="f-email" class="v2-field" placeholder="Email (opcional)">

    <label class="v2-label">Datos Omnia</label>
    <div class="v2-grid2" style="margin-bottom:8px;">
        <input id="f-dni" class="v2-field" placeholder="DNI">
        <input id="f-fecha" class="v2-field" type="date">
    </div>
    <input id="f-omnia-id" class="v2-field" placeholder="ID paciente Omnia (opcional)">

    <label class="v2-label">Notas</label>
    <textarea id="f-notas" class="v2-field" placeholder="Notas internas (opcional)" rows="2" style="resize:none;"></textarea>

    <div id="f-waid-row" style="display:none;font-size:11px;color:var(--v2-text-mute);background:var(--v2-bg-app);border:1px solid var(--v2-border);border-radius:var(--v2-radius-sm);padding:8px 11px;margin-top:10px;font-family:'JetBrains Mono',monospace;">
        <span style="text-transform:uppercase;letter-spacing:.5px;">ID WhatsApp:</span>
        <span id="f-waid-val" style="margin-left:6px;color:var(--v2-text);"></span>
        <span id="f-waid-tipo" class="v2-pill" style="margin-left:8px;font-size:10px;"></span>
    </div>

    <div id="form-error" style="color:var(--v2-urg);font-size:12px;margin-top:10px;display:none;"></div>
    <div class="v2-dialog-foot">
        <button class="v2-btn" onclick="cerrarForm()">Cancelar</button>
        <button class="v2-btn primary" onclick="guardar()">Guardar</button>
    </div>
</dialog>

{{-- Modal importar CSV (3 pasos) --}}
<dialog class="v2-dialog" id="modal-import" style="width:min(880px,calc(100vw - 40px));max-height:93vh;display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;">Importar contactos desde CSV de Omnia</h3>
        <button onclick="cerrarImport()" style="margin-left:auto;background:none;border:none;color:var(--v2-text-mute);font-size:20px;cursor:pointer;line-height:1;">×</button>
    </div>

    <div style="display:flex;gap:0;margin-bottom:18px;">
        <div id="paso-ind-1" class="import-step on">1. Archivo</div>
        <div id="paso-ind-2" class="import-step">2. Revisar</div>
        <div id="paso-ind-3" class="import-step">3. Resultado</div>
    </div>

    <div style="flex:1;overflow-y:auto;">
        {{-- Paso 1 --}}
        <div id="paso-1">
            <p style="font-size:13px;color:var(--v2-text-2);margin-bottom:14px;">
                Exportá el listado de pacientes desde Omnia en formato CSV y subilo acá.
                El archivo debe estar en formato <strong>CSV (separado por comas)</strong>, tal como lo exporta Omnia.
            </p>
            <div style="background:var(--v2-bg-app);border:1px solid var(--v2-border);border-radius:var(--v2-radius-sm);padding:12px 14px;margin-bottom:14px;font-size:12px;color:var(--v2-text-mute);">
                <div style="font-weight:600;color:var(--v2-text);margin-bottom:5px;">Columnas esperadas del CSV de Omnia:</div>
                ID · Apellido paterno · Apellido materno · Nombre · Otros nombres · Número de documento · Celular · Teléfono · Email · Fecha nacimiento · Obra social del paciente
            </div>
            <label class="v2-label" style="margin-top:0;">Archivo CSV</label>
            <input type="file" id="import-archivo" accept=".csv" class="v2-field" style="margin-bottom:14px;">
            <div id="import-error-1" style="color:var(--v2-urg);font-size:12px;margin-bottom:10px;display:none;"></div>
            <div style="display:flex;justify-content:flex-end;">
                <button class="v2-btn primary" onclick="previsualizarCSV()">Previsualizar →</button>
            </div>
        </div>

        {{-- Paso 2 --}}
        <div id="paso-2" style="display:none;">
            <div id="import-stats" style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;"></div>
            <div style="font-size:12px;color:var(--v2-text-mute);margin-bottom:10px;">
                Marcá los contactos que querés importar. Los que tienen conflictos están desmarcados por defecto.
            </div>
            <div style="margin-bottom:10px;display:flex;gap:10px;align-items:center;">
                <label style="font-size:12px;cursor:pointer;display:flex;align-items:center;gap:5px;">
                    <input type="checkbox" id="selec-todos" onchange="toggleTodos(this.checked)"> Seleccionar todos sin conflicto
                </label>
                <div style="margin-left:auto;display:flex;gap:8px;align-items:center;font-size:12px;color:var(--v2-text-2);">
                    <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:var(--v2-urg-bg);display:inline-block;border:1px solid var(--v2-urg);"></span>Conflicto</span>
                    <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:var(--v2-warn-bg);display:inline-block;border:1px solid var(--v2-warn);"></span>Sin teléfono</span>
                </div>
            </div>
            <div style="overflow-x:auto;max-height:380px;overflow-y:auto;border:1px solid var(--v2-border);border-radius:var(--v2-radius-sm);">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead style="position:sticky;top:0;background:var(--v2-bg-card);z-index:1;">
                        <tr style="font-size:10.5px;color:var(--v2-text-mute);text-transform:uppercase;letter-spacing:.5px;">
                            <th style="padding:8px 10px;text-align:center;border-bottom:1px solid var(--v2-border);width:36px;"></th>
                            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--v2-border);">Nombre</th>
                            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--v2-border);">Teléfono</th>
                            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--v2-border);">DNI</th>
                            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--v2-border);">Nacimiento</th>
                            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--v2-border);">Conflicto</th>
                        </tr>
                    </thead>
                    <tbody id="import-tbody"></tbody>
                </table>
            </div>
            <div id="import-error-2" style="color:var(--v2-urg);font-size:12px;margin-top:10px;display:none;"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
                <button class="v2-btn" onclick="irPaso(1)">← Volver</button>
                <button class="v2-btn primary" onclick="confirmarImport()">Importar seleccionados</button>
            </div>
        </div>

        {{-- Paso 3 --}}
        <div id="paso-3" style="display:none;text-align:center;padding:30px 0;">
            <div id="import-resultado"></div>
            <button class="v2-btn primary" style="margin-top:20px;" onclick="cerrarImport()">Cerrar</button>
        </div>
    </div>
</dialog>

{{-- Modal: iniciar chat WhatsApp --}}
<dialog class="v2-dialog" id="modal-chat" style="width:min(500px,calc(100vw - 40px));">
    <h3>Chatear con <span id="chat-nombre">—</span></h3>
    <div style="font-size:12px;color:var(--v2-text-mute);margin-bottom:8px;">Teléfono: <span id="chat-tel" style="font-family:'JetBrains Mono',monospace;">—</span></div>

    <label class="v2-label">Plantilla rápida</label>
    <select id="chat-plantilla" class="v2-field" onchange="aplicarPlantillaChat(this.value)">
        <option value="0">En blanco</option>
        <option value="1">Recordatorio de turno</option>
        <option value="2">Confirmar receta lista</option>
        <option value="3">Solicitar muestra</option>
        <option value="4">Pedido de información</option>
    </select>

    <label class="v2-label">Mensaje inicial</label>
    <textarea id="chat-texto" class="v2-field" placeholder="Escribí el primer mensaje…" style="min-height:100px;resize:vertical;"></textarea>

    <div class="v2-dialog-foot">
        <button class="v2-btn" onclick="cerrarChat()">Cancelar</button>
        <button class="v2-btn primary" id="chat-enviar" onclick="enviarChat()">Iniciar y enviar</button>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const { esc } = V2;

// api local: igual que producción, tira el JSON de error parseado (para
// mostrar e.errors de validación en el form).
async function api(method, url, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(url, opts);
    if (!r.ok) throw await r.json().catch(() => ({ message: r.status }));
    return r.json();
}

function inicial(nombre) {
    const m = String(nombre ?? '').trim().match(/[A-Za-zÁÉÍÓÚÑáéíóúñ]/);
    return m ? m[0].toUpperCase() : '?';
}

function fechaCorta(iso) {
    if (!iso) return '—';
    const m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : esc(iso);
}

function avatarMiniHtml(c) {
    if (c.avatar_url) {
        return `<img src="${esc(c.avatar_url)}" class="c-avatar" alt="" onclick='event.stopPropagation();abrirForm(${JSON.stringify(c)})' title="Ver ficha">`;
    }
    return `<div class="c-avatar" onclick='event.stopPropagation();abrirForm(${JSON.stringify(c)})' title="Ver ficha">${inicial(c.nombre)}</div>`;
}

function avatarBigHtml(c) {
    if (c.avatar_url) {
        return `<img src="${esc(c.avatar_url)}" class="c-avatar-big" alt="" onclick="verAvatarGrande('${esc(c.avatar_url)}')" title="Click para ver más grande">`;
    }
    return `<div class="c-avatar-big no-photo" title="Sin foto de perfil">${inicial(c.nombre)}</div>`;
}

function verAvatarGrande(url) {
    document.getElementById('avatar-zoom-img').src = url;
    document.getElementById('avatar-zoom').style.display = 'flex';
}

// ── Listado: búsqueda debounced + paginación "Ver más" ───────────
let _pageActual = 1;
let _qActual    = '';
let _paginando  = false;
let _debounceTimer = null;
let _reqSeq    = 0;

function cargarDebounced() {
    clearTimeout(_debounceTimer);
    document.getElementById('buscar-spinner').style.display = 'inline';
    _debounceTimer = setTimeout(() => cargar(true), 250);
}

async function cargar(reset = true) {
    if (!reset && _paginando) return;
    if (!reset) _paginando = true;
    const seq = ++_reqSeq;

    const q = document.getElementById('buscar').value.trim();
    if (reset) { _pageActual = 1; _qActual = q; }

    document.getElementById('buscar-spinner').style.display = 'inline';
    document.getElementById('btn-ver-mas')?.setAttribute('disabled', '');

    try {
        const r = await api('GET', `/contactos/data?q=${encodeURIComponent(_qActual)}&page=${_pageActual}`);
        if (seq !== _reqSeq) return;
        renderListado(r, reset);
    } finally {
        if (!reset) _paginando = false;
        if (seq === _reqSeq) document.getElementById('buscar-spinner').style.display = 'none';
        document.getElementById('btn-ver-mas')?.removeAttribute('disabled');
    }
}

async function cargarMas() {
    _pageActual++;
    await cargar(false);
}

function renderListado({ data, total, has_more, page, per_page }, reset) {
    const lista  = document.getElementById('lista');
    const info   = document.getElementById('info-listado');
    const verMas = document.getElementById('ver-mas-row');

    if (total === 0) {
        info.textContent = _qActual ? `Sin resultados para "${_qActual}"` : 'No hay contactos cargados';
    } else if (total <= per_page) {
        info.textContent = `${total} contacto${total !== 1 ? 's' : ''}`;
    } else {
        const mostrados = Math.min(page * per_page, total);
        info.textContent = `Mostrando ${mostrados} de ${total}` + (_qActual ? ` para "${_qActual}"` : '');
    }

    if (!data.length && reset) {
        lista.innerHTML = '<div class="v2-empty" style="background:var(--v2-bg-card);border:1px solid var(--v2-border);border-radius:var(--v2-radius);"><span class="ico">🔍</span>Sin contactos</div>';
        verMas.style.display = 'none';
        return;
    }

    const filasHtml = data.map(c => filaHtml(c)).join('');

    if (reset) {
        lista.innerHTML = `<table class="v2-table">
            <thead><tr>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>DNI</th>
                <th>Nacimiento</th>
                <th>Email</th>
                <th></th>
            </tr></thead>
            <tbody id="lista-tbody">${filasHtml}</tbody></table>`;
    } else {
        const tbody = document.getElementById('lista-tbody');
        if (tbody) tbody.insertAdjacentHTML('beforeend', filasHtml);
    }

    verMas.style.display = has_more ? 'block' : 'none';
}

function filaHtml(c) {
    return `
        <tr class="item-row" id="row-${c.id}">
            <td style="font-weight:600;">
                <div style="display:flex;align-items:center;">
                    ${avatarMiniHtml(c)}
                    <div>
                        ${esc(c.nombre)}
                        ${c.omnia_patient_id ? '<span class="c-mini-badge" title="Vinculado a Omnia" style="background:var(--v2-info-bg);color:var(--v2-info);">Omnia</span>' : ''}
                        ${c.wa_id && c.wa_id.endsWith('@lid') ? '<span class="c-mini-badge" title="WhatsApp identifica con LID (privacidad)" style="background:var(--v2-warn-bg);color:var(--v2-warn);">LID</span>' : ''}
                    </div>
                </div>
            </td>
            <td style="color:var(--v2-text-2);font-family:'JetBrains Mono',monospace;font-size:11.5px;">${esc(c.telefono || '—')}</td>
            <td style="font-family:'JetBrains Mono',monospace;font-size:11.5px;">${esc(c.dni ?? '—')}</td>
            <td style="color:var(--v2-text-2);">${fechaCorta(c.fecha_nacimiento)}</td>
            <td style="color:var(--v2-text-2);">${esc(c.email ?? '—')}</td>
            <td style="text-align:right;white-space:nowrap;">
                <a href="/pacientes/${c.id}/documentos" class="v2-btn sm" style="text-decoration:none;" title="Ver legajo de documentos">📁 Legajo</a>
                ${c.telefono ? `<button class="v2-btn sm accent" onclick='iniciarChat(${JSON.stringify(c)})' title="Iniciar conversación por WhatsApp">Chatear</button>` : ''}
                <button class="v2-btn sm" onclick='abrirForm(${JSON.stringify(c)})' title="Editar">✏️</button>
                <button class="v2-btn sm danger" onclick="eliminar(${c.id})" title="Eliminar">✕</button>
            </td>
        </tr>`;
}

// ── Modal crear/editar ────────────────────────────────────────────
let editId = null;

function abrirForm(c) {
    editId = c?.id ?? null;
    document.getElementById('modal-titulo').textContent = editId ? 'Editar contacto' : 'Agregar contacto';

    const avRow = document.getElementById('modal-avatar-row');
    const avEl  = document.getElementById('modal-avatar');
    if (editId && c) {
        avEl.innerHTML = avatarBigHtml(c);
        avRow.style.display = 'block';
    } else {
        avRow.style.display = 'none';
    }

    document.getElementById('f-telefono').value = c?.telefono ?? '';
    document.getElementById('f-nombre').value   = c?.nombre ?? '';
    document.getElementById('f-email').value    = c?.email ?? '';
    document.getElementById('f-dni').value      = c?.dni ?? '';
    document.getElementById('f-fecha').value    = c?.fecha_nacimiento ? String(c.fecha_nacimiento).slice(0, 10) : '';
    document.getElementById('f-omnia-id').value = c?.omnia_patient_id ?? '';
    document.getElementById('f-notas').value    = c?.notas ?? '';

    const waRow = document.getElementById('f-waid-row');
    if (c?.wa_id) {
        document.getElementById('f-waid-val').textContent = c.wa_id;
        const tipo = document.getElementById('f-waid-tipo');
        const esLid = c.wa_id.endsWith('@lid');
        tipo.textContent = esLid ? 'LID' : 'estándar';
        tipo.className = 'v2-pill ' + (esLid ? 'espera' : 'proceso');
        waRow.style.display = 'block';
    } else {
        waRow.style.display = 'none';
    }

    document.getElementById('form-error').style.display = 'none';
    document.getElementById('modal-contacto').showModal();
    document.getElementById('f-telefono').focus();
}

function cerrarForm() {
    document.getElementById('modal-contacto').close();
    editId = null;
}

async function guardar() {
    const telefono         = document.getElementById('f-telefono').value.replace(/\D/g, '');
    const nombre           = document.getElementById('f-nombre').value.trim();
    const email            = document.getElementById('f-email').value.trim() || null;
    const dni              = document.getElementById('f-dni').value.trim() || null;
    const fecha_nacimiento = document.getElementById('f-fecha').value || null;
    const omnia_patient_id = document.getElementById('f-omnia-id').value.trim() || null;
    const notas            = document.getElementById('f-notas').value.trim() || null;
    const errEl            = document.getElementById('form-error');

    if (!nombre) {
        errEl.textContent = 'El nombre es obligatorio.';
        errEl.style.display = '';
        return;
    }

    const body = { telefono, nombre, email, dni, fecha_nacimiento, omnia_patient_id, notas };

    try {
        if (editId) {
            await api('PATCH', `/contactos/${editId}`, body);
        } else {
            await api('POST', '/contactos', body);
        }
        const fueEdicion = !!editId;
        cerrarForm();
        cargar();
        v2toast(fueEdicion ? 'Contacto actualizado' : 'Contacto agregado');
    } catch (e) {
        const msg = e?.errors ? Object.values(e.errors).flat().join(' ') : (e?.message ?? 'Error');
        errEl.textContent = msg;
        errEl.style.display = '';
    }
}

async function eliminar(id) {
    if (!confirm('¿Eliminar este contacto del directorio?')) return;
    try {
        await api('DELETE', `/contactos/${id}`);
        cargar();
        v2toast('Contacto eliminado');
    } catch (e) { v2toast('No se pudo eliminar', 'err'); }
}

// ── Iniciar chat WA (mismas plantillas que producción) ───────────
const PLANTILLAS_CHAT = [
    '',
    'Hola! Te recordamos tu turno en Crecer Reproducción. Cualquier consulta, escribinos por acá. Saludos!',
    'Hola! Tu receta ya está lista para retirar. Te esperamos en el horario habitual. Saludos!',
    'Hola! Necesitamos coordinar una nueva toma de muestra. ¿Cuándo te queda cómodo pasar? Saludos!',
    'Hola! Soy de Crecer Reproducción. Necesitaríamos consultarte algunos datos. ¿Podés responderme cuando puedas? Gracias!',
];
let _chatContacto = null;

function iniciarChat(c) {
    _chatContacto = c;
    document.getElementById('chat-nombre').textContent = c.nombre;
    document.getElementById('chat-tel').textContent    = c.telefono;
    document.getElementById('chat-texto').value = '';
    document.getElementById('chat-plantilla').value = '0';
    document.getElementById('modal-chat').showModal();
    setTimeout(() => document.getElementById('chat-texto').focus(), 50);
}

function cerrarChat() {
    document.getElementById('modal-chat').close();
    _chatContacto = null;
}

function aplicarPlantillaChat(idx) {
    document.getElementById('chat-texto').value = PLANTILLAS_CHAT[parseInt(idx)] || '';
}

async function enviarChat() {
    const texto = document.getElementById('chat-texto').value.trim();
    if (!texto || !_chatContacto) { v2toast('Falta el mensaje', 'err'); return; }

    const btn = document.getElementById('chat-enviar');
    btn.disabled = true;
    btn.textContent = 'Verificando…';

    try {
        const r = await fetch('/atencion/iniciar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ contacto_id: _chatContacto.id, texto }),
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
            v2toast(data.error || 'No se pudo iniciar', 'err');
            btn.disabled = false; btn.textContent = 'Iniciar y enviar';
            return;
        }
        cerrarChat();
        btn.disabled = false; btn.textContent = 'Iniciar y enviar';
        if (confirm((data.reusada ? 'Conversación reabierta' : 'Mensaje enviado') + '. ¿Ir a la conversación ahora?')) {
            window.location.href = '/v2/atencion';
        } else {
            v2toast('Mensaje enviado');
        }
    } catch (e) {
        v2toast('Error de red', 'err');
        btn.disabled = false; btn.textContent = 'Iniciar y enviar';
    }
}

// ── Importar CSV (3 pasos, igual que producción) ──────────────────
let filasImport = [];

function abrirImport() {
    filasImport = [];
    document.getElementById('import-archivo').value = '';
    document.getElementById('import-error-1').style.display = 'none';
    irPaso(1);
    document.getElementById('modal-import').showModal();
}

function cerrarImport() {
    document.getElementById('modal-import').close();
}

function irPaso(n) {
    [1, 2, 3].forEach(i => {
        document.getElementById(`paso-${i}`).style.display = i === n ? '' : 'none';
        document.getElementById(`paso-ind-${i}`).classList.toggle('on', i === n);
    });
}

async function previsualizarCSV() {
    const archivo = document.getElementById('import-archivo').files[0];
    const errEl   = document.getElementById('import-error-1');
    if (!archivo) {
        errEl.textContent = 'Seleccioná un archivo CSV.';
        errEl.style.display = '';
        return;
    }
    errEl.style.display = 'none';

    const fd = new FormData();
    fd.append('archivo', archivo);
    fd.append('_token', CSRF);

    try {
        const r = await fetch('/contactos/import/preview', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        if (!r.ok) throw await r.json().catch(() => ({ message: r.status }));
        const { filas, stats } = await r.json();

        filasImport = filas;

        const chip = (label, val, color) =>
            `<div class="import-chip"><div class="n" style="color:${color};">${val}</div><div class="l">${label}</div></div>`;
        document.getElementById('import-stats').innerHTML =
            chip('Total', stats.total, 'var(--v2-text)') +
            chip('Listos', stats.ok, 'var(--v2-ok)') +
            chip('Tel. a revisar', stats.tel_warn ?? 0, 'var(--v2-warn)') +
            chip('DNI a revisar', stats.dni_warn ?? 0, 'var(--v2-warn)') +
            chip('Sin teléfono', stats.sin_telefono, 'var(--v2-text-mute)') +
            chip('Ya importados', stats.duplicados, 'var(--v2-text-mute)');

        const LABEL = {
            omnia_duplicado: 'Ya importado',
            sin_nombre:      'Sin nombre',
            tel_duplicado:   'Tel duplicado (entra sin tel)',
            tel_invalido:    'Tel inválido (entra sin tel)',
            dni_duplicado:   'DNI duplicado (entra sin DNI)',
        };

        const tbody = document.getElementById('import-tbody');
        tbody.innerHTML = filas.map((f, i) => {
            const warn = f.dni_warn || f.tel_warn;
            const bg = f.conflicto ? 'var(--v2-urg-bg)' : (warn ? 'var(--v2-warn-bg)' : '');
            const warns = [];
            if (f.tel_warn) warns.push(`⚠ ${LABEL[f.tel_warn]}`);
            if (f.dni_warn) warns.push(`⚠ ${LABEL[f.dni_warn]}`);
            const etiqueta = f.conflicto
                ? `<span class="v2-pill urgente" style="font-size:10.5px;">${LABEL[f.conflicto] ?? f.conflicto}</span>`
                : (warns.length
                    ? warns.map(w => `<span class="v2-pill espera" style="font-size:10.5px;margin-right:3px;">${w}</span>`).join('')
                    : '<span style="font-size:11px;color:var(--v2-ok);">OK</span>');
            return `<tr style="border-bottom:1px solid var(--v2-border);background:${bg};">
                <td style="padding:7px 10px;text-align:center;">
                    <input type="checkbox" data-i="${i}" ${f.importar ? 'checked' : ''} ${f.conflicto === 'sin_nombre' ? 'disabled' : ''} onchange="filasImport[${i}].importar = this.checked">
                </td>
                <td style="padding:7px 10px;font-weight:500;">${esc(f.nombre || '—')}</td>
                <td style="padding:7px 10px;color:var(--v2-text-2);">${esc(f.telefono || (f.tel_warn ? f.tel_raw : '—'))}</td>
                <td style="padding:7px 10px;">${esc(f.dni || (f.dni_warn ? f.dni_raw : '—'))}</td>
                <td style="padding:7px 10px;color:var(--v2-text-2);">${esc(f.fecha_nacimiento || '—')}</td>
                <td style="padding:7px 10px;">${etiqueta}</td>
            </tr>`;
        }).join('');

        document.getElementById('selec-todos').checked = filas.filter(f => !f.conflicto).every(f => f.importar);
        irPaso(2);
    } catch (e) {
        errEl.textContent = e?.message ?? 'Error al leer el archivo.';
        errEl.style.display = '';
    }
}

function toggleTodos(checked) {
    filasImport.forEach((f, i) => {
        if (!f.conflicto) {
            f.importar = checked;
            const cb = document.querySelector(`input[data-i="${i}"]`);
            if (cb) cb.checked = checked;
        }
    });
}

async function confirmarImport() {
    const seleccionados = filasImport.filter(f => f.importar).length;
    if (!seleccionados) {
        const errEl = document.getElementById('import-error-2');
        errEl.textContent = 'Seleccioná al menos un contacto para importar.';
        errEl.style.display = '';
        return;
    }
    document.getElementById('import-error-2').style.display = 'none';

    try {
        const { importados, omitidos, errores } = await api('POST', '/contactos/import/confirm', { filas: filasImport });

        document.getElementById('import-resultado').innerHTML = `
            <div style="font-size:40px;margin-bottom:12px;color:var(--v2-ok);">✓</div>
            <div style="font-size:17px;font-weight:650;margin-bottom:6px;">Importación completada</div>
            <div style="font-size:14px;color:var(--v2-text-2);margin-bottom:16px;">
                <strong style="color:var(--v2-ok);">${importados}</strong> contactos importados
                · <strong>${omitidos}</strong> omitidos
            </div>
            ${errores.length ? `<div style="font-size:12px;color:var(--v2-urg);background:var(--v2-urg-bg);border:1px solid var(--v2-urg);border-radius:var(--v2-radius-sm);padding:10px;text-align:left;max-width:400px;margin:0 auto;">
                ${errores.map(e => `<div>${esc(e)}</div>`).join('')}
            </div>` : ''}`;

        irPaso(3);
        cargar();
        v2toast(`${importados} contacto${importados !== 1 ? 's' : ''} importado${importados !== 1 ? 's' : ''}`);
    } catch (e) {
        const errEl = document.getElementById('import-error-2');
        errEl.textContent = e?.message ?? 'Error al importar.';
        errEl.style.display = '';
    }
}

// Enter guarda en el modal de contacto (Escape lo maneja <dialog> nativo).
document.addEventListener('keydown', e => {
    if (e.key === 'Enter' && document.getElementById('modal-contacto').open && e.target.tagName !== 'TEXTAREA') guardar();
});

// Init + deep-link ?sel=ID (desde el legajo de una conversación): abre la ficha.
(async () => {
    await cargar();
    const sel = new URLSearchParams(location.search).get('sel');
    if (sel) {
        try {
            const d = await api('GET', `/contactos/${parseInt(sel)}`);
            if (d.contacto) abrirForm(d.contacto);
        } catch (e) {}
    }
})();
</script>
@endpush
