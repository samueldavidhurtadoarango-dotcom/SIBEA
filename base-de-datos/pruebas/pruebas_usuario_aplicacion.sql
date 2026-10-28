-- =====================================================================
-- BenefitRack - Pruebas con el usuario limitado de la aplicación
-- =====================================================================
-- Comprueba que, conectado como benefitrack_app:
--   * con @app_id_usuario = NULL no se pueden hacer acciones de gestión;
--   * un aprendiz no puede darse el rol de Administrador;
--   * el administrador 5 sí puede gestionar;
--   * una cuenta bloqueada por intentos fallidos se desbloquea sola a los
--     15 minutos, y no antes;
--   * un cambio de estado del administrador exige @app_justificacion y
--     queda en auditoria; benefitrack_app no escribe en auditoria;
--   * pr_cambiar_estado_usuario se puede usar (EXECUTE) y limpia la
--     justificación; un aprendiz con sesión no bloquea a otros;
--   * un aprendiz no sube documentos ya validados;
--   * ni un aprendiz ni una sesión NULL cambian convocatorias, documentos
--     validados, solicitudes de documentación o catálogos (U13).
--
-- Preparación (como root):
--   1. benefitrack_v2.sql
--   2. usuario_aplicacion.sql
--   3. pruebas/concurrencia/preparar.sql   (crea el administrador 5, el
--      aprendiz 22 y la postulación 2 en estado Validada)
-- Ejecución (como benefitrack_app, con --force para que siga después de
-- cada error esperado):
--   mysql -u benefitrack_app -p --force --table < pruebas/pruebas_usuario_aplicacion.sql
--
-- Todo ocurre en una transacción que se deshace al final. Los errores
-- esperados aparecen en la salida; el resumen final dice OK o FALLO.
-- =====================================================================

SET NAMES utf8mb4;
USE benefitrack_v2;
SELECT CURRENT_USER() AS conectado_como;

START TRANSACTION;

-- U1: sin @app_id_usuario no se puede rechazar (debe fallar).
SET @app_id_usuario = NULL;
UPDATE postulaciones SET estado = 'Rechazada', justificacion_rechazo = 'Sin usuario de sesión'
 WHERE id_postulacion = 2;
SELECT estado INTO @u1 FROM postulaciones WHERE id_postulacion = 2;

-- U2: sin @app_id_usuario no se puede cambiar un rol (debe fallar).
UPDATE usuarios SET id_rol = 1 WHERE id_usuario = 22;
SELECT id_rol INTO @u2 FROM usuarios WHERE id_usuario = 22;

-- U3: el aprendiz 22 no puede darse el rol de Administrador (debe fallar).
SET @app_id_usuario = 22;
UPDATE usuarios SET id_rol = 1 WHERE id_usuario = 22;
SELECT id_rol INTO @u3 FROM usuarios WHERE id_usuario = 22;

-- U4: el administrador 5 sí puede rechazar (debe funcionar).
SET @app_id_usuario = 5;
UPDATE postulaciones SET estado = 'Rechazada', justificacion_rechazo = 'No fue elegida por el comité'
 WHERE id_postulacion = 2;
SELECT estado INTO @u4 FROM postulaciones WHERE id_postulacion = 2;
SELECT id_usuario INTO @u4h FROM historial_estados WHERE id_postulacion = 2 ORDER BY id_historial DESC LIMIT 1;

-- U5: en el inicio de sesión (sin @app_id_usuario) se bloquea al aprendiz 22
-- y, antes de 15 minutos, no se puede desbloquear (debe fallar).
SET @app_id_usuario = NULL;
UPDATE usuarios SET estado = 'Bloqueado', intentos_fallidos = 3, fecha_bloqueo = NOW() WHERE id_usuario = 22;
UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = 22;
SELECT estado INTO @u5 FROM usuarios WHERE id_usuario = 22;

-- U6: pasados 15 minutos sí se desbloquea solo (debe funcionar).
SET @app_id_usuario = 5;
UPDATE usuarios SET fecha_bloqueo = NOW() - INTERVAL 16 MINUTE WHERE id_usuario = 22;
SET @app_id_usuario = NULL;
UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = 22;
SELECT estado, intentos_fallidos, fecha_bloqueo INTO @u6, @u6i, @u6f FROM usuarios WHERE id_usuario = 22;

