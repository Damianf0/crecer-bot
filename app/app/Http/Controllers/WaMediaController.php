<?php

namespace App\Http\Controllers;

/**
 * Sirve los archivos multimedia de mensajes de WhatsApp con auth de sesión.
 *
 * Antes el panel consumía URLs públicas del bot (http://IP:300X/media/...) que
 * se servían sin autenticación a toda la LAN — audios/imágenes de pacientes con
 * el teléfono en el nombre del archivo. Ahora el bot exige token en /media y el
 * panel pasa por acá:
 *
 *   - entrantes:  el bot guarda en ./bot/media → bind mount RO en /bot-media
 *   - salientes:  storeAs('public/wa-media') sobre el disk default (local) →
 *                 storage/app/private/public/wa-media
 *
 * MensajeWA::archivo_url reescribe las URLs históricas a /wa-media/{filename}
 * al leer, así no hace falta migrar datos ni tocar las vistas.
 */
class WaMediaController extends Controller
{
    public function show(string $filename)
    {
        // La ruta ya restringe el formato, pero defensa en profundidad.
        // El @ es por archivos legacy con el JID sin sanitizar ("<ts>_<n>@lid.ogg").
        if (!preg_match('/^[A-Za-z0-9._@-]+$/', $filename) || str_contains($filename, '..')) {
            abort(400);
        }

        $candidatos = [
            '/bot-media/' . $filename,
            storage_path('app/private/public/wa-media/' . $filename),
        ];

        foreach ($candidatos as $abs) {
            if (is_file($abs)) {
                return response()->file($abs, [
                    'Content-Type'  => mime_content_type($abs) ?: 'application/octet-stream',
                    'Cache-Control' => 'private, max-age=86400',
                ]);
            }
        }

        abort(404);
    }
}
