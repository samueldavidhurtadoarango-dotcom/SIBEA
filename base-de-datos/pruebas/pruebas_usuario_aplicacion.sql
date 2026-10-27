-- =====================================================================
-- BenefitRack - Pruebas con el usuario limitado de la aplicación
-- =====================================================================
-- Comprueba que, conectado como benefitrack_app:
--   * con @app_id_usuario = NULL no se pueden hacer acciones de gestión;
--   * un aprendiz no puede darse el rol de Administrador;
--   * el administrador 5 sí puede gestionar.
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
