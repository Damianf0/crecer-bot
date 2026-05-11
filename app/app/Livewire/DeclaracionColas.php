<?php

namespace App\Livewire;

use App\Models\ConversacionWA;
use App\Models\SesionSecretaria;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class DeclaracionColas extends Component
{
    public array $colasSeleccionadas = [];
    public string $error = '';

    public function mount(): void
    {
        // Pre-seleccionar colas de la sesión anterior si existe
        $sesionAnterior = Auth::user()->sesiones()
            ->latest('inicio_sesion')
            ->first();

        if ($sesionAnterior) {
            $this->colasSeleccionadas = $sesionAnterior->colas;
        }
    }

    private function conversacionesPendientes(): \Illuminate\Support\Collection
    {
        return ConversacionWA::where('estado', 'activa')
            ->where('asignada_a', Auth::id())
            ->with('ultimoMensaje')
            ->orderByDesc('urgente')
            ->orderByDesc('ultima_actividad')
            ->limit(6)
            ->get()
            ->map(fn ($c) => [
                'id'        => $c->id,
                'nombre'    => $c->nombreOTelefono ?: $c->contacto,
                'preview'   => $c->ultimoMensaje?->contenido
                    ? Str::limit($c->ultimoMensaje->contenido, 80)
                    : ($c->ultimoMensaje?->tipo ? '['.$c->ultimoMensaje->tipo.']' : '—'),
                'urgente'   => (bool) $c->urgente,
                'no_leidos' => $c->no_leidos,
                'hace'      => $c->ultima_actividad?->diffForHumans(),
            ]);
    }

    private function tareasPendientes(): \Illuminate\Support\Collection
    {
        return Tarea::pendientes()
            ->where('asignada_a', Auth::id())
            ->with('creadaPor:id,nombre_completo')
            ->orderByRaw("FIELD(prioridad,'alta','normal','baja')")
            ->orderByRaw('vence_at IS NULL, vence_at ASC')
            ->limit(6)
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'titulo'     => $t->titulo,
                'descripcion' => $t->descripcion ? Str::limit($t->descripcion, 90) : null,
                'prioridad'  => $t->prioridad,
                'vence_at'   => $t->vence_at?->format('d/m H:i'),
                'vencida'    => $t->vencida,
                'creada_por' => $t->creadaPor?->nombre_completo,
            ]);
    }

    public function toggleCola(string $cola): void
    {
        if (in_array($cola, $this->colasSeleccionadas)) {
            $this->colasSeleccionadas = array_values(
                array_filter($this->colasSeleccionadas, fn($c) => $c !== $cola)
            );
        } else {
            $this->colasSeleccionadas[] = $cola;
        }
    }

    public function confirmar(): void
    {
        if (empty($this->colasSeleccionadas)) {
            $this->error = 'Seleccioná al menos una cola.';
            return;
        }

        // Cerrar sesión activa anterior si existe
        $sesionActiva = Auth::user()->sesionActiva();
        if ($sesionActiva) {
            $sesionActiva->update(['fin_sesion' => now()]);
        }

        // Crear nueva sesión
        SesionSecretaria::create([
            'user_id'       => Auth::id(),
            'colas'         => $this->colasSeleccionadas,
            'inicio_sesion' => now(),
        ]);

        session(['colas' => $this->colasSeleccionadas]);

        $this->redirect('/secretaria', navigate: false);
    }

    public function render()
    {
        return view('livewire.declaracion-colas', [
            'todasLasColas'  => User::COLAS,
            'areas'          => ConversacionWA::AREAS,
            'usuario'        => Auth::user(),
            'conversaciones' => $this->conversacionesPendientes(),
            'tareas'         => $this->tareasPendientes(),
        ])->layout('layouts.minimal');
    }
}
