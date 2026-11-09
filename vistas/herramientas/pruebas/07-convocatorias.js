// Pruebas propias de la vista 07-convocatorias (spec 002 §5 «Vista 7», plan tarea 7).
// El administrador abre y cierra las convocatorias de cada beneficio (tabla `convocatorias`).
const texto = (page, selector = 'body') =>
  page.$eval(selector, (el) => el.innerText.replace(/\s+/g, ' ').trim());

const exige = (cond, mensaje) => {
  if (!cond) throw new Error(mensaje);
};

const ESTADOS = ['lista', 'crear-editar', 'cerrar', 'vacio'];
const CON_TABLA = ['lista', 'cerrar'];
const MIGA = {
  lista: 'Convocatorias',
  cerrar: 'Convocatorias',
  vacio: 'Convocatorias',
  'crear-editar': 'Nueva convocatoria',
};
const COLUMNAS = ['Convocatoria', 'Fechas', 'Cupos', 'Pagos', 'Estado'];
const ESTADOS_BD = ['Activa', 'Inactiva', 'Cerrada', 'Cierre anticipado'];
const BENEFICIOS = ['Apoyo de alimentación', 'Plan de datos', 'Tarjeta cívica'];
// Los 9 campos de la tabla `convocatorias` que llena el administrador (más el estado inicial)
const CAMPOS = ['Nombre', 'Beneficio', 'Fecha de inicio', 'Fecha de cierre', 'Cupos', 'Requisitos',
  'Valor de cada pago', 'Número de pagos', 'Duración en meses'];
const REPORTE = '../../04-reporte-aprobados/html/dialogo.html';

// Filas de la tabla: nombre, texto, estados y textos de sus botones y enlaces (también los del menú)
const filas = (page) => page.$$eval('[data-prueba=tabla-convocatorias] tbody tr', (trs) => trs.map((tr) => {
  const barra = tr.querySelector('progress');
  return {
    nombre: (tr.querySelector('.br-tabla__principal') || tr).textContent.replace(/\s+/g, ' ').trim(),
    texto: tr.innerText.replace(/\s+/g, ' ').trim(),
    estados: [...tr.querySelectorAll('.br-tabla__estado .br-etiqueta')].map((e) => e.textContent.trim()),
    acciones: [...tr.querySelectorAll('button, a')].map((b) => b.textContent.replace(/\s+/g, ' ').trim()),
    cupos: (tr.querySelector('[data-etiqueta=Cupos]') || tr).innerText.replace(/\s+/g, ' ').trim(),
    barra: barra ? { valor: barra.value, max: barra.max, etiqueta: barra.getAttribute('aria-label') || '' } : null,
  };
}));

const acciones = (page) => page.$$eval('main a, main button', (els) =>
  els.map((e) => ({ texto: e.textContent.replace(/\s+/g, ' ').trim(), href: e.getAttribute('href') || '', clase: e.className })));

