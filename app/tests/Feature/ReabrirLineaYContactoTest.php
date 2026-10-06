<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use App\Models\MensajeWA;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Reabrir eligiendo la línea y compartir un contacto como tarjeta (06/10).
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=ReabrirLineaYContacto
 */
class ReabrirLineaYContactoTest extends TestCase
{
    use RefreshDatabase;

    private User $u;
    private ConversacionWA $conv;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            '*/check-numero' => Http::response(['ok' => true, 'registered' => true, 'normalizedId' => '5492235550001@c.us']),
            '*/enviar'       => Http::response(['ok' => true, 'wa_id' => 'true_x_TARJETA']),
            '*'              => Http::response(['ok' => true]),
        ]);
        $this->u = User::create(['name' => 'S', 'nombre_completo' => 'Sec', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'secretaria', 'activo' => true]);
        $this->conv = ConversacionWA::create(['contacto' => '111@lid', 'area' => 'atencion', 'estado' => 'archivada',
            'nombre' => 'Paciente', 'telefono_wa' => '5492235550001']);
    }

    private function reabrir(array $extra = [])
    {
        return $this->actingAs($this->u)->withSession(['colas' => ['administracion']])
            ->postJson('/atencion/reabrir', ['id' => $this->conv->id, 'tipo' => 'wa'] + $extra);
    }

    public function test_sin_elegir_linea_vuelve_a_la_cola_donde_estaba(): void
    {
        $this->reabrir()->assertOk()->assertJson(['ok' => true, 'area' => 'atencion']);

        $this->assertSame('activa', $this->conv->fresh()->estado);
        $this->assertSame(1, ConversacionWA::count());
    }

    public function test_por_otra_linea_abre_la_conversacion_ahi_y_deja_la_original_archivada(): void
    {
        $r = $this->reabrir(['area' => 'administracion'])->assertOk()->assertJson(['ok' => true, 'otra_linea' => true, 'area' => 'administracion']);

        $nueva = ConversacionWA::findOrFail($r->json('conv_id'));
        $this->assertSame('111@lid', $nueva->contacto);
        $this->assertSame('administracion', $nueva->area);
        $this->assertSame('activa', $nueva->estado);
        $this->assertSame($this->u->id, (int) $nueva->asignada_a);
        $this->assertSame('Paciente', $nueva->nombre);
        $this->assertSame('archivada', $this->conv->fresh()->estado);
        $this->assertSame(1, MensajeWA::where('conversacion_id', $nueva->id)->where('direccion', 'nota_interna')->count());
        Http::assertSent(fn ($req) => str_contains($req->url(), '/check-numero') && $req['numero'] === '5492235550001');
    }

    public function test_por_otra_linea_reusa_la_conversacion_que_ya_existia(): void
    {
        $otra = ConversacionWA::create(['contacto' => '111@lid', 'area' => 'administracion', 'estado' => 'archivada', 'nombre' => 'Paciente']);

        $this->reabrir(['area' => 'administracion'])->assertOk()->assertJson(['conv_id' => $otra->id]);

        $this->assertSame('activa', $otra->fresh()->estado);
        $this->assertSame(2, ConversacionWA::count());
        Http::assertNotSent(fn ($req) => str_contains($req->url(), '/check-numero'));
    }

    public function test_no_reabre_si_la_otra_linea_no_encuentra_el_numero(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*/check-numero' => Http::response(['ok' => true, 'registered' => false])]);

        $this->reabrir(['area' => 'ovodonacion'])->assertStatus(422);

        $this->assertSame(1, ConversacionWA::count());
        $this->assertSame('archivada', $this->conv->fresh()->estado);
    }

    public function test_compartir_contacto_manda_una_tarjeta_y_lo_registra(): void
    {
        $this->conv->update(['estado' => 'activa']);
        $c = Contacto::create(['telefono' => '5492235550009', 'nombre' => 'Dra. Destino; Clínica']);

        $this->actingAs($this->u)->withSession(['colas' => ['atencion']])
            ->postJson('/atencion/enviar-contacto', ['conv_id' => $this->conv->id, 'contacto_id' => $c->id])
            ->assertOk()->assertJson(['ok' => true]);

        Http::assertSent(function ($req) {
            if (!str_ends_with($req->url(), '/enviar')) return false;
            return $req['contacto'] === '111@lid'
                && str_starts_with($req['texto'], "BEGIN:VCARD\n")
                && str_contains($req['texto'], 'FN:Dra. Destino\\; Clínica')
                && str_contains($req['texto'], 'waid=5492235550009:+5492235550009')
                && str_ends_with($req['texto'], 'END:VCARD');
        });
        $m = MensajeWA::where('conversacion_id', $this->conv->id)->where('direccion', 'saliente')->firstOrFail();
        $this->assertSame('true_x_TARJETA', $m->wa_id);
        $this->assertStringContainsString('Dra. Destino; Clínica', $m->contenido);
        $this->assertStringContainsString('+5492235550009', $m->contenido);
    }

    public function test_compartir_contacto_sin_telefono_valido_no_manda_nada(): void
    {
        $c = Contacto::create(['telefono' => '123', 'nombre' => 'Sin número']);

        $this->actingAs($this->u)->withSession(['colas' => ['atencion']])
            ->postJson('/atencion/enviar-contacto', ['conv_id' => $this->conv->id, 'contacto_id' => $c->id])
            ->assertStatus(422);

        Http::assertNothingSent();
        $this->assertSame(0, MensajeWA::count());
    }
}
