<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use App\Models\DifusionBaja;
use App\Models\DifusionCampania;
use App\Models\DifusionDestinatario;
use App\Models\User;
use App\Services\Difusion\CanalWwebjs;
use App\Services\Difusion\Difusiones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Módulo de difusiones con el proveedor simulado (QUEUE_CONNECTION=sync en
 * tests: la cadena de envío y los acuses corren en el acto).
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=Difusiones
 */
class DifusionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.bot_token' => 'token-de-prueba', 'difusion.canal_por_defecto' => 'simulado', 'difusion.canales_habilitados' => ['simulado', 'difusion', 'atencion'], 'difusion.activa' => true]);
        $this->admin = User::create([
            'name' => 'Sup', 'nombre_completo' => 'Supervisora Prueba', 'email' => 'sup@example.com',
            'password' => bcrypt('x'), 'rol' => 'supervisora', 'activo' => true,
        ]);
        // Tres pacientes que escribieron (uno por @lid) y uno que nunca escribió.
        foreach ([['5492235550001', 'Ana Pérez'], ['5492235550002', 'Bruno Díaz'], ['5492235550003', 'Carla Gómez'], ['5492235550004', 'Dora Nunca']] as [$tel, $nom]) {
            Contacto::create(['telefono' => $tel, 'nombre' => $nom, 'wa_id' => $tel === '5492235550003' ? '111222333@lid' : null]);
        }
        ConversacionWA::create(['contacto' => '5492235550001@c.us', 'area' => 'atencion', 'estado' => 'activa', 'ultima_actividad' => now()]);
        ConversacionWA::create(['contacto' => '5492235550002@c.us', 'area' => 'administracion', 'estado' => 'activa', 'ultima_actividad' => now()->subDays(100)]);
        ConversacionWA::create(['contacto' => '111222333@lid', 'area' => 'atencion', 'estado' => 'activa', 'ultima_actividad' => now()]);
    }

    private function sesion()
    {
        return $this->actingAs($this->admin)->withSession(['colas' => ['atencion']]);
    }

    public function test_audiencia_solo_quienes_escribieron_y_sin_bajas(): void
    {
        $a = Difusiones::armarAudiencia(['origen' => 'contactos', 'solo_con_conversacion' => true]);
        $this->assertEqualsCanonicalizing(['5492235550001', '5492235550002', '5492235550003'], array_column($a['destinatarios'], 'telefono'));

        DifusionBaja::create(['telefono' => '5492235550002', 'origen' => 'manual']);
        $a = Difusiones::armarAudiencia(['origen' => 'contactos', 'area' => 'atencion']);
        $this->assertEqualsCanonicalizing(['5492235550001', '5492235550003'], array_column($a['destinatarios'], 'telefono'));

        $a = Difusiones::armarAudiencia(['origen' => 'contactos', 'inactivos_dias' => 30]);
        $this->assertSame(0, count($a['destinatarios']));   // el único inactivo está de baja
        $this->assertSame(1, $a['descartados']['bajas']);
    }

    public function test_audiencia_desde_lista_normaliza_y_descarta(): void
    {
        $a = Difusiones::armarAudiencia(['origen' => 'lista', 'lista' => "223 555-0009, Eva\n2235550009\nbasura\n+54 9 223 555 0001"]);
        $this->assertSame(['5492235550009', '5492235550001'], array_column($a['destinatarios'], 'telefono'));
        $this->assertSame('Eva', $a['destinatarios'][0]['nombre']);
        $this->assertSame('Ana Pérez', $a['destinatarios'][1]['nombre']);   // tomado del contacto
        $this->assertSame(1, $a['descartados']['repetidos']);
        $this->assertSame(1, $a['descartados']['invalidos']);
    }

    public function test_campania_simulada_se_envia_entera_con_acuses(): void
    {
        $this->sesion()->post('/difusiones/campanias', [
            'nombre' => 'Prueba', 'texto' => 'Hola {nombre}, te escribimos de Crecer.', 'accion' => 'enviar', 'canal' => 'simulado',
            'audiencia_json' => json_encode(['origen' => 'contactos', 'solo_con_conversacion' => true]),
        ], ['Accept' => 'application/json'])->assertOk();

        $c = DifusionCampania::first();
        $this->assertSame('terminada', $c->estado);
        $this->assertSame(3, $c->destinatarios()->count());
        $m = DifusionCampania::metricas([$c->id])[$c->id];
        $this->assertSame(0, $m['pendientes']);
        $this->assertSame($m['enviados'], $m['entregados']);   // el simulado entrega todo lo enviado
        $this->assertSame('Hola Ana, te escribimos de Crecer.', $c->textoPara('Ana Pérez'));
        $this->assertSame('Hola, te escribimos de Crecer.', $c->textoPara(null));
    }

    public function test_pausar_corta_la_cadena_y_reanudar_la_retoma(): void
    {
        Queue::fake();
        $this->sesion()->post('/difusiones/campanias', [
            'nombre' => 'P', 'texto' => 'x', 'accion' => 'enviar', 'canal' => 'simulado',
            'audiencia_json' => json_encode(['origen' => 'contactos']),
        ], ['Accept' => 'application/json'])->assertOk();
        $c = DifusionCampania::first();
        $this->assertSame('enviando', $c->estado);

        $this->sesion()->postJson("/difusiones/campanias/{$c->id}/pausar")->assertOk();
        // El job que quedó en la cola ya no envía nada.
        (new \App\Jobs\DespacharDifusion($c->id, (string) cache(\App\Jobs\DespacharDifusion::claveToken($c->id))))->handle();
        $this->assertSame(0, DifusionDestinatario::whereNotNull('enviado_at')->count());

        $this->sesion()->postJson("/difusiones/campanias/{$c->id}/reanudar")->assertOk();
        $this->assertSame('enviando', $c->fresh()->estado);
        $this->sesion()->postJson("/difusiones/campanias/{$c->id}/reanudar")->assertStatus(422);
    }

    public function test_respuesta_y_baja_por_el_numero_de_difusiones(): void
    {
        // El entrante pide el avatar al bot del área: sin fake, espera el timeout de un host inexistente.
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'url' => null])]);
        $c = DifusionCampania::create(['nombre' => 'X', 'texto' => 'x', 'proveedor' => 'wwebjs', 'estado' => 'terminada']);
        DifusionDestinatario::create(['campania_id' => $c->id, 'telefono' => '5492235550003', 'estado' => 'leido',
            'enviado_at' => now()->subHour(), 'mensaje_id' => 'true_111222333@lid_ABC', 'chat_id' => '111222333@lid']);

        // Responde desde el @lid (no trae número): se reconoce por el chat.
        $this->withToken('token-de-prueba')->postJson('/api/bot/mensajes', [
            'contacto' => '111222333@lid', 'area' => 'difusion', 'tipo' => 'texto', 'contenido' => 'Baja por favor!',
            'timestamp' => now()->toISOString(),
        ])->assertCreated();

        $d = DifusionDestinatario::first();
        $this->assertNotNull($d->respondio_at);
        $this->assertNotNull($d->baja_at);
        $this->assertDatabaseHas('difusion_bajas', ['telefono' => '5492235550003', 'origen' => 'respuesta']);
        $this->assertDatabaseHas('mensajes_wa', ['direccion' => 'nota_interna']);
    }

    private function crear(array $extra)
    {
        return $this->sesion()->post('/difusiones/campanias', $extra + [
            'nombre' => 'C', 'texto' => 'Hola {nombre}',
            'audiencia_json' => json_encode(['origen' => 'contactos']),
        ], ['Accept' => 'application/json']);
    }

    public function test_el_canal_se_elige_por_campania(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::failedConnection()]);

        // Un canal que no está habilitado no se puede elegir.
        $this->crear(['accion' => 'borrador', 'canal' => 'meta'])->assertStatus(422);

        // Enviar ya por un número que no está conectado: no arranca.
        $r = $this->crear(['accion' => 'enviar', 'canal' => 'difusion'])->assertStatus(422);
        $this->assertStringContainsString('Número de difusiones no está listo', $r->json('error'));

        // Borrador sí; queda con su canal y se puede cambiar.
        $id = $this->crear(['accion' => 'borrador', 'canal' => 'difusion'])->assertOk()->json('id');
        $c = DifusionCampania::find($id);
        $this->assertSame('difusion', $c->canal);
        $this->assertSame('wwebjs', $c->proveedor);
        $this->sesion()->postJson("/difusiones/campanias/{$id}/iniciar")->assertStatus(422);   // sigue sin conexión
        $this->sesion()->postJson("/difusiones/campanias/{$id}/canal", ['canal' => 'simulado'])->assertOk();
        $this->sesion()->postJson("/difusiones/campanias/{$id}/iniciar")->assertOk();
        $this->assertSame('terminada', $c->fresh()->estado);   // simulado + cola sync

        $data = $this->sesion()->getJson('/difusiones/data')->assertOk();
        $this->assertSame(['simulado', 'difusion', 'atencion'], array_column($data->json('canales'), 'clave'));
        $this->assertNotNull($data->json('canales.2.riesgo'));   // el número de un área avisa del riesgo
    }

    public function test_respuesta_por_la_cola_de_un_area(): void
    {
        $conv = ConversacionWA::first();   // 5492235550001@c.us en atención
        $baja = fn() => $this->withToken('token-de-prueba')->postJson('/api/bot/mensajes', [
            'contacto' => '5492235550001@c.us', 'area' => 'atencion', 'tipo' => 'texto', 'contenido' => 'baja',
            'timestamp' => now()->toISOString(),
        ])->assertCreated();

        // Sin difusión reciente, un "baja" en atención no es un pedido de baja.
        $baja();
        $this->assertSame(0, DifusionBaja::count());

        $c = DifusionCampania::create(['nombre' => 'Por atención', 'texto' => 'x', 'proveedor' => 'wwebjs', 'canal' => 'atencion', 'estado' => 'terminada']);
        DifusionDestinatario::create(['campania_id' => $c->id, 'telefono' => '5492235550001', 'estado' => 'entregado',
            'enviado_at' => now()->subHour(), 'chat_id' => '5492235550001@c.us']);
        $this->withToken('token-de-prueba')->postJson('/api/bot/mensajes', [
            'contacto' => '5492235550001@c.us', 'area' => 'atencion', 'tipo' => 'texto', 'contenido' => '¿A qué hora es?',
            'timestamp' => now()->toISOString(),
        ])->assertCreated();
        $this->assertNotNull(DifusionDestinatario::first()->respondio_at);
        $this->assertDatabaseHas('mensajes_wa', ['conversacion_id' => $conv->id, 'direccion' => 'nota_interna', 'contenido' => '📣 Responde a la difusión «Por atención».']);

        $baja();
        $this->assertDatabaseHas('difusion_bajas', ['telefono' => '5492235550001']);
    }

    public function test_que_cuenta_como_pedido_de_baja(): void
    {
        foreach (['BAJA', 'baja.', 'No más', 'no mas!', 'Stop', 'Baja gracias'] as $t) {
            $this->assertTrue(Difusiones::esPedidoDeBaja($t), $t);
        }
        foreach (['No más dudas, gracias', 'quiero saber si hay baja de precios en los estudios', 'hola', '', null] as $t) {
            $this->assertFalse(Difusiones::esPedidoDeBaja($t), (string) $t);
        }
    }

    public function test_acuses_del_bot_no_retroceden(): void
    {
        $c = DifusionCampania::create(['nombre' => 'X', 'texto' => 'x', 'proveedor' => 'wwebjs', 'estado' => 'enviando']);
        $d = DifusionDestinatario::create(['campania_id' => $c->id, 'telefono' => '5492235550001', 'estado' => 'enviado',
            'enviado_at' => now(), 'mensaje_id' => 'true_5492235550001@c.us_XYZ']);
        $ack = fn($n) => $this->withToken('token-de-prueba')->postJson('/api/bot/difusion/ack', ['wa_id' => 'true_5492235550001@c.us_XYZ', 'ack' => $n])->assertOk();

        $ack(3);   // leído antes que entregado
        $ack(2);
        $d->refresh();
        $this->assertSame('leido', $d->estado);
        $this->assertNotNull($d->entregado_at);
        $this->assertSame('5492235550001@c.us', CanalWwebjs::chatDeWaId('true_5492235550001@c.us_XYZ'));
    }

    public function test_la_pantalla_carga(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['status' => 'listo', 'phone' => '5492230000000'])]);
        $this->sesion()->get('/v2/difusiones')->assertOk()->assertSee('Difusiones');
        $this->sesion()->getJson('/difusiones/data')->assertOk()
            ->assertJsonPath('canal_por_defecto', 'simulado')
            ->assertJsonPath('canales.1.ok', true);   // el bot de difusiones responde "listo"
    }
}
