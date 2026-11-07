<?php
require_once __DIR__ . '/config/guard.php';
require_once __DIR__ . '/config/bd.php';

requerir_login(['administrador']);

if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$pdo = db();
$mensaje = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id          = (int) ($_POST['id'] ?? 0);
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $activo      = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            $error = 'El nombre es obligatorio.';
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE categorias SET nombre=:n, descripcion=:d, activo=:a WHERE id=:id')
                        ->execute([':n'=>$nombre, ':d'=>$descripcion, ':a'=>$activo, ':id'=>$id]);
                    $mensaje = '✅ Categoría actualizada.';
                } else {
                    $pdo->prepare('INSERT INTO categorias (nombre, descripcion, activo) VALUES (:n,:d,:a)')
                        ->execute([':n'=>$nombre, ':d'=>$descripcion, ':a'=>$activo]);
                    $mensaje = '✅ Categoría creada.';
                }
            } catch (PDOException $e) {
                $error = $e->getCode() === '23000'
                    ? '⚠️ Ya existe una categoría con ese nombre.'
                    : '⚠️ Error: ' . $e->getMessage();
            }
        }
    }
    elseif ($accion === 'toggle') {
        $pdo->prepare('UPDATE categorias SET activo = NOT activo WHERE id=:id')
            ->execute([':id'=>(int)$_POST['id']]);
        $mensaje = '✅ Estado cambiado.';
    }
    elseif ($accion === 'eliminar') {
        $id  = (int) $_POST['id'];
        $chk = $pdo->prepare('SELECT COUNT(*) FROM equipos WHERE categoria_id=:id');
        $chk->execute([':id'=>$id]);
        if ((int)$chk->fetchColumn() > 0) {
            $error = '⚠️ No se puede eliminar: hay equipos asociados.';
        } else {
            $pdo->prepare('DELETE FROM categorias WHERE id=:id')->execute([':id'=>$id]);
            $mensaje = '✅ Categoría eliminada.';
        }
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM categorias WHERE id=:id');
    $stmt->execute([':id'=>(int)$_GET['editar']]);
    $editar = $stmt->fetch() ?: null;
}

$categorias = $pdo->query(
    'SELECT c.*,
            (SELECT COUNT(*) FROM equipos e WHERE e.categoria_id = c.id) AS total_equipos
     FROM categorias c
     ORDER BY c.activo DESC, c.nombre ASC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Categorías — Inventario</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">📦 Inventario Tec.</div>
        <div class="sidebar-section"><a href="inicio.php">🏠 Inicio</a></div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Inventario</div>
            <a href="#">💻 Equipos</a>
            <a href="categorias.php" class="active">🏷️ Categorías</a>
            <a href="#">🔧 Componentes</a>
            <a href="#">📝 Especificaciones</a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Ubicaciones</div>
            <a href="sedes.php">🏢 Sedes</a>
            <a href="#">🗂️ Áreas</a>
            <a href="#">📍 Ubicaciones</a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Personas</div>
            <a href="#">👥 Personas</a>
            <a href="#">📋 Asignaciones</a>
            <a href="#">🔑 Usuarios</a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Operaciones</div>
            <a href="#">🔄 Movimientos</a>
            <a href="#">🛠️ Mantenimientos</a>
            <a href="#">🔩 Repuestos</a>
            <a href="#">📎 Archivos</a>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <h1>Categorías</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['usuario']) ?></span>
                <span class="role"><?= htmlspecialchars($_SESSION['rol']) ?></span>
                <a href="categorias.php?logout=1">Salir</a>
            </div>
        </header>

        <main class="content">
            <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="form-card">
                <h2><?= $editar ? '✏️ Editar categoría' : '➕ Nueva categoría' ?></h2>
                <form method="POST">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" value="<?= $editar['id'] ?? 0 ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" name="nombre" required
                                   value="<?= htmlspecialchars($editar['nombre'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <input type="text" name="descripcion"
                                   value="<?= htmlspecialchars($editar['descripcion'] ?? '') ?>">
                        </div>
                        <div class="checkbox-wrap">
                            <input type="checkbox" id="activo" name="activo"
                                <?= (!$editar || !empty($editar['activo'])) ? 'checked' : '' ?>>
                            <label for="activo">Activa</label>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <?= $editar ? 'Guardar cambios' : 'Crear categoría' ?>
                            </button>
                            <?php if ($editar): ?>
                                <a href="categorias.php" class="btn btn-secondary">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <?php if (empty($categorias)): ?>
                    <div class="empty">No hay categorías registradas todavía.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr><th>#</th><th>Nombre</th><th>Descripción</th><th>Equipos</th><th>Estado</th><th>Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categorias as $c): ?>
                            <tr>
                                <td><?= $c['id'] ?></td>
                                <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($c['descripcion'] ?? '—') ?></td>
                                <td><?= (int)$c['total_equipos'] ?></td>
                                <td>
                                    <span class="badge <?= $c['activo'] ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= $c['activo'] ? 'Activa' : 'Inactiva' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="categorias.php?editar=<?= $c['id'] ?>" class="btn-sm btn-edit">✏️ Editar</a>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="accion" value="toggle">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn-sm btn-toggle">
                                                <?= $c['activo'] ? '🚫 Desactivar' : '✅ Activar' ?>
                                            </button>
                                        </form>
                                        <?php if ((int)$c['total_equipos'] === 0): ?>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn-sm btn-delete">🗑️ Eliminar</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>