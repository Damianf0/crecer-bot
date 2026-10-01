<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use App\Services\IdentidadWA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Identidad de WhatsApp de las conversaciones (29/09): teléfono real del @lid,
 * nombre de perfil, vinculación segura con el directorio y el aviso de
 * "WhatsApp a nombre de otra persona".
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=IdentidadWA
 */
class IdentidadWATest extends TestCase
{
    use RefreshDatabase;

    public function test_nombres_distintos_no_cuenta_apodos(): void
    {
        $this->assertFalse(ConversacionWA::nombresDistintos('Belén Pérez', 'Bel'));
        $this->assertFalse(ConversacionWA::nombresDistintos('María José Gómez', 'Majo Gómez 🌸'));
        $this->assertFalse(ConversacionWA::nombresDistintos('Ana López', null));
        $this->assertTrue(ConversacionWA::nombresDistintos('Carla Benítez', 'Diego Suárez'));
    }

    public function test_telefono_visible_nunca_es_el_codigo_lid(): void
    {
        $c = new ConversacionWA(['contacto' => '123456789012345@lid']);
        $this->assertSame('', $c->telefono);
        $this->assertSame('123456789012345@lid', $c->nombreOTelefono);   // último recurso

        $c->telefono_wa = '5492235550001';
        $c->nombre_wa = 'Ana';
        $this->assertSame('5492235550001', $c->telefono);
        $this->assertSame('Ana', $c->nombreOTelefono);
    }

    public function test_vincula_solo_con_una_ficha_sin_whatsapp(): void
    {
        $libre = Contacto::create(['telefono' => '5492235550001', 'nombre' => 'Ana López']);
        Contacto::create(['telefono' => '5492235550002', 'nombre' => 'Bruno', 'wa_id' => '999@lid']);

        $c1 = ConversacionWA::create(['contacto' => '111@lid', 'area' => 'atencion', 'estado' => 'activa']);
        $this->assertSame('vinculada', IdentidadWA::aplicar($c1, ['telefono' => '5492235550001', 'nombre' => 'Anita']));
        $this->assertSame('111@lid', $libre->fresh()->wa_id);
        $this->assertSame('Ana López', $c1->fresh()->nombre);
        $this->assertSame('Anita', $c1->fresh()->nombre_wa);

        // La ficha de Bruno ya tiene otro WhatsApp: no se toca (lo decide una persona).
        $c2 = ConversacionWA::create(['contacto' => '222@lid', 'area' => 'atencion', 'estado' => 'activa']);
        $this->assertNull(IdentidadWA::aplicar($c2, ['telefono' => '5492235550002', 'nombre' => 'Bruno']));
        $this->assertSame('999@lid', Contacto::where('telefono', '5492235550002')->value('wa_id'));
        $this->assertSame('5492235550002', $c2->fresh()->telefono_wa);   // igual queda buscable por número
    }

    public function test_el_nombre_de_agenda_de_la_clinica_gana_sobre_el_de_perfil(): void
    {
        // Caso real 29/09: ficha de una paciente con el celular del director cargado en Omnia.
        Http::fake(['*/contactos-info' => Http::response(['ok' => true, 'data' => [
            ['jid' => '444@lid', 'telefono' => '5492235550004', 'nombre' => 'Alfredo', 'agenda' => 'Dr Elena'],
        ]])]);
        Contacto::create(['telefono' => '5492235550004', 'nombre' => 'CANDELA ORFEI', 'wa_id' => '444@lid']);
        $c = ConversacionWA::create(['contacto' => '444@lid', 'area' => 'atencion', 'estado' => 'activa', 'nombre' => 'CANDELA ORFEI']);

        IdentidadWA::identificar($c);

        $this->assertSame('Dr Elena', $c->fresh()->nombre_wa);
        $this->assertTrue(ConversacionWA::nombresDistintos('CANDELA ORFEI', 'Dr Elena'));
    }

