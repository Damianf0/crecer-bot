<?php

use App\Livewire\ColaBot;
use App\Livewire\ColaSecretaria;
use App\Livewire\InboxWA;
use App\Livewire\GestionAtencion;
use App\Livewire\DeclaracionColas;
use App\Http\Controllers\AtencionController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MedicoController;
use App\Http\Controllers\EstadisticasController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DocumentoController;
use App\Livewire\Login;
use App\Livewire\Tablet;
use App\Http\Middleware\SecretariaAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!Auth::check()) return redirect('/login');
    $u = Auth::user();
    if ($u->hasPermiso('secretaria')) return redirect('/secretaria');
    if ($u->hasPermiso('medico'))     return redirect('/medico');
    if ($u->hasPermiso('atencion'))   return redirect('/atencion');
    if ($u->hasPermiso('admin'))      return redirect('/admin');
    return redirect('/login');
});

// Auth
Route::get('/login',  Login::class)->name('login');
Route::get('/logout', function () {
    // Cerrar sesión activa en base de datos
    if (Auth::check()) {
        Auth::user()->sesionActiva()?->update(['fin_sesion' => now()]);
        session()->forget('colas');
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }
    return redirect('/login');
})->name('logout');

// Declaración de colas (requiere auth, no requiere colas)
Route::get('/declarar-colas', DeclaracionColas::class)
    ->middleware('auth');

// Tablet — sin auth (pantalla pública en la sala)
Route::get('/tablet', Tablet::class);

// Panel médico — auth + permiso:medico, sin requerir declaración de colas.
Route::middleware(['auth', 'permiso:medico'])->prefix('medico')->group(function () {
    Route::get('/',                  [MedicoController::class, 'index']);
    Route::get('/data',              [MedicoController::class, 'data']);
    Route::post('/tareas',           [MedicoController::class, 'crearTarea']);
    Route::post('/{id}/llamar',      [MedicoController::class, 'llamar'])->whereNumber('id');
    Route::post('/{id}/rellamar',    [MedicoController::class, 'rellamar'])->whereNumber('id');
    Route::post('/{id}/atendido',    [MedicoController::class, 'atendido'])->whereNumber('id');
});

