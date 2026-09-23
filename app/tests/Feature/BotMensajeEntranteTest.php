<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\MensajeWA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regresión del incidente 13/07 → 23/09: 84 mensajes entrantes perdidos.
 *
 * El sync de avatar corría inline y ANTES de guardar el mensaje. Cuando el bot
 * tardaba en /profile-pic, el warning del timeout iba a un log con dueño root
 * (lo creaba un `docker exec` de las tareas de las 04:00): Log::warning tiraba
 * excepción → 500 → el bot reintentaba 3 veces y daba el mensaje por PERDIDO.
 *
 * Correr: docker exec -u www-data crecer-web-1 php artisan test --filter=BotMensajeEntrante
 */
class BotMensajeEntranteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.bot_token' => 'token-de-prueba']);
        // Con QUEUE_CONNECTION=sync el resumen LLM correría adentro del request.
        Queue::fake();
        Contacto::create(['telefono' => '5492235550000', 'wa_id' => '5492235550000@c.us', 'nombre' => 'Paciente Prueba']);
    }

    private function entrante(string $waId)
    {
        return $this->withToken('token-de-prueba')->postJson('/api/bot/mensajes', [
            'contacto'  => '5492235550000@c.us',
            'area'      => 'administracion',
            'tipo'      => 'texto',
            'contenido' => 'Hola, quería consultar por un turno',
            'wa_id'     => $waId,
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function test_el_entrante_se_guarda_aunque_fallen_el_avatar_y_el_log(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        // Lo que pasaba con el log de root: cualquier escritura explota.
        Log::shouldReceive('warning')->once()->andThrow(new \UnexpectedValueException('log no escribible'));

        $this->entrante('false_5492235550000@c.us_AAA')->assertCreated();

        $this->assertDatabaseHas('mensajes_wa', ['wa_id' => 'false_5492235550000@c.us_AAA', 'direccion' => 'entrante']);
    }

    public function test_el_avatar_se_pide_al_bot_del_area_una_sola_vez_por_rafaga(): void
    {
        Http::fake(['*' => Http::response(['ok' => false], 503)]);

        $this->entrante('false_5492235550000@c.us_AAA')->assertCreated();
        $this->entrante('false_5492235550000@c.us_BBB')->assertCreated();

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === 'http://bot-administracion:3002/profile-pic');
        $this->assertSame(2, MensajeWA::count());
    }

    public function test_el_log_diario_se_crea_escribible_para_web_y_worker(): void
    {
        // Lo crea el primero que escribe, a veces root (docker exec): tiene
        // que quedar escribible para www-data.
        $this->assertSame(0666, config('logging.channels.daily.permission'));
    }
}
