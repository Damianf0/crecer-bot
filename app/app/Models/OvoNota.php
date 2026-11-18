<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nota libre sobre una ficha del CRM de ovodonación. */
class OvoNota extends Model
{
    protected $table = 'ovo_notas';

    protected $fillable = ['ficha_id', 'user_id', 'texto'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
