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
 * 28/10: tras derivar una conversación de área, el paciente seguía escribiendo
 * (y mandando adjuntos) al número viejo; eso abría una conversación nueva en el
 * área de origen y el área destino nunca lo veía. Ahora, durante unos días, lo
 * que llega al número viejo va a la conversación derivada, marcado.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=DerivacionRedirige
 */
class DerivacionRedirigeEntrantesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ConversacionWA $conv;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.bot_token' => 'tok', 'app.bot_ingress_token' => 'tok']);
        Queue::fake();
        Http::fake([
            '*/enviar' => Http::response(['ok' => true, 'wa_id' => 'true_aviso_1']),
            '*'        => Http::response(['phone' => '5492233469767']),
        ]);
        $this->user = User::create(['name' => 'S', 'nombre_completo' => 'Secre', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'supervisora', 'activo' => true]);
        $this->conv = ConversacionWA::create(['contacto' => '999@lid', 'area' => 'atencion', 'estado' => 'activa']);
    }

    private function derivar()
    {
        return $this->actingAs($this->user)->withSession(['colas' => ['atencion']])
            ->postJson("/atencion/conversacion/{$this->conv->id}/derivar-area", ['area' => 'administracion']);
    }

    private function entrante(string $area, string $waId, string $tipo = 'imagen')
    {
        return $this->withToken('tok')->postJson('/api/bot/mensajes', [
            'contacto' => '999@lid', 'area' => $area, 'tipo' => $tipo,
            'archivo_url' => 'http://bot:3001/media/x.jpg', 'wa_id' => $waId,
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function test_lo_que_llega_al_numero_viejo_va_a_la_conversacion_derivada(): void
    {
        $this->derivar()->assertOk();
        $this->conv->refresh();
        $this->assertSame('administracion', $this->conv->area);
        $this->assertSame('atencion', $this->conv->derivada_desde);
        $this->assertDatabaseHas('conversacion_eventos', ['conversacion_id' => $this->conv->id,
            'tipo' => 'derivada_area', 'detalle' => 'Secretaría → Administración']);

        $this->entrante('atencion', 'false_999@lid_A')->assertCreated();

        $this->assertSame(0, ConversacionWA::where('contacto', '999@lid')->where('area', 'atencion')->count());
        $m = MensajeWA::where('wa_id', 'false_999@lid_A')->first();
        $this->assertSame($this->conv->id, $m->conversacion_id);
        $this->assertSame('atencion', $m->via_area);

        // En el panel se ve marcado.
        $r = $this->actingAs($this->user)->withSession(['colas' => ['administracion']])
            ->getJson("/atencion/conversacion/{$this->conv->id}");
        $this->assertSame('Secretaría', collect($r->json('mensajes'))->firstWhere('id', $m->id)['via']);
    }

    public function test_pasada_la_ventana_vuelve_a_abrir_conversacion_en_el_area_de_origen(): void
    {
        $this->derivar()->assertOk();
        $this->travel(ConversacionWA::DIAS_REDIRIGIR_DERIVADA + 1)->days();

        $this->entrante('atencion', 'false_999@lid_B', 'texto')->assertCreated();

        $origen = ConversacionWA::where('contacto', '999@lid')->where('area', 'atencion')->first();
        $this->assertNotNull($origen);
        $this->assertNull(MensajeWA::where('wa_id', 'false_999@lid_B')->value('via_area'));
    }

    public function test_si_el_area_de_origen_retomo_la_conversacion_no_se_redirige(): void
    {
        $this->derivar()->assertOk();
        // Secretaría le escribe desde su número (p. ej. desde el celular).
        $otra = ConversacionWA::create(['contacto' => '999@lid', 'area' => 'atencion', 'estado' => 'activa']);

        $this->entrante('atencion', 'false_999@lid_C', 'texto')->assertCreated();

        $this->assertSame($otra->id, MensajeWA::where('wa_id', 'false_999@lid_C')->value('conversacion_id'));
    }
}
