<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * DIST-001: comprobar que la distribución de archivo único coincide
 * byte a byte con la fuente mantenida y funciona sin autoload de Composer.
 */
final class DistributionSingleFileTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    public function testSingleFileMatchesMaintainedSourceByteForByte(): void
    {
        $root = $this->root();
        $single = $root . '/simple_html_dom.php';
        $maintained = $root . '/librerias/fase1/simple_html_dom.php';

        self::assertFileExists($single);
        self::assertFileExists($maintained);

        $baseline = json_decode(
            (string) file_get_contents($root . '/HASHES_LIBRERIAS_BASE.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $expected = $baseline['fase1']['simple_html_dom.php'];
        self::assertSame(
            'bba1d651b7c58eab6e70a031a6d8ca52b86c68bc746188440652f877b9110d25',
            $expected
        );
        self::assertSame($expected, hash_file('sha256', $maintained));
        self::assertSame($expected, hash_file('sha256', $single));
        self::assertSame(
            (string) file_get_contents($maintained),
            (string) file_get_contents($single)
        );
    }

    public function testSingleFileSupportsTraditionalApiInFreshPhpProcess(): void
    {
        $root = $this->root();
        $code = <<<'PHP'
require_once $argv[1];
if (!function_exists('str_get_html') || !function_exists('file_get_html')) {
    exit(2);
}
$html = str_get_html('<div><p id="dato" data-value="42">OK</p></div>');
if ($html === false) {
    exit(3);
}
$node = $html->find('#dato', 0);
if ($node === null || $node->plaintext !== 'OK' || !$node->hasAttribute('data-value')) {
    exit(4);
}
$node->removeAttribute('data-value');
if ($node->hasAttribute('data-value')) {
    exit(5);
}
$html->clear();
echo 'DIST-001 OK';
PHP;

        $process = proc_open(
            [PHP_BINARY, '-r', $code, '--', $root . '/simple_html_dom.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            null,
            ['bypass_shell' => true, 'suppress_errors' => true]
        );

        self::assertIsResource($process, 'No se pudo iniciar PHP aislado.');
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        self::assertSame(0, $exit, 'Proceso de archivo único: ' . $stderr);
        self::assertSame('DIST-001 OK', trim($stdout));
        self::assertSame('', trim($stderr));
    }
}
