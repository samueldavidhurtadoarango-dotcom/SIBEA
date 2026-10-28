-- =====================================================================
-- BenefitRack - Pruebas de las reglas de negocio en la base de datos
-- =====================================================================
-- Cubre los criterios de aceptación de specs/001-correccion-entregables.md
-- y las decisiones D1-D12 que se fuerzan con triggers y restricciones.
--
-- Cómo funciona:
--   * Ejecutar DESPUÉS de benefitrack_v2.sql, sobre la base recién creada.
--   * Todos los datos de prueba se crean dentro de una transacción que se
--     deshace (ROLLBACK) al final: la base queda igual que antes.
--   * Cada caso guarda OK o FALLO en una tabla temporal MEMORY (que no se
--     deshace con el ROLLBACK) y al final se imprime el resumen.
--   * Un caso "debe fallar" solo es OK si la base lanza un error y el
--     mensaje contiene el texto esperado (así se comprueba que falla por
--     la razón correcta y no por otra).
-- =====================================================================

SET NAMES utf8mb4;
USE benefitrack_v2;

-- ---------------------------------------------------------------------
-- Utilidades de prueba
-- ---------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS resultados_pruebas;
CREATE TEMPORARY TABLE resultados_pruebas (
  orden       INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  caso        VARCHAR(10)  NOT NULL,
  descripcion VARCHAR(200) NOT NULL,
  resultado   VARCHAR(5)   NOT NULL,
  detalle     VARCHAR(300) NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS pr_esperar_error;
DROP PROCEDURE IF EXISTS pr_esperar_exito;
DROP PROCEDURE IF EXISTS pr_verificar;

DELIMITER //

-- Ejecuta p_sql y espera que falle con un mensaje que contenga p_mensaje.
CREATE PROCEDURE pr_esperar_error(IN p_caso VARCHAR(10), IN p_desc VARCHAR(200),
                                  IN p_sql TEXT, IN p_mensaje VARCHAR(200))
BEGIN
  DECLARE v_error TEXT DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    GET DIAGNOSTICS CONDITION 1 v_error = MESSAGE_TEXT;

  SET @pr_sql = p_sql;
  PREPARE st FROM @pr_sql;
  EXECUTE st;
  DEALLOCATE PREPARE st;

  INSERT INTO resultados_pruebas (caso, descripcion, resultado, detalle)
  VALUES (p_caso, p_desc,
          IF(v_error IS NOT NULL AND v_error LIKE CONCAT('%', p_mensaje, '%'), 'OK', 'FALLO'),
          LEFT(COALESCE(CONCAT('Error: ', v_error), 'No se produjo ningún error (se esperaba que fallara)'), 300));
END//

-- Ejecuta p_sql y espera que funcione sin errores.
CREATE PROCEDURE pr_esperar_exito(IN p_caso VARCHAR(10), IN p_desc VARCHAR(200), IN p_sql TEXT)
BEGIN
  DECLARE v_error TEXT DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    GET DIAGNOSTICS CONDITION 1 v_error = MESSAGE_TEXT;

  SET @pr_sql = p_sql;
  PREPARE st FROM @pr_sql;
  EXECUTE st;
  DEALLOCATE PREPARE st;

  INSERT INTO resultados_pruebas (caso, descripcion, resultado, detalle)
  VALUES (p_caso, p_desc, IF(v_error IS NULL, 'OK', 'FALLO'),
          LEFT(COALESCE(CONCAT('Error inesperado: ', v_error), 'Sin errores'), 300));
END//

-- Registra OK si la condición es verdadera.
CREATE PROCEDURE pr_verificar(IN p_caso VARCHAR(10), IN p_desc VARCHAR(200),
                              IN p_condicion INT, IN p_detalle VARCHAR(300))
BEGIN
  INSERT INTO resultados_pruebas (caso, descripcion, resultado, detalle)
  VALUES (p_caso, p_desc, IF(IFNULL(p_condicion, 0) = 1, 'OK', 'FALLO'), LEFT(p_detalle, 300));
END//

DELIMITER ;

-- =====================================================================
-- Bloque A. Estructura del script (CA1, D7, D10, D11, D12)
-- =====================================================================
CALL pr_verificar('A01', 'CA1: existen las 23 tablas esperadas',
  (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
      AND table_name IN ('instituciones','roles','tipos_cuenta','tipos_documento','entidades_bancarias',
                         'eps','programas','usuarios','sesiones','recuperacion_contrasena','beneficios',
                         'convocatorias','postulaciones','documentos_postulacion','solicitudes_documentacion',
                         'informacion_bancaria','historial_estados','asignaciones_beneficio','pagos',
                         'novedades','notificaciones','auditoria','migraciones')) = 23,
  CONCAT('Tablas encontradas: ', (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE())));

CALL pr_verificar('A02', 'CA1: existen los 28 triggers esperados',
  (SELECT COUNT(*) FROM information_schema.triggers
    WHERE trigger_schema = DATABASE()
      AND trigger_name IN ('tr_validacion_documento','tr_postulacion_inicial','tr_postulacion_creada',
                           'tr_postulacion_validar','tr_postulacion_estado','tr_auditoria_postulacion',
                           'tr_postulacion_notificar','tr_postulacion_crear_asignacion',
                           'tr_solicitud_documentacion_validar','tr_asignacion_validar_insercion',
                           'tr_asignacion_validar_estado','tr_asignacion_cambio_estado','tr_pago_validar',
                           'tr_pago_registrado','tr_pago_inmutable','tr_pago_anulado','tr_pago_no_eliminar',
                           'tr_novedad_preparar','tr_novedad_registrada',
                           'tr_convocatoria_validar','tr_postulacion_no_eliminar','tr_asignacion_no_eliminar',
                           'tr_historial_no_modificar','tr_historial_no_eliminar',
                           'tr_auditoria_no_modificar','tr_auditoria_no_eliminar',
                           'tr_usuario_validar','tr_usuario_validar_insercion')) = 28,
  CONCAT('Triggers encontrados: ', (SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE())));

CALL pr_verificar('A03', 'D7: roles semilla son exactamente Administrador y Aprendiz',
  (SELECT COUNT(*) FROM roles WHERE nombre IN ('Administrador','Aprendiz')) = 2
  AND (SELECT COUNT(*) FROM roles) = 2,
  (SELECT GROUP_CONCAT(nombre ORDER BY id_rol) FROM roles));

CALL pr_verificar('A04', 'D10: existe la institución SENA Centro Textil y de Gestión Industrial',
  (SELECT COUNT(*) FROM instituciones WHERE nombre LIKE '%Centro Textil y de Gesti%n Industrial%') = 1,
  (SELECT GROUP_CONCAT(nombre) FROM instituciones));

CALL pr_verificar('A05', 'D10: beneficios y programas referencian instituciones (FK)',
  (SELECT COUNT(*) FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE() AND referenced_table_name = 'instituciones'
      AND table_name IN ('beneficios','programas')) = 2,
  'Se buscan FK beneficios.id_institucion y programas.id_institucion');

CALL pr_verificar('A06', 'D11: usuarios tiene fecha_nacimiento y ya no tiene edad',
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'fecha_nacimiento') = 1
  AND (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'edad') = 0,
  'usuarios.fecha_nacimiento / usuarios.edad');

CALL pr_verificar('A07', 'D6: documentos_postulacion ya no tiene campos de OCR',
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND column_name IN ('resultado_ocr','datos_extraidos')) = 0,
  'resultado_ocr / datos_extraidos');

CALL pr_verificar('A08', 'D1: postulaciones.estado solo admite los 4 estados y empieza en Pendiente',
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'postulaciones' AND column_name = 'estado'
      AND column_type = 'enum(''Pendiente'',''Validada'',''Aprobada'',''Rechazada'')'
      AND column_default IN ('Pendiente', '''Pendiente''')) = 1,
  (SELECT CONCAT(column_type, ' default ', column_default) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'postulaciones' AND column_name = 'estado'));

CALL pr_verificar('A09', 'D12: todas las tablas usan utf8mb4_unicode_ci',
  (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
      AND table_collation <> 'utf8mb4_unicode_ci') = 0
  AND (SELECT default_collation_name FROM information_schema.schemata
        WHERE schema_name = DATABASE()) = 'utf8mb4_unicode_ci',
  (SELECT CONCAT('Tablas con otra intercalación: ', COUNT(*)) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_collation <> 'utf8mb4_unicode_ci'));

CALL pr_verificar('A10', 'Datos semilla: tipos de cuenta, tipos de documento y EPS',
  (SELECT COUNT(*) FROM tipos_cuenta) >= 2 AND (SELECT COUNT(*) FROM tipos_documento) >= 3
  AND (SELECT COUNT(*) FROM eps) >= 3,
  CONCAT('tipos_cuenta=', (SELECT COUNT(*) FROM tipos_cuenta), ' tipos_documento=',
         (SELECT COUNT(*) FROM tipos_documento), ' eps=', (SELECT COUNT(*) FROM eps)));

CALL pr_verificar('A11', 'D4: pagos pertenece a la asignación y ya no tiene id_beneficio ni id_postulacion',
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'pagos'
      AND column_name IN ('id_beneficio','id_postulacion')) = 0
  AND (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'pagos' AND column_name = 'id_asignacion') = 1,
  'pagos.id_asignacion');

CALL pr_verificar('A12', 'D7: ningún dato semilla menciona "Estudiante"',
  (SELECT COUNT(*) FROM roles WHERE nombre LIKE '%studiante%' OR descripcion LIKE '%studiante%') = 0
  AND (SELECT COUNT(*) FROM tipos_documento WHERE nombre LIKE '%studiante%' OR descripcion LIKE '%studiante%') = 0,
  'roles / tipos_documento');

CALL pr_verificar('A13', 'Semilla: tipos de documento 1-3 sin cambios y 4 = Certificado de EPS',
  (SELECT COUNT(*) FROM tipos_documento) = 4
  AND (SELECT nombre FROM tipos_documento WHERE id_tipo_documento = 1) = 'Documento de identidad'
  AND (SELECT nombre FROM tipos_documento WHERE id_tipo_documento = 2) = 'Certificado bancario'
  AND (SELECT nombre FROM tipos_documento WHERE id_tipo_documento = 3) = 'Documento adicional'
  AND (SELECT nombre FROM tipos_documento WHERE id_tipo_documento = 4) = 'Certificado de EPS'
  AND (SELECT descripcion FROM tipos_documento WHERE id_tipo_documento = 4)
      = 'Certificado de afiliación a la EPS del aprendiz',
  (SELECT CONCAT('tipos_documento: ', GROUP_CONCAT(CONCAT(id_tipo_documento, '=', nombre) ORDER BY id_tipo_documento SEPARATOR ', '))
     FROM tipos_documento));

-- =====================================================================
-- Datos de prueba (dentro de una transacción que se deshace al final)
-- =====================================================================
START TRANSACTION;

SET @app_id_usuario = NULL;

-- Fechas relativas a hoy: las pruebas siguen siendo válidas cualquier día
-- (la convocatoria debe estar abierta hoy y los pagos no pueden ser futuros
-- ni anteriores al inicio de la asignación).
SET @f_inicio_asig = CURDATE() - INTERVAL 120 DAY;  -- inicio de las asignaciones de prueba
SET @f_pago1       = CURDATE() - INTERVAL 90 DAY;
SET @f_prog2       = CURDATE() - INTERVAL 60 DAY;   -- fecha programada del pago 2
SET @f_pago2       = CURDATE() - INTERVAL 51 DAY;   -- pago 2 con 9 días de retraso
SET @f_pago3       = CURDATE() - INTERVAL 30 DAY;

