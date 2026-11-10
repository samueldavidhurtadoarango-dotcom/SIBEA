# Vistas de BenefitRack

Aquí están las pantallas del sistema, dibujadas como páginas HTML estáticas (maquetas) con su captura de pantalla. Lo que se debe hacer y por qué está en `specs/002-vistas.md`.

Piensa en esta carpeta como un taller: hay moldes (lo común), planos de cada pieza (la fuente), las piezas terminadas (la salida) y un inspector que revisa que todo salga bien (la verificación).

## Qué hay en cada carpeta

| Carpeta o archivo | Para qué sirve |
|---|---|
| `estados.json` | La lista oficial de pantallas y de los estados de cada una (copiada de la spec). El inspector la usa para saber qué debe existir. |
| `_comun/estilo.css` | Letra, colores y piezas visuales compartidas (tarjeta, etiqueta, aviso, pasos, tabla, botones, diálogo, estado vacío). |
| `_comun/barra-aprendiz.html` | Barra de arriba del aprendiz: logo, «Hola, nombre», campanita con contador y «Salir». Admite `avisos="N"` y `panel="abierto"` (ver más abajo). |
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

Las carpetas de vistas se llaman `00-dashboard`, `01-mi-proceso`, `01b-avisos`, `02-contrasena`, `03-detalle-postulacion`, `04-reporte-aprobados`, `05-pagos`, `06-usuarios`, `07-convocatorias`, `08-subir-documento` y `09-postulacion`. No confundir `00-dashboard` (pantalla nueva) con `00-existentes` (copia de lo ya hecho).

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

`verificar.js` imprime `OK` o `FALLA <vista>/<estado>: <motivo>` por cada revisión y termina con código 1 si algo falló. Revisa, entre otras cosas: que exista el HTML de cada estado de `estados.json`, que las tildes se vean bien, que no haya desplazamiento horizontal a 390 px, la letra Work Sans, que solo se usen los colores de la paleta, el contraste de texto (4.5:1), que las capturas midan 1440 y 390 px, que el aprendiz solo pueda postularse desde `01-mi-proceso/convocatoria-abierta` y dentro de `09-postulacion` (CA-3; se revisan enlaces, botones, campos de envío y `role="button"`), que los enlaces relativos apunten a archivos que existen, que no aparezca el tipo de documento (CC o TI) y que el administrador tenga siempre el mismo menú.

## Colores de estado agregados (salen de `00-existentes/postulaciones`)

- `#C2392F`: rojo de la marca de documento no válido; también el botón de peligro.
- `#9E3A34` sobre `#FCEBEA`: texto y fondo de documento no válido (`.br-etiqueta--peligro`).
- `#5C4400` sobre `#FFF0B8` y punto `#E0A800`: insignia «Pendiente» (`.br-etiqueta--pendiente`).
- `#6E5200` sobre `#FFF4D1`: chip «hace N días».
- `#0B5180` sobre `#E2F2FB`: insignia «Validada» (`.br-etiqueta--validada`).
- `#22610A` sobre `#E6F4DD` y marca `#2E7D12`: documento válido.
- `#EEF2F5`: fondo de documento sin revisar (la marca gris `#7D8E9A` ya estaba en la paleta).
- `#EEF4F8` con borde `#C9DCEA`: chip «Doc. adicional».

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

   En una pantalla de detalle se agrega `pagina="..."` para que las migas digan «Dashboard > Postulaciones > Daniela Mosquera Palacios» (en el celular se esconde «Dashboard >»). La campanita de la cabecera muestra 4 avisos; se cambia con `avisos="0"`, `avisos="2"`, etc.

   Piezas del administrador en `estilo.css` (para reutilizar en las vistas que siguen): `.br-encabezado` (volver, título y un indicador a la derecha), `.br-cupos` (contador de cupos con barra), `.br-detalle` (contenido + panel lateral fijo; en celular el panel baja al final), `.br-ficha-datos` (datos clave / valor), `.br-documentos` / `.br-documento` (documentos con su marca y acciones), `.br-revision` («Válido» / «No válido»), `.br-decision` (pasos de decisión), `.br-motivo` (por qué un botón está deshabilitado), `.br-nota` y `.br-historial`.

   Piezas de listado (vista 05-pagos, para las que siguen): `.br-pestanas` con `.br-contador` (pestañas con número), `.br-listado` (tarjeta con `__herramientas`, `__resumen` y `__pie`), `.br-filtros` y `.br-buscador`, `.br-seleccion` (barra de selección múltiple, con `.br-enlace-claro`), `.br-casilla` (casilla con área de toque de 44 px), `.br-tabla--listado` y `.br-tabla--fichas` (en el celular cada fila es una ficha: celdas `.br-tabla__seleccion`, `__titulo`, `__estado`, `__acciones` y las demás con `data-etiqueta="..."`), `.br-ocultar-celular`, `.br-menu-acciones` (menú «Más acciones» de una fila, con `<details>`), `.br-paginacion`, `.br-aviso--verde`, `.br-dialogo--ancho`, `.br-resumen`, `.br-opciones` / `.br-opcion` (opciones grandes con radio), `.br-motivos` y `.br-nombres` (chips de nombres).

   Piezas de cuentas y carga de archivos (vista 06-usuarios): `.br-encabezado__acciones` (botones a la derecha del título), `.br-tabla--sin-seleccion` (tabla de fichas sin casilla; en el celular nombre y estado arriba) y `.br-tabla__ancha` (dato largo que ocupa toda la ficha), `.br-columnas` (columnas que debe traer un archivo, con su letra de Excel y un ejemplo), `.br-formulario` (campos en dos columnas; `__ancho` ocupa la fila), `.br-acciones-pie` (botones al pie de una tarjeta) y `.br-boton--ancho`. La zona de carga es la misma `.br-zona` / `.br-archivo` de la vista 08; un `<a>` dentro de `.br-archivo` queda a la derecha («Cambiar archivo»).

   Piezas de convocatorias (vista 07-convocatorias): `.br-tabla__cupos` (celda de cupos «18 de 40» con barra fina; `--lleno` la pone en rojo), `.br-tabla__estado--largo` (estado con una explicación larga, p. ej. el motivo de un cierre anticipado; en el celular baja debajo del nombre), `.br-menu-acciones__item--peligro` (opción delicada del menú de una fila; las opciones también pueden ser `<a>`) y `.br-casos` / `.br-casos__item--actual` / `.br-casos__marca` (casos posibles de una acción y cuál aplica ahora, p. ej. cierre normal o anticipado).

   Piezas del Dashboard, el acceso sin sesión y el formulario de postulación (vistas 00, 02 y 09): `.br-pendientes` y `.br-pendiente` (franja «Tareas pendientes»: cada fila es un enlace a la lista ya filtrada, con `__numero`, `__texto`, `__detalle` y `__flecha`), `.br-cifras` (fila de 4 tarjetas iguales; en pantallas chicas, 2 y luego 1) con `.br-medida` (una cifra con `__etiqueta`, `__valor` y `__nota`; `--lleno`, `--aviso` y `--vacia` cambian su aspecto), `.br-acceso` (tarjeta centrada sin sesión, igual al login de Samuel, con `__marca`, `__encabezado`, `__cuenta`, `__formulario`, `__acciones`, `__estado`, `__sello` y `__pie`), `.br-campo__clave` y `.br-campo__ojo` (campo de contraseña con botón de mostrar), `.br-reglas-caja` y `.br-reglas` (reglas de la contraseña, cada una con ícono y texto «cumplida», «pendiente» o «incumplida», no solo color), `.br-formulario-largo` (formulario en tarjetas), `.br-plegable` (requisitos que se abren y cierran), `.br-error-campo` y `.br-resumen-errores` (error junto a cada campo y resumen arriba), `.br-acciones-formulario` (botones al pie), `.br-revision-bloque` (cada bloque de «Revisar y enviar») y `.br-zona` / `.br-archivo` para subir archivos (la misma de las vistas 06 y 08; `.br-zona--error` para un archivo no permitido).

