# Spec 002 — Vistas (pantallas) de BenefitRack

- **Estado:** borrador para aprobación de Samuel
- **Fecha:** 2026-11-08
- **Rama:** `feat/002-vistas`
- **Depende de:** spec 001 (base de datos v2 en `base-de-datos/benefitrack_v2.sql`)
- **Cambio 2026-11-09 (decisión de Samuel):** el aprendiz **sí se postula dentro de BenefitRack**, con un formulario propio (vista 9) que reemplaza al formulario externo. Cambian §1, §4, la vista 1 (`sin-postulacion` y el nuevo `convocatoria-abierta`), CA-3, §7 y §9; se agregan la vista 9, CA-11 y CA-12.

## 1. Objetivo y por qué

**Hoy**, el administrador de Bienestar al Aprendiz lee las respuestas del formulario de postulación y **copia a mano, dato por dato, a cada aprendiz en una tabla de Excel**. Después compara esos datos con la base de datos de aprendices del SENA, escoge a los beneficiarios y pasa un reporte para que les paguen. Los aprendices, mientras tanto, no tienen cómo saber en qué va su proceso, así que preguntan.

**BenefitRack** existe para:

1. Que el aprendiz **se postule dentro de BenefitRack** (vista 9) y sus respuestas queden directamente en la tabla de postulaciones, que se puede **descargar en Excel con un clic**. Nadie vuelve a copiar respuestas a mano.
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

- **Aprendiz** (en la BD el rol se llama «Estudiante»). Su cuenta la crea el administrador (vista 6). Entra a **postularse** cuando hay una convocatoria abierta (vista 9) y a **ver cómo va su proceso**.
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
6. `sin-postulacion`: cuenta creada, sin postulación y **sin convocatorias abiertas**. Mensaje: «Aún no tienes postulaciones» y la invitación a estar pendiente: cuando se abra una convocatoria, aparecerá aquí y le llegará un aviso.
6b. `convocatoria-abierta`: hay una convocatoria abierta a la que aún no se ha postulado. Tarjeta con el beneficio, el valor de cada pago, el número de pagos, la fecha de cierre y el botón **«Postularme»**, que lleva a la vista 9.
7. `documento-pedido`: la postulación está en revisión y el administrador pidió un documento (ver la vista 3). Arriba aparece un **aviso azul**: «Bienestar te pidió: <documento>. <mensaje del administrador>», con el botón **«Subir documento»**, que lleva a la vista 8. Es la **única acción** que el aprendiz puede hacer en «Mi proceso».
8. `cargando`.

**Casos borde a cubrir en el diseño:** nombres largos; beneficio con 1 solo pago; asignación «Suspendida» o «Cancelada» (se muestra como aviso con el motivo); un aprendiz con postulaciones en dos convocatorias distintas (se muestra la más reciente arriba y las anteriores en una sección plegable «Postulaciones anteriores»).

### Vista 1b — Aprendiz: avisos (campanita)

No es una pantalla aparte: es un **panel desplegable** desde la campanita de la vista 1. Lista de avisos, el más reciente primero, por ejemplo «Tu postulación fue aprobada», «Se registró tu pago de octubre», «Tu pago de noviembre va retrasado» o «Bienestar te pidió un documento». Los no leídos se marcan con un punto. Estados: `con-avisos`, `sin-avisos` («No tienes avisos nuevos»).

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

1. **Confirmar datos (siempre manual).** El administrador ve los datos que el aprendiz puso en el formulario (nombre, documento, ficha, programa, dirección, EPS, etc.) y los documentos adjuntos. Los compara con la base de datos de aprendices del SENA, que está fuera de BenefitRack.
   - **Cada documento se revisa por separado:** se puede abrir o ver, y se marca «Válido» o «No válido», con motivo si no es válido (`documentos_postulacion.estado_validacion`).
   - **Pedir documento** (decisión de Samuel, 2026-11-08): si falta un documento o no es válido, el administrador pulsa «Pedir documento», elige el tipo (catálogo `tipos_documento`, por ejemplo «Certificado de EPS») y escribe un mensaje corto. Se crea una solicitud (`solicitudes_documentacion`, estado Pendiente) y al aprendiz le llega un aviso (vistas 1 y 1b). Mientras la solicitud está Pendiente, la postulación sigue en revisión y «Datos confirmados» está deshabilitado, con su explicación. Cuando el aprendiz sube el archivo, la solicitud pasa a Atendida y el documento nuevo aparece aquí para revisarlo.
   - Al final el administrador elige «Datos confirmados», que pasa la postulación a Validada (solo si todos los documentos obligatorios están en «Válido»), o «Rechazar», con un motivo obligatorio.
