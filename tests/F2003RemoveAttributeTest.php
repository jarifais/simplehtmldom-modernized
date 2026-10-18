<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class F2003RemoveAttributeTest extends TestCase
{
    private function createDom(string $html): simple_html_dom
    {
        require_once dirname(__DIR__)
            . '/librerias/fase1/simple_html_dom.php';

        return str_get_html($html);
    }

    private static function ids(array $nodes): array
    {
        return array_map(
            static fn($node): string => (string)$node->id,
            $nodes
        );
    }

    public function testRemovedAttributeIsReportedAsAbsent(): void
    {
        $dom = $this->createDom(
            '<div id="x" data-a="1"></div>'
        );

        $node = $dom->find('#x', 0);

        self::assertTrue(
            $node->hasAttribute('data-a')
        );

        $node->removeAttribute('data-a');

        self::assertFalse(
            $node->hasAttribute('data-a')
        );

        $dom->clear();
    }

    public function testRemovedAttributeIsNotSerialized(): void
    {
        $dom = $this->createDom(
            '<div id="x" data-a="1"></div>'
        );

        $node = $dom->find('#x', 0);
        $node->removeAttribute('data-a');

        self::assertSame(
            '<div id="x"></div>',
            $node->outertext
        );

        self::assertStringNotContainsString(
            'data-a',
            $node->outertext
        );

        $dom->clear();
    }

    public function testEmptyZeroAndBooleanRemainPresent(): void
    {
        $dom = $this->createDom(
            '<input id="x" data-empty="" data-zero="0" disabled>'
        );

        $node = $dom->find('#x', 0);

        self::assertTrue(
            $node->hasAttribute('data-empty')
        );

        self::assertTrue(
            $node->hasAttribute('data-zero')
        );

        self::assertTrue(
            $node->hasAttribute('disabled')
        );

        $dom->clear();
    }

    public function testFalseValueIsReportedAsAbsent(): void
    {
        $dom = $this->createDom(
            '<div id="x"></div>'
        );

        $node = $dom->find('#x', 0);
        $node->setAttribute('data-false', false);

        self::assertFalse(
            $node->hasAttribute('data-false')
        );

        self::assertStringNotContainsString(
            'data-false',
            $node->outertext
        );

        $dom->clear();
    }

    public function testRemovingMiddlePreservesOrder(): void
    {
        $dom = $this->createDom(
            '<div id="x" data-first="A" '
            . 'data-middle="B" data-last="C"></div>'
        );

        $node = $dom->find('#x', 0);
        $node->removeAttribute('data-middle');

        self::assertFalse(
            $node->hasAttribute('data-middle')
        );

        self::assertSame(
            '<div id="x" data-first="A" data-last="C"></div>',
            $node->outertext
        );

        $dom->clear();
    }

    public function testRepeatedRemovalIsIdempotent(): void
    {
        $dom = $this->createDom(
            '<div id="x" data-a="1"></div>'
        );

        $node = $dom->find('#x', 0);

        $node->removeAttribute('data-a');
        $first = $node->outertext;
        $node->removeAttribute('data-a');

        self::assertFalse(
            $node->hasAttribute('data-a')
        );

        self::assertSame(
            $first,
            $node->outertext
        );

        $dom->clear();
    }

    public function testSetAttributeRestoresAttribute(): void
    {
        $dom = $this->createDom(
            '<div id="x" data-a="1"></div>'
        );

        $node = $dom->find('#x', 0);

        $node->removeAttribute('data-a');
        $node->setAttribute('data-a', '2');

        self::assertTrue(
            $node->hasAttribute('data-a')
        );

        self::assertSame(
            '2',
            $node->getAttribute('data-a')
        );

        self::assertSame(
            '<div id="x" data-a="2"></div>',
            $node->outertext
        );

        $dom->clear();
    }

    public function testSelectorStopsFindingRemovedAttribute(): void
    {
        $dom = $this->createDom(
            '<section>'
            . '<div id="x" data-a="1"></div>'
            . '</section>'
        );

        $node = $dom->find('#x', 0);

        self::assertSame(
            ['x'],
            self::ids(
                $dom->find('[data-a]')
            )
        );

        $node->removeAttribute('data-a');

        self::assertSame(
            [],
            self::ids(
                $dom->find('[data-a]')
            )
        );

        $dom->clear();
    }

    public function testF2001GeneralSiblingRegression(): void
    {
        $dom = $this->createDom(
            '<div>'
            . '<p id="p1"></p>'
            . '<span></span>'
            . '<p id="p2"></p>'
            . '<p id="p3"></p>'
            . '</div>'
        );

        self::assertSame(
            ['p2', 'p3'],
            self::ids(
                $dom->find('#p1 ~ p')
            )
        );

        $dom->clear();
    }

    public function testF2002LangDashMatchRegression(): void
    {
        $dom = $this->createDom(
            '<div id="en" lang="en"></div>'
            . '<div id="en-us" lang="en-US"></div>'
            . '<div id="english" lang="english"></div>'
        );

        self::assertSame(
            ['en', 'en-us'],
            self::ids(
                $dom->find('[lang|=en]')
            )
        );

        $dom->clear();
    }
}