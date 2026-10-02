<?php

namespace Tests\Feature;

use App\Models\ColaAtencion;
use App\Models\Contacto;
use App\Models\PrimeraVez;
use App\Models\PrimeraVezMedico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pacientes de primera vez (02/10): reemplaza la planilla de recepción.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=PrimeraVez
 */
class PrimeraVezTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol, string $mail): User
    {
        return User::create(['name' => $mail, 'nombre_completo' => ucfirst($rol), 'email' => "{$mail}@example.com",
            'password' => bcrypt('x'), 'rol' => $rol, 'activo' => true]);
    }

    private function como(User $u)
    {
        return $this->actingAs($u)->withSession(['colas' => ['atencion']]);
    }

    public function test_cualquiera_registra_y_queda_vinculado_al_contacto(): void
    {
        $sec = $this->usuario('secretaria', 'sec');
        $c = Contacto::create(['telefono' => '5492235550001', 'nombre' => 'Ana López', 'dni' => '30111222']);

        $this->como($sec)->get('/v2/primera-vez')->assertOk();

        $r = $this->como($sec)->postJson('/primera-vez', ['nombre' => ' Ana López ', 'dni' => '30.111.222', 'motivo' => 'derivado',
            'medico' => 'Dra. Uno', 'derivante' => 'Dr. Deriva'])->assertCreated();
        $this->assertSame($c->id, $r->json('item.contacto_id'));
        $this->assertSame('30111222', $r->json('item.dni'));
        $this->assertSame(now()->toDateString(), $r->json('item.fecha'));

        // Aparece en el mes actual y el derivante queda para autocompletar.
        $d = $this->como($sec)->getJson('/primera-vez/data')->assertOk();
        $this->assertSame(['Ana López'], array_column($d->json('items'), 'nombre'));
        $this->assertSame(['Dr. Deriva'], $d->json('derivantes'));
        $this->assertFalse($d->json('es_admin'));

        // Sin nombre o con un motivo inventado no entra.
        $this->como($sec)->postJson('/primera-vez', ['motivo' => 'turno'])->assertStatus(422);
        $this->como($sec)->postJson('/primera-vez', ['nombre' => 'X', 'motivo' => 'otro'])->assertStatus(422);
        // No se crean contactos desde acá.
        $this->como($sec)->postJson('/primera-vez', ['nombre' => 'Nueva', 'dni' => '40999888', 'motivo' => 'turno'])->assertCreated();
        $this->assertSame(1, Contacto::count());
    }

    public function test_corrige_y_borra_quien_lo_cargo_o_supervision(): void
    {
        $sec  = $this->usuario('secretaria', 'sec');
        $otra = $this->usuario('secretaria', 'otra');
        $sup  = $this->usuario('supervisora', 'sup');
        $id = $this->como($sec)->postJson('/primera-vez', ['nombre' => 'Ana', 'motivo' => 'turno'])->json('item.id');

        $this->como($otra)->postJson("/primera-vez/{$id}", ['nombre' => 'Otra', 'motivo' => 'turno'])->assertForbidden();
        $this->como($otra)->postJson("/primera-vez/{$id}/borrar")->assertForbidden();

        $this->como($sec)->postJson("/primera-vez/{$id}", ['nombre' => 'Ana María', 'motivo' => 'espontaneo'])->assertOk();
        $this->assertSame('espontaneo', PrimeraVez::find($id)->motivo);

        $this->como($sup)->postJson("/primera-vez/{$id}/borrar")->assertOk();
        $this->assertSame(0, PrimeraVez::count());
    }

    public function test_medicos_los_edita_supervision_y_el_cambio_de_nombre_llega_al_historial(): void
    {
        $sec = $this->usuario('secretaria', 'sec');
        $sup = $this->usuario('supervisora', 'sup');

        $this->como($sec)->postJson('/primera-vez/medicos', ['nombre' => 'Dra. Uno'])->assertForbidden();

        $id = $this->como($sup)->postJson('/primera-vez/medicos', ['nombre' => 'Dra. Uno'])->assertOk()->json('medicos.0.id');
        $this->como($sup)->postJson('/primera-vez/medicos', ['nombre' => 'Dra. Uno'])->assertStatus(422);   // repetido
        $this->como($sec)->postJson('/primera-vez', ['nombre' => 'Ana', 'motivo' => 'turno', 'medico' => 'Dra. Uno'])->assertCreated();

        $this->como($sup)->postJson('/primera-vez/medicos', ['id' => $id, 'nombre' => 'Dra. Uno Bis', 'activo' => false])->assertOk();
        $this->assertSame('Dra. Uno Bis', PrimeraVez::first()->medico);
        $this->assertFalse(PrimeraVezMedico::find($id)->activo);
    }

    public function test_reporte_por_mes_medico_motivo_derivante_y_asistencia(): void
    {
        $sec = $this->usuario('secretaria', 'sec');
        $sup = $this->usuario('supervisora', 'sup');
        $mes = now()->format('Y-m');
        $base = ['fecha' => now()->toDateString(), 'origen' => 'panel'];

        PrimeraVez::create($base + ['nombre' => 'A', 'motivo' => 'turno', 'medico' => 'Dra. Uno', 'dni' => '30111222']);
        PrimeraVez::create($base + ['nombre' => 'B', 'motivo' => 'derivado', 'medico' => 'Dra. Uno', 'derivante' => 'dr. deriva', 'dni' => '30111333']);
        PrimeraVez::create($base + ['nombre' => 'C', 'motivo' => 'derivado', 'medico' => 'Dr. Dos', 'derivante' => 'Dr. Deriva']);
        PrimeraVez::create(['fecha' => now()->subMonths(2)->startOfMonth()->toDateString(), 'solo_mes' => true, 'origen' => 'planilla',
            'nombre' => 'D', 'motivo' => 'espontaneo']);
        // A vino a la clínica después de registrarse; B no.
        ColaAtencion::create(['dni' => '30111222', 'nombre' => 'A', 'apellido' => 'A', 'motivo' => 'turno', 'origen' => 'tablet',
            'estado' => 'esperando', 'hora_llegada' => now(), 'orden' => 1]);

        $this->como($sec)->getJson('/primera-vez/reporte')->assertForbidden();

        $r = $this->como($sup)->getJson('/primera-vez/reporte')->assertOk();
        $this->assertSame(4, $r->json('total'));
        $this->assertEquals(['turno' => 1, 'derivado' => 2, 'espontaneo' => 1], $r->json('por_motivo'));
        $actual = collect($r->json('por_mes'))->firstWhere('mes', $mes);
        $this->assertSame(3, $actual['total']);
        $this->assertSame(2, $actual['medicos']['Dra. Uno']);
        $this->assertSame(1, collect($r->json('por_medico'))->firstWhere('medico', '(sin médico)')['total']);
        // El mismo derivante escrito distinto cuenta junto.
        $this->assertSame([['derivante' => 'DR. DERIVA', 'n' => 2]], $r->json('derivantes'));
        $this->assertSame(['con_dni' => 2, 'vinieron' => 1], $r->json('asistencia'));
    }
}
