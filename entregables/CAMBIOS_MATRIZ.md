# Cambios en la matriz de requisitos de BenefitRack (versión 2)

Este documento explica, en palabras sencillas, qué cambió entre la matriz original
(`originales/Matriz_Requisitos.xlsx`) y la nueva (`entregables/Matriz_Requisitos_v2.xlsx`), y por qué.

Cada cambio cita la decisión de la especificación `specs/001-correccion-entregables.md`
que lo justifica (D1 a D17). Cuando no hay una decisión detrás, se indica con «—».

---

## 0. Primero, ¿qué es la matriz?

Es una tabla con todo lo que la aplicación debe hacer. Cada fila tiene tres partes que van juntas
y llevan el mismo número:

| Parte | Pregunta que responde | Ejemplo |
|---|---|---|
| **RF** (requisito funcional) | ¿Qué debe hacer el sistema? | RF-6.0: «registrar un pago» |
| **RN** (regla de negocio) | ¿Qué condiciones debe cumplir? | RN-6.0: «si hubo retraso, el motivo es obligatorio» |
| **RI** (requisito de información) | ¿Qué datos de la base de datos usa? | RI-6.0: `pagos: monto, fecha_pago, ...` |

Si RF, RN y RI no tienen el mismo número, o si el RI nombra un campo que no existe en la
base de datos, la matriz deja de servir como guía para programar. Eso es justo lo que se corrigió.

## 1. Resumen en números

| | Original | Versión 2 |
|---|---|---|
| Módulos | 6 | 9 (3 nuevos) |
| Requisitos (filas RF/RN/RI) | 21 | 46 (25 nuevos) |
| Requisitos eliminados | — | 0 (se corrigió su contenido, no se borró ninguno) |
| Reglas del verificador que pasan | 1 de 10 | 10 de 10 |

Los números de los módulos originales (1 a 6) **se conservaron** para que nadie del equipo pierda la
referencia: RF-4.1 sigue siendo «comparar datos» y RF-6.0 sigue siendo «registrar pago». Los módulos
nuevos son el 7, el 8 y el 9. En el Excel, el número de cada requisito nuevo tiene fondo verde, y la
hoja «Control de cambios» tiene una fila por cada cambio (fechas 26/10/2026, 27/10/2026 y 28/10/2026, responsable
Samuel David Hurtado Arango).

## 2. ¿Cómo se comprobó? (el verificador)

`entregables/verificar_matriz.js` es un pequeño programa que lee el Excel y la base de datos y revisa
diez reglas. No necesita instalar nada: solo Node. Se ejecuta así:

```bash
node entregables/verificar_matriz.js                                   # revisa la v2
node entregables/verificar_matriz.js originales/Matriz_Requisitos.xlsx # revisa la original
```

| Regla | Qué revisa | Original | v2 |
|---|---|---|---|
| (c) | Cada `tabla: campo` del RI existe en `benefitrack_v2.sql`, y cada procedimiento, función o trigger citado en la hoja (`pr_…`, `fn_…`, `tr_…`) también existe | FALLO (15 problemas) | OK |
| (d) | No aparecen «OCR», «resultado_ocr», «datos_extraidos», «Estudiante», «Completado», «Borrador», «En revisión» ni «Enviada» («Enviada» se permite solo en RF-5.7, donde es un valor real de `notificaciones.estado_correo`) | FALLO (5 problemas) | OK |
| (e) | RF-x.y, RN-x.y y RI-x.y tienen el mismo número en cada fila | FALLO (4 problemas) | OK |
| (g) | El estado del requisito es «Obligatorio» u «Opcional» | FALLO (2 problemas) | OK |
| (h) | Frases acordadas de las reglas de la base de datos (rondas 2 a 10) y marcas «lo valida la aplicación» presentes en el requisito que corresponde, y ausencia de algunas frases equivocadas conocidas | FALLO (55 problemas) | OK |
| (i) | Los tipos de documento de la semilla del SQL (`tipos_documento`) aparecen en RF-3.1 | FALLO (4 problemas) | OK |
| (j) | RF-2.0 y RF-1.3 dicen los mismos minutos de desbloqueo que el SQL (15). Lee todas las apariciones en el bloque de usuarios del SQL (hoy, `fn_cambio_estado_sistema` y el texto de la auditoría), exige que sean iguales entre sí y compara el número completo | FALLO (2 problemas) | OK |
| (k) | No quedan preguntas abiertas ni temas «por confirmar» en la hoja ni en la sección 7 de este documento | OK | OK |
| (l) | Frases acordadas del bloqueo de la cuenta (quién bloquea y quién desbloquea) presentes | FALLO (8 problemas) | OK |
| (m) | Frases acordadas de la auditoría de cuentas y migraciones (rondas 7 y 8) presentes | FALLO (24 problemas) | OK |

La regla (d) revisa solo la hoja «Matriz de Requisitos». La hoja «Control de cambios» sí nombra esas
palabras, porque explica qué se quitó.

Las reglas (h) a (l) se agregaron el 27/10/2026 (sección 9) y la (m), el mismo día en la segunda tanda
(sección 10). El 27/10/2026 por la noche, con la octava ronda, (c) pasó a revisar también los nombres de las
rutinas y (j) a leer los minutos de todo el bloque de usuarios (sección 11).

**Qué lee del SQL y qué no (dicho con honestidad).** Hay dos tipos de reglas:

- **Las que leen el SQL:** (c), (i) y (j). Si mañana la base de datos agrega otro tipo de documento o
  cambia los 15 minutos, el verificador avisa que la matriz quedó desactualizada.
