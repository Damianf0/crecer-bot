<?php

namespace Tests\Feature;

use App\Models\ConversacionWA;
use App\Models\FavoritoWA;
use App\Models\PreferenciaAviso;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Avisos del panel (06/10): viajan en /bot-pulso para que salten en cualquier
 * pantalla, y cada persona elige cuáles quiere en "Mis avisos".
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=Avisos
 */
class AvisosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $mail, string $rol = 'secretaria'): User
    {
        return User::create(['name' => $mail, 'nombre_completo' => ucfirst($mail), 'email' => "{$mail}@example.com",
            'password' => bcrypt('x'), 'rol' => $rol, 'activo' => true]);
    }

    private function como(User $u, array $colas = ['atencion'])
    {
        return $this->actingAs($u)->withSession(['colas' => $colas]);
    }

    private function conv(array $a): ConversacionWA
    {
        static $n = 0;
        return ConversacionWA::create($a + ['contacto' => (++$n) . '555@lid', 'area' => 'atencion', 'estado' => 'activa', 'nombre' => "Paciente {$n}"]);
    }

    public function test_preferencias_por_defecto_y_guardado(): void
    {
        $u = $this->usuario('ana');

        $r = $this->como($u)->getJson('/mis-avisos')->assertOk();
        $this->assertSame(PreferenciaAviso::DEFAULTS, $r->json('prefs'));
        $this->assertArrayHasKey('tarea_asignada', $r->json('etiquetas'));

        $this->como($u)->postJson('/mis-avisos', ['chat' => false, 'sonido' => false, 'inventado' => true])->assertOk();
        $prefs = PreferenciaAviso::para($u->id);
        $this->assertFalse($prefs['chat']);
        $this->assertFalse($prefs['sonido']);
        $this->assertTrue($prefs['conv_delegada']);
        $this->assertArrayNotHasKey('inventado', $prefs);

        $this->como($u)->postJson('/mis-avisos', ['chat' => 'quizás'])->assertStatus(422);
        // Las preferencias de una persona no tocan las de otra.
        $this->assertSame(PreferenciaAviso::DEFAULTS, PreferenciaAviso::para($this->usuario('beto')->id));
    }

    public function test_el_pulso_trae_lo_que_esta_en_mi(): void
    {
        Http::fake(['*' => Http::response(['status' => 'listo', 'has_qr' => false])]);
        $yo   = $this->usuario('ana');
        $otra = $this->usuario('beto');

        $mia     = $this->conv(['asignada_a' => $yo->id, 'resumen_llm' => 'Pide turno']);
        $deOtra  = $this->conv(['asignada_a' => $otra->id]);
        $tarea   = Tarea::create(['titulo' => 'Llamar al laboratorio', 'estado' => 'pendiente', 'prioridad' => 'normal',
                                  'asignada_a' => $yo->id, 'creada_por' => $otra->id]);
        Tarea::create(['titulo' => 'Ya hecha', 'estado' => 'completada', 'prioridad' => 'normal', 'asignada_a' => $yo->id, 'creada_por' => $otra->id]);
        Tarea::create(['titulo' => 'De otra', 'estado' => 'pendiente', 'prioridad' => 'normal', 'asignada_a' => $otra->id, 'creada_por' => $yo->id]);

        $fav       = $this->conv(['contacto' => '999@lid', 'no_leidos' => 2]);
        $favOtraCola = $this->conv(['contacto' => '888@lid', 'area' => 'ovodonacion', 'no_leidos' => 1]);
        FavoritoWA::create(['contacto' => '999@lid']);
        FavoritoWA::create(['contacto' => '888@lid']);
        FavoritoWA::limpiarCache();

        $urgente  = $this->conv(['urgente' => true, 'no_leidos' => 1]);
        $this->conv(['urgente' => true, 'no_leidos' => 1, 'asignada_a' => $otra->id]);   // ya tomada
        $this->conv(['urgente' => true, 'no_leidos' => 1, 'area' => 'administracion']);   // no es mi cola

        $a = $this->como($yo, ['atencion'])->getJson('/bot-pulso')->assertOk()->json('avisos');

        $this->assertSame([$mia->id], array_column($a['mis_convs'], 'id'));
        $this->assertSame('Pide turno', $a['mis_convs'][0]['resumen']);
        $this->assertSame([$tarea->id], array_column($a['mis_tareas'], 'id'));
        $this->assertSame('Beto', $a['mis_tareas'][0]['de']);
        $this->assertSame([$fav->id], array_column($a['favoritos'], 'id'));
        $this->assertSame(2, $a['favoritos'][0]['no_leidos']);
        $this->assertSame([$urgente->id], array_column($a['urgentes'], 'id'));
        $this->assertTrue($a['prefs']['urgente']);

        // Con la cola de ovodonación declarada aparece ese favorito.
        $a2 = $this->como($yo, ['atencion', 'ovodonacion'])->getJson('/bot-pulso')->json('avisos');
        $this->assertEqualsCanonicalizing([$fav->id, $favOtraCola->id], array_column($a2['favoritos'], 'id'));
    }

    public function test_una_tarea_que_me_asigne_yo_no_trae_de(): void
    {
        Http::fake(['*' => Http::response(['status' => 'listo'])]);
        $yo = $this->usuario('ana');
        Tarea::create(['titulo' => 'Recordatorio propio', 'estado' => 'pendiente', 'prioridad' => 'normal', 'asignada_a' => $yo->id, 'creada_por' => $yo->id]);

        $t = $this->como($yo)->getJson('/bot-pulso')->json('avisos.mis_tareas');
        $this->assertCount(1, $t);
        $this->assertNull($t[0]['de']);
    }
}
