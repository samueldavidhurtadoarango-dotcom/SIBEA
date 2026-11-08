// Pruebas propias de la vista 05-pagos (spec 002 §5 «Vista 5», plan tarea 5, CA-6).
const texto = (page, selector = 'body') =>
  page.$eval(selector, (el) => el.innerText.replace(/\s+/g, ' ').trim());

const exige = (cond, mensaje) => {
  if (!cond) throw new Error(mensaje);
};

const visible = (page, selector) =>
  page.$eval(selector, (el) => {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none';
  });

const ESTADOS = ['lista', 'seleccion-multiple', 'confirmar-pago-masivo', 'pago-con-retraso',
  'avisar-retraso-uno', 'avisar-retraso-varios', 'exito', 'vacio'];
const DIALOGOS = ['confirmar-pago-masivo', 'pago-con-retraso', 'avisar-retraso-uno', 'avisar-retraso-varios'];
const CON_TABLA = ESTADOS.filter((e) => e !== 'vacio');
const COLUMNAS = ['Aprendiz', 'Beneficio', 'Pago', 'Valor', 'Fecha esperada', 'Estado'];
const LUISA = 'Luisa Fernanda';

// Diálogo abierto: role=dialog, aria-modal y un título que exista
const dialogo = (page) => page.evaluate(() => {
  const d = document.querySelectorAll('[role=dialog]');
  if (d.length !== 1) return { n: d.length };
  const id = d[0].getAttribute('aria-labelledby');
  const titulo = id ? document.getElementById(id) : null;
  return {
    n: 1,
    modal: d[0].getAttribute('aria-modal') === 'true',
    titulo: titulo ? titulo.innerText.trim() : '',
    texto: d[0].innerText.replace(/\s+/g, ' '),
    textareas: d[0].querySelectorAll('textarea').length,
    radios: [...d[0].querySelectorAll('input[type=radio]')].map((r) => ({
      etiqueta: (r.labels && r.labels[0] ? r.labels[0].innerText : '').replace(/\s+/g, ' ').trim(),
      marcado: r.checked,
    })),
  };
});

