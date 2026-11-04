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
| Las reglas de negocio vivían solo en la aplicación (o no existían). | Las reglas clave se fuerzan en la base con triggers que lanzan errores en español (`SQLSTATE '45000'`). | La aplicación no puede saltarse las reglas por error. **Ojo:** los triggers no protegen contra quien tiene permisos totales (por ejemplo `root`), que puede borrar triggers o escribir directamente en las tablas. Por eso la aplicación debe conectarse con un usuario de permisos limitados (`usuario_aplicacion.sql`, ver la sección 16). |
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

### Triggers del script corregido (55)

**Postulaciones y convocatorias**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_postulacion_inicial` | antes de insertar | Exige que toda postulación nueva empiece en Pendiente, la cree un usuario con rol Aprendiz, en una convocatoria Activa y dentro de sus fechas, y sin datos de validación, aprobación o rechazo ya llenos. La crea el propio aprendiz a su nombre o un administrador activo. | D1, D7, D19 |
| `tr_postulacion_creada` | después de insertar | Anota la creación en el historial (sin estado → Pendiente) y en la auditoría. | D9 |
| `tr_postulacion_validar` | antes de actualizar | Solo deja pasar las transiciones Pendiente → Validada/Rechazada y Validada → Aprobada/Rechazada; exige justificación al rechazar; al aprobar revisa que queden **cupos** en la convocatoria: bloquea la convocatoria con `FOR UPDATE` y cuenta las aprobadas con `LOCK IN SHARE MODE`, para que dos aprobaciones al mismo tiempo no superen los cupos (en la primera versión faltaba este segundo candado y sí se superaban: ver `pruebas/concurrencia.md`); guarda quién validó o aprobó y cuándo, y no deja reescribir esos datos, ni la justificación del rechazo, ni el formulario si ya no está Pendiente; impide cambiar el aprendiz o la convocatoria. Un cambio que no es de estado (formulario, observaciones) lo hace solo el aprendiz dueño o un administrador. | D1, D2, D9, D19 |
| `tr_postulacion_estado` | después de actualizar | Guarda cada cambio en `historial_estados` con `@app_id_usuario`. | D9 |
| `tr_auditoria_postulacion` | después de actualizar | Guarda cada modificación en `auditoria` con `@app_id_usuario`. | D9 |
| `tr_postulacion_notificar` | después de actualizar | Crea la notificación al pasar a Validada, Aprobada o Rechazada (esta última con la justificación). | D8 |
| `tr_postulacion_crear_asignacion` | después de actualizar | Al aprobar, crea la asignación copiando valor por pago, número de pagos y duración de la convocatoria; empieza hoy o, si la convocatoria aún no empezó, el día de inicio de la convocatoria. | D3 |
| `tr_solicitud_documentacion_validar` | antes de insertar en `solicitudes_documentacion` | Solo un administrador pide documentos, y solo a postulaciones Pendiente. La solicitud nace Pendiente (otro estado se rechaza), sin fecha de atención, con fecha de la hora real y a nombre del usuario de sesión. | D1, D9 |
| `tr_postulacion_no_eliminar` | antes de borrar | Impide borrar postulaciones (se rechazan con justificación). | D9 |
| `tr_convocatoria_validar_insercion` | antes de insertar una convocatoria | Solo un administrador activo la crea; nace Activa o Inactiva (otro estado se rechaza) y sin datos de cierre (`usuario_cierre` y `fecha_cierre_real` en `NULL`). | D2, D9 |
| `tr_convocatoria_validar` | antes de actualizar una convocatoria | Todo cambio exige un administrador activo. Los cupos no pueden quedar por debajo de las postulaciones ya aprobadas. Al pasar a Cerrada o Cierre anticipado, la base anota quién (usuario de sesión) y cuándo (hora real); al reabrir, los vacía; fuera de eso no se escriben a mano. | D2, D9 |
| `tr_solicitud_documentacion_actualizar` | antes de actualizar una solicitud de documentación | Lo pedido, a quién, quién lo pidió y cuándo no cambian. Solo Pendiente → Atendida (el aprendiz dueño o un administrador; fecha de atención = hora real) o Pendiente → Cerrada (solo un administrador; sin fecha de atención). | D1, D9 |

**Asignaciones**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_asignacion_validar_insercion` | antes de insertar | Solo si la postulación está Aprobada; solo una asignación por postulación (se revisa antes que la fecha); la fecha de inicio debe estar entre el inicio de la convocatoria y un año después de hoy; debe empezar En curso; con usuario de sesión, el registro queda a su nombre. | D3, D9 |
| `tr_asignacion_validar_estado` | antes de actualizar | Transiciones válidas (En curso → Finalizado/Suspendido/Cancelado, Suspendido → En curso/Cancelado, Finalizado → En curso **solo** cuando lo pide `tr_pago_anulado`); Finalizado solo con todos los pagos vigentes (contados con `LOCK IN SHARE MODE`); motivo nuevo obligatorio al suspender, reactivar o cancelar; valor, número de pagos, duración y fecha de inicio solo se cambian si está En curso y aún no tiene pagos; la fecha de inicio nueva debe estar entre el inicio de la convocatoria y un año después de hoy; quién la registró no cambia. | D3, D4, D9 |
| `tr_asignacion_cambio_estado` | después de actualizar | Audita el cambio y crea automáticamente la novedad de Suspensión, Reactivación o Cancelación. | D5, D9 |
| `tr_asignacion_no_eliminar` | antes de borrar | Impide borrar asignaciones (se cancelan con motivo). | D3 |

