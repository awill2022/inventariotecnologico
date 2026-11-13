<?php
/**
 * Layout para páginas que se imprimen (etiquetas, actas): sin menú, con una barra de
 * herramientas que no sale en papel.
 * @var string $contenido
 * @var string $titulo
 * @var string $volver   ruta del botón "Volver"
 * @var array  $estilos  CSS extra de la página (además de etiquetas.css, que trae la hoja y la barra)
 */
$estilos ??= [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($titulo ?? '') ?> | <?= View::e(App::config('app.name')) ?></title>
    <?php require APP_PATH . '/views/partials/head.php'; ?>
    <link rel="stylesheet" href="<?= View::asset('css/etiquetas.css') ?>">
    <?php foreach ($estilos as $css): ?>
        <link rel="stylesheet" href="<?= View::asset('css/' . $css) ?>">
    <?php endforeach; ?>
</head>
<body class="hoja">
    <?php require APP_PATH . '/views/partials/iconos.php'; ?>
    <header class="hoja-barra">
        <a class="btn btn-outline" href="<?= View::url($volver ?? '/equipos') ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
            Volver
        </a>
        <button class="btn btn-primary" type="button" data-imprimir>
            <svg class="icon" aria-hidden="true"><use href="#i-printer"/></svg>
            Imprimir
        </button>
    </header>

    <main id="contenido">
        <?= $contenido ?>
    </main>

    <script src="<?= View::asset('js/vendor/qrcode.min.js') ?>"></script>
    <script src="<?= View::asset('js/etiquetas.js') ?>"></script>
</body>
</html>
