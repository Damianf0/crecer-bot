<?php

namespace Tests\Feature;

use App\Models\ColaAtencion;
use App\Models\User;
use App\Services\OmniaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Atención en mostrador (01/10): pacientes que no se anotan en el tablet pero se
 * atienden en el mostrador. Antes no quedaba registro (2 llegadas en 30 días).
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=RecepcionMostrador
 */
class RecepcionMostradorTest extends TestCase
{
    use RefreshDatabase;

    private User $u;

    protected function setUp(): void
    {
        parent::setUp();
        $omnia = Mockery::mock(OmniaService::class);
        $omnia->shouldReceive('buscarPaciente')->with('30111222')->andReturn([
            'id' => 77, 'nombre' => 'Ana', 'apellido' => 'López', 'obra_social' => 'OSDE', 'plan' => '210', 'financiador' => 'OSDE Binario', 'primera_vez' => false]);
        $omnia->shouldReceive('buscarPaciente')->andReturn(null);
        $omnia->shouldReceive('turnosHoy')->with(77)->andReturn([
            ['id' => 'T9', 'hora' => '10:30', 'practica' => 'Ecografía', 'profesional' => 'Dra. X', 'planta' => 'baja', 'practicas' => ['Ecografía']]]);
        $omnia->shouldReceive('financiadorDelTurno')->andReturn(null);
        $this->app->instance(OmniaService::class, $omnia);

        $this->u = User::create(['name' => 'R', 'nombre_completo' => 'Recepción', 'email' => 'r@example.com',
            'password' => bcrypt('x'), 'rol' => 'secretaria', 'activo' => true]);
    }

    private function sesion() { return $this->actingAs($this->u)->withSession(['colas' => ['atencion']]); }

    public function test_busca_por_dni_en_omnia_con_turnos_de_hoy(): void
    {
        $r = $this->sesion()->getJson('/v2/recepcion/mostrador/buscar?dni=30.111.222')->assertOk();
        $this->assertSame('omnia', $r->json('origen'));
        $this->assertSame('Ana', $r->json('paciente.nombre'));
        $this->assertSame('T9', $r->json('turnos.0.id'));

        $this->assertNull($this->sesion()->getJson('/v2/recepcion/mostrador/buscar?dni=99888777')->json('paciente'));
    }

    public function test_atendido_en_mostrador_queda_registrado_y_resuelto(): void
    {
        $this->sesion()->postJson('/v2/recepcion/mostrador', [
            'accion' => 'atendido', 'dni' => '30111222', 'nombre' => 'Ana', 'apellido' => 'López',
            'obra_social' => 'OSDE', 'motivo' => 'recetas', 'nota' => 'Retiró la receta',
        ])->assertOk();

        $f = ColaAtencion::first();
        $this->assertSame('mostrador', $f->origen);
        $this->assertSame($this->u->id, $f->registrado_por);
        $this->assertSame('resuelto', $f->estado);
        $this->assertTrue((bool) $f->sin_turno);

        // No aparece en la sala, pero cuenta en el registro del día.
        $cola = $this->sesion()->getJson('/v2/recepcion/cola')->assertOk();
        $this->assertCount(0, $cola->json('cola'));
        $this->assertSame(1, $cola->json('stats.hoy_mostrador'));
    }

    public function test_en_la_clinica_vuelve_al_mostrador_y_reporte(): void
    {
        // Liberado al consultorio hace 20 min: está "en la clínica".
        $visita = ColaAtencion::create(['dni' => '30111222', 'nombre' => 'Ana', 'apellido' => 'López', 'motivo' => 'turno',
            'origen' => 'tablet', 'estado' => 'liberado', 'hora_llegada' => now()->subMinutes(40), 'hora_liberado' => now()->subMinutes(20), 'orden' => 1]);
        // Liberado hace 1 h: ya salió de la lista por tiempo (45 min).
        ColaAtencion::create(['dni' => '20111222', 'nombre' => 'Viejo', 'apellido' => 'Pérez', 'motivo' => 'turno', 'origen' => 'tablet', 'estado' => 'liberado',
            'hora_llegada' => now()->startOfDay()->addMinute(), 'hora_liberado' => now()->subHour(), 'orden' => 2]);

        $enClinica = $this->sesion()->getJson('/v2/recepcion/cola')->json('en_clinica');
        if (now()->subHour()->isToday()) $this->assertSame([$visita->id], array_column($enClinica, 'id'));

        // Vuelve al mostrador sin anotarse: visita nueva enlazada, la anterior sale de la lista.
        $this->sesion()->postJson('/v2/recepcion/mostrador', ['accion' => 'atendido', 'nombre' => 'Ana', 'apellido' => 'López',
            'motivo' => 'regreso', 'vuelve_de_id' => $visita->id])->assertOk();
        $this->assertNotNull($visita->fresh()->salio_at);
        $this->assertSame([], $this->sesion()->getJson('/v2/recepcion/cola')->json('en_clinica'));

        $rep = $this->sesion()->getJson('/admin/estadisticas/recepcion');
        $rep->assertStatus(403);   // reportes es de supervisión
        $sup = User::create(['name' => 'S', 'nombre_completo' => 'Sup', 'email' => 'sup@example.com',
            'password' => bcrypt('x'), 'rol' => 'supervisora', 'activo' => true]);
        $r = $this->actingAs($sup)->withSession(['colas' => ['atencion']])->getJson('/admin/estadisticas/recepcion')->assertOk();
        $this->assertSame(1, $r->json('totales.mostrador'));
        $this->assertSame(1, $r->json('totales.regresos'));
        $this->assertSame('Recepción', $r->json('personas.0.nombre'));
    }

    public function test_sin_dni_tambien_se_registra(): void
    {
        // Paciente que no trae DNI: se carga solo el nombre (la columna dni es NOT NULL).
        $this->sesion()->postJson('/v2/recepcion/mostrador', ['accion' => 'atendido', 'nombre' => 'Señora sin DNI', 'motivo' => 'consulta'])->assertOk();
        $this->assertSame('', ColaAtencion::first()->dni);
    }

    public function test_pasa_a_la_sala_con_su_turno(): void
    {
        $this->sesion()->postJson('/v2/recepcion/mostrador', [
            'accion' => 'sala', 'dni' => '30111222', 'nombre' => 'Ana', 'apellido' => 'López', 'motivo' => 'turno',
            'turno' => ['id' => 'T9', 'hora' => '10:30', 'practica' => 'Ecografía', 'profesional' => 'Dra. X', 'planta' => 'baja', 'practicas' => ['Ecografía']],
        ])->assertOk();

        $cola = $this->sesion()->getJson('/v2/recepcion/cola')->json('cola');
        $this->assertCount(1, $cola);
        $this->assertSame('esperando', $cola[0]['estado']);
        $this->assertSame('Ecografía', $cola[0]['practica']);
        $this->assertContains('Mostrador', array_column($cola[0]['flags'], 'label'));
    }
}
