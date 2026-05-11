<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $nombre_clinica }} — Llamador</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            width: 100%;
            overflow: hidden;
            background: #0a0a14;
            color: #e8e8f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        :root {
            --accent: #C0273A;
            --accent-light: #ff6b80;
            --hint: #565680;
        }

        .header {
            position: fixed; top: 0; left: 0; right: 0; height: 64px;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 32px;
            background: linear-gradient(180deg, rgba(10,10,20,.96), rgba(10,10,20,.5));
            z-index: 10;
        }
        .clinica {
            font-size: 22px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
            color: var(--accent-light);
        }
        .clock {
            font-size: 20px; font-weight: 600; color: var(--hint); font-variant-numeric: tabular-nums;
        }

        .stage {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            padding: 64px 32px 200px;
        }

        /* Estado idle */
        .idle {
            text-align: center;
            color: var(--hint);
        }
        .idle .ico { font-size: 100px; opacity: .35; margin-bottom: 18px; }
        .idle .msg { font-size: 28px; opacity: .55; }

        /* Llamado activo */
        .llamado {
            text-align: center;
            opacity: 0;
            transform: scale(.9);
            transition: opacity .35s ease, transform .35s ease;
        }
        .llamado.show {
            opacity: 1;
            transform: scale(1);
        }
        .llamado .nombre {
            font-size: clamp(80px, 14vw, 220px);
            font-weight: 800;
            letter-spacing: -2px;
            line-height: 1;
            color: #fff;
            text-shadow: 0 0 60px rgba(192,39,58,.35);
            margin-bottom: 30px;
        }
        .llamado .destino {
            display: inline-flex; align-items: baseline; gap: 18px;
            font-weight: 700;
        }
        .llamado .destino .lbl {
            font-size: clamp(28px, 4vw, 56px); color: var(--hint); text-transform: uppercase; letter-spacing: 3px;
        }
        .llamado .destino .num {
            font-size: clamp(96px, 14vw, 220px);
            color: var(--accent-light);
            font-weight: 900;
            line-height: 1;
            text-shadow: 0 0 80px rgba(255,107,128,.4);
        }
        .llamado .planta {
            margin-top: 18px;
            font-size: clamp(24px, 3vw, 40px); color: var(--hint);
            text-transform: capitalize;
        }

        .llamado.show .nombre {
            animation: pop .8s ease-out;
        }
        @keyframes pop {
            0%   { transform: scale(.5); opacity: 0; filter: blur(8px); }
            60%  { transform: scale(1.06); opacity: 1; filter: blur(0); }
            100% { transform: scale(1); }
        }

        /* Lista esperando (footer) */
        .esperando {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: linear-gradient(0deg, rgba(10,10,20,.95), rgba(10,10,20,.6));
            padding: 18px 32px 22px;
            z-index: 5;
        }
        .esperando-head {
            font-size: 13px; color: var(--hint); text-transform: uppercase; letter-spacing: 2px;
            margin-bottom: 10px; font-weight: 700;
        }
        .esperando-list {
            display: flex; flex-wrap: wrap; gap: 10px;
            font-size: 18px;
        }
        .esperando-chip {
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 999px;
            padding: 8px 18px;
            font-weight: 500;
            color: #d0d0dc;
        }
        .esperando-empty {
            color: var(--hint); font-style: italic; font-size: 16px;
        }

        /* Pancarta de llamado previo (chico arriba) */
        .previos {
            position: fixed; top: 80px; right: 32px;
            display: flex; flex-direction: column; gap: 8px;
            z-index: 8;
        }
        .previo {
            background: rgba(192,39,58,.18);
            border: 1px solid rgba(192,39,58,.4);
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 16px; color: #fff; font-weight: 500;
            text-align: right;
            opacity: .65;
        }
        .previo .who { font-weight: 700; }
        .previo .where { color: var(--accent-light); margin-left: 8px; }

        /* Botón inicial para activar TTS (autoplay block) */
        .audio-gate {
            position: fixed; inset: 0; z-index: 100;
            background: rgba(5,5,12,.95);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 24px;
        }
        .audio-gate.hidden { display: none; }
        .audio-gate .ico-tv { font-size: 96px; }
        .audio-gate .lbl { font-size: 28px; color: var(--hint); }
        .audio-gate button {
            background: var(--accent); color: #fff; border: none;
            padding: 22px 56px; border-radius: 14px;
            font-size: 22px; font-weight: 700; cursor: pointer;
            box-shadow: 0 12px 40px rgba(192,39,58,.4);
            transition: transform .15s, box-shadow .15s;
        }
        .audio-gate button:hover { transform: translateY(-2px); box-shadow: 0 16px 48px rgba(192,39,58,.55); }
        .audio-gate button:active { transform: translateY(0); }

        /* Indicador estado conexión */
        .conn {
            position: fixed; top: 16px; left: 50%; transform: translateX(-50%);
            font-size: 12px; padding: 6px 14px;
            background: rgba(255,107,128,.15); border: 1px solid rgba(255,107,128,.4);
            color: var(--accent-light); border-radius: 999px;
            opacity: 0; transition: opacity .3s;
            z-index: 9;
        }
        .conn.show { opacity: 1; }
    </style>
