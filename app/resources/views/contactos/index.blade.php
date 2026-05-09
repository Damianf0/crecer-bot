@extends('layouts.app')
@section('title', 'Contactos')

@section('content')
<style>
.c-avatar { width:34px; height:34px; border-radius:50%; object-fit:cover; background:var(--surface);
            display:inline-flex; align-items:center; justify-content:center; font-weight:700; color:var(--muted);
            font-size:13px; border:1px solid var(--border); vertical-align:middle; margin-right:10px; cursor:pointer;
            flex-shrink:0; transition:transform .12s; }
.c-avatar:hover { transform:scale(1.08); border-color:var(--info); }
.c-avatar-big { width:110px; height:110px; border-radius:50%; object-fit:cover; background:var(--surface);
                display:inline-flex; align-items:center; justify-content:center; font-weight:700; color:var(--muted);
                font-size:42px; border:1px solid var(--border); cursor:zoom-in; }
.c-avatar-big.no-photo { cursor:default; }
.c-zoom { position:fixed; inset:0; background:rgba(0,0,0,.85); display:none; align-items:center; justify-content:center; z-index:9999; cursor:zoom-out; }
.c-zoom img { max-width:90vw; max-height:90vh; border-radius:10px; box-shadow:0 16px 48px rgba(0,0,0,.5); }
</style>

{{-- Lightbox del avatar --}}
<div id="avatar-zoom" class="c-zoom" onclick="this.style.display='none'"><img id="avatar-zoom-img" alt=""></div>

