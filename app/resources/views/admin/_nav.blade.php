@php $cur = request()->path(); @endphp
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
    <a href="/admin"           class="{{ $cur === 'admin' ? 'active' : '' }}">Estado bot</a>
    <a href="/admin/textos"    class="{{ str_starts_with($cur, 'admin/textos') ? 'active' : '' }}">Textos</a>
    <a href="/admin/pruebas"   class="{{ str_starts_with($cur, 'admin/pruebas') ? 'active' : '' }}">Pruebas</a>
    <a href="/admin/logs"      class="{{ str_starts_with($cur, 'admin/logs') ? 'active' : '' }}">Logs</a>
    <a href="/admin/usuarios"  class="{{ str_starts_with($cur, 'admin/usuarios') ? 'active' : '' }}">Usuarios</a>
    <a href="/admin/legajo"    class="{{ str_starts_with($cur, 'admin/legajo') ? 'active' : '' }}">Legajo</a>
    <a href="/admin/estadisticas" class="{{ str_starts_with($cur, 'admin/estadisticas') ? 'active' : '' }}">Estadísticas</a>
</nav>
