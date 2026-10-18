# Simple HTML DOM Modernized — v0.1.0-beta.1

> **Versión preliminar / Prerelease.** Fork no oficial de Simple HTML DOM 1.9.1. No es una versión estable y todavía no se ha registrado en Packagist.

## Español

### Primera beta pública

Esta es la primera entrega beta del fork con las correcciones incrementales F2-001 a F2-007, manteniendo la API global clásica (`str_get_html()`, `file_get_html()`, `simple_html_dom` y `simple_html_dom_node`).

**Archivos de esta release:** `simple_html_dom.php` (uso tradicional), `simplehtmldom-modernized-v0.1.0-beta.1.zip` (biblioteca, licencia, guía y ejemplo), `LICENSE`, `NOTAS_v0.1.0-beta.1.md`, `SHA256SUMS_RELEASE.txt` y un fichero `.zip.sha256` externo.

**Integridad:** SHA-256 esperado de `simple_html_dom.php`: `bba1d651b7c58eab6e70a031a6d8ca52b86c68bc746188440652f877b9110d25`. El manifiesto de la release contiene los SHA-256 de cada archivo principal; el ZIP contiene también su propio `SHA256SUMS.txt`.

**Comprobaciones exigidas para publicar:** CI Ubuntu 24.04 y Windows 2025 (PHP 8.4), pruebas funcionales 22/22, PHPUnit 64 pruebas/1.629 aserciones, smoke 126/126, API/hashes y servidores, ZIP binariamente idéntico en ambos sistemas, e instalación Composer desde GitHub. Estas cifras corresponden a las ejecuciones verificadas de la rama publicada; no certifican otras versiones de PHP.

### Instalación manual

Descarga `simple_html_dom.php` y guárdalo junto a tu proyecto:

```php
<?php
require_once __DIR__ . '/simple_html_dom.php';
$html = str_get_html('<h1>Hola mundo</h1>');
echo $html->find('h1', 0)->plaintext;
$html->clear();
```

Para ZIP, descomprime y consulta su `README.md`. No se necesita Composer.

### Composer mediante GitHub

Antes de publicar en Packagist, configura GitHub como repositorio VCS:

```sh
composer config repositories.simplehtmldom-modernized vcs https://github.com/jarifais/simplehtmldom-modernized
composer require jarifais/simplehtmldom-modernized:0.1.0-beta.1
```

Luego carga `vendor/autoload.php`. El manifiesto declara PHP >=8.2 y `ext-iconv` obligatoria; `ext-mbstring` es opcional. **Por ahora solo PHP 8.4 ha superado la matriz de CI.**

### Advertencias

- Esta biblioteca **no sanitiza HTML** ni sustituye medidas contra XSS; controla las URL y las rutas externas.
- No instales simultáneamente otros forks que declaren las mismas funciones y clases globales.
- Se conserva la licencia MIT y el copyright de los autores originales. Este fork no está afiliado al proyecto original.
- No se garantiza aún compatibilidad completa con PHP 7.4–8.5 ni ausencia de todos los defectos de HTML malformado.

---

## English

### First public beta

This prerelease is an unofficial fork of Simple HTML DOM 1.9.1, including incremental F2-001 through F2-007 fixes while retaining the traditional global API (`str_get_html()`, `file_get_html()`, `simple_html_dom`, `simple_html_dom_node`).

**Assets:** standalone `simple_html_dom.php`, `simplehtmldom-modernized-v0.1.0-beta.1.zip` (library, license, instructions and example), `LICENSE`, bilingual `NOTAS_v0.1.0-beta.1.md`, `SHA256SUMS_RELEASE.txt` and a separate `.zip.sha256` file.

**Integrity:** expected SHA-256 of `simple_html_dom.php`: `bba1d651b7c58eab6e70a031a6d8ca52b86c68bc746188440652f877b9110d25`. Check the release manifest for file checksums, and the ZIP's internal `SHA256SUMS.txt` for its contents.

**Release gates:** successful PHP 8.4 CI on Ubuntu 24.04 and Windows 2025, 22/22 functional cases, PHPUnit 64 tests/1,629 assertions, 126/126 benchmark smoke samples, API/source integrity and server cleanup, byte-identical ZIPs across both systems, and GitHub VCS Composer installation. These results apply to the tested release commit only; other PHP versions are not certified.

### Manual installation

Download `simple_html_dom.php` and load it directly using `require_once`. Alternatively, extract the ZIP and consult its `README.md`. Composer is not required.

### Composer via GitHub

Before Packagist registration:

```sh
composer config repositories.simplehtmldom-modernized vcs https://github.com/jarifais/simplehtmldom-modernized
composer require jarifais/simplehtmldom-modernized:0.1.0-beta.1
```

Then load `vendor/autoload.php`. Composer metadata requires PHP >=8.2 and `ext-iconv`; `ext-mbstring` is optional. **Only PHP 8.4 has been verified in the CI matrix so far.**

### Warnings

This library is **not an HTML sanitizer** or an XSS defense. Validate externally supplied URLs and paths. Avoid loading other Simple HTML DOM forks with conflicting global definitions. The original MIT license and upstream copyright are preserved; this fork is unofficial. Full support for PHP 7.4–8.5 and all malformed-markup scenarios has not yet been certified.
