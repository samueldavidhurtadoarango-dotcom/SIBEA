-- Borra lo que creó preparar.sql (se ejecuta como root).
SET NAMES utf8mb4;
USE benefitrack_v2;
DELETE FROM usuarios WHERE documento = 'PRUEBA-OTRO';
DROP USER IF EXISTS 'benefitrack_otro'@'localhost';
SELECT 'Usuario temporal y aprendiz de prueba borrados' AS limpieza;
