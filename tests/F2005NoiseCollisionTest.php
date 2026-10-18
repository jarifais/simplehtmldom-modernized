<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/librerias/fase1/simple_html_dom.php';

final class F2005NoiseCollisionTest extends TestCase
{
    private const NOMINAL = '___noise___1000';
    private const FIRST = '___noise___ 1000';
    private const UNKNOWN = '___noise___ 9999';

    private function parse(string $html, bool $stripRN = true)
    {
        return str_get_html(
            $html,
            true,
            true,
            DEFAULT_TARGET_CHARSET,
            $stripRN
        );
    }

    public function testLiteralTextAttributesAndRepetitions(): void
    {
        $dom = $this->parse(
            '<div id="x" data-noise="'
            . self::NOMINAL
            . '">'
            . self::NOMINAL . '|'
            . self::NOMINAL . '|'
            . self::NOMINAL
            . '</div>'
        );

        $node = $dom->find('#x', 0);

        self::assertNotNull($node);
        self::assertSame(
            self::NOMINAL,
            $node->getAttribute('data-noise')
        );
        self::assertSame(
            3,
            substr_count($node->innertext, self::NOMINAL)
        );
        self::assertSame(
            '5f5f5f6e6f6973655f5f5f31303030',
            bin2hex(self::NOMINAL)
        );
        self::assertSame(15, strlen(self::NOMINAL));
    }

    public function testFirstCandidateAndProtectedNodes(): void
    {
        $pre = "p\n" . self::NOMINAL;

        $dom = $this->parse(
            '<div id="x">' . self::FIRST . '</div>'
            . '<script>one</script>'
            . '<script>two</script>'
            . '<code>c' . self::NOMINAL . '</code>'
            . '<pre>' . $pre . '</pre>'
        );

        self::assertSame(
            self::FIRST,
            $dom->find('#x', 0)->innertext
        );
        self::assertSame(
            'one',
            $dom->find('script', 0)->innertext
        );
        self::assertSame(
            'two',
            $dom->find('script', 1)->innertext
        );
        self::assertSame(
            'c' . self::NOMINAL,
            $dom->find('code', 0)->innertext
        );
        self::assertSame(
            $pre,
            $dom->find('pre', 0)->innertext
        );
    }

    public function testNoiseMapRemainsReusable(): void
    {
        $value = "  line 1\n    line 2  ";

        $dom = $this->parse(
            '<pre id="p">' . $value . '</pre>',
            true
        );

        $node = $dom->find('#p', 0);

        self::assertNotNull($node);
        self::assertSame($value, $node->innertext);
        self::assertSame(
            '<pre id="p">' . $value . '</pre>',
            $node->outertext
        );
        self::assertSame($value, $node->innertext);
        self::assertSame(
            '<pre id="p">' . $value . '</pre>',
            $node->outertext
        );
    }

    public function testUnknownIncompleteAndSimilarMarkers(): void
    {
        $incomplete = 'prefix|___noise___';

        $similar =
            '___noise__1000|'
            . '___noise___99999|'
            . 'x___noise___y';

        $dom = $this->parse(
            '<div id="unknown">'
            . self::UNKNOWN
            . '</div>'
            . '<div id="incomplete">'
            . $incomplete
            . '</div>'
            . '<div id="similar">'
            . $similar
            . '</div>'
            . '<script>valid</script>'
        );

        self::assertSame(
            self::UNKNOWN,
            $dom->find('#unknown', 0)->innertext
        );
        self::assertSame(
            $incomplete,
            $dom->find('#incomplete', 0)->innertext
        );
        self::assertSame(
            $similar,
            $dom->find('#similar', 0)->innertext
        );
        self::assertSame(
            'valid',
            $dom->find('script', 0)->innertext
        );
    }

