const fs = require('fs');
const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
  WidthType, ShadingType, AlignmentType, LevelFormat, BorderStyle,
} = require('docx');

const W = 9360; // ancho útil carta con márgenes de 1"
const FONT = 'Arial';
const VERDE = 'D9EAD3';

const run = (text, opts = {}) => new TextRun({ text, font: FONT, size: 20, ...opts });
const p = (text, opts = {}) => new Paragraph({ spacing: { after: 80 }, ...opts, children: Array.isArray(text) ? text : [run(text)] });
const label = (l, v) => p([run(l, { bold: true }), run(v)]);
const bullet = (text, level = 0) => new Paragraph({ numbering: { reference: 'vinetas', level }, spacing: { after: 40 }, children: Array.isArray(text) ? text : [run(text)] });
const numItem = (ref, title, text) => new Paragraph({ numbering: { reference: ref, level: 0 }, spacing: { after: 60 }, children: [run(title + ': ', { bold: true }), run(text)] });
const sub = (text) => p([run(text, { bold: true, underline: {} })], { spacing: { before: 120, after: 60 } });

const cell = (children, width, opts = {}) => new TableCell({
  width: { size: width, type: WidthType.DXA },
  margins: { top: 80, bottom: 80, left: 120, right: 120 },
  shading: opts.fill ? { type: ShadingType.CLEAR, color: 'auto', fill: opts.fill } : undefined,
  columnSpan: opts.span,
  children: Array.isArray(children) ? children : [p(children)],
});

// Tabla de una columna con encabezado sombreado
const section = (title, children) => new Table({
  width: { size: W, type: WidthType.DXA },
  columnWidths: [W],
  rows: [
    new TableRow({ children: [cell([p([run(title, { bold: true })], { alignment: AlignmentType.CENTER })], W, { fill: VERDE })] }),
    new TableRow({ children: [cell(children, W)] }),
  ],
});

const titleBar = (title) => new Table({ width: { size: W, type: WidthType.DXA }, columnWidths: [W], rows: [new TableRow({ children: [cell([p([run(title, { bold: true })], { alignment: AlignmentType.CENTER })], W, { fill: VERDE })] })] });

const gap = () => new Paragraph({ spacing: { after: 160 }, children: [] });

const grid = (widths, rows, headerFill = true) => new Table({
  width: { size: widths.reduce((a, b) => a + b, 0), type: WidthType.DXA },
  columnWidths: widths,
  rows: rows.map((r, i) => new TableRow({
    children: r.map((c, j) => cell([p(typeof c === 'string' ? [run(c, { bold: i === 0 && headerFill })] : c, { alignment: i === 0 && headerFill ? AlignmentType.CENTER : AlignmentType.LEFT })], widths[j], { fill: i === 0 && headerFill ? VERDE : undefined })),
  })),
});

// ---------- Contenido ----------

const informacionGeneral = section('INFORMACIÓN GENERAL', [
  label('Nombre de la aplicación: ', 'BenefitRack'),
  label('Nombre de la entidad o Centro del SENA con el que se asocia (cliente): ', 'SENA – Centro Textil y de Gestión Industrial, Sede Antioquia (Bienestar al Aprendiz).'),
  label('Programa de formación con el que se asocia: ', 'No aplica'),
  label('Nro. Ficha: ', '3425429'),
  label('Instructor titular: ', 'Héctor Darío Maya'),
  label('Programa de formación: ', 'Tecnología en Análisis y Desarrollo de Software (ADSO)'),
]);

const aprendices = grid([2000, 2900, 2960, 1500], [
  ['Documento de identidad', 'Nombres y apellidos', 'Correo electrónico', 'Teléfono'],
  ['1023635729', 'Samuel David Hurtado Arango', 'samuel_dhurtado1@soy.sena.edu.co', '3159179271'],
  ['1033184891', 'Santiago Cardona Montes', 'santiagocardonamontes72@gmail.com', '3137785265'],
  ['1018241865', 'Mariana Ospina Rodríguez', 'mariana_ospinaro@soy.sena.edu.co', '3003327975'],
  ['1011512803', 'Dylan Andrés Cardona', 'dylan_acardona@soy.sena.edu.co', '3005003963'],
]);

