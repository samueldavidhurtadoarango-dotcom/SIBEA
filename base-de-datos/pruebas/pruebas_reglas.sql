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

CALL pr_verificar('A02', 'CA1: existen los 19 triggers esperados',
  (SELECT COUNT(*) FROM information_schema.triggers
    WHERE trigger_schema = DATABASE()
      AND trigger_name IN ('tr_validacion_documento','tr_postulacion_inicial','tr_postulacion_creada',
                           'tr_postulacion_validar','tr_postulacion_estado','tr_auditoria_postulacion',
                           'tr_postulacion_notificar','tr_postulacion_crear_asignacion',
                           'tr_solicitud_documentacion_validar','tr_asignacion_validar_insercion',
                           'tr_asignacion_validar_estado','tr_asignacion_cambio_estado','tr_pago_validar',
                           'tr_pago_registrado','tr_pago_inmutable','tr_pago_anulado','tr_pago_no_eliminar',
                           'tr_novedad_preparar','tr_novedad_registrada')) = 19,
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

-- =====================================================================
-- Datos de prueba (dentro de una transacción que se deshace al final)
-- =====================================================================
START TRANSACTION;

SET @app_id_usuario = NULL;
SELECT id_rol INTO @rol_admin    FROM roles WHERE nombre = 'Administrador';
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
VALUES (@ben, 'Convocatoria de pruebas', '2026-01-01', '2026-12-31', 2, 'Ser aprendiz activo',
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
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_programada, fecha_pago) VALUES (@asig1, 1, 150000, '2026-02-01', '2026-02-01')");

CALL pr_verificar('D02', 'D4/D9: el pago guarda el usuario que lo registró (@app_id_usuario)',
  (SELECT id_usuario_registro FROM pagos WHERE id_asignacion = @asig1 AND numero_pago = 1) = 5,
  (SELECT CONCAT('id_usuario_registro=', IFNULL(id_usuario_registro, 'NULL'))
     FROM pagos WHERE id_asignacion = @asig1 AND numero_pago = 1));

CALL pr_esperar_error('D03', 'CA5: un segundo pago Registrado con el mismo número se rechaza',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 1, 150000, '2026-02-02')",
  'Ya existe un pago registrado');

CALL pr_esperar_error('D04', 'D4: el número de pago no puede superar el total de pagos (3)',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 4, 150000, '2026-02-02')",
  'supera el total de pagos');

CALL pr_esperar_error('D05', 'D4: el número de pago debe ser mayor que cero',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 0, 150000, '2026-02-02')",
  'mayor que cero');

CALL pr_esperar_error('D06', 'D4: el monto debe ser mayor que cero',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 2, 0, '2026-03-01')",
  'chk_pago_monto');

CALL pr_esperar_error('D07', 'CA6: pago con retraso y sin motivo se rechaza',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_programada, fecha_pago, hubo_retraso) VALUES (@asig1, 2, 150000, '2026-03-01', '2026-03-10', 1)",
  'motivo del retraso es obligatorio');

CALL pr_esperar_error('D08', 'CA6: pago con retraso y motivo en blanco se rechaza',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_programada, fecha_pago, hubo_retraso, motivo_retraso) VALUES (@asig1, 2, 150000, '2026-03-01', '2026-03-10', 1, '  ')",
  'motivo del retraso es obligatorio');

CALL pr_esperar_exito('D09', 'D4: pago con retraso y motivo se acepta',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_programada, fecha_pago, hubo_retraso, motivo_retraso) VALUES (@asig1, 2, 150000, '2026-03-01', '2026-03-10', 1, 'Demora en el giro de la institución')");

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
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_programada, fecha_pago, hubo_retraso, motivo_retraso) VALUES (@asig1, 2, 150000, '2026-03-01', '2026-03-10', 1, 'Demora en el giro de la institución')");

