// ── Navegación ────────────────────────────────────────────
const navItems = document.querySelectorAll('.nav-item');
const tabs = document.querySelectorAll('.tab-content');
let currentTab = 'dashboard';
let logSSEClose = null;
let pruebasSSEClose = null;

navItems.forEach((item) => {
  item.addEventListener('click', () => {
    const tab = item.dataset.tab;
    if (tab === currentTab) return;
    currentTab = tab;

    navItems.forEach((n) => n.classList.toggle('active', n === item));
    tabs.forEach((t) => t.classList.toggle('hidden', t.id !== `tab-${tab}`));

    if (STATUS_TABS.has(tab)) startStatusPolling();
    else stopStatusPolling();

    if (tab === 'config') loadConfig();
    if (tab === 'textos') loadTextos();
    if (tab === 'usuarios') loadUsuarios();
    if (tab === 'pruebas' && !pruebasSSEClose) startPruebas();
    if (tab === 'logs' && !logSSEClose) startLogs();
    if (tab === 'sistema') { loadSistema(); startSistemaPolling(); }
    else stopSistemaPolling();
  });
});

// ── Status polling ────────────────────────────────────────
const STATUS_LABELS = {
  iniciando:    'Iniciando...',
  esperando_qr: 'Esperando QR',
  autenticado:  'Autenticando...',
  listo:        'Conectado',
  desconectado: 'Desconectado',
  error:        'Error',
};

async function refreshStatus() {
  try {
    const { status, phone, uptime, qrDataUrl } = await window.bot.getStatus();
    updateSidebarStatus(status);
    updateDashboard(status, phone, uptime);
    if (currentTab === 'qr') updateQR(status, phone, qrDataUrl);
  } catch (_) {
    updateSidebarStatus('desconectado');
  }
}

function updateSidebarStatus(status) {
  const dot = document.getElementById('sidebarDot');
  const txt = document.getElementById('sidebarStatusText');
  dot.className = `status-dot ${status}`;
  txt.textContent = STATUS_LABELS[status] || status;
}

function updateDashboard(status, phone, uptime) {
  const label = STATUS_LABELS[status] || status;
  document.getElementById('statStatus').innerHTML =
    `<span class="status-label ${status}">${label}</span>`;
  document.getElementById('statPhone').textContent = phone ? `+${phone}` : '—';
  document.getElementById('statUptime').textContent = uptime || '—';
}

function updateQR(status, phone, qrDataUrl) {
  const container = document.getElementById('qrContainer');
  if (status === 'esperando_qr' && qrDataUrl) {
    container.innerHTML = `
      <img src="${qrDataUrl}" width="280" height="280" alt="QR WhatsApp">
      <p class="qr-hint">
        Abrí WhatsApp en el celular del número de la clínica.<br>
        <strong>Ajustes → Dispositivos vinculados → Vincular un dispositivo</strong><br>
        Apuntá la cámara a este código.
      </p>`;
  } else if (status === 'listo') {
    container.innerHTML = `
      <div class="connected-badge">
        <div class="connected-icon">✓</div>
        <div class="connected-text">WhatsApp conectado</div>
        <div class="connected-phone">${phone ? '+' + phone : ''}</div>
      </div>`;
  } else {
    container.innerHTML = `
      <div style="color:var(--text-muted);text-align:center;font-size:14px;">
        <div style="font-size:36px;margin-bottom:12px;">○</div>
        ${STATUS_LABELS[status] || status}…
      </div>`;
  }
}

// Solo pollea cuando la ventana está visible y en tab relevante
const STATUS_TABS = new Set(['dashboard', 'qr']);
let statusInterval = null;

function startStatusPolling() {
  if (statusInterval) return;
  refreshStatus();
  statusInterval = setInterval(refreshStatus, 5000);
}

function stopStatusPolling() {
  if (statusInterval) { clearInterval(statusInterval); statusInterval = null; }
}

document.addEventListener('visibilitychange', () => {
  if (document.hidden) stopStatusPolling();
  else if (STATUS_TABS.has(currentTab)) startStatusPolling();
});

startStatusPolling();
loadAccesos();

// ── Accesos rápidos (Dashboard) ───────────────────────────

