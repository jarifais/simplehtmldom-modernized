<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/servidor_error.log');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

function inject_metadata(string $body, string $charset, array $headers): string
{
    if (!str_contains($body, '<body')) { return $body; }
    $scenario = $headers['X-Test-Scenario'] ?? 'generic';
    $headerText = 'Content-Type: text/html; charset=' . $charset;
    foreach ($headers as $name => $value) { $headerText .= '; ' . $name . ': ' . $value; }
    $bytes = strlen($body);
    for ($i = 0; $i < 4; $i++) {
        $metadata = '<section id="test-metadata"><h1>Escenario: ' . htmlspecialchars((string)$scenario, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<dl><dt>Objetivo</dt><dd>Proporcionar un caso local, determinista y reproducible para el parser.</dd>'
            . '<dt>Resultado esperado</dt><dd>Los marcadores y datos propios del endpoint deben poder localizarse sin corrupción silenciosa.</dd>'
            . '<dt>Tamaño de la respuesta</dt><dd>' . $bytes . ' bytes</dd>'
            . '<dt>Charset enviado</dt><dd>' . htmlspecialchars($charset, ENT_QUOTES, 'UTF-8') . '</dd>'
            . '<dt>Cabeceras relevantes</dt><dd>' . htmlspecialchars($headerText, ENT_QUOTES, 'UTF-8') . '</dd></dl></section>';
        $candidate = preg_replace('/<body([^>]*)>/', '<body$1>' . $metadata, $body, 1);
        $newBytes = strlen((string)$candidate);
        if ($newBytes === $bytes) { return (string)$candidate; }
        $bytes = $newBytes;
    }
    return (string)$candidate;
}

function send_html(string $body, int $status = 200, string $charset = 'UTF-8', array $headers = []): never
{
    $body = inject_metadata($body, $charset, $headers);
    http_response_code($status);
    header('Content-Type: text/html; charset=' . $charset);
    header('X-Test-Body-Bytes: ' . strlen($body));
    foreach ($headers as $name => $value) { header($name . ': ' . $value); }
    echo $body;
    exit;
}

function page(string $title, string $body, string $metaCharset = 'UTF-8'): string
{
    return '<!DOCTYPE html><html lang="es"><head><meta charset="' . htmlspecialchars($metaCharset, ENT_QUOTES, 'UTF-8') . '"><title>'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head><body>' . $body . '</body></html>';
}

function request_headers_safe(): array
{
    $result = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $result[$name] = (string)$value;
        }
    }
    ksort($result);
    return $result;
}

