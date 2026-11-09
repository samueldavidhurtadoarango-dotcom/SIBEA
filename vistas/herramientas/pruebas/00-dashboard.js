// Pruebas propias de la vista 00-dashboard (spec 002 §5 «Vista 0», plan tarea 9).
// Página de inicio mínima del administrador: tareas pendientes y 3 a 4 cifras, sin gráficas.
const texto = (page, selector = 'body') =>
  page.$eval(selector, (el) => el.innerText.replace(/\s+/g, ' ').trim());

const exige = (cond, mensaje) => {
  if (!cond) throw new Error(mensaje);
};

const ESTADOS = ['con-pendientes', 'todo-al-dia', 'sin-convocatoria-activa'];

module.exports = [
  ...ESTADOS.map((estado) => ({
    estado,
    nombre: 'menú activo Dashboard, miga «Dashboard» y título',
    prueba: async (page) => {
      const activo = await page.$$eval('.br-lateral [aria-current="page"]', (e) => e.map((x) => x.innerText.trim()));
      exige(activo.some((t) => t.startsWith('Dashboard')), `opción activa: «${activo.join(' | ')}»`);
      const miga = await texto(page, 'nav[aria-label="Ruta"] [aria-current="page"]');
      exige(miga === 'Dashboard', `miga actual: «${miga}»`);
      const h1 = await texto(page, 'main h1');
      exige(h1.length > 0, 'falta el h1');
    },
  })),
  ...ESTADOS.map((estado) => ({
    estado,
    nombre: 'sin gráficas: ni canvas ni svg mayores de 24 px',
    prueba: async (page) => {
      exige((await page.$$('canvas')).length === 0, 'hay un canvas');
      const grandes = await page.$$eval('svg', (s) =>
        s.map((x) => x.getBoundingClientRect()).filter((r) => r.width > 24.5 || r.height > 24.5).length);
      exige(grandes === 0, `${grandes} svg miden más de 24 px`);
    },
  })),
  ...ESTADOS.map((estado) => ({
    estado,
    nombre: 'tiene 3 o 4 cifras',
    prueba: async (page) => {
      const n = (await page.$$('[data-prueba=cifra]')).length;
      exige(n >= 3 && n <= 4, `hay ${n} cifras`);
    },
  })),
  {
    estado: 'con-pendientes',
    nombre: 'franja «Tareas pendientes» con 3 enlaces con destino',
    prueba: async (page) => {
      exige(await page.$('[data-prueba=pendientes]'), 'falta [data-prueba=pendientes]');
      const t = await texto(page, '[data-prueba=pendientes]');
      exige(t.includes('Tareas pendientes'), 'falta el título «Tareas pendientes»');
      const enlaces = await page.$$eval('[data-prueba=pendientes] a', (a) =>
        a.map((x) => ({ href: x.getAttribute('href'), texto: x.innerText.replace(/\s+/g, ' ').trim() })));
      exige(enlaces.length === 3, `hay ${enlaces.length} enlaces`);
      for (const e of enlaces) exige(e.href && e.href !== '#', `enlace sin destino: «${e.texto}»`);
      const junto = enlaces.map((e) => e.texto).join(' | ');
      exige(/12.*postulaciones.*sin confirmar/.test(junto), `falta postulaciones sin confirmar: ${junto}`);
      exige(/2.*documentos.*recibidos/.test(junto), `falta documentos recibidos: ${junto}`);
      exige(/3.*pagos.*retrasados/.test(junto), `falta pagos retrasados: ${junto}`);
    },
  },
  {
    estado: 'con-pendientes',
    nombre: 'no dice «Todo al día»',
    prueba: async (page) => {
      exige(!(await texto(page)).includes('Todo al día'), 'dice «Todo al día» habiendo pendientes');
    },
  },
  {
    estado: 'todo-al-dia',
    nombre: 'dice «Todo al día» y no ofrece enlaces de pendientes',
    prueba: async (page) => {
      exige((await texto(page, 'main')).includes('Todo al día'), 'falta «Todo al día»');
      const n = await page.$$eval('[data-prueba=pendientes] a', (a) => a.length);
      exige(n === 0, `hay ${n} enlaces de pendientes`);
    },
  },
  {
    estado: 'sin-convocatoria-activa',
    nombre: 'dice «En el momento no hay convocatorias disponibles» en la franja y en la cifra, y enlaza a «Crear convocatoria»',
    prueba: async (page) => {
      const msg = 'En el momento no hay convocatorias disponibles';
      exige((await texto(page, '[data-prueba=pendientes]')).includes(msg), 'falta el mensaje en la franja de pendientes');
      exige(!(await texto(page, 'main')).includes('Todo al día'), 'dice «Todo al día» sin convocatorias');
      const enCifra = await page.$$eval('[data-prueba=cifra]', (c, m) => c.some((x) => x.innerText.replace(/\s+/g, ' ').includes(m)), msg);
      exige(enCifra, 'falta el mensaje en la cifra de cupos');
      const href = await page.$$eval('main a', (a) =>
        (a.find((x) => x.innerText.trim() === 'Crear convocatoria') || {}).href || null);
      exige(href && href.includes('07-convocatorias/html/crear-editar.html'), `enlace «Crear convocatoria»: ${href}`);
    },
  },
];