-- U7: un usuario Inactivo no se reactiva solo pasando por Bloqueado con una
-- fecha vieja (deben fallar los dos pasos).
SET @app_id_usuario = 5;
SET @app_justificacion = 'Retiro temporal del aprendiz';
UPDATE usuarios SET estado = 'Inactivo' WHERE id_usuario = 22;
SET @app_justificacion = NULL;
SET @app_id_usuario = NULL;
UPDATE usuarios SET estado = 'Bloqueado', fecha_bloqueo = '2000-01-01' WHERE id_usuario = 22;
UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = 22;
SELECT estado INTO @u7 FROM usuarios WHERE id_usuario = 22;

-- U8: el administrador 5 sin justificación no puede reactivar al aprendiz
-- 22 (debe fallar); con justificación sí, y queda la fila en auditoría.
SET @app_id_usuario = 5;
SET @app_justificacion = NULL;
UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = 22;
SELECT estado INTO @u8a FROM usuarios WHERE id_usuario = 22;
SET @app_justificacion = 'Reingreso aprobado por Bienestar';
UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = 22;
SELECT estado INTO @u8b FROM usuarios WHERE id_usuario = 22;
SELECT id_usuario, JSON_UNQUOTE(JSON_EXTRACT(detalle, '$.justificacion'))
  INTO @u8u, @u8j
  FROM auditoria WHERE entidad = 'usuarios' AND id_entidad = 22
 ORDER BY id_auditoria DESC LIMIT 1;
SET @app_justificacion = NULL;

-- U9: benefitrack_app no puede escribir en auditoria directamente (debe
-- fallar con el error 1142); solo escriben los triggers.
SELECT COUNT(*) INTO @u9a FROM auditoria;
INSERT INTO auditoria (id_usuario, accion, entidad, id_entidad) VALUES (5, 'FALSA', 'usuarios', 22);
SELECT COUNT(*) INTO @u9b FROM auditoria;

-- U10: benefitrack_app puede usar pr_cambiar_estado_usuario (EXECUTE); el
-- procedimiento guarda su propia justificación y deja la variable en NULL.
SET @app_id_usuario = 5;
SET @app_justificacion = 'Motivo viejo';
CALL pr_cambiar_estado_usuario(22, 'Inactivo', 'Fin de la etapa productiva');
SELECT estado INTO @u10e FROM usuarios WHERE id_usuario = 22;
SELECT JSON_UNQUOTE(JSON_EXTRACT(detalle, '$.justificacion')) INTO @u10j
  FROM auditoria WHERE entidad = 'usuarios' AND id_entidad = 22 ORDER BY id_auditoria DESC LIMIT 1;
SET @u10v = @app_justificacion;

-- U11 (B1): el aprendiz 21 con sesión no puede bloquear a los demás, ni
-- poniendo intentos_fallidos = 3 (debe fallar).
SET @app_id_usuario = 21;
SET @app_justificacion = NULL;
UPDATE usuarios SET estado = 'Bloqueado', intentos_fallidos = 3 WHERE estado = 'Activo' AND id_usuario <> 21;
SELECT COUNT(*) INTO @u11 FROM usuarios WHERE estado = 'Bloqueado';

-- U12: el aprendiz 21 no puede subir un documento ya Validado (debe fallar)
-- y, si lo sube con datos de validación inventados, la base los borra.
SET @app_id_usuario = 21;
INSERT INTO documentos_postulacion (id_postulacion, id_tipo_documento, nombre_archivo, ruta_archivo,
                                    extension, tamano_mb, estado_validacion, id_usuario_validacion, fecha_validacion)
VALUES (1, 1, 'u12_validado.pdf', 'docs/u12_validado.pdf', 'pdf', 1.00, 'Validado', 5, '2000-01-01');
INSERT INTO documentos_postulacion (id_postulacion, id_tipo_documento, nombre_archivo, ruta_archivo,
                                    extension, tamano_mb, id_usuario_validacion, fecha_validacion, observaciones)
VALUES (1, 1, 'u12_pendiente.pdf', 'docs/u12_pendiente.pdf', 'pdf', 1.00, 5, '2000-01-01', 'Aprobado');
SELECT COUNT(*) INTO @u12a FROM documentos_postulacion WHERE nombre_archivo = 'u12_validado.pdf';
SELECT estado_validacion, id_usuario_validacion, fecha_validacion, observaciones
  INTO @u12e, @u12u, @u12f, @u12o
  FROM documentos_postulacion WHERE nombre_archivo = 'u12_pendiente.pdf';

