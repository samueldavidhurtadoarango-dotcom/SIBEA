-- B1, sesión A: aprueba la postulación 1 y tarda 4 segundos en confirmar.
USE sibea;
SET @app_id_usuario = 5;
START TRANSACTION;
UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = 1;
SELECT 'A: aprobó la postulación 1, espera 4 s antes del COMMIT' AS sesion_a;
SELECT SLEEP(4) AS espera;
COMMIT;
SELECT 'A: COMMIT hecho' AS sesion_a;
