# 001 — Corrección de los entregables existentes de BenefitRack

Estado: aprobado por Samuel (2026-10-26).

## Objetivo
Dejar coherentes entre sí la ficha de proyecto, la matriz de requisitos y el script de base de datos, antes de empezar a programar.

## Decisiones de negocio (fuente de verdad para todos los entregables)

### D1. Estados de la postulación
Solo cuatro: `Pendiente`, `Validada`, `Aprobada`, `Rechazada`.

| Estado | Significado |
|---|---|
| Pendiente | El aprendiz envió la postulación completa; nadie la ha revisado. Es el estado inicial. |
| Validada | El administrador revisó datos y documentos y están correctos. |
| Aprobada | La institución eligió al aprendiz como beneficiario y el administrador lo registró. |
| Rechazada | No cumple (en la validación) o no fue elegido. Siempre lleva justificación. |

Transiciones permitidas: `Pendiente → Validada`, `Pendiente → Rechazada`, `Validada → Aprobada`, `Validada → Rechazada`. Ninguna otra.
Se eliminan `Borrador`, `Enviada`, `En revisión`, `Pendiente de documentación` y `Completado`. Si el administrador pide un documento adicional (tabla `solicitudes_documentacion`), la postulación sigue en `Pendiente`.

### D2. Selección de beneficiarios
El sistema NO decide quién recibe el beneficio. La institución (Bienestar / comité) decide fuera del sistema; el administrador marca como `Aprobada` cada postulación `Validada` elegida. El sistema impide aprobar más postulaciones que los `cupos` de la convocatoria y registra quién aprobó y cuándo.

### D3. Asignación del beneficio (beneficiario)
Al aprobarse una postulación se crea una **asignación de beneficio** (una por postulación aprobada) con: valor por pago, número total de pagos, duración (meses), fecha de inicio y su propio estado:
`En curso` → `Finalizado` (automático al registrar el último pago vigente), `Suspendido`, `Cancelado` (manuales, con motivo).
La convocatoria define los valores por defecto (valor por pago, número de pagos, duración); la asignación los copia al crearse.
"Completado" deja de ser estado de la postulación: ahora es `Finalizado` de la asignación.

### D4. Pagos
El sistema no hace transacciones; el administrador registra cada pago a mano. Cada pago pertenece a una asignación y tiene: número de pago, monto, fecha programada (opcional), fecha de pago, si hubo retraso (sí/no), motivo del retraso (obligatorio si hubo retraso), observación, usuario que lo registró y estado `Registrado` / `Anulado`.
Los pagos no se editan ni se borran: un error se corrige anulando el pago (con motivo) y registrando uno nuevo con el mismo número. Solo puede haber un pago `Registrado` por número dentro de una asignación. El número de pago no puede superar el número total de pagos de la asignación.

### D5. Novedades
Tabla de novedades por asignación, visibles para el aprendiz en su portal: `Retraso de pago`, `Suspensión`, `Reactivación`, `Cancelación`, `Otra`, con descripción, fecha y usuario que la registró.

### D6. Validación
Es manual: el administrador compara en una misma vista los datos digitados con los documentos cargados. **No hay OCR** (se eliminan `resultado_ocr` y `datos_extraidos`).

### D7. Roles
`Administrador` y `Aprendiz` (se renombra "Estudiante" → "Aprendiz" en todos los entregables).

### D8. Notificaciones (en el sistema y por correo)
Se generan al pasar a Validada, Aprobada y Rechazada (con la justificación), al registrar un pago y al registrar una novedad.

### D9. Trazabilidad
Auditoría y historial de estados deben guardar al **usuario de la aplicación que hizo el cambio** (el administrador), no un id fijo ni el aprendiz dueño de la postulación. En los triggers se usa la variable de sesión `@app_id_usuario`, que la aplicación fijará al conectarse; si no está definida se guarda `NULL`.
Al **crear** filas (documentos, pagos, novedades, solicitudes de documentación, convocatorias, asignaciones), los datos de «quién y cuándo» los pone la base: con usuario de sesión, el autor es siempre ese usuario (se ignora el valor recibido) y las fechas son la hora real; los datos de validación, anulación, atención o cierre empiezan vacíos. Un documento o una solicitud que llegue con un estado distinto de `Pendiente` se **rechaza** (no se corrige en silencio). Solo un administrador activo crea convocatorias, y nacen `Activa` o `Inactiva`.

