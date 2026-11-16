# SIBEA — Sistema de Información de Beneficios y Apoyos (aplicación web, MVP 3.0.0)

SIBEA es el Sistema de Información de Beneficios y Apoyos. El código PHP (espacio de nombres `SIBEA`) y los
archivos de la aplicación ya usan ese nombre; solo la carpeta del proyecto conserva el nombre de trabajo anterior.

Aplicación en PHP puro con el patrón MVC, sobre la base de datos MariaDB `sibea`
(ver `../base-de-datos/README.md`). Es un proyecto del SENA (ADSO): cada línea está pensada
para poder explicarse.

**Qué hace.** El administrador de Bienestar deja de copiar a mano cada respuesta al Excel:
crea cuentas de aprendices (de a una o desde un Excel), abre convocatorias, revisa las postulaciones
(datos y documentos), las aprueba o rechaza y descarga el Excel. El aprendiz entra, se postula
en la convocatoria abierta y ve en «Mi proceso» cómo va su postulación.

---

## 1. Instalarla en XAMPP, paso a paso

Piensa en esto como armar una casa: primero los cimientos (la base de datos), luego los muebles
(las dependencias) y al final las llaves (la configuración y el primer administrador).

### 1.1 Aviso de seguridad (léelo antes)

El PHP que trae XAMPP aquí es el **8.2.12**, que tiene una falla grave conocida («PHP2Shell»,
CVE-2026-109618 y CVE-2026-109619). Actualiza a **PHP 8.2.35 o más nuevo** descargándolo solo de la
página oficial <https://windows.php.net/download/> (versión *Thread Safe* x64 si usas Apache de
XAMPP). Mientras no lo hagas, **no dejes Apache encendido en redes compartidas** (wifi del
centro, cafés): usa solo tu computador o el servidor de prueba de PHP (sección 1.5).

### 1.2 La base de datos (un solo archivo)

Abre el panel de XAMPP, enciende **MySQL** y entra a phpMyAdmin (o usa la consola `mysql`).
Carga **un solo archivo**, una sola vez y como `root`: `../base-de-datos/sibea.sql`
(en phpMyAdmin, pestaña **Importar**). Ese archivo lo trae todo, como una caja con todas las piezas:

- crea las tablas, los triggers y las reglas (los cimientos);
- carga beneficios, bancos, EPS y programas de ejemplo (sin esto no se puede crear una convocatoria
  ni llenar el formulario);
- crea el usuario `sibea_app`, con el que la aplicación se conecta (la aplicación **nunca**
  usa `root`).

> **Cambia la contraseña del usuario de la aplicación.** El script trae una clave de ejemplo
> (`CAMBIE_ESTA_CLAVE`). Antes de usar la aplicación de verdad, cámbiala en phpMyAdmin
> (Cuentas de usuario → `sibea_app` → Cambiar contraseña) o con
> `ALTER USER 'sibea_app'@'localhost' IDENTIFIED BY 'una-clave-larga-y-propia';`
> y escribe esa misma clave en el paso 1.4.

### 1.3 PHP y Composer

1. Abre el archivo `C:\xampp\php\php.ini` y haz estos cambios (borra el `;` del inicio de la línea
   para «encender» una extensión):
   - `extension=zip`  (para leer y crear archivos Excel)
   - `extension=gd`   (para el Excel)
   - `upload_max_filesize` debe valer **al menos `5M`** (cada documento pesa máximo 5 MB) y `post_max_size`
     **al menos `16M`** (el formulario lleva 3 documentos a la vez). Si ya valen más (XAMPP trae `40M`),
     **déjalos como están**: no hay que bajarlos.
2. **Reinicia Apache** en el panel de XAMPP (si no, PHP no lee los cambios).
3. **Consigue Composer** (el instalador de librerías de PHP) si no lo tienes: descarga el instalador oficial de
   <https://getcomposer.org/download/> (Composer-Setup.exe en Windows; en la instalación, elige el `php.exe` de
   `C:\xampp\php`). En el computador del proyecto ya hay un `composer.phar` dentro de Laravel Herd:
   `C:\Users\samue\.config\herd\bin\composer.phar` (se usa como `php C:\Users\samue\.config\herd\bin\composer.phar ...`).
