<?php
/**
 * Layout de la aplicación con sesión: encabezado navy, contenido y pie.
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
    <?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
    <title><?= View::e($titulo ?? '') ?> | <?= View::e(App::config('app.name')) ?></title>
    <?php require APP_PATH . '/views/partials/head.php'; ?>
    <link rel="stylesheet" href="<?= View::asset('css/layout.css') ?>">
    <?php foreach ($estilos as $css): ?>
        <link rel="stylesheet" href="<?= View::asset('css/' . $css) ?>">
    <?php endforeach; ?>
</head>
<body>
    <?php require APP_PATH . '/views/partials/iconos.php'; ?>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <?php require APP_PATH . '/views/partials/header.php'; ?>

    <main id="contenido">
        <?= $contenido ?>
    </main>

    <?php require APP_PATH . '/views/partials/footer.php'; ?>

    <script src="<?= View::asset('js/app.js') ?>"></script>
    <?php foreach ($scripts as $js): ?>
        <script src="<?= View::asset('js/' . $js) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
