/**
 * etiquetas.js — Hoja de etiquetas: dibuja el QR real de cada equipo
 * (librería qrcode-generator) y abre el cuadro de impresión.
 */
(function () {
  'use strict';

  function dibujarQR(texto) {
    var qr = qrcode(0, 'M');
    qr.addData(texto);
    qr.make();
    var n = qr.getModuleCount();
    var d = '';
    for (var y = 0; y < n; y++) {
      for (var x = 0; x < n; x++) {
        if (qr.isDark(y, x)) d += 'M' + x + ' ' + y + 'h1v1h-1z';
      }
    }
    // Margen de 2 módulos para que los lectores lo reconozcan aun pegado al borde
    return '<svg viewBox="-2 -2 ' + (n + 4) + ' ' + (n + 4) + '" shape-rendering="crispEdges" aria-hidden="true">' +
           '<rect x="-2" y="-2" width="' + (n + 4) + '" height="' + (n + 4) + '" fill="#fff"/>' +
           '<path fill="#000" d="' + d + '"/></svg>';
  }

  if (typeof qrcode === 'function') {
    document.querySelectorAll('.etiqueta-qr[data-qr]').forEach(function (caja) {
      caja.innerHTML = dibujarQR(caja.dataset.qr);
    });
  }

  var boton = document.querySelector('[data-imprimir]');
  if (boton) {
    boton.addEventListener('click', function () { window.print(); });
  }
})();
