<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Aviso de que cambió algo en /v2/recepcion. `tipo` dice qué solapa re-pedir:
 * 'sala' (cola_atencion: check-in del tablet, ficha, presente, reorden) o
 * 'bot' (derivaciones). Sin datos del paciente por el socket: el panel vuelve
 * a pedir la lista. Se dispara vía App\Support\AvisoRecepcion.
 */
class RecepcionActualizada implements ShouldBroadcastNow
{
    public function __construct(public string $tipo) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('recepcion');
    }

    public function broadcastAs(): string
    {
        return 'RecepcionActualizada';
    }
}
