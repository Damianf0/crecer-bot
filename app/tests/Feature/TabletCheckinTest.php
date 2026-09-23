<?php

namespace Tests\Feature;

use App\Livewire\Tablet;
use App\Models\ColaAtencion;
use App\Services\OmniaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Check-in con turno: el tablet no espera a Omnia.
 *
 * El financiador DEL TURNO (Omnia, reporte ambulatorio) se consulta después
 * de responderle al paciente y corrige la fila de la cola si difiere de la
 * ficha. Antes esa consulta iba adentro del "Confirmar" (hasta ~17 s).
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=TabletCheckin
 */
class TabletCheckinTest extends TestCase
{
    use RefreshDatabase;

    private function confirmar(?array $delTurno): ColaAtencion
    {
        $omnia = Mockery::mock(OmniaService::class);
        $omnia->shouldReceive('financiadorDelTurno')->with(555)->andReturn($delTurno);
        $this->app->instance(OmniaService::class, $omnia);

        $this->withoutDefer();   // los defer() corren en el acto, como al final del request
        Livewire::test(Tablet::class)
            ->set('dni', '30111222')
            ->set('paciente', ['id' => 1, 'nombre' => 'Ana', 'apellido' => 'Paz', 'obra_social' => 'OSDE',
                               'financiador' => 'Osde Binario', 'plan' => '210', 'primera_vez' => false])
            ->set('turnos', [['id' => 555, 'hora' => '10:00', 'profesional' => 'Dra. X',
                              'practica' => 'Consulta', 'practicas' => ['Consulta'], 'planta' => 'alta']])
            ->call('confirmarLlegada')
            ->assertSet('paso', 'confirmado');

        return ColaAtencion::sole();
    }

    public function test_corrige_con_el_financiador_del_turno(): void
    {
        $fila = $this->confirmar(['financiador' => 'Particular', 'plan' => null]);

        $this->assertSame('Particular', $fila->financiador);
        $this->assertSame('210', $fila->plan);            // Omnia no trajo plan: queda el de la ficha
        $this->assertSame(555, (int) $fila->omnia_turno_id);
    }

    public function test_sin_respuesta_de_omnia_queda_la_ficha(): void
    {
        $fila = $this->confirmar(null);

        $this->assertSame('Osde Binario', $fila->financiador);
        $this->assertNotEmpty($fila->checklist);
    }
}
