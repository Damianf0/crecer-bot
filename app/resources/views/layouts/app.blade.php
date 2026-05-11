<!DOCTYPE html>
<html lang="es" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crecer — {{ $title ?? 'Panel' }}</title>
    @livewireStyles
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
    <script>
        // Aplica el tema ANTES de renderizar para evitar flash
        (function() {
            if (localStorage.getItem('tema') === 'dark') {
                document.getElementById('html-root').classList.add('dark');
            }
        })();
    </script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── Tema claro (default) ── */
        :root {
            --accent: #C0273A;
            --accent-dark: #8C1B29;
            --bg: #e2e4e8;
            --surface: #d4d7de;
            --card: #f7f8fa;
            --border: #c0c4cc;
            --text: #0d1220;
            --muted: #3d4d66;
            --success: #00875a;
            --warning: #c96a00;
            --error: #cc1f2e;
            --info: #1a56c4;
        }

        /* ── Tema oscuro ── */
        html.dark {
            --bg: #09090f;
            --surface: #0e0e18;
            --card: #14141f;
            --border: #22223a;
            --text: #e2e2f0;
            --muted: #64648a;
            --success: #00e676;
            --warning: #ffab40;
            --error: #ff5252;
            --info: #40c4ff;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 16px;
            min-height: 100vh;
        }

        nav {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 16px;
            display: flex;
            align-items: center;
            gap: 0;
            height: 52px;
            overflow-x: auto;
            overflow-y: hidden;
        }

        .nav-brand {
            display: inline-flex;
            align-items: center;
            margin-right: 16px;
            flex-shrink: 0;
        }

        .nav-brand img {
            height: 44px;
            width: 44px;
            border-radius: 6px;
            object-fit: cover;
            display: block;
        }

        .nav-link {
            display: inline-flex;
            align-items: center;
            padding: 0 11px;
            height: 52px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            transition: color 0.15s, border-color 0.15s;
        }

        .nav-link:hover { color: var(--text); }
        .nav-link.active { color: var(--text); border-bottom-color: var(--accent); }

        main { padding: 24px; max-width: 1400px; margin: 0 auto; }

        /* Cards */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
        }

        /* Toast */
        .toast-fixed {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: none;
            animation: fadeIn 0.2s;
        }

        .toast-fixed.ok    { background: rgba(63,185,80,0.15); color: var(--success); border: 1px solid rgba(63,185,80,0.3); display: block; }
        .toast-fixed.error { background: rgba(248,81,73,0.15); color: var(--error);   border: 1px solid rgba(248,81,73,0.3); display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

        .sortable-ghost { opacity: 0.35; }
        .drag-handle:active { cursor: grabbing; }
    </style>
    @stack('styles')
</head>
<body>

<nav>
    <a href="/" class="nav-brand" title="Crecer — Centro de Reproducción y Genética Humana">
        <img src="/logo.jpg" alt="Crecer">
    </a>
    @php
        // Counts del navbar cacheados 5s — evita 3 COUNTs por cada render de cualquier página.
        // Cache por usuario (clave incluye uid). Se invalida automáticamente o se puede forzar:
        // \Illuminate\Support\Facades\Cache::forget("navbar.counts.{$uid}")
        $uid = auth()->id() ?? 0;
        [$misTareas, $pendientesAtencion] = \Illuminate\Support\Facades\Cache::remember(
            "navbar.counts.{$uid}",
            5,
            fn() => [
                \App\Models\Derivacion::where('estado','en_atencion')->where('asignada_a', $uid)->count()
                    + \App\Models\ConversacionWA::where('estado','activa')->where('asignada_a', $uid)->count(),
                \App\Models\ConversacionWA::where('estado','activa')->whereNull('asignada_a')->where('no_leidos','>',0)->count(),
            ]
        );
    @endphp
    @auth @if(auth()->user()->hasPermiso('medico'))
    <a href="/medico" class="nav-link {{ request()->is('medico*') ? 'active' : '' }}">Mi consultorio</a>
    @endif @endauth
    @auth @if(auth()->user()->hasPermiso('secretaria'))
    <a href="/secretaria" class="nav-link {{ request()->is('secretaria*') ? 'active' : '' }}">Recepción</a>
    @endif @endauth
    @auth @if(auth()->user()->hasPermiso('atencion'))
    <a href="/atencion" class="nav-link {{ request()->is('atencion*') ? 'active' : '' }}" style="display:inline-flex;align-items:center;gap:6px;">
        Atención WApp
        @if($pendientesAtencion > 0)
            <span style="background:var(--accent);color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;">{{ $pendientesAtencion }}</span>
        @endif
    </a>
    <a href="/mis-tareas" class="nav-link {{ request()->is('mis-tareas*') ? 'active' : '' }}" style="display:inline-flex;align-items:center;gap:6px;">
        Mis tareas
        @if($misTareas > 0)
            <span style="background:var(--info);color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;">{{ $misTareas }}</span>
        @endif
    </a>
    @endif @endauth
    @auth @if(auth()->user()->hasPermiso('contactos'))
    <a href="/contactos" class="nav-link {{ request()->is('contactos*') ? 'active' : '' }}">Contactos</a>
    @endif @endauth
    @auth
    @if(auth()->user()->hasPermiso('historial'))
    <a href="/historial" class="nav-link {{ request()->is('historial*') ? 'active' : '' }}">Historial</a>
    @endif
    @endauth

    @auth
    @if(auth()->user()->hasPermiso('admin'))
    <a href="/admin" class="nav-link {{ request()->is('admin*') ? 'active' : '' }}">Admin</a>
    @endif
    <a id="bot-pulso" href="{{ auth()->user()->hasPermiso('admin') ? '/admin' : '#' }}"
       title="Estado del bot WhatsApp"
       style="display:inline-flex;align-items:center;gap:6px;padding:0 11px;height:52px;font-size:12px;color:var(--muted);text-decoration:none;border-bottom:2px solid transparent;transition:color .15s;">
        <span id="bot-pulso-dot" style="width:9px;height:9px;border-radius:50%;background:var(--muted);transition:.15s;"></span>
        <span id="bot-pulso-txt">Bot…</span>
    </a>
    @endauth
    <button id="tema-btn" onclick="toggleTema()"
        style="margin-left:auto;background:none;border:1px solid var(--border);border-radius:6px;
               color:var(--muted);cursor:pointer;font-size:15px;padding:4px 9px;flex-shrink:0;
               transition:.15s;line-height:1;"
        title="Cambiar tema"></button>

    <div style="display:flex;align-items:center;gap:0;flex-shrink:0;">
        @auth
            <span style="display:inline-flex;align-items:center;gap:7px;padding:0 10px;border-left:1px solid var(--border);">
                <span style="width:26px;height:26px;border-radius:50%;background:var(--accent);color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;"
                      title="{{ auth()->user()->nombre_completo }}{{ session('colas') ? ' · '.implode(', ', array_map(fn($c) => \App\Models\User::COLAS[$c] ?? $c, session('colas', []))) : '' }}">
                    {{ mb_strtoupper(mb_substr(auth()->user()->nombre_completo, 0, 1)) }}
                </span>
                <span style="font-size:12px;color:var(--text);font-weight:500;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    {{ explode(' ', auth()->user()->nombre_completo)[0] }}
                </span>
            </span>
            @if(auth()->user()->hasPermiso('secretaria'))
            <a href="/declarar-colas" class="nav-link" title="Cambiar mis colas" style="font-size:12px;padding:0 8px;">⇄</a>
            @endif
            <a href="/logout" class="nav-link" onclick="return confirm('¿Cerrar sesión?');" style="color:var(--muted);font-size:12px;padding:0 8px;">Salir</a>
        @endauth
    </div>
</nav>

<main>
    {{ $slot ?? '' }}
    @yield('content')
</main>

@include('chat._widget')

@livewireScripts

<script>
// ── Notificaciones del navegador (Notification API) ─────────────
// Módulo global window.Notify. Por defecto activado: pide permiso al primer
// poll y, si el usuario lo concede, dispara notificaciones cuando llegan
// mensajes de chat interno o se delegan conversaciones al usuario.
// Solo notifica si la pestaña no está visible (document.hidden).
window.Notify = (function () {
    const STORAGE_OFF = 'notify_off';   // por si más adelante agregamos toggle
    let permisoPedido = false;

    function activado() {
        return localStorage.getItem(STORAGE_OFF) !== '1';
    }

    async function pedirPermiso() {
        if (!('Notification' in window)) return false;
        if (Notification.permission === 'granted') return true;
        if (Notification.permission === 'denied') return false;
        if (permisoPedido) return false;
        permisoPedido = true;
        try {
            const p = await Notification.requestPermission();
            return p === 'granted';
        } catch { return false; }
    }

    /**
     * Dispara una notificación.
     * @param {object} opts
     * @param {string} opts.titulo
     * @param {string} opts.cuerpo
     * @param {string} [opts.tag]   id estable para reemplazar la notif anterior del mismo tema
     * @param {string} [opts.url]   click → navegar acá
     * @param {boolean} [opts.soloOculto=false]  si true, no notifica cuando la pestaña está visible (úsalo para chat: si el usuario está activo no molestar; para delegaciones conviene notificar siempre).
     */
    function disparar({ titulo, cuerpo, tag, url, soloOculto = false }) {
        if (!activado()) return;
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        if (soloOculto && !document.hidden) return;

        try {
            const n = new Notification(titulo, {
                body: cuerpo,
                tag: tag || undefined,
                renotify: !!tag,
                silent: true,
            });
            n.onclick = () => {
                window.focus();
                if (url) window.location.href = url;
                n.close();
            };
            // Auto-cerrar a los 8s para no acumular
            setTimeout(() => n.close(), 8000);
        } catch {}
    }

    return { activado, pedirPermiso, disparar };
})();

// Al cargar, pedir permiso si todavía no se decidió. La API exige llamarlo
// desde un user gesture en algunos browsers; si falla en el load, se reintenta
// al primer click en cualquier botón.
document.addEventListener('DOMContentLoaded', () => {
    if (window.Notify.activado()) {
        window.Notify.pedirPermiso();
        document.addEventListener('click', () => window.Notify.pedirPermiso(), { once: true });
    }
});

function toggleTema() {
    const html = document.getElementById('html-root');
    const esDark = html.classList.toggle('dark');
    localStorage.setItem('tema', esDark ? 'dark' : 'light');
    actualizarBotonTema();
}

function actualizarBotonTema() {
    const btn = document.getElementById('tema-btn');
    if (!btn) return;
    const esDark = document.getElementById('html-root').classList.contains('dark');
    btn.textContent = esDark ? '☀' : '🌙';
    btn.title = esDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro';
}

document.addEventListener('DOMContentLoaded', actualizarBotonTema);

// ── Pulso del bot WhatsApp (badge en navbar) ────────────────────
async function actualizarPulsoBot() {
    const dot = document.getElementById('bot-pulso-dot');
    const txt = document.getElementById('bot-pulso-txt');
    if (!dot) return;
    try {
        const r = await fetch('/bot-pulso', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const d = await r.json();
        const e = d.estado;
        if (e === 'listo') {
            dot.style.background = 'var(--success)';
            dot.style.boxShadow  = '0 0 0 3px color-mix(in srgb, var(--success) 22%, transparent)';
            txt.textContent = 'Bot OK';
            txt.style.color = 'var(--success)';
        } else if (d.has_qr || e === 'iniciando' || e === 'autenticado') {
            dot.style.background = 'var(--warning)';
            dot.style.boxShadow  = '0 0 0 3px color-mix(in srgb, var(--warning) 22%, transparent)';
            txt.textContent = d.has_qr ? 'Bot esperando QR' : 'Bot iniciando';
            txt.style.color = 'var(--warning)';
        } else {
            dot.style.background = 'var(--error)';
            dot.style.boxShadow  = '0 0 0 3px color-mix(in srgb, var(--error) 22%, transparent)';
            txt.textContent = 'Bot caído';
            txt.style.color = 'var(--error)';
        }
    } catch {
        dot.style.background = 'var(--muted)';
        txt.textContent = 'Bot ?';
    }
}
actualizarPulsoBot();
setInterval(actualizarPulsoBot, 15000);

document.addEventListener('livewire:initialized', () => {
    Livewire.on('toast', () => {
        // El toast se muestra via Livewire properties, se oculta tras 4s
        setTimeout(() => {
            document.querySelectorAll('.toast-fixed').forEach(el => {
                el.classList.remove('ok', 'error');
            });
        }, 4000);
    });
});
</script>

</body>
</html>
