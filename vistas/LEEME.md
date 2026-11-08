# Vistas de BenefitRack

Aquí están las pantallas del sistema, dibujadas como páginas HTML estáticas (maquetas) con su captura de pantalla. Lo que se debe hacer y por qué está en `specs/002-vistas.md`.

Piensa en esta carpeta como un taller: hay moldes (lo común), planos de cada pieza (la fuente), las piezas terminadas (la salida) y un inspector que revisa que todo salga bien (la verificación).

## Qué hay en cada carpeta

| Carpeta o archivo | Para qué sirve |
|---|---|
| `estados.json` | La lista oficial de pantallas y de los estados de cada una (copiada de la spec). El inspector la usa para saber qué debe existir. |
| `_comun/estilo.css` | Letra, colores y piezas visuales compartidas (tarjeta, etiqueta, aviso, pasos, tabla, botones, diálogo, estado vacío). |
| `_comun/barra-aprendiz.html` | Barra de arriba del aprendiz: logo, «Hola, nombre», campanita con contador y «Salir». |
| `_comun/menu-admin.html` | Menú lateral y cabecera del administrador: Dashboard, Convocatorias, Postulaciones, Pagos, Usuarios. |
| `_fuente/<vista>/<estado>.html` | Los planos. **Aquí se edita.** |
| `<vista>/html/<estado>.html` | La pieza terminada: una página autocontenida (CSS adentro). **No se edita a mano**, se genera. |
| `<vista>/computador/<estado>.png` | Captura a 1440 px de ancho. Se genera. |
| `<vista>/celular/<estado>.png` | Captura a 390 px de ancho. Se genera. |
| `00-existentes/` | Copia de los diseños que ya hizo Samuel: `login/` (inicio de sesión, tal cual vino del zip) y `postulaciones/html/` (el panel «Revisa las postulaciones», desempaquetado y sin la opción «Auditoría»). El inspector no los revisa. |
| `herramientas/construir.js` | Arma las páginas terminadas y saca las capturas. |
| `herramientas/verificar.js` | El inspector. |
| `herramientas/pruebas/_global.js` | Revisiones que se aplican a todas las pantallas. |
| `herramientas/pruebas/<vista>.js` | Revisiones propias de cada pantalla (se agregan con cada vista). |

Las carpetas de vistas se llaman `00-dashboard`, `01-mi-proceso`, `01b-avisos`, `02-contrasena`, `03-detalle-postulacion`, `04-reporte-aprobados`, `05-pagos`, `06-usuarios`, `07-convocatorias` y `08-subir-documento`. No confundir `00-dashboard` (pantalla nueva) con `00-existentes` (copia de lo ya hecho).

## Instalar las herramientas (una sola vez, fuera del repositorio)

Las herramientas usan `playwright-core` (maneja el navegador) y `axe-core` (revisa accesibilidad). No se instalan dentro del repositorio: no debe aparecer `node_modules` ni `package.json` aquí. Se usa el Microsoft Edge que ya trae Windows, así que no se descarga ningún navegador.

```bash
mkdir -p "$CARPETA_TEMPORAL/deps-vistas" && cd "$CARPETA_TEMPORAL/deps-vistas"
npm init -y
npm i playwright-core@1.48.2 axe-core@4.10.2
```

`$CARPETA_TEMPORAL` es cualquier carpeta fuera del repositorio. Después, cada comando se ejecuta con la variable `NODE_PATH` apuntando a esa instalación:

```bash
export NODE_PATH="$CARPETA_TEMPORAL/deps-vistas/node_modules"
```

## Construir y verificar

Desde la raíz del repositorio (`BenefitRack`):

```bash
node vistas/herramientas/construir.js                       # todas las vistas
node vistas/herramientas/construir.js --vista 01-mi-proceso # solo una
node vistas/herramientas/verificar.js                       # revisa todo
node vistas/herramientas/verificar.js --vista 01-mi-proceso # revisa una
```

`verificar.js` imprime `OK` o `FALLA <vista>/<estado>: <motivo>` por cada revisión y termina con código 1 si algo falló. Revisa, entre otras cosas: que exista el HTML de cada estado de `estados.json`, que las tildes se vean bien, que no haya desplazamiento horizontal a 390 px, la letra Work Sans, que solo se usen los colores de la paleta, el contraste de texto (4.5:1), que las capturas midan 1440 y 390 px, que el aprendiz no tenga botones para postularse y que el administrador tenga siempre el mismo menú.

## Cómo se escribe una vista nueva

1. Crear `_fuente/<vista>/<estado>.html` (un archivo por estado). Esqueleto:

   ```html
   <!doctype html>
   <html lang="es">
   <head>
     <meta charset="utf-8">
     <meta name="viewport" content="width=device-width, initial-scale=1">
     <title>Mi proceso · Seleccionado</title>
     <link rel="stylesheet" href="../../_comun/estilo.css">
   </head>
   <body>
     <!-- incluir:barra-aprendiz nombre="Daniela" avisos="2" -->
     <main class="br-pagina"> ... </main>
   </body>
   </html>
   ```

   Para el administrador, el cuerpo va dentro de `<div class="br-admin">` con el menú y el contenido en un `<main>`:

   ```html
   <div class="br-admin">
     <!-- incluir:menu-admin activo="Postulaciones" -->
     <main> ... </main>
   </div>
   ```

2. `construir.js` reemplaza `<!-- incluir:... -->` por la pieza de `_comun/` (en el menú marca la opción activa con `aria-current="page"`) y cambia el `<link>` del CSS por un `<style>` con su contenido.
3. Ejecutar `construir.js --vista <vista>` y luego `verificar.js --vista <vista>`.
4. Los diálogos se escriben como `<div class="br-dialogo-capa"><div class="br-dialogo" role="dialog" aria-modal="true" aria-labelledby="..."> ... </div></div>` al final del `<body>`.
5. Las tablas van dentro de `<div class="br-tabla-envoltura">` para que, si no caben en el celular, se desplacen solas sin mover toda la página.

## Reglas que conviene recordar

- Solo se usan los colores de `_comun/estilo.css` (la paleta de la spec, sección 2, más los del aviso naranja del boceto aprobado). La paleta no tiene rojo: lo destructivo (`.br-boton--peligro`) va en naranja oscuro.
- Los grises `#6B7C88` y `#7D8E9A` están permitidos, pero sobre fondos claros no llegan a 4.5:1 como texto. Para texto secundario se usa `#556776`. Si una vista usa un gris claro para texto, la verificación de contraste decide si está bien.
- Todo botón y campo tiene contorno de foco visible (`:focus-visible`) definido en el CSS común.
- Textos en español con tildes y ñ; nombres colombianos inventados, `$ 180.000`, fechas «15 nov 2026». Nunca nombres reales del equipo.
- Las vistas del aprendiz nunca llevan botones ni enlaces para postularse.
- La barra del aprendiz trae la campanita cerrada. La vista `01b-avisos` (panel desplegable abierto) amplía la pieza cuando se diseñe.
