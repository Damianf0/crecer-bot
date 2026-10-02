<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Historial (02/10): el buscador solo miraba el identificador interno de
 * WhatsApp (buscar por nombre daba 0) y cada fuente traía sus últimas 200, así
 * que sin filtro de fechas no se llegaba más allá de los últimos dos días.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=Historial
 */
class HistorialTest extends TestCase
{
    use RefreshDatabase;

    private User $u;

    protected function setUp(): void
    {
        parent::setUp();
        $this->u = User::create(['name' => 'S', 'nombre_completo' => 'Sup', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'supervisora', 'activo' => true]);
    }

    private function historial(array $params = [])
    {
        return $this->actingAs($this->u)->withSession(['colas' => ['atencion']])
            ->getJson('/v2/historial?' . http_build_query($params))->assertOk();
    }

    /** Conversación archivada; $hace = minutos de antigüedad (para ordenar). */
    private function conv(array $a, int $hace = 0): ConversacionWA
    {
        static $n = 0;
        $c = ConversacionWA::create($a + ['contacto' => (++$n) . '000111@lid', 'area' => 'atencion', 'estado' => 'archivada']);
        DB::table('conversaciones_wa')->where('id', $c->id)->update(['updated_at' => now()->subMinutes($hace)]);
        return $c;
    }

    public function test_busca_por_nombre_telefono_dni_y_resumen(): void
    {
        $porNombre  = $this->conv(['nombre' => 'María García']);
        $porNombreWa = $this->conv(['nombre_wa' => 'Mari G.']);
        $porTel     = $this->conv(['telefono_wa' => '5492235550009']);
        $porResumen = $this->conv(['resumen_llm' => 'Consulta por presupuesto de tratamiento']);
        $porFicha   = $this->conv(['contacto' => '777888@lid']);
        Contacto::create(['telefono' => '5492234440001', 'nombre' => 'Lucía Fernández', 'dni' => '30111222', 'wa_id' => '777888@lid']);
        $this->conv(['nombre' => 'Otra persona']);

        $ids = fn (array $p) => array_column($this->historial($p)->json('data'), 'id');

        $this->assertSame([$porNombre->id], $ids(['q' => 'garcía']));
        $this->assertSame([$porNombreWa->id], $ids(['q' => 'Mari G']));
        $this->assertSame([$porTel->id], $ids(['q' => '223 555-0009']));
        $this->assertSame([$porResumen->id], $ids(['q' => 'presupuesto']));
        $this->assertSame([$porFicha->id], $ids(['q' => 'Fernández']));
        $this->assertSame([$porFicha->id], $ids(['q' => '30111222']));
        $this->assertSame([], $ids(['q' => 'nadie con este nombre']));
        // Buscar vacío (el formulario manda q= ) no filtra nada.
        $this->assertSame(6, $this->historial(['q' => ''])->json('total'));
    }

    public function test_llega_a_todo_el_historial_paginando_sin_tope(): void
    {
        // 260 archivadas: antes el total quedaba clavado en 200.
        $filas = [];
        for ($i = 1; $i <= 260; $i++) {
            $filas[] = ['contacto' => "9{$i}@lid", 'area' => 'atencion', 'estado' => 'archivada', 'nombre' => "Paciente {$i}",
                        'created_at' => now(), 'updated_at' => now()->subMinutes($i)];
        }
        DB::table('conversaciones_wa')->insert($filas);

        $p1 = $this->historial();
        $this->assertSame(260, $p1->json('total'));
        $this->assertSame(6, $p1->json('pages'));
        $this->assertSame('Paciente 1', $p1->json('data.0.contacto'));
        $this->assertCount(50, $p1->json('data'));

        $p6 = $this->historial(['page' => 6]);
        $this->assertCount(10, $p6->json('data'));
        $this->assertSame('Paciente 260', $p6->json('data.9.contacto'));

        // La más vieja también se encuentra buscando, sin poner fechas.
        $this->assertSame(['Paciente 260'], array_column($this->historial(['q' => 'Paciente 260'])->json('data'), 'contacto'));
    }

    public function test_filtros_de_tipo_y_area_y_orden_entre_fuentes(): void
    {
        $vieja = $this->conv(['nombre' => 'Vieja', 'area' => 'ovodonacion'], 120);
        $nueva = $this->conv(['nombre' => 'Nueva'], 5);
        $tarea = Tarea::create(['titulo' => 'Llamar al laboratorio', 'descripcion' => 'pedir resultado', 'estado' => 'completada',
            'prioridad' => 'normal', 'creada_por' => $this->u->id]);
        DB::table('tareas')->where('id', $tarea->id)->update(['updated_at' => now()->subMinutes(30)]);

        $tipos = fn (array $p) => array_map(fn ($i) => $i['tipo'] . ':' . $i['id'], $this->historial($p)->json('data'));

        // Todo junto, de lo más nuevo a lo más viejo, mezclando fuentes.
        $this->assertSame(["wa:{$nueva->id}", "tarea:{$tarea->id}", "wa:{$vieja->id}"], $tipos([]));
        $this->assertSame(["tarea:{$tarea->id}"], $tipos(['tipo' => 'tarea']));
        $this->assertSame(["wa:{$nueva->id}", "wa:{$vieja->id}"], $tipos(['tipo' => 'wa']));
        $this->assertSame(["wa:{$vieja->id}"], $tipos(['area' => 'ovodonacion']));
        $this->assertSame(["tarea:{$tarea->id}"], $tipos(['q' => 'laboratorio']));
        $this->assertSame(["tarea:{$tarea->id}"], $tipos(['q' => 'resultado']));
        // El formulario manda todos los campos, también los vacíos.
        $this->assertCount(3, $tipos(['desde' => '', 'hasta' => '', 'tipo' => 'todos', 'area' => 'todas', 'q' => '']));
        $this->assertSame([], $tipos(['desde' => now()->addDay()->toDateString()]));
    }
}
