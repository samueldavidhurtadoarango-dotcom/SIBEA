# BenefitRack · aplicación web (MVP)

Aplicación en PHP puro con el patrón MVC, sobre la base de datos MariaDB `benefitrack_v2`
(ver `../base-de-datos/README.md`). Es un proyecto del SENA (ADSO): cada línea está pensada
para poder explicarse.

## Cómo instalarla (primera versión)

1. **Base de datos.** En XAMPP, cargar en este orden `../base-de-datos/benefitrack_v2.sql`,
   `datos_iniciales.sql` y `usuario_aplicacion.sql`. Cambiar la clave del usuario `benefitrack_app`
   y crear el primer administrador como explica el README de la base (sección 1).
2. **Dependencias.** Desde esta carpeta: `php composer.phar install` (necesita PHP 8.2 con las
   extensiones `zip` y `gd`).
3. **Configuración.** Copiar `config/config.ejemplo.php` como `config/config.local.php` y poner la
   clave real de `benefitrack_app`. Ese archivo no se sube a git.
4. **Probarla.** `php -S 127.0.0.1:8080 -t public` y abrir <http://127.0.0.1:8080>.
   El servidor de XAMPP (Apache) debe apuntar a la carpeta `public/`.

## Las carpetas

| Carpeta | Qué hay |
|---|---|
| `public/` | Lo único que ve el navegador: `index.php` (entrada), `css/` y `js/` (Bootstrap 5.3.3 y estilos propios). |
| `src/Nucleo/` | El motor: `Peticion`, `Enrutador`, `Aplicacion`, `Respuesta`, `Vista`, `Sesion`, `Csrf`, `BaseDatos`, `Fabrica`, errores. |
| `src/Filtros/` | Los guardias que revisan la petición antes del controlador: sesión, rol, CSRF y primer ingreso. |
| `src/Controladores/`, `src/Servicios/`, `src/Repositorios/`, `src/Validacion/` | Se llenan en las tareas siguientes: controladores, reglas de negocio, consultas SQL y validación. |
| `vistas/` | Todo el HTML: `plantillas/` (marcos público, administrador y aprendiz), `parciales/` y `errores/`. |
| `config/` | `config.ejemplo.php` (modelo) y `config.local.php` (el real, fuera de git). |
| `almacenamiento/` | Documentos subidos, fuera de `public/` para que nadie los abra por URL. |
| `pruebas/` | `Unidad/` (sin base de datos) e `Integracion/` (con una MariaDB temporal). |

## El recorrido de una petición

`index.php` → `Peticion` → `Enrutador` → **filtros** (sesión, rol, CSRF) → controlador (lo arma la
`Fabrica`) → `Vista` → `Respuesta`. Si algo falla sin esperarlo se muestra la página 500.

## Seguridad que ya está puesta

- Cookie de sesión `HttpOnly`, `SameSite=Lax`, modo estricto, y el id de sesión se renueva al ingresar.
- Todo formulario que cambia datos es `POST` con token `_csrf` (`Csrf`, comparado con `hash_equals`).
- Todo dato que se dibuja pasa por `e()` (escape contra XSS).
- La conexión usa `benefitrack_app` (nunca `root`) con consultas preparadas de verdad.
- Los errores técnicos no se muestran: van al log; la persona ve un mensaje sencillo.

## Pruebas y calidad

Desde esta carpeta (PHP de XAMPP):

```
php -d extension=zip -d extension=gd vendor/bin/phpunit
php vendor/bin/phpstan analyse --memory-limit=512M
php vendor/bin/php-cs-fixer fix --dry-run --diff
```

Las pruebas de integración solo corren si existen las variables `BR_PRUEBA_BD_PUERTO` (por ejemplo
`3398`) y `BR_PRUEBA_MYSQL` (ruta de `mysql.exe`) y apuntan a una MariaDB **temporal**, nunca a
la base real de XAMPP. Sin ellas se saltan y la prueba dice por qué.
