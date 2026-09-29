<?php

namespace App\Services\Difusion;

/**
 * WhatsApp Cloud API: Meta directo o un intermediario con el mismo formato
 * (360dialog y similares cambian la URL base y el encabezado de autenticación).
 * El envío todavía no está implementado. Hace falta:
 *  - número verificado en WhatsApp Business + token permanente;
 *  - los mensajes que inicia la clínica solo pueden ser PLANTILLAS aprobadas
 *    por Meta (texto libre únicamente dentro de las 24 h de una respuesta):
 *    la campaña tendría que elegir la plantilla aprobada en vez de un texto;
 *  - un webhook público HTTPS para los acuses (entregado/leído) y las
 *    respuestas, que acá se traducen a DifusionDestinatario::aplicarAcuse y a
 *    la cola 'difusion' como cualquier entrante.
 */
class CanalMeta implements Canal
{
    public function __construct(private array $cfg = []) {}

    public function nombre(): string { return 'cloudapi'; }

    private function configurado(): bool
    {
        return !empty($this->cfg['base_url']) && !empty($this->cfg['phone_number_id']) && !empty($this->cfg['token']);
    }

    public function enviar(string $telefono, string $texto, ?array $adjunto): ResultadoEnvio
    {
        return ResultadoEnvio::fallido('Este proveedor todavía no está conectado.');
    }

    public function alRegistrar(string $mensajeId): void {}

    public function estado(): array
    {
        return $this->configurado()
            ? ['ok' => false, 'detalle' => 'Credenciales cargadas; falta implementar el envío con plantillas aprobadas y el webhook.']
            : ['ok' => false, 'detalle' => 'Sin configurar (falta cuenta, plantillas aprobadas y webhook).'];
    }

    public function pausaSegundos(): int { return 1; }
}
