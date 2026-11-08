# Spec 002 — Vistas (pantallas) de BenefitRack

- **Estado:** borrador para aprobación de Samuel
- **Fecha:** 2026-11-08
- **Rama:** `feat/002-vistas`
- **Depende de:** spec 001 (base de datos v2 en `base-de-datos/benefitrack_v2.sql`)

## 1. Objetivo y por qué

**Hoy**, el administrador de Bienestar al Aprendiz lee las respuestas del formulario de postulación y **copia a mano, dato por dato, a cada aprendiz en una tabla de Excel**. Después compara esos datos con la base de datos de aprendices del SENA, escoge a los beneficiarios y pasa un reporte para que les paguen. Los aprendices, mientras tanto, no tienen cómo saber en qué va su proceso, así que preguntan.

**BenefitRack** existe para:

1. Que las respuestas del formulario **lleguen solas** a una tabla dentro del sistema, que se puede **descargar en Excel con un clic**.
2. Que el administrador haga en pantalla los pasos que siguen siendo manuales (confirmar datos, aprobar o rechazar, registrar pagos).
3. Que el aprendiz **vea solo** cómo va su proceso, sin tener que preguntar.

Esta spec define **las pantallas (vistas) que faltan diseñar**, como maquetas HTML estáticas. No incluye programar la aplicación.

**Principio de Samuel:** «simple pero completa». Pocas pantallas, cada una bien hecha y fácil de entender. Si algo no es necesario, no va.

## 2. Lo que ya existe (no se rehace)

Hecho por Samuel en Claude Design el 2026-11-05 (archivos en `C:\Users\samue\Downloads`):

| Vista | Archivo | Contenido |
|---|---|---|
| Inicio de sesión | `BenefitRack-inicio-de-sesion.zip` | 6 estados: normal, validación, datos incorrectos, cuenta bloqueada, cuenta inactiva, cargando. Versiones para computador y celular (HTML + PNG). Sin botón «Crear cuenta». |
| Panel del administrador: «Revisa las postulaciones» | `BenefitRack · Dashboard del Administrador-final.html` y `-documentos.html` | Tabla de postulaciones con buscador, filtros (beneficio, programa, estado, fecha), paginación, estado vacío «No encontramos postulaciones con estos filtros», «Descargar en Excel» con selección de columnas (datos del aprendiz, datos de la postulación, estado de documentos). |

**Estilo que deben copiar las vistas nuevas** (sacado del HTML del inicio de sesión):

- Letra: `'Work Sans', Inter, system-ui, sans-serif`.
- Azul SENA (texto principal y barra superior): `#00304D`. Verde SENA (éxito): `#007832` (hover `#00632A`), verde claro `#6BD11F`. Azul de enlaces: `#0B6CB8`.
- Grises: textos secundarios `#556776`, `#6B7C88`, `#7D8E9A`; fondos y bordes `#F5F7F9`, `#F0F4F6`, `#EEF2F4`, `#E3E8EC`.
- Logo del SENA y el texto «Bienestar al Aprendiz» en la barra del administrador, como en el panel existente.

## 3. Cómo se hacen las vistas (decidido con Samuel)

- **Claude las construye en HTML** con el mismo estilo de las vistas existentes. Samuel solo revisa y aprueba. **De a una pantalla:** se muestra, Samuel dice «sí» o «cámbiale esto» y se sigue con la siguiente.
- No se usa Claude Design (su página independiente cierra el 2026-12-14).
- Cada vista se guarda en `vistas/<NN>-<nombre>/` con:
  - `html/<estado>.html`: un archivo por estado, autocontenido (CSS dentro del archivo, sin internet salvo la fuente de Google Fonts, que debe tener una fuente de respaldo).
  - `computador/<estado>.png` (1440 px de ancho) y `celular/<estado>.png` (390 px de ancho).
  - Es el mismo formato que el zip del inicio de sesión.
- Las vistas existentes de Samuel se copian a `vistas/00-existentes/` para que todo quede junto.
- Cada vista funciona en celular (390 px) y en computador (1440 px).
- Por el manual global §5.1 se trabaja con la skill `impeccable` en modo «Operate»: claridad y patrones conocidos por encima de efectos llamativos.
- Datos de ejemplo: nombres colombianos inventados, montos en pesos (`$ 180.000`), fechas en formato «15 nov 2026». Nunca datos de personas reales del equipo.

## 4. Roles

