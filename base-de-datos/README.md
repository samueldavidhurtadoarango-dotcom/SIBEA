# Base de datos de BenefitRack

| Archivo | Qué es |
|---|---|
| `benefitrack_v2.sql` | Script que crea la base `benefitrack_v2` completa: tablas, reglas, triggers y datos iniciales. |
| `pruebas/pruebas_reglas.sql` | Pruebas de las reglas de negocio. Imprime OK o FALLO por cada caso. |
| `pruebas/pruebas_usuario_aplicacion.sql` | Pruebas que se ejecutan conectado como el usuario limitado `benefitrack_app`. |
| `pruebas/otro_usuario/` | Prueba de que un usuario distinto de `root` no puede gestionar sin `@app_id_usuario`. |
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

La instalación, el mantenimiento y las pruebas se hacen con un usuario de MariaDB llamado
**`root`**: es el único que la base deja trabajar sin `@app_id_usuario` (ver la sección 3).

### Crear el primer administrador

La semilla **no trae ningún usuario**, tampoco administradores. Como crear un Administrador exige
un administrador activo, el primero se crea a mano **conectado como `root`** y con
`@app_id_usuario` en `NULL` (en phpMyAdmin, pestaña **SQL** con la base `benefitrack_v2`
seleccionada; o en la consola `mysql.exe -u root`):

```sql
USE benefitrack_v2;
SET @app_id_usuario = NULL;
INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol)
VALUES ('1000000000', 'Administrador', 'Inicial', 'admin@benefitrack.local',
        '$2y$10$EJEMPLOEJEMPLOEJEMPLOuNoUseEsteHashEnProduccion000000',
        (SELECT id_rol FROM roles WHERE nombre = 'Administrador'));
```

`password_hash` **no es la contraseña**: es el resultado de cifrarla con la aplicación (por
ejemplo, `password_hash('su clave', PASSWORD_BCRYPT)` en PHP). El valor de arriba es solo de
ejemplo y no sirve para entrar: cámbielo por uno generado por la aplicación. Después, ese
administrador crea los demás desde la aplicación.

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
| TOTAL | OK | 257 casos: 257 OK, 0 FALLO | TODAS LAS PRUEBAS PASAN |
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

Y, solo antes de cambiar el estado de un usuario, `SET @app_justificacion = '<motivo>';` (ver
«Justificación de los cambios de estado» más abajo).

Si no se define, la base guarda `NULL` en lugar del usuario, **pero** las acciones de gestión con
`@app_id_usuario` en `NULL` se **rechazan** salvo cuando la conexión es el usuario `root` (ver
«Si la variable es `NULL`» más abajo). Si la aplicación reutiliza
conexiones (pool), debe volver a fijar la variable cada vez que toma una conexión para otro
usuario.

**Control de rol.** Las acciones de gestión (validar, aprobar o rechazar postulaciones y
documentos, pedir documentación, gestionar asignaciones, registrar o anular pagos, registrar
novedades y cambiar cupos o estado de una convocatoria) solo se aceptan si `@app_id_usuario` es un
usuario con rol Administrador y estado Activo. Si no, la base responde, por ejemplo,
`Solo un administrador activo puede aprobar una postulación`. Las rutinas que hacen esta revisión
son `fn_es_administrador` y `pr_exigir_administrador`.

**Si la variable es `NULL`.** Solo el usuario de mantenimiento `root` puede hacer acciones de
gestión con la variable en `NULL` (se guarda `NULL`, decisión D9). Con **cualquier otro usuario de
conexión** (`benefitrack_app` o cualquiera que usted cree), las acciones de gestión **se
rechazan**: la aplicación debe mandar **siempre** `@app_id_usuario` en cada acción de gestión.

**Usuarios y roles.** Cambiar el rol o el estado de un usuario, crear un usuario Administrador o
crear un usuario que no esté Activo exige un administrador activo. El registro de aprendices
nuevos (que nacen Activos) y la actualización de datos de contacto siguen libres.

