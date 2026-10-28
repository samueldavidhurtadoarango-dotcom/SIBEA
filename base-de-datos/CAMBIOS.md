# Cambios en la base de datos de BenefitRack

Este documento explica, en lenguaje sencillo, qué cambió entre el script original
(`originales/BENEFITRACK_CORREGIDA.sql`) y el script corregido
(`base-de-datos/benefitrack_v2.sql`), y por qué.

Cada cambio cita la decisión de la especificación `specs/001-correccion-entregables.md`
que lo justifica (D1 a D12). Cuando un cambio corrige un error del original que la
especificación no menciona, se indica como **Error del original**.

---

## 1. Cambios generales

| Antes | Ahora | Por qué |
|---|---|---|
| Era un volcado automático de `mysqldump` (con `LOCK TABLES`, comentarios `/*!40101 ... */` y `DEFINER=root@localhost`). | Es un script escrito a mano, ordenado y comentado en español. | Se lee y se explica mejor. Sin `DEFINER` el script se puede instalar en cualquier XAMPP. Pero desde la quinta ronda la instalación, el mantenimiento y las pruebas **deben hacerse con un usuario llamado `root`**: es el único que puede trabajar con `@app_id_usuario` en `NULL` (sección 9). |
| Intercalación `utf8mb4_uca1400_ai_ci`, que solo existe en MariaDB 11. | `utf8mb4_unicode_ci` en la base y en todas las tablas. | **D12**: el SENA usa XAMPP con MariaDB 10.4; con la intercalación anterior el script no corría. |
| La base se llamaba `benefitrack` y el script no la creaba. | El script empieza con `DROP DATABASE IF EXISTS benefitrack_v2` (con aviso visible de que borra la base), `CREATE DATABASE` y `USE`. | Se puede ejecutar desde cero las veces que haga falta. |
| Al final había un bloque de "AJUSTES" que modificaba tablas ya creadas (`ALTER TABLE`) y reemplazaba triggers. | Cada tabla se crea una sola vez con su forma final. | Evita que la estructura dependa del orden de los parches. |
| Tablas creadas en orden alfabético con las llaves foráneas desactivadas. | Tablas creadas en orden de dependencias (primero catálogos, luego usuarios, convocatorias, postulaciones, pagos...). | El script no necesita desactivar las llaves foráneas para funcionar. |
| Nombres de índices y restricciones mezclados (`fk_...` usado también para índices simples). | Prefijos claros: `uk_` (único), `idx_` (índice), `fk_` (llave foránea), `chk_` (regla CHECK). | Se entiende de un vistazo qué es cada cosa. |
| Las reglas de negocio vivían solo en la aplicación (o no existían). | Las reglas clave se fuerzan en la base con triggers que lanzan errores en español (`SQLSTATE '45000'`). | La aplicación no puede saltarse las reglas por error. **Ojo:** los triggers no protegen contra quien tiene permisos totales (por ejemplo `root`), que puede borrar triggers o escribir directamente en las tablas. Por eso la aplicación debe conectarse con un usuario de permisos limitados (`usuario_aplicacion.sql`, ver la sección 11). |
| Los triggers guardaban un usuario fijo o el aprendiz dueño. | Todos usan la variable de sesión `@app_id_usuario`. | **D9** (ver la sección 4). |

---

## 2. Tabla por tabla

### Tablas que se conservan con cambios

**`roles`**
- El rol "Estudiante" se renombra a **"Aprendiz"** y se actualizan las descripciones. (**D7**)

**`tipos_documento`**
- Las descripciones dicen "aprendiz" en lugar de "estudiante". (**D7**)
- Cuarta ronda: nuevo tipo **4 «Certificado de EPS»** (certificado de afiliación a la EPS del
  aprendiz). Los tipos 1 a 3 no cambian (ver la sección 8).

**`tipos_cuenta`, `entidades_bancarias`, `sesiones`, `informacion_bancaria`**
- Sin cambios de fondo: solo la intercalación (**D12**) y nombres de índices.
- `entidades_bancarias` conserva su llave `id_banco` (nombre original) para no romper referencias.

**`eps`**
- Ya no se agrega al final con un parche: se crea junto a los demás catálogos.
- Se amplía la lista inicial (Coosalud, Famisanar, Nueva EPS, Salud Total, Sanitas, Savia Salud, SURA).

**`programas`**
- Nueva columna `id_institucion` (obligatoria): cada programa pertenece a una institución. (**D10**)
- El nombre del programa es único dentro de cada institución.

**`usuarios`**
- `edad` se reemplaza por **`fecha_nacimiento`** (la edad se calcula, no se guarda, porque cambia cada año). (**D11**)
- `id_eps` ya viene en la tabla (antes se añadía con un parche).
- Se quita el índice `idx_usuario_documento`, que repetía el índice único del documento.
- Regla: `intentos_fallidos` no puede ser negativo.
- Cuarta ronda: cambiar el rol o el estado exige un administrador activo, crear un Administrador
  también, y una cuenta bloqueada por intentos fallidos se desbloquea sola a los 15 minutos (ver la
  sección 8).

