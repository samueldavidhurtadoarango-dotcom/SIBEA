// Pruebas propias de la vista 01-mi-proceso (spec 002 §5 «Vista 1», plan tarea 2).
const fs = require('fs');
const path = require('path');

const texto = (page, selector = 'body') =>
  page.$eval(selector, (el) => el.innerText.replace(/\s+/g, ' '));

const exige = (cond, mensaje) => {
  if (!cond) throw new Error(mensaje);
};

module.exports = [
  {
    estado: 'seleccionado-con-retraso',
    nombre: 'CA-1 resultado y aviso de retraso visibles sin desplazarse a 390×844',
    prueba: async (page) => {
      await page.setViewportSize({ width: 390, height: 844 });
      for (const sel of ['[data-prueba=resultado]', '[data-prueba=aviso-retraso]']) {
        const fondo = await page.$eval(sel, (el) => el.getBoundingClientRect().bottom);
        exige(fondo <= 844, `${sel} termina en ${Math.round(fondo)} px (debe ser ≤ 844)`);
      }
    },
  },
  {
    estado: 'seleccionado-con-retraso',
    nombre: 'aviso naranja con mes y fecha del retraso',
    prueba: async (page) => {
      const clase = await page.$eval('[data-prueba=aviso-retraso]', (el) => el.className);
      exige(/br-aviso--naranja/.test(clase), 'el aviso de retraso no usa .br-aviso--naranja');
      const t = await texto(page, '[data-prueba=aviso-retraso]');
      exige(/El pago de noviembre va retrasado\./.test(t), `texto del aviso: «${t}»`);
      exige(/Debía llegar el 15 nov 2026\./.test(t), `falta la fecha esperada: «${t}»`);
    },
  },
  {
    estado: 'seleccionado-con-retraso',
    nombre: 'la fuente deja el texto exacto para asignación suspendida',
    prueba: async (page, ctx) => {
      const fuente = fs.readFileSync(path.join(ctx.raiz, '_fuente', ctx.vista, `${ctx.estado}.html`), 'utf8');
      const comentarios = (fuente.match(/<!--[\s\S]*?-->/g) || []).join('\n');
      exige(comentarios.includes('Tu beneficio está suspendido: <motivo>'),
        'falta el comentario HTML con «Tu beneficio está suspendido: <motivo>»');
    },
  },
  {
    estado: 'seleccionado',
    nombre: 'CA-1 resumen de pagos: $ 360.000 recibidos · quedan 4 meses',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=resumen-pagos]');
      exige(t.includes('$ 360.000 recibidos'), `falta «$ 360.000 recibidos»: «${t}»`);
      exige(t.includes('quedan 4 meses'), `falta «quedan 4 meses»: «${t}»`);
      exige(t.includes('Pagos recibidos: 2 de 6'), `falta «Pagos recibidos: 2 de 6»: «${t}»`);
    },
  },
  {
    estado: 'seleccionado',
    nombre: 'etiqueta «Fuiste seleccionada», pasos con fecha y tabla «Mis pagos»',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=resultado]');
      exige(/Fuiste seleccionad[ao]/.test(t), 'falta la etiqueta «Fuiste seleccionado/a»');
      const pasos = await page.$$eval('.br-pasos__paso', (ps) => ps.map((p) => p.innerText.replace(/\s+/g, ' ')));
      exige(pasos.length === 3, `hay ${pasos.length} pasos, deben ser 3`);
      ['Enviada', 'Validada', 'Aprobada'].forEach((n, i) => {
        exige(pasos[i].includes(n) && /\d{1,2} \w{3} \d{4}/.test(pasos[i]), `paso ${i + 1}: «${pasos[i]}»`);
      });
      const cab = await page.$$eval('table th', (ths) => ths.map((th) => th.innerText.trim()));
      exige(cab.join('|') === 'Mes|Fecha esperada|Valor|Estado', `columnas de «Mis pagos»: ${cab.join(', ')}`);
    },
  },
  {
    estado: 'seleccionado',
    nombre: 'postulaciones anteriores plegadas, con un beneficio de 1 solo pago',
    prueba: async (page) => {
      const d = await page.$eval('details[data-prueba=postulaciones-anteriores]', (el) => ({
        abierto: el.open,
        resumen: el.querySelector('summary') ? el.querySelector('summary').textContent : '',
        texto: el.textContent.replace(/\s+/g, ' '),
      }));
      exige(!d.abierto, 'el details debe estar plegado');
      exige(/Postulaciones anteriores/.test(d.resumen), `resumen: «${d.resumen}»`);
      exige(d.texto.includes('Recibiste tu pago'), 'falta el beneficio de 1 solo pago («Recibiste tu pago»)');
    },
  },
  {
    estado: 'en-revision',
    nombre: 'en revisión: Enviada hecha y Validada en curso',
    prueba: async (page) => {
      const t = await texto(page);
      exige(t.includes('Tu postulación está en revisión'), 'falta «Tu postulación está en revisión»');
      const hecho = await page.$$eval('.br-pasos__paso--hecho', (ps) => ps.map((p) => p.innerText));
      const actual = await page.$$eval('.br-pasos__paso--actual', (ps) => ps.map((p) => p.innerText));
      exige(hecho.length === 1 && /Enviada/.test(hecho[0]), `pasos hechos: ${hecho.join(' | ')}`);
      exige(actual.length === 1 && /Validada/.test(actual[0]), `paso en curso: ${actual.join(' | ')}`);
    },
  },
  {
    estado: 'no-seleccionado',
    nombre: 'CA-2 mensaje amable sin detalles internos',
    prueba: async (page) => {
      const t = await texto(page);
      exige(/no fuiste seleccionad/i.test(t), 'falta «no fuiste seleccionado/a»');
      exige(/próximas convocatorias/i.test(t), 'falta la invitación a las «próximas convocatorias»');
      exige(!/cupo/i.test(t), 'menciona «cupo»');
      exige(!/puntaje/i.test(t), 'menciona «puntaje»');
    },
  },
  {
    estado: 'beneficio-terminado',
    nombre: 'beneficio terminado: los 6 pagos recibidos, resumen y tabla',
    prueba: async (page) => {
      const t = await texto(page);
      exige(t.includes('Recibiste los 6 pagos de tu beneficio'), 'falta «Recibiste los 6 pagos de tu beneficio»');
      exige(t.includes('Pagos recibidos: 6 de 6'), 'falta «Pagos recibidos: 6 de 6»');
      const filas = await page.$$eval('table tbody tr', (trs) => trs.map((tr) => tr.innerText));
      exige(filas.length === 6 && filas.every((f) => /Pagado/.test(f)), `la tabla debe tener 6 pagos «Pagado»: ${filas.length}`);
    },
  },
  {
    estado: 'sin-postulacion',
    nombre: 'sin postulación registrada',
    prueba: async (page) => {
      const t = await texto(page);
      exige(t.includes('Aún no tenemos una postulación tuya registrada'), 'falta el mensaje de sin postulación');
    },
  },
  {
    estado: 'documento-pedido',
    nombre: 'aviso azul con «Subir documento» hacia la vista 8',
    prueba: async (page) => {
      const r = await page.$eval('[data-prueba=aviso-documento]', (el) => {
        const accion = [...el.querySelectorAll('a, button')].find((a) => a.innerText.trim() === 'Subir documento');
        return {
          clase: el.className,
          texto: el.innerText.replace(/\s+/g, ' '),
          href: accion ? accion.getAttribute('href') || accion.getAttribute('formaction') || '' : null,
        };
      });
      exige(/br-aviso--azul/.test(r.clase), 'el aviso no usa .br-aviso--azul');
      exige(/Bienestar te pidió: /.test(r.texto), `texto del aviso: «${r.texto}»`);
      exige(r.href !== null, 'falta el enlace o botón «Subir documento»');
      exige(r.href === '../../08-subir-documento/html/elegir-archivo.html', `«Subir documento» apunta a «${r.href}»`);
      const acciones = await page.$$eval('main a, main button', (els) => els.map((e) => e.innerText.trim()));
      exige(acciones.length === 1, `«Subir documento» debe ser la única acción de la página: ${acciones.join(' | ')}`);
    },
  },
  {
    estado: 'cargando',
    nombre: 'cargando: esqueleto marcado como ocupado',
    prueba: async (page) => {
      const r = await page.evaluate(() => ({
        ocupado: !!document.querySelector('[aria-busy="true"]'),
        esqueletos: document.querySelectorAll('.br-esqueleto').length,
        texto: document.body.innerText,
      }));
      exige(r.ocupado, 'falta aria-busy="true"');
      exige(r.esqueletos >= 3, `hay ${r.esqueletos} bloques .br-esqueleto`);
      exige(/Cargando/.test(r.texto), 'falta el texto «Cargando…» para lectores de pantalla');
    },
  },
];
