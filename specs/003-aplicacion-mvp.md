# Spec 003 — Aplicación BenefitRack: primera versión que funciona (MVP)

- **Estado:** implementada (versión 3.0.0, 2026-11-11)
- **Fecha:** 2026-11-10
- **Rama:** `feat/003-aplicacion-mvp`
- **Depende de:** spec 001 (BD `benefitrack_v2`) y spec 002 (pantallas aprobadas en `vistas/`)
- **Arquitectura:** `docs/adr/0001-arquitectura-de-la-aplicacion.md`

## 1. Objetivo y por qué

Convertir las pantallas aprobadas en una **aplicación web real** conectada a la base de datos. La primera versión resuelve el dolor principal: **el administrador deja de copiar a mano cada respuesta al Excel**. El aprendiz se postula en BenefitRack, el administrador revisa y decide en pantalla, y descarga el Excel con un clic.

**Condición de Samuel (2026-11-10):** el código tiene que ser **limpio, con buenas prácticas y fácil de explicar en la sustentación**. Se permite usar IA, pero Samuel debe saber con claridad qué hace cada parte. Por eso:

- Se usan los lenguajes que el equipo conoce: **PHP, JavaScript y Bootstrap**.
- **PHP puro organizado en MVC por capas, sin Laravel** (ver la ADR 0001). Es un paso pequeño desde los «PHP sueltos» que ya conocen.
- Se construye **por funciones completas, de punta a punta** (pantalla + PHP + BD), una a la vez.
- **El código lleva comentarios en español en varias partes** (ver §6).

## 2. Historias de usuario del MVP

1. **Como administrador**, quiero crear las cuentas de los aprendices (una a mano o muchas desde un Excel) para que puedan entrar.
2. **Como administrador**, quiero crear y abrir una convocatoria, y después cerrarla, para que los aprendices se postulen.
3. **Como aprendiz**, quiero entrar con mi cuenta, cambiar la contraseña la primera vez y postularme llenando el formulario con mis documentos.
4. **Como administrador**, quiero ver las postulaciones con filtros, revisar el detalle y los documentos, confirmar los datos, aprobar o rechazar con un motivo, y **descargar todo en Excel**.
5. **Como aprendiz**, quiero ver en «Mi proceso» en qué va mi postulación (en revisión, seleccionado o no seleccionado).

## 3. Qué entra en el MVP (por módulos, en orden de construcción)

| # | Módulo | Pantallas que usa (`vistas/`) |
|---|---|---|
| M0 | **Base del proyecto**: estructura de carpetas, Composer, configuración, conexión a la BD, enrutador, sesión, CSRF, plantilla general (barra y menú), páginas de error 403/404/500, guía `LEEME.md` del código. | Barra y menú de `00-existentes`. |
| M1 | **Iniciar y cerrar sesión**: 3 intentos fallidos bloquean la cuenta, se desbloquea sola a los 15 min (eso lo hace la BD), y mensajes de cuenta bloqueada o inactiva. | `00-existentes/login` (6 estados). |
| M2 | **Primer ingreso**: el aprendiz debe cambiar la contraseña que le dio el administrador. | `02-contrasena` (`primer-ingreso`, `error-validacion`, `exito`). |
| M3 | **Usuarios (admin)**: lista con buscador; crear una cuenta; cargar Excel con plantilla, vista previa de errores y resultado; activar, desactivar, desbloquear (con justificación) y **restablecer la contraseña** de un aprendiz. | `06-usuarios`. |
| M4 | **Convocatorias (admin)**: lista, crear y editar, activar, cerrar y cierre anticipado con motivo. | `07-convocatorias`. |
| M5 | **Postularse (aprendiz)**: «Mi proceso» con `convocatoria-abierta`, formulario por secciones, subida de los 3 documentos, revisar y enviar, enviada, convocatoria cerrada y «ya te postulaste». | `01-mi-proceso` (estados sin pagos), `09-postulacion`. |
| M6 | **Postulaciones (admin)**: lista con buscador, filtros y paginación; detalle con documentos (ver y descargar); confirmar datos (pasa a Validada); aprobar (si hay cupo); rechazar con motivo; **descargar en Excel** eligiendo columnas. | `00-existentes/postulaciones`, `03-detalle-postulacion`. |
| M7 | **Mi proceso (aprendiz)**: estados `en-revision`, `seleccionado` (sin pagos todavía) y `no-seleccionado`. | `01-mi-proceso`. |
| M8 | **Cierre**: suite completa, análisis estático, revisión de seguridad y de código, CHANGELOG 3.0.0 y guía de instalación en XAMPP. | — |