**`beneficios`**
- Nueva columna `id_institucion` (obligatoria). (**D10**)
- El nombre es único dentro de cada institución (antes era único en todo el sistema).
- `fecha_actualizacion` se llena sola con `ON UPDATE CURRENT_TIMESTAMP`; por eso sobra el trigger `tr_beneficio_actualizado`.

**`convocatorias`**
- Nuevas columnas con los **valores por defecto** que hereda cada beneficiario: `valor_pago`, `numero_pagos` y `duracion_meses`. (**D3**)
- `numero_pagos_esperados` (del parche) pasa a llamarse `numero_pagos`, igual que en la asignación.
- Reglas nuevas: valor, número de pagos y duración mayores que cero; si el estado es "Cierre anticipado" el motivo es obligatorio.

**`postulaciones`**
- Estados: solo **Pendiente, Validada, Aprobada, Rechazada**; el estado inicial es Pendiente. Se eliminan Borrador, Enviada, En revisión, Pendiente de documentación y Completado. (**D1**)
- Nueva columna `justificacion_rechazo`, obligatoria al rechazar. (**D1**)
- Nuevas columnas para saber quién validó y quién aprobó, y cuándo: `id_usuario_validacion`, `fecha_validacion`, `id_usuario_aprobacion`, `fecha_aprobacion`. (**D2**, **D9**)
- Se quita `fecha_envio`: como ya no hay borradores, la postulación se envía al crearla (`fecha_creacion`).
- "Completado" deja de ser un estado de la postulación: ahora es "Finalizado" en la asignación. (**D3**)

**`documentos_postulacion`**
- Se eliminan `resultado_ocr` y `datos_extraidos`: la validación es manual, sin OCR. (**D6**)
- Nueva columna `id_usuario_validacion` (quién validó el documento). (**D9**)
- Regla de tamaño: mayor que 0 y hasta 5 MB (antes solo se controlaba el máximo).

**`solicitudes_documentacion`**
- Misma estructura. Ahora la base solo permite pedir documentos a postulaciones Pendiente, y la postulación sigue en Pendiente mientras tanto. (**D1**)

**`historial_estados`**
- `estado_anterior` y `estado_nuevo` usan la misma lista de estados de la postulación (antes era texto libre).
- `id_usuario` ahora admite `NULL` y guarda a **quien hizo el cambio**. (**D9**)

**`notificaciones`**
- Sirve para el aviso dentro del sistema y para el correo (**D8**):
  - nuevas columnas `leida` y `fecha_lectura` (aviso en el portal);
  - `estado` pasa a llamarse `estado_correo` (Pendiente / Enviada / Error), para que se entienda que es el estado del envío;
  - `tipo` (siempre decía "Correo") se reemplaza por `evento`: Validada, Aprobada, Rechazada, Pago o Novedad;
  - nueva columna `id_postulacion` para enlazar la notificación con su postulación.

**`auditoria`**
- Misma estructura. Ahora `id_usuario` es quien hizo la acción (**D9**) y `ip` se llena con la variable opcional `@app_ip`.

**`pagos`** (rehecha)
- Ahora pertenece a una **asignación** (`id_asignacion`) y no a la postulación. (**D4**)
- Se elimina `id_beneficio`: era redundante (el beneficio se conoce por la asignación → postulación → convocatoria). **Error del original.**
- Columnas nuevas (**D4**): `fecha_programada`, `hubo_retraso`, `motivo_retraso`, `id_usuario_registro`, `motivo_anulacion`, `fecha_anulacion`, `id_usuario_anulacion`, `fecha_registro`.
- `fecha_pago` pasa de fecha y hora a solo fecha.
- **Error del original:** `UNIQUE (id_postulacion, numero_pago)` impedía corregir un pago (anularlo y registrar otro con el mismo número). Se cambia por una columna calculada `vigente` (vale 1 si el pago está Registrado y `NULL` si está Anulado) y el índice único `uk_pago_vigente (id_asignacion, numero_pago, vigente)`. Como los `NULL` no chocan en un índice único, puede haber muchos pagos anulados con el mismo número, pero **solo uno Registrado**.

### Tablas nuevas

| Tabla | Para qué | Decisión |
|---|---|---|
| `instituciones` | Institución que ofrece los beneficios. Registro inicial: SENA - Centro Textil y de Gestión Industrial. La referencian `beneficios` y `programas`. | **D10** |
| `asignaciones_beneficio` | El "beneficiario": se crea al aprobar una postulación, con valor por pago, número de pagos, duración, fecha de inicio y su propio estado (En curso, Finalizado, Suspendido, Cancelado). | **D3** |
| `novedades` | Novedades de cada asignación que el aprendiz ve en su portal: Retraso de pago, Suspensión, Reactivación, Cancelación, Otra. | **D5** |
| `migraciones` | Registro de cada carga de datos desde Excel: archivo, tabla destino, totales de filas exitosas y fallidas, estado y detalle de errores. | **D11** (RF-4.3) |
| `recuperacion_contrasena` | Solicitudes de recuperación de contraseña: guarda solo el **hash** del token, la fecha de expiración y si ya se usó. | **D11** (RF-2.2) |

