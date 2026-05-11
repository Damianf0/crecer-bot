const { contextBridge, ipcRenderer } = require('electron');

// Un bot por área (= número de WhatsApp). El de 'atencion' es el original.
const BOT_PORTS    = { atencion: 3001, administracion: 3002, ovodonacion: 3003 };
const AREA_LABELS  = { atencion: 'Tel clínica', administracion: 'Tel administración', ovodonacion: 'Tel ovodonación' };
// Cada área es un servicio de docker-compose distinto: bot / bot-administracion / bot-ovodonacion.
const AREA_SERVICE = { atencion: 'bot', administracion: 'bot-administracion', ovodonacion: 'bot-ovodonacion' };
const botUrlFor    = (area) => `http://localhost:${BOT_PORTS[area] || 3001}`;
const BOT_URL = botUrlFor('atencion');   // alias: config/textos/usuarios siguen usando el de atención (archivos compartidos)
// Reemplazar por el token real (mismo valor que BOT_INGRESS_TOKEN en bot/.env).
// Generar uno con: openssl rand -hex 32
const BOT_INGRESS_TOKEN = '<<BOT_INGRESS_TOKEN>>';

const authHeaders = (extra = {}) => ({
  Authorization: `Bearer ${BOT_INGRESS_TOKEN}`,
  ...extra,
});

const tokenQuery = `?token=${encodeURIComponent(BOT_INGRESS_TOKEN)}`;

contextBridge.exposeInMainWorld('bot', {
  AREA_LABELS,
  AREA_SERVICE,
  areas: Object.keys(BOT_PORTS),

  async getStatus() {
    const res = await fetch(`${BOT_URL}/status`);
    return res.json();
  },

  // Estado de los 3 bots. { atencion: {...}|null, administracion: {...}|null, ovodonacion: {...}|null }
  async statusAll() {
    const entries = await Promise.all(Object.keys(BOT_PORTS).map(async (area) => {
      try {
        const res = await fetch(`${botUrlFor(area)}/status`, { signal: AbortSignal.timeout(4000) });
        return [area, res.ok ? await res.json() : null];
      } catch {
        return [area, null];
      }
    }));
    return Object.fromEntries(entries);
  },

  async getConfig() {
    const res = await fetch(`${BOT_URL}/config`, { headers: authHeaders() });
    return res.json();
  },

  async saveConfig(data) {
    const res = await fetch(`${BOT_URL}/config`, {
      method: 'POST',
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(data),
    });
    return res.json();
  },

  async getTextos() {
    const res = await fetch(`${BOT_URL}/textos`, { headers: authHeaders() });
    return res.json();
  },

  async saveTextos(data) {
    const res = await fetch(`${BOT_URL}/textos`, {
      method: 'POST',
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(data),
    });
    return res.json();
  },

  async getUsuarios() {
    const res = await fetch(`${BOT_URL}/usuarios`, { headers: authHeaders() });
    return res.json();
  },

  async crearUsuario(data) {
    const res = await fetch(`${BOT_URL}/usuarios`, {
      method: 'POST',
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(data),
    });
    return res.json();
  },

  async actualizarUsuario(id, data) {
    const res = await fetch(`${BOT_URL}/usuarios/${id}`, {
      method: 'PATCH',
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(data),
    });
    return res.json();
  },

  streamLogs(onLine) {
    const es = new EventSource(`${BOT_URL}/logs${tokenQuery}`);
    es.onmessage = (e) => onLine(e.data);
    es.onerror = () => {};
    return () => es.close();
  },

  async getPruebas() {
    const res = await fetch(`${BOT_URL}/pruebas`, { headers: authHeaders() });
    return res.json();
  },

  streamClasificaciones(onEntry, onModo) {
    const es = new EventSource(`${BOT_URL}/pruebas/stream${tokenQuery}`);
    es.addEventListener('clasificacion', (e) => onEntry(JSON.parse(e.data)));
    es.addEventListener('modo', (e) => onModo(JSON.parse(e.data).modoPrueba));
    es.onerror = () => {};
    return () => es.close();
  },

  async setModoPrueba(valor) {
    const res = await fetch(`${BOT_URL}/pruebas/modo`, {
      method: 'POST',
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ modoPrueba: valor }),
    });
    return res.json();
  },
});

contextBridge.exposeInMainWorld('sistema', {
  getDockerStatus() {
    return ipcRenderer.invoke('docker:status');
  },
  iniciarTodo() {
    return ipcRenderer.invoke('docker:up');
  },
  detenerTodo() {
    return ipcRenderer.invoke('docker:down');
  },
  reiniciarServicio(service) {
    return ipcRenderer.invoke('docker:restart', service);
  },
  async getOllamaModels() {
    try {
      const res = await fetch('http://localhost:11434/api/tags');
      return res.json();
    } catch {
      return { models: [] };
    }
  },
  abrirURL(url) {
    return ipcRenderer.invoke('shell:openExternal', url);
  },
  pullModelo(model) {
    return ipcRenderer.invoke('ollama:pull', model);
  },
  eliminarModelo(model) {
    return ipcRenderer.invoke('ollama:rm', model);
  },
});