- **Las que comparan frases acordadas:** (h), (l) y (m). Son como una lista de chequeo escrita a mano:
  las frases se copiaron de `base-de-datos/CAMBIOS.md` y se revisaron contra `benefitrack_v2.sql`, pero
  el verificador **no lee el SQL** para estas reglas. Comprueban que esas frases estén y que no
  aparezcan algunas frases equivocadas que ya conocemos; no pueden garantizar que ninguna otra frase
  de la matriz contradiga la base. Si la base de datos cambia, hay que actualizar la lista. Las frases de
  (h), (l) y (m) ya incluyen la octava ronda, que desde el 27/10/2026 está en el SQL de esta rama
  (sección 11), y (h) incluye la novena y la décima (sección 12).

**Sobre la regla (k).** Nunca estuvo en rojo contra la original, porque la hoja original no tenía
frases como «por confirmar»: las preguntas abiertas estaban escritas en la sección 7 de este documento.
Por eso, desde la segunda tanda del 27/10/2026, (k) revisa también esa sección (lo tachado con `~~`
cuenta como cerrado). Contra la original sigue saliendo OK, porque la sección 7 de hoy ya no tiene
preguntas abiertas.

## 3. Requisitos que se corrigieron

### Módulo 1 · Gestión de usuarios

- **RF-1.0 Registrar usuario.** La regla y la información decían «RN 1.0» y «RI 1.0» (sin guion): ahora
  dicen «RN-1.0» y «RI-1.0». La base de datos ya no guarda la `edad` (cambia cada año) sino la
  `fecha_nacimiento`. Se agregan la EPS (`id_eps`) y la regla de que el correo no se puede repetir. (**D11**)
- **RF-1.1 Consultar usuarios.** Se agrega el estado «Bloqueado», que existe en la base de datos.
- **RF-1.2 Modificar usuario.** Se agregan `fecha_nacimiento` e `id_eps`; todo cambio de rol queda en la
  auditoría con quién lo hizo (la base no anota los cambios de los demás datos, ver sección 10). (**D9**)
- **RF-1.3 Cambiar estado.** Ahora son tres estados: Activo, Inactivo y Bloqueado; todo cambio de estado
  queda en la auditoría, con justificación obligatoria si lo hace una persona (sección 10). (**D9**)

### Módulo 2 · Autenticación y acceso

- **RF-2.0 Iniciar sesión.** El RI decía `password_ est` (error de escritura): ahora es `password_hash`.
  La regla dice con claridad qué pasa al tercer intento fallido: la cuenta queda Bloqueada y se
  desbloquea sola a los 15 minutos (ver sección 9).
- **RF-2.1 Cerrar sesión.** El RI decía «ID sesión, fecha/hora cierre», que no son nombres de la base de
  datos: ahora es `sesiones: id_sesion, token_sesion, fecha_cierre, activa`.
- **RF-2.2 Recuperar contraseña.** Estaba numerado como «RN-2.2» en las tres columnas y no tenía estado.
  Ahora es RF-2.2 / RN-2.2 / RI-2.2, Obligatorio. Usa la tabla nueva `recuperacion_contrasena`: el código
  se guarda cifrado (hash), vence y sirve una sola vez. También se corrigió la ortografía. (**D11**)

### Módulo 3 · Registro de aprendices

- **RF-3.0 Formulario.** Solo se puede postular a una convocatoria Activa y una sola vez por convocatoria
  (la base de datos lo impide). El RI incluye banco, tipo de cuenta y convocatoria.
- **RF-3.1 Documentos.** Los documentos pedidos son los que existen como tipo en la base de datos:
  documento de identidad, certificado bancario y certificado de EPS (este último desde el 27/10/2026,
  ver sección 9). El tamaño debe ser mayor que 0 y hasta 5 MB.
- **RF-3.2 Enviar postulación.** Se quita el campo `fecha_envio`, que ya no existe: la postulación se
  envía en el mismo momento en que se crea (`fecha_creacion`) y nace en **Pendiente**. (**D1**)

### Módulo 4 · Panel de administración

- **RF-4.0 Listado.** El filtro usaba los estados «Pendiente, Validada, Rechazada, Completado». Ahora usa
  solo los cuatro oficiales: **Pendiente, Validada, Aprobada, Rechazada**. (**D1**)
- **RF-4.1 Comparar datos.** Se quitan `resultado_ocr` y `datos_extraidos`: el sistema **no lee** los
  documentos por su cuenta (no hay OCR); el administrador los compara a ojo en la misma pantalla. (**D6**)
- **RF-4.2 Validar o rechazar.** Desde Pendiente solo se puede pasar a Validada o Rechazada; el motivo del
  rechazo va en `justificacion_rechazo` y queda guardado quién validó. (**D1**, **D9**)
- **RF-4.3 Migración desde Excel.** Las celdas RF-4.3 y RI-4.3 tenían una fórmula en lugar de texto, y el RI
  nombraba campos sin tabla o que no existen (`registros_con_error`, `ruta_archivo`...). Ahora usa la tabla
  `migraciones` con sus campos reales. Se marcó **Opcional** porque es una carga que se hace una vez, no
  parte del flujo diario. (**D11**)
- **RF-4.4 Auditoría.** Ahora también cubre el historial de estados y deja claro que se guarda **el
  usuario que hizo el cambio**, nunca un número fijo: el propio aprendiz cuando crea o corrige su
  postulación y el administrador cuando valida, aprueba o rechaza (sección 10). (**D9**)

### Módulo 5 · Notificaciones

- **RF-5.0 y RF-5.1.** La tabla `notificaciones` ya no tiene `tipo` ni `estado`: ahora tiene `evento`
  (Validada, Aprobada, Rechazada, Pago, Novedad) y `estado_correo`. El aviso llega en el portal y por
  correo. (**D8**)
