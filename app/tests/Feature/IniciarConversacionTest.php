<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Iniciar una conversación eligiendo desde qué número sale (30/09: desde
 * Contactos salía siempre por Atención).
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=IniciarConversacion
 */
class IniciarConversacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_por_el_numero_elegido_y_queda_en_esa_cola(): void
    {
        Http::fake([
            '*/status'       => Http::response(['status' => 'listo']),
            '*/check-numero' => Http::response(['ok' => true, 'registered' => true, 'normalizedId' => '5492235550001@c.us']),
            '*/enviar'       => Http::response(['ok' => true, 'wa_id' => 'true_5492235550001@c.us_ABC']),
            '*'              => Http::response(['ok' => true]),
        ]);
        $u = User::create(['name' => 'S', 'nombre_completo' => 'Sec', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'secretaria', 'activo' => true]);
        $c = Contacto::create(['telefono' => '5492235550001', 'nombre' => 'Ana López']);

        $r = $this->actingAs($u)->withSession(['colas' => ['atencion', 'administracion']])
            ->postJson('/atencion/iniciar', ['contacto_id' => $c->id, 'texto' => 'Hola Ana', 'area' => 'administracion'])
            ->assertOk();

        $conv = ConversacionWA::find($r->json('conv_id'));
        $this->assertSame('administracion', $conv->area);
        Http::assertSent(fn ($req) => $req->url() === 'http://bot-administracion:3002/enviar');
        Http::assertNotSent(fn ($req) => str_starts_with($req->url(), 'http://bot:3001/enviar'));
    }
}
