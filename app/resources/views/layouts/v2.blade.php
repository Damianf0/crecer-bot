<!DOCTYPE html>
{{-- PoC del concepto UI V2 (ver docs/DESIGN-SYSTEM.md y CONCEPTO_UI_UX_V2.md).
     Corre en paralelo a producción bajo /v2/* — no toca layouts/app ni el DS. --}}
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
    // Mismos contadores cacheados que usa el navbar de producción (comparte clave de cache).
    $uid = auth()->id() ?? 0;
    [$misConv, $misTareasCnt, $pendByArea] = \Illuminate\Support\Facades\Cache::remember(
        "navbar.counts.{$uid}",
        5,
        fn() => [
            \App\Models\ConversacionWA::where('estado','activa')->where('asignada_a', $uid)->count(),
            \App\Models\Derivacion::where('estado','en_atencion')->where('asignada_a', $uid)->count()
                + \App\Models\Tarea::where('estado','!=','completada')->where('asignada_a', $uid)->count(),
            \App\Models\ConversacionWA::where('estado','activa')->whereNull('asignada_a')->where('no_leidos','>',0)
                ->selectRaw('area, count(*) as n')->groupBy('area')->pluck('n','area')->toArray(),
        ]
    );
    $areaActiva = $area ?? null;
    $navActiva  = $navActive ?? null;   // mis-conversaciones | tareas | historial | contactos
    $u = auth()->user();
@endphp

<div class="v2-shell">
    <header class="v2-topbar">
        <button class="tb-btn" id="sb-toggle" title="Colapsar menú">☰</button>
        <span class="brand">
            <img src="/logo.jpg" alt="Crecer">
            Crecer <span class="mod">· {{ $modulo ?? 'Atención' }}</span>
        </span>
        <span class="v2-pill neutral" title="Prueba de concepto — la UI de producción sigue en /atencion">PoC V2</span>
        <span class="spacer"></span>
        <span class="tb-item" id="bots-status" title="Estado de los bots WhatsApp">
            <span class="v2-dot warn" id="bots-dot"></span><span id="bots-txt">Bots…</span>
        </span>
        <button class="tb-btn" id="theme-btn" title="Cambiar tema">🌙</button>
        <a class="tb-btn" href="/atencion" style="text-decoration:none;" title="Volver a la interfaz actual">UI actual ↗</a>
        <a class="tb-btn" href="/logout" style="text-decoration:none;" onclick="return confirm('¿Cerrar sesión?');">Salir</a>
    </header>

    <div class="v2-body" id="v2-body">
        <aside class="v2-sidebar">
            <div class="v2-nav-sec">Uso diario</div>
            <span class="v2-nav-item disabled" title="Próximamente — home con tus pendientes del día">
                <span class="ico">☀️</span><span class="lbl">Mi día</span>
            </span>

            <div class="v2-nav-sec">Conversaciones</div>
            @foreach(\App\Models\ConversacionWA::AREAS as $aKey => $aLabel)
            <a class="v2-nav-item {{ $areaActiva === $aKey ? 'active' : '' }}" href="/v2/atencion/{{ $aKey }}">
                <span class="ico">💬</span><span class="lbl">{{ $aLabel }}</span>
                @if(($pendByArea[$aKey] ?? 0) > 0)<span class="v2-nav-badge">{{ $pendByArea[$aKey] }}</span>@endif
            </a>
            @endforeach
            <a class="v2-nav-item {{ $navActiva === 'mis-conversaciones' ? 'active' : '' }}" href="/v2/mis-conversaciones">
                <span class="ico">👤</span><span class="lbl">Mis conversaciones</span>
                @if($misConv > 0)<span class="v2-nav-badge">{{ $misConv }}</span>@endif
            </a>

            <div class="v2-nav-sec">Trabajo</div>
            <a class="v2-nav-item {{ $navActiva === 'tareas' ? 'active' : '' }}" href="/v2/centro-tareas">
                <span class="ico">✓</span><span class="lbl">Tareas</span>
                @if($misTareasCnt > 0)<span class="v2-nav-badge" style="background:var(--v2-info);">{{ $misTareasCnt }}</span>@endif
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

            @if($u && $u->hasPermiso('admin'))
            <div class="v2-nav-sec">Supervisión</div>
            <a class="v2-nav-item" href="/admin/estadisticas" title="Abre en la UI actual">
                <span class="ico">📈</span><span class="lbl">Reportes</span>
            </a>
            <a class="v2-nav-item" href="/admin" title="Abre en la UI actual">
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
            @yield('content')
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

    async function pulso() {
        const dot = document.getElementById('bots-dot'), txt = document.getElementById('bots-txt');
        try {
            const r = await fetch('/bot-pulso', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            if (d.estado === 'listo') { dot.className = 'v2-dot ok'; txt.textContent = 'Bots ok'; }
            else if (d.has_qr || d.estado === 'iniciando') { dot.className = 'v2-dot warn'; txt.textContent = 'Bot iniciando'; }
            else { dot.className = 'v2-dot err'; txt.textContent = 'Bot caído'; }
        } catch { dot.className = 'v2-dot warn'; txt.textContent = 'Bots ?'; }
    }
    pulso(); setInterval(pulso, 20000);
})();

window.v2toast = function (msg, tipo = 'ok') {
    const el = document.getElementById('v2-toast');
    el.textContent = msg;
    el.className = `v2-toast ${tipo} show`;
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 3000);
};
</script>
<script src="/js/crecer-v2.js?v={{ filemtime(public_path('js/crecer-v2.js')) }}"></script>
@stack('scripts')
</body>
</html>
