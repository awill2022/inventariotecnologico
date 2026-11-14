/**
 * tecnico.js — QR en la ficha, desplegable de "Atender equipo" y "Programar preventivo",
 * reporte con PIN, filas dinámicas de componentes/repuestos y escáner QR.
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  /* 1. Dibujar el QR en la placa de la ficha técnica y de la etiqueta imprimible */
  document.querySelectorAll('.tag-qr[data-qr]').forEach(function (caja) {
    if (typeof qrcode !== 'function') return;
    var qr = qrcode(0, 'M');
    qr.addData(caja.dataset.qr);
    qr.make();
    var n = qr.getModuleCount();
    var d = '';
    for (var y = 0; y < n; y++) {
      for (var x = 0; x < n; x++) {
        if (qr.isDark(y, x)) d += 'M' + x + ' ' + y + 'h1v1h-1z';
      }
    }
    caja.innerHTML = '<svg viewBox="0 0 ' + n + ' ' + n + '" shape-rendering="crispEdges" aria-hidden="true">' +
                     '<path fill="#121e30" d="' + d + '"/></svg>';
  });

  /* 2. Mostrar/ocultar el formulario técnico ("Atender equipo") y "Programar preventivo" */
  var btnAtender = document.getElementById('btn-atender-equipo');
  var cajaForm   = document.getElementById('caja-form-mantenimiento');
  var btnOcultar = document.getElementById('btn-cerrar-mantenimiento');
  var btnPrev    = document.getElementById('btn-toggle-preventivo');
  var cajaPrev   = document.getElementById('caja-form-preventivo');

  function cambiarEstadoForm(abrir) {
    if (!cajaForm) return;
    cajaForm.hidden = !abrir;
    if (btnAtender) {
      btnAtender.setAttribute('aria-expanded', String(abrir));
      var span = btnAtender.querySelector('span');
      if (span) span.textContent = abrir ? 'Ocultar formulario técnico' : 'Atender equipo / Llenar informe';
    }
    if (abrir) {
      cajaForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
      var diag = document.getElementById('campo-diagnostico');
      if (diag) diag.focus({ preventScroll: true });
    }
  }

  if (btnAtender && cajaForm) {
    btnAtender.addEventListener('click', function () {
      cambiarEstadoForm(cajaForm.hidden);
    });
  }
  document.querySelectorAll('[data-abrir-mantenimiento]').forEach(function (b) {
    b.addEventListener('click', function () { cambiarEstadoForm(true); });
  });
  if (btnOcultar) {
    btnOcultar.addEventListener('click', function () { cambiarEstadoForm(false); });
  }
  if (btnPrev && cajaPrev) {
    btnPrev.addEventListener('click', function () {
      cajaPrev.hidden = !cajaPrev.hidden;
      if (!cajaPrev.hidden) cajaPrev.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  /* 3. Mostrar/ocultar el formulario de reporte con PIN en /q/{token} */
  var btnMostrarReporte = document.getElementById('btn-mostrar-reporte');
  var panelReporte      = document.getElementById('form-qr-reportar');
  if (btnMostrarReporte && panelReporte) {
    btnMostrarReporte.addEventListener('click', function () {
      var abierto = btnMostrarReporte.getAttribute('aria-expanded') === 'true';
      btnMostrarReporte.setAttribute('aria-expanded', String(!abierto));
      panelReporte.hidden = abierto;
      if (!abierto) {
        panelReporte.scrollIntoView({ behavior: 'smooth', block: 'start' });
        var inputId = document.getElementById('qr-identificador');
        if (inputId) inputId.focus({ preventScroll: true });
      }
    });
  }

  /* 4. Clonar filas para agregar más componentes o repuestos en el mantenimiento */
  function configurarClonador(idBoton, idContenedor) {
    var boton = document.getElementById(idBoton);
    var cont  = document.getElementById(idContenedor);
    if (!boton || !cont || !cont.firstElementChild) return;

    boton.addEventListener('click', function () {
      var clon = cont.firstElementChild.cloneNode(true);
      clon.querySelectorAll('input, select').forEach(function (inp) {
        if (inp.name === 'rep_cantidad[]') inp.value = '1';
        else if (inp.name === 'rep_costo[]') inp.value = '0.00';
        else inp.value = '';
      });
      cont.appendChild(clon);
    });
  }
  configurarClonador('btn-agregar-comp', 'lista-nuevos-componentes');
  configurarClonador('btn-agregar-rep', 'lista-repuestos');

  /* 5. Escáner QR con cámara desde la bandeja del técnico */
  var btnAbrir = document.getElementById('btn-abrir-escaner');
  var dialogo  = document.getElementById('dialog-escaner');
  if (!btnAbrir || !dialogo) return;

  var video     = document.getElementById('qr-video');
  var errorCaja = document.getElementById('qr-error');
  var btnCerrar = document.getElementById('btn-cerrar-escaner');
  var stream    = null;
  var activo    = false;

  function detenerCamara() {
    activo = false;
    if (stream) {
      stream.getTracks().forEach(function (t) { t.stop(); });
      stream = null;
    }
  }

  function procesarTextoQr(texto) {
    var match = texto.match(/\/q\/([a-f0-9]{16,64})/i);
    if (match) {
      detenerCamara();
      dialogo.close();
      window.location.href = texto;
      return true;
    }
    var inputQ = document.getElementById('q');
    if (inputQ) {
      detenerCamara();
      dialogo.close();
      inputQ.value = texto.trim();
      inputQ.form.submit();
      return true;
    }
    return false;
  }

  btnAbrir.addEventListener('click', function () {
    errorCaja.hidden = true;
    dialogo.showModal();

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      errorCaja.textContent = 'Usa la cámara de tu teléfono o escribe el código en el buscador.';
      errorCaja.hidden = false;
      return;
    }

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(function (s) {
        stream = s;
        video.srcObject = s;
        video.play();
        activo = true;

        if ('BarcodeDetector' in window) {
          var detector = new BarcodeDetector({ formats: ['qr_code'] });
          (function escanear() {
            if (!activo) return;
            detector.detect(video).then(function (codigos) {
              if (codigos.length > 0 && procesarTextoQr(codigos[0].rawValue)) return;
              requestAnimationFrame(escanear);
            }).catch(function () {
              requestAnimationFrame(escanear);
            });
          })();
        } else {
          errorCaja.textContent = 'Escanea el QR con la app de cámara de tu celular.';
          errorCaja.hidden = false;
        }
      })
      .catch(function () {
        errorCaja.textContent = 'No se pudo acceder a la cámara. Revisa los permisos del navegador.';
        errorCaja.hidden = false;
      });
  });

  btnCerrar.addEventListener('click', function () { dialogo.close(); });
  dialogo.addEventListener('close', detenerCamara);
});