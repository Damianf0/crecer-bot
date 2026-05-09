<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatCanal extends Model
{
    protected $table = 'chat_canales';
    protected $fillable = ['tipo', 'nombre'];

    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_canal_user', 'canal_id', 'user_id')
            ->withPivot('ultimo_leido_id')
            ->withTimestamps();
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(ChatMensaje::class, 'canal_id');
    }

    /** Devuelve (o crea) el canal único de equipo. */
    public static function equipo(): self
    {
        return static::firstOrCreate(['tipo' => 'equipo'], ['nombre' => 'Equipo']);
    }

    /**
     * Devuelve (o crea) el canal DM entre dos usuarios.
     * Garantiza un único DM por par sin importar el orden.
     */
    public static function dmEntre(int $userA, int $userB): self
    {
        if ($userA === $userB) {
            throw new \InvalidArgumentException('No se puede crear DM consigo mismo.');
        }
        [$a, $b] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        // Buscar canal DM con exactamente esos dos miembros
        $existing = static::where('tipo', 'dm')
            ->whereHas('miembros', fn($q) => $q->where('user_id', $a))
            ->whereHas('miembros', fn($q) => $q->where('user_id', $b))
            ->withCount('miembros')
            ->get()
            ->first(fn($c) => $c->miembros_count === 2);

        if ($existing) return $existing;

        $canal = static::create(['tipo' => 'dm', 'nombre' => null]);
        $canal->miembros()->attach([$a, $b]);
        return $canal;
    }

    /** Asegura que el usuario sea miembro (idempotente). Útil para canal Equipo. */
    public function agregarMiembro(int $userId): void
    {
        if (!$this->miembros()->where('user_id', $userId)->exists()) {
            $this->miembros()->attach($userId);
        }
    }
}