SELECT id_rol INTO @rol_admin   FROM roles WHERE nombre = 'Administrador';
SELECT id_rol INTO @rol_aprendiz FROM roles WHERE nombre = 'Aprendiz';
SELECT id_institucion INTO @inst FROM instituciones ORDER BY id_institucion LIMIT 1;

-- Administrador con id 5 (criterio: "cambio hecho con @app_id_usuario = 5").
INSERT INTO usuarios (id_usuario, documento, nombre, apellido, fecha_nacimiento, correo, password_hash, id_rol)
VALUES (5, 'PRUEBA-ADM', 'Ana', 'Administradora', '1990-01-01', 'admin.pruebas@benefitrack.test', 'hash', @rol_admin);

INSERT INTO programas (id_institucion, nombre) VALUES (@inst, 'ADSO - pruebas');
SET @prog = LAST_INSERT_ID();

INSERT INTO usuarios (documento, nombre, apellido, fecha_nacimiento, correo, password_hash, id_rol, id_programa, ficha)
VALUES ('PRUEBA-A1', 'Aprendiz', 'Uno',    '2005-01-01', 'a1@benefitrack.test', 'hash', @rol_aprendiz, @prog, '2900001');
SET @a1 = LAST_INSERT_ID();
INSERT INTO usuarios (documento, nombre, apellido, fecha_nacimiento, correo, password_hash, id_rol, id_programa, ficha)
VALUES ('PRUEBA-A2', 'Aprendiz', 'Dos',    '2005-01-01', 'a2@benefitrack.test', 'hash', @rol_aprendiz, @prog, '2900001');
SET @a2 = LAST_INSERT_ID();
INSERT INTO usuarios (documento, nombre, apellido, fecha_nacimiento, correo, password_hash, id_rol, id_programa, ficha)
VALUES ('PRUEBA-A3', 'Aprendiz', 'Tres',   '2005-01-01', 'a3@benefitrack.test', 'hash', @rol_aprendiz, @prog, '2900001');
SET @a3 = LAST_INSERT_ID();
INSERT INTO usuarios (documento, nombre, apellido, fecha_nacimiento, correo, password_hash, id_rol, id_programa, ficha)
VALUES ('PRUEBA-A4', 'Aprendiz', 'Cuatro', '2005-01-01', 'a4@benefitrack.test', 'hash', @rol_aprendiz, @prog, '2900001');
SET @a4 = LAST_INSERT_ID();
INSERT INTO usuarios (documento, nombre, apellido, fecha_nacimiento, correo, password_hash, id_rol, id_programa, ficha)
VALUES ('PRUEBA-A5', 'Aprendiz', 'Cinco',  '2005-01-01', 'a5@benefitrack.test', 'hash', @rol_aprendiz, @prog, '2900001');
SET @a5 = LAST_INSERT_ID();

INSERT INTO beneficios (id_institucion, nombre, descripcion, categoria)
VALUES (@inst, 'Apoyo de alimentación - pruebas', 'Beneficio de prueba', 'Alimentación');
SET @ben = LAST_INSERT_ID();

-- Convocatoria con 2 cupos y 3 pagos de 150.000 durante 3 meses.
INSERT INTO convocatorias (id_beneficio, nombre, fecha_inicio, fecha_cierre, cupos, requisitos,
                           valor_pago, numero_pagos, duracion_meses, estado)
VALUES (@ben, 'Convocatoria de pruebas', CURDATE() - INTERVAL 150 DAY, CURDATE() + INTERVAL 30 DAY, 2, 'Ser aprendiz activo',
        150000, 3, 3, 'Activa');
SET @conv = LAST_INSERT_ID();

INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (@a1, @conv, '{"estrato": 1}');
SET @p1 = LAST_INSERT_ID();
INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (@a2, @conv, '{"estrato": 2}');
SET @p2 = LAST_INSERT_ID();
INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (@a3, @conv, '{"estrato": 1}');
SET @p3 = LAST_INSERT_ID();
INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (@a4, @conv, '{"estrato": 3}');
SET @p4 = LAST_INSERT_ID();
INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (@a5, @conv, '{"estrato": 2}');
SET @p5 = LAST_INSERT_ID();

-- =====================================================================
-- Bloque B. Estados de la postulación y trazabilidad (D1, D9, CA2, CA7)
-- =====================================================================
CALL pr_verificar('B01', 'D1: una postulación nueva queda en Pendiente',
  (SELECT estado FROM postulaciones WHERE id_postulacion = @p1) = 'Pendiente',
  (SELECT estado FROM postulaciones WHERE id_postulacion = @p1));

CALL pr_esperar_error('B02', 'D1: no se puede crear una postulación directamente en Validada',
  "INSERT INTO postulaciones (id_usuario, id_convocatoria, estado) VALUES (5, @conv, 'Validada')",
  'debe iniciar en estado Pendiente');

CALL pr_verificar('B03', 'D9: la creación de la postulación queda en el historial (NULL -> Pendiente)',
  (SELECT COUNT(*) FROM historial_estados
    WHERE id_postulacion = @p1 AND estado_anterior IS NULL AND estado_nuevo = 'Pendiente') = 1,
  (SELECT CONCAT('Filas: ', COUNT(*)) FROM historial_estados WHERE id_postulacion = @p1));

SET @app_id_usuario = 5;

CALL pr_esperar_error('B04', 'CA2: Pendiente -> Aprobada directamente se rechaza',
  "UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = @p1",
  'Transición de estado no permitida');

CALL pr_esperar_exito('B05', 'D1: Pendiente -> Validada se acepta',
  "UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion = @p1");

CALL pr_verificar('B06', 'CA7: el historial guarda el usuario 5 (@app_id_usuario), no un id fijo ni el aprendiz',
  (SELECT id_usuario FROM historial_estados
    WHERE id_postulacion = @p1 AND estado_nuevo = 'Validada' ORDER BY id_historial DESC LIMIT 1) = 5,
  (SELECT CONCAT('id_usuario=', IFNULL(id_usuario, 'NULL')) FROM historial_estados
    WHERE id_postulacion = @p1 AND estado_nuevo = 'Validada' ORDER BY id_historial DESC LIMIT 1));

CALL pr_verificar('B07', 'CA7: la auditoría guarda el usuario 5 (@app_id_usuario), no el aprendiz dueño',
  (SELECT id_usuario FROM auditoria
    WHERE entidad = 'postulaciones' AND id_entidad = @p1
      AND JSON_VALUE(detalle, '$.estado_nuevo') = 'Validada'
    ORDER BY id_auditoria DESC LIMIT 1) = 5,
  (SELECT CONCAT('id_usuario=', IFNULL(id_usuario, 'NULL'), ' detalle=', IFNULL(detalle, 'NULL'))
     FROM auditoria WHERE entidad = 'postulaciones' AND id_entidad = @p1
    ORDER BY id_auditoria DESC LIMIT 1));

CALL pr_verificar('B08', 'D9: al validar se registra quién validó y cuándo',
  (SELECT id_usuario_validacion = 5 AND fecha_validacion IS NOT NULL
     FROM postulaciones WHERE id_postulacion = @p1),
  (SELECT CONCAT('id_usuario_validacion=', IFNULL(id_usuario_validacion, 'NULL'))
     FROM postulaciones WHERE id_postulacion = @p1));

CALL pr_esperar_error('B09', 'D1: Validada -> Pendiente se rechaza',
  "UPDATE postulaciones SET estado = 'Pendiente' WHERE id_postulacion = @p1",
  'Transición de estado no permitida');

CALL pr_esperar_error('B10', 'D1: rechazar sin justificación se rechaza',
  "UPDATE postulaciones SET estado = 'Rechazada' WHERE id_postulacion = @p2",
  'justificación es obligatoria');

CALL pr_esperar_error('B11', 'D1: rechazar con justificación en blanco se rechaza',
  "UPDATE postulaciones SET estado = 'Rechazada', justificacion_rechazo = '   ' WHERE id_postulacion = @p2",
  'justificación es obligatoria');

CALL pr_esperar_exito('B12', 'D1: Pendiente -> Rechazada con justificación se acepta',
  "UPDATE postulaciones SET estado = 'Rechazada', justificacion_rechazo = 'El certificado bancario no corresponde al aprendiz' WHERE id_postulacion = @p2");

CALL pr_verificar('B13', 'D1/D9: el historial del rechazo guarda la justificación',
  (SELECT justificacion FROM historial_estados
    WHERE id_postulacion = @p2 AND estado_nuevo = 'Rechazada' LIMIT 1)
    = 'El certificado bancario no corresponde al aprendiz',
  (SELECT IFNULL(justificacion, 'NULL') FROM historial_estados
    WHERE id_postulacion = @p2 AND estado_nuevo = 'Rechazada' LIMIT 1));

CALL pr_esperar_error('B14', 'D1: Rechazada es final (Rechazada -> Validada se rechaza)',
  "UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion = @p2",
  'Transición de estado no permitida');

SET @app_id_usuario = NULL;
CALL pr_esperar_exito('B15', 'D9: cambio sin @app_id_usuario definido se acepta',
  "UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion = @p3");
CALL pr_verificar('B16', 'D9: sin @app_id_usuario el historial y la auditoría guardan NULL',
  (SELECT id_usuario IS NULL FROM historial_estados
    WHERE id_postulacion = @p3 AND estado_nuevo = 'Validada' LIMIT 1)
  AND (SELECT id_usuario IS NULL FROM auditoria
    WHERE entidad = 'postulaciones' AND id_entidad = @p3
      AND JSON_VALUE(detalle, '$.estado_nuevo') = 'Validada' LIMIT 1),
  'historial_estados.id_usuario / auditoria.id_usuario');
SET @app_id_usuario = 5;

CALL pr_esperar_error('B17', 'D1: solo se pide documentación adicional a una postulación Pendiente',
  "INSERT INTO solicitudes_documentacion (id_postulacion, id_usuario, observacion) VALUES (@p1, 5, 'Falta certificado')",
  'postulación Pendiente');

CALL pr_esperar_exito('B18', 'D1: pedir documentación a una postulación Pendiente se acepta',
  "INSERT INTO solicitudes_documentacion (id_postulacion, id_usuario, observacion) VALUES (@p4, 5, 'Adjunte certificado bancario')");

CALL pr_verificar('B19', 'D1: tras pedir documentación la postulación sigue en Pendiente',
  (SELECT estado FROM postulaciones WHERE id_postulacion = @p4) = 'Pendiente',
  (SELECT estado FROM postulaciones WHERE id_postulacion = @p4));

CALL pr_esperar_error('B20', 'D2: no se puede mover una postulación a otra convocatoria (saltaría los cupos)',
  "UPDATE postulaciones SET id_convocatoria = id_convocatoria + 1000 WHERE id_postulacion = @p4",
  'No se puede cambiar el aprendiz ni la convocatoria');

-- =====================================================================
-- Bloque C. Cupos y asignación del beneficio (D2, D3, CA3)
-- =====================================================================
UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion IN (@p4, @p5);

