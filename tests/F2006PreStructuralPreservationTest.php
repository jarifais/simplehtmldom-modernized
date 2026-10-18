<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/librerias/fase1/simple_html_dom.php';

final class F2006PreStructuralPreservationTest extends TestCase
{
    private function parse(string $html, bool $stripRN = true): simple_html_dom
    {
        $dom = str_get_html(
            $html,
            true,
            true,
            DEFAULT_TARGET_CHARSET,
            $stripRN
        );

        self::assertInstanceOf(simple_html_dom::class, $dom);
        return $dom;
    }

    public static function lineEndingProvider(): array
    {
        return [
            'LF' => ["\n"],
            'CRLF' => ["\r\n"],
            'CR' => ["\r"],
        ];
    }

    #[DataProvider('lineEndingProvider')]
    public function testPreservesEveryLineEndingInsidePre(string $eol): void
    {
        $value = '  A' . $eol . '    B  ';
        $dom = $this->parse('<pre id="p">' . $value . '</pre>');
        $pre = $dom->find('#p', 0);

        self::assertNotNull($pre);
        self::assertSame($value, $pre->innertext);
        self::assertSame('<pre id="p">' . $value . '</pre>', $pre->outertext);
        self::assertSame($value, $pre->plaintext);
        $dom->clear();
    }

    public function testQuotedGreaterThanDoesNotTruncateOpeningTag(): void
    {
        $dom = $this->parse(
            '<pre id="p" data-double="a>b" data-single=\'c>d\''
            . ' data-entity="a&gt;b">texto</pre>'
        );
        $pre = $dom->find('#p', 0);

        self::assertNotNull($pre);
        self::assertSame('a>b', $pre->getAttribute('data-double'));
        self::assertSame('c>d', $pre->getAttribute('data-single'));
        self::assertSame('a&gt;b', $pre->getAttribute('data-entity'));
        self::assertSame('texto', $pre->innertext);
        self::assertStringContainsString('data-double="a>b"', $pre->outertext);
        self::assertStringContainsString("data-single='c>d'", $pre->outertext);
        $dom->clear();
    }

    public function testNestedElementsRemainStructuralAndSelectable(): void
    {
        $html = '<pre id="p">inicio <b id="b">negrita</b> '
            . '<span id="s">texto</span> '
            . '<a id="a" href="/x">enlace</a> fin</pre>';
        $dom = $this->parse($html);
        $pre = $dom->find('#p', 0);

        self::assertNotNull($pre);
        self::assertSame('negrita', $pre->find('b', 0)->innertext);
        self::assertSame('texto', $pre->find('span', 0)->innertext);
        self::assertSame('enlace', $pre->find('a', 0)->innertext);
        self::assertSame('/x', $pre->find('a', 0)->href);
        self::assertSame(
            'inicio <b id="b">negrita</b> <span id="s">texto</span> '
            . '<a id="a" href="/x">enlace</a> fin',
            $pre->innertext
        );
        self::assertStringNotContainsString('<b', $pre->plaintext);
        self::assertStringNotContainsString('<span', $pre->plaintext);
        self::assertStringNotContainsString('<a', $pre->plaintext);
        self::assertSame(
            'inicio negrita texto enlace fin',
            preg_replace('/\s+/', ' ', trim($pre->plaintext))
        );
        $dom->clear();
    }

    public function testChildCanBeModifiedAndSerialized(): void
    {
        $dom = $this->parse(
            '<pre id="p">x <b id="b">y</b>' . "\n" . '<span>z</span></pre>'
        );
        $child = $dom->find('#b', 0);

        self::assertNotNull($child);
        $child->innertext = 'Y';

        self::assertSame(
            'x <b id="b">Y</b>' . "\n" . '<span>z</span>',
            $dom->find('#p', 0)->innertext
        );
        self::assertSame(
            '<pre id="p">x <b id="b">Y</b>' . "\n" . '<span>z</span></pre>',
            $dom->find('#p', 0)->outertext
        );
        $dom->clear();
    }

    public function testMultipleUppercaseEmptyAndLiteralNoise(): void
    {
        $literal = '___noise___1000';
        $dom = $this->parse(
            '<PRE id="upper">A' . "\n" . 'B</PRE>'
            . '<pre id="empty"></pre>'
            . '<pre id="noise">X' . "\r\n" . $literal . '</pre>'
        );

        self::assertSame("A\nB", $dom->find('#upper', 0)->innertext);
        self::assertSame('', $dom->find('#empty', 0)->innertext);
        self::assertSame("X\r\n" . $literal, $dom->find('#noise', 0)->innertext);
        self::assertSame(3, count($dom->find('pre')));
        $dom->clear();
    }

