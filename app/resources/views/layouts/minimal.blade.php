<!DOCTYPE html>
{{-- Layout de las pantallas gate (login + declarar colas), fuera del shell.
     Restyleado al design system V2 (2026-06-30): carga crecer-v2.css por sus
     tokens + fuente Inter + puente de variables, y comparte el tema con V2
     (data-theme + localStorage 'v2-theme'). Así el flujo login → declarar colas
     → app es visualmente consistente con V2. --}}
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crecer — {{ $title ?? '' }}</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    @livewireStyles
    <script>
        // Tema antes del CSS para evitar flash. Misma clave que el shell V2.
        document.documentElement.dataset.theme = localStorage.getItem('v2-theme') || 'light';
    </script>
    <link rel="stylesheet" href="/css/crecer-v2.css?v={{ filemtime(public_path('css/crecer-v2.css')) }}">
    <style>
        /* El puente de crecer-v2.css deja --card = bg de la app (para el chat).
           Acá queremos tarjetas con contraste, así que lo reapuntamos al card V2. */
        :root { --card: var(--v2-bg-card); }

        body {
            background: var(--v2-bg-app);
            color: var(--v2-text);
            min-height: 100vh;
            overflow-y: auto;            /* crecer-v2.css pone overflow:hidden */
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .wrap { width: 100%; max-width: 440px; }

        .brand { text-align: center; margin-bottom: 24px; }
        .brand-logo { height: 40px; width: auto; margin: 0 auto 10px; display: block; border-radius: 8px; }
        .brand-name { font-size: 19px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--v2-accent); }
        .brand-sub  { font-size: 12px; color: var(--v2-text-mute); margin-top: 4px; }

        .card {
            background: var(--v2-bg-card);
            border: 1px solid var(--v2-border);
            border-radius: var(--v2-radius);
            padding: 28px;
        }
        .card-title { font-size: 16px; font-weight: 650; margin-bottom: 22px; color: var(--v2-text); }

        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; color: var(--v2-text-2); margin-bottom: 6px; font-weight: 500; }
        input[type=email], input[type=password], input[type=text] {
            width: 100%; background: var(--v2-bg-app); border: 1px solid var(--v2-border);
            border-radius: var(--v2-radius-sm); color: var(--v2-text);
            padding: 10px 12px; font-size: 14px; font-family: inherit;
            transition: border-color 0.15s;
        }
        input:focus { outline: none; border-color: var(--v2-accent); }
        input::placeholder { color: var(--v2-text-mute); }

        .error-msg {
            background: var(--v2-urg-bg);
            border: 1px solid color-mix(in srgb, var(--v2-urg) 35%, transparent);
            color: var(--v2-urg);
            border-radius: var(--v2-radius-sm); padding: 10px 14px; font-size: 13px; margin-bottom: 16px;
        }

        .btn {
            width: 100%; padding: 11px; border-radius: var(--v2-radius-sm); font-size: 14px; font-weight: 600;
            cursor: pointer; border: 1px solid var(--v2-accent-solid);
            background: var(--v2-accent-solid); color: #fff;
            transition: opacity 0.15s; margin-top: 4px;
        }
        .btn:hover { opacity: 0.85; }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn-ghost {
            background: var(--v2-bg-card); border: 1px solid var(--v2-border); color: var(--v2-text); margin-top: 10px;
        }
        .btn-ghost:hover { background: var(--v2-bg-hover); opacity: 1; }

        /* Selección de colas (declarar-colas) */
        .colas-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        .cola-item {
            background: var(--v2-bg-card); border: 1px solid var(--v2-border); border-radius: var(--v2-radius);
            padding: 14px 12px; cursor: pointer; transition: border-color 0.15s, background 0.15s;
            font-size: 13px; font-weight: 500; text-align: center; line-height: 1.3; color: var(--v2-text);
        }
        .cola-item:hover { border-color: var(--v2-border-strong); background: var(--v2-bg-hover); }
        .cola-item.selected {
            border-color: var(--v2-accent); background: var(--v2-accent-bg); color: var(--v2-accent);
        }
        .cola-icon { font-size: 22px; display: block; margin-bottom: 6px; }

        .usuario-badge {
            display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding: 12px;
            background: var(--v2-bg-app); border-radius: var(--v2-radius-sm); border: 1px solid var(--v2-border);
        }
        .usuario-avatar {
            width: 36px; height: 36px; background: var(--v2-accent-solid); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 14px; color: #fff; flex-shrink: 0;
        }
        .usuario-nombre { font-size: 14px; font-weight: 600; }
        .usuario-rol { font-size: 11px; color: var(--v2-text-2); }

        /* Toggle de tema, fixed bottom-right */
        .tema-toggle {
            position: fixed; bottom: 16px; right: 16px;
            background: var(--v2-bg-card); border: 1px solid var(--v2-border); color: var(--v2-text-2);
            width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 16px;
            display: flex; align-items: center; justify-content: center;
            transition: color 0.15s, border-color 0.15s;
        }
        .tema-toggle:hover { color: var(--v2-text); border-color: var(--v2-border-strong); }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <img class="brand-logo" src="/logo.jpg" alt="Crecer">
            <div class="brand-name">Crecer Reproducción</div>
            <div class="brand-sub">Sistema de gestión operativa</div>
        </div>
        {{ $slot }}
    </div>

    <button class="tema-toggle" onclick="(function(){var r=document.documentElement;var d=r.dataset.theme==='dark'?'light':'dark';r.dataset.theme=d;localStorage.setItem('v2-theme',d);})()" title="Cambiar tema">
        <span id="tema-icon"></span>
    </button>
    <script>
        (function() {
            const icon = document.getElementById('tema-icon');
            const sync = () => icon.textContent = document.documentElement.dataset.theme === 'dark' ? '☀' : '🌙';
            sync();
            new MutationObserver(sync).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        })();
    </script>

    @livewireScripts
</body>
</html>
