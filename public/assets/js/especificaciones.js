/**
 * especificaciones.js — Editor de especificaciones: agregar y quitar filas.
 * Quitar vacía los campos y oculta la fila; el servidor elimina las filas vacías.
 * Sin JavaScript se usan las filas vacías que trae el formulario.
 */
(function () {
  'use strict';

  var lista = document.querySelector('[data-specs]');
  var agregar = document.querySelector('[data-agregar]');
  if (!lista || !agregar) return;

  var siguiente = lista.querySelectorAll('[data-spec-fila]').length;

  function activarQuitar(fila) {
    var boton = fila.querySelector('[data-quitar]');
    boton.hidden = false;
    boton.addEventListener('click', function () {
      fila.querySelectorAll('input[type="text"]').forEach(function (campo) { campo.value = ''; });
      fila.classList.add('is-quitada');
      agregar.focus();
    });
  }

  lista.querySelectorAll('[data-spec-fila]').forEach(activarQuitar);

  agregar.hidden = false;
  agregar.addEventListener('click', function () {
    var modelo = lista.querySelector('[data-spec-fila]');
    var fila = modelo.cloneNode(true);
    var i = siguiente++;

    fila.classList.remove('is-quitada');
    fila.querySelectorAll('input').forEach(function (campo) {
      campo.name = campo.name.replace(/filas\[\d+\]/, 'filas[' + i + ']');
      campo.id = campo.id ? campo.id.replace(/spec-\d+-/, 'spec-' + i + '-') : campo.id;
      if (campo.hasAttribute('aria-describedby')) {
        campo.setAttribute('aria-describedby', campo.getAttribute('aria-describedby').replace(/spec-\d+-/, 'spec-' + i + '-'));
      }
      campo.removeAttribute('aria-invalid');
      campo.value = '';
    });
    fila.querySelectorAll('label').forEach(function (label) {
      label.htmlFor = label.htmlFor.replace(/spec-\d+-/, 'spec-' + i + '-');
    });
    fila.querySelectorAll('.field-error').forEach(function (p) {
      p.id = p.id.replace(/spec-\d+-/, 'spec-' + i + '-');
      p.hidden = true;
      p.textContent = '';
    });
    // El botón clonado no trae el evento: se reemplaza por uno limpio
    var boton = fila.querySelector('[data-quitar]');
    boton.replaceWith(boton.cloneNode(true));

    lista.appendChild(fila);
    activarQuitar(fila);
    fila.querySelector('input[type="text"]').focus();
  });
})();
