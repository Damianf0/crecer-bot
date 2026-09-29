<?php

namespace App\Support;

/**
 * Catálogo de los códigos del clasificador del bot (bot/ollama.js) y su
 * agrupación en familias para los reportes. Los códigos son de ACCIÓN (qué
 * responder o a quién derivar) y demasiado finos para un informe; las familias
 * responden "de qué nos consultan".
 */
class TiposConsulta
{
    /**
     * familia => [etiqueta, color modo claro, color modo oscuro]. Paleta
     * categórica de 8 colores validada para daltonismo (skill dataviz,
     * superficies #ffffff / #18181b del panel): un color FIJO por familia, nunca
     * por ranking. "Sin clasificar" y "Saludos" van en gris (hacen de "Otros").
     */
    public const FAMILIAS = [
        'turnos'         => ['Turnos',                 '#2a78d6', '#3987e5'],
        'primera'        => ['Primera consulta',       '#eb6834', '#d95926'],
        'presupuestos'   => ['Presupuestos',           '#1baf7a', '#199e70'],
        'resultados'     => ['Resultados',             '#eda100', '#c98500'],
        'clinica'        => ['Consulta clínica',       '#e87ba4', '#d55181'],
        'ordenes'        => ['Órdenes',                '#008300', '#008300'],
        'medicacion'     => ['Medicación',             '#4a3aa7', '#9085e9'],
        'secretaria'     => ['Derivado a secretaría',  '#e34948', '#e66767'],
        'sin_clasificar' => ['Sin clasificar',         '#9b9a93', '#7a7973'],
        'ruido'          => ['Saludos / sin consulta', '#d6d5ce', '#4a4945'],
    ];

    /** codigo => [etiqueta, familia] */
    public const CODIGOS = [
        'PRIMERA_CONSULTA'       => ['Primera consulta',               'primera'],
        'TURNO_PORTAL'           => ['Turno por portal',               'turnos'],
        'TURNO_ECO_CON_CUENTA'   => ['Turno ecografía (con cuenta)',   'turnos'],
        'TURNO_ECO_SIN_CUENTA'   => ['Turno ecografía (sin cuenta)',   'turnos'],
        'TURNO_DGP'              => ['Turno DGP',                      'turnos'],
        'TURNO_PRESERVACION'     => ['Preservación de fertilidad',     'turnos'],
        'TURNO_PRESUPUESTO'      => ['Presupuesto',                    'presupuestos'],
        'RESULTADO_BETA'         => ['Resultado beta hCG',             'resultados'],
        'RESULTADO_OTROS'        => ['Otros resultados',               'resultados'],
        'MEDICACION_INSTRUCTIVO' => ['Instructivo de medicación',      'medicacion'],
        'ORDEN_MDP'              => ['Orden (Mar del Plata)',          'ordenes'],
        'ORDEN_OTRA_CIUDAD'      => ['Orden (otra ciudad)',            'ordenes'],
        'CONSULTA_CLINICA'       => ['Consulta clínica',               'clinica'],
        'DERIVAR_SECRETARIA'     => ['Derivación a secretaría',        'secretaria'],
        'FALLBACK'               => ['Sin clasificar',                 'sin_clasificar'],
        'IGNORAR'                => ['Saludo / sin consulta',          'ruido'],
    ];

    public static function valido(string $codigo): bool
    {
        return isset(self::CODIGOS[$codigo]);
    }

    public static function etiqueta(string $codigo): string
    {
        return self::CODIGOS[$codigo][0] ?? $codigo;
    }

    public static function familia(string $codigo): string
    {
        return self::CODIGOS[$codigo][1] ?? 'sin_clasificar';
    }

    /** Expresión SQL CASE código → familia (para agrupar en la base). */
    public static function sqlFamilia(string $columna): string
    {
        $cases = collect(self::CODIGOS)
            ->map(fn($v, $k) => "WHEN '{$k}' THEN '{$v[1]}'")
            ->implode(' ');
        return "CASE {$columna} {$cases} ELSE 'sin_clasificar' END";
    }
}
