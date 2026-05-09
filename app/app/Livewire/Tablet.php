<?php

namespace App\Livewire;

use App\Models\ColaAtencion;
use App\Models\Contacto;
use App\Services\OmniaService;
use Livewire\Component;

class Tablet extends Component
{
    public string $paso = 'inicio';   // inicio | turno | sin_turno | confirmado | acercarse

    public string $dni = '';
    public string $error = '';

    public ?array $paciente = null;
    public array  $turnos = [];
    public ?array $turnoSeleccionado = null;

    public string $motivo = '';       // turnos | recetas | muestras
    public string $motivoDescripcion = '';

    public string $planta = '';
    public string $sala = '';

    private int $resetSegundos = 15;

    /**
     * @param string|null $dni Si viene desde el cliente (Alpine keypad), lo asignamos
     *                         para evitar 8 round-trips Livewire dígito por dígito.
     */
    public function buscarDni(?string $dni = null): void
    {
        if ($dni !== null) $this->dni = preg_replace('/\D/', '', $dni);
        $this->error = '';

        if (strlen($this->dni) < 7) {
            $this->error = 'Ingresá tu número de DNI completo.';
            return;
        }

        $omnia = app(OmniaService::class);
        $paciente = $omnia->buscarPaciente($this->dni);

        if (!$paciente) {
            // Fallback: buscar en directorio de contactos por DNI
            $contacto = Contacto::where('dni', $this->dni)->first();
            if ($contacto) {
                $this->paciente = [
                    'id'          => null,
                    'nombre'      => $contacto->nombre,
                    'apellido'    => '',
                    'obra_social' => null,
                    'plan'        => null,
                    'primera_vez' => false,
                ];
                $this->paso = 'sin_turno';
                return;
            }
            $this->paso = 'acercarse';
            return;
        }

        $this->paciente = $paciente;
        $this->turnos = $omnia->turnosHoy($paciente['id']);

        if (count($this->turnos) === 1) {
            $this->turnoSeleccionado = $this->turnos[0];
            $this->paso = 'turno';
        } elseif (count($this->turnos) > 1) {
            $this->paso = 'turno';       // muestra lista para elegir
        } else {
            $this->paso = 'sin_turno';   // no tiene turno hoy
        }
    }

    public function seleccionarTurno(int $index): void
    {
        $this->turnoSeleccionado = $this->turnos[$index] ?? null;
    }

    public function confirmarLlegada(): void
    {
        if (!$this->paciente) return;
        if (count($this->turnos) > 1 && !$this->turnoSeleccionado) return;

        $turno = $this->turnoSeleccionado ?? ($this->turnos[0] ?? null);
        $this->planta = $turno['planta'] ?? 'baja';

        ColaAtencion::create([
            'dni'          => $this->dni,
            'nombre'       => $this->paciente['nombre'],
            'apellido'     => $this->paciente['apellido'],
            'obra_social'  => $this->paciente['obra_social'] ?? null,
            'plan'         => $this->paciente['plan'] ?? null,
            'omnia_turno_id' => $turno['id'] ?? null,
            'profesional'  => $turno['profesional'] ?? null,
            'practica'     => $turno['practica'] ?? null,
            'turno_hora'   => $turno['hora'] ?? null,
            'planta'       => $this->planta,
            'motivo'       => 'turno',
            'primera_vez'  => $this->paciente['primera_vez'] ?? false,
            'sin_turno'    => false,
            'checklist'    => ColaAtencion::checklistDefault(),
            'hora_llegada' => now(),
            'orden'        => ColaAtencion::max('orden') + 1,
        ]);

        $this->paso = 'confirmado';
        $this->dispatch('iniciarReset', segundos: $this->resetSegundos, componentId: $this->getId());
    }

    public function confirmarSinTurno(?string $motivo = null, ?string $descripcion = null): void
    {
        if ($motivo !== null)      $this->motivo = $motivo;
        if ($descripcion !== null) $this->motivoDescripcion = $descripcion;
        if (!$this->paciente || !$this->motivo) return;

        ColaAtencion::create([
            'dni'         => $this->dni,
            'nombre'      => $this->paciente['nombre'],
            'apellido'    => $this->paciente['apellido'],
            'obra_social' => $this->paciente['obra_social'] ?? null,
            'plan'        => $this->paciente['plan'] ?? null,
            'planta'      => null,
            'motivo'      => $this->motivo,
            'primera_vez' => $this->paciente['primera_vez'] ?? false,
            'sin_turno'   => true,
            'checklist'   => ColaAtencion::checklistDefault(),
            'nota'        => $this->motivoDescripcion ?: null,
            'hora_llegada' => now(),
            'orden'       => ColaAtencion::max('orden') + 1,
        ]);

        $this->paso = 'confirmado';
        $this->dispatch('iniciarReset', segundos: $this->resetSegundos, componentId: $this->getId());
    }

    public function agregarDigito(string $d): void
    {
        if (strlen($this->dni) < 8) $this->dni .= $d;
    }

    public function borrarDigito(): void
    {
        $this->dni = substr($this->dni, 0, -1);
    }

    public function reset2(): void
    {
        $this->paso = 'inicio';
        $this->dni  = '';
        $this->error = '';
        $this->paciente = null;
        $this->turnos = [];
        $this->turnoSeleccionado = null;
        $this->motivo = '';
        $this->motivoDescripcion = '';
        $this->planta = '';
    }

    public function render()
    {
        return view('livewire.tablet')
            ->layout('layouts.tablet');
    }
}
