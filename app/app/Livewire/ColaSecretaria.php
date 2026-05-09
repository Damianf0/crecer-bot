<?php

namespace App\Livewire;

use App\Models\ColaAtencion;
use Livewire\Component;

class ColaSecretaria extends Component
{
    public ?int $fichaId = null;     // paciente con ficha abierta
    public string $nota = '';
    public string $toastMsg = '';
    public string $toastType = '';

    // Umbral de alerta de espera en minutos
    public int $umbralEspera = 20;

    public function getFichaProperty(): ?ColaAtencion
    {
        return $this->fichaId ? ColaAtencion::find($this->fichaId) : null;
    }

    public function getColaProperty()
    {
        return ColaAtencion::activos()->get();
    }

    // Una sola UPDATE en lote en lugar de N updates dentro del getter
    public function revisarAlertas(): void
    {
        ColaAtencion::activos()
            ->where('alerta_espera', false)
            ->where('hora_llegada', '<=', now()->subMinutes($this->umbralEspera))
            ->update(['alerta_espera' => true]);
    }

    public function abrirFicha(int $id): void
    {
        $this->fichaId = $id;
        $paciente = ColaAtencion::find($id);
        $this->nota = $paciente?->nota ?? '';

        if ($paciente && $paciente->estado === 'esperando') {
            $paciente->update([
                'estado'       => 'en_atencion',
                'hora_llamado' => now(),
            ]);
        }
    }

    public function cerrarFicha(): void
    {
        $this->fichaId = null;
        $this->nota = '';
    }

    public function toggleChecklist(int $pacienteId, string $itemId): void
    {
        $paciente = ColaAtencion::find($pacienteId);
        if (!$paciente) return;

        $checklist = $paciente->checklist ?? [];
        foreach ($checklist as &$item) {
            if ($item['id'] === $itemId) {
                $item['done'] = !$item['done'];
                break;
            }
        }
        $paciente->update(['checklist' => $checklist]);
    }

    public function guardarNota(int $pacienteId): void
    {
        ColaAtencion::find($pacienteId)?->update(['nota' => $this->nota]);
        $this->toast('Nota guardada', 'ok');
    }

    public function liberarASala(int $pacienteId): void
    {
        $paciente = ColaAtencion::find($pacienteId);
        if (!$paciente || !$paciente->checklistCompleto()) {
            $this->toast('Completá los ítems obligatorios primero', 'error');
            return;
        }

        $paciente->update([
            'estado'        => 'liberado',
            'hora_liberado' => now(),
        ]);

        $this->fichaId = null;
        $this->toast('Paciente liberada a sala', 'ok');
    }

    public function resolverSinLiberar(int $pacienteId): void
    {
        ColaAtencion::find($pacienteId)?->update(['estado' => 'resuelto']);
        $this->fichaId = null;
        $this->toast('Caso resuelto', 'ok');
    }

    public function subirOrden(int $id): void
    {
        $p = ColaAtencion::find($id);
        $anterior = ColaAtencion::activos()
            ->where('orden', '<', $p->orden)
            ->orderBy('orden', 'desc')
            ->first();

        if ($p && $anterior) {
            [$p->orden, $anterior->orden] = [$anterior->orden, $p->orden];
            $p->save();
            $anterior->save();
        }
    }

    public function bajarOrden(int $id): void
    {
        $p = ColaAtencion::find($id);
        $siguiente = ColaAtencion::activos()
            ->where('orden', '>', $p->orden)
            ->orderBy('orden')
            ->first();

        if ($p && $siguiente) {
            [$p->orden, $siguiente->orden] = [$siguiente->orden, $p->orden];
            $p->save();
            $siguiente->save();
        }
    }

    public function reordenar(array $ids): void
    {
        foreach ($ids as $orden => $id) {
            ColaAtencion::where('id', (int) $id)->update(['orden' => $orden + 1]);
        }
    }

    private function toast(string $msg, string $type): void
    {
        $this->toastMsg  = $msg;
        $this->toastType = $type;
        $this->dispatch('toast');
    }

    public function render()
    {
        return view('livewire.cola-secretaria', [
            'cola'  => $this->cola,
            'ficha' => $this->ficha,
        ])->layout('layouts.app');
    }
}