4. Desde la carpeta `aplicacion/`: `composer install` (o `php ruta\a\composer.phar install`). Descarga la
   librería del Excel y las herramientas de prueba a `vendor/` (esa carpeta no se sube a git).

### 1.4 La configuración

1. Copia `config/config.ejemplo.php` y llama a la copia `config/config.local.php`.
2. Ábrelo y pon los datos reales: host `127.0.0.1`, puerto `3306` (el de tu XAMPP), base
   `sibea`, usuario `sibea_app` y **la clave que elegiste** en el paso 1.2.
   Deja `depuracion` en `false` (en `true` se muestran detalles de los errores: solo en tu computador).
3. `config.local.php` no se sube a git porque tiene una clave.
4. **Zona horaria.** `app.zona_horaria` (por defecto `America/Bogota`) fija la hora de PHP al arrancar. Importa
   porque XAMPP trae `Europe/Berlin` en `php.ini`: sin este ajuste, las horas que escribe PHP irían 6 horas
   corridas respecto a Colombia. Si no pones la clave, se usa `America/Bogota`; un nombre inventado hace
   fallar el arranque con un mensaje claro.
5. **Duración de la sesión.** `app.sesion_inactividad_minutos` (30) cierra la sesión tras ese tiempo sin
   actividad y `app.sesion_duracion_maxima_horas` (8) la cierra aunque se siga usando. Al expirar, la persona
   vuelve a `/ingresar` con el aviso «Tu sesión expiró por seguridad. Vuelve a ingresar.»
6. **En producción, `display_errors=Off`** en `php.ini` (así PHP nunca pinta rutas ni claves en la página). Los
   errores van al registro del servidor (`error_log`) y, si el arranque falla, la persona solo ve una página
   «Algo salió mal» sin detalles.

### 1.5 Servir la aplicación (importante: la carpeta pública)

El navegador **solo debe poder ver `aplicacion/public/`**. Allí están `index.php`, `css/` y `js/`.
Todo lo demás (`src/`, `config/`, `almacenamiento/` con los documentos de los aprendices) queda
fuera del alcance del navegador. Por eso Apache debe tener como carpeta raíz (`DocumentRoot`)
exactamente `aplicacion/public`.

**Opción A: Apache de XAMPP.** Edita `C:\xampp\apache\conf\extra\httpd-vhosts.conf` y agrega
(cambia la ruta por la de tu computador):

```apache
Listen 8080
<VirtualHost *:8080>
    ServerName localhost
    DocumentRoot "C:/Users/TU_USUARIO/SIBEA/codigo/aplicacion/public"
    <Directory "C:/Users/TU_USUARIO/SIBEA/codigo/aplicacion/public">
        Require all granted
        # Toda dirección que no sea un archivo real (css, js) la atiende index.php.
        FallbackResource /index.php
    </Directory>
</VirtualHost>
```

Para que Apache lea este archivo, en `C:\xampp\apache\conf\httpd.conf` la línea
`Include conf/extra/httpd-vhosts.conf` **no debe estar comentada** (sin `#` al inicio; en XAMPP suele venir así).
Reinicia Apache y abre <http://localhost:8080>.

**Opción B: el servidor de prueba de PHP** (la más simple para estudiar):

```
cd codigo\aplicacion
php -S 127.0.0.1:8080 -t public
```

y abre <http://127.0.0.1:8080>. Se apaga con `Ctrl + C`.

### 1.6 El primer administrador

La base nace sin usuarios, y crear un administrador exige que ya exista otro. El primero se crea
por consola, desde `aplicacion/`:

```
php herramientas/crear_administrador.php
```

Pide documento, nombre, apellido, correo y contraseña (las mismas 4 reglas de la aplicación: mínimo 8
caracteres, una mayúscula, una minúscula y un número; la pide dos veces) y la clave de `root` de
MariaDB (en XAMPP suele estar vacía: Enter). Usa el host, puerto y base de `config/config.local.php`.
Se conecta como `root` con `@app_id_usuario = NULL` (el único caso en que la base lo permite) y guarda
solo el hash de la contraseña. Después entras en `/ingresar` y creas las demás cuentas desde
**Usuarios**. Ojo: en esa consola la contraseña **se ve mientras la escribes** (PHP en Windows no puede ocultarla de forma fiable): hazlo a solas.