- **RF-5.2 Consultar estado.** El aprendiz ve también el historial de cambios y la justificación del rechazo.

### Módulo 6 · Seguimiento de pagos

- **RF-6.0 Registrar pago.** Antes el pago colgaba de la postulación (`id_postulacion`, `id_beneficio`).
  Ahora cuelga de la **asignación del beneficio** (`id_asignacion`) y trae fecha programada, si hubo
  retraso y el motivo del retraso (obligatorio si hubo retraso). Solo se registran pagos en
  asignaciones **En curso**. (**D4**)
- **RF-6.1 Cierre automático.** Antes la *postulación* pasaba a «Completado». Ahora la *asignación* pasa
  a **Finalizado** cuando tiene todos sus pagos; la postulación se queda en Aprobada. (**D3**)
- **RF-6.2 Historial de pagos.** Muestra también los pagos anulados con su motivo. Pasó de Opcional a
  **Obligatorio** porque sin él el administrador no puede corregir pagos. (**D4**)

## 4. Requisitos nuevos

Piense en el flujo del beneficio como un camino con varias puertas: la matriz original tenía las puertas
de la entrada (postularse, validar) y de la salida (pagar), pero le faltaban las del medio (crear
convocatorias, elegir beneficiarios, suspender, anular, avisar). Estas son las nuevas:

### En módulos que ya existían

| Requisito | Qué hace, en una frase | Decisión |
|---|---|---|
| RF-2.3 | Solo un administrador activo hace las acciones de gestión (agregado el 27/10/2026) | D7, D9 |
| RF-3.3 | El aprendiz entrega un documento que el administrador le pidió | D1 |
| RF-4.5 | El administrador pide un documento adicional; la postulación sigue en Pendiente | D1 |
| RF-5.3 | Aviso al aprendiz cuando lo aprueban | D8 |
| RF-5.4 | Aviso al aprendiz cuando le registran un pago | D8 |
| RF-5.5 | Aviso al aprendiz cuando hay una novedad | D8 |
| RF-5.6 | Bandeja de avisos en el portal (leído / no leído) | D8 |
| RF-5.7 | Envío de cada aviso por correo, con su resultado | D8 |
| RF-6.3 | Anular un pago equivocado (con motivo) y volver a registrarlo | D4 |
| RF-6.4 | El aprendiz ve su beneficio y sus pagos | D4 |

### Módulo 7 · Beneficios y convocatorias (nuevo)

| Requisito | Qué hace | Decisión |
|---|---|---|
| RF-7.0 | Crear y administrar los beneficios (alimentación, plan de datos, cívica), ligados a la institución | D10 |
| RF-7.1 | Crear convocatorias con fechas, **cupos** y valores de pago por defecto | D2, D3 |
| RF-7.2 | Abrir, cerrar o cerrar antes de tiempo una convocatoria (con motivo) | — |
| RF-7.3 | El aprendiz ve las convocatorias abiertas | — |

### Módulo 8 · Asignación de beneficios (nuevo)

Una **asignación** es «el beneficio ya entregado a un aprendiz»: cuánto recibe por pago, cuántos pagos,
desde cuándo y en qué estado va (En curso, Finalizado, Suspendido o Cancelado).

| Requisito | Qué hace | Decisión |
|---|---|---|
| RF-8.0 | Aprobar a los elegidos por la institución, **sin pasarse de los cupos** | D2 |
| RF-8.1 | Rechazar, con justificación, a los validados que no fueron elegidos | D1, D2 |
| RF-8.2 | Al aprobar, el sistema crea solo la asignación copiando los valores de la convocatoria | D3 |
| RF-8.3 | Ajustar valor, número de pagos, duración o fecha de inicio de una asignación antes de su primer pago (Opcional) | D3 |
| RF-8.4 | Suspender una asignación (con motivo) | D3, D5 |
| RF-8.5 | Reactivar una asignación suspendida (con motivo) | D3, D5 |
| RF-8.6 | Cancelar una asignación (con motivo) | D3, D5 |
| RF-8.7 | Ver el listado de beneficiarios con sus pagos | D3 |

### Módulo 9 · Novedades (nuevo)

Una **novedad** es un aviso sobre el beneficio que el aprendiz ve en su portal.

| Requisito | Qué hace | Decisión |
|---|---|---|
| RF-9.0 | El administrador escribe una novedad de «Retraso de pago» u «Otra» | D5 |
| RF-9.1 | El sistema crea solo la novedad al suspender, reactivar o cancelar (para que no se dupliquen, no se escriben a mano) | D5 |
| RF-9.2 | El aprendiz ve sus novedades | D5 |

## 5. Requisitos que se quitaron

Ninguno se borró. Lo que se quitó fue **contenido equivocado** dentro de requisitos que siguen:

- El OCR (`resultado_ocr`, `datos_extraidos`) de RF-4.1. (**D6**)
- El estado «Completado» de RN-4.0, RF-6.1 y RN-6.1. (**D1**, **D3**)
- Los campos que ya no existen: `edad`, `fecha_envio`, `pagos.id_postulacion`, `pagos.id_beneficio`,
  `notificaciones.tipo` y `notificaciones.estado`. (**D3**, **D4**, **D8**, **D11**)
- La fórmula que había dentro de las celdas RF-4.3 y RI-4.3.

## 6. ¿Dónde está cada decisión?

