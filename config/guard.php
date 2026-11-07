<?php
function requerir_login(array $rolesPermitidos = []): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
    if ($rolesPermitidos && !in_array($_SESSION['rol'], $rolesPermitidos, true)) {
        http_response_code(403);
        die('⛔ Acceso denegado: no tienes permisos para esta sección.');
    }
}