2. **Aprobar o rechazar.** Solo se habilita si la postulación está en Validada. Muestra **siempre a la vista el contador de cupos**: «Cupos: 18 de 25 usados». Si no quedan cupos, «Aprobar» se deshabilita y se explica por qué.

**Estados:** `pendiente`, `documento-pedido` (muestra la solicitud abierta y desde cuándo espera), `documento-recibido` (el documento nuevo resaltado para revisarlo), `pedir-documento` (diálogo), `validada`, `aprobada` (muestra el resumen de la asignación creada), `rechazada` (muestra el motivo), `sin-cupos`, `confirmar-accion` (diálogo «¿Seguro que quieres aprobar a <nombre>?»), `error`.

*Decisión de Claude:* la versión base del panel de postulaciones es `-documentos.html` (la más reciente y la que tiene la columna de estado de documentos). En esa tabla, una postulación con documento pedido muestra «Documento pedido» en la columna de documentos.

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
- Acciones de una fila: registrar el pago con su observación, **anular** un pago mal registrado (con motivo, sin borrar nada) y registrar una novedad (**Retraso de pago**, Suspensión, Reactivación, Cancelación, Otra).
- **«Avisar retraso»** (decisión de Samuel, 2026-11-08): el administrador **elige si el aviso es para todos o solo para uno**:
  - **Para uno:** desde la acción de una fila en estado Retrasado.
  - **Para todos:** desde la barra de selección múltiple. Puede ser «Seleccionar todos los retrasados» (por ejemplo, todo el pago de noviembre de una convocatoria) o los que marque a mano.
  - El diálogo dice a cuántos aprendices les llegará el aviso («Se avisará a 23 aprendices») y pide **un motivo**, que se guarda igual para todos.
  - Cada aviso guarda una novedad de tipo «Retraso de pago» por asignación, con esa descripción, y una notificación para cada aprendiz. Esa descripción es el motivo que ve el aprendiz en el estado `seleccionado-con-retraso` de la vista 1, aunque el pago todavía no se haya hecho.
- **Registrar pagos con retraso**, con la misma idea (decisión de Samuel): al registrar el pago de uno o de varios, si alguno está fuera de fecha, el resumen los muestra aparte («3 de estos pagos van con retraso»). El administrador elige **«Mismo motivo para todos»** (un solo campo) o **«Un motivo para cada uno»** (un campo por pago).
- **Estados:** `lista`, `seleccion-multiple`, `confirmar-pago-masivo` (resumen: «Vas a registrar 23 pagos por $ 4.140.000»), `pago-con-retraso` (pide el motivo, con la opción «para todos / uno por uno»), `avisar-retraso-uno`, `avisar-retraso-varios`, `exito`, `vacio`.

### Vista 6 — Administrador: «Usuarios» (crear cuentas de aprendices cargando Excel)

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

### Vista 8 — Aprendiz: subir documento pedido

Se llega aquí solo desde el aviso «Bienestar te pidió…» (vista 1, estado `documento-pedido`). No es una postulación nueva: solo completa la que ya existe.

- `elegir-archivo`: qué documento se pide, el mensaje del administrador, la zona para elegir o arrastrar el archivo y los formatos y tamaño permitidos (*decisión de Claude:* PDF, JPG o PNG, máximo 5 MB).
- `subiendo`: barra de progreso.
- `enviado`: «Listo, Bienestar revisará tu documento», con un botón para volver a «Mi proceso».
- `error`: tipo de archivo o tamaño no permitido, o falla al subir, con la opción de intentar de nuevo.

### Vista 9 — Aprendiz: formulario de postulación

