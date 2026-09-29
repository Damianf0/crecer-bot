<?php

namespace App\Services\Difusion;

/**
 * Proveedor por el que sale una difusión. Todo el módulo (campañas, audiencias,
 * estadísticas) habla con esta interfaz; cambiar de proveedor es cambiar
 * DIFUSION_PROVEEDOR, no el código.
 */
interface Canal
{
    public function nombre(): string;

    /**
     * Manda un mensaje. $adjunto: ['path' => absoluto, 'nombre' => ..., 'mime' => ...] o null.
     * No tira excepciones: todo resultado (incluido "el proveedor no responde")
     * vuelve como ResultadoEnvio.
     */
    public function enviar(string $telefono, string $texto, ?array $adjunto): ResultadoEnvio;

    /** Corre después de guardar un envío exitoso (el destinatario ya tiene su mensaje_id). */
    public function alRegistrar(string $mensajeId): void;

    /** Para la pantalla: ['ok' => bool, 'detalle' => string]. */
    public function estado(): array;

    /** Pausas reales entre envíos (el simulado usa pausas cortas para probar rápido). */
    public function pausaSegundos(): int;
}