</head>
<body>
    {{-- Overlay inicial para overrider auto-play block del browser --}}
    <div class="audio-gate" id="audio-gate">
        <div class="ico-tv">📺</div>
        <div class="lbl">Tocá el botón para iniciar el llamador</div>
        <button onclick="activar()">Iniciar pantalla</button>
        <div class="lbl" style="font-size:14px;opacity:.5;">El audio se activa una sola vez</div>
    </div>

    <div class="header">
        <div class="clinica">
            {{ $nombre_clinica }}
            @if($planta)
                <span style="opacity:.55;font-weight:500;font-size:18px;letter-spacing:1px;margin-left:14px;">· planta {{ $planta }}</span>
            @endif
        </div>
        <div class="clock" id="clock">--:--</div>
    </div>

    <div class="conn" id="conn">⚠ reconectando…</div>

    <div class="stage" id="stage">
        <div class="idle" id="idle">
            <div class="ico">⏳</div>
            <div class="msg">Esperando próximo llamado</div>
        </div>
        <div class="llamado" id="llamado" style="display:none;">
            <div class="nombre" id="lla-nombre">—</div>
            <div class="destino">
                <span class="lbl">Consultorio</span>
                <span class="num" id="lla-num">—</span>
            </div>
            <div class="planta" id="lla-planta"></div>
        </div>
    </div>

    <div class="previos" id="previos"></div>

    <div class="esperando">
        <div class="esperando-head">Esperando en sala <span id="esp-cnt">(0)</span></div>
        <div class="esperando-list" id="esp-list">
            <div class="esperando-empty">Nadie en sala todavía</div>
        </div>
    </div>

    <script>
        const TOKEN = @json($token);
        const PLANTA = @json($planta);   // 'baja' | 'alta' | null
        const VENTANA = {{ $ventana_segs }};
        let lastAnnouncedId = null;     // último llamado que ya anunciamos
        let lastAnnouncedTs = 0;
        let synth = window.speechSynthesis;
        let voicesReady = false;
        let voicePreferida = null;

        function $(id) { return document.getElementById(id); }
        function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

        // ── Reloj ─────────────────────────────────────────────
        function tick() {
            const d = new Date();
            $('clock').textContent =
                String(d.getHours()).padStart(2, '0') + ':' +
                String(d.getMinutes()).padStart(2, '0');
        }
        setInterval(tick, 5000);
        tick();

        // ── Voz ───────────────────────────────────────────────
        function elegirVoz() {
            const voces = synth.getVoices();
            // Preferir español rioplatense / latinoamericano
            voicePreferida =
                voces.find(v => /es[-_]AR/i.test(v.lang)) ||
                voces.find(v => /es[-_](MX|CL|PE|419|US)/i.test(v.lang)) ||
                voces.find(v => /^es/i.test(v.lang)) ||
                voces[0] || null;
            voicesReady = true;
        }
        if (synth) {
            synth.onvoiceschanged = elegirVoz;
            elegirVoz();
        }

        function anunciar(nombre, consultorio) {
            if (!synth) return;
            const partes = [];
            if (nombre) partes.push(nombre + ',');
            if (consultorio) {
                partes.push('por favor pase al consultorio ' + consultorio);
            } else {
                partes.push('por favor acérquese');
            }
            const texto = partes.join(' ') + '.';

            function decir() {
                const u = new SpeechSynthesisUtterance(texto);
                u.lang = 'es-AR';
                if (voicePreferida) u.voice = voicePreferida;
                u.rate = 0.92;
                u.pitch = 1;
                u.volume = 1;
                synth.speak(u);
            }
            // Cancelar lo que sonara antes y decir 2 veces con pausa
            synth.cancel();
            decir();
            setTimeout(decir, 2200);
        }

        // ── Render ────────────────────────────────────────────
        let _llamadoVisibleId = null;

        function mostrarLlamado(p) {
            $('idle').style.display = 'none';
            $('llamado').style.display = '';
            $('lla-nombre').textContent = p.nombre_display;
            $('lla-num').textContent = p.consultorio ?? '—';
            // Si la TV está filtrada por planta, no repetimos la planta abajo.
            $('lla-planta').textContent = (!PLANTA && p.planta) ? 'planta ' + p.planta : '';
            requestAnimationFrame(() => $('llamado').classList.add('show'));
            _llamadoVisibleId = p.id;
        }

        function ocultarLlamado() {
            $('llamado').classList.remove('show');
            setTimeout(() => {
                $('llamado').style.display = 'none';
                $('idle').style.display = '';
            }, 350);
            _llamadoVisibleId = null;
        }

        function renderPrevios(previos) {
            $('previos').innerHTML = previos.map(p => `
                <div class="previo">
                    <span class="who">${esc(p.nombre_display)}</span>
                    <span class="where">Consultorio ${esc(p.consultorio ?? '—')}</span>
                </div>`).join('');
        }

        function renderEsperando(esperando) {
            $('esp-cnt').textContent = '(' + esperando.length + ')';
            const list = $('esp-list');
            if (!esperando.length) {
                list.innerHTML = '<div class="esperando-empty">Nadie en sala todavía</div>';
                return;
            }
            list.innerHTML = esperando.map(p => `
                <div class="esperando-chip">${esc(p.nombre)}${(!PLANTA && p.planta) ? ' · ' + esc(p.planta) : ''}</div>
            `).join('');
        }

        // ── Polling ───────────────────────────────────────────
        let errCount = 0;
        async function poll() {
            try {
                let url = '/llamador/data?token=' + encodeURIComponent(TOKEN);
                if (PLANTA) url += '&planta=' + encodeURIComponent(PLANTA);
                const r = await fetch(url, {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const j = await r.json();
                errCount = 0;
                $('conn').classList.remove('show');

                renderEsperando(j.esperando || []);
                renderPrevios(j.previos || []);

                const actual = j.actual;
                if (actual) {
                    // ¿Es un llamado nuevo? Compara id+ts contra el último anunciado.
                    const isNew = actual.id !== lastAnnouncedId || actual.llamado_ts !== lastAnnouncedTs;
                    if (isNew) {
                        lastAnnouncedId = actual.id;
                        lastAnnouncedTs = actual.llamado_ts;
                        mostrarLlamado(actual);
                        anunciar(actual.nombre_anuncio, actual.consultorio);
                    } else if (_llamadoVisibleId !== actual.id) {
                        // Mostramos sin anunciar (re-render por F5 o reconexión)
                        mostrarLlamado(actual);
                    }
                } else if (_llamadoVisibleId !== null) {
                    ocultarLlamado();
                }
            } catch (e) {
                errCount++;
                if (errCount >= 2) $('conn').classList.add('show');
            }
        }

        // ── Activar audio (gate inicial) ──────────────────────
        function activar() {
            $('audio-gate').classList.add('hidden');
            // Disparar utterance vacía para overrider el bloqueo
            if (synth) {
                const test = new SpeechSynthesisUtterance(' ');
                test.lang = 'es-AR';
                test.volume = 0;
                synth.speak(test);
            }
            // Fullscreen opcional
            try { document.documentElement.requestFullscreen?.(); } catch (e) {}
            poll();
            setInterval(poll, 3000);
        }
    </script>
</body>
</html>