| Decisión | Requisitos que la cumplen |
|---|---|
| D1 Estados de la postulación | RF-3.2, RF-3.3, RF-4.0, RF-4.2, RF-4.5, RF-5.2, RF-8.1 |
| D2 Selección y cupos | RF-7.1, RF-8.0 |
| D3 Asignación del beneficio | RF-6.1, RF-8.2 a RF-8.7 |
| D4 Pagos | RF-6.0, RF-6.2, RF-6.3, RF-6.4 |
| D5 Novedades | RF-8.4 a RF-8.6, RF-9.0 a RF-9.2 |
| D6 Validación manual, sin OCR | RF-4.1 |
| D7 Roles Administrador y Aprendiz | columna ROL de todas las filas; RF-1.0, RF-1.2, RF-2.3, RF-3.0 |
| D8 Notificaciones | RF-5.0, RF-5.1, RF-5.3 a RF-5.7 |
| D9 Trazabilidad | RF-1.2, RF-1.3, RF-2.3, RF-4.1, RF-4.2, RF-4.4, RF-8.0 |
| D10 Instituciones | RF-7.0 |
| D11 Datos faltantes | RF-1.0 (fecha de nacimiento), RF-2.2 (recuperación), RF-4.3 (migraciones) |
| D12 Compatibilidad MariaDB 10.4 | No es un requisito funcional: se cumple en el script de base de datos |
| D13 Bloqueo y desbloqueo automáticos | RF-1.3, RF-2.0, RF-2.3 |
| D14 Certificado de EPS | RF-3.1, RF-3.2 |
| D15 Auditoría de cuentas y migraciones con justificación | RF-1.2, RF-1.3, RF-2.0, RF-4.3, RF-4.4 |
| D16 Hora real del servidor | RF-2.0, RF-4.3, RF-4.4 |
| D17 Lista blanca de `root` (mantenimiento) | RF-1.3, RF-2.0, RF-2.3, RF-4.4 |

## 7. Decisiones que tomé y que las fuentes no cubrían

1. **Se conservó la numeración original (1 a 6)** y los módulos nuevos son el 7, el 8 y el 9, en lugar de
   renumerar todo. Así las referencias que ya usa el equipo siguen siendo válidas.
2. **Bloqueo por intentos fallidos (RN-2.0):** ~~quedaba abierto cuánto dura el bloqueo~~. **Cerrado el
   27/10/2026 por decisión de Samuel:** la cuenta se desbloquea sola a los 15 minutos y un administrador
   activo puede desbloquearla antes (sección 9).
3. **Documentos obligatorios (RF-3.1, RN-3.2):** ~~quedaba abierto si se pide el certificado de EPS~~.
   **Cerrado el 27/10/2026 por decisión de Samuel:** se agrega el tipo «Certificado de EPS» y es
   obligatorio junto al documento de identidad y el certificado bancario (sección 9). Que los tres
   estén cargados antes de enviar lo comprueba la aplicación, no la base de datos (sección 10).
4. **RF-8.3 (ajustar la asignación) es Opcional:** la base de datos lo permite, pero el flujo funciona sin él.
5. **RF-4.3 (migración desde Excel) es Opcional:** es una carga que se hace una vez.
6. **RF-6.2 pasó de Opcional a Obligatorio:** para anular un pago (RF-6.3) primero hay que verlo.
7. **Columna ROL:** se usan «Administrador», «Aprendiz» y, como en el original, «Sistema» para lo que pasa
   solo (por ejemplo, crear la asignación al aprobar).

## 8. Qué tablas usa cada requisito

Esta tabla sale de la columna «Requisitos de información» de la matriz. Las 23 tablas de
`benefitrack_v2.sql` aparecen en al menos un requisito.