    public function test_desata_la_ficha_atada_al_whatsapp_de_otra_persona(): void
    {
        // Caso real 30/09: la ficha de una paciente tenía el celular del director.
        Http::fake(['*/contactos-info' => function ($req) {
            $data = [];
            foreach ($req['jids'] as $j) {
                $data[] = ['jid' => $j, 'telefono' => null, 'nombre' => null,
                           'agenda' => ['555@lid' => 'Dr Elena', '666@lid' => 'Bel'][$j] ?? null];
            }
            return Http::response(['ok' => true, 'data' => $data]);
        }]);
        $candela = Contacto::create(['telefono' => '5492235550005', 'nombre' => 'CANDELA ORFEI', 'wa_id' => '555@lid', 'avatar_path' => 'wa-avatars/x.jpg']);
        $belen   = Contacto::create(['telefono' => '5492235550006', 'nombre' => 'Belén Pérez', 'wa_id' => '666@lid']);   // apodo: no se toca
        $conv = ConversacionWA::create(['contacto' => '555@lid', 'area' => 'atencion', 'estado' => 'activa', 'nombre' => 'CANDELA ORFEI']);
        $doc = \App\Models\DocumentoPaciente::create(['contacto_id' => $candela->id, 'conversacion_id' => $conv->id, 'direccion' => 'entrante',
            'tipo' => 'audio', 'mime' => 'audio/ogg', 'nombre_original' => 'a.ogg', 'nombre_storage' => 'a.ogg', 'path' => 'x/a.ogg', 'tamanio_bytes' => 1]);

        $r = IdentidadWA::detectarAjenos();
        $this->assertCount(1, $r['casos']);
        $this->assertSame($candela->id, $r['casos'][0]['ficha']->id);

        $hecho = IdentidadWA::desatar($r['casos'][0]['ficha'], $r['casos'][0]['agendas']);
        $candela->refresh();
        $this->assertNull($candela->wa_id);
        $this->assertNull($candela->avatar_path);
        $this->assertSame('555@lid', $candela->wa_id_rechazado);
        $this->assertSame('Dr Elena', $conv->fresh()->nombre);
        $this->assertNull($doc->fresh()->contacto_id);                       // el audio del doctor sale del legajo de la paciente
        $this->assertSame([$doc->id], $hecho['documentos_desvinculados']);
        $this->assertSame('666@lid', $belen->fresh()->wa_id);

        // Nada la vuelve a atar: ni por teléfono (@c.us) ni la vinculación automática.
        $this->assertNull(Contacto::buscarPorContacto('5492235550005@c.us'));
        $otra = ConversacionWA::create(['contacto' => '777@lid', 'area' => 'atencion', 'estado' => 'activa']);
        $this->assertNull(IdentidadWA::aplicar($otra, ['telefono' => '5492235550005', 'nombre' => 'Dr Elena']));
        $this->assertNull($candela->fresh()->wa_id);
    }

    public function test_el_entrante_de_una_conversacion_nueva_la_identifica(): void
    {
        config(['app.bot_token' => 'token-de-prueba']);
        Queue::fake();
        Http::fake([
            '*/contactos-info' => Http::response(['ok' => true, 'data' => [['jid' => '333@lid', 'telefono' => '5492235550003', 'nombre' => 'Caro']]]),
            '*' => Http::response(['ok' => true, 'url' => null]),
        ]);
        Contacto::create(['telefono' => '5492235550003', 'nombre' => 'Carolina Díaz']);

        $this->withToken('token-de-prueba')->postJson('/api/bot/mensajes', [
            'contacto' => '333@lid', 'area' => 'atencion', 'tipo' => 'texto', 'contenido' => 'Hola',
            'timestamp' => now()->toISOString(),
        ])->assertCreated();

        $c = ConversacionWA::where('contacto', '333@lid')->first();
        $this->assertSame('5492235550003', $c->telefono_wa);
        $this->assertSame('Carolina Díaz', $c->nombre);
        $this->assertSame('333@lid', Contacto::where('telefono', '5492235550003')->value('wa_id'));
    }
}
