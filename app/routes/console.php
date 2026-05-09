<?php

use App\Models\Contacto;
use App\Models\ConversacionWA;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Audita contactos sin wa_id resuelto agrupándolos por motivo.
 *   sin_telefono     → telefono vacío en BD
 *   formato_invalido → digitos no normalizables (fijo, número corto, internacional raro)
 *   no_es_whatsapp   → bot dice que el número NO está registrado en WA
 *
 * Uso: docker exec crecer-web-1 php artisan contactos:auditar-telefonos
 *      docker exec crecer-web-1 php artisan contactos:auditar-telefonos --csv=/var/www/html/storage/logs/audit.csv
 */
Artisan::command('contactos:auditar-telefonos {--csv=}', function () {
    $csvPath = $this->option('csv');
    $csv = null;
    if ($csvPath) {
        $csv = fopen($csvPath, 'w');
        fputcsv($csv, ['id', 'nombre', 'telefono_raw', 'telefono_normalizado', 'motivo']);
    }

    $contactos = Contacto::whereNull('wa_id')->get(['id', 'nombre', 'telefono']);
    $this->info("Auditando " . $contactos->count() . " contactos sin wa_id…");

    $stats = ['sin_telefono' => 0, 'formato_invalido' => 0, 'no_es_whatsapp' => 0];

    $bar = $this->output->createProgressBar($contactos->count());
    $bar->start();

    foreach ($contactos as $c) {
        $motivo = null;
        $norm   = '';

        if (empty($c->telefono)) {
            $motivo = 'sin_telefono';
        } else {
            $norm = Contacto::normalizarTelefono($c->telefono);
            if (!$norm) {
                $motivo = 'formato_invalido';
            } else {
                // Reintentar resolver — el bot puede haber estado caído cuando se procesó antes
                $waId = Contacto::resolverWaId($norm);
                if ($waId) {
                    $duplicado = Contacto::where('wa_id', $waId)->where('id', '!=', $c->id)->exists();
                    if (!$duplicado) {
                        $c->update(['wa_id' => $waId]);
                        // Sale de la auditoría → no contar
                        $bar->advance();
                        continue;
                    }
                }
                $motivo = 'no_es_whatsapp';
            }
        }

        $stats[$motivo]++;
        if ($csv) fputcsv($csv, [$c->id, $c->nombre, $c->telefono, $norm, $motivo]);
        $bar->advance();
        usleep(80_000);  // throttle bot
    }

    $bar->finish();
    $this->newLine();

    if ($csv) {
        fclose($csv);
        $this->info("CSV exportado a: $csvPath");
    }

    $this->newLine();
    $this->info('Resumen por motivo:');
    foreach ($stats as $motivo => $count) {
        $label = match ($motivo) {
            'sin_telefono'     => 'Sin teléfono',
            'formato_invalido' => 'Formato inválido (fijo, internacional raro, etc.)',
            'no_es_whatsapp'   => 'Número válido pero NO está en WhatsApp',
        };
        $this->line(sprintf("  %-50s %5d", $label, $count));
    }
    $this->info('Total: ' . array_sum($stats));
})->purpose('Audita contactos sin wa_id agrupando por motivo y opcionalmente exporta CSV');

/**
 * Sincroniza fotos de perfil de contactos con wa_id resuelto.
 * Por default: solo los que no tienen avatar o cuyo cache expiró (TTL 7 días).
 * Con --force: re-sync de todos.
 *
 * Uso: docker exec crecer-web-1 php artisan contactos:sync-avatares
 *      docker exec crecer-web-1 php artisan contactos:sync-avatares --force
 *      docker exec crecer-web-1 php artisan contactos:sync-avatares --limit=50
 */
Artisan::command('contactos:sync-avatares {--force} {--limit=}', function () {
    $force = $this->option('force');
    $limit = (int) $this->option('limit') ?: 0;

    $q = Contacto::whereNotNull('wa_id');
    if (!$force) {
        $q->where(function ($w) {
            $w->whereNull('avatar_actualizado_at')
              ->orWhere('avatar_actualizado_at', '<', now()->subDays(Contacto::AVATAR_TTL_DAYS));
        });
    }
    if ($limit > 0) $q->limit($limit);

    $contactos = $q->get();
    $this->info("Contactos a procesar: {$contactos->count()}" . ($force ? ' (--force activo)' : ''));

    if ($contactos->isEmpty()) { $this->info('Nada por hacer.'); return; }

    $bar = $this->output->createProgressBar($contactos->count());
    $bar->start();
    $con_foto = 0; $sin_foto = 0;

    foreach ($contactos as $c) {
        if (Contacto::sincronizarAvatar($c)) $con_foto++;
        else $sin_foto++;
        $bar->advance();
        usleep(500_000);  // 500ms throttle (descarga puede ser pesada)
    }
    $bar->finish();
    $this->newLine();
    $this->info("Resultado: {$con_foto} con foto · {$sin_foto} sin foto/privacidad");
})->purpose('Descarga y cachea fotos de perfil de WhatsApp para contactos con wa_id resuelto');

