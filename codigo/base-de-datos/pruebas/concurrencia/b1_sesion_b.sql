-- B1, sesión B: empieza 1 s después de A (su transacción ya "leyó" la base
-- antes del COMMIT de A) e intenta aprobar la postulación 2 del mismo cupo.
USE sibea;
SET @app_id_usuario = 5;
SELECT SLEEP(1) AS espera;
START TRANSACTION WITH CONSISTENT SNAPSHOT;
SELECT 'B: intenta aprobar la postulación 2 (queda esperando a A)' AS sesion_b;
UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = 2;
COMMIT;
SELECT 'B: COMMIT hecho' AS sesion_b;