module.exports = [
  // Migas y título de la página en todos los estados
  ...ESTADOS.map((estado) => ({
    estado,
    nombre: 'migas Dashboard > Pagos y título «Pagos»',
    prueba: async (page) => {
      const actual = await texto(page, 'nav[aria-label="Ruta"] [aria-current="page"]');
      exige(actual === 'Pagos', `miga actual: «${actual}»`);
      const h1 = await texto(page, 'main h1');
      exige(h1.startsWith('Pagos'), `h1: «${h1}»`);
    },
  })),
  // Columnas de la spec en todas las tablas
  ...CON_TABLA.map((estado) => ({
    estado,
    nombre: 'tabla con aprendiz, beneficio, pago N de M, valor, fecha esperada y estado',
    prueba: async (page) => {
      const ths = await page.$$eval('[data-prueba=tabla-pagos] thead th', (t) => t.map((x) => x.innerText.trim()));
      for (const c of COLUMNAS) exige(ths.includes(c), `falta la columna «${c}»: ${ths.join(' | ')}`);
      const t = await texto(page, '[data-prueba=tabla-pagos] tbody');
      exige(/\d+ de \d+/.test(t), `no aparece «pago N de M»: «${t.slice(0, 120)}»`);
      exige(t.includes('$ 180.000'), 'no aparece el valor $ 180.000');
    },
  })),
  // En el celular la tabla se apila: nada se desplaza de lado, ni siquiera dentro de la envoltura
  ...CON_TABLA.map((estado) => ({
    estado,
    nombre: 'a 390 px la tabla cabe sin desplazarse (filas apiladas)',
    prueba: async (page) => {
      await page.setViewportSize({ width: 390, height: 844 });
      const r = await page.$eval('[data-prueba=tabla-pagos]', (tabla) => {
        const env = tabla.closest('.br-tabla-envoltura') || tabla.parentElement;
        return { scroll: env.scrollWidth, cliente: env.clientWidth, thead: getComputedStyle(tabla.querySelector('thead')).position };
      });
      exige(r.scroll <= r.cliente, `la tabla se desplaza dentro de su caja: ${r.scroll} > ${r.cliente}`);
    },
  })),
  // Los diálogos van sobre la lista atenuada, con role=dialog, aria-modal y título
  ...DIALOGOS.map((estado) => ({
    estado,
    nombre: 'diálogo con role=dialog, aria-modal y título, sobre la lista',
    prueba: async (page) => {
      const d = await dialogo(page);
      exige(d.n === 1, `hay ${d.n} [role=dialog], debe haber 1`);
      exige(d.modal, 'el diálogo no tiene aria-modal="true"');
      exige(d.titulo.length > 5, `título del diálogo: «${d.titulo}»`);
      exige(await page.$('.br-dialogo-capa [role=dialog]'), 'el diálogo no está dentro de .br-dialogo-capa');
      exige(await page.$('main [data-prueba=tabla-pagos]'), 'detrás del diálogo no está la lista de pagos');
    },
  })),
  {
    estado: 'lista',
    nombre: 'botón «Seleccionar todos los retrasados» y al menos una fila «Retrasado»',
    prueba: async (page) => {
      const botones = await page.$$eval('main button', (bs) => bs.map((b) => b.innerText.replace(/\s+/g, ' ').trim()));
      exige(botones.some((b) => b.startsWith('Seleccionar todos los retrasados')), `botones: ${botones.join(' | ')}`);
      const retrasadas = await page.$$eval('[data-prueba=tabla-pagos] tbody tr', (trs) =>
        trs.filter((tr) => [...tr.querySelectorAll('.br-etiqueta')].some((e) => e.innerText.trim() === 'Retrasado')).length);
      exige(retrasadas >= 1, 'ninguna fila tiene la etiqueta «Retrasado»');
    },
  },
  {
    estado: 'lista',
    nombre: 'Luisa Fernanda: pago de noviembre (15 nov 2026) retrasado',
    prueba: async (page) => {
      const fila = await page.$$eval('[data-prueba=tabla-pagos] tbody tr', (trs, nombre) => {
        const tr = trs.find((x) => x.innerText.includes(nombre));
        return tr ? tr.innerText.replace(/\s+/g, ' ') : '';
      }, LUISA);
      exige(fila, `no está la fila de ${LUISA}`);
      for (const dato of ['Apoyo de alimentación', '3 de 6', '$ 180.000', '15 nov 2026', 'Retrasado']) {
        exige(fila.includes(dato), `falta «${dato}» en la fila de Luisa: «${fila}»`);
      }
    },
  },
  {
    estado: 'lista',
    nombre: 'acciones de una fila: registrar pago, novedad (5 tipos) y «Anular pago» de peligro, que no borra',
    prueba: async (page) => {
      const r = await page.$eval('[data-prueba=acciones-fila]', (m) => ({
        texto: m.innerText.replace(/\s+/g, ' '),
        anular: [...m.querySelectorAll('button')].filter((b) => b.innerText.trim() === 'Anular pago').map((b) => b.className),
      }));
      exige(r.anular.length === 1, 'no hay un botón «Anular pago» en el menú de acciones');
      exige(/br-boton--peligro/.test(r.anular[0]), `«Anular pago» no es de peligro: «${r.anular[0]}»`);
      exige(/motivo/i.test(r.texto) && /no se borra/i.test(r.texto), `el menú no dice que anular pide motivo y no borra: «${r.texto}»`);
      for (const tipo of ['Retraso de pago', 'Suspensión', 'Reactivación', 'Cancelación', 'Otra']) {
        exige(r.texto.includes(tipo), `falta la novedad «${tipo}»: «${r.texto}»`);
      }
      const registrar = await page.$$eval('[data-prueba=tabla-pagos] button', (bs) => bs.filter((b) => /^Registrar pago/.test(b.innerText.trim())).length);
      exige(registrar >= 1, 'ninguna fila tiene «Registrar pago»');
    },
  },
  {
    estado: 'seleccion-multiple',
    nombre: 'barra de selección: 23 pagos, $ 4.140.000, «Marcar como pagados» y «Avisar retraso»',
    prueba: async (page) => {
      exige(await visible(page, '[data-prueba=barra-seleccion]'), '[data-prueba=barra-seleccion] no se ve');
      const t = await texto(page, '[data-prueba=barra-seleccion]');
      exige(t.includes('23 pagos') && t.includes('$ 4.140.000'), `barra: «${t}»`);
      const botones = await page.$$eval('[data-prueba=barra-seleccion] button', (bs) => bs.map((b) => b.innerText.trim()));
      exige(botones.includes('Marcar como pagados'), `falta «Marcar como pagados»: ${botones.join(' | ')}`);
      exige(botones.includes('Avisar retraso'), `falta «Avisar retraso»: ${botones.join(' | ')}`);
      const marcadas = await page.$$eval('[data-prueba=tabla-pagos] tbody input[type=checkbox]:checked', (c) => c.length);
      exige(marcadas >= 2, `solo hay ${marcadas} filas marcadas`);
    },
  },
  {
    estado: 'confirmar-pago-masivo',
    nombre: 'CA-6 resumen «Vas a registrar N pagos por $ X», fecha de pago y cuántos van con retraso',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=resumen]');
      exige(/Vas a registrar \d+ pagos por \$ [\d.]+/.test(t), `resumen: «${t}»`);
      exige(t.includes('Vas a registrar 23 pagos por $ 4.140.000'), `resumen: «${t}»`);
      const fecha = await page.$eval('[role=dialog]', (d) => !!d.querySelector('input[type=date]'));
      exige(fecha, 'el diálogo no pide la fecha de pago (input type=date)');
      const d = await dialogo(page);
      exige(/3 de estos pagos van con retraso/.test(d.texto), `no dice cuántos van con retraso: «${d.texto}»`);
    },
  },
  {
    estado: 'pago-con-retraso',
    nombre: 'opciones «Mismo motivo para todos» y «Un motivo para cada uno»',
    prueba: async (page) => {
      const d = await dialogo(page);
      const etiquetas = d.radios.map((r) => r.etiqueta);
      exige(etiquetas.some((e) => e.startsWith('Mismo motivo para todos')), `opciones: ${etiquetas.join(' | ')}`);
      exige(etiquetas.some((e) => e.startsWith('Un motivo para cada uno')), `opciones: ${etiquetas.join(' | ')}`);
      exige(d.radios.filter((r) => r.marcado).length === 1, 'debe haber exactamente una opción marcada');
      exige(d.textareas >= 1, 'no hay campo para el motivo');
    },
  },
  {
    estado: 'avisar-retraso-uno',
    nombre: 'aviso para una sola aprendiz (Luisa Fernanda) con un motivo',
    prueba: async (page) => {
      const d = await dialogo(page);
      exige(d.titulo.includes(LUISA) || d.texto.includes(LUISA), `el diálogo no nombra a Luisa: «${d.texto}»`);
      exige(d.textareas === 1, `el diálogo tiene ${d.textareas} textarea, debe tener 1`);
      exige(/15 nov 2026/.test(d.texto), 'no dice la fecha esperada del pago');
    },
  },
  {
    estado: 'avisar-retraso-varios',
    nombre: '«Se avisará a N aprendices» con un solo motivo para todos',
    prueba: async (page) => {
      const d = await dialogo(page);
      exige(/Se avisará a \d+ aprendices/.test(d.texto), `el diálogo no dice a cuántos: «${d.texto}»`);
      exige(d.textareas === 1, `el diálogo tiene ${d.textareas} textarea, debe tener 1`);
    },
  },
  {
    estado: 'exito',
    nombre: 'mensaje de éxito con cuántos pagos se registraron',
    prueba: async (page) => {
      const t = await texto(page, '[role=status]');
      exige(/Registraste 23 pagos/.test(t), `mensaje: «${t}»`);
      const pendientes = await page.$$eval('[data-prueba=tabla-pagos] tbody .br-etiqueta', (e) =>
        e.filter((x) => ['Pendiente', 'Retrasado'].includes(x.innerText.trim())).length);
      exige(pendientes === 0, `aún hay ${pendientes} filas pendientes o retrasadas`);
    },
  },
  {
    estado: 'vacio',
    nombre: 'estado vacío explica por qué no hay pagos y qué hacer',
    prueba: async (page) => {
      exige(await visible(page, '.br-vacio'), '.br-vacio no se ve');
      const t = await texto(page, '.br-vacio');
      exige(/No hay pagos/.test(t), `texto del vacío: «${t}»`);
      exige(!(await page.$('[data-prueba=tabla-pagos]')), 'el estado vacío no debe tener tabla');
      const acciones = await page.$$eval('.br-vacio a, .br-vacio button', (els) => els.length);
      exige(acciones >= 1, 'el estado vacío no ofrece ninguna acción');
    },
  },
];
