<!DOCTYPE html>
{{-- Layout V2 — la única interfaz desde Fase 4 (23/07; V1 retirada, las URLs
     viejas redirigen a /v2/*). Cutover original: 30/06, commit effdce9.
     Ver docs/MIGRACION-V2.md, DESIGN-SYSTEM.md y CONCEPTO_UI_UX_V2.md. --}}
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crecer V2 — {{ $title ?? 'Panel' }}</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script>
        // Tema antes del CSS para evitar flash. Clave propia (v2-theme) para no
        // pisar la preferencia de la UI actual.
        document.documentElement.dataset.theme = localStorage.getItem('v2-theme') || 'light';
    </script>
    <link rel="stylesheet" href="/css/crecer-v2.css?v={{ filemtime(public_path('css/crecer-v2.css')) }}">
    @stack('styles')
</head>
<body>
@php
    // Contadores del menú: al cargar salen de acá; después los refresca /bot-pulso (ver script abajo).
    $uid = auth()->id() ?? 0;
    $cnt = \App\Support\ContadoresNavbar::para($uid);
    [$misConv, $misTareasCnt, $pendByArea] = [$cnt['mis_conv'], $cnt['mis_tareas'], $cnt['por_area']];
    $areaActiva = $area ?? null;
    $navActiva  = $navActive ?? null;   // mis-conversaciones | tareas | historial | contactos
    $u = auth()->user();
@endphp

<div class="v2-shell">
    <header class="v2-topbar">
        <button class="tb-btn" id="sb-toggle" title="Colapsar menú">☰</button>
        <span class="brand">
            <img src="/logo.jpg" alt="Crecer">
            Crecer <span class="mod">· {{ $modulo ?? 'Secretaría' }}</span>
        </span>
        <span class="spacer"></span>
        <span class="tb-item" id="bots-status" title="Estado de los bots WhatsApp">
            <span class="v2-dot warn" id="bots-dot"></span><span id="bots-txt">Bots…</span>
        </span>
        <button class="tb-btn" id="theme-btn" title="Cambiar tema">🌙</button>
        @if(auth()->user()?->hasPermiso('secretaria'))
        <a class="tb-btn" href="/declarar-colas" style="text-decoration:none;" title="Cambiar mis colas">⇄</a>
        @endif
        <a class="tb-btn" href="/logout" style="text-decoration:none;" onclick="return confirm('¿Cerrar sesión?');">Salir</a>
    </header>

    <div class="v2-body" id="v2-body">
        <aside class="v2-sidebar">
            <div class="v2-nav-sec">Uso diario</div>
            <a class="v2-nav-item {{ $navActiva === 'mi-dia' ? 'active' : '' }}" href="/v2/mi-dia">
                <span class="ico">☀️</span><span class="lbl">Mi día</span>
            </a>

            <div class="v2-nav-sec">WhatsApp</div>
            @foreach(\App\Models\ConversacionWA::areas() as $aKey => $aLabel)
            <a class="v2-nav-item {{ $areaActiva === $aKey ? 'active' : '' }}" href="/v2/atencion/{{ $aKey }}">
                <span class="ico">💬</span><span class="lbl">{{ $aLabel }}</span>
                <span class="v2-nav-badge" data-contador="area:{{ $aKey }}" @if(($pendByArea[$aKey] ?? 0) === 0) hidden @endif>{{ $pendByArea[$aKey] ?? 0 }}</span>
            </a>
            @endforeach
            <a class="v2-nav-item {{ $navActiva === 'mis-conversaciones' ? 'active' : '' }}" href="/v2/mis-conversaciones">
                <span class="ico">👤</span><span class="lbl">Mis conversaciones</span>
                <span class="v2-nav-badge" data-contador="mis_conv" @if($misConv === 0) hidden @endif>{{ $misConv }}</span>
            </a>

            <div class="v2-nav-sec">Trabajo</div>
            <a class="v2-nav-item {{ $navActiva === 'tareas' ? 'active' : '' }}" href="/v2/centro-tareas">
                <span class="ico">✓</span><span class="lbl">Tareas</span>
                <span class="v2-nav-badge" data-contador="mis_tareas" style="background:var(--v2-info);" @if($misTareasCnt === 0) hidden @endif>{{ $misTareasCnt }}</span>
            </a>
            @if($u && $u->hasPermiso('historial'))
            <a class="v2-nav-item {{ $navActiva === 'historial' ? 'active' : '' }}" href="/v2/historial">
                <span class="ico">🕘</span><span class="lbl">Historial</span>
            </a>
            @endif
            @if($u && $u->hasPermiso('contactos'))
            <a class="v2-nav-item {{ $navActiva === 'contactos' ? 'active' : '' }}" href="/v2/contactos">
                <span class="ico">📇</span><span class="lbl">Contactos</span>
            </a>
            @endif
            @if($u && $u->hasPermiso('agenda'))
            <a class="v2-nav-item {{ $navActiva === 'agenda' ? 'active' : '' }}" href="/v2/agenda">
                <span class="ico">📅</span><span class="lbl">Agenda</span>
            </a>
            @endif
            {{-- Base de conocimiento: la consulta es para todo el equipo, sin permiso propio --}}
            <a class="v2-nav-item {{ $navActiva === 'procedimientos' ? 'active' : '' }}" href="/v2/procedimientos">
                <span class="ico">📘</span><span class="lbl">Procedimientos</span>
            </a>

            @if($u && ($u->hasPermiso('secretaria') || $u->hasPermiso('atencion')))
            <div class="v2-nav-sec">Recepción</div>
            @if($u->hasPermiso('secretaria'))
            <a class="v2-nav-item {{ $navActiva === 'recepcion' ? 'active' : '' }}" href="/v2/recepcion">
                <span class="ico">🪑</span><span class="lbl">Recepción</span>
            </a>
            @if($u->hasPermiso('admin'))
            <a class="v2-nav-item {{ $navActiva === 'recepcion-reglas' ? 'active' : '' }}" href="/v2/recepcion/reglas">
                <span class="ico">☑️</span><span class="lbl">Checklist por obra social</span>
            </a>
            @endif
            @endif
            {{-- Registro de pacientes de primera vez: lo carga cualquiera que atienda --}}
            <a class="v2-nav-item {{ $navActiva === 'primera-vez' ? 'active' : '' }}" href="/v2/primera-vez">
                <span class="ico">🌱</span><span class="lbl">Primera vez</span>
            </a>
            @endif

            @if($u && $u->hasPermiso('medico'))
            <div class="v2-nav-sec">Consultorio</div>
            <a class="v2-nav-item {{ $navActiva === 'medico' ? 'active' : '' }}" href="/v2/medico">
                <span class="ico">🩺</span><span class="lbl">Mi consultorio</span>
            </a>
            @endif

            @if($u && $u->hasPermiso('admin'))
            <div class="v2-nav-sec">Supervisión</div>
            <a class="v2-nav-item {{ $navActiva === 'reportes' ? 'active' : '' }}" href="/v2/reportes">
                <span class="ico">📈</span><span class="lbl">Reportes</span>
            </a>
            <a class="v2-nav-item {{ $navActiva === 'difusiones' ? 'active' : '' }}" href="/v2/difusiones">
                <span class="ico">📣</span><span class="lbl">Difusiones</span>
            </a>
            <a class="v2-nav-item {{ $navActiva === 'admin' ? 'active' : '' }}" href="/v2/admin">
                <span class="ico">⚙️</span><span class="lbl">Admin</span>
            </a>
            @endif

            <div class="sb-foot">
                <span class="v2-av-fb" style="width:26px;height:26px;font-size:11px;">{{ mb_strtoupper(mb_substr($u->nombre_completo ?? '?', 0, 1)) }}</span>
                <span class="sb-meta" style="min-width:0;">
                    <span style="display:block;font-weight:600;font-size:12px;overflow:hidden;text-overflow:ellipsis;">{{ explode(' ', $u->nombre_completo ?? '')[0] }}</span>
                    <span style="display:block;font-size:10.5px;color:var(--v2-text-mute);">{{ \App\Models\User::ROLES[$u?->rol] ?? $u?->rol }}</span>
                </span>
            </div>
        </aside>

        <main class="v2-main">
            @if(!empty($v2Wrap))
            {{-- Vistas de producción reusadas (admin/*): emula el <main> con
                 padding y scroll de layouts/app, que esas vistas asumen. --}}
            <div style="flex:1;overflow-y:auto;padding:24px;">
                @yield('content')
            </div>
            @else
            @yield('content')
            @endif
        </main>
    </div>
</div>

<div class="v2-toast" id="v2-toast"></div>

<script>
// ── Shell: tema, colapso, estado bots ─────────────────────────────
(function () {
    const body = document.getElementById('v2-body');
    if (localStorage.getItem('v2-sidebar') === 'collapsed') body.classList.add('collapsed');
    document.getElementById('sb-toggle').onclick = () => {
        body.classList.toggle('collapsed');
        localStorage.setItem('v2-sidebar', body.classList.contains('collapsed') ? 'collapsed' : '');
    };

    const themeBtn = document.getElementById('theme-btn');
    function syncThemeBtn() { themeBtn.textContent = document.documentElement.dataset.theme === 'dark' ? '☀️' : '🌙'; }
    themeBtn.onclick = () => {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        localStorage.setItem('v2-theme', next);
        syncThemeBtn();
    };
    syncThemeBtn();

    // Estado de los bots + contadores del menú. Cada 15 s y, además, al instante
    // cuando llega un aviso en tiempo real de una cola (ver abajo, V2Tiempo).
    function pintarContador(clave, n) {
        const el = document.querySelector(`[data-contador="${clave}"]`);
        if (!el) return;
        el.textContent = n;
        el.hidden = !n;
    }
    async function pulso() {
        const dot = document.getElementById('bots-dot'), txt = document.getElementById('bots-txt');
        try {
            const r = await fetch('/bot-pulso', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            if (r.redirected || r.status === 401) return;
            const d = await r.json();
            const bots = Object.values(d.bots || {});
            const mal = bots.filter(b => b.estado !== 'listo').map(b => b.label).join(', ');
            dot.parentElement.title = bots.map(b => `${b.label}: ${b.estado}${b.has_qr ? ' (falta QR)' : ''}`).join('\n');
            if (d.estado === 'listo') { dot.className = 'v2-dot ok'; txt.textContent = 'Bots ok'; }
            else if (d.estado === 'iniciando') { dot.className = 'v2-dot warn'; txt.textContent = 'Iniciando: ' + mal; }
            else { dot.className = 'v2-dot err'; txt.textContent = 'Caído: ' + mal; }
            const c = d.contadores || {};
            for (const [area, n] of Object.entries(c.por_area || {})) pintarContador('area:' + area, n);
            document.querySelectorAll('[data-contador^="area:"]').forEach(el => {
                if (!(el.dataset.contador.slice(5) in (c.por_area || {}))) pintarContador(el.dataset.contador, 0);
            });
            pintarContador('mis_conv', c.mis_conv || 0);
            pintarContador('mis_tareas', c.mis_tareas || 0);
        } catch { dot.className = 'v2-dot warn'; txt.textContent = 'Bots ?'; }
    }
    let pulsoTimer = null;
    window.v2PulsoPronto = () => { clearTimeout(pulsoTimer); pulsoTimer = setTimeout(pulso, 1500); };
    pulso(); setInterval(pulso, 15000);
})();

// Números de WhatsApp desde los que se puede iniciar una conversación, y el que
// se ofrece primero: la primera cola que la persona declaró al entrar (antes
// Contactos salía siempre por Atención aunque la persona atendiera otra cola).
window.V2_AREAS = @json(\App\Models\ConversacionWA::areas());
window.V2_AREA_DEFAULT = @json(\App\Models\ConversacionWA::areasDeLaSesion()[0] ?? 'atencion');
window.v2OpcionesArea = (elegida) => Object.entries(window.V2_AREAS)
    .map(([k, l]) => `<option value="${k}" ${k === elegida ? 'selected' : ''}>WhatsApp de ${l}</option>`).join('');

window.v2toast = function (msg, tipo = 'ok') {
    const el = document.getElementById('v2-toast');
    el.textContent = msg;
    el.className = `v2-toast ${tipo} show`;
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 3000);
};
</script>
<script src="/js/crecer-notify.js?v={{ filemtime(public_path('js/crecer-notify.js')) }}"></script>
<script src="/js/crecer-v2.js?v={{ filemtime(public_path('js/crecer-v2.js')) }}"></script>
@if($u && $u->hasPermiso('atencion'))
<script>
// Cualquier cambio en una cola (mensaje nuevo, tomada, resuelta…) refresca los
// contadores del menú en ~1,5 s, sin esperar al pulso de 15 s.
V2Tiempo.escuchar(@json(array_keys(\App\Models\ConversacionWA::areas())), () => window.v2PulsoPronto());
</script>
@endif
@stack('scripts')

{{-- Chat interno (mismo widget React que producción; sus var(--accent) etc.
     se resuelven con el puente de variables definido en crecer-v2.css). --}}
@include('chat._widget')
</body>
</html>
