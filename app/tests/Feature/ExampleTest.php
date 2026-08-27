<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz no sirve contenido: reparte según el rol de quien entra
     * (routes/web.php:23). Sin sesión, manda al login.
     *
     * El test de stock esperaba un 200 acá y venía fallando desde el commit
     * inicial, dejando la suite siempre en rojo.
     */
    public function test_la_raiz_redirige_al_login_sin_sesion(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_el_login_responde(): void
    {
        $this->get('/login')->assertStatus(200);
    }
}