const ACCESOS = [
  {
    key: 'admin',
    label: 'Panel Admin Web',
    desc: 'Estado bot, textos, logs, usuarios, pruebas — accesible desde cualquier PC',
    icon: '★',
    url: 'http://localhost/admin',
    destacado: true,
  },
  {
    key: 'atencion',
    label: 'Gestión de Atención',
    desc: 'Derivaciones y WhatsApp unificados',
    icon: '⊛',
    url: 'http://localhost/atencion',
  },
  {
    key: 'secretaria',
    label: 'Cola de recepción',
    desc: 'Pacientes en espera en la sala',
    icon: '◫',
    url: 'http://localhost/secretaria',
  },
  {
    key: 'tablet',
    label: 'Tablet sala de espera',
    desc: 'Pantalla de check-in para pacientes',
    icon: '⊹',
    url: 'http://localhost/tablet',
  },
  {
    key: 'bot',
    label: 'Bot API',
    desc: 'Estado y logs del bot WhatsApp',
    icon: '⬡',
    url: 'http://localhost:3001/status',
  },
  {
    key: 'ollama',
    label: 'Ollama',
    desc: 'API local de inteligencia artificial',
    icon: '◉',
    url: 'http://localhost:11434',
  },
];

async function loadAccesos() {
  const grid = document.getElementById('accesosGrid');
  if (!grid) return;

  // Render inmediato con estado desconocido, luego actualiza con checks
  renderAccesos(grid, {});

  // Checks HTTP en paralelo
  const results = {};
  await Promise.all(
    ACCESOS.map(async ({ key, url }) => {
      try {
        const res = await fetch(url, { signal: AbortSignal.timeout(3000) });
        results[key] = res.ok || res.status < 500;
      } catch {
        results[key] = false;
      }
    })
  );

  renderAccesos(grid, results);
}

function renderAccesos(grid, results) {
  grid.innerHTML = '';
  ACCESOS.forEach(({ key, label, desc, icon, url, destacado }) => {
    const estado = results[key];
    let badge = '';
    if (estado === true)  badge = '<span class="svc-badge running">↑ En línea</span>';
    else if (estado === false) badge = '<span class="svc-badge stopped">↓ Sin respuesta</span>';

    const card = document.createElement('div');
    card.className = 'acceso-card' + (destacado ? ' acceso-destacado' : '');
    if (destacado) {
      card.style.cssText = 'border-color: var(--accent, #C0273A); background: color-mix(in srgb, var(--accent, #C0273A) 8%, transparent);';
    }
    card.innerHTML = `
      <span class="acceso-icon">${icon}</span>
      <div class="acceso-info">
        <div class="acceso-name">${label}</div>
        <div class="acceso-desc">${desc}</div>
        <div class="acceso-url">${url.replace('http://', '')}</div>
      </div>
      <div class="acceso-right">
        ${badge}
        <button class="btn ${destacado ? '' : 'btn-secondary'} acceso-btn" data-url="${url}">Abrir ↗</button>
      </div>
    `;
    grid.appendChild(card);
  });

  grid.querySelectorAll('.acceso-btn').forEach((btn) => {
    btn.addEventListener('click', () => window.sistema.abrirURL(btn.dataset.url));
  });
}

document.getElementById('btnRefreshAccesos').addEventListener('click', loadAccesos);

// Banner del dashboard: link al panel admin web
document.getElementById('lnkAdminBanner')?.addEventListener('click', (e) => {
    e.preventDefault();
    window.sistema.abrirURL('http://localhost/admin');
});

// Marcar visualmente los tabs cuyas funciones ahora están en la web
['config', 'textos', 'usuarios', 'pruebas', 'logs'].forEach((tab) => {
    const item = document.querySelector(`.nav-item[data-tab="${tab}"]`);
    if (item && !item.querySelector('.web-tag')) {
        const tag = document.createElement('span');
        tag.className = 'web-tag';
        tag.title = 'También disponible en el panel web admin';
        tag.textContent = '↗';
        tag.style.cssText = 'margin-left:auto;font-size:11px;color:var(--text-muted);opacity:.6;';
        item.appendChild(tag);
    }
});

// ── Config ────────────────────────────────────────────────

// Carga el select de modelos con los instalados en Ollama
async function loadModelosSelect(valorActual) {
  const select = document.getElementById('cfg-OLLAMA_MODEL');
  try {
    const { models } = await window.sistema.getOllamaModels();
    select.innerHTML = '';

    if (!models || models.length === 0) {
      select.innerHTML = '<option value="">Sin modelos instalados</option>';
      return;
    }

    models.forEach(({ name }) => {
      const opt = document.createElement('option');
      opt.value = name;
      opt.textContent = name;
      if (name === valorActual) opt.selected = true;
      select.appendChild(opt);
    });

    // Si el valor actual no está en la lista, lo agrego como opción
    if (valorActual && !models.find((m) => m.name === valorActual)) {
      const opt = document.createElement('option');
      opt.value = valorActual;
      opt.textContent = `${valorActual} (no instalado)`;
      opt.selected = true;
      select.insertBefore(opt, select.firstChild);
    }
  } catch (_) {
    select.innerHTML = `<option value="${valorActual || ''}">${valorActual || 'Error cargando modelos'}</option>`;
  }
}

