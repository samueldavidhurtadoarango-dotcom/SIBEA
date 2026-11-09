// Pruebas propias de la vista 06-usuarios (spec 002 §5 «Vista 6», plan tarea 6, CA-7).
// Las cuentas de aprendices las crea el administrador, sobre todo cargando un Excel.
const texto = (page, selector = 'body') =>
  page.$eval(selector, (el) => el.innerText.replace(/\s+/g, ' ').trim());

const exige = (cond, mensaje) => {
  if (!cond) throw new Error(mensaje);
};

const ESTADOS = ['lista', 'cargar-excel', 'vista-previa', 'resultado', 'crear-uno', 'archivo-invalido'];
const LISTADOS = ['lista', 'resultado'];
const CARGA = ['cargar-excel', 'vista-previa', 'archivo-invalido'];
const COLUMNAS_PLANTILLA = ['Tipo de documento', 'Número de documento', 'Nombres', 'Apellidos',
  'Correo', 'Teléfono', 'Programa', 'Ficha'];
const COLUMNAS_LISTA = ['Aprendiz', 'Correo', 'Programa', 'Estado'];
const MIGA = {
  lista: 'Usuarios',
  resultado: 'Usuarios',
  'cargar-excel': 'Cargar Excel',
  'vista-previa': 'Cargar Excel',
  'archivo-invalido': 'Cargar Excel',
  'crear-uno': 'Crear una cuenta',
};

// Filas de la tabla de usuarios: nombre, estado y textos de todos sus botones (también los del menú)
const filasUsuarios = (page) => page.$$eval('[data-prueba=tabla-usuarios] tbody tr', (trs) => trs.map((tr) => ({
  nombre: (tr.querySelector('.br-tabla__principal') || tr).textContent.replace(/\s+/g, ' ').trim(),
  estados: [...tr.querySelectorAll('.br-etiqueta')].map((e) => e.textContent.trim()),
  botones: [...tr.querySelectorAll('button')].map((b) => b.textContent.replace(/\s+/g, ' ').trim()),
})));

// Textos de botones y enlaces de la página (sin el menú lateral)
const acciones = (page) => page.$$eval('main a, main button', (els) =>
  els.map((e) => e.textContent.replace(/\s+/g, ' ').trim()));

