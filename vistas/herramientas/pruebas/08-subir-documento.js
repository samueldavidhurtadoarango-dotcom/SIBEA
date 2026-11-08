// Pruebas propias de la vista 08-subir-documento (spec 002 §5 «Vista 8», plan tarea 3).
const texto = (page, selector = 'body') =>
  page.$eval(selector, (el) => el.innerText.replace(/\s+/g, ' '));

const exige = (cond, mensaje) => {
  if (!cond) throw new Error(mensaje);
};

const VOLVER = '../../01-mi-proceso/html/en-revision.html';

module.exports = [
  {
    estado: 'elegir-archivo',
    nombre: 'muestra el documento pedido y el mensaje de Bienestar',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=pedido]');
      exige(t.includes('Certificado de afiliación a la EPS'), `falta el documento pedido: «${t}»`);
      exige(/no se alcanza a leer/.test(t), `falta el mensaje del administrador: «${t}»`);
    },
  },
  {
    estado: 'elegir-archivo',
    nombre: 'zona de carga con formatos «PDF, JPG o PNG» y tamaño «5 MB»',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=zona]');
      exige(t.includes('PDF, JPG o PNG'), `falta «PDF, JPG o PNG»: «${t}»`);
      exige(t.includes('5 MB'), `falta «5 MB»: «${t}»`);
      const r = await page.$eval('[data-prueba=zona]', (el) => {
        const i = el.querySelector('input[type=file]');
        return { hay: !!i, accept: i ? i.getAttribute('accept') : '', nombre: i ? (i.labels.length > 0) : false };
      });
      exige(r.hay, 'falta <input type="file">');
      exige(/pdf/i.test(r.accept) && /jpe?g/i.test(r.accept) && /png/i.test(r.accept), `accept: «${r.accept}»`);
      exige(r.nombre, 'el campo de archivo no tiene <label>');
    },
  },
  {
    estado: 'subiendo',
    nombre: 'barra de progreso con valor a medias y nombre accesible',
    prueba: async (page) => {
      const r = await page.$eval('progress', (p) => ({
        valor: p.value, max: p.max, nombre: p.getAttribute('aria-label') || (p.labels && p.labels.length ? 'label' : ''),
      }));
      exige(r.valor > 0 && r.valor < r.max, `progress value=${r.valor} max=${r.max}`);
      exige(r.nombre, 'el <progress> no tiene nombre accesible');
      const t = await texto(page);
      exige(/Subiendo/.test(t), 'falta el texto «Subiendo»');
    },
  },
  {
    estado: 'enviado',
    nombre: 'confirmación y enlace de vuelta a «Mi proceso»',
    prueba: async (page) => {
      const t = await texto(page);
      exige(t.includes('Listo, Bienestar revisará tu documento'), 'falta «Listo, Bienestar revisará tu documento»');
      const hrefs = await page.$$eval('main a', (as) => as.map((a) => a.getAttribute('href')));
      exige(hrefs.includes(VOLVER), `no hay enlace a 01-mi-proceso: ${hrefs.join(' | ')}`);
    },
  },
  {
    estado: 'error',
    nombre: 'error claro con «Intentar de nuevo»',
    prueba: async (page) => {
      const a = await page.$eval('[role=alert]', (el) => el.innerText.replace(/\s+/g, ' '));
      exige(/5 MB/.test(a), `el mensaje de error no explica el límite: «${a}»`);
      const acciones = await page.$$eval('main a, main button', (els) => els.map((e) => e.innerText.trim()));
      exige(acciones.includes('Intentar de nuevo'), `falta «Intentar de nuevo»: ${acciones.join(' | ')}`);
    },
  },
];
