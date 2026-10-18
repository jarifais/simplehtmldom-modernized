<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

$root = dirname(__DIR__);
$cert = $root . '/certificados/cert.pem';
$key = $root . '/certificados/key.pem';
$listen = $argv[1] ?? '127.0.0.1:8443';
$upstreamHost = $argv[2] ?? '127.0.0.1';
$upstreamPort = (int)($argv[3] ?? 8080);
if (!is_file($cert) || !is_file($key)) {
    fwrite(STDERR, "Faltan certificados TLS. Ejecute configurar_entorno.ps1.\n");
    exit(2);
}
$context = stream_context_create(['ssl' => [
    'local_cert' => $cert,
    'local_pk' => $key,
    'allow_self_signed' => true,
    'verify_peer' => false,
    'verify_peer_name' => false,
    'disable_compression' => true,
]]);
$server = stream_socket_server('tls://' . $listen, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) { fwrite(STDERR, "No se pudo iniciar TLS: $errstr ($errno)\n"); exit(3); }
fwrite(STDOUT, "TLS proxy escuchando en https://$listen -> http://$upstreamHost:$upstreamPort\n");
while ($client = @stream_socket_accept($server, -1)) {
    stream_set_timeout($client, 10);
    $request = '';
    while (!str_contains($request, "\r\n\r\n") && !feof($client)) {
        $chunk = fread($client, 8192);
        if ($chunk === false || $chunk === '') { break; }
        $request .= $chunk;
        if (strlen($request) > 1048576) { break; }
    }
    $upstream = @fsockopen($upstreamHost, $upstreamPort, $ue, $us, 5);
    if ($upstream === false) { fwrite($client, "HTTP/1.1 502 Bad Gateway\r\nContent-Length: 0\r\nConnection: close\r\n\r\n"); fclose($client); continue; }
    stream_set_timeout($upstream, 10);
    $request = preg_replace('/^Host:\s*[^\r\n]+/mi', 'Host: ' . $upstreamHost . ':' . $upstreamPort, $request);
    fwrite($upstream, $request);
    while (!feof($upstream)) {
        $chunk = fread($upstream, 16384);
        if ($chunk === false || $chunk === '') { break; }
        fwrite($client, $chunk);
    }
    fclose($upstream);
    fclose($client);
}