---

## 2. El recorrido de una petición (el ejemplo del login)

Una petición es lo que pasa cada vez que el navegador pide algo. Piénsalo como pedir comida en un
restaurante: el mesero (`index.php`) recibe el pedido, el jefe de sala (`Aplicacion`) mira la carta
(`Enrutador`), los guardias de la puerta (filtros) revisan que puedas pasar, la cocina (controlador y
servicio) prepara el plato, la bodega (repositorio y BD) entrega los ingredientes y el mesero lleva el
plato a tu mesa (`Vista` y `Respuesta`).

**El ejemplo:** Luisa escribe su correo y contraseña en `/ingresar` y pulsa «Ingresar» (un `POST`).

| # | Archivo | Qué pasa aquí |
|---|---|---|
| 1 | `public/index.php` | Es la **única puerta**. Carga las librerías, lee `config/config.local.php`, arma la `Peticion` con lo que mandó el navegador y crea la `Fabrica`, el `Enrutador` y la `Aplicacion`. |
| 2 | `src/Nucleo/Aplicacion.php` | El **director**. Llama `ejecutar()`: pide al enrutador la ruta, corre los filtros, llama al controlador y, si algo se rompe, muestra la página 500 sin filtrar detalles. |
| 3 | `src/Nucleo/Enrutador.php` + `rutas.php` | **Busca en la tabla de rutas.** `POST /ingresar` pertenece a `AccesoControlador::ingresar` y lleva un filtro (CSRF). Si ninguna ruta coincide: página 404. |
| 4 | `src/Filtros/FiltroCsrf.php` | El **guardia**. Todo `POST` debe traer el token secreto `_csrf` que la página puso en el formulario; así se sabe que el envío vino de *nuestra* página y no de un sitio engañoso. Sin token válido: 403 (en `/ingresar` vuelve al formulario con un aviso). Otras rutas suman `FiltroSesion` (¿hay sesión?), `FiltroRol` (¿es administrador o aprendiz?) y `FiltroPrimerIngreso` (¿ya cambió su contraseña temporal?). |
| 5 | `src/Nucleo/Fabrica.php` | La **fábrica** arma el controlador con todo lo que necesita (sesión, vista, servicio, repositorio y la conexión a la BD). Así ninguna clase crea sus propias herramientas: se las entregan. |
| 6 | `src/Controladores/AccesoControlador.php` | El **recepcionista**: lee el correo y la clave de la `Peticion`, revisa lo mínimo (¿parece un correo?, ¿la clave no está vacía?) y le pasa el trabajo real al servicio. **No** tiene reglas de negocio. |
| 7 | `src/Servicios/ServicioAcceso.php` | La **regla de negocio**: busca al usuario, verifica la contraseña con `password_verify`, cuenta los intentos fallidos (3 bloquean la cuenta 15 minutos) y decide: entra, datos incorrectos, cuenta bloqueada o inactiva. Responde lo mismo si el correo no existe o la clave es mala, para no revelar qué correos están registrados. |
| 8 | `src/Repositorios/RepositorioUsuarios.php` | La **única** que escribe SQL del usuario, siempre con consultas preparadas (`?`), nunca pegando texto del usuario dentro del SQL. |
| 9 | `src/Nucleo/BaseDatos.php` → MariaDB | Abre la conexión con el usuario `sibea_app`. En cada petición manda `SET @app_id_usuario = ?, @app_ip = ?` para que los triggers de la BD sepan **quién** hace el cambio y lo anoten en la auditoría. En el login todavía no hay nadie con sesión, así que el id va como `NULL` (`Fabrica::baseDatosParaIngreso()`); es el único caso permitido. |
| 10 | `AccesoControlador::abrirSesion()` + `src/Nucleo/Sesion.php` | Si entró: se guarda en la sesión quién es (y se **renueva el id de sesión** para que nadie reutilice uno viejo) y se redirige: administrador a `/admin/postulaciones`, aprendiz a `/mi-proceso`, y a `/contrasena/nueva` si tiene contraseña temporal. |
| 11 | `src/Nucleo/Vista.php` + `vistas/acceso/ingresar.php` | Si **no** entró, se dibuja el formulario otra vez con su mensaje. El HTML vive **solo** en `vistas/`, y todo dato que se dibuja pasa por `e()` (escapa los símbolos peligrosos contra XSS). |
| 12 | `src/Nucleo/Respuesta.php` | Empaqueta el resultado (código 200, 302 de redirección, 403...). `index.php` llama `->enviar()` y el navegador recibe la página. Fin del recorrido. |

