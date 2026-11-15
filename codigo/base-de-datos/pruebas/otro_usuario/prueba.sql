-- Se ejecuta conectado como sibea_otro (ni root ni sibea_app).
-- Con @app_id_usuario NULL, darse el rol de Administrador debe fallar con
-- «Solo un administrador activo puede ...» (el ERROR 1644 es el esperado).
SET NAMES utf8mb4;
USE sibea;
SELECT CURRENT_USER() AS conectado_como;
SET @app_id_usuario = NULL;
UPDATE usuarios SET id_rol = (SELECT id_rol FROM roles WHERE nombre = 'Administrador')
 WHERE documento = 'PRUEBA-OTRO';
SELECT r.nombre AS rol_final,
       IF(r.nombre = 'Aprendiz', 'CORRECTO: con NULL y un usuario distinto de root se rechaza',
                                 'INCORRECTO: la lista blanca no funcionó') AS veredicto
  FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
 WHERE u.documento = 'PRUEBA-OTRO';