La página de inicio del administrador en el MVP es **Postulaciones**. El Dashboard con cifras llega en la fase 2.

## 4. Fuera de alcance (fase 2 o después)

- **Pagos** (vista 05) y la parte de pagos de «Mi proceso».
- **Reporte de aprobados** (vista 04).
- **Avisos / campanita** (vista 01b) y **envío de correos**. La BD guarda las notificaciones, pero no se envían.
- **Pedir un documento y que el aprendiz lo suba** (vista 08).
- **Recuperar la contraseña por correo** (vista 02, `recuperar-*` y `enlace-vencido`). En el MVP, si alguien la olvida, el administrador se la restablece.
- **Dashboard con cifras** (vista 00).
- Publicar en un servidor de internet. El MVP corre en XAMPP.
- Pantallas de auditoría y de catálogos (EPS, bancos, programas), guardar el formulario como borrador y que el aprendiz edite su perfil (igual que en la spec 002).

## 5. Arquitectura (resumen de la ADR 0001)

- **PHP 8.2 puro**, MVC por capas: `public/index.php` (todo entra por aquí) → enrutador → filtros (sesión, rol, CSRF) → **Controlador** → **Servicio** (reglas) → **Repositorio** (único lugar con SQL) → MariaDB. La respuesta se arma con una **Vista** (plantilla PHP).
- **Ubicación:** carpeta `aplicacion/` dentro del repositorio, con `public/`, `src/` (`Nucleo`, `Controladores`, `Servicios`, `Repositorios`, `Validacion`), `vistas/`, `pruebas/`, `config/` y `almacenamiento/`.
- **Composer** para la carga automática de clases. Librerías: `phpoffice/phpspreadsheet` (Excel), y en desarrollo `phpunit`, `phpstan` y `php-cs-fixer`.
- **Bootstrap 5.3**, copia local (funciona sin internet en la sala del SENA), más un `benefitrack.css` con los colores y la letra de las maquetas aprobadas. **Las maquetas de `vistas/` son la referencia visual**: la pantalla real debe verse como la maqueta aprobada; se aceptan diferencias mínimas que traiga Bootstrap.
- **JavaScript puro**, solo donde ayuda: validación en el navegador, confirmaciones y mostrar el nombre del archivo elegido.
- **Nombres en español** para clases, métodos, variables, rutas y tablas (por ejemplo `ServicioPostulacion::enviar()`), para que se lean como se explican. Las palabras propias de PHP siguen en inglés.
- **Conexión a la BD** con el usuario `benefitrack_app` (nunca `root`). En cada petición, después de identificar al usuario, la app ejecuta `SET @app_id_usuario = ?, @app_ip = ?`, y en el inicio de sesión lo deja en `NULL` (`base-de-datos/README.md` §3). Los errores que lanza la BD (`SIGNAL 45000`) se traducen a mensajes sencillos.
- **Configuración** en `config/config.local.php` (fuera de git), con una copia `config.ejemplo.php` en el repositorio. Ninguna clave va al repositorio.

## 6. Reglas de código limpio (obligatorias en todo el MVP)

1. **Comentarios en español en varias partes** (pedido de Samuel):
   - Al inicio de cada archivo, un bloque que dice **qué hace el archivo y en qué parte del recorrido de la petición está**.
   - Cada método público lleva un comentario (`/** ... */`) con **qué hace, qué recibe y qué devuelve**, en lenguaje sencillo.
   - Comentarios dentro del código en los pasos que no son obvios: seguridad, reglas del negocio y llamadas a la BD. No se comenta lo evidente (`$i++ // suma uno`).
