// Pruebas propias de la vista 03-detalle-postulacion (spec 002 §5 «Vista 3», plan tarea 4).
const texto = (page, selector = 'body') =>
  page.$eval(selector, (el) => el.innerText.replace(/\s+/g, ' '));

const exige = (cond, mensaje) => {
  if (!cond) throw new Error(mensaje);
};

const visible = (page, selector) =>
  page.$eval(selector, (el) => {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none';
  });

const deshabilitado = (page, selector) => page.$eval(selector, (el) => el.disabled === true);

const ESTADOS = ['pendiente', 'documento-pedido', 'documento-recibido', 'pedir-documento', 'validada',
  'aprobada', 'rechazada', 'sin-cupos', 'confirmar-accion', 'error'];
const NOMBRE = 'Daniela Mosquera Palacios';

module.exports = [
  // Contador de cupos siempre a la vista (todos menos «error»)
  ...ESTADOS.filter((e) => e !== 'error').map((estado) => ({
    estado,
    nombre: 'contador «Cupos: N de M» visible',
    prueba: async (page) => {
      exige(await visible(page, '[data-prueba=cupos]'), '[data-prueba=cupos] no se ve');
      const t = await texto(page, '[data-prueba=cupos]');
      exige(/Cupos: \d+ de \d+/.test(t), `texto del contador: «${t}»`);
    },
  })),
  // La postulación es la de Daniela, en la cabecera y en las migas
  ...ESTADOS.map((estado) => ({
    estado,
    nombre: 'migas Dashboard > Postulaciones > nombre',
    prueba: async (page) => {
      const t = await texto(page, 'nav[aria-label="Ruta"]');
      exige(t.includes('Dashboard') && t.includes('Postulaciones') && t.includes(NOMBRE), `migas: «${t}»`);
      const actual = await texto(page, 'nav[aria-label="Ruta"] [aria-current="page"]');
      exige(actual === NOMBRE, `miga actual: «${actual}»`);
    },
  })),
  // Orden obligatorio (spec §5 Vista 3): primero «Datos confirmados» (Validada), después «Aprobar».
  ...['pendiente', 'documento-pedido', 'documento-recibido', 'pedir-documento'].map((estado) => ({
    estado,
    nombre: 'orden: «Datos confirmados» deshabilitado mientras haya documentos sin validar',
    prueba: async (page) => {
      exige(await deshabilitado(page, '[data-prueba=confirmar-datos]'), '[data-prueba=confirmar-datos] no tiene disabled');
    },
  })),
  ...['pendiente', 'documento-pedido', 'documento-recibido', 'pedir-documento', 'sin-cupos'].map((estado) => ({
    estado,
    nombre: 'orden: «Aprobar» deshabilitado (no está Validada o no hay cupos)',
    prueba: async (page) => {
      exige(await deshabilitado(page, '[data-prueba=aprobar]'), '[data-prueba=aprobar] no tiene disabled');
    },
  })),
  {
    estado: 'validada',
    nombre: 'orden: «Aprobar» habilitado en Validada',
    prueba: async (page) => {
      exige(!(await deshabilitado(page, '[data-prueba=aprobar]')), '[data-prueba=aprobar] está deshabilitado');
    },
  },
  ...['aprobada', 'rechazada'].map((estado) => ({
    estado,
    nombre: 'orden: ya decidida, no hay «Aprobar» habilitado',
    prueba: async (page) => {
      const habilitados = await page.$$eval('[data-prueba=aprobar]', (bs) => bs.filter((b) => !b.disabled).length);
      exige(habilitados === 0, `hay ${habilitados} [data-prueba=aprobar] habilitado(s)`);
    },
  })),
  {
    estado: 'pendiente',
    nombre: 'CA-4 «Aprobar» deshabilitado mientras no esté Validada',
    prueba: async (page) => {
      exige(await deshabilitado(page, '[data-prueba=aprobar]'), '[data-prueba=aprobar] no tiene disabled');
    },
  },
  {
    estado: 'pendiente',
    nombre: 'el certificado de EPS está «No válido» con su motivo',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=documento-eps]');
      exige(/No válido/.test(t), `el documento de EPS no dice «No válido»: «${t}»`);
      exige(/no se alcanza a leer/i.test(t), `falta el motivo: «${t}»`);
      const pedir = await page.$$eval('button', (bs) => bs.map((b) => b.innerText.trim()));
      exige(pedir.includes('Pedir documento'), `falta «Pedir documento»: ${pedir.join(' | ')}`);
    },
  },
  {
    estado: 'pendiente',
    nombre: 'datos del formulario a la vista (nombre, documento, ficha, programa, dirección, EPS)',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=datos]');
      for (const dato of [NOMBRE, '1.034.567.218', '2879415', 'Gestión Administrativa', 'Dirección', 'EPS']) {
        exige(t.includes(dato), `falta «${dato}» en los datos: «${t}»`);
      }
    },
  },
  {
    estado: 'documento-pedido',
    nombre: 'CA-3b «Datos confirmados» deshabilitado y texto «esperando»',
    prueba: async (page) => {
      exige(await deshabilitado(page, '[data-prueba=confirmar-datos]'), '[data-prueba=confirmar-datos] no tiene disabled');
      const ok = await page.evaluate(() => {
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
          const n = walker.currentNode;
          if (/esperando/i.test(n.textContent)) {
            const r = n.parentElement.getBoundingClientRect();
            if (r.width > 0 && r.height > 0) return true;
          }
        }
        return false;
      });
      exige(ok, 'no hay un texto visible con «esperando»');
    },
  },
  {
    estado: 'documento-pedido',
    nombre: 'muestra la solicitud abierta y desde cuándo espera',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=solicitud]');
      exige(t.includes('Certificado de EPS'), `falta el tipo pedido: «${t}»`);
      exige(/\d{1,2} (oct|nov) 2026/.test(t), `falta la fecha de la solicitud: «${t}»`);
      exige(/hace \d+ días?/.test(t), `falta desde cuándo espera: «${t}»`);
    },
  },
  {
    estado: 'documento-recibido',
    nombre: 'el documento nuevo está resaltado y listo para revisar',
    prueba: async (page) => {
      const r = await page.$eval('[data-prueba=documento-nuevo]', (el) => ({
        clase: el.className,
        texto: el.innerText.replace(/\s+/g, ' '),
        botones: [...el.querySelectorAll('button')].map((b) => b.innerText.trim()),
      }));
      exige(/br-documento--nuevo/.test(r.clase), `el documento nuevo no usa .br-documento--nuevo: «${r.clase}»`);
      exige(r.texto.includes('Certificado de EPS'), `el documento nuevo no es el de EPS: «${r.texto}»`);
      exige(r.botones.includes('Válido') && r.botones.includes('No válido'), `faltan «Válido» y «No válido»: ${r.botones.join(' | ')}`);
    },
  },
  {
    estado: 'pedir-documento',
    nombre: 'diálogo con select «Certificado de EPS» y un textarea',
    prueba: async (page) => {
      const r = await page.$eval('[role=dialog]', (d) => {
        const s = d.querySelector('select');
        return {
          opciones: s ? [...s.options].map((o) => o.textContent.trim()) : [],
          elegida: s ? s.options[s.selectedIndex].textContent.trim() : '',
          textarea: !!d.querySelector('textarea'),
        };
      });
      exige(r.opciones.includes('Certificado de EPS'), `opciones del select: ${r.opciones.join(' | ')}`);
      exige(r.elegida === 'Certificado de EPS', `opción elegida: «${r.elegida}»`);
      exige(r.textarea, 'el diálogo no tiene textarea');
    },
  },
  ...['validada', 'aprobada', 'rechazada'].map((estado) => ({
    estado,
    nombre: 'historial con quién y cuándo (historial_estados)',
    prueba: async (page) => {
      exige(await visible(page, '[data-prueba=historial]'), '[data-prueba=historial] no se ve');
      const t = await texto(page, '[data-prueba=historial]');
      exige(t.includes('Laura Gómez'), `el historial no dice quién: «${t}»`);
      exige(/\d{1,2} (oct|nov) 2026/.test(t), `el historial no dice cuándo: «${t}»`);
    },
  })),
  {
    estado: 'validada',
    nombre: '«Aprobar» habilitado cuando está Validada y hay cupos',
    prueba: async (page) => {
      exige(!(await deshabilitado(page, '[data-prueba=aprobar]')), '[data-prueba=aprobar] está deshabilitado');
    },
  },
  {
    estado: 'aprobada',
    nombre: 'resumen de la asignación creada',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=asignacion]');
      exige(t.includes('$ 90.000'), `falta el valor: «${t}»`);
      exige(t.includes('Tarjeta cívica 2026-2'), `falta la convocatoria: «${t}»`);
    },
  },
  // Coherencia con 07-convocatorias: Tarjeta cívica 2026-2 es 0 de 30, 4 pagos de $ 90.000
  ...['pendiente', 'documento-pedido', 'documento-recibido', 'pedir-documento', 'validada', 'rechazada', 'confirmar-accion'].map((estado) => ({
    estado,
    nombre: 'cupos de Tarjeta cívica 2026-2: 0 de 30 usados (como en la vista 07)',
    prueba: async (page) => {
      const c = await texto(page, '[data-prueba=cupos]');
      exige(c.includes('Cupos: 0 de 30 usados'), `contador: «${c}»`);
      exige(!/Alimentación/.test(await texto(page, 'main')), 'sigue hablando de Alimentación 2026-2');
    },
  })),
  {
    estado: 'aprobada',
    nombre: 'cupos tras aprobar: 1 de 30 usados',
    prueba: async (page) => {
      const c = await texto(page, '[data-prueba=cupos]');
      exige(c.includes('Cupos: 1 de 30 usados'), `contador: «${c}»`);
    },
  },
  {
    estado: 'confirmar-accion',
    nombre: 'el diálogo resume 4 pagos de $ 90.000 y cupos 1 de 30',
    prueba: async (page) => {
      const t = await texto(page, '[role=dialog]');
      exige(t.includes('4 pagos de $ 90.000') && t.includes('1 de 30 usados'), `diálogo: «${t}»`);
    },
  },
  {
    estado: 'rechazada',
    nombre: 'muestra el motivo del rechazo',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=motivo-rechazo]');
      exige(/Motivo/.test(t) && t.length > 30, `motivo: «${t}»`);
    },
  },
  {
    estado: 'sin-cupos',
    nombre: 'CA-5 sin cupos: «Aprobar» deshabilitado y se explica por qué',
    prueba: async (page) => {
      exige(await deshabilitado(page, '[data-prueba=aprobar]'), '[data-prueba=aprobar] no tiene disabled');
      exige(await visible(page, '[data-prueba=motivo-deshabilitado]'), '[data-prueba=motivo-deshabilitado] no se ve');
      const t = await texto(page, '[data-prueba=motivo-deshabilitado]');
      exige(/cupos/.test(t), `el motivo no menciona «cupos»: «${t}»`);
      const c = await texto(page, '[data-prueba=cupos]');
      exige(c.includes('Cupos: 25 de 25 usados'), `contador: «${c}»`);
    },
  },
  {
    estado: 'confirmar-accion',
    nombre: 'diálogo «¿Seguro que quieres aprobar a <nombre>?»',
    prueba: async (page) => {
      const t = await texto(page, '[role=dialog]');
      exige(t.includes(`¿Seguro que quieres aprobar a ${NOMBRE}?`), `diálogo: «${t}»`);
    },
  },
  ...['pendiente', 'validada'].map((estado) => ({
    estado,
    nombre: '«Rechazar» es un botón de peligro',
    prueba: async (page) => {
      const clases = await page.$$eval('main button', (bs) =>
        bs.filter((b) => b.innerText.trim() === 'Rechazar').map((b) => b.className));
      exige(clases.length > 0, 'no hay botón «Rechazar»');
      exige(clases.every((c) => /br-boton--peligro/.test(c)), `clases de «Rechazar»: ${clases.join(' | ')}`);
    },
  })),
  {
    estado: 'error',
    nombre: 'error claro con «Intentar de nuevo»',
    prueba: async (page) => {
      const a = await texto(page, '[role=alert]');
      exige(/No pudimos/.test(a), `mensaje de error: «${a}»`);
      const acciones = await page.$$eval('main a, main button', (els) => els.map((e) => e.innerText.trim()));
      exige(acciones.includes('Intentar de nuevo'), `falta «Intentar de nuevo»: ${acciones.join(' | ')}`);
    },
  },
];
