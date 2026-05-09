const { app, BrowserWindow, Menu, ipcMain, shell } = require('electron');
const { exec } = require('child_process');
const path = require('path');

// Directorio raíz del proyecto Crecer (donde está docker-compose.yml)
const CRECER_DIR = process.env.CRECER_DIR || 'C:\\crecer';

function createWindow() {
  const win = new BrowserWindow({
    width: 1100,
    height: 720,
    minWidth: 800,
    minHeight: 600,
    title: 'Crecer Bot — Panel',
    backgroundColor: '#0d1117',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  });

  win.loadFile(path.join(__dirname, 'index.html'));

  // Ocultar menú en producción
  Menu.setApplicationMenu(null);
}

// ── Shell ────────────────────────────────────────────────────
ipcMain.handle('shell:openExternal', (_, url) => shell.openExternal(url));

// ── Docker IPC handlers ──────────────────────────────────────

function runDocker(cmd) {
  return new Promise((resolve) => {
    exec(cmd, { cwd: CRECER_DIR, timeout: 60000 }, (err, stdout, stderr) => {
      resolve({
        ok: !err,
        stdout: (stdout || '').trim(),
        stderr: (stderr || err?.message || '').trim(),
      });
    });
  });
}

// Estado de contenedores
ipcMain.handle('docker:status', async () => {
  const result = await runDocker('docker ps -a --filter "name=crecer-" --format "{{json .}}"');
  if (!result.ok && !result.stdout) {
    return { ok: false, error: result.stderr || 'Docker no disponible' };
  }
  try {
    const containers = result.stdout
      .split('\n')
      .filter(Boolean)
      .map((line) => JSON.parse(line));
    return { ok: true, containers };
  } catch (e) {
    return { ok: false, error: `Error parseando salida: ${e.message}` };
  }
});

// Iniciar todos los servicios
ipcMain.handle('docker:up', async () => {
  return runDocker('docker compose up -d');
});

// Detener todos los servicios
ipcMain.handle('docker:down', async () => {
  return runDocker('docker compose down');
});

// Reiniciar un servicio individual
ipcMain.handle('docker:restart', async (_, service) => {
  return runDocker(`docker compose restart ${service}`);
});

// Bajar un modelo de Ollama (puede tardar varios minutos)
ipcMain.handle('ollama:pull', async (_, model) => {
  return new Promise((resolve) => {
    exec(
      `docker exec crecer-ollama-1 ollama pull ${model}`,
      { cwd: CRECER_DIR, timeout: 3600000 }, // 1 hora máximo
      (err, stdout, stderr) => {
        resolve({
          ok: !err,
          stdout: (stdout || '').trim(),
          stderr: (stderr || err?.message || '').trim(),
        });
      }
    );
  });
});

// Eliminar un modelo de Ollama
ipcMain.handle('ollama:rm', async (_, model) => {
  return new Promise((resolve) => {
    exec(
      `docker exec crecer-ollama-1 ollama rm ${model}`,
      { cwd: CRECER_DIR, timeout: 30000 },
      (err, stdout, stderr) => {
        resolve({
          ok: !err,
          stdout: (stdout || '').trim(),
          stderr: (stderr || err?.message || '').trim(),
        });
      }
    );
  });
});

// ── App lifecycle ────────────────────────────────────────────

app.whenReady().then(() => {
  createWindow();
  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});