    public function testPreviousPhaseRegressions(): void
    {
        $siblings = $this->parse(
            '<div id="a"></div><span id="b"></span>'
        );

        self::assertSame(
            'b',
            $siblings->find('div ~ span', 0)->id
        );

        $language = $this->parse(
            '<p id="x" lang="en-US">ok</p>'
        );

        self::assertSame(
            'x',
            $language->find('[lang|=en]', 0)->id
        );

        $attributes = $this->parse(
            '<div id="x" data-a="1">ok</div>'
        );

        $node = $attributes->find('#x', 0);
        $node->removeAttribute('data-a');

        self::assertFalse(
            $node->hasAttribute('data-a')
        );

        self::assertStringNotContainsString(
            'data-a=',
            $node->outertext
        );

        $lf = "a\n\nb";
        $crlf = "a\r\n\r\nb";

        $lfDom = $this->parse(
            '<pre id="x">' . $lf . '</pre>',
            true
        );

        $crlfDom = $this->parse(
            '<pre id="x">' . $crlf . '</pre>',
            true
        );

        self::assertSame(
            $lf,
            $lfDom->find('#x', 0)->innertext
        );

        self::assertSame(
            '<pre id="x">' . $lf . '</pre>',
            $lfDom->find('#x', 0)->outertext
        );

        self::assertSame(
            $crlf,
            $crlfDom->find('#x', 0)->innertext
        );

        self::assertSame(
            '<pre id="x">' . $crlf . '</pre>',
            $crlfDom->find('#x', 0)->outertext
        );
    }

    public function testStripRnScriptCodeAndPre(): void
    {
        $outside = "a\r\nb";

        self::assertSame(
            'a  b',
            $this->parse(
                '<div id="x">' . $outside . '</div>',
                true
            )->find('#x', 0)->innertext
        );

        self::assertSame(
            $outside,
            $this->parse(
                '<div id="x">' . $outside . '</div>',
                false
            )->find('#x', 0)->innertext
        );

        $script =
            'if (a < b) { x = "'
            . self::NOMINAL
            . '"; }';

        $code = 'a < b && ' . self::NOMINAL;
        $pre = "a\n\t" . self::NOMINAL . "\n";

        $dom = $this->parse(
            '<script>' . $script . '</script>'
            . '<code>' . $code . '</code>'
            . '<pre>' . $pre . '</pre>'
        );

        self::assertSame(
            $script,
            $dom->find('script', 0)->innertext
        );

        self::assertSame(
            $code,
            $dom->find('code', 0)->innertext
        );

        self::assertSame(
            $pre,
            $dom->find('pre', 0)->innertext
        );

        self::assertSame(
            '<pre>' . $pre . '</pre>',
            $dom->find('pre', 0)->outertext
        );
    }

    public function testEndpoint(): void
    {
        $base = getenv('SHD_HTTP_BASE');

        if ($base === false || $base === '') {
            $base = 'http://127.0.0.1:8080';
        }

        $dom = file_get_html(
            rtrim((string)$base, '/') . '/noise',
            false
        );

        self::assertNotFalse($dom);

        $node = $dom->find('#literal-noise', 0);

        self::assertNotNull($node);

        self::assertSame(
            self::NOMINAL,
            $node->plaintext
        );
    }

    public function testKOrderAndActivation(): void
    {
        $source = (string)file_get_contents(
            dirname(__DIR__)
            . '/pruebas/casos/todos.php'
        );

        preg_match_all(
            '/["\x27]id["\x27]\s*=>\s*'
            . '["\x27](K-00[1-5])["\x27]/',
            $source,
            $matches
        );

        self::assertSame(
            [
                'K-001',
                'K-002',
                'K-003',
                'K-004',
                'K-005',
            ],
            array_values($matches[1])
        );

        self::assertSame(
            1,
            preg_match(
                '/["\x27]id["\x27]\s*=>\s*'
                . '["\x27]K-005["\x27]'
                . '[\s\S]*?["\x27]category["\x27]'
                . '\s*=>\s*["\x27]phase2["\x27]'
                . '[\s\S]*?["\x27]mandatory["\x27]'
                . '\s*=>\s*true'
                . '[\s\S]*?["\x27]known_failure["\x27]'
                . '\s*=>\s*false/',
                $source
            )
        );
    }

    public function testLegacyDiagnosticsAreAbsent(): void
    {
        $source = (string)file_get_contents(
            dirname(__DIR__)
            . '/librerias/fase1/simple_html_dom.php'
        );

        self::assertStringNotContainsString(
            'UNDEFINED NOISE FOR KEY:',
            $source
        );

        self::assertStringNotContainsString(
            'NO NUMERIC NOISE KEY',
            $source
        );
    }
}
