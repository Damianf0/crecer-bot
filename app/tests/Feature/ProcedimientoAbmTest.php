<?php

namespace Tests\Feature;

use App\Models\Procedimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ABM de procedimientos: eliminar, despublicar y recuperar.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=ProcedimientoAbm
 */
class ProcedimientoAbmTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol): User
    {
        return User::create(['name' => $rol, 'nombre_completo' => ucfirst($rol), 'email' => "{$rol}@example.com",
            'password' => bcrypt('x'), 'rol' => $rol, 'activo' => true]);
    }

    /** Sesión con colas declaradas: sin eso SecretariaAuth redirige a /declarar-colas. */
    private function como(User $u)
    {
        return $this->actingAs($u)->withSession(['colas' => ['atencion']]);
    }

    /** Procedimiento publicado y con contenido (un paso). */
    private function proc(array $a = []): Procedimiento
    {
        $p = Procedimiento::create($a + ['titulo' => 'Viejo', 'slug' => 'viejo-' . uniqid(), 'area' => 'general', 'estado' => 'publicado']);
        $p->pasos()->create(['orden' => 1, 'contenido' => '<p>Paso</p>', 'contenido_texto' => 'Paso']);
        return $p;
    }

    private function ids($resp): array
    {
        return array_column($resp->json('data'), 'id');
    }

    public function test_supervision_elimina_y_restaura_como_borrador(): void
    {
        $p = $this->proc();
        $sup = $this->usuario('supervisora');

        $this->como($sup)->deleteJson("/procedimientos/{$p->id}")->assertOk()->assertJson(['definitivo' => false]);

        // Sale del listado y del detalle, y aparece en la papelera.
        $this->assertSoftDeleted('procedimientos', ['id' => $p->id]);
        $lista = $this->como($sup)->getJson('/procedimientos/data');
        $this->assertSame([], $this->ids($lista));
        $this->assertSame(1, $lista->json('eliminados_count'));
        $this->como($sup)->getJson("/procedimientos/{$p->id}")->assertNotFound();
        $this->assertSame([$p->id], $this->ids($this->como($sup)->getJson('/procedimientos/data?eliminados=1')));

        // Vuelve como borrador: el equipo no lo ve hasta que alguien lo publique.
        $this->como($sup)->postJson("/procedimientos/{$p->id}/restaurar")->assertOk();
        $this->assertSame('borrador', $p->fresh()->estado);
        $this->assertNull($p->fresh()->deleted_at);
    }

    public function test_retirar_lo_saca_de_la_vista_del_equipo(): void
    {
        $p = $this->proc();
        $sup = $this->usuario('supervisora');
        $sec = $this->usuario('secretaria');

        $this->assertSame([$p->id], $this->ids($this->como($sec)->getJson('/procedimientos/data')));

        $this->como($sup)->postJson("/procedimientos/{$p->id}/estado", ['estado' => 'borrador'])->assertOk();
        $this->assertSame([], $this->ids($this->como($sec)->getJson('/procedimientos/data')));
        $this->como($sec)->getJson("/procedimientos/{$p->id}")->assertNotFound();
        // Supervisión lo sigue viendo para corregirlo.
        $this->assertSame([$p->id], $this->ids($this->como($sup)->getJson('/procedimientos/data')));

        $this->como($sup)->postJson("/procedimientos/{$p->id}/estado", ['estado' => 'publicado'])->assertOk();
        $this->assertSame([$p->id], $this->ids($this->como($sec)->getJson('/procedimientos/data')));
    }

    public function test_borrador_vacio_se_borra_del_todo_y_el_slug_no_choca(): void
    {
        $sup = $this->usuario('supervisora');

        // "+ Nuevo" y salir sin escribir: no deja nada, ni en la papelera.
        $id = $this->como($sup)->postJson('/procedimientos')->assertCreated()->json('id');
        $this->como($sup)->deleteJson("/procedimientos/{$id}")->assertOk()->assertJson(['definitivo' => true]);
        $this->assertDatabaseMissing('procedimientos', ['id' => $id]);

        // Uno eliminado con contenido conserva su slug: el siguiente "+ Nuevo" no choca con él.
        $id = $this->como($sup)->postJson('/procedimientos')->json('id');
        Procedimiento::find($id)->pasos()->create(['orden' => 1, 'contenido' => '<p>x</p>', 'contenido_texto' => 'x']);
        $this->como($sup)->deleteJson("/procedimientos/{$id}")->assertOk();
        $this->como($sup)->postJson('/procedimientos')->assertCreated();
    }

    public function test_secretaria_no_puede_eliminar_retirar_ni_ver_la_papelera(): void
    {
        $p = $this->proc();
        $borrado = $this->proc();
        $borrado->delete();
        $sec = $this->usuario('secretaria');

        $this->como($sec)->deleteJson("/procedimientos/{$p->id}")->assertForbidden();
        $this->como($sec)->postJson("/procedimientos/{$p->id}/estado", ['estado' => 'borrador'])->assertForbidden();
        $this->como($sec)->postJson("/procedimientos/{$borrado->id}/restaurar")->assertForbidden();
        $this->assertNotSoftDeleted('procedimientos', ['id' => $p->id]);

        $lista = $this->como($sec)->getJson('/procedimientos/data?eliminados=1');
        $this->assertSame([$p->id], $this->ids($lista));
        $this->assertSame(0, $lista->json('eliminados_count'));
    }
}
