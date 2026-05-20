<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast cuando un usuario envía un mensaje al chat interno.
 * Lo emite ChatController::enviar() después de persistir el mensaje en BD.
 *
 * Usamos ShouldBroadcastNow (no encola) porque el chat es real-time y la
 * cola podría agregar latencia inaceptable. El payload es liviano.
 *
 * El frontend (React + Echo) escucha 'ChatMensajeEnviado' en el canal
 * privado 'chat.canal.{id}' al que está suscripto.
 */
class ChatMensajeEnviado implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int   $canalId;
    public array $mensaje;

    public function __construct(int $canalId, array $mensaje)
    {
        $this->canalId = $canalId;
        $this->mensaje = $mensaje;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("chat.canal.{$this->canalId}");
    }

    public function broadcastAs(): string
    {
        return 'ChatMensajeEnviado';
    }

    public function broadcastWith(): array
    {
        return [
            'canal_id' => $this->canalId,
            'mensaje'  => $this->mensaje,
        ];
    }
}
