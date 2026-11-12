# Aplicación BenefitRack (MVP) — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** construir la primera versión de BenefitRack que funciona. El admin crea las cuentas y las convocatorias; el aprendiz se postula con sus documentos; el admin revisa, decide y descarga el Excel; y el aprendiz ve su proceso.

**Architecture:** PHP 8.2 puro en MVC por capas (ADR 0001). Todo entra por `public/index.php`, que pasa por el enrutador, los filtros, el controlador, el servicio y el repositorio (único lugar con SQL) hasta MariaDB. La respuesta se arma con una vista (plantilla PHP + Bootstrap). Las reglas fuertes ya están en la BD (triggers): la app se identifica con `SET @app_id_usuario` y traduce los errores `SIGNAL 45000`.

**Tech Stack:** PHP 8.2 (XAMPP), MariaDB 10.4, Composer (PSR-4), PHPUnit 10, PHPStan, PHP-CS-Fixer, phpoffice/phpspreadsheet, Bootstrap 5.3.3 (copia local) y JavaScript puro.

**Spec:** `specs/003-aplicacion-mvp.md` (y `docs/adr/0001-arquitectura-de-la-aplicacion.md`). Las maquetas aprobadas están en `vistas/` (spec 002). Las reglas de la BD, en `base-de-datos/README.md`.

## Global Constraints

