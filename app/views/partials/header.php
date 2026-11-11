<?php
/**
 * Encabezado institucional. Si $nav es true muestra la navegación de la página docente.
 * @var ?bool $nav
 */
$u    = Auth::user();
$nav ??= false;
$roles = [
    'consulta'      => 'Docente · Consulta',
    'administrador' => 'Administrador',
    'inventario'    => 'Unidad de Inventario',
    'tecnico'       => 'Técnico',
];
?>
<header class="site-header">
    <div class="wrap header-row">
        <a class="brand" href="<?= View::url('/') ?>" aria-label="Inventario Tecnológico, inicio">
            <img class="brand-crest" src="<?= View::e(App::config('app.escudo')) ?>" alt="" width="56" height="56">
            <span class="brand-text">
                <span class="brand-name">Inventario Tecnológico</span>
                <span class="brand-inst">Instituto Superior Tecnológico San Gabriel</span>
            </span>
        </a>

        <?php if ($u): ?>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu-principal">
                <svg class="icon icon-abrir" aria-hidden="true"><use href="#i-menu"/></svg>
                <svg class="icon icon-cerrar" aria-hidden="true"><use href="#i-close"/></svg>
                <span class="menu-toggle-text">Menú</span>
            </button>

            <nav class="main-nav" id="menu-principal" aria-label="Principal">
                <?php if ($nav): ?>
                    <ul>
                        <li><a href="#contenido" aria-current="page">Inicio</a></li>
                        <li><a href="#mis-equipos">Mis equipos</a></li>
                        <li><a href="#reportar">Reportar falla</a></li>
                        <li><a href="#historial">Historial</a></li>
                    </ul>
                <?php endif; ?>
                <div class="user-chip">
                    <span class="avatar" aria-hidden="true"><?= View::e($u['iniciales']) ?></span>
                    <span class="user-meta">
                        <span class="user-name"><?= View::e($u['nombre']) ?></span>
                        <span class="user-role"><?= View::e($roles[$u['rol']] ?? $u['rol']) ?></span>
                    </span>
                    <a class="logout" href="<?= View::url('/perfil') ?>" aria-label="Mi perfil" title="Mi perfil"
                       <?= App::path() === '/perfil' ? 'aria-current="page"' : '' ?>>
                        <svg class="icon" aria-hidden="true"><use href="#i-user"/></svg>
                    </a>
                    <form class="logout-form" method="POST" action="<?= View::url('/logout') ?>">
                        <input type="hidden" name="_csrf" value="<?= View::e(Session::csrfToken()) ?>">
                        <button class="logout" type="submit" aria-label="Cerrar sesión" title="Cerrar sesión">
                            <svg class="icon" aria-hidden="true"><use href="#i-logout"/></svg>
                        </button>
                    </form>
                </div>
            </nav>
        <?php endif; ?>
    </div>
</header>
<?php if ($u): ?>
    <div class="menu-scrim" id="menu-scrim" aria-hidden="true"></div>
<?php endif; ?>
