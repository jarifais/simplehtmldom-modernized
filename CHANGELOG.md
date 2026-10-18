# Registro de cambios / Changelog

## Español

### Instantánea inicial para revisión (sin versión estable)

Esta importación es una **instantánea de un trabajo anterior**. Los cambios que se enumeran proceden de las evidencias y del código recibido, no se han realizado ahora.

- **F2-001:** corrección del selector general de hermanos (`~`).
- **F2-002:** corrección del operador de idioma (`|=`).
- **F2-003:** contrato de `hasAttribute()` tras eliminar atributos.
- **F2-004 y F2-006:** preservación de saltos de línea y posterior reparación estructural de `<pre>`.
- **F2-005:** protección frente a colisiones de marcadores de *noise*.
- **F2-007:** contrato de tipos de entrada para `str_get_html()`, constructor y `load()`.
- **F3-001:** infraestructura de pruebas *smoke* de rendimiento sin conclusiones comparativas.

No se asigna número de release ni se afirma soporte PHP multiversión aún no probado.

---

## English

### Initial review snapshot (no stable version)

This import is a **snapshot of earlier work**. The changes below are supported by the received source and historical records; they were not implemented as part of this import.

- **F2-001:** general sibling selector (`~`) fix.
- **F2-002:** language attribute operator (`|=`) fix.
- **F2-003:** `hasAttribute()` semantics after attribute removal.
- **F2-004 and F2-006:** newline preservation and subsequent structural repair of `<pre>`.
- **F2-005:** collision protection for noise markers.
- **F2-007:** input type contract across `str_get_html()`, constructor, and `load()`.
- **F3-001:** performance smoke-test infrastructure without comparative conclusions.

No release version or unverified multi-PHP-version support is claimed.
