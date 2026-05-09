<div wire:poll.8s>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h1 style="font-size:18px;font-weight:600;">Pacientes listas para atender</h1>
        <input wire:model.live="profesional"
               placeholder="Filtrar por profesional..."
               style="background:var(--card);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:7px 12px;font-size:13px;width:240px;">
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
        @forelse ($pacientes as $p)
            <div style="background:var(--card);border:1px solid var(--border);border-radius:8px;padding:20px;">
                <div style="font-size:17px;font-weight:600;margin-bottom:4px;">{{ $p->nombre_completo }}</div>
                <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">DNI {{ $p->dni }}</div>

                <div style="font-size:13px;margin-bottom:4px;">{{ $p->practica }}</div>
                <div style="font-size:12px;color:var(--muted);margin-bottom:16px;">{{ $p->profesional }}</div>

                @foreach ($p->getFlags() as $flag)
                    <span style="font-size:12px;margin-right:6px;">{{ $flag['icon'] }} {{ $flag['label'] }}</span>
                @endforeach

                <div style="margin-top:16px;display:flex;gap:8px;justify-content:space-between;align-items:center;">
                    <span style="font-size:11px;color:var(--muted);">
                        Liberada {{ $p->hora_liberado?->format('H:i') }}
                    </span>
                    <button wire:click="llamar({{ $p->id }})"
                            style="background:var(--accent);border:none;border-radius:6px;color:#fff;padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer;">
                        Llamar
                    </button>
                </div>
            </div>
        @empty
            <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--muted);">
                <div style="font-size:40px;margin-bottom:12px;">○</div>
                No hay pacientes listas todavía
            </div>
        @endforelse
    </div>

</div>
