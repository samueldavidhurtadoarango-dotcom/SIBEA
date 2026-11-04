#!/usr/bin/env node
/*
 * Verificador de la matriz de requisitos de BenefitRack.
 *
 * Uso:
 *   node entregables/verificar_matriz.js [ruta_xlsx] [ruta_sql]
 *
 * Por defecto revisa entregables/Matriz_Requisitos_v2.xlsx contra
 * base-de-datos/benefitrack_v2.sql. No necesita dependencias: lee el .xlsx
 * (que es un archivo ZIP con XML) usando solo módulos de Node.
 *
 * Reglas:
 *   (c) cada "tabla: campo" citado en Requisitos de información existe en el SQL,
 *       y cada procedimiento, función o trigger citado (pr_..., fn_..., tr_...)
 *       en la hoja existe en el SQL;
 *   (d) no aparecen palabras prohibidas (OCR, Estudiante, estados eliminados);
 *       «Enviada» se permite solo en RF-5.7, donde es un valor real de
 *       notificaciones.estado_correo;
 *   (e) en cada fila coinciden los números RF-x.y, RN-x.y y RI-x.y;
 *   (g) el estado del requisito es "Obligatorio" u "Opcional";
 *   (h) frases acordadas de las reglas de la base de datos (rondas 2 a 10 de
 *       base-de-datos/CAMBIOS.md) presentes en el RF/RN que corresponde, y
 *       ausencia de algunas frases equivocadas conocidas. OJO: esta regla NO
 *       lee el SQL; las frases se copiaron a mano de CAMBIOS.md y se revisaron
 *       contra el script. Si la base de datos cambia, hay que actualizarlas;
 *   (i) todos los tipos de documento de la semilla del SQL aparecen en RF-3.1
 *       (esta sí lee el SQL);
 *   (j) los minutos del desbloqueo automático de RF-2.0 y RF-1.3 son los del
 *       SQL (todas las apariciones en el bloque de usuarios, que deben ser
 *       iguales entre sí; compara el número completo,
 *       así "115 minutos" no cuenta como "15 minutos");
 *   (k) no quedan preguntas abiertas ni temas "por confirmar" en la hoja de
 *       la matriz ni en la sección 7 de entregables/CAMBIOS_MATRIZ.md (donde
 *       estaban las preguntas abiertas; lo tachado con ~~ ~~ no cuenta);
 *   (l) frases acordadas del bloqueo de la cuenta (quién bloquea y quién
 *       desbloquea); igual que (h), copiadas a mano y no leídas del SQL;
 *   (m) frases acordadas de la auditoría de cuentas y migraciones (ronda 7):
 *       qué anota la base en auditoria y quién queda registrado; igual que
 *       (h), copiadas a mano y no leídas del SQL;
 *   (n) frases acordadas de la ronda 11 (D19, nadie toca los datos de otra
 *       persona): cuenta bancaria, datos de la cuenta, recuperación de
 *       contraseña, postulaciones, documentos, notificaciones y migraciones;
 *       igual que (h), copiadas a mano y no leídas del SQL.
 * Termina con código 1 si alguna regla falla.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const RAIZ = path.resolve(__dirname, '..');
const RUTA_XLSX = process.argv[2] || path.join(RAIZ, 'entregables', 'Matriz_Requisitos_v2.xlsx');
const RUTA_SQL = process.argv[3] || path.join(RAIZ, 'base-de-datos', 'benefitrack_v2.sql');
const RUTA_CAMBIOS_MATRIZ = path.join(RAIZ, 'entregables', 'CAMBIOS_MATRIZ.md');
const HOJA_MATRIZ = 'Matriz de Requisitos';

// Columnas de la matriz (iguales en la original y en la v2).
const COL = { modulo: 'A', subproceso: 'B', rol: 'C', rf: 'D', rfDesc: 'E', rn: 'F', rnDesc: 'G', ri: 'H', riDesc: 'I', estado: 'J' };
const PRIMERA_FILA_DATOS = 3;

const PALABRAS_PROHIBIDAS = ['OCR', 'resultado_ocr', 'datos_extraidos', 'Estudiante', 'Completado', 'Borrador', 'En revisión', 'Enviada'];

const VERDE = '\x1b[32m';
const ROJO = '\x1b[31m';
const NORMAL = '\x1b[0m';

// ---------------------------------------------------------------------------
// (a) Lectura del .xlsx
// ---------------------------------------------------------------------------

/** Devuelve un Map nombre -> Buffer con los archivos del ZIP. */
function leerZip(ruta) {
  const buf = fs.readFileSync(ruta);
  let fin = -1;
  for (let i = buf.length - 22; i >= 0; i--) {
    if (buf.readUInt32LE(i) === 0x06054b50) { fin = i; break; }
  }
  if (fin < 0) throw new Error(`${ruta} no es un archivo ZIP/XLSX válido`);
  const total = buf.readUInt16LE(fin + 10);
  let p = buf.readUInt32LE(fin + 16);
  const archivos = new Map();
  for (let n = 0; n < total; n++) {
    if (buf.readUInt32LE(p) !== 0x02014b50) throw new Error('Directorio central del ZIP dañado');
    const metodo = buf.readUInt16LE(p + 10);
    const tamComprimido = buf.readUInt32LE(p + 20);
    const largoNombre = buf.readUInt16LE(p + 28);
    const largoExtra = buf.readUInt16LE(p + 30);
    const largoComentario = buf.readUInt16LE(p + 32);
    const offsetLocal = buf.readUInt32LE(p + 42);
    const nombre = buf.toString('utf8', p + 46, p + 46 + largoNombre);
    const inicioDatos = offsetLocal + 30 + buf.readUInt16LE(offsetLocal + 26) + buf.readUInt16LE(offsetLocal + 28);
    const datos = buf.subarray(inicioDatos, inicioDatos + tamComprimido);
    archivos.set(nombre, metodo === 0 ? datos : zlib.inflateRawSync(datos));
    p += 46 + largoNombre + largoExtra + largoComentario;
  }
  return archivos;
}

