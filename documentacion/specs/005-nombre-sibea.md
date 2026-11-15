# 005 — El sistema se llama SIBEA (solo lo visible)

## Objetivo
Desde el 2026-11-15 el equipo decidió que el sistema se llama **SIBEA — Sistema de Información
de Beneficios y Apoyos**. Este cambio pone el nombre nuevo en todo lo que ve una persona
(pantallas de la aplicación y documentos de entrega) sin tocar los nombres internos.
«BenefitRack» queda como nombre de trabajo interno.

## Historia de usuario
Como aprendiz o administrador que usa el sistema, quiero ver el nombre SIBEA en las pantallas,
y como instructor quiero ver SIBEA en los documentos de entrega.

## Criterios de aceptación
- **CA-1** Dada cualquier pantalla (pública, aprendiz o administrador), cuando se abre, entonces
  la pestaña del navegador dice «<título> · SIBEA» y la marca visible (menú lateral, barra del
  aprendiz, pantalla de acceso en computador y celular) dice «SIBEA».
- **CA-2** Dada la página de error del arranque, entonces su título dice «Error · SIBEA».
- **CA-3** Dado el detalle de una postulación, entonces el texto del aviso dice «recibe un aviso en SIBEA».
- **CA-4** Dado el código de vistas y plantillas, entonces no queda ningún texto visible con
  «BenefitRack» (una prueba automática lo comprueba).
- **CA-5** La suite completa de la aplicación sigue en verde (538 pruebas o más), igual que PHPStan
  y el formateador.
- **CA-6** Documentos de entrega con el nombre nuevo:
  - `LEEME.md` de la raíz y `codigo/aplicacion/LEEME.md` (títulos y textos de presentación);
  - `documentacion/entregables/Ficha_Proyecto_BenefitRack_v2.docx` → `Ficha_Proyecto_SIBEA_v2.docx`, con el nombre de la aplicación «SIBEA — Sistema de Información de Beneficios y Apoyos»;
  - `documentacion/entregables/Matriz_Requisitos_v2.xlsx`: donde aparezca el nombre del sistema;
  - `Pantallas_BenefitRack.html/.pdf` → `Pantallas_SIBEA.html/.pdf`, con el nombre nuevo en portada y textos;
  - enlaces entre documentos actualizados (que ninguno apunte a un archivo renombrado).

## Fuera de alcance (Samuel decide después de revisar)
- Nombres internos: espacio de nombres PHP `BenefitRack\...`, `composer.json`, la base de datos
  `benefitrack_v2`, el usuario `benefitrack_app`, archivos `benefitrack.css`/`benefitrack.js`,
  prefijos `[BenefitRack]` de los registros de error, carpetas, scripts y pruebas de la BD.
- Cualquier cambio en la base de datos.
- Las specs 001 y 003 y el ADR 0001 (son historia: se dejan como se escribieron), salvo las rutas a
  archivos renombrados, para que ningún enlace quede roto.
- Subir archivos al Drive.

## Pendiente para Samuel
- Las capturas dentro de `Pantallas_SIBEA.html/.pdf` son fotos de las maquetas viejas y siguen mostrando
  «BenefitRack»: volver a tomarlas es una decisión aparte.

## Casos borde
- Comentarios del código que dicen «BenefitRack» no se ven en pantalla: se dejan.
- Los nombres de archivo `benefitrack.css/.js` en las etiquetas `<link>`/`<script>` son internos: se dejan.
