/**
 * lector-qr.js — Botón «QR» de la página del docente: lee el código de la etiqueta con la cámara
 * y abre la página del equipo (/q/{token}).
 *
 * - Cámara en vivo: getUserMedia (el navegador solo la permite en HTTPS o en localhost).
 *   Lee cada cuadro con BarcodeDetector si existe (Chrome en Android) o con jsQR.
 * - Sin cámara en vivo (p. ej. el sistema abierto por http desde el celular): pide una foto del código
 *   y la lee con jsQR.
 * Solo acepta códigos de etiquetas del inventario: arma la dirección con la base de este sitio,
 * nunca abre un enlace ajeno que venga en el QR.
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var boton = document.getElementById('btn-qr');
  var dialogo = document.getElementById('lector-qr');
  if (!boton || !dialogo) return;

  var inputBuscar = document.getElementById('buscar');
  if (typeof HTMLDialogElement === 'undefined' || typeof dialogo.showModal !== 'function') {
    // Navegador muy antiguo: se queda la búsqueda por código
    boton.addEventListener('click', function () { if (inputBuscar) inputBuscar.focus(); });
    return;
  }

  var base = dialogo.dataset.baseQr;
  var cajaVideo = dialogo.querySelector('[data-lector-camara]');
  var video = cajaVideo.querySelector('video');
  var estado = document.getElementById('lector-estado');
  var botonFoto = dialogo.querySelector('[data-lector-foto]');
  var archivo = dialogo.querySelector('[data-lector-archivo]');
  var lienzo = document.createElement('canvas');
  var ctx = lienzo.getContext('2d', { willReadFrequently: true });

  var flujo = null;
  var leyendo = false;
  var esperaHasta = 0;  // pausa tras leer un QR que no es de una etiqueta
  var detector = null;
  if ('BarcodeDetector' in window) {
    try { detector = new window.BarcodeDetector({ formats: ['qr_code'] }); } catch (e) { detector = null; }
  }
  var camaraEnVivo = window.isSecureContext && navigator.mediaDevices &&
                     typeof navigator.mediaDevices.getUserMedia === 'function';

  boton.addEventListener('click', function () {
    dialogo.showModal();
    if (camaraEnVivo) {
      iniciarCamara();
    } else {
      modoFoto('Desde esta dirección el navegador no deja abrir la cámara en vivo. ' +
               'Toma una foto del código QR y la leemos.');
    }
  });

  dialogo.querySelector('[data-cerrar-lector]').addEventListener('click', function () { dialogo.close(); });
  dialogo.addEventListener('click', function (e) { if (e.target === dialogo) dialogo.close(); });
  dialogo.addEventListener('close', detener);

  function decir(texto) {
    estado.textContent = texto;
  }

  var demora = null;

  function iniciarCamara() {
    decir('Abriendo la cámara…');
    cajaVideo.hidden = true;
    botonFoto.hidden = true;
    // Si el permiso queda sin responder, ofrece la foto como alternativa
    clearTimeout(demora);
    demora = setTimeout(function () {
      if (dialogo.open && !flujo) {
        modoFoto('Si el navegador te pide permiso, acéptalo para usar la cámara. También puedes tomar una foto del código QR.');
      }
    }, 5000);
    navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
      .then(function (s) {
        if (!dialogo.open) { apagar(s); return; }
        flujo = s;
        video.srcObject = s;
        cajaVideo.hidden = false;
        botonFoto.hidden = true;
        return video.play().then(function () {
          leyendo = true;
          decir('Apunta la cámara al código QR de la etiqueta.');
          buscar();
        });
      })
      .catch(function (err) {
        apagar(flujo);
        flujo = null;
        modoFoto(err && err.name === 'NotAllowedError'
          ? 'No diste permiso para usar la cámara. Puedes tomar una foto del código QR.'
          : 'No pudimos abrir la cámara. Puedes tomar una foto del código QR.');
      });
  }

  function modoFoto(mensaje) {
    cajaVideo.hidden = true;
    botonFoto.hidden = false;
    decir(mensaje);
  }

  function buscar() {
    if (!leyendo) return;
    if (video.readyState < 2 || Date.now() < esperaHasta) { siguiente(); return; }

    if (detector) {
      detector.detect(video).then(function (codigos) {
        if (codigos.length) procesar(codigos[0].rawValue);
        siguiente();
      }).catch(function () {
        detector = null; // si falla una vez, sigue con jsQR
        siguiente();
      });
      return;
    }
    var texto = leer(video, video.videoWidth, video.videoHeight, 'dontInvert');
    if (texto) procesar(texto);
    siguiente();
  }

  function siguiente() {
    if (leyendo) setTimeout(function () { requestAnimationFrame(buscar); }, 120);
  }

  /** Lee un QR de un video o una imagen con jsQR (reducido para que sea rápido). */
  function leer(fuente, ancho, alto, inversion) {
    if (typeof window.jsQR !== 'function' || !ancho || !alto) return null;
    var escala = Math.min(1, 900 / Math.max(ancho, alto));
    lienzo.width = Math.round(ancho * escala);
    lienzo.height = Math.round(alto * escala);
    ctx.drawImage(fuente, 0, 0, lienzo.width, lienzo.height);
    var datos = ctx.getImageData(0, 0, lienzo.width, lienzo.height);
    var codigo = window.jsQR(datos.data, lienzo.width, lienzo.height, { inversionAttempts: inversion });
    return codigo ? codigo.data : null;
  }

  function tokenDe(texto) {
    var m = /\/q\/([a-f0-9]{32})\/?(?:[?#].*)?$/.exec(String(texto || '').trim());
    return m ? m[1] : null;
  }

  function procesar(texto) {
    var token = tokenDe(texto);
    if (token) {
      leyendo = false;
      decir('Etiqueta reconocida. Abriendo el equipo…');
      detener();
      window.location.href = base + token;
      return true;
    }
    esperaHasta = Date.now() + 2000;
    decir('Ese código no es de una etiqueta del inventario. Prueba con el QR de la etiqueta patrimonial.');
    return false;
  }

  archivo.addEventListener('change', function () {
    var foto = archivo.files && archivo.files[0];
    archivo.value = '';
    if (!foto) return;
    decir('Leyendo la foto…');
    var url = URL.createObjectURL(foto);
    var img = new Image();
    img.onload = function () {
      URL.revokeObjectURL(url);
      var texto = leer(img, img.naturalWidth, img.naturalHeight, 'attemptBoth');
      if (!texto) {
        decir('No encontramos un código QR en la foto. Acércate más: el código debe verse completo y nítido.');
      } else {
        procesar(texto);
      }
    };
    img.onerror = function () {
      URL.revokeObjectURL(url);
      decir('No pudimos abrir esa foto. Prueba de nuevo.');
    };
    img.src = url;
  });

  function apagar(s) {
    if (s) s.getTracks().forEach(function (pista) { pista.stop(); });
  }

  function detener() {
    clearTimeout(demora);
    leyendo = false;
    apagar(flujo);
    flujo = null;
    video.srcObject = null;
  }
});
