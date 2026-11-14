/**
 * reporte.js — Formulario «Reportar una falla» (página del docente y página del QR).
 *
 * 1. Validación en el navegador (el servidor valida todo de nuevo).
 * 2. Fotos de la falla: «Tomar foto» abre la cámara del celular y «Elegir de la galería» las fotos guardadas.
 *    Cada foto se reduce a 1600 px y se vuelve a guardar en JPEG antes de enviarla: pesa poco aunque la conexión
 *    sea lenta y pierde los datos ocultos de la cámara (como la ubicación GPS).
 *    Las fotos listas se colocan en el <input name="fotos[]"> del formulario, que se envía de forma normal.
 * Sin JavaScript (o en navegadores muy antiguos) queda el campo de archivos nativo.
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var form = document.getElementById('form-reporte');
  if (!form) return;

  var selectEquipo = document.getElementById('r-equipo');
  var problema = document.getElementById('r-problema');
  var botonEnviar = document.getElementById('btn-reportar');
  var esSelect = selectEquipo && selectEquipo.tagName === 'SELECT';
  var MIN_PROBLEMA = 15;

  /* ---------- 2. Fotos ---------- */
  var caja = form.querySelector('[data-fotos]');
  var fotos = [];       // { file, url, item }
  var procesando = 0;   // fotos que todavía se están reduciendo
  var LADO_MAX = 1600;
  var CALIDAD = 0.82;
  var TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

  var nativo = caja && caja.querySelector('.fotos-nativo');
  var botones = caja && caja.querySelector('[data-fotos-botones]');
  var camara = caja && caja.querySelector('[data-fotos-camara]');
  var galeria = caja && caja.querySelector('[data-fotos-galeria]');
  var lista = caja && caja.querySelector('[data-fotos-lista]');
  var conteo = caja && caja.querySelector('[data-fotos-conteo]');
  var MAX = caja ? parseInt(caja.dataset.max, 10) || 3 : 0;
  var MAX_BYTES = caja ? parseInt(caja.dataset.maxBytes, 10) || 5242880 : 0;

  var mejorado = Boolean(caja) && puedeArmarArchivos();

  if (mejorado) {
    nativo.hidden = true;
    botones.hidden = false;

    // En computadoras «Tomar foto» abriría el mismo selector de archivos: solo se muestra en pantallas táctiles
    var tactil = window.matchMedia('(hover: none) and (pointer: coarse)').matches;
    if (!tactil) {
      camara.closest('label').hidden = true;
      textoBoton(galeria.closest('label'), 'Elegir fotos');
    }

    [camara, galeria].forEach(function (input) {
      input.addEventListener('change', function () {
        agregar(Array.prototype.slice.call(input.files || []));
        input.value = ''; // permite volver a elegir la misma foto
      });
    });
  }

  function puedeArmarArchivos() {
    try {
      new DataTransfer();
      return typeof File === 'function' && typeof URL.createObjectURL === 'function';
    } catch (e) {
      return false;
    }
  }

  function textoBoton(label, texto) {
    for (var i = 0; i < label.childNodes.length; i++) {
      var nodo = label.childNodes[i];
      if (nodo.nodeType === 3 && nodo.textContent.trim()) {
        nodo.textContent = ' ' + texto + ' ';
        return;
      }
    }
  }

  function errorFotos(mensaje) {
    marcarError(galeria, 'e-fotos', mensaje);
  }

  function agregar(archivos) {
    errorFotos('');
    var libres = MAX - fotos.length - procesando;
    if (archivos.length > libres) {
      errorFotos('Puedes adjuntar hasta ' + MAX + (MAX === 1 ? ' foto.' : ' fotos.') +
                 (libres > 0 ? ' Agregamos solo ' + libres + '.' : ''));
      archivos = archivos.slice(0, Math.max(0, libres));
    }

    archivos.forEach(function (archivo) {
      if (archivo.type && archivo.type.indexOf('image/') !== 0) {
        errorFotos('«' + archivo.name + '» no es una imagen.');
        return;
      }
      procesando++;
      var item = document.createElement('li');
      item.className = 'foto-item is-procesando';
      lista.appendChild(item);
      actualizar();

      reducir(archivo).then(function (final) {
        procesando--;
        if (!final) {
          item.remove();
          errorFotos('No pudimos leer «' + archivo.name + '». Prueba con una foto en JPG o PNG.');
        } else if (final.size > MAX_BYTES) {
          item.remove();
          errorFotos('«' + archivo.name + '» pesa demasiado. Prueba con otra foto.');
        } else {
          var foto = { file: final, url: URL.createObjectURL(final), item: item };
          fotos.push(foto);
          pintar(foto);
        }
        sincronizar();
      });
    });
  }

  /** Reduce la foto a LADO_MAX px y la guarda en JPEG. Si el navegador no puede leerla, la envía tal cual. */
  function reducir(archivo) {
    return cargarImagen(archivo).then(function (img) {
      var ancho = img.naturalWidth;
      var alto = img.naturalHeight;
      var escala = Math.min(1, LADO_MAX / Math.max(ancho, alto));
      var lienzo = document.createElement('canvas');
      lienzo.width = Math.max(1, Math.round(ancho * escala));
      lienzo.height = Math.max(1, Math.round(alto * escala));
      var ctx = lienzo.getContext('2d');
      ctx.fillStyle = '#fff'; // fondo blanco para los PNG con transparencia
      ctx.fillRect(0, 0, lienzo.width, lienzo.height);
      ctx.drawImage(img, 0, 0, lienzo.width, lienzo.height);
      return new Promise(function (listo) {
        lienzo.toBlob(listo, 'image/jpeg', CALIDAD);
      });
    }).then(function (blob) {
      if (!blob) throw new Error('sin imagen');
      var nombre = (archivo.name || 'foto').replace(/\.[^.]*$/, '') + '.jpg';
      return new File([blob], nombre, { type: 'image/jpeg', lastModified: Date.now() });
    }).catch(function () {
      return TIPOS.indexOf(archivo.type) !== -1 ? archivo : null;
    });
  }

  function cargarImagen(archivo) {
    return new Promise(function (listo, falla) {
      var url = URL.createObjectURL(archivo);
      var img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); listo(img); };
      img.onerror = function () { URL.revokeObjectURL(url); falla(new Error('no es imagen')); };
      img.src = url;
    });
  }

  function pintar(foto) {
    var item = foto.item;
    var numero = fotos.indexOf(foto) + 1;
    item.className = 'foto-item';
    item.innerHTML = '';

    var img = document.createElement('img');
    img.src = foto.url;
    img.alt = 'Foto ' + numero + ' de la falla';
    item.appendChild(img);

    var quitar = document.createElement('button');
    quitar.type = 'button';
    quitar.className = 'foto-quitar';
    quitar.setAttribute('aria-label', 'Quitar la foto ' + numero);
    quitar.innerHTML = '<svg class="icon" aria-hidden="true"><use href="#i-close"/></svg>';
    quitar.addEventListener('click', function () {
      quitarFoto(foto);
      (camara.closest('label').hidden ? galeria : camara).focus();
    });
    item.appendChild(quitar);
  }

  function quitarFoto(foto) {
    var i = fotos.indexOf(foto);
    if (i === -1) return;
    fotos.splice(i, 1);
    URL.revokeObjectURL(foto.url);
    foto.item.remove();
    errorFotos('');
    sincronizar();
  }

  /** Pone las fotos listas en el campo que se envía y actualiza los textos. */
  function sincronizar() {
    // Mismo orden que las miniaturas (cada foto termina de reducirse a su ritmo)
    fotos.sort(function (a, b) {
      return a.item.compareDocumentPosition(b.item) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
    });
    var dt = new DataTransfer();
    fotos.forEach(function (foto, i) {
      dt.items.add(foto.file);
      var img = foto.item.querySelector('img');
      var boton = foto.item.querySelector('.foto-quitar');
      if (img) img.alt = 'Foto ' + (i + 1) + ' de la falla';
      if (boton) boton.setAttribute('aria-label', 'Quitar la foto ' + (i + 1));
    });
    nativo.files = dt.files;
    actualizar();
  }

  function actualizar() {
    var usadas = fotos.length + procesando;
    var lleno = usadas >= MAX;
    [camara, galeria].forEach(function (input) {
      input.disabled = lleno;
      input.closest('label').setAttribute('aria-disabled', String(lleno));
    });
    conteo.hidden = fotos.length === 0 && procesando === 0;
    conteo.textContent = procesando
      ? 'Preparando ' + (procesando === 1 ? 'la foto' : procesando + ' fotos') + '…'
      : fotos.length + ' de ' + MAX + (MAX === 1 ? ' foto adjunta.' : ' fotos adjuntas.') +
        (lleno ? ' Quita una si quieres cambiarla.' : '');
  }

  /* ---------- 1. Validación ---------- */
  form.addEventListener('submit', function (e) {
    var errEquipo = esSelect && selectEquipo.value === '' ? 'Elige el equipo que presenta la falla.' : '';
    var errProblema = problema.value.trim().length < MIN_PROBLEMA
      ? 'Cuéntanos qué ocurre (al menos ' + MIN_PROBLEMA + ' caracteres).' : '';

    if (esSelect) marcarError(selectEquipo, 'e-equipo', errEquipo);
    marcarError(problema, 'e-problema', errProblema);

    if (errEquipo || errProblema) {
      e.preventDefault();
      (errEquipo ? selectEquipo : problema).focus();
      return;
    }
    if (mejorado && procesando > 0) {
      e.preventDefault();
      errorFotos('Espera un momento: todavía estamos preparando las fotos.');
      return;
    }
    botonEnviar.disabled = true;
    botonEnviar.textContent = fotos.length ? 'Enviando fotos…' : 'Enviando…';
  });

  form.addEventListener('reset', function () {
    if (esSelect) marcarError(selectEquipo, 'e-equipo', '');
    marcarError(problema, 'e-problema', '');
    if (mejorado) {
      fotos.slice().forEach(quitarFoto);
      errorFotos('');
    }
  });

  if (esSelect) {
    selectEquipo.addEventListener('change', function () {
      if (selectEquipo.value) marcarError(selectEquipo, 'e-equipo', '');
    });
  }
  problema.addEventListener('input', function () {
    if (problema.value.trim().length >= MIN_PROBLEMA) marcarError(problema, 'e-problema', '');
  });
});
