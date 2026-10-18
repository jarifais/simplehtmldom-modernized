<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class F3001BenchmarkInfrastructureTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    private function path(string $relative): string
    {
        return $this->root() . '/' . $relative;
    }

    private function json(string $relative): array
    {
        return json_decode(
            (string) file_get_contents($this->path($relative)),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function runCommand(array $command): array
    {
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $this->root(),
            null,
            ['bypass_shell' => true, 'suppress_errors' => true]
        );

        self::assertIsResource($process);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, $stdout, $stderr];
    }

    public function testFilesAndLint(): void
    {
        $files = [
            'benchmarks/README.md',
            'benchmarks/config.php',
            'benchmarks/generate_fixtures.php',
            'benchmarks/worker.php',
            'benchmarks/run.php',
            'benchmarks/fixtures/manifest.json',
            'benchmarks/fixtures/small.html',
            'benchmarks/fixtures/medium.html',
            'benchmarks/fixtures/large.html',
            'tests/F3001BenchmarkInfrastructureTest.php',
            'resultados/benchmarks/f3_001_smoke.json',
            'CAMBIOS_FASE_3.md',
            'RESULTADO_PRUEBAS_FASE_3.md',
            'REGRESIONES_FASE_3.md',
        ];

        foreach ($files as $relative) {
            self::assertFileExists($this->path($relative));
            $bytes = (string) file_get_contents($this->path($relative));
            self::assertFalse(str_starts_with($bytes, "\xEF\xBB\xBF"));
            self::assertSame(1, preg_match('//u', $bytes));
        }

        foreach (
            [
                'benchmarks/config.php',
                'benchmarks/generate_fixtures.php',
                'benchmarks/worker.php',
                'benchmarks/run.php',
                __FILE__,
            ] as $relative
        ) {
            $file = str_starts_with($relative, 'benchmarks/')
                ? $this->path($relative)
                : $relative;

            [$exitCode, $stdout, $stderr] = $this->runCommand(
                [PHP_BINARY, '-l', $file]
            );

            self::assertSame(0, $exitCode, $stdout . $stderr);
        }
    }

    public function testConfigManifestAndDeterminism(): void
    {
        $config = require $this->path('benchmarks/config.php');

        self::assertSame(
            ['original', 'fase1'],
            array_keys($config['implementations'])
        );

        self::assertSame(
            [
                'parse',
                'find_by_tag',
                'find_by_class',
                'find_by_attribute',
                'traverse_nodes',
                'read_plaintext',
                'read_outertext',
            ],
            $config['workloads']
        );

        $manifest = $this->json('benchmarks/fixtures/manifest.json');

        self::assertSame(3001, $manifest['seed']);
        self::assertSame(
            ['small', 'medium', 'large'],
            array_column($manifest['fixtures'], 'name')
        );

        foreach ($manifest['fixtures'] as $fixture) {
            self::assertSame(
                hash_file(
                    'sha256',
                    $this->path($fixture['relative_path'])
                ),
                $fixture['sha256']
            );

            self::assertSame(
                filesize($this->path($fixture['relative_path'])),
                $fixture['size_bytes']
            );
        }
    }

    public function testSmokeCounts(): void
    {
        $smoke = $this->json(
            'resultados/benchmarks/f3_001_smoke.json'
        );

        self::assertSame(
            126,
            $smoke['validation']['expected_samples']
        );
        self::assertSame(
            126,
            $smoke['validation']['actual_samples']
        );
        self::assertSame(
            42,
            $smoke['validation']['expected_warmups']
        );
        self::assertSame(
            168,
            $smoke['validation']['actual_execution_records']
        );
        self::assertSame(
            [
                'PASS' => 126,
                'SEMANTIC_MISMATCH' => 0,
                'ERROR' => 0,
                'TIMEOUT' => 0,
            ],
            $smoke['validation']['status_counts']
        );
        self::assertTrue($smoke['validation']['success']);
        self::assertCount(126, $smoke['samples']);
        self::assertCount(168, $smoke['execution_order']);
    }

    public function testSamplesAndIsolation(): void
    {
        $smoke = $this->json(
            'resultados/benchmarks/f3_001_smoke.json'
        );

        $runnerPid = $smoke['environment']['runner_pid'];
        $seen = [];
        $digests = [];

        foreach ($smoke['samples'] as $sample) {
            $key = $sample['implementation']
                . '|'
                . $sample['fixture']
                . '|'
                . $sample['workload']
                . '|'
                . $sample['sample_index'];

            self::assertArrayNotHasKey($key, $seen);
            $seen[$key] = true;

            self::assertSame('PASS', $sample['status']);
            self::assertSame(0, $sample['exit_code']);
            self::assertGreaterThan(0, $sample['wall_time_ns']);
            self::assertGreaterThan(
                0,
                $sample['peak_memory_bytes']
            );
            self::assertGreaterThan(
                0,
                $sample['operations_per_second']
            );
            self::assertSame(
                1,
                preg_match(
                    '/^[0-9a-f]{64}$/',
                    $sample['semantic_digest']
                )
            );
            self::assertGreaterThan(0, $sample['worker_pid']);
            self::assertNotSame(
                $runnerPid,
                $sample['worker_pid']
            );

            $digests[
                $sample['fixture']
                . '|'
                . $sample['workload']
            ][$sample['semantic_digest']] = true;
        }

        foreach ($digests as $group) {
            self::assertCount(1, $group);
        }
    }

    public function testSourceCheckpointAndNoCharts(): void
    {
        $worker = (string) file_get_contents(
            $this->path('benchmarks/worker.php')
        );
        $runner = (string) file_get_contents(
            $this->path('benchmarks/run.php')
        );

        self::assertStringContainsString(
            'require_once $library',
            $worker
        );
        self::assertStringContainsString('proc_open', $runner);
        self::assertStringContainsString('TIMEOUT', $runner);
        self::assertStringContainsString('taskkill', $runner);
        self::assertStringNotContainsString(
            'eval(',
            $worker . $runner
        );

        $baseline = $this->json('HASHES_LIBRERIAS_BASE.json');

        self::assertSame(
            $baseline['fase1']['simple_html_dom.php'],
            hash_file(
                'sha256',
                $this->path(
                    'librerias/fase1/simple_html_dom.php'
                )
            )
        );

        [$exitCode, $stdout] = $this->runCommand(
            [
                PHP_BINARY,
                $this->path('pruebas/generar_hashes.php'),
            ]
        );

        self::assertSame(0, $exitCode);

        $checkpoint = json_decode(
            trim((string) $stdout),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertTrue($checkpoint['original_unchanged']);
        self::assertTrue($checkpoint['fase1_unchanged']);

        foreach (
            ['*.png', '*.svg', '*.jpg', '*.jpeg', '*.csv']
            as $pattern
        ) {
            self::assertSame(
                [],
                glob(
                    $this->path(
                        'resultados/benchmarks/' . $pattern
                    )
                ) ?: []
            );
        }
    }
}
