// Arma el catálogo de pantallas de BenefitRack (HTML autocontenido + PDF).
// Uso: node armar.js <raiz-del-repo> <carpeta-de-salida>
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright-core');

const RAIZ = process.argv[2];
const SALIDA = process.argv[3];
const V = path.join(RAIZ, 'vistas');
const estados = JSON.parse(fs.readFileSync(path.join(V, 'estados.json'), 'utf8'));

const grupos = [
  {
    titulo: 'Lo que ve el aprendiz',
    intro: 'El aprendiz entra con la cuenta que le crea Bienestar, se postula cuando hay una convocatoria abierta y sigue su proceso sin tener que preguntar.',
    pantallas: [
      { nombre: 'Inicio de sesión', pc: '00-existentes/login/html/1-normal.html', cel: '00-existentes/login/html/1-normal.html', n: 6,
        texto: 'Entrada al sistema con correo y contraseña. Avisa si los datos están mal, si la cuenta está inactiva o si se bloqueó tras 3 intentos (se desbloquea sola a los 15 minutos). No hay botón de «Crear cuenta»: las cuentas las crea Bienestar.' },
      { nombre: 'Primer ingreso y contraseña', vista: '02-contrasena', estado: 'primer-ingreso',
        texto: 'La primera vez que entra, el aprendiz cambia la contraseña que le dio Bienestar. Las reglas se ven mientras escribe. Incluye «¿Olvidaste tu contraseña?» con un enlace por correo que vence y solo sirve una vez.' },
      { nombre: 'Mi proceso', vista: '01-mi-proceso', estado: 'seleccionado',
        texto: 'El panel principal del aprendiz. Se lee de arriba abajo: si fue seleccionado, cuál es su beneficio, cuánto ha recibido y el estado de cada pago (pagado, pendiente o retrasado). Si un pago se atrasa, lo ve de primeras con el motivo.' },
      { nombre: 'Convocatoria abierta', vista: '01-mi-proceso', estado: 'convocatoria-abierta', sinConteo: true,
        texto: 'Cuando Bienestar abre una convocatoria, aparece aquí con el beneficio, el valor de cada pago, el número de pagos y la fecha de cierre. Es el único lugar con el botón «Postularme». Si no hay ninguna, dice «En el momento no hay convocatorias disponibles».' },
      { nombre: 'Formulario de postulación', vista: '09-postulacion', estado: 'formulario',
        texto: 'El aprendiz se postula dentro de BenefitRack. Sus datos de la cuenta ya vienen llenos; completa contacto, EPS, cuenta para los pagos y sube 3 documentos (identidad, Certificado de EPS y certificado bancario). Antes de enviar ve un resumen y, si falta algo, el formulario le dice exactamente qué.' },
      { nombre: 'Avisos', vista: '01b-avisos', estado: 'con-avisos',
        texto: 'La campanita de arriba. Muestra los avisos más recientes primero: postulación aprobada, pago registrado, pago retrasado o un documento que Bienestar le pidió. Los no leídos tienen un punto.' },
      { nombre: 'Subir un documento pedido', vista: '08-subir-documento', estado: 'elegir-archivo',
        texto: 'Si Bienestar pide un documento, el aprendiz lo sube aquí: PDF, JPG o PNG de máximo 5 MB. Ve el progreso de la carga y una confirmación al terminar.' },
    ],
  },
  {
    titulo: 'Lo que ve la administradora',
    intro: 'Bienestar al Aprendiz revisa las postulaciones, confirma los datos, aprueba o rechaza, registra los pagos y crea las cuentas. El sistema solo registra los pagos: no mueve dinero.',
    pantallas: [
      { nombre: 'Dashboard', vista: '00-dashboard', estado: 'con-pendientes',
        texto: 'La página de inicio. Arriba, lo que necesita atención hoy (postulaciones sin confirmar, documentos por revisar y pagos retrasados), cada uno con un enlace directo. Debajo, cuatro cifras clave. Sin gráficas.' },
      { nombre: 'Postulaciones', pc: '00-existentes/postulaciones/html/computador-normal.html', cel: '00-existentes/postulaciones/html/celular-normal.html', n: 6,
        texto: 'La tabla con todas las postulaciones. Se puede buscar, filtrar por beneficio, programa, estado y fecha, y descargar en Excel eligiendo las columnas. Reemplaza el Excel que hoy se llena a mano. Los documentos van solo con números para copiarlos y pegarlos.' },
      { nombre: 'Detalle de una postulación', vista: '03-detalle-postulacion', estado: 'pendiente',
        texto: 'Todo lo que el aprendiz envió, con sus documentos. La administradora confirma los datos contra la base del SENA, puede pedir un documento y después aprueba o rechaza (con motivo). «Aprobar» se bloquea si no hay cupos y la pantalla dice por qué.' },
      { nombre: 'Reporte de aprobados', vista: '04-reporte-aprobados', estado: 'dialogo',
        texto: 'Descarga en Excel de los aprendices aprobados de una convocatoria, para quien hace los pagos: nombre, documento, ficha, programa, beneficio, valor y datos bancarios. Las columnas se pueden quitar o agregar. Advierte que el archivo tiene datos bancarios.' },
      { nombre: 'Pagos', vista: '05-pagos', estado: 'lista',
        texto: 'Registro de los pagos de cada beneficiario. Se pueden marcar varios como pagados de una vez (con un resumen de cantidad y total antes de confirmar) y avisar a los aprendices cuando un pago se retrasa, con el mismo motivo para todos o uno para cada uno.' },
      { nombre: 'Usuarios', vista: '06-usuarios', estado: 'vista-previa',
        texto: 'Creación de las cuentas de los aprendices cargando un Excel con la plantilla del sistema. Antes de crear nada, muestra qué filas tienen errores y por qué. También se puede crear una cuenta suelta a mano.' },
      { nombre: 'Convocatorias', vista: '07-convocatorias', estado: 'lista',
        texto: 'Crear, editar y cerrar convocatorias: nombre, beneficio, fechas, cupos, requisitos, valor de cada pago, número de pagos y duración. Desde aquí se descarga también el reporte de aprobados.' },
    ],
  },
];