module.exports = [
  // Migas y título en todos los estados
  ...ESTADOS.map((estado) => ({
    estado,
    nombre: `migas con «${MIGA[estado]}» como página actual y un título h1`,
    prueba: async (page) => {
      const actual = await texto(page, 'nav[aria-label="Ruta"] [aria-current="page"]');
      exige(actual === MIGA[estado], `miga actual: «${actual}»`);
      const h1s = await page.$$eval('main h1', (h) => h.length);
      exige(h1s === 1, `hay ${h1s} h1 en main, debe haber 1`);
    },
  })),

  // ---------- Listado de aprendices ----------
  ...LISTADOS.map((estado) => ({
    estado,
    nombre: 'tabla de aprendices con buscador, columnas, estados y accesos a «Cargar Excel» y «Crear una cuenta»',
    prueba: async (page) => {
      const h1 = await texto(page, 'main h1');
      exige(h1.startsWith('Usuarios'), `h1: «${h1}»`);
      const ths = await page.$$eval('[data-prueba=tabla-usuarios] thead th', (t) => t.map((x) => x.innerText.trim()));
      for (const c of COLUMNAS_LISTA) exige(ths.includes(c), `falta la columna «${c}»: ${ths.join(' | ')}`);
      const buscador = await page.$eval('main', (m) => {
        const i = m.querySelector('input[type=search]');
        return i ? !!(i.labels && i.labels.length) || !!i.getAttribute('aria-label') : null;
      });
      exige(buscador !== null, 'no hay buscador (input type=search)');
      exige(buscador, 'el buscador no tiene etiqueta');
      const a = await acciones(page);
      exige(a.some((t) => t.startsWith('Cargar Excel')), `falta «Cargar Excel»: ${a.join(' | ')}`);
      exige(a.some((t) => t.startsWith('Crear una cuenta')), `falta «Crear una cuenta»: ${a.join(' | ')}`);
    },
  })),
  ...LISTADOS.map((estado) => ({
    estado,
    nombre: 'a 390 px la tabla de aprendices cabe sin desplazarse (filas apiladas)',
    prueba: async (page) => {
      await page.setViewportSize({ width: 390, height: 844 });
      const r = await page.$eval('[data-prueba=tabla-usuarios]', (tabla) => {
        const env = tabla.closest('.br-tabla-envoltura') || tabla.parentElement;
        return { scroll: env.scrollWidth, cliente: env.clientWidth };
      });
      exige(r.scroll <= r.cliente, `la tabla se desplaza dentro de su caja: ${r.scroll} > ${r.cliente}`);
    },
  })),
  {
    estado: 'lista',
    nombre: 'estados de cuenta Activo, Inactivo y Bloqueado, con los aprendices de las vistas anteriores',
    prueba: async (page) => {
      const filas = await filasUsuarios(page);
      const estados = filas.flatMap((f) => f.estados);
      for (const e of ['Activo', 'Inactivo', 'Bloqueado']) {
        exige(estados.includes(e), `ninguna fila está «${e}»: ${[...new Set(estados)].join(' | ')}`);
      }
      const nombres = filas.map((f) => f.nombre).join(' | ');
      for (const n of ['Luisa Fernanda', 'Daniela Mosquera Palacios', 'Brayan Stiven Cardona']) {
        exige(nombres.includes(n), `no está ${n}: ${nombres}`);
      }
    },
  },
  {
    estado: 'lista',
    nombre: 'acciones por estado: Activo → Desactivar, Inactivo → Activar, Bloqueado → Desbloquear (solo ahí)',
    prueba: async (page) => {
      const filas = await filasUsuarios(page);
      exige(filas.length >= 6, `solo hay ${filas.length} filas`);
      for (const f of filas) {
        const estado = f.estados[0];
        const tiene = (t) => f.botones.some((b) => b.startsWith(t));
        if (estado === 'Activo') exige(tiene('Desactivar'), `${f.nombre} (Activo) no tiene «Desactivar»: ${f.botones.join(' | ')}`);
        if (estado === 'Inactivo') exige(tiene('Activar'), `${f.nombre} (Inactivo) no tiene «Activar»: ${f.botones.join(' | ')}`);
        if (estado === 'Bloqueado') exige(tiene('Desbloquear'), `${f.nombre} (Bloqueado) no tiene «Desbloquear»: ${f.botones.join(' | ')}`);
        else exige(!tiene('Desbloquear'), `${f.nombre} (${estado}) tiene «Desbloquear» y no está bloqueado`);
        if (estado !== 'Inactivo') exige(!tiene('Activar'), `${f.nombre} (${estado}) tiene «Activar» y no está inactivo`);
      }
    },
  },
  {
    estado: 'lista',
    nombre: 'el menú «Más acciones» abierto explica qué pasa al desactivar',
    prueba: async (page) => {
      const r = await page.$eval('[data-prueba=acciones-fila]', (m) => ({
        abierto: m.open,
        etiqueta: (m.querySelector('summary') || m).getAttribute('aria-label') || '',
        texto: m.innerText.replace(/\s+/g, ' '),
      }));
      exige(r.abierto, 'el menú [data-prueba=acciones-fila] no está abierto');
      exige(/^Más acciones para /.test(r.etiqueta), `el botón del menú no dice para quién es: «${r.etiqueta}»`);
      exige(/Desactivar/.test(r.texto) && /no podrá ingresar/i.test(r.texto), `menú: «${r.texto}»`);
    },
  },

  // ---------- Carga del Excel ----------
  ...CARGA.map((estado) => ({
    estado,
    nombre: 'h1 de la carga y enlace para volver a usuarios',
    prueba: async (page) => {
      const h1 = await texto(page, 'main h1');
      exige(/Excel|archivo/i.test(h1), `h1: «${h1}»`);
      const a = await acciones(page);
      exige(a.some((t) => t === 'Volver a usuarios'), `falta «Volver a usuarios»: ${a.join(' | ')}`);
    },
  })),
  ...['cargar-excel', 'archivo-invalido'].map((estado) => ({
    estado,
    nombre: 'zona para arrastrar un .xlsx, «Descargar plantilla» y las 8 columnas requeridas visibles',
    prueba: async (page) => {
      const zona = await page.$eval('[data-prueba=zona]', (z) => {
        const i = z.querySelector('input[type=file]') || document.getElementById(z.getAttribute('for'));
        return { accept: i ? i.accept : null, texto: z.innerText.replace(/\s+/g, ' ') };
      });
      exige(zona.accept !== null, 'la zona no tiene input type=file');
      exige(/\.xlsx/.test(zona.accept), `el input no acepta .xlsx: «${zona.accept}»`);
      exige(/arrastr/i.test(zona.texto), `la zona no invita a arrastrar: «${zona.texto}»`);
      const a = await acciones(page);
      exige(a.some((t) => t.startsWith('Descargar plantilla')), `falta «Descargar plantilla»: ${a.join(' | ')}`);
      const cols = await page.$eval('[data-prueba=columnas]', (c) => {
        const r = c.getBoundingClientRect();
        return { visible: r.width > 0 && r.height > 0, texto: c.innerText.replace(/\s+/g, ' ') };
      });
      exige(cols.visible, '[data-prueba=columnas] no se ve');
      for (const c of COLUMNAS_PLANTILLA) exige(cols.texto.includes(c), `falta la columna requerida «${c}»`);
    },
  })),
  {
    estado: 'cargar-excel',
    nombre: '«Revisar archivo» deshabilitado hasta elegir un archivo, con su porqué',
    prueba: async (page) => {
      const r = await page.$eval('[data-prueba=revisar]', (b) => ({
        texto: b.textContent.trim(),
        deshabilitado: b.disabled,
        porque: (b.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean)
          .map((id) => document.getElementById(id)).filter(Boolean).map((x) => x.innerText).join(' '),
      }));
      exige(r.texto.startsWith('Revisar archivo'), `botón: «${r.texto}»`);
      exige(r.deshabilitado, '«Revisar archivo» debería estar deshabilitado');
      exige(/archivo/i.test(r.porque), `no explica por qué está deshabilitado: «${r.porque}»`);
    },
  },
  {
    estado: 'vista-previa',
    nombre: 'CA-7 resumen: 124 filas revisadas, 120 válidas y 4 con errores',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=resumen-filas]');
      for (const parte of ['124 filas', '120', '4 con errores']) exige(t.includes(parte), `falta «${parte}» en el resumen: «${t}»`);
    },
  },
  {
    estado: 'vista-previa',
    nombre: 'CA-7 tabla de errores con Fila, Columna y Motivo (columnas de la plantilla)',
    prueba: async (page) => {
      const ths = await page.$$eval('[data-prueba=errores] thead th', (t) => t.map((x) => x.innerText.trim()));
      for (const c of ['Fila', 'Columna', 'Motivo']) exige(ths.includes(c), `falta la columna «${c}»: ${ths.join(' | ')}`);
      const filas = await page.$$eval('[data-prueba=errores] tbody tr', (trs, cab) => trs.map((tr) => {
        const celdas = [...tr.children].map((td) => td.innerText.replace(/\s+/g, ' ').trim());
        const de = (nombre) => celdas[cab.indexOf(nombre)] || '';
        return { fila: de('Fila'), columna: de('Columna'), motivo: de('Motivo') };
      }), ths);
      exige(filas.length === 4, `la tabla de errores tiene ${filas.length} filas, deben ser 4`);
      for (const f of filas) {
        exige(/\d+/.test(f.fila), `fila sin número: «${f.fila}»`);
        exige(COLUMNAS_PLANTILLA.includes(f.columna), `«${f.columna}» no es una columna de la plantilla`);
        exige(f.motivo.length > 3, `fila ${f.fila} sin motivo`);
      }
      const motivos = filas.map((f) => f.motivo.toLowerCase()).join(' | ');
      for (const m of ['correo repetido', 'documento vacío', 'ficha no existe', 'tipo de documento no válido']) {
        exige(motivos.includes(m), `falta el motivo «${m}»: ${motivos}`);
      }
    },
  },
  {
    estado: 'vista-previa',
    nombre: 'CA-7 el botón para crear las cuentas indica el número de filas válidas («Crear 120 cuentas»)',
    prueba: async (page) => {
      const r = await page.$eval('[data-prueba=crear-cuentas]', (b) => ({ texto: b.textContent.replace(/\s+/g, ' ').trim(), deshabilitado: b.disabled }));
      exige(r.texto === 'Crear 120 cuentas', `botón: «${r.texto}»`);
      exige(!r.deshabilitado, '«Crear 120 cuentas» está deshabilitado');
      const t = await texto(page, 'main');
      exige(/no se crean/i.test(t), 'no aclara que las filas con errores no se crean');
    },
  },
  {
    estado: 'archivo-invalido',
    nombre: 'error «El archivo no es un Excel (.xlsx)» con la otra causa como ayuda y sin poder revisar',
    prueba: async (page) => {
      const alerta = await texto(page, '[role=alert]');
      exige(alerta.includes('El archivo no es un Excel (.xlsx)'), `alerta: «${alerta}»`);
      const t = await texto(page, 'main');
      exige(t.includes('Faltan las columnas: Ficha, Programa'), 'no menciona el otro motivo «Faltan las columnas: Ficha, Programa»');
      const deshabilitado = await page.$eval('[data-prueba=revisar]', (b) => b.disabled);
      exige(deshabilitado, '«Revisar archivo» debería estar deshabilitado con un archivo inválido');
    },
  },

  // ---------- Resultado ----------
  {
    estado: 'resultado',
    nombre: '«Se crearon 120 cuentas. 4 filas con errores» con «Descargar lista de errores»',
    prueba: async (page) => {
      const t = await texto(page, '[role=status]');
      exige(/Se crearon \d+ cuentas/.test(t), `mensaje: «${t}»`);
      exige(t.includes('Se crearon 120 cuentas. 4 filas con errores'), `mensaje: «${t}»`);
      const botones = await page.$$eval('[role=status] a, [role=status] button', (b) => b.map((x) => x.textContent.trim()));
      exige(botones.some((b) => b.startsWith('Descargar lista de errores')), `botones del aviso: ${botones.join(' | ')}`);
    },
  },

  // ---------- Crear una cuenta ----------
  {
    estado: 'crear-uno',
    nombre: 'formulario con los mismos campos de la plantilla, cada uno con su label',
    prueba: async (page) => {
      const campos = await page.$$eval('main form :is(input, select, textarea)', (els) => els
        .filter((e) => e.type !== 'hidden')
        .map((e) => ({ etiqueta: (e.labels && e.labels[0] ? e.labels[0].innerText : '').replace(/\s+/g, ' ').trim(), requerido: e.required })));
      for (const c of COLUMNAS_PLANTILLA) {
        exige(campos.some((x) => x.etiqueta.startsWith(c)), `falta el campo «${c}» con label: ${campos.map((x) => x.etiqueta).join(' | ')}`);
      }
      exige(campos.every((x) => x.etiqueta), 'hay un campo sin label');
      const boton = await page.$$eval('main form button', (b) => b.map((x) => x.textContent.trim()));
      exige(boton.includes('Crear cuenta'), `botones del formulario: ${boton.join(' | ')}`);
    },
  },
  {
    estado: 'crear-uno',
    nombre: 'aclara la contraseña temporal que se cambia al primer ingreso',
    prueba: async (page) => {
      const t = await texto(page, 'main');
      exige(/contraseña temporal/i.test(t), 'no menciona la contraseña temporal');
      exige(/primer ingreso|primera vez que ingrese/i.test(t), 'no dice que se cambia al primer ingreso');
    },
  },
];