async function loadConfig() {
  try {
    const { ok, data } = await window.bot.getConfig();
    if (!ok) return;
    const keys = [
      'OLLAMA_URL', 'LARAVEL_URL', 'LARAVEL_TOKEN',
      'ESPERA_MENSAJES', 'ESPERA_MAXIMA', 'RESET_CONVERSACION',
      'HORARIO_INICIO', 'HORARIO_FIN', 'HORARIO_SAB_FIN',
    ];
    keys.forEach((k) => {
      const el = document.getElementById(`cfg-${k}`);
      if (el && data[k] !== undefined) el.value = data[k];
    });
    // Cargar el select con el modelo actual
    await loadModelosSelect(data['OLLAMA_MODEL'] || '');
  } catch (_) {
    showToast('toastConfig', 'No se pudo cargar la configuración', 'error');
  }
}

document.getElementById('btnRefreshModelos').addEventListener('click', async () => {
  const select = document.getElementById('cfg-OLLAMA_MODEL');
  const valorActual = select.value;
  await loadModelosSelect(valorActual);
});

document.getElementById('btnSaveConfig').addEventListener('click', async () => {
  const keys = [
    'OLLAMA_MODEL', 'OLLAMA_URL', 'LARAVEL_URL', 'LARAVEL_TOKEN',
    'ESPERA_MENSAJES', 'ESPERA_MAXIMA', 'RESET_CONVERSACION',
    'HORARIO_INICIO', 'HORARIO_FIN', 'HORARIO_SAB_FIN',
  ];
  const data = {};
  keys.forEach((k) => {
    const el = document.getElementById(`cfg-${k}`);
    if (el) data[k] = el.value;
  });

  try {
    const res = await window.bot.saveConfig(data);
    showToast('toastConfig', res.mensaje || 'Guardado', res.ok ? 'info' : 'error');
  } catch (_) {
    showToast('toastConfig', 'Error al guardar', 'error');
  }
});

// ── Textos ────────────────────────────────────────────────
const TEXTOS_META = {
  BIENVENIDA:           'Bienvenida',
  PRIMERA_CONSULTA:     'Primera consulta → llamar',
  TURNO_PORTAL:         'Turno — portal Omnia',
  TURNO_ECO_CON_CUENTA: 'Turno ecografía — con cuenta',
  TURNO_ECO_SIN_CUENTA: 'Turno ecografía — sin cuenta (+ PDF)',
  TURNO_DGP:            'Turno DGP — pedir orden',
  TURNO_PRESERVACION:   'Preservación de fertilidad',
  TURNO_PRESUPUESTO:    'Presupuesto',
  RESULTADO_BETA:       'Resultado beta hCG',
  RESULTADO_OTROS:      'Otros resultados',
  MEDICACION_INSTRUCTIVO: 'Medicación — instructivo (+ PDF)',
  ORDEN_MDP:            'Orden — Mar del Plata',
  ORDEN_OTRA_CIUDAD:    'Orden — otra ciudad',
  CONSULTA_CLINICA:     'Consulta clínica / diagnóstico',
  DERIVAR_SECRETARIA:   'Derivar a secretaria',
  FALLBACK:             'No entendió',
  FUERA_HORARIO:        'Mensaje fuera de horario',
};

let textosData = {};

async function loadTextos() {
  try {
    const { ok, data } = await window.bot.getTextos();
    if (!ok) return;
    textosData = data;
    renderTextosForm(data);
  } catch (_) {
    document.getElementById('textosForm').textContent = 'No se pudo cargar los textos.';
  }
}

function renderTextosForm(data) {
  const container = document.getElementById('textosForm');
  container.innerHTML = '';
  Object.entries(TEXTOS_META).forEach(([code, label]) => {
    const div = document.createElement('div');
    div.className = 'texto-item';
    div.innerHTML = `
      <div class="texto-label">${label}</div>
      <div class="texto-code">${code}</div>
      <textarea id="txt-${code}" rows="4">${escapeHtml(data[code] || '')}</textarea>
    `;
    container.appendChild(div);
  });
}

