/**
 * BenefitRack · JavaScript propio (se carga después de bootstrap.bundle.min.js).
 *
 * Es lo mínimo: la aplicación funciona sin JavaScript. Aquí solo hay mejoras:
 *   1. Al enviar un formulario, el botón de envío se desactiva para evitar doble clic
 *      (dos postulaciones, dos pagos...).
 *   2. Los avisos de éxito desaparecen solos; los de error y de aviso se quedan hasta que se lean.
 */
(function () {
  'use strict';

  // 1. Evitar el doble envío.
  document.addEventListener('submit', function (evento) {
    var formulario = evento.target;
    if (!(formulario instanceof HTMLFormElement)) {
      return;
    }
    var boton = formulario.querySelector('button[type="submit"]');
    if (boton) {
      // Se desactiva un instante después, para no impedir el envío mismo.
      setTimeout(function () { boton.disabled = true; }, 0);
    }
  });

  // Si la persona vuelve con el botón «Atrás», la página puede venir de la memoria del navegador:
  // se reactivan los botones.
  window.addEventListener("pageshow", function () {
    document.querySelectorAll("button[type=submit]").forEach(function (boton) { boton.disabled = false; });
  });

  // 2. Los avisos de éxito se ocultan a los 6 segundos.
  document.querySelectorAll('.br-alerta--exito').forEach(function (alerta) {
    setTimeout(function () { alerta.hidden = true; }, 6000);
  });
})();
