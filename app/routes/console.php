<?php

use App\Models\Contacto;
use App\Models\ConversacionWA;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Importa contactos desde un archivo .vcf (vCard 3.0).
 * Sin --apply hace dry-run (sólo reporta). Con --apply ejecuta los INSERT.
 *
 * Nombre = FN; si falta, usa ORG; si falta también, skipea (no hay info útil).
 * Teléfono = primer TEL del vcard, normalizado con Contacto::normalizarTelefono.
 * Skipea con motivo: sin_nombre, sin_tel, tel_invalido, tel_duplicado_db,
 * tel_duplicado_archivo.
 *
 * Uso: docker exec -u www-data crecer-web-1 php artisan contactos:importar-vcf /var/www/html/storage/app/import/admin.vcf
 *      docker exec -u www-data crecer-web-1 php artisan contactos:importar-vcf /var/www/html/storage/app/import/admin.vcf --apply
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
 * Uso: docker exec -u www-data crecer-web-1 php artisan contactos:auditar-telefonos
 *      docker exec -u www-data crecer-web-1 php artisan contactos:auditar-telefonos --csv=/var/www/html/storage/logs/audit.csv
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
 * Uso: docker exec -u www-data crecer-web-1 php artisan contactos:sync-avatares
 *      docker exec -u www-data crecer-web-1 php artisan contactos:sync-avatares --force
 *      docker exec -u www-data crecer-web-1 php artisan contactos:sync-avatares --limit=50
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
 * Uso: docker exec -u www-data crecer-web-1 php artisan documentos:sync
 *      docker exec -u www-data crecer-web-1 php artisan documentos:sync --limit=500
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
 * Uso: docker exec -u www-data crecer-web-1 php artisan contactos:mapear-wa
 *      docker exec -u www-data crecer-web-1 php artisan contactos:mapear-wa --solo-contactos
 *      docker exec -u www-data crecer-web-1 php artisan contactos:mapear-wa --solo-conversaciones
 *      docker exec -u www-data crecer-web-1 php artisan contactos:mapear-wa --limit=200
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
 * Uso: docker exec -u www-data crecer-web-1 php artisan conversaciones:regenerar-resumenes --dry-run
 *      docker exec -u www-data crecer-web-1 php artisan conversaciones:regenerar-resumenes --limit=50
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
 * en tramos de 7 días (el máximo que acepta Omnia) y se deduplica por DNI.
 *
 * Política de merge (conservadora):
 *   - Matchea contacto existente por dni, si no por teléfono normalizado.
 *   - Solo COMPLETA campos vacíos (nombre, dni, email, fecha_nacimiento);
 *     nunca pisa datos cargados. Los conflictos se reportan y se skipean.
 *   - Crea contactos nuevos solo si tienen celular normalizable (la tabla
 *     es el directorio WA; un paciente sin celular no sirve acá).
 *   - Skipea placeholders ("No Dar") y turnos sin DNI.
 *
 * Uso: docker exec -u www-data crecer-web-1 php artisan contactos:sync-omnia                      (dry-run, últimos 12 meses + 2 futuros)
 *      docker exec -u www-data crecer-web-1 php artisan contactos:sync-omnia --desde=2025-01-01 --apply
 */