---

## 3. Trigger por trigger

### Triggers del original

| Trigger original | Qué pasa ahora | Por qué |
|---|---|---|
| `tr_beneficio_actualizado` | **Eliminado.** Lo reemplaza `ON UPDATE CURRENT_TIMESTAMP` en la columna. | Hace lo mismo sin código. |
| `tr_validacion_documento` | **Se conserva** y además guarda quién validó (`id_usuario_validacion`). | **D9** |
| `tr_postulacion_estado` | **Corregido.** Guardaba siempre `id_usuario = 1` y el texto fijo "Cambio automático de estado". Ahora guarda `@app_id_usuario` y, si es un rechazo, la justificación. | **Error del original**, **D9** |
| `tr_auditoria_postulacion` | **Corregido.** Guardaba `NEW.id_usuario` (el aprendiz dueño de la postulación) como si él hubiera hecho el cambio. Ahora guarda `@app_id_usuario` y distingue `CAMBIO_ESTADO` de `ACTUALIZAR`. | **Error del original**, **D9** |
| `tr_postulacion_aprobada` | **Reemplazado** por `tr_postulacion_notificar`. En el parche final este trigger se había cambiado para avisar "Validada", y con eso se perdía el aviso de aprobación. | **Error del original**, **D8** |
| `tr_postulacion_rechazada` | **Reemplazado** por `tr_postulacion_notificar`; el aviso incluye la justificación obligatoria. | **D1**, **D8** |
| `tr_pago_completar_postulacion` | **Reemplazado** por `tr_pago_registrado`: ya no pasa la postulación a "Completado"; pasa la **asignación** a "Finalizado". | **D3** |

### Triggers del script corregido (28)

**Postulaciones y convocatorias**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_postulacion_inicial` | antes de insertar | Exige que toda postulación nueva empiece en Pendiente, la cree un usuario con rol Aprendiz, en una convocatoria Activa y dentro de sus fechas, y sin datos de validación, aprobación o rechazo ya llenos. | D1, D7 |
| `tr_postulacion_creada` | después de insertar | Anota la creación en el historial (sin estado → Pendiente) y en la auditoría. | D9 |
| `tr_postulacion_validar` | antes de actualizar | Solo deja pasar las transiciones Pendiente → Validada/Rechazada y Validada → Aprobada/Rechazada; exige justificación al rechazar; al aprobar revisa que queden **cupos** en la convocatoria: bloquea la convocatoria con `FOR UPDATE` y cuenta las aprobadas con `LOCK IN SHARE MODE`, para que dos aprobaciones al mismo tiempo no superen los cupos (en la primera versión faltaba este segundo candado y sí se superaban: ver `pruebas/concurrencia.md`); guarda quién validó o aprobó y cuándo, y no deja reescribir esos datos, ni la justificación del rechazo, ni el formulario si ya no está Pendiente; impide cambiar el aprendiz o la convocatoria. | D1, D2, D9 |
| `tr_postulacion_estado` | después de actualizar | Guarda cada cambio en `historial_estados` con `@app_id_usuario`. | D9 |
| `tr_auditoria_postulacion` | después de actualizar | Guarda cada modificación en `auditoria` con `@app_id_usuario`. | D9 |
| `tr_postulacion_notificar` | después de actualizar | Crea la notificación al pasar a Validada, Aprobada o Rechazada (esta última con la justificación). | D8 |
| `tr_postulacion_crear_asignacion` | después de actualizar | Al aprobar, crea la asignación copiando valor por pago, número de pagos y duración de la convocatoria; empieza hoy o, si la convocatoria aún no empezó, el día de inicio de la convocatoria. | D3 |
| `tr_solicitud_documentacion_validar` | antes de insertar en `solicitudes_documentacion` | Solo permite pedir documentos a postulaciones Pendiente. | D1 |
| `tr_postulacion_no_eliminar` | antes de borrar | Impide borrar postulaciones (se rechazan con justificación). | D9 |
| `tr_convocatoria_validar` | antes de actualizar una convocatoria | Los cupos no pueden quedar por debajo de las postulaciones ya aprobadas. | D2 |

**Asignaciones**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_asignacion_validar_insercion` | antes de insertar | Solo si la postulación está Aprobada; solo una asignación por postulación (se revisa antes que la fecha); la fecha de inicio debe estar entre el inicio de la convocatoria y un año después de hoy; debe empezar En curso. | D3 |
| `tr_asignacion_validar_estado` | antes de actualizar | Transiciones válidas (En curso → Finalizado/Suspendido/Cancelado, Suspendido → En curso/Cancelado, Finalizado → En curso **solo** cuando lo pide `tr_pago_anulado`); Finalizado solo con todos los pagos vigentes (contados con `LOCK IN SHARE MODE`); motivo nuevo obligatorio al suspender, reactivar o cancelar; valor, número de pagos, duración y fecha de inicio solo se cambian si está En curso y aún no tiene pagos; la fecha de inicio nueva debe estar entre el inicio de la convocatoria y un año después de hoy. | D3, D4 |
| `tr_asignacion_cambio_estado` | después de actualizar | Audita el cambio y crea automáticamente la novedad de Suspensión, Reactivación o Cancelación. | D5, D9 |
| `tr_asignacion_no_eliminar` | antes de borrar | Impide borrar asignaciones (se cancelan con motivo). | D3 |

