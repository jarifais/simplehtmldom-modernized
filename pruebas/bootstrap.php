<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

final class TestEnvironment
{
    public string $root;
    public string $version;
    public string $libraryPath;
    public string $httpBase;
    public string $httpsBase;
    public string $fixture;

    public function __construct(string $version)
    {
        $this->root = dirname(__DIR__);
        $this->version = $version;
        $this->libraryPath = $this->root . '/librerias/' . $version . '/simple_html_dom.php';
        $this->httpBase = getenv('SHD_HTTP_BASE') ?: 'http://127.0.0.1:8080';
        $this->httpsBase = getenv('SHD_HTTPS_BASE') ?: 'https://127.0.0.1:8443';
        $this->fixture = $this->root . '/servidor_objetivo/fixtures/local-file.html';
        stream_context_set_default([
            'http' => ['timeout' => 5, 'follow_location' => 1, 'max_redirects' => 5],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
        ]);
        require_once $this->libraryPath;
    }

    public function insecureContext(array $http = [])
    {
        return stream_context_create([
            'http' => array_merge(['timeout' => 5, 'follow_location' => 1, 'max_redirects' => 5], $http),
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
        ]);
    }
}

final class ResultBuilder
{
    private TestEnvironment $env;
    public function __construct(TestEnvironment $env) { $this->env = $env; }

    public function run(array $definition): array
    {
        gc_collect_cycles();
        $errors = [];
        $exceptions = [];
        $before = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);
        $start = hrtime(true);
        $actual = null;
        $status = 'FAIL';
        $handler = static function (int $severity, string $message, string $file, int $line) use (&$errors): bool {
            $errors[] = ['severity' => $severity, 'type' => self::errorName($severity), 'message' => $message, 'file' => $file, 'line' => $line];
            return true;
        };
        set_error_handler($handler, E_ALL);
        try {
            $actual = ($definition['run'])($this->env, $errors);
            $status = ($definition['assert'])($actual, $errors) ? 'PASS' : 'FAIL';
        } catch (Throwable $e) {
            $exceptions[] = ['class' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()];
            $status = 'FAIL';
        } finally {
            restore_error_handler();
            gc_collect_cycles();
        }
        if (($definition['known_failure'] ?? false) === true) {
            $status = ($status === 'PASS') ? 'UNEXPECTED_PASS' : 'KNOWN_FAILURE';
        }
        $end = hrtime(true);
        $after = memory_get_usage(true);
        return [
            'test' => $definition['id'],
            'name' => $definition['name'],
            'category' => $definition['category'] ?? 'general',
            'mandatory' => (bool)($definition['mandatory'] ?? false),
            'known_failure' => (bool)($definition['known_failure'] ?? false),
            'version' => $this->env->version,
            'status' => $status,
            'expected' => $definition['expected'] ?? null,
            'actual' => self::normalize($actual),
            'warnings' => $errors,
            'exceptions' => $exceptions,
            'duration_ms' => round(($end - $start) / 1_000_000, 3),
            'memory_initial_bytes' => $before,
            'memory_final_bytes' => $after,
            'memory_delta_bytes' => $after - $before,
            'memory_peak_delta_bytes' => max(0, memory_get_peak_usage(true) - $peakBefore),
        ];
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_object($value)) return ['object' => get_class($value)];
        if (is_resource($value)) return ['resource' => get_resource_type($value)];
        if (is_string($value) && preg_match('//u', $value) !== 1) return ['binary_base64' => base64_encode($value), 'bytes' => strlen($value)];
        return $value;
    }

    private static function errorName(int $severity): string
    {
        return match ($severity) {
            E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE', E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR', E_CORE_WARNING => 'E_CORE_WARNING', E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING', E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE', 2048 => 'E_STRICT', E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED', E_USER_DEPRECATED => 'E_USER_DEPRECATED', default => 'E_' . $severity,
        };
    }
}

function no_error_matching(array $errors, string $needle): bool
{
    foreach ($errors as $e) if (stripos($e['message'], $needle) !== false) return false;
    return true;
}

function dom_node_count(mixed $dom): int
{
    return is_object($dom) && isset($dom->nodes) && is_array($dom->nodes) ? count($dom->nodes) : 0;
}
