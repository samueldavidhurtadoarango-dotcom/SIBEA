# Cambios en la matriz de requisitos de BenefitRack (versión 2)

Este documento explica, en palabras sencillas, qué cambió entre la matriz original
(`originales/Matriz_Requisitos.xlsx`) y la nueva (`entregables/Matriz_Requisitos_v2.xlsx`), y por qué.

Cada cambio cita la decisión de la especificación `specs/001-correccion-entregables.md`
que lo justifica (D1 a D12). Cuando no hay una decisión detrás, se indica con «—».

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
| Reglas del verificador que pasan | 1 de 9 | 9 de 9 |

Los números de los módulos originales (1 a 6) **se conservaron** para que nadie del equipo pierda la
referencia: RF-4.1 sigue siendo «comparar datos» y RF-6.0 sigue siendo «registrar pago». Los módulos
nuevos son el 7, el 8 y el 9. En el Excel, el número de cada requisito nuevo tiene fondo verde, y la
hoja «Control de cambios» tiene una fila por cada cambio (fechas 26/10/2026 y 27/10/2026, responsable
Samuel David Hurtado Arango).

## 2. ¿Cómo se comprobó? (el verificador)

`entregables/verificar_matriz.js` es un pequeño programa que lee el Excel y la base de datos y revisa
nueve reglas. No necesita instalar nada: solo Node. Se ejecuta así:

```bash
node entregables/verificar_matriz.js                                   # revisa la v2
node entregables/verificar_matriz.js originales/Matriz_Requisitos.xlsx # revisa la original
```

| Regla | Qué revisa | Original | v2 |
|---|---|---|---|
| (c) | Cada `tabla: campo` del RI existe en `benefitrack_v2.sql` | FALLO (15 problemas) | OK |
| (d) | No aparecen «OCR», «resultado_ocr», «datos_extraidos», «Estudiante», «Completado», «Borrador», «En revisión» ni «Enviada» | FALLO (5 problemas) | OK |
| (e) | RF-x.y, RN-x.y y RI-x.y tienen el mismo número en cada fila | FALLO (4 problemas) | OK |
| (g) | El estado del requisito es «Obligatorio» u «Opcional» | FALLO (2 problemas) | OK |
| (h) | Las reglas que la base de datos hace cumplir (rondas 2 a 4) están escritas en el requisito que corresponde, y no queda ninguna frase que las contradiga | FALLO (26 problemas) | OK |
| (i) | Los tipos de documento de la semilla del SQL (`tipos_documento`) aparecen en RF-3.1 | FALLO (4 problemas) | OK |
| (j) | RF-2.0 y RF-1.3 dicen los mismos minutos de desbloqueo que el trigger `tr_usuario_validar` (15) | FALLO (2 problemas) | OK |
| (k) | No quedan preguntas abiertas ni temas «por confirmar» | OK | OK |
| (l) | El bloqueo de la cuenta dice quién bloquea y quién desbloquea | FALLO (5 problemas) | OK |

La regla (d) revisa solo la hoja «Matriz de Requisitos». La hoja «Control de cambios» sí nombra esas
palabras, porque explica qué se quitó.

Las reglas (h) a (l) se agregaron el 27/10/2026 (sección 9). Las reglas (i) y (j) leen los datos
directamente del SQL: si mañana la base de datos agrega otro tipo de documento o cambia los 15 minutos,
el verificador avisa que la matriz quedó desactualizada.

## 3. Requisitos que se corrigieron

### Módulo 1 · Gestión de usuarios

- **RF-1.0 Registrar usuario.** La regla y la información decían «RN 1.0» y «RI 1.0» (sin guion): ahora
  dicen «RN-1.0» y «RI-1.0». La base de datos ya no guarda la `edad` (cambia cada año) sino la
  `fecha_nacimiento`. Se agregan la EPS (`id_eps`) y la regla de que el correo no se puede repetir. (**D11**)
- **RF-1.1 Consultar usuarios.** Se agrega el estado «Bloqueado», que existe en la base de datos.
- **RF-1.2 Modificar usuario.** Se agregan `fecha_nacimiento` e `id_eps`; la auditoría guarda quién hizo el cambio. (**D9**)
- **RF-1.3 Cambiar estado.** Ahora son tres estados: Activo, Inactivo y Bloqueado; la justificación
  queda en la auditoría. (**D9**)

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
  administrador que hizo el cambio**, no un número fijo ni el aprendiz. (**D9**)

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
| RF-8.3 | Ajustar valor, número de pagos o duración de una asignación (Opcional) | D3 |
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

## 7. Decisiones que tomé y que las fuentes no cubrían

1. **Se conservó la numeración original (1 a 6)** y los módulos nuevos son el 7, el 8 y el 9, en lugar de
   renumerar todo. Así las referencias que ya usa el equipo siguen siendo válidas.
2. **Bloqueo por intentos fallidos (RN-2.0):** ~~quedaba abierto cuánto dura el bloqueo~~. **Cerrado el
   27/10/2026 por decisión de Samuel:** la cuenta se desbloquea sola a los 15 minutos y un administrador
   activo puede desbloquearla antes (sección 9).
3. **Documentos obligatorios (RF-3.1, RN-3.2):** ~~quedaba abierto si se pide el certificado de EPS~~.
   **Cerrado el 27/10/2026 por decisión de Samuel:** se agrega el tipo «Certificado de EPS» y es
   obligatorio junto al documento de identidad y el certificado bancario (sección 9).
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
| RF-2.0 | Iniciar sesión | `usuarios`, `sesiones` |
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

La base de datos tuvo tres rondas más de correcciones (secciones 6, 7 y 8 de
`base-de-datos/CAMBIOS.md`). La matriz las tiene que decir también; si no, quien programe la
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
con `herramientas/generar_matriz.js` y el verificador quedó en **9 de 9 reglas OK** (verde). La hoja
«Control de cambios» tiene 11 filas nuevas con fecha 27/10/2026.

**Una advertencia honesta.** Tres puntos del bloqueo (solo una cuenta Activa se bloquea por intentos,
la fecha de bloqueo la pone siempre el sistema y una cuenta Inactiva no se reactiva pasando por
Bloqueado) los hace cumplir la quinta ronda de la base de datos, que se trabaja en otra rama. El script
`base-de-datos/benefitrack_v2.sql` de esta rama todavía permite pasar una cuenta Inactiva a Bloqueado
sin administrador (y que a los 15 minutos quede Activa) y acepta una `fecha_bloqueo` enviada por la
aplicación. La matriz ya dice cómo debe ser; falta que llegue esa ronda.
