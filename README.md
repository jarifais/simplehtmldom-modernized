# Simple HTML DOM 1.9.1 — Modernizado / Modernized

> **Español:** Repositorio de desarrollo y validación de un fork no oficial de Simple HTML DOM 1.9.1. **No es todavía una versión estable ni un paquete anunciado en Packagist.**
>
> **English:** Development and validation repository for an unofficial fork of Simple HTML DOM 1.9.1. **This is not yet a stable release or an announced Packagist package.**

## Español

### Objetivo

Mantener la API pública de Simple HTML DOM 1.9.1 y aplicar correcciones incrementales verificadas frente a una copia original inalterada. Este repositorio contiene una **instantánea de trabajo**, no una reescritura ni una actualización automática a la rama 2.x.

### Estructura

- `librerias/original/simple_html_dom.php`: referencia original 1.9.1, sin modificar.
- `librerias/fase1/simple_html_dom.php`: variante corregida hasta F2-007.
- `pruebas/` y `tests/`: comparación funcional y pruebas PHPUnit.
- `servidor_objetivo/`: fixtures y servidores de pruebas locales.
- `benchmarks/`: infraestructura *smoke* F3-001; **no** proporciona comparativas concluyentes de rendimiento.
- `HASHES_LIBRERIAS_BASE.json`: checkpoint de los archivos de biblioteca incluidos en este repositorio.

### Instalación manual: archivo PHP único

Descarga [`simple_html_dom.php`](simple_html_dom.php), pulsa **Raw** en GitHub y guarda el archivo junto a tu aplicación. No necesita Composer ni otros archivos de este repositorio para la carga clásica. Es una copia byte a byte de `librerias/fase1/simple_html_dom.php`, con SHA-256 `bba1d651b7c58eab6e70a031a6d8ca52b86c68bc746188440652f877b9110d25`. La versión continúa en desarrollo; todavía no existe una release estable.

### Uso básico

```php
require_once __DIR__ . '/simple_html_dom.php';
$html = str_get_html('<p class="ejemplo">Hola</p>');
if ($html !== false) {
    echo $html->find('p', 0)->plaintext;
    $html->clear();
}
```

### Validación local

El entorno de pruebas de Windows requiere PHP **8.2 o posterior**, PowerShell y, para HTTPS, OpenSSL. Si Composer está disponible, puede instalar PHPUnit. El `composer.json` de la raíz configura el entorno de pruebas, **no** es aún un paquete distribuible. Desde la raíz:

```powershell
.\configurar_entorno.ps1
.\iniciar_entorno.ps1
try { .\ejecutar_pruebas.ps1 } finally { .\detener_entorno.ps1 }
```

Las declaraciones de compatibilidad del archivo `composer.json` heredado de pruebas y las del paquete antiguo **no equivalen a una matriz multiversión demostrada**. Los resultados históricos están identificados en [Estado y validación](docs/ESTADO_Y_VALIDACION.md).

### Estado y limitaciones

Las correcciones F2-001…F2-007 están presentes en la instantánea proporcionada. La siguiente tarea del roadmap reconstruido es revisar etiquetas especiales y casos adversariales (F2-008), junto con la independencia de las pruebas del pipeline. La publicación inicial no implica que la versión sea estable ni que PHP 7.4–8.5 esté certificado.

Simple HTML DOM **no es un sanitizador HTML**: no lo utilices como barrera contra XSS. Controla también las URL y rutas externas proporcionadas por usuarios.

### Documentación e idioma

Toda documentación **nueva** se redacta primero en español y después en inglés. Los manuales heredados que aún no siguen esta regla no forman parte de esta importación inicial; siguen conservados en el archivo de trabajo original.

### Licencia

Licencia MIT. Se conserva el texto legal original en [`LICENSE`](LICENSE) y los avisos de los titulares originales. Fork no oficial, sin afiliación declarada con el equipo original.

---

## English

### Purpose

Preserve the public API of Simple HTML DOM 1.9.1 while introducing incremental, verified fixes against an unchanged original reference. This repository is a **development snapshot**, not a rewrite or an automatic migration to the 2.x line.

### Layout

- `librerias/original/simple_html_dom.php`: unchanged upstream 1.9.1 reference.
- `librerias/fase1/simple_html_dom.php`: maintained variant through F2-007.
- `pruebas/` and `tests/`: functional comparison and PHPUnit tests.
- `servidor_objetivo/`: local test server and fixtures.
- `benchmarks/`: F3-001 smoke-test infrastructure; **not** a conclusive performance comparison.
- `HASHES_LIBRERIAS_BASE.json`: checkpoint for the library files included in this repository.

### Manual installation: one PHP file

Download [`simple_html_dom.php`](simple_html_dom.php), select **Raw** on GitHub, and save it alongside your application. Traditional usage needs neither Composer nor other files from this repository. It is byte-for-byte identical to `librerias/fase1/simple_html_dom.php`, with SHA-256 `bba1d651b7c58eab6e70a031a6d8ca52b86c68bc746188440652f877b9110d25`. This fork remains under development; no stable release is available yet.

### Basic usage

```php
require_once __DIR__ . '/simple_html_dom.php';
$html = str_get_html('<p class="example">Hello</p>');
if ($html !== false) {
    echo $html->find('p', 0)->plaintext;
    $html->clear();
}
```

### Local validation

The Windows test environment requires PHP **8.2 or newer**, PowerShell, and OpenSSL for HTTPS. Composer can install PHPUnit when available. The root `composer.json` describes the **test environment**, not yet a distributable library package. From the repository root:

```powershell
.\configurar_entorno.ps1
.\iniciar_entorno.ps1
try { .\ejecutar_pruebas.ps1 } finally { .\detener_entorno.ps1 }
```

The inherited test environment `composer.json` and the former package metadata **do not establish a verified multi-version compatibility matrix**. See [Status and validation](docs/ESTADO_Y_VALIDACION.md).

### Status and limitations

Fixes F2-001 through F2-007 are present in the provided snapshot. The next task in the reconstructed roadmap is to assess special tags and adversarial markup (F2-008), along with test-pipeline independence. This initial snapshot does not claim stable-release status or certified support across PHP 7.4–8.5.

Simple HTML DOM **is not an HTML sanitizer**; do not rely on it for XSS prevention. Restrict external URLs and paths supplied by users.

### Documentation and language

All **new** documentation uses Spanish first and English second. Legacy manuals not yet meeting this requirement are excluded from this initial import and remain preserved in the original working archive.

### License

MIT. The original legal text and copyright notices are preserved in [`LICENSE`](LICENSE). This is an unofficial fork with no claimed affiliation with the upstream team.