Si el login fuera otra pantalla (por ejemplo, aprobar una postulación), cambian las piezas del medio
pero el camino es el mismo: puerta → director → tabla de rutas → filtros → fábrica → controlador →
servicio → repositorio → BD → vista → respuesta.

---

## 3. Qué hay en cada carpeta

| Carpeta | Qué hay |
|---|---|
| `public/` | Lo único que ve el navegador: `index.php` (la puerta), `css/` y `js/` (Bootstrap 5.3.3 y estilos propios). |
| `rutas.php` | La tabla de rutas: qué URL atiende qué controlador y con qué filtros. |
| `src/Nucleo/` | El motor: `Peticion`, `Enrutador`, `Aplicacion`, `Respuesta`, `Vista`, `Sesion`, `Csrf`, `BaseDatos`, `Fabrica`, `Configuracion`, errores y funciones sueltas (`e()`, formatos de fecha y pesos). |
| `src/Filtros/` | Los guardias: sesión, rol, CSRF y primer ingreso. |
| `src/Controladores/` | Un controlador por tema (acceso, contraseña, usuarios, carga de usuarios, convocatorias, postulaciones del administrador, postulación del aprendiz, mi proceso, documentos). Reciben la petición y devuelven una respuesta. |
| `src/Servicios/` | Las reglas de negocio (qué se puede hacer y qué no): acceso, contraseñas, usuarios, carga desde Excel, convocatorias, postulación, revisión, mi proceso, exportación a Excel, almacenamiento de documentos. |
| `src/Repositorios/` | Todo el SQL, siempre preparado. Un repositorio por grupo de tablas. |
| `src/Validacion/` | Los validadores de cada formulario (usuario, contraseña, convocatoria, postulación, archivos). |
| `vistas/` | Todo el HTML: `plantillas/` (marcos público, administrador y aprendiz), `parciales/`, `errores/` y una carpeta por pantalla. |
| `config/` | `config.ejemplo.php` (modelo) y `config.local.php` (el real, fuera de git). |
| `almacenamiento/` | `documentos/` (los PDF, JPG y PNG de los aprendices, con nombre aleatorio) y `temporal/`. Está **fuera de `public/`**: nadie los abre por URL; se descargan solo a través de `/documentos/{id}`, que revisa quién eres. |
| `herramientas/` | Scripts de consola: `crear_administrador.php` y `revisar_comentarios.php` (comprueba que todo archivo y método público tenga su comentario). |
| `pruebas/` | `Unidad/` (sin base de datos) e `Integracion/` (con una MariaDB temporal). |

Cuando un comentario de `vistas/` dice, por ejemplo, «Reproduce la maqueta 09-postulacion/formulario»,
se refiere a esa pantalla aprobada, que aparece en el catálogo `../../documentacion/entregables/Pantallas_SIBEA.pdf`.

Reglas de comentarios del proyecto: cada archivo empieza con un bloque que dice qué hace y en qué paso
del recorrido está; cada método público lleva `/** ... */` con qué recibe y qué devuelve; se comentan
los pasos de seguridad, las reglas de negocio y las llamadas a la BD.

## 4. Seguridad que ya está puesta