- **Aprendiz** (en la BD el rol se llama «Estudiante»). **No se postula dentro de la página.** Se postula con el formulario que se le envía, y su cuenta la crea el administrador. Entra solo a **ver cómo va su proceso**.
- **Administrador** (Bienestar al Aprendiz). Revisa, confirma, aprueba o rechaza, registra pagos y gestiona aprendices y convocatorias.

## 5. Vistas a diseñar

### Vista 1 — Aprendiz: «Mi proceso» (panel del aprendiz)

Es la vista más importante del lado del aprendiz. Usa una sola columna que se lee de arriba abajo, como una historia: «¿me escogieron? → ¿cómo es mi beneficio? → ¿cómo van mis pagos?». Es la **opción A del boceto**, aprobada por Samuel.

**Barra superior:** logo BenefitRack, «Hola, <nombre>», campanita de avisos (ver la vista 1b) y «Salir».

**Estados (un archivo HTML por estado):**

1. `seleccionado`:
   - Etiqueta verde «✓ Fuiste seleccionado/a», nombre del beneficio, convocatoria y fecha de aprobación.
   - Pasos: Enviada ✓ → Validada ✓ → Aprobada ✓, con su fecha debajo de cada uno.
   - Tarjeta del beneficio: valor de cada pago, duración, desde–hasta.
   - Barra de progreso «Pagos recibidos: 2 de 6» y debajo la línea **«$ 360.000 recibidos · quedan 4 meses»** (detalle tomado de la opción B).
   - Tabla «Mis pagos»: mes, fecha esperada, valor y estado (Pagado / Pendiente / Retrasado).
2. `seleccionado-con-retraso`: igual que el anterior, más un **aviso naranja arriba**: «⚠ El pago de <mes> va retrasado. Debía llegar el <fecha>.» Si el administrador registró un motivo, se muestra.
3. `en-revision`: «Tu postulación está en revisión». Los pasos muestran cuáles ya pasó (Enviada ✓, Validada en curso). Texto tranquilizador: aún no hay respuesta y se le avisará.
4. `no-seleccionado`: mensaje claro y amable: «Esta vez no fuiste seleccionado/a para <beneficio>». Invitación a **estar pendiente de las próximas convocatorias**. Sin culpas y sin detalles internos de la selección.
5. `beneficio-terminado`: los pagos están completos. «Recibiste los 6 pagos de tu beneficio», con el resumen y la tabla de pagos.
6. `sin-postulacion`: cuenta creada, pero sin postulación registrada (por ejemplo, el administrador creó la cuenta antes de cargar las respuestas). Mensaje: «Aún no tenemos una postulación tuya registrada».
7. `cargando`.

**Casos borde a cubrir en el diseño:** nombres largos; beneficio con 1 solo pago; asignación «Suspendida» o «Cancelada» (se muestra como aviso con el motivo); un aprendiz con postulaciones en dos convocatorias distintas (se muestra la más reciente arriba y las anteriores en una sección plegable «Postulaciones anteriores»).

### Vista 1b — Aprendiz: avisos (campanita)

No es una pantalla aparte: es un **panel desplegable** desde la campanita de la vista 1. Lista de avisos, el más reciente primero, por ejemplo «Tu postulación fue aprobada», «Se registró tu pago de octubre» o «Tu pago de noviembre va retrasado». Los no leídos se marcan con un punto. Estados: `con-avisos`, `sin-avisos` («No tienes avisos nuevos»).

Fuente de datos: tabla `notificaciones` (eventos Validada, Aprobada, Rechazada, Pago, Novedad).

### Vista 2 — Aprendiz: primer ingreso y contraseña

- `primer-ingreso`: al entrar por primera vez con la contraseña que le dio el administrador, el aprendiz **debe cambiarla**. Campos: nueva contraseña y confirmar contraseña, con reglas visibles mientras escribe.
- `recuperar-solicitud`: «¿Olvidaste tu contraseña?» Escribe su correo. Mensaje neutro: «Si el correo está registrado, te enviamos un enlace» (no revela si la cuenta existe).
- `recuperar-nueva`: pantalla del enlace del correo para poner la contraseña nueva.
- `enlace-vencido`: el enlace caducó o ya se usó, con el botón «Pedir uno nuevo».
- Estados de error de validación y de éxito («Contraseña cambiada. Ya puedes entrar.»).

Mismo estilo de tarjeta centrada que el inicio de sesión existente.