document.getElementById('btnSaveTextos').addEventListener('click', async () => {
  const payload = { ...textosData };
  Object.keys(TEXTOS_META).forEach((code) => {
    const el = document.getElementById(`txt-${code}`);
    if (el) payload[code] = el.value;
  });
  try {
    const res = await window.bot.saveTextos(payload);
    showToast('toastTextos', res.mensaje || 'Guardado', res.ok ? 'ok' : 'error');
    if (res.ok) textosData = payload;
  } catch (_) {
    showToast('toastTextos', 'Error al guardar', 'error');
  }
});

// ── Usuarios ──────────────────────────────────────────────
const ROLES_LABEL = {
  secretaria: 'Secretaria', supervisora: 'Supervisora',
  admin: 'Administrativo', tecnico: 'Técnico',
};

const PERMISOS_LABELS = {
  secretaria: 'Cola recepción',
  atencion:   'Atención / Mis tareas',
  contactos:  'Contactos',
  agenda:     'Agenda',
  historial:  'Ver historial',
  admin:      'Administración',
};
const TODOS_PERMISOS = Object.keys(PERMISOS_LABELS);

let _usuariosData = [];

async function loadUsuarios() {
  const container = document.getElementById('usuariosList');
  try {
    const res = await window.bot.getUsuarios();
    if (!res.ok || !res.data.length) {
      container.textContent = 'Sin usuarios registrados.';
      return;
    }
    _usuariosData = res.data;
    renderUsuariosTabla();
  } catch (_) {
    container.textContent = 'No se pudo cargar la lista de usuarios.';
  }
}