<div style="max-width:900px;margin:0 auto;">

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
        <h2 style="font-size:18px;font-weight:700;margin:0;">Directorio de contactos</h2>
        <button onclick="abrirImport()"
                style="margin-left:auto;background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:7px;padding:7px 16px;font-size:13px;font-weight:600;cursor:pointer;">
            ↑ Importar CSV
        </button>
        <button onclick="abrirForm()"
                style="background:var(--accent);border:none;color:#fff;border-radius:7px;padding:7px 16px;font-size:13px;font-weight:600;cursor:pointer;">
            + Agregar
        </button>
    </div>

    <div style="position:relative;margin-bottom:8px;">
        <input id="buscar" placeholder="Buscar por nombre, teléfono, DNI o ID WhatsApp…"
               oninput="cargarDebounced()"
               style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:7px;
                      color:var(--text);padding:9px 38px 9px 13px;font-size:14px;font-family:inherit;">
        <span id="buscar-spinner" style="display:none;position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--muted);">…</span>
    </div>
    <div id="info-listado" style="font-size:12px;color:var(--muted);margin-bottom:10px;min-height:18px;">—</div>

    <div id="lista"></div>

    <div id="ver-mas-row" style="display:none;text-align:center;padding:14px 0;">
        <button onclick="cargarMas()" id="btn-ver-mas"
                style="padding:8px 22px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:13px;cursor:pointer;font-weight:600;">
            Ver más contactos
        </button>
    </div>

    {{-- Modal contacto --}}
    <div id="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:500;align-items:center;justify-content:center;">
        <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;width:420px;max-width:94vw;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
            <div style="font-size:15px;font-weight:700;margin-bottom:14px;" id="modal-titulo">Agregar contacto</div>

            {{-- Avatar grande del contacto (solo en modo edición). Click → lightbox si tiene foto. --}}
            <div id="modal-avatar-row" style="display:none;text-align:center;margin-bottom:18px;">
                <div id="modal-avatar"></div>
            </div>

            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Datos de contacto</div>
            <input id="f-telefono" placeholder="Teléfono WhatsApp (solo números, opcional)"
                   style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);
                          padding:8px 11px;font-size:14px;font-family:inherit;margin-bottom:10px;">
            <input id="f-nombre" placeholder="Nombre completo *"
                   style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);
                          padding:8px 11px;font-size:14px;font-family:inherit;margin-bottom:10px;">
            <input id="f-email" placeholder="Email (opcional)"
                   style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);
                          padding:8px 11px;font-size:14px;font-family:inherit;margin-bottom:16px;">

            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Datos Omnia</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">
                <input id="f-dni" placeholder="DNI"
                       style="background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);
                              padding:8px 11px;font-size:14px;font-family:inherit;">
                <input id="f-fecha" type="date"
                       style="background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);
                              padding:8px 11px;font-size:14px;font-family:inherit;">
            </div>
            <input id="f-omnia-id" placeholder="ID paciente Omnia (opcional)"
                   style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);
                          padding:8px 11px;font-size:14px;font-family:inherit;margin-bottom:16px;">

            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Notas</div>
            <textarea id="f-notas" placeholder="Notas internas (opcional)" rows="2"
                   style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);
                          padding:8px 11px;font-size:14px;font-family:inherit;resize:none;margin-bottom:16px;"></textarea>

            <div id="f-waid-row" style="display:none;font-size:11px;color:var(--muted);background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:8px 11px;margin-bottom:14px;font-family:monospace;">
                <span style="text-transform:uppercase;letter-spacing:.5px;font-family:inherit;">ID WhatsApp:</span>
                <span id="f-waid-val" style="margin-left:6px;color:var(--text);"></span>
                <span id="f-waid-tipo" style="margin-left:8px;font-family:inherit;font-size:10px;padding:1px 6px;border-radius:8px;"></span>
            </div>

            <div id="form-error" style="color:var(--error);font-size:12px;margin-bottom:10px;display:none;"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button onclick="cerrarForm()"
                        style="background:var(--surface);border:1px solid var(--border);color:var(--muted);border-radius:6px;padding:7px 16px;font-size:13px;cursor:pointer;">
                    Cancelar
                </button>
                <button onclick="guardar()"
                        style="background:var(--accent);border:none;color:#fff;border-radius:6px;padding:7px 16px;font-size:13px;font-weight:600;cursor:pointer;">
                    Guardar
                </button>
            </div>
        </div>
    </div>

    {{-- Modal importar --}}
    <div id="modal-import" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:600;align-items:center;justify-content:center;">
        <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;width:860px;max-width:97vw;max-height:93vh;display:flex;flex-direction:column;" onclick="event.stopPropagation()">

            {{-- Header --}}
            <div style="display:flex;align-items:center;margin-bottom:18px;">
                <div style="font-size:15px;font-weight:700;">Importar contactos desde CSV de Omnia</div>
                <button onclick="cerrarImport()" style="margin-left:auto;background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;line-height:1;">×</button>
            </div>

            {{-- Pasos --}}
            <div style="display:flex;gap:0;margin-bottom:20px;">
                <div id="paso-ind-1" style="flex:1;text-align:center;font-size:12px;font-weight:600;padding:6px;border-bottom:2px solid var(--accent);color:var(--accent);">1. Archivo</div>
                <div id="paso-ind-2" style="flex:1;text-align:center;font-size:12px;font-weight:600;padding:6px;border-bottom:2px solid var(--border);color:var(--muted);">2. Revisar</div>
                <div id="paso-ind-3" style="flex:1;text-align:center;font-size:12px;font-weight:600;padding:6px;border-bottom:2px solid var(--border);color:var(--muted);">3. Resultado</div>
            </div>

            {{-- Contenido pasos --}}
            <div style="flex:1;overflow-y:auto;">

                {{-- Paso 1: subir archivo --}}
                <div id="paso-1">
                    <p style="font-size:13px;color:var(--muted);margin-bottom:16px;">
                        Exportá el listado de pacientes desde Omnia en formato CSV y subilo acá.
                        El archivo debe estar en formato <strong>CSV (separado por comas)</strong>, tal como lo exporta Omnia.
                    </p>
                    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:16px;font-size:12px;color:var(--muted);">
                        <div style="font-weight:600;color:var(--text);margin-bottom:6px;">Columnas esperadas del CSV de Omnia:</div>
                        ID · Apellido paterno · Apellido materno · Nombre · Otros nombres · Número de documento · Celular · Teléfono · Email · Fecha nacimiento · Obra social del paciente
                    </div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;">Archivo CSV</label>
                    <input type="file" id="import-archivo" accept=".csv"
                           style="display:block;width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;
                                  color:var(--text);padding:8px 11px;font-size:13px;font-family:inherit;margin-bottom:16px;">
                    <div id="import-error-1" style="color:var(--error);font-size:12px;margin-bottom:10px;display:none;"></div>
                    <div style="display:flex;justify-content:flex-end;">
                        <button onclick="previsualizarCSV()"
                                style="background:var(--accent);border:none;color:#fff;border-radius:6px;padding:8px 20px;font-size:13px;font-weight:600;cursor:pointer;">
                            Previsualizar →
                        </button>
                    </div>
                </div>

                {{-- Paso 2: preview --}}
                <div id="paso-2" style="display:none;">
                    <div id="import-stats" style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap;"></div>

                    <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">
                        Marcá los contactos que querés importar. Los que tienen conflictos están desmarcados por defecto.
                    </div>

                    <div style="margin-bottom:10px;display:flex;gap:10px;align-items:center;">
                        <label style="font-size:12px;cursor:pointer;display:flex;align-items:center;gap:5px;">
                            <input type="checkbox" id="selec-todos" onchange="toggleTodos(this.checked)"> Seleccionar todos sin conflicto
                        </label>
                        <div style="margin-left:auto;display:flex;gap:8px;align-items:center;font-size:12px;">
                            <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:rgba(248,81,73,.15);display:inline-block;border:1px solid rgba(248,81,73,.4);"></span>Conflicto</span>
                            <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:rgba(210,153,34,.12);display:inline-block;border:1px solid rgba(210,153,34,.3);"></span>Sin teléfono</span>
                        </div>
                    </div>

                    <div style="overflow-x:auto;max-height:380px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;">
                        <table style="width:100%;border-collapse:collapse;font-size:12px;" id="import-tabla">
                            <thead style="position:sticky;top:0;background:var(--card);z-index:1;">
                                <tr style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">
                                    <th style="padding:8px 10px;text-align:center;border-bottom:1px solid var(--border);width:36px;"></th>
                                    <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);">Nombre</th>
                                    <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);">Teléfono</th>
                                    <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);">DNI</th>
                                    <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);">Nacimiento</th>
                                    <th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);">Conflicto</th>
                                </tr>
                            </thead>
                            <tbody id="import-tbody"></tbody>
                        </table>
                    </div>

                    <div id="import-error-2" style="color:var(--error);font-size:12px;margin-top:10px;display:none;"></div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
                        <button onclick="irPaso(1)"
                                style="background:var(--surface);border:1px solid var(--border);color:var(--muted);border-radius:6px;padding:7px 16px;font-size:13px;cursor:pointer;">
                            ← Volver
                        </button>
                        <button onclick="confirmarImport()"
                                style="background:var(--accent);border:none;color:#fff;border-radius:6px;padding:7px 20px;font-size:13px;font-weight:600;cursor:pointer;">
                            Importar seleccionados
                        </button>
                    </div>
                </div>

                {{-- Paso 3: resultado --}}
                <div id="paso-3" style="display:none;text-align:center;padding:30px 0;">
                    <div id="import-resultado"></div>
                    <button onclick="cerrarImport()"
                            style="margin-top:20px;background:var(--accent);border:none;color:#fff;border-radius:6px;padding:8px 24px;font-size:13px;font-weight:600;cursor:pointer;">
                        Cerrar
                    </button>
                </div>

            </div>
        </div>
    </div>

    <div id="toast" style="position:fixed;bottom:90px;right:24px;padding:10px 18px;border-radius:8px;font-size:13px;
                            opacity:0;transition:.2s;pointer-events:none;z-index:999;"></div>

