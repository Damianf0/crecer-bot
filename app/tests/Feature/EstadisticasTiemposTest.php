<?php

namespace Tests\Feature;

use App\Models\ConversacionEvento;
use App\Models\ConversacionWA;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Reportes: los tiempos (SLA, respuesta, resolución) tienen que salir positivos.
 * Con Carbon 3, $a->diffInSeconds($b) da b − a con signo; antes se restaba al revés
 * y Reportes mostraba tiempos negativos y un SLA de 100 % falso.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=EstadisticasTiempos
 */
class EstadisticasTiemposTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sla_respuesta_y_resolucion_son_positivos(): void
    {
        $sup = User::create(['name' => 'S', 'nombre_completo' => 'Sup', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'supervisora', 'activo' => true]);

        $t0 = now()->startOfDay()->setTime(10, 0);
        $conv = null;
        $entrante = function () use (&$conv) { \App\Models\MensajeWA::create(['conversacion_id' => $conv->id, 'direccion' => 'entrante', 'tipo' => 'texto', 'contenido' => 'Hola', 'leido' => true]); };

        // Conversación vieja (de hace 3 meses), ya resuelta una vez: no cuenta para la espera.
        Carbon::setTestNow($t0->copy()->subMonths(3));
        $conv = ConversacionWA::create(['contacto' => '5492235550000@c.us', 'area' => 'atencion', 'estado' => 'activa']);
        $entrante();
        Carbon::setTestNow($t0->copy()->subMonths(3)->addHour());
        ConversacionEvento::create(['conversacion_id' => $conv->id, 'tipo' => 'resuelta']);

        Carbon::setTestNow($t0);                            // la paciente vuelve a escribir
        $entrante();
        Carbon::setTestNow($t0->copy()->addMinutes(5));
        $entrante();

        Carbon::setTestNow($t0->copy()->addMinutes(20));   // la toman a los 20 min del primer mensaje
        ConversacionEvento::create(['conversacion_id' => $conv->id, 'tipo' => 'tomada', 'usuario_id' => $sup->id]);

        Carbon::setTestNow($t0->copy()->addMinutes(30));   // la resuelven 10 min después
        ConversacionEvento::create(['conversacion_id' => $conv->id, 'tipo' => 'resuelta', 'usuario_id' => $sup->id]);

        Carbon::setTestNow($t0->copy()->addHour());

        // /estadisticas/hoy (SLA) usa SQL de MySQL (CHAR_LENGTH) y no corre en sqlite.
        $fila = $this->actingAs($sup)->withSession(['colas' => ['atencion']])
            ->getJson('/admin/estadisticas/secretarias')->assertOk()->json('rows.0');
        $this->assertEquals(20 * 60, $fila['t_resp_medio_seg']);
        $this->assertEquals(10 * 60, $fila['t_resol_medio_seg']);
    }
}
