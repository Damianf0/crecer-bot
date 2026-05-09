<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crecer — {{ $title ?? '' }}</title>
    @livewireStyles
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --accent: #C0273A; --accent-dark: #8C1B29; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0d1117;
            color: #c9d1d9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .wrap { width: 100%; max-width: 440px; }
        .brand { text-align: center; margin-bottom: 32px; }
        .brand-name { font-size: 20px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--accent); }
        .brand-sub  { font-size: 12px; color: #8b949e; margin-top: 4px; }
        .card { background: #161b22; border: 1px solid #30363d; border-radius: 10px; padding: 32px; }
        .card-title { font-size: 16px; font-weight: 600; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; color: #8b949e; margin-bottom: 6px; font-weight: 500; }
        input[type=email], input[type=password], input[type=text] {
            width: 100%; background: #0d1117; border: 1px solid #30363d; border-radius: 6px;
            color: #c9d1d9; padding: 10px 12px; font-size: 14px; font-family: inherit;
            transition: border-color 0.15s;
        }
        input:focus { outline: none; border-color: var(--accent); }
        .error-msg { background: rgba(248,81,73,0.1); border: 1px solid rgba(248,81,73,0.3); color: #f85149; border-radius: 6px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
        .btn { width: 100%; padding: 11px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; background: var(--accent); color: #fff; transition: background 0.15s; margin-top: 4px; }
        .btn:hover { background: var(--accent-dark); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-ghost { background: #21262d; border: 1px solid #30363d; color: #c9d1d9; margin-top: 10px; }
        .btn-ghost:hover { background: #30363d; }

        /* Colas */
        .colas-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        .cola-item {
            background: #0d1117; border: 2px solid #30363d; border-radius: 8px;
            padding: 14px 12px; cursor: pointer; transition: border-color 0.15s, background 0.15s;
            font-size: 13px; font-weight: 500; text-align: center; line-height: 1.3;
        }
        .cola-item:hover { border-color: #8b949e; }
        .cola-item.selected { border-color: var(--accent); background: rgba(192,39,58,0.08); color: #fff; }
        .cola-icon { font-size: 22px; display: block; margin-bottom: 6px; }
        .usuario-badge { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding: 12px; background: #0d1117; border-radius: 6px; border: 1px solid #30363d; }
        .usuario-avatar { width: 36px; height: 36px; background: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: #fff; flex-shrink: 0; }
        .usuario-nombre { font-size: 14px; font-weight: 600; }
        .usuario-rol { font-size: 11px; color: #8b949e; }
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
    @livewireScripts
</body>
</html>