{{-- Modal: Iniciar chat WhatsApp --}}
<div id="modal-chat" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
    <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;width:min(500px,calc(100vw - 32px));max-height:calc(100vh - 64px);overflow-y:auto;box-shadow:0 12px 32px rgba(0,0,0,.3);" onclick="event.stopPropagation()">
        <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <span style="font-weight:700;font-size:15px;">Chatear con <span id="chat-nombre">—</span></span>
            <button onclick="cerrarChat()" style="background:none;border:none;color:var(--muted);font-size:22px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div style="padding:16px 18px;">
            <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">Teléfono: <span id="chat-tel">—</span></div>

            <label style="display:block;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin:6px 0 4px;">Plantilla rápida</label>
            <select id="chat-plantilla" onchange="aplicarPlantillaChat(this.value)" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid var(--border);background:var(--surface);color:var(--text);font-size:14px;">
                <option value="0">En blanco</option>
                <option value="1">Recordatorio de turno</option>
                <option value="2">Confirmar receta lista</option>
                <option value="3">Solicitar muestra</option>
                <option value="4">Pedido de información</option>
            </select>

            <label style="display:block;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin:12px 0 4px;">Mensaje inicial</label>
            <textarea id="chat-texto" placeholder="Escribí el primer mensaje…"
                style="width:100%;min-height:100px;padding:8px 10px;border-radius:6px;border:1px solid var(--border);background:var(--surface);color:var(--text);font-size:14px;font-family:inherit;resize:vertical;"></textarea>
        </div>
        <div style="padding:12px 18px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;">
            <button onclick="cerrarChat()" style="padding:8px 16px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-size:13px;">Cancelar</button>
            <button id="chat-enviar" onclick="enviarChat()" style="padding:8px 16px;border-radius:6px;border:none;background:var(--success);color:#fff;font-weight:600;cursor:pointer;font-size:13px;">Iniciar y enviar</button>
        </div>
    </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';

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
    const m = document.getElementById('modal-chat');
    m.style.display = 'flex';
    m.onclick = (e) => { if (e.target === m) cerrarChat(); };
    setTimeout(() => document.getElementById('chat-texto').focus(), 50);
}