CALL pr_esperar_exito('C01', 'D2: Validada -> Aprobada se acepta (cupo 1 de 2)',
  "UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = @p1");

CALL pr_verificar('C02', 'D2: se registra quién aprobó y cuándo',
  (SELECT id_usuario_aprobacion = 5 AND fecha_aprobacion IS NOT NULL
     FROM postulaciones WHERE id_postulacion = @p1),
  (SELECT CONCAT('id_usuario_aprobacion=', IFNULL(id_usuario_aprobacion, 'NULL'))
     FROM postulaciones WHERE id_postulacion = @p1));

SELECT id_asignacion INTO @asig1 FROM asignaciones_beneficio WHERE id_postulacion = @p1;

CALL pr_verificar('C03', 'D3: al aprobar se crea la asignación copiando los valores de la convocatoria',
  (SELECT COUNT(*) FROM asignaciones_beneficio
    WHERE id_postulacion = @p1 AND valor_pago = 150000 AND numero_pagos = 3
      AND duracion_meses = 3 AND estado = 'En curso' AND fecha_inicio IS NOT NULL) = 1,
  (SELECT CONCAT('Asignaciones de la postulación: ', COUNT(*)) FROM asignaciones_beneficio WHERE id_postulacion = @p1));

CALL pr_esperar_exito('C04', 'D2: segunda aprobación se acepta (cupo 2 de 2)',
  "UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = @p3");

SELECT id_asignacion INTO @asig3 FROM asignaciones_beneficio WHERE id_postulacion = @p3;

-- Preparación: mover el inicio de las dos asignaciones al pasado para poder
-- registrar pagos con fechas anteriores a hoy (se permite porque aún no hay pagos).
CALL pr_esperar_exito('C04a', 'Preparación: fecha_inicio de las asignaciones en el pasado (sin pagos)',
  "UPDATE asignaciones_beneficio SET fecha_inicio = @f_inicio_asig WHERE id_asignacion IN (@asig1, @asig3)");

CALL pr_esperar_error('C05', 'CA3: con 2 cupos y 2 aprobadas, aprobar otra se rechaza',
  "UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = @p4",
  'No hay cupos disponibles');

CALL pr_verificar('C06', 'CA3: la postulación rechazada por cupos sigue Validada y sin asignación',
  (SELECT estado FROM postulaciones WHERE id_postulacion = @p4) = 'Validada'
  AND (SELECT COUNT(*) FROM asignaciones_beneficio WHERE id_postulacion = @p4) = 0,
  (SELECT estado FROM postulaciones WHERE id_postulacion = @p4));

CALL pr_esperar_exito('C07', 'D1: Validada -> Rechazada (no elegida) con justificación se acepta',
  "UPDATE postulaciones SET estado = 'Rechazada', justificacion_rechazo = 'No fue elegida por el comité' WHERE id_postulacion = @p4");

CALL pr_esperar_error('C08', 'D3: no se puede crear a mano una asignación de una postulación no Aprobada',
  "INSERT INTO asignaciones_beneficio (id_postulacion, valor_pago, numero_pagos, duracion_meses, fecha_inicio) VALUES (@p5, 100000, 3, 3, CURDATE())",
  'postulación Aprobada');

CALL pr_esperar_error('C09', 'D3: una sola asignación por postulación aprobada',
  "INSERT INTO asignaciones_beneficio (id_postulacion, valor_pago, numero_pagos, duracion_meses, fecha_inicio) VALUES (@p1, 100000, 3, 3, CURDATE())",
  'ya tiene una asignación');

-- =====================================================================
-- Bloque D. Pagos (D4, D3 Finalizado, CA4, CA5, CA6)
-- =====================================================================
CALL pr_esperar_exito('D01', 'D4: registrar el pago 1 se acepta',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_programada, fecha_pago) VALUES (@asig1, 1, 150000, @f_pago1, @f_pago1)");

CALL pr_verificar('D02', 'D4/D9: el pago guarda el usuario que lo registró (@app_id_usuario)',
  (SELECT id_usuario_registro FROM pagos WHERE id_asignacion = @asig1 AND numero_pago = 1) = 5,
  (SELECT CONCAT('id_usuario_registro=', IFNULL(id_usuario_registro, 'NULL'))
     FROM pagos WHERE id_asignacion = @asig1 AND numero_pago = 1));

CALL pr_esperar_error('D03', 'CA5: un segundo pago Registrado con el mismo número se rechaza',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 1, 150000, @f_pago1)",
  'Ya existe un pago registrado');

CALL pr_esperar_error('D04', 'D4: el número de pago no puede superar el total de pagos (3)',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 4, 150000, @f_pago1)",
  'supera el total de pagos');

CALL pr_esperar_error('D05', 'D4: el número de pago debe ser mayor que cero',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 0, 150000, @f_pago1)",
  'mayor que cero');

CALL pr_esperar_error('D06', 'D4: el monto debe ser mayor que cero',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 2, 0, @f_prog2)",
  'chk_pago_monto');

CALL pr_esperar_error('D07', 'CA6: pago con retraso y sin motivo se rechaza',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_programada, fecha_pago, hubo_retraso) VALUES (@asig1, 2, 150000, @f_prog2, @f_pago2, 1)",
  'motivo del retraso es obligatorio');

CALL pr_esperar_error('D08', 'CA6: pago con retraso y motivo en blanco se rechaza',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_programada, fecha_pago, hubo_retraso, motivo_retraso) VALUES (@asig1, 2, 150000, @f_prog2, @f_pago2, 1, '  ')",
  'motivo del retraso es obligatorio');

CALL pr_esperar_exito('D09', 'D4: pago con retraso y motivo se acepta',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_programada, fecha_pago, hubo_retraso, motivo_retraso) VALUES (@asig1, 2, 150000, @f_prog2, @f_pago2, 1, 'Demora en el giro de la institución')");

CALL pr_verificar('D10', 'D3: con 2 de 3 pagos la asignación sigue En curso',
  (SELECT estado FROM asignaciones_beneficio WHERE id_asignacion = @asig1) = 'En curso',
  (SELECT estado FROM asignaciones_beneficio WHERE id_asignacion = @asig1));

CALL pr_esperar_error('D11', 'D4: los pagos no se editan (cambiar el monto se rechaza)',
  "UPDATE pagos SET monto = 1 WHERE id_asignacion = @asig1 AND numero_pago = 1",
  'no se pueden modificar');

CALL pr_esperar_error('D12', 'D4: los pagos no se borran',
  "DELETE FROM pagos WHERE id_asignacion = @asig1 AND numero_pago = 1",
  'no se pueden eliminar');

CALL pr_esperar_error('D13', 'D4: anular un pago sin motivo se rechaza',
  "UPDATE pagos SET estado = 'Anulado' WHERE id_asignacion = @asig1 AND numero_pago = 2",
  'motivo de anulación es obligatorio');

CALL pr_esperar_exito('D14', 'D4: anular un pago con motivo se acepta',
  "UPDATE pagos SET estado = 'Anulado', motivo_anulacion = 'Monto digitado con error' WHERE id_asignacion = @asig1 AND numero_pago = 2");

CALL pr_verificar('D15', 'D4/D9: la anulación guarda quién anuló y cuándo',
  (SELECT id_usuario_anulacion = 5 AND fecha_anulacion IS NOT NULL FROM pagos
    WHERE id_asignacion = @asig1 AND numero_pago = 2 AND estado = 'Anulado'),
  'pagos.id_usuario_anulacion / pagos.fecha_anulacion');

CALL pr_esperar_exito('D16', 'CA5: tras anular, registrar un pago nuevo con el mismo número se acepta',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_programada, fecha_pago, hubo_retraso, motivo_retraso) VALUES (@asig1, 2, 150000, @f_prog2, @f_pago2, 1, 'Demora en el giro de la institución')");

CALL pr_verificar('D17', 'CA5: el pago 2 queda con 1 Anulado y 1 Registrado',
  (SELECT COUNT(*) FROM pagos WHERE id_asignacion = @asig1 AND numero_pago = 2 AND estado = 'Anulado') = 1
  AND (SELECT COUNT(*) FROM pagos WHERE id_asignacion = @asig1 AND numero_pago = 2 AND estado = 'Registrado') = 1,
  (SELECT GROUP_CONCAT(estado) FROM pagos WHERE id_asignacion = @asig1 AND numero_pago = 2));

CALL pr_esperar_error('D18', 'D4: un pago anulado no se puede volver a Registrado',
  "UPDATE pagos SET estado = 'Registrado' WHERE id_asignacion = @asig1 AND numero_pago = 2 AND estado = 'Anulado'",
  'no se pueden modificar');

CALL pr_esperar_exito('D19', 'D4: registrar el 3.er pago se acepta',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 3, 150000, @f_pago3)");

CALL pr_verificar('D20', 'CA4: con el 3.er pago vigente la asignación pasa a Finalizado',
  (SELECT estado = 'Finalizado' AND fecha_fin IS NOT NULL
     FROM asignaciones_beneficio WHERE id_asignacion = @asig1),
  (SELECT CONCAT(estado, ' fecha_fin=', IFNULL(fecha_fin, 'NULL'))
     FROM asignaciones_beneficio WHERE id_asignacion = @asig1));

CALL pr_esperar_error('D21', 'D4: no se registran pagos en una asignación Finalizado',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 1, 150000, @f_pago3)",
  'asignaciones En curso');

CALL pr_esperar_exito('D22', 'D4: anular el último pago de una asignación finalizada se acepta',
  "UPDATE pagos SET estado = 'Anulado', motivo_anulacion = 'Fecha equivocada' WHERE id_asignacion = @asig1 AND numero_pago = 3 AND estado = 'Registrado'");

CALL pr_verificar('D23', 'D3: al anular el último pago la asignación vuelve a En curso',
  (SELECT estado FROM asignaciones_beneficio WHERE id_asignacion = @asig1) = 'En curso',
  (SELECT estado FROM asignaciones_beneficio WHERE id_asignacion = @asig1));

CALL pr_esperar_exito('D24', 'D4: registrar de nuevo el pago 3 se acepta',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 3, 150000, @f_pago3)");

CALL pr_verificar('D25', 'CA4: la asignación vuelve a Finalizado con el pago 3 corregido',
  (SELECT estado FROM asignaciones_beneficio WHERE id_asignacion = @asig1) = 'Finalizado',
  (SELECT estado FROM asignaciones_beneficio WHERE id_asignacion = @asig1));

CALL pr_verificar('D26', 'D4: existe el índice único (id_asignacion, numero_pago, vigente) como respaldo del trigger',
  (SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'pagos' AND index_name = 'uk_pago_vigente'
      AND non_unique = 0) = 'id_asignacion,numero_pago,vigente',
  (SELECT IFNULL(GROUP_CONCAT(column_name ORDER BY seq_in_index), 'No existe uk_pago_vigente')
     FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'pagos' AND index_name = 'uk_pago_vigente'));

-- =====================================================================
-- Bloque E. Estados de la asignación (D3, D5)
-- =====================================================================
CALL pr_esperar_error('E01', 'D3: no se puede poner Finalizado a mano sin todos los pagos',
  "UPDATE asignaciones_beneficio SET estado = 'Finalizado' WHERE id_asignacion = @asig3",
  'solo puede finalizar');