module.exports = [
  // Migas y un único h1 en todos los estados
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
  // Datos de ejemplo: nunca nombres del equipo
  ...ESTADOS.map((estado) => ({
    estado,
    nombre: 'sin nombres del equipo en los datos de ejemplo',
    prueba: async (page) => {
      const t = await texto(page, 'body');
      const m = t.match(/\b(Samuel|Santiago|Mariana|Dylan)\b/);
      exige(!m, `aparece «${m && m[0]}»`);
    },
  })),

  // ---------- Lista ----------
  ...CON_TABLA.map((estado) => ({
    estado,
    nombre: 'tabla con convocatoria (y su beneficio), fechas, cupos usados / totales, pagos y estado',
    prueba: async (page) => {
      const h1 = await texto(page, 'main h1');
      exige(h1.startsWith('Convocatorias'), `h1: «${h1}»`);
      const ths = await page.$$eval('[data-prueba=tabla-convocatorias] thead th', (t) => t.map((x) => x.innerText.trim()));
      for (const c of COLUMNAS) exige(ths.includes(c), `falta la columna «${c}»: ${ths.join(' | ')}`);
      const fs = await filas(page);
      exige(fs.length >= 5, `solo hay ${fs.length} filas`);
      for (const f of fs) {
        exige(BENEFICIOS.some((b) => f.texto.includes(b)), `${f.nombre} no dice su beneficio: «${f.texto}»`);
        exige(/\d+ de \d+/.test(f.cupos), `${f.nombre} no muestra «N de M» cupos: «${f.cupos}»`);
        exige(f.barra, `${f.nombre} no tiene barra <progress> de cupos`);
        exige(f.cupos.startsWith(`${f.barra.valor} de ${f.barra.max}`), `${f.nombre}: la barra (${f.barra.valor}/${f.barra.max}) no coincide con «${f.cupos}»`);
        exige(f.barra.etiqueta.includes(f.nombre), `la barra de ${f.nombre} no dice de qué convocatoria es: «${f.barra.etiqueta}»`);
        exige(/\d{1,2} [a-z]{3}/.test(f.texto), `${f.nombre} sin fechas: «${f.texto}»`);
        exige(f.estados.length === 1 && ESTADOS_BD.includes(f.estados[0]), `${f.nombre} estado: ${f.estados.join(' | ')}`);
      }
    },
  })),
  ...CON_TABLA.map((estado) => ({
    estado,
    nombre: 'a 390 px la tabla de convocatorias cabe sin desplazarse (filas apiladas)',
    prueba: async (page) => {
      await page.setViewportSize({ width: 390, height: 844 });
      const r = await page.$eval('[data-prueba=tabla-convocatorias]', (tabla) => {
        const env = tabla.closest('.br-tabla-envoltura') || tabla.parentElement;
        return { scroll: env.scrollWidth, cliente: env.clientWidth };
      });
      exige(r.scroll <= r.cliente, `la tabla se desplaza dentro de su caja: ${r.scroll} > ${r.cliente}`);
    },
  })),
  {
    estado: 'lista',
    nombre: 'los 4 estados de la base (Activa, Inactiva, Cerrada, Cierre anticipado) y los datos de las vistas aprobadas',
    prueba: async (page) => {
      const fs = await filas(page);
      const estados = fs.flatMap((f) => f.estados);
      for (const e of ESTADOS_BD) exige(estados.includes(e), `ninguna fila está «${e}»: ${[...new Set(estados)].join(' | ')}`);
      const de = (nombre) => fs.find((f) => f.nombre === nombre);
      for (const n of ['Alimentación 2026-2', 'Plan de datos 2026-2', 'Tarjeta cívica 2026-2']) {
        exige(de(n), `no está «${n}»: ${fs.map((f) => f.nombre).join(' | ')}`);
        exige(de(n).estados[0] === 'Activa', `«${n}» no está Activa: ${de(n).estados[0]}`);
      }
      const ali = de('Alimentación 2026-2');
      for (const dato of ['Apoyo de alimentación', '25 de 25', '$ 180.000', '6 pagos', '6 meses']) {
        exige(ali.texto.includes(dato), `falta «${dato}» en Alimentación 2026-2: «${ali.texto}»`);
      }
      exige(fs.some((f) => /2026-1$/.test(f.nombre) && f.estados[0] === 'Cerrada'), 'no hay una convocatoria «2026-1» Cerrada');
    },
  },
  {
    estado: 'lista',
    nombre: 'el cierre anticipado muestra su motivo',
    prueba: async (page) => {
      const fs = await filas(page);
      const anticipadas = fs.filter((f) => f.estados[0] === 'Cierre anticipado');
      exige(anticipadas.length >= 1, 'no hay filas «Cierre anticipado»');
      for (const f of anticipadas) exige(/motivo/i.test(f.texto), `${f.nombre} no muestra el motivo del cierre: «${f.texto}»`);
    },
  },
  {
    estado: 'lista',
    nombre: 'acciones por estado: abiertas (Activa, Inactiva) se editan y cierran; las cerradas no',
    prueba: async (page) => {
      const fs = await filas(page);
      for (const f of fs) {
        const tiene = (t) => f.acciones.some((a) => a.startsWith(t));
        const abierta = ['Activa', 'Inactiva'].includes(f.estados[0]);
        exige(tiene('Editar') === abierta, `${f.nombre} (${f.estados[0]}) ${abierta ? 'no tiene' : 'tiene'} «Editar»`);
        exige(tiene('Cerrar convocatoria') === abierta, `${f.nombre} (${f.estados[0]}) ${abierta ? 'no tiene' : 'tiene'} «Cerrar convocatoria»`);
      }
    },
  },
  {
    estado: 'lista',
    nombre: '«Reporte de aprobados» enlaza al diálogo de la vista 04 y «Nueva convocatoria» al formulario',
    prueba: async (page) => {
      const a = await acciones(page);
      const reporte = a.filter((x) => x.texto.startsWith('Reporte de aprobados'));
      exige(reporte.length >= 1, `falta «Reporte de aprobados»: ${a.map((x) => x.texto).join(' | ')}`);
      for (const r of reporte) exige(r.href === REPORTE, `«Reporte de aprobados» enlaza a «${r.href}», debe ser ${REPORTE}`);
      const nueva = a.find((x) => x.texto.startsWith('Nueva convocatoria'));
      exige(nueva, 'falta «Nueva convocatoria»');
      exige(nueva.href === 'crear-editar.html', `«Nueva convocatoria» enlaza a «${nueva.href}»`);
    },
  },
  {
    estado: 'lista',
    nombre: 'el menú «Más acciones» abierto dice para quién es y que cerrar no deja postularse más',
    prueba: async (page) => {
      const r = await page.$eval('[data-prueba=acciones-fila]', (m) => ({
        abierto: m.open,
        etiqueta: (m.querySelector('summary') || m).getAttribute('aria-label') || '',
        texto: m.innerText.replace(/\s+/g, ' '),
      }));
      exige(r.abierto, 'el menú [data-prueba=acciones-fila] no está abierto');
      exige(/^Más acciones para /.test(r.etiqueta), `el botón del menú no dice para cuál es: «${r.etiqueta}»`);
      exige(/Cerrar convocatoria/.test(r.texto) && /postular/i.test(r.texto), `menú: «${r.texto}»`);
    },
  },

  // ---------- Crear o editar ----------
  {
    estado: 'crear-editar',
    nombre: 'formulario con los 9 campos de la tabla convocatorias, cada uno con su label',
    prueba: async (page) => {
      const campos = await page.$$eval('main form :is(input, select, textarea)', (els) => els
        .filter((e) => e.type !== 'hidden')
        .map((e) => ({
          etiqueta: (e.labels && e.labels[0] ? e.labels[0].innerText : '').replace(/\s+/g, ' ').trim(),
          tipo: e.tagName.toLowerCase() === 'input' ? e.type : e.tagName.toLowerCase(),
          valor: e.value,
          opciones: e.tagName === 'SELECT' ? [...e.options].map((o) => o.text.trim()) : null,
        })));
      exige(campos.every((x) => x.etiqueta), 'hay un campo sin label');
      const campo = (c) => campos.find((x) => x.etiqueta === c || x.etiqueta.startsWith(`${c} `) || x.etiqueta.startsWith(`${c}(`));
      for (const c of CAMPOS) exige(campo(c), `falta el campo «${c}» con label: ${campos.map((x) => x.etiqueta).join(' | ')}`);
      // Nada que la tabla no guarde: solo los 9 campos y el estado inicial (Activa o Inactiva)
      const sobran = campos.filter((x) => x.tipo !== 'radio' && !CAMPOS.some((c) => campo(c) === x));
      exige(sobran.length === 0, `campos que la tabla convocatorias no guarda: ${sobran.map((x) => x.etiqueta).join(' | ')}`);
      const radios = campos.filter((x) => x.tipo === 'radio').map((x) => x.etiqueta.split(' ')[0]);
      exige(radios.join(',') === 'Activa,Inactiva', `estado inicial: ${radios.join(' | ')} (una convocatoria nueva nace Activa o Inactiva)`);
      exige(campo('Beneficio').tipo === 'select', 'Beneficio no es una lista');
      exige(campo('Beneficio').opciones.join(',') === BENEFICIOS.join(','), `opciones de Beneficio: ${campo('Beneficio').opciones.join(' | ')}`);
      exige(campo('Fecha de inicio').tipo === 'date' && campo('Fecha de cierre').tipo === 'date', 'las fechas no son type=date');
      exige(campo('Fecha de cierre').valor > campo('Fecha de inicio').valor, 'la fecha de cierre debe ser posterior a la de inicio');
      for (const c of ['Cupos', 'Número de pagos', 'Duración en meses']) exige(campo(c).tipo === 'number', `${c} no es type=number`);
      exige(campo('Requisitos').tipo === 'textarea', 'Requisitos no es un textarea');
      exige(campo('Requisitos').valor.includes('Certificado de EPS'), `Requisitos no menciona «Certificado de EPS»: «${campo('Requisitos').valor}»`);
      const botones = await page.$$eval('main form button', (b) => b.map((x) => x.textContent.trim()));
      exige(botones.includes('Guardar convocatoria'), `botones del formulario: ${botones.join(' | ')}`);
    },
  },
  {
    estado: 'crear-editar',
    nombre: 'resume lo que recibe cada aprendiz (6 pagos de $ 180.000) y aclara qué pasa al editar',
    prueba: async (page) => {
      const r = await texto(page, '[data-prueba=resumen-pagos]');
      for (const dato of ['6 pagos', '$ 180.000', '$ 1.080.000', '6 meses']) exige(r.includes(dato), `falta «${dato}» en el resumen: «${r}»`);
      const t = await texto(page, 'main');
      exige(/aprobadas/i.test(t) && /cupos/i.test(t), 'no aclara que los cupos no bajan de las postulaciones aprobadas');
      exige(/ya aprobados/i.test(t), 'no aclara que cambiar los valores no afecta a los ya aprobados');
    },
  },

  // ---------- Cerrar ----------
  {
    estado: 'cerrar',
    nombre: 'diálogo con role=dialog, aria-modal y título «Cerrar…», sobre la lista',
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
      exige(/^Cerrar /.test(d.titulo), `título del diálogo: «${d.titulo}»`);
      exige(await page.$('.br-dialogo-capa [role=dialog]'), 'el diálogo no está dentro de .br-dialogo-capa');
    },
  },
  {
    estado: 'cerrar',
    nombre: 'distingue cierre normal («Cerrada») de cierre anticipado, que exige un motivo en un textarea',
    prueba: async (page) => {
      const d = await page.$eval('[role=dialog]', (el) => {
        const ta = [...el.querySelectorAll('textarea')];
        return {
          texto: el.innerText.replace(/\s+/g, ' '),
          motivos: ta.map((t) => ({
            etiqueta: (t.labels && t.labels[0] ? t.labels[0].innerText : '').replace(/\s+/g, ' ').trim(),
            requerido: t.required,
          })),
          botones: [...el.querySelectorAll('button')].map((b) => ({ texto: b.textContent.trim(), clase: b.className })),
        };
      });
      exige(/cierre normal/i.test(d.texto) && d.texto.includes('Cerrada'), `no explica el cierre normal («Cerrada»): «${d.texto}»`);
      exige(d.texto.includes('Cierre anticipado'), 'no menciona «Cierre anticipado»');
      exige(d.motivos.length === 1, `hay ${d.motivos.length} textarea en el diálogo, debe haber 1`);
      exige(/motivo/i.test(d.motivos[0].etiqueta), `el textarea no es del motivo: «${d.motivos[0].etiqueta}»`);
      exige(d.motivos[0].requerido, 'el motivo del cierre anticipado no es obligatorio (required)');
      const cerrar = d.botones.find((b) => b.texto === 'Cerrar convocatoria');
      exige(cerrar, `botones: ${d.botones.map((b) => b.texto).join(' | ')}`);
      exige(/br-boton--peligro/.test(cerrar.clase), '«Cerrar convocatoria» no es de peligro');
      exige(d.botones.some((b) => b.texto === 'Cancelar'), 'falta «Cancelar»');
    },
  },

  // ---------- Vacío ----------
  {
    estado: 'vacio',
    nombre: '«Aún no hay convocatorias. Crea la primera.» con el botón «Nueva convocatoria» y sin tabla',
    prueba: async (page) => {
      const t = await texto(page, '.br-vacio');
      exige(t.includes('Aún no hay convocatorias'), `vacío: «${t}»`);
      exige(t.includes('Crea la primera'), `vacío: «${t}»`);
      const a = await acciones(page);
      const nueva = a.find((x) => x.texto.startsWith('Nueva convocatoria'));
      exige(nueva && nueva.href === 'crear-editar.html', `falta «Nueva convocatoria» hacia crear-editar.html: ${a.map((x) => x.texto).join(' | ')}`);
      exige(!(await page.$('main table')), 'el estado vacío no debe tener tabla');
    },
  },
];
