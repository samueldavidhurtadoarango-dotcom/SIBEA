-- Prepara la prueba de la lista blanca (se ejecuta como root).
SET NAMES utf8mb4;
USE benefitrack_v2;
CREATE USER IF NOT EXISTS 'benefitrack_otro'@'localhost' IDENTIFIED BY 'Otro_Prueba_1';
GRANT SELECT, UPDATE ON benefitrack_v2.usuarios TO 'benefitrack_otro'@'localhost';
GRANT SELECT ON benefitrack_v2.roles TO 'benefitrack_otro'@'localhost';
SET @app_id_usuario = NULL;
DELETE FROM usuarios WHERE documento = 'PRUEBA-OTRO';
INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol)
VALUES ('PRUEBA-OTRO', 'Aprendiz', 'Otro', 'otro@benefitrack.test', 'hash',
        (SELECT id_rol FROM roles WHERE nombre = 'Aprendiz'));
