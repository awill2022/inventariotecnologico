/**
 * docente.js — Página de inicio del rol consulta.
 * QR de cada etiqueta, ficha desplegable, búsqueda y filtros de "Mis equipos",
 * vínculo chip de código → etiqueta y «Reportar falla» desde una etiqueta. El lector QR está en lector-qr.js.
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  /* ---------- 1. QR real de cada etiqueta (librería qrcode-generator) ----------
     Codifica la URL pública del equipo: /q/{qr_token}. */
  document.querySelectorAll('.tag-qr[data-qr]').forEach(function (caja) {
    if (typeof qrcode !== 'function') return;
    var qr = qrcode(0, 'M');
    qr.addData(caja.dataset.qr);
    qr.make();
    caja.innerHTML = dibujarQR(qr);
  });

  function dibujarQR(qr) {
    var n = qr.getModuleCount();
    var d = '';
    for (var y = 0; y < n; y++) {
      for (var x = 0; x < n; x++) {
        if (qr.isDark(y, x)) d += 'M' + x + ' ' + y + 'h1v1h-1z';
      }
    }
    return '<svg viewBox="0 0 ' + n + ' ' + n + '" shape-rendering="crispEdges" aria-hidden="true">' +
           '<path fill="#121e30" d="' + d + '"/></svg>';
  }

  /* ---------- 2. Ver / ocultar la ficha del equipo ---------- */
  document.querySelectorAll('.tag-actions [aria-controls]').forEach(function (boton) {
    var texto = boton.querySelector('span');
    boton.addEventListener('click', function () {
      var ficha = document.getElementById(boton.getAttribute('aria-controls'));
      var abierta = boton.getAttribute('aria-expanded') === 'true';
      boton.setAttribute('aria-expanded', String(!abierta));
      texto.textContent = abierta ? 'Ver ficha' : 'Ocultar ficha';
      ficha.hidden = abierta;
    });
  });

  /* ---------- 2b. "Ver código": el QR ampliado en una ventana (celulares) ---------- */
  var dialogo = document.getElementById('qr-dialog');
  if (dialogo && typeof dialogo.showModal === 'function') {
    var dlgCodigo = document.getElementById('qr-dialog-codigo');
    var dlgPatr = document.getElementById('qr-dialog-patr');
    var dlgQr = document.getElementById('qr-dialog-qr');
    var dlgNombre = document.getElementById('qr-dialog-nombre');
    var origen = null; // botón que abrió la ventana: recibe el foco al cerrarla

    document.querySelectorAll('[data-ver-qr]').forEach(function (boton) {
      boton.addEventListener('click', function () {
        origen = boton;
        var tag = boton.closest('.tag');
        var patr = tag.querySelector('.tag-patr');
        var qr = tag.querySelector('.tag-qr svg');
        dlgCodigo.textContent = tag.dataset.codigo;
        dlgPatr.textContent = patr ? patr.textContent : '';
        dlgPatr.hidden = !patr;
        dlgNombre.textContent = tag.querySelector('.tag-name').textContent;
        dlgQr.innerHTML = '';
        if (qr) dlgQr.appendChild(qr.cloneNode(true));
        dlgQr.setAttribute('role', 'img');
        dlgQr.setAttribute('aria-label', 'Código QR del equipo ' + tag.dataset.codigo);
        dialogo.showModal();
      });
    });

    dialogo.querySelector('[data-cerrar-qr]').addEventListener('click', function () {
      dialogo.close();
    });
    // Tocar fuera de la ventana (sobre el fondo oscuro) la cierra
    dialogo.addEventListener('click', function (e) {
      if (e.target === dialogo) dialogo.close();
    });
    // Cerrar con el botón, el fondo o Escape devuelve el foco a su etiqueta
    dialogo.addEventListener('close', function () {
      if (origen) origen.focus({ preventScroll: true });
    });
  }

  /* ---------- 3. Búsqueda y filtros de "Mis equipos" ---------- */
  var tags = Array.prototype.slice.call(document.querySelectorAll('#lista-equipos .tag'));
  var chips = document.querySelectorAll('.chip');
  var conteo = document.getElementById('conteo');
  var vacio = document.getElementById('sin-resultados');
  var tituloVacio = document.getElementById('sin-resultados-titulo');
  var inputBuscar = document.getElementById('buscar');
  var formBuscar = document.getElementById('form-buscar');

  function normalizar(texto) {
    return (texto || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
  }

  function aplicar(filtro, texto) {
    if (!tags.length) return;
    var q = normalizar(texto);
    var visibles = 0;

    tags.forEach(function (tag) {
      var ok = filtro !== 'atencion' || tag.dataset.estado !== 'operativo';
      if (q) ok = ok && normalizar(tag.dataset.buscar).indexOf(q) !== -1;
      tag.hidden = !ok;
      if (ok) visibles++;
    });

    var palabra = tags.length === 1 ? 'equipo asignado' : 'equipos asignados';
    conteo.textContent = 'Mostrando ' + visibles + ' de ' + tags.length + ' ' + palabra + ' a ti.';
    vacio.hidden = visibles > 0;
    tituloVacio.textContent = q
      ? 'Ningún equipo a tu cargo coincide con “' + texto.trim() + '”.'
      : 'Ninguno de tus equipos requiere atención.';
  }

  function marcarChip(filtro) {
    chips.forEach(function (c) {
      c.setAttribute('aria-pressed', String(c.dataset.filtro === filtro));
    });
  }

  function mostrarTodos() {
    inputBuscar.value = '';
    marcarChip('todos');
    aplicar('todos', '');
  }

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      inputBuscar.value = '';
      marcarChip(chip.dataset.filtro);
      aplicar(chip.dataset.filtro, '');
    });
  });

  formBuscar.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!tags.length) return;
    marcarChip('todos');
    aplicar('todos', inputBuscar.value);
    document.getElementById('mis-equipos').scrollIntoView({ behavior: prefiereMenosMovimiento() ? 'auto' : 'smooth' });
  });

  var limpiar = document.getElementById('limpiar');
  if (limpiar) {
    limpiar.addEventListener('click', function () {
      mostrarTodos();
      inputBuscar.focus();
    });
  }

  /* ---------- 5. Chip de código (EQ-…) → su etiqueta ----------
     Solo el clic lleva al equipo; al llegar, la etiqueta se eleva unos segundos. */
  var temporizador = null;
  document.querySelectorAll('.tag-ref').forEach(function (ref) {
    ref.addEventListener('click', function (e) {
      var tag = document.getElementById(ref.getAttribute('href').slice(1));
      if (!tag) return;
      e.preventDefault();
      mostrarTodos();

      document.querySelectorAll('.tag.is-linked').forEach(function (t) {
        t.classList.remove('is-linked', 'is-flash');
      });
      clearTimeout(temporizador);

      var reducido = prefiereMenosMovimiento();
      tag.scrollIntoView({ behavior: reducido ? 'auto' : 'smooth', block: 'center' });
      tag.focus({ preventScroll: true });

      // Con movimiento reducido el CSS anula la elevación; el resaltado dorado se mantiene
      tag.classList.add('is-linked');
      void tag.offsetWidth; // reinicia la animación
      tag.classList.add('is-flash');
      temporizador = setTimeout(function () {
        tag.classList.remove('is-linked', 'is-flash');
      }, 2600);
    });
  });

  /* ---------- 6. "Reportar falla" desde una etiqueta ----------
     La validación y las fotos del formulario están en reporte.js. */
  var form = document.getElementById('form-reporte');
  if (!form) return;

  var selectEquipo = document.getElementById('r-equipo');
  var problema = document.getElementById('r-problema');

  document.querySelectorAll('[data-reportar]').forEach(function (enlace) {
    enlace.addEventListener('click', function (e) {
      e.preventDefault();
      selectEquipo.value = enlace.dataset.reportar;
      marcarError(selectEquipo, 'e-equipo', '');
      document.getElementById('reportar').scrollIntoView({
        behavior: prefiereMenosMovimiento() ? 'auto' : 'smooth', block: 'start'
      });
      problema.focus({ preventScroll: true });
    });
  });
});
