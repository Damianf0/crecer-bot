<?php

namespace App\Support;

use App\Events\RecepcionActualizada;

/**
 * Tiempo real de /v2/recepcion. Lo marcan los hooks de ColaAtencion y
 * Derivacion; un solo aviso por solapa y por request (ver AvisoDiferido).
 */
class AvisoRecepcion
{
    public static function marcar(string $tipo): void
    {
        AvisoDiferido::encolar("recepcion:{$tipo}", fn () => broadcast(new RecepcionActualizada($tipo)));
    }
}
