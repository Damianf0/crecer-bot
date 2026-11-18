<?php

namespace Tests\Feature;

use App\Models\OvoFicha;
use App\Models\OvoMovimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRM de ovodonación (12/11): reemplaza Zoho Bigin.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=OvoCrm
 */
class OvoCrmTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol, string $mail, ?array $permisos = null): User
    {
        return User::create(['name' => $mail, 'nombre_completo' => ucfirst($mail), 'email' => "{$mail}@example.com",
            'password' => bcrypt('x'), 'rol' => $rol, 'activo' => true, 'permisos' => $permisos]);
    }

    private function crm(): User
    {
        return $this->usuario('secretaria', 'ovo', ['atencion', 'crm_ovodonacion']);
    }

    private function como(User $u)
    {
        return $this->actingAs($u)->withSession(['colas' => ['atencion']]);
    }

    /** Donante que cumple todo (IMC 22, 25 años, 165 cm, sin tatuajes). */
    private function donanteOk(array $extra = []): array
    {
        return array_merge(['tipo' => 'donante', 'nombre' => 'Ana', 'apellido' => 'López', 'dni' => '40.111.222',
            'fecha_nacimiento' => now()->subYears(25)->subDays(10)->toDateString(), 'peso' => 60, 'altura' => 165,
            'tatuaje_reciente' => false, 'planea_tatuaje' => false], $extra);
    }

    public function test_sin_permiso_no_entra_ni_supervision(): void
    {
        $admin = $this->usuario('admin', 'admin');
        $this->como($admin)->get('/v2/crm-ovo')->assertForbidden();
        $this->como($admin)->getJson('/crm-ovo/tablero')->assertForbidden();
        $this->como($admin)->postJson('/crm-ovo/ficha', $this->donanteOk())->assertForbidden();

        $this->como($this->crm())->get('/v2/crm-ovo')->assertOk();
    }

    public function test_donante_que_cumple_queda_en_primer_contacto(): void
    {
        $u = $this->crm();
        $r = $this->como($u)->postJson('/crm-ovo/ficha', $this->donanteOk())->assertCreated();

        $this->assertSame('donantes', $r->json('ficha.proceso'));
        $this->assertSame('primer_contacto', $r->json('ficha.etapa'));
        $this->assertSame('40111222', $r->json('ficha.dni'));
        $this->assertEquals(22.0, $r->json('ficha.imc'));

        // Mismo DNI otra vez: no duplica y avisa cuál es.
        $d = $this->como($u)->postJson('/crm-ovo/ficha', $this->donanteOk(['nombre' => 'Otra']))->assertStatus(422);
        $this->assertSame($r->json('ficha.id'), $d->json('ficha_id'));
    }

    public function test_filtros_de_ingreso_en_cascada(): void
    {
        $u = $this->crm();

        // IMC fuera de rango → No cumple (IMC), aunque también falle la edad (gana el primero).
        $r = $this->como($u)->postJson('/crm-ovo/ficha', $this->donanteOk(['dni' => '1', 'peso' => 95,
            'fecha_nacimiento' => now()->subYears(40)->toDateString()]))->assertCreated();
        $this->assertSame('no_cumple', $r->json('ficha.etapa'));
        $this->assertStringStartsWith('IMC', $r->json('ficha.motivo_no_cumple'));

        // Edad
        $r = $this->como($u)->postJson('/crm-ovo/ficha', $this->donanteOk(['dni' => '2',
            'fecha_nacimiento' => now()->subYears(35)->toDateString()]))->assertCreated();
        $this->assertSame('no_cumple', $r->json('ficha.etapa'));
        $this->assertStringStartsWith('Edad', $r->json('ficha.motivo_no_cumple'));

        // Tatuaje reciente con fecha → tablero Tatuaje, recontactar a los 6 meses.
        $hecho = now()->subMonths(2)->toDateString();
        $r = $this->como($u)->postJson('/crm-ovo/ficha', $this->donanteOk(['dni' => '3',
            'tatuaje_reciente' => true, 'fecha_tatuaje' => $hecho]))->assertCreated();
        $this->assertSame('tatuaje', $r->json('ficha.proceso'));
        $this->assertSame('esperando', $r->json('ficha.etapa'));
        $this->assertSame(now()->subMonths(2)->addMonths(6)->toDateString(), $r->json('ficha.recontactar_desde'));

        // Altura
        $r = $this->como($u)->postJson('/crm-ovo/ficha', $this->donanteOk(['dni' => '4', 'altura' => 150, 'peso' => 50]))->assertCreated();
        $this->assertSame('no_cumple', $r->json('ficha.etapa'));
        $this->assertStringStartsWith('Altura', $r->json('ficha.motivo_no_cumple'));

        // Sin datos para evaluar: no se descarta.
        $r = $this->como($u)->postJson('/crm-ovo/ficha', ['tipo' => 'donante', 'nombre' => 'Sin datos'])->assertCreated();
        $this->assertSame('primer_contacto', $r->json('ficha.etapa'));

        // Las receptoras no pasan por los filtros.
        $r = $this->como($u)->postJson('/crm-ovo/ficha', ['tipo' => 'receptora', 'nombre' => 'Rec', 'edad' => 45])->assertCreated();
        $this->assertSame('receptoras', $r->json('ficha.proceso'));
        $this->assertSame('esperando', $r->json('ficha.etapa'));
    }

    public function test_mover_registra_historial_y_pide_motivo(): void
    {
        $u = $this->crm();
        $id = $this->como($u)->postJson('/crm-ovo/ficha', $this->donanteOk())->json('ficha.id');

        $this->como($u)->postJson("/crm-ovo/ficha/{$id}/mover", ['etapa' => 'consulta', 'proximo_turno' => now()->addDays(3)->format('Y-m-d H:i')])->assertOk();
        $f = OvoFicha::find($id);
        $this->assertSame('consulta', $f->etapa);
        $this->assertNotNull($f->proximo_turno);

        // Cancelar sin motivo no.
        $this->como($u)->postJson("/crm-ovo/ficha/{$id}/mover", ['etapa' => 'cancelado'])->assertStatus(422);
        // Etapa de otro tablero / de receptoras no.
        $this->como($u)->postJson("/crm-ovo/ficha/{$id}/mover", ['etapa' => 'transferencia'])->assertStatus(422);
        $this->como($u)->postJson("/crm-ovo/ficha/{$id}/mover", ['proceso' => 'receptoras', 'etapa' => 'esperando'])->assertStatus(422);

        $this->como($u)->postJson("/crm-ovo/ficha/{$id}/mover", ['etapa' => 'cancelado', 'motivo' => 'No responde'])->assertOk();
        $this->assertSame('No responde', OvoFicha::find($id)->motivo_cancelacion);

        // Alta + consulta + cancelado
        $this->assertSame(3, OvoMovimiento::where('ficha_id', $id)->count());
        $det = $this->como($u)->getJson("/crm-ovo/ficha/{$id}")->assertOk();
        $this->assertCount(3, $det->json('ficha.movimientos'));
    }

    public function test_vuelve_a_donacion_pasa_solo_a_primer_contacto(): void
    {
        $u = $this->crm();
        $id = $this->como($u)->postJson('/crm-ovo/ficha', $this->donanteOk())->json('ficha.id');
        $this->como($u)->postJson("/crm-ovo/ficha/{$id}/mover", ['proceso' => 'ya_donaron', 'etapa' => 'esperar'])->assertOk();
        $this->como($u)->postJson("/crm-ovo/ficha/{$id}/mover", ['etapa' => 'vuelve'])->assertOk();

        $f = OvoFicha::find($id);
        $this->assertSame(['donantes', 'primer_contacto'], [$f->proceso, $f->etapa]);
        $this->assertTrue(OvoMovimiento::where('ficha_id', $id)->latest('id')->first()->automatico);
    }

    public function test_avisos_de_turno_pasado_y_estancada(): void
    {
        $f = OvoFicha::create(['tipo' => 'donante', 'proceso' => 'donantes', 'etapa' => 'ecografia', 'nombre' => 'X',
            'etapa_desde' => now()->subDays(30), 'proximo_turno' => now()->subDays(2)]);
        $claves = array_column($f->avisos(), 'clave');
        $this->assertContains('turno_pasado', $claves);
        $this->assertContains('estancada', $claves);

        // Con turno por delante no está estancada; en etapa terminal no hay avisos.
        $f->update(['proximo_turno' => now()->addDays(2)]);
        $this->assertSame([], $f->fresh()->avisos());
        $f->update(['etapa' => 'cancelado', 'proximo_turno' => now()->subDays(2)]);
        $this->assertSame([], $f->fresh()->avisos());

        // En espera no está "estancada", pero avisa cuando llega la fecha de recontacto.
        $t = OvoFicha::create(['tipo' => 'donante', 'proceso' => 'tatuaje', 'etapa' => 'esperando', 'nombre' => 'T',
            'etapa_desde' => now()->subMonths(7), 'fecha_tatuaje' => now()->subMonths(7), 'recontactar_desde' => now()->subDay()]);
        $this->assertSame(['recontactar'], array_column($t->avisos(), 'clave'));
    }

    public function test_tablero_busca_por_nombre_y_apellido_y_nota(): void
    {
        $u = $this->crm();
        $id = $this->como($u)->postJson('/crm-ovo/ficha', $this->donanteOk())->json('ficha.id');
        $this->como($u)->postJson('/crm-ovo/ficha', $this->donanteOk(['nombre' => 'Bea', 'apellido' => 'Gómez', 'dni' => '9']));

        // (con acento: SQLite no los ignora en el LIKE; MySQL de producción sí)
        $r = $this->como($u)->getJson('/crm-ovo/tablero?proceso=donantes&q=' . urlencode('ana lóp'))->assertOk();
        $col = collect($r->json('columnas'))->firstWhere('etapa', 'primer_contacto');
        $this->assertSame(['Ana López'], array_column($col['fichas'], 'nombre'));

        $n = $this->como($u)->postJson("/crm-ovo/ficha/{$id}/nota", ['texto' => 'Llamar el lunes'])->assertOk();
        $this->assertSame('Llamar el lunes', $n->json('ficha.notas.0.texto'));

        $this->como($u)->postJson("/crm-ovo/ficha/{$id}/borrar")->assertOk();
        $this->assertSoftDeleted('ovo_fichas', ['id' => $id]);
    }
}
