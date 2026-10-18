<?php
/**
 * Español: Ejemplo de instalación manual desde el ZIP.
 * English: Manual installation example from the ZIP.
 */
require_once dirname(__DIR__) . '/simple_html_dom.php';

$html = str_get_html('<main><h1>Hola mundo</h1></main>');
if ($html === false) {
    fwrite(STDERR, "ERROR: no se pudo analizar HTML.\n");
    exit(1);
}
$node = $html->find('h1', 0);
if ($node === null || $node->plaintext !== 'Hola mundo') {
    $html->clear();
    fwrite(STDERR, "ERROR: resultado inesperado.\n");
    exit(2);
}
echo $node->plaintext . PHP_EOL;
$html->clear();
