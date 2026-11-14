<?php
// ============================================================
// index.php — Solo login (usa tu BD existente)
// ============================================================

// ============ CONFIGURACIÓN DE BASE DE DATOS ============
const DB_HOST = 'localhost';
const DB_NAME = 'inventario_tecnologico';
const DB_USER = 'root';
const DB_PASS = '';
const DB_PORT = 3307;
// ============ CONEXIÓN A BASE DE DATOS ============
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST .";port=". DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    return $pdo;
}