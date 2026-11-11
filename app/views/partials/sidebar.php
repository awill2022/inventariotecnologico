<?php
/**
 * Barra lateral del panel: marca, módulos según el rol y sesión del usuario.
 */
$u      = Auth::user();
$grupos = Menu::para($u['rol'] ?? null, App::path());
$roles  = [
    'administrador' => 'Administrador',
    'inventario'    => 'Unidad de Inventario',
    'tecnico'       => 'Técnico',
];
?>
<aside class="side" id="menu-principal">
    <a class="side-brand" href="<?= View::url('/panel') ?>" aria-label="Inventario Tecnológico, inicio">
        <img src="<?= View::e(App::config('app.escudo')) ?>" alt="" width="48" height="48">
        <span class="side-brand-text">
            <span class="side-brand-name">Inventario Tecnológico</span>
            <span class="side-brand-inst">ISTSGA · San Gabriel</span>
        </span>
    </a>

    <nav class="side-nav" aria-label="Módulos">
        <?php foreach ($grupos as $i => $grupo): ?>
            <?php if ($grupo['titulo']): ?>
                <h2 class="side-group" id="grupo-<?= $i ?>"><?= View::e($grupo['titulo']) ?></h2>
            <?php endif; ?>
            <ul <?= $grupo['titulo'] ? 'aria-labelledby="grupo-' . $i . '"' : '' ?>>
                <?php foreach ($grupo['items'] as $item): ?>
                    <li>
                        <a href="<?= View::url($item['ruta']) ?>" <?= $item['actual'] ? 'aria-current="page"' : '' ?>>
                            <svg class="icon" aria-hidden="true"><use href="#i-<?= View::e($item['icono']) ?>"/></svg>
                            <?= View::e($item['texto']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>

    <div class="side-user">
        <span class="avatar" aria-hidden="true"><?= View::e($u['iniciales']) ?></span>
        <span class="side-user-meta">
            <span class="side-user-name"><?= View::e($u['nombre']) ?></span>
            <span class="side-user-role"><?= View::e($roles[$u['rol']] ?? $u['rol']) ?></span>
        </span>
        <form method="POST" action="<?= View::url('/logout') ?>">
            <input type="hidden" name="_csrf" value="<?= View::e(Session::csrfToken()) ?>">
            <button class="side-logout" type="submit" aria-label="Cerrar sesión" title="Cerrar sesión">
                <svg class="icon" aria-hidden="true"><use href="#i-logout"/></svg>
            </button>
        </form>
    </div>
</aside>