/**
 * Indexa al legajo todos los mensajes_wa con archivo que aún no estén en documentos_paciente.
 *
 * Uso: docker exec crecer-web-1 php artisan documentos:sync
 *      docker exec crecer-web-1 php artisan documentos:sync --limit=500
 */
Artisan::command('documentos:sync {--limit=}', function () {
    $limit = (int) $this->option('limit') ?: 0;

    $q = \App\Models\MensajeWA::whereNotNull('archivo_url')
        ->whereIn('tipo', ['imagen', 'documento', 'audio', 'video'])
        ->whereNotIn('id', \App\Models\DocumentoPaciente::whereNotNull('mensaje_id')->pluck('mensaje_id'));
    if ($limit > 0) $q->limit($limit);

    $msgs = $q->orderBy('id')->get();
    $this->info("Mensajes WA con archivo a indexar: {$msgs->count()}");

    $bar = $this->output->createProgressBar($msgs->count());
    $bar->start();
    $ok = 0; $sin_archivo = 0; $err = 0;

    foreach ($msgs as $m) {
        $url = $m->archivo_url;
        $filename = basename(parse_url($url, PHP_URL_PATH) ?? '');

        // Determinar path local según el origen
        $srcAbs = null;
        if ($m->direccion === 'entrante') {
            $srcAbs = '/bot-media/' . $filename;
        } else {
            // saliente: storage/app/public/wa-media/<filename>
            $srcAbs = storage_path('app/public/wa-media/' . $filename);
        }

        if (!$srcAbs || !file_exists($srcAbs)) {
            $sin_archivo++;
            $bar->advance();
            continue;
        }

        try {
            $conv = $m->conversacion;
            $contacto = $conv ? \App\Models\Contacto::buscarPorContacto($conv->contacto) : null;
            \App\Services\LegajoStorage::indexar($srcAbs, [
                'contacto_id'     => $contacto?->id,
                'conversacion_id' => $m->conversacion_id,
                'mensaje_id'      => $m->id,
                'direccion'       => $m->direccion === 'saliente' ? 'saliente' : 'entrante',
                'usuario_id'      => $m->usuario_id,
                'mime'            => mime_content_type($srcAbs) ?: 'application/octet-stream',
                'nombre_original' => $filename,
            ]);
            $ok++;
        } catch (\Exception $e) {
            $err++;
        }
        $bar->advance();
        usleep(50_000); // 50ms para no saturar
    }
    $bar->finish();
    $this->newLine();
    $this->info("Indexados: {$ok} · Sin archivo en disk: {$sin_archivo} · Errores: {$err}");
})->purpose('Indexa al legajo los mensajes_wa con archivo que aún no estén indexados');

/**
 * Re-corre OCR en docs sin texto extraído (típicamente porque el OCR sincrónico falló o
 * porque se sumó el feature después). Para volver a procesar todos: --force.
 */
Artisan::command('documentos:ocr-rescan {--force} {--limit=}', function () {
    $force = $this->option('force');
    $limit = (int) $this->option('limit') ?: 0;

    $q = \App\Models\DocumentoPaciente::whereIn('tipo', ['imagen', 'documento']);
    if (!$force) $q->whereNull('ocr_at');
    if ($limit > 0) $q->limit($limit);

    $docs = $q->get();
    $this->info("Documentos a procesar OCR: {$docs->count()}");

    $bar = $this->output->createProgressBar($docs->count());
    $bar->start();
    $con_texto = 0; $sin_texto = 0;

    foreach ($docs as $d) {
        $abs = $d->pathAbsoluto();
        if (!file_exists($abs)) { $bar->advance(); continue; }
        $texto = \App\Services\OcrService::extraer($abs, $d->mime);
        $d->update(['texto_ocr' => $texto, 'ocr_at' => now()]);
        if ($texto !== null) $con_texto++;
        else $sin_texto++;
        $bar->advance();
    }
    $bar->finish();
    $this->newLine();
    $this->info("Con texto: {$con_texto} · Sin texto: {$sin_texto}");
})->purpose('Re-procesa OCR de documentos del legajo');

/**
 * Mapea contactos existentes con su wa_id real (consulta al bot) y vincula
 * conversaciones huérfanas (@lid sin nombre) con el contacto correspondiente.
 *
 * Uso: docker exec crecer-web-1 php artisan contactos:mapear-wa
 *      docker exec crecer-web-1 php artisan contactos:mapear-wa --solo-contactos
 *      docker exec crecer-web-1 php artisan contactos:mapear-wa --solo-conversaciones
 */