Artisan::command('contactos:sync-omnia {--desde=} {--hasta=} {--apply} {--muestra=15}', function () {
    $tz      = 'America/Argentina/Buenos_Aires';
    $apply   = (bool) $this->option('apply');
    // (int) directo: el signature ya trae default 15, y así --muestra=0 se respeta
    $muestra = (int) $this->option('muestra');
    $desde   = $this->option('desde')
        ? \Carbon\Carbon::parse($this->option('desde'), $tz)->startOfDay()
        : now($tz)->subMonths(12)->startOfDay();
    $hasta   = $this->option('hasta')
        ? \Carbon\Carbon::parse($this->option('hasta'), $tz)->endOfDay()
        : now($tz)->addMonths(2)->endOfDay();

    $svc = app(\App\Services\OmniaService::class);

    // ── 1. Bajar el reporte por tramos y consolidar pacientes por DNI ──
    $pacientes = [];   // dni => [nombre, celular, email, fnac]
    $stats = ['turnos' => 0, 'sin_dni' => 0, 'placeholder' => 0, 'dias_perdidos' => []];

    // Procesamiento de los turnos de un tramo — común a todos los tramos.
    $procesarTurnos = function (array $rep) use (&$pacientes, &$stats) {
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
    };

    // Baja un tramo; si Omnia lo rechaza, lo parte al medio y reintenta cada
    // mitad, hasta llegar al día. Así un solo día podrido (el 10/09/2025 da 502
    // SIEMPRE) cuesta ese día y no el mes entero, que era el comportamiento
    // viejo. Los días que fallan aislados quedan listados en $stats.
    $bajarTramo = function (\Carbon\Carbon $ini, \Carbon\Carbon $fin) use (&$bajarTramo, $svc, $procesarTurnos, &$stats) {
        $rep = $svc->reporteAmbulatorio($ini->copy()->utc()->timestamp, $fin->copy()->utc()->timestamp, 180);

        if ($rep !== null) {
            $this->line(sprintf('  %s → %s   %d turnos',
                $ini->format('d/m/Y'), $fin->format('d/m/Y'), count($rep)));
            $procesarTurnos($rep);
            return;
        }

        // Un solo día que falla: no queda nada por subdividir.
        if ($ini->diffInDays($fin) < 1) {
            $this->warn(sprintf('  %s   FALLÓ — día salteado', $ini->format('d/m/Y')));
            $stats['dias_perdidos'][] = $ini->format('Y-m-d');
            return;
        }

        $medio = $ini->copy()->addSeconds((int) ($ini->diffInSeconds($fin) / 2))->endOfDay();
        if ($medio >= $fin) $medio = $fin->copy()->subDay()->endOfDay();

        $this->line(sprintf('  %s → %s   falló, subdividiendo',
            $ini->format('d/m/Y'), $fin->format('d/m/Y')));
        $bajarTramo($ini, $medio);
        $bajarTramo($medio->copy()->addSecond(), $fin);
    };

    // Tramos de 7 días: Omnia rechaza ventanas más largas (400
    // report_window_too_wide). Con tramos mensuales cada noche se iban 12
    // pedidos rechazados + 12 warnings antes de que la subdivisión llegara a 7.
    $cursor = $desde->copy()->startOfDay();
    while ($cursor < $hasta) {
        $finTramo = min($cursor->copy()->addDays(6)->endOfDay(), $hasta->copy());
        $bajarTramo($cursor->copy(), $finTramo->copy());
        $cursor = $finTramo->copy()->addSecond();
    }

    $this->newLine();
    $this->info(sprintf('Turnos leídos: %d · Pacientes únicos: %d · Sin DNI: %d · Placeholder: %d · Días perdidos: %d',
        $stats['turnos'], count($pacientes), $stats['sin_dni'], $stats['placeholder'], count($stats['dias_perdidos'])));
    if (!empty($stats['dias_perdidos'])) {
        $this->warn('  Días que Omnia rechazó: ' . implode(', ', $stats['dias_perdidos']));
    }

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
        return empty($stats["dias_perdidos"]) ? 0 : 1;
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
    // Exit != 0 si Omnia rechazó algún día: el script de la tarea programada lo
    // detecta y lo deja registrado en el log en vez de fallar en silencio.
    return empty($stats["dias_perdidos"]) ? 0 : 1;
})->purpose('Sincroniza contactos (dni/email/nacimiento/nuevos) desde los turnos de Omnia; dry-run sin --apply');

/**
 * Sonda de salud de la API de Omnia: signin + healthcheck contra el ambiente
 * configurado en OMNIA_BASE_URL. No usa el token cacheado — prueba el circuito
 * de autenticación completo, que es justamente lo que se rompe cuando cambian
 * o vencen las credenciales.
 *
 * Exit 0 = OK, 1 = caído, para que la tarea programada pueda alertar.
 *
 * Uso: docker exec -u www-data crecer-web-1 php artisan omnia:status
 *      docker exec -u www-data crecer-web-1 php artisan omnia:status --json
 */