CALL pr_esperar_error('E02', 'D3: suspender sin motivo se rechaza',
  "UPDATE asignaciones_beneficio SET estado = 'Suspendido' WHERE id_asignacion = @asig3",
  'motivo es obligatorio');

CALL pr_esperar_exito('E03', 'D3: suspender con motivo se acepta',
  "UPDATE asignaciones_beneficio SET estado = 'Suspendido', motivo_estado = 'Inasistencia reportada' WHERE id_asignacion = @asig3");

CALL pr_verificar('E04', 'D5: al suspender se crea la novedad Suspensión',
  (SELECT COUNT(*) FROM novedades WHERE id_asignacion = @asig3 AND tipo = 'Suspensión'
      AND descripcion = 'Inasistencia reportada' AND id_usuario = 5) = 1,
  (SELECT CONCAT('Novedades: ', COUNT(*)) FROM novedades WHERE id_asignacion = @asig3));

CALL pr_esperar_error('E05', 'D3: no se registran pagos en una asignación Suspendido',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig3, 1, 150000, @f_pago1)",
  'asignaciones En curso');

CALL pr_esperar_exito('E06', 'D3: reactivar (Suspendido -> En curso) con motivo se acepta',
  "UPDATE asignaciones_beneficio SET estado = 'En curso', motivo_estado = 'Se justificó la inasistencia' WHERE id_asignacion = @asig3");

CALL pr_verificar('E07', 'D5: al reactivar se crea la novedad Reactivación',
  (SELECT COUNT(*) FROM novedades WHERE id_asignacion = @asig3 AND tipo = 'Reactivación') = 1,
  (SELECT GROUP_CONCAT(tipo) FROM novedades WHERE id_asignacion = @asig3));

CALL pr_esperar_exito('E08a', 'Preparación: pago 1 de la asignación reactivada',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig3, 1, 150000, @f_pago1)");
CALL pr_esperar_exito('E08b', 'Preparación: pago 2 de la asignación reactivada',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig3, 2, 150000, @f_prog2)");

CALL pr_esperar_error('E08', 'D4: el total de pagos no puede quedar por debajo de los ya registrados',
  "UPDATE asignaciones_beneficio SET numero_pagos = 1 WHERE id_asignacion = @asig3",
  'no puede ser menor');

CALL pr_esperar_exito('E09', 'D3: cancelar con motivo se acepta',
  "UPDATE asignaciones_beneficio SET estado = 'Cancelado', motivo_estado = 'El aprendiz se retiró del programa' WHERE id_asignacion = @asig3");

CALL pr_verificar('E10', 'D5: al cancelar se crea la novedad Cancelación',
  (SELECT COUNT(*) FROM novedades WHERE id_asignacion = @asig3 AND tipo = 'Cancelación') = 1,
  (SELECT GROUP_CONCAT(tipo) FROM novedades WHERE id_asignacion = @asig3));

CALL pr_esperar_error('E11', 'D3: Cancelado es final (Cancelado -> En curso se rechaza)',
  "UPDATE asignaciones_beneficio SET estado = 'En curso', motivo_estado = 'Error' WHERE id_asignacion = @asig3",
  'Transición de estado de asignación no permitida');

CALL pr_esperar_error('E12', 'D3: Finalizado no pasa a Suspendido',
  "UPDATE asignaciones_beneficio SET estado = 'Suspendido', motivo_estado = 'Prueba' WHERE id_asignacion = @asig1",
  'Transición de estado de asignación no permitida');

-- =====================================================================
-- Bloque F. Novedades y notificaciones (D5, D8)
-- =====================================================================
CALL pr_esperar_exito('F01', 'D5: registrar una novedad Retraso de pago se acepta',
  "INSERT INTO novedades (id_asignacion, tipo, descripcion) VALUES (@asig1, 'Retraso de pago', 'El pago 2 se giró 9 días tarde')");

CALL pr_verificar('F02', 'D5/D9: la novedad guarda el usuario que la registró',
  (SELECT id_usuario FROM novedades WHERE id_asignacion = @asig1 AND tipo = 'Retraso de pago') = 5,
  (SELECT CONCAT('id_usuario=', IFNULL(id_usuario, 'NULL')) FROM novedades
    WHERE id_asignacion = @asig1 AND tipo = 'Retraso de pago'));

CALL pr_verificar('F03', 'D8: hay notificación al pasar a Validada',
  (SELECT COUNT(*) FROM notificaciones WHERE id_usuario = @a1 AND evento = 'Validada') = 1,
  (SELECT CONCAT('Notificaciones: ', COUNT(*)) FROM notificaciones WHERE id_usuario = @a1));

CALL pr_verificar('F04', 'D8: hay notificación al pasar a Aprobada',
  (SELECT COUNT(*) FROM notificaciones WHERE id_usuario = @a1 AND evento = 'Aprobada') = 1,
  (SELECT GROUP_CONCAT(evento) FROM notificaciones WHERE id_usuario = @a1));

CALL pr_verificar('F05', 'D8: la notificación de rechazo incluye la justificación',
  (SELECT COUNT(*) FROM notificaciones WHERE id_usuario = @a2 AND evento = 'Rechazada'
      AND cuerpo LIKE '%El certificado bancario no corresponde al aprendiz%') = 1,
  (SELECT IFNULL(MAX(cuerpo), 'Sin notificación') FROM notificaciones WHERE id_usuario = @a2));

CALL pr_verificar('F06', 'D8: hay una notificación por cada pago registrado (5 en la asignación 1)',
  (SELECT COUNT(*) FROM notificaciones WHERE id_usuario = @a1 AND evento = 'Pago') = 5,
  (SELECT CONCAT('Notificaciones de pago: ', COUNT(*)) FROM notificaciones WHERE id_usuario = @a1 AND evento = 'Pago'));

CALL pr_verificar('F07', 'D8: hay notificación al registrar una novedad (manual y automáticas)',
  (SELECT COUNT(*) FROM notificaciones WHERE id_usuario = @a1 AND evento = 'Novedad') = 1
  AND (SELECT COUNT(*) FROM notificaciones WHERE id_usuario = @a3 AND evento = 'Novedad') = 3,
  CONCAT('a1=', (SELECT COUNT(*) FROM notificaciones WHERE id_usuario = @a1 AND evento = 'Novedad'),
         ' a3=', (SELECT COUNT(*) FROM notificaciones WHERE id_usuario = @a3 AND evento = 'Novedad')));

CALL pr_verificar('F08', 'D8: las notificaciones van al correo del aprendiz y quedan sin leer y pendientes de envío',
  (SELECT COUNT(*) FROM notificaciones
    WHERE id_usuario = @a1 AND (destinatario <> 'a1@benefitrack.test' OR leida <> 0 OR estado_correo <> 'Pendiente')) = 0
  AND (SELECT COUNT(*) FROM notificaciones WHERE id_usuario = @a1) > 0,
  'notificaciones.destinatario / leida / estado_correo');

CALL pr_verificar('F09', 'D8: no se notifica al pasar a Pendiente (solo Validada, Aprobada y Rechazada)',
  (SELECT COUNT(*) FROM notificaciones WHERE id_usuario = @a5) = 1,
  (SELECT GROUP_CONCAT(evento) FROM notificaciones WHERE id_usuario = @a5));

-- =====================================================================
-- Bloque G. Recuperación de contraseña, migraciones e instituciones (D10, D11)
-- =====================================================================
CALL pr_esperar_exito('G01', 'D11: guardar un token de recuperación (hash, expiración, usado) se acepta',
  "INSERT INTO recuperacion_contrasena (id_usuario, token_hash, fecha_expiracion) VALUES (@a1, SHA2('token-de-prueba', 256), NOW() + INTERVAL 1 HOUR)");

CALL pr_verificar('G02', 'D11: el token nuevo queda sin usar',
  (SELECT usado FROM recuperacion_contrasena WHERE token_hash = SHA2('token-de-prueba', 256)) = 0,
  'recuperacion_contrasena.usado');

CALL pr_esperar_error('G03', 'D11: el mismo hash de token no se puede repetir',
  "INSERT INTO recuperacion_contrasena (id_usuario, token_hash, fecha_expiracion) VALUES (@a2, SHA2('token-de-prueba', 256), NOW() + INTERVAL 1 HOUR)",
  'Duplicate');

CALL pr_esperar_error('G04', 'D11: la expiración debe ser posterior a la creación',
  "INSERT INTO recuperacion_contrasena (id_usuario, token_hash, fecha_creacion, fecha_expiracion) VALUES (@a1, SHA2('otro', 256), NOW(), NOW() - INTERVAL 1 HOUR)",
  'chk_recuperacion_expiracion');

CALL pr_esperar_exito('G05', 'D11: registrar una migración desde Excel se acepta',
  "INSERT INTO migraciones (id_usuario, nombre_archivo, entidad_destino, total_registros, registros_exitosos, registros_fallidos, estado) VALUES (5, 'aprendices_2026.xlsx', 'usuarios', 10, 9, 1, 'Completada con errores')");

CALL pr_esperar_error('G06', 'D11: exitosos + fallidos no pueden superar el total',
  "INSERT INTO migraciones (id_usuario, nombre_archivo, entidad_destino, total_registros, registros_exitosos, registros_fallidos, estado) VALUES (5, 'malo.xlsx', 'usuarios', 5, 5, 1, 'Completada')",
  'chk_migracion_conteos');

CALL pr_esperar_error('G07', 'D10: un beneficio sin institución se rechaza',
  "INSERT INTO beneficios (id_institucion, nombre, descripcion, categoria) VALUES (NULL, 'Sin institución', 'x', 'Otro')",
  'id_institucion');

-- =====================================================================
-- Bloque H. Correcciones de la auditoría (ronda 2)
-- =====================================================================
-- Datos: una segunda convocatoria abierta (5 cupos, 3 pagos), una Inactiva
-- y una Activa ya cerrada por fechas.
INSERT INTO convocatorias (id_beneficio, nombre, fecha_inicio, fecha_cierre, cupos, requisitos,
                           valor_pago, numero_pagos, duracion_meses, estado)
VALUES (@ben, 'Convocatoria 2 de pruebas', CURDATE() - INTERVAL 150 DAY, CURDATE() + INTERVAL 30 DAY, 5,
        'Ser aprendiz activo', 150000, 3, 3, 'Activa');
SET @conv2 = LAST_INSERT_ID();
INSERT INTO convocatorias (id_beneficio, nombre, fecha_inicio, fecha_cierre, cupos, requisitos,
                           valor_pago, numero_pagos, duracion_meses, estado)
VALUES (@ben, 'Convocatoria inactiva', CURDATE() - INTERVAL 30 DAY, CURDATE() + INTERVAL 30 DAY, 5,
        'x', 150000, 3, 3, 'Inactiva');
SET @conv3 = LAST_INSERT_ID();
INSERT INTO convocatorias (id_beneficio, nombre, fecha_inicio, fecha_cierre, cupos, requisitos,
                           valor_pago, numero_pagos, duracion_meses, estado)
VALUES (@ben, 'Convocatoria vencida', CURDATE() - INTERVAL 60 DAY, CURDATE() - INTERVAL 1 DAY, 5,
        'x', 150000, 3, 3, 'Activa');
SET @conv4 = LAST_INSERT_ID();