const planteamiento = [
  p('En el SENA, Centro Textil y de Gestión Industrial, los beneficios que se otorgan a los aprendices (apoyo de alimentación, plan de datos, cívica estudiantil, entre otros) se gestionan de forma manual. Los aprendices se postulan mediante formularios, la información se transcribe a hojas de Excel y el seguimiento de los pagos se lleva en archivos separados. Esta forma de trabajo genera los siguientes problemas:'),
  bullet('Errores de transcripción y datos desactualizados (documento, cuenta bancaria, ficha, programa de formación).'),
  bullet('Validación lenta de los documentos de soporte, que se revisan uno por uno sin una herramienta que los organice.'),
  bullet('Dificultad para saber cuántos pagos ha recibido cada beneficiario, cuáles se retrasaron y por qué.'),
  bullet('El aprendiz no tiene un canal para consultar el estado de su postulación ni el avance de sus pagos, por lo que debe preguntar personalmente.'),
  bullet('No queda registro de quién validó, aprobó o rechazó una postulación, ni de cuándo lo hizo.'),
  p('Como consecuencia, el proceso consume mucho tiempo del personal administrativo, se pierde información y los aprendices desconfían del proceso.'),
];

const justificacion = [
  p('Una aplicación web que centralice las postulaciones, la validación, la asignación de beneficios y el control de los pagos reduce los errores de transcripción, organiza los documentos de soporte, deja trazabilidad de cada decisión y permite que el aprendiz consulte su información en cualquier momento. Esto hace el proceso más rápido, transparente y confiable.'),
  p([run('Beneficiarios:', { bold: true })]),
  bullet([run('Aprendices: ', { bold: true }), run('se postulan en línea, cargan sus documentos y consultan el estado de su postulación y de sus pagos.')]),
  bullet([run('Administradores (Bienestar al Aprendiz): ', { bold: true }), run('validan postulaciones, registran a los beneficiarios y llevan el control de los pagos desde un solo lugar.')]),
  bullet([run('Institución: ', { bold: true }), run('obtiene información ordenada y trazable sobre los beneficios entregados.')]),
];

const especificos = [
  ['Gestión de usuarios y roles', 'Gestionar las cuentas de usuario y sus roles (administrador y aprendiz), mediante autenticación con documento y contraseña, de acuerdo con las políticas de seguridad del sistema.'],
  ['Gestión de beneficios y convocatorias', 'Administrar los tipos de beneficio y sus convocatorias (fechas, cupos, requisitos, valor y número de pagos), mediante formularios de configuración, de acuerdo con las condiciones definidas por la institución.'],
  ['Postulación', 'Registrar las postulaciones de los aprendices a las convocatorias, mediante un formulario en línea con carga de documentos de soporte, de acuerdo con los requisitos de cada convocatoria.'],
  ['Validación de postulaciones', 'Validar los datos y documentos de cada postulación, mediante una vista que muestre lado a lado la información digitada y los documentos cargados, de acuerdo con los criterios del administrador.'],
  ['Asignación de beneficios', 'Registrar la decisión de aprobación o rechazo de cada postulación, mediante el control de cupos de la convocatoria, de acuerdo con la selección realizada por la institución.'],
  ['Seguimiento de pagos', 'Registrar cada pago efectuado a un beneficiario (número de pago, fecha, valor, retrasos y su motivo), mediante un módulo de control de pagos, de acuerdo con las condiciones del beneficio. El sistema no realiza transacciones financieras.'],
  ['Consulta y notificaciones', 'Informar al aprendiz el estado de su postulación y el avance de sus pagos, mediante un portal de consulta y notificaciones por correo, de acuerdo con los cambios registrados por el administrador.'],
  ['Migración de información', 'Importar la información existente en hojas de Excel, mediante la carga de archivos con una plantilla validada, de acuerdo con la estructura de datos del sistema.'],
  ['Seguridad y trazabilidad', 'Controlar el acceso y registrar las acciones realizadas sobre la información, mediante permisos por rol y un registro de auditoría, de acuerdo con las normas de protección de datos personales (Ley 1581 de 2012).'],
  ['Escalabilidad', 'Diseñar la arquitectura de la aplicación, mediante una estructura modular y parametrizable, de acuerdo con la necesidad de adaptarla en el futuro a otras instituciones, otros tipos de beneficio u otros tipos de terceros.'],
];

