<?php

/*
 * Difusiones: envíos masivos. Cada campaña elige el CANAL por el que sale: un
 * número de WhatsApp (un bot con QR) o un proveedor (API oficial de Meta o un
 * intermediario). El resto del módulo (audiencias, estadísticas, bajas) es el
 * mismo para todos: ver App\Services\Difusion\Canal.
 */
return [

    // Muestra la cola de Difusiones en el panel y acepta mensajes del bot del
    // área 'difusion'. Encenderlo recién cuando el número esté pareado.
    'activa' => (bool) env('DIFUSION_ACTIVA', false),

    /*
     * Canales posibles. tipo:
     *   simulado  no manda nada; acuses ficticios para probar de punta a punta.
     *   wwebjs    un bot de WhatsApp con QR (bot_url). Las respuestas entran a
     *             la cola de su `area`.
     *   cloudapi  WhatsApp Cloud API: Meta directo o un intermediario que use el
     *             mismo formato (360dialog, etc.). Pendiente de implementar el
     *             envío: plantillas aprobadas + webhook público para acuses.
     * riesgo: aviso que se muestra al elegirlo (los números de las áreas son los
     * que usan los pacientes todos los días: un bloqueo por envío masivo deja al
     * área sin WhatsApp).
     */
    'canales' => [
        'simulado' => ['tipo' => 'simulado', 'nombre' => 'Simulado (no envía)'],
        'difusion' => ['tipo' => 'wwebjs', 'nombre' => 'Número de difusiones', 'area' => 'difusion',
                       'bot_url' => env('BOT_URL_DIFUSION', 'http://bot-difusion:3004')],
        'atencion' => ['tipo' => 'wwebjs', 'nombre' => 'Número de Atención', 'area' => 'atencion',
                       'bot_url' => env('BOT_URL', 'http://bot:3001'),
                       'riesgo' => 'Es el número principal de los pacientes: si WhatsApp lo bloquea por envío masivo, atención se queda sin WhatsApp.'],
        'administracion' => ['tipo' => 'wwebjs', 'nombre' => 'Número de Administración', 'area' => 'administracion',
                       'bot_url' => env('BOT_URL_ADMINISTRACION', 'http://bot-administracion:3002'),
                       'riesgo' => 'Si WhatsApp lo bloquea por envío masivo, administración se queda sin WhatsApp.'],
        'ovodonacion' => ['tipo' => 'wwebjs', 'nombre' => 'Número de Ovodonación', 'area' => 'ovodonacion',
                       'bot_url' => env('BOT_URL_OVODONACION', 'http://bot-ovodonacion:3003'),
                       'riesgo' => 'Si WhatsApp lo bloquea por envío masivo, ovodonación se queda sin WhatsApp.'],
        'meta'     => ['tipo' => 'cloudapi', 'nombre' => 'API oficial de Meta',
                       'base_url' => 'https://graph.facebook.com/v21.0',
                       'phone_number_id' => env('DIFUSION_META_PHONE_ID'), 'token' => env('DIFUSION_META_TOKEN')],
        'tercero'  => ['tipo' => 'cloudapi', 'nombre' => env('DIFUSION_BSP_NOMBRE', 'Proveedor externo'),
                       'base_url' => env('DIFUSION_BSP_URL'), 'phone_number_id' => env('DIFUSION_BSP_PHONE_ID'),
                       'token' => env('DIFUSION_BSP_TOKEN')],
    ],

    // Cuáles aparecen para elegir en una campaña (en ese orden). Los números
    // de las áreas no están por defecto: agregarlos es una decisión explícita.
    'canales_habilitados' => array_values(array_filter(array_map('trim',
        explode(',', env('DIFUSION_CANALES', 'simulado,difusion,meta,tercero'))))),

    // Canal preseleccionado en una campaña nueva.
    'canal_por_defecto' => env('DIFUSION_CANAL', 'simulado'),

    // Ritmo de envío por número con QR. Es lo que más protege al número de un
    // bloqueo: pausa aleatoria entre mensajes y tope diario (por canal).
    'pausa_min_seg' => (int) env('DIFUSION_PAUSA_MIN', 20),
    'pausa_max_seg' => (int) env('DIFUSION_PAUSA_MAX', 60),
    'tope_diario'   => (int) env('DIFUSION_TOPE_DIARIO', 200),
    'horario'       => [env('DIFUSION_HORA_DESDE', '09:00'), env('DIFUSION_HORA_HASTA', '19:00')],

    // Una respuesta que sea exactamente una de estas palabras da de baja al
    // contacto de todas las difusiones futuras.
    'palabras_baja' => ['BAJA', 'STOP', 'NO MAS', 'NO MÁS', 'DESUSCRIBIR'],

    // Ventana para contar una respuesta como "respondió a la campaña".
    'ventana_respuesta_horas' => 72,
];
