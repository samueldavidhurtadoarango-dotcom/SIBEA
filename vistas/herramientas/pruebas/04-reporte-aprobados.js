// Pruebas propias de la vista 04-reporte-aprobados (spec 002 §5 «Vista 4», plan tarea 8).
// El administrador descarga en Excel los aprendices aprobados de una convocatoria, para quien hace los pagos.
const texto = (page, selector = 'body') =>
  page.$eval(selector, (el) => el.innerText.replace(/\s+/g, ' ').trim());

const exige = (cond, mensaje) => {
  if (!cond) throw new Error(mensaje);
};

const ESTADOS = ['dialogo', 'sin-aprobados'];
const POR_DEFECTO = ['Nombre', 'Documento', 'Ficha', 'Programa', 'Beneficio', 'Valor del pago',
  'Entidad bancaria', 'Tipo de cuenta', 'Número de cuenta'];
const OPCIONALES = ['Correo', 'Teléfono', 'Fecha de aprobación'];

// Casillas del diálogo con su texto de label, si están marcadas y a qué grupo (fieldset) pertenecen
const casillas = (page) => page.$$eval('[role=dialog] input[type=checkbox]', (els) => els.map((e) => {
  const leyenda = e.closest('fieldset') && e.closest('fieldset').querySelector('legend');
  return {
    etiqueta: (e.labels && e.labels[0] ? e.labels[0].innerText : '').replace(/\s+/g, ' ').trim(),
    marcada: e.checked,
    deshabilitada: e.disabled,
    grupo: leyenda ? leyenda.innerText.trim() : '',
  };
}));

const dialogo = (page) => page.$eval('[role=dialog]', (el) => ({
  texto: el.innerText.replace(/\s+/g, ' ').trim(),
  botones: [...el.querySelectorAll('button, a.br-boton')].map((b) => ({
    texto: b.textContent.replace(/\s+/g, ' ').trim(),
    deshabilitado: b.disabled || b.getAttribute('aria-disabled') === 'true',
    descrito: (b.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean)
      .map((id) => (document.getElementById(id) || { innerText: '' }).innerText).join(' ').replace(/\s+/g, ' ').trim(),
  })),
}));

