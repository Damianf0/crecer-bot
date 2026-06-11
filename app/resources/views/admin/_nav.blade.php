@php
    // En el shell V2 (/v2/admin/*) los tabs quedan dentro de la V2; en
    // producción siguen siendo /admin/*. El active-check usa el path sin v2/.
    $esV2 = str_starts_with(request()->path(), 'v2/');
    $base = $esV2 ? '/v2/admin' : '/admin';
    $cur  = preg_replace('#^v2/#', '', request()->path());
@endphp
<style>
.adm-nav {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--border);
    margin: -24px -24px 20px;
    padding: 0 24px;
    background: var(--surface);
    overflow-x: auto;
}
.adm-nav a {
    display: inline-flex;
    align-items: center;
    padding: 12px 16px;
    font-size: 13px;
    color: var(--muted);
    text-decoration: none;
    border-bottom: 2px solid transparent;
    white-space: nowrap;
    transition: .15s;
}
.adm-nav a:hover { color: var(--text); }
.adm-nav a.active {
    color: var(--text);
    border-bottom-color: var(--accent);
    font-weight: 600;
}
</style>
<nav class="adm-nav">
    <a href="{{ $base }}"           class="{{ $cur === 'admin' ? 'active' : '' }}">Estado bot</a>
    <a href="{{ $base }}/textos"    class="{{ str_starts_with($cur, 'admin/textos') ? 'active' : '' }}">Textos</a>
    <a href="{{ $base }}/pruebas"   class="{{ str_starts_with($cur, 'admin/pruebas') ? 'active' : '' }}">Pruebas</a>
    <a href="{{ $base }}/logs"      class="{{ str_starts_with($cur, 'admin/logs') ? 'active' : '' }}">Logs</a>
    <a href="{{ $base }}/usuarios"  class="{{ str_starts_with($cur, 'admin/usuarios') ? 'active' : '' }}">Usuarios</a>
    <a href="{{ $base }}/medicos"   class="{{ str_starts_with($cur, 'admin/medicos') ? 'active' : '' }}">Médicos</a>
    <a href="{{ $base }}/respuestas-rapidas" class="{{ str_starts_with($cur, 'admin/respuestas-rapidas') ? 'active' : '' }}">Respuestas rápidas</a>
    <a href="{{ $base }}/legajo"    class="{{ str_starts_with($cur, 'admin/legajo') ? 'active' : '' }}">Legajo</a>
    <a href="{{ $base }}/estadisticas" class="{{ str_starts_with($cur, 'admin/estadisticas') ? 'active' : '' }}">Estadísticas</a>
    <a href="{{ $base }}/tunnel"    class="{{ str_starts_with($cur, 'admin/tunnel') ? 'active' : '' }}">Acceso remoto</a>
</nav>
