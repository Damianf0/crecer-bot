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

        /* Tema claro (12/10). Los tamaños van atados al alto de la pantalla (vh)
           para que botones y teclado queden grandes en cualquier tablet. */
        :root {
            --accent: #C0273A; --accent-dark: #8C1B29; --accent-suave: #fdecef;
            --fondo: #f5f4f0; --tarjeta: #ffffff; --linea: #d9d6ce;
            --texto: #1c1c1e; --texto-suave: #5f5e5a;
        }

        html, body {
            height: 100%;
            background: var(--fondo);
            color: var(--texto);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow: hidden;
            user-select: none;
        }

        button { font-family: inherit; }

        /* ── Estructura principal ── */
        .tablet-wrap {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .tablet-header {
            display: flex;
            align-items: center;
            padding: 1.8vh 5vw;
            background: var(--tarjeta);
            border-bottom: 1px solid var(--linea);
            flex-shrink: 0;
            gap: 12px;
        }

        .header-title {
            font-size: clamp(18px, 2.8vh, 26px);
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--accent);
        }

        .header-sub {
            font-size: clamp(13px, 1.8vh, 16px);
            color: var(--texto-suave);
            letter-spacing: .5px;
        }

        .tablet-body {
            flex: 1;
            min-height: 0;
            display: flex;
        }

        /* ── Inicio: bienvenida + teclado ── */
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
            padding: 4vh 5vw;
        }

        .col-right {
            width: min(46vw, 540px);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3vh 4vw;
            gap: 1.6vh;
            background: var(--tarjeta);
            border-left: 1px solid var(--linea);
        }

        /* ── Pasos con opciones: título, opciones y acciones abajo ── */
        .step {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            padding: 4vh 5vw;
            gap: 2.4vh;
        }

        .step-main {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .step-actions {
            flex-shrink: 0;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2vw;
        }

        .opciones {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.8vh 1.8vw;
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
            font-size: clamp(28px, 4.8vh, 44px);
            font-weight: 700;
            margin-bottom: 1vh;
        }

        .step-sub {
            font-size: clamp(18px, 2.7vh, 25px);
            color: var(--texto-suave);
            line-height: 1.45;
            margin-bottom: 2.4vh;
        }

        /* ── DNI display ── */
        .dni-display {
            font-size: clamp(44px, 8vh, 78px);
            font-weight: 700;
            letter-spacing: .12em;
            color: var(--texto);
            height: clamp(72px, 12vh, 112px);
            display: flex;
            align-items: center;
            justify-content: flex-start;
            border-bottom: 3px solid var(--linea);
            margin-bottom: 1.4vh;
        }

        .dni-display.empty { color: #c9c6bd; justify-content: center; }

        .error-msg {
            color: #b3261e;
            font-size: clamp(16px, 2.3vh, 20px);
            font-weight: 600;
            min-height: 28px;
        }

        /* ── Teclado numérico ── */
        .keypad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.4vh;
        }

        .key {
            background: var(--tarjeta);
            border: 2px solid var(--linea);
            border-radius: 16px;
            color: var(--texto);
            font-size: clamp(28px, 4.8vh, 42px);
            font-weight: 600;
            height: clamp(64px, 11.5vh, 108px);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 2px rgba(0,0,0,.06);
            transition: background 0.1s, transform 0.08s;
        }

        .key:active { background: #e9e7e0; transform: scale(0.96); }
        .key.borrar { color: var(--texto-suave); }
        .key.vacio  { visibility: hidden; }

        /* ── Botones de acción ── */
        .btn {
            width: 100%;
            min-height: clamp(64px, 10.5vh, 92px);
            padding: 0 20px;
            border-radius: 16px;
            font-size: clamp(20px, 3vh, 27px);
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: background 0.15s, transform 0.08s;
        }

        .btn:active { transform: scale(0.98); }

        .btn-primary            { background: var(--accent); color: #fff; }
        .btn-primary:active     { background: var(--accent-dark); }
        .btn-primary:disabled   { background: #e3e1da; color: #a3a199; cursor: not-allowed; }

        .btn-secondary {
            background: var(--tarjeta);
            color: #3a3a3c;
            border: 2px solid var(--linea);
        }

        /* ── Paciente info ── */
        .paciente-nombre {
            font-size: clamp(30px, 5vh, 46px);
            font-weight: 700;
            margin-bottom: .6vh;
            line-height: 1.2;
        }

        .paciente-os {
            font-size: clamp(16px, 2.4vh, 21px);
            color: var(--texto-suave);
            margin-bottom: 2.4vh;
        }

        /* ── Turno card ── */
        .turno-card {
            background: var(--tarjeta);
            border: 2px solid var(--linea);
            border-radius: 16px;
            padding: 2.2vh 2.4vw;
            margin-bottom: 1.6vh;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
        }

        .turno-card.selected { border-color: var(--accent); background: var(--accent-suave); }

        .turno-hora       { font-size: clamp(34px, 5.6vh, 52px); font-weight: 700; color: var(--accent); }
        .turno-practica   { font-size: clamp(19px, 2.8vh, 25px); font-weight: 600; margin: 3px 0 2px; }
        .turno-profesional{ font-size: clamp(16px, 2.4vh, 21px); color: var(--texto-suave); }

        /* ── Motivo botones ── */
        .motivo-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: clamp(96px, 18vh, 150px);
            padding: 12px 18px;
            background: var(--tarjeta);
            border: 2px solid var(--linea);
            border-radius: 16px;
            color: var(--texto);
            font-size: clamp(21px, 3.2vh, 29px);
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,.06);
            transition: border-color 0.15s, background 0.15s;
            text-align: center;
        }

        .motivo-btn.selected {
            border-color: var(--accent);
            border-width: 3px;
            background: var(--accent-suave);
            color: var(--accent-dark);
        }

        /* ── Confirmado ── */
        .check-icon {
            width: clamp(110px, 20vh, 160px);
            height: clamp(110px, 20vh, 160px);
            border-radius: 50%;
            background: #e3f3e7;
            color: #1e7e34;
            font-size: clamp(60px, 11vh, 90px);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 3vh;
        }

        .saludo-icon { font-size: clamp(64px, 12vh, 100px); margin-bottom: 2vh; }

        .planta-badge {
            display: inline-block;
            background: var(--accent);
            color: #fff;
            padding: 12px 32px;
            border-radius: 40px;
            font-size: clamp(20px, 3vh, 27px);
            font-weight: 700;
            margin-top: 1vh;
        }

        .countdown {
            font-size: clamp(15px, 2.1vh, 18px);
            color: var(--texto-suave);
            margin-top: 4vh;
        }

        /* ── Primera vez ── */
        .primera-vez-badge {
            align-self: flex-start;
            background: #fff4d6;
            color: #7a5200;
            border: 1px solid #ecd28a;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: clamp(15px, 2.1vh, 18px);
            font-weight: 700;
            margin-bottom: 1.6vh;
        }

        /* ── Tablet en vertical: el teclado pasa abajo ── */
        @media (orientation: portrait) {
            .two-col   { flex-direction: column; }
            .col-left  { flex: 1; }
            .col-right { width: 100%; border-left: none; border-top: 1px solid var(--linea); }
            .key       { height: clamp(64px, 8vh, 108px); }
            .opciones  { grid-template-columns: repeat(2, 1fr); }
            .motivo-btn { min-height: clamp(84px, 10vh, 132px); }
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