**Pagos**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_pago_validar` | antes de insertar | Solo en asignaciones En curso; número entre 1 y el total de pagos; un solo pago Registrado por número; motivo obligatorio si hubo retraso; fecha de pago entre el inicio de la asignación y hoy; fecha programada no anterior al inicio; observación obligatoria si el monto es distinto del valor del pago; guarda quién lo registró (con usuario de sesión, siempre ese usuario) y la hora real; un pago nuevo no trae datos de anulación. | D4, D9 |
| `tr_pago_registrado` | después de insertar | Notifica al aprendiz, audita y, si ya están todos los pagos vigentes (contados con `LOCK IN SHARE MODE`), pasa la asignación a **Finalizado**. | D3, D8, D9 |
| `tr_pago_inmutable` | antes de actualizar | Los pagos no se editan (ni siquiera su `id_pago`): lo único permitido es pasar de Registrado a Anulado con motivo; guarda quién anuló y cuándo. | D4 |
| `tr_pago_anulado` | después de actualizar | Audita la anulación y, si la asignación estaba Finalizado, la devuelve a En curso para registrar el pago corregido. Es el único que puede hacerlo, con la variable interna `@_br_reapertura`. | D3, D4 |
| `tr_pago_no_eliminar` | antes de borrar | Impide borrar pagos. | D4 |

**Usuarios**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_usuario_validar_insercion` | antes de insertar | Crear un usuario con rol Administrador, o con un estado distinto de Activo, exige un administrador activo. La base pone `fecha_bloqueo` (hora actual si nace Bloqueado, `NULL` si no). El registro de aprendices sigue libre. | D7, D9 |
| `tr_usuario_validar` | antes de actualizar | Todo cambio de rol (`id_rol`) o de estado exige un administrador activo (o `root`) y `@app_justificacion`, salvo los automáticos del inicio de sesión (sin usuario de sesión): Activo → Bloqueado con `intentos_fallidos` >= 3 y Bloqueado → Activo pasados 15 minutos desde `fecha_bloqueo` (nunca si es `NULL`). Al pasar a Bloqueado, la base guarda `fecha_bloqueo` = hora real e ignora la fecha recibida. Todo desbloqueo deja `intentos_fallidos = 0` y `fecha_bloqueo = NULL`. Mientras sigue Bloqueado, cambiar `fecha_bloqueo` exige administrador. Los datos de la cuenta (contraseña, correo, documento, contacto...) los cambia su dueño; los de otro usuario, solo un administrador activo, y nunca la contraseña ni el correo de otro administrador; sin sesión, solo `root`. | D7, D9, D13, D15, D19 |
| `tr_usuario_auditar` | después de actualizar | Cada cambio de rol o de estado queda en `auditoria` con quién, valor anterior y nuevo, origen (`Sistema`, `Mantenimiento` o `Usuario`) y justificación. Sumar `intentos_fallidos` solo no se audita. | D9, D15 |

**Migraciones**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_migracion_creada` | después de insertar | Registrar o iniciar una carga queda en `auditoria` (`INICIAR_MIGRACION` / `REGISTRAR_MIGRACION`). | D11 (RI-4.3) |
| `tr_migracion_actualizada` | después de actualizar | Cada cambio de estado de la carga queda en `auditoria` con los totales (`FINALIZAR_MIGRACION`). | D11 (RI-4.3) |
| `tr_migracion_preparar` | antes de insertar | Pone `fecha_inicio` (y `fecha_fin` si ya nace terminada) con la hora real. Solo un administrador activo (o `root` sin sesión) la registra, y con sesión queda a su nombre. | D15, D16, D19 |
| `tr_migracion_validar` | antes de actualizar | Una migración terminada no se puede modificar; al terminar, pone `fecha_fin` con la hora real; `fecha_inicio` no cambia. Solo la modifica un administrador activo; quién hizo la carga no cambia. | D15, D16, D19 |

**Otros**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_documento_validar_insercion` | antes de insertar un documento | Un documento nuevo debe estar Pendiente (otro estado se rechaza); deja en `NULL` quién lo validó, cuándo y la observación, y pone la fecha de carga con la hora real. Solo sube documentos el aprendiz dueño de la postulación o un administrador activo. | D6, D9, D19 |
| `tr_validacion_documento` | antes de actualizar un documento | Guarda fecha y usuario de la validación y no deja escribirlos a mano, ni la fecha de carga, ni pasar el documento a otra postulación. La observación solo la cambia un administrador. Reemplazar el archivo: no si está Validado; si está Pendiente o Rechazado, solo el aprendiz dueño o un administrador, y vuelve a Pendiente sin datos de validación y con fecha de carga nueva. | D6, D9 |
| `tr_novedad_preparar` | antes de insertar una novedad | Exige descripción y guarda quién la registró (con usuario de sesión, siempre ese usuario) y la hora real. | D5, D9 |
| `tr_novedad_registrada` | después de insertar una novedad | Notifica al aprendiz y audita. | D8, D9 |
| `tr_historial_no_modificar` / `tr_historial_no_eliminar` | antes de actualizar / borrar en `historial_estados` | El historial solo admite filas nuevas. | D9 |
| `tr_auditoria_no_modificar` / `tr_auditoria_no_eliminar` | antes de actualizar / borrar en `auditoria` | La auditoría solo admite filas nuevas. | D9 |
| `tr_auditoria_fecha` | antes de insertar en `auditoria` | La fecha de cada fila es la hora real del servidor (`SYSDATE()`). | D16 |

