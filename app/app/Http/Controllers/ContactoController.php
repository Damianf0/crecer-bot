<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactoController extends Controller
{
    public function index()
    {
        return view('contactos.index');
    }

    public function data(Request $request): JsonResponse
    {
        $q       = trim($request->input('q', ''));
        $page    = max(1, (int) $request->input('page', 1));
        // Sin búsqueda: 100 por página. Con búsqueda: 200 (cabe casi todo lo que importa).
        $perPage = $q ? 200 : 100;

        $base = Contacto::query();

        if ($q !== '') {
            // Para teléfono/DNI: el usuario puede escribir con puntos, guiones o espacios.
            // Normalizamos a solo dígitos para buscar contra columnas que se guardan limpias.
            $qDigitos = preg_replace('/\D/', '', $q);

            $base->where(function ($w) use ($q, $qDigitos) {
                $w->where('nombre', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('wa_id', 'like', "%{$q}%");

                if ($qDigitos !== '') {
                    $w->orWhere('telefono', 'like', "%{$qDigitos}%")
                      ->orWhere('dni',      'like', "%{$qDigitos}%");
                }
            });
        }

        $total = $base->count();

        $contactos = $base
            ->orderBy('nombre')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get(['id', 'telefono', 'wa_id', 'avatar_path', 'nombre', 'dni', 'email', 'fecha_nacimiento', 'omnia_patient_id', 'notas', 'updated_at'])
            ->each->setAppends(['avatar_url'])
            ->makeHidden('avatar_path');

        return response()->json([
            'ok'       => true,
            'data'     => $contactos,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'has_more' => ($page * $perPage) < $total,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $c = Contacto::findOrFail($id);
        $c->setAppends(['avatar_url'])->makeHidden('avatar_path');
        return response()->json(['ok' => true, 'contacto' => $c]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telefono'         => 'nullable|string|max:30|unique:contactos,telefono',
            'nombre'           => 'required|string|max:150',
            'dni'              => 'nullable|string|max:20|unique:contactos,dni',
            'email'            => 'nullable|email|max:150',
            'fecha_nacimiento' => 'nullable|date',
            'omnia_patient_id' => 'nullable|integer|unique:contactos,omnia_patient_id',
            'notas'            => 'nullable|string|max:500',
        ]);

        $data['telefono'] = $data['telefono'] ? preg_replace('/\D/', '', $data['telefono']) : null;
        if ($data['telefono']) {
            // Auto-resolver wa_id contra el bot. Si falla, queda null y el comando artisan lo retoma después.
            $data['wa_id'] = Contacto::resolverWaId($data['telefono']);
            // Si el JID ya estaba asignado a otro contacto (puede pasar con @lid), liberarlo.
            // El contacto nuevo gana porque su info es más fresca.
            if ($data['wa_id']) {
                Contacto::where('wa_id', $data['wa_id'])->update(['wa_id' => null]);
            }
        }
        $contacto = Contacto::create($data);

        return response()->json(['ok' => true, 'contacto' => $contacto], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $contacto = Contacto::findOrFail($id);

        $data = $request->validate([
            'telefono'         => "nullable|string|max:30|unique:contactos,telefono,{$id}",
            'nombre'           => 'required|string|max:150',
            'dni'              => "nullable|string|max:20|unique:contactos,dni,{$id}",
            'email'            => 'nullable|email|max:150',
            'fecha_nacimiento' => 'nullable|date',
            'omnia_patient_id' => "nullable|integer|unique:contactos,omnia_patient_id,{$id}",
            'notas'            => 'nullable|string|max:500',
        ]);

        $data['telefono'] = $data['telefono'] ? preg_replace('/\D/', '', $data['telefono']) : null;
        // Si cambió el teléfono, re-resolver wa_id. Si no, mantener el actual.
        if ($data['telefono'] !== $contacto->telefono) {
            $data['wa_id'] = $data['telefono'] ? Contacto::resolverWaId($data['telefono']) : null;
            // Si el JID ya estaba asignado a otro contacto (puede pasar con @lid),
            // liberarlo en el otro para evitar UNIQUE violation. El que se está
            // editando gana porque la info es más fresca.
            if ($data['wa_id']) {
                Contacto::where('wa_id', $data['wa_id'])->where('id', '!=', $id)->update(['wa_id' => null]);
            }
        }
        $contacto->update($data);

        return response()->json(['ok' => true, 'contacto' => $contacto]);
    }

    public function destroy(int $id): JsonResponse
    {
        Contacto::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    // ── Importación desde CSV de Omnia ────────────────────────

    public function importPreview(Request $request): JsonResponse
    {
        $request->validate(['archivo' => 'required|file|max:10240']);

        $path = $request->file('archivo')->getRealPath();

        // El CSV de Omnia viene en Latin-1 — convertir a UTF-8
        $contenido = file_get_contents($path);
        $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');

        $lineas = array_filter(explode("\n", str_replace("\r", '', $contenido)));
        $lineas = array_values($lineas);

        // Omnia agrega "sep=," como primera línea (puede venir con comillas) — la ignoramos
        if (isset($lineas[0]) && str_contains(strtolower($lineas[0]), 'sep=')) {
            array_shift($lineas);
        }

        // Parsear headers
        $headers = str_getcsv(array_shift($lineas), ',');
        $headers = array_map(fn($h) => mb_strtolower(trim($h)), $headers);

        $idx = fn(string ...$nombres) => collect($nombres)
            ->map(fn($n) => array_search($n, $headers))
            ->first(fn($v) => $v !== false);

        $iId        = $idx('id');
        $iApPat     = $idx('apellido paterno');
        $iApMat     = $idx('apellido materno');
        $iNombre    = $idx('nombre');
        $iOtros     = $idx('otros nombres');
        $iDni       = $idx('número de documento', 'numero de documento');
        $iTel       = $idx('teléfono', 'telefono');
        $iCel       = $idx('celular');
        $iEmail     = $idx('email');
        $iFecha     = $idx('fecha nacimiento');
        $iOs        = $idx('obra social del paciente');

        $filas = [];

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '') continue;

            $cols = str_getcsv($linea, ',');

            $col = fn($i) => isset($i, $cols[$i]) ? trim($cols[$i]) : '';

            // Construir nombre completo: Nombre [Otros] Apellido paterno [Apellido materno]
            $partes = array_filter([
                $col($iNombre),
                $col($iOtros),
                $col($iApPat),
                $col($iApMat),
            ]);
            $nombre = implode(' ', $partes);

            $dni = preg_replace('/\D/', '', $col($iDni));

            // Preferir celular; si no hay, usar teléfono.
            $telRaw = $col($iCel) ?: $col($iTel);
            $telefono = Contacto::normalizarTelefono($telRaw);

            $email = $col($iEmail);
            $fechaRaw = $col($iFecha);
            $fecha = null;
            if ($fechaRaw) {
                // Formato d/m/yyyy → yyyy-mm-dd
                $partesFecha = explode('/', $fechaRaw);
                if (count($partesFecha) === 3) {
                    $fecha = sprintf('%04d-%02d-%02d', $partesFecha[2], $partesFecha[1], $partesFecha[0]);
                }
            }

            $omniaId = $col($iId) ? (int) $col($iId) : null;
            $obraSocial = $col($iOs) ?: null;

            // Detectar conflictos bloqueantes. Política: solo bloqueamos por:
            //   • sin nombre (no podemos identificarlo)
            //   • Omnia ID ya importado (es el mismo paciente — duplicado real)
            // Tel duplicado/inválido y DNI duplicado entran como warning con el dato
            // crudo en notas para corrección manual.
            $conflicto = null;
            if (! $nombre) {
                $conflicto = 'sin_nombre';
            } elseif ($omniaId && Contacto::where('omnia_patient_id', $omniaId)->exists()) {
                $conflicto = 'omnia_duplicado';
            }

            // Warnings informativos que NO bloquean: tel y DNI con problemas.
            $telWarn = null;
            $telImportable = $telefono;
            if ($telefono && Contacto::where('telefono', $telefono)->exists()) {
                $telWarn = 'tel_duplicado';
                $telImportable = null;
            } elseif (!$telefono && $telRaw !== '') {
                $telWarn = 'tel_invalido';
                $telImportable = null;
            }

            $dniWarn = null;
            $dniImportable = $dni ?: null;
            if ($dni && Contacto::where('dni', $dni)->exists()) {
                $dniWarn = 'dni_duplicado';
                $dniImportable = null;
            }

            $notasParts = [];
            if ($obraSocial) $notasParts[] = "O.S.: {$obraSocial}";
            if ($telWarn === 'tel_duplicado') $notasParts[] = "⚠ Tel CSV (duplicado, revisar): {$telRaw}";
            if ($telWarn === 'tel_invalido')  $notasParts[] = "⚠ Tel CSV (formato inválido): {$telRaw}";
            if ($dniWarn === 'dni_duplicado') $notasParts[] = "⚠ DNI CSV (duplicado, revisar): {$dni}";

            $filas[] = [
                'nombre'           => $nombre,
                'dni'              => $dniImportable,
                'dni_raw'          => $dni ?: null,
                'dni_warn'         => $dniWarn,
                'telefono'         => $telImportable ?: null,
                'tel_raw'          => $telRaw ?: null,
                'tel_warn'         => $telWarn,
                'email'            => $email ?: null,
                'fecha_nacimiento' => $fecha,
                'notas'            => $notasParts ? implode(' | ', $notasParts) : null,
                'omnia_patient_id' => $omniaId,
                'conflicto'        => $conflicto,
                'importar'         => $conflicto === null,
            ];
        }

        $stats = [
            'total'         => count($filas),
            'ok'            => collect($filas)->where('conflicto', null)->where('tel_warn', null)->where('dni_warn', null)->count(),
            'sin_telefono'  => collect($filas)->where('telefono', null)->where('conflicto', null)->where('tel_warn', null)->count(),
            'tel_warn'      => collect($filas)->whereIn('tel_warn', ['tel_duplicado', 'tel_invalido'])->where('conflicto', null)->count(),
            'dni_warn'      => collect($filas)->where('dni_warn', 'dni_duplicado')->where('conflicto', null)->count(),
            'duplicados'    => collect($filas)->where('conflicto', 'omnia_duplicado')->count(),
        ];

        return response()->json(['ok' => true, 'filas' => $filas, 'stats' => $stats]);
    }

    public function importConfirm(Request $request): JsonResponse
    {
        // Para batches grandes el endpoint puede correr varios minutos: subimos
        // los límites de PHP. Nginx tiene su propio timeout configurado en default.conf.
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $filas = $request->input('filas', []);
        $importados = 0;
        $omitidos   = 0;
        $errores    = [];

        // Para evitar que la request HTTP se eternice (nginx timeout = 504),
        // NO resolvemos wa_id contra el bot durante el import masivo. Eso lo
        // hace después el comando artisan `contactos:mapear-wa`, que tiene
        // throttle y corre en background.
        DB::transaction(function () use ($filas, &$importados, &$omitidos, &$errores) {
            foreach ($filas as $fila) {
                if (! ($fila['importar'] ?? false)) {
                    $omitidos++;
                    continue;
                }
                try {
                    Contacto::create([
                        'telefono'         => $fila['telefono'] ?: null,
                        'wa_id'            => null, // se resuelve después con contactos:mapear-wa
                        'nombre'           => $fila['nombre'],
                        'dni'              => $fila['dni'] ?: null,
                        'email'            => $fila['email'] ?: null,
                        'fecha_nacimiento' => $fila['fecha_nacimiento'] ?: null,
                        'notas'            => $fila['notas'] ?: null,
                        'omnia_patient_id' => $fila['omnia_patient_id'] ?: null,
                    ]);
                    $importados++;
                } catch (\Exception $e) {
                    $omitidos++;
                    $errores[] = $fila['nombre'] . ': ' . $e->getMessage();
                }
            }
        });

        return response()->json([
            'ok'         => true,
            'importados' => $importados,
            'omitidos'   => $omitidos,
            'errores'    => array_slice($errores, 0, 10),
        ]);
    }
}