function cerrarChat() {
    document.getElementById('modal-chat').style.display = 'none';
    _chatContacto = null;
}

function aplicarPlantillaChat(idx) {
    document.getElementById('chat-texto').value = PLANTILLAS_CHAT[parseInt(idx)] || '';
}

async function enviarChat() {
    const texto = document.getElementById('chat-texto').value.trim();
    if (!texto || !_chatContacto) { toast('Falta el mensaje', false); return; }

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
            toast(data.error || 'No se pudo iniciar', false);
            btn.disabled = false; btn.textContent = 'Iniciar y enviar';
            return;
        }
        cerrarChat();
        if (confirm((data.reusada ? 'Conversación reabierta' : 'Mensaje enviado') + '. ¿Ir a la conversación ahora?')) {
            window.location.href = '/atencion?conv_id=' + data.conv_id;
        } else {
            toast('Mensaje enviado');
        }
    } catch (e) {
        toast('Error de red', false);
        btn.disabled = false; btn.textContent = 'Iniciar y enviar';
    }
}
let editId = null;
let filasImport = [];

async function api(method, url, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(url, opts);
    if (!r.ok) throw await r.json().catch(() => ({ message: r.status }));
    return r.json();
}

function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function inicial(nombre) {
    const m = String(nombre ?? '').trim().match(/[A-Za-zÁÉÍÓÚÑáéíóúñ]/);
    return m ? m[0].toUpperCase() : '?';
}

// Formatea "1974-08-31T03:00:00.000000Z" o "1974-08-31" a "31/08/1974".
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

function toast(msg, ok = true) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.style.cssText += ok
        ? ';background:rgba(63,185,80,.15);color:var(--success);border:1px solid rgba(63,185,80,.3);'
        : ';background:rgba(248,81,73,.15);color:var(--error);border:1px solid rgba(248,81,73,.3);';
    el.style.opacity = '1';
    setTimeout(() => el.style.opacity = '0', 2800);
}

// Estado de paginación / búsqueda
let _pageActual = 1;
let _qActual    = '';
let _paginando  = false;   // solo bloquea "Ver más"; las búsquedas siempre se disparan
let _debounceTimer = null;
let _reqSeq    = 0;  // contador para descartar respuestas viejas si llegan fuera de orden

function cargarDebounced() {
    clearTimeout(_debounceTimer);
    document.getElementById('buscar-spinner').style.display = 'inline';
    _debounceTimer = setTimeout(() => cargar(true), 250);
}

