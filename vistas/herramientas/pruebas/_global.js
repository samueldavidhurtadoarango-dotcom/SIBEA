// Pruebas que se aplican a TODOS los estados de TODAS las vistas (estado: '*').
// Cada prueba recibe (page, ctx) y lanza un Error si falla.
// ctx = { vista, estado, rol, raiz, archivo, estados }
const fs = require('fs');
const path = require('path');

const PALETA = [
  '#00304D', '#007832', '#00632A', '#6BD11F', '#0B6CB8', '#556776', '#6B7C88', '#7D8E9A',
  '#F5F7F9', '#F0F4F6', '#EEF2F4', '#E3E8EC', '#FFFFFF',
  // avisos del boceto aprobado
  '#FFF3E0', '#A35200', '#F5C98B', '#7A3E00', '#E6F4EC',
  // estados del panel de postulaciones aprobado (00-existentes/postulaciones)
  '#C2392F', '#9E3A34', '#FCEBEA', '#FFF0B8', '#5C4400', '#E0A800', '#FFF4D1', '#6E5200',
  '#E2F2FB', '#0B5180', '#E6F4DD', '#22610A', '#2E7D12', '#EEF2F5', '#EEF4F8', '#C9DCEA',
].map((c) => c.toLowerCase());

// CA-3: texto de un enlace o botón que invita a postularse (con o sin tilde). No debe chocar con «Postulaciones» ni «postulación».
const PATRON_POSTULARSE = /post[uú]la(r|te|rse|me)|inscr[ií]b/i;

// Elementos que cuentan como «botón o enlace» para CA-3.
const SELECTOR_ACCIONES = 'a, button';

const ANCHOS = [1440, 390];
const MENU_ADMIN = ['Dashboard', 'Convocatorias', 'Postulaciones', 'Pagos', 'Usuarios'];

function anchoPng(ruta) {
  const b = fs.readFileSync(ruta);
  const firma = b.subarray(0, 8).toString('hex');
  if (firma !== '89504e470d0a1a0a') throw new Error(`no es un PNG: ${ruta}`);
  return b.readUInt32BE(16);
}