-- U13 (ronda 10): con benefitrack_app, ni un aprendiz ni una sesión NULL
-- cambian convocatorias, documentos validados, solicitudes o catálogos.
SET @app_id_usuario = 21;
UPDATE convocatorias SET valor_pago = 9999999 WHERE id_convocatoria = 1;
SET @app_id_usuario = NULL;
UPDATE convocatorias SET numero_pagos = 99 WHERE id_convocatoria = 1;
SET @app_id_usuario = 5;
UPDATE documentos_postulacion SET estado_validacion = 'Validado' WHERE nombre_archivo = 'u12_pendiente.pdf';
SET @app_id_usuario = 21;
UPDATE documentos_postulacion SET ruta_archivo = 'docs/otro.pdf' WHERE nombre_archivo = 'u12_pendiente.pdf';
UPDATE documentos_postulacion SET observaciones = 'Aprobado por Ana' WHERE nombre_archivo = 'u12_pendiente.pdf';
INSERT INTO postulaciones (id_usuario, id_convocatoria) VALUES (21, 2);
SET @u13p = LAST_INSERT_ID();
SET @app_id_usuario = 5;
INSERT INTO solicitudes_documentacion (id_postulacion, id_usuario, observacion) VALUES (@u13p, 5, 'U13: adjunte el RUT');
SET @app_id_usuario = 21;
UPDATE solicitudes_documentacion SET id_usuario = 21, fecha_solicitud = '2000-01-01' WHERE observacion = 'U13: adjunte el RUT';
UPDATE solicitudes_documentacion SET estado = 'Atendida', fecha_atencion = '2000-01-02' WHERE observacion = 'U13: adjunte el RUT';
INSERT INTO programas (id_institucion, nombre) VALUES (1, 'U13 programa falso');
SET @app_id_usuario = NULL;
INSERT INTO eps (nombre) VALUES ('U13 EPS falsa');
SELECT valor_pago, numero_pagos INTO @u13v, @u13n FROM convocatorias WHERE id_convocatoria = 1;
SELECT estado_validacion, ruta_archivo, IFNULL(observaciones, 'NULL') INTO @u13e, @u13r, @u13o
  FROM documentos_postulacion WHERE nombre_archivo = 'u12_pendiente.pdf';
SELECT id_usuario, estado, fecha_solicitud > '2001-01-01', fecha_atencion >= NOW() - INTERVAL 1 MINUTE
  INTO @u13su, @u13se, @u13sf, @u13sa
  FROM solicitudes_documentacion WHERE observacion = 'U13: adjunte el RUT';
SELECT (SELECT COUNT(*) FROM programas WHERE nombre = 'U13 programa falso')
     + (SELECT COUNT(*) FROM eps WHERE nombre = 'U13 EPS falsa') INTO @u13c;

ROLLBACK;
SET @app_id_usuario = NULL;

SELECT 'U1' AS caso, 'Con @app_id_usuario NULL no se puede rechazar' AS descripcion,
       IF(@u1 = 'Validada', 'OK', 'FALLO') AS resultado, CONCAT('estado=', IFNULL(@u1, 'NULL')) AS detalle;
SELECT 'U2' AS caso, 'Con @app_id_usuario NULL no se puede cambiar un rol' AS descripcion,
       IF(@u2 = 2, 'OK', 'FALLO') AS resultado, CONCAT('id_rol=', IFNULL(@u2, 'NULL')) AS detalle;
SELECT 'U3' AS caso, 'Un aprendiz no puede darse el rol de Administrador' AS descripcion,
       IF(@u3 = 2, 'OK', 'FALLO') AS resultado, CONCAT('id_rol=', IFNULL(@u3, 'NULL')) AS detalle;
SELECT 'U4' AS caso, 'El administrador 5 sí puede rechazar y queda como autor en el historial' AS descripcion,
       IF(@u4 = 'Rechazada' AND @u4h = 5, 'OK', 'FALLO') AS resultado,
       CONCAT('estado=', IFNULL(@u4, 'NULL'), ' historial.id_usuario=', IFNULL(@u4h, 'NULL')) AS detalle;
SELECT 'U5' AS caso, 'Sin administrador no se desbloquea antes de 15 minutos' AS descripcion,
       IF(@u5 = 'Bloqueado', 'OK', 'FALLO') AS resultado, CONCAT('estado=', IFNULL(@u5, 'NULL')) AS detalle;
