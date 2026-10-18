# Matriz de compatibilidad / Compatibility matrix

## Español

### Estado de pruebas de la instantánea F2-007

| Versión PHP | Verificación de esta instantánea | Resultado |
|---|---|---|
| 7.4–8.1 | No ejecutada | Compatibilidad no demostrada |
| 8.2 | Registros históricos de trabajo previo, sin repetición en este contenedor | No certificada por esta validación |
| 8.3 | No ejecutada | Compatibilidad no demostrada |
| 8.4.24 | Suite funcional y smoke ejecutados en Linux | 22/22 y 126/126 PASS; API idéntica |
| 8.5 | No ejecutada | Compatibilidad no demostrada |

**PHPUnit completo:** no ejecutado en la validación actual: faltan las extensiones PHP `dom`, `mbstring` y `xmlwriter`. El historial de F2-007 registra 62 pruebas / 1.619 aserciones en Windows, pero no sustituye una ejecución nueva.

La raíz `composer.json` establece PHP >=8.2 para las herramientas de pruebas; esto no demuestra soporte de la biblioteca en todas esas versiones. No se publicará ninguna matriz como certificada hasta ejecutarla de forma reproducible.

---

## English

### F2-007 snapshot verification status

| PHP version | Verification of this snapshot | Result |
|---|---|---|
| 7.4–8.1 | Not run | Compatibility unverified |
| 8.2 | Historical work records only; not rerun in this environment | Not certified by this validation |
| 8.3 | Not run | Compatibility unverified |
| 8.4.24 | Functional suite and smoke executed on Linux | 22/22 and 126/126 PASS; unchanged public API |
| 8.5 | Not run | Compatibility unverified |

**Full PHPUnit suite:** not run in the current validation because the PHP `dom`, `mbstring`, and `xmlwriter` extensions are unavailable. Historical F2-007 records report 62 tests / 1,619 assertions on Windows; these are not a substitute for a fresh execution.

The root `composer.json` requires PHP >=8.2 for the test tools; this does not establish that the library supports every such version. A compatibility matrix will not be advertised as certified until reproducibly executed.
