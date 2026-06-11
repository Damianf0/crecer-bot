@extends($layout ?? 'layouts.app')
@section('title', 'Admin · Legajo')

@section('content')
@include('admin._nav')

<style>
.lg-conf-card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 20px; max-width: 720px; }
.lg-stat { display: flex; gap: 30px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border); }
.lg-stat div { text-align: left; }
.lg-stat b { display: block; font-size: 22px; font-weight: 700; }
.lg-stat span { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; }
.lg-label { display: block; font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
.lg-input { width: 100%; padding: 9px 12px; border-radius: 7px; border: 1px solid var(--border); background: var(--surface); color: var(--text); font-size: 13px; font-family: monospace; }
.lg-input:focus { outline: none; border-color: var(--info); }
.lg-aviso { background: color-mix(in srgb, var(--warning) 12%, transparent); border: 1px solid color-mix(in srgb, var(--warning) 30%, transparent); color: var(--warning); padding: 10px 14px; border-radius: 7px; font-size: 12px; margin-top: 12px; }
.lg-ok    { background: color-mix(in srgb, var(--success) 12%, transparent); border: 1px solid color-mix(in srgb, var(--success) 30%, transparent); color: var(--success); padding: 10px 14px; border-radius: 7px; font-size: 12px; margin-top: 12px; display: none; }
.lg-err   { background: color-mix(in srgb, var(--error)   12%, transparent); border: 1px solid color-mix(in srgb, var(--error)   30%, transparent); color: var(--error);   padding: 10px 14px; border-radius: 7px; font-size: 12px; margin-top: 12px; display: none; }
</style>

<h2 style="font-size:16px;font-weight:700;margin-bottom:14px;">Legajo de documentos</h2>

<div class="lg-conf-card">
    <div class="lg-stat">
        <div><b>{{ number_format($totalDocs) }}</b><span>Documentos indexados</span></div>
        <div><b>{{ number_format($tamanioTotal/1024/1024, 1) }} MB</b><span>Tamaño total</span></div>
        <div><b style="color:{{ $esEscribible ? 'var(--success)' : 'var(--error)' }};">{{ $esEscribible ? '✓ OK' : '⚠ Error' }}</b><span>Escritura</span></div>
    </div>

    <label class="lg-label">Path de almacenamiento</label>
    <input class="lg-input" id="path-input" value="{{ $pathActual }}">
    <div style="font-size:11px;color:var(--muted);margin-top:5px;">
        Default: <code>{{ $pathDefault }}</code>. Debe ser absoluto y escribible por el proceso PHP-FPM.
    </div>

    <div class="lg-aviso">
        ⚠ Cambiar el path NO mueve los documentos existentes — solo afecta a los que se indexen a partir de ahora.
        Si querés migrar los archivos físicos al nuevo path, hacelo manualmente desde el host o pedime un comando para eso.
    </div>

    <div class="lg-ok"  id="ok"></div>
    <div class="lg-err" id="err"></div>

    <div style="display:flex;gap:8px;margin-top:18px;">
        <button onclick="guardar()" id="btn-save" style="background:var(--success);border:none;color:#fff;padding:9px 20px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;">Guardar path</button>
        <button onclick="document.getElementById('path-input').value='{{ $pathDefault }}'" style="background:transparent;border:1px solid var(--border);color:var(--muted);padding:9px 16px;border-radius:7px;font-size:13px;cursor:pointer;">Restaurar default</button>
    </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
async function guardar() {
    const path = document.getElementById('path-input').value.trim();
    const btn  = document.getElementById('btn-save');
    document.getElementById('ok').style.display = 'none';
    document.getElementById('err').style.display = 'none';
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
        const r = await fetch('/admin/legajo/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ path }),
        });
        const d = await r.json();
        if (!d.ok) { document.getElementById('err').textContent = d.error; document.getElementById('err').style.display = 'block'; }
        else      { document.getElementById('ok').textContent  = '✓ ' + (d.aviso || 'Guardado'); document.getElementById('ok').style.display = 'block'; }
    } catch (e) {
        document.getElementById('err').textContent = 'Error de red'; document.getElementById('err').style.display = 'block';
    } finally {
        btn.disabled = false; btn.textContent = 'Guardar path';
    }
}
</script>
@endsection
