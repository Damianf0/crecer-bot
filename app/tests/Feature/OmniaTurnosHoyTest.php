<?php

namespace Tests\Feature;

use App\Services\OmniaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Turnos de hoy de un paciente (tablet y mostrador): cuenta todo lo que todavía
 * no se atendió. El 15/10 el tablet le decía "no tenés turno" a quien tenía el
 * turno "confirmado" en Omnia (solo contaba "pendiente").
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=OmniaTurnosHoy
 */
class OmniaTurnosHoyTest extends TestCase
{
    public function test_cuenta_pendientes_y_confirmados_de_hoy(): void
    {
        Cache::put('omnia_token', 'tok', 60);
        $tz  = 'America/Argentina/Buenos_Aires';
        $hoy = fn (string $h) => now($tz)->setTimeFromTimeString($h)->timestamp;

        Http::fake(['*/patients/7/appointments/pending' => Http::response([
            ['id' => 1, 'state' => 'confirmado', 'begins' => $hoy('15:00'), 'practices' => ['Consulta']],
            ['id' => 2, 'state' => 'pendiente',  'begins' => $hoy('09:30'), 'practices' => ['Ecografía']],
            ['id' => 3, 'state' => 'confirmado', 'begins' => now($tz)->addDays(2)->timestamp],
            ['id' => 4, 'state' => 'cancelado',  'begins' => $hoy('11:00')],
        ])]);

        $turnos = app(OmniaService::class)->turnosHoy(7);

        $this->assertSame([2, 1], array_column($turnos, 'id'));
        $this->assertSame(['09:30', '15:00'], array_column($turnos, 'hora'));
    }
}
