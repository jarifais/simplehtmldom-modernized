# Estado y validación / Status and validation

## Español

### Procedencia de la instantánea

- Archivo de origen: `simplehtmldom_entorno_pruebas.zip` adjunto a la conversación.
- SHA-256 del archivo de origen: `832d141d830e6499e568522f7eae52ac02ace8dc0743b9a10521a951426bd716`.
- SHA-256 de la biblioteca original 1.9.1: `d3fcca27ce5d1c6ddb06402d8ea22ee1b3989f62c44e2025154c9d4eef501cdd`.
- SHA-256 de la biblioteca corregida F2-007: `bba1d651b7c58eab6e70a031a6d8ca52b86c68bc746188440652f877b9110d25`.

**Nota de trazabilidad:** una auditoría anterior inventarió un ZIP de SHA-256 distinto (`6611022c…`). No deben atribuirse automáticamente sus resultados a este archivo posterior. La evidencia histórica incluida con este ZIP declara F2-006 y F2-007 completadas.

### Resultados anteriores, no reproducidos como parte del historial de Git

El artefacto `RESULTADO_PRUEBAS_FASE_2.md` recibido informa tras F2-007:

- PHPUnit completo: 62 tests, 1.619 aserciones, correcto.
- Suite funcional fase1: 22 PASS, 0 FAIL.
- Suite funcional original: 10 PASS, 12 FAIL (comportamiento esperado por el comparador histórico).
- Smoke F3-001: 126/126 PASS.
- API pública, hash, UTF-8 y parada de servidores: PASS.

Estas cifras describen **evidencia histórica**, no resultados ejecutados en GitHub Actions ni una matriz de compatibilidad.

### Validación nueva de la copia publicable (2026-10-18)

Ejecución real en Linux/PHP 8.4.24, sobre la selección de archivos preparada para Git:

- HTTP y HTTPS locales: PASS.
- Suite funcional: fase1 22 PASS / 0 FAIL; original 10 PASS / 12 FAIL (diferencial esperado).
- Comparación API pública: idéntica; checkpoint de bibliotecas: intacto.
- Smoke F3-001: 126/126 PASS; 0 discrepancias, errores o timeouts.
- Finalización: servidores detenidos y certificados temporales retirados.
- PHPUnit completo: **NO EJECUTADO** por ausencia de extensiones `dom`, `mbstring` y `xmlwriter` en este PHP.

Los logs y el informe de esta comprobación se conservan fuera del paquete público y se entregan para revisión junto con él.

### Validación nueva de la copia publicable (2026-10-18)

Ejecución real en Linux/PHP 8.4.24, sobre la selección de archivos preparada para Git:

- HTTP y HTTPS locales: PASS.
- Suite funcional: fase1 22 PASS / 0 FAIL; original 10 PASS / 12 FAIL (diferencial esperado).
- Comparación API pública: idéntica; checkpoint de bibliotecas: intacto.
- Smoke F3-001: 126/126 PASS; 0 discrepancias, errores o timeouts.
- Finalización: servidores detenidos y certificados temporales retirados.
- PHPUnit completo: **NO EJECUTADO** por ausencia de extensiones `dom`, `mbstring` y `xmlwriter` en este PHP.

Los logs y el informe de esta comprobación se conservan fuera del paquete público y se entregan para revisión junto con él.

### Criterios de publicación

- No se modifican los PHP originales ni las licencias.
- El checkpoint incluido abarca **solo los archivos de biblioteca seleccionados para este repositorio** y no reproduce el inventario completo del antiguo entorno de trabajo.
- Se excluyen dependencias instaladas, backups, resultados generados, certificados, claves, orquestadores históricos y manuales aún no bilingües.
- Los defectos y riesgos remanentes se abordan en subfases independientes; no se considera finalizada una release.

---

## English

### Snapshot provenance

- Source archive: `simplehtmldom_entorno_pruebas.zip` attached to the conversation.
- Source archive SHA-256: `832d141d830e6499e568522f7eae52ac02ace8dc0743b9a10521a951426bd716`.
- Original 1.9.1 library SHA-256: `d3fcca27ce5d1c6ddb06402d8ea22ee1b3989f62c44e2025154c9d4eef501cdd`.
- Maintained F2-007 library SHA-256: `bba1d651b7c58eab6e70a031a6d8ca52b86c68bc746188440652f877b9110d25`.

**Traceability note:** an earlier audit inventoried a ZIP with a different SHA-256 (`6611022c…`). Its findings should not be automatically attributed to this later archive. The historical evidence bundled with the current ZIP records completed F2-006 and F2-007 work.

### Earlier results, not newly generated as Git history

The provided `RESULTADO_PRUEBAS_FASE_2.md` records the following after F2-007:

- Full PHPUnit suite: 62 tests, 1,619 assertions, successful.
- Maintained functional suite: 22 PASS, 0 FAIL.
- Original functional suite: 10 PASS, 12 FAIL (expected historical comparison behavior).
- F3-001 smoke: 126/126 PASS.
- Public API, hashes, UTF-8, and server shutdown: PASS.

These figures are **historical evidence**, not GitHub Actions runs or multi-version compatibility certification.

### New validation of the publishable snapshot (2026-10-18)

Fresh execution on Linux/PHP 8.4.24 using the selected files prepared for Git:

- Local HTTP and HTTPS: PASS.
- Functional suite: maintained version 22 PASS / 0 FAIL; original 10 PASS / 12 FAIL (expected differential).
- Public API comparison: unchanged; library checkpoint: unchanged.
- F3-001 smoke: 126/126 PASS; no mismatches, errors, or timeouts.
- Cleanup: local servers stopped and temporary certificates removed.
- Full PHPUnit suite: **NOT RUN** because the `dom`, `mbstring`, and `xmlwriter` PHP extensions are unavailable.

Logs and evidence for these checks are kept outside the public package and supplied separately for review.

### New validation of the publishable snapshot (2026-10-18)

Fresh execution on Linux/PHP 8.4.24 using the selected files prepared for Git:

- Local HTTP and HTTPS: PASS.
- Functional suite: maintained version 22 PASS / 0 FAIL; original 10 PASS / 12 FAIL (expected differential).
- Public API comparison: unchanged; library checkpoint: unchanged.
- F3-001 smoke: 126/126 PASS; no mismatches, errors, or timeouts.
- Cleanup: local servers stopped and temporary certificates removed.
- Full PHPUnit suite: **NOT RUN** because the `dom`, `mbstring`, and `xmlwriter` PHP extensions are unavailable.

Logs and evidence for these checks are kept outside the public package and supplied separately for review.

### Publication criteria

- The two PHP library sources and original licenses remain unchanged.
- The checkpoint covers **only the selected library files included in this repository**; it is not the full inventory of the former development environment.
- Installed dependencies, backups, generated results, certificates, keys, historical orchestrators, and monolingual legacy manuals are excluded.
- Remaining defects and risks will be handled in separate, reviewed steps; no stable release is claimed.
