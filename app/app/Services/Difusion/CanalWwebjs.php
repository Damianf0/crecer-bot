<?php

namespace App\Services\Difusion;

use Illuminate\Support\Facades\Http;

/**
 * El 4º bot (bot-difusion, whatsapp-web.js con QR). Mismos endpoints que los
 * bots de las colas: /check-numero para saber si el número tiene WhatsApp y su
 * JID real, y /enviar o /enviar-archivo. Los acuses llegan por
 * POST /api/bot/difusion/ack (ver bot/whatsapp.js).
 *
 * Una llamada por destinatario, espaciada por la pausa de la campaña: nunca en
 * ráfaga (incidente 19/05, bombardeo CDP).
 */
class CanalWwebjs implements Canal
{
    public function __construct(private string $botUrl, private string $etiqueta = 'Bot') {}

    public function nombre(): string { return 'wwebjs'; }

    private function http()
    {
        return Http::withToken((string) config('app.bot_ingress_token'))
            ->baseUrl(rtrim($this->botUrl, '/'))
            ->acceptJson();
    }

    public function enviar(string $telefono, string $texto, ?array $adjunto): ResultadoEnvio
    {
        try {
            $chk = $this->http()->timeout(20)->post('/check-numero', ['numero' => $telefono]);
            if (!$chk->successful()) return ResultadoEnvio::transitorio("check-numero HTTP {$chk->status()}");
            if (!$chk->json('registered')) return ResultadoEnvio::omitido('No tiene WhatsApp');
            $jid = (string) $chk->json('normalizedId');

            if ($adjunto) {
                $r = $this->http()->timeout(60)->post('/enviar-archivo', [
                    'contacto' => $jid,
                    'base64'   => base64_encode(file_get_contents($adjunto['path'])),
                    'mimetype' => $adjunto['mime'],
                    'filename' => $adjunto['nombre'],
                    'caption'  => $texto,
                    'difusion' => true,   // el bot reporta sus acuses (ver bot/difusion.js)
                ]);
            } else {
                $r = $this->http()->timeout(30)->post('/enviar', ['contacto' => $jid, 'texto' => $texto, 'difusion' => true]);
            }
        } catch (\Throwable $e) {
            return ResultadoEnvio::transitorio('El bot de difusiones no responde: ' . $e->getMessage());
        }

        if ($r->status() >= 500 || $r->status() === 503) {
            return ResultadoEnvio::transitorio($r->json('error') ?: "HTTP {$r->status()}");
        }
        if (!$r->successful() || !$r->json('ok')) {
            return ResultadoEnvio::fallido($r->json('error') ?: "HTTP {$r->status()}");
        }
        $waId = (string) $r->json('wa_id');
        return ResultadoEnvio::enviado($waId, self::chatDeWaId($waId) ?: $jid);
    }

    /** "true_<chat>_<id>[_<participante>]" → "<chat>" (el chat real, puede ser @lid). */
    public static function chatDeWaId(string $waId): ?string
    {
        $partes = explode('_', $waId);
        return count($partes) >= 3 ? $partes[1] : null;
    }

    public function alRegistrar(string $mensajeId): void {}   // los acuses los manda el bot

    public function estado(): array
    {
        try {
            $r = Http::timeout(4)->get(rtrim($this->botUrl, '/') . '/status');
            $st = $r->json('status');
            return $st === 'listo'
                ? ['ok' => true,  'detalle' => 'Conectado (' . ($r->json('phone') ?: 'sin número') . ').', 'numero' => $r->json('phone')]
                : ['ok' => false, 'detalle' => "Estado: {$st}" . ($r->json('has_qr') ? ' — falta escanear el QR.' : '.')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detalle' => 'El bot no está levantado.'];
        }
    }

    public function pausaSegundos(): int
    {
        return random_int((int) config('difusion.pausa_min_seg'), max((int) config('difusion.pausa_min_seg'), (int) config('difusion.pausa_max_seg')));
    }
}