**Pagos**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_pago_validar` | antes de insertar | Solo en asignaciones En curso; número entre 1 y el total de pagos; un solo pago Registrado por número; motivo obligatorio si hubo retraso; fecha de pago entre el inicio de la asignación y hoy; fecha programada no anterior al inicio; observación obligatoria si el monto es distinto del valor del pago; guarda quién lo registró. | D4 |
| `tr_pago_registrado` | después de insertar | Notifica al aprendiz, audita y, si ya están todos los pagos vigentes (contados con `LOCK IN SHARE MODE`), pasa la asignación a **Finalizado**. | D3, D8, D9 |
| `tr_pago_inmutable` | antes de actualizar | Los pagos no se editan (ni siquiera su `id_pago`): lo único permitido es pasar de Registrado a Anulado con motivo; guarda quién anuló y cuándo. | D4 |
| `tr_pago_anulado` | después de actualizar | Audita la anulación y, si la asignación estaba Finalizado, la devuelve a En curso para registrar el pago corregido. Es el único que puede hacerlo, con la variable interna `@_br_reapertura`. | D3, D4 |
| `tr_pago_no_eliminar` | antes de borrar | Impide borrar pagos. | D4 |

**Usuarios**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_usuario_validar_insercion` | antes de insertar | Crear un usuario con rol Administrador, o con un estado distinto de Activo, exige un administrador activo. La base pone `fecha_bloqueo` (hora actual si nace Bloqueado, `NULL` si no). El registro de aprendices sigue libre. | D7, D9 |
| `tr_usuario_validar` | antes de actualizar | Cambiar el rol (`id_rol`) o el estado exige un administrador activo. Sin administrador solo se permite Activo → Bloqueado y el desbloqueo automático Bloqueado → Activo cuando ya pasaron 15 minutos desde `fecha_bloqueo` (nunca si `fecha_bloqueo` es `NULL`). Al pasar a Bloqueado, la base guarda `fecha_bloqueo` = hora actual e ignora la fecha recibida. Todo desbloqueo deja `intentos_fallidos = 0` y `fecha_bloqueo = NULL`. Mientras sigue Bloqueado, cambiar `fecha_bloqueo` exige administrador. | D7, D9 |

