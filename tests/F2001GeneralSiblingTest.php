<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class F2001GeneralSiblingTest extends TestCase
{
    public function testGeneralSiblingCombinator(): void
    {
        require_once dirname(__DIR__)
            . '/librerias/fase1/simple_html_dom.php';

        $html = '<div id="grupo-1">'
            . '<p id="p1" class="a" data-x="1">Uno</p>'
            . '<span id="s1" class="b">Separador</span>'
            . '<p id="p2" class="b" data-x="2">Dos</p>'
            . '<p id="p3" class="b">Tres</p>'
            . '<div>'
            . '<p id="n1">N1</p>'
            . '<p id="n2">N2</p>'
            . '</div>'
            . '</div>'
            . '<div>'
            . '<p id="p4" class="b" data-x="2">Cuatro</p>'
            . '</div>';

        $dom = str_get_html($html);

        $ids = static fn(array $nodes): array =>
            array_map(
                static fn($node): string => (string)$node->id,
                $nodes
            );

        self::assertSame(
            ['p2', 'p3'],
            $ids($dom->find('#p1 ~ p'))
        );

        self::assertSame(
            ['s1'],
            $ids($dom->find('#p1 ~ span'))
        );

        self::assertSame(
            ['p2', 'p3'],
            $ids($dom->find('#s1 ~ p'))
        );

        self::assertSame(
            ['p2', 'p3', 'n2'],
            $ids($dom->find('p ~ p'))
        );

        self::assertSame(
            ['s1', 'p2', 'p3'],
            $ids($dom->find('.a ~ .b'))
        );

        self::assertSame(
            ['p2'],
            $ids($dom->find('[data-x="1"] ~ [data-x="2"]'))
        );

        self::assertSame(
            [],
            $ids($dom->find('#p3 ~ span'))
        );

        self::assertSame(
            ['n2'],
            $ids($dom->find('#n1 ~ p'))
        );

        self::assertSame(
            'p2',
            $dom->find('#p1 ~ p', 0)->id
        );

        self::assertSame(
            'p3',
            $dom->find('#p1 ~ p', 1)->id
        );

        self::assertNull(
            $dom->find('#p1 ~ p', 2)
        );

        $dom->clear();
    }
}