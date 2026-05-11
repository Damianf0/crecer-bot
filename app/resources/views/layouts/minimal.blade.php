<!DOCTYPE html>
<html lang="es" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crecer — {{ $title ?? '' }}</title>
    @livewireStyles
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .wrap { width: 100%; max-width: 440px; }
        .brand { text-align: center; margin-bottom: 24px; }
        .brand-name { font-size: 20px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--accent); }
        .brand-sub  { font-size: 12px; color: var(--muted); margin-top: 4px; }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 28px;
        }
        .card-title { font-size: 16px; font-weight: 600; margin-bottom: 22px; color: var(--text); }

        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 6px; font-weight: 500; }
        input[type=email], input[type=password], input[type=text] {
            width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 6px;
            color: var(--text); padding: 10px 12px; font-size: 14px; font-family: inherit;
            transition: border-color 0.15s;
        }
        input:focus { outline: none; border-color: var(--accent); }

        .error-msg {
            background: color-mix(in srgb, var(--error) 10%, transparent);
            border: 1px solid color-mix(in srgb, var(--error) 35%, transparent);
            color: var(--error);
            border-radius: 6px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px;
        }

        .btn {
            width: 100%; padding: 11px; border-radius: 6px; font-size: 14px; font-weight: 600;
            cursor: pointer; border: none; background: var(--accent); color: #fff;
            transition: background 0.15s; margin-top: 4px;
        }
        .btn:hover { background: var(--accent-dark); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-ghost {
            background: var(--surface); border: 1px solid var(--border); color: var(--text); margin-top: 10px;
        }
        .btn-ghost:hover { background: var(--border); }

        /* Colas */
        .colas-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        .cola-item {
            background: var(--bg); border: 2px solid var(--border); border-radius: 8px;
            padding: 14px 12px; cursor: pointer; transition: border-color 0.15s, background 0.15s;
            font-size: 13px; font-weight: 500; text-align: center; line-height: 1.3; color: var(--text);
        }
        .cola-item:hover { border-color: var(--muted); }
        .cola-item.selected {
            border-color: var(--accent);
            background: color-mix(in srgb, var(--accent) 8%, transparent);
        }
        .cola-icon { font-size: 22px; display: block; margin-bottom: 6px; }
        .usuario-badge {
            display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding: 12px;
            background: var(--bg); border-radius: 6px; border: 1px solid var(--border);
        }
        .usuario-avatar {
            width: 36px; height: 36px; background: var(--accent); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 14px; color: #fff; flex-shrink: 0;
        }
        .usuario-nombre { font-size: 14px; font-weight: 600; }
        .usuario-rol { font-size: 11px; color: var(--muted); }

        /* Toggle de tema, fixed bottom-right */
        .tema-toggle {
            position: fixed; bottom: 16px; right: 16px;
            background: var(--card); border: 1px solid var(--border); color: var(--muted);
            width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 16px;
            display: flex; align-items: center; justify-content: center;
            transition: color 0.15s, border-color 0.15s;
        }
        .tema-toggle:hover { color: var(--text); border-color: var(--muted); }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <div class="brand-name">Crecer Reproducción</div>
            <div class="brand-sub">Sistema de gestión operativa</div>
        </div>
        {{ $slot }}
    </div>

    <button class="tema-toggle" onclick="(function(){var r=document.getElementById('html-root');r.classList.toggle('dark');localStorage.setItem('tema',r.classList.contains('dark')?'dark':'light');})()" title="Cambiar tema">
        <span id="tema-icon"></span>
    </button>
    <script>
        (function() {
            const icon = document.getElementById('tema-icon');
            const sync = () => icon.textContent = document.getElementById('html-root').classList.contains('dark') ? '☀' : '◐';
            sync();
            new MutationObserver(sync).observe(document.getElementById('html-root'), { attributes: true, attributeFilter: ['class'] });
        })();
    </script>

    @livewireScripts
</body>
</html>