2. **Funciones cortas** (idealmente menos de 30 líneas) y **una responsabilidad por clase** (SOLID, DRY, KISS, YAGNI).
3. **Sin SQL fuera de los repositorios** y **sin HTML fuera de las vistas**.
4. **`declare(strict_types=1);`** y tipos en todos los parámetros y retornos.
5. **Seguridad por defecto** (OWASP Top 10):
   - Consultas **siempre preparadas** (PDO, sin concatenar datos).
   - Todo dato que se muestra pasa por `e()` (`htmlspecialchars`).
   - **Token CSRF** en todo formulario que cambie datos.
   - Contraseñas con `password_hash` / `password_verify`.
   - Al iniciar sesión, `session_regenerate_id(true)`. Cookie `HttpOnly` y `SameSite=Lax`.
   - Revisión del **rol** en cada ruta, y de que el aprendiz **solo vea lo suyo**.
   - Archivos: solo PDF, JPG o PNG, máximo 5 MB. El tipo real se revisa con `finfo`. Se guardan con nombre aleatorio fuera de `public/` y se entregan por un controlador que revisa el permiso.
   - Excel exportado protegido contra **inyección CSV/fórmulas**: un texto que empiece por `=`, `+`, `-` o `@` se guarda como texto.
6. **Formato y análisis:** `php-cs-fixer` (estilo PSR-12) y `phpstan` nivel 6 o más, sin errores, antes de cada merge.
7. **Guía para la sustentación:** `aplicacion/LEEME.md` explica, en lenguaje sencillo, cómo instalarla en XAMPP, el **recorrido de una petición** con un ejemplo real (iniciar sesión) y para qué sirve cada carpeta.

## 7. Pruebas (TDD: rojo → verde → refactor)

- **PHPUnit `Unidad/`**: validadores, servicios (con repositorios falsos), enrutador, CSRF y el escape de salida.
- **PHPUnit `Integracion/`**: repositorios y flujos contra una **BD de prueba temporal** (instancia de MariaDB en el puerto 3398 cargada con `benefitrack_v2.sql`, igual que en la spec 001). Nunca contra `C:\xampp\mysql\data`.
- **Cobertura ≥ 80 %** en `Servicios/` y `Validacion/`.
- Cada módulo termina con la **verificación del orquestador** (suite completa), la revisión de `code-reviewer` y el «sí» de Samuel antes del merge.

## 8. Criterios de aceptación

- **CA-1** Dado un usuario con 2 intentos fallidos, cuando falla el 3.º, entonces ve el mensaje de cuenta bloqueada. A los 15 minutos puede entrar otra vez sin ayuda.
- **CA-2** Dado un aprendiz cuya contraseña la puso el administrador, cuando entra, entonces solo puede ir a «Cambiar contraseña» hasta que la cambie.
- **CA-3** Dado un aprendiz con sesión iniciada, cuando escribe la URL de una pantalla del administrador o de la postulación de otro aprendiz, entonces recibe «No tienes permiso» (403) y no ve ningún dato ajeno.
- **CA-4** Dado un formulario enviado sin token CSRF o con uno inválido, entonces la aplicación lo rechaza y no cambia nada.
- **CA-5** Dado un Excel de aprendices con filas erróneas, cuando el administrador lo carga, entonces ve qué filas fallan y por qué **antes** de crear cuentas, y al confirmar solo se crean las filas correctas.
- **CA-6** Dada una convocatoria Activa y dentro de fechas, cuando el aprendiz envía el formulario completo con los 3 documentos (incluido el **Certificado de EPS**), entonces la postulación queda Pendiente con sus documentos, y «Mi proceso» muestra `en-revision`.
- **CA-7** Dado un formulario con faltantes o un archivo de más de 5 MB o de otro tipo, cuando el aprendiz pulsa «Revisar y enviar», entonces ve el resumen de errores arriba y no se guarda nada.
- **CA-8** Dado un aprendiz que ya se postuló a esa convocatoria, cuando vuelve al formulario, entonces ve «Ya te postulaste a esta convocatoria».
- **CA-9** Dada una postulación Pendiente, «Aprobar» está deshabilitado hasta confirmar los datos. Con la convocatoria sin cupos, «Aprobar» está deshabilitado y la pantalla dice por qué. Rechazar exige un motivo.
- **CA-10** Dadas postulaciones filtradas, cuando el administrador pulsa «Descargar en Excel» y elige columnas, entonces descarga un `.xlsx` con esas columnas y filas, y una celda que empiece por `=` se ve como texto.
- **CA-11** Toda pantalla del MVP se ve como su maqueta aprobada, a 1440 px y a 390 px, sin desplazamiento horizontal.
- **CA-12** Cada archivo PHP de `src/` tiene su bloque de comentario inicial, y cada método público su comentario. `phpstan` y `php-cs-fixer` no reportan errores.

## 9. Cambio necesario en la BD

