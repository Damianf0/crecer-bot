<?php

namespace App\Livewire;

use App\Models\SesionSecretaria;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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
            'todasLasColas' => User::COLAS,
            'usuario'       => Auth::user(),
        ])->layout('layouts.minimal');
    }
}
