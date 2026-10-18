<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class F2004PrePreservationTest extends TestCase
{
    private function dom(
        string $html,
        bool $strip = true
    ): simple_html_dom {
        require_once dirname(__DIR__)
            . '/librerias/fase1/simple_html_dom.php';

        return str_get_html(
            $html,
            true,
            true,
            DEFAULT_TARGET_CHARSET,
            $strip
        );
    }

    private static function ids(array $nodes): array
    {
        return array_map(
            static fn($node): string =>
                (string)$node->id,
            $nodes
        );
    }

    public function testEndpoint(): void
    {
        require_once dirname(__DIR__)
            . '/librerias/fase1/simple_html_dom.php';

        $base = getenv('SHD_HTTP_BASE')
            ?: 'http://127.0.0.1:8080';

        $context = stream_context_create([
            'http'=>[
                'timeout'=>5,
            ],
            'ssl'=>[
                'verify_peer'=>false,
                'verify_peer_name'=>false,
                'allow_self_signed'=>true,
            ],
        ]);

        $dom = file_get_html(
            rtrim($base, '/').'/noise',
            false,
            $context
        );

        self::assertInstanceOf(
            simple_html_dom::class,
            $dom
        );

        $pre = $dom->find('#pre-one', 0);
        $content = "  línea 1\n    línea 2  ";

        self::assertSame($content, $pre->innertext);

        self::assertSame(
            '<pre id="pre-one">'
            . $content
            . '</pre>',
            $pre->outertext
        );

        self::assertSame($content, $pre->plaintext);

        $dom->clear();
    }

    public function testWhitespaceAndLineEndings(): void
    {
        $dom = $this->dom(
            '<pre id="lf">  A'."\n".'    B  </pre>'
            . '<pre id="crlf">C'."\r\n".'D</pre>'
            . '<pre id="ws">'." \tA\n\nB\t ".'</pre>'
        );

        self::assertSame(
            "  A\n    B  ",
            $dom->find('#lf', 0)->innertext
        );

        self::assertSame(
            "C\r\nD",
            $dom->find('#crlf', 0)->innertext
        );

        self::assertSame(
            " \tA\n\nB\t ",
            $dom->find('#ws', 0)->innertext
        );

        $dom->clear();
    }

    public function testMultipleUppercaseAttributesAndEmpty(): void
    {
        $dom = $this->dom(
            '<PRE class="x" data-a="1" id="upper">U'
            . "\n"
            . '1</PRE>'
            . '<pre id="second">V'."\n".'2</pre>'
            . '<pre id="empty"></pre>'
        );

        self::assertSame(
            "U\n1",
            $dom->find('#upper', 0)->innertext
        );

        self::assertSame(
            "V\n2",
            $dom->find('#second', 0)->innertext
        );

        self::assertSame(
            'x',
            $dom->find('#upper', 0)->class
        );

        self::assertSame(
            '',
            $dom->find('#empty', 0)->innertext
        );

        $dom->clear();
    }

    public function testStripRnScope(): void
    {
        $dom = $this->dom(
            '<div id="outside">A'."\n".'B</div>'
            . '<pre id="inside">C'."\n".'D</pre>'
        );

        self::assertSame(
            'A B',
            $dom->find('#outside', 0)->innertext
        );

        self::assertSame(
            "C\nD",
            $dom->find('#inside', 0)->innertext
        );

        $dom->clear();

        $dom = $this->dom(
            '<div id="outside">A'."\n".'B</div>'
            . '<pre id="inside">C'."\r\n".'D</pre>',
            false
        );

        self::assertSame(
            "A\nB",
            $dom->find('#outside', 0)->innertext
        );

        self::assertSame(
            "C\r\nD",
            $dom->find('#inside', 0)->innertext
        );

        $dom->clear();
    }

    public function testPreviousFixes(): void
    {
        $dom = $this->dom(
            '<div>'
            . '<p id="p1"></p>'
            . '<span></span>'
            . '<p id="p2"></p>'
            . '<p id="p3"></p>'
            . '</div>'
        );

        self::assertSame(
            ['p2', 'p3'],
            self::ids($dom->find('#p1 ~ p'))
        );

        $dom->clear();

        $dom = $this->dom(
            '<div id="en" lang="en"></div>'
            . '<div id="en-us" lang="en-US"></div>'
            . '<div id="english" lang="english"></div>'
        );

        self::assertSame(
            ['en', 'en-us'],
            self::ids($dom->find('[lang|=en]'))
        );

        $dom->clear();

        $dom = $this->dom(
            '<div id="x" data-a="1"></div>'
        );

        $node = $dom->find('#x', 0);
        $node->removeAttribute('data-a');

        self::assertFalse(
            $node->hasAttribute('data-a')
        );

        $dom->clear();
    }

    public function testK005IsActivated(): void
    {
        $source = (string)file_get_contents(
            dirname(__DIR__)
            . '/pruebas/casos/todos.php'
        );

        self::assertSame(
            1,
            preg_match(
                '/[\'"]id[\'"]\s*=>\s*[\'"]K-005[\'"]'
                . '.*?[\'"]known_failure[\'"]\s*=>\s*false/s',
                $source
            )
        );
    }
}