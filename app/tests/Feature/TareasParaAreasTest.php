<?php

namespace Tests\Feature;

use App\Models\ConversacionEvento;
use App\Models\ConversacionWA;
use App\Models\MensajeWA;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tareas para un área (29/10): desde un mensaje (o varios) de una conversación
 * se le pide algo a un área sin derivar la conversación. El área la ve hasta
 * que alguien la toma, la completa avisando en el chat y puede contestarle al
 * paciente por el número de la conversación.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=TareasParaAreas
 */
class TareasParaAreasTest extends TestCase
{
    use RefreshDatabase;

    private User $secre;
    private User $admin;
    private ConversacionWA $conv;
    private MensajeWA $comprobante;
    private bool $botCaido = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.bot_token' => 'tok', 'app.bot_ingress_token' => 'tok']);
        Queue::fake();
        // Un solo fake para toda la prueba: los Http::fake posteriores no pisan al primero.
        Http::fake(fn () => $this->botCaido
            ? Http::response(['ok' => false], 500)
            : Http::response(['ok' => true, 'wa_id' => 'true_resp_1']));

        $this->secre = $this->usuario('secre');
        $this->admin = $this->usuario('admin');
        $this->conv = ConversacionWA::create(['contacto' => '111@lid', 'area' => 'atencion', 'estado' => 'activa', 'nombre' => 'Paciente Uno']);
        MensajeWA::create(['conversacion_id' => $this->conv->id, 'direccion' => 'entrante', 'tipo' => 'texto', 'contenido' => 'Les mando el comprobante']);
        $this->comprobante = MensajeWA::create(['conversacion_id' => $this->conv->id, 'direccion' => 'entrante', 'tipo' => 'imagen',
            'archivo_url' => 'http://panel/wa-media/comprobante.jpg']);
    }

    private function usuario(string $n): User
    {
        return User::create(['name' => $n, 'nombre_completo' => ucfirst($n) . ' Apellido', 'email' => "{$n}@example.com",
            'password' => bcrypt('x'), 'rol' => 'supervisora', 'activo' => true]);
    }

    private function como(User $u, array $colas)
    {
        return $this->actingAs($u)->withSession(['colas' => $colas]);
    }

    private function crear(array $extra = [])
    {
        return $this->como($this->secre, ['atencion'])->postJson('/tareas', $extra + [
            'titulo'      => 'Registrar el pago',
            'area'        => 'administracion',
            'ref_tipo'    => 'wa',
            'ref_id'      => $this->conv->id,
            'mensaje_ids' => [$this->comprobante->id],
        ]);
    }

    public function test_crear_desde_mensajes_la_ve_el_area_y_queda_en_el_chat(): void
    {
        $id = $this->crear()->assertOk()->json('data.id');
        $t = Tarea::findOrFail($id);
        $this->assertSame('administracion', $t->area);
        $this->assertNull($t->asignada_a);
        $this->assertSame([$this->comprobante->id], $t->mensajes->pluck('id')->all());

        // La conversación no se movió y quedó registrado el pedido.
        $this->assertSame('atencion', $this->conv->fresh()->area);
        $ev = ConversacionEvento::where('conversacion_id', $this->conv->id)->where('tipo', 'tarea_area')->firstOrFail();
        $this->assertStringContainsString('Registrar el pago', $ev->detalle);

        // Administración la ve en "Mías" (sin tomar), con el comprobante; Secretaría no la tiene como "de su área".
        $data = $this->como($this->admin, ['administracion'])->getJson('/tareas/data?filtro=mias')->assertOk();
        $this->assertSame([$id], array_column($data->json('data'), 'id'));
        $this->assertSame('http://panel/wa-media/comprobante.jpg', $data->json('data.0.mensajes.0.archivo_url'));
        $this->assertSame('Paciente Uno', $data->json('data.0.conversacion.contacto'));
        $this->assertSame([], $this->como($this->usuario('otra'), ['atencion'])->getJson('/tareas/data?filtro=area')->json('data'));

        // Aviso para el área, no para quien la pidió.
        $a = $this->como($this->admin, ['administracion'])->getJson('/bot-pulso')->json('avisos');
        $this->assertSame([$id], array_column($a['tareas_area'], 'id'));
        $this->assertSame('Secre Apellido', $a['tareas_area'][0]['de']);
        $this->assertSame([], $this->como($this->secre, ['administracion'])->getJson('/bot-pulso')->json('avisos.tareas_area'));

        // En el chat, el mensaje muestra que está en una tarea.
        $msgs = $this->como($this->secre, ['atencion'])->getJson("/atencion/conversacion/{$this->conv->id}")->json('mensajes');
        $img = collect($msgs)->firstWhere('id', $this->comprobante->id);
        $this->assertSame('Administración', $img['tareas'][0]['area']);
    }

    public function test_tomar_y_completar_avisa_a_quien_la_pidio(): void
    {
        $id = $this->crear()->json('data.id');

        $this->como($this->admin, ['administracion'])->patchJson("/tareas/{$id}", ['asignada_a' => $this->admin->id, 'estado' => 'en_progreso'])->assertOk();
        // Ya tomada: deja de avisarse al área.
        $this->assertSame([], $this->como($this->usuario('otra'), ['administracion'])->getJson('/bot-pulso')->json('avisos.tareas_area'));

        $this->como($this->admin, ['administracion'])->patchJson("/tareas/{$id}", ['estado' => 'completada', 'resolucion' => 'Pago registrado, recibo 0001-234'])->assertOk();
        $t = Tarea::findOrFail($id);
        $this->assertSame($this->admin->id, $t->completada_por);
        $this->assertNotNull($t->completada_at);
        $this->assertSame('Pago registrado, recibo 0001-234', $t->comentarios()->first()->contenido);

        $ev = ConversacionEvento::where('conversacion_id', $this->conv->id)->where('tipo', 'tarea_area_hecha')->firstOrFail();
        $this->assertStringContainsString('recibo 0001-234', $ev->detalle);

        $p = $this->como($this->secre, ['atencion'])->getJson('/bot-pulso')->json('avisos.mis_pedidos');
        $this->assertTrue($p[0]['hecha']);
        $this->assertSame('Admin Apellido', $p[0]['por']);
        $this->assertSame(1, $p[0]['coms']);

        // Reabrir limpia quién la completó.
        $this->como($this->admin, ['administracion'])->patchJson("/tareas/{$id}", ['estado' => 'pendiente'])->assertOk();
        $this->assertNull(Tarea::find($id)->completada_por);
    }

    public function test_responder_al_paciente_sale_por_el_numero_de_la_conversacion(): void
    {
        $id = $this->crear()->json('data.id');

        $r = $this->como($this->admin, ['administracion'])->postJson("/tareas/{$id}/responder", ['texto' => 'Recibimos tu pago, gracias'])->assertOk();
        $this->assertSame('Secretaría', $r->json('numero'));

        Http::assertSent(fn ($req) => str_contains($req->url(), '/enviar')
            && $req['contacto'] === '111@lid' && $req['texto'] === 'Recibimos tu pago, gracias'
            && str_contains($req->url(), parse_url($this->conv->botUrl(), PHP_URL_HOST)));

        $m = MensajeWA::where('tarea_id', $id)->firstOrFail();
        $this->assertSame($this->conv->id, $m->conversacion_id);
        $this->assertSame('saliente', $m->direccion);
        $this->assertSame($this->admin->id, $m->usuario_id);

        $msgs = $this->como($this->secre, ['atencion'])->getJson("/atencion/conversacion/{$this->conv->id}")->json('mensajes');
        $this->assertSame('Administración', collect($msgs)->firstWhere('id', $m->id)['desde_tarea']['area']);
        $this->assertStringContainsString('Recibimos tu pago', Tarea::find($id)->comentarios()->latest('id')->first()->contenido);
    }

    public function test_si_el_bot_no_envia_no_queda_registrado(): void
    {
        $id = $this->crear()->json('data.id');
        $this->botCaido = true;
        $this->como($this->admin, ['administracion'])->postJson("/tareas/{$id}/responder", ['texto' => 'Hola'])->assertStatus(502);
        $this->assertSame(0, MensajeWA::where('tarea_id', $id)->count());
    }

    public function test_tarea_para_la_propia_area_y_para_una_persona_siguen_andando(): void
    {
        // Pares: Secretaría → Secretaría.
        $this->crear(['area' => 'atencion'])->assertOk();
        $this->assertCount(1, $this->como($this->usuario('par'), ['atencion'])->getJson('/tareas/data?filtro=area')->json('data'));

        // Persona, sin área (lo de siempre).
        $id = $this->como($this->secre, ['atencion'])->postJson('/tareas', ['titulo' => 'Llamar', 'asignada_a' => $this->admin->id])->assertOk()->json('data.id');
        $this->assertNull(Tarea::find($id)->area);
        $this->assertSame($this->admin->id, Tarea::find($id)->asignada_a);
    }

    public function test_no_se_puede_pedir_a_un_area_que_no_existe_ni_adjuntar_mensajes_de_otra_conversacion(): void
    {
        $this->crear(['area' => 'difusion'])->assertStatus(422);
        $this->crear(['area' => 'inventada'])->assertStatus(422);

        $otra = ConversacionWA::create(['contacto' => '222@lid', 'area' => 'atencion', 'estado' => 'activa']);
        $ajeno = MensajeWA::create(['conversacion_id' => $otra->id, 'direccion' => 'entrante', 'tipo' => 'texto', 'contenido' => 'otro']);
        $id = $this->crear(['mensaje_ids' => [$this->comprobante->id, $ajeno->id]])->assertOk()->json('data.id');
        $this->assertSame([$this->comprobante->id], Tarea::find($id)->mensajes->pluck('id')->all());
    }
}