Se llega desde «Postularme» (vista 1, `convocatoria-abierta`). Es una sola página que se lee de arriba abajo, por secciones; en celular, una columna. Lo que el aprendiz envía queda en `postulaciones` (las respuestas en `datos_formulario`), sus datos personales en `usuarios`, la cuenta en `informacion_bancaria` y los archivos en `documentos_postulacion`.

**Encabezado:** nombre de la convocatoria, beneficio, valor de cada pago, número de pagos y «Cierra el <fecha>». Los requisitos de la convocatoria (texto de `convocatorias`), plegables.

**Secciones** (*decisión de Claude*; Samuel puede cambiarlas):
1. **Tus datos** (de tu cuenta, solo lectura): nombre, documento, correo, programa y ficha. Nota: «¿Algo está mal? Avísale a Bienestar antes de enviar».
2. **Contacto:** teléfono, dirección y fecha de nacimiento (obligatorios). Si ya están en la cuenta, aparecen llenos y se pueden corregir.
3. **Salud:** EPS (lista de la tabla `eps`).
4. **Cuenta para recibir los pagos:** entidad bancaria, tipo de cuenta (Ahorros / Corriente), número de cuenta y titular. Nota: la cuenta debe estar a nombre del aprendiz.
5. **Documentos** (PDF, JPG o PNG, máximo 5 MB cada uno, todos obligatorios): Documento de identidad, **Certificado de EPS** y Certificado bancario.
6. **¿Por qué necesitas este beneficio?** (opcional, máximo 500 caracteres).
7. Casilla obligatoria: «Declaro que la información es verdadera y autorizo el tratamiento de mis datos personales (Ley 1581 de 2012)».

Botón «Revisar y enviar». Después de enviar no se puede editar; si algo cambia, se habla con Bienestar.

**Estados:**
- `formulario`: vacío, con «Tus datos» llenos desde la cuenta.
- `con-errores`: el aprendiz intentó enviar con faltantes. Arriba, un resumen «Revisa 3 campos» con enlaces a cada campo; cada campo con error dice qué falta (por ejemplo «Falta el Certificado de EPS» o «El archivo pesa más de 5 MB»).
- `revisar-y-enviar`: resumen de lo que va a enviar, con «Corregir» y «Enviar postulación», y el aviso de que después no se puede editar.
- `enviada`: «Listo, tu postulación fue enviada», con la fecha, qué sigue (Bienestar revisa tus datos y documentos y te avisará) y el botón «Ver mi proceso» (vista 1, `en-revision`).
- `convocatoria-cerrada`: el aprendiz llega cuando ya pasó la fecha de cierre: «Esta convocatoria ya cerró», con el enlace a «Mi proceso».

**Casos borde:** nombre largo; número de cuenta largo; archivo de más de 5 MB o de formato no permitido; el aprendiz ya se postuló a esa convocatoria (la BD no permite dos postulaciones a la misma convocatoria: la app muestra el mismo diseño de `convocatoria-cerrada` con el texto «Ya te postulaste a esta convocatoria»; la fuente deja ese texto en un comentario HTML).

### Vista 0 — Administrador: «Dashboard» (inicio)

*Decisión de Claude:* el menú existente tiene «Dashboard», pero entre las vistas existentes no hay una pantalla para esa opción (solo Postulaciones). Se diseña una página de inicio **mínima**:

- Franja **«Tareas pendientes»** con enlaces a la vista filtrada, por ejemplo: «12 postulaciones sin confirmar datos · 2 documentos recibidos por revisar · 3 pagos retrasados». Si no hay pendientes: «Todo al día».
- Una fila de 3 a 4 cifras: postulaciones por revisar, cupos usados de la convocatoria activa, pagos registrados este mes y pagos retrasados.
- Sin gráficas.
- Estados: `con-pendientes`, `todo-al-dia`, `sin-convocatoria-activa`.

Así la vista existente de postulaciones **no se modifica** (solo cambia el texto de la columna de documentos, ver la vista 3).

### Navegación del administrador

Se usa **el menú del panel existente, sin «Auditoría»** (decisión de Samuel, 2026-11-08): **Dashboard · Convocatorias · Postulaciones · Pagos · Usuarios**. Misma forma y estilo que en la vista existente. «Usuarios» es la vista 6 (cuentas de aprendices). Si el diseño existente muestra «Auditoría», se quita de todas las vistas nuevas, y en las existentes al copiarlas a `vistas/00-existentes/`.