Artisan::command('omnia:status {--json}', function () {
    $svc = app(\App\Services\OmniaService::class);
    $e   = $svc->estado();

    if ($this->option('json')) {
        $this->line(json_encode($e, JSON_UNESCAPED_UNICODE));
        return $e['ok'] ? 0 : 1;
    }

    $this->line('Ambiente:    ' . config('services.omnia.base_url'));
    $this->line('Usuario:     ' . (config('services.omnia.user') ?: '(sin configurar)'));
    $this->line('signin:      ' . ($e['signin']      ? 'OK' : 'FALLÓ'));
    $this->line('healthcheck: ' . ($e['healthcheck'] ? 'OK' : 'FALLÓ'));
    $this->line("Latencia:    {$e['ms']} ms");

    if ($e['ok']) {
        $this->info('Omnia responde correctamente.');
        return 0;
    }

    $this->error('Omnia NO responde: ' . ($e['error'] ?? 'desconocido'));
    return 1;
})->purpose('Chequea el acceso a la API de Omnia (signin + healthcheck); exit 1 si está caído');

/**
 * Archiva conversaciones activas sin actividad hace N días (default 7), en las
 * tres áreas/bots o en una sola con --area.
 *
 * Misma lógica que el panel /admin/archivar (App\Services\ArchivadoConversaciones):
 * semántica del botón "Resolver" (estado=archivada, asignada_a=null,
 * urgente=false + evento archivada_auto), sin tocar no_leidos ni borrar mensajes,
 * y el corte por COALESCE(ultima_actividad, created_at). Hasta el 23/09 era una
 * copia aparte que no dejaba lote: sus corridas no aparecían en el historial del
 * panel ni se podían deshacer desde ahí.
 *
 * Cada --apply deja un lote (origen "consola") que se ve en /admin/archivar y se
 * deshace desde ahí o con --revertir=<id del lote>. A diferencia del panel, por
 * default INCLUYE las asignadas a alguien (así fue siempre este comando);
 * --excluir-asignadas las deja afuera.
 *
 * Uso: docker exec -u www-data crecer-web-1 php artisan conversaciones:archivar-inactivas
 *      docker exec -u www-data crecer-web-1 php artisan conversaciones:archivar-inactivas --apply
 *      docker exec -u www-data crecer-web-1 php artisan conversaciones:archivar-inactivas --dias=30 --area=ovodonacion --apply
 *      docker exec -u www-data crecer-web-1 php artisan conversaciones:archivar-inactivas --revertir=12
 *      (corridas anteriores al 23/09: --revertir=/var/www/html/storage/logs/archivadas-20260820-1609.json)
 */