CALL pr_esperar_exito('H00a', 'Preparación: 4 postulaciones de aprendices en la convocatoria 2',
  "INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (@a1, @conv2, '{}'), (@a2, @conv2, '{}'), (@a3, @conv2, '{}'), (@a4, @conv2, '{\"estrato\": 1}')");
SELECT MAX(id_postulacion) INTO @q4 FROM postulaciones WHERE id_convocatoria = @conv2;
SET @q3 = @q4 - 1, @q2 = @q4 - 2, @q1 = @q4 - 3;

CALL pr_esperar_exito('H00b', 'Preparación: validar y aprobar 3 postulaciones de la convocatoria 2',
  "UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion IN (@q1, @q2, @q3)");
CALL pr_esperar_exito('H00c', 'Preparación: aprobar 3 postulaciones de la convocatoria 2',
  "UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion IN (@q1, @q2, @q3)");
SELECT id_asignacion INTO @h1 FROM asignaciones_beneficio WHERE id_postulacion = @q1;
SELECT id_asignacion INTO @h2 FROM asignaciones_beneficio WHERE id_postulacion = @q2;
SELECT id_asignacion INTO @h3 FROM asignaciones_beneficio WHERE id_postulacion = @q3;
CALL pr_esperar_exito('H00d', 'Preparación: fecha_inicio de las nuevas asignaciones en el pasado',
  "UPDATE asignaciones_beneficio SET fecha_inicio = @f_inicio_asig WHERE id_asignacion IN (@h1, @h2, @h3)");

-- B2: valores de la asignación congelados cuando ya hay pagos o no está En curso
CALL pr_esperar_exito('H01', 'B2: sin pagos, cambiar numero_pagos de una asignación En curso se permite',
  "UPDATE asignaciones_beneficio SET numero_pagos = 4 WHERE id_asignacion = @h1");
CALL pr_esperar_exito('H02a', 'Preparación: registrar solo el pago 3 de la asignación h1',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@h1, 3, 150000, @f_pago1)");
CALL pr_esperar_error('H02', 'B2: con pagos, bajar numero_pagos (por debajo del pago 3 vigente) se rechaza',
  "UPDATE asignaciones_beneficio SET numero_pagos = 2 WHERE id_asignacion = @h1",
  'no se pueden cambiar');
CALL pr_esperar_exito('H03a', 'Preparación: pago 1 de h2',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@h2, 1, 150000, @f_pago1)");
CALL pr_esperar_exito('H03b', 'Preparación: anular el pago 1 de h2',
  "UPDATE pagos SET estado = 'Anulado', motivo_anulacion = 'Prueba' WHERE id_asignacion = @h2 AND numero_pago = 1");
CALL pr_esperar_error('H03', 'B2: con solo pagos anulados, cambiar valor_pago también se rechaza',
  "UPDATE asignaciones_beneficio SET valor_pago = 200000 WHERE id_asignacion = @h2",
  'no se pueden cambiar');
CALL pr_esperar_exito('H04a', 'Preparación: suspender h3 (sin pagos)',
  "UPDATE asignaciones_beneficio SET estado = 'Suspendido', motivo_estado = 'Motivo repetido' WHERE id_asignacion = @h3");
CALL pr_esperar_error('H04', 'B2: en una asignación que no está En curso, cambiar duracion_meses se rechaza',
  "UPDATE asignaciones_beneficio SET duracion_meses = 6 WHERE id_asignacion = @h3",
  'no se pueden cambiar');
CALL pr_esperar_error('H05', 'D3: cancelar repitiendo el motivo anterior se rechaza',
  "UPDATE asignaciones_beneficio SET estado = 'Cancelado', motivo_estado = 'Motivo repetido' WHERE id_asignacion = @h3",
  'motivo es obligatorio');
CALL pr_esperar_exito('H06', 'D3: Suspendido -> Cancelado con motivo nuevo se acepta',
  "UPDATE asignaciones_beneficio SET estado = 'Cancelado', motivo_estado = 'Retiro voluntario' WHERE id_asignacion = @h3");
CALL pr_verificar('H07', 'D9: las novedades automáticas guardan @app_id_usuario (5)',
  (SELECT COUNT(*) FROM novedades WHERE id_asignacion IN (@asig3, @h3)) = 5
  AND (SELECT COUNT(*) FROM novedades WHERE id_asignacion IN (@asig3, @h3) AND NOT (id_usuario <=> 5)) = 0,
  (SELECT GROUP_CONCAT(CONCAT(tipo, ':', IFNULL(id_usuario, 'NULL'))) FROM novedades WHERE id_asignacion IN (@asig3, @h3)));
CALL pr_verificar('H08', 'D9: la asignación creada al aprobar guarda @app_id_usuario (5)',
  (SELECT COUNT(*) FROM asignaciones_beneficio
    WHERE id_asignacion IN (@asig1, @asig3, @h1, @h2, @h3) AND id_usuario_registro <=> 5) = 5,
  (SELECT GROUP_CONCAT(IFNULL(id_usuario_registro, 'NULL')) FROM asignaciones_beneficio
    WHERE id_asignacion IN (@asig1, @asig3, @h1, @h2, @h3)));

-- I2: Finalizado -> En curso solo al anular un pago
CALL pr_esperar_error('H09', 'I2: volver de Finalizado a En curso a mano (subiendo numero_pagos) se rechaza',
  "UPDATE asignaciones_beneficio SET estado = 'En curso', numero_pagos = numero_pagos + 1, motivo_estado = 'Reabierta a mano' WHERE id_asignacion = @asig1",
  'solo vuelve a En curso al anular');
CALL pr_esperar_error('H10', 'B2: en una asignación Finalizado, cambiar valor_pago se rechaza',
  "UPDATE asignaciones_beneficio SET valor_pago = 1 WHERE id_asignacion = @asig1",
  'no se pueden cambiar');
CALL pr_verificar('H11', 'I2/B2: la asignación 1 sigue Finalizado con 3 pagos y valor 150000',
  (SELECT estado = 'Finalizado' AND numero_pagos = 3 AND valor_pago = 150000
     FROM asignaciones_beneficio WHERE id_asignacion = @asig1),
  (SELECT CONCAT(estado, ' numero_pagos=', numero_pagos, ' valor_pago=', valor_pago)
     FROM asignaciones_beneficio WHERE id_asignacion = @asig1));

-- I3: trazabilidad no falsificable
CALL pr_esperar_error('H12', 'I3: escribir a mano quién aprobó (sin aprobar) se rechaza',
  "UPDATE postulaciones SET id_usuario_aprobacion = @a1, fecha_aprobacion = '2020-01-01' WHERE id_postulacion = @p5",
  'los registra la base de datos');
CALL pr_esperar_error('H13', 'I3: reescribir fecha_validacion se rechaza',
  "UPDATE postulaciones SET fecha_validacion = '2020-01-01' WHERE id_postulacion = @p1",
  'los registra la base de datos');
CALL pr_esperar_error('H14', 'I3: cambiar la justificación después de rechazar se rechaza',
  "UPDATE postulaciones SET justificacion_rechazo = 'Otra razón distinta' WHERE id_postulacion = @p2",
  'solo se registra al rechazar');
CALL pr_esperar_error('H15', 'I3: cambiar datos_formulario de una postulación no Pendiente se rechaza',
  "UPDATE postulaciones SET datos_formulario = '{\"estrato\": 6}' WHERE id_postulacion = @p1",
  'mientras la postulación está Pendiente');
CALL pr_esperar_exito('H16', 'I3: cambiar datos_formulario de una postulación Pendiente se permite',
  "UPDATE postulaciones SET datos_formulario = '{\"estrato\": 2}' WHERE id_postulacion = @q4");
CALL pr_esperar_exito('H17a', 'Preparación: cargar un documento a una postulación Pendiente',
  "INSERT INTO documentos_postulacion (id_postulacion, id_tipo_documento, nombre_archivo, ruta_archivo, extension, tamano_mb) VALUES (@q4, 1, 'cc.pdf', 'docs/cc.pdf', 'pdf', 1.20)");
SELECT MAX(id_documento) INTO @doc FROM documentos_postulacion WHERE id_postulacion = @q4;
CALL pr_esperar_exito('H17', 'D6: validar un documento se acepta',
  "UPDATE documentos_postulacion SET estado_validacion = 'Validado' WHERE id_documento = @doc");
CALL pr_verificar('H18', 'D6/D9: tr_validacion_documento guarda fecha y usuario (5) de la validación',
  (SELECT fecha_validacion IS NOT NULL AND id_usuario_validacion <=> 5
     FROM documentos_postulacion WHERE id_documento = @doc),
  (SELECT CONCAT('id_usuario_validacion=', IFNULL(id_usuario_validacion, 'NULL'))
     FROM documentos_postulacion WHERE id_documento = @doc));
CALL pr_esperar_error('H19', 'I3: escribir a mano quién validó un documento se rechaza',
  "UPDATE documentos_postulacion SET id_usuario_validacion = @a1 WHERE id_documento = @doc",
  'los registra la base de datos');

-- I4: auditoría e historial solo de inserción; sin borrados directos
CALL pr_esperar_error('H20', 'I4: modificar la auditoría se rechaza',
  "UPDATE auditoria SET id_usuario = NULL WHERE entidad = 'postulaciones' AND id_entidad = @p1",
  'La auditoría no se puede modificar ni eliminar');
CALL pr_esperar_error('H21', 'I4: borrar la auditoría se rechaza',
  "DELETE FROM auditoria WHERE entidad = 'postulaciones' AND id_entidad = @p1",
  'La auditoría no se puede modificar ni eliminar');
CALL pr_esperar_error('H22', 'I4: modificar el historial de estados se rechaza',
  "UPDATE historial_estados SET justificacion = 'x' WHERE id_postulacion = @p1",
  'El historial de estados no se puede modificar ni eliminar');
CALL pr_esperar_error('H23', 'I4: borrar el historial de estados se rechaza',
  "DELETE FROM historial_estados WHERE id_postulacion = @p1",
  'El historial de estados no se puede modificar ni eliminar');
CALL pr_esperar_error('H24', 'I4: borrar una asignación se rechaza',
  "DELETE FROM asignaciones_beneficio WHERE id_asignacion = @h3",
  'Las asignaciones no se pueden eliminar');
CALL pr_esperar_error('H25', 'I4: borrar una postulación se rechaza',
  "DELETE FROM postulaciones WHERE id_postulacion = @q4",
  'Las postulaciones no se pueden eliminar');

-- I5: fechas y monto del pago
CALL pr_esperar_error('H26', 'I5: fecha_pago anterior al inicio de la asignación se rechaza',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@h1, 1, 150000, @f_inicio_asig - INTERVAL 1 DAY)",
  'La fecha de pago debe estar entre');
CALL pr_esperar_error('H27', 'I5: fecha_pago futura se rechaza',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@h1, 1, 150000, CURDATE() + INTERVAL 1 DAY)",
  'La fecha de pago debe estar entre');
CALL pr_esperar_error('H28', 'I5: fecha_programada anterior al inicio de la asignación se rechaza',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_programada, fecha_pago) VALUES (@h1, 1, 150000, @f_inicio_asig - INTERVAL 1 DAY, @f_pago1)",
  'La fecha programada no puede ser anterior');
CALL pr_esperar_error('H29', 'I5: monto distinto del valor del pago sin observación se rechaza',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@h1, 1, 100000, @f_pago1)",
  'la observación es obligatoria');
