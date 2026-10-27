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

-- historial_estados y auditoria: solo SELECT (ya concedido arriba).

FLUSH PRIVILEGES;
