<?php
/**
 * Layout de la aplicación con sesión: encabezado navy, contenido y pie.
 * @var string $contenido  HTML de la vista
 * @var string $titulo
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($titulo ?? '') ?> · <?= View::e(App::config('app.name')) ?></title>
    <?php require APP_PATH . '/views/partials/head.php'; ?>
    <link rel="stylesheet" href="<?= View::asset('css/docente.css') ?>">
</head>
<body>
    <?php require APP_PATH . '/views/partials/header.php'; ?>
    <main id="contenido">
        <?= $contenido ?>
    </main>
    <?php require APP_PATH . '/views/partials/footer.php'; ?>
    <script src="<?= View::asset('js/app.js') ?>" defer></script>
    <script src="<?= View::asset('js/docente.js') ?>" defer></script>
</body>
</html>