CALL pr_esperar_exito('H30', 'I5: monto distinto del valor del pago con observación se acepta',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago, observacion) VALUES (@h1, 1, 100000, @f_pago1, 'Descuento por inasistencia')");

-- Menores
CALL pr_esperar_error('H31', 'Menor: cambiar id_pago al anular se rechaza',
  "UPDATE pagos SET id_pago = id_pago + 100000, estado = 'Anulado', motivo_anulacion = 'x' WHERE id_asignacion = @h1 AND numero_pago = 3",
  'no se pueden modificar');
CALL pr_esperar_error('H32', 'Menor: bajar los cupos por debajo de las aprobadas se rechaza',
  "UPDATE convocatorias SET cupos = 1 WHERE id_convocatoria = @conv",
  'Los cupos no pueden ser menores');
CALL pr_esperar_exito('H33', 'Menor: bajar los cupos hasta el número de aprobadas se permite',
  "UPDATE convocatorias SET cupos = 3 WHERE id_convocatoria = @conv2");
CALL pr_esperar_error('H34', 'Menor: un Administrador no puede crear una postulación',
  "INSERT INTO postulaciones (id_usuario, id_convocatoria) VALUES (5, @conv2)",
  'Solo un usuario con rol Aprendiz');
CALL pr_esperar_error('H35', 'Menor: no se puede postular a una convocatoria Inactiva',
  "INSERT INTO postulaciones (id_usuario, id_convocatoria) VALUES (@a5, @conv3)",
  'La convocatoria no está abierta');
CALL pr_esperar_error('H36', 'Menor: no se puede postular a una convocatoria Activa fuera de sus fechas',
  "INSERT INTO postulaciones (id_usuario, id_convocatoria) VALUES (@a5, @conv4)",
  'La convocatoria no está abierta');
CALL pr_esperar_error('H37', 'D4: insertar un pago directamente en estado Anulado se rechaza',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago, estado, motivo_anulacion) VALUES (@h1, 2, 150000, @f_pago1, 'Anulado', 'x')",
  'debe registrarse en estado Registrado');
CALL pr_esperar_error('H39', 'I3: no se puede crear una postulación con la aprobación ya llena',
  "INSERT INTO postulaciones (id_usuario, id_convocatoria, id_usuario_aprobacion, fecha_aprobacion) VALUES (@a5, @conv2, 5, NOW())",
  'los registra la base de datos');
CALL pr_verificar('H38', 'Orden FOLLOWS de los triggers AFTER UPDATE de postulaciones',
  (SELECT GROUP_CONCAT(trigger_name ORDER BY action_order) FROM information_schema.triggers
    WHERE trigger_schema = DATABASE() AND event_object_table = 'postulaciones'
      AND action_timing = 'AFTER' AND event_manipulation = 'UPDATE')
    = 'tr_postulacion_estado,tr_auditoria_postulacion,tr_postulacion_notificar,tr_postulacion_crear_asignacion',
  (SELECT GROUP_CONCAT(CONCAT(action_order, ':', trigger_name) ORDER BY action_order) FROM information_schema.triggers
    WHERE trigger_schema = DATABASE() AND event_object_table = 'postulaciones'
      AND action_timing = 'AFTER' AND event_manipulation = 'UPDATE'));

-- =====================================================================
-- Bloque I. Solo un administrador activo puede hacer las acciones de
-- gestión (ronda 3). Con @app_id_usuario = NULL se mantiene D9: se permite.
-- =====================================================================
CALL pr_verificar('I00', 'Existen fn_es_administrador y pr_exigir_administrador',
  (SELECT COUNT(*) FROM information_schema.routines
    WHERE routine_schema = DATABASE()
      AND ((routine_name = 'fn_es_administrador' AND routine_type = 'FUNCTION')
        OR (routine_name = 'pr_exigir_administrador' AND routine_type = 'PROCEDURE'))) = 2,
  (SELECT IFNULL(GROUP_CONCAT(CONCAT(routine_type, ' ', routine_name)), 'ninguna') FROM information_schema.routines
    WHERE routine_schema = DATABASE() AND routine_name IN ('fn_es_administrador', 'pr_exigir_administrador')));

SET @app_id_usuario = NULL;
INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol, estado)
VALUES ('PRUEBA-ADM-INACTIVO', 'Admin', 'Inactivo', 'adm.inactivo@benefitrack.test', 'hash', @rol_admin, 'Inactivo');
SET @adm_inactivo = LAST_INSERT_ID();
INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (@a5, @conv2, '{}');
SET @q5 = LAST_INSERT_ID();
INSERT INTO documentos_postulacion (id_postulacion, id_tipo_documento, nombre_archivo, ruta_archivo, extension, tamano_mb)
VALUES (@q5, 2, 'cert.pdf', 'docs/cert.pdf', 'pdf', 0.80);
SET @doc2 = LAST_INSERT_ID();

-- El aprendiz sí puede corregir su propio formulario mientras está Pendiente.
SET @app_id_usuario = @a5;
CALL pr_esperar_exito('I01', 'El aprendiz puede corregir su formulario Pendiente (no se restringe de más)',
  "UPDATE postulaciones SET datos_formulario = '{\"estrato\": 2}' WHERE id_postulacion = @q5");

-- Convocatoria: cupos y estado
SET @app_id_usuario = @a1;
CALL pr_esperar_error('I02', 'Rol: un aprendiz no puede modificar los cupos de una convocatoria',
  "UPDATE convocatorias SET cupos = 6 WHERE id_convocatoria = @conv2", 'Solo un administrador activo puede');
CALL pr_esperar_error('I03', 'Rol: un aprendiz no puede cambiar el estado de una convocatoria',
  "UPDATE convocatorias SET estado = 'Activa' WHERE id_convocatoria = @conv3", 'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('I04', 'Rol: un administrador inactivo no puede modificar los cupos',
  "UPDATE convocatorias SET cupos = 6 WHERE id_convocatoria = @conv2", 'Solo un administrador activo puede');