La tabla `usuarios` no tiene cómo saber si es el **primer ingreso**. *Decisión de Claude:* agregar la columna `debe_cambiar_contrasena TINYINT(1) NOT NULL DEFAULT 1`. La pone en 1 el administrador al crear o restablecer una cuenta, y en 0 el propio usuario al cambiar su contraseña. Se agrega a `benefitrack_v2.sql` con sus pruebas (que la suite de la BD siga en verde) y se anota en `base-de-datos/CAMBIOS.md`.

## 9.1 Decisiones de Samuel (2026-11-10)

- **Varias convocatorias abiertas a la vez.** «Mi proceso» muestra una tarjeta por cada convocatoria abierta a la que el aprendiz aún no se ha postulado, y cada postulación suya por separado. El aprendiz puede postularse a cada convocatoria una vez (la BD ya no permite dos a la misma).
- **Contraseña temporal aleatoria.** Al crear cuentas (de a una o desde el Excel) o al restablecer una contraseña, el sistema genera una contraseña temporal aleatoria por aprendiz (al menos 10 caracteres, con `random_bytes`, sin caracteres que se confundan como `0/O` o `1/l`). El administrador **descarga un Excel de entrega** con documento, nombre, correo y contraseña temporal, para dárselo a cada aprendiz. Al crear una sola cuenta, la contraseña también se muestra **una sola vez** en pantalla. Ese Excel no se guarda en el servidor: se genera y se descarga en ese momento, y la pantalla avisa que se guarde en un lugar seguro. En la BD solo queda el hash. El aprendiz debe cambiarla al primer ingreso (§9). Se descartó usar el número de documento porque no es secreto. El texto «la recibe en su correo» de las maquetas `06-usuarios` cambia a «descarga el Excel con las contraseñas temporales para entregárselas».

## 9.2 Decisiones de Claude tras la revisión del constructor (Samuel puede cambiarlas)

- **Dónde quedan los datos del formulario** (lo mismo que dice la spec 002, vista 9): los datos personales (teléfono, dirección, fecha de nacimiento, EPS) se actualizan en `usuarios`; la cuenta en `informacion_bancaria` (una por aprendiz: si se postula otra vez, se **actualiza** con la nueva); los archivos en `documentos_postulacion`; y una **copia completa de lo que envió** en `postulaciones.datos_formulario`, para que la postulación conserve lo que dijo ese día. El Excel de M6 lee de esa copia.
- **Revisión de cada documento** (como en la maqueta aprobada `03-detalle-postulacion`): entra en M6. Cada documento se marca «Válido» o «No válido» con motivo, y «Confirmar datos» exige que todos estén válidos. Como «pedir un documento» es de la fase 2, en el MVP una postulación con un documento no válido **solo se puede rechazar** (con motivo).
- **Menú del administrador en el MVP:** Convocatorias · Postulaciones · Usuarios. «Dashboard» y «Pagos» **se ocultan** hasta la fase 2, para no dejar enlaces que no llevan a nada.
- **Datos iniciales (catálogos):** la BD no trae beneficios, programas ni bancos, y sin ellos no se puede crear una convocatoria ni llenar el formulario. Se agrega `base-de-datos/datos_iniciales.sql` (aparte del esquema, se puede volver a ejecutar sin duplicar) con: beneficios Alimentación, Plan de datos y Cívica; los bancos más comunes de Colombia; y programas de ejemplo del Centro Textil, que Samuel corrige si hace falta.

## 10. Casos borde

- Nombres largos, tildes y ñ (todo en `utf8mb4`).
- Dos administradores aprueban a la vez la última plaza: la BD lo impide. La app muestra «Ya no hay cupos» y no un error técnico.
- Un archivo con extensión `.pdf` que en realidad no es PDF: se rechaza.
- La sesión vence a mitad del formulario: se vuelve al inicio de sesión con el aviso «Tu sesión terminó».
- Un Excel vacío, con columnas de más o de menos, o con documentos repetidos dentro del mismo archivo.

## 11. Orden de trabajo

M0 → M1 → M2 → M3 → M4 → M5 → M6 → M7 → M8. Cada módulo se hace en su propia rama (`feat/003-<modulo>`), con TDD, y se une a `main` solo después de la verificación, la revisión y el «sí» de Samuel. El plan detallado, tarea por tarea, va en `docs/superpowers/plans/2026-11-10-aplicacion-mvp.md` cuando Samuel apruebe esta spec.