### Vista 3 — Administrador: detalle de una postulación

Se abre al hacer clic en una fila de «Revisa las postulaciones». Aquí el administrador hace los dos pasos manuales, **en este orden** (como ya exige la BD: Validada → Aprobada):

1. **Confirmar datos (siempre manual).** El administrador ve los datos que el aprendiz puso en el formulario (nombre, documento, ficha, programa, dirección, EPS, etc.) y los documentos adjuntos. Los compara con la base de datos de aprendices del SENA, que está fuera de BenefitRack. Luego elige «Datos confirmados», que pasa la postulación a Validada, o «Rechazar», con un motivo obligatorio.
2. **Aprobar o rechazar.** Solo se habilita si la postulación está en Validada. Muestra **siempre a la vista el contador de cupos**: «Cupos: 18 de 25 usados». Si no quedan cupos, «Aprobar» se deshabilita y se explica por qué.

**Estados:** `pendiente`, `validada`, `aprobada` (muestra el resumen de la asignación creada), `rechazada` (muestra el motivo), `sin-cupos`, `confirmar-accion` (diálogo «¿Seguro que quieres aprobar a <nombre>?»), `error`.

Historial visible en la misma vista: quién cambió el estado y cuándo (tabla `historial_estados`).

### Vista 4 — Administrador: reporte de aprobados

Descarga en Excel de los aprendices **aprobados** de una convocatoria, para quien hace los pagos. **Reutiliza el diálogo «Descargar en Excel» que ya existe**, con estas columnas marcadas por defecto: nombre, documento, ficha, programa, beneficio, valor del pago, entidad bancaria, tipo de cuenta y número de cuenta (tabla `informacion_bancaria`). El administrador puede agregar o quitar columnas.

Nadie del equipo sabe todavía a quién se entrega el reporte ni qué datos pide esa persona. Por eso las columnas son ajustables y no se fija un formato.

Se diseña como: botón «Reporte de aprobados» en la vista de postulaciones y en la de convocatorias; diálogo con las columnas por defecto; estado `sin-aprobados` («Esta convocatoria aún no tiene aprendices aprobados»).

### Vista 5 — Administrador: pagos

Lista de aprendices con beneficio en curso, por convocatoria y por mes. El objetivo es **registrar pagos de varios a la vez**: seleccionar «todos los de noviembre» → «Marcar como pagados», indicando la fecha de pago.

- Columnas: aprendiz, beneficio, pago N de M, valor, fecha esperada y estado (Pagado / Pendiente / Retrasado).
- **Retrasado** = la fecha esperada ya pasó y no hay pago registrado. Lo calcula la aplicación: la BD guarda solo los pagos hechos (`pagos.fecha_pago` es obligatoria) y el calendario sale de `asignaciones_beneficio` (`fecha_inicio`, `numero_pagos`, `duracion_meses`).
- Si un pago se registra después de su fecha esperada, la BD exige `hubo_retraso = 1` y un **motivo de retraso**: el diálogo lo pide.
- Acciones de una fila: registrar el pago con su observación, **anular** un pago mal registrado (con motivo, sin borrar nada) y registrar una novedad (Suspensión, Reactivación, Cancelación, Otra).
- **Estados:** `lista`, `seleccion-multiple`, `confirmar-pago-masivo` (resumen: «Vas a registrar 23 pagos por $ 4.140.000»), `pago-con-retraso` (pide el motivo), `exito`, `vacio`.

### Vista 6 — Administrador: aprendices (crear cuentas cargando Excel)

Las cuentas de aprendices **no se crean solas: las crea el administrador** (decisión de Samuel del 2026-11-05). La forma principal es **cargar un archivo Excel**, que usa la tabla `migraciones`, RF-4.3.

- `lista`: tabla de aprendices con buscador, estado de la cuenta (Activo / Inactivo / Bloqueado) y acciones (activar, desactivar, desbloquear).
- `cargar-excel`: zona para arrastrar el archivo, enlace «Descargar plantilla» y columnas requeridas visibles.
- `vista-previa`: antes de crear, se muestra cuántas filas están bien y cuáles tienen errores (fila, columna y motivo, por ejemplo «correo repetido» o «documento vacío»).
- `resultado`: «Se crearon 120 cuentas. 4 filas con errores», con la opción de descargar la lista de errores.
- `crear-uno`: formulario para crear una sola cuenta a mano.
- Error de archivo inválido (no es Excel, o le faltan columnas).