**Otros**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_validacion_documento` | antes de actualizar un documento | Guarda fecha y usuario de la validación, y no deja escribirlos a mano. | D6, D9 |
| `tr_novedad_preparar` | antes de insertar una novedad | Exige descripción y guarda quién la registró. | D5, D9 |
| `tr_novedad_registrada` | después de insertar una novedad | Notifica al aprendiz y audita. | D8, D9 |
| `tr_historial_no_modificar` / `tr_historial_no_eliminar` | antes de actualizar / borrar en `historial_estados` | El historial solo admite filas nuevas. | D9 |
| `tr_auditoria_no_modificar` / `tr_auditoria_no_eliminar` | antes de actualizar / borrar en `auditoria` | La auditoría solo admite filas nuevas. | D9 |

---

## 4. La variable `@app_id_usuario` (D9)

La base de datos no sabe qué persona está usando la aplicación: la aplicación se conecta
siempre con el mismo usuario de MariaDB. Por eso, **cada vez que la aplicación abre una
conexión**, debe decirle a la base quién inició sesión:

```sql
SET @app_id_usuario = 5;   -- id del usuario que inició sesión
SET @app_ip = '10.0.0.7';  -- opcional: IP del cliente, para la auditoría
```

Los triggers leen esa variable y la guardan en el historial, la auditoría, los pagos y las
novedades. Si la aplicación no la define, se guarda `NULL` (nunca un id inventado).

Desde la tercera ronda (sección 7), la variable también sirve para **comprobar el rol**: las
acciones de gestión solo las puede hacer un Administrador con estado Activo.

¿Y si la variable es `NULL`? Desde la quinta ronda (sección 9) depende de **con qué usuario de
MariaDB** se conectó:

- Con `root` (mantenimiento, scripts y pruebas), la acción se permite y se guarda `NULL` (D9).
- Con **cualquier otro usuario** (`benefitrack_app` o el que se cree), la acción de gestión **se
  rechaza** con `Solo un administrador activo puede <acción>`. Así, si la aplicación olvida fijar
  la variable, no se salta el control de rol.

Igual, **la aplicación SIEMPRE debe fijar `@app_id_usuario`** en cada conexión.

---

## 5. Decisiones que tomé y que la especificación no cubría

1. **Novedades automáticas.** Al suspender, reactivar o cancelar una asignación, la base crea sola la novedad correspondiente con el motivo. Así el aprendiz siempre la ve en su portal. La aplicación no debe registrar esas tres novedades a mano (se duplicarían); sí registra a mano "Retraso de pago" y "Otra".
2. **Corrección del último pago.** Si se anula un pago de una asignación ya Finalizado, la asignación vuelve a En curso para poder registrar el pago corregido; al registrarlo vuelve a Finalizado.
3. **Pagos solo en asignaciones En curso.** No se registran pagos en asignaciones Suspendido, Cancelado o Finalizado.
4. **Motivo nuevo en cada cambio manual.** Al suspender, reactivar o cancelar se exige un motivo distinto del anterior, para que cada novedad explique su propio cambio.
5. **La postulación no cambia de aprendiz ni de convocatoria** después de creada (si se pudiera mover de convocatoria, se saltaría el control de cupos).
6. **Sin "Borrador".** Como la postulación nace en Pendiente (D1), el borrador del formulario, si se quiere, lo guarda la aplicación y no la base.
7. **Notificación única para sistema y correo.** Una fila de `notificaciones` sirve para las dos cosas (`leida` para el portal, `estado_correo` para el envío).
8. **Limitación técnica de MariaDB (error 1442).** Una sentencia no puede leer una tabla que sus propios triggers modifican. Por ejemplo, `INSERT INTO pagos ... SELECT ... FROM asignaciones_beneficio` falla, porque el trigger del pago actualiza `asignaciones_beneficio`. Regla general: **no lea, en la misma sentencia, las tablas que modifican los triggers** (`asignaciones_beneficio`, `historial_estados`, `auditoria`, `notificaciones`, `novedades`). Consulte primero los datos y luego use `INSERT ... VALUES (...)`.
9. **Postulación nueva limpia.** Además de lo que pidió la auditoría, una postulación nueva no puede traer ya llenos los datos de validación, aprobación o rechazo; si no, se podrían inventar al crearla.
10. **La fecha de inicio de la asignación** también queda congelada cuando ya hay pagos, porque las fechas de los pagos dependen de ella.

---

## 6. Correcciones de la auditoría (segunda ronda), explicadas fácil

Un revisor intentó "hacer trampa" contra la base de datos y encontró huecos. Esto es lo que se
corrigió, explicado sin tecnicismos. **Ninguna tabla ni columna cambió de nombre ni se borró**;
solo se agregaron triggers.

**B1. Dos aprobaciones al mismo tiempo superaban los cupos.**
Imagine una convocatoria con un solo cupo y dos administradores que pulsan "Aprobar" en el mismo
segundo. La base ponía a uno a esperar al otro (bien), pero cuando el segundo continuaba, miraba
una *foto vieja* de la base en la que el primero todavía no había aprobado, y también aprobaba.
Resultado: 2 aprobados con 1 cupo. Ahora el conteo se hace "con candado" (`LOCK IN SHARE MODE`),
que siempre mira lo último que se guardó. Se probó de verdad con dos conexiones a la vez
(`pruebas/concurrencia.md`).

**B2. Se podía cambiar el número de pagos después de empezar a pagar.**
Con 3 pagos y solo el pago 3 hecho, se podía bajar el total a 2; al registrar el pago 1, la
asignación quedaba "Finalizado" con los pagos 1 y 3 (el pago 2 nunca existió). Ahora, en cuanto hay
algún pago (aunque esté anulado) o la asignación no está En curso, ya no se pueden cambiar el valor,
el número de pagos, la duración ni la fecha de inicio. Antes del primer pago sí se puede.

**I1. Los dos últimos pagos registrados a la vez no cerraban la asignación.**
Es el mismo problema de la foto vieja: si se registraban a la vez el pago 1 y el pago 2 (de 2),
cada uno contaba solo el suyo y ninguno cerraba la asignación. Ahora ese conteo también es "con
candado" y la asignación queda Finalizado.

**I2. Se podía "reabrir" a mano una asignación terminada.**
Una asignación Finalizado solo debe volver a En curso cuando se anula uno de sus pagos para
corregirlo. Ahora la base solo acepta ese cambio cuando lo pide el propio trigger de anulación, con
una "contraseña interna" de un solo uso (la variable `@_br_reapertura`). Ojo: esto evita errores de
la aplicación, pero no es una barrera contra alguien que se conecte directamente a la base con
intención de hacer trampa.

**I3. Se podía falsificar quién validó o aprobó.**
Con un cambio simple se podía escribir que otra persona había aprobado, cambiar la fecha, cambiar
la justificación de un rechazo ya hecho o cambiar las respuestas del formulario después de revisado.
Ahora esos datos los escribe solo la base en el momento del cambio de estado, la justificación solo
se escribe al rechazar y el formulario solo se corrige mientras la postulación está Pendiente. Lo
mismo vale para quién validó un documento.

**I4. Se podían borrar o editar las huellas.**
El historial y la auditoría son como un libro de actas: solo se pueden agregar páginas, nunca
borrarlas ni tacharlas. Ahora la base lo impide. Tampoco se pueden borrar asignaciones ni
postulaciones. Para que nadie escriba huellas falsas directamente, la aplicación debe usar un
usuario de MariaDB sin permiso para escribir en esas tablas; el ejemplo está en
`usuario_aplicacion.sql`. Los triggers siguen funcionando porque trabajan con los permisos de quien
los creó.

**I5. Fechas y montos absurdos.**
Se podía registrar un pago del año 1900 o del 2099. Ahora la fecha del pago debe estar entre el
inicio de la asignación y hoy, y la fecha programada no puede ser anterior al inicio. Si se paga un
monto distinto del acordado, hay que escribir por qué (observación).

**Correcciones menores.**
- Al anular un pago no se puede cambiar su número interno (`id_pago`).
- No se pueden bajar los cupos de una convocatoria por debajo de los ya aprobados.
- Solo un usuario con rol Aprendiz puede postularse, y solo a una convocatoria Activa y dentro de
  sus fechas.

---

## 7. Tercera ronda: solo un administrador activo gestiona (explicado fácil)

**El problema.** La base guardaba *quién* hacía cada cambio, pero no revisaba *si tenía derecho* a
hacerlo. Si la aplicación fallaba, un aprendiz podía, por ejemplo, aprobar su propia postulación, y
la base lo aceptaba y lo dejaba anotado como si nada.

**La corrección.** Ahora la base revisa, antes de cada acción de gestión, que el usuario de la
sesión (`@app_id_usuario`) sea un **Administrador** con estado **Activo**. Si es un aprendiz, o un
administrador inactivo (por ejemplo, alguien que ya no trabaja en Bienestar), la acción se rechaza
con el mensaje `Solo un administrador activo puede <acción>`, por ejemplo
`Solo un administrador activo puede aprobar una postulación`.

Se revisa en estas acciones:

| Acción | Trigger que lo revisa |
|---|---|
| Validar, aprobar o rechazar una postulación | `tr_postulacion_validar` |
| Validar o rechazar un documento | `tr_validacion_documento` |
| Pedir documentación adicional | `tr_solicitud_documentacion_validar` |
| Crear una asignación | `tr_asignacion_validar_insercion` |
| Modificar, suspender, reactivar o cancelar una asignación | `tr_asignacion_validar_estado` |
| Registrar un pago | `tr_pago_validar` |
| Anular un pago | `tr_pago_inmutable` |
| Registrar una novedad | `tr_novedad_preparar` |
| Cambiar los cupos o el estado de una convocatoria | `tr_convocatoria_validar` |

Lo que el aprendiz sí puede seguir haciendo: crear su postulación y corregir su formulario mientras
está Pendiente.

**Cómo está hecho.** Para no repetir la misma consulta nueve veces, hay dos rutinas nuevas:

- `fn_es_administrador(id)`: función que responde 1 si ese usuario existe, tiene rol Administrador
  y está Activo, y 0 si no.
- `pr_exigir_administrador(acción)`: procedimiento que los triggers llaman. Si `@app_id_usuario`
  tiene un valor y no es un administrador activo, lanza el error.

Las rutinas se ejecutan dentro de los triggers con los permisos de quien los creó (`root@localhost`),
así que el usuario limitado `benefitrack_app` no necesita permisos extra (`EXECUTE`). Se probó: con
`@app_id_usuario` de un aprendiz el rechazo falla con
`Solo un administrador activo puede rechazar una postulación`; con el administrador 5, funciona.

**Importante.** En esta ronda, con `@app_id_usuario` en `NULL` la acción se permitía siempre (D9).
La cuarta ronda (sección 8) lo cierra para el usuario `benefitrack_app` y la quinta (sección 9), para
cualquier usuario distinto de `root`.

No se agregaron ni cambiaron tablas, columnas ni triggers; solo las dos rutinas y las llamadas
dentro de los triggers existentes.

---

## 8. Cuarta ronda: fechas, roles, bloqueos y variable vacía (explicado fácil)

**1. Fecha de inicio absurda en la asignación.**
Se podía decir que un beneficio empezó en 1900 (y luego registrar pagos de 1900) o dentro de
diez años. Ahora la fecha de inicio de la asignación debe estar **entre el día en que empezó la
convocatoria y un año después de hoy** (los dos extremos se permiten). Se revisa al crear la
asignación (`tr_asignacion_validar_insercion`) y al cambiarla (`tr_asignacion_validar_estado`).
Si no cumple, el mensaje es, por ejemplo:
`La fecha de inicio de la asignación debe estar entre el inicio de la convocatoria (30/05/2026) y un año después de hoy`.

**2. Un aprendiz podía darse a sí mismo el rol de Administrador.**
Es como si un estudiante pudiera cambiar su propio carné por uno de profesor. Ahora hay dos
triggers nuevos en `usuarios`:

- `tr_usuario_validar` (antes de actualizar): cambiar el **rol** o el **estado** de un usuario
  exige un administrador activo. Así un aprendiz no se hace administrador y un administrador
  inactivo no se reactiva solo. Sigue libre actualizar datos de contacto (teléfono, correo...).
- `tr_usuario_validar_insercion` (antes de insertar): crear un usuario **Administrador** exige un
  administrador activo. El registro de aprendices nuevos sigue libre (no hay sesión todavía).

**3. Bloqueo por intentos fallidos y desbloqueo automático a los 15 minutos.**
Funciona como el cajero automático: tras 3 intentos fallidos la cuenta queda **Bloqueada** y,
pasados **15 minutos**, se puede volver a entrar sin llamar a nadie.

- Pasar a Bloqueado no necesita administrador (lo hace el inicio de sesión). Si la aplicación no
  envía `fecha_bloqueo`, la base guarda la hora actual.
- Sin administrador, Bloqueado → Activo **solo** se acepta si `fecha_bloqueo` + 15 minutos ya llegó
  (`<= NOW()`). Antes de eso se rechaza con `Solo un administrador activo puede cambiar el rol o el
  estado de un usuario`.
- Un **administrador activo** puede desbloquear en cualquier momento.
- En todo desbloqueo la base deja `intentos_fallidos = 0` y `fecha_bloqueo = NULL`, aunque la
  aplicación no lo pida.
- Trampa cerrada: mientras la cuenta sigue Bloqueada, cambiar `fecha_bloqueo` (por ejemplo, para
  "adelantar el reloj") exige administrador.
- Ojo: si un administrador pone a alguien en Bloqueado a mano, también se desbloqueará solo a los 15
  minutos. Para impedir el acceso de forma indefinida use el estado **Inactivo**.
- **Corregido en la quinta ronda (sección 9):** en esta ronda, un Inactivo todavía podía
  reactivarse solo pasando por Bloqueado con una fecha vieja, y la fecha de bloqueo la podía mandar
  la aplicación. Desde la quinta ronda, lo de «Inactivo impide el acceso indefinidamente» sí es
  cierto.

**4. La variable vacía ya no abre la puerta con el usuario de la aplicación.**
Antes, si la aplicación olvidaba `SET @app_id_usuario`, la base dejaba pasar cualquier acción de
gestión. Ahora `pr_exigir_administrador` mira con qué usuario de MariaDB se conectó (`USER()`): si
es `benefitrack_app` y la variable está en `NULL`, rechaza la acción. Con `root` sigue permitida
(mantenimiento y pruebas, D9). El usuario se reconoce por su nombre, sin importar el host
(`localhost` o `127.0.0.1`; ver la nota en `usuario_aplicacion.sql`). **Quinta ronda:** la regla
se invirtió y ahora solo `root` puede usar `NULL` (sección 9).

**5. Nuevo tipo de documento «Certificado de EPS».**
La semilla de `tipos_documento` incluye el tipo **4 «Certificado de EPS»** (certificado de
afiliación a la EPS del aprendiz). Los tipos 1 (Documento de identidad), 2 (Certificado bancario) y
3 (Documento adicional) conservan su número.

**6. La prueba de concurrencia ya no deja archivos en el proyecto.**
`pruebas/concurrencia/ejecutar.sh` guardaba la salida de una de las sesiones en el archivo
`.a.out` dentro del repositorio. Ahora usa un archivo temporal del sistema (`mktemp`), que se
borra solo al terminar (`trap`), y `.gitignore` ignora los `*.out` de esa carpeta por si acaso.

**Pruebas nuevas.**
- `pruebas/pruebas_reglas.sql`: A13 (tipos de documento) y bloque J (J01 a J26: fechas de inicio,
  cambios de rol y estado, bloqueo y desbloqueo). Resultado: **222 casos, 222 OK**.
- `pruebas/pruebas_usuario_aplicacion.sql` (U1 a U6): se ejecuta **conectado como
  `benefitrack_app`** y comprueba que con la variable en `NULL` no se puede rechazar ni cambiar un
  rol, que un aprendiz no se da el rol de Administrador, que el administrador 5 sí gestiona y que el
  desbloqueo automático respeta los 15 minutos.

No se cambiaron tablas ni columnas: se agregaron 2 triggers (ahora son 28), un tipo de documento, y
se ajustaron 2 triggers de asignaciones y 1 procedimiento.

---

## 9. Quinta ronda: huecos encontrados en la revisión de la cuarta (explicado fácil)

Un revisor volvió a "hacer trampa" contra las reglas de la cuarta ronda y encontró estos huecos.

**B1. Un usuario Inactivo podía reactivarse solo.**
El truco tenía dos pasos: pasar de Inactivo a Bloqueado (eso no pedía administrador) mandando una
fecha de bloqueo vieja (`2000-01-01`), y luego pasar a Activo, porque «ya habían pasado 15
minutos». Servía hasta para un administrador inactivo. Ahora:

- Sin administrador, solo se puede pasar a Bloqueado **desde Activo**. Desde Inactivo se rechaza.
- Al pasar a Bloqueado la base escribe **siempre** la hora actual en `fecha_bloqueo` e ignora la
  fecha que mande la aplicación. Así nadie puede "adelantar el reloj" ni en el mismo `UPDATE`.

**I1. Una cuenta Bloqueada sin fecha se desbloqueaba sin administrador.**
Si `fecha_bloqueo` estaba vacía (`NULL`), la cuenta de «fecha + 15 minutos» daba "no sé" y la base
lo tomaba como permiso. Ahora una cuenta sin fecha de bloqueo **no** se desbloquea sola: hace falta
un administrador. Además, al **crear** un usuario:

- si no nace Activo (por ejemplo, Bloqueado o Inactivo), hace falta un administrador;
- la fecha de bloqueo la pone la base (la hora actual si nace Bloqueado; vacía si no).

**I2. Lista blanca en lugar de lista negra para la variable vacía.**
Antes solo se rechazaba `@app_id_usuario` en `NULL` si el usuario de conexión se llamaba
exactamente `benefitrack_app`; con otro nombre, la puerta seguía abierta. Ahora es al revés: **solo
`root`** (el usuario de mantenimiento) puede hacer acciones de gestión con la variable en `NULL`.
Cualquier otro usuario de conexión debe mandar siempre `@app_id_usuario`. Se probó con un usuario
temporal `otro_usuario`: con la variable en `NULL`, cambiar un rol se rechazó.

**Menores.**
- **M1.** La asignación que se crea al aprobar empezaba siempre «hoy». Si el administrador había
  movido el inicio de la convocatoria al futuro, aprobar fallaba con un mensaje confuso. Ahora
  empieza el día más tarde entre hoy y el inicio de la convocatoria (`GREATEST`).
- **M2.** El README aclara que, con la cuenta Bloqueada, la aplicación no debe tocar
  `fecha_bloqueo` (solo sumar `intentos_fallidos`).
- **M4.** `ejecutar.sh` borra su archivo temporal también si se interrumpe (Ctrl+C), y
  `.gitignore` solo ignora los `*.out` de `base-de-datos/pruebas/concurrencia/`.
- **M5.** Al crear una asignación para una postulación que ya tiene una, el mensaje es «La
  postulación ya tiene una asignación de beneficio», aunque la fecha también esté mal.

**Pruebas nuevas.**
- `pruebas/pruebas_reglas.sql`, bloque K (K01 a K13), y J06 ajustada por M5. Resultado:
  **235 casos, 235 OK**.
- `pruebas/pruebas_usuario_aplicacion.sql`, U7: conectado como `benefitrack_app` y con la variable
  en `NULL`, un Inactivo no se reactiva pasando por Bloqueado.

No se cambiaron tablas ni columnas; se ajustaron 4 triggers (`tr_usuario_validar`,
`tr_usuario_validar_insercion`, `tr_postulacion_crear_asignacion`,
`tr_asignacion_validar_insercion`) y el procedimiento `pr_exigir_administrador`. Siguen siendo 28
triggers.

---

## 10. Sexta ronda: pulidos (explicado fácil)

- **La hora del bloqueo ya no se puede falsear.** Cualquier conexión puede "mover el reloj" de su
  sesión con `SET timestamp = ...`, y eso cambia lo que devuelve `NOW()`. Con eso se podía bloquear
  con una fecha vieja y desbloquear enseguida, o desbloquear "mañana". Ahora el bloqueo y el
  desbloqueo usan `SYSDATE()`, que es la hora real del servidor y no cambia con `SET timestamp`.
  Funciona igual en MariaDB 10.4 y 11.x (salvo que el servidor se arranque con
  `--sysdate-is-now`, que no se debe usar).
- **Primer administrador.** El README explica cómo crearlo como `root`, porque la semilla no trae
  usuarios.
- **Prueba con un tercer usuario.** `pruebas/otro_usuario/ejecutar.sh` crea un usuario temporal
  que no es `root` ni `benefitrack_app`, comprueba que con `@app_id_usuario` en `NULL` no puede
  darse el rol de Administrador y lo borra al terminar.
- **Límites conocidos** en el README: no tener cuentas anónimas en el servidor, el efecto de
  `SET timestamp` en el resto de fechas y que una convocatoria movida a más de un año en el futuro
  no deja aprobar sus postulaciones.
- `ejecutar.sh` borra su temporal al terminar y, si se interrumpe, sale con el código 130.

**Pruebas nuevas.** K14 a K18 en `pruebas/pruebas_reglas.sql` (un usuario creado Activo queda sin
fecha de bloqueo; `SET timestamp` no permite desbloquear antes de tiempo). Resultado: **240 casos,
240 OK**.

---

## 11. Pendientes (solo documentados, no implementados todavía)

- **Usuario de la aplicación.** Las protecciones de la sección 6 dependen de que la aplicación use
  el usuario limitado de `usuario_aplicacion.sql` y no `root`. Con `root` se pueden borrar
  triggers o escribir directamente en cualquier tabla.
- **Número de cuenta en texto plano.** `informacion_bancaria.numero_cuenta` se guarda tal cual. Se
  cifrará en la aplicación; cuando exista ese código, la columna tendrá que crecer, porque el texto
  cifrado es más largo que `VARCHAR(100)`.
- **Mejoras de nombres.** `convocatorias.usuario_cierre` debería llamarse `id_usuario_cierre`, y los
  estados mezclan género (`Activa` en convocatorias, `Activo` en catálogos). No se cambian ahora
  porque la matriz de requisitos ya usa los nombres actuales.