const objetivos = [
  p([run('General: ', { bold: true }), run('Desarrollar una aplicación web para la gestión de los beneficios otorgados a los aprendices del SENA, mediante el registro de postulaciones, la validación de la información y el seguimiento de los pagos, de acuerdo con los requerimientos del administrador y del aprendiz.')]),
  p([run('Específicos:', { bold: true })]),
  ...especificos.map(([t, d]) => numItem('objetivos', t, d)),
];

const antecedentes = [
  p('Actualmente el proceso se apoya en formularios de postulación y hojas de Excel administradas manualmente por el personal de Bienestar al Aprendiz. No se conoce un sistema de información anterior en el centro de formación que cubra la postulación, la validación y el seguimiento de pagos de los beneficios.'),
];

const resultados = [
  p([run('Entregables de producto:', { bold: true })]),
  sub('Requisitos'),
  bullet('Sprint 01: Instrumentos de recolección de información. Mapa de procesos.'),
  bullet('Sprint 02: Ficha de proyecto aprobada.'),
  bullet('Sprint 03: Historias de usuario con criterios de aceptación.'),
  sub('Análisis'),
  bullet('Sprint 04: Product Backlog refinado.'),
  bullet('Sprint 05: Diagramas de clases y modelo relacional. Casos de uso y su documentación.'),
  bullet('Sprint 06: Facilitación gráfica.'),
  sub('Modelado'),
  bullet('Sprint 07: Prototipo en Figma.'),
  bullet('Sprint 08: Script y diagrama físico de la base de datos.'),
  bullet('Sprint 09: Diagramas de componentes y de despliegue. Refinamiento del diagrama de clases.'),
  sub('Construcción'),
  bullet('Definición de los sprints de desarrollo, código fuente, informe de evaluación de la calidad y documentación de las pruebas.'),
  sub('Implantación'),
  bullet('Capacitación del usuario.'),
  p([run('Funcionalidad de la aplicación:', { bold: true })], { spacing: { before: 120, after: 60 } }),
  ...especificos.slice(0, 9).map(([t, d]) => numItem('funcionalidad', t, d)),
];