Artisan::command('conversaciones:archivar-inactivas {--dias=7} {--area=} {--excluir-asignadas} {--apply} {--muestra=15} {--revertir=}', function () {
    $svc = \App\Services\ArchivadoConversaciones::class;

    // ── Rollback ──────────────────────────────────────────────────────────
    if ($rev = $this->option('revertir')) {
        if (ctype_digit($rev)) {
            $lote = \App\Models\ArchivadoLote::find((int) $rev);
            if (!$lote) { $this->error("No existe el lote {$rev}."); return 1; }
            if ($lote->revertido_at) { $this->error("El lote {$rev} ya se revirtió el {$lote->revertido_at->format('d/m/Y H:i')}."); return 1; }
        } else {
            // JSON de rollback de las corridas previas a los lotes. Lote sin
            // guardar: revertir() deshace igual y no intenta marcarlo.
            if (!file_exists($rev)) { $this->error("No existe: $rev"); return 1; }
            $prev = json_decode(file_get_contents($rev), true);
            if (!is_array($prev) || empty($prev)) { $this->error('Archivo de rollback vacío o ilegible.'); return 1; }
            $lote = new \App\Models\ArchivadoLote(['snapshot' => $prev]);
        }
        $revertidas = $svc::revertir($lote, null);
        $this->info("Revertidas: {$revertidas} (solo las que seguían archivadas).");
        return 0;
    }

    // ── Selección ─────────────────────────────────────────────────────────
    $area = $this->option('area');
    if ($area && !isset(ConversacionWA::AREAS[$area])) {
        $this->error("Área inválida: {$area}. Válidas: " . implode(', ', array_keys(ConversacionWA::AREAS)));
        return 1;
    }
    $criterio = [
        'modo'              => 'dias',
        'dias'              => (int) $this->option('dias') ?: 7,
        'area'              => $area,
        'excluir_asignadas' => (bool) $this->option('excluir-asignadas'),
    ];
    $muestra = (int) $this->option('muestra');
    $p = $svc::previsualizar($criterio, $muestra);

    $this->newLine();
    $this->info('Corte: ' . $p['corte']);
    if ($p['total'] === 0) { $this->info('No hay conversaciones para archivar.'); return 0; }

    // Desglose por área para que se vea qué bot aporta qué
    $this->newLine();
    $this->table(['Área', 'A archivar', 'Con no leídos', 'Asignadas a alguien'],
        array_map(fn($r) => [$r['area_label'], $r['total'], $r['con_no_leidos'], $r['asignadas']], $p['por_area']));
    $this->info("TOTAL: {$p['total']}");

    if ($p['muestra']) {
        $this->newLine();
        $this->line("Más recientes de la selección (primeras {$muestra}):");
        $this->table(['ID', 'Área', 'Contacto', 'Última actividad'],
            array_map(fn($c) => [$c['id'], $c['area'], $c['nombre'], $c['ultima_actividad'] ?: '—'], $p['muestra']));
    }

    if (!$this->option('apply')) {
        $this->newLine();
        $this->warn('DRY-RUN — nada se archivó. Pasá --apply para ejecutar.');
        return 0;
    }

    // ── Aplicar ───────────────────────────────────────────────────────────
    $lote = $svc::aplicar($criterio, null, 'consola');

    $this->newLine();
    $this->info("Archivadas: {$lote->total} (lote {$lote->id}, visible en /admin/archivar)");
    $this->line("Deshacer: php artisan conversaciones:archivar-inactivas --revertir={$lote->id}");
    return 0;
})->purpose('Archiva conversaciones activas sin actividad hace N dias (default 7) en todas las areas; dry-run sin --apply');

/**
 * Catálogo de financiadores y prácticas tal como los nombra Omnia, para que las
 * reglas del checklist de recepción se carguen eligiendo de una lista y
 * matcheen letra por letra contra lo que trae el tablet.
 *
 * Fuente: reporte ambulatorio en tramos de 7 días (los rangos largos dan 502 al
 * azar; un tramo que falla se saltea y se informa). Idempotente: suma el
 * conteo de turnos de la ventana y actualiza visto_at; nunca borra.
 *
 * Uso: docker exec -u www-data crecer-web-1 php artisan omnia:catalogo            (últimos 120 días)
 *      docker exec -u www-data crecer-web-1 php artisan omnia:catalogo --dias=30
 */
Artisan::command('omnia:catalogo {--dias=120}', function () {
    $svc   = app(\App\Services\OmniaService::class);
    $dias  = max(1, (int) $this->option('dias'));
    $cuentas = ['financiador' => [], 'practica' => []];
    $fallidos = [];

    for ($ini = now()->subDays($dias)->startOfDay(); $ini->lte(now()); $ini->addDays(7)) {
        $fin = $ini->copy()->addDays(6)->endOfDay();
        $turnos = $svc->reporteAmbulatorio($ini->timestamp, min($fin->timestamp, now()->endOfDay()->timestamp));
        if (!is_array($turnos)) { $fallidos[] = $ini->format('d/m'); continue; }
        foreach ($turnos as $t) {
            if (($t['Estado'] ?? '') === 'cancelado') continue;
            if ($f = trim($t['FinanciadorDelTurno'] ?? '')) $cuentas['financiador'][$f] = ($cuentas['financiador'][$f] ?? 0) + 1;
            // "Prácticas" junta las del turno con ", " (el endpoint del tablet las da como lista).
            foreach (preg_split('/,\s+/', (string) ($t['Prácticas'] ?? '')) as $p) {
                if ($p = trim($p)) $cuentas['practica'][$p] = ($cuentas['practica'][$p] ?? 0) + 1;
            }
        }
    }

    $nuevos = 0;
    foreach ($cuentas as $tipo => $nombres) {
        foreach ($nombres as $nombre => $n) {
            $c = \App\Models\OmniaCatalogo::firstOrNew(['tipo' => $tipo, 'nombre' => mb_substr($nombre, 0, 191)]);
            if (!$c->exists) $nuevos++;
            $c->turnos = $n;
            $c->visto_at = now();
            $c->save();
        }
    }

    $this->info(sprintf('Financiadores: %d · prácticas: %d · nuevos en el catálogo: %d',
        count($cuentas['financiador']), count($cuentas['practica']), $nuevos));
    if ($fallidos) {
        $this->warn('Tramos que Omnia rechazó (se reintentan en la próxima corrida): ' . implode(', ', $fallidos));
        return 1;
    }
    return 0;
})->purpose('Actualiza el catálogo de financiadores y prácticas de Omnia para las reglas del checklist de recepción');

