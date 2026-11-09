// Pruebas propias de la vista 09-postulacion (spec 002 §5 «Vista 9», plan tarea 9b).
const fs = require('fs');
const path = require('path');

const texto = (page, selector = 'body') =>
  page.$eval(selector, (el) => el.innerText.replace(/\s+/g, ' '));

const exige = (cond, mensaje) => {
  if (!cond) throw new Error(mensaje);
};

const DOCUMENTOS = ['Documento de identidad', 'Certificado de EPS', 'Certificado bancario'];

module.exports = [
  {
    estado: '*',
    nombre: 'enlace «Volver a Mi proceso» arriba',
    prueba: async (page) => {
      const href = await page.$$eval('main a', (as) => {
        const a = as.find((x) => /Volver a Mi proceso/.test(x.innerText));
        return a ? a.getAttribute('href') : null;
      });
      exige(href && /01-mi-proceso\/html\//.test(href), `falta «Volver a Mi proceso»: ${href}`);
    },
  },
  {
    estado: 'formulario',
    nombre: 'cada campo tiene label; 3 documentos con «PDF, JPG o PNG» y «5 MB»; casilla Ley 1581',
    prueba: async (page) => {
      const sin = await page.$$eval('input:not([type=hidden]), select, textarea', (els) =>
        els.filter((e) => !e.labels || e.labels.length === 0).map((e) => e.name || e.id || e.type));
      exige(sin.length === 0, `campos sin label: ${sin.join(', ')}`);
      const t = await texto(page);
      for (const d of DOCUMENTOS) exige(t.includes(d), `falta el documento «${d}»`);
      const archivos = await page.$$eval('input[type=file]', (els) => els.length);
      exige(archivos === 3, `debe haber 3 zonas de archivo: ${archivos}`);
      const ayudas = await page.$$eval('.br-zona', (els) => els.map((e) => e.innerText));
      exige(ayudas.every((a) => a.includes('PDF, JPG o PNG') && a.includes('5 MB')),
        'cada zona debe decir «PDF, JPG o PNG» y «5 MB»');
      exige(t.includes('Ley 1581'), 'falta la casilla de la Ley 1581');
      const casillas = await page.$$eval('input[type=checkbox]', (els) => els.length);
      exige(casillas >= 1, 'falta la casilla');
      exige(t.includes('Revisar y enviar'), 'falta «Revisar y enviar»');
      exige(t.includes('Avísale a Bienestar antes de enviar'), 'falta la nota de «Tus datos»');
    },
  },
  {
    estado: 'con-errores',
    nombre: 'CA-11 resumen de errores con enlaces a campos y campos con aria-invalid',
    prueba: async (page) => {
      const r = await page.$eval('[data-prueba=resumen-errores]', (el) => ({
        rol: el.getAttribute('role'),
        texto: el.innerText.replace(/\s+/g, ' '),
        hrefs: [...el.querySelectorAll('a')].map((a) => a.getAttribute('href')),
      }));
      exige(r.rol === 'alert', 'el resumen debe tener role="alert"');
      exige(r.texto.includes('Revisa 3 campos'), `falta «Revisa 3 campos»: «${r.texto}»`);
      exige(r.hrefs.length === 3, `el resumen debe tener 3 enlaces: ${r.hrefs.length}`);
      for (const h of r.hrefs) {
        exige(/^#.+/.test(h), `enlace no es ancla: ${h}`);
        exige(await page.$(h), `el enlace ${h} no apunta a un elemento existente`);
      }
      const malos = await page.$$eval('[aria-invalid=true]', (els) =>
        els.filter((e) => {
          const ids = (e.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
          return !ids.some((i) => document.getElementById(i) && document.getElementById(i).innerText.trim());
        }).map((e) => e.id));
      exige(malos.length === 0, `campos con aria-invalid sin mensaje: ${malos.join(', ')}`);
      const n = await page.$$eval('[aria-invalid=true]', (els) => els.length);
      exige(n === 3, `debe haber 3 campos con aria-invalid: ${n}`);
      const t = await texto(page);
      exige(t.includes('Falta el Certificado de EPS'), 'falta «Falta el Certificado de EPS»');
      exige(t.includes('El archivo pesa más de 5 MB'), 'falta «El archivo pesa más de 5 MB»');
    },
  },
  {
    estado: 'revisar-y-enviar',
    nombre: 'CA-12 resumen con «Corregir», «Enviar postulación» y aviso «no podrás editar»',
    prueba: async (page) => {
      const t = await texto(page);
      exige(t.includes('Enviar postulación'), 'falta «Enviar postulación»');
      exige(t.includes('Corregir'), 'falta «Corregir»');
      exige(/no podrás editar/i.test(t), 'falta el aviso «no podrás editar»');
      for (const d of DOCUMENTOS) exige(t.includes(d), `falta el documento «${d}» en el resumen`);
      exige(/Quitar/.test(t), 'falta «Quitar» en el archivo listo');
    },
  },
  {
    estado: 'enviada',
    nombre: 'enviada: fecha, qué sigue y enlace a en-revision',
    prueba: async (page) => {
      const t = await texto(page);
      exige(t.includes('Listo, tu postulación fue enviada'), 'falta «Listo, tu postulación fue enviada»');
      exige(t.includes('20 nov 2026'), 'falta la fecha de envío');
      exige(/Bienestar revisa tus datos y documentos/.test(t), 'falta qué sigue');
      const href = await page.$$eval('a', (as) => {
        const a = as.find((x) => /Ver mi proceso/.test(x.innerText));
        return a ? a.getAttribute('href') : null;
      });
      exige(href === '../../01-mi-proceso/html/en-revision.html', `«Ver mi proceso» apunta a «${href}»`);
    },
  },
  {
    estado: 'convocatoria-cerrada',
    nombre: 'convocatoria cerrada con texto alterno «Ya te postulaste…» en comentario',
    prueba: async (page, ctx) => {
      const t = await texto(page);
      exige(t.includes('Esta convocatoria ya cerró'), 'falta «Esta convocatoria ya cerró»');
      const fuente = fs.readFileSync(path.join(ctx.raiz, '_fuente', ctx.vista, `${ctx.estado}.html`), 'utf8');
      const comentarios = (fuente.match(/<!--[\s\S]*?-->/g) || []).join('\n');
      exige(comentarios.includes('Ya te postulaste a esta convocatoria'),
        'falta el comentario con «Ya te postulaste a esta convocatoria»');
    },
  },
];
