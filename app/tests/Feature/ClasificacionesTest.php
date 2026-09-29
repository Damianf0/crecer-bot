<?php

namespace Tests\Feature;

use App\Models\ConversacionWA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clasificaciones de la IA (base de los reportes por tipo de consulta) y áreas
 * con la cola de difusiones detrás de DIFUSION_ACTIVA.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=Clasificaciones
 */
class ClasificacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.bot_token' => 'token-de-prueba']);
    }

    private function clasificar(array $datos)
    {
        return $this->withToken('token-de-prueba')->postJson('/api/bot/clasificaciones', $datos + [
            'contacto'   => '5492235550000@c.us',
            'area'       => 'atencion',
            'confianza'  => 'alta',
            'en_horario' => true,
        ]);
    }

    public function test_guarda_la_clasificacion_y_la_asocia_a_la_conversacion(): void
    {
        $conv = ConversacionWA::create(['contacto' => '5492235550000@c.us', 'area' => 'atencion', 'estado' => 'activa']);

        $this->clasificar(['codigo' => 'RESULTADO_BETA', 'resumen' => 'Pregunta por su beta'])->assertCreated();

        $this->assertDatabaseHas('clasificaciones_wa', [
            'conversacion_id' => $conv->id, 'codigo' => 'RESULTADO_BETA', 'origen' => 'bot', 'sin_ia' => false,
        ]);
    }

    public function test_sin_conversacion_igual_se_guarda(): void
    {
        $this->clasificar(['codigo' => 'IGNORAR'])->assertCreated();
        $this->assertDatabaseHas('clasificaciones_wa', ['codigo' => 'IGNORAR', 'conversacion_id' => null]);
    }

    public function test_un_codigo_inventado_por_el_modelo_cuenta_como_fallback(): void
    {
        $this->clasificar(['codigo' => 'TURNO_INVENTADO'])->assertCreated();
        $this->assertDatabaseHas('clasificaciones_wa', ['codigo' => 'FALLBACK']);
    }

    public function test_sin_token_no_entra(): void
    {
        $this->postJson('/api/bot/clasificaciones', ['contacto' => 'x', 'codigo' => 'IGNORAR'])->assertUnauthorized();
    }

    public function test_reporte_por_tipo_con_tiempos_y_correccion(): void
    {
        $sup = \App\Models\User::create(['name' => 'S', 'nombre_completo' => 'Sup', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'supervisora', 'activo' => true]);
        $conv = ConversacionWA::create(['contacto' => '5492235550000@c.us', 'area' => 'atencion', 'estado' => 'activa']);
        $hace = now()->subDays(2)->setTime(10, 0);
        foreach ([['RESULTADO_BETA', $conv->id], ['RESULTADO_BETA', null], ['TURNO_DGP', null], ['IGNORAR', null]] as [$cod, $cid]) {
            \App\Models\ClasificacionWA::create(['area' => 'atencion', 'contacto' => 'x', 'codigo' => $cod, 'conversacion_id' => $cid,
                'confianza' => 'alta', 'en_horario' => true, 'origen' => 'log'])->forceFill(['created_at' => $hace])->save();
        }
        // Respuesta humana 30 min después de la clasificación asociada.
        \App\Models\MensajeWA::create(['conversacion_id' => $conv->id, 'direccion' => 'saliente', 'tipo' => 'texto', 'contenido' => 'Hola'])
            ->forceFill(['created_at' => $hace->copy()->addMinutes(30)])->save();

        $r = $this->actingAs($sup)->withSession(['colas' => ['atencion']])->getJson('/admin/estadisticas/tipos')->assertOk();
        $this->assertSame(3, $r->json('consultas'));
        $this->assertSame(1, $r->json('ruido'));
        $this->assertSame('resultados', $r->json('familias.0.familia'));
        $this->assertEquals(66.7, $r->json('familias.0.pct'));
        $this->assertEquals(30, $r->json('tiempos.0.mediana_min'));

        $det = $this->actingAs($sup)->withSession(['colas' => ['atencion']])->getJson('/admin/estadisticas/tipos/detalle?familia=resultados')->assertOk();
        $this->assertCount(1, $det->json('filas'));   // solo las asociadas a conversación

        $id = $det->json('filas.0.id');
        $this->actingAs($sup)->withSession(['colas' => ['atencion']])->postJson("/admin/estadisticas/tipos/{$id}/corregir", ['codigo' => 'RESULTADO_OTROS'])->assertOk();
        $r = $this->actingAs($sup)->withSession(['colas' => ['atencion']])->getJson('/admin/estadisticas/tipos')->assertOk();
        $this->assertSame(1, $r->json('calidad.revisadas'));
        $this->assertEquals(0, $r->json('calidad.pct_acierto'));   // la IA se equivocó en la única revisada
    }

    public function test_la_cola_de_difusiones_solo_existe_si_esta_activa(): void
    {
        config(['difusion.activa' => false]);
        $this->assertArrayNotHasKey('difusion', ConversacionWA::areas());
        $this->clasificar(['codigo' => 'IGNORAR', 'area' => 'difusion'])->assertUnprocessable();

        config(['difusion.activa' => true]);
        $this->assertArrayHasKey('difusion', ConversacionWA::areas());
        $this->clasificar(['codigo' => 'IGNORAR', 'area' => 'difusion'])->assertCreated();
    }
}
