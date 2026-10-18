# Simple HTML DOM Modernized — paquete de desarrollo / Development package

## Español

Este ZIP incluye una copia verificable de `simple_html_dom.php`, la licencia MIT, un ejemplo y el manifiesto `SHA256SUMS.txt`.

**Estado:** versión de desarrollo del fork no oficial basado en Simple HTML DOM 1.9.1, con correcciones hasta F2-007. No es una versión estable ni está publicada todavía en Packagist. Las pruebas automatizadas de esta distribución se ejecutan con PHP 8.4 en Windows y Ubuntu; no se afirma compatibilidad con otras versiones sin validación.

### Instalación manual

1. Extrae el ZIP en una carpeta de tu proyecto PHP.
2. Copia `simple_html_dom.php` a tu aplicación, o referencia su ruta.
3. Cárgalo mediante `require_once`; no requiere Composer.
4. Para comprobar la instalación, ejecuta `php ejemplos/ejemplo_basico.php` desde la carpeta extraída. Debe mostrar `Hola mundo`.

Ejemplo mínimo:

```php
<?php
require_once __DIR__ . '/simple_html_dom.php';
$html = str_get_html('<h1>Hola mundo</h1>');
if ($html !== false) {
    $node = $html->find('h1', 0);
    if ($node !== null) { echo $node->plaintext; }
    $html->clear();
}
```

### Integridad

El archivo `SHA256SUMS.txt` contiene un hash SHA-256 para cada archivo del paquete excepto el propio manifiesto. Verifica los hashes después de extraer. El artefacto de GitHub Actions incluye asimismo el SHA-256 del ZIP como archivo externo `.sha256`.

El código original y el aviso de copyright están cubiertos por `LICENSE` (MIT). El parser **no** sanitiza HTML: no lo uses como protección contra XSS ni abras URL no confiables sin controles adicionales.

Repositorio: https://github.com/jarifais/simplehtmldom-modernized

---

## English

This ZIP contains a verifiable single-file PHP library, the original MIT license notices, an example and a `SHA256SUMS.txt` manifest.

**Status:** development build of an unofficial fork based on Simple HTML DOM 1.9.1, with fixes through F2-007. This is not a stable release and is not yet published on Packagist. Automated checks for this distribution run on PHP 8.4 under Windows and Ubuntu; other PHP versions are not claimed without testing.

### Manual installation

1. Extract the ZIP to a directory in your PHP project.
2. Copy `simple_html_dom.php` to your application, or reference it by path.
3. Load it using `require_once`; Composer is not required.
4. Run `php ejemplos/ejemplo_basico.php` from the extracted directory. It should print `Hola mundo`.

### Integrity

`SHA256SUMS.txt` lists SHA-256 hashes for every package file except itself. Verify hashes after extraction. The GitHub Actions artifact also includes an external `.sha256` file for the ZIP archive.

The original copyright notices and MIT license are in `LICENSE`. This parser is **not** an HTML sanitizer and should not be treated as an XSS defense.

Repository: https://github.com/jarifais/simplehtmldom-modernized
