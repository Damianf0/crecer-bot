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

    public function test_con_resumen_al_dia_no_encola(): void
    {
        for ($i = 0; $i < 3; $i++) $this->entrante();
        $ultimo = DB::table('mensajes_wa')->max('id');
        $this->conv->forceFill(['resumen_llm' => 'Consulta por turno', 'resumen_msg_id' => $ultimo])->save();
        $this->travel(5)->minutes();
        $this->conv->refresh()->despacharResumenSiAmerita();
        Queue::assertPushed(GenerarResumenLLM::class, 1);   // solo el primero
    }

    public function test_mensajes_nuevos_despues_del_resumen_lo_regeneran(): void
    {
        for ($i = 0; $i < 3; $i++) $this->entrante();
        $this->conv->forceFill(['resumen_llm' => 'Consulta por turno', 'resumen_msg_id' => DB::table('mensajes_wa')->max('id')])->save();
        $this->travel(5)->minutes();
        $this->entrante();
        Queue::assertPushed(GenerarResumenLLM::class, 2);
    }

    public function test_volver_a_escribir_tras_dias_abre_consulta_nueva_y_borra_el_resumen(): void
    {
        $this->conv->forceFill(['resumen_llm' => 'Resumen de mayo', 'resumen_msg_id' => 1, 'ultima_actividad' => now()->subDays(10)])->save();
        $this->assertTrue($this->conv->refresh()->abrirEpisodioSiCorresponde());
        $c = $this->conv->refresh();
        $this->assertNull($c->resumen_llm);
        $this->assertNotNull($c->episodio_desde);
    }

    public function test_charla_en_curso_no_abre_consulta_nueva(): void
    {
        $this->conv->forceFill(['resumen_llm' => 'Consulta por turno', 'ultima_actividad' => now()->subHours(5)])->save();
        $this->assertFalse($this->conv->refresh()->abrirEpisodioSiCorresponde());
        $this->assertSame('Consulta por turno', $this->conv->refresh()->resumen_llm);
    }

    public function test_reabrir_archivada_abre_consulta_nueva(): void
    {
        $this->conv->forceFill(['resumen_llm' => 'Viejo', 'estado' => 'archivada', 'ultima_actividad' => now()->subHour()])->save();
        $this->assertTrue($this->conv->refresh()->abrirEpisodioSiCorresponde());
    }

    public function test_amerita_cuenta_solo_la_consulta_actual(): void
    {
        DB::table('mensajes_wa')->insert([
            'conversacion_id' => $this->conv->id, 'direccion' => 'entrante', 'tipo' => 'texto', 'leido' => true,
            'contenido' => str_repeat('consulta larga de mayo ', 10), 'created_at' => now()->subMonths(5), 'updated_at' => now()->subMonths(5),
        ]);
        $this->conv->forceFill(['episodio_desde' => now()->subMinute()])->save();
        $this->entrante();   // "hola" en la consulta nueva
        Queue::assertNothingPushed();
    }

    public function test_el_job_resume_la_consulta_actual_y_anota_hasta_donde(): void
    {
        DB::table('mensajes_wa')->insert([
            'conversacion_id' => $this->conv->id, 'direccion' => 'entrante', 'tipo' => 'texto', 'leido' => true,
            'contenido' => 'TEMA VIEJO', 'created_at' => now()->subMonths(5), 'updated_at' => now()->subMonths(5),
        ]);
        $this->conv->forceFill(['episodio_desde' => now()->subMinute(), 'resumen_llm' => 'Resumen viejo', 'resumen_msg_id' => 1])->save();
        $this->entrante();
        $ultimo = DB::table('mensajes_wa')->max('id');

        $enviado = null;
        \Illuminate\Support\Facades\Http::fake(function ($req) use (&$enviado) {
            $enviado = $req['texto'];
            return \Illuminate\Support\Facades\Http::response(['resumen' => 'Resumen nuevo']);
        });
        (new GenerarResumenLLM($this->conv->id))->handle();

        $c = $this->conv->refresh();
        $this->assertSame('Resumen nuevo', $c->resumen_llm);
        $this->assertSame($ultimo, $c->resumen_msg_id);
        $this->assertStringNotContainsString('TEMA VIEJO', $enviado);
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
