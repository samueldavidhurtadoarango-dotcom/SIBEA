# Pruebas de concurrencia (B1 y I1)

Estas dos fallas solo aparecen cuando **dos personas hacen lo mismo al mismo tiempo**, así que no se
pueden probar en `pruebas_reglas.sql`, que usa una sola conexión. Se prueban con **dos clientes
`mysql` en paralelo**: la sesión A hace su cambio y espera 4 segundos antes de confirmarlo
(`SELECT SLEEP(4)`); la sesión B empieza 1 segundo después e intenta lo mismo.

Los guiones están en `pruebas/concurrencia/`:

| Archivo | Qué hace |
|---|---|
| `preparar.sql` | Crea los datos: convocatoria 1 con **1 cupo** y dos postulaciones Validadas; convocatoria 2 con una asignación de **2 pagos**. |
| `b1_sesion_a.sql` / `b1_sesion_b.sql` | B1: A y B aprueban a la vez dos postulaciones distintas de la convocatoria de 1 cupo. |
| `b1_resultado.sql` | Muestra cupos y aprobadas. |
| `i1_sesion_a.sql` / `i1_sesion_b.sql` | I1: A registra el pago 1 y B el pago 2 (el último) a la vez. |
| `i1_resultado.sql` | Muestra pagos vigentes y estado de la asignación. |
| `ejecutar.sh` | Recrea la base, prepara los datos y lanza A en segundo plano y B en primer plano. |

> **Atención:** `ejecutar.sh` vuelve a crear la base `benefitrack_v2` (borra sus datos).

## Por qué fallaba

MariaDB usa por defecto el aislamiento `REPEATABLE READ`: dentro de una transacción, una lectura
normal (`SELECT COUNT(*) ...`) ve una **foto** de la base tomada al empezar a leer. El trigger
bloqueaba la convocatoria (o la asignación) con `FOR UPDATE`, así que B esperaba a que A terminara;
pero cuando B por fin contaba, contaba sobre su foto vieja, donde el cambio de A todavía no existía.

La corrección agrega `LOCK IN SHARE MODE` a esos conteos: es una lectura "con candado" que siempre
lee lo último confirmado.

## Paso a paso (con dos consolas)

Puede hacerse a mano abriendo dos consolas de `mysql` (por ejemplo, dos ventanas **Shell** de XAMPP):

1. En cualquiera de las dos: ejecutar `benefitrack_v2.sql` y luego `preparar.sql`.
2. **Prueba B1**
   1. Consola A: `source codigo/base-de-datos/pruebas/concurrencia/b1_sesion_a.sql` (queda esperando 4 s).
   2. Antes de que pasen los 4 s, consola B: `source codigo/base-de-datos/pruebas/concurrencia/b1_sesion_b.sql`.
   3. Al terminar ambas: `source codigo/base-de-datos/pruebas/concurrencia/b1_resultado.sql`.
3. **Prueba I1**: igual con `i1_sesion_a.sql`, `i1_sesion_b.sql` e `i1_resultado.sql`.

O de una vez, desde Git Bash:

```sh
sh codigo/base-de-datos/pruebas/concurrencia/ejecutar.sh "/c/xampp/mysql/bin/mysql.exe -u root -P 3306 -h 127.0.0.1"
```

## Resultado real ANTES de la corrección

Ejecutado el 2026-10-26 en MariaDB 10.4.32 (instancia de prueba en el puerto 3399), con el esquema
del commit `926a985` (sin `LOCK IN SHARE MODE`):