// Área de secretaria — requiere auth + activo + colas declaradas
Route::middleware([SecretariaAuth::class])->group(function () {

    // Pulso del bot: badge en navbar para cualquier usuario autenticado.
    Route::get('/bot-pulso', [AdminController::class, 'pulso']);

    // Ficha de contacto (read-only) — usada desde /atencion al click en avatar.
    // Disponible para cualquier secretaria autenticada, no requiere permiso:contactos.
    Route::get('/contactos/{id}', [ContactoController::class, 'show'])->whereNumber('id');

    // Chat interno (equipo + DMs) — disponible para cualquier usuario autenticado.
    Route::prefix('chat')->group(function () {
        Route::get('/canales',                       [ChatController::class, 'canales']);
        Route::get('/canales/{id}/mensajes',         [ChatController::class, 'mensajes']);
        Route::post('/canales/{id}/mensajes',        [ChatController::class, 'enviar']);
        Route::post('/canales/{id}/marcar-leido',    [ChatController::class, 'marcarLeido']);
        Route::post('/canales/{id}/cerrar',          [ChatController::class, 'cerrar']);
        Route::get('/canales/{id}/buscar',           [ChatController::class, 'buscar']);
        Route::delete('/mensajes/{id}',              [ChatController::class, 'eliminarMensaje']);
        Route::get('/usuarios',                      [ChatController::class, 'usuarios']);
        Route::post('/dm',                           [ChatController::class, 'abrirDm']);
        Route::get('/no-leidos',                     [ChatController::class, 'noLeidos']);
    });

    // Cola de recepción
    Route::middleware('permiso:secretaria')->group(function () {
        Route::get('/secretaria', ColaSecretaria::class);
        Route::get('/cola-bot',   ColaBot::class);
        Route::get('/inbox-wa',   InboxWA::class);
    });

    // Atención y mis tareas
    Route::middleware('permiso:atencion')->group(function () {
        Route::get('/atencion',                   [AtencionController::class, 'index']);
        Route::get('/atencion/items',             [AtencionController::class, 'items']);
        Route::get('/atencion/conversacion/{id}', [AtencionController::class, 'conversacion']);
        Route::post('/atencion/conversacion/{id}/agregar-contacto', [AtencionController::class, 'agregarContactoDesdeConv']);
        Route::get('/atencion/derivacion/{id}',   [AtencionController::class, 'derivacion']);
        Route::post('/atencion/tomar',            [AtencionController::class, 'tomar']);
        Route::post('/atencion/delegar',          [AtencionController::class, 'delegar']);
        Route::post('/atencion/urgente',          [AtencionController::class, 'urgente']);
        Route::post('/atencion/resolver',         [AtencionController::class, 'resolver']);
        Route::post('/atencion/enviar',           [AtencionController::class, 'enviarMensaje']);
        Route::post('/atencion/enviar-archivo',   [AtencionController::class, 'enviarArchivo']);
        Route::post('/atencion/iniciar',          [AtencionController::class, 'iniciarConversacion']);
        Route::post('/atencion/reabrir',          [AtencionController::class, 'reabrir']);
        Route::get('/mis-tareas',                 [AtencionController::class, 'misTareas']);
        Route::get('/mis-tareas/data',            [AtencionController::class, 'misTareasData']);

        // Tareas generales
        Route::get('/tareas/data',                [TareaController::class, 'data']);
        Route::post('/tareas',                    [TareaController::class, 'store']);
        Route::patch('/tareas/{id}',              [TareaController::class, 'update']);
        Route::delete('/tareas/{id}',             [TareaController::class, 'destroy']);
        Route::post('/tareas/{id}/comentario',    [TareaController::class, 'comentar']);
        Route::delete('/tareas/comentario/{id}',  [TareaController::class, 'eliminarComentario']);
    });

    // Historial
    Route::middleware('permiso:historial')->group(function () {
        Route::get('/historial', [AtencionController::class, 'historial']);
    });

    // Legajo de documentos por paciente
    Route::middleware('permiso:contactos')->group(function () {
        Route::get('/pacientes/{id}/documentos',          [DocumentoController::class, 'indexPaciente']);
        Route::get('/pacientes/{id}/documentos/data',     [DocumentoController::class, 'dataPaciente']);
        Route::post('/pacientes/{id}/documentos/upload',  [DocumentoController::class, 'uploadManual']);
        Route::post('/pacientes/{id}/documentos/zip',     [DocumentoController::class, 'descargarZip']);
        Route::get('/documentos/{id}/preview',            [DocumentoController::class, 'preview'])->name('docs.preview');
        Route::get('/documentos/{id}/descargar',          [DocumentoController::class, 'descargar'])->name('docs.descargar');
        Route::post('/documentos/{id}/destacar',          [DocumentoController::class, 'destacar']);
        Route::patch('/documentos/{id}/notas',            [DocumentoController::class, 'notas']);
        Route::post('/documentos/{id}/reenviar',          [DocumentoController::class, 'reenviar']);
        Route::delete('/documentos/{id}',                 [DocumentoController::class, 'eliminar']);
    });

    // Contactos
    Route::middleware('permiso:contactos')->group(function () {
        Route::get('/contactos',                    [ContactoController::class, 'index']);
        Route::get('/contactos/data',               [ContactoController::class, 'data']);
        Route::post('/contactos',                   [ContactoController::class, 'store']);
        Route::patch('/contactos/{id}',             [ContactoController::class, 'update']);
        Route::delete('/contactos/{id}',            [ContactoController::class, 'destroy']);
        Route::post('/contactos/import/preview',    [ContactoController::class, 'importPreview']);
        Route::post('/contactos/import/confirm',    [ContactoController::class, 'importConfirm']);
    });

    // Admin (panel de administración del bot via web)
    Route::middleware('permiso:admin')->prefix('admin')->group(function () {
        Route::get('/',                         [AdminController::class, 'dashboard']);
        Route::get('/bot/status',               [AdminController::class, 'botStatus']);
        Route::get('/tareas',                   [AdminController::class, 'tareas']);

        Route::get('/textos',                   [AdminController::class, 'textos']);
        Route::get('/textos/data',              [AdminController::class, 'textosGet']);
        Route::post('/textos/save',             [AdminController::class, 'textosSave']);

        Route::get('/pruebas',                  [AdminController::class, 'pruebas']);
        Route::post('/pruebas/modo',            [AdminController::class, 'pruebasModo']);
        Route::get('/pruebas/stream',           [AdminController::class, 'pruebasStream']);

        Route::get('/logs',                     [AdminController::class, 'logs']);
        Route::get('/logs/stream',              [AdminController::class, 'logsStream']);

        Route::get('/legajo',                   [AdminController::class, 'legajoConfig']);
        Route::post('/legajo/save',             [AdminController::class, 'legajoConfigSave']);

        Route::get('/usuarios',                 [AdminController::class, 'usuarios']);
        Route::get('/usuarios/data',            [AdminController::class, 'usuariosData']);
        Route::post('/usuarios/save',           [AdminController::class, 'usuariosSave']);
        Route::post('/usuarios/{id}/save',      [AdminController::class, 'usuariosSave']);

        Route::get('/estadisticas',              [EstadisticasController::class, 'index']);
        Route::get('/estadisticas/hoy',          [EstadisticasController::class, 'hoy']);
        Route::get('/estadisticas/secretarias',  [EstadisticasController::class, 'secretarias']);
        Route::get('/estadisticas/tendencias',   [EstadisticasController::class, 'tendencias']);

        Route::get('/medicos',           [AdminController::class, 'medicos']);
        Route::get('/medicos/data',      [AdminController::class, 'medicosData']);
        Route::post('/medicos/save',     [AdminController::class, 'medicosSave']);
        Route::delete('/medicos/{id}',   [AdminController::class, 'medicosDestroy']);

        Route::get('/tunnel',         [AdminController::class, 'tunnel']);
        Route::get('/tunnel/status',  [AdminController::class, 'tunnelStatus']);
        Route::post('/tunnel/start',  [AdminController::class, 'tunnelStart']);
        Route::post('/tunnel/stop',   [AdminController::class, 'tunnelStop']);
    });

    // Agenda
    Route::middleware('permiso:agenda')->group(function () {
        Route::get('/agenda',            [AgendaController::class, 'index']);
        Route::get('/agenda/data',       [AgendaController::class, 'data']);
        Route::post('/agenda',           [AgendaController::class, 'store']);
        Route::patch('/agenda/{id}',     [AgendaController::class, 'update']);
        Route::delete('/agenda/{id}',    [AgendaController::class, 'destroy']);
    });
});