### D10. Escalabilidad ("preparado", no multi-institución completo)
Tabla `instituciones` (con el SENA – Centro Textil y de Gestión Industrial como registro inicial) referenciada por `beneficios` y `programas`. No se construye administración de varias instituciones.

### D11. Datos faltantes
- Tabla de migraciones desde Excel (RF-4.3).
- Tabla de recuperación de contraseña: token guardado como hash, fecha de expiración y si ya se usó (RF-2.2).
- `usuarios.edad` se reemplaza por `fecha_nacimiento`.

### D12. Compatibilidad técnica
El script debe correr en MariaDB 10.4 (XAMPP, usado en el SENA) y en MariaDB 11.x: se usa la intercalación `utf8mb4_unicode_ci` en lugar de `utf8mb4_uca1400_ai_ci`.

### D13. Bloqueo por intentos fallidos y desbloqueo automático (decisión de Samuel, 27/10/2026)
Tras **3 intentos fallidos** de inicio de sesión la cuenta pasa de Activo a **Bloqueado** y se **desbloquea sola a los 15 minutos**, sin administrador. Esos dos cambios automáticos solo los hace el inicio de sesión, que no tiene usuario de sesión (`@app_id_usuario = NULL`); con cualquier usuario de sesión son cambios de esa persona y exigen administrador. Un administrador activo puede desbloquear en cualquier momento. Al desbloquear, `intentos_fallidos` vuelve a 0 y `fecha_bloqueo` a `NULL`. La fecha de bloqueo la pone la base. Un usuario Inactivo solo lo reactiva un administrador activo (ni solo ni pasando por Bloqueado); para impedir el acceso sin límite de tiempo se usa Inactivo.

### D14. Tipo de documento «Certificado de EPS» (decisión de Samuel, 27/10/2026)
La semilla de `tipos_documento` incluye el tipo 4 «Certificado de EPS» (certificado de afiliación a la EPS del aprendiz), sin cambiar los tipos 1 a 3. Es **obligatorio** en la postulación; esa obligatoriedad la valida la **aplicación** (la base solo ofrece el tipo).

### D15. Auditoría de cuentas y migraciones con justificación (decisión de Samuel, 27/10/2026)
Todo cambio de rol o de estado de un usuario queda en `auditoria` con quién lo hizo, el valor anterior y el nuevo, el origen (`Sistema`, `Mantenimiento` o `Usuario`) y la justificación. Los cambios que no son automáticos (D13) exigen una justificación no vacía en la variable de sesión `@app_justificacion`; la vía recomendada son los procedimientos `pr_cambiar_estado_usuario` y `pr_cambiar_rol_usuario`, que la fijan solo para ese cambio y la limpian. Crear una migración y cada cambio de su estado quedan en `auditoria` (RI-4.3); una migración terminada no se puede modificar. La aplicación no escribe en `auditoria`: solo lo hacen los triggers.

### D16. Hora real del servidor frente a `SET timestamp`
Las fechas que sirven de control o de evidencia (`fecha_bloqueo` y la regla de los 15 minutos, `auditoria.fecha`, `migraciones.fecha_inicio` y `fecha_fin`) usan `SYSDATE()`, la hora real del servidor, porque `NOW()` se puede cambiar en la sesión con `SET timestamp`. El servidor no debe arrancarse con `--sysdate-is-now`.

### D17. Lista blanca de `root` para `@app_id_usuario = NULL`
Solo el usuario de conexión `root` (instalación, mantenimiento y pruebas) puede hacer acciones de gestión con `@app_id_usuario` en `NULL`, y en los cambios de cuentas igual debe justificar (origen `Mantenimiento`). Con cualquier otro usuario de conexión, una acción de gestión con la variable en `NULL` se rechaza. El usuario se reconoce por el nombre con que se conectó (`USER()`), por eso el servidor no debe tener cuentas anónimas.

## Fuera de alcance
Código de la aplicación, reportes/exportaciones, diagramas.