```
+--------------------+
| mensaje            |
+--------------------+
| Preparación lista  |
+--------------------+
===== Prueba B1: dos aprobaciones en paralelo con 1 cupo =====
+--------+
| espera |
+--------+
|      0 |
+--------+
+------------------------------------------------------------+
| sesion_b                                                   |
+------------------------------------------------------------+
| B: intenta aprobar la postulación 2 (queda esperando a A)  |
+------------------------------------------------------------+
+-----------------+
| sesion_b        |
+-----------------+
| B: COMMIT hecho |
+-----------------+
+-----------------------------------------------------------+
| sesion_a                                                  |
+-----------------------------------------------------------+
| A: aprobó la postulación 1, espera 4 s antes del COMMIT   |
+-----------------------------------------------------------+
+--------+
| espera |
+--------+
|      0 |
+--------+
+-----------------+
| sesion_a        |
+-----------------+
| A: COMMIT hecho |
+-----------------+
+-------+-----------+-------------------------------+
| cupos | aprobadas | veredicto                     |
+-------+-----------+-------------------------------+
|     1 |         2 | ERROR: se superaron los cupos |
+-------+-----------+-------------------------------+
===== Prueba I1: los dos últimos pagos en paralelo =====
+--------+
| espera |
+--------+
|      0 |
+--------+
+---------------------------------------------+
| sesion_b                                    |
+---------------------------------------------+
| B: registra el pago 2 (queda esperando a A) |
+---------------------------------------------+
+-----------------+
| sesion_b        |
+-----------------+
| B: COMMIT hecho |
+-----------------+
+-----------------------------------------------------+
| sesion_a                                            |
+-----------------------------------------------------+
| A: registró el pago 1, espera 4 s antes del COMMIT  |
+-----------------------------------------------------+
+--------+
| espera |
+--------+
|      0 |
+--------+
+-----------------+
| sesion_a        |
+-----------------+
| A: COMMIT hecho |
+-----------------+
+--------------+----------------+----------+---------------------------------------------------------------------+
| numero_pagos | pagos_vigentes | estado   | veredicto                                                           |
+--------------+----------------+----------+---------------------------------------------------------------------+
|            2 |              2 | En curso | ERROR: todos los pagos están registrados pero no quedó Finalizado   |
+--------------+----------------+----------+---------------------------------------------------------------------+
```

Las dos aprobaciones pasaron (**2 aprobadas con 1 cupo**) y, con los dos pagos registrados, la
asignación **quedó En curso** en vez de Finalizado.

## Resultado real DESPUÉS de la corrección

Mismo procedimiento, con el esquema del commit `8b88ce2`:

```
+--------------------+
| mensaje            |
+--------------------+
| Preparación lista  |
+--------------------+
===== Prueba B1: dos aprobaciones en paralelo con 1 cupo =====
ERROR 1644 (45000) at line 8: No hay cupos disponibles en la convocatoria: 1 aprobadas de 1 cupos
+--------+
| espera |
+--------+
|      0 |
+--------+
+------------------------------------------------------------+
| sesion_b                                                   |
+------------------------------------------------------------+
| B: intenta aprobar la postulación 2 (queda esperando a A)  |
+------------------------------------------------------------+
+-----------------------------------------------------------+
| sesion_a                                                  |
+-----------------------------------------------------------+
| A: aprobó la postulación 1, espera 4 s antes del COMMIT   |
+-----------------------------------------------------------+
+--------+
| espera |
+--------+
|      0 |
+--------+
+-----------------+
| sesion_a        |
+-----------------+
| A: COMMIT hecho |
+-----------------+
+-------+-----------+-----------------------------------+
| cupos | aprobadas | veredicto                         |
+-------+-----------+-----------------------------------+
|     1 |         1 | CORRECTO: no se superan los cupos |
+-------+-----------+-----------------------------------+
===== Prueba I1: los dos últimos pagos en paralelo =====
+--------+
| espera |
+--------+
|      0 |
+--------+
+---------------------------------------------+
| sesion_b                                    |
+---------------------------------------------+
| B: registra el pago 2 (queda esperando a A) |
+---------------------------------------------+
+-----------------+
| sesion_b        |
+-----------------+
| B: COMMIT hecho |
+-----------------+
+-----------------------------------------------------+
| sesion_a                                            |
+-----------------------------------------------------+
| A: registró el pago 1, espera 4 s antes del COMMIT  |
+-----------------------------------------------------+
+--------+
| espera |
+--------+
|      0 |
+--------+
+-----------------+
| sesion_a        |
+-----------------+
| A: COMMIT hecho |
+-----------------+
+--------------+----------------+------------+-----------------------------+
| numero_pagos | pagos_vigentes | estado     | veredicto                   |
+--------------+----------------+------------+-----------------------------+
|            2 |              2 | Finalizado | CORRECTO: quedó Finalizado  |
+--------------+----------------+------------+-----------------------------+
```

Ahora la sesión B recibe `No hay cupos disponibles en la convocatoria: 1 aprobadas de 1 cupos`
(la línea `ERROR 1644` aparece arriba porque el cliente imprime los errores antes que los resultados),
y la asignación queda **Finalizado** con sus 2 pagos.
