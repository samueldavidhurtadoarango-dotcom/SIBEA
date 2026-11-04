-- Datos para las pruebas de concurrencia (se guardan de verdad: COMMIT).
-- Ejecutar sobre una base recién creada con benefitrack_v2.sql.
SET NAMES utf8mb4;
USE benefitrack_v2;
SET @app_id_usuario = NULL;
INSERT INTO usuarios (id_usuario, documento, nombre, apellido, correo, password_hash, id_rol)
VALUES (5, 'CONC-ADM', 'Ana', 'Admin', 'adm@conc.test', 'hash', 1),
       (21, 'CONC-A1', 'Aprendiz', 'Uno', 'a1@conc.test', 'hash', 2),
       (22, 'CONC-A2', 'Aprendiz', 'Dos', 'a2@conc.test', 'hash', 2),
       (23, 'CONC-A3', 'Aprendiz', 'Tres', 'a3@conc.test', 'hash', 2);
INSERT INTO beneficios (id_beneficio, id_institucion, nombre, descripcion, categoria)
VALUES (1, 1, 'Beneficio concurrencia', 'Prueba', 'Alimentación');
-- Convocatoria 1: 1 solo cupo (prueba B1). Convocatoria 2: 2 pagos (prueba I1).
INSERT INTO convocatorias (id_convocatoria, id_beneficio, nombre, fecha_inicio, fecha_cierre, cupos,
                           requisitos, valor_pago, numero_pagos, duracion_meses, estado)
VALUES (1, 1, 'Un cupo', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 10 DAY, 1, 'r', 100000, 2, 2, 'Activa'),
       (2, 1, 'Dos pagos', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 10 DAY, 5, 'r', 100000, 2, 2, 'Activa');
INSERT INTO postulaciones (id_postulacion, id_usuario, id_convocatoria)
VALUES (1, 21, 1), (2, 22, 1), (3, 23, 2);
SET @app_id_usuario = 5;
UPDATE postulaciones SET estado = 'Validada';
UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = 3;  -- crea la asignación de la prueba I1
SELECT 'Preparación lista' AS mensaje;