const alcance = [
  p('BenefitRack es una aplicación web que permite gestionar los beneficios de los aprendices del SENA desde la postulación hasta el último pago. El sistema permite:'),
  bullet('Configurar beneficios y convocatorias con sus cupos, fechas, valor y número de pagos.'),
  bullet('Recibir postulaciones de los aprendices con sus datos personales, EPS, información bancaria y documentos de soporte (PDF, JPG o PNG de máximo 5 MB).'),
  bullet('Validar cada postulación y registrar la decisión, con los estados Validada, Aprobada o Rechazada (el rechazo siempre lleva justificación).'),
  bullet('Registrar manualmente cada pago efectuado a un beneficiario, incluidos los retrasos y su motivo.'),
  bullet('Ofrecer al aprendiz un portal para consultar su estado, sus pagos y sus novedades, y enviarle notificaciones.'),
  bullet('Importar información histórica desde Excel y llevar un registro de auditoría de las acciones.'),
  p([run('Fuera del alcance: ', { bold: true }), run('el sistema no realiza transacciones financieras ni pagos; solo registra y controla la información de los pagos. Tampoco decide quién recibe el beneficio: la selección la realiza la institución y el administrador la registra en el sistema.')]),

  p([run('PROCESOS ESTRATÉGICOS', { bold: true })], { spacing: { before: 160, after: 60 } }),
  bullet([run('Planeación de beneficios y convocatorias: ', { bold: true }), run('inicia cuando la institución define un beneficio; permite crear, consultar, actualizar e inactivar beneficios y convocatorias; termina cuando la convocatoria queda abierta a postulaciones.')]),
  bullet([run('Gestión de calidad: ', { bold: true }), run('inicia al definir los estándares del sistema; permite registrar y revisar pruebas y controles de calidad; termina cuando la versión cumple los criterios de aceptación.')]),
  bullet([run('Mejora continua: ', { bold: true }), run('inicia al identificar oportunidades de mejora a partir de la retroalimentación de los usuarios; termina cuando la mejora se implementa y se verifica.')]),

  p([run('PROCESOS MISIONALES (CLAVE)', { bold: true })], { spacing: { before: 160, after: 60 } }),
  bullet([run('Postulación: ', { bold: true }), run('inicia cuando el aprendiz diligencia el formulario de una convocatoria abierta; permite registrar datos y cargar documentos; termina cuando el aprendiz envía la postulación completa.')]),
  bullet([run('Validación: ', { bold: true }), run('inicia cuando el administrador revisa una postulación enviada; permite comparar datos y documentos y marcarla como Validada o Rechazada con justificación; termina cuando la postulación queda revisada y el aprendiz es notificado.')]),
  bullet([run('Asignación de beneficios: ', { bold: true }), run('inicia cuando la institución define a los seleccionados; permite marcar las postulaciones validadas como Aprobadas, respetando los cupos; termina cuando los beneficiarios quedan registrados.')]),
  bullet([run('Seguimiento de pagos: ', { bold: true }), run('inicia cuando se efectúa un pago a un beneficiario; permite registrar el número de pago, la fecha, el valor y los retrasos con su motivo; termina cuando se registran todos los pagos del beneficio.')]),
  bullet([run('Consulta y notificaciones: ', { bold: true }), run('inicia cuando cambia el estado de una postulación o se registra un pago; permite al aprendiz consultar su información y recibir avisos; termina cuando el aprendiz es informado.')]),

  p([run('PROCESOS DE SOPORTE', { bold: true })], { spacing: { before: 160, after: 60 } }),
  bullet([run('Administración de usuarios: ', { bold: true }), run('inicia con la creación o solicitud de un usuario; permite registrar, consultar, actualizar, bloquear o desactivar usuarios y recuperar la contraseña; termina cuando el usuario queda activo, actualizado o desactivado.')]),
  bullet([run('Migración de datos: ', { bold: true }), run('inicia cuando el administrador carga un archivo de Excel; permite validar la plantilla e importar los registros sin duplicados; termina con un resumen de registros importados y rechazados.')]),
  bullet([run('Auditoría: ', { bold: true }), run('inicia con cada acción relevante sobre la información; registra el usuario, la fecha y el detalle; termina cuando la acción queda almacenada para consulta.')]),
  bullet([run('Soporte técnico: ', { bold: true }), run('inicia al detectar un problema de un usuario; termina al resolver la incidencia.')]),
];

const descripcion = new Table({
  width: { size: W, type: WidthType.DXA },
  columnWidths: [W],
  rows: [
    new TableRow({ children: [cell([p([run('DESCRIPCIÓN DEL PROYECTO', { bold: true })], { alignment: AlignmentType.CENTER })], W, { fill: VERDE })] }),
    ...[
      ['Planteamiento del problema a resolver', planteamiento],
      ['Justificación', justificacion],
      ['Objetivos del proyecto', objetivos],
      ['Antecedentes', antecedentes],
      ['Resultados esperados', resultados],
      ['Alcance', alcance],
    ].flatMap(([t, c]) => [
      new TableRow({ children: [cell([p([run(t, { bold: true })])], W, { fill: 'F2F2F2' })] }),
      new TableRow({ children: [cell(c, W)] }),
    ]),
  ],
});

const fechas = grid([W / 2, W / 2], [
  [[run('Fecha de inicio: ', { bold: true }), run('____ / ____ / ________')], [run('Fecha de terminación: ', { bold: true }), run('Fin de la etapa lectiva')]],
], false);

