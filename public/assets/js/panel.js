/**
 * panel.js — Comportamiento de las páginas del panel:
 * confirmación antes de desactivar y validación de formularios en el navegador.
 * Sin JavaScript todo sigue funcionando: el servidor vuelve a validar.
 */
(function () {
  'use strict';

  /* ---------- Confirmación (formularios con data-confirmar) ---------- */
  var ventana = null;
  var formPendiente = null;

  function crearVentana() {
    var d = document.createElement('dialog');
    d.className = 'confirmar';
    d.setAttribute('aria-labelledby', 'confirmar-titulo');
    d.innerHTML =
      '<h2 id="confirmar-titulo">Confirmar</h2>' +
      '<p id="confirmar-texto"></p>' +
      '<div class="confirmar-acciones">' +
      '<button type="button" class="btn btn-danger" data-aceptar></button>' +
      '<button type="button" class="btn btn-outline" data-cancelar>Cancelar</button>' +
      '</div>';
    document.body.appendChild(d);

    d.querySelector('[data-cancelar]').addEventListener('click', function () { d.close(); });
    d.querySelector('[data-aceptar]').addEventListener('click', function () {
      var form = formPendiente;
      d.close();
      if (form) {
        form.dataset.confirmado = '1';
        form.submit();
      }
    });
    // Tocar el fondo oscuro cierra la ventana
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
    d.addEventListener('close', function () { formPendiente = null; });
    return d;
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches || !form.matches('form[data-confirmar]') || form.dataset.confirmado) return;
    if (typeof HTMLDialogElement === 'undefined') return; // navegador antiguo: envía sin preguntar

    e.preventDefault();
    if (!ventana) ventana = crearVentana();
    formPendiente = form;
    ventana.querySelector('#confirmar-texto').textContent = form.dataset.confirmar;
    ventana.querySelector('[data-aceptar]').textContent = form.dataset.confirmarBoton || 'Aceptar';
    ventana.showModal();
    ventana.querySelector('[data-cancelar]').focus();
  });

  /* ---------- Validación de formularios (data-form-validar) ---------- */
  function mensajeDe(campo) {
    var esClave = campo.type === 'password';
    var valor = esClave ? campo.value : campo.value.trim(); // en las claves los espacios cuentan
    if (campo.required && valor === '') {
      return campo.tagName === 'SELECT' ? 'Elige una opción.' : 'Este campo es obligatorio.';
    }
    if (valor === '') return '';

    // Confirmación: debe coincidir con la clave indicada en data-igual-a
    if (campo.dataset.igualA) {
      var otra = document.getElementById(campo.dataset.igualA);
      return otra && otra.value !== valor ? 'Las claves no coinciden.' : '';
    }
    var min = parseInt(campo.getAttribute('minlength'), 10);
    if (min && valor.length < min) {
      return esClave ? 'La clave debe tener al menos ' + min + ' caracteres.'
                     : 'Escribe al menos ' + min + ' caracteres.';
    }
    // Misma política que Clave::problema() en el servidor
    if (campo.hasAttribute('data-politica') && !(/\p{L}/u.test(valor) && /\d/.test(valor))) {
      return 'Incluye al menos una letra y un número.';
    }
    return '';
  }

  function validar(campo) {
    var mensaje = mensajeDe(campo);
    window.marcarError(campo, campo.id + '-error', mensaje);
    return mensaje === '';
  }

  document.querySelectorAll('form[data-form-validar]').forEach(function (form) {
    var campos = form.querySelectorAll(
      'input[type="text"]:not([hidden]), input[type="password"], select, textarea'
    );

    campos.forEach(function (campo) {
      campo.addEventListener('blur', function () { validar(campo); });
      campo.addEventListener('input', function () {
        if (campo.getAttribute('aria-invalid') === 'true') validar(campo);
        // Si cambia la clave, se revisa de nuevo su confirmación ya marcada
        var confirmacion = form.querySelector('[data-igual-a="' + campo.id + '"]');
        if (confirmacion && confirmacion.getAttribute('aria-invalid') === 'true') validar(confirmacion);
      });
    });

    form.addEventListener('submit', function (e) {
      var primero = null;
      campos.forEach(function (campo) {
        if (!validar(campo) && !primero) primero = campo;
      });
      if (primero) {
        e.preventDefault();
        primero.focus();
        return;
      }
      var boton = form.querySelector('button[type="submit"]');
      if (boton) boton.disabled = true;
    });
  });
})();
