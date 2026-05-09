<?php

namespace App\Livewire;

use App\Models\Derivacion;
use Livewire\Component;

class ColaBot extends Component
{
    public ?int $fichaId = null;
    public string $nota = '';
    public string $toastMsg = '';
    public string $toastType = '';
    public bool $mostrarPrueba = true;

    public function getColaProperty()
    {
        return Derivacion::where('estado', '!=', 'resuelto')
            ->when(!$this->mostrarPrueba, fn($q) => $q->where('es_prueba', false))
            ->orderBy('created_at')
            ->get();
    }

    public function getFichaProperty(): ?Derivacion
    {
        return $this->fichaId ? Derivacion::find($this->fichaId) : null;
    }

    public function abrirFicha(int $id): void
    {
        $this->fichaId = $id;
        $d = Derivacion::find($id);
        $this->nota = $d?->nota ?? '';

        if ($d && $d->estado === 'pendiente') {
            $d->update(['estado' => 'en_atencion']);
        }
    }

    public function cerrarFicha(): void
    {
        $this->fichaId = null;
        $this->nota = '';
    }

    public function guardarNota(int $id): void
    {
        Derivacion::find($id)?->update(['nota' => $this->nota]);
        $this->toast('Nota guardada', 'ok');
    }

    public function resolver(int $id): void
    {
        Derivacion::find($id)?->update([
            'estado'      => 'resuelto',
            'atendido_at' => now(),
            'nota'        => $this->nota ?: null,
        ]);
        $this->fichaId = null;
        $this->nota = '';
        $this->toast('Derivación resuelta', 'ok');
    }

    private function toast(string $msg, string $type): void
    {
        $this->toastMsg  = $msg;
        $this->toastType = $type;
        $this->dispatch('toast');
    }

    public function render()
    {
        return view('livewire.cola-bot', [
            'cola'  => $this->cola,
            'ficha' => $this->ficha,
        ])->layout('layouts.app', ['title' => 'Mensajes WhatsApp']);
    }
}
