<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Avisos del panel que una persona tiene prendidos (ver la migración). */
class PreferenciaAviso extends Model
{
    protected $table = 'preferencias_avisos';
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = ['user_id', 'prefs'];

    protected $casts = ['prefs' => 'array'];

    /** Todo prendido salvo que la persona lo apague. */
    public const DEFAULTS = [
        'chat'           => true,
        'conv_delegada'  => true,
        'tarea_asignada' => true,
        'tarea_area'     => true,
        'tarea_pedida'   => true,
        'favorito'       => true,
        'urgente'        => true,
        'sonido'         => true,
    ];

    public const ETIQUETAS = [
        'chat'           => 'Mensajes del chat interno',
        'conv_delegada'  => 'Conversaciones que me delegan',
        'tarea_asignada' => 'Tareas que me asignan',
        'tarea_area'     => 'Tareas nuevas para mis áreas',
        'tarea_pedida'   => 'Novedades de las tareas que pedí a un área',
        'favorito'       => 'Un favorito escribió',
        'urgente'        => 'Entró una consulta urgente',
        'sonido'         => 'Con sonido',
    ];

    public static function para(int $uid): array
    {
        $guardadas = (array) (self::find($uid)?->prefs ?? []);
        return array_merge(self::DEFAULTS, array_intersect_key(array_map('boolval', $guardadas), self::DEFAULTS));
    }
}