**Catálogos y beneficios (14 triggers)**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_institucion_crear` / `tr_institucion_modificar` | antes de insertar / actualizar en `instituciones` | Exigen un administrador activo (o `root` sin sesión, como la semilla). | D7, D18 |
| `tr_tipo_cuenta_crear` / `tr_tipo_cuenta_modificar` | ídem en `tipos_cuenta` | Igual. | D7, D18 |
| `tr_tipo_documento_crear` / `tr_tipo_documento_modificar` | ídem en `tipos_documento` | Igual. | D7, D18 |
| `tr_banco_crear` / `tr_banco_modificar` | ídem en `entidades_bancarias` | Igual. | D7, D18 |
| `tr_eps_crear` / `tr_eps_modificar` | ídem en `eps` | Igual. | D7, D18 |
| `tr_programa_crear` / `tr_programa_modificar` | ídem en `programas` | Igual. | D7, D18 |
| `tr_beneficio_crear` / `tr_beneficio_modificar` | ídem en `beneficios` | Igual. | D7, D18 |

**Datos de cada persona (4 triggers, ronda 11)**

| Trigger | Cuándo | Qué hace | Decisión |
|---|---|---|---|
| `tr_bancaria_crear` | antes de insertar en `informacion_bancaria` | Solo el titular de la cuenta o un administrador activo la registra. | D19 |
| `tr_bancaria_modificar` | antes de actualizar en `informacion_bancaria` | Solo el titular o un administrador activo la cambia; la cuenta no se pasa a otro usuario. | D19 |
| `tr_notificacion_crear` | antes de insertar en `notificaciones` | Solo el sistema (los triggers, que corren con un administrador o con `root`) o un administrador activo crea notificaciones. | D8, D19 |
| `tr_notificacion_modificar` | antes de actualizar en `notificaciones` | Marcarla como leída: su titular o un administrador. El texto y el envío del correo: solo un administrador. | D8, D19 |

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

Desde la séptima ronda (sección 11) hay una tercera variable, **`@app_justificacion`**: el motivo
que escribe el administrador antes de cambiar el estado de un usuario. Es obligatoria en esos
cambios (salvo los automáticos del inicio de sesión) y se guarda en `auditoria`.

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
| Crear una convocatoria | `tr_convocatoria_validar_insercion` |
| Modificar una convocatoria (cualquier dato) | `tr_convocatoria_validar` |
| Escribir la observación de un documento | `tr_validacion_documento` |
| Cerrar una solicitud de documentación | `tr_solicitud_documentacion_actualizar` |
| Crear o modificar instituciones, tipos de cuenta, tipos de documento, entidades bancarias, EPS, programas y beneficios | `tr_*_crear` / `tr_*_modificar` (sección 14) |

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

## 11. Séptima ronda: auditoría de cuentas y migraciones (explicado fácil)

**Por qué.** La matriz (RF-1.3) pide que el cambio de estado de un usuario lleve una justificación
guardada en la auditoría, y RI-4.3 pide que las migraciones desde Excel dejen huella en la
auditoría. Antes, la base solo controlaba *quién* podía cambiar una cuenta, pero no anotaba nada.

**1. Justificación obligatoria: `@app_justificacion`.**
Es como la casilla «motivo» de un formulario: sin escribirla, no se puede enviar. Antes de cambiar
el estado de un usuario, la aplicación fija `SET @app_justificacion = '<motivo>';`. Si está vacía,
la base responde `El cambio de estado de un usuario exige una justificación (@app_justificacion)`.
No se pide en los cambios **automáticos** (bloqueo por 3 intentos fallidos y desbloqueo a los 15
minutos), porque ahí no hay una persona decidiendo. Si un administrador bloquea a alguien a mano,
sí se pide.

**2. Cada cambio de cuenta queda anotado.**
El trigger nuevo `tr_usuario_auditar` escribe una fila en `auditoria` cada vez que cambia el rol o
el estado de un usuario: quién lo hizo, el rol y el estado anterior y nuevo, la justificación y el
origen. Si el origen es `Sistema` (bloqueo o desbloqueo automático), `id_usuario` queda en `NULL`,
que aquí significa «lo hizo el sistema», y la justificación la escribe la base («Bloqueo
automático por intentos fallidos» o «Desbloqueo automático pasados 15 minutos»). Sumar intentos
fallidos sin cambiar el estado no se anota, para no llenar la tabla en cada intento.

**3. Las migraciones también quedan anotadas.**
`tr_migracion_creada` anota cada carga nueva y `tr_migracion_actualizada`, cada cambio de su estado
(por ejemplo, cuando termina), con los totales de filas exitosas y fallidas. Actualizar solo los
contadores mientras sigue «En proceso» no se anota.

**4. Quién escribe en la auditoría.** Solo los triggers. El usuario de la aplicación
(`benefitrack_app`) sigue sin permiso de `INSERT` en `auditoria`, y las filas siguen sin poderse
modificar ni borrar.

**Pruebas nuevas.**
- `pruebas/pruebas_reglas.sql`, bloque L (L01 a L17) y A02 (ahora 31 triggers). Resultado:
  **257 casos, 257 OK**.
- `pruebas/pruebas_usuario_aplicacion.sql`, U8 (conectado como `benefitrack_app`: sin
  justificación el administrador 5 no puede reactivar a un aprendiz; con ella sí, y queda la fila
  en auditoría) y U9 (`benefitrack_app` no puede insertar en `auditoria`: error 1142).

No se cambiaron tablas ni columnas: se agregaron 3 triggers (ahora son 31) y la función
`fn_cambio_estado_sistema`, y se ajustó `tr_usuario_validar`.

**Corregido en la octava ronda (sección 12):** en esta ronda, un cambio se tomaba como
«automático» solo por su forma y porque quien lo hacía no era administrador. Así, un aprendiz con
sesión podía bloquear a los administradores y la auditoría lo anotaba como «Sistema».

---

## 12. Octava ronda: quién hizo de verdad cada cambio (explicado fácil)

Un revisor volvió a probar las reglas de la séptima ronda con sus propios guiones y encontró estos
huecos. Las decisiones nuevas quedaron escritas en la especificación como **D13 a D17**.

**B1. Un aprendiz podía bloquear a todos y la auditoría decía «Sistema».**
La base decidía que un bloqueo era «automático» mirando solo la forma del cambio (Activo →
Bloqueado) y que quien lo hacía no fuera administrador. Un aprendiz con sesión ejecutó
`UPDATE usuarios SET estado = 'Bloqueado' WHERE estado = 'Activo' AND id_usuario <> 3` y bloqueó a
los administradores; la auditoría anotó «Bloqueo automático por intentos fallidos». Ahora un cambio
es automático **solo** si lo hace el inicio de sesión, que no tiene usuario de sesión
(`@app_id_usuario` en `NULL`), y además:

- para el bloqueo, la cuenta debe quedar con `intentos_fallidos` en 3 o más;
- para el desbloqueo, deben haber pasado 15 minutos reales desde el bloqueo.

En cualquier otro caso hace falta un administrador activo y una justificación.

**I1. El administrador que se bloqueaba a sí mismo quedaba como «Sistema».**
La auditoría volvía a preguntar «¿es administrador?» después del cambio, cuando ya estaba
Bloqueado, y perdía su justificación. Con el criterio de B1 esa pregunta ya no se hace: el origen
depende solo de si hay usuario de sesión. Queda un límite, documentado en el README: en un
`UPDATE` de varias filas, si el administrador se inactiva primero, las filas siguientes ya no son
suyas y la sentencia entera se rechaza.

**I2. La fecha de la auditoría se podía falsear.** Con `SET timestamp` la fila quedaba con la fecha
que uno quisiera. Ahora la pone el trigger `tr_auditoria_fecha` con la hora real (`SYSDATE()`). Lo
mismo pasa con `fecha_inicio` y `fecha_fin` de las migraciones.

**I3. El motivo se arrastraba al siguiente cambio.** `@app_justificacion` se quedaba puesta en la
sesión, y un segundo cambio la reutilizaba sin que nadie escribiera un motivo nuevo. Ahora hay dos
procedimientos, `pr_cambiar_estado_usuario` y `pr_cambiar_rol_usuario`, que fijan el motivo solo
para ese cambio y lo borran al terminar, aunque falle. `benefitrack_app` tiene permiso `EXECUTE`
sobre ellos y son la vía recomendada. Para que sea coherente, **los cambios de rol también exigen
justificación**, así nunca se guarda un motivo que no se pidió. Ojo con la prueba **L23**: documenta
el **riesgo** de la vía directa (`SET @app_justificacion` + `UPDATE`). Que salga «OK» significa que
el arrastre sigue ocurriendo por esa vía, no que esté resuelto. La solución es usar los
procedimientos (L24 a L28).

**I4. Una migración terminada se podía reescribir sin dejar huella.** Ahora, cuando una carga
termina (Completada, Completada con errores o Fallida), ya no se puede modificar
(`tr_migracion_validar`).

**I5. La prueba con un tercer usuario era más débil de lo necesario.** `pruebas/otro_usuario`:

- recibe por separado la ruta de `mysql.exe`, el host y el puerto, y ya no edita el argumento;
- usa una contraseña aleatoria guardada en un archivo temporal que se borra al terminar;
- crea el usuario para `localhost` y `127.0.0.1` con permisos mínimos por columna;
- limpia empezando por `DROP USER`;
- sale con código 0 o 1 según el veredicto.

**Menores.**
- **M5.** Con `root` y sin usuario de sesión, los cambios de cuentas que no son automáticos exigen
  justificación y quedan con origen `Mantenimiento`. Solo con origen `Sistema` un `id_usuario` en
  `NULL` significa «el sistema».
- **M7.** El inicio de sesión debe ejecutarse con `@app_id_usuario = NULL`.
- **M8.** Con un id que no existe, la base no lo reconoce como administrador.
- **M10.** Nuevo límite conocido: `SYSDATE()` con `binlog_format=STATEMENT`.

**Pruebas.** En `pruebas/pruebas_reglas.sql`:

- se añadieron L18 a L31 y el bloque M (M01 a M17);
- las pruebas de bloqueo y desbloqueo automáticos de los bloques J y K ahora usan el flujo real del
  inicio de sesión;
- A02 espera ahora 34 triggers.

Resultado: **288 casos, 288 OK**. En `pruebas/pruebas_usuario_aplicacion.sql` se añadieron U10 (el
procedimiento con `benefitrack_app`) y U11 (el caso del revisor con `benefitrack_app`).

No se cambiaron tablas ni columnas. Se agregaron 3 triggers (ahora son 34) y 2 procedimientos, y
se ajustaron `fn_cambio_estado_sistema`, `tr_usuario_validar` y `tr_usuario_auditar`.

---

## 13. Novena ronda: los procedimientos de cambio de cuentas, sin cabos sueltos (explicado fácil)

La revisión de la octava ronda dio el visto bueno, con tres arreglos en los procedimientos
`pr_cambiar_estado_usuario` y `pr_cambiar_rol_usuario`.

**I1. Un estado inexistente dejaba el motivo puesto.**
Con `CALL pr_cambiar_estado_usuario(3, 'Nada', 'motivo')`, MariaDB daba el error 1265 («Data
truncated»). En modo estricto ese error llega con un código de clase *advertencia* (`01000`), y el
manejador del procedimiento solo atrapaba errores, así que `@app_justificacion` quedaba con
«motivo». Ahora:

- el procedimiento revisa el estado al principio y responde
  `Estado no válido: debe ser Activo, Inactivo o Bloqueado`;
- el manejador atrapa también las advertencias (`SQLWARNING`).

El rol también se revisa: si no existe o es `NULL`, la respuesta es `No existe el rol indicado`.

**M1. Un motivo largo fallaba antes de empezar.** El parámetro del motivo era `VARCHAR(1000)`; con
1001 caracteres MariaDB rechazaba la llamada antes de entrar al procedimiento. Ahora es `TEXT` y se
guarda completo en la auditoría (que es `LONGTEXT`). Se probó con 5000 caracteres.

**M2. Pedir un cambio que no cambia nada no avisaba.** Si el usuario ya tenía ese estado o ese rol,
el procedimiento terminaba «bien» sin hacer nada. Ahora responde `El usuario ya tiene ese estado` o
`El usuario ya tiene ese rol`. Además, si el `UPDATE` no cambió ninguna fila (`ROW_COUNT() = 0`),
avisa. La comparación se hace antes del `UPDATE` porque algunos conectores (por ejemplo, JDBC con su
configuración por defecto) hacen que `ROW_COUNT()` cuente las filas encontradas y no las
cambiadas. En todos los casos la variable queda en `NULL`.

**M4.** Se aclaró, en la sección 12 y en el README, que la prueba L23 documenta un riesgo de la vía
directa: su «OK» no significa que esté resuelto.

**En las pruebas.** Ahora `pr_esperar_error` atrapa también advertencias. Sin eso, el error 1265
cortaba toda la ejecución de las pruebas en vez de contarse como un caso fallido.

**Pruebas nuevas.** Bloque N (N01 a N10) en `pruebas/pruebas_reglas.sql`. Resultado: **298 casos,
298 OK**. Con `benefitrack_app`, U1 a U11 siguen en OK.

No se cambiaron tablas, columnas ni triggers en esta parte: solo los dos procedimientos.

### 13.1 Lo que se crea nace limpio: nadie se inventa un «validado por»

**El hueco.** `documentos_postulacion` solo tenía un trigger para los cambios (`UPDATE`), no para
las filas nuevas (`INSERT`). Como la aplicación sí puede insertar documentos, un aprendiz podía
subir su documento **ya** con `estado_validacion = 'Validado'`, `id_usuario_validacion = 5` y una
fecha inventada. Es como entregar un formulario con el sello de «aprobado» ya puesto por uno mismo.

**El arreglo.** Nuevo trigger `tr_documento_validar_insercion` (antes de insertar):

- **Decisión: rechazar, no corregir en silencio.** Si el documento llega con un estado distinto de
  `Pendiente`, la base responde `Un documento nuevo debe empezar en estado Pendiente`. Se eligió
  rechazar porque un estado «Validado» al subir es un error claro (o un intento de trampa) y es
  mejor que la aplicación se entere.
- Los datos de la validación (`id_usuario_validacion`, `fecha_validacion` y `observaciones`, que
  es la nota del administrador) se dejan en `NULL` aunque vengan llenos. Solo los escribe
  `tr_validacion_documento` cuando un administrador valida.
- `fecha_carga` es la hora real del servidor (`SYSDATE()`).

**Revisión de las demás tablas donde la aplicación puede insertar.** Se buscaron campos de «quién
y cuándo» que se pudieran inventar al crear la fila:

| Tabla | Antes | Ahora |
|---|---|---|
| `postulaciones` | Ya rechazaba datos de validación, aprobación o rechazo al crear (`tr_postulacion_inicial`). Comprobado. | Sin cambios. |
| `pagos` | Si la aplicación mandaba `id_usuario_registro`, se respetaba (se podía poner a otro administrador como autor). Los datos de anulación se podían mandar llenos. | Con usuario de sesión, el autor es **siempre** ese usuario. `fecha_registro` es la hora real. Motivo, fecha y autor de la anulación quedan en `NULL`. |
| `novedades` | Igual que pagos con `id_usuario`. | Autor = usuario de sesión; `fecha` = hora real. |
| `solicitudes_documentacion` | El autor lo ponía la aplicación; se podía crear ya `Atendida` y con fecha de atención. | Debe nacer `Pendiente` (si no, `Una solicitud nueva debe empezar en estado Pendiente`); `fecha_atencion` en `NULL`; `fecha_solicitud` = hora real; autor = usuario de sesión. |
| `convocatorias` | No tenía trigger al crear: cualquiera con permiso de `INSERT` podía crear una, incluso ya `Cerrada` y con `usuario_cierre` inventado. | Nuevo `tr_convocatoria_validar_insercion`: solo un administrador activo; nace `Activa` o `Inactiva` (si no, `Una convocatoria nueva debe estar Activa o Inactiva`); `usuario_cierre` y `fecha_cierre_real` en `NULL`. |
| `asignaciones_beneficio` | La aplicación no tiene `INSERT` (las crea la base al aprobar). | Por coherencia, misma regla de autor. |

**La regla de autor, en una línea.** Si hay usuario de sesión (`@app_id_usuario`), la base pone a
ese usuario como autor e ignora lo que mande la aplicación. Si no hay (solo `root`, en
mantenimiento), respeta **solo el autor** recibido. *(Corregido en la décima ronda, I1: antes decía
que sin sesión se respetaba «el valor recibido» en general, y no es así.)* Aun con `root`:

- las **fechas** (`fecha_carga`, `fecha_registro`, `fecha`, `fecha_solicitud`) las pone siempre la
  base con la hora real: no se pueden cargar fechas históricas por esta vía;
- los **estados iniciales** se validan igual (un documento o una solicitud deben nacer `Pendiente`;
  una convocatoria, `Activa` o `Inactiva`);
- los datos de validación, anulación, atención o cierre empiezan vacíos.

**Pruebas nuevas.** Bloque O (O01 a O14) en `pruebas/pruebas_reglas.sql` y U12 en
`pruebas/pruebas_usuario_aplicacion.sql` (como `benefitrack_app`, un aprendiz no puede subir un
documento ya validado ni con datos de validación inventados). A02 espera ahora **36 triggers**.
Resultado: **312 casos, 312 OK**; con `benefitrack_app`, U1 a U12 en OK.

No se cambiaron tablas ni columnas: se agregaron 2 triggers (ahora son 36) y se ajustaron 4.

---

## 14. Décima ronda: lo que ya existe solo lo cambia quien debe (explicado fácil)

La auditoría de la novena ronda dijo «no se puede hacer merge» por tres huecos que **ya existían**
en los cambios (`UPDATE`). La novena ronda cuidó las filas nuevas; esta cuida las que ya están.

**B1. Convocatorias.** Antes solo se pedía administrador si cambiaban los cupos o el estado. Un
aprendiz (o la aplicación con la variable en `NULL`) podía cambiar el valor del pago, el número de
pagos, la duración o las fechas, y esos valores se copian a la asignación al aprobar. Es como si un
estudiante pudiera cambiar el monto de la beca en el reglamento. Ahora:

- **todo** cambio de una convocatoria exige un administrador activo;
- al pasar a `Cerrada` o `Cierre anticipado`, la base anota quién la cerró (`usuario_cierre` = usuario
  de sesión; sin sesión, solo `root`, se respeta el autor recibido) y cuándo (`fecha_cierre_real` =
  hora real), aunque la aplicación mande otros valores;
- al reabrirla (volver a `Activa` o `Inactiva`), esos dos datos se vacían;
- fuera de esos dos momentos no se pueden escribir a mano: `Los datos del cierre de la convocatoria
  los registra la base de datos; no se pueden modificar`.

Con esto se cierra el pendiente «Quién cerró una convocatoria» de la ronda anterior.

**B2. Documentos.** En un documento ya `Validado` se podía cambiar el archivo y seguía diciendo
«validado por» el administrador, aunque él nunca vio el archivo nuevo. Ahora:

- **Validado:** el archivo (tipo, nombre, ruta, extensión o tamaño) no se reemplaza: `Un documento
  validado no se puede reemplazar`.
- **Pendiente o Rechazado:** sí se puede reemplazar (así el aprendiz corrige un documento
  rechazado), pero solo el **aprendiz dueño** de la postulación o un administrador. El documento
  vuelve a `Pendiente`, se vacían quién lo validó, cuándo y la observación, y `fecha_carga` pasa a
  ser la hora real. Si en el mismo cambio se intenta dejarlo `Validado`, se rechaza: se valida
  después, por separado.
- La **observación** es la nota del administrador: solo él la cambia.
- `fecha_carga` no se cambia a mano.
- **Decisión propia:** un documento **no se pasa a otra postulación** (`id_postulacion` fijo),
  ni siquiera estando Pendiente. El pedido permitía cambiarlo en Pendiente o Rechazado, pero mover el
  documento de una persona a la postulación de otra no tiene uso real y sí riesgo.

**B3. Solicitudes de documentación.** No tenían control en los cambios: un aprendiz podía ponerse
como autor, cambiar el texto pedido o inventar fechas. Nuevo trigger
`tr_solicitud_documentacion_actualizar`:

- no cambian lo pedido (`observacion`), la postulación, quién lo pidió ni cuándo;
- solo se puede pasar de `Pendiente` a `Atendida` o a `Cerrada`. Nada vuelve atrás;
- **Atendida** (decisión): la marca el **aprendiz dueño** de la postulación, que es quien sube lo
  pedido, o un administrador. Otro aprendiz no puede. La fecha de atención es la hora real, aunque
  se mande otra;
- **Cerrada** (la solicitud ya no hace falta): solo un administrador, y queda sin fecha de atención
  porque nadie la atendió.

Para B2 y B3 hay una rutina nueva, `pr_exigir_dueno_o_administrador(postulación, acción)`, igual que
`pr_exigir_administrador` pero que también acepta al dueño de la postulación. Con la variable en
`NULL`, solo `root` pasa.

**I1.** Se corrigió la sección 13.1: sin sesión (`root`) solo se respeta el autor. Las fechas las
pone siempre la base y los estados iniciales se validan igual.

**I2. Catálogos y beneficios.** `benefitrack_app` puede insertar y modificar instituciones, tipos de
cuenta, tipos de documento, entidades bancarias, EPS, programas y beneficios. Antes lo podía hacer
cualquiera, por ejemplo un aprendiz que creara un beneficio. Ahora cada tabla tiene dos triggers
cortos (crear y modificar) que llaman a `pr_exigir_administrador`: son los 14 de la tabla
«Catálogos y beneficios» de la sección 3. La semilla del script la carga `root` con la variable en
`NULL`. Para que no falle si alguien ejecuta el script con la variable puesta, el script hace
`SET @app_id_usuario = NULL` antes de la semilla. Comprobado: el script corre dos veces seguidas
sin errores.

**M1.** Los manejadores de errores de `pr_cambiar_estado_usuario`, `pr_cambiar_rol_usuario` y del
arnés de pruebas tienen ahora un comentario. Ese comentario explica qué atrapan y por qué.

**M2.** En las pruebas, `pr_esperar_exito` marca FALLO si la sentencia deja advertencias (antes solo
miraba los errores). El caso P44 lo comprueba con una sentencia que deja una advertencia a
propósito. Ninguna prueba anterior dejaba advertencias.

**M3.** El usuario que registró una asignación (`id_usuario_registro`) no se cambia con un
`UPDATE`, ni siquiera un administrador.

**Pruebas nuevas.**

- Bloque P (P01 a P44) en `pruebas/pruebas_reglas.sql`.
- U13 en `pruebas/pruebas_usuario_aplicacion.sql`. Como `benefitrack_app`, ni un aprendiz ni la
  variable en `NULL` cambian convocatorias, documentos validados, solicitudes ni catálogos.
- A02 espera ahora **51 triggers**.

Resultado: **356 casos, 356 OK**; con `benefitrack_app`, U1 a U13 en OK. El guion de
reproducción del revisor ya no logra ningún cambio indebido.

Una nota honesta sobre P34: la primera versión intentaba modificar entidades bancarias, pero la
semilla no trae ninguna. Así el `UPDATE` no tocaba filas y la prueba no probaba nada. Se corrigió
creando antes un banco como administrador. Con el código anterior, la prueba corregida sigue en
FALLO.

No se cambiaron tablas ni columnas. Se agregaron 15 triggers (ahora son 51) y 1 procedimiento, y
se ajustaron 3 triggers.

---

## 15. Undécima ronda: nadie toca los datos de otra persona (explicado fácil)

La revisión de la décima ronda encontró que un aprendiz con sesión podía tocar datos que **no son
suyos**. Es como si en un colegio cualquier estudiante pudiera abrir el casillero de otro: la
cerradura revisaba que fuera «un estudiante», pero no que fuera **el dueño**. Esta ronda cierra
seis huecos (D19).

Para casi todos se agregó una rutina nueva, `pr_exigir_titular_o_administrador(titular, acción)`.
Es igual que `pr_exigir_administrador`, pero también deja pasar al **titular** del dato (el usuario
de sesión es el mismo `id_usuario` de la fila). Con la variable en `NULL`, como siempre, solo pasa
`root`. Responde, por ejemplo: `Solo el titular o un administrador activo puede modificar esta
cuenta bancaria`.

**1. Cuenta bancaria (`informacion_bancaria`).** Antes un aprendiz podía registrar o cambiar la
cuenta de otro aprendiz, y así desviar sus pagos. Ahora dos triggers nuevos, `tr_bancaria_crear` y
`tr_bancaria_modificar`, exigen que lo haga el titular o un administrador activo. Además, una
cuenta **no se pasa a otro usuario** (`La cuenta bancaria no se puede pasar a otro usuario`).

**2. Contraseña y datos de la cuenta (`usuarios`).** Antes cualquiera con sesión podía cambiar la
contraseña o el correo de cualquier usuario, incluido un administrador. Ahora `tr_usuario_validar`
revisa los **datos de la cuenta** (contraseña, correo, documento, nombre, apellido, fecha de
nacimiento, teléfono, dirección, programa, EPS, ficha y fecha de registro):

- cada quien cambia **los suyos**;
- los de **otro** usuario (y también sus intentos fallidos) solo un administrador activo:
  `Solo un administrador activo puede modificar los datos de la cuenta de otro usuario`;
- **decisión:** un administrador **no** cambia la contraseña ni el correo de **otro
  administrador** (`Un administrador no puede cambiar la contraseña ni el correo de otro
  administrador`). Con la contraseña entraría a su nombre, y con el correo pediría una
  recuperación de contraseña que le llegaría a él. Ese administrador cambia los suyos o usa la
  recuperación. Sí puede corregir los demás datos de otro administrador (por ejemplo el
  teléfono) y sí puede restablecer la contraseña de un **aprendiz**;
- sin usuario de sesión (`NULL`), la aplicación solo puede tocar lo del inicio de sesión
  (intentos, bloqueo, desbloqueo). Los datos de una cuenta, solo `root` (mantenimiento).
  **Consecuencia para la aplicación:** en la recuperación de contraseña, después de comprobar el
  token, debe fijar `SET @app_id_usuario = <dueño del token>`; así es un cambio de la propia
  contraseña.

**3. Postulaciones.** Antes un aprendiz podía crear una postulación a nombre de otro y cambiar el
formulario o las observaciones de postulaciones ajenas. Ahora:

- `tr_postulacion_inicial`: la postulación la crea el propio aprendiz a su nombre o un
  administrador activo (**decisión:** el administrador puede registrarla a nombre de un aprendiz,
  por ejemplo si el aprendiz la entregó en papel);
- `tr_postulacion_validar`: un cambio que no es de estado (formulario, observaciones) lo hace el
  aprendiz dueño o un administrador. El formulario, como antes, solo mientras esté Pendiente; los
  cambios de estado siguen exigiendo administrador.

**4. Subir documentos a una postulación ajena.** Era el pendiente «Dueño de la postulación en otras
acciones». Ahora `tr_documento_validar_insercion` llama a `pr_exigir_dueno_o_administrador`: solo
sube documentos el aprendiz dueño de la postulación o un administrador.

**5. Notificaciones.** Antes un aprendiz podía marcar como leídas (o cambiar) las notificaciones de
otro, e inventarse avisos. Dos triggers nuevos:

- `tr_notificacion_crear`: las crea el **sistema** o un administrador activo. El «sistema» son los
  triggers de postulaciones, pagos y novedades; esas acciones ya las hace un administrador (o
  `root`), así que sus avisos siguen creándose solos;
- `tr_notificacion_modificar`: marcarla como leída (`leida`, `fecha_lectura`) solo su titular o un
  administrador. El texto (a quién, correo de destino, evento, asunto, cuerpo, postulación, fecha)
  y el envío del correo (`estado_correo`, `fecha_envio`) solo un administrador. **Decisión:** el
  estado del correo es dato del sistema, no del aprendiz; la aplicación lo anota en la sesión del
  administrador que causó el aviso.

**6. Migraciones.** Antes cualquier sesión podía registrar o cambiar una carga desde Excel. Ahora
`tr_migracion_preparar` y `tr_migracion_validar` exigen un administrador activo (o `root` sin
sesión). Además, siguiendo la regla de autor de la novena ronda: con usuario de sesión la carga
queda **a su nombre** aunque la aplicación mande otro, y quién hizo la carga **no cambia** después
(`El usuario que hizo la migración no se puede modificar`).

**Pruebas nuevas.**

- Bloque Q (Q01 a Q44) en `pruebas/pruebas_reglas.sql`: un caso que debe fallar y otro que debe
  funcionar para cada hueco.
- U14 en `pruebas/pruebas_usuario_aplicacion.sql`: como `benefitrack_app`, el aprendiz 21 intenta
  cambiar la contraseña del administrador 5, registrar la cuenta bancaria del aprendiz 22,
  postularlo, subir un documento a su postulación, marcar sus notificaciones como leídas y
  registrar una migración; y sin sesión, cambiar la contraseña del administrador. Todo se rechaza.
- A02 espera ahora **55 triggers**.

Primero se escribieron las pruebas y se comprobó que fallaban (26 casos en FALLO con el script de la
décima ronda, y U14 en FALLO), y después se cambió el script. Resultado: **400 casos, 400 OK**; con
`benefitrack_app`, U1 a U14 en OK; las pruebas de concurrencia B1 e I1 y la de `otro_usuario` dicen
CORRECTO; el script corre dos veces seguidas sin errores.

No se cambiaron tablas ni columnas. Se agregaron 4 triggers (ahora son 55) y 1 procedimiento, y se
ajustaron 6 triggers (`tr_usuario_validar`, `tr_postulacion_inicial`, `tr_postulacion_validar`,
`tr_documento_validar_insercion`, `tr_migracion_preparar` y `tr_migracion_validar`).

---

## 16. Pendientes (solo documentados, no implementados todavía)

- **Usuario de la aplicación.** Las protecciones de la sección 6 dependen de que la aplicación use
  el usuario limitado de `usuario_aplicacion.sql` y no `root`. Con `root` se pueden borrar
  triggers o escribir directamente en cualquier tabla.
- **Número de cuenta en texto plano.** `informacion_bancaria.numero_cuenta` se guarda tal cual. Se
  cifrará en la aplicación; cuando exista ese código, la columna tendrá que crecer, porque el texto
  cifrado es más largo que `VARCHAR(100)`.
- **Reglas de reapertura de convocatorias.** La base no define todavía qué transiciones de estado
  de una convocatoria son válidas (por ejemplo, si una `Cerrada` puede volver a `Activa`). Hoy un
  administrador puede reabrirla; al hacerlo se vacían los datos del cierre (sección 14).
- **Sesiones y tokens de recuperación.** `sesiones` y `recuperacion_contrasena` no revisan quién es
  el titular: se escriben durante el inicio de sesión y la recuperación, cuando todavía no hay
  usuario de sesión, así que la base no tiene con qué compararlo. Ese control queda en la
  aplicación (ronda 11).
- **Correos enviados por un proceso aparte.** Si en el futuro los correos los envía una cola o un
  proceso sin sesión de administrador, no podrá anotar `estado_correo` (ronda 11, D19). Habría que
  decidir una forma segura de identificar ese proceso.
- **Mejoras de nombres.** `convocatorias.usuario_cierre` debería llamarse `id_usuario_cierre`, y los
  estados mezclan género (`Activa` en convocatorias, `Activo` en catálogos). No se cambian ahora
  porque la matriz de requisitos ya usa los nombres actuales.
