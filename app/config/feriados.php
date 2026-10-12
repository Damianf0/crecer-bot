<?php

/*
 * Feriados nacionales en días de semana: la clínica no atiende y casi no
 * entran mensajes. Los usa salud:ingesta para no avisar "bot sordo" por el
 * silencio de un feriado (12/10: alertó Secretaría y Administración con los
 * bots sanos) y para no contarlos en la línea de base.
 *
 * Un día de más acá apaga el aviso de silencio ese día: cargar solo los que la
 * clínica realmente no trabaja. Los puentes turísticos de 2027 no están (se
 * decretan aparte): sumarlos cuando salgan.
 */
return [
    'fechas' => [
        // 2026
        '2026-10-12',   // Diversidad Cultural
        '2026-11-23',   // Soberanía Nacional (20/11 trasladado)
        '2026-12-08',   // Inmaculada Concepción
        '2026-12-25',   // Navidad

        // 2027
        '2027-01-01',   // Año Nuevo
        '2027-02-08',   // Carnaval
        '2027-02-09',   // Carnaval
        '2027-03-24',   // Memoria
        '2027-03-26',   // Viernes Santo
        '2027-04-02',   // Malvinas
        '2027-05-25',   // Revolución de Mayo
        '2027-06-21',   // Güemes (17/06 trasladado)
        '2027-07-09',   // Independencia
        '2027-08-16',   // San Martín (17/08 trasladado)
        '2027-10-11',   // Diversidad Cultural (12/10 trasladado)
        '2027-12-08',   // Inmaculada Concepción
    ],
];
