<?php

namespace Tests\Feature;

use App\Models\ConversacionWA;
use App\Models\MensajeWA;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Área restringida (Criopreservación, 06/10): funciona como cualquier cola,
 * pero solo la ve y la atiende quien tiene el permiso del área. Para el resto
 * no existe: ni menú, ni cola, ni conversaciones, ni contadores, ni bots.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=AreaRestringida
 */
class AreaRestringidaTest extends TestCase
{
    use RefreshDatabase;

    private User $conAcceso;
    private User $sinAcceso;
    private ConversacionWA $conv;

    protected function setUp(): void
    {
        // Antes de arrancar la app: las rutas /atencion/{area} fijan las áreas válidas al registrarse.
        putenv('CRIOPRESERVACION_ACTIVA=true');
        $_ENV['CRIOPRESERVACION_ACTIVA'] = $_SERVER['CRIOPRESERVACION_ACTIVA'] = 'true';
        parent::setUp();
        Http::fake([
            '*/enviar' => Http::response(['ok' => true, 'wa_id' => 'true_x_1']),
            '*'        => Http::response(['status' => 'esperando_qr', 'has_qr' => true]),
        ]);
        Queue::fake();
        config(['lineas.restringidas.criopreservacion.activa' => true, 'app.bot_token' => 'tok-prueba']);
        $this->conAcceso = User::create(['name' => 'C', 'nombre_completo' => 'Con Acceso', 'email' => 'c@example.com',
            'password' => bcrypt('x'), 'rol' => 'secretaria', 'activo' => true,
            'permisos' => ['secretaria', 'atencion', 'contactos', 'historial', 'area_criopreservacion']]);
        $this->sinAcceso = User::create(['name' => 'S', 'nombre_completo' => 'Sin Acceso', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'supervisora', 'activo' => true]);
        $this->conv = ConversacionWA::create(['contacto' => '777@lid', 'area' => 'criopreservacion', 'estado' => 'activa',
            'nombre' => 'Paciente Crio', 'no_leidos' => 2]);
    }

    protected function tearDown(): void
    {
        putenv('CRIOPRESERVACION_ACTIVA');
        unset($_ENV['CRIOPRESERVACION_ACTIVA'], $_SERVER['CRIOPRESERVACION_ACTIVA']);
        parent::tearDown();
    }

    private function como(User $u)
    {
        return $this->actingAs($u)->withSession(['colas' => ['atencion']]);
    }

    public function test_apagada_el_area_no_existe(): void
    {
        config(['lineas.restringidas.criopreservacion.activa' => false]);

        $this->assertArrayNotHasKey('criopreservacion', ConversacionWA::areas());
        $this->withToken('tok-prueba')->postJson('/api/bot/mensajes', [
            'contacto' => '1@lid', 'area' => 'criopreservacion', 'tipo' => 'texto', 'contenido' => 'hola',
            'wa_id' => 'false_1@lid_A', 'timestamp' => now()->toISOString(),
        ])->assertStatus(422);
    }

    public function test_el_bot_guarda_como_en_cualquier_cola(): void
    {
        $this->withToken('tok-prueba')->postJson('/api/bot/mensajes', [
            'contacto' => '777@lid', 'area' => 'criopreservacion', 'tipo' => 'texto', 'contenido' => 'Hola, consulto por mis muestras',
            'wa_id' => 'false_777@lid_B', 'timestamp' => now()->toISOString(),
        ])->assertStatus(201);

        $this->assertSame(3, $this->conv->fresh()->no_leidos);
        $this->assertSame('http://bot-criopreservacion:3005', $this->conv->botUrl());
    }

    public function test_quien_tiene_el_permiso_la_ve_y_la_atiende(): void
    {
        $this->como($this->conAcceso)->get('/v2/atencion/criopreservacion')->assertOk()->assertSee('Criopreservación');
        $this->como($this->conAcceso)->getJson('/atencion/criopreservacion/items')->assertOk();
        $this->como($this->conAcceso)->getJson("/atencion/conversacion/{$this->conv->id}")->assertOk();
        $this->como($this->conAcceso)->postJson('/atencion/tomar', ['id' => $this->conv->id, 'tipo' => 'wa'])->assertOk();
        $this->como($this->conAcceso)->postJson('/atencion/enviar', ['conv_id' => $this->conv->id, 'texto' => 'Hola', 'modo' => 'mensaje'])->assertOk();

        $this->assertSame($this->conAcceso->id, (int) $this->conv->fresh()->asignada_a);
        $this->assertSame(1, MensajeWA::where('conversacion_id', $this->conv->id)->where('direccion', 'saliente')->count());
        $r = $this->como($this->conAcceso)->getJson('/bot-pulso')->assertOk();
        $this->assertArrayHasKey('criopreservacion', $r->json('bots'));
    }

    public function test_para_el_resto_no_existe(): void
    {
        $yo = fn () => $this->como($this->sinAcceso);

        $yo()->get('/v2/atencion/criopreservacion')->assertStatus(403);
        $yo()->getJson('/atencion/criopreservacion/items')->assertStatus(403);
        $yo()->getJson("/atencion/conversacion/{$this->conv->id}")->assertStatus(403);
        $yo()->postJson('/atencion/tomar', ['id' => $this->conv->id, 'tipo' => 'wa'])->assertStatus(403);
        $yo()->postJson('/atencion/enviar', ['conv_id' => $this->conv->id, 'texto' => 'Hola', 'modo' => 'mensaje'])->assertStatus(403);
        $yo()->postJson('/atencion/resolver', ['id' => $this->conv->id, 'tipo' => 'wa'])->assertStatus(403);
        $yo()->postJson('/atencion/iniciar', ['telefono' => '2235550001', 'texto' => 'Hola', 'area' => 'criopreservacion'])->assertStatus(403);

        $this->assertNull($this->conv->fresh()->asignada_a);
        Http::assertNotSent(fn ($req) => str_contains($req->url(), '/enviar'));

        // Ni menú, ni contadores, ni el estado de ese bot.
        $yo()->get('/v2/mis-conversaciones')->assertOk()->assertDontSee('/v2/atencion/criopreservacion');
        $r = $yo()->getJson('/bot-pulso')->assertOk();
        $this->assertArrayNotHasKey('criopreservacion', $r->json('bots'));
        $this->assertArrayNotHasKey('criopreservacion', (array) $r->json('contadores.por_area'));
        $this->assertNotSame('sin_respuesta', $r->json('estado'));

        // Las otras colas siguen andando igual.
        $yo()->get('/v2/atencion/atencion')->assertOk();
    }

    public function test_el_historial_las_muestra_solo_a_quien_tiene_el_permiso(): void
    {
        $this->conv->update(['estado' => 'archivada']);

        $this->como($this->conAcceso)->get('/v2/historial?tipo=wa')->assertOk()->assertSee('Paciente Crio');
        $this->como($this->sinAcceso)->get('/v2/historial?tipo=wa')->assertOk()->assertDontSee('Paciente Crio');
    }

    public function test_no_se_puede_delegar_a_quien_no_tiene_acceso(): void
    {
        $this->como($this->conAcceso)->postJson('/atencion/delegar', ['id' => $this->conv->id, 'tipo' => 'wa', 'user_id' => $this->sinAcceso->id])
            ->assertStatus(422);

        $this->assertNull($this->conv->fresh()->asignada_a);
    }
}
