# Cambios de la Fase 3 / Phase 3 changes

> **Español:** Transcripción bilingüe del registro histórico F3-001/F3-001A. Origen: `simplehtmldom_entorno_pruebas.zip`, archivo `CAMBIOS_FASE_3.md`, SHA-256 original `f79ca2780cf5703ec810dd162fd1f164643d36be795227d12cebbce69d4ab2e0`. Las fechas y conclusiones pertenecen a las ejecuciones históricas, no a GitHub Actions.
>
> **English:** Bilingual rendering of the historical F3-001/F3-001A record. Source: `simplehtmldom_entorno_pruebas.zip`, file `CAMBIOS_FASE_3.md`, original SHA-256 `f79ca2780cf5703ec810dd162fd1f164643d36be795227d12cebbce69d4ab2e0`. Dates and conclusions concern historical runs, not GitHub Actions.

## Español

<!-- F3-001-CHANGES BEGIN -->
### F3-001 — Infraestructura reproducible

Fecha: 2026-07-26 20:42:55

- Orquestador principal: PHP CLI autónomo.
- Servidores HTTP y TLS gestionados directamente con proc_open.
- Fixtures deterministas, worker aislado, warmups, muestras y timeouts.
- Workloads: parse, find_by_tag, find_by_class, find_by_attribute, traverse_nodes, read_plaintext, read_outertext.
- Esquema JSON: 1.

Fixtures:
- small: a4e4ceb1d707df36cbb3214cfbdcb2e4421147417f2de5cc6047496e773ebad7 (2895 bytes)
- medium: 28f2cc4d6e20500fd1af69c06e8a70244613d811cc9f8a3dfcb910cac2b10e96 (28762 bytes)
- large: 263d82eedb88098dd45fe4b9ceb01f7654b55e7f6a4a3651dbe4810c05ff7c7b (87861 bytes)

F3-001 completada.
F3-002 pendiente.
F3-003 pendiente.
<!-- F3-001-CHANGES END -->

<!-- F3-001A-CHANGES BEGIN -->
### F3-001A — Desacoplamiento del checkpoint de fase1

Fecha: 2026-07-26 23:28:03

- F3001 obtiene el hash esperado de `librerias/fase1/simple_html_dom.php` desde `HASHES_LIBRERIAS_BASE.json`.
- `tools/f3_001.php` usa el mismo checkpoint canónico y deja de fijar el digest completo de fase1.
- No se modificaron bibliotecas, API, fixtures, workloads ni resultados benchmark almacenados.
- F2-006 continúa pendiente y queda desbloqueada tras esta micro-subfase.
<!-- F3-001A-CHANGES END -->

**Nota de interpretación:** «F2-006 pendiente» describe el estado del 26 de julio de 2026, no el estado de la instantánea F2-007 actualmente publicada. Las rutas de herramientas mencionadas son referencias históricas; no se afirma que todas formen parte del paquete público.

---

## English

### F3-001 — Reproducible infrastructure

Date: 2026-07-26 20:42:55

- Main orchestrator: standalone PHP CLI.
- HTTP and TLS servers managed directly with proc_open.
- Deterministic fixtures, isolated worker, warmups, samples, and timeouts.
- Workloads: parse, find_by_tag, find_by_class, find_by_attribute, traverse_nodes, read_plaintext, read_outertext.
- JSON schema: 1.

Fixtures:
- small: a4e4ceb1d707df36cbb3214cfbdcb2e4421147417f2de5cc6047496e773ebad7 (2895 bytes)
- medium: 28f2cc4d6e20500fd1af69c06e8a70244613d811cc9f8a3dfcb910cac2b10e96 (28762 bytes)
- large: 263d82eedb88098dd45fe4b9ceb01f7654b55e7f6a4a3651dbe4810c05ff7c7b (87861 bytes)

F3-001 completed.
F3-002 pending.
F3-003 pending.

### F3-001A — Decoupling the phase1 checkpoint

Date: 2026-07-26 23:28:03

- F3001 reads the expected hash for `librerias/fase1/simple_html_dom.php` from `HASHES_LIBRERIAS_BASE.json`.
- The historical `tools/f3_001.php` uses the same canonical checkpoint instead of hardcoding the entire phase1 digest.
- Libraries, API, fixtures, workloads, and stored benchmark results were not changed.
- At that time F2-006 was still pending, and this subphase unblocked it.

**Interpretation note:** “F2-006 pending” refers to the July 26, 2026 historical status, not the currently published F2-007 snapshot. Referenced tooling paths may exist only in the historical archive.
