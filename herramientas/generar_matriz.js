'use strict';
const ExcelJS = require('exceljs');
const { modulos, cambios } = require('./datos_matriz');

const SALIDA = process.argv[2];
if (!SALIDA) {
  console.error('Falta la ruta de salida del Excel.\n'
    + 'Uso: node herramientas/generar_matriz.js <ruta_salida.xlsx>\n'
    + 'Ejemplo: node herramientas/generar_matriz.js entregables/Matriz_Requisitos_v2.xlsx');
  process.exit(1);
}
const FUENTE = 'Arial';
const AZUL = 'FF1F4E79';
const AZUL_CLARO = 'FFDDEBF7';
const GRIS_BORDE = 'FF808080';
const VERDE_NUEVO = 'FFE2EFDA';

const borde = { style: 'thin', color: { argb: GRIS_BORDE } };
const bordes = { top: borde, left: borde, bottom: borde, right: borde };

function estiloEncabezado(celda) {
  celda.font = { name: FUENTE, size: 10, bold: true, color: { argb: 'FFFFFFFF' } };
  celda.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: AZUL } };
  celda.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
  celda.border = bordes;
}

(async () => {
  const wb = new ExcelJS.Workbook();
  wb.creator = 'Samuel David Hurtado Arango';
  wb.created = new Date('2026-10-26T12:00:00');

  // ---------------- Hoja 1: matriz ----------------
  const ws = wb.addWorksheet('Matriz de Requisitos', {
    views: [{ state: 'frozen', xSplit: 3, ySplit: 2, topLeftCell: 'D3', activeCell: 'D3' }],
  });
  ws.columns = [
    { width: 20 }, { width: 24 }, { width: 16 }, { width: 10 }, { width: 46 },
    { width: 10 }, { width: 60 }, { width: 10 }, { width: 58 }, { width: 14 },
  ];
  ws.getRow(1).values = ['Módulo', 'Subprocesos', 'ROL', 'REQUISITO FUNCIONAL', null, 'REGLAS DEL NEGOCIO', null, 'REQUISITOS DE INFORMACION', null, 'Estado del requisito'];
  ws.getRow(2).values = ['Módulo', 'Subprocesos', 'ROL', 'Nro.', 'Descripción', 'Nro.', 'Descripción', 'Nro.', 'Descripción', 'Estado del requisito'];
  for (const r of [1, 2]) {
    ws.getRow(r).height = r === 1 ? 22 : 20;
    for (let c = 1; c <= 10; c++) estiloEncabezado(ws.getRow(r).getCell(c));
  }
  ['A1:A2', 'B1:B2', 'C1:C2', 'D1:E1', 'F1:G1', 'H1:I1', 'J1:J2'].forEach((m) => ws.mergeCells(m));

  let fila = 3;
  for (const modulo of modulos) {
    const inicio = fila;
    for (const [num, , sub, rol, rf, rn, ri, estado, nuevo] of modulo.requisitos) {
      const row = ws.getRow(fila);
      row.values = [modulo.nombre, sub, rol, `RF-${num}`, rf, `RN-${num}`, rn, `RI-${num}`, ri, estado];
      for (let c = 1; c <= 10; c++) {
        const celda = row.getCell(c);
        celda.font = { name: FUENTE, size: 10 };
        celda.border = bordes;
        celda.alignment = { vertical: 'top', wrapText: true, horizontal: [4, 6, 8, 10].includes(c) ? 'center' : 'left' };
      }
      row.getCell(1).font = { name: FUENTE, size: 10, bold: true, color: { argb: AZUL } };
      row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: AZUL_CLARO } };
      row.getCell(1).alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
      [4, 6, 8].forEach((c) => { row.getCell(c).font = { name: FUENTE, size: 10, bold: true }; });
      if (nuevo) row.getCell(4).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: VERDE_NUEVO } };
      row.getCell(10).dataValidation = { type: 'list', allowBlank: false, formulae: ['"Obligatorio,Opcional"'] };
      const largo = Math.max(rf.length / 44, rn.length / 58, ri.length / 56);
      row.height = Math.max(30, Math.ceil(largo + 1) * 13);
      fila++;
    }
    if (fila - 1 > inicio) ws.mergeCells(`A${inicio}:A${fila - 1}`);
  }
  const ultimaFila = fila - 1;
  ws.autoFilter = { from: 'A2', to: `J${ultimaFila}` };

  ws.pageSetup = { orientation: 'landscape', paperSize: 9, fitToPage: true, fitToWidth: 1, fitToHeight: 0, printTitlesRow: '1:2' };

  // ---------------- Hoja 2: control de cambios ----------------
  const cc = wb.addWorksheet('Control de cambios', {
    views: [{ state: 'frozen', ySplit: 1, topLeftCell: 'A2', activeCell: 'A2' }],
  });
  cc.columns = [
    { header: 'Nro.', width: 7 },
    { header: 'Fecha', width: 12 },
    { header: 'Responsable', width: 30 },
    { header: 'Requisito', width: 16 },
    { header: 'Tipo de cambio', width: 14 },
    { header: 'Descripción del cambio', width: 80 },
    { header: 'Decisión', width: 11 },
  ];
  cc.getRow(1).height = 22;
  for (let c = 1; c <= 7; c++) estiloEncabezado(cc.getRow(1).getCell(c));
  cambios.forEach(([req, tipo, desc, dec, fecha = '26/10/2026'], i) => {
    const row = cc.getRow(i + 2);
    row.values = [i + 1, fecha, 'Samuel David Hurtado Arango', req, tipo, desc, dec];
    for (let c = 1; c <= 7; c++) {
      const celda = row.getCell(c);
      celda.font = { name: FUENTE, size: 10 };
      celda.border = bordes;
      celda.alignment = { vertical: 'top', wrapText: true, horizontal: [1, 2, 5, 7].includes(c) ? 'center' : 'left' };
    }
    if (tipo === 'Nuevo') row.getCell(5).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: VERDE_NUEVO } };
    row.height = Math.max(18, Math.ceil(desc.length / 78 + 0.3) * 13 + 4);
  });
  cc.autoFilter = { from: 'A1', to: `G${cambios.length + 1}` };

  await wb.xlsx.writeFile(SALIDA);
  const total = modulos.reduce((s, m) => s + m.requisitos.length, 0);
  console.log(`Escrito ${SALIDA}: ${modulos.length} módulos, ${total} requisitos, ${cambios.length} cambios.`);
})().catch((e) => { console.error(e); process.exit(1); });