/**
 * Manda un aviso operativo por mail (lo usa el watchdog de los bots). Es el
 * canal fuera de banda: si los tres WhatsApp están caídos, el aviso por
 * WhatsApp no tiene por dónde salir, el mail sí.
 *
 * El texto viaja en base64 para que PowerShell → docker exec no rompa acentos
 * ni comillas. Exit: 0 enviado · 1 error de envío · 3 mail sin configurar
 * (MAIL_MAILER=log/array: "sale" a un archivo, no le llega a nadie).
 *
 * Uso: docker exec -u www-data crecer-web-1 php artisan alerta:mail <asunto_b64> <cuerpo_b64>
 */
Artisan::command('alerta:mail {asunto_b64} {cuerpo_b64}', function () {
    $asunto = base64_decode($this->argument('asunto_b64'), true);
    $cuerpo = base64_decode($this->argument('cuerpo_b64'), true);
    if ($asunto === false || $cuerpo === false) { $this->error('asunto/cuerpo no son base64 válidos'); return 1; }

    $para    = config('services.alertas.mail_to');
    $mailer  = config('mail.default');
    try {
        \Illuminate\Support\Facades\Mail::raw($cuerpo, fn ($m) => $m->to($para)->subject($asunto));
    } catch (\Throwable $e) {
        $this->error("No se pudo enviar a {$para}: " . $e->getMessage());
        return 1;
    }
    if (in_array($mailer, ['log', 'array'], true)) {
        $this->warn("Mail sin configurar (MAIL_MAILER={$mailer}): quedó en el log, no le llegó a {$para}.");
        return 3;
    }
    $this->info("Enviado a {$para}");
    return 0;
})->purpose('Aviso operativo por mail (watchdog); textos en base64');

/**
 * Salud de la ingesta de WhatsApp: ¿los mensajes están entrando a la base, y
 * enteros? El watchdog mira que cada bot esté vivo; esto mira el resultado.
 *
 * Las peores fallas de 2026 fueron silenciosas con el bot "listo": atención
 * sordo del 15/09 21:51 al 20/09 (cero entrantes, página respondiendo) y, del
 * 17/07 al 23/09, entrantes sin wa_id y adjuntos sin archivo (downloadMedia
 * fallando callado). Ninguna la vio nadie durante días.
 *
 * Por área:
 *   - silencio: 0 entrantes en las últimas --horas, con el tramo entero dentro
 *     de lunes a viernes 08:30-18:30, cuando la mediana del mismo tramo en los
 *     días hábiles de las últimas 4 semanas (sin contar días en cero, que son
 *     caídas o feriados) es de al menos --minimo.
 *   - calidad: en las últimas --horas-calidad, más de la mitad de los
 *     entrantes sin wa_id o más de la mitad de los adjuntos sin archivo.
 *
 * Exit 0 = bien · 1 = hay alertas (líneas que empiezan con "- "). Lo corre el
 * watchdog una vez por hora en horario de clínica y avisa por WhatsApp y mail.
 *
 * Uso: docker exec -u www-data crecer-web-1 php artisan salud:ingesta
 */
