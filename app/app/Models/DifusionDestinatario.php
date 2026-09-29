<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DifusionDestinatario extends Model
{
    protected $table = 'difusion_destinatarios';

    /** Orden de avance del estado: un acuse viejo nunca hace retroceder. */
    public const ORDEN = ['pendiente' => 0, 'enviado' => 1, 'entregado' => 2, 'leido' => 3];

    protected $fillable = [
        'campania_id', 'contacto_id', 'telefono', 'nombre', 'estado', 'error',
        'mensaje_id', 'chat_id', 'enviado_at', 'entregado_at', 'leido_at', 'respondio_at', 'baja_at',
    ];

    protected $casts = [
        'enviado_at'   => 'datetime',
        'entregado_at' => 'datetime',
        'leido_at'     => 'datetime',
        'respondio_at' => 'datetime',
        'baja_at'      => 'datetime',
    ];

    public function campania(): BelongsTo
    {
        return $this->belongsTo(DifusionCampania::class, 'campania_id');
    }

    /**
     * Aplica un acuse del proveedor (entregado / leído / fallido). Idempotente
     * y sin retrocesos: los acuses pueden llegar repetidos o desordenados.
     */
    public function aplicarAcuse(string $estado, ?string $error = null): void
    {
        $ahora = now();
        if ($estado === 'fallido') {
            if (in_array($this->estado, ['pendiente', 'enviado'], true)) {
                $this->update(['estado' => 'fallido', 'error' => $error ? mb_substr($error, 0, 255) : 'Rechazado por WhatsApp']);
            }
            return;
        }
        if (!isset(self::ORDEN[$estado]) || $this->estado === 'fallido') return;

        $cambios = [];
        // Leído implica entregado aunque el acuse de entrega no haya llegado.
        if (self::ORDEN[$estado] >= self::ORDEN['entregado'] && !$this->entregado_at) $cambios['entregado_at'] = $ahora;
        if ($estado === 'leido' && !$this->leido_at) $cambios['leido_at'] = $ahora;
        if (self::ORDEN[$estado] > (self::ORDEN[$this->estado] ?? 0)) $cambios['estado'] = $estado;
        if ($cambios) $this->update($cambios);
    }
}