module.exports = [
  ...ESTADOS.map((estado) => ({
    estado,
    nombre: 'migas con «Convocatorias», un h1 y menú «Convocatorias» activo (el fondo es la lista de convocatorias)',
    prueba: async (page) => {
      const actual = await texto(page, 'nav[aria-label="Ruta"] [aria-current="page"]');
      exige(actual === 'Convocatorias', `miga actual: «${actual}»`);
      const h1s = await page.$$eval('main h1', (h) => h.length);
      exige(h1s === 1, `hay ${h1s} h1 en main, debe haber 1`);
      const menu = await texto(page, 'nav[aria-label="Menú principal"] [aria-current="page"]');
      exige(menu.includes('Convocatorias'), `opción activa del menú: «${menu}»`);
    },
  })),
  ...ESTADOS.map((estado) => ({
    estado,
    nombre: 'sin nombres del equipo en los datos de ejemplo',
    prueba: async (page) => {
      const t = await texto(page, 'body');
      const m = t.match(/\b(Samuel|Santiago|Mariana|Dylan)\b/);
      exige(!m, `aparece «${m && m[0]}»`);
    },
  })),
  ...ESTADOS.map((estado) => ({
    estado,
    nombre: 'diálogo con role=dialog, aria-modal, título «Reporte de aprobados», dentro de .br-dialogo-capa y sobre la lista',
    prueba: async (page) => {
      const d = await page.evaluate(() => {
        const ds = document.querySelectorAll('[role=dialog]');
        if (ds.length !== 1) return { n: ds.length };
        const id = ds[0].getAttribute('aria-labelledby');
        const t = id ? document.getElementById(id) : null;
        return { n: 1, modal: ds[0].getAttribute('aria-modal') === 'true', titulo: t ? t.innerText.trim() : '' };
      });
      exige(d.n === 1, `hay ${d.n} [role=dialog], debe haber 1`);
      exige(d.modal, 'el diálogo no tiene aria-modal="true"');
      exige(d.titulo === 'Reporte de aprobados', `título del diálogo: «${d.titulo}»`);
      exige(await page.$('.br-dialogo-capa [role=dialog]'), 'el diálogo no está dentro de .br-dialogo-capa');
      exige(await page.$('main [data-prueba=tabla-convocatorias]'), 'el fondo no es la lista de convocatorias');
    },
  })),

  // ---------- Diálogo ----------
  {
    estado: 'dialogo',
    nombre: 'marcadas por defecto exactamente las 9 columnas de la spec',
    prueba: async (page) => {
      const cs = await casillas(page);
      const marcadas = cs.filter((c) => c.marcada).map((c) => c.etiqueta);
      for (const c of POR_DEFECTO) exige(marcadas.includes(c), `«${c}» no está marcada: marcadas = ${marcadas.join(' | ')}`);
      exige(marcadas.length === POR_DEFECTO.length, `hay ${marcadas.length} marcadas, deben ser ${POR_DEFECTO.length}: ${marcadas.join(' | ')}`);
      exige(cs.every((c) => c.etiqueta), 'hay una casilla sin label');
    },
  },
  {
    estado: 'dialogo',
    nombre: 'hay otras columnas sin marcar (Correo, Teléfono, Fecha de aprobación) y el administrador puede cambiar todas',
    prueba: async (page) => {
      const cs = await casillas(page);
      for (const c of OPCIONALES) {
        const x = cs.find((k) => k.etiqueta === c);
        exige(x, `falta la columna opcional «${c}»`);
        exige(!x.marcada, `«${c}» no debe venir marcada`);
      }
      exige(cs.every((c) => !c.deshabilitada), 'alguna casilla está deshabilitada: el administrador debe poder agregar o quitar columnas');
    },
  },
  {
    estado: 'dialogo',
    nombre: 'las columnas van en grupos con nombre (fieldset + legend) y las bancarias en el suyo',
    prueba: async (page) => {
      const cs = await casillas(page);
      exige(cs.every((c) => c.grupo), 'hay casillas fuera de un fieldset con legend');
      const grupos = new Set(cs.map((c) => c.grupo));
      exige(grupos.size >= 3, `solo ${grupos.size} grupos`);
      const banco = cs.filter((c) => ['Entidad bancaria', 'Tipo de cuenta', 'Número de cuenta'].includes(c.etiqueta));
      exige(new Set(banco.map((c) => c.grupo)).size === 1 && /banc/i.test(banco[0].grupo), `datos bancarios en grupos: ${banco.map((c) => c.grupo).join(' | ')}`);
    },
  },
  {
    estado: 'dialogo',
    nombre: 'dice «25 aprendices aprobados», la convocatoria y el nombre de archivo sugerido',
    prueba: async (page) => {
      const d = await dialogo(page);
      exige(d.texto.includes('25 aprendices aprobados'), `no dice «25 aprendices aprobados»: «${d.texto}»`);
      exige(d.texto.includes('Alimentación 2026-2'), 'no dice de qué convocatoria es');
      const nombre = await page.$eval('[role=dialog] input[type=text]', (e) => ({
        valor: e.value, etiqueta: e.labels && e.labels[0] ? e.labels[0].innerText.trim() : '',
      }));
      exige(nombre.etiqueta === 'Nombre del archivo', `label del nombre: «${nombre.etiqueta}»`);
      exige(nombre.valor === 'aprobados-alimentacion-2026-2.xlsx', `nombre sugerido: «${nombre.valor}»`);
    },
  },
  {
    estado: 'dialogo',
    nombre: 'avisa que el archivo contiene datos bancarios y que se comparte solo con quien hace los pagos',
    prueba: async (page) => {
      const d = await dialogo(page);
      exige(/datos bancarios/i.test(d.texto) && /solo con quien hace los pagos/i.test(d.texto), `sin aviso de seguridad: «${d.texto}»`);
    },
  },
  {
    estado: 'dialogo',
    nombre: 'botones «Cancelar» y «Descargar» (activo) y un botón «Cerrar» con nombre accesible',
    prueba: async (page) => {
      const d = await dialogo(page);
      const b = (t) => d.botones.find((x) => x.texto === t);
      exige(b('Cancelar'), `botones: ${d.botones.map((x) => x.texto).join(' | ')}`);
      exige(b('Descargar') && !b('Descargar').deshabilitado, '«Descargar» debe estar activo');
      exige(await page.$('[role=dialog] button[aria-label="Cerrar"]'), 'falta el botón con aria-label «Cerrar»');
    },
  },

  // ---------- Sin aprobados ----------
  {
    estado: 'sin-aprobados',
    nombre: 'dice «aún no tiene aprendices aprobados» y nombra la convocatoria',
    prueba: async (page) => {
      const d = await dialogo(page);
      exige(d.texto.includes('aún no tiene aprendices aprobados'), `texto: «${d.texto}»`);
      exige(d.texto.includes('Tarjeta cívica 2026-2'), 'no nombra la convocatoria');
    },
  },
  {
    estado: 'sin-aprobados',
    nombre: '«Descargar» está deshabilitado y dice su razón (aria-describedby)',
    prueba: async (page) => {
      const d = await dialogo(page);
      const desc = d.botones.find((x) => x.texto === 'Descargar');
      exige(desc, 'falta «Descargar»');
      exige(desc.deshabilitado, '«Descargar» debe estar deshabilitado');
      exige(/aprobad/i.test(desc.descrito), `la razón no está enlazada con aria-describedby: «${desc.descrito}»`);
      exige(d.botones.some((x) => x.texto === 'Cancelar' && !x.deshabilitado), '«Cancelar» debe estar activo');
    },
  },
  {
    estado: 'sin-aprobados',
    nombre: 'coherente con la lista: la convocatoria tiene 0 cupos usados',
    prueba: async (page) => {
      const v = await page.$eval('[data-prueba=tabla-convocatorias] progress[aria-label*="Tarjeta cívica 2026-2"]', (p) => p.value);
      exige(v === 0, `la barra de Tarjeta cívica 2026-2 marca ${v}`);
    },
  },
];
