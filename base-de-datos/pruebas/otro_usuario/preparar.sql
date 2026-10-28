-- Prepara la prueba de la lista blanca (se ejecuta como root, después de
-- que ejecutar.sh creó el usuario benefitrack_otro con una clave aleatoria).
-- Permisos mínimos: solo lo que usa prueba.sql.
SET NAMES utf8mb4;
USE benefitrack_v2;
GRANT SELECT (id_usuario, documento, id_rol), UPDATE (id_rol) ON benefitrack_v2.usuarios
  TO 'benefitrack_otro'@'localhost', 'benefitrack_otro'@'127.0.0.1';
GRANT SELECT (id_rol, nombre) ON benefitrack_v2.roles
  TO 'benefitrack_otro'@'localhost', 'benefitrack_otro'@'127.0.0.1';
SET @app_id_usuario = NULL;
DELETE FROM usuarios WHERE documento = 'PRUEBA-OTRO';
INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol)
VALUES ('PRUEBA-OTRO', 'Aprendiz', 'Otro', 'otro@benefitrack.test', 'hash',
        (SELECT id_rol FROM roles WHERE nombre = 'Aprendiz'));
