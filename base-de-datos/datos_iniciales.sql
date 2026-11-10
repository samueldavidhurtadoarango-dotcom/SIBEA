-- =====================================================================
-- BenefitRack - Datos iniciales (catálogos)
-- =====================================================================
-- Qué es: los datos que la aplicación necesita para empezar a usarse:
-- los beneficios, los bancos y los programas de formación. Sin ellos no se
-- puede crear una convocatoria ni llenar el formulario de postulación.
--
-- Cuándo se ejecuta: DESPUÉS de benefitrack_v2.sql y ANTES de
-- usuario_aplicacion.sql, conectado como root (ver README.md).
--
-- Se puede ejecutar varias veces: cada fila usa
-- INSERT ... ON DUPLICATE KEY UPDATE sobre el nombre (que es único), así que
-- repetir el script no duplica nada; solo deja cada fila como está aquí.
-- (No borra lo que usted haya agregado por su cuenta.)
--
-- Los programas son ejemplos del Centro Textil: corríjalos si hace falta.
-- =====================================================================

SET NAMES utf8mb4;
USE benefitrack_v2;

-- Los catálogos exigen un administrador activo o root sin usuario de sesión.
-- Este script lo ejecuta root, así que se deja la variable en NULL.
SET @app_id_usuario = NULL;

-- Beneficios (id_institucion 1 = Centro Textil; la categoría es el mismo nombre).
INSERT INTO beneficios (id_institucion, nombre, descripcion, categoria) VALUES
  (1, 'Alimentación',  'Apoyo económico para la alimentación del aprendiz.',          'Alimentación'),
  (1, 'Plan de datos', 'Apoyo para pagar un plan de datos móviles o de internet.',    'Plan de datos'),
  (1, 'Cívica',        'Apoyo para los aprendices que participan en actividades cívicas.', 'Cívica')
ON DUPLICATE KEY UPDATE
  descripcion = VALUES(descripcion),
  categoria   = VALUES(categoria);

-- Bancos y billeteras donde el aprendiz recibe los pagos.
INSERT INTO entidades_bancarias (nombre) VALUES
  ('Bancolombia'),
  ('Banco de Bogotá'),
  ('Davivienda'),
  ('BBVA Colombia'),
  ('Banco de Occidente'),
  ('Banco Popular'),
  ('Banco Caja Social'),
  ('Banco AV Villas'),
  ('Banco Agrario'),
  ('Nequi'),
  ('Daviplata')
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre);

-- Programas de formación de ejemplo del Centro Textil.
INSERT INTO programas (id_institucion, nombre) VALUES
  (1, 'Análisis y Desarrollo de Software'),
  (1, 'Confección Industrial'),
  (1, 'Gestión de la Producción Industrial'),
  (1, 'Diseño de Modas'),
  (1, 'Gestión Administrativa')
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre);
