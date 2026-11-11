<?php
/**
 * Layout del panel (administrador, inventario, técnico): barra lateral navy
 * con los módulos y contenido a la derecha. En celular la barra es un menú
 * que se abre desde la barra superior.
 * @var string $contenido  HTML de la vista
 * @var string $titulo
 * @var array  $estilos    CSS propios de la página (en public/assets/css)
 * @var array  $scripts    JS propios de la página (en public/assets/js)
 */
$estilos ??= [];
$scripts ??= [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($titulo ?? '') ?> | <?= View::e(App::config('app.name')) ?></title>
    <?php require APP_PATH . '/views/partials/head.php'; ?>
    <link rel="stylesheet" href="<?= View::asset('css/panel.css') ?>">
    <?php foreach ($estilos as $css): ?>
        <link rel="stylesheet" href="<?= View::asset('css/' . $css) ?>">
    <?php endforeach; ?>
</head>
<body class="shell">
    <?php require APP_PATH . '/views/partials/iconos.php'; ?>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <header class="topbar">
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu-principal">
            <svg class="icon icon-abrir" aria-hidden="true"><use href="#i-menu"/></svg>
            <svg class="icon icon-cerrar" aria-hidden="true"><use href="#i-close"/></svg>
            <span class="sr-only">Menú</span>
        </button>
        <a class="topbar-brand" href="<?= View::url('/panel') ?>">
            <img src="<?= View::e(App::config('app.escudo')) ?>" alt="" width="36" height="36">
            <span>Inventario Tecnológico</span>
        </a>
    </header>

    <?php require APP_PATH . '/views/partials/sidebar.php'; ?>
    <div class="menu-scrim" id="menu-scrim" aria-hidden="true"></div>

    <div class="shell-main">
        <main id="contenido" class="page">
            <?= $contenido ?>
        </main>
        <footer class="page-foot">
            <p>© <?= date('Y') ?> ISTSGA · Inventario Tecnológico</p>
        </footer>
    </div>

    <script src="<?= View::asset('js/app.js') ?>"></script>
    <?php foreach ($scripts as $js): ?>
        <script src="<?= View::asset('js/' . $js) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
