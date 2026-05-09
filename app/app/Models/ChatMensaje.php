<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMensaje extends Model
{
    use SoftDeletes;

    protected $table = 'chat_mensajes';
    protected $fillable = ['canal_id', 'user_id', 'texto'];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(ChatCanal::class, 'canal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
