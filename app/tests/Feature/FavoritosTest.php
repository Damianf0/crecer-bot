<?php

namespace Tests\Feature;

use App\Models\ConversacionWA;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contactos favoritos (compartidos por el equipo, por JID): marcar/desmarcar,
 * destacados en la cola de cualquier área y su pestaña con abiertas y resueltas.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=Favoritos
 */
class FavoritosTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorito_compartido_y_en_todas_las_colas(): void
    {
        $u = User::create(['name' => 'S', 'nombre_completo' => 'Sec', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'secretaria', 'activo' => true]);
        $sesion = fn() => $this->actingAs($u)->withSession(['colas' => ['atencion', 'administracion']]);

        $enAtencion = ConversacionWA::create(['contacto' => '111@lid', 'area' => 'atencion', 'estado' => 'activa', 'no_leidos' => 2, 'nombre' => 'Laboratorio']);
        $enAdmin    = ConversacionWA::create(['contacto' => '111@lid', 'area' => 'administracion', 'estado' => 'archivada', 'nombre' => 'Laboratorio']);
        ConversacionWA::create(['contacto' => '222@lid', 'area' => 'atencion', 'estado' => 'activa', 'no_leidos' => 1, 'nombre' => 'Otra']);

        // Se marca desde la conversación de atención…
        $sesion()->postJson('/atencion/favorito', ['conv_id' => $enAtencion->id])->assertOk()->assertJsonPath('favorito', true);

        // …y queda destacado en la cola.
        $items = collect($sesion()->getJson('/atencion/atencion/items')->assertOk()->json('nuevas'));
        $this->assertTrue($items->firstWhere('id', $enAtencion->id)['favorito']);
        $this->assertFalse($items->firstWhere('contacto', 'Otra')['favorito']);

        // Vale para todas las colas: en administración aparece aunque esté resuelta.
        $favAdmin = $sesion()->getJson('/atencion/administracion/favoritos')->assertOk()->json('items');
        $this->assertSame([$enAdmin->id], array_column($favAdmin, 'id'));
        $this->assertSame('archivada', $favAdmin[0]['estado']);

        // Desmarcar.
        $sesion()->postJson('/atencion/favorito', ['conv_id' => $enAdmin->id])->assertOk()->assertJsonPath('favorito', false);
        $this->assertSame([], $sesion()->getJson('/atencion/atencion/favoritos')->json('items'));
    }
}
