<?php

namespace App\Jobs;

use App\Models\DifusionDestinatario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** Acuse ficticio del proveedor "simulado" (ver CanalSimulado). */
class SimularAcuseDifusion implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public string $mensajeId, public string $estado)
    {
        $this->onQueue('difusion');
    }

    public function handle(): void
    {
        DifusionDestinatario::where('mensaje_id', $this->mensajeId)->first()?->aplicarAcuse($this->estado);
    }
}
