<?php

namespace Tests\Feature;

use App\Jobs\GenerarResumenLLM;
use App\Models\ConversacionWA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Despacho del resumen (12/10): entraron 4 mensajes cortos en 12 segundos, el
 * primero solo no ameritaba y los otros tres cayeron en la espera de 10 min,
 * así que la conversación quedó sin resumen.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=ResumenDespacho
 */
class ResumenDespachoTest extends TestCase
{
    use RefreshDatabase;

    private ConversacionWA $conv;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->conv = ConversacionWA::create(['contacto' => '111@lid', 'area' => 'atencion', 'estado' => 'activa', 'nombre' => 'Paciente']);
    }

    private function entrante(): void
    {
        DB::table('mensajes_wa')->insert([
            'conversacion_id' => $this->conv->id, 'direccion' => 'entrante', 'tipo' => 'texto', 'leido' => false,
            'contenido' => 'hola', 'wa_id' => 'false_111@lid_' . uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->conv->refresh()->despacharResumenSiAmerita();
    }

    public function test_una_rafaga_de_mensajes_cortos_termina_con_resumen(): void
    {
        $this->entrante();
        $this->entrante();
        Queue::assertNothingPushed();
        $this->assertNotNull($this->conv->refresh()->resumen_intento_at);   // evaluada: no amerita

        $this->entrante();
        Queue::assertPushed(GenerarResumenLLM::class, 1);
    }

    public function test_no_encola_uno_por_mensaje(): void
    {
        for ($i = 0; $i < 6; $i++) $this->entrante();
        Queue::assertPushed(GenerarResumenLLM::class, 1);

        $this->travel(11)->minutes();
        $this->entrante();
        Queue::assertPushed(GenerarResumenLLM::class, 2);
    }
}