| RF | Requisito | Tablas que usa |
|---|---|---|
| RF-1.0 | Registrar usuario | `usuarios`, `roles`, `programas`, `eps` |
| RF-1.1 | Consultar usuarios | `usuarios`, `roles`, `programas` |
| RF-1.2 | Modificar usuario | `usuarios`, `auditoria` |
| RF-1.3 | Cambiar estado del usuario | `usuarios`, `auditoria` |
| RF-2.0 | Iniciar sesión | `usuarios`, `sesiones`, `auditoria` |
| RF-2.1 | Cerrar sesión | `sesiones` |
| RF-2.2 | Recuperar contraseña | `usuarios`, `recuperacion_contrasena` |
| RF-2.3 | Controlar permisos de gestión (nuevo) | `usuarios`, `roles` |
| RF-3.0 | Diligenciar formulario de postulación | `usuarios`, `eps`, `informacion_bancaria`, `entidades_bancarias`, `tipos_cuenta`, `convocatorias`, `postulaciones` |
| RF-3.1 | Cargar documentos de soporte | `documentos_postulacion`, `tipos_documento` |
| RF-3.2 | Enviar postulación | `postulaciones`, `historial_estados`, `auditoria` |
| RF-3.3 | Atender solicitud de documentación adicional (nuevo) | `solicitudes_documentacion`, `documentos_postulacion`, `postulaciones` |
| RF-4.0 | Listar postulaciones | `usuarios`, `postulaciones`, `convocatorias`, `documentos_postulacion` |
| RF-4.1 | Comparar datos y documentos | `postulaciones`, `usuarios`, `informacion_bancaria`, `documentos_postulacion` |
| RF-4.2 | Validar o rechazar postulación | `postulaciones`, `documentos_postulacion` |
| RF-4.3 | Migrar datos desde Excel | `migraciones`, `auditoria` |
| RF-4.4 | Registrar auditoría e historial | `auditoria`, `historial_estados` |
| RF-4.5 | Solicitar documentación adicional (nuevo) | `solicitudes_documentacion`, `postulaciones` |
| RF-5.0 | Notificar validación | `notificaciones`, `postulaciones` |
| RF-5.1 | Notificar rechazo | `notificaciones`, `postulaciones` |
| RF-5.2 | Consultar estado de la postulación | `postulaciones`, `historial_estados`, `convocatorias` |
| RF-5.3 | Notificar aprobación (nuevo) | `notificaciones`, `postulaciones`, `convocatorias` |
| RF-5.4 | Notificar pago registrado (nuevo) | `notificaciones`, `pagos`, `asignaciones_beneficio` |
| RF-5.5 | Notificar novedad (nuevo) | `notificaciones`, `novedades` |
| RF-5.6 | Ver notificaciones en el portal (nuevo) | `notificaciones` |
| RF-5.7 | Enviar notificaciones por correo (nuevo) | `notificaciones` |
| RF-6.0 | Registrar pago | `pagos`, `asignaciones_beneficio` |
| RF-6.1 | Finalizar asignación automáticamente | `pagos`, `asignaciones_beneficio` |
| RF-6.2 | Consultar historial de pagos | `pagos`, `asignaciones_beneficio`, `usuarios` |
| RF-6.3 | Anular pago (nuevo) | `pagos`, `asignaciones_beneficio`, `auditoria` |
| RF-6.4 | Consultar mis pagos (nuevo) | `asignaciones_beneficio`, `pagos`, `postulaciones` |
| RF-7.0 | Gestionar beneficios (nuevo) | `beneficios`, `instituciones` |
| RF-7.1 | Crear y modificar convocatoria (nuevo) | `convocatorias`, `beneficios`, `postulaciones` |
| RF-7.2 | Cambiar estado de la convocatoria (nuevo) | `convocatorias` |
| RF-7.3 | Consultar convocatorias abiertas (nuevo) | `convocatorias`, `beneficios` |
| RF-8.0 | Aprobar beneficiario con control de cupos (nuevo) | `postulaciones`, `convocatorias` |
| RF-8.1 | Rechazar postulación no elegida (nuevo) | `postulaciones`, `historial_estados` |
| RF-8.2 | Crear asignación automáticamente (nuevo) | `asignaciones_beneficio`, `convocatorias` |
| RF-8.3 | Ajustar condiciones de la asignación (nuevo) | `asignaciones_beneficio`, `pagos`, `convocatorias`, `auditoria` |
| RF-8.4 | Suspender asignación (nuevo) | `asignaciones_beneficio`, `novedades` |
| RF-8.5 | Reactivar asignación (nuevo) | `asignaciones_beneficio`, `novedades` |
| RF-8.6 | Cancelar asignación (nuevo) | `asignaciones_beneficio`, `novedades` |
| RF-8.7 | Consultar asignaciones (nuevo) | `asignaciones_beneficio`, `postulaciones`, `usuarios`, `convocatorias`, `pagos` |
| RF-9.0 | Registrar novedad (nuevo) | `novedades`, `asignaciones_beneficio` |
| RF-9.1 | Crear novedades automáticas (nuevo) | `novedades`, `asignaciones_beneficio` |
| RF-9.2 | Consultar mis novedades (nuevo) | `novedades`, `asignaciones_beneficio`, `postulaciones` |

## 9. Cambios del 27/10/2026: reglas de la base de datos y decisiones de Samuel

La base de datos tuvo varias rondas más de correcciones (de la segunda a la novena: secciones 6 a 13
de `base-de-datos/CAMBIOS.md`). Esta sección cubre las rondas 2 a 6; la séptima (auditoría de cuentas y
migraciones) está en la sección 10, la octava en la sección 11 y la novena en la sección 12. La matriz las tiene que decir también; si no, quien programe la
aplicación se encontraría con errores de la base de datos que ningún requisito explica. Es como un
reglamento del colegio: si el portero no deja entrar sin carné, el reglamento escrito tiene que
decirlo.

### 9.1 Reglas de la base de datos que ahora están en la matriz

| Regla | Dónde quedó | Qué dice, en palabras sencillas |
|---|---|---|
| Quién se postula | RN-3.0 | Solo un usuario con rol Aprendiz se postula, y solo a una convocatoria Activa y dentro de sus fechas (el primer y el último día cuentan). |
| Fechas y montos de los pagos | RN-6.0 | La fecha de pago va del inicio de la asignación hasta hoy (no se anotan pagos «del futuro»); la fecha programada no puede ser anterior al inicio; el monto es mayor que cero y, si no es el valor acordado, hay que escribir por qué en la observación. |
| Las huellas no se borran | RN-4.4 | La auditoría y el historial de estados son como un libro de actas: solo se agregan páginas. Tampoco se borran postulaciones (se rechazan), asignaciones (se cancelan) ni pagos (se anulan). |
| Nadie falsifica quién validó | RN-4.1, RN-4.2, RN-3.2 | Quién validó o aprobó y cuándo lo anota el sistema; no se escribe a mano. La justificación de un rechazo no se cambia, el formulario solo se corrige mientras está Pendiente y la postulación no cambia de aprendiz ni de convocatoria. |
| Solo un administrador activo gestiona | **RF-2.3 (nuevo)**, RN-1.0, RN-1.2, RN-1.3 | Validar, aprobar, rechazar, pedir documentos, manejar asignaciones, pagos y novedades, cambiar cupos o estado de una convocatoria, cambiar el rol o el estado de un usuario y crear administradores. Un aprendiz o un administrador inactivo recibe «Solo un administrador activo puede…». |
| Cupos | RN-7.1 | Los cupos no pueden bajar de las postulaciones ya aprobadas. |
| Congelamiento de la asignación | RN-8.3 | Valor, número de pagos, duración y fecha de inicio solo se ajustan mientras la asignación está En curso y **no tiene ningún pago** (ni siquiera anulado). Después quedan congelados. Antes la matriz decía que se podía ajustar el número de pagos con pagos ya hechos, y eso contradecía la base de datos. |
| Fecha de inicio de la asignación | RN-8.2, RN-8.3 | Entre el día en que empezó la convocatoria y un año después de hoy (los dos extremos cuentan). |

