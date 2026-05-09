<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TareaComentario extends Model
{
    protected $table = 'tarea_comentarios';

    protected $fillable = ['tarea_id', 'user_id', 'contenido'];

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
