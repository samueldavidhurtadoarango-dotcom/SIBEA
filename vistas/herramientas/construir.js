// Construye las vistas: node vistas/herramientas/construir.js [--vista <carpeta>]
// Para cada _fuente/<carpeta>/<estado>.html: arma <carpeta>/html/<estado>.html (piezas y CSS incrustados)
// y saca las capturas computador/<estado>.png (1440) y celular/<estado>.png (390).
// Las dependencias (playwright-core) se instalan fuera del repo: ver vistas/LEEME.md.
const fs = require('fs');
const path = require('path');
const { pathToFileURL } = require('url');
const { chromium } = require('playwright-core');

const RAIZ = path.resolve(__dirname, '..');
const FUENTE = path.join(RAIZ, '_fuente');
const ENLACE_CSS = '<link rel="stylesheet" href="../../_comun/estilo.css">';
const OPCIONES_MENU = ['Dashboard', 'Convocatorias', 'Postulaciones', 'Pagos', 'Usuarios'];
const TAMANOS = [
  { dir: 'computador', ancho: 1440, alto: 900 },
  { dir: 'celular', ancho: 390, alto: 844 },
];

const leePieza = (nombre) => fs.readFileSync(path.join(RAIZ, '_comun', `${nombre}.html`), 'utf8').trim();
const escapa = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

function leeAtributos(texto) {
  const attrs = {};
  for (const m of texto.matchAll(/([\w-]+)="([^"]*)"/g)) attrs[m[1]] = m[2];
  return attrs;
}

function migas(activo) {
  const dashboard = '<a href="#">Dashboard</a>';
  if (!activo || activo === 'Dashboard') {
    return '<nav aria-label="Ruta" class="br-migas"><span aria-current="page">Dashboard</span></nav>';
  }
  const flecha = '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"></path></svg>';
  return `<nav aria-label="Ruta" class="br-migas">${dashboard}${flecha}<span aria-current="page">${escapa(activo)}</span></nav>`;
}

// Panel de avisos de la campanita (vista 01b). La fuente lo escribe entre
// <!-- panel-avisos --> y <!-- /panel-avisos -->, y la barra lo pide con panel="abierto".
const BLOQUE_PANEL = /<!--\s*panel-avisos\s*-->([\s\S]*?)<!--\s*\/panel-avisos\s*-->/;

function barraAprendiz(attrs, panel, archivo) {
  const avisos = attrs.avisos === undefined ? '2' : attrs.avisos;
  const abierto = attrs.panel === 'abierto';
  if (abierto && panel === null) throw new Error(`${archivo}: panel="abierto" sin bloque <!-- panel-avisos -->`);
  const sinLeer = avisos === '0';
  return leePieza('barra-aprendiz')
    .replace(/{{nombre}}/g, () => escapa(attrs.nombre || 'Daniela'))
    .replace('{{etiquetaAvisos}}', () => (sinLeer ? 'Avisos, ninguno sin leer' : `Avisos, ${escapa(avisos)} sin leer`))
    .replace('{{contador}}', () => (sinLeer ? '' : `<span class="br-campanita__contador" aria-hidden="true">${escapa(avisos)}</span>`))
    .replace('{{expandido}}', abierto ? 'true' : 'false')
    .replace('{{controla}}', abierto ? ' aria-controls="panel-avisos"' : '')
    .replace('{{panel}}', () => (abierto
      ? `<section class="br-avisos" id="panel-avisos" aria-labelledby="panel-avisos-titulo" data-prueba="panel-avisos">${panel.trim()}</section>`
      : ''));
}