module.exports = [
  {
    estado: '*',
    nombre: 'CA-8 existe html/<estado>.html',
    sinPagina: true,
    prueba: async (page, ctx) => {
      if (!fs.existsSync(ctx.archivo)) throw new Error(`falta ${path.relative(ctx.raiz, ctx.archivo)}`);
    },
  },
  {
    estado: '*',
    nombre: 'charset utf-8 y sin caracteres rotos',
    prueba: async (page) => {
      const r = await page.evaluate(() => ({
        juego: document.characterSet,
        meta: !!document.querySelector('meta[charset]'),
        texto: document.body.innerText,
      }));
      if (r.juego !== 'UTF-8') throw new Error(`document.characterSet es ${r.juego}`);
      if (!r.meta) throw new Error('falta <meta charset="utf-8">');
      const roto = r.texto.match(/Ã[±³¡©­º]|Â./);
      if (roto) throw new Error(`texto con codificación rota: «${roto[0]}»`);
    },
  },
  {
    estado: '*',
    nombre: 'sin desplazamiento horizontal a 390',
    prueba: async (page) => {
      await page.setViewportSize({ width: 390, height: 844 });
      const ancho = await page.evaluate(() => document.documentElement.scrollWidth);
      if (ancho > 390) throw new Error(`scrollWidth ${ancho} > 390`);
    },
  },
  {
    estado: '*',
    nombre: 'letra Work Sans',
    prueba: async (page) => {
      const f = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
      if (!/Work Sans/.test(f)) throw new Error(`fontFamily del body: ${f}`);
    },
  },
  {
    estado: '*',
    nombre: 'colores de la paleta',
    prueba: async (page) => {
      const errores = [];
      for (const ancho of ANCHOS) {
      await page.setViewportSize({ width: ancho, height: 900 });
      const fuera = await page.evaluate((paleta) => {
        const hex = (c) => '#' + c.slice(0, 3).map((n) => Number(n).toString(16).padStart(2, '0')).join('');
        const lee = (valor) => {
          const m = valor.match(/^rgba?\(([^)]+)\)$/);
          if (!m) return { error: valor };
          const p = m[1].split(/[ ,\/]+/).filter(Boolean);
          const alfa = p.length > 3 ? Number(p[3]) : 1;
          if (alfa === 0) return null;
          if (alfa < 1) return { error: `${valor} (con transparencia)` };
          return { hex: hex(p) };
        };
        const malos = new Map();
        const anota = (el, prop, valor) => {
          const r = lee(valor);
          if (!r) return;
          if (r.error || !paleta.includes(r.hex)) {
            const id = `${prop}: ${r.error || r.hex}`;
            if (!malos.has(id)) {
              const clase = (el.className && el.className.baseVal === undefined ? String(el.className) : '').trim().replace(/\s+/g, '.');
              malos.set(id, `${el.tagName.toLowerCase()}${clase ? '.' + clase : ''}`);
            }
          }
        };
        for (const el of [document.documentElement, document.body, ...document.body.querySelectorAll('*')]) {
          if (['SCRIPT', 'STYLE', 'TEMPLATE', 'NOSCRIPT'].includes(el.tagName)) continue;
          if (el.getClientRects().length === 0) continue;
          const cs = getComputedStyle(el);
          if (cs.visibility === 'hidden') continue;
          anota(el, 'color', cs.color);
          anota(el, 'background-color', cs.backgroundColor);
          for (const lado of ['top', 'right', 'bottom', 'left']) {
            const ancho = parseFloat(cs[`border-${lado}-width`]);
            const estilo = cs[`border-${lado}-style`];
            if (ancho > 0 && estilo !== 'none' && estilo !== 'hidden') anota(el, 'border-color', cs[`border-${lado}-color`]);
          }
        }
        return [...malos].map(([id, el]) => `${id} en <${el}>`);
      }, PALETA);
      if (fuera.length) errores.push(`a ${ancho} px: ${fuera.slice(0, 6).join('; ')}`);
      }
      if (errores.length) throw new Error(`colores fuera de la paleta ${errores.join(' | ')}`);
    },
  },
  {
    estado: '*',
    nombre: 'contraste AA, botones con nombre, campos con etiqueta, imágenes con alt',
    prueba: async (page) => {
      const axe = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
      const errores = [];
      for (const ancho of ANCHOS) {
      await page.setViewportSize({ width: ancho, height: 900 });
      await page.evaluate(axe);
      const violaciones = await page.evaluate(async () => {
        const r = await window.axe.run(document, {
          runOnly: ['color-contrast', 'button-name', 'label', 'image-alt'],
        });
        return r.violations.map((v) => ({
          id: v.id,
          nodos: v.nodes.map((n) => `${n.target.join(' ')} ${(n.any[0] && n.any[0].message) || ''}`.trim()),
        }));
      });
      if (violaciones.length) {
        const txt = violaciones.map((v) => `${v.id} → ${v.nodos.slice(0, 3).join(' | ')}`).join('; ');
        errores.push(`a ${ancho} px: ${txt}`);
      }
      }
      if (errores.length) throw new Error(`axe ${errores.join(' | ')}`);
    },
  },
  {
    estado: '*',
    nombre: 'foco visible definido en el CSS común',
    sinPagina: true,
    prueba: async (page, ctx) => {
      const css = fs.readFileSync(path.join(ctx.raiz, '_comun', 'estilo.css'), 'utf8');
      if (!css.includes(':focus-visible')) throw new Error('estilo.css no define :focus-visible');
    },
  },
  {
    estado: '*',
    nombre: 'capturas de computador (1440) y celular (390)',
    prueba: async (page, ctx) => {
      for (const [dir, ancho] of [['computador', 1440], ['celular', 390]]) {
        const ruta = path.join(ctx.raiz, ctx.vista, dir, `${ctx.estado}.png`);
        if (!fs.existsSync(ruta)) throw new Error(`falta ${dir}/${ctx.estado}.png`);
        const real = anchoPng(ruta);
        if (real !== ancho) throw new Error(`${dir}/${ctx.estado}.png mide ${real} px de ancho, debería ser ${ancho}`);
      }
    },
  },
  {
    estado: '*',
    nombre: 'CA-3 el aprendiz solo puede postularse desde convocatoria-abierta',
    prueba: async (page, ctx) => {
      if (ctx.rol !== 'aprendiz') return;
      // Cambio del 2026-11-09: solo se permite en 01-mi-proceso/convocatoria-abierta y dentro del formulario (09-postulacion).
      if (ctx.vista === '09-postulacion') return;
      if (ctx.vista === '01-mi-proceso' && ctx.estado === 'convocatoria-abierta') return;
      const malos = await page.evaluate(({ fuente, banderas, selector }) => {
        const re = new RegExp(fuente, banderas);
        return [...document.querySelectorAll(selector)]
          .map((e) => `${e.innerText || ''} ${e.getAttribute('aria-label') || ''} ${e.tagName === 'INPUT' ? e.value : ''}`.trim())
          .filter((t) => re.test(t));
      }, { fuente: PATRON_POSTULARSE.source, banderas: PATRON_POSTULARSE.flags, selector: SELECTOR_ACCIONES });
      if (malos.length) throw new Error(`enlaces o botones para postularse: ${malos.join(' | ')}`);
    },
  },
  {
    estado: '*',
    nombre: 'CA-3 el selector también revisa input[type=submit|button] y [role=button]',
    prueba: async (page) => {
      const n = await page.evaluate((selector) => {
        const d = document.createElement('div');
        d.innerHTML = '<input type="submit" value="Postularme"><input type="button" value="Inscríbete"><span role="button">Postúlate</span>';
        document.body.appendChild(d);
        const r = d.querySelectorAll(selector).length;
        d.remove();
        return r;
      }, SELECTOR_ACCIONES);
      if (n < 3) throw new Error(`el selector de CA-3 solo atrapa ${n} de 3 botones disfrazados (input submit, input button, [role=button])`);
    },
  },
  {
    estado: '*',
    nombre: 'el documento va sin tipo (la BD no guarda CC/TI): ningún «CC 1.» ni «TI 1.»',
    prueba: async (page) => {
      const t = await page.$eval('body', (el) => el.innerText);
      const m = t.match(/\b(CC|TI) 1\.\d/);
      if (m) throw new Error(`prefijo de tipo de documento: «${m[0]}»`);
    },
  },
  {
    estado: '*',
    nombre: 'los enlaces relativos apuntan a archivos que existen',
    sinPagina: true,
    prueba: async (page, ctx) => {
      if (!fs.existsSync(ctx.archivo)) return;
      const html = fs.readFileSync(ctx.archivo, 'utf8');
      const rotos = [];
      for (const m of html.matchAll(/\bhref="([^"]+)"/g)) {
        const h = m[1];
        if (/^(https?:|mailto:|tel:|#|data:|javascript:)/i.test(h)) continue;
        const destino = path.resolve(path.dirname(ctx.archivo), h.split('#')[0].split('?')[0]);
        if (!fs.existsSync(destino)) rotos.push(h);
      }
      if (rotos.length) throw new Error(`enlaces rotos: ${[...new Set(rotos)].join(' | ')}`);
    },
  },
  {
    estado: '*',
    nombre: 'CA-3 el patrón atrapa «Postularme», «Postúlate», «Inscríbete» y no «Postulaciones»',
    prueba: async () => {
      for (const t of ['Postularme', 'Postúlate', 'Postulate', 'Postularse', 'Inscríbete', 'Inscribete', 'Inscribirme']) {
        if (!PATRON_POSTULARSE.test(t)) throw new Error(`el patrón no atrapa «${t}»`);
      }
      for (const t of ['Postulaciones', 'Postulaciones por revisar', 'Revisa la postulación', 'Revisar y enviar', 'Enviar postulación']) {
        if (PATRON_POSTULARSE.test(t)) throw new Error(`el patrón choca con el texto legítimo «${t}»`);
      }
    },
  },
  {
    estado: '*',
    nombre: 'CA-10 menú del administrador',
    prueba: async (page, ctx) => {
      if (ctx.rol !== 'admin') return;
      const items = await page.evaluate(() => {
        const nav = document.querySelectorAll('nav[aria-label="Menú principal"]');
        if (nav.length !== 1) return { n: nav.length };
        return { n: 1, textos: [...nav[0].querySelectorAll('a')].map((a) => a.textContent.trim()) };
      });
      if (items.n !== 1) throw new Error(`hay ${items.n} nav[aria-label="Menú principal"], debe haber 1`);
      if (items.textos.join(',') !== MENU_ADMIN.join(',')) {
        throw new Error(`menú: ${items.textos.join(', ')}; debía ser ${MENU_ADMIN.join(', ')}`);
      }
    },
  },
];
