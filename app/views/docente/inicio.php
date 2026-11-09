<?php
/**
 * Página principal del docente (estructura base).
 * Secciones previstas según DESIGN.md: banda de saludo + buscador,
 * agenda (Hoy · Esta semana · Más adelante), Mis equipos (etiquetas patrimoniales)
 * y "Ya no están a tu cargo".
 * @var array $usuario
 */
?>
<section class="welcome">
    <div class="wrap">
        <h1>Hola, <?= View::e($usuario['nombre']) ?></h1>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <h2 class="section-title">Mis equipos</h2>
        <p>Contenido en construcción.</p>
    </div>
</section>
