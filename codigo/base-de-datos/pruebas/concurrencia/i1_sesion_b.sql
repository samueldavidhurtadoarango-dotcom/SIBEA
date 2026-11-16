-- I1, sesión B: 1 s después registra el pago 2 de 2 (el último).
USE sibea;
SET @app_id_usuario = 5;
SELECT id_asignacion INTO @asig FROM asignaciones_beneficio WHERE id_postulacion = 3;
SELECT SLEEP(1) AS espera;
START TRANSACTION WITH CONSISTENT SNAPSHOT;
SELECT 'B: registra el pago 2 (queda esperando a A)' AS sesion_b;
INSERT INTO pagos (id_asignacion, numero_pago, monto, fecha_pago) VALUES (@asig, 2, 100000, CURDATE());
COMMIT;
SELECT 'B: COMMIT hecho' AS sesion_b;
