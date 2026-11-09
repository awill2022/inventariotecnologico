<?php $u = Auth::user(); ?>
<header class="site-header">
    <div class="wrap site-header__inner">
        <a class="brand" href="<?= View::url('/') ?>">Inventario Tecnológico · ISTSGA</a>
        <?php if ($u): ?>
            <div class="user-chip">
                <span><?= View::e($u['nombre']) ?></span>
                <form method="POST" action="<?= View::url('/logout') ?>">
                    <input type="hidden" name="_csrf" value="<?= View::e(Session::csrfToken()) ?>">
                    <button class="btn btn--ghost" type="submit">Cerrar sesión</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</header>