function decodificarXml(texto) {
  return texto
    .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&apos;/g, "'")
    .replace(/&#x([0-9a-fA-F]+);/g, (_, h) => String.fromCodePoint(parseInt(h, 16)))
    .replace(/&#(\d+);/g, (_, d) => String.fromCodePoint(Number(d)))
    .replace(/&amp;/g, '&');
}

/** Une todos los <t> de un fragmento (texto simple o enriquecido). */
function textoDe(fragmento) {
  let salida = '';
  for (const m of fragmento.matchAll(/<t(?:\s[^>]*)?>([\s\S]*?)<\/t>/g)) salida += decodificarXml(m[1]);
  return salida;
}

/** Lee una hoja por nombre y devuelve { 'A1': 'texto', ... }. */
function leerHoja(rutaXlsx, nombreHoja) {
  const zip = leerZip(rutaXlsx);
  const leer = (n) => (zip.has(n) ? zip.get(n).toString('utf8') : null);

  const compartidas = [];
  const sst = leer('xl/sharedStrings.xml');
  if (sst) for (const m of sst.matchAll(/<si>([\s\S]*?)<\/si>/g)) compartidas.push(textoDe(m[1]));

  const libro = leer('xl/workbook.xml');
  const rels = leer('xl/_rels/workbook.xml.rels');
  const hojas = [...libro.matchAll(/<sheet\b[^>]*>/g)].map((m) => ({
    nombre: decodificarXml((m[0].match(/name="([^"]*)"/) || [])[1] || ''),
    rid: (m[0].match(/r:id="([^"]*)"/) || [])[1],
  }));
  const hoja = hojas.find((h) => h.nombre === nombreHoja);
  if (!hoja) throw new Error(`No existe la hoja "${nombreHoja}". Hojas: ${hojas.map((h) => h.nombre).join(', ')}`);
  const rel = [...rels.matchAll(/<Relationship\b[^>]*>/g)].map((m) => m[0]).find((r) => r.includes(`Id="${hoja.rid}"`));
  let destino = rel.match(/Target="([^"]*)"/)[1];
  destino = destino.startsWith('/') ? destino.slice(1) : path.posix.join('xl', destino);

  const xml = leer(destino);
  const celdas = {};
  for (const m of xml.matchAll(/<c\b([^>]*?)(?:\/>|>([\s\S]*?)<\/c>)/g)) {
    const atributos = m[1];
    const contenido = m[2] || '';
    const ref = (atributos.match(/\br="([A-Z]+\d+)"/) || [])[1];
    if (!ref) continue;
    const tipo = (atributos.match(/\bt="([^"]*)"/) || [])[1];
    const v = (contenido.match(/<v>([\s\S]*?)<\/v>/) || [])[1];
    let valor = '';
    if (tipo === 's' && v !== undefined) valor = compartidas[Number(v)];
    else if (tipo === 'inlineStr') valor = textoDe(contenido);
    else if (v !== undefined) valor = decodificarXml(v);
    celdas[ref] = valor == null ? '' : String(valor);
  }
  return celdas;
}

// ---------------------------------------------------------------------------
// (b) Esquema desde el SQL
// ---------------------------------------------------------------------------

const TIPOS_SQL = 'INT|BIGINT|SMALLINT|TINYINT|MEDIUMINT|VARCHAR|CHAR|TEXT|MEDIUMTEXT|LONGTEXT|DATE|DATETIME|TIMESTAMP|TIME|DECIMAL|FLOAT|DOUBLE|ENUM|JSON|BOOLEAN|BLOB';

/** Devuelve Map tabla -> Set(columnas) a partir de cada CREATE TABLE. */
function leerEsquema(rutaSql) {
  const sql = fs.readFileSync(rutaSql, 'utf8');
  const esquema = new Map();
  const reTabla = /CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\(([\s\S]*?)\)\s*ENGINE/gi;
  const reColumna = new RegExp(`^\\s*\`?([a-z_][a-z0-9_]*)\`?\\s+(?:${TIPOS_SQL})\\b`, 'i');
  const reservadas = /^(PRIMARY|KEY|UNIQUE|INDEX|CONSTRAINT|FOREIGN|CHECK|AND|OR|ON|REFERENCES)$/i;
  for (const m of sql.matchAll(reTabla)) {
    const columnas = new Set();
    for (const linea of m[2].split('\n')) {
      const c = linea.match(reColumna);
      if (c && !reservadas.test(c[1])) columnas.add(c[1]);
    }
    esquema.set(m[1], columnas);
  }
  return esquema;
}

// ---------------------------------------------------------------------------
// Reglas
// ---------------------------------------------------------------------------

function filasDeDatos(celdas) {
  const filas = [];
  const maxFila = Math.max(0, ...Object.keys(celdas).map((r) => Number(r.replace(/[A-Z]+/, ''))));
  for (let f = PRIMERA_FILA_DATOS; f <= maxFila; f++) {
    const fila = { numero: f };
    let vacia = true;
    for (const [clave, col] of Object.entries(COL)) {
      fila[clave] = (celdas[col + f] || '').trim();
      if (fila[clave] && clave !== 'modulo') vacia = false;
    }
    if (!vacia) filas.push(fila);
  }
  return filas;
}

/** (c) Cada "tabla: campo1, campo2; tabla2: campo" existe en el esquema. */
function reglaCamposExisten(filas, esquema) {
  const errores = [];
  for (const fila of filas) {
    const etiqueta = `fila ${fila.numero} (${fila.ri || 'sin número'})`;
    if (!fila.riDesc) { errores.push(`${etiqueta}: requisito de información vacío`); continue; }
    for (const segmentoBruto of fila.riDesc.split(';')) {
      const segmento = segmentoBruto.trim();
      if (!segmento) continue;
      const m = segmento.match(/^([a-z_][a-z0-9_]*)\s*:\s*(.+)$/);
      if (!m) { errores.push(`${etiqueta}: "${segmento}" no sigue el formato "tabla: campo1, campo2"`); continue; }
      const [, tabla, lista] = m;
      if (!esquema.has(tabla)) { errores.push(`${etiqueta}: la tabla "${tabla}" no existe`); continue; }
      for (const campoBruto of lista.split(',')) {
        const campo = campoBruto.trim();
        if (!/^[a-z_][a-z0-9_]*$/.test(campo)) errores.push(`${etiqueta}: "${tabla}: ${campo}" no es un nombre de campo válido`);
        else if (!esquema.get(tabla).has(campo)) errores.push(`${etiqueta}: el campo "${tabla}.${campo}" no existe`);
      }
    }
  }
  return errores;
}

/** Nombres de las rutinas y triggers del SQL (CREATE PROCEDURE / FUNCTION / TRIGGER). */
function rutinasSql(rutaSql) {
  const sql = fs.readFileSync(rutaSql, 'utf8');
  return new Set([...sql.matchAll(/CREATE\s+(?:PROCEDURE|FUNCTION|TRIGGER)\s+`?(\w+)`?/gi)].map((x) => x[1]));
}

/** (c) Cada procedimiento, función o trigger citado en la hoja (pr_..., fn_..., tr_...) existe en el SQL. */
function reglaRutinasExisten(celdas, rutaSql) {
  const rutinas = rutinasSql(rutaSql);
  const errores = [];
  for (const [ref, valor] of Object.entries(celdas)) {
    for (const m of valor.matchAll(/\b(?:pr|fn|tr)_[a-z0-9_]+\b/g)) {
      if (!rutinas.has(m[0])) errores.push(`celda ${ref}: la rutina «${m[0]}» no existe en el SQL`);
    }
  }
  return errores;
}

// Palabras prohibidas que sí son válidas en ciertos requisitos: «Enviada» es un
// valor real de notificaciones.estado_correo (RF-5.7), no un estado de la postulación.
const PERMITIDAS_EN = { Enviada: ['RF-5.7'] };

/** (d) Ninguna celda de la hoja contiene palabras prohibidas (sin distinguir mayúsculas). */
function reglaPalabrasProhibidas(celdas, filas) {
  const errores = [];
  const filaDe = (rf) => (filas.find((f) => f.rf === rf) || {}).numero;
  for (const [ref, valor] of Object.entries(celdas)) {
    const numeroFila = Number(ref.replace(/[A-Z]+/, ''));
    for (const palabra of PALABRAS_PROHIBIDAS) {
      if ((PERMITIDAS_EN[palabra] || []).some((rf) => filaDe(rf) === numeroFila)) continue;
      const escapada = palabra.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      const re = new RegExp(`(^|[^\\p{L}\\p{N}_])${escapada}(?=$|[^\\p{L}\\p{N}_])`, 'iu');
      if (re.test(valor)) errores.push(`celda ${ref}: contiene "${palabra}"`);
    }
  }
  return errores;
}

/** (e) RF-x.y, RN-x.y y RI-x.y con el mismo número en cada fila. */
function reglaNumeracion(filas) {
  const errores = [];
  const vistos = new Map();
  for (const fila of filas) {
    const rf = fila.rf.match(/^RF-(\d+\.\d+)$/);
    const rn = fila.rn.match(/^RN-(\d+\.\d+)$/);
    const ri = fila.ri.match(/^RI-(\d+\.\d+)$/);
    const f = `fila ${fila.numero}`;
    if (!rf) errores.push(`${f}: número de requisito funcional "${fila.rf}" no tiene la forma RF-x.y`);
    if (!rn) errores.push(`${f}: número de regla de negocio "${fila.rn}" no tiene la forma RN-x.y`);
    if (!ri) errores.push(`${f}: número de requisito de información "${fila.ri}" no tiene la forma RI-x.y`);
    if (rf && rn && rn[1] !== rf[1]) errores.push(`${f}: ${fila.rf} no coincide con ${fila.rn}`);
    if (rf && ri && ri[1] !== rf[1]) errores.push(`${f}: ${fila.rf} no coincide con ${fila.ri}`);
    if (rf) {
      if (vistos.has(rf[1])) errores.push(`${f}: ${fila.rf} repetido (ya está en la fila ${vistos.get(rf[1])})`);
      else vistos.set(rf[1], fila.numero);
    }
  }
  return errores;
}

/** (g) Estado del requisito: Obligatorio u Opcional. */
function reglaEstadoRequisito(filas) {
  return filas
    .filter((f) => !['Obligatorio', 'Opcional'].includes(f.estado))
    .map((f) => `fila ${f.numero} (${f.rf}): estado del requisito "${f.estado}" no es Obligatorio ni Opcional`);
}

// ---------------------------------------------------------------------------
// Reglas de contenido (h, i, j, k, l, m, n)
// ---------------------------------------------------------------------------

/** Minúsculas, sin tildes y con espacios simples, para comparar frases. */
function normalizar(texto) {
  return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, ' ');
}