**Justificación de los cambios de estado: `@app_justificacion`.** Igual que `@app_id_usuario`,
es una variable de sesión que fija la aplicación. Antes de cambiar el estado de un usuario
(Activo, Inactivo o Bloqueado), la aplicación debe ejecutar:

```sql
SET @app_justificacion = 'Retiro voluntario del programa';   -- el motivo que escribió el administrador
UPDATE usuarios SET estado = 'Inactivo' WHERE id_usuario = ...;
SET @app_justificacion = NULL;   -- para no reutilizar un motivo viejo
```

Si la variable está en `NULL` o vacía, la base responde
`El cambio de estado de un usuario exige una justificación (@app_justificacion)`. No hace falta en
el bloqueo y desbloqueo **automáticos** del inicio de sesión.

**Auditoría de cuentas.** Cada cambio de rol o de estado de un usuario queda en `auditoria`
(`entidad = 'usuarios'`, acción `CAMBIO_ESTADO`, `CAMBIO_ROL` o `CAMBIO_ROL_ESTADO`) con quién lo
hizo, el rol y el estado anterior y nuevo, la justificación y el origen:

- `Usuario`: lo hizo una persona (normalmente un administrador).
- `Sistema`: bloqueo automático por intentos fallidos o desbloqueo automático a los 15 minutos. En
  ese caso `id_usuario` queda en `NULL`, que significa «el sistema», y la justificación la escribe
  la base.

Sumar `intentos_fallidos` sin cambiar el estado **no** se audita, para no llenar la tabla en cada
intento. La aplicación no puede escribir en `auditoria`: solo lo hacen los triggers.

**Migraciones (RI-4.3).** Registrar o iniciar una carga en `migraciones` y cada cambio de su estado
(por ejemplo, al terminar) quedan en `auditoria` (`INICIAR_MIGRACION`, `REGISTRAR_MIGRACION`,
`FINALIZAR_MIGRACION`) con los totales. Actualizar solo los contadores mientras sigue «En proceso»
no se audita.

**Bloqueo por intentos fallidos.** Tras 3 intentos fallidos, la aplicación pasa la cuenta de
`Activo` a `Bloqueado` (no necesita administrador). La base guarda siempre `fecha_bloqueo` con la
hora actual e **ignora** la fecha que mande la aplicación. Pasar a `Bloqueado` desde `Inactivo`
exige administrador. La cuenta se **desbloquea sola a los 15 minutos**: la aplicación puede hacer
`UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = ...` sin administrador solo si
`fecha_bloqueo` + 15 minutos ya llegó; antes, la base lo rechaza (y si `fecha_bloqueo` está vacía,
también). Un administrador activo puede desbloquear en cualquier momento. Al desbloquear, la base
deja `intentos_fallidos = 0` y `fecha_bloqueo = NULL`.

Mientras la cuenta está Bloqueada, **la aplicación no debe tocar `fecha_bloqueo`**: solo puede
sumar `intentos_fallidos`. Cambiar `fecha_bloqueo` en ese estado exige administrador.

Para impedir el acceso de forma indefinida use el estado `Inactivo`, no `Bloqueado`: un usuario
Inactivo (también un administrador inactivo) no puede reactivarse solo, ni directamente ni pasando
por Bloqueado.

**Fecha de inicio de la asignación.** Debe estar entre el inicio de la convocatoria y un año
después de hoy; si no, la base responde
`La fecha de inicio de la asignación debe estar entre el inicio de la convocatoria (...) y un año después de hoy`.
La asignación que se crea sola al aprobar empieza hoy o, si la convocatoria todavía no ha
empezado, el día de inicio de la convocatoria.

**Tipos de documento.** La semilla trae 4: 1 Documento de identidad, 2 Certificado bancario,
3 Documento adicional y 4 Certificado de EPS.

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

Para comprobar los controles con ese usuario (después de `benefitrack_v2.sql`,
`usuario_aplicacion.sql` y `pruebas\concurrencia\preparar.sql`, como `root`):

