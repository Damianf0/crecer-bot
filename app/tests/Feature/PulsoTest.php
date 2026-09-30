<?php

namespace Tests\Feature;

use App\Models\ConversacionWA;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * /bot-pulso: contadores del menú (antes congelados desde que se cargaba la
 * página) y estado de TODOS los bots (antes solo el de atención).
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=Pulso
 */
class PulsoTest extends TestCase
{
    use RefreshDatabase;

    public function test_devuelve_contadores_y_el_peor_estado_de_los_bots(): void
    {
        Http::fake([
            'bot-administracion:3002/*' => Http::failedConnection(),
            '*' => Http::response(['status' => 'listo', 'has_qr' => false]),
        ]);
        $u = User::create(['name' => 'S', 'nombre_completo' => 'Sec', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'secretaria', 'activo' => true]);
        ConversacionWA::create(['contacto' => 'a@c.us', 'area' => 'atencion', 'estado' => 'activa', 'no_leidos' => 2]);
        ConversacionWA::create(['contacto' => 'b@c.us', 'area' => 'atencion', 'estado' => 'activa', 'no_leidos' => 1]);
        ConversacionWA::create(['contacto' => 'c@c.us', 'area' => 'ovodonacion', 'estado' => 'activa', 'no_leidos' => 0]);
        ConversacionWA::create(['contacto' => 'd@c.us', 'area' => 'atencion', 'estado' => 'activa', 'no_leidos' => 3, 'asignada_a' => $u->id]);

        $r = $this->actingAs($u)->withSession(['colas' => ['atencion']])->getJson('/bot-pulso')->assertOk();

        $this->assertSame(['atencion' => 2], $r->json('contadores.por_area'));   // sin tomar y con no leídos
        $this->assertSame(1, $r->json('contadores.mis_conv'));
        $this->assertSame('sin_respuesta', $r->json('estado'));                  // administración caído
        $this->assertSame('listo', $r->json('bots.atencion.estado'));
        $this->assertSame('sin_respuesta', $r->json('bots.administracion.estado'));
    }
}
