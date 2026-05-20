{{--
    Widget de chat interno (Equipo + DMs).
    Refactor 2026-05-19: la UI fue migrada a React (resources/js/chat/) +
    Laravel Reverb para tiempo real. Este partial solo carga el bundle y
    expone los datos del usuario actual via window.__USER__ para que el
    código JS sepa quién es sin pedirlo al backend.

    Backend: ChatController + 11 rutas /chat/* + Reverb broadcasts (eventos
    ChatMensajeEnviado y ChatMensajeEliminado).

    Portar al repo light: copiar ChatController.php + modelos + migraciones +
    rutas /chat + routes/channels.php + servicio reverb en docker-compose +
    todo el módulo resources/js/chat/ + agregar @include('chat._widget') al
    layout.
--}}
@auth
<div id="chat-root"></div>
<script>
    window.__USER__ = {
        id: {{ Auth::id() }},
        nombre_completo: @json(Auth::user()->nombre_completo ?? Auth::user()->name ?? '?'),
    };
</script>
@vite(['resources/js/chat/index.tsx'])
@endauth
