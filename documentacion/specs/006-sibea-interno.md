# 006 — SIBEA también por dentro: código, base de datos, pantallas del catálogo y receta del diagrama

## Objetivo
La spec 005 puso el nombre SIBEA solo en lo visible. El 2026-11-15 Samuel decidió llevarlo también
a lo interno: el código, la base de datos y las fotos del catálogo de pantallas. Además, se guarda en
el proyecto la «receta» (scripts) que construye y revisa el diagrama de casos de uso.
Significado de la sigla, siempre escrito igual: **SIBEA — Sistema de Información de Beneficios y Apoyos**.

## Historias de usuario
- Como aprendiz que sustenta, quiero que el código y la base de datos se llamen SIBEA, para que
  no me pregunten por qué hay dos nombres.
- Como instructor, quiero que las fotos del catálogo de pantallas muestren SIBEA.
- Como equipo, queremos poder rehacer el diagrama si cambia la matriz.

## Criterios de aceptación

### Parte A — Código (rama `feat/006-sibea-interno`)
- **CA-A1** El espacio de nombres PHP pasa de `BenefitRack\` a `SIBEA\` (y `BenefitRack\Pruebas\` a
  `SIBEA\Pruebas\`), con `composer.json` (`name` `sibea/aplicacion`, descripción con el significado)
  y el autoload regenerado.
- **CA-A2** `public/css/benefitrack.css` → `sibea.css` y `public/js/benefitrack.js` → `sibea.js`, con
  las plantillas apuntando a los nombres nuevos.
- **CA-A3** Comentarios, prefijos de los registros de error (`[BenefitRack]` → `[SIBEA]`), nombres de
  cookies o de sesión, y textos de las herramientas dicen SIBEA.
- **CA-A4** La pantalla de acceso (computador y celular) muestra, debajo de la marca, el significado
  «Sistema de Información de Beneficios y Apoyos». `LEEME.md` de la raíz, `codigo/aplicacion/LEEME.md`
  y la cabecera de `public/index.php` empiezan con «SIBEA — Sistema de Información de Beneficios y Apoyos».
- **CA-A5** Una prueba automática comprueba que en `codigo/` (sin `vendor/`) no queda la palabra
  «benefitrack», sin importar mayúsculas.

### Parte B — Base de datos (misma rama, con el agente guardian-base-datos)
- **CA-B1** `codigo/base-de-datos/benefitrack_v2.sql` → `sibea.sql`; la base se llama `sibea` y el
  usuario de la aplicación `sibea_app`. La estructura, los datos iniciales y los permisos no cambian.
- **CA-B2** Todo lo que depende de esos nombres se ajusta: `config.ejemplo.php`, pruebas PHP de
  integración, pruebas SQL (`pruebas_reglas.sql`, `otro_usuario/`, `concurrencia/`), `README.md` de la
  base y los LEEME.
- **CA-B3** En una instancia temporal (puerto 3398): `sibea.sql` corre dos veces seguidas sin errores,
  las pruebas de reglas, del usuario de la aplicación, otro_usuario y concurrencia dan el mismo
  resultado que antes, y la suite PHP completa queda en verde.
- **CA-B4** El informe trae los comandos exactos para pasar la base de Samuel (XAMPP, puerto 3306) de
  `benefitrack_v2` a `sibea` copiando los datos, SIN borrar `benefitrack_v2` (queda de respaldo).
  Esos comandos los ejecuta el orquestador, no el agente.
- **CA-B5** `documentacion/entregables/Matriz_Requisitos_v2.xlsx` y la ficha citan `sibea.sql` (y no
  `benefitrack_v2.sql`) donde nombran el script; no cambia nada más en ellas.

### Parte C — Fotos del catálogo (rama `feat/007-pantallas-sibea`)
- **CA-C1** Las maquetas se recuperan del historial (commit anterior a 448f103), se les cambia
  «BenefitRack» por «SIBEA» (y la pantalla de acceso muestra el significado, igual que CA-A4) y se
  vuelve a generar el catálogo `Pantallas_SIBEA.html/.pdf` con las mismas pantallas y versiones.
- **CA-C2** Ninguna foto ni texto del catálogo dice «BenefitRack» (se comprueba buscando el texto en el
  HTML y revisando las imágenes).
- **CA-C3** Las maquetas y el generador NO vuelven al repositorio (Samuel los quitó a propósito el
  2026-11-12); se trabajan en una carpeta temporal.

### Parte D — Receta del diagrama (rama `chore/receta-diagrama`)
- **CA-D1** Los scripts `construir.ps1`, `parte2.ps1`, `verificar.ps1` y `exportar.ps1` quedan en
  `documentacion/herramientas/diagrama/`, sin rutas fijas de la carpeta temporal (reciben las rutas
  como parámetros), con un `LEEME.md` sencillo: qué hace cada uno, qué se necesita (Enterprise Architect 8,
  PowerShell de 32 bits) y el comando para rehacer y revisar el diagrama.
- **CA-D2** Ejecutados desde la nueva carpeta, `verificar.ps1` sobre `SIBEA_CasosDeUso.eap` da 61/0.

## Fuera de alcance
- Renombrar la carpeta del proyecto `C:\Users\samue\BenefitRack` (rompería rutas de XAMPP y de las
  herramientas; se puede hacer aparte).
- Las specs 001, 003, 004 y 005 y el ADR 0001: son historia, se dejan como se escribieron.
- Borrar la base `benefitrack_v2` de XAMPP (lo decide Samuel después).
- Cambiar el diagrama `.eap`.

## Casos borde
- Un cambio de nombre de cookie de sesión cierra las sesiones abiertas: aceptable (es una demo).
- Los archivos binarios (`.xlsx`, `.docx`) se editan sin dañar su formato y se comprueba que abren.