### 9.2 Decisiones de Samuel (27/10/2026)

1. **Bloqueo de la cuenta (RN-2.0 y RN-1.3).** Funciona como un cajero automático:
   - tras 3 intentos fallidos seguidos, la cuenta queda Bloqueada; solo una cuenta Activa se bloquea
     por intentos fallidos, y la fecha del bloqueo la pone el sistema en ese momento;
   - a los **15 minutos** la cuenta se desbloquea sola (vuelve a Activo, con los intentos en 0);
   - un administrador activo puede desbloquearla antes, en cualquier momento;
   - una cuenta **Inactiva** solo la reactiva un administrador activo: no se reactiva sola ni
     «pasando por Bloqueado». Por eso, para quitar el acceso sin límite de tiempo se usa Inactivo y no
     Bloqueado.
2. **Certificado de EPS (RF-3.1 y RN-3.2).** Los documentos que adjunta el aprendiz son: documento de
   identidad, certificado bancario y **certificado de EPS** (los tres obligatorios para enviar), más el
   documento adicional cuando el administrador lo pide (RF-3.3).

Con esto se cierran las dos preguntas que quedaban abiertas en la sección 7.

### 9.3 Cómo se comprobó

Primero se agregaron al verificador las reglas (h) a (l) y se ejecutaron contra la matriz anterior:
fallaron (rojo). Luego se cambiaron los datos de `herramientas/datos_matriz.js`, se regeneró el Excel
con `herramientas/generar_matriz.js` y el verificador quedó, en ese momento, en **9 de 9 reglas OK** (verde). La hoja
«Control de cambios» tiene 11 filas nuevas con fecha 27/10/2026.

**La base de datos ya lo cumple.** Esta rama está rebasada sobre la novena ronda de la base de datos.
Los tres puntos del bloqueo que antes estaban pendientes ya los hace cumplir el script
`base-de-datos/benefitrack_v2.sql` (quinta y sexta ronda, trigger `tr_usuario_validar`): sin
administrador, solo una cuenta Activa pasa a Bloqueado; la fecha de bloqueo la pone siempre la base
con la hora real del servidor (`SYSDATE()`), aunque la aplicación mande otra; y una cuenta Inactiva no
se reactiva pasando por Bloqueado.

## 10. Cambios del 27/10/2026 (segunda tanda): auditoría de cuentas y correcciones de la revisión

Una revisión de la matriz encontró una regla que no coincidía con la base de datos, frases que
prometían más de lo que la base hace y comprobaciones del verificador demasiado débiles. Además, la
séptima ronda de la base de datos agregó la auditoría de las cuentas, y el coordinador anunció una
octava ronda cuyas reglas ya quedan escritas aquí en su forma final.

### 10.1 Qué cambió en la matriz

| Requisito | Qué dice ahora, en palabras sencillas | Por qué |
|---|---|---|
| RN-8.2 | La asignación empieza el día de la aprobación o, si la convocatoria aún no ha empezado, el día de inicio de la convocatoria. | Antes decía «igual a la fecha de aprobación», pero la base usa el día más tarde entre hoy y el inicio de la convocatoria. |
| RN-1.2 | Todo cambio de rol queda en la auditoría con quién, cuándo, el rol anterior y el nuevo. Los cambios de los demás datos (nombre, correo...) la base **no** los anota. | Antes decía «cada cambio queda en auditoría», y la base solo anota el rol y el estado. |
| RN-1.3 | Todo cambio de estado queda en la auditoría con quién, cuándo, el estado anterior y el nuevo y la justificación. Si lo hace una persona, la justificación es obligatoria y cada cambio lleva la suya. El bloqueo y el desbloqueo automáticos quedan como hechos por el sistema. | Decisión de Samuel: la auditoría de las cuentas la guarda la base (séptima ronda). |
| RN-2.0 | El bloqueo automático solo lo hace el sistema durante el inicio de sesión y tras 3 intentos fallidos; cualquier otro bloqueo lo hace un administrador activo con justificación. El desbloqueo automático solo lo hace el sistema a los 15 minutos. Los dos quedan en la auditoría sin autor. | Séptima ronda y forma final de la octava. |
| RN-4.3 | Cada carga desde Excel queda en la auditoría al iniciarse o registrarse y en cada cambio de su estado (por ejemplo, al terminar). Una migración terminada no se puede modificar. | Séptima ronda y forma final de la octava. |
| RF-4.4 / RN-4.4 | Dice qué se anota (postulaciones, asignaciones, pagos y anulaciones, novedades, rol y estado de usuarios, migraciones) y quién queda registrado: el propio aprendiz cuando crea o corrige su postulación, el administrador cuando valida, aprueba o rechaza, y nadie (el sistema) en el bloqueo y el desbloqueo automáticos. La fecha de la auditoría la pone el sistema. | Antes decía que «nunca» se guardaba al aprendiz dueño de la postulación, y sí se guarda cuando él mismo hace el cambio. |
| RN-1.0 / RN-2.3 | Crear un usuario que no nace Activo también exige un administrador activo. | Lo hace cumplir la base desde la quinta ronda. |
| RN-3.2 | El certificado de EPS es obligatorio para enviar la postulación, junto al documento de identidad y el certificado bancario. | **Decisión de Samuel (27/10/2026).** Esto lo comprueba la **aplicación**, no la base de datos: la base no revisa qué documentos están cargados antes de crear la postulación. |

La hoja «Control de cambios» tiene 7 filas nuevas con fecha 27/10/2026 por esta tanda.

