<?php

namespace Tests\Feature;

use App\Models\ColaAtencion;
use App\Models\RecepcionChecklist;
use App\Models\User;
use App\Services\ChecklistRecepcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checklist de recepción por obra social (08/10): cada obra social tiene su
 * lista de pasos; las que no, usan la General. Reemplaza al motor de reglas.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=ChecklistPorObraSocial
 */
class ChecklistPorObraSocialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'A', 'nombre_completo' => 'Supervisora', 'email' => 'a@example.com',
            'password' => bcrypt('x'), 'rol' => 'supervisora', 'activo' => true]);
    }

    private function sesion() { return $this->actingAs($this->admin)->withSession(['colas' => ['atencion']]); }

    private function guardar(array $body)
    {
        return $this->sesion()->postJson('/v2/recepcion/reglas/checklist', $body);
    }

    private function textos(array $checklist): array { return array_column($checklist, 'label'); }

    public function test_la_general_arranca_con_la_lista_de_siempre(): void
    {
        $general = RecepcionChecklist::where('clave', '')->with('items')->sole();
        $this->assertSame(4, $general->items->count());

        $c = ChecklistRecepcion::para('Una obra social sin lista', '210', ['Consulta']);
        $this->assertSame(['Obra social verificada', 'Copago gestionado', 'Orden médica validada', 'Datos del paciente completos'], $this->textos($c));
        $this->assertSame($this->textos($c), $this->textos(ChecklistRecepcion::para(null, null)));
        $this->assertFalse($c[0]['done']);
    }

    public function test_cada_obra_social_ve_su_propia_lista_por_nombre_exacto(): void
    {
        $this->guardar(['financiador' => 'Osde Binario', 'items' => [
            ['texto' => 'Pedir token', 'obligatorio' => true, 'nota' => 'Por la app'],
            ['texto' => 'Autorización en la página web', 'obligatorio' => false],
        ]])->assertOk();

        $c = ChecklistRecepcion::para('  osde   BINARIO ', null);      // mayúsculas y espacios no cuentan
        $this->assertSame(['Pedir token', 'Autorización en la página web'], $this->textos($c));
        $this->assertTrue($c[0]['obligatorio']);
        $this->assertFalse($c[1]['obligatorio']);
        $this->assertSame('Por la app', $c[0]['nota']);

        // "OSDE" es otra obra social: usa la General.
        $this->assertContains('Copago gestionado', $this->textos(ChecklistRecepcion::para('OSDE', null)));
    }

    public function test_apagado_o_sin_pasos_usa_la_general(): void
    {
        $id = $this->guardar(['financiador' => 'Galeno', 'items' => [['texto' => 'Pedir credencial', 'obligatorio' => true]]])
            ->assertOk()->json('checklist.id');
        $this->assertSame(['Pedir credencial'], $this->textos(ChecklistRecepcion::para('Galeno', null)));

        $this->guardar(['id' => $id, 'financiador' => 'Galeno', 'activo' => false, 'items' => [['texto' => 'Pedir credencial', 'obligatorio' => true]]])->assertOk();
        $this->assertContains('Copago gestionado', $this->textos(ChecklistRecepcion::para('Galeno', null)));

        $this->guardar(['id' => $id, 'financiador' => 'Galeno', 'activo' => true, 'items' => []])->assertOk();
        $this->assertContains('Copago gestionado', $this->textos(ChecklistRecepcion::para('Galeno', null)));
    }

    public function test_guardar_reemplaza_los_pasos_y_respeta_el_orden(): void
    {
        $id = $this->guardar(['financiador' => 'Medicus', 'items' => [['texto' => 'A'], ['texto' => 'B']]])->assertOk()->json('checklist.id');

        $r = $this->guardar(['id' => $id, 'financiador' => 'Medicus', 'items' => [['texto' => 'C'], ['texto' => 'A'], ['texto' => 'B']]])->assertOk();

        $this->assertSame(['C', 'A', 'B'], array_column($r->json('checklist.items'), 'texto'));
        $this->assertSame(['C', 'A', 'B'], $this->textos(ChecklistRecepcion::para('Medicus', null)));
        $this->assertSame('Supervisora', $r->json('checklist.editado_por'));
    }

    public function test_no_admite_dos_listas_para_la_misma_obra_social_ni_pasos_vacios(): void
    {
        $this->guardar(['financiador' => 'Medicus', 'items' => [['texto' => 'A']]])->assertOk();

        $this->guardar(['financiador' => ' medicus ', 'items' => [['texto' => 'B']]])->assertStatus(422);
        $this->guardar(['financiador' => '', 'items' => [['texto' => 'B']]])->assertStatus(422);
        $this->guardar(['financiador' => 'Galeno', 'items' => [['texto' => '']]])->assertStatus(422);
        $this->assertSame(2, RecepcionChecklist::count());   // General + Medicus
    }

    public function test_la_general_se_edita_pero_no_se_borra_ni_se_renombra(): void
    {
        $general = RecepcionChecklist::where('clave', '')->sole();

        $this->guardar(['id' => $general->id, 'financiador' => 'Otra cosa', 'activo' => false, 'items' => [['texto' => 'Pedir DNI', 'obligatorio' => true]]])->assertOk();
        $general->refresh();
        $this->assertTrue($general->esGeneral());
        $this->assertTrue($general->activo);
        $this->assertSame(['Pedir DNI'], $this->textos(ChecklistRecepcion::para('Sin lista', null)));

        $this->sesion()->postJson("/v2/recepcion/reglas/checklist/{$general->id}/borrar")->assertStatus(422);

        $id = $this->guardar(['financiador' => 'Galeno', 'items' => [['texto' => 'X']]])->json('checklist.id');
        $this->sesion()->postJson("/v2/recepcion/reglas/checklist/{$id}/borrar")->assertOk();
        $this->assertSame(['Pedir DNI'], $this->textos(ChecklistRecepcion::para('Galeno', null)));
    }

    public function test_la_pantalla_y_los_datos_cargan_y_piden_permiso(): void
    {
        $this->sesion()->get('/v2/recepcion/reglas')->assertOk()->assertSee('Obras sociales');
        $r = $this->sesion()->getJson('/v2/recepcion/reglas/data')->assertOk();
        $this->assertTrue($r->json('checklists.0.general'));

        $sec = User::create(['name' => 'S', 'nombre_completo' => 'Sec', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'secretaria', 'activo' => true]);
        $this->actingAs($sec)->withSession(['colas' => ['atencion']])->postJson('/v2/recepcion/reglas/checklist',
            ['financiador' => 'Galeno', 'items' => [['texto' => 'X']]])->assertStatus(403);
    }

    public function test_el_checklist_de_un_paciente_ya_en_la_cola_sale_de_su_obra_social(): void
    {
        $this->guardar(['financiador' => 'Osde Binario', 'items' => [['texto' => 'Pedir token', 'obligatorio' => true]]])->assertOk();

        $p = ColaAtencion::create(['dni' => '30111222', 'nombre' => 'Ana', 'apellido' => 'Paz', 'obra_social' => 'OSDE',
            'financiador' => 'Osde Binario', 'motivo' => 'turno', 'hora_llegada' => now(), 'orden' => 1]);

        $this->assertSame(['Pedir token'], $this->textos(ChecklistRecepcion::paraPaciente($p)));
    }
}