Artisan::command('salud:ingesta {--horas=2} {--horas-calidad=6} {--minimo=6}', function () {
    $ahora   = now();
    $horas   = max(1, (int) $this->option('horas'));
    $hCal    = max(1, (int) $this->option('horas-calidad'));
    $minimo  = max(1, (int) $this->option('minimo'));
    $desde   = $ahora->copy()->subHours($horas);
    $tipos   = "'imagen','audio','video','documento','sticker'";
    $entrantes = fn () => DB::table('mensajes_wa as m')
        ->join('conversaciones_wa as c', 'c.id', '=', 'm.conversacion_id')
        ->where('m.direccion', 'entrante');

    $enHorario = $ahora->isWeekday() && $desde->isSameDay($ahora)
        && $desde->format('H:i') >= '08:30' && $ahora->format('H:i') <= '18:30';

    $recientes = $entrantes()->where('m.created_at', '>=', $desde)
        ->groupBy('c.area')->selectRaw('c.area, COUNT(*) n')->pluck('n', 'area');

    // Línea de base: el mismo tramo horario en los días hábiles previos.
    $base = [];
    if ($enHorario) {
        $filas = $entrantes()
            ->where('m.created_at', '>=', $ahora->copy()->subDays(28)->startOfDay())
            ->where('m.created_at', '<', $ahora->copy()->startOfDay())
            ->selectRaw('c.area, DATE(m.created_at) dia, COUNT(*) total, SUM(TIME(m.created_at) BETWEEN ? AND ?) tramo',
                [$desde->format('H:i:s'), $ahora->format('H:i:s')])
            ->groupBy('c.area', 'dia')->get();
        foreach ($filas as $f) {
            if ($f->total > 0 && \Carbon\Carbon::parse($f->dia)->isWeekday()) $base[$f->area][] = (int) $f->tramo;
        }
    }

    $calidad = $entrantes()->where('m.created_at', '>=', $ahora->copy()->subHours($hCal))
        ->selectRaw("c.area, COUNT(*) total, SUM(m.wa_id IS NULL) sin_id,
            SUM(m.tipo IN ($tipos)) media, SUM(m.tipo IN ($tipos) AND m.archivo_url IS NULL) media_sin_archivo")
        ->groupBy('c.area')->get()->keyBy('area');

    $alertas = [];
    foreach (ConversacionWA::AREAS as $area => $nombre) {
        $n   = (int) ($recientes[$area] ?? 0);
        $dias = $base[$area] ?? [];
        sort($dias);
        $esperado = count($dias) >= 5 ? $dias[intdiv(count($dias), 2)] : null;   // mediana
        $q = $calidad[$area] ?? null;

        $this->line(sprintf('%s: %d entrantes en %d h%s · últimas %d h: %d entrantes, %d sin wa_id, %d/%d adjuntos sin archivo',
            $nombre, $n, $horas, $esperado !== null ? " (lo normal: {$esperado})" : '',
            $hCal, $q->total ?? 0, $q->sin_id ?? 0, $q->media_sin_archivo ?? 0, $q->media ?? 0));

        if ($enHorario && $esperado !== null && $esperado >= $minimo && $n === 0) {
            $alertas[] = "{$nombre}: ningún mensaje entrante en las últimas {$horas} h (lo normal a esta hora: {$esperado}). "
                . 'Si el bot figura "listo", está sordo: reiniciarlo, con el celular del área a mano por si pide QR.';
        }
        if ($q && $q->total >= 10 && $q->sin_id * 2 > $q->total) {
            $alertas[] = "{$nombre}: {$q->sin_id} de {$q->total} entrantes de las últimas {$hCal} h sin wa_id "
                . '(el bot no lee el id de los mensajes: sin él no se deduplican reintentos ni se puede citar).';
        }
        if ($q && $q->media >= 4 && $q->media_sin_archivo * 2 > $q->media) {
            $alertas[] = "{$nombre}: {$q->media_sin_archivo} de {$q->media} adjuntos de las últimas {$hCal} h sin archivo "
                . '(no se están guardando imágenes, audios ni documentos de pacientes).';
        }
    }

    if (!$alertas) {
        $this->info('Ingesta OK' . ($enHorario ? '' : ' (fuera de horario: solo se mide la calidad)'));
        return 0;
    }
    $this->newLine();
    $this->error('ALERTAS:');
    foreach ($alertas as $a) $this->line("- {$a}");
    return 1;
})->purpose('Chequea que los mensajes de WhatsApp entren a la base y enteros; exit 1 si hay alertas');
