<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class F2002LangDashMatchTest extends TestCase
{
    private function dom(): simple_html_dom
    {
        require_once dirname(__DIR__) . '/librerias/fase1/simple_html_dom.php';
        return str_get_html(
            '<div id="contenedor">'
            . '<div id="lang-en" lang="en"></div>'
            . '<div id="lang-en-us" lang="en-US"></div>'
            . '<div id="lang-en-gb" lang="en-GB"></div>'
            . '<div id="lang-en-lower" lang="en-us"></div>'
            . '<div id="lang-uppercase" lang="EN"></div>'
            . '<div id="lang-english" lang="english"></div>'
            . '<div id="lang-enus" lang="enUS"></div>'
            . '<div id="lang-underscore" lang="en_US"></div>'
            . '<div id="lang-enough" lang="enough"></div>'
            . '<div id="lang-trailing" lang="en-"></div>'
            . '<div id="lang-double" lang="en--US"></div>'
            . '<div id="lang-prefix" lang="x-en"></div>'
            . '<div id="lang-empty" lang=""></div>'
            . '<div id="lang-none"></div>'
            . '</div>'
            . '<section>'
            . '<div id="attr-presence" data-test="alpha beta"></div>'
            . '<div id="attr-exact" data-test="alpha"></div>'
            . '<div id="attr-prefix" data-test="alphabet"></div>'
            . '<div id="attr-suffix" data-test="beta"></div>'
            . '<div id="attr-middle" data-test="xalphay"></div>'
            . '<div id="attr-empty" data-test=""></div>'
            . '<div id="attr-none"></div>'
            . '</section>'
        );
    }
    private static function ids(array $nodes): array
    {
        return array_map(static fn($node): string => (string)$node->id, $nodes);
    }
    public function testLangDashMatchOperator(): void
    {
        $dom = $this->dom();
        $expected = ['lang-en','lang-en-us','lang-en-gb','lang-en-lower','lang-trailing','lang-double'];
        self::assertSame($expected, self::ids($dom->find('[lang|=en]')));
        self::assertSame($expected, self::ids($dom->find('div[lang|=en]')));
        self::assertSame($expected, self::ids($dom->find('#contenedor [lang|=en]')));
        self::assertSame($expected, self::ids($dom->find('[lang|="en"]')));
        self::assertSame($expected, self::ids($dom->find("[lang|='en']")));
        self::assertSame($expected, self::ids($dom->find('[lang|=en s]')));
        self::assertSame(
            ['lang-en','lang-en-us','lang-en-gb','lang-en-lower','lang-uppercase','lang-trailing','lang-double'],
            self::ids($dom->find('[lang|=en i]'))
        );
        self::assertSame(['lang-uppercase'], self::ids($dom->find('[lang|=EN]')));
        self::assertSame('lang-en', $dom->find('[lang|=en]', 0)->id);
        self::assertSame('lang-double', $dom->find('[lang|=en]', 5)->id);
        self::assertNull($dom->find('[lang|=en]', 6));
        $dom->clear();
    }
    public function testOtherAttributeOperatorsRemainStable(): void
    {
        $dom = $this->dom();
        self::assertSame(
            ['attr-presence','attr-exact','attr-prefix','attr-suffix','attr-middle','attr-empty'],
            self::ids($dom->find('[data-test]'))
        );
        self::assertSame(['attr-exact'], self::ids($dom->find('[data-test=alpha]')));
        self::assertSame(['attr-exact'], self::ids($dom->find('[data-test="alpha"]')));
        self::assertSame(['attr-presence','attr-exact'], self::ids($dom->find('[data-test~=alpha]')));
        self::assertSame(['attr-presence','attr-exact','attr-prefix'], self::ids($dom->find('[data-test^=alpha]')));
        self::assertSame(['attr-presence','attr-suffix'], self::ids($dom->find('[data-test$=beta]')));
        self::assertSame(
            ['attr-presence','attr-exact','attr-prefix','attr-middle'],
            self::ids($dom->find('[data-test*=alpha]'))
        );
        $dom->clear();
    }
}