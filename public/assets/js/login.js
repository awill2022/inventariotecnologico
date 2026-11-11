/**
 * login.js — Validación en el navegador, mostrar/ocultar contraseña
 * y estado de envío. El servidor vuelve a validar todo.
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var form = document.getElementById('form-login');
  if (!form) return;

  var usuario = document.getElementById('usuario');
  var clave = document.getElementById('clave');
  var boton = document.getElementById('btn-ingresar');
  var toggle = document.getElementById('toggle-clave');
  var PATRON_USUARIO = /^[A-Za-z0-9._@-]+$/;

  function errorUsuario() {
    var v = usuario.value.trim();
    if (v === '') return 'Escribe tu usuario.';
    if (v.length < 3 || v.length > 80) return 'El usuario debe tener entre 3 y 80 caracteres.';
    if (!PATRON_USUARIO.test(v)) return 'El usuario solo admite letras, números y . _ - @ (sin espacios).';
    return '';
  }

  function errorClave() {
    if (clave.value === '') return 'Escribe tu contraseña.';
    if (clave.value.length > 255) return 'La contraseña es demasiado larga.';
    return '';
  }

  form.addEventListener('submit', function (e) {
    var eu = errorUsuario();
    var ec = errorClave();
    marcarError(usuario, 'e-usuario', eu);
    marcarError(clave, 'e-clave', ec);

    if (eu || ec) {
      e.preventDefault();
      (eu ? usuario : clave).focus();
      return;
    }

    // Evita envíos dobles mientras el servidor responde
    boton.disabled = true;
    boton.textContent = 'Ingresando…';
  });

  // El error desaparece en cuanto el campo es válido
  usuario.addEventListener('input', function () {
    if (usuario.getAttribute('aria-invalid') && !errorUsuario()) marcarError(usuario, 'e-usuario', '');
  });
  clave.addEventListener('input', function () {
    if (clave.getAttribute('aria-invalid') && !errorClave()) marcarError(clave, 'e-clave', '');
  });

  // Mostrar / ocultar contraseña
  toggle.addEventListener('click', function () {
    var visible = clave.type === 'text';
    clave.type = visible ? 'password' : 'text';
    toggle.setAttribute('aria-pressed', String(!visible));
    toggle.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
    toggle.querySelector('use').setAttribute('href', visible ? '#i-eye' : '#i-eye-off');
    clave.focus();
  });

  // Si el navegador vuelve a esta página desde el historial, reactivar el botón
  window.addEventListener('pageshow', function () {
    if (!form.querySelector('input:disabled')) {
      boton.disabled = false;
      boton.textContent = 'Ingresar';
    }
  });
});