## 6. Criterios de aceptación

- **CA-1** Dado un aprendiz seleccionado con un pago retrasado, cuando abre «Mi proceso», entonces lo primero que ve, sin desplazarse en un celular de 390 px, es que fue seleccionado y el aviso naranja del retraso.
- **CA-2** Dado un aprendiz no seleccionado, cuando abre «Mi proceso», entonces ve un mensaje claro de que no fue seleccionado y la invitación a próximas convocatorias, sin detalles internos.
- **CA-3** (cambiado el 2026-11-09) Dado un aprendiz, el botón o enlace para postularse aparece **solo** en «Mi proceso» `convocatoria-abierta` y lleva a la vista 9. Ningún otro estado de las vistas 1, 1b, 2 y 8 tiene acciones de postulación.
- **CA-11** Dado un aprendiz que llena el formulario con datos faltantes o un archivo no permitido, cuando pulsa «Revisar y enviar», entonces ve arriba un resumen de los errores y cada campo dice qué le falta; no se envía nada.
- **CA-12** Dado un formulario completo, cuando el aprendiz pulsa «Revisar y enviar», entonces ve un resumen antes de enviar y el aviso de que después no se podrá editar. El Certificado de EPS es obligatorio.
- **CA-3b** Dada una postulación con un documento pedido y pendiente, cuando el administrador abre su detalle, entonces «Datos confirmados» está deshabilitado y la vista dice que se espera el documento. Cuando el aprendiz entra, ve arriba el aviso con el botón «Subir documento».
- **CA-4** Dada una postulación Pendiente, cuando el administrador abre su detalle, entonces «Aprobar» está deshabilitado hasta marcar «Datos confirmados».
- **CA-5** Dada una convocatoria sin cupos, cuando el administrador abre el detalle de una postulación Validada, entonces «Aprobar» está deshabilitado y la vista dice por qué.
- **CA-6** Dado que el administrador selecciona varios pagos, cuando pulsa «Marcar como pagados», entonces ve un resumen con la cantidad y el total antes de confirmar.
- **CA-7** Dado un archivo Excel con filas erróneas, cuando el administrador lo carga, entonces ve qué filas fallan y por qué antes de que se cree alguna cuenta.
- **CA-8** Cada estado listado en la sección 5 tiene su HTML y sus capturas de computador y celular, sin desplazamiento horizontal a 390 px.
- **CA-9** Todas las vistas usan la letra y los colores de la sección 2, y el texto tiene un contraste de al menos 4.5:1 (WCAG AA).
- **CA-10** Todas las vistas del administrador comparten la misma barra y el mismo menú.

## 7. Fuera de alcance

- Programar la aplicación (backend, rutas reales, conexión a la BD). Esto es solo diseño.
- El formulario externo (Google Forms u otro): lo reemplaza la vista 9.
- Guardar un borrador del formulario para terminarlo después (se agrega si Samuel lo pide).
- Gráficas, estadísticas, chat y reportes complejos (decisión de Samuel: simple pero completo).
- Figuras `anatomy` (Samuel decidió omitirlas en este proyecto).
- Perfil o edición de datos personales por parte del aprendiz.
- Pantallas de auditoría y de catálogos (EPS, bancos, programas). La BD las soporta, pero no se diseñan ahora.

## 8. Preguntas abiertas (no bloquean el diseño)

1. A quién se entrega el reporte de aprobados y qué datos necesita (la vista 4 lo resuelve con columnas ajustables).
2. Orden real en la oficina: Samuel cree que a veces se selecciona antes de confirmar datos. Las vistas siguen el orden de la BD (confirmar → aprobar). Si la realidad es otra, se cambia.

## 9. Orden de trabajo propuesto

1 (Mi proceso + avisos) → 8 (subir documento) → 3 (detalle de postulación) → 5 (pagos) → 6 (usuarios) → 7 (convocatorias) → 4 (reporte) → 0 (Dashboard) → 9 (formulario de postulación, agregada el 2026-11-09) → 2 (contraseña).

Primero va lo que aporta más valor: lo que ve el aprendiz y el flujo principal del administrador.
