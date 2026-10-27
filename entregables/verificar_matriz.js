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
 *   (c) cada "tabla: campo" citado en Requisitos de información existe en el SQL;
 *   (d) no aparecen palabras prohibidas (OCR, Estudiante, estados eliminados);
 *   (e) en cada fila coinciden los números RF-x.y, RN-x.y y RI-x.y;
 *   (g) el estado del requisito es "Obligatorio" u "Opcional".
 * Termina con código 1 si alguna regla falla.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const RAIZ = path.resolve(__dirname, '..');
const RUTA_XLSX = process.argv[2] || path.join(RAIZ, 'entregables', 'Matriz_Requisitos_v2.xlsx');
const RUTA_SQL = process.argv[3] || path.join(RAIZ, 'base-de-datos', 'benefitrack_v2.sql');
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

/** (d) Ninguna celda de la hoja contiene palabras prohibidas (sin distinguir mayúsculas). */
function reglaPalabrasProhibidas(celdas) {
  const errores = [];
  for (const [ref, valor] of Object.entries(celdas)) {
    for (const palabra of PALABRAS_PROHIBIDAS) {
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
    ['(c) Campos citados existen en benefitrack_v2.sql', reglaCamposExisten(filas, esquema)],
    ['(d) Sin palabras prohibidas', reglaPalabrasProhibidas(celdas)],
    ['(e) Numeración RF/RN/RI coincide', reglaNumeracion(filas)],
    ['(g) Estado del requisito válido', reglaEstadoRequisito(filas)],
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
