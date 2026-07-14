<?php

use App\Models\Contacto;
use App\Models\ConversacionWA;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Importa contactos desde un archivo .vcf (vCard 3.0).
 * Sin --apply hace dry-run (sólo reporta). Con --apply ejecuta los INSERT.
 *
 * Nombre = FN; si falta, usa ORG; si falta también, skipea (no hay info útil).
 * Teléfono = primer TEL del vcard, normalizado con Contacto::normalizarTelefono.
 * Skipea con motivo: sin_nombre, sin_tel, tel_invalido, tel_duplicado_db,
 * tel_duplicado_archivo.
 *
 * Uso: docker exec crecer-web-1 php artisan contactos:importar-vcf /var/www/html/storage/app/import/admin.vcf
 *      docker exec crecer-web-1 php artisan contactos:importar-vcf /var/www/html/storage/app/import/admin.vcf --apply
 */
Artisan::command('contactos:importar-vcf {archivo} {--apply} {--muestra=20}', function () {
    $archivo = $this->argument('archivo');
    $apply   = (bool) $this->option('apply');
    $muestra = (int) $this->option('muestra') ?: 20;

    if (!file_exists($archivo)) {
        $this->error("No existe: $archivo");
        return 1;
    }

    $contenido = file_get_contents($archivo);
    $contenido = str_replace("\r\n", "\n", $contenido);

    // vCard permite líneas plegadas (continuación con espacio o tab al inicio): unfold.
    $contenido = preg_replace("/\n[ \t]/", '', $contenido);

    $bloques = preg_split('/BEGIN:VCARD/i', $contenido);
    array_shift($bloques);   // antes del primer BEGIN

    $stats = [
        'total'                  => 0,
        'sin_nombre'             => 0,
        'sin_tel'                => 0,
        'tel_invalido'           => 0,
        'tel_duplicado_db'       => 0,
        'tel_duplicado_archivo'  => 0,
        'importables'            => 0,
    ];
    $importables = [];
    $vistosTel   = [];

    foreach ($bloques as $b) {
        $stats['total']++;
        $b = explode('END:VCARD', $b)[0];

        $fn  = null; $org = null; $tel = null;
        foreach (explode("\n", $b) as $linea) {
            $linea = trim($linea);
            if ($linea === '') continue;

            // El "name" puede tener parámetros antes de los ":" — separamos por el primer ":".
            $sep = strpos($linea, ':');
            if ($sep === false) continue;
            $head = strtoupper(strtok(substr($linea, 0, $sep), ';'));
            $val  = substr($linea, $sep + 1);

            if ($head === 'FN'  && $fn  === null) $fn  = $val;
            if ($head === 'ORG' && $org === null) $org = explode(';', $val)[0];
            if ($head === 'TEL' && $tel === null) $tel = $val;
        }

        $nombre = trim($fn ?: ($org ?: ''));
        // Limpiar caracteres raros pegados al inicio (emojis, comillas tipográficas son OK
        // pero algunos prefijos tipo "*" o caracteres de control conviene removerlos)
        $nombre = preg_replace('/^[\s\*]+|[\s\*]+$/', '', $nombre);

        if ($nombre === '') { $stats['sin_nombre']++; continue; }
        if (!$tel)          { $stats['sin_tel']++;    continue; }

        $telNorm = Contacto::normalizarTelefono($tel);
        if (!$telNorm) { $stats['tel_invalido']++; continue; }

        if (isset($vistosTel[$telNorm])) {
            $stats['tel_duplicado_archivo']++;
            continue;
        }
        $vistosTel[$telNorm] = true;

        if (Contacto::where('telefono', $telNorm)->exists()) {
            $stats['tel_duplicado_db']++;
            continue;
        }

        $importables[] = ['nombre' => $nombre, 'telefono' => $telNorm];
        $stats['importables']++;
    }

    // Reporte
    $this->newLine();
    $this->info("Archivo: $archivo");
    $this->line(sprintf("  %-30s %d", 'vCards leídas',            $stats['total']));
    $this->line(sprintf("  %-30s %d", 'Sin nombre (FN/ORG)',      $stats['sin_nombre']));
    $this->line(sprintf("  %-30s %d", 'Sin teléfono',             $stats['sin_tel']));
    $this->line(sprintf("  %-30s %d", 'Tel inválido (no normalizable)', $stats['tel_invalido']));
    $this->line(sprintf("  %-30s %d", 'Tel duplicado en archivo', $stats['tel_duplicado_archivo']));
    $this->line(sprintf("  %-30s %d", 'Tel duplicado en DB',      $stats['tel_duplicado_db']));
    $this->info(sprintf("  %-30s %d", 'IMPORTABLES',              $stats['importables']));

    if (!empty($importables) && $muestra > 0) {
        $this->newLine();
        $this->line("Primeros $muestra para revisar:");
        $this->table(['Nombre', 'Teléfono'], array_slice($importables, 0, $muestra));
    }

    if (!$apply) {
        $this->newLine();
        $this->warn('DRY-RUN — nada se insertó. Pasá --apply para ejecutar.');
        return 0;
    }

    if (empty($importables)) {
        $this->info('Nada por importar.');
        return 0;
    }

    $now = now();
    $rows = array_map(fn($r) => $r + ['created_at' => $now, 'updated_at' => $now], $importables);

    // Inserto en chunks de 500 para no pegarle a max_allowed_packet
    $insertados = 0;
    foreach (array_chunk($rows, 500) as $chunk) {
        \App\Models\Contacto::insert($chunk);
        $insertados += count($chunk);
    }

    $this->info("Insertados: $insertados");
    return 0;
})->purpose('Importa contactos desde un .vcf (vCard) con dry-run por default; pasá --apply para ejecutar');

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
            // saliente: disk default (local) → storage/app/private/public/wa-media
            $srcAbs = \Illuminate\Support\Facades\Storage::path('public/wa-media/' . $filename);
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
 *      docker exec crecer-web-1 php artisan contactos:mapear-wa --limit=200
 *
 * --limit=N: procesa como mucho N items por sección. Diseñado para corridas
 *   diarias automáticas que NO deben pisar horario laboral aunque haya cola
 *   acumulada (19/05: una corrida sin límite duró 6+ horas y colgó el bot
 *   atención al saturar el CDP de Chromium).
 * --max-errors=N: si hay N errores/timeouts seguidos resolviendo wa_ids,
 *   asume que el bot está caído y aborta. Evita bombardear un bot colgado
 *   acumulando timeouts.
 */