CALL pr_verificar('D17', 'CA5: el pago 2 queda con 1 Anulado y 1 Registrado',
  (SELECT COUNT(*) FROM pagos WHERE id_asignacion = @asig1 AND numero_pago = 2 AND estado = 'Anulado') = 1
  AND (SELECT COUNT(*) FROM pagos WHERE id_asignacion = @asig1 AND numero_pago = 2 AND estado = 'Registrado') = 1,
  (SELECT GROUP_CONCAT(estado) FROM pagos WHERE id_asignacion = @asig1 AND numero_pago = 2));

CALL pr_esperar_error('D18', 'D4: un pago anulado no se puede volver a Registrado',
  "UPDATE pagos SET estado = 'Registrado' WHERE id_asignacion = @asig1 AND numero_pago = 2 AND estado = 'Anulado'",
  'no se pueden modificar');

CALL pr_esperar_exito('D19', 'D4: registrar el 3.er pago se acepta',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 3, 150000, '2026-04-01')");

CALL pr_verificar('D20', 'CA4: con el 3.er pago vigente la asignación pasa a Finalizado',
  (SELECT estado = 'Finalizado' AND fecha_fin IS NOT NULL
     FROM asignaciones_beneficio WHERE id_asignacion = @asig1),
  (SELECT CONCAT(estado, ' fecha_fin=', IFNULL(fecha_fin, 'NULL'))
     FROM asignaciones_beneficio WHERE id_asignacion = @asig1));

CALL pr_esperar_error('D21', 'D4: no se registran pagos en una asignación Finalizado',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 1, 150000, '2026-04-02')",
  'asignaciones En curso');

CALL pr_esperar_exito('D22', 'D4: anular el último pago de una asignación finalizada se acepta',
  "UPDATE pagos SET estado = 'Anulado', motivo_anulacion = 'Fecha equivocada' WHERE id_asignacion = @asig1 AND numero_pago = 3 AND estado = 'Registrado'");

CALL pr_verificar('D23', 'D3: al anular el último pago la asignación vuelve a En curso',
  (SELECT estado FROM asignaciones_beneficio WHERE id_asignacion = @asig1) = 'En curso',
  (SELECT estado FROM asignaciones_beneficio WHERE id_asignacion = @asig1));

CALL pr_esperar_exito('D24', 'D4: registrar de nuevo el pago 3 se acepta',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig1, 3, 150000, '2026-04-03')");

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
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig3, 1, 150000, '2026-02-01')",
  'asignaciones En curso');

CALL pr_esperar_exito('E06', 'D3: reactivar (Suspendido -> En curso) con motivo se acepta',
  "UPDATE asignaciones_beneficio SET estado = 'En curso', motivo_estado = 'Se justificó la inasistencia' WHERE id_asignacion = @asig3");

CALL pr_verificar('E07', 'D5: al reactivar se crea la novedad Reactivación',
  (SELECT COUNT(*) FROM novedades WHERE id_asignacion = @asig3 AND tipo = 'Reactivación') = 1,
  (SELECT GROUP_CONCAT(tipo) FROM novedades WHERE id_asignacion = @asig3));

CALL pr_esperar_exito('E08a', 'Preparación: pago 1 de la asignación reactivada',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig3, 1, 150000, '2026-02-01')");
CALL pr_esperar_exito('E08b', 'Preparación: pago 2 de la asignación reactivada',
  "INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig3, 2, 150000, '2026-03-01')");

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
-- Fin: deshacer los datos de prueba e imprimir el resumen
-- =====================================================================
ROLLBACK;
SET @app_id_usuario = NULL;

DROP PROCEDURE IF EXISTS pr_esperar_error;
DROP PROCEDURE IF EXISTS pr_esperar_exito;
DROP PROCEDURE IF EXISTS pr_verificar;

SELECT caso, resultado, descripcion, detalle
  FROM resultados_pruebas
 ORDER BY orden;

SELECT COUNT(*)                   AS total_casos,
       SUM(resultado = 'OK')      AS ok,
       SUM(resultado = 'FALLO')   AS fallos,
       IF(SUM(resultado = 'FALLO') = 0, 'TODAS LAS PRUEBAS PASAN', 'HAY PRUEBAS QUE FALLAN') AS veredicto
  FROM resultados_pruebas;
