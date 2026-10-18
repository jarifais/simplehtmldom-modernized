# CI-001 — Validación automática / Automated validation

## Español

### Objetivo
Preparar validación reproducible en runners **alojados por GitHub**: Ubuntu 24.04 y Windows Server 2025, ambos con PHP 8.4. No requiere un runner propio en Windows ni Debian.

### Archivos de esta subfase
- `.github/workflows/ci.yml`: matriz de GitHub Actions, desencadenada por pull requests a `main`, cambios en `main` o ejecución manual.
- `.github/ci/validar.ps1`: ejecutor común PowerShell 7, encapsulado en `& { }`.

### Comprobaciones previstas
1. PHP, extensiones, inventario Git, UTF-8 y lint de todos los archivos PHP.
2. Checkpoint SHA-256 y referencia original intacta.
3. Servidor HTTP y proxy HTTPS locales con certificado temporal generado durante la ejecución.
4. Suite funcional de 22 casos, comparación de API, comprobación por `id` en `todos.php` y por `test` en los resultados JSON.
5. Smoke F3-001 (126 muestras) y PHPUnit específico/completo mediante Composer.
6. Integridad posterior, limpieza de procesos y eliminación de certificados. Los informes temporales de `resultados/` pueden adjuntarse al job durante 7 días.

### Límites y criterios
- **No se ha certificado ningún PASS** hasta contar con logs reales de ambas plataformas.
- La matriz inicial incluye únicamente PHP 8.4. Se ampliará mediante otras subfases y ejecución de pruebas.
- El servidor HTTPS utiliza un certificado autofirmado **solo en las pruebas locales**; no es una recomendación para producción.
- Los posibles problemas del ejecutor se corregirán sin cambiar las bibliotecas para ocultar fallos.
- `DOC-001` sigue siendo una PR independiente. `CI-001` no la fusiona ni modifica `main`.
- Si el job falla, se inspeccionarán sus logs y se hará una corrección mínima antes de autorizar la fusión.

---

## English

### Purpose
Prepare reproducible verification on **GitHub-hosted runners**: Ubuntu 24.04 and Windows Server 2025, both with PHP 8.4. Neither a local Windows runner nor a Debian server is required.

### Files in this subphase
- `.github/workflows/ci.yml`: GitHub Actions matrix, triggered by pull requests targeting `main`, pushes to `main`, and manual dispatch.
- `.github/ci/validar.ps1`: shared PowerShell 7 runner, wrapped in `& { }`.

### Planned checks
1. PHP environment, required extensions, Git inventory, UTF-8 and PHP lint.
2. SHA-256 checkpoint and unchanged upstream reference.
3. Temporary local HTTP server and HTTPS proxy with a test-only certificate.
4. 22 functional cases, public API comparison, `id` extraction from `todos.php` and `test` extraction from result JSON.
5. F3-001 smoke (126 samples), targeted PHPUnit and the full PHPUnit suite using Composer.
6. Post-run integrity checks, process shutdown and certificate removal. Temporary `resultados/` reports may be attached to the job for 7 days.

### Limitations and acceptance criteria
- **No PASS is claimed** until both operating systems have produced real execution logs.
- The initial matrix tests PHP 8.4 only. Other PHP versions require subsequent verified work.
- Self-signed HTTPS certificates are used **only in local tests** and are not suitable production guidance.
- Harness failures must be fixed in isolation rather than hidden by changing the library.
- `DOC-001` remains a separate pull request. `CI-001` does not merge it or modify `main`.
- Failed jobs require log review and a minimal correction before merging.
