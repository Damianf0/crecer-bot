<?php

namespace Tests\Feature;

use App\Models\ConversacionWA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Medidor salud:ingesta, la alerta de adjuntos sin archivo (05/10): avisó a las
 * 15 h por los adjuntos perdidos antes de las 10:20, con todo lo posterior
 * guardado. No tiene que avisar por una falla que ya terminó.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=SaludIngesta
 */
class SaludIngestaTest extends TestCase
{
    use RefreshDatabase;

    private ConversacionWA $conv;

    protected function setUp(): void
    {
        parent::setUp();
        // Sábado: fuera de horario, solo se mide la calidad.
        Carbon::setTestNow('2026-10-03 15:00:00');
        $this->conv = ConversacionWA::create(['contacto' => '111@lid', 'area' => 'atencion', 'estado' => 'activa', 'nombre' => 'Paciente']);
    }

    private function adjunto(string $hora, bool $guardado): void
    {
        DB::table('mensajes_wa')->insert([
            'conversacion_id' => $this->conv->id, 'direccion' => 'entrante', 'tipo' => 'imagen', 'leido' => true,
            'wa_id' => 'false_111@lid_' . uniqid(), 'archivo_url' => $guardado ? 'http://bot:3001/media/x.jpg' : null,
            'created_at' => "2026-10-03 {$hora}:00", 'updated_at' => "2026-10-03 {$hora}:00",
        ]);
    }

    public function test_no_avisa_si_la_falla_ya_termino(): void
    {
        foreach (['09:10', '09:20', '09:30', '09:40'] as $h) $this->adjunto($h, false);
        foreach (['10:30', '11:00', '12:00'] as $h) $this->adjunto($h, true);

        $this->artisan('salud:ingesta')
            ->expectsOutputToContain('falla terminada')
            ->assertExitCode(0);
    }

    public function test_avisa_si_sigue_fallando_y_dice_la_hora(): void
    {
        foreach (['10:30', '11:00'] as $h) $this->adjunto($h, true);
        foreach (['13:10', '13:20', '14:05'] as $h) $this->adjunto($h, false);

        $this->artisan('salud:ingesta')
            ->expectsOutputToContain('última falla a las 14:05; último guardado bien a las 11:00')
            ->assertExitCode(1);
    }

    public function test_avisa_si_una_falla_reciente_se_mezcla_con_guardados(): void
    {
        foreach (['09:10', '09:20', '09:30'] as $h) $this->adjunto($h, false);
        foreach (['13:00', '13:30'] as $h) $this->adjunto($h, true);
        $this->adjunto('14:00', false);
        $this->adjunto('14:30', true);

        $this->artisan('salud:ingesta')
            ->expectsOutputToContain('última falla a las 14:00')
            ->assertExitCode(1);
    }
}
