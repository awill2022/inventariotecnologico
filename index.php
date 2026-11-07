<?php
require_once __DIR__ . '/config/bd.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!empty($_SESSION['usuario_id'])) {
    header('Location: inicio.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = $_POST['clave'] ?? '';

    if ($usuario === '' || $clave === '') {
        $error = 'Completa todos los campos.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare(
                'SELECT id, usuario, clave_hash, rol
                 FROM usuarios
                 WHERE usuario = :u AND activo = 1
                 LIMIT 1'
            );
            $stmt->execute([':u' => $usuario]);
            $u = $stmt->fetch();

            if ($u && $clave === $u['clave_hash']) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $u['id'];
                $_SESSION['usuario']    = $u['usuario'];
                $_SESSION['rol']        = $u['rol'];

                $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id')
                    ->execute([':id' => $u['id']]);

                header('Location: inicio.php');
                exit;
            }
            $error = 'Usuario o contraseña incorrectos.';
        } catch (PDOException $e) {
            $error = 'Error de conexión: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login — Inventario Tecnológico</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex;
               align-items: center; justify-content: center;
               background: #eef2f7; font-family: system-ui, Arial, sans-serif; }
        .card { background: #fff; padding: 2rem 2.2rem; border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,.08); width: 360px; }
        h1 { font-size: 1.4rem; margin: 0 0 1.2rem; text-align: center; color: #1f2937; }
        label { display: block; font-size: .85rem; color: #374151; margin-bottom: .3rem; }
        input { width: 100%; padding: .7rem .8rem; border: 1px solid #d1d5db;
                border-radius: 8px; margin-bottom: 1rem; font-size: .95rem; }
        input:focus { outline: none; border-color: #2563eb;
                      box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        button { width: 100%; padding: .8rem; background: #2563eb; color: #fff;
                 border: none; border-radius: 8px; font-weight: 600;
                 cursor: pointer; font-size: 1rem; }
        button:hover { background: #1d4ed8; }
        .error { background: #fee2e2; color: #991b1b; padding: .7rem;
                 border-radius: 8px; font-size: .85rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <form class="card" method="POST" autocomplete="off">
        <h1>🔐 Iniciar sesión</h1>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <label for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" required
               value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
        <label for="clave">Contraseña</label>
        <input type="password" id="clave" name="clave" required>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>
