<?php

namespace App\Services\Difusion;

final class ResultadoEnvio
{
    /**
     * @param string $tipo enviado | omitido (no se le puede mandar: sin WhatsApp,
     *                     número inválido) | fallido (el proveedor lo rechazó) |
     *                     transitorio (el proveedor no responde: se reintenta)
     */
    private function __construct(
        public readonly string $tipo,
        public readonly ?string $mensajeId = null,
        public readonly ?string $chatId = null,
        public readonly ?string $error = null,
    ) {}

    public static function enviado(string $mensajeId, ?string $chatId): self
    {
        return new self('enviado', $mensajeId, $chatId);
    }

    public static function omitido(string $motivo): self    { return new self('omitido', error: $motivo); }
    public static function fallido(string $motivo): self    { return new self('fallido', error: $motivo); }
    public static function transitorio(string $motivo): self { return new self('transitorio', error: $motivo); }
}
