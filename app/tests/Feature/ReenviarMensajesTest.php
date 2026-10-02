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
 * Reenviar mensajes sueltos (01/10): como el "Reenviar" de WhatsApp, solo lo
 * seleccionado. Antes el único reenvío mandaba el hilo entero como texto y las
 * fotos llegaban como "[imagen]".
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=ReenviarMensajes
 */
class ReenviarMensajesTest extends TestCase
{
    use RefreshDatabase;

    private User $u;
    private ConversacionWA $conv;
    private Contacto $destino;
    private string $foto;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            '*/status'         => Http::response(['status' => 'listo']),
            '*/check-numero'   => Http::response(['ok' => true, 'registered' => true, 'normalizedId' => '5492235550009@c.us']),
            '*/enviar-archivo' => Http::response(['ok' => true, 'wa_id' => 'true_x_ARCH']),
            '*/enviar'         => Http::response(['ok' => true, 'wa_id' => 'true_x_TXT']),
            '*'                => Http::response(['ok' => true]),
        ]);
        $this->u = User::create(['name' => 'S', 'nombre_completo' => 'Sec', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'secretaria', 'activo' => true]);
        $this->conv = ConversacionWA::create(['contacto' => '111@lid', 'area' => 'administracion', 'estado' => 'activa', 'nombre' => 'Paciente']);
        $this->destino = Contacto::create(['telefono' => '5492235550009', 'nombre' => 'Dra. Destino']);

        $dir = storage_path('app/private/public/wa-media');
        @mkdir($dir, 0775, true);
        $this->foto = 'test_reenvio_' . uniqid() . '.png';
        // PNG de 1x1 para que mime_content_type lo reconozca.
        file_put_contents("{$dir}/{$this->foto}", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/private/public/wa-media/' . $this->foto));
        parent::tearDown();
    }

    private function msg(array $a): MensajeWA
    {
        return MensajeWA::create($a + ['conversacion_id' => $this->conv->id, 'direccion' => 'entrante', 'tipo' => 'texto', 'leido' => true]);
    }

    private function reenviar(array $body)
    {
        return $this->actingAs($this->u)->withSession(['colas' => ['administracion']])
            ->postJson("/atencion/conversacion/{$this->conv->id}/reenviar-mensajes", $body + ['contacto_id' => $this->destino->id]);
    }

    public function test_reenvia_solo_lo_seleccionado_con_la_foto_real(): void
    {
        $txt  = $this->msg(['contenido' => 'Les mando la orden']);
        $foto = $this->msg(['tipo' => 'imagen', 'contenido' => 'orden médica', 'archivo_url' => 'http://192.168.1.115:3002/media/' . $this->foto]);
        $otro = $this->msg(['contenido' => 'Esto no va']);

        $r = $this->reenviar(['mensaje_ids' => [$foto->id, $txt->id]])->assertOk();
        $this->assertSame(2, $r->json('enviados'));

        // La foto viaja como archivo (no como "[imagen]"), con su leyenda y por el número del área.
        Http::assertSent(fn ($req) => $req->url() === 'http://bot-administracion:3002/enviar-archivo'
            && $req['contacto'] === '5492235550009@c.us' && $req['mimetype'] === 'image/png'
            && $req['caption'] === 'orden médica' && $req['base64'] !== '');
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/enviar') && $req['texto'] === 'Les mando la orden');
        Http::assertNotSent(fn ($req) => ($req['texto'] ?? null) === 'Esto no va');

        // Destino: conversación nueva archivada (no ocupa la cola) con lo reenviado, en orden.
        $dest = ConversacionWA::where('contacto', '5492235550009@c.us')->where('area', 'administracion')->first();
        $this->assertSame('archivada', $dest->estado);
        $this->assertSame(['texto', 'imagen'], MensajeWA::where('conversacion_id', $dest->id)->orderBy('id')->pluck('tipo')->all());

        // Origen: sigue abierta y con nota interna.
        $this->assertSame('activa', $this->conv->fresh()->estado);
        $nota = MensajeWA::where('conversacion_id', $this->conv->id)->where('direccion', 'nota_interna')->value('contenido');
        $this->assertStringContainsString('Dra. Destino', $nota);
        $this->assertStringContainsString('1 mensaje, 1 foto', $nota);
    }

    public function test_desde_otro_numero(): void
    {
        $txt = $this->msg(['contenido' => 'Hola']);
        $this->reenviar(['mensaje_ids' => [$txt->id], 'area' => 'atencion'])->assertOk();
        Http::assertSent(fn ($req) => $req->url() === 'http://bot:3001/enviar');
        Http::assertNotSent(fn ($req) => str_starts_with($req->url(), 'http://bot-administracion:3002/enviar'));
    }

    public function test_archivo_perdido_o_nota_no_se_reenvian(): void
    {
        $perdida = $this->msg(['tipo' => 'imagen', 'archivo_url' => 'http://192.168.1.115:3002/media/no_existe.jpg']);
        $nota    = $this->msg(['direccion' => 'nota_interna', 'contenido' => 'interno']);

        $this->reenviar(['mensaje_ids' => [$perdida->id]])->assertStatus(422);
        $this->reenviar(['mensaje_ids' => [$nota->id]])->assertStatus(422);
        Http::assertNotSent(fn ($req) => str_contains($req->url(), '/enviar'));

        // El panel los muestra como no seleccionables.
        $msgs = collect($this->actingAs($this->u)->getJson("/atencion/conversacion/{$this->conv->id}")->json('mensajes'))->keyBy('id');
        $this->assertFalse($msgs[$perdida->id]['reenviable']);
        $this->assertFalse($msgs[$nota->id]['reenviable']);
    }

    public function test_no_acepta_mensajes_de_otra_conversacion(): void
    {
        $ajena = ConversacionWA::create(['contacto' => '222@lid', 'area' => 'atencion', 'estado' => 'activa']);
        $m = MensajeWA::create(['conversacion_id' => $ajena->id, 'direccion' => 'entrante', 'tipo' => 'texto', 'contenido' => 'x', 'leido' => true]);
        $this->reenviar(['mensaje_ids' => [$m->id]])->assertStatus(422);
    }
}
