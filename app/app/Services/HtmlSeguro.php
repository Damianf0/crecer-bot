<?php

namespace App\Services;

/**
 * Sanitizador de HTML para el contenido de los procedimientos.
 *
 * El editor del panel es un contenteditable: lo que manda el navegador NO es
 * confiable (execCommand emite markup impredecible, y pegar desde Word o desde
 * una página web arrastra cualquier cosa). Todo lo que se guarda pasa por acá.
 *
 * Criterio: WHITELIST, nunca blacklist. Un tag que no está en PERMITIDOS se
 * desenvuelve — desaparece el elemento y sobreviven sus hijos —, salvo los de
 * ELIMINAR, que se borran con todo su subárbol (desenvolver un <script>
 * volcaría el código como texto visible).
 *
 * Se invoca UNA sola vez, al guardar. Lo que queda en la base ya está limpio y
 * se renderiza sin re-sanitizar.
 */
class HtmlSeguro
{
    /** Tag permitido => atributos permitidos en ese tag. */
    private const PERMITIDOS = [
        'p'      => [],
        'br'     => [],
        'strong' => [],
        'em'     => [],
        'u'      => [],
        'ul'     => [],
        'ol'     => [],
        'li'     => [],
        'a'      => ['href', 'target', 'rel'],
        'img'    => ['src', 'alt'],
    ];

    /**
     * Se normalizan antes de mirar la whitelist. `execCommand` emite <b> e <i>
     * en vez de <strong>/<em>, y <div> por cada salto de línea.
     */
    private const RENOMBRAR = [
        'b'      => 'strong',
        'i'      => 'em',
        'div'    => 'p',
        'ins'    => 'u',
        'h1'     => 'p', 'h2' => 'p', 'h3' => 'p',
        'h4'     => 'p', 'h5' => 'p', 'h6' => 'p',
    ];