### 10.2 Qué cambió en el verificador

- Las reglas (h) y (l) se renombraron con honestidad: comprueban **frases acordadas**, no leen el SQL
  (sección 2).
- Se reforzaron frases que eran demasiado cortas: en RF-3.0 ya no basta la palabra «activa», en RF-8.3
  ya no bastan «anulado» ni «fecha de inicio», y en RF-3.2 se exige la frase completa de que no se puede
  enviar sin los tres documentos.
- (j) compara el número completo de minutos: «115 minutos» ya no pasa por «15 minutos».
- (k) revisa también la sección 7 de este documento.
- (l) tiene frases equivocadas conocidas que no deben aparecer (por ejemplo, «hasta que el
  administrador lo reactive»).
- (m) es nueva: auditoría de cuentas y migraciones.

### 10.3 Cómo se comprobó

Igual que antes: primero se cambió el verificador y se ejecutó contra la matriz anterior (rojo: 8 de
10). Luego se cambiaron los datos, se regeneró el Excel y quedó en **10 de 10 reglas OK** (verde).
Contra la matriz original da **1 de 10** (solo (k) pasa). Lo mismo se repitió para las reglas de la
octava ronda.

### 10.4 La octava ronda: el SQL ya la cumple

Cuando se escribió esta tanda, algunas reglas se redactaron en su forma final antes de que la octava
ronda llegara al SQL. Desde el 27/10/2026 por la noche esta rama está rebasada sobre esa ronda y
`base-de-datos/benefitrack_v2.sql` **ya las cumple**:

- el bloqueo automático solo cuenta como tal si lo hace el inicio de sesión (sin usuario de sesión) y
  la cuenta tiene 3 o más intentos fallidos; si no, hace falta un administrador activo con
  justificación;
- una migración terminada (que ya no está En proceso) no se puede modificar;
- la fecha de la auditoría la pone la base con la hora real del servidor;
- los procedimientos `pr_cambiar_estado_usuario` y `pr_cambiar_rol_usuario` usan la justificación
  solo para ese cambio y la borran al terminar, aunque el cambio falle.

## 11. Cambios del 27/10/2026 (tercera tanda): octava ronda de la base de datos (decisiones D13 a D17)

La octava ronda (sección 12 de `base-de-datos/CAMBIOS.md` y decisiones D13 a D17 de
`specs/001-correccion-entregables.md`) cambió quién cuenta como autor de cada cambio. Es como el libro
de visitas de un edificio: no basta con anotar «alguien entró»; hay que distinguir si fue el portero
automático, el técnico de mantenimiento o una persona con nombre.

### 11.1 Qué cambió en la matriz

| Requisito | Qué dice ahora, en palabras sencillas | Decisión |
|---|---|---|
| RN-1.2 | El cambio de rol también exige una justificación, que queda en la auditoría. La vía recomendada es `pr_cambiar_rol_usuario`. | D15 |
| RN-1.3 | Cada cambio de estado lleva su propia justificación. La vía recomendada es `pr_cambiar_estado_usuario`, que la borra al terminar. | D15 |
| RN-2.3 | Fuera del mantenimiento de la base con `root` (que también debe justificar los cambios de cuentas), una acción de gestión sin usuario identificado se rechaza. | D17 |
| RN-4.3 | Las fechas de inicio y de fin de una carga desde Excel las pone el sistema. | D16 |
| RN-4.4 | La auditoría de las cuentas distingue tres orígenes: **Sistema** (cambio automático del inicio de sesión), **Mantenimiento** (`root` sin usuario de sesión) y **Usuario** (una persona con sesión). El usuario vacío solo significa «el sistema» cuando el origen es Sistema. | D15, D17 |

La hoja «Control de cambios» tiene 1 fila nueva por esta ronda (65 en total).

### 11.2 Qué cambió en el verificador

- **(j) lee los minutos de donde estén.** La octava ronda movió el cálculo de los 15 minutos de
  `tr_usuario_validar` a `fn_cambio_estado_sistema`, y el verificador dejó de encontrarlo (9 de 10).
  Ahora (j) junta todas las apariciones de «INTERVAL n MINUTE» y «n minutos» en el código del bloque
  de usuarios del SQL, exige que sean iguales entre sí y que coincidan con RF-2.0 y RF-1.3. Se probó
  con una copia del SQL que decía 20 en un lugar: (j) falló como debía.
- **(c) revisa también las rutinas.** Como la matriz ahora cita procedimientos, (c) comprueba que
  cada `pr_…`, `fn_…` o `tr_…` de la hoja exista en el SQL. Se probó con una copia del SQL que
  renombraba `pr_cambiar_rol_usuario`: (c) falló como debía.
- **(h) y (m)** tienen frases nuevas para las decisiones de la tabla de arriba.

### 11.3 Cómo se comprobó

Primero se cambió el verificador y se ejecutó contra la matriz anterior (rojo: 8 de 10, fallaban (h)
y (m)). Luego se cambiaron los datos, se regeneró el Excel y quedó en **10 de 10 reglas OK** (verde).
Contra la matriz original daba, en ese momento, **1 de 10**: solo pasaba (k); (m) tenía 25 problemas. Se revisaron los RN
de cuentas, auditoría y migraciones contra el SQL de la octava ronda y no se encontraron
contradicciones.

## 12. Cambios del 28/10/2026: novena ronda, reglas de la aplicación y textos más claros

Es la última pasada antes de unir la rama. La revisión final dio el visto bueno, con algunos arreglos.

### 12.1 Novena ronda de la base de datos (secciones 13 y 13.1 de `base-de-datos/CAMBIOS.md`, D9 y D15)

Lo que se crea nace limpio: nadie puede subir algo con el sello de «aprobado» ya puesto.

