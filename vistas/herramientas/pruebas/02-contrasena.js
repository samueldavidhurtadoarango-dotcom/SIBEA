// Pruebas propias de la vista 02-contrasena (spec 002 §5 «Vista 2», plan tarea 10).
// Primer ingreso del aprendiz y recuperación de la contraseña, en la misma tarjeta centrada del login.
const texto = (page, selector = 'body') =>
  page.$eval(selector, (el) => el.innerText.replace(/\s+/g, ' '));

const exige = (cond, mensaje) => {
  if (!cond) throw new Error(mensaje);
};

const LOGIN = '../../00-existentes/login/html/1-normal.html';
const ESTADOS = ['primer-ingreso', 'recuperar-solicitud', 'recuperar-nueva', 'enlace-vencido', 'error-validacion', 'exito'];

const camposDeClave = {
  nombre: 'dos campos de contraseña, cada uno con su label y botón de mostrar con nombre',
  prueba: async (page) => {
    const r = await page.$$eval('input[type=password]', (els) =>
      els.map((i) => ({ label: i.labels.length > 0 && i.labels[0].innerText.trim().length > 0, auto: i.getAttribute('autocomplete') })));
    exige(r.length === 2, `hay ${r.length} input[type=password], se esperan 2`);
    exige(r.every((c) => c.label), 'un campo de contraseña no tiene <label>');
    exige(r.every((c) => c.auto === 'new-password'), `autocomplete debe ser new-password: ${r.map((c) => c.auto).join(', ')}`);
    const botones = await page.$$eval('.br-campo__ojo', (els) => els.map((b) => b.getAttribute('aria-label') || ''));
    exige(botones.length === 2 && botones.every(Boolean), 'falta el botón «Mostrar contraseña» con nombre accesible en cada campo');
  },
};

const reglasVisibles = {
  nombre: 'lista de reglas visible: 4 reglas, cada una con ícono y texto «cumplida» o «pendiente» (no solo color)',
  prueba: async (page) => {
    const r = await page.$$eval('[data-prueba=reglas] li', (els) =>
      els.map((li) => ({
        texto: li.textContent.replace(/\s+/g, ' ').trim(),
        icono: !!li.querySelector('svg'),
        visible: li.getClientRects().length > 0,
        estado: li.getAttribute('data-estado'),
      })));
    exige(r.length === 4, `hay ${r.length} reglas, se esperan 4`);
    exige(r.every((x) => x.visible), 'hay reglas no visibles');
    exige(r.every((x) => x.icono), 'cada regla necesita su ícono');
    exige(r.every((x) => /cumplida|pendiente|no se cumple/i.test(x.texto)), `reglas sin estado en texto: ${r.map((x) => x.texto).join(' | ')}`);
    exige(r.every((x) => ['cumplida', 'pendiente', 'incumplida'].includes(x.estado)), 'cada regla necesita data-estado');
    const t = r.map((x) => x.texto).join(' | ');
    exige(/8 caracteres/.test(t) && /mayúscula/.test(t) && /minúscula/.test(t) && /número/.test(t), `faltan reglas (8 caracteres, mayúscula, minúscula, número): ${t}`);
    const nombre = await page.$eval('[data-prueba=reglas]', (el) => {
      const id = el.getAttribute('aria-labelledby');
      const ref = id && document.getElementById(id);
      return el.getAttribute('aria-label') || (ref ? ref.innerText.trim() : '');
    });
    exige(nombre, 'la lista de reglas necesita nombre accesible');
  },
};

