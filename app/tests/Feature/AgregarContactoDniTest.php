<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agregar a contactos desde una conversación sin ficha con un DNI que ya
 * tiene otro contacto: mensaje claro (422), no error 500.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=AgregarContactoDni
 */
class AgregarContactoDniTest extends TestCase
{
    use RefreshDatabase;

    public function test_dni_repetido_avisa_quien_lo_tiene(): void
    {
        $u = User::create(['name' => 'S', 'nombre_completo' => 'Sec', 'email' => 's@example.com',
            'password' => bcrypt('x'), 'rol' => 'secretaria', 'activo' => true]);
        $sesion = fn() => $this->actingAs($u)->withSession(['colas' => ['atencion']]);

        Contacto::create(['nombre' => 'Ana Prueba', 'telefono' => '5492230000001', 'dni' => '30111222']);
        $conv = ConversacionWA::create(['contacto' => '999@lid', 'area' => 'atencion', 'estado' => 'activa']);

        // Con puntos también se reconoce como el mismo DNI.
        $sesion()->postJson("/atencion/conversacion/{$conv->id}/agregar-contacto", [
            'nombre' => 'Otra Persona', 'telefono' => '5492230000002', 'dni' => '30.111.222',
        ])->assertStatus(422)
          ->assertJsonPath('ok', false)
          ->assertJsonFragment(['error' => 'El DNI 30111222 ya está cargado en otro contacto: Ana Prueba (tel. 5492230000001). Revisá el DNI o dejalo vacío.']);

        $this->assertSame(1, Contacto::count());

        // Sin DNI se agrega bien.
        $sesion()->postJson("/atencion/conversacion/{$conv->id}/agregar-contacto", [
            'nombre' => 'Otra Persona', 'telefono' => '5492230000002',
        ])->assertOk()->assertJsonPath('vinculado', false);

        $this->assertSame(2, Contacto::count());
    }
}
