<?php

use App\Http\Controllers\BotController;
use App\Http\Controllers\UsuariosController;
use App\Http\Middleware\BotTokenAuth;
use Illuminate\Support\Facades\Route;

Route::middleware(BotTokenAuth::class)->group(function () {

    // Bot — derivaciones
    Route::prefix('bot')->group(function () {
        Route::post('derivaciones',       [BotController::class, 'recibirDerivacion']);
        Route::get('derivaciones',        [BotController::class, 'listarDerivaciones']);
        Route::patch('derivaciones/{id}',         [BotController::class, 'actualizarDerivacion']);
        Route::patch('derivaciones/{id}/resumen', [BotController::class, 'actualizarResumen']);

        // Inbox WhatsApp
        Route::post('mensajes',              [BotController::class, 'mensajeEntrante']);
        Route::post('mensajes/saliente',     [BotController::class, 'mensajeSaliente']);
        Route::post('mensajes/marcar-leido', [BotController::class, 'marcarLeido']);

        // Conversación WA directa (sin pasar por derivaciones)
        Route::post('conversacion/derivar',    [BotController::class, 'derivarConversacion']);
        Route::get('conversacion/historial',   [BotController::class, 'obtenerHistorial']);
        Route::patch('conversacion/historial', [BotController::class, 'guardarHistorial']);
    });

    // Usuarios — gestionados desde el panel Electron
    Route::prefix('usuarios')->group(function () {
        Route::get('/',      [UsuariosController::class, 'index']);
        Route::post('/',     [UsuariosController::class, 'store']);
        Route::patch('{id}', [UsuariosController::class, 'update']);
    });
});
