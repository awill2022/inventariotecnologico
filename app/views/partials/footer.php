<?php /** @var ?bool $nav */ ?>
<footer class="site-footer">
    <div class="wrap footer-grid">
        <div class="footer-brand">
            <img src="<?= View::e(App::config('app.escudo')) ?>" alt="Escudo del Instituto Superior Tecnológico San Gabriel" width="64" height="64">
            <p>Instituto Superior Tecnológico San Gabriel<br>Riobamba, Ecuador</p>
        </div>
        <div>
            <h2 class="footer-title">¿Necesitas ayuda?</h2>
            <ul class="footer-list">
                <li>Unidad de Inventario · Bloque A, oficina 103</li>
                <li>Soporte TI · extensión 214</li>
                <?php if (!empty($nav)): ?>
                    <li><a href="#reportar">Reportar una falla</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div>
            <h2 class="footer-title">Sistema</h2>
            <ul class="footer-list">
                <?php if (!empty($nav)): ?>
                    <li><a href="#mis-equipos">Mis equipos</a></li>
                    <li><a href="#historial">Historial</a></li>
                <?php endif; ?>
                <li><a href="https://academico.sangabrielriobamba.edu.ec/login">Sistema académico</a></li>
            </ul>
        </div>
    </div>
    <p class="footer-legal wrap">© <?= date('Y') ?> ISTSGA · Inventario Tecnológico.</p>
</footer>
