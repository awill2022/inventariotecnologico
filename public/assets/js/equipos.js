/**
 * equipos.js — Módulo de equipos:
 * 1. Alta: propone el código según la categoría elegida (mientras no se escriba a mano).
 * 2. Listado: selección de equipos para imprimir sus etiquetas.
 * Sin JavaScript todo sigue funcionando: el código se escribe a mano y el servidor valida.
 */
(function () {
  'use strict';

  /* ---------- 1. Código propuesto por categoría ---------- */
  var categoria = document.querySelector('select[data-codigos]');
  var codigo = document.getElementById('campo-codigo');

  if (categoria && codigo) {
    var propuestos = {};
    try { propuestos = JSON.parse(categoria.dataset.codigos) || {}; } catch (e) { propuestos = {}; }

    var valores = Object.keys(propuestos).map(function (k) { return propuestos[k]; });
    // Se considera "escrito a mano" si el valor no es ninguno de los propuestos
    var aMano = codigo.value.trim() !== '' && valores.indexOf(codigo.value.trim()) === -1;

    codigo.addEventListener('input', function () {
      aMano = codigo.value.trim() !== '' && valores.indexOf(codigo.value.trim()) === -1;
    });

    function proponer() {
      if (aMano) return;
      var sugerido = propuestos[categoria.value];
      codigo.value = sugerido || '';
      if (sugerido && codigo.getAttribute('aria-invalid') === 'true') {
        window.marcarError(codigo, codigo.id + '-error', '');
      }
    }

    categoria.addEventListener('change', proponer);
    if (codigo.value.trim() === '') proponer();
  }

  /* ---------- 2. Selección para imprimir etiquetas ---------- */
  var barra = document.querySelector('[data-seleccion]');
  if (barra) {
    var casillas = Array.prototype.slice.call(document.querySelectorAll('input[data-equipo]'));
    var todos = barra.querySelector('[data-seleccionar-todos]');
    var imprimir = barra.querySelector('[data-imprimir-seleccion]');
    var contador = barra.querySelector('[data-contador]');

    function actualizar() {
      var marcadas = casillas.filter(function (c) { return c.checked; }).length;
      imprimir.disabled = marcadas === 0;
      contador.textContent = marcadas ? '(' + marcadas + ')' : '';
      todos.checked = marcadas === casillas.length;
      todos.indeterminate = marcadas > 0 && marcadas < casillas.length;
    }

    barra.querySelectorAll('[data-solo-js]').forEach(function (el) { el.hidden = false; });
    casillas.forEach(function (c) { c.addEventListener('change', actualizar); });
    todos.addEventListener('change', function () {
      casillas.forEach(function (c) { c.checked = todos.checked; });
      actualizar();
    });
    actualizar();
  }
})();
