<?php

namespace Tests\Unit;

use App\Services\HtmlSeguro;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests del sanitizador de los procedimientos.
 *
 * Es el único punto donde el sistema acepta HTML de un navegador y lo vuelve a
 * mostrar, así que acá se concentra todo el riesgo de XSS de la feature.
 *
 * Unitario puro: sin base de datos ni bootstrap de Laravel.
 * Correr con: docker exec -u www-data crecer-web-1 php artisan test --filter=HtmlSeguro
 */
class HtmlSeguroTest extends TestCase
{
    // ── Lo que hay que dejar pasar ────────────────────────────

    public function test_conserva_el_formato_basico(): void
    {
        $this->assertSame(
            '<p>Pedir el <strong>DNI</strong> y verificar en <em>Omnia</em>.</p>',
            HtmlSeguro::limpiar('<p>Pedir el <strong>DNI</strong> y verificar en <em>Omnia</em>.</p>')
        );
    }

    public function test_conserva_listas(): void
    {
        $html = '<ul><li>Uno</li><li>Dos</li></ul>';
        $this->assertSame($html, HtmlSeguro::limpiar($html));

        $ord = '<ol><li>Primero</li><li>Segundo</li></ol>';
        $this->assertSame($ord, HtmlSeguro::limpiar($ord));
    }

    public function test_conserva_acentos_y_entidades(): void
    {
        $salida = HtmlSeguro::limpiar('<p>ñandú áéíóü &amp; &lt;</p>');
        $this->assertStringContainsString('ñandú áéíóü', $salida);
        $this->assertStringContainsString('&amp;', $salida);
        $this->assertStringContainsString('&lt;', $salida);
    }

    public function test_normaliza_los_tags_que_emite_execcommand(): void
    {
        // Chrome emite <b>/<i> en vez de <strong>/<em>, y <div> por cada línea.
        $this->assertSame('<strong>a</strong><em>b</em>', HtmlSeguro::limpiar('<b>a</b><i>b</i>'));
        $this->assertSame('<p>linea</p>', HtmlSeguro::limpiar('<div>linea</div>'));
    }

    public function test_no_genera_parrafos_anidados(): void
    {
        // <div><p>…</p></div> con div→p produciría <p><p>…</p></p>, que es HTML
        // inválido: el parser lo reacomoda y la salida deja de ser estable.
        $this->assertSame('<p>a</p>', HtmlSeguro::limpiar('<div><p>a</p></div>'));
        $this->assertSame('<p>a</p>', HtmlSeguro::limpiar('<div><div><p>a</p></div></div>'));
    }

