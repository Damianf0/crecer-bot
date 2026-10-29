<?php

namespace Tests\Feature;

use App\Models\RecepcionChecklist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consulta de "qué tiene que traer" por obra social dentro de Procedimientos
 * (29/10): la ve todo el equipo, aunque editar las listas siga siendo de
 * supervisión. Son las mismas listas que usa recepción.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=ObrasSocialesConsulta
 */
class ObrasSocialesConsultaTest extends TestCase
{
    use RefreshDatabase;

    private function lista(?string $nombre, array $pasos, bool $activo = true): RecepcionChecklist
    {
        // La General ya la crea una migración.
        $c = RecepcionChecklist::updateOrCreate(['clave' => RecepcionChecklist::clave($nombre)], ['financiador' => $nombre, 'activo' => $activo]);
        $c->items()->delete();
        foreach ($pasos as $i => $p) {
            $c->items()->create(['texto' => $p, 'obligatorio' => $i === 0, 'nota' => $i === 0 ? 'original' : null, 'orden' => $i + 1]);
        }
        return $c;
    }

    public function test_cualquiera_del_equipo_ve_las_listas_activas_con_pasos(): void
    {
        $this->lista('Medicus', ['Credencial', 'Orden médica']);
        $this->lista(null, ['DNI']);                          // General
        $this->lista('Inactiva', ['Algo'], false);
        $this->lista('Sin pasos', []);

        $u = User::create(['name' => 'r', 'nombre_completo' => 'Recep', 'email' => 'r@example.com',
            'password' => bcrypt('x'), 'rol' => 'secretaria', 'activo' => true]);
        $this->assertFalse($u->hasPermiso('admin'));

        $r = $this->actingAs($u)->withSession(['colas' => ['atencion']])->getJson('/procedimientos/obras-sociales')->assertOk();
        $this->assertSame(['General', 'Medicus'], array_column($r->json('data'), 'nombre'));
        $this->assertTrue($r->json('data.0.general'));
        $this->assertSame('Credencial', $r->json('data.1.items.0.texto'));
        $this->assertTrue($r->json('data.1.items.0.obligatorio'));
        $this->assertSame('original', $r->json('data.1.items.0.nota'));
        $this->assertFalse($r->json('puede_editar'));
    }

    public function test_sin_sesion_no_se_ve(): void
    {
        $this->getJson('/procedimientos/obras-sociales')->assertRedirect('/login');
    }
}