const tarjetaCentrada = {
  nombre: 'misma tarjeta centrada del login (440 px, centrada, con logo y nombre), sin barra del aprendiz',
  prueba: async (page) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const c = await page.$eval('[data-prueba=tarjeta]', (el) => {
      const r = el.getBoundingClientRect();
      return { ancho: Math.round(r.width), izq: r.left, der: window.innerWidth - r.right, radio: getComputedStyle(el).borderTopLeftRadius };
    });
    exige(c.ancho === 440, `la tarjeta mide ${c.ancho} px, el login usa 440`);
    exige(Math.abs(c.izq - c.der) <= 1, `la tarjeta no está centrada: izquierda ${c.izq}, derecha ${c.der}`);
    exige(c.radio === '16px', `radio ${c.radio}, el login usa 16px`);
    const extra = await page.evaluate(() => ({
      barra: !!document.querySelector('.br-barra-aprendiz'),
      marca: document.body.innerText.includes('BenefitRack'),
      logo: !!document.querySelector('[data-prueba=logo]'),
      h1: document.querySelectorAll('h1').length,
    }));
    exige(!extra.barra, 'no debe llevar la barra del aprendiz (aún no tiene sesión)');
    exige(extra.marca && extra.logo, 'falta el logo y el nombre BenefitRack sobre la tarjeta');
    exige(extra.h1 === 1, `hay ${extra.h1} <h1>, se espera 1`);
    await page.setViewportSize({ width: 390, height: 844 });
    const m = await page.$eval('[data-prueba=tarjeta]', (el) => {
      const r = el.getBoundingClientRect();
      return { izq: r.left, der: r.right };
    });
    exige(m.izq >= 16 && m.der <= 374, `en el celular la tarjeta va de ${Math.round(m.izq)} a ${Math.round(m.der)} px (margen mínimo 16)`);
  },
};