### Vista 7 — Administrador: convocatorias

- `lista`: convocatorias con beneficio, fechas, cupos usados / totales y estado (Activa, Inactiva, Cerrada, Cierre anticipado).
- `crear-editar`: nombre, beneficio, fechas de inicio y cierre, cupos, requisitos, valor de cada pago, número de pagos y duración en meses (campos de la tabla `convocatorias`).
- `cerrar`: cerrar una convocatoria. El cierre anticipado pide un motivo.
- `vacio`: «Aún no hay convocatorias. Crea la primera.»

### Agregado al panel existente — franja «Tareas pendientes»

Arriba de «Revisa las postulaciones» se agrega una franja corta con lo que el administrador debe hacer primero, por ejemplo «12 postulaciones sin confirmar datos · 3 pagos retrasados». Cada parte es un enlace a la vista filtrada. Si no hay pendientes, la franja dice «Todo al día». Es el **único cambio** a una vista existente.

### Navegación del administrador

Hay que diseñar el menú común a todas las vistas del administrador: **Postulaciones · Pagos · Aprendices · Convocatorias**. La forma (lateral o superior) debe ser la misma que ya usa el panel existente.

## 6. Criterios de aceptación

- **CA-1** Dado un aprendiz seleccionado con un pago retrasado, cuando abre «Mi proceso», entonces lo primero que ve, sin desplazarse en un celular de 390 px, es que fue seleccionado y el aviso naranja del retraso.
- **CA-2** Dado un aprendiz no seleccionado, cuando abre «Mi proceso», entonces ve un mensaje claro de que no fue seleccionado y la invitación a próximas convocatorias, sin detalles internos.
- **CA-3** Dado que el aprendiz no puede postularse en la página, entonces ninguna vista del aprendiz tiene botones ni enlaces para postularse.
- **CA-4** Dada una postulación Pendiente, cuando el administrador abre su detalle, entonces «Aprobar» está deshabilitado hasta marcar «Datos confirmados».
- **CA-5** Dada una convocatoria sin cupos, cuando el administrador abre el detalle de una postulación Validada, entonces «Aprobar» está deshabilitado y la vista dice por qué.
- **CA-6** Dado que el administrador selecciona varios pagos, cuando pulsa «Marcar como pagados», entonces ve un resumen con la cantidad y el total antes de confirmar.
- **CA-7** Dado un archivo Excel con filas erróneas, cuando el administrador lo carga, entonces ve qué filas fallan y por qué antes de que se cree alguna cuenta.
- **CA-8** Cada estado listado en la sección 5 tiene su HTML y sus capturas de computador y celular, sin desplazamiento horizontal a 390 px.
- **CA-9** Todas las vistas usan la letra y los colores de la sección 2, y el texto tiene un contraste de al menos 4.5:1 (WCAG AA).
- **CA-10** Todas las vistas del administrador comparten la misma barra y el mismo menú.

## 7. Fuera de alcance

- Programar la aplicación (backend, rutas reales, conexión a la BD). Esto es solo diseño.
- **Postularse dentro de la página** (el aprendiz se postula con el formulario externo).
- Cómo llegan técnicamente las respuestas del formulario al sistema. Se decide cuando se programe la aplicación, y para las vistas basta con que aparezcan en la tabla.
- Gráficas, estadísticas, chat y reportes complejos (decisión de Samuel: simple pero completo).
- Figuras `anatomy` (Samuel decidió omitirlas en este proyecto).
- Perfil o edición de datos personales por parte del aprendiz.
- Pantallas de auditoría y de catálogos (EPS, bancos, programas). La BD las soporta, pero no se diseñan ahora.

## 8. Preguntas abiertas (no bloquean el diseño)

1. A quién se entrega el reporte de aprobados y qué datos necesita (la vista 4 lo resuelve con columnas ajustables).
2. Orden real en la oficina: Samuel cree que a veces se selecciona antes de confirmar datos. Las vistas siguen el orden de la BD (confirmar → aprobar). Si la realidad es otra, se cambia.

## 9. Orden de trabajo propuesto

1 (Mi proceso + avisos) → 3 (detalle de postulación) → 5 (pagos) → 6 (aprendices) → 7 (convocatorias) → 4 (reporte) → franja «Tareas pendientes» → 2 (contraseña).

Primero va lo que aporta más valor: lo que ve el aprendiz y el flujo principal del administrador.