## Criterios de aceptación
- Dado el script corregido, cuando se ejecuta en una base vacía de MariaDB 10.4, entonces termina sin errores y crea todas las tablas y triggers.
- Dada una postulación Pendiente, cuando se intenta pasar a Aprobada directamente, entonces la base de datos lo rechaza.
- Dada una convocatoria con N cupos y N postulaciones aprobadas, cuando se intenta aprobar otra, entonces se rechaza.
- Dada una asignación de 3 pagos, cuando se registra el 3.er pago vigente, entonces la asignación pasa a Finalizado.
- Dado un pago anulado, cuando se registra uno nuevo con el mismo número, entonces se acepta; si ya existe uno Registrado con ese número, se rechaza.
- Dado un pago con retraso sin motivo, cuando se registra, entonces se rechaza.
- Dado un cambio de estado hecho con `@app_id_usuario = 5`, entonces historial y auditoría guardan el usuario 5.
- La matriz usa solo los estados de D1, tiene requisitos para todas las decisiones D2–D11 y ningún requisito menciona OCR ni "Estudiante".
- Todo nombre de tabla y campo citado en la columna "Requisitos de información" de la matriz existe en el script.
- (D13) Dada una cuenta Activa con 3 intentos fallidos, cuando el inicio de sesión (sin `@app_id_usuario`) la pasa a Bloqueado, entonces se acepta, `fecha_bloqueo` queda con la hora real y la auditoría guarda origen `Sistema` con `id_usuario` NULL.
- (D13) Dada una cuenta Activa con menos de 3 intentos fallidos, cuando el inicio de sesión intenta bloquearla, entonces no cuenta como automático y se rechaza.
- (D13) Dado un aprendiz con sesión, cuando intenta bloquear a otros usuarios (aunque ponga `intentos_fallidos = 3`), entonces se rechaza y nadie queda bloqueado.
- (D13) Dada una cuenta Bloqueada hace menos de 15 minutos, cuando el inicio de sesión intenta pasarla a Activo, entonces se rechaza; pasados 15 minutos, entonces se acepta e `intentos_fallidos` vuelve a 0 y `fecha_bloqueo` a NULL.
- (D13) Dada una cuenta Inactiva, cuando se intenta pasar a Bloqueado o a Activo sin un administrador activo, entonces se rechaza.
- (D14) Dado el script recién ejecutado, entonces `tipos_documento` tiene los tipos 1 a 3 sin cambios y el tipo 4 «Certificado de EPS».
- (D15) Dado un administrador activo con sesión, cuando cambia el estado de un usuario sin justificación, entonces se rechaza; con justificación, entonces se acepta y la auditoría guarda el administrador, el estado anterior y el nuevo, origen `Usuario` y la justificación.
- (D15) Dado un cambio hecho con `pr_cambiar_estado_usuario` o `pr_cambiar_rol_usuario`, cuando termina (bien o con error, incluidos un estado o rol que no existe y un usuario que ya tiene ese estado o rol), entonces `@app_justificacion` queda en NULL; con un estado no válido, entonces la respuesta es «Estado no válido»; con el mismo estado o rol, entonces la respuesta es «El usuario ya tiene ese estado» o «... ese rol»; con un motivo largo (miles de caracteres), entonces se acepta y la auditoría lo guarda completo.
- (D15) Dada una migración Completada, cuando se intenta modificar, entonces se rechaza; al crearla y al terminarla, entonces queda una fila en `auditoria`.
- (D15) Dado el usuario `benefitrack_app`, cuando intenta insertar directamente en `auditoria`, entonces MariaDB lo rechaza (error 1142).
- (D16) Dada una sesión con `SET timestamp` en el pasado o en el futuro, cuando se bloquea o desbloquea una cuenta o se escribe en la auditoría o en migraciones, entonces las fechas guardadas son la hora real del servidor y el desbloqueo antes de 15 minutos reales se rechaza.
- (D17) Dado un usuario de conexión que no es `root` (`benefitrack_app` u otro), cuando hace una acción de gestión con `@app_id_usuario` NULL, entonces se rechaza; dado `root` sin sesión, cuando cambia el estado de un usuario sin justificación, entonces se rechaza, y con justificación queda con origen `Mantenimiento`.
- (D9) Dado un aprendiz con sesión, cuando sube un documento con `estado_validacion = 'Validado'`, entonces se rechaza con «Un documento nuevo debe empezar en estado Pendiente»; cuando lo sube Pendiente pero con validador, fecha de validación u observación, entonces se acepta y esos datos quedan en NULL.
- (D9) Dado un administrador con sesión, cuando registra un pago, una novedad o una solicitud de documentación indicando a otro usuario como autor, entonces queda como autor el administrador de la sesión; un pago nuevo no conserva datos de anulación y una solicitud nueva debe ser Pendiente y sin fecha de atención.
- (D9) Dado un usuario que no es administrador activo, cuando crea una convocatoria, entonces se rechaza; dado un administrador, cuando la crea `Cerrada`, entonces se rechaza, y si manda `usuario_cierre` o `fecha_cierre_real`, entonces quedan en NULL.