module.exports = [
  ...ESTADOS.map((estado) => ({ estado, ...tarjetaCentrada })),
  { estado: 'primer-ingreso', ...camposDeClave },
  { estado: 'primer-ingreso', ...reglasVisibles },
  {
    estado: 'primer-ingreso',
    nombre: 'explica por qué debe cambiarla y ofrece «Guardar contraseña»',
    prueba: async (page) => {
      const t = await texto(page, 'main');
      exige(/contraseña que te dio/i.test(t) && /Bienestar/.test(t), `falta explicar que la contraseña la dio Bienestar: «${t}»`);
      const acciones = await page.$$eval('main button, main a', (els) => els.map((e) => e.innerText.trim()));
      exige(acciones.includes('Guardar contraseña'), `falta «Guardar contraseña»: ${acciones.join(' | ')}`);
    },
  },
  {
    estado: 'recuperar-solicitud',
    nombre: 'tras enviar muestra el mensaje neutro «Si el correo está registrado» (no revela si la cuenta existe)',
    prueba: async (page) => {
      const t = await texto(page, '[data-prueba=mensaje-neutro]');
      exige(t.includes('Si el correo está registrado, te enviamos un enlace'), `texto neutro: «${t}»`);
      const rol = await page.$eval('[data-prueba=mensaje-neutro]', (el) => el.getAttribute('role'));
      exige(rol === 'status', `el mensaje debe ser role="status", es ${rol}`);
      const todo = await texto(page, 'main');
      exige(!/no existe|no encontramos|no hay una cuenta|no está registrado/i.test(todo), 'el texto revela si la cuenta existe');
    },
  },
  {
    estado: 'recuperar-solicitud',
    nombre: 'campo de correo con label, correo largo sin desbordar y aviso de que el enlace vence',
    prueba: async (page) => {
      const r = await page.$eval('input[type=email]', (i) => ({ label: i.labels.length > 0, valor: i.value }));
      exige(r.label, 'el campo de correo no tiene <label>');
      exige(/@soy\.sena\.edu\.co$/.test(r.valor) && r.valor.length >= 35, `usa un correo largo de @soy.sena.edu.co: «${r.valor}»`);
      const t = await texto(page, 'main');
      exige(/vence en 1 hora/.test(t), `falta «vence en 1 hora» (la BD guarda fecha_expiracion): «${t}»`);
      await page.setViewportSize({ width: 390, height: 844 });
      const d = await page.$eval('[data-prueba=tarjeta]', (card) => {
        const c = card.getBoundingClientRect();
        return [...card.querySelectorAll('input, p, span, a, button')].filter((e) => e.getBoundingClientRect().right > c.right + 0.5).map((e) => e.tagName);
      });
      exige(d.length === 0, `elementos que se salen de la tarjeta a 390: ${d.join(', ')}`);
    },
  },
  {
    estado: 'recuperar-solicitud',
    nombre: 'ofrece volver al inicio de sesión (enlace al login)',
    prueba: async (page) => {
      const h = await page.$$eval('main a', (as) => as.map((a) => a.getAttribute('href')));
      exige(h.includes(LOGIN), `no hay enlace al login: ${h.join(' | ')}`);
    },
  },
  { estado: 'recuperar-nueva', ...camposDeClave },
  { estado: 'recuperar-nueva', ...reglasVisibles },
  {
    estado: 'recuperar-nueva',
    nombre: 'botón «Guardar contraseña nueva»',
    prueba: async (page) => {
      const acciones = await page.$$eval('main button, main a', (els) => els.map((e) => e.innerText.trim()));
      exige(acciones.includes('Guardar contraseña nueva'), `falta «Guardar contraseña nueva»: ${acciones.join(' | ')}`);
    },
  },
  {
    estado: 'enlace-vencido',
    nombre: 'explica que el enlace caducó o ya se usó y ofrece «Pedir uno nuevo»',
    prueba: async (page) => {
      const t = await texto(page, 'main');
      exige(/venci|caduc/i.test(t) && /ya se usó/.test(t), `falta explicar que caducó o ya se usó: «${t}»`);
      const a = await page.$$eval('main a', (as) => as.map((x) => ({ t: x.innerText.trim(), h: x.getAttribute('href') })));
      const pedir = a.find((x) => x.t === 'Pedir uno nuevo');
      exige(pedir, `falta «Pedir uno nuevo»: ${a.map((x) => x.t).join(' | ')}`);
      exige(pedir.h === 'recuperar-solicitud.html', `«Pedir uno nuevo» lleva a ${pedir.h}`);
    },
  },
  { estado: 'error-validacion', ...camposDeClave },
  { estado: 'error-validacion', ...reglasVisibles },
  {
    estado: 'error-validacion',
    nombre: 'campos con aria-invalid y mensaje propio enlazado con aria-describedby, más resumen role=alert',
    prueba: async (page) => {
      const r = await page.$$eval('input[type=password]', (els) =>
        els.map((i) => {
          const ids = (i.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
          const msg = ids.map((id) => document.getElementById(id)).filter(Boolean).map((e) => e.innerText.trim()).join(' ');
          return { inv: i.getAttribute('aria-invalid'), msg };
        }));
      exige(r.every((x) => x.inv === 'true'), 'los dos campos deben llevar aria-invalid="true"');
      exige(r.every((x) => x.msg.length > 8), `cada campo necesita su mensaje con aria-describedby: ${r.map((x) => `«${x.msg}»`).join(' ')}`);
      const t = r.map((x) => x.msg).join(' ');
      exige(/no coinciden/.test(t), `falta el mensaje «no coinciden»: ${t}`);
      const alerta = await page.$eval('[role=alert]', (el) => el.innerText.replace(/\s+/g, ' '));
      exige(alerta.length > 10, 'falta el resumen con role="alert"');
      const incumplidas = await page.$$eval('[data-prueba=reglas] li[data-estado=incumplida]', (e) => e.length);
      exige(incumplidas >= 1, 'debe haber al menos una regla incumplida marcada');
    },
  },
  {
    estado: 'exito',
    nombre: '«Contraseña cambiada. Ya puedes entrar.» con botón al login',
    prueba: async (page) => {
      const t = await texto(page, 'main');
      exige(t.includes('Contraseña cambiada. Ya puedes entrar.'), `falta el mensaje de éxito: «${t}»`);
      const a = await page.$$eval('main a', (as) => as.map((x) => ({ t: x.innerText.trim(), h: x.getAttribute('href') })));
      const entrar = a.find((x) => x.h === LOGIN);
      exige(entrar, `falta el botón al login (${LOGIN}): ${a.map((x) => x.h).join(' | ')}`);
      const rol = await page.$eval('[data-prueba=tarjeta] [role=status]', (el) => el.innerText).catch(() => '');
      exige(rol.includes('Contraseña cambiada'), 'el mensaje de éxito debe estar en un role="status"');
    },
  },
];