/** Texto RF + RN de una fila, normalizado (null si la fila no existe). */
function textoRequisito(filas, rf) {
  const fila = filas.find((f) => f.rf === rf);
  return fila ? normalizar(fila.rfDesc + ' ' + fila.rnDesc) : null;
}

/** Revisa una lista de { rf, que, debe: [frases], noDebe: [frases] }. */
function revisarFrases(filas, lista) {
  const errores = [];
  for (const r of lista) {
    const texto = textoRequisito(filas, r.rf);
    if (texto === null) { errores.push(`${r.rf}: no existe (debería decir: ${r.que})`); continue; }
    for (const frase of r.debe || []) {
      if (!texto.includes(normalizar(frase))) errores.push(`${r.rf}: falta «${frase}» (${r.que})`);
    }
    for (const frase of r.noDebe || []) {
      if (texto.includes(normalizar(frase))) errores.push(`${r.rf}: no debe decir «${frase}» (${r.que})`);
    }
  }
  return errores;
}

// (h) Frases acordadas de las reglas de la base de datos (rondas 2 a 10 de
// base-de-datos/CAMBIOS.md) y decisiones de Samuel del 27/10/2026. Son frases
// copiadas a mano y revisadas contra el SQL; el verificador no lee el SQL aquí.
const REGLAS_BD = [
  { rf: 'RF-3.0', que: 'solo un Aprendiz se postula, a una convocatoria Activa y dentro de sus fechas',
    debe: ['solo un usuario con rol aprendiz', 'solo a convocatorias en estado activa',
      'dentro de sus fechas de inicio y cierre'] },
  { rf: 'RF-6.0', que: 'fechas y montos de los pagos',
    debe: ['la fecha de pago debe estar entre la fecha de inicio de la asignación y hoy',
      'la fecha programada', 'no puede ser anterior a la fecha de inicio de la asignación',
      'el monto debe ser mayor que cero', 'distinto del valor por pago', 'la observación es obligatoria'] },
  { rf: 'RF-4.4', que: 'trazabilidad, historial y auditoría no se modifican ni se borran',
    debe: ['no se pueden modificar ni borrar', 'el sistema rechaza'] },
  { rf: 'RF-4.2', que: 'los datos de validación los registra el sistema y no se cambian',
    debe: ['los registra el sistema', 'no se pueden cambiar', 'solo se corrige mientras la postulación está pendiente'] },
  { rf: 'RF-2.3', que: 'solo un administrador activo hace acciones de gestión',
    debe: ['administrador en estado activo', 'cambiar el rol o el estado de un usuario',
      'crear un usuario administrador', 'crear un usuario que no nace activo', 'registrar y anular pagos',
      'solo un administrador activo puede', 'crear o modificar convocatorias',
      'crear o modificar beneficios y catálogos (instituciones, programas, tipos de documento, entidades bancarias, eps y tipos de cuenta)',
      'fuera del mantenimiento de la base de datos con root, que igual debe justificar los cambios de cuentas',
      'las demás reglas y permisos de esta matriz los valida la aplicación'] },
  { rf: 'RF-1.0', que: 'crear un Administrador, o un usuario que no nace Activo, exige un administrador activo',
    debe: ['solo un administrador activo puede crear un usuario con rol administrador',
      'crear un usuario en otro estado también exige un administrador activo'] },
  { rf: 'RF-1.2', que: 'cambiar el rol exige un administrador activo',
    debe: ['solo un administrador activo puede cambiar el rol'] },
  { rf: 'RF-1.3', que: 'cambiar el estado exige un administrador activo',
    debe: ['solo un administrador activo puede cambiar el estado'],
    noDebe: ['hasta que el administrador lo reactive'] },
  { rf: 'RF-7.1', que: 'los cupos no bajan de las postulaciones aprobadas',
    debe: ['los cupos no pueden quedar por debajo del número de postulaciones aprobadas'] },
  // Novena ronda (CAMBIOS.md §13 y §13.1, spec D9): lo que se crea nace limpio.
  { rf: 'RF-7.1', que: 'crear convocatorias exige administrador activo y nacen Activa o Inactiva (novena ronda)',
    debe: ['solo un administrador activo puede crear una convocatoria',
      'nace activa o inactiva (por defecto, inactiva)'],
    noDebe: ['la convocatoria se crea inactiva'] },
  { rf: 'RF-3.1', que: 'un documento nuevo nace Pendiente y sin datos de validación (novena ronda)',
    debe: ['un documento nuevo debe empezar en estado pendiente y sin datos de validación',
      'la fecha de carga la pone el sistema'] },
  { rf: 'RF-4.1', que: 'un documento nuevo no trae datos de validación (novena ronda)',
    debe: ['si los trae, el sistema los deja vacíos'] },
  { rf: 'RF-4.5', que: 'una solicitud nueva nace Pendiente; autor y fecha los pone el sistema (novena ronda)',
    debe: ['una solicitud nueva debe empezar en estado pendiente', 'el autor y la fecha de la solicitud los pone el sistema'] },
  { rf: 'RF-6.0', que: 'el autor y la fecha del pago los pone el sistema (novena ronda)',
    debe: ['quién registró el pago y cuándo lo pone el sistema'] },
  { rf: 'RF-9.0', que: 'el autor y la fecha de la novedad los pone el sistema (novena ronda)',
    debe: ['la fecha y el usuario que la registró los pone el sistema'] },
  { rf: 'RF-1.2', que: 'pr_cambiar_rol_usuario da error si el rol no existe o no cambia nada (novena ronda)',
    debe: ['da error si el rol no existe o no cambia nada'] },
  { rf: 'RF-1.3', que: 'pr_cambiar_estado_usuario da error si el estado no es válido o no cambia nada (novena ronda)',
    debe: ['da error si el estado no es válido o no cambia nada'] },
  { rf: 'RF-8.6', que: 'el motivo de la cancelación es distinto del anterior',
    debe: ['el motivo es obligatorio y debe ser distinto del motivo anterior'] },
  { rf: 'RF-5.7', que: 'estado_correo tiene Pendiente, Enviada y Error',
    debe: ['enviada cuando se envía'] },
  { rf: 'RF-1.3', que: 'excepción del mantenimiento', debe: ['(salvo mantenimiento, rf-2.3)'] },
  { rf: 'RF-2.0', que: 'excepción del mantenimiento y desbloqueo al volver a intentar entrar',
    debe: ['(salvo mantenimiento, rf-2.3)', 'cuando el usuario vuelve a intentar entrar'] },
  // Reglas que la base de datos no comprueba: se marcan como de la aplicación
  // (revisado contra el SQL de la novena ronda).
  { rf: 'RF-1.2', que: 'el documento no se modifica: lo valida la aplicación',
    debe: ['el número de documento no se puede modificar (lo valida la aplicación)'] },
  { rf: 'RF-3.1', que: 'formato del archivo y tipo Activo: lo valida la aplicación',
    debe: ['el formato (pdf, jpg o png) y que el tipo de documento esté en estado activo los valida la aplicación'] },
  { rf: 'RF-3.3', que: 'el tipo Documento adicional: lo valida la aplicación',
    debe: ['que el documento se cargue con el tipo documento adicional (lo valida la aplicación)'] },
  { rf: 'RF-7.0', que: 'beneficio Inactivo sin convocatorias nuevas: lo valida la aplicación',
    debe: ['un beneficio inactivo no admite convocatorias nuevas (lo valida la aplicación)'] },
  // Décima ronda (CAMBIOS.md §14, D18; ya está en el SQL desde el rebase sobre
  // la base de datos final).
  { rf: 'RF-7.1', que: 'cualquier cambio de una convocatoria lo hace solo un administrador activo (décima ronda)',
    debe: ['cualquier cambio de una convocatoria (valor, número de pagos, duración, fechas, cupos o estado) lo hace solo un administrador activo'] },
  { rf: 'RF-7.2', que: 'quién cerró y cuándo lo anota el sistema al cerrar (décima ronda)',
    debe: ['al cerrar, el sistema anota quién la cerró y la fecha real de cierre'],
    noDebe: ['lo valida la aplicación'] },
  { rf: 'RF-7.0', que: 'crear o modificar beneficios exige administrador activo (décima ronda)',
    debe: ['solo un administrador activo puede crear o modificar beneficios'] },
  { rf: 'RF-3.1', que: 'reemplazo de documentos (décima ronda)',
    debe: ['un documento validado no se puede reemplazar',
      'al reemplazarlo vuelve a pendiente y pierde los datos de validación'] },
  { rf: 'RF-4.1', que: 'las observaciones del documento solo las escribe un administrador (décima ronda)',
    debe: ['las observaciones del documento solo las escribe un administrador'] },
  { rf: 'RF-3.3', que: 'cambios de una solicitud de documentación (décima ronda)',
    debe: ['una solicitud solo pasa de pendiente a atendida o cerrada', 'sus datos de origen no se modifican',
      'la fecha de atención la pone el sistema'] },
  { rf: 'RF-4.5', que: 'cerrar una solicitud exige administrador activo (décima ronda)',
    debe: ['solo un administrador activo puede cerrar la solicitud'] },
  { rf: 'RF-8.3', que: 'condiciones congeladas en cuanto hay pagos o deja de estar En curso',
    debe: ['solo mientras la asignación está en curso y no tiene ningún pago', 'ni siquiera uno anulado',
      'la duración y la fecha de inicio quedan congelados'],
    noDebe: ['no puede quedar por debajo de los pagos ya registrados'] },
  { rf: 'RF-8.2', que: 'fecha_inicio entre el inicio de la convocatoria y hoy + 1 año',
    debe: ['entre la fecha de inicio de la convocatoria y un año después de hoy'] },
  { rf: 'RF-8.2', que: 'la asignación empieza en GREATEST(CURDATE(), fecha de inicio de la convocatoria)',
    debe: ['empieza el día de la aprobación o, si la convocatoria aún no ha empezado, el día de inicio de la convocatoria'],
    noDebe: ['igual a la fecha de aprobación'] },
  { rf: 'RF-8.3', que: 'fecha_inicio entre el inicio de la convocatoria y hoy + 1 año',
    debe: ['la nueva fecha de inicio debe estar entre la fecha de inicio de la convocatoria y un año después de hoy'] },
  { rf: 'RF-2.0', que: 'bloqueo tras 3 intentos y desbloqueo automático (decisión del 27/10/2026)',
    debe: ['3 intentos fallidos', 'se desbloquea sola', 'un administrador activo también puede desbloquearla antes'],
    noDebe: ['hasta que el administrador lo reactive', 'temporalmente'] },
  { rf: 'RF-3.2', que: 'el certificado de EPS es obligatorio para enviar y lo valida la aplicación (decisión del 27/10/2026)',
    debe: ['no se puede enviar sin haber cargado el documento de identidad, el certificado bancario y el certificado de eps',
      'esto lo valida la aplicación, no la base de datos'] },
];

