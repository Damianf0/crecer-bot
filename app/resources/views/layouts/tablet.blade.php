<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title>Crecer Reproducción</title>
    @livewireStyles
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

        :root { --accent: #C0273A; --accent-dark: #8C1B29; }

        html, body {
            height: 100%;
            background: #0a0a0a;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow: hidden;
            user-select: none;
        }

        /* ── Estructura principal ── */
        .tablet-wrap {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .tablet-header {
            display: flex;
            align-items: center;
            padding: 12px 32px;
            border-bottom: 1px solid #1c1c1c;
            flex-shrink: 0;
            gap: 12px;
        }

        .header-title {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .header-sub {
            font-size: 12px;
            color: rgba(255,255,255,0.35);
            letter-spacing: .5px;
        }

        .header-sep {
            width: 1px;
            height: 24px;
            background: #2a2a2a;
            margin: 0 4px;
        }

        .tablet-body {
            flex: 1;
            min-height: 0;
            display: flex;
        }

        /* ── Dos columnas ── */
        .two-col {
            display: flex;
            width: 100%;
            height: 100%;
        }

        .col-left {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 32px 40px;
            border-right: 1px solid #1c1c1c;
            overflow-y: auto;
        }

        .col-right {
            width: 320px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 24px 28px;
            gap: 10px;
        }

        /* ── Paso full-width (confirmado, acercarse) ── */
        .full-step {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
        }

        /* ── Textos ── */
        .step-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .step-sub {
            font-size: 15px;
            color: rgba(255,255,255,0.45);
            line-height: 1.5;
            margin-bottom: 20px;
        }

        /* ── DNI display ── */
        .dni-display {
            font-size: 44px;
            font-weight: 700;
            letter-spacing: 8px;
            color: #fff;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            border-bottom: 2px solid #333;
            margin-bottom: 10px;
        }

        .dni-display.empty { color: #333; justify-content: center; }

        .error-msg {
            color: #f85149;
            font-size: 13px;
            min-height: 20px;
        }

        /* ── Teclado numérico ── */
        .keypad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }

        .key {
            background: #1e1e1e;
            border: 1px solid #2e2e2e;
            border-radius: 10px;
            color: #fff;
            font-size: 24px;
            font-weight: 500;
            height: 60px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.1s, transform 0.08s;
        }

        .key:active { background: #333; transform: scale(0.95); }
        .key.borrar { font-size: 18px; color: rgba(255,255,255,0.6); }
        .key.vacio  { visibility: hidden; }

        /* ── Botones de acción ── */
        .btn {
            width: 100%;
            padding: 16px;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: background 0.15s, transform 0.08s;
        }

        .btn:active { transform: scale(0.98); }

        .btn-primary            { background: var(--accent); color: #fff; }
        .btn-primary:hover      { background: var(--accent-dark); }
        .btn-primary:disabled   { background: #2a2a2a; color: #555; cursor: not-allowed; }

        .btn-secondary {
            background: #1e1e1e;
            color: rgba(255,255,255,0.6);
            border: 1px solid #2e2e2e;
        }

        /* ── Paciente info ── */
        .paciente-nombre {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 4px;
            line-height: 1.2;
        }

        .paciente-os {
            font-size: 13px;
            color: rgba(255,255,255,0.35);
            margin-bottom: 24px;
        }

        /* ── Turno card ── */
        .turno-card {
            background: #161616;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: border-color 0.15s;
        }

        .turno-card.selected { border-color: var(--accent); }
        .turno-card:active    { background: #1e1e1e; }

        .turno-hora       { font-size: 28px; font-weight: 700; color: var(--accent); }
        .turno-practica   { font-size: 15px; font-weight: 600; margin: 3px 0 2px; }
        .turno-profesional{ font-size: 13px; color: rgba(255,255,255,0.45); }

        /* ── Motivo botones ── */
        .motivo-btn {
            display: block;
            width: 100%;
            padding: 18px;
            background: #1e1e1e;
            border: 2px solid #2e2e2e;
            border-radius: 10px;
            color: #fff;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: border-color 0.15s;
            text-align: center;
        }

        .motivo-btn.selected { border-color: var(--accent); }
        .motivo-btn:active   { background: #252525; }

        /* ── Confirmado ── */
        .check-icon { font-size: 72px; margin-bottom: 16px; }

        .planta-badge {
            display: inline-block;
            background: var(--accent);
            color: #fff;
            padding: 6px 20px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 12px;
        }

        .countdown {
            font-size: 13px;
            color: rgba(255,255,255,0.3);
            margin-top: 24px;
        }

        /* ── Primera vez ── */
        .primera-vez-badge {
            display: inline-block;
            background: rgba(255,200,0,.12);
            color: #ffc800;
            border: 1px solid rgba(255,200,0,.25);
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
<div class="tablet-wrap">

    <div class="tablet-header">
        <div>
            <div class="header-title">Crecer Reproducción</div>
            <div class="header-sub">Centro de Reproducción y Genética Humana</div>
        </div>
    </div>

    <div class="tablet-body">
        {{ $slot }}
    </div>

</div>
@livewireScripts
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('iniciarReset', ({ segundos, componentId }) => {
            let restante = segundos;
            const el = document.getElementById('countdown');
            const intervalo = setInterval(() => {
                restante--;
                if (el) el.textContent = `Volviendo al inicio en ${restante}s...`;
                if (restante <= 0) {
                    clearInterval(intervalo);
                    Livewire.find(componentId).reset2();
                }
            }, 1000);
        });
    });
</script>
</body>
</html>