CALL pr_esperar_error('I05', 'Rol: un administrador inactivo no puede cambiar el estado de una convocatoria',
  "UPDATE convocatorias SET estado = 'Activa' WHERE id_convocatoria = @conv3", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('I06', 'Rol: un administrador activo puede modificar los cupos',
  "UPDATE convocatorias SET cupos = 6 WHERE id_convocatoria = @conv2");
CALL pr_esperar_exito('I07', 'Rol: un administrador activo puede cambiar el estado de una convocatoria',
  "UPDATE convocatorias SET estado = 'Activa' WHERE id_convocatoria = @conv3");

-- Documentos y solicitud de documentación (sobre q5, Pendiente)
SET @app_id_usuario = @a5;
CALL pr_esperar_error('I08', 'Rol: un aprendiz no puede validar o rechazar un documento',
  "UPDATE documentos_postulacion SET estado_validacion = 'Rechazado' WHERE id_documento = @doc2", 'Solo un administrador activo puede');
CALL pr_esperar_error('I09', 'Rol: un aprendiz no puede pedir documentación adicional',
  "INSERT INTO solicitudes_documentacion (id_postulacion, id_usuario, observacion) VALUES (@q5, @a5, 'x')", 'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('I10', 'Rol: un administrador inactivo no puede validar o rechazar un documento',
  "UPDATE documentos_postulacion SET estado_validacion = 'Rechazado' WHERE id_documento = @doc2", 'Solo un administrador activo puede');
CALL pr_esperar_error('I11', 'Rol: un administrador inactivo no puede pedir documentación adicional',
  "INSERT INTO solicitudes_documentacion (id_postulacion, id_usuario, observacion) VALUES (@q5, @adm_inactivo, 'x')", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('I12', 'Rol: un administrador activo puede rechazar un documento',
  "UPDATE documentos_postulacion SET estado_validacion = 'Rechazado' WHERE id_documento = @doc2");
CALL pr_esperar_exito('I13', 'Rol: un administrador activo puede pedir documentación adicional',
  "INSERT INTO solicitudes_documentacion (id_postulacion, id_usuario, observacion) VALUES (@q5, 5, 'Adjunte un certificado legible')");

-- Postulaciones: validar, aprobar, rechazar
SET @app_id_usuario = @a4;
CALL pr_esperar_error('I14', 'Rol: un aprendiz no puede validar una postulación (ni la suya)',
  "UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion = @q4", 'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('I15', 'Rol: un administrador inactivo no puede validar una postulación',
  "UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion = @q4", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('I16', 'Rol: un administrador activo puede validar una postulación',
  "UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion = @q4");
SET @app_id_usuario = @a4;
CALL pr_esperar_error('I17', 'Rol: un aprendiz no puede aprobar una postulación (caso 11 del revisor)',
  "UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = @q4", 'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('I18', 'Rol: un administrador inactivo no puede aprobar una postulación',
  "UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = @q4", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('I19', 'Rol: un administrador activo puede aprobar una postulación',
  "UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = @q4");
SELECT id_asignacion INTO @h4 FROM asignaciones_beneficio WHERE id_postulacion = @q4;
SET @app_id_usuario = @a5;
CALL pr_esperar_error('I20', 'Rol: un aprendiz no puede rechazar una postulación',
  "UPDATE postulaciones SET estado = 'Rechazada', justificacion_rechazo = 'x' WHERE id_postulacion = @q5", 'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('I21', 'Rol: un administrador inactivo no puede rechazar una postulación',
  "UPDATE postulaciones SET estado = 'Rechazada', justificacion_rechazo = 'x' WHERE id_postulacion = @q5", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('I22', 'Rol: un administrador activo puede rechazar una postulación',
  "UPDATE postulaciones SET estado = 'Rechazada', justificacion_rechazo = 'Documentos ilegibles' WHERE id_postulacion = @q5");

-- Asignaciones: crear, modificar y cambiar estado (sobre h4, En curso y sin pagos)
SET @app_id_usuario = @a1;
CALL pr_esperar_error('I23', 'Rol: un aprendiz no puede crear una asignación',
  "INSERT INTO asignaciones_beneficio (id_postulacion, valor_pago, numero_pagos, duracion_meses, fecha_inicio) VALUES (@q4, 1, 1, 1, CURDATE())", 'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('I24', 'Rol: un administrador inactivo no puede crear una asignación',
  "INSERT INTO asignaciones_beneficio (id_postulacion, valor_pago, numero_pagos, duracion_meses, fecha_inicio) VALUES (@q4, 1, 1, 1, CURDATE())", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_verificar('I25', 'Rol: el administrador activo que aprobó creó la asignación (y queda como su autor)',
  (SELECT id_usuario_registro FROM asignaciones_beneficio WHERE id_asignacion = @h4) = 5,
  (SELECT CONCAT('id_usuario_registro=', IFNULL(id_usuario_registro, 'NULL')) FROM asignaciones_beneficio WHERE id_asignacion = @h4));
SET @app_id_usuario = @a4;
CALL pr_esperar_error('I26', 'Rol: un aprendiz no puede modificar una asignación',
  "UPDATE asignaciones_beneficio SET numero_pagos = 2 WHERE id_asignacion = @h4", 'Solo un administrador activo puede');
CALL pr_esperar_error('I27', 'Rol: un aprendiz no puede suspender una asignación',
  "UPDATE asignaciones_beneficio SET estado = 'Suspendido', motivo_estado = 'x' WHERE id_asignacion = @h4", 'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('I28', 'Rol: un administrador inactivo no puede modificar una asignación',
  "UPDATE asignaciones_beneficio SET numero_pagos = 2 WHERE id_asignacion = @h4", 'Solo un administrador activo puede');
CALL pr_esperar_error('I29', 'Rol: un administrador inactivo no puede suspender una asignación',
  "UPDATE asignaciones_beneficio SET estado = 'Suspendido', motivo_estado = 'x' WHERE id_asignacion = @h4", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('I30', 'Rol: un administrador activo puede modificar una asignación sin pagos',
  "UPDATE asignaciones_beneficio SET numero_pagos = 2 WHERE id_asignacion = @h4");
CALL pr_esperar_exito('I31', 'Rol: un administrador activo puede suspender una asignación',
  "UPDATE asignaciones_beneficio SET estado = 'Suspendido', motivo_estado = 'Revisión de asistencia' WHERE id_asignacion = @h4");
SET @app_id_usuario = @a4;
CALL pr_esperar_error('I32', 'Rol: un aprendiz no puede reactivar una asignación',
  "UPDATE asignaciones_beneficio SET estado = 'En curso', motivo_estado = 'y' WHERE id_asignacion = @h4", 'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('I33', 'Rol: un administrador inactivo no puede reactivar una asignación',
  "UPDATE asignaciones_beneficio SET estado = 'En curso', motivo_estado = 'y' WHERE id_asignacion = @h4", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('I34', 'Rol: un administrador activo puede reactivar una asignación',
  "UPDATE asignaciones_beneficio SET estado = 'En curso', motivo_estado = 'Asistencia verificada' WHERE id_asignacion = @h4");

-- Pagos: registrar y anular (sobre h4, inicio hoy)
SET @app_id_usuario = @a4;
CALL pr_esperar_error('I35', 'Rol: un aprendiz no puede registrar un pago',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@h4, 1, 150000, CURDATE())", 'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('I36', 'Rol: un administrador inactivo no puede registrar un pago',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@h4, 1, 150000, CURDATE())", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('I37', 'Rol: un administrador activo puede registrar un pago',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@h4, 1, 150000, CURDATE())");
SET @app_id_usuario = @a4;
CALL pr_esperar_error('I38', 'Rol: un aprendiz no puede anular un pago',
  "UPDATE pagos SET estado = 'Anulado', motivo_anulacion = 'x' WHERE id_asignacion = @h4 AND numero_pago = 1", 'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('I39', 'Rol: un administrador inactivo no puede anular un pago',
  "UPDATE pagos SET estado = 'Anulado', motivo_anulacion = 'x' WHERE id_asignacion = @h4 AND numero_pago = 1", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('I40', 'Rol: un administrador activo puede anular un pago',
  "UPDATE pagos SET estado = 'Anulado', motivo_anulacion = 'Fecha equivocada' WHERE id_asignacion = @h4 AND numero_pago = 1");

-- Novedad manual y cancelación
SET @app_id_usuario = @a4;
CALL pr_esperar_error('I41', 'Rol: un aprendiz no puede registrar una novedad',
  "INSERT INTO novedades (id_asignacion, tipo, descripcion) VALUES (@h4, 'Otra', 'x')", 'Solo un administrador activo puede');
CALL pr_esperar_error('I42', 'Rol: un aprendiz no puede cancelar una asignación',
  "UPDATE asignaciones_beneficio SET estado = 'Cancelado', motivo_estado = 'z' WHERE id_asignacion = @h4", 'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('I43', 'Rol: un administrador inactivo no puede registrar una novedad',
  "INSERT INTO novedades (id_asignacion, tipo, descripcion) VALUES (@h4, 'Otra', 'x')", 'Solo un administrador activo puede');
CALL pr_esperar_error('I44', 'Rol: un administrador inactivo no puede cancelar una asignación',
  "UPDATE asignaciones_beneficio SET estado = 'Cancelado', motivo_estado = 'z' WHERE id_asignacion = @h4", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('I45', 'Rol: un administrador activo puede registrar una novedad',
  "INSERT INTO novedades (id_asignacion, tipo, descripcion) VALUES (@h4, 'Otra', 'Cambio de cuenta bancaria')");
CALL pr_esperar_exito('I46', 'Rol: un administrador activo puede cancelar una asignación',
  "UPDATE asignaciones_beneficio SET estado = 'Cancelado', motivo_estado = 'Retiro del programa' WHERE id_asignacion = @h4");

-- D9 se mantiene: sin @app_id_usuario la acción se permite y se guarda NULL.
SET @app_id_usuario = NULL;
CALL pr_esperar_exito('I47', 'D9: con @app_id_usuario = NULL se sigue permitiendo (novedad manual)',
  "INSERT INTO novedades (id_asignacion, tipo, descripcion) VALUES (@h4, 'Otra', 'Registro sin usuario de sesión')");
CALL pr_verificar('I48', 'D9: esa novedad guarda id_usuario NULL',
  (SELECT COUNT(*) FROM novedades WHERE id_asignacion = @h4 AND descripcion = 'Registro sin usuario de sesión' AND id_usuario IS NULL) = 1,
  'novedades.id_usuario');
SET @app_id_usuario = 5;

-- =====================================================================
-- Bloque J. Fecha de inicio de la asignación y cambios de rol (ronda 4)
-- =====================================================================
SET @app_id_usuario = NULL;
INSERT INTO usuarios (documento, nombre, apellido, fecha_nacimiento, correo, password_hash, id_rol, id_programa, ficha)
VALUES ('PRUEBA-A6', 'Aprendiz', 'Seis', '2005-01-01', 'a6@benefitrack.test', 'hash', @rol_aprendiz, @prog, '2900001');
SET @a6 = LAST_INSERT_ID();
INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (@a6, @conv2, '{}');
SET @r1 = LAST_INSERT_ID();
SET @app_id_usuario = 5;
UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion = @r1;
UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = @r1;
SELECT id_asignacion INTO @j1 FROM asignaciones_beneficio WHERE id_postulacion = @r1;
SELECT fecha_inicio INTO @f_conv2 FROM convocatorias WHERE id_convocatoria = @conv2;

CALL pr_esperar_error('J01', 'Fecha absurda: fecha_inicio de la asignación en 1900 se rechaza',
  "UPDATE asignaciones_beneficio SET fecha_inicio = '1900-01-01' WHERE id_asignacion = @j1",
  'La fecha de inicio de la asignación debe estar entre');
CALL pr_esperar_error('J02', 'Fecha absurda: fecha_inicio más de un año en el futuro se rechaza',
  "UPDATE asignaciones_beneficio SET fecha_inicio = CURDATE() + INTERVAL 2 YEAR WHERE id_asignacion = @j1",
  'La fecha de inicio de la asignación debe estar entre');
CALL pr_esperar_error('J03', 'Fecha absurda: fecha_inicio anterior al inicio de la convocatoria se rechaza',
  "UPDATE asignaciones_beneficio SET fecha_inicio = @f_conv2 - INTERVAL 1 DAY WHERE id_asignacion = @j1",
  'La fecha de inicio de la asignación debe estar entre');
CALL pr_esperar_exito('J04', 'fecha_inicio exactamente un año después de hoy se permite',
  "UPDATE asignaciones_beneficio SET fecha_inicio = CURDATE() + INTERVAL 1 YEAR WHERE id_asignacion = @j1");
CALL pr_esperar_exito('J05', 'fecha_inicio igual al inicio de la convocatoria se permite',
  "UPDATE asignaciones_beneficio SET fecha_inicio = @f_conv2 WHERE id_asignacion = @j1");
-- Ronda 5 (M5): la duplicada se reporta antes que la fecha. La fecha al
-- crear se prueba por la vía real (aprobación) en J30.
CALL pr_esperar_error('J06', 'Una postulación que ya tiene asignación responde eso antes que la fecha absurda',
  "INSERT INTO asignaciones_beneficio (id_postulacion, valor_pago, numero_pagos, duracion_meses, fecha_inicio) VALUES (@p1, 150000, 3, 3, '1900-01-01')",
  'La postulación ya tiene una asignación de beneficio');

-- Cambios de rol y estado de usuarios
SET @app_id_usuario = @a1;
CALL pr_esperar_error('J07', 'Escalada: un aprendiz no puede darse el rol de Administrador',
  "UPDATE usuarios SET id_rol = @rol_admin WHERE id_usuario = @a1", 'Solo un administrador activo puede');
CALL pr_esperar_exito('J08', 'Un aprendiz sí puede actualizar sus datos de contacto',
  "UPDATE usuarios SET telefono = '3001234567' WHERE id_usuario = @a1");
CALL pr_esperar_exito('J09', 'El bloqueo por intentos fallidos (-> Bloqueado) no exige administrador',
  "UPDATE usuarios SET estado = 'Bloqueado', intentos_fallidos = 3, fecha_bloqueo = NOW() WHERE id_usuario = @a1");
CALL pr_esperar_error('J10', 'Un aprendiz bloqueado no puede desbloquearse solo',
  "UPDATE usuarios SET estado = 'Activo', intentos_fallidos = 0 WHERE id_usuario = @a1", 'Solo un administrador activo puede');
CALL pr_esperar_error('J11', 'Escalada: un aprendiz no puede crear un usuario Administrador',
  "INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol) VALUES ('PRUEBA-FALSO', 'Falso', 'Admin', 'falso@benefitrack.test', 'hash', @rol_admin)",
  'Solo un administrador activo puede');
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('J12', 'Un administrador inactivo no puede reactivarse solo',
  "UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = @adm_inactivo", 'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('J13', 'Un administrador activo puede desbloquear a un aprendiz',
  "UPDATE usuarios SET estado = 'Activo', intentos_fallidos = 0, fecha_bloqueo = NULL WHERE id_usuario = @a1");
CALL pr_esperar_exito('J14', 'Un administrador activo puede reactivar a otro administrador',
  "UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = @adm_inactivo");
CALL pr_esperar_exito('J15', 'Un administrador activo puede crear un usuario Administrador',
  "INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol) VALUES ('PRUEBA-ADM2', 'Otro', 'Admin', 'adm2@benefitrack.test', 'hash', @rol_admin)");
SET @app_id_usuario = NULL;
CALL pr_esperar_exito('J16', 'El registro de un aprendiz nuevo (sin sesión) se permite',
  "INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol) VALUES ('PRUEBA-A7', 'Aprendiz', 'Siete', 'a7@benefitrack.test', 'hash', @rol_aprendiz)");

-- Desbloqueo automático a los 15 minutos (ronda 4). Aquí @a1 está Activo.
-- "Sin administrador" = la sesión es el propio aprendiz (@a1); con root y
-- NULL el control no aplica (D9), con benefitrack_app y NULL se prueba en
-- pruebas_usuario_aplicacion.sql.
SET @app_id_usuario = @a1;
CALL pr_esperar_exito('J17', 'Bloqueo por intentos: pasar a Bloqueado con fecha_bloqueo = ahora',
  "UPDATE usuarios SET estado = 'Bloqueado', intentos_fallidos = 3, fecha_bloqueo = NOW() WHERE id_usuario = @a1");
CALL pr_esperar_error('J18', 'Desbloqueo sin administrador antes de 15 minutos se rechaza',
  "UPDATE usuarios SET estado = 'Activo', intentos_fallidos = 0, fecha_bloqueo = NULL WHERE id_usuario = @a1",
  'Solo un administrador activo puede');
CALL pr_esperar_error('J19', 'Trampa: sin administrador no se puede adelantar fecha_bloqueo para desbloquear antes',
  "UPDATE usuarios SET fecha_bloqueo = NOW() - INTERVAL 1 DAY WHERE id_usuario = @a1",
  'Solo un administrador activo puede');
SET @app_id_usuario = 5;
UPDATE usuarios SET fecha_bloqueo = NOW() - INTERVAL 14 MINUTE WHERE id_usuario = @a1;
SET @app_id_usuario = @a1;
CALL pr_esperar_error('J20', 'Desbloqueo sin administrador a los 14 minutos se rechaza',
  "UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = @a1",
  'Solo un administrador activo puede');
SET @app_id_usuario = 5;
UPDATE usuarios SET fecha_bloqueo = NOW() - INTERVAL 15 MINUTE WHERE id_usuario = @a1;
SET @app_id_usuario = @a1;
CALL pr_esperar_exito('J21', 'Desbloqueo sin administrador a los 15 minutos o más se permite',
  "UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = @a1");
CALL pr_verificar('J22', 'Al desbloquear, intentos_fallidos vuelve a 0 y fecha_bloqueo a NULL',
  (SELECT estado = 'Activo' AND intentos_fallidos = 0 AND fecha_bloqueo IS NULL FROM usuarios WHERE id_usuario = @a1),
  (SELECT CONCAT('estado=', estado, ' intentos=', intentos_fallidos, ' fecha_bloqueo=', IFNULL(fecha_bloqueo, 'NULL'))
     FROM usuarios WHERE id_usuario = @a1));
CALL pr_esperar_exito('J23', 'Bloqueo sin fecha_bloqueo: la base la llena con la hora actual',
  "UPDATE usuarios SET estado = 'Bloqueado', intentos_fallidos = 3 WHERE id_usuario = @a1");
CALL pr_verificar('J24', 'El bloqueo deja fecha_bloqueo con la hora actual',
  (SELECT fecha_bloqueo >= NOW() - INTERVAL 1 MINUTE FROM usuarios WHERE id_usuario = @a1),
  (SELECT CONCAT('fecha_bloqueo=', IFNULL(fecha_bloqueo, 'NULL')) FROM usuarios WHERE id_usuario = @a1));
SET @app_id_usuario = 5;
CALL pr_esperar_exito('J25', 'Un administrador activo puede desbloquear en cualquier momento (antes de 15 minutos)',
  "UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = @a1");
CALL pr_verificar('J26', 'El desbloqueo del administrador también deja intentos en 0 y fecha_bloqueo en NULL',
  (SELECT estado = 'Activo' AND intentos_fallidos = 0 AND fecha_bloqueo IS NULL FROM usuarios WHERE id_usuario = @a1),
  (SELECT CONCAT('estado=', estado, ' intentos=', intentos_fallidos, ' fecha_bloqueo=', IFNULL(fecha_bloqueo, 'NULL'))
     FROM usuarios WHERE id_usuario = @a1));

-- =====================================================================
-- Bloque K. Hallazgos de la auditoría de la ronda 4 (ronda 5)
-- =====================================================================
-- M1: la asignación automática empieza en GREATEST(hoy, inicio de la
-- convocatoria). Convocatoria propia para poder mover su fecha_inicio.
SET @app_id_usuario = 5;
INSERT INTO convocatorias (id_beneficio, nombre, fecha_inicio, fecha_cierre, cupos, requisitos,
                           valor_pago, numero_pagos, duracion_meses, estado)
VALUES (@ben, 'Convocatoria 5 de pruebas', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 800 DAY, 5,
        'Ser aprendiz activo', 150000, 3, 3, 'Activa');
SET @conv5 = LAST_INSERT_ID();
SET @app_id_usuario = NULL;
INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol)
VALUES ('PRUEBA-A8', 'Aprendiz', 'Ocho', 'a8@benefitrack.test', 'hash', @rol_aprendiz);
SET @a8 = LAST_INSERT_ID();
INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol)
VALUES ('PRUEBA-A9', 'Aprendiz', 'Nueve', 'a9@benefitrack.test', 'hash', @rol_aprendiz);
SET @a9 = LAST_INSERT_ID();
INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (@a8, @conv5, '{}');
SET @r8 = LAST_INSERT_ID();
INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (@a9, @conv5, '{}');
SET @r9 = LAST_INSERT_ID();
SET @app_id_usuario = 5;
UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion IN (@r8, @r9);
UPDATE convocatorias SET fecha_inicio = CURDATE() + INTERVAL 10 DAY WHERE id_convocatoria = @conv5;
CALL pr_esperar_exito('K01', 'M1: aprobar con la convocatoria movida al futuro funciona',
  "UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = @r8");
CALL pr_verificar('K02', 'M1: la asignación empieza el día de inicio de la convocatoria (no hoy)',
  (SELECT fecha_inicio = CURDATE() + INTERVAL 10 DAY FROM asignaciones_beneficio WHERE id_postulacion = @r8),
  (SELECT CONCAT('fecha_inicio=', IFNULL(MAX(fecha_inicio), 'NULL')) FROM asignaciones_beneficio WHERE id_postulacion = @r8));
UPDATE convocatorias SET fecha_inicio = CURDATE() + INTERVAL 400 DAY WHERE id_convocatoria = @conv5;
CALL pr_esperar_error('K03', 'Fecha absurda: también se revisa al crear la asignación (convocatoria a más de un año)',
  "UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = @r9",
  'La fecha de inicio de la asignación debe estar entre');

-- B1: pasar a Bloqueado solo desde Activo sin administrador, y la fecha
-- de bloqueo siempre la pone la base.
UPDATE usuarios SET estado = 'Inactivo' WHERE id_usuario = @a1;
SET @app_id_usuario = @a1;
CALL pr_esperar_error('K04', 'B1: un Inactivo no puede pasar a Bloqueado sin administrador',
  "UPDATE usuarios SET estado = 'Bloqueado', fecha_bloqueo = '2000-01-01' WHERE id_usuario = @a1",
  'Solo un administrador activo puede');
SET @app_id_usuario = 5;
UPDATE usuarios SET estado = 'Inactivo' WHERE id_usuario = @adm_inactivo;
SET @app_id_usuario = @adm_inactivo;
CALL pr_esperar_error('K05', 'B1: un administrador inactivo no puede reactivarse pasando por Bloqueado',
  "UPDATE usuarios SET estado = 'Bloqueado', fecha_bloqueo = '2000-01-01' WHERE id_usuario = @adm_inactivo",
  'Solo un administrador activo puede');
CALL pr_verificar('K06', 'B1: el administrador inactivo sigue Inactivo',
  (SELECT estado = 'Inactivo' FROM usuarios WHERE id_usuario = @adm_inactivo),
  (SELECT CONCAT('estado=', estado) FROM usuarios WHERE id_usuario = @adm_inactivo));
SET @app_id_usuario = 5;
UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = @a1;
SET @app_id_usuario = @a1;
CALL pr_esperar_exito('K07', 'B1: Activo -> Bloqueado con fecha_bloqueo de ayer se acepta',
  "UPDATE usuarios SET estado = 'Bloqueado', intentos_fallidos = 3, fecha_bloqueo = NOW() - INTERVAL 1 DAY WHERE id_usuario = @a1");
CALL pr_verificar('K08', 'B1: la base ignora la fecha recibida y guarda la hora actual',
  (SELECT fecha_bloqueo >= NOW() - INTERVAL 1 MINUTE FROM usuarios WHERE id_usuario = @a1),
  (SELECT CONCAT('fecha_bloqueo=', IFNULL(fecha_bloqueo, 'NULL')) FROM usuarios WHERE id_usuario = @a1));
CALL pr_esperar_error('K09', 'B1: por eso el desbloqueo inmediato se rechaza',
  "UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = @a1",
  'Solo un administrador activo puede');

-- I1: una cuenta Bloqueada con fecha_bloqueo NULL no se desbloquea sola.
SET @app_id_usuario = 5;
UPDATE usuarios SET fecha_bloqueo = NULL WHERE id_usuario = @a1;
SET @app_id_usuario = @a1;
CALL pr_esperar_error('K10', 'I1: Bloqueado con fecha_bloqueo NULL no se desbloquea sin administrador',
  "UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = @a1",
  'Solo un administrador activo puede');
CALL pr_esperar_error('K11', 'I1: crear un usuario que no esté Activo exige administrador',
  "INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol, estado) VALUES ('PRUEBA-A10', 'Aprendiz', 'Diez', 'a10@benefitrack.test', 'hash', @rol_aprendiz, 'Bloqueado')",
  'Solo un administrador activo puede');
SET @app_id_usuario = 5;
CALL pr_esperar_exito('K12', 'I1: un administrador activo sí crea un usuario Bloqueado',
  "INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol, estado) VALUES ('PRUEBA-A11', 'Aprendiz', 'Once', 'a11@benefitrack.test', 'hash', @rol_aprendiz, 'Bloqueado')");
CALL pr_verificar('K13', 'I1: al crearlo Bloqueado sin fecha, la base pone la hora actual',
  (SELECT fecha_bloqueo >= NOW() - INTERVAL 1 MINUTE FROM usuarios WHERE documento = 'PRUEBA-A11'),
  (SELECT CONCAT('fecha_bloqueo=', IFNULL(fecha_bloqueo, 'NULL')) FROM usuarios WHERE documento = 'PRUEBA-A11'));
UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = @a1;
SET @app_id_usuario = 5;
SET @app_id_usuario = 5;

-- =====================================================================
-- Fin: deshacer los datos de prueba e imprimir el resumen
-- =====================================================================
ROLLBACK;
SET @app_id_usuario = NULL;

DROP PROCEDURE IF EXISTS pr_esperar_error;
DROP PROCEDURE IF EXISTS pr_esperar_exito;
DROP PROCEDURE IF EXISTS pr_verificar;

-- Totales en variables (una tabla temporal no se puede leer dos veces en
-- la misma consulta).
SELECT COUNT(*), SUM(resultado = 'OK'), SUM(resultado = 'FALLO')
  INTO @total_casos, @total_ok, @total_fallos
  FROM resultados_pruebas;

-- Resumen: una fila por caso y, al final, la fila TOTAL con el veredicto.
-- Es la última consulta del script para que phpMyAdmin la muestre.
SELECT caso, resultado, descripcion, detalle
  FROM (SELECT orden, caso, resultado, descripcion, detalle
          FROM resultados_pruebas
        UNION ALL
        SELECT 1000000,
               CONVERT('TOTAL' USING utf8mb4) COLLATE utf8mb4_unicode_ci,
               CONVERT(IF(@total_fallos = 0, 'OK', 'FALLO') USING utf8mb4) COLLATE utf8mb4_unicode_ci,
               CONVERT(CONCAT(@total_casos, ' casos: ', @total_ok, ' OK, ', @total_fallos, ' FALLO')
                       USING utf8mb4) COLLATE utf8mb4_unicode_ci,
               CONVERT(IF(@total_fallos = 0, 'TODAS LAS PRUEBAS PASAN', 'HAY PRUEBAS QUE FALLAN')
                       USING utf8mb4) COLLATE utf8mb4_unicode_ci) AS resumen
 ORDER BY orden;
