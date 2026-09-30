<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\ConversacionEvento;
use App\Models\ConversacionWA;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pestaña "Resueltas" de la cola (/atencion/{area}/resueltas): consultar lo ya
 * cerrado sin ir al Historial.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=Resueltas
 */
class ResueltasTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_las_resueltas_del_area_con_busqueda_y_quien_la_cerro(): void
    {
        $u = User::create(['name' => 'S', 'nombre_completo' => 'Laura Gómez', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'secretaria', 'activo' => true]);
        Contacto::create(['telefono' => '5492235550001', 'nombre' => 'Ana López', 'wa_id' => '111@lid']);

        $ana   = ConversacionWA::create(['contacto' => '111@lid', 'area' => 'administracion', 'estado' => 'archivada', 'ultima_actividad' => now()->subDay()]);
        $bruno = ConversacionWA::create(['contacto' => '5492235550002@c.us', 'area' => 'administracion', 'estado' => 'archivada', 'nombre' => 'Bruno Díaz', 'ultima_actividad' => now()]);
        ConversacionWA::create(['contacto' => '333@lid', 'area' => 'administracion', 'estado' => 'activa', 'nombre' => 'Activa']);
        ConversacionWA::create(['contacto' => '444@lid', 'area' => 'atencion', 'estado' => 'archivada', 'nombre' => 'Otra área']);
        ConversacionEvento::create(['conversacion_id' => $ana->id, 'tipo' => 'resuelta', 'usuario_id' => $u->id]);

        $get = fn($q = '') => $this->actingAs($u)->withSession(['colas' => ['administracion']])
            ->getJson('/atencion/administracion/resueltas' . ($q ? '?q=' . urlencode($q) : ''))->assertOk();

        $todas = $get();
        $this->assertSame([$bruno->id, $ana->id], array_column($todas->json('items'), 'id'));   // más recientes primero, solo el área

        $porFicha = $get('ana');   // la conversación no tiene nombre propio: se encuentra por la ficha
        $this->assertSame([$ana->id], array_column($porFicha->json('items'), 'id'));
        $this->assertSame('Laura Gómez', $porFicha->json('items.0.cerrada_por'));

        $this->assertSame([$bruno->id], array_column($get('223 555-0002')->json('items'), 'id'));   // por número, con espacios
    }
}