- Todo el código de la app vive en `aplicacion/`. Espacio de nombres raíz: `BenefitRack\` → `aplicacion/src/`. Pruebas: `BenefitRack\Pruebas\` → `aplicacion/pruebas/`.
- **Nombres en español** (clases, métodos, variables, rutas). Las palabras propias de PHP, en inglés.
- **Comentarios (pedido de Samuel):**
  - Bloque inicial en cada archivo de `src/` y `vistas/`: qué hace y en qué paso del recorrido de la petición está.
  - `/** ... */` en cada método público: qué hace, qué recibe y qué devuelve.
  - Comentarios en los pasos de seguridad, reglas de negocio y llamadas a la BD. No se comenta lo evidente.
- `declare(strict_types=1);` y tipos en todos los parámetros y retornos. Funciones de menos de ~30 líneas.
- SQL **solo** en `src/Repositorios/`, siempre preparado (PDO, `ATTR_EMULATE_PREPARES=false`, `ATTR_PERSISTENT=false`, `ERRMODE_EXCEPTION`, `charset=utf8mb4`). HTML **solo** en `vistas/`, y todo dato pasa por `e()`.
- La conexión usa el usuario `benefitrack_app`, nunca `root` (salvo la herramienta de crear el primer administrador). En cada petición: `SET @app_id_usuario = ?, @app_ip = ?`. En el flujo de inicio de sesión, `@app_id_usuario = NULL`. Para cambios de rol o estado de cuentas: `@app_justificacion` y después volverla a `NULL`.
- Formulario que cambia datos = `POST` + token CSRF (campo `_csrf`).
- Archivos: PDF, JPG o PNG; máximo 5 MB; tipo real con `finfo`; nombre aleatorio (`bin2hex(random_bytes(16))`) en `aplicacion/almacenamiento/documentos/`, fuera de `public/`.
- Excel exportado: toda celda de texto que empiece por `=`, `+`, `-` o `@` se escribe como texto explícito (`setValueExplicit(..., DataType::TYPE_STRING)`). Los documentos y las cuentas van solo con dígitos (como texto); los montos, con puntos (`$ 180.000`); las fechas, como «15 nov 2026».
- Contraseña temporal: 10 caracteres de `ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789` con `random_int`. Solo se guarda `password_hash(PASSWORD_DEFAULT)`. El Excel de entrega no se guarda en el servidor.
- Las pantallas se ven como su maqueta en `vistas/<NN>/html/*.html`, a 1440 px y a 390 px, sin desplazamiento horizontal. Colores y letra: spec 002 §2.
- Menú del admin en el MVP: **Convocatorias · Postulaciones · Usuarios**. Tras iniciar sesión, el admin va a `/admin/postulaciones` y el aprendiz a `/mi-proceso`.
- Cobertura ≥ 80 % en `src/Servicios/` y `src/Validacion/`. `phpstan` (nivel 6) y `php-cs-fixer --dry-run` sin errores antes de cada merge.
- Pruebas de integración solo contra la BD temporal del puerto **3398** (variables `BR_PRUEBA_BD_PUERTO=3398`, `BR_PRUEBA_MYSQL=C:/xampp/mysql/bin/mysql.exe`). **Nunca** contra `C:\xampp\mysql\data`.
- Comandos (desde `aplicacion/`): `PHP=/c/xampp/php/php.exe`; Composer: `$PHP /c/Users/samue/.config/herd/bin/composer.phar`; pruebas: `$PHP vendor/bin/phpunit --filter <Nombre>` (con timeout).
- Ramas: la spec y este plan se unen a `main` primero. Después, **una rama por tarea** desde `main` (`feat/003-<slug>`), con commits `test:` (rojo) → `feat:` (verde), verificación del orquestador, `code-reviewer`, «sí» de Samuel y `merge --no-ff`.

## Review Focus

1. **Conexión que arrastra el usuario anterior**: si `@app_id_usuario` quedara con el valor de otra petición, el bloqueo dejaría de verse como automático o se le daría permiso a la persona equivocada. Prueba en la tarea 3: `BaseDatos::fijarUsuario(null)` deja `@app_id_usuario` en NULL aunque antes tuviera un id.
2. **Dos admins aprueban la última plaza a la vez**: la BD rechaza la segunda aprobación. El usuario debe ver «Ya no hay cupos en esta convocatoria», no un error técnico. Prueba en la tarea 12.
3. **Un aprendiz cambia el id en la URL** (`/mi-proceso/documentos/{id}` o `/postulaciones/{id}`) para ver lo de otro: debe recibir 403. Pruebas en las tareas 10 y 12.
4. **Archivo disfrazado** (un `.exe` renombrado `.pdf`) o de 5,01 MB: se rechaza con el mensaje del campo y no se guarda nada (ni archivo ni postulación a medias). Prueba en la tarea 10.
5. **Excel de aprendices con tildes, ñ, celdas con espacios, documentos con puntos («1.020.345.678») o repetidos dentro del mismo archivo**: los espacios se recortan, los puntos se quitan, la ñ se conserva y el repetido aparece como error de fila. Prueba en la tarea 7.

---

## Mapa de archivos

```
aplicacion/
  composer.json  phpunit.xml  phpstan.neon  .php-cs-fixer.dist.php  LEEME.md
  config/config.ejemplo.php            (config.local.php va en .gitignore)
  public/index.php  public/.htaccess  public/css/{bootstrap.min.css,benefitrack.css}
  public/js/{bootstrap.bundle.min.js,benefitrack.js}  public/img/ (logo copiado de vistas/_comun)
  rutas.php                            (tabla de rutas: método, URL, controlador, filtros)
  herramientas/crear_administrador.php (CLI, crea el primer admin con root)
  src/Nucleo/       Configuracion, Peticion, Respuesta, Enrutador, Vista, funciones.php (e()),
                    Sesion, Csrf, BaseDatos, ErrorDeNegocio, TraductorErroresBd, Aplicacion
  src/Filtros/      Filtro (interfaz), FiltroSesion, FiltroRol, FiltroCsrf, FiltroPrimerIngreso
  src/Controladores/ AccesoControlador, ContrasenaControlador, UsuariosControlador,
                    CargaUsuariosControlador, ConvocatoriasControlador, MiProcesoControlador,
                    PostulacionAprendizControlador, PostulacionesAdminControlador, DocumentosControlador
  src/Servicios/    ServicioAcceso, ServicioContrasena, GeneradorContrasena, ServicioUsuarios,
                    ServicioCargaUsuarios, ServicioConvocatorias, ServicioPostulacion,
                    AlmacenDocumentos, ServicioRevision, ExportadorExcel, ServicioMiProceso
  src/Repositorios/ RepositorioUsuarios, RepositorioCatalogos, RepositorioConvocatorias,
                    RepositorioPostulaciones, RepositorioDocumentos, RepositorioMigraciones
  src/Validacion/   Validador (base), ResultadoValidacion, ValidadorContrasena, ValidadorUsuario,
                    ValidadorConvocatoria, ValidadorPostulacion, ValidadorArchivo
  vistas/plantillas/{publica,admin,aprendiz}.php  vistas/parciales/{alertas,menu_admin}.php
  vistas/errores/{403,404,500}.php  vistas/<modulo>/*.php
  pruebas/Unidad/...  pruebas/Integracion/{BaseDePrueba.php, ...}  pruebas/bootstrap.php
  almacenamiento/documentos/.gitkeep
base-de-datos/benefitrack_v2.sql (modificado)  base-de-datos/datos_iniciales.sql (nuevo)
base-de-datos/pruebas/pruebas_reglas.sql (modificado)  base-de-datos/CAMBIOS.md (modificado)
```

## Tabla de rutas (la usan todas las tareas)

| Método y URL | Controlador::método | Filtros |
|---|---|---|
| GET/POST `/ingresar` · POST `/salir` | `AccesoControlador::formulario/ingresar/salir` | — (`salir`: sesión + CSRF) |
| GET/POST `/contrasena/nueva` | `ContrasenaControlador::formulario/cambiar` | sesión (+CSRF en POST) |
| GET `/admin/usuarios` · GET/POST `/admin/usuarios/nuevo` | `UsuariosControlador::lista/formulario/crear` | sesión, rol Administrador, primer ingreso |
| POST `/admin/usuarios/{id}/estado` · POST `/admin/usuarios/{id}/restablecer` | `UsuariosControlador::cambiarEstado/restablecer` | ídem + CSRF |
| GET `/admin/usuarios/cargar` · POST `/admin/usuarios/cargar/vista-previa` · POST `/admin/usuarios/cargar/confirmar` · GET `/admin/usuarios/plantilla.xlsx` | `CargaUsuariosControlador::...` | admin |
| GET `/admin/convocatorias` · GET/POST `/admin/convocatorias/nueva` · GET/POST `/admin/convocatorias/{id}/editar` · POST `/admin/convocatorias/{id}/estado` | `ConvocatoriasControlador::...` | admin |
| GET `/mi-proceso` | `MiProcesoControlador::ver` | sesión, rol Aprendiz, primer ingreso |
| GET/POST `/convocatorias/{id}/postulacion` · POST `/convocatorias/{id}/postulacion/enviar` | `PostulacionAprendizControlador::formulario/revisar/enviar` | aprendiz |
| GET `/admin/postulaciones` · GET `/admin/postulaciones/excel` · GET `/admin/postulaciones/{id}` · POST `/admin/postulaciones/{id}/{documentos|confirmar|aprobar|rechazar}` | `PostulacionesAdminControlador::...` | admin |
| GET `/documentos/{id}` | `DocumentosControlador::descargar` | sesión (dueño o admin) |

`{id}` solo acepta dígitos (`\d+`). `/` redirige según el rol (o a `/ingresar` si no hay sesión).

---

### Task 1: BD — primer ingreso, datos iniciales y primer administrador

**Files:**
- Modify: `base-de-datos/benefitrack_v2.sql` (tabla `usuarios` y trigger `tr_usuario_validar`)
- Create: `base-de-datos/datos_iniciales.sql`
- Modify: `base-de-datos/pruebas/pruebas_reglas.sql`, `base-de-datos/CAMBIOS.md`, `base-de-datos/README.md`

**Interfaces:**
- Produces: la columna `usuarios.debe_cambiar_contrasena TINYINT(1) NOT NULL DEFAULT 1`. La regla vive en la BD: cuando cambia `password_hash`, el trigger la pone en `0` si `@app_id_usuario = OLD.id_usuario` (cambio propio) y en `1` en cualquier otro caso (admin que restablece). Un `UPDATE` que solo cambia `debe_cambiar_contrasena` sin tocar la contraseña se rechaza (`SIGNAL 45000`).
- Produces: catálogos con `INSERT ... ON DUPLICATE KEY UPDATE` (se puede ejecutar dos veces sin duplicar), cargados por root con `@app_id_usuario = NULL`:
  - beneficios **Alimentación**, **Plan de datos** y **Cívica** (categoría = el mismo nombre; descripción corta);
  - bancos: Bancolombia, Banco de Bogotá, Davivienda, BBVA Colombia, Banco de Occidente, Banco Popular, Banco Caja Social, Banco AV Villas, Banco Agrario, Nequi y Daviplata;
  - programas de ejemplo del Centro Textil (id_institucion 1): «Análisis y Desarrollo de Software», «Confección Industrial», «Gestión de la Producción Industrial», «Diseño de Modas» y «Gestión Administrativa».

- [ ] **Step 1: Escribir las pruebas que fallan** en `pruebas_reglas.sql`, con el mismo estilo de las existentes:
  - **(a)** un usuario nuevo nace con `debe_cambiar_contrasena = 1`;
  - **(b)** el propio usuario cambia su `password_hash` y queda en `0`;
  - **(c)** el admin cambia el `password_hash` de un aprendiz y queda en `1`;
  - **(d)** cambiar solo `debe_cambiar_contrasena` se rechaza;
  - **(e)** ejecutar `datos_iniciales.sql` dos veces deja exactamente 3 beneficios, 11 bancos y 5 programas.
- [ ] **Step 2: Comprobar que fallan.** Levantar la instancia temporal: `mysql_install_db.exe --datadir=<scratchpad>/mariadb-verif --port=3398`, y después `mysqld --port=3398` en segundo plano. Cargar `benefitrack_v2.sql` y luego `pruebas_reglas.sql`. Esperado: fallan las pruebas nuevas (falta la columna).
- [ ] **Step 3: Commit rojo** `test: pruebas de primer ingreso y datos iniciales`.
- [ ] **Step 4: Implementar** la columna, la regla en `tr_usuario_validar` y `datos_iniciales.sql`.
- [ ] **Step 5: Verificar todo.**
  - Script cargado dos veces sin errores.
  - Pruebas: todas en verde (las 400 de antes + las nuevas).
  - `sh pruebas/concurrencia/ejecutar.sh "<mysql> -u root -P 3398 -h 127.0.0.1"` → CORRECTO.
  - `pruebas/otro_usuario` → CORRECTO.
- [ ] **Step 6: Documentar** el cambio en `CAMBIOS.md` (D20) y, en `README.md`, el orden de carga: `benefitrack_v2.sql` → `datos_iniciales.sql` → `usuario_aplicacion.sql`.
- [ ] **Step 7: Commit verde** `feat(bd): primer ingreso obligatorio y datos iniciales`.

### Task 2: Esqueleto del proyecto y núcleo HTTP

**Files:**
- Create: `aplicacion/composer.json`, `phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.dist.php`, `config/config.ejemplo.php`, `public/index.php`, `public/.htaccess`, `rutas.php`
- Create: `src/Nucleo/{Configuracion,Peticion,Respuesta,Enrutador,Vista,Aplicacion}.php` y `src/Nucleo/funciones.php`
- Modify: `.gitignore` (raíz): `aplicacion/vendor/`, `aplicacion/config/config.local.php`, `aplicacion/almacenamiento/documentos/*` y `aplicacion/almacenamiento/temporal/*` (excepto `.gitkeep`)
- Test: `pruebas/Unidad/Nucleo/{EnrutadorTest,FuncionesTest,VistaTest}.php`

**Interfaces:**
- Produces:
  - `Configuracion::desdeArchivo(string $ruta): Configuracion` y `->obtener(string $clave): mixed`. Claves: `bd.host`, `bd.puerto`, `bd.nombre`, `bd.usuario`, `bd.clave`, `app.url_base`, `app.depuracion` (bool).
  - `Peticion::desdeGlobales(): Peticion`, con `metodo(): string`, `ruta(): string`, `campo(string $nombre, string $porDefecto = ''): string` (con `trim`), `archivo(string $nombre): ?array`, `parametro(string $nombre): string` (los de `{id}`) e `ip(): string`.
  - `Respuesta::html(string $cuerpo, int $codigo = 200)`, `::redirigir(string $url)` y `::archivo(string $ruta, string $nombreDescarga, string $tipoMime)`, con `->enviar(): void`.
  - `Enrutador::agregar(string $metodo, string $patron, callable|array $destino, array $filtros = []): void` y `->resolver(Peticion $p): ?array{destino, filtros, parametros}`.
  - `Vista::mostrar(string $plantilla, array $datos = [], string $marco = 'publica'): string`.
  - `e(?string $texto): string` (en `funciones.php`, cargado por Composer `files`).
  - Composer: `require` = `php ^8.2`, `phpoffice/phpspreadsheet ^2.2`; `require-dev` = `phpunit/phpunit ^10.5`, `phpstan/phpstan ^1.12`, `friendsofphp/php-cs-fixer ^3.64`. Scripts: `pruebas`, `analisis` y `formato`.

- [ ] **Step 1: Escribir las pruebas que fallan.**
  - `EnrutadorTest`:
    - `test_resuelve_ruta_con_parametro`: `/admin/postulaciones/15` con el patrón `/admin/postulaciones/{id}` da `parametros['id'] === '15'`.
    - `test_no_resuelve_id_no_numerico`: `/admin/postulaciones/abc` da `null`.
    - `test_distingue_metodo`: un GET registrado no atiende un POST.
  - `FuncionesTest::test_e_escapa_html_y_comillas`: `e('<script>"x"</script>') === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;'`, y `e(null) === ''`.
  - `VistaTest::test_mostrar_inserta_datos_escapados_en_el_marco`.
- [ ] **Step 2: Correrlas.** `$PHP vendor/bin/phpunit --filter 'Enrutador|Funciones|Vista'` → FAIL (las clases no existen).
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar** las clases del núcleo. `Aplicacion::ejecutar(Peticion): Respuesta` resuelve la ruta, corre los filtros en orden y llama al controlador. Si no hay ruta, devuelve la vista `errores/404` con código 404. Ante una excepción no prevista: `errores/500` y un registro en `error_log`, sin mostrar detalles salvo con `app.depuracion`. `index.php` tiene menos de 25 líneas.
- [ ] **Step 5: Correr las pruebas.** PASS. Además: `composer analisis` y `composer formato -- --dry-run` sin errores.
- [ ] **Step 6: Commit verde** `feat(app): esqueleto y núcleo HTTP`.

### Task 3: Sesión, CSRF, BD, filtros y plantillas base

**Files:**
- Create: `src/Nucleo/{Sesion,Csrf,BaseDatos,ErrorDeNegocio,TraductorErroresBd}.php`, `src/Filtros/{Filtro,FiltroSesion,FiltroRol,FiltroCsrf,FiltroPrimerIngreso}.php`
- Create: `vistas/plantillas/{publica,admin,aprendiz}.php`, `vistas/parciales/{alertas,menu_admin}.php`, `vistas/errores/{403,404,500}.php`, `public/css/benefitrack.css`, `public/js/benefitrack.js`, y Bootstrap 5.3.3 en `public/css` y `public/js`
- Create: `pruebas/bootstrap.php`, `pruebas/Integracion/BaseDePrueba.php`, `aplicacion/LEEME.md` (primera versión: instalación y carpetas)
- Test: `pruebas/Unidad/Nucleo/{CsrfTest,TraductorErroresBdTest}.php`, `pruebas/Unidad/Filtros/FiltrosTest.php`, `pruebas/Integracion/BaseDatosTest.php`

**Interfaces:**
- Consumes: `Peticion` y `Respuesta` (tarea 2).
- Produces:
  - `Sesion::iniciar(): void` (cookie `HttpOnly`, `SameSite=Lax`, `use_strict_mode`). También:
    - `->iniciarPara(int $idUsuario, string $rol, bool $debeCambiar): void`, que llama a `session_regenerate_id(true)`;
    - `->idUsuario(): ?int`, `->rol(): ?string` y `->debeCambiarContrasena(): bool`;
    - `->marcarContrasenaCambiada(): void` y `->cerrar(): void`;
    - `->mensaje(string $tipo, string $texto)` y `->tomarMensajes(): array` (mensajes de una sola vez).
  - `Csrf::token(): string` y `Csrf::esValido(string $enviado): bool`, con `hash_equals`.
  - `BaseDatos::conectar(Configuracion): BaseDatos`, `->pdo(): PDO`, `->fijarUsuario(?int $id, string $ip): void` y `->conJustificacion(string $texto, callable $accion): mixed` (fija `@app_justificacion`, ejecuta y la vuelve a `NULL` en un `finally`).
  - `ErrorDeNegocio extends RuntimeException`: su mensaje ya está en lenguaje sencillo, apto para mostrar.
  - `TraductorErroresBd::traducir(PDOException $e): ErrorDeNegocio`. Un SQLSTATE `45000` conserva el texto de la BD. El código 1062 (duplicado) da «Ese registro ya existe». Cualquier otro da «No pudimos guardar los cambios. Intenta de nuevo.».
  - `interface Filtro { public function aplicar(Peticion $p): ?Respuesta; }`: devuelve `null` para dejar pasar la petición.
  - `FiltroRol` recibe `'Administrador'` o `'Aprendiz'` y, si el rol no coincide, devuelve la vista 403.
  - `FiltroPrimerIngreso` redirige a `/contrasena/nueva` mientras `debeCambiarContrasena()` sea verdadero.
  - `BaseDePrueba` (TestCase abstracto de integración):
    - si no hay `BR_PRUEBA_BD_PUERTO`, marca la prueba como saltada, con el motivo;
    - en `setUpBeforeClass` recrea `benefitrack_v2` en el puerto 3398 cargando `benefitrack_v2.sql`, `datos_iniciales.sql` y `usuario_aplicacion.sql`;
    - ofrece `crearAdmin(): int`, `crearAprendiz(array $datos = []): int` y `crearConvocatoriaActiva(int $cupos = 10): int`.
  - Plantillas: `admin.php` lleva la barra y el menú (Convocatorias · Postulaciones · Usuarios), con el enlace activo según la URL. `aprendiz.php` lleva la barra del aprendiz. `publica.php` es la tarjeta centrada del login. Todas copian la estructura de `vistas/00-existentes`.

- [ ] **Step 1: Escribir las pruebas que fallan.**
  - `CsrfTest`: un token válido pasa y uno alterado o vacío falla.
  - `FiltrosTest`:
    - sin sesión, `FiltroSesion` redirige a `/ingresar` con el mensaje «Tu sesión terminó»;
    - un aprendiz en una ruta de admin recibe 403;
    - un POST sin `_csrf` recibe 403 y no llama al controlador;
    - con `debeCambiar` en verdadero, la petición se redirige a `/contrasena/nueva`.
  - `TraductorErroresBdTest`: el 45000 conserva su texto y el 1062 da «Ese registro ya existe».
  - `BaseDatosTest` (integración, Review Focus 1): después de `fijarUsuario(5, ...)` y luego de `fijarUsuario(null, ...)`, la consulta `SELECT @app_id_usuario` da `NULL`; y `conJustificacion` deja `@app_justificacion` en `NULL` aunque la acción lance una excepción.
- [ ] **Step 2: Correrlas.** FAIL.
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar** las clases, las plantillas y el CSS. Para que se vea como la maqueta, `benefitrack.css` redefine las variables de Bootstrap (`--bs-primary: #00304D`, `--bs-success: #007832`, `--bs-danger: #C2392F`, `--bs-link-color: #0B6CB8` y la letra `'Work Sans', Inter, system-ui, sans-serif`).
- [ ] **Step 5: Correr las pruebas y el análisis.** PASS; análisis y formato limpios.
- [ ] **Step 6: Commit verde** `feat(app): sesión, CSRF, conexión y plantillas base`.

### Task 4: Iniciar y cerrar sesión (M1)

**Files:**
- Create: `src/Repositorios/RepositorioUsuarios.php` (primeros métodos), `src/Servicios/ServicioAcceso.php`, `src/Controladores/AccesoControlador.php`, `vistas/acceso/ingresar.php`
- Create: `herramientas/crear_administrador.php` (CLI)
- Test: `pruebas/Unidad/Servicios/ServicioAccesoTest.php`, `pruebas/Integracion/AccesoTest.php`

**Interfaces:**
- Produces:
  - `RepositorioUsuarios::buscarPorCorreo(string $correo): ?array`, con `id_usuario`, `password_hash`, `estado`, `rol`, `intentos_fallidos`, `fecha_bloqueo` y `debe_cambiar_contrasena`.
  - `->registrarIntentoFallido(int $id): void` y `->reiniciarIntentos(int $id): void`.
  - `ResultadoAcceso` (en `ServicioAcceso.php`): `exito(int $id, string $rol, bool $debeCambiar)` o `fallo(string $estadoVista)`, con `$estadoVista` ∈ `datos-incorrectos`, `cuenta-bloqueada` o `cuenta-inactiva`. Son los nombres de los estados de la maqueta `00-existentes/login`.
  - `ServicioAcceso::ingresar(string $correo, string $clave): ResultadoAcceso`. Antes de tocar la BD llama a `BaseDatos::fijarUsuario(null, $ip)`.
- Comportamiento: el correo se compara sin mayúsculas. Si el correo no existe, se responde **lo mismo** que con una clave errada (`datos-incorrectos`) y se llama igual a `password_verify` contra un hash falso, para que el tiempo de respuesta no delate si la cuenta existe. El bloqueo tras 3 intentos y el desbloqueo a los 15 minutos los hace la BD (README §3). El servicio solo registra el intento y vuelve a leer el estado.

- [ ] **Step 1: Escribir las pruebas que fallan.**
  - `ServicioAccesoTest`, con un repositorio falso:
    - con la clave correcta, devuelve `exito` y reinicia los intentos;
    - con la clave errada, devuelve `datos-incorrectos`;
    - una cuenta `Inactivo` devuelve `cuenta-inactiva` aunque la clave sea correcta;
    - con un correo inexistente, devuelve `datos-incorrectos`.
  - `AccesoTest` (integración, CA-1):
    - 3 claves erradas dejan `estado = 'Bloqueado'` y la 4.ª petición, aun con la clave correcta, da `cuenta-bloqueada`;
    - con `fecha_bloqueo` 16 minutos atrás (ajustada con root en la prueba), la clave correcta da `exito`.
- [ ] **Step 2: Correrlas.** FAIL.
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar** el servicio, el controlador y la vista (los estados `normal`, `validacion`, `datos-incorrectos`, `cuenta-bloqueada`, `cuenta-inactiva` y `cargando`, este último con JS al enviar). `salir` cierra la sesión y redirige a `/ingresar`. `crear_administrador.php` pide documento, nombre, apellido, correo y contraseña por consola, se conecta como root con `@app_id_usuario = NULL` e inserta el primer administrador con `debe_cambiar_contrasena = 0`. Se documenta en el LEEME.
- [ ] **Step 5: Correr las pruebas.** PASS. Prueba manual: entrar con el admin creado por la CLI en `http://localhost/BenefitRack/aplicacion/public/` (Apache de XAMPP).
- [ ] **Step 6: Commit verde** `feat(app): iniciar y cerrar sesión`.

### Task 5: Cambio de contraseña en el primer ingreso (M2)

**Files:**
- Create: `src/Validacion/{Validador,ResultadoValidacion,ValidadorContrasena}.php`, `src/Servicios/ServicioContrasena.php`, `src/Controladores/ContrasenaControlador.php`, `vistas/contrasena/{nueva,exito}.php`
- Modify: `RepositorioUsuarios` (agregar `cambiarContrasena(int $id, string $hash): void`)
- Test: `pruebas/Unidad/Validacion/ValidadorContrasenaTest.php`, `pruebas/Integracion/PrimerIngresoTest.php`

**Interfaces:**
- Produces:
  - `ResultadoValidacion`: `->esValido(): bool`, `->errores(): array<string campo, string mensaje>`, `->agregar(string $campo, string $mensaje): void` y `->datos(): array` (los datos ya limpios). La usan **todos** los validadores.
  - `ValidadorContrasena::validar(string $nueva, string $confirmacion): ResultadoValidacion`. Reglas, iguales a las de la maqueta `02-contrasena/primer-ingreso`: mínimo 8 caracteres, al menos una letra y un número, y que las dos coincidan. Los mensajes se copian de la maqueta.
  - `ServicioContrasena::cambiarPropia(int $idUsuario, string $nueva, string $confirmacion): ResultadoValidacion`. La BD pone `debe_cambiar_contrasena = 0` (tarea 1).

- [ ] **Step 1: Escribir las pruebas que fallan.**
  - El validador: «abc» falla por corta; «abcdefgh» falla por no tener número; dos contraseñas distintas fallan por no coincidir; «Bienestar2026» pasa.
  - `PrimerIngresoTest` (CA-2): un aprendiz nuevo tiene `debe_cambiar_contrasena = 1`; después de `cambiarPropia`, queda en `0`.
- [ ] **Step 2: Correrlas.** FAIL.
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar.** Al cambiar la contraseña con éxito: `Sesion::marcarContrasenaCambiada()` y se muestra la vista `exito`, con el botón que lleva a su inicio según el rol. Las reglas se marcan en vivo mientras el usuario escribe (en `benefitrack.js`).
- [ ] **Step 5: Correr las pruebas.** PASS.
- [ ] **Step 6: Commit verde** `feat(app): cambio de contraseña en el primer ingreso`.

### Task 6: Usuarios — lista, crear uno, estado, restablecer y Excel de entrega (M3a)

**Files:**
- Create: `src/Servicios/{GeneradorContrasena,ServicioUsuarios}.php`, `src/Validacion/ValidadorUsuario.php`, `src/Repositorios/RepositorioCatalogos.php`, `src/Servicios/ExportadorExcel.php` (primera parte), `src/Controladores/UsuariosControlador.php`, `vistas/usuarios/{lista,crear,creada}.php`
- Modify: `RepositorioUsuarios` (agregar `listarAprendices`, `crear` y `restablecerContrasena`; el cambio de estado va por `CALL pr_cambiar_estado_usuario(?, ?, ?)`)
- Test: `pruebas/Unidad/Servicios/GeneradorContrasenaTest.php`, `pruebas/Unidad/Validacion/ValidadorUsuarioTest.php`, `pruebas/Unidad/Servicios/ExportadorExcelTest.php`, `pruebas/Integracion/UsuariosTest.php`

**Interfaces:**
- Produces:
  - `GeneradorContrasena::generar(): string` (Global Constraints).
  - `ValidadorUsuario::validar(array $fila, array $programasPorNombre): ResultadoValidacion`. Campos: `documento` (solo dígitos tras quitar puntos y espacios, de 6 a 12), `nombre`, `apellido`, `correo` (`FILTER_VALIDATE_EMAIL`, en minúsculas), `programa` (debe existir) y `ficha` (dígitos).
  - `RepositorioCatalogos`: `programas(): array`, `beneficios(): array`, `bancos(): array`, `eps(): array`, `tiposCuenta(): array` (solo los activos).
  - `ServicioUsuarios`:
    - `crearAprendiz(array $datos): array{id:int, contrasena:string}` (lanza `ErrorDeNegocio` si el documento o el correo ya existen);
    - `cambiarEstado(int $id, string $estado, string $justificacion): void` (`$estado` ∈ Activo, Inactivo; «desbloquear» = Activo);
    - `restablecerContrasena(int $id): string`.
  - `ExportadorExcel::entregaContrasenas(array $filas): string`. Devuelve la ruta de un archivo temporal `.xlsx` con las columnas Documento, Nombre completo, Correo y Contraseña temporal. El controlador lo envía y lo borra.
  - `ExportadorExcel::textoSeguro(string $v)` aplica la regla de inyección (Global Constraints).

- [ ] **Step 1: Escribir las pruebas que fallan.**
  - `GeneradorContrasenaTest`:
    - 1 000 contraseñas generadas tienen 10 caracteres;
    - ninguna contiene `0`, `O`, `1`, `l` ni `I`;
    - y no hay repetidas.
  - `ValidadorUsuarioTest`:
    - «1.020.345.678» queda como «1020345678»;
    - un correo inválido da error en `correo`;
    - un programa que no existe da error en `programa`.
  - `ExportadorExcelTest::test_texto_que_empieza_por_igual_queda_como_texto`: al leer el archivo con PhpSpreadsheet, la celda `=1+1` tiene el tipo `s` y el valor `=1+1`.
  - `UsuariosTest` (integración):
    - crear un aprendiz devuelve una contraseña que sirve con `password_verify` y deja `debe_cambiar_contrasena = 1`;
    - crearlo otra vez con el mismo documento lanza `ErrorDeNegocio`;
    - desactivar sin justificación lanza `ErrorDeNegocio` con el texto de la BD;
    - restablecer devuelve una contraseña nueva y deja `debe_cambiar_contrasena = 1`.
- [ ] **Step 2: Correrlas.** FAIL.
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar.**
  - Las vistas siguen `06-usuarios` (`lista` y `crear-uno`). La lista lleva buscador por nombre o documento, 20 filas por página y la acción de cada fila según el estado.
  - Las acciones de estado piden la justificación en un modal de Bootstrap.
  - La vista `creada` muestra la contraseña **una sola vez** y el botón «Descargar Excel de entrega». El texto «la recibe en su correo» se cambia por el de la spec §9.1.
  - Mientras dura la sesión, los datos de entrega se guardan en `$_SESSION['entrega_pendiente']`, sin guardarlos en el disco, y se borran al descargar.
- [ ] **Step 5: Correr las pruebas.** PASS.
- [ ] **Step 6: Commit verde** `feat(app): gestión de usuarios y contraseña temporal`.

### Task 7: Cargar aprendices desde Excel (M3b)

**Files:**
- Create: `src/Servicios/ServicioCargaUsuarios.php`, `src/Repositorios/RepositorioMigraciones.php`, `src/Controladores/CargaUsuariosControlador.php`, `vistas/usuarios/{cargar,vista_previa,resultado}.php`
- Test: `pruebas/Unidad/Servicios/ServicioCargaUsuariosTest.php` (con archivos `.xlsx` de prueba creados en la misma prueba), `pruebas/Integracion/CargaUsuariosTest.php`

**Interfaces:**
- Consumes: `ValidadorUsuario`, `GeneradorContrasena`, `ServicioUsuarios::crearAprendiz` y `ExportadorExcel::entregaContrasenas` (tarea 6).
- Produces:
  - `ServicioCargaUsuarios::leer(string $rutaXlsx): array{filas: array, errores: array<int fila, array<string campo, string mensaje>>}`. Lanza `ErrorDeNegocio('El archivo no es un Excel válido o le faltan columnas: …')`.
  - Columnas exactas de la plantilla: `Documento`, `Nombre`, `Apellido`, `Correo`, `Programa`, `Ficha`. Máximo 2 000 filas.
  - `ServicioCargaUsuarios::confirmar(array $filasValidas, string $nombreArchivo): array{creadas:int, fallidas:int, entrega: array}`. Registra la carga en `migraciones` (`entidad_destino = 'usuarios'`): `En proceso` y, al terminar, `Completada` o `Completada con errores`, con `detalle_errores` en JSON.
  - `CargaUsuariosControlador::plantilla()` genera la plantilla `.xlsx` con esos encabezados.
- Flujo: la vista previa guarda las filas válidas en la sesión (no el archivo). Al confirmar, solo se crean esas filas. Si al crear alguna choca en la BD (por ejemplo, otra carga creó el mismo documento entre la vista previa y la confirmación), esa fila se cuenta como fallida con el mensaje traducido, y las demás siguen.

- [ ] **Step 1: Escribir las pruebas que fallan** (CA-5 y Review Focus 5).
  - Un Excel con 4 filas: una correcta con «Peña» y «  Ana  », una con «1.020.345.678», otra con ese mismo documento repetido y una con el correo vacío. Debe dar 2 válidas (la ñ conservada y los espacios recortados) y 2 errores: en la fila 4, «Documento repetido en el archivo», y en la fila 5, «Falta el correo». Las filas cuentan desde 2, porque la 1 es el encabezado.
  - Un archivo `.txt` renombrado `.xlsx` lanza `ErrorDeNegocio`.
  - Si falta la columna `Correo`, se lanza `ErrorDeNegocio` con su nombre.
  - Integración: `confirmar` crea 2 cuentas y una fila en `migraciones` con `registros_exitosos = 2`.
- [ ] **Step 2: Correrlas.** FAIL.
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar.** Las vistas siguen `06-usuarios` (`cargar-excel`, `vista-previa`, `resultado` y `archivo-invalido`). `resultado` ofrece «Descargar Excel de entrega» y «Descargar errores».
- [ ] **Step 5: Correr las pruebas.** PASS.
- [ ] **Step 6: Commit verde** `feat(app): carga de aprendices desde Excel`.

### Task 8: Convocatorias (M4)

**Files:**
- Create: `src/Repositorios/RepositorioConvocatorias.php`, `src/Validacion/ValidadorConvocatoria.php`, `src/Servicios/ServicioConvocatorias.php`, `src/Controladores/ConvocatoriasControlador.php`, `vistas/convocatorias/{lista,formulario}.php`
- Test: `pruebas/Unidad/Validacion/ValidadorConvocatoriaTest.php`, `pruebas/Integracion/ConvocatoriasTest.php`

**Interfaces:**
- Produces:
  - `RepositorioConvocatorias::listar(): array` (incluye `cupos_usados` = postulaciones Aprobadas), `->buscar(int $id): ?array`, `->crear(array $d): int`, `->actualizar(int $id, array $d): void`, `->cambiarEstado(int $id, string $estado, ?string $motivo): void` y `->abiertasPara(int $idAprendiz): array`. Esta última devuelve las convocatorias `Activa` con `CURDATE()` entre `fecha_inicio` y `fecha_cierre`, junto con el `id_postulacion` del aprendiz si ya se postuló (o NULL). La usa la tarea 13.
  - `ValidadorConvocatoria::validar(array $d): ResultadoValidacion`. Reglas: `nombre`, `id_beneficio`, `fecha_inicio < fecha_cierre`, `cupos > 0`, `requisitos`, `valor_pago > 0` (acepta «180.000»), `numero_pagos > 0` y `duracion_meses > 0`.
  - `ServicioConvocatorias::cambiarEstado(int $id, string $accion, ?string $motivo): void`, con `$accion` ∈ `activar` (pasa a Activa), `desactivar` (Inactiva), `cerrar` (Cerrada) y `cerrar-anticipado` (Cierre anticipado, con el motivo obligatorio).
- Varias convocatorias pueden estar **Activas a la vez** (spec §9.1). No hay regla que las limite.

- [ ] **Step 1: Escribir las pruebas que fallan.**
  - El validador: cierre igual al inicio da error; «180.000» queda como `180000.00`; cupos en 0 dan error.
  - Integración:
    - crear y activar dos convocatorias deja las dos `Activa`;
    - `cerrar-anticipado` sin motivo lanza `ErrorDeNegocio`;
    - un aprendiz que intenta `crear` (con `fijarUsuario` del aprendiz) recibe `ErrorDeNegocio`: la BD lo impide.
- [ ] **Step 2: Correrlas.** FAIL.
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar.** Las vistas siguen `07-convocatorias` (`lista`, `crear-editar`, `cerrar` y `vacio`). El modal de cierre pide el motivo cuando es anticipado. Los estados se muestran con los mismos colores de la maqueta.
- [ ] **Step 5: Correr las pruebas.** PASS.
- [ ] **Step 6: Commit verde** `feat(app): convocatorias`.

### Task 9: Formulario de postulación — mostrar y validar (M5a)

**Files:**
- Create: `src/Validacion/{ValidadorPostulacion,ValidadorArchivo}.php`, `src/Controladores/PostulacionAprendizControlador.php` (métodos `formulario` y `revisar`), `vistas/postulacion/{formulario,revisar,cerrada}.php`
- Test: `pruebas/Unidad/Validacion/{ValidadorPostulacionTest,ValidadorArchivoTest}.php`

**Interfaces:**
- Consumes: `RepositorioConvocatorias::buscar` y `RepositorioCatalogos` (eps, bancos, tiposCuenta).
- Produces:
  - `ValidadorArchivo::validar(?array $archivoSubido, string $campo, string $nombreLegible): ResultadoValidacion`. Reglas:
    - lo que no se subió da «Falta el <nombreLegible>»;
    - más de 5 242 880 bytes da «El archivo pesa más de 5 MB»;
    - un tipo real (`finfo`) distinto de `application/pdf`, `image/jpeg` o `image/png` da «Solo se aceptan PDF, JPG o PNG».
  - `ValidadorPostulacion::validar(array $campos, array $archivos, array $catalogos): ResultadoValidacion`. Campos: `telefono` (de 7 a 10 dígitos), `direccion`, `fecha_nacimiento` (de 14 a 60 años), `id_eps`, `id_entidad_bancaria`, `id_tipo_cuenta`, `numero_cuenta` (de 6 a 20 dígitos, sin puntos), `titular_cuenta`, `motivo` (opcional, 500 caracteres como máximo) y `acepta_datos` (obligatoria). Archivos: `doc_identidad` (tipo de documento 1), `cert_eps` (tipo 4) y `cert_bancario` (tipo 2).
  - Flujo de 2 pasos:
    1. POST `/convocatorias/{id}/postulacion` valida todo. Si hay errores, vuelve a `formulario` con el resumen «Revisa N campos» y el error de cada campo. Si está bien, guarda los archivos ya validados en una carpeta temporal de la sesión (`almacenamiento/temporal/<id de sesión>/`) y muestra `revisar`.
    2. `enviar` (tarea 10) crea la postulación.
  - El aprendiz llega a `cerrada` cuando la convocatoria no está abierta, y a `cerrada` con el texto «Ya te postulaste a esta convocatoria» si ya se postuló (CA-8).

- [ ] **Step 1: Escribir las pruebas que fallan** (CA-7 y Review Focus 4):
  - un archivo `.exe` renombrado `.pdf` (en la prueba, bytes `MZ...`) da «Solo se aceptan PDF, JPG o PNG»;
  - un archivo de 5 242 881 bytes da «El archivo pesa más de 5 MB»;
  - si falta `cert_eps`, el error dice «Falta el Certificado de EPS»;
  - un formulario completo y correcto es válido;
  - un `motivo` de 501 caracteres da error.
- [ ] **Step 2: Correrlas.** FAIL.
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar** el controlador y las vistas, siguiendo `09-postulacion` (`formulario`, `con-errores`, `revisar-y-enviar` y `convocatoria-cerrada`). «Tus datos» se muestra en solo lectura, desde la cuenta. Los campos de contacto vienen llenos si ya existen. En JS: validación previa en el navegador y el nombre del archivo elegido. **El servidor siempre vuelve a validar.**
- [ ] **Step 5: Correr las pruebas.** PASS.
- [ ] **Step 6: Commit verde** `feat(app): formulario de postulación con validación`.

### Task 10: Enviar la postulación y guardar los documentos (M5b)

**Files:**
- Create: `src/Servicios/{ServicioPostulacion,AlmacenDocumentos}.php`, `src/Repositorios/{RepositorioPostulaciones,RepositorioDocumentos}.php`, `src/Controladores/DocumentosControlador.php`, `vistas/postulacion/enviada.php`
- Modify: `PostulacionAprendizControlador` (método `enviar`) y `RepositorioUsuarios` (`actualizarContacto` y `guardarCuentaBancaria`)
- Test: `pruebas/Unidad/Servicios/AlmacenDocumentosTest.php`, `pruebas/Integracion/{PostulacionTest,DocumentosAccesoTest}.php`

**Interfaces:**
- Consumes: los datos y archivos temporales validados en la tarea 9.
- Produces:
  - `AlmacenDocumentos::guardar(string $rutaTemporal, string $extension): string`. Mueve el archivo a `almacenamiento/documentos/<32 hex>.<ext>` y devuelve la ruta relativa. También ofrece `->rutaAbsoluta(string $relativa): string` y `->borrar(string $relativa): void`.
  - `ServicioPostulacion::enviar(int $idAprendiz, int $idConvocatoria, array $datos, array $archivosTemporales): int`. Hace todo **en una sola transacción**:
    1. actualiza el contacto y la EPS en `usuarios`;
    2. guarda o actualiza `informacion_bancaria`;
    3. inserta la postulación con `datos_formulario` = JSON con todo lo enviado, incluida la fecha;
    4. inserta los 3 `documentos_postulacion`.

    Si algo falla: `rollBack`, borra los archivos ya movidos y relanza el error como `ErrorDeNegocio`.
  - `RepositorioDocumentos::buscarConDueno(int $idDocumento): ?array` (incluye el `id_usuario` dueño de la postulación).
  - `DocumentosControlador::descargar` entrega el archivo solo si el usuario es el dueño o un admin; si no, 403. Usa `Content-Disposition: inline` para PDF e imágenes y `X-Content-Type-Options: nosniff`.

- [ ] **Step 1: Escribir las pruebas que fallan.**
  - CA-6: enviar deja 1 postulación `Pendiente` con 3 documentos (uno de tipo 4 = Certificado de EPS), y `datos_formulario` es JSON válido con `numero_cuenta`.
  - Enviar dos veces a la misma convocatoria: la segunda lanza `ErrorDeNegocio` y no deja archivos sueltos en `almacenamiento/documentos`.
  - Si falla el insert del 3.er documento (simulado con un `id_tipo_documento` inexistente), no queda ninguna postulación ni ningún archivo.
  - `DocumentosAccesoTest` (Review Focus 3): el aprendiz B pide el documento del aprendiz A y recibe 403; el admin lo recibe con 200.
- [ ] **Step 2: Correrlas.** FAIL.
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar.** Al terminar, se borra la carpeta temporal de la sesión y se muestra la vista `enviada` (maqueta `09-postulacion/enviada`).
- [ ] **Step 5: Correr las pruebas.** PASS.
- [ ] **Step 6: Commit verde** `feat(app): envío de postulación y documentos`.

### Task 11: Postulaciones del admin — lista, filtros y Excel (M6a)

**Files:**
- Modify: `RepositorioPostulaciones` (agregar `buscar(FiltroPostulaciones $f, int $pagina, int $porPagina = 20): array{filas, total}` y `todasParaExportar(FiltroPostulaciones $f): iterable`)
- Create: `src/Servicios/FiltroPostulaciones.php` (objeto de valor), `src/Controladores/PostulacionesAdminControlador.php` (métodos `lista` y `excel`), `vistas/postulaciones/lista.php`
- Modify: `ExportadorExcel` (agregar `postulaciones(iterable $filas, array $columnas): string`)
- Test: `pruebas/Unidad/Servicios/{FiltroPostulacionesTest,ExportadorExcelTest}.php`, `pruebas/Integracion/ListaPostulacionesTest.php`

**Interfaces:**
- Produces:
  - `FiltroPostulaciones::desdePeticion(Peticion $p): FiltroPostulaciones`, con `texto` (nombre o documento), `idBeneficio`, `idPrograma`, `estado` (Pendiente, Validada, Aprobada o Rechazada), `desde` y `hasta` (fechas). Los valores que no son válidos se ignoran (no fallan).
  - `ExportadorExcel::COLUMNAS`: un arreglo clave → título, agrupado como en la maqueta `00-existentes/postulaciones`:
    - datos del aprendiz: documento, nombre, correo, teléfono, programa, ficha, EPS;
    - datos de la postulación: convocatoria, beneficio, estado, fecha, banco, tipo de cuenta, número de cuenta, titular, motivo;
    - estado de los documentos.

    Solo se exportan las claves de `COLUMNAS` que pidió el usuario (las desconocidas se ignoran). Los datos de la postulación se leen de `datos_formulario`.

- [ ] **Step 1: Escribir las pruebas que fallan.**
  - Integración: con 3 postulaciones (una Aprobada), el filtro `estado=Aprobada` devuelve 1, y el texto «peña» encuentra a «Peña».
  - `FiltroPostulacionesTest`: `estado=Borrada` se ignora.
  - CA-10: exportar con las columnas `documento`, `nombre` y `estado` crea un `.xlsx` con exactamente esas 3 columnas. El documento «1020345678» es de tipo texto, y un nombre «=HYPERLINK(...)» queda como texto.
- [ ] **Step 2: Correrlas.** FAIL.
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar** la vista igual a `00-existentes/postulaciones`: buscador, filtros, paginación, el estado vacío «No encontramos postulaciones con estos filtros» y el modal «Descargar en Excel» con las casillas de columnas. El Excel respeta los filtros activos. Para no quedarse sin memoria, se escribe fila por fila.
- [ ] **Step 5: Correr las pruebas.** PASS.
- [ ] **Step 6: Commit verde** `feat(app): lista de postulaciones y Excel`.

### Task 12: Detalle de postulación — documentos y decisiones (M6b)

**Files:**
- Create: `src/Servicios/ServicioRevision.php`, `vistas/postulaciones/detalle.php`
- Modify: `PostulacionesAdminControlador` (métodos `detalle`, `documentos`, `confirmar`, `aprobar` y `rechazar`), y `RepositorioPostulaciones` / `RepositorioDocumentos` (`detalle`, `marcarDocumento`, `cambiarEstado` y `cuposDisponibles`)
- Test: `pruebas/Unidad/Servicios/ServicioRevisionTest.php`, `pruebas/Integracion/RevisionTest.php`

**Interfaces:**
- Produces:
  - `ServicioRevision::acciones(array $detalle): array{confirmar: bool, aprobar: bool, motivoAprobarDeshabilitado: ?string}`. Reglas:
    - `confirmar` solo se puede si la postulación está Pendiente y sus 3 documentos están Validado;
    - `aprobar` solo se puede si está Validada y hay cupos.

    Los motivos de la maqueta 03 son: «Primero confirma los datos» y «Ya no hay cupos en esta convocatoria».
  - `ServicioRevision::marcarDocumentos(int $idPostulacion, array<int idDocumento, array{estado: 'Validado'|'Rechazado', observacion: string}>): void`. «Rechazado» exige una observación.
  - `->confirmar(int $id): void` (pasa a Validada), `->aprobar(int $id): void` (Aprobada) y `->rechazar(int $id, string $motivo): void` (Rechazada, con un motivo obligatorio de máximo 1 000 caracteres).
  - Todo error de la BD llega como `ErrorDeNegocio` y se muestra como alerta en el detalle.

- [ ] **Step 1: Escribir las pruebas que fallan.**
  - CA-9: `acciones` de una postulación Pendiente con un documento Pendiente da `confirmar = false` y `aprobar = false` con «Primero confirma los datos».
  - `rechazar` sin motivo lanza `ErrorDeNegocio`.
  - Review Focus 2, integración: una convocatoria con 1 cupo y dos postulaciones Validadas. La primera se aprueba bien; la segunda lanza `ErrorDeNegocio` con «Ya no hay cupos en esta convocatoria», que traduce el mensaje de la BD si es otro.
  - Review Focus 3: un aprendiz que pide `/admin/postulaciones/{id}` recibe 403.
- [ ] **Step 2: Correrlas.** FAIL.
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar** la vista igual a `03-detalle-postulacion`, en sus estados (sin «Pedir documento», que es de la fase 2). Si un documento está «No válido», la vista explica que solo queda rechazar (spec §9.2). Rechazar abre un modal con el motivo.
- [ ] **Step 5: Correr las pruebas.** PASS.
- [ ] **Step 6: Commit verde** `feat(app): revisión y decisión de postulaciones`.

### Task 13: «Mi proceso» del aprendiz (M7 y la entrada a M5)

**Files:**
- Create: `src/Servicios/ServicioMiProceso.php`, `src/Controladores/MiProcesoControlador.php`, `vistas/mi_proceso/ver.php` y los parciales `vistas/mi_proceso/{tarjeta_convocatoria,tarjeta_postulacion}.php`
- Test: `pruebas/Unidad/Servicios/ServicioMiProcesoTest.php`, `pruebas/Integracion/MiProcesoTest.php`

**Interfaces:**
- Consumes: `RepositorioConvocatorias::abiertasPara` y `RepositorioPostulaciones::delAprendiz(int $id): array` (que se agrega aquí).
- Produces: `ServicioMiProceso::resumen(int $idAprendiz): array{abiertas: array, postulaciones: array<array{..., estadoVista: string}>}`. El `estadoVista` sale del estado de la postulación:
  - Pendiente o Validada → `en-revision`;
  - Aprobada → `seleccionado`;
  - Rechazada → `no-seleccionado`.

  Si no hay ninguna convocatoria abierta ni ninguna postulación, el estado es `sin-postulacion`, con el texto «En el momento no hay convocatorias disponibles».

- [ ] **Step 1: Escribir las pruebas que fallan.**
  - Con 2 convocatorias abiertas y una postulación en una de ellas, `abiertas` tiene 1 (la otra) y `postulaciones` tiene 1 en `en-revision`.
  - Una postulación Rechazada da `no-seleccionado`, sin el motivo interno (CA-2 de la spec 002).
  - Sin nada, el estado es `sin-postulacion`.
- [ ] **Step 2: Correrlas.** FAIL.
- [ ] **Step 3: Commit rojo.**
- [ ] **Step 4: Implementar** la vista igual a `01-mi-proceso` (`convocatoria-abierta`, `en-revision`, `seleccionado` sin pagos, `no-seleccionado` y `sin-postulacion`), con una tarjeta por cada convocatoria abierta (el botón «Postularme» lleva a `/convocatorias/{id}/postulacion`, CA-3 de la spec 002) y una por cada postulación.
- [ ] **Step 5: Correr las pruebas.** PASS.
- [ ] **Step 6: Commit verde** `feat(app): mi proceso del aprendiz`.

### Task 14: Cierre del MVP (M8)

**Files:**
- Modify: `aplicacion/LEEME.md` (versión final), `CHANGELOG.md` (3.0.0) y `specs/003-aplicacion-mvp.md` (estado: implementada)
- Create: `aplicacion/pruebas/Integracion/PermisosRutasTest.php`

- [ ] **Step 1: Escribir `PermisosRutasTest`** (CA-3 y CA-4):
  - un aprendiz recibe 403 en cada ruta `/admin/*` de la tabla de rutas;
  - cada POST de la tabla sin `_csrf` recibe 403;
  - sin sesión, toda ruta salvo `/ingresar` redirige a `/ingresar`.
- [ ] **Step 2: Correr la suite completa** (`composer pruebas`, con la BD 3398), `composer analisis` y `composer formato -- --dry-run`, más la cobertura (`--coverage-text`, ≥ 80 % en Servicios y Validacion). Todo en verde.
- [ ] **Step 3: Revisión visual** (CA-11): captura de cada pantalla a 1440 y a 390 px (con Playwright, fuera del repositorio, igual que en la spec 002), comparada con su maqueta, sin desplazamiento horizontal. Las rutas de los PNG se le dan a Samuel.
- [ ] **Step 4: Comprobar los comentarios** (CA-12): un script `herramientas/revisar_comentarios.php` revisa que cada archivo de `src/` tenga su bloque inicial y que cada método público tenga `/**`. Se incluye en `composer analisis`.
- [ ] **Step 5: Escribir la versión final del `LEEME.md`:**
  - instalación en XAMPP paso a paso (BD → datos iniciales → usuario de la app → `composer install` → `config.local.php` → `crear_administrador.php`);
  - **el recorrido de una petición con el ejemplo del login**, archivo por archivo;
  - qué hay en cada carpeta;
  - cómo correr las pruebas.
- [ ] **Step 6: Revisión final.** `security-scanner` y `code-reviewer` sobre toda la app; se corrigen los hallazgos bloqueantes.
- [ ] **Step 7: Commit** `docs: cierre del MVP 3.0.0`. Pedir el «sí» de Samuel antes del `merge --no-ff` a `main`.
