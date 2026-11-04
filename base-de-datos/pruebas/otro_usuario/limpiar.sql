-- Borra lo que creó la prueba (se ejecuta como root y con --force, para
-- que cada paso se intente aunque el anterior falle). Primero el usuario.
DROP USER IF EXISTS 'benefitrack_otro'@'localhost';
DROP USER IF EXISTS 'benefitrack_otro'@'127.0.0.1';
SET NAMES utf8mb4;
USE benefitrack_v2;
DELETE FROM usuarios WHERE documento = 'PRUEBA-OTRO';
SELECT 'Usuario temporal y aprendiz de prueba borrados' AS limpieza;
