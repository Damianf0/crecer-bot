<?php

namespace App\Livewire;

use App\Models\ColaAtencion;
use App\Models\Contacto;
use App\Services\ChecklistRecepcion;
use App\Services\OmniaService;
use Livewire\Component;

use function Illuminate\Support\defer;

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

        // Arranca con la obra social de la ficha: el tablet no espera a Omnia
        // (ver corregirConFinanciadorDelTurno).
        $financiador = $this->paciente['financiador'] ?? $this->paciente['obra_social'] ?? null;
        $plan        = $this->paciente['plan'] ?? null;
        $practicas   = $turno['practicas'] ?? array_filter([$turno['practica'] ?? null]);

        $fila = ColaAtencion::create([
            'dni'          => $this->dni,
            'nombre'       => $this->paciente['nombre'],
            'apellido'     => $this->paciente['apellido'],
            'obra_social'  => $this->paciente['obra_social'] ?? null,
            'plan'         => $plan,
            'financiador'  => $financiador,
            'omnia_turno_id' => $turno['id'] ?? null,
            'profesional'  => $turno['profesional'] ?? null,
            'practica'     => $turno['practica'] ?? null,
            'practicas'    => $practicas,
            'turno_hora'   => $turno['hora'] ?? null,
            'planta'       => $this->planta,
            'motivo'       => 'turno',
            'primera_vez'  => $this->paciente['primera_vez'] ?? false,
            'sin_turno'    => false,
            'checklist'    => ChecklistRecepcion::para($financiador, $plan, $practicas),
            'hora_llegada' => now(),
            'orden'        => ColaAtencion::max('orden') + 1,
        ]);

        if (!empty($turno['id'])) {
            $turnoId = $turno['id'];
            defer(fn () => self::corregirConFinanciadorDelTurno($fila, $turnoId, $practicas));
        }

        $this->paso = 'confirmado';
        $this->dispatch('iniciarReset', segundos: $this->resetSegundos, componentId: $this->getId());
    }

    /**
     * El checklist va con el financiador DEL TURNO, que puede no ser el de la
     * ficha (una paciente con OSFATLYF vino como Particular). Omnia lo da en el
     * reporte ambulatorio, que tarda segundos: antes el paciente esperaba en el
     * tablet (hasta ~17 s si Omnia estaba lento). Ahora corre después de
     * responderle y, si difiere, corrige la fila; el saved avisa a recepción.
     * El checklist se rearma solo si nadie empezó a tildarlo.
     */
    private static function corregirConFinanciadorDelTurno(ColaAtencion $fila, int|string $turnoId, array $practicas): void
    {
        try {
            $delTurno = app(OmniaService::class)->financiadorDelTurno($turnoId);
        } catch (\Throwable) {
            return;   // sin Omnia queda el de la ficha, como antes
        }
        if (!$delTurno) return;

        $fila->refresh();
        $financiador = $delTurno['financiador'] ?? $fila->financiador;
        $plan        = $delTurno['plan'] ?? $fila->plan;
        if ($financiador === $fila->financiador && $plan === $fila->plan) return;

        $cambios = ['financiador' => $financiador, 'plan' => $plan];
        $tildado = collect($fila->checklist ?? [])->contains(fn ($i) => !empty($i['done']));
        if (!$tildado) $cambios['checklist'] = ChecklistRecepcion::para($financiador, $plan, $practicas);
        $fila->update($cambios);
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
            'financiador' => $this->paciente['financiador'] ?? null,
            'planta'      => null,
            'motivo'      => $this->motivo,
            'primera_vez' => $this->paciente['primera_vez'] ?? false,
            'sin_turno'   => true,
            // Sin turno no hay práctica: aplican las reglas generales y las de su obra social.
            'checklist'   => ChecklistRecepcion::para(
                $this->paciente['financiador'] ?? $this->paciente['obra_social'] ?? null,
                $this->paciente['plan'] ?? null,
            ),
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
