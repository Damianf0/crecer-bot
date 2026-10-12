<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un paso del checklist de recepción de una obra social. */
class RecepcionChecklistItem extends Model
{
    protected $table = 'recepcion_checklist_items';

    protected $fillable = ['checklist_id', 'texto', 'obligatorio', 'nota', 'orden'];

    protected $casts = ['obligatorio' => 'boolean', 'orden' => 'integer'];
}