    public function testUnclosedPreExtendsToEofAndKeepsChildren(): void
    {
        $html = '<pre id="p">línea 1' . "\n"
            . 'línea 2' . "\n"
            . '<span id="s">línea 3</span>';
        $dom = $this->parse($html);
        $pre = $dom->find('#p', 0);

        self::assertNotNull($pre);
        self::assertNotNull($pre->find('#s', 0));
        self::assertSame(
            "línea 1\nlínea 2\n<span id=\"s\">línea 3</span>",
            $pre->innertext
        );
        self::assertStringNotContainsString('<span', $pre->plaintext);
        self::assertSame(
            "línea 1\nlínea 2\nlínea 3",
            rtrim($pre->plaintext)
        );
        $dom->clear();
    }

    public function testStripRnScopeIsUnchangedOutsidePre(): void
    {
        $html = '<div id="outside">A' . "\r\n" . 'B</div>'
            . '<pre id="inside">C' . "\r\n" . 'D</pre>';

        $stripped = $this->parse($html, true);
        self::assertSame('A  B', $stripped->find('#outside', 0)->innertext);
        self::assertSame("C\r\nD", $stripped->find('#inside', 0)->innertext);
        $stripped->clear();

        $unstripped = $this->parse($html, false);
        self::assertSame("A\r\nB", $unstripped->find('#outside', 0)->innertext);
        self::assertSame("C\r\nD", $unstripped->find('#inside', 0)->innertext);
        $unstripped->clear();
    }

    public function testProtectedContentTypesRemainUnchanged(): void
    {
        $script = "if (a < b) {\n  x = 1;\n}";
        $style = ".x {\n  color: red;\n}";
        $code = "a < b\nline 2";
        $dom = $this->parse(
            '<script>' . $script . '</script>'
            . '<style>' . $style . '</style>'
            . '<code>' . $code . '</code>'
            . '<div id="comment"><!--a' . "\n" . 'b--></div>'
            . '<div id="cdata"><![CDATA[x' . "\n" . 'y]]></div>'
        );

        self::assertSame($script, $dom->find('script', 0)->innertext);
        self::assertSame('.x {   color: red; }', $dom->find('style', 0)->innertext);
        self::assertSame('a < b line 2', $dom->find('code', 0)->innertext);
        self::assertSame('<!--a b-->', $dom->find('#comment', 0)->innertext);
        self::assertSame('<![CDATA[x y]]>', $dom->find('#cdata', 0)->innertext);
        $dom->clear();
    }

    public function testPreviousFunctionalFixesRemainActive(): void
    {
        $dom = $this->parse(
            '<div><p id="p1"></p><span></span><p id="p2"></p><p id="p3"></p></div>'
            . '<div id="en" lang="en"></div><div id="en-us" lang="en-US"></div>'
            . '<div id="english" lang="english"></div>'
            . '<div id="attr" data-a="1"></div>'
        );

        self::assertSame(
            ['p2', 'p3'],
            array_map(static fn($node): string => (string) $node->id, $dom->find('#p1 ~ p'))
        );
        self::assertSame(
            ['en', 'en-us'],
            array_map(static fn($node): string => (string) $node->id, $dom->find('[lang|=en]'))
        );
        $node = $dom->find('#attr', 0);
        $node->removeAttribute('data-a');
        self::assertFalse($node->hasAttribute('data-a'));
        self::assertStringNotContainsString('data-a=', $node->outertext);
        $dom->clear();
    }

    public function testKCasesRemainOrderedAndActivated(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/pruebas/casos/todos.php');
        preg_match_all(
            '/["\x27]id["\x27]\s*=>\s*["\x27](K-00[1-5])["\x27]/',
            $source,
            $matches
        );

        self::assertSame(
            ['K-001', 'K-002', 'K-003', 'K-004', 'K-005'],
            array_values($matches[1])
        );
        foreach (['K-001', 'K-002', 'K-003', 'K-004', 'K-005'] as $id) {
            self::assertSame(
                1,
                preg_match(
                    '/["\x27]id["\x27]\s*=>\s*["\x27]'
                    . preg_quote($id, '/')
                    . '["\x27][\s\S]*?["\x27]known_failure["\x27]\s*=>\s*false/',
                    $source
                ),
                $id . ' debe permanecer activado.'
            );
        }
    }
}
