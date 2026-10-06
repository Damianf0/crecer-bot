<?php

/*
 * Áreas de WhatsApp restringidas: números que funcionan como cualquier otra
 * cola (se toman, se responden, se resuelven desde el panel), pero que solo ve
 * y atiende quien tiene el permiso del área (Usuarios → permisos). Para el
 * resto no existen: ni en el menú, ni en las colas a declarar, ni en Historial,
 * ni en los contadores, ni en el estado de los bots.
 *
 * Se suman a ConversacionWA::areas() cuando están activas. El control de acceso
 * vive en App\Http\Middleware\AccesoAreaWA y en ConversacionWA::puedeVer().
 */
return [
    'restringidas' => [
        // Consultorio externo (06/10). Bot: bot-criopreservacion, puerto 3005.
        'criopreservacion' => [
            'nombre'  => 'Criopreservación',
            'permiso' => 'area_criopreservacion',
            'activa'  => (bool) env('CRIOPRESERVACION_ACTIVA', false),
        ],
    ],
];
