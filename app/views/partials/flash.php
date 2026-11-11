<?php
/**
 * Avisos de la última acción (Session::flash 'ok' y 'error').
 */
$flashOk    = Session::flash('ok');
$flashError = Session::flash('error');
?>
<?php if ($flashOk): ?>
    <div class="notice notice--ok" role="status">
        <svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>
        <p><?= View::e($flashOk) ?></p>
    </div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="notice notice--error" role="alert">
        <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
        <p><?= View::e($flashError) ?></p>
    </div>
<?php endif; ?>