    /** Se borran con su contenido: desenvolverlos dejaría el código a la vista. */
    private const ELIMINAR = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'textarea', 'select', 'button', 'svg', 'math', 'link', 'meta', 'base',
        'noscript', 'template', 'title', 'head', 'audio', 'video', 'source',
        'track', 'canvas', 'applet', 'frame', 'frameset', 'marquee',
    ];

    /** Esquemas que se rechazan de plano en un href. */
    private const ESQUEMAS_VETADOS = [
        'javascript:', 'data:', 'vbscript:', 'file:', 'about:',
        'blob:', 'jar:', 'view-source:',
    ];

    private const MAX_LEN   = 120000;
    private const MAX_PROF  = 25;

    /**
     * HTML sucio -> HTML limpio.
     *
     * @throws \InvalidArgumentException si el contenido excede MAX_LEN.
     */
    public static function limpiar(string $html): string
    {
        $html = trim($html);
        if ($html === '') return '';

        if (mb_strlen($html) > self::MAX_LEN) {
            throw new \InvalidArgumentException('El contenido es demasiado largo.');
        }

        $prev = libxml_use_internal_errors(true);
        $doc  = new \DOMDocument('1.0', 'UTF-8');

        // El prólogo XML fija el encoding sin recurrir a mb_convert_encoding con
        // 'HTML-ENTITIES', que está deprecado en PHP 8.2. El div raíz da un
        // contenedor estable del que después se serializan solo los hijos.
        $ok = $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="cr-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$ok) return '';

        $root = $doc->getElementById('cr-root');
        if (!$root) {
            // getElementById puede fallar sin DTD; buscamos el div a mano.
            foreach ($doc->childNodes as $n) {
                if ($n instanceof \DOMElement && $n->nodeName === 'div') { $root = $n; break; }
            }
        }
        if (!$root) return '';

        self::procesarHijos($root, 0);

        $salida = '';
        foreach ($root->childNodes as $hijo) {
            $salida .= $doc->saveHTML($hijo);
        }

        return trim($salida);
    }

    /**
     * HTML limpio -> texto plano, para la columna de búsqueda.
     * Se corre sobre el resultado de limpiar(), no sobre el HTML crudo.
     */
    public static function aTexto(string $htmlLimpio): string
    {
        $conEspacios = str_replace(
            ['</p>', '<br>', '<br/>', '<br />', '</li>', '</ul>', '</ol>'],
            ' ',
            $htmlLimpio
        );

        $txt = html_entity_decode(strip_tags($conEspacios), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $txt = preg_replace('/\s+/u', ' ', $txt);

        return mb_substr(trim($txt), 0, 8000);
    }

    /**
     * Valida un href. Devuelve el href original (trimeado) si pasa, o null si
     * hay que rechazarlo.
     *
     * La detección de esquema NO se hace sobre el string tal cual: primero se
     * decodifican entidades y se sacan los caracteres de control, porque
     * `jav&#x09;ascript:alert(1)` y `java\nscript:` son vectores reales que el
     * navegador interpreta pero un match ingenuo deja pasar.
     */
    public static function hrefValido(string $href): ?string
    {
        $orig = trim($href);
        if ($orig === '') return null;

        $probe = html_entity_decode($orig, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $probe = preg_replace('/[\x00-\x20\x7F\xA0]+/u', '', $probe);
        $probe = mb_strtolower((string) $probe);

        foreach (self::ESQUEMAS_VETADOS as $veto) {
            if (str_starts_with($probe, $veto)) return null;
        }

        // Protocol-relative: //evil.com hereda el esquema y sale del sitio.
        if (str_starts_with($probe, '//')) return null;

        // Absolutos http(s) con host real
        if (str_starts_with($probe, 'http://') || str_starts_with($probe, 'https://')) {
            return parse_url($orig, PHP_URL_HOST) ? $orig : null;
        }

        if (str_starts_with($probe, 'mailto:')) {
            $mail = substr($probe, 7);
            return filter_var($mail, FILTER_VALIDATE_EMAIL) ? $orig : null;
        }

        if (preg_match('/^tel:\+?[0-9\-\s()]{5,25}$/', $probe)) return $orig;

        // Internos: otro procedimiento, un adjunto, o un ancla de la misma página
        if (preg_match('#^/v2/procedimientos/\d+$#', $probe))                  return $orig;
        if (preg_match('#^/procedimientos/adjunto/\d+(/descargar)?$#', $probe)) return $orig;
        if (preg_match('/^#[a-z0-9_-]{1,50}$/', $probe))                        return $orig;

        return null;
    }

    /** Los adjuntos se sirven por controller autenticado: nada externo, nada data:. */
    public static function srcValido(string $src): ?string
    {
        $s = trim($src);
        return preg_match('#^/procedimientos/adjunto/\d+$#', $s) ? $s : null;
    }

    // ── Interno ───────────────────────────────────────────────

    /**
     * @param bool $dentroP Si ya venimos dentro de un <p>. Un <p> anidado en
     *                      otro <p> es HTML inválido: el parser lo reacomoda en
     *                      la siguiente lectura y el sanitizador dejaría de ser
     *                      idempotente. Por eso se desenvuelve.
     */
    private static function procesarHijos(\DOMNode $nodo, int $prof, bool $dentroP = false): void
    {
        // Snapshot obligatorio: iterar el DOMNodeList vivo mientras se remueven
        // nodos saltea elementos. Es EL bug clásico de estos sanitizadores.
        foreach (iterator_to_array($nodo->childNodes) as $hijo) {

            if ($hijo instanceof \DOMText) continue;

            // Comentarios, CDATA, processing instructions: fuera.
            if (!($hijo instanceof \DOMElement)) {
                $hijo->parentNode?->removeChild($hijo);
                continue;
            }

            $original = strtolower($hijo->nodeName);
            $tag      = self::RENOMBRAR[$original] ?? $original;

            if (in_array($tag, self::ELIMINAR, true) || in_array($original, self::ELIMINAR, true)) {
                $hijo->parentNode?->removeChild($hijo);
                continue;
            }

            // Un <p> dentro de otro <p> no existe en HTML: se desenvuelve para
            // que la salida sea válida y estable entre pasadas.
            $pAnidado = ($tag === 'p' && $dentroP);

            if ($prof >= self::MAX_PROF || !isset(self::PERMITIDOS[$tag]) || $pAnidado) {
                self::procesarHijos($hijo, $prof + 1, $dentroP);
                self::desenvolver($hijo);
                continue;
            }

            if ($tag !== $original) {
                $hijo = self::renombrar($hijo, $tag);
                if (!$hijo) continue;
            }

            if (!self::limpiarAtributos($hijo, $tag)) {
                // El elemento no sobrevive a la validación de sus atributos
                // (ej: <a> con javascript:, <img> con src externo).
                if ($tag === 'img') {
                    $hijo->parentNode?->removeChild($hijo);
                } else {
                    self::procesarHijos($hijo, $prof + 1, $dentroP);
                    self::desenvolver($hijo);
                }
                continue;
            }

            self::procesarHijos($hijo, $prof + 1, $dentroP || $tag === 'p');
        }
    }

    /**
     * Deja solo los atributos permitidos y valida los que apuntan a algún lado.
     * Devuelve false si el elemento debe caer.
     */
    private static function limpiarAtributos(\DOMElement $el, string $tag): bool
    {
        $permitidos = self::PERMITIDOS[$tag];

        // Snapshot: quitar atributos mientras se itera $el->attributes los saltea.
        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            if (!in_array(strtolower($attr->nodeName), $permitidos, true)) {
                $el->removeAttribute($attr->nodeName);
            }
        }

        if ($tag === 'a') {
            $href = self::hrefValido($el->getAttribute('href'));
            if ($href === null) return false;

            $el->setAttribute('href', $href);

            // Externo: se abre aparte y sin pasarle el opener (tabnabbing).
            if (preg_match('#^https?://#i', $href)) {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener noreferrer nofollow');
            } else {
                $el->removeAttribute('target');
                $el->removeAttribute('rel');
            }
        }

        if ($tag === 'img') {
            $src = self::srcValido($el->getAttribute('src'));
            if ($src === null) return false;
            $el->setAttribute('src', $src);
        }

        return true;
    }

    /** Reemplaza el elemento por otro con el tag nuevo, conservando los hijos. */
    private static function renombrar(\DOMElement $el, string $tagNuevo): ?\DOMElement
    {
        $doc    = $el->ownerDocument;
        $padre  = $el->parentNode;
        if (!$doc || !$padre) return null;

        $nuevo = $doc->createElement($tagNuevo);

        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $nuevo->setAttribute($attr->nodeName, $attr->nodeValue);
        }
        foreach (iterator_to_array($el->childNodes) as $hijo) {
            $nuevo->appendChild($hijo);
        }

        $padre->replaceChild($nuevo, $el);

        return $nuevo;
    }

    /** Saca el elemento y sube sus hijos al lugar que ocupaba. */
    private static function desenvolver(\DOMElement $el): void
    {
        $padre = $el->parentNode;
        if (!$padre) return;

        foreach (iterator_to_array($el->childNodes) as $hijo) {
            $padre->insertBefore($hijo, $el);
        }

        $padre->removeChild($el);
    }
}
