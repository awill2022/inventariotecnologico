/**
 * app.js — Utilidades compartidas por todas las páginas.
 */
(function () {
  'use strict';

  /** Marca o desmarca un campo con error y muestra su mensaje. */
  window.marcarError = function (campo, idError, mensaje) {
    var caja = document.getElementById(idError);
    var hayError = Boolean(mensaje);
    if (hayError) {
      campo.setAttribute('aria-invalid', 'true');
    } else {
      campo.removeAttribute('aria-invalid');
    }
    if (caja) {
      if (hayError) caja.textContent = mensaje;
      caja.hidden = !hayError;
    }
  };

  /** true si el usuario prefiere movimiento reducido. */
  window.prefiereMenosMovimiento = function () {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  };

  /* Menú del encabezado en celulares */
  document.addEventListener('DOMContentLoaded', function () {
    var boton = document.querySelector('.menu-toggle');
    var menu = document.getElementById('menu-principal');
    var velo = document.getElementById('menu-scrim');
    if (!boton || !menu) return;

    function estaAbierto() {
      return boton.getAttribute('aria-expanded') === 'true';
    }

    function abrir() {
      menu.classList.add('is-open');
      if (velo) velo.classList.add('is-visible');
      boton.setAttribute('aria-expanded', 'true');
    }

    /* devolverFoco: true cuando se cierra con Escape, para no perder la posición del teclado */
    function cerrar(devolverFoco) {
      if (!estaAbierto()) return;
      menu.classList.remove('is-open');
      if (velo) velo.classList.remove('is-visible');
      boton.setAttribute('aria-expanded', 'false');
      if (devolverFoco) boton.focus();
    }

    boton.addEventListener('click', function () {
      if (estaAbierto()) cerrar(false);
      else abrir();
    });

    menu.querySelectorAll('a').forEach(function (enlace) {
      enlace.addEventListener('click', function () { cerrar(false); });
    });

    // Tocar fuera del menú (sobre el velo) lo cierra sin activar lo que hay debajo
    if (velo) velo.addEventListener('click', function () { cerrar(false); });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && estaAbierto()) cerrar(true);
    });

    // Si la pantalla crece hasta mostrar la navegación completa, el menú desplegable deja de existir
    var escritorio = window.matchMedia('(min-width: 901px)');
    var alCambiar = function (mq) { if (mq.matches) cerrar(false); };
    if (escritorio.addEventListener) escritorio.addEventListener('change', alCambiar);
    else if (escritorio.addListener) escritorio.addListener(alCambiar);
  });
})();