Artisan::command('contactos:mapear-wa {--solo-contactos} {--solo-conversaciones} {--limit=} {--max-errors=10}', function () {
    $soloContactos       = $this->option('solo-contactos');
    $soloConversaciones  = $this->option('solo-conversaciones');
    $hacerContactos      = !$soloConversaciones;
    $hacerConversaciones = !$soloContactos;
    $limit               = $this->option('limit') !== null ? (int) $this->option('limit') : null;
    $maxErrors           = (int) $this->option('max-errors');

    $abortar = false;  // se setea si maxErrors consecutivos hace que el bot esté evidentemente colgado

    if ($hacerContactos) {
        $this->info('=== Mapeando wa_id de contactos sin resolver ===');
        $q = Contacto::whereNull('wa_id')->whereNotNull('telefono');
        if ($limit) $q->limit($limit);
        $contactos = $q->get();
        $this->info("Contactos a procesar: {$contactos->count()}" . ($limit ? " (limit={$limit})" : ''));

        $ok = 0; $sin_wa = 0; $err = 0; $errSeguidos = 0;
        $bar = $this->output->createProgressBar($contactos->count());
        $bar->start();

        foreach ($contactos as $c) {
            $waId = Contacto::resolverWaId($c->telefono);
            if ($waId) {
                $errSeguidos = 0;
                $duplicado = Contacto::where('wa_id', $waId)->where('id', '!=', $c->id)->exists();
                if ($duplicado) {
                    $err++;
                } else {
                    $c->update(['wa_id' => $waId]);
                    $ok++;
                }
            } else {
                $sin_wa++;
                $errSeguidos++;
                if ($errSeguidos >= $maxErrors) {
                    $bar->finish();
                    $this->newLine();
                    $this->error("Aborto: {$errSeguidos} resoluciones fallidas seguidas — el bot parece colgado.");
                    $abortar = true;
                    break;
                }
            }
            $bar->advance();
            usleep(150_000);
        }
        if (!$abortar) { $bar->finish(); $this->newLine(); }
        $this->info("Resueltos: {$ok}  ·  Sin WhatsApp: {$sin_wa}  ·  Conflictos: {$err}");
    }

    if ($hacerConversaciones && !$abortar) {
        $this->info('');
        $this->info('=== Vinculando conversaciones huérfanas (@lid sin nombre) ===');

        $q = ConversacionWA::where('contacto', 'like', '%@lid')
            ->where(function ($q) { $q->whereNull('nombre')->orWhere('nombre', ''); });
        if ($limit) $q->limit($limit);
        $convs = $q->get();
        $this->info("Conversaciones @lid huérfanas: {$convs->count()}" . ($limit ? " (limit={$limit})" : ''));

        $vinculadas = 0; $sin_match = 0; $errSeguidos = 0;
        $bar = $this->output->createProgressBar($convs->count());
        $bar->start();

        foreach ($convs as $conv) {
            $hit = Contacto::where('wa_id', $conv->contacto)->first();

            if (!$hit) {
                $numero = Contacto::resolverNumeroDesdeJid($conv->contacto);
                if ($numero) {
                    $errSeguidos = 0;
                    $telNorm = Contacto::normalizarTelefono($numero);
                    $hit = Contacto::where('telefono', $telNorm)->first();
                    if ($hit && !$hit->wa_id) {
                        $hit->update(['wa_id' => $conv->contacto]);
                    }
                } else {
                    $errSeguidos++;
                    if ($errSeguidos >= $maxErrors) {
                        $bar->finish();
                        $this->newLine();
                        $this->error("Aborto: {$errSeguidos} resoluciones fallidas seguidas — el bot parece colgado.");
                        $abortar = true;
                        break;
                    }
                }
            } else {
                $errSeguidos = 0;
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
        if (!$abortar) { $bar->finish(); $this->newLine(); }
        $this->info("Vinculadas: {$vinculadas}  ·  Sin match en directorio: {$sin_match}");
    }

    $this->info('');
    $this->info($abortar ? 'Cortado por errores.' : 'Listo.');
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

/**
 * Sincroniza contactos desde Omnia (API prod) usando el reporte ambulatorio.
 *
 * Omnia no expone "listar pacientes"; la fuente es el reporte de turnos del
 * centro, que trae los datos de contacto del paciente en cada turno. Se pide
 * por mes (rangos largos tardan ~110s los 6 meses) y se deduplica por DNI.
 *
 * Política de merge (conservadora):
 *   - Matchea contacto existente por dni, si no por teléfono normalizado.
 *   - Solo COMPLETA campos vacíos (nombre, dni, email, fecha_nacimiento);
 *     nunca pisa datos cargados. Los conflictos se reportan y se skipean.
 *   - Crea contactos nuevos solo si tienen celular normalizable (la tabla
 *     es el directorio WA; un paciente sin celular no sirve acá).
 *   - Skipea placeholders ("No Dar") y turnos sin DNI.
 *
 * Uso: docker exec crecer-web-1 php artisan contactos:sync-omnia                      (dry-run, últimos 12 meses + 2 futuros)
 *      docker exec crecer-web-1 php artisan contactos:sync-omnia --desde=2025-01-01 --apply
 */
Artisan::command('contactos:sync-omnia {--desde=} {--hasta=} {--apply} {--muestra=15}', function () {
    $tz      = 'America/Argentina/Buenos_Aires';
    $apply   = (bool) $this->option('apply');
    $muestra = (int) $this->option('muestra') ?: 15;
    $desde   = $this->option('desde')
        ? \Carbon\Carbon::parse($this->option('desde'), $tz)->startOfDay()
        : now($tz)->subMonths(12)->startOfDay();
    $hasta   = $this->option('hasta')
        ? \Carbon\Carbon::parse($this->option('hasta'), $tz)->endOfDay()
        : now($tz)->addMonths(2)->endOfDay();

    $svc = app(\App\Services\OmniaService::class);

    // ── 1. Bajar el reporte por meses y consolidar pacientes por DNI ──
    $pacientes = [];   // dni => [nombre, celular, email, fnac]
    $stats = ['turnos' => 0, 'sin_dni' => 0, 'placeholder' => 0, 'meses_fallidos' => 0];

    $cursor = $desde->copy();
    while ($cursor < $hasta) {
        $finTramo = min($cursor->copy()->addMonth(), $hasta->copy());
        $s = $cursor->copy()->utc()->timestamp;
        $e = $finTramo->copy()->utc()->timestamp;

        $this->output->write(sprintf('  %s → %s ... ', $cursor->format('d/m/Y'), $finTramo->format('d/m/Y')));
        $rep = $svc->reporteAmbulatorio($s, $e, 180);

        if ($rep === null) {
            $this->warn('FALLÓ (ver laravel.log)');
            $stats['meses_fallidos']++;
            $cursor = $finTramo;
            continue;
        }
        $this->line(count($rep) . ' turnos');

        foreach ($rep as $t) {
            $stats['turnos']++;
            $dni = preg_replace('/\D/', '', (string) ($t['NúmeroDeDocumento'] ?? ''));
            if ($dni === '') { $stats['sin_dni']++; continue; }

            $nombre = trim(implode(' ', array_filter([
                trim($t['Nombre'] ?? ''),
                trim($t['OtrosNombres'] ?? ''),
                trim($t['ApellidoPaterno'] ?? ''),
            ])));
            if (mb_stripos($nombre, 'no dar') !== false) { $stats['placeholder']++; continue; }

            $fila = $pacientes[$dni] ?? ['nombre' => '', 'celular' => '', 'email' => '', 'fnac' => ''];
            // Completar con lo que traiga este turno (campos vacíos solamente:
            // los datos del paciente son los mismos en todos sus turnos, pero
            // algunos turnos vienen con campos en blanco).
            if ($fila['nombre'] === '')  $fila['nombre']  = $nombre;
            if ($fila['celular'] === '') $fila['celular'] = trim((string) ($t['Celular'] ?? ''));
            if ($fila['email'] === '')   $fila['email']   = trim((string) ($t['Email'] ?? ''));
            if ($fila['fnac'] === '')    $fila['fnac']    = trim((string) ($t['FechaDeNacimiento'] ?? ''));
            $pacientes[$dni] = $fila;
        }

        $cursor = $finTramo;
    }

    $this->newLine();
    $this->info(sprintf('Turnos leídos: %d · Pacientes únicos: %d · Sin DNI: %d · Placeholder: %d · Meses fallidos: %d',
        $stats['turnos'], count($pacientes), $stats['sin_dni'], $stats['placeholder'], $stats['meses_fallidos']));

    // ── 2. Matchear contra contactos y decidir acción ──
    $acciones = ['actualizar' => [], 'crear' => [], 'sin_cambios' => 0, 'sin_celular' => 0, 'conflictos' => []];

    foreach ($pacientes as $dni => $p) {
        $dni     = (string) $dni;   // las keys numéricas del array vuelven como int
        $telNorm = $p['celular'] !== '' ? Contacto::normalizarTelefono($p['celular']) : '';
        $email   = filter_var($p['email'], FILTER_VALIDATE_EMAIL) ? $p['email'] : null;
        $fnac    = null;
        if ($p['fnac'] !== '') {
            try { $fnac = \Carbon\Carbon::createFromFormat('j/n/Y', $p['fnac'], $tz)->format('Y-m-d'); }
            catch (\Throwable) {}
        }

        $contacto = Contacto::where('dni', $dni)->first()
            ?: ($telNorm !== '' ? Contacto::where('telefono', $telNorm)->first() : null);

        if ($contacto) {
            // Conflicto: el contacto matcheado por teléfono ya tiene OTRO dni cargado.
            if ($contacto->dni && $contacto->dni !== $dni) {
                $acciones['conflictos'][] = ['id' => $contacto->id, 'nombre' => $contacto->nombre,
                    'motivo' => "dni distinto (db={$contacto->dni}, omnia={$dni})"];
                continue;
            }
            // Conflicto: quiere tomar un dni que ya tiene otro contacto (unique).
            if (!$contacto->dni && Contacto::where('dni', $dni)->where('id', '!=', $contacto->id)->exists()) {
                $acciones['conflictos'][] = ['id' => $contacto->id, 'nombre' => $contacto->nombre,
                    'motivo' => "dni {$dni} ya está en otro contacto"];
                continue;
            }

            $cambios = [];
            if (!$contacto->dni)                                   $cambios['dni'] = $dni;
            if (!$contacto->email && $email)                       $cambios['email'] = $email;
            if (!$contacto->fecha_nacimiento && $fnac)             $cambios['fecha_nacimiento'] = $fnac;
            if (trim((string) $contacto->nombre) === '' && $p['nombre'] !== '') $cambios['nombre'] = $p['nombre'];

            if (empty($cambios)) { $acciones['sin_cambios']++; continue; }
            $acciones['actualizar'][] = ['id' => $contacto->id, 'nombre' => $contacto->nombre ?: $p['nombre'],
                'campos' => implode(', ', array_keys($cambios)), '_cambios' => $cambios];
            continue;
        }

        if ($telNorm === '') { $acciones['sin_celular']++; continue; }

        $acciones['crear'][] = ['nombre' => $p['nombre'], 'telefono' => $telNorm, '_datos' => [
            'nombre' => $p['nombre'], 'telefono' => $telNorm, 'dni' => $dni,
            'email' => $email, 'fecha_nacimiento' => $fnac,
        ]];
    }

    // ── 3. Reporte ──
    $this->newLine();
    $this->line(sprintf('  %-38s %d', 'Contactos a CREAR',                   count($acciones['crear'])));
    $this->line(sprintf('  %-38s %d', 'Contactos a COMPLETAR (existentes)',  count($acciones['actualizar'])));
    $this->line(sprintf('  %-38s %d', 'Ya al día (sin cambios)',             $acciones['sin_cambios']));
    $this->line(sprintf('  %-38s %d', 'Nuevos sin celular (skipeados)',      $acciones['sin_celular']));
    $this->line(sprintf('  %-38s %d', 'Conflictos (revisar a mano)',         count($acciones['conflictos'])));

    if (!empty($acciones['conflictos'])) {
        $this->newLine();
        $this->warn('Conflictos:');
        $this->table(['ID', 'Nombre', 'Motivo'], array_map(
            fn($c) => [$c['id'], $c['nombre'], $c['motivo']],
            array_slice($acciones['conflictos'], 0, 30)
        ));
    }
    if (!empty($acciones['crear']) && $muestra > 0) {
        $this->newLine();
        $this->line("Muestra de nuevos (primeros $muestra):");
        $this->table(['Nombre', 'Teléfono'], array_map(
            fn($c) => [$c['nombre'], $c['telefono']],
            array_slice($acciones['crear'], 0, $muestra)
        ));
    }
    if (!empty($acciones['actualizar']) && $muestra > 0) {
        $this->newLine();
        $this->line("Muestra de completados (primeros $muestra):");
        $this->table(['ID', 'Nombre', 'Campos que completa'], array_map(
            fn($c) => [$c['id'], $c['nombre'], $c['campos']],
            array_slice($acciones['actualizar'], 0, $muestra)
        ));
    }

    if (!$apply) {
        $this->newLine();
        $this->warn('DRY-RUN — nada se escribió. Pasá --apply para ejecutar.');
        return 0;
    }

    // ── 4. Aplicar ──
    $creados = 0;
    foreach ($acciones['crear'] as $c) {
        // Un mismo run puede traer dos DNI con el mismo celular normalizado
        // (madre/hijo comparten teléfono): el segundo queda afuera.
        if (Contacto::where('telefono', $c['_datos']['telefono'])->exists()) continue;
        Contacto::create($c['_datos']);
        $creados++;
    }
    $actualizados = 0;
    foreach ($acciones['actualizar'] as $a) {
        Contacto::whereKey($a['id'])->update($a['_cambios']);
        $actualizados++;
    }

    $this->newLine();
    $this->info("Creados: $creados · Completados: $actualizados");
    return 0;
})->purpose('Sincroniza contactos (dni/email/nacimiento/nuevos) desde los turnos de Omnia; dry-run sin --apply');
