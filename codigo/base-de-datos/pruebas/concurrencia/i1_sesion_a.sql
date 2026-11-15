-- I1, sesión A: registra el pago 1 de 2 y tarda 4 segundos en confirmar.
USE sibea;
SET @app_id_usuario = 5;
SELECT id_asignacion INTO @asig FROM asignaciones_beneficio WHERE id_postulacion = 3;
START TRANSACTION;
INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig, 1, 100000, CURDATE());
SELECT 'A: registró el pago 1, espera 4 s antes del COMMIT' AS sesion_a;
SELECT SLEEP(4) AS espera;
COMMIT;
SELECT 'A: COMMIT hecho' AS sesion_a;