async function capturar(page, archivo, ancho, alto) {
  await page.setViewportSize({ width: ancho, height: alto });
  await page.goto('file:///' + archivo.replace(/\\/g, '/'), { waitUntil: 'networkidle' });
  await page.waitForTimeout(250);
  const buf = await page.screenshot({ type: 'jpeg', quality: 82 });
  return 'data:image/jpeg;base64,' + buf.toString('base64');
}

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;');

(async () => {
  const navegador = await chromium.launch({ channel: 'msedge' });
  const page = await navegador.newPage();
  let numero = 0;
  let cuerpo = '';
  let totalEstados = 0;
  for (const g of grupos) {
    let tarjetas = '';
    for (const p of g.pantallas) {
      numero += 1;
      const pc = path.join(V, p.pc || `${p.vista}/html/${p.estado}.html`);
      const cel = path.join(V, p.cel || `${p.vista}/html/${p.estado}.html`);
      const imgPc = await capturar(page, pc, 1440, 900);
      const imgCel = await capturar(page, cel, 390, 844);
      const n = p.n || (estados[p.vista] ? estados[p.vista].estados.length : 0);
      if (!p.sinConteo) totalEstados += n;
      const conteo = p.sinConteo ? 'Parte de «Mi proceso»' : `${n} versiones diseñadas`;
      tarjetas += `
      <article class="pantalla">
        <header class="pantalla__cabeza">
          <span class="pantalla__num">${String(numero).padStart(2, '0')}</span>
          <div>
            <h3>${esc(p.nombre)}</h3>
            <p class="pantalla__meta">${conteo} · computador y celular</p>
          </div>
        </header>
        <p class="pantalla__texto">${esc(p.texto)}</p>
        <div class="capturas">
          <figure class="captura captura--pc"><img src="${imgPc}" alt="${esc(p.nombre)} en computador"><figcaption>Computador</figcaption></figure>
          <figure class="captura captura--cel"><img src="${imgCel}" alt="${esc(p.nombre)} en celular"><figcaption>Celular</figcaption></figure>
        </div>
      </article>`;
      process.stdout.write('.');
    }
    cuerpo += `
    <section class="grupo">
      <div class="grupo__cabeza">
        <h2>${esc(g.titulo)}</h2>
        <p>${esc(g.intro)}</p>
      </div>
      ${tarjetas}
    </section>`;
  }

  const html = `<title>Pantallas de BenefitRack</title>
<meta name="claude:viewport-fit-sides" content="cover">
<meta charset="utf-8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700&display=swap">
<style>
/* Catálogo: portada sobria con el azul SENA, luego una ficha por pantalla (texto a la izquierda/arriba, capturas computador + celular). */
:root {
  --azul: #00304D;
  --verde: #007832;
  --verde-claro: #6BD11F;
  --fondo: #F5F7F9;
  --papel: #FFFFFF;
  --tinta: #0E2433;
  --gris: #556776;
  --borde: #E3E8EC;
  --sobre-azul: #FFFFFF;
  --letra: 'Work Sans', Inter, system-ui, sans-serif;
  color-scheme: light;
}
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    --fondo: #0B1A24; --papel: #10232F; --tinta: #E8EEF2; --gris: #9FB0BC; --borde: #22394A;
    --azul: #00304D; --verde: #3DA35D; color-scheme: dark;
  }
}
:root[data-theme="dark"] {
  --fondo: #0B1A24; --papel: #10232F; --tinta: #E8EEF2; --gris: #9FB0BC; --borde: #22394A;
  --azul: #00304D; --verde: #3DA35D; color-scheme: dark;
}
* { box-sizing: border-box; }
body { margin: 0; background: var(--fondo); color: var(--tinta); font-family: var(--letra); font-size: 15px; line-height: 1.55; }
.envoltura { max-width: 1180px; margin: 0 auto; padding-block: 0 64px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); display: grid; gap: 56px; }
.portada { background: var(--azul); color: var(--sobre-azul); padding-block: 56px 48px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); }
.portada__in { max-width: 1180px; margin: 0 auto; display: grid; gap: 18px; }
.marca { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
.marca strong { font-size: 15px; font-weight: 700; letter-spacing: .02em; }
.marca span { font-size: 13px; opacity: .8; letter-spacing: .06em; text-transform: uppercase; }
.portada h1 { margin: 0; font-size: clamp(32px, 5vw, 52px); line-height: 1.08; font-weight: 700; letter-spacing: -.02em; text-wrap: balance; max-width: 18ch; }
.portada h1 em { font-style: normal; color: var(--verde-claro); }
.portada p { margin: 0; max-width: 62ch; font-size: 17px; opacity: .92; }
.datos { display: flex; flex-wrap: wrap; gap: 10px 28px; margin-top: 8px; font-size: 14px; }
.datos b { font-size: 22px; font-weight: 700; display: block; font-variant-numeric: tabular-nums; }
.nota { font-size: 13px !important; opacity: .75 !important; }
.grupo { display: grid; gap: 28px; }
.grupo__cabeza { display: grid; gap: 6px; border-bottom: 2px solid var(--verde); padding-bottom: 14px; }
.grupo__cabeza h2 { margin: 0; font-size: 26px; letter-spacing: -.01em; }
.grupo__cabeza p { margin: 0; color: var(--gris); max-width: 70ch; }
.pantalla { background: var(--papel); border: 1px solid var(--borde); border-radius: 14px; padding: 24px; display: grid; gap: 14px; break-inside: avoid; page-break-inside: avoid; }
.pantalla__cabeza { display: flex; gap: 14px; align-items: center; }
.pantalla__num { font-size: 13px; font-weight: 700; color: var(--verde); border: 1.5px solid var(--verde); border-radius: 8px; padding: 4px 8px; font-variant-numeric: tabular-nums; }
.pantalla h3 { margin: 0; font-size: 21px; line-height: 1.2; }
.pantalla__meta { margin: 2px 0 0; font-size: 12.5px; color: var(--gris); letter-spacing: .02em; }
.pantalla__texto { margin: 0; max-width: 78ch; }
.capturas { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 210px); gap: 18px; align-items: start; }
.captura { margin: 0; display: grid; gap: 6px; }
.captura img { width: 100%; max-width: 100%; height: auto; display: block; border: 1px solid var(--borde); border-radius: 8px; background: #fff; }
.captura--cel img { border-radius: 16px; }
.captura figcaption { font-size: 12px; color: var(--gris); text-transform: uppercase; letter-spacing: .08em; }
.pie { color: var(--gris); font-size: 13px; border-top: 1px solid var(--borde); padding-top: 16px; }
@media (max-width: 640px) {
  .capturas { grid-template-columns: 1fr; }
  .captura--cel { max-width: 240px; }
  .pantalla { padding: 18px; }
}
@media print {
  @page { size: A4 landscape; margin: 12mm; }
  body { background: #fff; font-size: 12px; }
  .portada { break-after: page; padding: 40px; -webkit-print-color-adjust: exact; print-color-adjust: exact; min-height: 170mm; display: flex; align-items: center; }
  .envoltura { gap: 0; padding: 0; max-width: none; }
  .grupo { gap: 0; }
  .grupo__cabeza { margin-bottom: 10px; }
  .pantalla { break-before: page; border: none; padding: 0; grid-template-columns: 300px 1fr; column-gap: 24px; align-content: start; }
  .grupo .pantalla:first-of-type { break-before: avoid; }
  .pantalla__cabeza, .pantalla__texto { grid-column: 1; }
  .capturas { grid-column: 2; grid-row: 1 / span 3; grid-template-columns: minmax(0, 1fr) 150px; }
  .pantalla__texto { font-size: 13px; }
  .grupo { break-before: page; }
  .pie { display: none; }
}
</style>
<div class="portada"><div class="portada__in">
  <div class="marca"><strong>BenefitRack</strong><span>Bienestar al Aprendiz · SENA</span></div>
  <h1>Las pantallas de <em>BenefitRack</em></h1>
  <p>Así se verá el sistema para gestionar los beneficios de los aprendices (alimentación, plan de datos y tarjeta cívica): desde la postulación hasta el registro de cada pago. Cada pantalla se muestra en computador y en celular, con una explicación corta de para qué sirve.</p>
  <div class="datos">
    <div><b>${numero}</b>pantallas</div>
    <div><b>${totalEstados}</b>versiones diseñadas</div>
    <div><b>2</b>tipos de usuario</div>
  </div>
  <p class="nota">Son maquetas de diseño: muestran cómo se verá y cómo funcionará el sistema, todavía sin programar. Los nombres y datos son de ejemplo.</p>
</div></div>
<main class="envoltura">
${cuerpo}
  <p class="pie">BenefitRack · Proyecto formativo ADSO, ficha 3425429 · Centro Textil y de Gestión Industrial · Versión de las pantallas 2.1.1 (noviembre de 2026).</p>
</main>`;

  fs.mkdirSync(SALIDA, { recursive: true });
  const archivoHtml = path.join(SALIDA, 'Pantallas_BenefitRack.html');
  fs.writeFileSync(archivoHtml, html, 'utf8');
  const pdfPage = await navegador.newPage();
  await pdfPage.goto('file:///' + archivoHtml.replace(/\\/g, '/'), { waitUntil: 'networkidle' });
  await pdfPage.emulateMedia({ media: 'print' });
  await pdfPage.pdf({ path: path.join(SALIDA, 'Pantallas_BenefitRack.pdf'), format: 'A4', landscape: true, printBackground: true, preferCSSPageSize: true });
  // Vista previa de dos páginas del PDF como imagen, para revisar
  await pdfPage.setViewportSize({ width: 1440, height: 900 });
  await pdfPage.emulateMedia({ media: 'screen' });
  await pdfPage.screenshot({ path: path.join(SALIDA, '..', 'preview.png'), fullPage: false });
  await navegador.close();
  console.log('\nlisto', numero, 'pantallas,', totalEstados, 'versiones');
})().catch((e) => { console.error(e); process.exit(1); });
