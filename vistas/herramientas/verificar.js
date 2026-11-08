// Verifica las vistas: node vistas/herramientas/verificar.js [--vista <carpeta>]
// Imprime OK / FALLA por comprobación y sale con código 1 si hay alguna falla.
// Las dependencias (playwright-core, axe-core) se instalan fuera del repo: ver vistas/LEEME.md.
const fs = require('fs');
const path = require('path');
const { pathToFileURL } = require('url');
const { chromium } = require('playwright-core');

const RAIZ = path.resolve(__dirname, '..');
const estados = require(path.join(RAIZ, 'estados.json'));

function leeVista() {
  const i = process.argv.indexOf('--vista');
  return i >= 0 ? process.argv[i + 1] : null;
}

function cargaPruebas(vista) {
  const globales = require('./pruebas/_global.js');
  const propia = path.join(__dirname, 'pruebas', `${vista}.js`);
  return fs.existsSync(propia) ? [...globales, ...require(propia)] : globales;
}

async function main() {
  const filtro = leeVista();
  if (filtro && !estados[filtro]) {
    console.error(`FALLA ${filtro}: la vista no está en estados.json`);
    process.exit(1);
  }
  const vistas = filtro ? [filtro] : Object.keys(estados);
  const navegador = await chromium.launch({ channel: 'msedge' });
  const contexto = await navegador.newContext();
  // Sin internet: la fuente de Google Fonts tiene respaldo y así las pruebas no dependen de la red.
  await contexto.route(/^https?:/, (ruta) => ruta.abort());
  const page = await contexto.newPage();
  let ok = 0;
  let fallas = 0;

  for (const vista of vistas) {
    const { rol, estados: lista } = estados[vista];
    const pruebas = cargaPruebas(vista);
    for (const estado of lista) {
      const archivo = path.join(RAIZ, vista, 'html', `${estado}.html`);
      const hayHtml = fs.existsSync(archivo);
      const ctx = { vista, estado, rol, raiz: RAIZ, archivo, estados };
      for (const t of pruebas) {
        if (t.estado !== '*' && t.estado !== estado) continue;
        if (!hayHtml && !t.sinPagina) continue;
        try {
          await page.setViewportSize({ width: 1440, height: 900 });
          if (hayHtml) await page.goto(pathToFileURL(archivo).href, { waitUntil: 'load', timeout: 20000 });
          await t.prueba(page, ctx);
          ok++;
          console.log(`OK ${vista}/${estado}: ${t.nombre}`);
        } catch (e) {
          fallas++;
          console.log(`FALLA ${vista}/${estado}: ${t.nombre} — ${String(e.message).split('\n')[0]}`);
        }
      }
    }
  }

  await navegador.close();
  console.log(`\n${ok} comprobaciones OK, ${fallas} con falla.`);
  process.exit(fallas ? 1 : 0);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
