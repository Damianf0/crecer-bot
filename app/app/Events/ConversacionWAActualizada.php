<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Aviso de que una conversación de WhatsApp cambió (mensaje nuevo, tomada,
 * delegada, resuelta, derivada…). Viaja SOLO el id: el panel vuelve a pedir la
 * cola y, si la tiene abierta, la conversación — así el payload no duplica la
 * lógica de buildItems ni filtra datos del paciente por el socket.
 *
 * No se dispara directo: pasa por App\Support\AvisoColaWA, que deduplica por
 * request y nunca deja que una caída de Reverb rompa la ingesta.
 */
class ConversacionWAActualizada implements ShouldBroadcastNow
{
    public function __construct(
        public int $id,
        public string $area,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("wa.area.{$this->area}");
    }

    public function broadcastAs(): string
    {
        return 'ConversacionWAActualizada';
    }
}
