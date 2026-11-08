// Pruebas propias de la vista 01b-avisos (spec 002 §5 «Vista 1b», plan tarea 2).
// Es el panel desplegable de la campanita, abierto sobre «Mi proceso».
const exige = (cond, mensaje) => {
  if (!cond) throw new Error(mensaje);
};

const panelAbierto = {
  nombre: 'panel de avisos abierto desde la campanita',
  prueba: async (page) => {
    const r = await page.evaluate(() => {
      const boton = document.querySelector('.br-campanita');
      const id = boton && boton.getAttribute('aria-controls');
      const panel = id && document.getElementById(id);
      return {
        expandido: boton && boton.getAttribute('aria-expanded'),
        panel: !!panel,
        visible: !!panel && panel.getClientRects().length > 0,
        fondo: !!document.querySelector('main [data-prueba=resultado]'),
      };
    });
    exige(r.expandido === 'true', `aria-expanded de la campanita: ${r.expandido}`);
    exige(r.panel && r.visible, 'la campanita no apunta (aria-controls) a un panel visible');
    exige(r.fondo, 'falta la página «Mi proceso» de fondo');
  },
};

const panelDentroDeLaPantalla = {
  nombre: 'el panel cabe a lo ancho en el celular',
  prueba: async (page) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const c = await page.$eval('[data-prueba=panel-avisos]', (el) => {
      const r = el.getBoundingClientRect();
      return { izq: r.left, der: r.right };
    });
    exige(c.izq >= 8 && c.der <= 382, `el panel va de ${Math.round(c.izq)} a ${Math.round(c.der)} px`);
  },
};

module.exports = [
  { estado: 'con-avisos', ...panelAbierto },
  { estado: 'sin-avisos', ...panelAbierto },
  { estado: 'con-avisos', ...panelDentroDeLaPantalla },
  { estado: 'sin-avisos', ...panelDentroDeLaPantalla },
  {
    estado: 'con-avisos',
    nombre: 'avisos del más reciente al más antiguo, los no leídos con punto',
    prueba: async (page) => {
      const avisos = await page.$$eval('[data-prueba=panel-avisos] [data-prueba=aviso]', (els) =>
        els.map((el) => ({
          titulo: el.querySelector('.br-avisos__titulo') ? el.querySelector('.br-avisos__titulo').innerText.trim() : '',
          fecha: el.querySelector('time') ? el.querySelector('time').getAttribute('datetime') : '',
          noLeido: el.classList.contains('br-avisos__item--nuevo'),
          punto: !!el.querySelector('.br-avisos__punto'),
          texto: el.innerText,
        })));
      exige(avisos.length >= 4, `hay ${avisos.length} avisos, se esperan al menos 4`);
      const fechas = avisos.map((a) => a.fecha);
      exige(fechas.every(Boolean), 'cada aviso necesita <time datetime>');
      exige([...fechas].sort().reverse().join() === fechas.join(), `no están del más reciente al más antiguo: ${fechas.join(', ')}`);
      const titulos = avisos.map((a) => a.titulo).join(' | ');
      for (const t of ['Tu postulación fue aprobada', 'Se registró tu pago de octubre', 'Tu pago de noviembre va retrasado']) {
        exige(titulos.includes(t), `falta el aviso «${t}»: ${titulos}`);
      }
      const nuevos = avisos.filter((a) => a.noLeido);
      exige(nuevos.length >= 1, 'no hay avisos sin leer');
      exige(avisos.every((a) => a.punto === a.noLeido), 'solo los no leídos llevan punto');
      exige(nuevos.every((a) => /sin leer/i.test(a.texto)), 'los no leídos deben decir «sin leer» a lectores de pantalla');
      const contador = await page.$eval('.br-campanita__contador', (el) => el.textContent.trim());
      exige(contador === String(nuevos.length), `el contador dice ${contador} y hay ${nuevos.length} sin leer`);
    },
  },
  {
    estado: 'sin-avisos',
    nombre: 'sin avisos: «No tienes avisos nuevos» y campanita sin contador',
    prueba: async (page) => {
      const t = await page.$eval('[data-prueba=panel-avisos]', (el) => el.innerText);
      exige(t.includes('No tienes avisos nuevos'), 'falta «No tienes avisos nuevos»');
      const contador = await page.$('.br-campanita__contador');
      exige(!contador, 'la campanita no debe mostrar contador sin avisos nuevos');
    },
  },
];
