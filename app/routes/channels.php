<?php

use App\Models\ChatCanal;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado del chat interno: solo los miembros del canal pueden subscribir.
// Usado por React+Echo para escuchar ChatMensajeEnviado y ChatMensajeEliminado.
// La auth corre en cada conexión nueva; devolver array no-vacío = autorizado.
Broadcast::channel('chat.canal.{id}', function ($user, $id) {
    $esMiembro = DB::table('chat_canal_user')
        ->where('canal_id', (int) $id)
        ->where('user_id', $user->id)
        ->exists();
    return $esMiembro ? ['id' => $user->id, 'nombre' => $user->nombre_completo] : false;
});