| Requisito | Qué dice ahora |
|---|---|
| RN-3.1, RN-4.1 | Un documento nuevo debe empezar en estado Pendiente y sin datos de validación; si trae datos de validación, el sistema los deja vacíos. La fecha de carga la pone el sistema. |
| RN-4.5 | Una solicitud de documentación nueva debe empezar en Pendiente; el autor y la fecha los pone el sistema. |
| RN-6.0, RN-9.0 | Quién registró un pago o una novedad, y cuándo, lo pone el sistema. |
| RN-7.1, RN-2.3 | Solo un administrador activo crea convocatorias, que nacen Activa o Inactiva (por defecto, Inactiva). Antes decía «se crea Inactiva», y la base también acepta Activa. |
| RN-1.2, RN-1.3 | Los procedimientos `pr_cambiar_rol_usuario` y `pr_cambiar_estado_usuario` dan error si el rol o el estado no es válido o si el cambio no cambia nada. |

### 12.2 Qué valida la base y qué valida la aplicación

Muchas reglas de la matriz las hace cumplir la base de datos (por ejemplo, los permisos de RN-2.3).
**Las demás reglas y permisos de esta matriz los valida la aplicación.** Para que no haya dudas, se
marcaron con «lo valida la aplicación» las reglas que la base **no** comprueba (revisado contra el SQL):

- RN-1.2: el número de documento no se puede modificar;
- RN-3.1: el formato del archivo (PDF, JPG o PNG) y que el tipo de documento esté Activo;
- RN-3.2: que estén los tres documentos obligatorios antes de enviar (ya estaba marcada);
- RN-3.3: que el documento pedido se cargue con el tipo Documento adicional;
- RN-7.0: que un beneficio Inactivo no admita convocatorias nuevas.

RN-7.2 (quién cerró la convocatoria y cuándo) **no** se marca: lo anotará el sistema al cerrar
(décima ronda, sección 12.5).

### 12.3 Otros arreglos

- **«Enviada».** La regla (d) prohibía la palabra «Enviada» (era un estado viejo de la postulación),
  pero `notificaciones.estado_correo` sí tiene el valor «Enviada». Ahora (d) la permite solo en RF-5.7, y
  RN-5.7 dice «Enviada cuando se envía».
- **RN-8.6.** El motivo de la cancelación debe ser distinto del motivo anterior, como en suspender y
  reactivar.
- **RN-1.3 y RN-2.0.** Dicen «(salvo mantenimiento, RF-2.3)», y RN-2.0 aclara que el desbloqueo
  automático ocurre cuando el usuario vuelve a intentar entrar.
- **Textos más cortos.** RN-1.3, RN-2.0, RN-2.3 y RN-4.4 se reescribieron en frases cortas y sin
  repeticiones; las reglas no cambiaron. En el verificador se quitó una frase acordada de RN-4.4 que
  repetía otra: «en el bloqueo y el desbloqueo automáticos de una cuenta el campo queda vacío y
  significa que lo hizo el sistema» (ya lo dice «el usuario vacío solo significa “el sistema” cuando el
  origen es Sistema»).
- **Generadores.** `generar_ficha.js`, igual que `generar_matriz.js`, avisa con un mensaje claro y
  termina con código 1 si falta la ruta de salida.
- **Fechas.** Se unificaron: la primera y la segunda tanda y la octava ronda son del 27/10/2026; esta
  sección es del 28/10/2026.

La hoja «Control de cambios» tiene 4 filas nuevas con fecha 28/10/2026 (69 en total), contando la
de la décima ronda.

### 12.4 Cómo se comprobó

Primero se cambió el verificador (rojo: 9 de 10, con 24 problemas en (h)). Luego se cambiaron los
datos, se regeneró el Excel y quedó en **10 de 10 reglas OK** (verde). Lo mismo se repitió para la
décima ronda (rojo: 9 de 10, con 14 problemas en (h); después, verde). Contra la matriz original da
**1 de 10** (solo pasa (k)).

### 12.5 Décima ronda: escrita ya en su forma final

El coordinador anunció una décima ronda de la base de datos, que se trabaja en otra rama. Para no
reescribir la matriz otra vez, sus reglas ya quedan escritas (sin nombrar triggers ni columnas nuevas):

| Requisito | Qué dice |
|---|---|
| RN-7.1, RN-2.3 | Cualquier cambio de una convocatoria (valor, número de pagos, duración, fechas, cupos o estado) lo hace solo un administrador activo. |
| RN-7.2 | Al cerrar, el sistema anota quién la cerró y la fecha real de cierre. |
| RN-3.1 | Un documento Validado no se puede reemplazar; uno Pendiente o Rechazado sí, y al reemplazarlo vuelve a Pendiente y pierde los datos de validación. |
| RN-4.1 | Las observaciones del documento solo las escribe un administrador. |
| RN-3.3, RN-4.5 | Una solicitud solo pasa de Pendiente a Atendida o Cerrada y sus datos de origen no se modifican; la fecha de atención la pone el sistema; cerrarla exige un administrador activo. |
| RN-7.0, RN-2.3 | Crear o modificar beneficios y catálogos (instituciones, programas, tipos de documento, entidades bancarias, EPS y tipos de cuenta) exige un administrador activo. |

**Advertencia honesta.** El SQL de esta rama (novena ronda) **todavía no** hace cumplir estas reglas.
Por ejemplo, hoy no llena solo quién cerró una convocatoria (pendiente de la sección 14 de
`base-de-datos/CAMBIOS.md`) ni impide reemplazar un documento ya Validado. Cuando esta rama se rebase
sobre la décima ronda, esta advertencia se debe quitar.
