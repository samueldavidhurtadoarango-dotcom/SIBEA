# Registro de cambios (CHANGELOG)

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

## [2.0.0] - 2026-11-04

Corrección completa de los entregables según `specs/001-correccion-entregables.md` (decisiones D1 a D19).

### Añadido
- `entregables/Ficha_Proyecto_BenefitRack_v2.docx`: ficha del proyecto corregida (ya no contiene texto de otro proyecto).
- `base-de-datos/benefitrack_v2.sql`: base de datos v2 para MariaDB 10.4 (XAMPP), con 55 triggers (reglas automáticas) y procedimientos para cambiar el estado o el rol de una cuenta con justificación.
- `base-de-datos/pruebas/`: 400 pruebas de reglas, 14 pruebas con el usuario limitado de la aplicación (U1–U14), pruebas de concurrencia y de usuario distinto de `root`.
- `base-de-datos/usuario_aplicacion.sql`: usuario limitado `benefitrack_app` para la aplicación (hay que cambiar la clave de ejemplo).
- `entregables/Matriz_Requisitos_v2.xlsx`: matriz de requisitos con 46 requisitos en 9 módulos y control de cambios; `entregables/verificar_matriz.js` (11 de 11 reglas).
- `herramientas/`: generadores de la ficha y de la matriz.

### Cambiado
- Estados de postulación: Pendiente, Validada, Aprobada y Rechazada.
- Bloqueo tras 3 intentos fallidos, con desbloqueo automático a los 15 minutos.
- Certificado de EPS obligatorio para postular (nuevo tipo de documento).
- La base guarda auditoría de cambios de rol y estado de las cuentas, de migraciones y de postulaciones.
- Nadie puede modificar datos de otra persona (cuenta bancaria, contraseña, postulaciones, documentos, notificaciones, migraciones), salvo un administrador activo donde corresponde.
- Las aprobaciones respetan los cupos aunque dos administradores aprueben al mismo tiempo.

### Pendiente (mejoras futuras)
- Ver `base-de-datos/CAMBIOS.md` §15 y §16: cifrado del número de cuenta, reglas de reapertura de convocatorias, control de titular en `sesiones` y `recuperacion_contrasena`, envío de correos sin sesión de administrador y mejoras de nombres.
- Figuras explicativas de la documentación (skill `anatomy`).