/** (h) Las reglas de la base de datos están en el requisito que corresponde. */
function reglaReglasBaseDatos(filas) {
  return revisarFrases(filas, REGLAS_BD);
}

/** Nombres de la semilla INSERT INTO tipos_documento del SQL. */
function tiposDocumentoSql(rutaSql) {
  const sql = fs.readFileSync(rutaSql, 'utf8');
  const m = sql.match(/INSERT\s+INTO\s+tipos_documento\b[^;]*;/i);
  if (!m) return [];
  return [...m[0].matchAll(/\(\s*\d+\s*,\s*'([^']+)'/g)].map((x) => x[1]);
}

/** (i) Cada tipo de documento del SQL aparece en RF-3.1 / RN-3.1. */
function reglaTiposDocumento(filas, rutaSql) {
  const tipos = tiposDocumentoSql(rutaSql);
  if (tipos.length === 0) return ['no se encontró la semilla de tipos_documento en el SQL'];
  const texto = textoRequisito(filas, 'RF-3.1');
  if (texto === null) return ['RF-3.1 no existe'];
  return tipos.filter((t) => !texto.includes(normalizar(t)))
    .map((t) => `RF-3.1: no menciona el tipo de documento «${t}», que existe en el SQL`);
}

/**
 * Minutos de desbloqueo que usa el SQL: todas las apariciones de
 * "INTERVAL n MINUTE" y de "n minutos" dentro del código (sin comentarios)
 * del bloque de usuarios (desde "-- Usuarios" hasta "-- Documentos de la
 * postulación"). Así no depende de en qué trigger o función esté el cálculo.
 */
function minutosDesbloqueoSql(rutaSql) {
  const sql = fs.readFileSync(rutaSql, 'utf8');
  const bloque = sql.match(/^-- Usuarios\s*$([\s\S]*?)^-- Documentos de la postulaci/m);
  if (!bloque) return { error: 'no se encontró el bloque de usuarios («-- Usuarios» … «-- Documentos de la postulación») en el SQL' };
  const codigo = bloque[1].split('\n').map((l) => l.replace(/--.*$/, '')).join('\n');
  const valores = [
    ...[...codigo.matchAll(/INTERVAL\s+(\d+)\s+MINUTE/gi)].map((x) => x[1]),
    ...[...codigo.matchAll(/(?<![0-9])(\d+) minutos/gi)].map((x) => x[1]),
  ];
  if (valores.length === 0) return { error: 'no se encontró ningún INTERVAL ... MINUTE en el bloque de usuarios del SQL' };
  return { valores };
}

/** (j) RF-2.0 y RF-1.3 dicen los mismos minutos de desbloqueo que el SQL, y el SQL es coherente. */
function reglaMinutosDesbloqueo(filas, rutaSql) {
  const leido = minutosDesbloqueoSql(rutaSql);
  if (leido.error) return [leido.error];
  const distintos = [...new Set(leido.valores)];
  if (distintos.length > 1) {
    return [`el SQL usa minutos distintos en el bloque de usuarios: ${distintos.join(', ')} (deberían ser iguales)`];
  }
  const m = [null, distintos[0]];
  const frase = `${m[1]} minutos`;
  const errores = [];
  for (const rf of ['RF-2.0', 'RF-1.3']) {
    const texto = textoRequisito(filas, rf);
    if (texto === null) { errores.push(`${rf} no existe`); continue; }
    // Número completo: «115 minutos» no cuenta como «15 minutos».
    const minutos = [...texto.matchAll(/(?<![0-9])(\d+) minutos/g)].map((x) => x[1]);
    if (!minutos.includes(m[1])) errores.push(`${rf}: debe decir «${frase}» (desbloqueo automático del SQL)`);
    for (const otro of minutos.filter((x) => x !== m[1])) {
      errores.push(`${rf}: dice «${otro} minutos», pero el SQL desbloquea a los ${m[1]}`);
    }
  }
  return errores;
}

const FRASES_ABIERTAS = ['pregunta abierta', 'por confirmar', 'por definir', 'hay que definir', 'sin definir',
  'pendiente de confirmar', 'a confirmar', 'si se quiere'];

/** Texto de la sección 7 de CAMBIOS_MATRIZ.md (decisiones y preguntas), o null si no está. */
function seccionDecisiones(rutaMd) {
  if (!fs.existsSync(rutaMd)) return null;
  const md = fs.readFileSync(rutaMd, 'utf8');
  const m = md.match(/^## 7\.[^\n]*\n([\s\S]*?)(?=^## \d)/m);
  return m ? m[1] : null;
}

/** (k) Ni la hoja ni la sección 7 de CAMBIOS_MATRIZ.md dejan temas abiertos. */
function reglaSinPreguntasAbiertas(celdas, rutaMd) {
  const errores = [];
  for (const [ref, valor] of Object.entries(celdas)) {
    const texto = normalizar(valor);
    for (const frase of FRASES_ABIERTAS) if (texto.includes(frase)) errores.push(`celda ${ref}: contiene «${frase}»`);
  }
  const seccion = seccionDecisiones(rutaMd);
  if (seccion === null) {
    errores.push(`no se encontró la sección 7 de ${path.basename(rutaMd)}`);
  } else {
    const texto = normalizar(seccion.replace(/~~[\s\S]*?~~/g, ''));  // lo tachado ya está cerrado
    for (const frase of FRASES_ABIERTAS) {
      if (texto.includes(frase)) errores.push(`${path.basename(rutaMd)} §7: contiene «${frase}»`);
    }
  }
  return errores;
}

// (l) Frases acordadas de quién bloquea y quién desbloquea una cuenta
// (copiadas a mano de las rondas 4 a 6 y de la octava ronda de CAMBIOS.md,
// decisión D13 de la spec; no leídas del SQL).
const FRASES_BLOQUEO_EQUIVOCADAS = ['hasta que el administrador lo reactive', 'hasta que un administrador la desbloquee',
  'bloqueo permanente', 'bloqueada de forma permanente'];
const REGLAS_BLOQUEO = [
  { rf: 'RF-2.0', que: 'solo una cuenta Activa se bloquea y la fecha la pone el sistema',
    debe: ['solo una cuenta activa puede quedar bloqueada por intentos fallidos',
      'la fecha de bloqueo la pone el sistema en el momento del bloqueo'],
    noDebe: FRASES_BLOQUEO_EQUIVOCADAS },
  { rf: 'RF-2.0', que: 'el bloqueo y el desbloqueo automáticos solo los hace el sistema (ronda 8)',
    debe: ['el bloqueo automático solo lo hace el sistema durante el inicio de sesión (sin usuario de sesión) y solo tras 3 intentos fallidos',
      'cualquier otro bloqueo lo hace un administrador activo con justificación',
      'el desbloqueo automático solo lo hace el sistema, pasados 15 minutos'] },
  { rf: 'RF-1.3', que: 'una cuenta Inactiva solo la reactiva un administrador activo',
    debe: ['una cuenta inactiva solo la reactiva un administrador activo',
      'no se reactiva sola ni pasando por bloqueado'],
    noDebe: FRASES_BLOQUEO_EQUIVOCADAS },
  { rf: 'RF-1.3', que: 'un administrador activo desbloquea en cualquier momento',
    debe: ['un administrador activo puede desbloquear una cuenta en cualquier momento'] },
];

/** (l) El bloqueo de la cuenta está explicado sin huecos. */
function reglaBloqueoCuenta(filas) {
  return revisarFrases(filas, REGLAS_BLOQUEO);
}

// (m) Frases acordadas de la auditoría de cuentas y migraciones (séptima y octava ronda de
// CAMBIOS.md y decisiones D13 a D17 de la spec: tr_usuario_auditar, @app_justificacion,
// pr_cambiar_estado_usuario, pr_cambiar_rol_usuario y triggers de migraciones).
// Copiadas a mano, no leídas del SQL.
const REGLAS_AUDITORIA = [
  { rf: 'RF-1.2', que: 'la base audita el cambio de rol, no los demás datos del usuario',
    debe: ['todo cambio de rol queda en la auditoría con quién lo hizo, cuándo, el rol anterior y el nuevo',
      'la base de datos no anota en la auditoría los cambios de los demás datos del usuario'],
    noDebe: ['cada cambio queda en auditoría'] },
  { rf: 'RF-1.2', que: 'el cambio de rol también exige justificación (octava ronda, D15)',
    debe: ['el cambio de rol también exige una justificación, que queda en la auditoría',
      'pr_cambiar_rol_usuario'] },
  { rf: 'RF-1.3', que: 'todo cambio de estado queda en auditoría; justificación obligatoria si lo hace una persona',
    debe: ['todo cambio de estado queda en la auditoría con quién lo hizo, cuándo, el estado anterior y el nuevo y la justificación',
      'si el cambio lo hace una persona, la justificación es obligatoria y sin ella el sistema rechaza el cambio',
      'el bloqueo por intentos fallidos y el desbloqueo automático quedan registrados como hechos por el sistema',
      'cada cambio de estado lleva su propia justificación', 'pr_cambiar_estado_usuario'],
    noDebe: ['el cambio exige una justificación que se guarda en auditoría'] },
  { rf: 'RF-2.0', que: 'el bloqueo y el desbloqueo automáticos quedan en auditoría como hechos por el sistema, sin autor',
    debe: ['el bloqueo y el desbloqueo automáticos quedan en la auditoría como hechos por el sistema, sin autor'] },
  { rf: 'RF-4.3', que: 'las migraciones dejan huella y, ya terminadas, no se modifican (ronda 8)',
    debe: ['cada carga queda en la auditoría al iniciarse o registrarse y en cada cambio de su estado (por ejemplo, al terminar)',
      'actualizar solo los contadores mientras sigue en proceso no se anota',
      'una migración terminada (que ya no está en proceso) no se puede modificar',
      'las fechas de inicio y de fin de la carga las pone el sistema'] },
  { rf: 'RF-4.4', que: 'qué anota la base en la auditoría y quién queda registrado',
    debe: ['los cambios de rol o de estado de los usuarios', 'las migraciones desde excel',
      'el propio aprendiz cuando crea o corrige su postulación',
      'el administrador cuando valida, aprueba o rechaza',
      'la fecha de la auditoría la pone el sistema y no se puede alterar',
      'la auditoría de las cuentas distingue tres orígenes',
      'sistema (cambio automático del inicio de sesión)',
      'mantenimiento (root sin usuario de sesión)',
      'usuario (una persona con sesión)',
      'el usuario vacío solo significa «el sistema» cuando el origen es sistema'],
    noDebe: ['el aprendiz dueño de la postulación', 'cada cambio de estado, validación, aprobación, pago y novedad',
      'si no se conoce el usuario, el campo queda vacío'] },
];

/** (m) La auditoría de cuentas y migraciones está descrita como la hace la base. */
function reglaAuditoriaCuentas(filas) {
  return revisarFrases(filas, REGLAS_AUDITORIA);
}

// (n) Frases acordadas de la ronda 11 (CAMBIOS.md §15 y decisión D19 de la spec):
// nadie toca los datos de otra persona. Copiadas a mano y revisadas contra los
// triggers de la ronda 11; el verificador no lee el SQL aquí.
const REGLAS_RONDA11 = [
  { rf: 'RF-3.0', que: 'la cuenta bancaria la gestiona su titular o un administrador y no se traspasa',
    debe: ['la cuenta bancaria solo la registra o la cambia su titular o un administrador activo',
      'no se pasa a otro usuario'] },
  { rf: 'RF-1.2', que: 'cada quien cambia sus datos de cuenta; los de otro, solo un administrador',
    debe: ['cada usuario cambia sus propios datos de cuenta',
      'los de otro usuario, y sus intentos fallidos, solo un administrador activo',
      'un administrador no puede cambiar la contraseña ni el correo de otro administrador'] },
  { rf: 'RF-2.2', que: 'recuperación de contraseña con el dueño del token como usuario de sesión',
    debe: ['sin usuario de sesión, la aplicación solo puede cambiar los intentos fallidos, el bloqueo y el desbloqueo',
      'la aplicación fija como usuario de sesión al dueño del token'] },
  { rf: 'RF-3.2', que: 'el aprendiz postula solo a su nombre; un administrador puede registrarla por él',
    debe: ['el aprendiz solo crea postulaciones a su nombre',
      'un administrador activo puede registrarla a nombre de un aprendiz'] },
  { rf: 'RF-4.2', que: 'formulario y observaciones: el aprendiz dueño o un administrador',
    debe: ['el formulario y las observaciones de una postulación solo los cambia el aprendiz dueño o un administrador activo'] },
  { rf: 'RF-3.1', que: 'documentos solo a la postulación propia (o un administrador)',
    debe: ['solo el aprendiz dueño de la postulación o un administrador activo puede subirle documentos'] },
  { rf: 'RF-5.6', que: 'quién crea, marca como leída y cambia una notificación',
    debe: ['las notificaciones las crea solo el sistema o un administrador activo',
      'solo su titular o un administrador activo puede marcarla como leída',
      'el texto de la notificación solo lo cambia un administrador activo'] },
  { rf: 'RF-5.7', que: 'el estado del correo y la fecha de envío solo los cambia un administrador',
    debe: ['el estado del correo y la fecha de envío solo los cambia un administrador activo'] },
  { rf: 'RF-4.3', que: 'migraciones: solo un administrador; autor de la sesión y fijo',
    debe: ['solo un administrador activo puede registrar o modificar una carga',
      'la carga queda a nombre del usuario de sesión',
      'quién hizo la carga no se puede cambiar'] },
  { rf: 'RF-2.3', que: 'resumen de la ronda 11 en los permisos',
    debe: ['nadie toca los datos de otra persona', 'registrar o modificar migraciones desde excel'],
    noDebe: ['el aprendiz sí puede crear su postulación y corregir su formulario mientras está pendiente'] },
];

/** (n) Las reglas de la ronda 11 están en el requisito que corresponde. */
function reglaRonda11(filas) {
  return revisarFrases(filas, REGLAS_RONDA11);
}

// ---------------------------------------------------------------------------
// (f) Informe
// ---------------------------------------------------------------------------

function main() {
  console.log(`Matriz:  ${RUTA_XLSX}`);
  console.log(`Esquema: ${RUTA_SQL}`);
  const esquema = leerEsquema(RUTA_SQL);
  const totalColumnas = [...esquema.values()].reduce((s, c) => s + c.size, 0);
  console.log(`Esquema leído: ${esquema.size} tablas, ${totalColumnas} columnas.`);
  const celdas = leerHoja(RUTA_XLSX, HOJA_MATRIZ);
  const filas = filasDeDatos(celdas);
  console.log(`Requisitos leídos en la hoja "${HOJA_MATRIZ}": ${filas.length}\n`);

  const reglas = [
    ['(c) Campos y rutinas citados existen en benefitrack_v2.sql',
      [...reglaCamposExisten(filas, esquema), ...reglaRutinasExisten(celdas, RUTA_SQL)]],
    ['(d) Sin palabras prohibidas', reglaPalabrasProhibidas(celdas, filas)],
    ['(e) Numeración RF/RN/RI coincide', reglaNumeracion(filas)],
    ['(g) Estado del requisito válido', reglaEstadoRequisito(filas)],
    ['(h) Frases acordadas de las reglas de la BD presentes (no lee el SQL)', reglaReglasBaseDatos(filas)],
    ['(i) Tipos de documento del SQL en RF-3.1', reglaTiposDocumento(filas, RUTA_SQL)],
    ['(j) Minutos de desbloqueo iguales al SQL', reglaMinutosDesbloqueo(filas, RUTA_SQL)],
    ['(k) Sin preguntas abiertas (hoja y §7 de CAMBIOS_MATRIZ.md)', reglaSinPreguntasAbiertas(celdas, RUTA_CAMBIOS_MATRIZ)],
    ['(l) Frases acordadas del bloqueo de la cuenta presentes (no lee el SQL)', reglaBloqueoCuenta(filas)],
    ['(m) Frases acordadas de la auditoría de cuentas y migraciones presentes (no lee el SQL)', reglaAuditoriaCuentas(filas)],
    ['(n) Frases acordadas de la ronda 11 (nadie toca los datos de otra persona) presentes (no lee el SQL)', reglaRonda11(filas)],
  ];

  let fallidas = 0;
  for (const [nombre, errores] of reglas) {
    if (errores.length === 0) {
      console.log(`${VERDE}OK${NORMAL}     ${nombre}`);
    } else {
      fallidas++;
      console.log(`${ROJO}FALLO${NORMAL}  ${nombre} (${errores.length} problemas)`);
      for (const e of errores) console.log(`         - ${e}`);
    }
  }
  const color = fallidas ? ROJO : VERDE;
  console.log(`\n${color}TOTAL: ${reglas.length - fallidas} de ${reglas.length} reglas OK, ${fallidas} con FALLO${NORMAL}`);
  process.exitCode = fallidas ? 1 : 0;
}

try {
  main();
} catch (error) {
  console.error(`${ROJO}ERROR:${NORMAL} ${error.message}`);
  process.exitCode = 2;
}