async function cargar(reset = true) {
    // Solo bloqueamos paginación duplicada; búsquedas (reset=true) siempre se disparan,
    // y el contador _reqSeq descarta respuestas viejas.
    if (!reset && _paginando) return;
    if (!reset) _paginando = true;
    const seq = ++_reqSeq;

    const q = document.getElementById('buscar').value.trim();
    if (reset) { _pageActual = 1; _qActual = q; }

    document.getElementById('buscar-spinner').style.display = 'inline';
    document.getElementById('btn-ver-mas')?.setAttribute('disabled', '');

    try {
        const r = await api('GET', `/contactos/data?q=${encodeURIComponent(_qActual)}&page=${_pageActual}`);
        if (seq !== _reqSeq) return;   // llegó tarde, ya hay otra request más nueva
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
    const lista = document.getElementById('lista');
    const info  = document.getElementById('info-listado');
    const verMas= document.getElementById('ver-mas-row');

    // Texto del header de info
    if (total === 0) {
        info.textContent = _qActual ? `Sin resultados para "${_qActual}"` : 'No hay contactos cargados';
    } else if (total <= per_page) {
        info.textContent = `${total} contacto${total !== 1 ? 's' : ''}`;
    } else {
        const mostrados = Math.min(page * per_page, total);
        info.textContent = `Mostrando ${mostrados} de ${total}` + (_qActual ? ` para "${_qActual}"` : '');
    }

    if (!data.length && reset) {
        lista.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);font-size:13px;">Sin contactos</div>';
        verMas.style.display = 'none';
        return;
    }

    const filasHtml = data.map(c => filaHtml(c)).join('');

    if (reset) {
        lista.innerHTML = `<table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead><tr style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">
                <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--border);">Nombre</th>
                <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--border);">Teléfono</th>
                <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--border);">DNI</th>
                <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--border);">Nacimiento</th>
                <th style="padding:8px 12px;text-align:left;border-bottom:1px solid var(--border);">Email</th>
                <th style="padding:8px 12px;border-bottom:1px solid var(--border);"></th>
            </tr></thead>
            <tbody id="lista-tbody">${filasHtml}</tbody></table>`;
    } else {
        // Append en "Ver más"
        const tbody = document.getElementById('lista-tbody');
        if (tbody) tbody.insertAdjacentHTML('beforeend', filasHtml);
    }

    verMas.style.display = has_more ? 'block' : 'none';
}

function filaHtml(c) {
    return `
            <tr style="border-bottom:1px solid var(--border);" id="row-${c.id}">
                <td style="padding:10px 12px;font-weight:600;">
                    <div style="display:flex;align-items:center;">
                        ${avatarMiniHtml(c)}
                        <div>
                            ${esc(c.nombre)}
                            ${c.omnia_patient_id ? '<span title="Vinculado a Omnia" style="margin-left:5px;font-size:10px;background:rgba(26,86,196,.15);color:var(--info);border-radius:4px;padding:1px 5px;">Omnia</span>' : ''}
                            ${c.wa_id && c.wa_id.endsWith('@lid') ? '<span title="WhatsApp identifica con LID (privacidad)" style="margin-left:5px;font-size:10px;background:color-mix(in srgb,var(--warning) 15%,transparent);color:var(--warning);border-radius:4px;padding:1px 5px;">LID</span>' : ''}
                        </div>
                    </div>
                </td>
                <td style="padding:10px 12px;color:var(--muted);font-size:13px;">${esc(c.telefono)}</td>
                <td style="padding:10px 12px;font-size:13px;">${esc(c.dni ?? '\u2014')}</td>
                <td style="padding:10px 12px;color:var(--muted);font-size:13px;">${fechaCorta(c.fecha_nacimiento)}</td>
                <td style="padding:10px 12px;color:var(--muted);font-size:13px;">${esc(c.email ?? '\u2014')}</td>
                <td style="padding:10px 12px;text-align:right;white-space:nowrap;">
                    <a href="/pacientes/${c.id}/documentos" title="Ver legajo de documentos"
                            style="display:inline-block;text-decoration:none;background:none;border:1px solid color-mix(in srgb,var(--info) 35%,transparent);color:var(--info);border-radius:5px;padding:3px 10px;font-size:12px;cursor:pointer;margin-right:4px;font-weight:600;">📁 Legajo</a>
                    ${c.telefono ? `<button onclick='iniciarChat(${JSON.stringify(c)})' title="Iniciar conversación por WhatsApp"
                            style="background:none;border:1px solid color-mix(in srgb,var(--success) 35%,transparent);color:var(--success);border-radius:5px;padding:3px 10px;font-size:12px;cursor:pointer;margin-right:4px;font-weight:600;">Chatear</button>` : ''}
                    <button onclick='abrirForm(${JSON.stringify(c)})' title="Editar"
                            style="background:none;border:1px solid var(--border);color:var(--muted);border-radius:5px;padding:3px 10px;font-size:12px;cursor:pointer;margin-right:4px;">\u270F\uFE0F</button>
                    <button onclick="eliminar(${c.id})"
                            style="background:none;border:1px solid rgba(248,81,73,.3);color:var(--error);border-radius:5px;padding:3px 10px;font-size:12px;cursor:pointer;">\u2715</button>
                </td>
            </tr>`;
}