function renderUsuariosTabla() {
  const container = document.getElementById('usuariosList');
  container.innerHTML = `
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="border-bottom:1px solid var(--border);color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">
          <th style="text-align:left;padding:8px 4px;">Nombre</th>
          <th style="text-align:left;padding:8px 4px;">Rol</th>
          <th style="text-align:left;padding:8px 4px;">Permisos</th>
          <th style="text-align:left;padding:8px 4px;">Estado</th>
          <th style="padding:8px 4px;"></th>
        </tr>
      </thead>
      <tbody>
        ${_usuariosData.map(u => `
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:10px 4px;">
              <div style="font-weight:500;">${u.nombre_completo}</div>
              <div style="font-size:11px;color:var(--text-muted);">${u.email}</div>
            </td>
            <td style="padding:10px 4px;">${ROLES_LABEL[u.rol] ?? u.rol}</td>
            <td style="padding:10px 4px;">
              <div style="display:flex;flex-wrap:wrap;gap:4px;">
                ${TODOS_PERMISOS.map(p => {
                  const activo = (u.permisos_efectivos || []).includes(p);
                  return `<span onclick="togglePermiso(${u.id},'${p}')"
                    data-perm-${u.id}-${p}
                    style="font-size:10px;padding:2px 7px;border-radius:10px;cursor:pointer;user-select:none;
                      ${activo
                        ? 'background:color-mix(in srgb,var(--accent) 15%,transparent);color:var(--accent);border:1px solid color-mix(in srgb,var(--accent) 35%,transparent);'
                        : 'background:var(--bg);color:var(--text-muted);border:1px solid var(--border);opacity:0.5;'}">
                    ${PERMISOS_LABELS[p]}
                  </span>`;
                }).join('')}
              </div>
            </td>
            <td style="padding:10px 4px;">
              <span style="font-size:11px;padding:2px 8px;border-radius:10px;
                ${u.activo
                  ? 'background:color-mix(in srgb,var(--success) 15%,transparent);color:var(--success);'
                  : 'background:color-mix(in srgb,var(--error) 15%,transparent);color:var(--error);'}">
                ${u.activo ? 'Activo' : 'Inactivo'}
              </span>
            </td>
            <td style="padding:10px 4px;text-align:right;white-space:nowrap;">
              <button onclick="toggleActivo(${u.id}, ${u.activo})"
                style="background:var(--card);border:1px solid var(--border);border-radius:4px;color:var(--text-muted);padding:4px 10px;font-size:11px;cursor:pointer;">
                ${u.activo ? 'Desactivar' : 'Activar'}
              </button>
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>`;
}

async function togglePermiso(userId, permiso) {
  const u = _usuariosData.find(x => x.id === userId);
  if (!u) return;

  const permisos = [...(u.permisos_efectivos || [])];
  const idx = permisos.indexOf(permiso);
  if (idx >= 0) permisos.splice(idx, 1);
  else permisos.push(permiso);

  try {
    const res = await window.bot.actualizarUsuario(userId, { permisos });
    if (res.ok) {
      u.permisos_efectivos = permisos;
      u.permisos = permisos;
      renderUsuariosTabla();
    } else {
      showToast('toastUsuarios', 'Error al guardar permiso', 'error');
    }
  } catch (_) {
    showToast('toastUsuarios', 'Error de conexión', 'error');
  }
}

document.getElementById('btnCrearUsuario').addEventListener('click', async () => {
  const payload = {
    nombre_completo: document.getElementById('usr-nombre').value.trim(),
    email:           document.getElementById('usr-email').value.trim(),
    password:        document.getElementById('usr-password').value,
    rol:             document.getElementById('usr-rol').value,
  };

  if (!payload.nombre_completo || !payload.email || !payload.password) {
    showToast('toastUsuarios', 'Completá todos los campos', 'error');
    return;
  }

  try {
    const res = await window.bot.crearUsuario(payload);
    if (res.ok) {
      showToast('toastUsuarios', 'Usuario creado', 'ok');
      document.getElementById('usr-nombre').value = '';
      document.getElementById('usr-email').value = '';
      document.getElementById('usr-password').value = '';
      loadUsuarios();
    } else {
      showToast('toastUsuarios', res.message || 'Error al crear', 'error');
    }
  } catch (_) {
    showToast('toastUsuarios', 'Error de conexión', 'error');
  }
});

async function toggleActivo(id, activo) {
  try {
    await window.bot.actualizarUsuario(id, { activo: !activo });
    loadUsuarios();
  } catch (_) {
    showToast('toastUsuarios', 'Error al actualizar', 'error');
  }
}

// ── Logs ──────────────────────────────────────────────────
function startLogs() {
  const box = document.getElementById('logsBox');
  logSSEClose = window.bot.streamLogs((line) => {
    const div = document.createElement('div');
    div.className = 'log-line' +
      (line.includes('[ERROR]') ? ' error' : line.includes('[WARN]') ? ' warn' : '');
    div.textContent = line;
    box.appendChild(div);
    if (document.getElementById('autoScroll').checked) {
      box.scrollTop = box.scrollHeight;
    }
  });
}

document.getElementById('btnClearLogs').addEventListener('click', () => {
  document.getElementById('logsBox').innerHTML = '';
});

// ── Helpers ───────────────────────────────────────────────
function showToast(id, msg, type) {
  const el = document.getElementById(id);
  el.textContent = msg;
  el.className = `toast ${type}`;
  setTimeout(() => { el.className = 'toast'; }, 5000);
}

function escapeHtml(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ── Pruebas ───────────────────────────────────────────────
const CONFIANZA_COLOR = { alta: 'var(--success)', media: '#e3a008', baja: 'var(--error)' };

function actualizarModoUI(modoPrueba) {
  const label = document.getElementById('pruebasModoLabel');
  const btn   = document.getElementById('btnToggleModo');
  if (modoPrueba) {
    label.innerHTML = '<strong style="color:var(--warning, #e3a008)">MODO PRUEBA ACTIVO</strong> — el bot no envía respuestas';
    btn.textContent = 'Activar respuestas automáticas';
    btn.className   = 'btn btn-primary';
  } else {
    label.innerHTML = '<strong style="color:var(--success)">MODO NORMAL</strong> — el bot responde automáticamente';
    btn.textContent = 'Activar modo prueba (silenciar bot)';
    btn.className   = 'btn btn-secondary';
  }
  btn.dataset.modo = modoPrueba ? 'true' : 'false';
}

function agregarFila(entry) {
  const tbody = document.getElementById('pruebasTbody');
  // Quitar fila placeholder si existe
  const placeholder = tbody.querySelector('tr td[colspan]');
  if (placeholder) placeholder.parentElement.remove();

  const hora = entry.ts ? new Date(entry.ts).toLocaleTimeString('es-AR', { hour12: false }) : '—';
  const contacto = entry.contacto ? entry.contacto.replace('@c.us', '') : '—';
  const texto = escapeHtml((entry.texto || '').slice(0, 80)) + (entry.texto?.length > 80 ? '…' : '');
  const color = CONFIANZA_COLOR[entry.confianza] || 'var(--text-muted)';

  const tr = document.createElement('tr');
  tr.style.borderBottom = '1px solid var(--border)';
  tr.innerHTML = `
    <td style="padding:8px 4px;white-space:nowrap;color:var(--text-muted);">${hora}</td>
    <td style="padding:8px 4px;font-family:monospace;font-size:11px;">${contacto}</td>
    <td style="padding:8px 4px;max-width:280px;">${texto}</td>
    <td style="padding:8px 4px;font-weight:600;font-size:11px;">${entry.codigo || '—'}</td>
    <td style="padding:8px 4px;color:${color};font-size:11px;">${entry.confianza || '—'}</td>
    <td style="padding:8px 4px;font-size:11px;">${entry.enHorario ? '✓' : '✗'}</td>
    <td style="padding:8px 4px;font-size:11px;">${entry.enviado ? '<span style="color:var(--success)">Sí</span>' : '<span style="color:var(--text-muted)">No</span>'}</td>
  `;

  tbody.prepend(tr);

  // Limitar a 200 filas visibles
  while (tbody.children.length > 200) tbody.lastChild.remove();
}

function startPruebas() {
  pruebasSSEClose = window.bot.streamClasificaciones(
    (entry) => agregarFila(entry),
    (modoPrueba) => actualizarModoUI(modoPrueba),
  );
}

document.getElementById('btnToggleModo').addEventListener('click', async () => {
  const actual = document.getElementById('btnToggleModo').dataset.modo === 'true';
  try {
    const res = await window.bot.setModoPrueba(!actual);
    actualizarModoUI(res.modoPrueba);
    showToast('toastPruebas',
      res.modoPrueba ? 'Modo prueba activado — sin respuestas automáticas' : 'Bot en modo normal — respuestas activadas',
      res.modoPrueba ? 'info' : 'ok',
    );
  } catch (_) {
    showToast('toastPruebas', 'Error al cambiar modo', 'error');
  }
});

document.getElementById('btnLimpiarPruebas').addEventListener('click', () => {
  const tbody = document.getElementById('pruebasTbody');
  tbody.innerHTML = '<tr><td colspan="7" style="padding:20px 4px;color:var(--text-muted);text-align:center;">Vista limpiada — esperando nuevas clasificaciones...</td></tr>';
});

// ── Sistema ───────────────────────────────────────────────

const SISTEMA_SERVICES = [
  { key: 'bot',    container: 'crecer-bot-1',    label: 'Bot WhatsApp',   icon: '◈', httpCheck: 'http://localhost:3001/status' },
  { key: 'web',    container: 'crecer-web-1',    label: 'Web / Laravel',  icon: '◫', httpCheck: null },
  { key: 'nginx',  container: 'crecer-nginx-1',  label: 'Nginx / HTTP',   icon: '⊹', httpCheck: 'http://localhost/login' },
  { key: 'mysql',  container: 'crecer-mysql-1',  label: 'Base de datos',  icon: '◧', httpCheck: null },
  { key: 'ollama', container: 'crecer-ollama-1', label: 'Ollama / IA',    icon: '⬡', httpCheck: 'http://localhost:11434/api/tags' },
];

let sistemaInterval = null;

function startSistemaPolling() {
  if (sistemaInterval) return;
  sistemaInterval = setInterval(loadSistema, 12000);
}

function stopSistemaPolling() {
  if (sistemaInterval) { clearInterval(sistemaInterval); sistemaInterval = null; }
}

// ── Modelos Ollama (sección en tab Sistema) ───────────────

async function loadModelosList() {
  const container = document.getElementById('modelosList');
  const { models } = await window.sistema.getOllamaModels().catch(() => ({ models: [] }));

  if (!models || models.length === 0) {
    container.innerHTML = '<div style="font-size:13px;color:var(--text-muted);">Sin modelos instalados.</div>';
    return;
  }

  container.innerHTML = `
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="border-bottom:1px solid var(--border);color:var(--text-muted);font-size:10px;text-transform:uppercase;letter-spacing:0.5px;">
          <th style="text-align:left;padding:6px 4px;">Nombre</th>
          <th style="text-align:left;padding:6px 4px;">Tamaño</th>
          <th style="text-align:left;padding:6px 4px;">Modificado</th>
          <th style="padding:6px 4px;"></th>
        </tr>
      </thead>
      <tbody>
        ${models.map((m) => {
          const gb = m.size ? (m.size / 1e9).toFixed(1) + ' GB' : '—';
          const fecha = m.modified_at ? new Date(m.modified_at).toLocaleDateString('es-AR') : '—';
          return `
            <tr style="border-bottom:1px solid var(--border);">
              <td style="padding:9px 4px;font-weight:500;font-family:monospace;font-size:12px;">${escapeHtml(m.name)}</td>
              <td style="padding:9px 4px;color:var(--text-muted);font-size:12px;">${gb}</td>
              <td style="padding:9px 4px;color:var(--text-muted);font-size:12px;">${fecha}</td>
              <td style="padding:9px 4px;text-align:right;">
                <button onclick="confirmarEliminarModelo('${escapeHtml(m.name)}')"
                  style="background:transparent;border:1px solid rgba(248,81,73,0.3);border-radius:4px;color:var(--error);padding:3px 10px;font-size:11px;cursor:pointer;">
                  Eliminar
                </button>
              </td>
            </tr>`;
        }).join('')}
      </tbody>
    </table>`;
}

function confirmarEliminarModelo(name) {
  if (!confirm(`¿Eliminar el modelo "${name}"? Se puede volver a bajar después.`)) return;
  eliminarModelo(name);
}

async function eliminarModelo(name) {
  showToast('toastModelos', `Eliminando ${name}...`, 'info');
  const res = await window.sistema.eliminarModelo(name);
  setSistemaOutput(res);
  showToast('toastModelos', res.ok ? `${name} eliminado` : 'Error al eliminar', res.ok ? 'ok' : 'error');
  loadModelosList();
  // Refrescar select en Config si está visible
  if (currentTab === 'config') {
    const select = document.getElementById('cfg-OLLAMA_MODEL');
    loadModelosSelect(select.value);
  }
}

document.getElementById('btnPullModelo').addEventListener('click', async () => {
  const input = document.getElementById('pullModeloInput');
  const model = input.value.trim();
  if (!model) {
    showToast('toastModelos', 'Escribí el nombre del modelo', 'error');
    return;
  }

  const btn = document.getElementById('btnPullModelo');
  const statusDiv = document.getElementById('pullStatus');
  btn.disabled = true;
  btn.textContent = 'Bajando...';
  statusDiv.style.display = 'block';
  statusDiv.textContent = `Bajando ${model}... puede tardar varios minutos según el tamaño y la conexión.`;

  const res = await window.sistema.pullModelo(model);
  setSistemaOutput(res);

  btn.disabled = false;
  btn.textContent = '↓ Bajar modelo';
  statusDiv.style.display = 'none';

  if (res.ok) {
    showToast('toastModelos', `${model} instalado correctamente`, 'ok');
    input.value = '';
    loadModelosList();
    // Refrescar select en Config
    const select = document.getElementById('cfg-OLLAMA_MODEL');
    loadModelosSelect(select?.value || model);
  } else {
    showToast('toastModelos', `Error: ${res.stderr || 'No se pudo bajar el modelo'}`, 'error');
  }
});

// ─────────────────────────────────────────────────────────

async function loadSistema() {
  const grid = document.getElementById('sistemaGrid');

  // Obtener estado Docker y modelos Ollama en paralelo
  const [dockerResult, ollamaResult] = await Promise.all([
    window.sistema.getDockerStatus().catch(() => ({ ok: false, error: 'No se pudo conectar a Docker' })),
    window.sistema.getOllamaModels().catch(() => ({ models: [] })),
    loadModelosList(),
  ]);

  if (!dockerResult.ok && !dockerResult.containers) {
    grid.innerHTML = `<div style="color:var(--error);font-size:13px;padding:8px 0;">
      Docker no disponible: ${escapeHtml(dockerResult.error || 'Error desconocido')}
    </div>`;
    return;
  }

  const containerMap = {};
  (dockerResult.containers || []).forEach((c) => {
    containerMap[c.Names] = c;
  });

  // HTTP checks en paralelo
  const httpResults = {};
  await Promise.all(
    SISTEMA_SERVICES
      .filter((s) => s.httpCheck)
      .map(async (s) => {
        try {
          const res = await fetch(s.httpCheck, { signal: AbortSignal.timeout(3000) });
          httpResults[s.key] = res.ok || res.status < 500;
        } catch {
          httpResults[s.key] = false;
        }
      })
  );

  const ollamaModels = (ollamaResult.models || []).map((m) => m.name);

  // Renderizar como lista de filas (sin grilla)
  grid.innerHTML = '';
  SISTEMA_SERVICES.forEach((svc) => {
    const c = containerMap[svc.container];
    const state = c ? c.State : 'ausente';
    const isRunning = state === 'running';
    const uptime = c ? c.Status : 'No encontrado';

    const dotClass = isRunning ? 'running' : (state === 'exited' ? 'stopped' : 'absent');

    let stateBadge = '';
    if (isRunning)          stateBadge = `<span class="svc-badge running">En ejecución</span>`;
    else if (state === 'exited')  stateBadge = `<span class="svc-badge stopped">Detenido</span>`;
    else                    stateBadge = `<span class="svc-badge absent">No encontrado</span>`;

    let httpBadge = '';
    if (svc.httpCheck !== null) {
      if (httpResults[svc.key] === true)       httpBadge = `<span class="svc-badge http-ok">HTTP ✓</span>`;
      else if (httpResults[svc.key] === false)  httpBadge = `<span class="svc-badge http-fail">HTTP ✗</span>`;
    }

    let subText = escapeHtml(uptime);
    if (svc.key === 'ollama' && ollamaModels.length > 0) {
      subText += ` · ${ollamaModels.map(escapeHtml).join(', ')}`;
    } else if (svc.key === 'ollama' && isRunning) {
      subText += ' · Sin modelos cargados';
    }

    const row = document.createElement('div');
    row.className = 'svc-row';
    row.innerHTML = `
      <span class="svc-dot ${dotClass}"></span>
      <div class="svc-info">
        <div class="svc-name">${svc.label}</div>
        <div class="svc-sub">${subText}</div>
      </div>
      <div class="svc-badges">${stateBadge}${httpBadge}</div>
      <button class="btn btn-secondary svc-restart-btn"
        data-service="${svc.key}"
        ${!c ? 'disabled' : ''}
        style="padding:4px 12px;font-size:11px;flex-shrink:0;">
        ↻ Reiniciar
      </button>
    `;
    grid.appendChild(row);
  });

  // Bind reiniciar buttons
  grid.querySelectorAll('.svc-restart-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const svc = btn.dataset.service;
      btn.disabled = true;
      btn.textContent = 'Reiniciando...';
      const res = await window.sistema.reiniciarServicio(svc);
      setSistemaOutput(res);
      showToast('toastSistema', res.ok ? `${svc} reiniciado` : 'Error al reiniciar', res.ok ? 'ok' : 'error');
      setTimeout(loadSistema, 2000);
    });
  });
}

function setSistemaOutput(res) {
  const el = document.getElementById('sistemaOutput');
  const txt = [res.stdout, res.stderr].filter(Boolean).join('\n').trim() || (res.ok ? 'OK' : 'Sin salida');
  el.textContent = txt;
  el.style.color = res.ok ? 'var(--text-muted)' : 'var(--error)';
}

document.getElementById('btnRefreshSistema').addEventListener('click', loadSistema);

// Iniciar todo
document.getElementById('btnIniciarTodo').addEventListener('click', async () => {
  const btn = document.getElementById('btnIniciarTodo');
  btn.disabled = true;
  btn.textContent = 'Iniciando...';
  showToast('toastSistema', 'Ejecutando docker compose up -d…', 'info');
  const res = await window.sistema.iniciarTodo();
  setSistemaOutput(res);
  showToast('toastSistema', res.ok ? 'Todos los servicios iniciados' : 'Error al iniciar', res.ok ? 'ok' : 'error');
  btn.disabled = false;
  btn.textContent = '▶ Iniciar todo';
  setTimeout(loadSistema, 3000);
});

// Detener todo — con confirmación
document.getElementById('btnDetenerTodo').addEventListener('click', () => {
  document.getElementById('sistemaConfirm').classList.remove('hidden');
});

document.getElementById('btnCancelarDetener').addEventListener('click', () => {
  document.getElementById('sistemaConfirm').classList.add('hidden');
});

document.getElementById('btnConfirmarDetener').addEventListener('click', async () => {
  document.getElementById('sistemaConfirm').classList.add('hidden');
  const btn = document.getElementById('btnDetenerTodo');
  btn.disabled = true;
  btn.textContent = 'Deteniendo...';
  showToast('toastSistema', 'Ejecutando docker compose down…', 'info');
  const res = await window.sistema.detenerTodo();
  setSistemaOutput(res);
  showToast('toastSistema', res.ok ? 'Todos los servicios detenidos' : 'Error al detener', res.ok ? 'ok' : 'error');
  btn.disabled = false;
  btn.textContent = '■ Detener todo';
  setTimeout(loadSistema, 2000);
});