SELECT 'U6' AS caso, 'Pasados 15 minutos se desbloquea solo (intentos 0, fecha_bloqueo NULL)' AS descripcion,
       IF(@u6 = 'Activo' AND @u6i = 0 AND @u6f IS NULL, 'OK', 'FALLO') AS resultado,
       CONCAT('estado=', IFNULL(@u6, 'NULL'), ' intentos=', IFNULL(@u6i, 'NULL'),
              ' fecha_bloqueo=', IFNULL(@u6f, 'NULL')) AS detalle;
SELECT 'U7' AS caso, 'Un Inactivo no se reactiva solo pasando por Bloqueado' AS descripcion,
       IF(@u7 = 'Inactivo', 'OK', 'FALLO') AS resultado, CONCAT('estado=', IFNULL(@u7, 'NULL')) AS detalle;
SELECT 'U8' AS caso, 'Sin justificación se rechaza; con ella el cambio queda en auditoría (admin 5)' AS descripcion,
       IF(@u8a = 'Inactivo' AND @u8b = 'Activo' AND @u8u = 5 AND @u8j = 'Reingreso aprobado por Bienestar',
          'OK', 'FALLO') AS resultado,
       CONCAT('sin_justificacion=', IFNULL(@u8a, 'NULL'), ' con_justificacion=', IFNULL(@u8b, 'NULL'),
              ' auditoria.id_usuario=', IFNULL(@u8u, 'NULL'), ' justificacion=', IFNULL(@u8j, 'NULL')) AS detalle;
SELECT 'U9' AS caso, 'benefitrack_app no puede insertar en auditoria directamente' AS descripcion,
       IF(@u9a = @u9b, 'OK', 'FALLO') AS resultado,
       CONCAT('filas antes=', IFNULL(@u9a, 'NULL'), ' después=', IFNULL(@u9b, 'NULL')) AS detalle;
SELECT 'U10' AS caso, 'pr_cambiar_estado_usuario funciona con benefitrack_app y no deja la justificación puesta' AS descripcion,
       IF(@u10e = 'Inactivo' AND @u10j = 'Fin de la etapa productiva' AND @u10v IS NULL, 'OK', 'FALLO') AS resultado,
       CONCAT('estado=', IFNULL(@u10e, 'NULL'), ' justificacion=', IFNULL(@u10j, 'NULL'),
              ' variable después=', IFNULL(@u10v, 'NULL')) AS detalle;
SELECT 'U11' AS caso, 'Un aprendiz con sesión no puede bloquear a los demás (ni con intentos = 3)' AS descripcion,
       IF(@u11 = 0, 'OK', 'FALLO') AS resultado, CONCAT('bloqueados=', IFNULL(@u11, 'NULL')) AS detalle;
SELECT 'U12' AS caso, 'Un aprendiz no sube documentos ya validados ni con datos de validación inventados' AS descripcion,
       IF(@u12a = 0 AND @u12e = 'Pendiente' AND @u12u IS NULL AND @u12f IS NULL AND @u12o IS NULL,
          'OK', 'FALLO') AS resultado,
       CONCAT('validados=', IFNULL(@u12a, 'NULL'), ' estado=', IFNULL(@u12e, 'NULL'),
              ' validador=', IFNULL(@u12u, 'NULL'), ' fecha=', IFNULL(@u12f, 'NULL'),
              ' obs=', IFNULL(@u12o, 'NULL')) AS detalle;
SELECT 'U13' AS caso, 'Ni un aprendiz ni una sesión NULL cambian convocatorias, documentos validados, solicitudes o catálogos' AS descripcion,
       IF(@u13v = 100000 AND @u13n = 2 AND @u13e = 'Validado' AND @u13r = 'docs/u12_pendiente.pdf'
          AND @u13o = 'NULL' AND @u13su = 5 AND @u13se = 'Atendida' AND @u13sf = 1 AND @u13sa = 1 AND @u13c = 0,
          'OK', 'FALLO') AS resultado,
       CONCAT('valor_pago=', IFNULL(@u13v, 'NULL'), ' numero_pagos=', IFNULL(@u13n, 'NULL'),
              ' doc=', IFNULL(@u13e, 'NULL'), '/', IFNULL(@u13r, 'NULL'), '/', IFNULL(@u13o, 'NULL'),
              ' solicitud=', IFNULL(@u13su, 'NULL'), '/', IFNULL(@u13se, 'NULL'), '/', IFNULL(@u13sf, 'NULL'),
              '/', IFNULL(@u13sa, 'NULL'), ' catalogos_falsos=', IFNULL(@u13c, 'NULL')) AS detalle;