if ($path === '/' || $path === '/index.php') {
    $items = [
        '/basic','/content-zero','/large','/utf8-valid','/utf8-invalid','/charset-valid','/charset-invalid',
        '/http-test','/https-test','/empty-values','/malformed','/noise','/request-info','/status/200',
        '/status/301','/status/302','/status/404','/status/500','/slow?seconds=2','/large-headers','/gzip'
    ];
    $rows = '';
    foreach ($items as $item) {
        $rows .= '<tr><td><a href="' . htmlspecialchars($item, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($item, ENT_QUOTES, 'UTF-8') . '</a></td><td>Escenario local reproducible</td><td>Consulte el propio contenido y las cabeceras X-Test-*</td></tr>';
    }
    send_html(page('Simple HTML DOM - servidor objetivo', '<h1>Servidor objetivo local</h1><table border="1"><thead><tr><th>Endpoint</th><th>Objetivo</th><th>Resultado esperado</th></tr></thead><tbody>' . $rows . '</tbody></table>'));
}

switch ($path) {
    case '/basic':
        $body = '<h1 id="main-title" class="title primary">Página básica</h1>'
            . '<p id="intro" class="lead" data-kind="intro">Texto introductorio <a id="internal-link" href="/http-test">enlace interno</a>.</p>'
            . '<img id="product-image" class="product media" src="/assets/product.svg" alt="Producto de prueba" data-sku="ABC-001">'
            . '<ul id="list"><li class="item first">Uno</li><li class="item">Dos</li><li class="item last">Tres</li></ul>'
            . '<table id="prices"><thead><tr><th>Producto</th><th>Precio</th></tr></thead><tbody><tr data-id="1"><td>Prueba</td><td>9,99 €</td></tr></tbody></table>'
            . '<form id="search-form" method="get"><label for="q">Buscar</label><input id="q" name="q" value="0"><button type="submit">Enviar</button></form>'
            . '<div id="siblings"><p id="s1">A</p><p id="s2">B</p><p id="s3">C</p></div>'
            . '<div id="langs"><span id="lang-en" lang="en">English</span><span id="lang-en-gb" lang="en-GB">British</span><span id="lang-english" lang="english">Wrong</span></div>'
            . '<!-- BASIC_COMMENT --><style>.primary{font-weight:bold}</style><script>window.__LOCAL_TEST__ = true;</script>';
        send_html(page('Básico', $body), 200, 'UTF-8', ['X-Test-Scenario' => 'basic']);

    case '/content-zero':
        send_html(page('Cero', '<div id="zero-text">0</div><div id="empty-text"></div><div id="false-text">false</div><div id="zero-attribute" data-value="0">Elemento</div>'), 200, 'UTF-8', ['X-Test-Scenario' => 'content-zero']);

    case '/large':
        $prefix = '<div id="begin-marker">BEGIN</div><div id="payload">';
        $payload = str_repeat('A', 610000);
        $suffix = '</div><div id="after-limit">CONTENT_AFTER_600000_BYTES</div><div id="end-marker">END</div>';
        $html = page('Documento grande', $prefix . $payload . $suffix);
        send_html($html, 200, 'UTF-8', ['X-Test-Scenario' => 'large', 'X-Test-Exact-Bytes' => (string)strlen($html)]);

    case '/utf8-valid':
        send_html(page('UTF-8 válido', '<p id="utf8">Murcia, programación, niño, €, Ελληνικά, Русский, 日本語, 🚀 &amp; café</p>'), 200, 'UTF-8', ['X-Test-Scenario' => 'utf8-valid']);

    case '/utf8-invalid':
        $invalid = "\xC3\x28\xA0\xA1\xED\xA0\x80";
        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Inválido</title></head><body><div id="before">BEFORE</div><div id="invalid">' . $invalid . '</div><div id="after">AFTER</div></body></html>';
        send_html($body, 200, 'UTF-8', ['X-Test-Scenario' => 'utf8-invalid']);

    case '/charset-valid':
        send_html(page('Charset válido', '<p id="charset-text">programación, niño, €</p>', 'UTF-8'), 200, 'UTF-8', ['X-Test-Scenario' => 'charset-valid']);

    case '/charset-invalid':
        $body = '<!DOCTYPE html><html><head><meta charset="CHARSET-INEXISTENTE"><title>Charset inválido</title></head><body><p id="charset-invalid-text">CONTENIDO_CONSERVADO programación</p></body></html>';
        send_html($body, 200, 'CHARSET-INEXISTENTE', ['X-Test-Scenario' => 'charset-invalid']);

    case '/http-test':
    case '/https-test':
        send_html(page('Transporte', '<div id="transport-marker">TRANSPORT_OK</div><div id="scheme">' . htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http'), ENT_QUOTES, 'UTF-8') . '</div>'), 200, 'UTF-8', ['X-Test-Scenario' => ltrim($path, '/')]);

    case '/empty-values':
        send_html(page('Valores', '<div id="empty"></div><div id="zero">0</div><div id="space"> </div><div id="false">false</div><div id="null">null</div>'), 200, 'UTF-8', ['X-Test-Scenario' => 'empty-values']);

    case '/malformed':
        $body = '<!DOCTYPE html><html><head><title>Malformado</title></head><body><div id=outer><p id="open">Texto<div id="nested"><b>Negrita<i>cursiva</b></i><ul><li>Uno<li>Dos</ul><!-- comentario incompleto<script>var x = "</script>";<div id="tail">TAIL</div></body>';
        send_html($body, 200, 'UTF-8', ['X-Test-Scenario' => 'malformed']);

    case '/noise':
        $body = '<div id="before-noise">BEFORE</div><!-- COMMENT_ONE --><style id="style-one">.x{content:"___noise___1000"}</style>'
            . '<script id="script-one">const marker = "___noise___1000";</script><pre id="pre-one">  línea 1' . "\n" . '    línea 2  </pre>'
            . '<div id="literal-noise">___noise___1000</div><code id="php-text">&lt;?php echo "visible"; ?&gt;</code><div id="after-noise">AFTER</div>';
        send_html(page('Ruido', $body), 200, 'UTF-8', ['X-Test-Scenario' => 'noise']);

    case '/request-info':
        $data = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'host' => $_SERVER['HTTP_HOST'] ?? null,
            'accept' => $_SERVER['HTTP_ACCEPT'] ?? null,
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
            'headers' => request_headers_safe(),
            'query' => $_GET,
        ];
        send_html(page('Información petición', '<pre id="request-json">' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>'), 200, 'UTF-8', ['X-Test-Scenario' => 'request-info']);

    case '/status/200': send_html(page('200', '<div id="status">200</div>'), 200);
    case '/status/301': header('Location: /status/200', true, 301); exit;
    case '/status/302': header('Location: /status/200', true, 302); exit;
    case '/status/404': send_html(page('404', '<div id="status">404</div>'), 404);
    case '/status/500': send_html(page('500', '<div id="status">500</div>'), 500);

    case '/slow':
        $seconds = isset($_GET['seconds']) ? (int)$_GET['seconds'] : 1;
        $seconds = max(0, min(3, $seconds));
        if ($seconds > 0) { sleep($seconds); }
        send_html(page('Lento', '<div id="slow-seconds">' . $seconds . '</div>'), 200, 'UTF-8', ['X-Test-Scenario' => 'slow']);

    case '/large-headers':
        $headers = [];
        for ($i = 1; $i <= 12; $i++) { $headers['X-Test-Large-' . $i] = str_repeat((string)($i % 10), 180); }
        send_html(page('Cabeceras grandes', '<div id="large-headers">OK</div>'), 200, 'UTF-8', $headers);

    case '/gzip':
        $body = inject_metadata(page('Gzip', '<div id="gzip-marker">GZIP_OK</div>'), 'UTF-8', ['X-Test-Scenario' => 'gzip']);
        $accept = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        if (stripos($accept, 'gzip') !== false) {
            $encoded = gzencode($body, 6);
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Test-Body-Bytes: ' . strlen($body));
            header('X-Test-Compressed-Bytes: ' . strlen($encoded));
            echo $encoded;
            exit;
        }
        send_html($body, 200, 'UTF-8', ['X-Test-Scenario' => 'gzip-plain']);
}

send_html(page('No encontrado', '<div id="status">404</div>'), 404);
