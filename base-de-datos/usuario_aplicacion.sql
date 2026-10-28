-- =====================================================================
-- BenefitRack v2 - Usuario de MariaDB para la aplicación (ejemplo)
-- =====================================================================
-- Ejecutar como root DESPUÉS de benefitrack_v2.sql.
--
-- La aplicación NO debe conectarse como root. Este usuario puede leer
-- todo, pero NO puede:
--   * insertar, modificar ni borrar en historial_estados ni auditoria
--     (solo los triggers escriben ahí);
--   * insertar ni borrar asignaciones_beneficio (las crea el trigger al
--     aprobar; la aplicación solo cambia su estado);
--   * borrar postulaciones, pagos, novedades ni notificaciones.
-- Los triggers siguen funcionando porque se ejecutan con los permisos de
-- quien los creó (root), no con los de este usuario.
--
-- CAMBIE la contraseña antes de usarlo y guárdela en el archivo .env de la
-- aplicación, nunca en el repositorio.
--
-- 'localhost' frente a '127.0.0.1': en el XAMPP de Windows, conectarse con
-- -h 127.0.0.1 (o DB_HOST=127.0.0.1) se reconoce como 'localhost' (se probó:
-- CURRENT_USER() devuelve benefitrack_app@localhost). Si su servidor usa
-- skip-name-resolve o la aplicación se conecta desde otra máquina, cree el
-- mismo usuario también para ese host (por ejemplo 'benefitrack_app'@'127.0.0.1')
-- y repita los GRANT para él.
--
-- @app_id_usuario: con CUALQUIER usuario de conexión distinto de root
-- (benefitrack_app o el que usted cree), las acciones de gestión exigen que
-- la aplicación fije siempre SET @app_id_usuario = <id del administrador>.
-- Si la variable está en NULL, la base rechaza la acción. Solo root (el
-- usuario de mantenimiento) puede actuar con la variable en NULL.
--
-- La única excepción es el flujo de inicio de sesión: debe ejecutarse con
-- SET @app_id_usuario = NULL (todavía no hay usuario de sesión); solo así
-- el bloqueo tras 3 intentos y el desbloqueo a los 15 minutos son
-- automáticos.
--
-- @app_justificacion: todo cambio de rol o de estado de un usuario que no
-- sea automático exige un motivo. Vía recomendada (este usuario tiene
-- EXECUTE sobre ellos, ver más abajo):
--   CALL pr_cambiar_estado_usuario(<id>, 'Inactivo', '<motivo>');
--   CALL pr_cambiar_rol_usuario(<id>, 'Administrador', '<motivo>');
-- Fijan el motivo solo para ese cambio y lo limpian aunque falle. Si se
-- hace a mano (SET @app_justificacion = '<motivo>'; UPDATE ...), vuelva a
-- poner la variable en NULL después, para no arrastrar un motivo viejo.
-- El motivo queda en auditoria, que escriben solo los triggers: este
-- usuario NO tiene INSERT sobre auditoria.
-- =====================================================================

CREATE USER IF NOT EXISTS 'benefitrack_app'@'localhost' IDENTIFIED BY 'CAMBIE_ESTA_CLAVE';

-- Lectura de toda la base.
GRANT SELECT ON benefitrack_v2.* TO 'benefitrack_app'@'localhost';

-- Catálogos que administra el Administrador (roles queda solo lectura).
GRANT INSERT, UPDATE ON benefitrack_v2.instituciones       TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE ON benefitrack_v2.tipos_cuenta        TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE ON benefitrack_v2.tipos_documento     TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE ON benefitrack_v2.entidades_bancarias TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE ON benefitrack_v2.eps                 TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE ON benefitrack_v2.programas           TO 'benefitrack_app'@'localhost';

-- Usuarios y acceso.
GRANT INSERT, UPDATE         ON benefitrack_v2.usuarios                TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON benefitrack_v2.sesiones                TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE         ON benefitrack_v2.recuperacion_contrasena TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE         ON benefitrack_v2.informacion_bancaria    TO 'benefitrack_app'@'localhost';

-- Beneficios, convocatorias y postulaciones.
GRANT INSERT, UPDATE ON benefitrack_v2.beneficios                TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE ON benefitrack_v2.convocatorias             TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE ON benefitrack_v2.postulaciones             TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE ON benefitrack_v2.documentos_postulacion    TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE ON benefitrack_v2.solicitudes_documentacion TO 'benefitrack_app'@'localhost';

-- Beneficiarios, pagos y novedades.
GRANT UPDATE         ON benefitrack_v2.asignaciones_beneficio TO 'benefitrack_app'@'localhost';
GRANT INSERT, UPDATE ON benefitrack_v2.pagos                  TO 'benefitrack_app'@'localhost';
GRANT INSERT         ON benefitrack_v2.novedades              TO 'benefitrack_app'@'localhost';

-- Notificaciones: marcar como leída y actualizar el estado del correo.
GRANT UPDATE ON benefitrack_v2.notificaciones TO 'benefitrack_app'@'localhost';

-- Migraciones desde Excel.
GRANT INSERT, UPDATE ON benefitrack_v2.migraciones TO 'benefitrack_app'@'localhost';

-- Vía recomendada para cambiar el estado o el rol de un usuario: los
-- procedimientos fijan la justificación solo para ese cambio y la limpian.
GRANT EXECUTE ON PROCEDURE benefitrack_v2.pr_cambiar_estado_usuario TO 'benefitrack_app'@'localhost';
GRANT EXECUTE ON PROCEDURE benefitrack_v2.pr_cambiar_rol_usuario    TO 'benefitrack_app'@'localhost';

-- historial_estados y auditoria: solo SELECT (ya concedido arriba).

FLUSH PRIVILEGES;