```bat
C:\xampp\mysql\bin\mysql.exe -u benefitrack_app -p --default-character-set=utf8mb4 --force --table < base-de-datos\pruebas\pruebas_usuario_aplicacion.sql
```

Los `ERROR 1644` que aparecen son los rechazos esperados; las filas U1 a U9 deben decir `OK` (también aparece un `ERROR 1142`, el rechazo esperado de U9).

---

## 5. Pruebas de concurrencia

Dos reglas (cupos de la convocatoria y cierre de la asignación con el último pago) se prueban con
dos conexiones al mismo tiempo. El procedimiento y los resultados reales están en
`pruebas/concurrencia.md`.

> **Atención:** `pruebas/concurrencia/ejecutar.sh` **borra y vuelve a crear** la base
> `benefitrack_v2`. La salida de la segunda sesión va a un archivo temporal del sistema (fuera del
> proyecto) que se borra al terminar, así que el guion no deja archivos en el repositorio.

---

## 6. Prueba con un usuario que no es `root` ni `benefitrack_app`

Comprueba que, con `@app_id_usuario` en `NULL`, **cualquier** usuario distinto de `root` queda
rechazado en las acciones de gestión (no solo `benefitrack_app`). Necesita la base ya creada; no la
borra. Desde la carpeta `base-de-datos` (con Git Bash o cualquier `sh`):

```sh
sh pruebas/otro_usuario/ejecutar.sh "C:/xampp/mysql/bin/mysql.exe -u root -P 3306 -h 127.0.0.1"
```

El guion crea el usuario temporal `benefitrack_otro`@`localhost` y un aprendiz de prueba
(`PRUEBA-OTRO`), intenta darle el rol de Administrador sin `@app_id_usuario` y, al terminar
(aunque falle o se interrumpa), borra los dos. El `ERROR 1644` que aparece es el rechazo esperado;
el veredicto debe decir `CORRECTO: con NULL y un usuario distinto de root se rechaza`.

---

## 7. Límites conocidos

- **Sin usuarios anónimos en el servidor.** La base reconoce a `root` por el nombre que el
  cliente escribió al conectarse (`USER()`), no por la cuenta con la que MariaDB lo aceptó. Si el
  servidor tiene una cuenta anónima (usuario vacío, `''@'localhost'`), alguien podría conectarse
  escribiendo `-u root` sin ser root y la base lo trataría como root. Revise que esta consulta no
  devuelva filas y, si devuelve, borre esas cuentas:

  ```sql
  SELECT user, host FROM mysql.user WHERE user = '';
  -- DROP USER ''@'localhost';   (una por cada fila que aparezca)
  ```
- **`SET timestamp` cambia la hora de la sesión.** Cualquier conexión puede ejecutar
  `SET timestamp = ...` y con eso cambia lo que devuelven `NOW()` y `CURDATE()` en esa sesión
  (por ejemplo, la fecha de validación o la fecha "de hoy" que se usa para revisar pagos). El
  bloqueo y el desbloqueo por intentos fallidos usan la hora real del servidor (`SYSDATE()`), así
  que ahí el truco no sirve. En lo demás, el riesgo es bajo y del mismo nivel que poder fijar
  `@app_id_usuario`: solo lo tiene quien ya controla la conexión de la aplicación. No arranque el
  servidor con `--sysdate-is-now`, porque haría que `SYSDATE()` se comporte como `NOW()`.
- **Convocatorias movidas a más de un año en el futuro.** La asignación empieza el día de inicio
  de la convocatoria cuando esa fecha es posterior a hoy, y la fecha de inicio de una asignación no
  puede pasar de un año después de hoy. Por eso, si se mueve el inicio de una convocatoria a más de
  un año en el futuro, sus postulaciones Validadas **no se pueden aprobar** (la base responde
  `La fecha de inicio de la asignación debe estar entre ...`) hasta que falte menos de un año.
