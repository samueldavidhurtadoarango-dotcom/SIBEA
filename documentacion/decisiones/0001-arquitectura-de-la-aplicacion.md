# ADR 0001 — Arquitectura de la aplicación: PHP puro con MVC por capas

- **Estado:** propuesta (espera la aprobación de Samuel junto con la spec 003)
- **Fecha:** 2026-11-10
- **Relacionado:** `documentacion/specs/003-aplicacion-mvp.md`

## Contexto

- El código tiene que poder **explicarlo Samuel en la sustentación**. El SENA permite usar IA, pero el aprendiz debe saber con claridad qué hace cada parte.
- El equipo sabe **PHP, JavaScript y Bootstrap**. Hasta ahora ha trabajado con **archivos PHP sueltos**: nunca con MVC ni con frameworks.
- Ya existen la **base de datos `benefitrack_v2`** (MariaDB 10.4 en XAMPP, 23 tablas, 55 triggers, procedimientos con permisos) y las **11 pantallas aprobadas** (catálogo en `documentacion/entregables/Pantallas_SIBEA.pdf`).
- La BD ya hace cumplir muchas reglas de negocio (quién puede hacer qué, estados, cupos, bloqueo de cuenta). La aplicación debe avisarle quién es el usuario mediante `@app_id_usuario` (ver `base-de-datos/README.md` §3).
- Entorno disponible: PHP 8.2 (XAMPP), Composer (incluido en Herd) y Apache de XAMPP.

## Opciones consideradas

| Opción | A favor | En contra |
|---|---|---|
| **A. Archivos PHP sueltos** (como hasta ahora) | Lo conocen. | El SQL, el HTML y la lógica quedan mezclados; se repite código (sesión, conexión, permisos) en cada archivo; es difícil de probar y de defender como «buena práctica». |
| **B. Laravel** | Muy profesional y con mucha ayuda incluida. | Hace mucho «por debajo» (rutas, ORM, inyección de dependencias, middlewares mágicos). Samuel tendría que aprender un framework entero y explicar lo que no se ve. Su ORM (Eloquent) duplica reglas que ya están en la BD. |
| **C. PHP puro con MVC por capas** (elegida) | Es un paso pequeño desde lo que saben. Cada pieza es un archivo corto y visible. Se explica de punta a punta. Se puede probar con PHPUnit. | Hay que escribir nosotros un enrutador y una protección CSRF sencillos (unas decenas de líneas cada uno, comentadas). |

## Decisión

**Opción C: PHP 8.2 puro, organizado en capas MVC, con Composer solo para la carga automática de clases y unas pocas librerías justificadas.**

### Cómo viaja una petición (la idea central que Samuel debe poder explicar)

```
Navegador ──► public/index.php (controlador frontal: TODO entra por aquí)
               │ 1. arranca la sesión y carga la configuración
               │ 2. el Enrutador busca qué controlador atiende la URL
               ▼
          Middleware (filtros): ¿hay sesión? ¿es el rol correcto? ¿el token CSRF es válido?
               ▼
          Controlador: lee el formulario, llama al servicio y elige la vista
               ▼
          Servicio: aplica las reglas de la aplicación (validar, decidir)
               ▼
          Repositorio: el ÚNICO lugar con SQL (consultas preparadas con PDO)
               ▼
          MariaDB benefitrack_v2 (con sus triggers y permisos)
               ▲
          Vista (plantilla PHP basada en las maquetas aprobadas) ──► HTML al navegador
```

### Capas y su única responsabilidad

| Carpeta | Qué hace | Qué NO hace |
|---|---|---|
| `public/` | Lo único visible desde el navegador: `index.php`, CSS, JS e imágenes. | No tiene lógica. |
| `src/Nucleo/` | Piezas generales: enrutador, petición, respuesta, sesión, CSRF, conexión a la BD, render de vistas. | No sabe nada de beneficios ni de convocatorias. |
| `src/Controladores/` | Reciben la petición, llaman a un servicio y eligen la vista. Son cortos. | No tienen SQL ni reglas de negocio. |
| `src/Servicios/` | Reglas de la aplicación: iniciar sesión, postularse, cargar el Excel. | No tienen HTML ni SQL. |
| `src/Repositorios/` | Todo el SQL, siempre con consultas preparadas. | No deciden nada: solo leen y guardan. |
| `src/Validacion/` | Revisan los datos de los formularios y devuelven los errores en lenguaje sencillo. | No guardan nada. |
| `vistas/` | Plantillas PHP con Bootstrap. Todo dato se escapa con `e()`. | No consultan la BD. |
| `pruebas/` | PHPUnit: `Unidad/` (sin BD) e `Integracion/` (con una BD de prueba). | — |
| `almacenamiento/` | Archivos subidos, **fuera de `public/`**. | No se sirve directamente. |

### Librerías (pocas, cada una con un motivo)

- **Composer**: carga automática de clases (PSR-4), para no escribir `require` en cada archivo.
- **phpoffice/phpspreadsheet**: leer el Excel de aprendices y generar el Excel de postulaciones.
- **phpunit/phpunit** (solo desarrollo): pruebas.
- **phpstan/phpstan** y **friendsofphp/php-cs-fixer** (solo desarrollo): análisis estático y formato (manual §4).
- **Bootstrap 5.3** (copia local en `public/`, sin depender de internet): rejilla, formularios, tablas y modales, con un `benefitrack.css` que pone los colores y la letra de las maquetas aprobadas.

Sin ORM, sin plantillas de terceros y sin frameworks de JavaScript: JavaScript puro solo donde mejora la experiencia (vista previa del Excel, validación en el navegador, confirmaciones).

## Consecuencias

- **Lo bueno:** cada archivo hace una sola cosa y se puede abrir y explicar. Las reglas importantes viven en la BD (ya probadas con 400 pruebas) y en los servicios (probados con PHPUnit).
- **Lo que cuesta:** escribir el enrutador, la sesión y el CSRF a mano. A cambio, Samuel entiende de verdad cómo funcionan, que es justo lo que pide la sustentación.
- **Para el futuro:** si un día se pasa a Laravel, la separación por capas facilita la migración: los servicios y los repositorios se mueven casi tal cual.