Artisan::command('contactos:mapear-wa {--solo-contactos} {--solo-conversaciones}', function () {
    $soloContactos       = $this->option('solo-contactos');
    $soloConversaciones  = $this->option('solo-conversaciones');
    $hacerContactos      = !$soloConversaciones;
    $hacerConversaciones = !$soloContactos;

    if ($hacerContactos) {
        $this->info('=== Mapeando wa_id de contactos sin resolver ===');
        $contactos = Contacto::whereNull('wa_id')->whereNotNull('telefono')->get();
        $this->info("Contactos a procesar: {$contactos->count()}");

        $ok = 0; $sin_wa = 0; $err = 0;
        $bar = $this->output->createProgressBar($contactos->count());
        $bar->start();

        foreach ($contactos as $c) {
            $waId = Contacto::resolverWaId($c->telefono);
            if ($waId) {
                $duplicado = Contacto::where('wa_id', $waId)->where('id', '!=', $c->id)->exists();
                if ($duplicado) {
                    $err++;
                } else {
                    $c->update(['wa_id' => $waId]);
                    $ok++;
                }
            } else {
                $sin_wa++;
            }
            $bar->advance();
            usleep(150_000);
        }
        $bar->finish();
        $this->newLine();
        $this->info("Resueltos: {$ok}  ·  Sin WhatsApp: {$sin_wa}  ·  Conflictos: {$err}");
    }

    if ($hacerConversaciones) {
        $this->info('');
        $this->info('=== Vinculando conversaciones huérfanas (@lid sin nombre) ===');

        $convs = ConversacionWA::where('contacto', 'like', '%@lid')
            ->where(function ($q) { $q->whereNull('nombre')->orWhere('nombre', ''); })
            ->get();
        $this->info("Conversaciones @lid huérfanas: {$convs->count()}");

        $vinculadas = 0; $sin_match = 0;
        $bar = $this->output->createProgressBar($convs->count());
        $bar->start();

        foreach ($convs as $conv) {
            $hit = Contacto::where('wa_id', $conv->contacto)->first();

            if (!$hit) {
                $numero = Contacto::resolverNumeroDesdeJid($conv->contacto);
                if ($numero) {
                    $telNorm = Contacto::normalizarTelefono($numero);
                    $hit = Contacto::where('telefono', $telNorm)->first();
                    if ($hit && !$hit->wa_id) {
                        $hit->update(['wa_id' => $conv->contacto]);
                    }
                }
            }

            if ($hit) {
                $conv->update(['nombre' => $hit->nombre]);
                $vinculadas++;
            } else {
                $sin_match++;
            }

            $bar->advance();
            usleep(150_000);
        }
        $bar->finish();
        $this->newLine();
        $this->info("Vinculadas: {$vinculadas}  ·  Sin match en directorio: {$sin_match}");
    }

    $this->info('');
    $this->info('Listo.');
})->purpose('Resuelve wa_id de contactos existentes y vincula conversaciones @lid huerfanas');

/**
 * Backfill de resúmenes LLM para conversaciones históricas que ameritan y no tienen.
 * Despacha jobs a la queue 'resumen' (no procesa inline). El worker dedicado los toma.
 *
 * Uso: docker exec crecer-web-1 php artisan conversaciones:regenerar-resumenes --dry-run
 *      docker exec crecer-web-1 php artisan conversaciones:regenerar-resumenes --limit=50
 */
Artisan::command('conversaciones:regenerar-resumenes {--dry-run} {--limit=}', function () {
    $dry   = $this->option('dry-run');
    $limit = (int) $this->option('limit') ?: 0;

    // Candidatas: sin resumen y nunca intentadas (o intentadas hace más de 1 día)
    $q = \App\Models\ConversacionWA::whereNull('resumen_llm')
        ->where(function ($w) {
            $w->whereNull('resumen_intento_at')
              ->orWhere('resumen_intento_at', '<', now()->subDay());
        })
        ->orderByDesc('id');

    $candidatas = $q->get(['id']);
    $this->info("Candidatas a evaluar: {$candidatas->count()}");

    $ameritan = 0; $noAmeritan = 0; $despachados = 0;
    $bar = $this->output->createProgressBar($candidatas->count());
    $bar->start();

    foreach ($candidatas as $c) {
        $conv = \App\Models\ConversacionWA::find($c->id);
        if (!$conv) { $bar->advance(); continue; }
        if ($conv->ameritaResumen()) {
            $ameritan++;
            if (!$dry) {
                if ($limit > 0 && $despachados >= $limit) { $bar->advance(); continue; }
                \App\Jobs\GenerarResumenLLM::dispatch($conv->id)->onQueue('resumen');
                $despachados++;
            }
        } else {
            $noAmeritan++;
            if (!$dry) $conv->forceFill(['resumen_intento_at' => now()])->saveQuietly();
        }
        $bar->advance();
    }
    $bar->finish();
    $this->newLine();
    $this->info("Ameritan resumen: {$ameritan}  ·  No ameritan: {$noAmeritan}" . ($dry ? '  (dry-run)' : "  ·  Despachados: {$despachados}"));
})->purpose('Backfill: dispatcha jobs LLM para conversaciones históricas sin resumen que ameritan');