function abrirForm(c) {
    editId = c?.id ?? null;
    document.getElementById('modal-titulo').textContent = editId ? 'Editar contacto' : 'Agregar contacto';

    // Avatar grande arriba (solo en edición)
    const avRow = document.getElementById('modal-avatar-row');
    const avEl  = document.getElementById('modal-avatar');
    if (editId && c) {
        avEl.innerHTML = avatarBigHtml(c);
        avRow.style.display = 'block';
    } else {
        avRow.style.display = 'none';
    }

    document.getElementById('f-telefono').value  = c?.telefono ?? '';
    document.getElementById('f-nombre').value    = c?.nombre ?? '';
    document.getElementById('f-email').value     = c?.email ?? '';
    document.getElementById('f-dni').value       = c?.dni ?? '';
    // El backend serializa fecha_nacimiento como ISO con timestamp ("1974-08-31T03:00:00.000000Z").
    // <input type=date> solo acepta "YYYY-MM-DD", así que extraemos la parte de fecha.
    document.getElementById('f-fecha').value     = c?.fecha_nacimiento ? String(c.fecha_nacimiento).slice(0, 10) : '';
    document.getElementById('f-omnia-id').value  = c?.omnia_patient_id ?? '';
    document.getElementById('f-notas').value     = c?.notas ?? '';

    // Mostrar wa_id si existe (read-only): ayuda a entender por qué algunos chats parecen huérfanos
    const waRow = document.getElementById('f-waid-row');
    if (c?.wa_id) {
        document.getElementById('f-waid-val').textContent = c.wa_id;
        const tipo = document.getElementById('f-waid-tipo');
        const esLid = c.wa_id.endsWith('@lid');
        tipo.textContent = esLid ? 'LID' : 'estándar';
        tipo.style.background = esLid
            ? 'color-mix(in srgb, var(--warning) 15%, transparent)'
            : 'color-mix(in srgb, var(--success) 15%, transparent)';
        tipo.style.color = esLid ? 'var(--warning)' : 'var(--success)';
        waRow.style.display = 'block';
    } else {
        waRow.style.display = 'none';
    }

    document.getElementById('form-error').style.display = 'none';
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('f-telefono').focus();
}

function cerrarForm() {
    document.getElementById('modal').style.display = 'none';
    editId = null;
}

async function guardar() {
    const telefono         = document.getElementById('f-telefono').value.replace(/\D/g,'');
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
        cerrarForm();
        cargar();
        toast(editId ? 'Contacto actualizado' : 'Contacto agregado');
    } catch(e) {
        const msg = e?.errors ? Object.values(e.errors).flat().join(' ') : (e?.message ?? 'Error');
        errEl.textContent = msg;
        errEl.style.display = '';
    }
}

async function eliminar(id) {
    if (!confirm('¿Eliminar este contacto del directorio?')) return;
    await api('DELETE', `/contactos/${id}`);
    cargar();
    toast('Contacto eliminado');
}

// ── Importar ──────────────────────────────────────────────

function abrirImport() {
    filasImport = [];
    document.getElementById('import-archivo').value = '';
    document.getElementById('import-error-1').style.display = 'none';
    irPaso(1);
    document.getElementById('modal-import').style.display = 'flex';
}

function cerrarImport() {
    document.getElementById('modal-import').style.display = 'none';
}

