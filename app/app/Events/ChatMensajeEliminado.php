<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast cuando un usuario elimina un mensaje propio.
 * Lo emite ChatController::eliminarMensaje() después del soft-delete.
 *
 * Los suscriptores actualizan su UI para mostrar el mensaje como placeholder
 * eliminado (clase .eliminado en el bubble).
 */
class ChatMensajeEliminado implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $canalId;
    public int $mensajeId;

    public function __construct(int $canalId, int $mensajeId)
    {
        $this->canalId   = $canalId;
        $this->mensajeId = $mensajeId;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("chat.canal.{$this->canalId}");
    }

    public function broadcastAs(): string
    {
        return 'ChatMensajeEliminado';
    }

    public function broadcastWith(): array
    {
        return [
            'canal_id'   => $this->canalId,
            'mensaje_id' => $this->mensajeId,
        ];
    }
}
