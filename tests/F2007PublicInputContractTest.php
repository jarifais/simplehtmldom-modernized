<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/librerias/fase1/simple_html_dom.php';

final class F2007StringableValue
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

final class F2007PlainObject
{
}

final class F2007PublicInputContractTest extends TestCase
{
    private const WARNING = 'HTML source expects a string-compatible value.';

    /**
     * @return array{result:mixed,warnings:list<array{severity:int,message:string}>,exception:?Throwable}
     */
    private function capture(callable $callback): array
    {
        $warnings = [];
        $exception = null;
        $result = null;

        set_error_handler(
            static function (int $severity, string $message) use (&$warnings): bool {
                $warnings[] = ['severity' => $severity, 'message' => $message];
                return true;
            }
        );

        try {
            $result = $callback();
        } catch (Throwable $error) {
            $exception = $error;
        } finally {
            restore_error_handler();
        }

        return compact('result', 'warnings', 'exception');
    }

    private function assertNoDiagnostic(array $capture): void
    {
        self::assertNull($capture['exception']);
        self::assertSame([], $capture['warnings']);
    }

    private function assertRejected(array $capture, bool $constructor): void
    {
        self::assertNull($capture['exception']);
        self::assertCount(1, $capture['warnings']);
        self::assertSame(E_USER_WARNING, $capture['warnings'][0]['severity']);
        self::assertSame(self::WARNING, $capture['warnings'][0]['message']);

        if ($constructor) {
            self::assertInstanceOf(simple_html_dom::class, $capture['result']);
            self::assertNull($capture['result']->root);
        } else {
            self::assertFalse($capture['result']);
        }
    }

    public static function emptyValueProvider(): array
    {
        return [
            'null' => [null],
            'false' => [false],
            'empty string' => [''],
        ];
    }

    #[DataProvider('emptyValueProvider')]
    public function testEmptyValuesHaveExplicitApiAppropriateResults(mixed $value): void
    {
        $function = $this->capture(static fn () => str_get_html($value));
        $constructor = $this->capture(static fn () => new simple_html_dom($value));
        $load = $this->capture(static function () use ($value) {
            $dom = str_get_html('<p>old</p>');
            self::assertInstanceOf(simple_html_dom::class, $dom);
            return $dom->load($value);
        });

        $this->assertNoDiagnostic($function);
        self::assertFalse($function['result']);

        $this->assertNoDiagnostic($constructor);
        self::assertInstanceOf(simple_html_dom::class, $constructor['result']);
        self::assertNull($constructor['result']->root);

        $this->assertNoDiagnostic($load);
        self::assertFalse($load['result']);
    }

    public static function scalarValueProvider(): array
    {
        return [
            'integer zero' => [0, '0'],
            'string zero' => ['0', '0'],
            'true' => [true, '1'],
            'integer' => [42, '42'],
            'float' => [1.5, '1.5'],
        ];
    }

    #[DataProvider('scalarValueProvider')]
    public function testScalarsAreAcceptedConsistently(mixed $value, string $expected): void
    {
        $function = $this->capture(static fn () => str_get_html($value));
        $constructor = $this->capture(static fn () => new simple_html_dom($value));
        $load = $this->capture(static function () use ($value) {
            $dom = new simple_html_dom();
            return $dom->load($value);
        });

        foreach ([$function, $constructor, $load] as $capture) {
            $this->assertNoDiagnostic($capture);
            self::assertInstanceOf(simple_html_dom::class, $capture['result']);
            self::assertSame($expected, $capture['result']->plaintext);
            $capture['result']->clear();
        }
    }

    public function testStringableObjectsAreAcceptedConsistently(): void
    {
        $value = new F2007StringableValue('<p id="x">stringable</p>');
        $captures = [
            $this->capture(static fn () => str_get_html($value)),
            $this->capture(static fn () => new simple_html_dom($value)),
            $this->capture(static function () use ($value) {
                $dom = new simple_html_dom();
                return $dom->load($value);
            }),
        ];

        foreach ($captures as $capture) {
            $this->assertNoDiagnostic($capture);
            self::assertInstanceOf(simple_html_dom::class, $capture['result']);
            self::assertSame('stringable', $capture['result']->find('#x', 0)->plaintext);
            $capture['result']->clear();
        }
    }

    public function testArraysAreRejectedWithoutNativeConversionWarning(): void
    {
        $this->assertInvalidValueRejected([]);
    }

    public function testResourcesAreRejectedWithoutResourceCoercion(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        try {
            $this->assertInvalidValueRejected($resource);
        } finally {
            fclose($resource);
        }
    }

    public function testNonStringableObjectsAreRejectedWithoutThrowable(): void
    {
        $this->assertInvalidValueRejected(new F2007PlainObject());
    }

    private function assertInvalidValueRejected(mixed $value): void
    {
        $function = $this->capture(static fn () => str_get_html($value));
        $constructor = $this->capture(static fn () => new simple_html_dom($value));
        $load = $this->capture(static function () use ($value) {
            $dom = new simple_html_dom();
            return $dom->load($value);
        });

        $this->assertRejected($function, false);
        $this->assertRejected($constructor, true);
        $this->assertRejected($load, false);
    }

    public function testRejectedLoadClearsPreviousStateAndObjectCanBeReused(): void
    {
        $dom = str_get_html('<p id="old">old</p>');
        self::assertInstanceOf(simple_html_dom::class, $dom);
        self::assertNotNull($dom->find('#old', 0));

        $rejected = $this->capture(static fn () => $dom->load([]));
        $this->assertRejected($rejected, false);
        self::assertNull($dom->root);

        $accepted = $this->capture(static fn () => $dom->load('<p id="new">new</p>'));
        $this->assertNoDiagnostic($accepted);
        self::assertSame($dom, $accepted['result']);
        self::assertNull($dom->find('#old', 0));
        self::assertSame('new', $dom->find('#new', 0)->plaintext);
        $dom->clear();
    }

    public function testStringInputAndFileConstructorBehaviorRemainAvailable(): void
    {
        $html = '<div id="direct">direct</div>';
        $direct = $this->capture(static fn () => new simple_html_dom($html));
        $this->assertNoDiagnostic($direct);
        self::assertSame('direct', $direct['result']->find('#direct', 0)->plaintext);
        $direct['result']->clear();

        $file = tempnam(sys_get_temp_dir(), 'f2007_');
        self::assertNotFalse($file);
        file_put_contents($file, '<div id="file">file</div>');

        try {
            $fromFile = $this->capture(static fn () => new simple_html_dom($file));
            $this->assertNoDiagnostic($fromFile);
            self::assertSame('file', $fromFile['result']->find('#file', 0)->plaintext);
            $fromFile['result']->clear();
        } finally {
            @unlink($file);
        }
    }
}
