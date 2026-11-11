<?php
/**
 * Layout para páginas sin sesión: login y errores.
 * @var string $contenido  HTML de la vista
 * @var string $titulo
 * @var array  $scripts    JS propios de la página (en public/assets/js)
 */
$scripts ??= [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($titulo ?? '') ?> | <?= View::e(App::config('app.name')) ?></title>
    <?php require APP_PATH . '/views/partials/head.php'; ?>
    <link rel="stylesheet" href="<?= View::asset('css/auth.css') ?>">
</head>
<body class="page-auth">
    <?php require APP_PATH . '/views/partials/iconos.php'; ?>
    <?= $contenido ?>
    <script src="<?= View::asset('js/app.js') ?>"></script>
    <?php foreach ($scripts as $js): ?>
        <script src="<?= View::asset('js/' . $js) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
