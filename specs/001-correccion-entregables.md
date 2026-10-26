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

### D10. Escalabilidad ("preparado", no multi-institución completo)
Tabla `instituciones` (con el SENA – Centro Textil y de Gestión Industrial como registro inicial) referenciada por `beneficios` y `programas`. No se construye administración de varias instituciones.

### D11. Datos faltantes
- Tabla de migraciones desde Excel (RF-4.3).
- Tabla de recuperación de contraseña: token guardado como hash, fecha de expiración y si ya se usó (RF-2.2).
- `usuarios.edad` se reemplaza por `fecha_nacimiento`.

### D12. Compatibilidad técnica
El script debe correr en MariaDB 10.4 (XAMPP, usado en el SENA) y en MariaDB 11.x: se usa la intercalación `utf8mb4_unicode_ci` en lugar de `utf8mb4_uca1400_ai_ci`.

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
