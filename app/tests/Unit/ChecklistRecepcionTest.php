<?php

namespace Tests\Unit;

use App\Models\RecepcionRegla;
use App\Models\RecepcionRequisito;
use App\Services\ChecklistRecepcion;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChecklistRecepcionTest extends TestCase
{
    private array $requisitos = [];
    private int $reglaId = 0;

    private function req(int $id, string $nombre, int $orden = 0): RecepcionRequisito
    {
        $r = new RecepcionRequisito(['nombre' => $nombre, 'orden' => $orden, 'activo' => true]);
        $r->id = $id;
        return $this->requisitos[$id] = $r;
    }

    private function regla(int $reqId, array $attrs, string $editada = '2026-09-01 10:00'): RecepcionRegla
    {
        $r = new RecepcionRegla(array_merge(['modo' => 'obligatorio', 'activo' => true], $attrs, ['requisito_id' => $reqId]));
        $r->id = ++$this->reglaId;
        $r->updated_at = Carbon::parse($editada);
        $r->setRelation('requisito', $this->requisitos[$reqId]);
        return $r;
    }

    private function labels(array $items): array
    {
        return array_column($items, 'label');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->req(1, 'Orden médica', 1);
        $this->req(2, 'Autorización previa', 2);
        $this->req(3, 'Credencial', 3);
    }

    public function test_regla_general_aplica_a_todos(): void
    {
        $items = ChecklistRecepcion::resolver(collect([$this->regla(3, [])]), 'IOMA', null, ['Consulta Medica']);
        $this->assertSame(['Credencial'], $this->labels($items));
        $this->assertTrue($items[0]['obligatorio']);
    }

    public function test_campos_fijados_tienen_que_coincidir(): void
    {
        $reglas = collect([
            $this->regla(1, ['practica' => 'Ecografia Transvaginal']),
            $this->regla(2, ['financiador' => 'Osde Binario']),
        ]);
        $this->assertSame([], ChecklistRecepcion::resolver($reglas, 'IOMA', null, ['Consulta Medica']));
        $this->assertSame(['Orden médica'], $this->labels(ChecklistRecepcion::resolver($reglas, 'IOMA', null, ['Ecografia Transvaginal'])));
    }

    public function test_compara_sin_mayusculas_ni_espacios_de_mas(): void
    {
        $reglas = collect([$this->regla(2, ['financiador' => 'Osde Binario', 'practica' => 'Consulta Medica'])]);
        $items = ChecklistRecepcion::resolver($reglas, '  osde   BINARIO ', null, ['CONSULTA MEDICA']);
        $this->assertSame(['Autorización previa'], $this->labels($items));
    }

    public function test_matchea_cualquiera_de_las_practicas_del_turno(): void
    {
        $reglas = collect([$this->regla(1, ['practica' => 'Doppler arterias uterinas y subendometriales'])]);
        $items = ChecklistRecepcion::resolver($reglas, 'IOMA', null, ['Ecografia Transvaginal', 'DOPPLER ARTERIAS UTERINAS Y SUBENDOMETRIALES']);
        $this->assertSame(['Orden médica'], $this->labels($items));
    }

    public function test_la_obra_social_le_gana_a_la_practica(): void
    {
        // "Consulta no pide autorización" vs "IOMA pide autorización" → IOMA manda.
        $reglas = collect([
            $this->regla(2, ['practica' => 'Consulta Medica', 'modo' => 'no_pedir']),
            $this->regla(2, ['financiador' => 'IOMA']),
        ]);
        $this->assertSame(['Autorización previa'], $this->labels(ChecklistRecepcion::resolver($reglas, 'IOMA', null, ['Consulta Medica'])));
        $this->assertSame([], ChecklistRecepcion::resolver($reglas, 'Osde Binario', null, ['Consulta Medica']));
    }

    public function test_obra_social_mas_practica_le_gana_a_la_obra_social_sola(): void
    {
        $reglas = collect([
            $this->regla(1, ['financiador' => 'Osde Binario']),
            $this->regla(1, ['financiador' => 'Osde Binario', 'practica' => 'Consulta Medica', 'modo' => 'no_pedir']),
        ]);
        $this->assertSame([], ChecklistRecepcion::resolver($reglas, 'Osde Binario', null, ['Consulta Medica']));
        $this->assertSame(['Orden médica'], $this->labels(ChecklistRecepcion::resolver($reglas, 'Osde Binario', null, ['Ecografia Transvaginal'])));
    }

    public function test_el_plan_desempata_dentro_de_la_obra_social(): void
    {
        $reglas = collect([
            $this->regla(2, ['financiador' => 'Osde Binario']),
            $this->regla(2, ['financiador' => 'Osde Binario', 'plan' => '410', 'modo' => 'opcional']),
        ]);
        $items = ChecklistRecepcion::resolver($reglas, 'Osde Binario', '410', ['Consulta Medica']);
        $this->assertFalse($items[0]['obligatorio']);
        $items = ChecklistRecepcion::resolver($reglas, 'Osde Binario', '210', ['Consulta Medica']);
        $this->assertTrue($items[0]['obligatorio']);
    }

    public function test_empate_gana_la_editada_mas_reciente(): void
    {
        $reglas = collect([
            $this->regla(3, ['financiador' => 'IOMA', 'nota' => 'vieja'], '2026-09-01 10:00'),
            $this->regla(3, ['financiador' => 'IOMA', 'nota' => 'nueva'], '2026-09-10 10:00'),
        ]);
        $this->assertSame('nueva', ChecklistRecepcion::resolver($reglas, 'IOMA', null, [])[0]['nota']);
    }

    public function test_obligatorios_primero_y_despues_orden_del_catalogo(): void
    {
        $reglas = collect([
            $this->regla(1, ['modo' => 'opcional']),
            $this->regla(3, []),
            $this->regla(2, []),
        ]);
        $this->assertSame(['Autorización previa', 'Credencial', 'Orden médica'],
            $this->labels(ChecklistRecepcion::resolver($reglas, 'IOMA', null, [])));
    }

    public function test_sin_turno_solo_aplican_reglas_sin_practica(): void
    {
        $reglas = collect([
            $this->regla(1, ['practica' => 'Consulta Medica']),
            $this->regla(3, ['financiador' => 'IOMA']),
        ]);
        $this->assertSame(['Credencial'], $this->labels(ChecklistRecepcion::resolver($reglas, 'IOMA', null, [])));
    }
}
