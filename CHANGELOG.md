# Registro de cambios (CHANGELOG)

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

## [2.1.0] - 2026-11-09 — «Vistas de BenefitRack (spec 002)»

Maquetas de las pantallas del sistema según `specs/002-vistas.md`. Son páginas HTML sin programar (no hay aplicación todavía), cada una con su captura de pantalla para computador (1440 px) y para celular (390 px). Se explican en `vistas/LEEME.md`.

### Añadido
- `vistas/`: 11 vistas con todos sus estados (cargando, vacío, error, éxito, etc.):
  - 00 Dashboard del administrador.
  - 01 Mi proceso del aprendiz, y 01b Avisos (la campanita abierta).
  - 02 Contraseña (primer ingreso y recuperación).
  - 03 Detalle de postulación.
  - 04 Reporte de aprobados.
  - 05 Pagos.
  - 06 Usuarios.
  - 07 Convocatorias.
  - 08 Subir documento.
  - 09 Formulario de postulación.
- `vistas/herramientas/construir.js`: arma las páginas terminadas a partir de las fuentes (`vistas/_fuente/`) y saca las capturas.
- `vistas/herramientas/verificar.js`: revisa todas las pantallas (tildes, letra, colores de la paleta, contraste, celular sin desplazamiento horizontal, menú del administrador, capturas del tamaño correcto y reglas propias de cada vista).

### Cambiado
- Cambio de alcance del 2026-11-09: el aprendiz se postula dentro de BenefitRack, con la vista 09 (formulario de postulación). Por eso el criterio CA-3 ahora dice que el aprendiz solo puede postularse desde `01-mi-proceso/convocatoria-abierta` y dentro de `09-postulacion`; se agregan los criterios CA-11 y CA-12 de esa vista.

### Pendientes para la app o la BD
- `solicitudes_documentacion` no guarda el tipo de documento que se pidió (la vista lo muestra como texto libre).
- `notificaciones` no tiene un evento «convocatoria abierta» (la vista 01 lo avisa, pero la base no lo registra).
- Falta decidir cuánto dura el enlace de recuperación de contraseña: «1 hora» es solo un ejemplo de las maquetas (la BD guarda `fecha_expiracion`).
- La BD no guarda el tipo de documento (CC o TI), solo el número; por eso las vistas muestran únicamente el número.

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
