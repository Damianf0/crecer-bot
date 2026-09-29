<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DifusionCampania extends Model
{
    protected $table = 'difusion_campanias';

    public const ESTADOS = [
        'borrador'   => 'Borrador',
        'programada' => 'Programada',
        'enviando'   => 'Enviando',
        'pausada'    => 'Pausada',
        'terminada'  => 'Terminada',
        'cancelada'  => 'Cancelada',
    ];

    protected $fillable = [
        'nombre', 'plantilla_id', 'texto', 'adjunto_path', 'adjunto_nombre', 'adjunto_mime',
        'audiencia', 'proveedor', 'canal', 'estado', 'programada_para', 'iniciada_at', 'terminada_at', 'creado_por',
    ];

    protected $casts = [
        'audiencia'       => 'array',
        'programada_para' => 'datetime',
        'iniciada_at'     => 'datetime',
        'terminada_at'    => 'datetime',
    ];

    public function destinatarios(): HasMany
    {
        return $this->hasMany(DifusionDestinatario::class, 'campania_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** Texto final para una persona: {nombre} → primer nombre (o nada). */
    public function textoPara(?string $nombre): string
    {
        $primer = trim(explode(' ', trim((string) $nombre))[0] ?? '');
        $texto = str_replace(['{nombre}', '{ nombre }'], $primer, $this->texto);
        // "Hola ," cuando no hay nombre → "Hola,"
        return preg_replace('/ +([,.!?])/u', '$1', $texto);
    }

    /**
     * Métricas de una o varias campañas en una sola consulta.
     * @return array<int, array> campania_id => métricas
     */
    public static function metricas(array $ids): array
    {
        if (!$ids) return [];
        $filas = DB::table('difusion_destinatarios')
            ->whereIn('campania_id', $ids)
            ->groupBy('campania_id')
            ->selectRaw("campania_id,
                COUNT(*) total,
                SUM(estado = 'pendiente') pendientes,
                SUM(enviado_at IS NOT NULL) enviados,
                SUM(entregado_at IS NOT NULL) entregados,
                SUM(leido_at IS NOT NULL) leidos,
                SUM(respondio_at IS NOT NULL) respondieron,
                SUM(estado = 'fallido') fallidos,
                SUM(estado = 'omitido') omitidos,
                SUM(baja_at IS NOT NULL) bajas")
            ->get()->keyBy('campania_id');

        $out = [];
        foreach ($ids as $id) {
            $f = $filas[$id] ?? null;
            $m = [];
            foreach (['total', 'pendientes', 'enviados', 'entregados', 'leidos', 'respondieron', 'fallidos', 'omitidos', 'bajas'] as $k) {
                $m[$k] = (int) ($f->$k ?? 0);
            }
            $base = max(1, $m['enviados']);
            $m['tasa_entrega']   = $m['enviados'] ? round(100 * $m['entregados'] / $base, 1) : null;
            $m['tasa_lectura']   = $m['enviados'] ? round(100 * $m['leidos'] / $base, 1) : null;
            $m['tasa_respuesta'] = $m['enviados'] ? round(100 * $m['respondieron'] / $base, 1) : null;
            $out[$id] = $m;
        }
        return $out;
    }
}
