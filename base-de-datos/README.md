# Base de datos de BenefitRack

| Archivo | Qué es |
|---|---|
| `benefitrack_v2.sql` | Script que crea la base `benefitrack_v2` completa: tablas, reglas, triggers y datos iniciales. |
| `pruebas/pruebas_reglas.sql` | Pruebas de las reglas de negocio. Imprime OK o FALLO por cada caso. |
| `CAMBIOS.md` | Qué cambió respecto al script original y por qué. |
| `../originales/BENEFITRACK_CORREGIDA.sql` | Script original, sin cambios, como referencia. |

Funciona en MariaDB 10.4 (la de XAMPP) y en MariaDB 11.x.

> **Atención:** `benefitrack_v2.sql` empieza con `DROP DATABASE IF EXISTS benefitrack_v2`.
> Si ya existe una base con ese nombre, **la borra con todos sus datos** y la crea de nuevo vacía.

---

## 1. Ejecutar el script en XAMPP

Primero encienda MySQL en el panel de control de XAMPP (botón **Start** de MySQL).

### Opción A: phpMyAdmin

1. Abra <http://localhost/phpmyadmin>.
2. Sin seleccionar ninguna base, entre a la pestaña **Importar**.
3. En **Archivo a importar** elija `base-de-datos/benefitrack_v2.sql`.
4. Deje el conjunto de caracteres en `utf-8` y pulse **Importar** (abajo).
5. Debe aparecer el mensaje de importación exitosa y, a la izquierda, la base `benefitrack_v2` con 23 tablas.

### Opción B: línea de comandos

Abra la consola (botón **Shell** de XAMPP, o `cmd`) y ejecute, desde la carpeta del proyecto:

```bat
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < base-de-datos\benefitrack_v2.sql
```

Si su usuario `root` tiene contraseña, agregue `-p` y escríbala cuando la pida.
Si no aparece ningún mensaje de error, el script terminó bien.

---

## 2. Ejecutar las pruebas

Las pruebas se ejecutan **después** del script, sobre la base recién creada.
Todos los datos de prueba se crean dentro de una transacción que se deshace al final,
así que la base queda igual que antes y las pruebas se pueden repetir.

### Opción A: phpMyAdmin

1. Seleccione la base `benefitrack_v2` a la izquierda.
2. Pestaña **Importar**, elija `base-de-datos/pruebas/pruebas_reglas.sql` y pulse **Importar**.
3. El último resultado es la tabla de resumen: una fila por caso con `OK` o `FALLO`
   y, al final, la fila `TOTAL` con el veredicto (`TODAS LAS PRUEBAS PASAN`).

### Opción B: línea de comandos

```bat
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 --table < base-de-datos\pruebas\pruebas_reglas.sql
```

La salida muestra la misma tabla de resumen. Resultado esperado:

```
| TOTAL | OK | 97 casos: 97 OK, 0 FALLO | TODAS LAS PRUEBAS PASAN |
```

Si algún caso dice `FALLO`, la columna `detalle` explica qué pasó (por ejemplo, el
error que devolvió la base o el valor que encontró).

---

## 3. Nota para la aplicación: `@app_id_usuario`

La base de datos guarda en el historial, la auditoría, los pagos y las novedades **quién**
hizo cada cambio. Para eso, la aplicación debe ejecutar esto **en cada conexión**, justo
después de conectarse y antes de cualquier otra consulta:

```sql
SET @app_id_usuario = <id del usuario que inició sesión>;
SET @app_ip = '<IP del cliente>';   -- opcional, para la auditoría
```

Si no se define, la base guarda `NULL` en lugar del usuario. Si la aplicación reutiliza
conexiones (pool), debe volver a fijar la variable cada vez que toma una conexión para otro
usuario.

Otros puntos a tener en cuenta al programar:

- Las reglas fallan con `SQLSTATE 45000` y un mensaje en español (por ejemplo,
  `No hay cupos disponibles en la convocatoria: 2 aprobadas de 2 cupos`). La aplicación
  puede mostrar ese mensaje al usuario.
- Los pagos se registran con `INSERT INTO pagos (...) VALUES (...)`. No use
  `INSERT INTO pagos ... SELECT ... FROM asignaciones_beneficio`: MariaDB lo rechaza con el
  error 1442, porque el trigger que finaliza la asignación necesita actualizar esa tabla.
- Las novedades de Suspensión, Reactivación y Cancelación las crea la base sola al cambiar
  el estado de la asignación; no las inserte a mano.
- La asignación del beneficio se crea sola al aprobar la postulación; no la inserte a mano.