    public function test_link_externo_queda_seguro(): void
    {
        $salida = HtmlSeguro::limpiar('<a href="https://omniasalud.com">portal</a>');

        $this->assertStringContainsString('href="https://omniasalud.com"', $salida);
        $this->assertStringContainsString('target="_blank"', $salida);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $salida);
    }

    public function test_link_interno_no_lleva_target(): void
    {
        $salida = HtmlSeguro::limpiar('<a href="/v2/procedimientos/12">ver</a>');

        $this->assertStringContainsString('href="/v2/procedimientos/12"', $salida);
        $this->assertStringNotContainsString('target', $salida);
    }

    public function test_imagen_de_adjunto_propio_pasa(): void
    {
        $html = '<img src="/procedimientos/adjunto/7" alt="captura">';
        $this->assertStringContainsString('src="/procedimientos/adjunto/7"', HtmlSeguro::limpiar($html));
    }

    // ── Lo que hay que bloquear ───────────────────────────────

    #[DataProvider('vectoresXss')]
    public function test_bloquea_vectores_de_xss(string $entrada, string $noDebeAparecer): void
    {
        $salida = HtmlSeguro::limpiar($entrada);

        $this->assertStringNotContainsStringIgnoringCase(
            $noDebeAparecer,
            $salida,
            'El sanitizador dejó pasar: ' . $entrada
        );
    }

    public static function vectoresXss(): array
    {
        return [
            'script suelto'        => ['<script>alert(1)</script>Hola', 'alert'],
            'script anidado'       => ['<div><p><script>alert(1)</script></p></div>', 'alert'],
            'svg con script'       => ['<svg><script>alert(1)</script></svg>', 'alert'],
            'img con onerror'      => ['<img src=x onerror=alert(1)>', 'onerror'],
            'onclick en p'         => ['<p onclick="robar()">a</p>', 'onclick'],
            'onload en body'       => ['<body onload="alert(1)">a</body>', 'onload'],
            'href javascript'      => ['<a href="javascript:alert(1)">x</a>', 'javascript'],
            'href js con tab'      => ['<a href="jav&#x09;ascript:alert(1)">x</a>', 'javascript'],
            'href js mayusculas'   => ['<a href="  JaVaScRiPt:alert(1)">x</a>', 'javascript'],
            'href js entidad'      => ['<a href="&#106;avascript:alert(1)">x</a>', 'javascript'],
            'href data html'       => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'data:'],
            'href protocolo rel'   => ['<a href="//evil.com">x</a>', 'evil.com'],
            'href vbscript'        => ['<a href="vbscript:msgbox(1)">x</a>', 'vbscript'],
            'style con url js'     => ['<p style="background:url(javascript:1)">a</p>', 'javascript'],
            'iframe'               => ['<iframe src="//x"></iframe>', 'iframe'],
            'form con input'       => ['<form action="/robar"><input name="pass"></form>', 'input'],
            'img externa'          => ['<img src="https://tracker.com/p.gif">', 'tracker.com'],
            'img data uri'         => ['<img src="data:image/svg+xml;base64,PHN2Zz4=">', 'data:'],
            'meta refresh'         => ['<meta http-equiv="refresh" content="0;url=//evil.com">', 'evil.com'],
            'object'               => ['<object data="x.swf"></object>', 'object'],
            'link stylesheet'      => ['<link rel="stylesheet" href="//evil.com/x.css">', 'evil.com'],
        ];
    }

    public function test_el_texto_del_script_no_queda_visible(): void
    {
        // Desenvolver un <script> en vez de borrarlo volcaría el código como
        // texto plano a la vista de todos.
        $this->assertSame('Hola', HtmlSeguro::limpiar('<script>alert(1)</script>Hola'));
    }

    public function test_link_rechazado_conserva_el_texto(): void
    {
        // El <a> cae pero lo que la persona escribió no se pierde.
        $this->assertSame('clic acá', HtmlSeguro::limpiar('<a href="javascript:alert(1)">clic acá</a>'));
    }

    public function test_desenvuelve_tags_desconocidos_conservando_texto(): void
    {
        $this->assertSame('hola', HtmlSeguro::limpiar('<span style="font-weight:bold">hola</span>'));
        $this->assertSame('hola', HtmlSeguro::limpiar('<font color="red">hola</font>'));
        $this->assertSame('<p>a</p>', HtmlSeguro::limpiar('<article><p>a</p></article>'));
    }

    public function test_borra_atributos_no_permitidos(): void
    {
        $salida = HtmlSeguro::limpiar('<p class="x" id="y" data-foo="z" style="color:red">a</p>');
        $this->assertSame('<p>a</p>', $salida);
    }

    public function test_ignora_comentarios(): void
    {
        $this->assertSame('<p>a</p>', HtmlSeguro::limpiar('<!-- comentario --><p>a</p>'));
    }

    public function test_no_lee_archivos_por_xxe(): void
    {
        $salida = HtmlSeguro::limpiar(
            '<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><p>&xxe;</p>'
        );
        $this->assertStringNotContainsString('root:', $salida);
    }

    // ── Límites ───────────────────────────────────────────────

    public function test_rechaza_contenido_gigante(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HtmlSeguro::limpiar('<p>' . str_repeat('a', 130000) . '</p>');
    }

    public function test_soporta_anidamiento_patologico(): void
    {
        $salida = HtmlSeguro::limpiar(str_repeat('<div>', 60) . 'fondo' . str_repeat('</div>', 60));
        $this->assertStringContainsString('fondo', $salida);
    }

    public function test_vacio_devuelve_vacio(): void
    {
        $this->assertSame('', HtmlSeguro::limpiar(''));
        $this->assertSame('', HtmlSeguro::limpiar('   '));
    }

    public function test_es_idempotente(): void
    {
        foreach (self::vectoresXss() as $caso) {
            $unaVez = HtmlSeguro::limpiar($caso[0]);
            $this->assertSame($unaVez, HtmlSeguro::limpiar($unaVez), 'No es idempotente: ' . $caso[0]);
        }
    }

    // ── aTexto ────────────────────────────────────────────────

    public function test_a_texto_saca_el_markup(): void
    {
        $txt = HtmlSeguro::aTexto('<p>Pedir el <strong>DNI</strong></p><ul><li>Uno</li></ul>');

        $this->assertStringNotContainsString('strong', $txt);
        $this->assertStringNotContainsString('<', $txt);
        $this->assertStringContainsString('Pedir el DNI', $txt);
        $this->assertStringContainsString('Uno', $txt);
    }

    public function test_a_texto_normaliza_espacios(): void
    {
        $this->assertSame('a b', HtmlSeguro::aTexto("<p>a</p>\n\n   <p>b</p>"));
    }

    // ── hrefValido ────────────────────────────────────────────

    public function test_href_valido_acepta_lo_esperado(): void
    {
        $this->assertNotNull(HtmlSeguro::hrefValido('https://omniasalud.com/turnos?x=1'));
        $this->assertNotNull(HtmlSeguro::hrefValido('http://192.168.1.115/algo'));
        $this->assertNotNull(HtmlSeguro::hrefValido('mailto:info@crecer.com'));
        $this->assertNotNull(HtmlSeguro::hrefValido('tel:+542235997247'));
        $this->assertNotNull(HtmlSeguro::hrefValido('/v2/procedimientos/3'));
        $this->assertNotNull(HtmlSeguro::hrefValido('#paso-2'));
    }

    public function test_href_valido_rechaza_rutas_arbitrarias(): void
    {
        $this->assertNull(HtmlSeguro::hrefValido('/etc/passwd'));
        $this->assertNull(HtmlSeguro::hrefValido('/admin/usuarios'));
        $this->assertNull(HtmlSeguro::hrefValido('ftp://x.com'));
        $this->assertNull(HtmlSeguro::hrefValido(''));
    }
}
