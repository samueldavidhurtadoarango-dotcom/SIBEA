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
    // Si otra parte (la pre-validación de la postulación) ya detuvo el envío, el botón no se desactiva.
    if (!(formulario instanceof HTMLFormElement) || evento.defaultPrevented) {
      return;
    }
    var boton = formulario.querySelector('button[type="submit"]');
    if (boton) {
      // Se desactiva un instante después, para no impedir el envío mismo.
      setTimeout(function () { boton.disabled = true; }, 0);
      if (formulario.hasAttribute('data-cargando')) {
        mostrarCargando(formulario, boton);
      }
    }
  });

  // Estado «cargando» del inicio de sesión: botón con giro y texto «Ingresando…», campos de solo lectura
  // (readonly y no disabled: los campos disabled no se envían).
  function mostrarCargando(formulario, boton) {
    boton.dataset.textoOriginal = boton.textContent;
    boton.setAttribute('aria-busy', 'true');
    boton.innerHTML = '<span class="br-boton-ingresar__giro" aria-hidden="true"></span>';
    boton.append(boton.dataset.textoCargando || 'Cargando…');
    formulario.querySelectorAll('input:not([type=hidden])').forEach(function (campo) { campo.readOnly = true; });
  }

  function quitarCargando(formulario, boton) {
    if (boton.dataset.textoOriginal) {
      boton.textContent = boton.dataset.textoOriginal;
    }
    boton.removeAttribute('aria-busy');
    formulario.querySelectorAll('input:not([type=hidden])').forEach(function (campo) { campo.readOnly = false; });
  }

  // Si la persona vuelve con el botón «Atrás», la página puede venir de la memoria del navegador:
  // se reactivan los botones y se quita el estado «cargando».
  window.addEventListener("pageshow", function () {
    document.querySelectorAll("button[type=submit]").forEach(function (boton) {
      boton.disabled = false;
      if (boton.form && boton.form.hasAttribute('data-cargando')) {
        quitarCargando(boton.form, boton);
      }
    });
  });

  // Ojo de la contraseña: alterna entre ocultarla y mostrarla.
  document.addEventListener('click', function (evento) {
    var ojo = evento.target instanceof Element ? evento.target.closest('[data-mostrar-clave]') : null;
    if (!ojo) {
      return;
    }
    var campo = ojo.parentElement.querySelector('input');
    var visible = campo.type === 'password';
    campo.type = visible ? 'text' : 'password';
    ojo.setAttribute('aria-pressed', visible ? 'true' : 'false');
    ojo.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
  });

  // Reglas de la contraseña nueva, marcadas mientras la persona escribe (pantalla «Crea tu contraseña»).
  // Son las mismas que valida el servidor (ValidadorContrasena.php); el servidor es quien decide de verdad.
  var REGLAS_CLAVE = {
    longitud: function (texto) { return Array.from(texto).length >= 8; },
    mayuscula: function (texto) { return /\p{Lu}/u.test(texto); },
    minuscula: function (texto) { return /\p{Ll}/u.test(texto); },
    numero: function (texto) { return /[0-9]/.test(texto); }
  };
  // Trazo del ícono y texto para lectores de pantalla de cada estado (el color solo no basta).
  var MARCAS_REGLA = {
    cumplida: { trazo: 'm8 12.5 3 3 5-6', aviso: 'Cumplida: ' },
    pendiente: { trazo: '', aviso: 'Pendiente: ' }
  };

  function marcarReglas(formulario) {
    var texto = formulario.querySelector('[data-campo-nueva]').value.trim(); // el servidor también recorta (Peticion::campo)
    formulario.querySelectorAll('[data-regla]').forEach(function (regla) {
      var estado = REGLAS_CLAVE[regla.dataset.regla](texto) ? 'cumplida' : 'pendiente';
      regla.dataset.estado = estado;
      regla.querySelector('[data-marca]').setAttribute('d', MARCAS_REGLA[estado].trazo);
      regla.querySelector('[data-aviso]').textContent = MARCAS_REGLA[estado].aviso;
    });
  }

  document.addEventListener('input', function (evento) {
    var campo = evento.target;
    if (campo instanceof HTMLInputElement && campo.hasAttribute('data-campo-nueva')) {
      marcarReglas(campo.form);
    }
  });

  // 2. Los avisos de éxito se ocultan a los 6 segundos.
  document.querySelectorAll('.br-alerta--exito').forEach(function (alerta) {
    setTimeout(function () { alerta.hidden = true; }, 6000);
  });

  // 3. Ventanas (modales) de las listas de usuarios y convocatorias: al abrirse, toman de la fila que las abrió
  //    a dónde se envía el formulario (data-accion), el estado nuevo (data-estado), el nombre y el título.
  document.querySelectorAll('.modal').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (evento) {
      var origen = evento.relatedTarget;
      if (!origen) {
        return;
      }
      modal.querySelector('form').action = origen.dataset.accion || '';
      modal.querySelectorAll('[data-modal-nombre]').forEach(function (el) { el.textContent = origen.dataset.nombre || ''; });
      var titulo = modal.querySelector('[data-modal-titulo]');
      if (titulo && origen.dataset.titulo) {
        titulo.textContent = origen.dataset.titulo;
      }
      var estado = modal.querySelector('input[name="estado"]');
      if (estado) {
        estado.value = origen.dataset.estado || '';
      }
      // Ventana de cerrar convocatoria: resumen y explicación de la fila, y el motivo solo si el cierre es anticipado.
      ['resumen', 'explicacion'].forEach(function (clave) {
        modal.querySelectorAll('[data-modal-' + clave + ']').forEach(function (el) { el.textContent = origen.dataset[clave] || ''; });
      });
      var accion = modal.querySelector('input[name="accion"]');
      if (accion) {
        accion.value = origen.dataset.cambio || '';
      }
      var motivo = modal.querySelector('[data-modal-motivo]');
      if (motivo) {
        var pideMotivo = origen.dataset.cambio === 'cerrar-anticipado';
        motivo.hidden = !pideMotivo;
        motivo.querySelector('textarea').required = pideMotivo;
      }
      // El menú «⋯» de la fila se cierra al abrir la ventana.
      var menu = origen.closest('details');
      if (menu) {
        menu.removeAttribute('open');
      }
    });
  });

  // 4. Formulario de postulación (data-prevalidar). Solo AVISA antes de enviar y muestra el nombre del archivo
  //    elegido. El servidor vuelve a validar todo (ValidadorPostulacion.php): esto se puede saltar, aquello no.
  var TAMANO_MAXIMO = 5 * 1024 * 1024; // 5 MB, igual que ValidadorArchivo.php
  var EXTENSIONES = /\.(pdf|jpe?g|png)$/i;

  // Mensaje de un campo obligatorio o de un archivo que no cumple; '' si está bien.
  function problemaDe(campo) {
    if (campo.type === 'file') {
      var archivo = campo.files[0];
      if (!archivo) { return 'Falta el archivo'; }
      if (archivo.size > TAMANO_MAXIMO) { return 'El archivo pesa más de 5 MB'; }
      return EXTENSIONES.test(archivo.name) ? '' : 'Solo se aceptan PDF, JPG o PNG';
    }
    if (campo.type === 'checkbox') {
      return campo.checked ? '' : 'Marca la declaración para continuar';
    }
    return campo.value.trim() === '' ? 'Este dato es obligatorio' : '';
  }

  function mostrarProblema(campo, mensaje) {
    var caja = campo.closest('.br-campo, .br-casilla-texto');
    var viejo = caja.querySelector('[data-aviso-js]');
    if (viejo) { viejo.remove(); }
    campo.removeAttribute('aria-invalid');
    if (mensaje === '') { return; }
    campo.setAttribute('aria-invalid', 'true');
    var parrafo = document.createElement('p');
    parrafo.className = 'br-error-campo';
    parrafo.setAttribute('data-aviso-js', '');
    parrafo.textContent = mensaje;
    caja.appendChild(parrafo);
  }

  // Fase de captura: corre antes que el desactivado del botón (punto 1), que respeta el envío ya detenido.
  document.addEventListener('submit', function (evento) {
    var formulario = evento.target;
    if (!(formulario instanceof HTMLFormElement) || !formulario.hasAttribute('data-prevalidar')) {
      return;
    }
    var primero = null;
    formulario.querySelectorAll('[data-requerido]').forEach(function (campo) {
      var mensaje = problemaDe(campo);
      mostrarProblema(campo, mensaje);
      if (mensaje !== '' && primero === null) { primero = campo; }
    });
    if (primero !== null) {
      evento.preventDefault();
      primero.focus();
    }
  }, true);

  document.addEventListener('change', function (evento) {
    var campo = evento.target;
    if (!(campo instanceof HTMLInputElement) || !campo.hasAttribute('data-archivo')) {
      return;
    }
    var texto = campo.form.querySelector('[data-elegido="' + campo.id + '"]');
    if (texto) {
      texto.textContent = campo.files[0] ? campo.files[0].name : '';
    }
  });
})();