function irPaso(n) {
    [1,2,3].forEach(i => {
        document.getElementById(`paso-${i}`).style.display = i === n ? '' : 'none';
        const ind = document.getElementById(`paso-ind-${i}`);
        ind.style.borderBottomColor = i === n ? 'var(--accent)' : 'var(--border)';
        ind.style.color = i === n ? 'var(--accent)' : 'var(--muted)';
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

        // Stats
        const statEl = document.getElementById('import-stats');
        const chip = (label, val, color) =>
            `<div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:8px 14px;text-align:center;">
                <div style="font-size:20px;font-weight:700;color:${color};">${val}</div>
                <div style="font-size:11px;color:var(--muted);">${label}</div>
             </div>`;
        statEl.innerHTML =
            chip('Total', stats.total, 'var(--text)') +
            chip('Listos', stats.ok, 'var(--success)') +
            chip('Tel. a revisar', stats.tel_warn ?? 0, 'var(--warning, #d99522)') +
            chip('DNI a revisar', stats.dni_warn ?? 0, 'var(--warning, #d99522)') +
            chip('Sin teléfono', stats.sin_telefono, 'var(--muted)') +
            chip('Ya importados', stats.duplicados, 'var(--muted)');

        // Tabla — `conflicto` bloquea import; `tel_warn`/`dni_warn` son informativos.
        const LABEL = {
            omnia_duplicado: 'Ya importado',
            sin_nombre:      'Sin nombre',
            tel_duplicado:   'Tel duplicado (entra sin tel)',
            tel_invalido:    'Tel inválido (entra sin tel)',
            dni_duplicado:   'DNI duplicado (entra sin DNI)',
        };
        const COLOR_BG = {
            omnia_duplicado: 'rgba(120,120,120,.06)',
            sin_nombre:      'rgba(248,81,73,.08)',
            tel_duplicado:   'rgba(210,153,34,.06)',
            tel_invalido:    'rgba(210,153,34,.06)',
            dni_duplicado:   'rgba(210,153,34,.06)',
        };

        const tbody = document.getElementById('import-tbody');
        tbody.innerHTML = filas.map((f, i) => {
            const warn = f.dni_warn || f.tel_warn;
            const bg = f.conflicto ? COLOR_BG[f.conflicto] || '' : (warn ? COLOR_BG[warn] : '');
            const warns = [];
            if (f.tel_warn) warns.push(`⚠ ${LABEL[f.tel_warn]}`);
            if (f.dni_warn) warns.push(`⚠ ${LABEL[f.dni_warn]}`);
            const etiqueta = f.conflicto
                ? `<span style="font-size:11px;background:rgba(248,81,73,.12);color:var(--error);border-radius:4px;padding:2px 7px;">${LABEL[f.conflicto] ?? f.conflicto}</span>`
                : (warns.length
                    ? warns.map(w => `<span style="font-size:11px;background:rgba(210,153,34,.18);color:var(--warning,#a36a00);border-radius:4px;padding:2px 7px;margin-right:3px;">${w}</span>`).join('')
                    : '<span style="font-size:11px;color:var(--success);">OK</span>');
            return `<tr style="border-bottom:1px solid var(--border);background:${bg};">
                <td style="padding:7px 10px;text-align:center;">
                    <input type="checkbox" data-i="${i}" ${f.importar ? 'checked' : ''} ${f.conflicto === 'sin_nombre' ? 'disabled' : ''} onchange="filasImport[${i}].importar = this.checked">
                </td>
                <td style="padding:7px 10px;font-weight:500;">${esc(f.nombre || '—')}</td>
                <td style="padding:7px 10px;color:var(--muted);">${esc(f.telefono || (f.tel_warn ? f.tel_raw : '—'))}</td>
                <td style="padding:7px 10px;">${esc(f.dni || (f.dni_warn ? f.dni_raw : '—'))}</td>
                <td style="padding:7px 10px;color:var(--muted);">${esc(f.fecha_nacimiento || '—')}</td>
                <td style="padding:7px 10px;">${etiqueta}</td>
            </tr>`;
        }).join('');

        document.getElementById('selec-todos').checked = filas.filter(f => !f.conflicto).every(f => f.importar);
        irPaso(2);
    } catch(e) {
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

        const resEl = document.getElementById('import-resultado');
        resEl.innerHTML = `
            <div style="font-size:40px;margin-bottom:12px;">✓</div>
            <div style="font-size:18px;font-weight:700;margin-bottom:6px;">Importación completada</div>
            <div style="font-size:14px;color:var(--muted);margin-bottom:16px;">
                <strong style="color:var(--success);">${importados}</strong> contactos importados
                · <strong>${omitidos}</strong> omitidos
            </div>
            ${errores.length ? `<div style="font-size:12px;color:var(--error);background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.2);border-radius:6px;padding:10px;text-align:left;max-width:400px;margin:0 auto;">
                ${errores.map(e => `<div>${esc(e)}</div>`).join('')}
            </div>` : ''}`;

        irPaso(3);
        cargar();
        toast(`${importados} contacto${importados !== 1 ? 's' : ''} importado${importados !== 1 ? 's' : ''}`);
    } catch(e) {
        const errEl = document.getElementById('import-error-2');
        errEl.textContent = e?.message ?? 'Error al importar.';
        errEl.style.display = '';
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { cerrarForm(); cerrarImport(); }
    if (e.key === 'Enter' && document.getElementById('modal').style.display === 'flex') guardar();
});

cargar();
</script>
@endsection