const comite = section('RESULTADOS DE COMITÉ (para uso exclusivo del comité)', [
  p('Estado:'),
  p('Primera entrega:  ☐ Aprobado   ☐ Por ajustar   ☐ Rechazado     Fecha: ____ / ____ / ______'),
  p('Segunda entrega:  ☐ Aprobado   ☐ Por ajustar   ☐ Rechazado     Fecha: ____ / ____ / ______'),
  p('Tercera entrega:  ☐ Aprobado   ☐ Por ajustar   ☐ Rechazado     Fecha: ____ / ____ / ______'),
  label('Nombre del instructor de ficha + requisitos: ', ''),
  label('Nombre y firma de quien revisa y aprueba la ficha de proyecto: ', ''),
  label('Observaciones: ', ''),
]);

const control = grid([2200, 2600, 2000, 2560], [
  ['', 'Nombre', 'Cargo / Área', 'Fecha'],
  ['Autores', 'Doris Elena Monsalve Sossa', 'Instructora – ADSI', 'Julio 23 de 2020'],
  ['Revisión', 'Liliana María Galeano Zea', 'Instructora – ADSI', 'Julio 28 de 2020'],
  ['Revisión', 'Jhonnys Rodríguez Payares', 'Instructor – ADSI', ''],
  ['Revisión', 'Jairo Israel Londoño Serrato', 'Instructor – Pedagogía', ''],
  ['Aprobación', '', '', ''],
]);

const cambios = grid([2800, 2800, 1500, 2260], [
  ['Descripción del cambio', 'Razón del cambio', 'Fecha', 'Responsable'],
  ['Ajuste del formato de ficha de proyecto.', 'Conservar estructura de documentos según compromiso.', '11/04/2020', 'Liliana Galeano (Instructora)'],
  ['Ajuste del formato de ficha de proyecto.', 'Reestructuración al formato en estructura didáctica y metodológica.', '21/07/2020', 'Equipo de instructores ADSI'],
  ['Ajuste del formato de ficha de proyecto.', 'Ajuste de actividades para el programa ADSO.', '30/01/2022', 'Doris Elena Monsalve Sossa'],
  ['Reescritura del contenido para el proyecto BenefitRack.', 'La ficha contenía el contenido de un proyecto anterior (horas de alfabetización).', '26/10/2026', 'Samuel David Hurtado Arango (Aprendiz)'],
]);

const heading = (t) => new Paragraph({ spacing: { before: 200, after: 100 }, children: [run(t, { bold: true, size: 22 })] });

const doc = new Document({
  creator: 'Samuel David Hurtado Arango',
  title: 'Ficha de Proyecto – BenefitRack',
  styles: { default: { document: { run: { font: FONT, size: 20 } } } },
  numbering: {
    config: [
      { reference: 'vinetas', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 500, hanging: 260 } } } }] },
      { reference: 'objetivos', levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 500, hanging: 300 } } } }] },
      { reference: 'funcionalidad', levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 500, hanging: 300 } } } }] },
    ],
  },
  sections: [{
    properties: { page: { size: { width: 12240, height: 15840 }, margin: { top: 1440, bottom: 1440, left: 1440, right: 1440 } } },
    children: [
      new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 60 }, children: [run('FICHA DE PROYECTO', { bold: true, size: 28 })] }),
      new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 240 }, border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: '39A900', space: 4 } }, children: [run('Tecnología en Análisis y Desarrollo de Software – ADSO', { size: 20 })] }),
      informacionGeneral, gap(),
      titleBar('APRENDICES PARTICIPANTES EN EL PROYECTO'), aprendices, gap(),
      descripcion, gap(),
      fechas, gap(),
      comite,
      heading('CONTROL DEL DOCUMENTO'), control,
      heading('CONTROL DE CAMBIOS'), cambios,
    ],
  }],
});

Packer.toBuffer(doc).then((buf) => {
  const out = process.argv[2];
  fs.writeFileSync(out, buf);
  console.log('ok', out, buf.length);
});