function incluirPiezas(html, archivo) {
  const bloque = html.match(BLOQUE_PANEL);
  const panel = bloque ? bloque[1] : null;
  let salida = html.replace(BLOQUE_PANEL, '');
  let panelUsado = false;
  salida = salida.replace(/<!--\s*incluir:barra-aprendiz((?:\s+[\w-]+="[^"]*")*)\s*-->/g, (_, a) => {
    const attrs = leeAtributos(a);
    if (attrs.panel === 'abierto') panelUsado = true;
    return barraAprendiz(attrs, panel, archivo);
  });
  if (panel !== null && !panelUsado) throw new Error(`${archivo}: hay bloque <!-- panel-avisos --> pero la barra no tiene panel="abierto"`);
  salida = salida.replace(/<!--\s*incluir:menu-admin((?:\s+[\w-]+="[^"]*")*)\s*-->/g, (_, a) => {
    const activo = leeAtributos(a).activo || '';
    if (activo && !OPCIONES_MENU.includes(activo)) throw new Error(`${archivo}: opción de menú desconocida «${activo}»`);
    let pieza = leePieza('menu-admin').replace('{{migas}}', migas(activo));
    if (activo) {
      pieza = pieza.replace(new RegExp(`data-opcion="${activo}"`, 'g'), `data-opcion="${activo}" aria-current="page"`);
    }
    return pieza;
  });
  const sobra = salida.match(/<!--\s*incluir:[^>]*-->/);
  if (sobra) throw new Error(`${archivo}: pieza desconocida ${sobra[0]}`);
  return salida;
}

function armar(fuente, archivo) {
  if (!fuente.includes(ENLACE_CSS)) throw new Error(`${archivo}: falta ${ENLACE_CSS}`);
  const css = fs.readFileSync(path.join(RAIZ, '_comun', 'estilo.css'), 'utf8');
  const conPiezas = incluirPiezas(fuente, archivo);
  // se usa una función para que los "$" del CSS no se interpreten como patrones de reemplazo
  return conPiezas.replace(ENLACE_CSS, () => `<style>\n${css}\n</style>`);
}

async function capturas(navegador, vista, estado, archivoHtml) {
  for (const t of TAMANOS) {
    const contexto = await navegador.newContext({ viewport: { width: t.ancho, height: t.alto }, deviceScaleFactor: 1 });
    const page = await contexto.newPage();
    try {
      await page.goto(pathToFileURL(archivoHtml).href, { waitUntil: 'load', timeout: 20000 });
      await page.evaluate(() => Promise.race([document.fonts.ready, new Promise((r) => setTimeout(r, 4000))]));
      const destino = path.join(RAIZ, vista, t.dir, `${estado}.png`);
      fs.mkdirSync(path.dirname(destino), { recursive: true });
      await page.screenshot({ path: destino, fullPage: true, animations: 'disabled' });
    } finally {
      await contexto.close();
    }
  }
}

async function main() {
  const i = process.argv.indexOf('--vista');
  const filtro = i >= 0 ? process.argv[i + 1] : null;
  const carpetas = fs.existsSync(FUENTE)
    ? fs.readdirSync(FUENTE, { withFileTypes: true }).filter((d) => d.isDirectory()).map((d) => d.name)
    : [];
  if (filtro && !carpetas.includes(filtro)) {
    console.error(`No existe _fuente/${filtro}`);
    process.exit(1);
  }
  const navegador = await chromium.launch({ channel: 'msedge' });
  let errores = 0;
  for (const vista of filtro ? [filtro] : carpetas) {
    const estados = fs.readdirSync(path.join(FUENTE, vista)).filter((f) => f.endsWith('.html')).map((f) => f.slice(0, -5));
    for (const estado of estados) {
      const origen = path.join(FUENTE, vista, `${estado}.html`);
      const salida = path.join(RAIZ, vista, 'html', `${estado}.html`);
      try {
        fs.mkdirSync(path.dirname(salida), { recursive: true });
        fs.writeFileSync(salida, armar(fs.readFileSync(origen, 'utf8'), `${vista}/${estado}`));
        await capturas(navegador, vista, estado, salida);
        console.log(`construido ${vista}/${estado}`);
      } catch (e) {
        errores++;
        console.error(`ERROR ${vista}/${estado}: ${e.message}`);
      }
    }
  }
  await navegador.close();
  process.exit(errores ? 1 : 0);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
