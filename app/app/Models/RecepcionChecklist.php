<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Checklist de recepción de una obra social: su lista de pasos, tal como la ve
 * recepción. financiador NULL (clave '') = el General, para las obras sociales
 * que no tienen uno propio. Ver App\Services\ChecklistRecepcion.
 */
class RecepcionChecklist extends Model
{
    protected $table = 'recepcion_checklists';

    protected $fillable = ['financiador', 'clave', 'activo', 'actualizado_por'];

    protected $casts = ['activo' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(RecepcionChecklistItem::class, 'checklist_id')->orderBy('orden')->orderBy('id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function esGeneral(): bool
    {
        return $this->clave === '';
    }

    /** Nombre para comparar: sin mayúsculas ni espacios de más. '' si viene vacío. */
    public static function clave(?string $financiador): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $financiador)));
    }
}
