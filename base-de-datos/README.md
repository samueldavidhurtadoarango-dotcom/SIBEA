# Base de datos de BenefitRack

| Archivo | Qué es |
|---|---|
| `benefitrack_v2.sql` | Script que crea la base `benefitrack_v2` completa: tablas, reglas, triggers y datos iniciales. |
| `pruebas/pruebas_reglas.sql` | Pruebas de las reglas de negocio. Imprime OK o FALLO por cada caso. |
| `pruebas/concurrencia.md` y `pruebas/concurrencia/` | Pruebas con dos conexiones al mismo tiempo (cupos y último pago). |
| `usuario_aplicacion.sql` | Ejemplo de usuario de MariaDB con permisos limitados para la aplicación. |
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
| TOTAL | OK | 146 casos: 146 OK, 0 FALLO | TODAS LAS PRUEBAS PASAN |
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

Si `@app_id_usuario` apunta a un id que **no existe** en `usuarios`, el cambio falla con el
error **1452** de llave foránea (por ejemplo,
`Cannot add or update a child row: a foreign key constraint fails (... fk_historial_usuario ...)`),
porque el historial y la auditoría solo aceptan usuarios reales. Revise que la sesión de la
aplicación guarde el id correcto.

La variable `@_br_reapertura` es de uso **interno** de los triggers: la aplicación no debe
usarla.

Otros puntos a tener en cuenta al programar:

- Las reglas fallan con `SQLSTATE 45000` y un mensaje en español (por ejemplo,
  `No hay cupos disponibles en la convocatoria: 2 aprobadas de 2 cupos`). La aplicación
  puede mostrar ese mensaje al usuario.
- **No lea, en la misma sentencia, las tablas que modifican los triggers**
  (`asignaciones_beneficio`, `historial_estados`, `auditoria`, `notificaciones`, `novedades`).
  MariaDB lo rechaza con el error 1442 (`Can't update table ... because it is already used by
  statement which invoked this stored function/trigger`). Ejemplo: no use
  `INSERT INTO pagos ... SELECT ... FROM asignaciones_beneficio`; consulte antes los datos y
  registre el pago con `INSERT INTO pagos (...) VALUES (...)`.
- Las novedades de Suspensión, Reactivación y Cancelación las crea la base sola al cambiar
  el estado de la asignación; no las inserte a mano.
- La asignación del beneficio se crea sola al aprobar la postulación; no la inserte a mano.

---

## 4. Usuario de MariaDB para la aplicación (recomendado)

Los triggers impiden modificar o borrar el historial y la auditoría, y borrar postulaciones,
asignaciones y pagos. Pero **un trigger no puede impedir un `INSERT` directo** en
`historial_estados`, `auditoria` o `asignaciones_beneficio` (alguien podría escribir una huella
falsa), y un usuario con todos los permisos, como `root`, puede incluso borrar los triggers.

Por eso la aplicación **no debe conectarse como `root`**, sino con un usuario que:

- puede leer todas las tablas;
- **no** puede insertar, modificar ni borrar en `historial_estados` ni `auditoria`;
- **no** puede insertar ni borrar en `asignaciones_beneficio` (solo cambiar su estado);
- **no** puede borrar postulaciones, pagos, novedades ni notificaciones.

El ejemplo está en `usuario_aplicacion.sql`. Ejecútelo como `root` después del script principal
y **cambie la contraseña** (`CAMBIE_ESTA_CLAVE`) antes de usarlo; guárdela en el `.env` de la
aplicación, nunca en el repositorio:

```bat
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < base-de-datos\usuario_aplicacion.sql
```

Los triggers siguen funcionando con ese usuario, porque se ejecutan con los permisos de quien los
creó (`root@localhost`). Si el usuario intenta escribir directamente en una tabla protegida, MariaDB
responde con el error 1142, por ejemplo:
`INSERT command denied to user 'benefitrack_app'@'localhost' for table 'auditoria'`.

---

## 5. Pruebas de concurrencia

Dos reglas (cupos de la convocatoria y cierre de la asignación con el último pago) se prueban con
dos conexiones al mismo tiempo. El procedimiento y los resultados reales están en
`pruebas/concurrencia.md`.