2. `construir.js` reemplaza `<!-- incluir:... -->` por la pieza de `_comun/` (en el menú marca la opción activa con `aria-current="page"`) y cambia el `<link>` del CSS por un `<style>` con su contenido.
3. Ejecutar `construir.js --vista <vista>` y luego `verificar.js --vista <vista>`.
4. Los diálogos se escriben como `<div class="br-dialogo-capa"><div class="br-dialogo" role="dialog" aria-modal="true" aria-labelledby="..."> ... </div></div>` al final del `<body>`.
5. Las tablas van dentro de `<div class="br-tabla-envoltura">` para que, si no caben en el celular, se desplacen solas sin mover toda la página.

## La barra del aprendiz: avisos y panel abierto

La barra se pide con `<!-- incluir:barra-aprendiz nombre="Luisa Fernanda" avisos="2" -->`. Tiene dos atributos opcionales:

- `avisos="N"`: cuántos avisos sin leer muestra la campanita. Con `avisos="0"` no hay contador y la campanita dice «ninguno sin leer». Si no se escribe, son 2.
- `panel="abierto"`: despliega el panel de avisos debajo de la campanita (vista `01b-avisos`). El contenido del panel se escribe en la misma fuente, entre `<!-- panel-avisos -->` y `<!-- /panel-avisos -->`. Si falta ese bloque, o el bloque existe pero la barra no lleva `panel="abierto"`, `construir.js` se detiene con un error.

Sin `panel`, la campanita queda cerrada.

## Reglas que conviene recordar

- Solo se usan los colores de `_comun/estilo.css`: la paleta de la spec (sección 2), los del aviso naranja del boceto aprobado y los de estado del panel de postulaciones de Samuel (lista abajo). Lo destructivo (`.br-boton--peligro`) va en rojo `#C2392F`.
- Los grises `#6B7C88` y `#7D8E9A` están permitidos, pero sobre fondos claros no llegan a 4.5:1 como texto. Para texto secundario se usa `#556776`. Si una vista usa un gris claro para texto, la verificación de contraste decide si está bien.
- Todo botón y campo tiene contorno de foco visible (`:focus-visible`) definido en el CSS común.
- Textos en español con tildes y ñ; nombres colombianos inventados, `$ 180.000`, fechas «15 nov 2026». Nunca nombres reales del equipo.
- El aprendiz solo puede postularse desde `01-mi-proceso/convocatoria-abierta` y dentro de `09-postulacion` (CA-3). En las demás vistas del aprendiz no hay botones ni enlaces para postularse.
- Los documentos de identidad se escriben solo con el número (`1.032.118.904`), sin «CC» ni «TI»: la base de datos no guarda el tipo.
- Cada persona de ejemplo es la misma en todas las vistas (nombre, documento, ficha, programa y correo). La aprendiz es Luisa Fernanda Ríos Cárdenas.