- Cookie de sesión `HttpOnly`, `SameSite=Lax`, modo estricto, y el id de sesión se renueva al ingresar.
- Todo formulario que cambia datos es `POST` con token `_csrf` (comparado con `hash_equals`).
- Todo dato que se dibuja pasa por `e()` (escape contra XSS).
- La conexión usa `sibea_app` (nunca `root`) con consultas preparadas de verdad.
- Los documentos se aceptan solo PDF, JPG o PNG, máximo 5 MB, se comprueba el tipo real con `finfo` y se
  guardan con nombre aleatorio fuera de `public/`.
- En el Excel exportado, toda celda de texto que empiece por `=`, `+`, `-` o `@` se escribe como texto
  (protección contra inyección de fórmulas).
- Las contraseñas temporales se generan con `random_int` y en la BD solo queda el hash. El Excel de
  entrega con las contraseñas no se guarda en el servidor.
- Los errores técnicos no se muestran: van al log; la persona ve un mensaje sencillo (también si falla el arranque).
- En cada petición privada la sesión se **revalida contra la base**: si la cuenta se desactiva, se bloquea, se
  borra o cambia de rol, la persona sale a `/ingresar` en su siguiente petición.
- La sesión caduca por inactividad (30 min) y por duración máxima (8 h), configurables.
- Toda respuesta lleva `X-Frame-Options: DENY`, `Content-Security-Policy` (`frame-ancestors 'none'; object-src 'none';
  base-uri 'self'`), `Referrer-Policy: same-origin` y `X-Content-Type-Options: nosniff`. No se usa `default-src`
  porque bloquearía Google Fonts (la letra de la marca).
- Los documentos subidos se entregan con `Content-Security-Policy: sandbox` y `nosniff`: aunque alguien subiera un
  archivo disfrazado, no puede ejecutar nada en el sitio.

## 5. Cómo correr las pruebas

Desde `aplicacion/` (con las extensiones `zip` y `gd` activas):

```
php vendor/bin/phpunit                                  # todas las pruebas
php vendor/bin/phpunit --filter AccesoTest              # solo las de una clase
composer analisis                                        # PHPStan nivel 6 + revisor de comentarios
php vendor/bin/php-cs-fixer fix --dry-run --diff        # revisa el formato sin cambiar nada
```

Las pruebas de **integración** hablan con una MariaDB de verdad, pero **solo** con una instancia
**temporal** en el puerto `3398`, nunca con la base real de XAMPP (porque recrean la base en cada
clase). Se activan con dos variables de entorno; sin ellas esas pruebas se saltan y dicen por qué:

```
BR_PRUEBA_BD_PUERTO=3398
BR_PRUEBA_MYSQL=C:/xampp/mysql/bin/mysql.exe
```

La instancia temporal se levanta con otro directorio de datos, por ejemplo
`mysqld --no-defaults --datadir=<carpeta-temporal> --port=3398`, y se borra al terminar.

**Cobertura** (qué porcentaje del código recorren las pruebas). El PHP de XAMPP no trae el motor de
cobertura; se usa un PHP con la extensión `pcov` (por ejemplo, el de Laravel Herd):

```
php -d pcov.enabled=1 -d extension=zip -d extension=gd vendor/bin/phpunit --coverage-text
```

La meta es **80 % o más en `src/Servicios/` y `src/Validacion/`**. Resultado al cerrar el MVP:
Servicios 95,9 %, Validacion 99,5 %.

`PermisosRutasTest` recorre la tabla de rutas **real** (`rutas.php`): comprueba que un aprendiz recibe
403 en toda ruta `/admin/*`, que todo `POST` sin token recibe 403 y que sin sesión todo lleva a
`/ingresar`. Si agregas una ruta nueva, queda revisada sola.

## 6. Lo que todavía no hace (fase 2)

Pagos, reporte de aprobados, avisos (campanita) y correos, pedir un documento adicional al aprendiz,
recuperar la contraseña por correo, el dashboard con cifras, auditoría y catálogos en pantalla. Por
ahora, si alguien olvida su contraseña, el administrador se la restablece desde **Usuarios**. Todas
las pantallas de la fase 2 ya están diseñadas en `../../documentacion/entregables/Pantallas_SIBEA.pdf`.
