<?php
session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
$usuario = $_SESSION['usuario'] ?? 'admin';
$rol     = $_SESSION['rol']     ?? 'administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inicio — Inventario Tecnológico</title>
    <style>
        :root {
            --sidebar-bg: #111827; --sidebar-hover: #1f2937; --sidebar-active: #2563eb;
            --bg: #f3f4f6; --card: #ffffff; --text: #111827; --muted: #6b7280;
            --border: #e5e7eb; --primary: #2563eb; --success: #10b981;
            --warning: #f59e0b; --danger: #ef4444;
            --purple: #8b5cf6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, Arial, sans-serif;
               background: var(--bg); color: var(--text);
               display: flex; min-height: 100vh; }

        .sidebar { width: 260px; background: var(--sidebar-bg); color: #fff;
                   display: flex; flex-direction: column;
                   position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 1.5rem 1.2rem; border-bottom: 1px solid #1f2937;
                          display: flex; align-items: center; gap: .7rem;
                          font-size: 1.05rem; font-weight: 700; }
        .sidebar-header .logo { width: 32px; height: 32px; background: var(--primary);
                                border-radius: 8px; display: flex; align-items: center;
                                justify-content: center; font-size: 1.1rem; }
        .sidebar-user { padding: 1rem 1.2rem; border-bottom: 1px solid #1f2937;
                        display: flex; align-items: center; gap: .7rem; }
        .avatar { width: 38px; height: 38px; border-radius: 50%;
                  background: linear-gradient(135deg, var(--primary), var(--purple));
                  display: flex; align-items: center; justify-content: center;
                  font-weight: 700; font-size: 1rem; color: #fff; }
        .sidebar-user .name { font-weight: 600; font-size: .9rem; }
        .sidebar-user .role { font-size: .7rem; color: #9ca3af; text-transform: uppercase;
                              letter-spacing: .05em; margin-top: 2px; }
        .sidebar-section { padding: .5rem 0; }
        .sidebar-section-title { padding: .8rem 1.2rem .3rem; font-size: .68rem;
                                 text-transform: uppercase; letter-spacing: .08em;
                                 color: #6b7280; font-weight: 700; }
        .sidebar a { display: flex; align-items: center; gap: .75rem;
                     padding: .65rem 1.2rem; color: #d1d5db; text-decoration: none;
                     font-size: .88rem; transition: all .15s;
                     border-left: 3px solid transparent; }
        .sidebar a:hover { background: var(--sidebar-hover); color: #fff; }
        .sidebar a.active { background: rgba(37,99,235,.15); color: #fff;
                            border-left-color: var(--primary); }
        .sidebar a .icon { font-size: 1.05rem; width: 20px; text-align: center; }
        .sidebar-footer { margin-top: auto; padding: 1rem 1.2rem;
                          border-top: 1px solid #1f2937; font-size: .75rem; color: #6b7280; }

        .main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .topbar { background: var(--card); padding: 1rem 2rem;
                  display: flex; justify-content: space-between; align-items: center;
                  border-bottom: 1px solid var(--border);
                  position: sticky; top: 0; z-index: 10; }
        .search-box { display: flex; align-items: center; gap: .5rem;
                      background: #f9fafb; border: 1px solid var(--border);
                      border-radius: 8px; padding: .5rem .9rem; width: 320px; }
        .search-box input { border: none; outline: none; background: transparent;
                            width: 100%; font-size: .88rem; color: var(--text); }
        .topbar-actions { display: flex; align-items: center; gap: 1rem; }
        .icon-btn { background: #f9fafb; border: 1px solid var(--border);
                    width: 38px; height: 38px; border-radius: 8px;
                    cursor: pointer; display: flex; align-items: center;
                    justify-content: center; font-size: 1rem; position: relative; }
        .icon-btn:hover { background: #f3f4f6; }
        .icon-btn .dot { position: absolute; top: 6px; right: 6px;
                         width: 8px; height: 8px; background: var(--danger);
                         border-radius: 50%; border: 2px solid #fff; }
        .btn-logout { color: var(--danger); text-decoration: none;
                      font-weight: 600; font-size: .88rem;
                      padding: .5rem .9rem; border-radius: 8px;
                      transition: background .15s; }
        .btn-logout:hover { background: #fee2e2; }

        .content { padding: 2rem; flex: 1; }

        .hero { background: linear-gradient(135deg, #1e3a8a, #2563eb 50%, #7c3aed);
                color: #fff; border-radius: 16px; padding: 2rem;
                margin-bottom: 1.5rem;
                display: flex; justify-content: space-between; align-items: center;
                position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -50%; right: -10%;
                        width: 400px; height: 400px;
                        background: rgba(255,255,255,.08); border-radius: 50%; }
        .hero::after { content: ''; position: absolute; bottom: -40%; right: 20%;
                       width: 250px; height: 250px;
                       background: rgba(255,255,255,.05); border-radius: 50%; }
        .hero-text { position: relative; z-index: 1; }
        .hero-text h1 { font-size: 1.6rem; margin-bottom: .4rem; }
        .hero-text p { font-size: .95rem; opacity: .9; max-width: 480px; }
        .hero-actions { display: flex; gap: .7rem; margin-top: 1.2rem;
                        position: relative; z-index: 1; }
        .hero-btn { padding: .6rem 1.2rem; border-radius: 8px;
                    font-size: .88rem; font-weight: 600;
                    border: none; cursor: pointer;
                    text-decoration: none; display: inline-block; }
        .hero-btn-primary { background: #fff; color: var(--primary); }
        .hero-btn-primary:hover { background: #f3f4f6; }
        .hero-btn-ghost { background: rgba(255,255,255,.15); color: #fff;
                          border: 1px solid rgba(255,255,255,.25); }
        .hero-btn-ghost:hover { background: rgba(255,255,255,.25); }
        .hero-icon { font-size: 6rem; opacity: .15; position: relative; z-index: 1; }

        .stats-grid { display: grid;
                      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                      gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--card); border-radius: 12px; padding: 1.2rem;
                     box-shadow: 0 1px 3px rgba(0,0,0,.05);
                     display: flex; justify-content: space-between; align-items: flex-start;
                     transition: transform .15s, box-shadow .15s; }
        .stat-card:hover { transform: translateY(-2px);
                           box-shadow: 0 4px 12px rgba(0,0,0,.08); }
        .stat-label { font-size: .72rem; text-transform: uppercase;
                      letter-spacing: .05em; color: var(--muted);
                      font-weight: 600; margin-bottom: .5rem; }
        .stat-value { font-size: 1.9rem; font-weight: 700; line-height: 1; }
        .stat-trend { font-size: .75rem; margin-top: .5rem;
                      display: flex; align-items: center; gap: .3rem; }
        .trend-up   { color: var(--success); }
        .trend-down { color: var(--danger); }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px;
                     display: flex; align-items: center; justify-content: center;
                     font-size: 1.3rem; }
        .stat-icon.blue   { background: #dbeafe; }
        .stat-icon.green  { background: #d1fae5; }
        .stat-icon.yellow { background: #fef3c7; }
        .stat-icon.red    { background: #fee2e2; }

        .grid-2 { display: grid; grid-template-columns: 2fr 1fr;
                  gap: 1.5rem; margin-bottom: 1.5rem; }
        .card { background: var(--card); border-radius: 12px;
                box-shadow: 0 1px 3px rgba(0,0,0,.05); overflow: hidden; }
        .card-header { padding: 1rem 1.3rem; border-bottom: 1px solid var(--border);
                       display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { font-size: 1rem; font-weight: 600; }
        .card-header a { font-size: .82rem; color: var(--primary);
                         text-decoration: none; font-weight: 600; }
        .card-header a:hover { text-decoration: underline; }
        .card-body { padding: 1.3rem; }

        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th, td { padding: .7rem 1rem; text-align: left; }
        th { background: #f9fafb; color: var(--muted);
             font-weight: 600; font-size: .7rem;
             text-transform: uppercase; letter-spacing: .04em;
             border-bottom: 1px solid var(--border); }
        tr:not(:last-child) td { border-bottom: 1px solid var(--border); }
        tbody tr:hover td { background: #f9fafb; }
        .badge { display: inline-block; padding: .2rem .6rem;
                 border-radius: 999px; font-size: .7rem;
                 font-weight: 600; text-transform: uppercase; }
        .badge-operativo       { background: #d1fae5; color: #065f46; }
        .badge-con_fallas      { background: #fee2e2; color: #991b1b; }
        .badge-en_mantenimiento{ background: #fef3c7; color: #92400e; }
        .badge-inoperativo     { background: #e5e7eb; color: #374151; }

        .modules-grid { display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                        gap: 1rem; }
        .module-card { background: var(--card); border-radius: 12px;
                       padding: 1.2rem 1rem; text-align: center;
                       cursor: pointer; transition: all .15s;
                       box-shadow: 0 1px 3px rgba(0,0,0,.05);
                       text-decoration: none; color: var(--text);
                       border: 1px solid transparent; }
        .module-card:hover { transform: translateY(-3px);
                             box-shadow: 0 6px 16px rgba(0,0,0,.08);
                             border-color: var(--primary); }
        .module-icon { width: 48px; height: 48px; border-radius: 12px;
                       display: flex; align-items: center; justify-content: center;
                       font-size: 1.4rem; margin: 0 auto .8rem; }
        .module-card h3 { font-size: .88rem; font-weight: 600; margin-bottom: .2rem; }
        .module-card p { font-size: .75rem; color: var(--muted); }

        .alert-list { list-style: none; }
        .alert-item { display: flex; gap: .8rem; padding: .8rem 0;
                      border-bottom: 1px solid var(--border); }
        .alert-item:last-child { border-bottom: none; }
        .alert-item .alert-icon { width: 34px; height: 34px; border-radius: 8px;
                                  display: flex; align-items: center; justify-content: center;
                                  font-size: 1rem; flex-shrink: 0; }
        .alert-warning .alert-icon { background: #fef3c7; }
        .alert-danger .alert-icon  { background: #fee2e2; }
        .alert-info .alert-icon    { background: #dbeafe; }
        .alert-item .alert-content { flex: 1; }
        .alert-item .alert-title { font-size: .85rem; font-weight: 600; margin-bottom: .15rem; }
        .alert-item .alert-desc { font-size: .75rem; color: var(--muted); line-height: 1.4; }
        .alert-item .alert-time { font-size: .7rem; color: var(--muted); margin-top: .3rem; }

        .chart-bars { display: flex; align-items: flex-end; gap: .7rem;
                      height: 160px; padding: .5rem 0;
                      border-bottom: 1px solid var(--border); }
        .chart-bar { flex: 1; display: flex; flex-direction: column;
                     align-items: center; gap: .4rem; }
        .chart-bar .bar { width: 100%;
                          background: linear-gradient(to top, #2563eb, #60a5fa);
                          border-radius: 6px 6px 0 0; }
        .chart-bar .bar-value { font-size: .72rem; font-weight: 600; color: var(--text); }
        .chart-bar .bar-label { font-size: .7rem; color: var(--muted);
                                text-transform: uppercase; letter-spacing: .03em; }

        .timeline { list-style: none; position: relative; padding-left: 1.5rem; }
        .timeline::before { content: ''; position: absolute; left: 6px; top: 6px; bottom: 6px;
                            width: 2px; background: var(--border); }
        .timeline li { position: relative; padding: .6rem 0; }
        .timeline li::before { content: ''; position: absolute; left: -1.5rem; top: .85rem;
                               width: 14px; height: 14px; border-radius: 50%;
                               background: var(--primary); border: 3px solid var(--card);
                               box-shadow: 0 0 0 1px var(--primary); }
        .timeline li.green::before  { background: var(--success); box-shadow: 0 0 0 1px var(--success); }
        .timeline li.yellow::before { background: var(--warning); box-shadow: 0 0 0 1px var(--warning); }
        .timeline li.red::before    { background: var(--danger); box-shadow: 0 0 0 1px var(--danger); }
        .timeline li .title { font-size: .85rem; font-weight: 600; }
        .timeline li .meta  { font-size: .75rem; color: var(--muted); margin-top: .15rem; }

        @media (max-width: 1024px) {
            .grid-2 { grid-template-columns: 1fr; }
            .search-box { width: 200px; }
        }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; }
            .content { padding: 1rem; }
            .hero { flex-direction: column; text-align: center; }
            .hero-icon { display: none; }
            .search-box { display: none; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">📦</div>
        <span>Inventario Tech</span>
    </div>
    <div class="sidebar-user">
        <div class="avatar"><?= strtoupper(substr($usuario, 0, 1)) ?></div>
        <div>
            <div class="name"><?= htmlspecialchars($usuario) ?></div>
            <div class="role"><?= htmlspecialchars($rol) ?></div>
        </div>
    </div>
    <div class="sidebar-section">
        <a href="inicio.php" class="active"><span class="icon">🏠</span> Inicio</a>
        <a href="#"><span class="icon">📊</span> Reportes</a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Inventario</div>
        <a href="#"><span class="icon">💻</span> Equipos</a>
        <a href="categorias.php"><span class="icon">🏷️</span> Categorías</a>
        <a href="#"><span class="icon">🔧</span> Componentes</a>
        <a href="#"><span class="icon">📝</span> Especificaciones</a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Ubicaciones</div>
        <a href="sedes.php"><span class="icon">🏢</span> Sedes</a>
        <a href="#"><span class="icon">🗂️</span> Áreas</a>
        <a href="#"><span class="icon">📍</span> Ubicaciones</a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Personas</div>
        <a href="#"><span class="icon">👥</span> Personas</a>
        <a href="#"><span class="icon">📋</span> Asignaciones</a>
        <a href="#"><span class="icon">🔑</span> Usuarios</a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Operaciones</div>
        <a href="#"><span class="icon">🔄</span> Movimientos</a>
        <a href="#"><span class="icon">🛠️</span> Mantenimientos</a>
        <a href="#"><span class="icon">🔩</span> Repuestos</a>
        <a href="#"><span class="icon">📎</span> Archivos</a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Sistema</div>
        <a href="#"><span class="icon">📊</span> Auditoría</a>
        <a href="#"><span class="icon">⚙️</span> Configuración</a>
    </div>
    <div class="sidebar-footer">v1.0.0 · © <?= date('Y') ?> Inventario Tech</div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" placeholder="Buscar equipo, código, serie...">
        </div>
        <div class="topbar-actions">
            <button class="icon-btn" title="Notificaciones">🔔<span class="dot"></span></button>
            <button class="icon-btn" title="Ayuda">❓</button>
            <a href="login.php?logout=1" class="btn-logout">Salir</a>
        </div>
    </header>

    <main class="content">
        <section class="hero">
            <div class="hero-text">
                <h1>¡Bienvenido de nuevo, <?= htmlspecialchars($usuario) ?>! 👋</h1>
                <p>Aquí tienes un resumen del estado de tu inventario tecnológico.</p>
                <div class="hero-actions">
                    <a href="#" class="hero-btn hero-btn-primary">➕ Registrar equipo</a>
                    <a href="#" class="hero-btn hero-btn-ghost">📊 Ver reportes</a>
                </div>
            </div>
            <div class="hero-icon">📦</div>
        </section>

        <section class="stats-grid">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Equipos totales</div>
                    <div class="stat-value">1,247</div>
                    <div class="stat-trend trend-up">▲ 12% vs. mes anterior</div>
                </div>
                <div class="stat-icon blue">💻</div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Operativos</div>
                    <div class="stat-value">1,089</div>
                    <div class="stat-trend trend-up">▲ 4% este mes</div>
                </div>
                <div class="stat-icon green">✅</div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">En mantenimiento</div>
                    <div class="stat-value">93</div>
                    <div class="stat-trend trend-down">▼ 2% esta semana</div>
                </div>
                <div class="stat-icon yellow">🛠️</div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Con fallas</div>
                    <div class="stat-value">65</div>
                    <div class="stat-trend trend-down">▼ 8% esta semana</div>
                </div>
                <div class="stat-icon red">⚠️</div>
            </div>
        </section>

        <section class="grid-2">
            <div class="card">
                <div class="card-header">
                    <h2>💻 Equipos recientes</h2>
                    <a href="#">Ver todos →</a>
                </div>
                <table>
                    <thead>
                        <tr><th>Código</th><th>Equipo</th><th>Área</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>EQ-2024-0871</strong></td><td>Dell OptiPlex 7010</td><td>Laboratorio Redes</td><td><span class="badge badge-operativo">Operativo</span></td></tr>
                        <tr><td><strong>EQ-2024-0872</strong></td><td>HP ProDesk 400 G9</td><td>Biblioteca</td><td><span class="badge badge-con_fallas">Con fallas</span></td></tr>
                        <tr><td><strong>EQ-2024-0873</strong></td><td>Lenovo ThinkPad T14</td><td>Dirección</td><td><span class="badge badge-operativo">Operativo</span></td></tr>
                        <tr><td><strong>EQ-2024-0874</strong></td><td>Apple iMac 24"</td><td>Diseño</td><td><span class="badge badge-en_mantenimiento">Mantenimiento</span></td></tr>
                        <tr><td><strong>EQ-2024-0875</strong></td><td>Epson PowerLite X49</td><td>Aula 203</td><td><span class="badge badge-operativo">Operativo</span></td></tr>
                        <tr><td><strong>EQ-2024-0876</strong></td><td>HP LaserJet M404</td><td>Secretaría</td><td><span class="badge badge-inoperativo">Inoperativo</span></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card">
                <div class="card-header"><h2>🔔 Alertas</h2><a href="#">Ver todas →</a></div>
                <div class="card-body">
                    <ul class="alert-list">
                        <li class="alert-item alert-danger">
                            <div class="alert-icon">⚠️</div>
                            <div class="alert-content">
                                <div class="alert-title">12 equipos con fallas críticas</div>
                                <div class="alert-desc">Requieren revisión inmediata.</div>
                                <div class="alert-time">Hace 2 horas</div>
                            </div>
                        </li>
                        <li class="alert-item alert-warning">
                            <div class="alert-icon">🛠️</div>
                            <div class="alert-content">
                                <div class="alert-title">5 mantenimientos próximos</div>
                                <div class="alert-desc">Programados para los próximos 7 días.</div>
                                <div class="alert-time">Hace 5 horas</div>
                            </div>
                        </li>
                        <li class="alert-item alert-info">
                            <div class="alert-icon">📦</div>
                            <div class="alert-content">
                                <div class="alert-title">23 equipos en bodega</div>
                                <div class="alert-desc">Sin asignar por más de 30 días.</div>
                                <div class="alert-time">Ayer</div>
                            </div>
                        </li>
                        <li class="alert-item alert-warning">
                            <div class="alert-icon">📅</div>
                            <div class="alert-content">
                                <div class="alert-title">3 garantías por vencer</div>
                                <div class="alert-desc">Vencen este mes.</div>
                                <div class="alert-time">Hace 2 días</div>
                            </div>
                        </li>
                        <li class="alert-item alert-info">
                            <div class="alert-icon">👤</div>
                            <div class="alert-content">
                                <div class="alert-title">Nuevo usuario creado</div>
                                <div class="alert-desc">"jlopez" con rol técnico.</div>
                                <div class="alert-time">Hace 3 días</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <section style="margin-bottom:1.5rem;">
            <h2 style="font-size:1rem; margin-bottom:1rem; font-weight:600;">🚀 Accesos rápidos</h2>
            <div class="modules-grid">
                <a href="#" class="module-card"><div class="module-icon" style="background:#dbeafe;">💻</div><h3>Equipos</h3><p>Gestionar inventario</p></a>
                <a href="categorias.php" class="module-card"><div class="module-icon" style="background:#ede9fe;">🏷️</div><h3>Categorías</h3><p>Clasificar equipos</p></a>
                <a href="sedes.php" class="module-card"><div class="module-icon" style="background:#d1fae5;">🏢</div><h3>Sedes</h3><p>Ubicaciones físicas</p></a>
                <a href="#" class="module-card"><div class="module-icon" style="background:#fef3c7;">👥</div><h3>Personas</h3><p>Responsables</p></a>
                <a href="#" class="module-card"><div class="module-icon" style="background:#fee2e2;">🛠️</div><h3>Mantenimientos</h3><p>Programar y registrar</p></a>
                <a href="#" class="module-card"><div class="module-icon" style="background:#fce7f3;">📊</div><h3>Reportes</h3><p>Estadísticas y exportar</p></a>
            </div>
        </section>

        <section class="grid-2">
            <div class="card">
                <div class="card-header"><h2>📈 Equipos por categoría</h2><a href="#">Este mes</a></div>
                <div class="card-body">
                    <div class="chart-bars">
                        <div class="chart-bar"><div class="bar-value">320</div><div class="bar" style="height:140px;"></div><div class="bar-label">PCs</div></div>
                        <div class="chart-bar"><div class="bar-value">180</div><div class="bar" style="height:80px;"></div><div class="bar-label">Laptops</div></div>
                        <div class="chart-bar"><div class="bar-value">240</div><div class="bar" style="height:105px;"></div><div class="bar-label">Monitores</div></div>
                        <div class="chart-bar"><div class="bar-value">150</div><div class="bar" style="height:65px;"></div><div class="bar-label">Impresoras</div></div>
                        <div class="chart-bar"><div class="bar-value">90</div><div class="bar" style="height:40px;"></div><div class="bar-label">Redes</div></div>
                        <div class="chart-bar"><div class="bar-value">60</div><div class="bar" style="height:28px;"></div><div class="bar-label">Otros</div></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>🕒 Actividad reciente</h2><a href="#">Ver todo →</a></div>
                <div class="card-body">
                    <ul class="timeline">
                        <li class="green"><div class="title">Equipo EQ-0871 asignado</div><div class="meta">Asignado a María González · Hace 20 min</div></li>
                        <li><div class="title">Nuevo mantenimiento registrado</div><div class="meta">EQ-0854 · Preventivo · Hace 1 hora</div></li>
                        <li class="yellow"><div class="title">Equipo EQ-0860 con fallas</div><div class="meta">Por Juan Pérez · Hace 3 horas</div></li>
                        <li class="green"><div class="title">Equipo dado de alta</div><div class="meta">Epson PowerLite X49 · Ayer</div></li>
                        <li class="red"><div class="title">Equipo dado de baja</div><div class="meta">HP LaserJet 1020 · Obsoleto · Ayer</div></li>
                    </ul>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>