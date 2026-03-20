<?php
/**
 * TurnoFlow - Layout Principal
 */

$user = $_SESSION['user'] ?? null;
$rol = $user['rol'] ?? '';
$currentPage = $currentPage ?? 'dashboard';

// Permisos por rol — Jerarquia: gerente > coordinador > supervisor > asesor
$isAdmin = in_array($rol, ['admin', 'gerente']); // gerente = mismo nivel que admin
$isCoordinador = in_array($rol, ['admin', 'gerente', 'coordinador']);
$isSupervisor = in_array($rol, ['admin', 'gerente', 'coordinador', 'supervisor']);
$isAsesor = $rol === 'asesor';

// Color del rol para badges
$rolColors = [
    'admin' => '#dc2626',
    'gerente' => '#7c3aed',
    'coordinador' => '#2563eb',
    'supervisor' => '#059669',
    'asesor' => '#d97706'
];
$rolColor = $rolColors[$rol] ?? '#2563eb';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - TurnoFlow</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- TurnoFlow CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">

    <!-- TurnoFlow JS -->
    <script src="<?= BASE_URL ?>/js/toast-loading.js"></script>
    <script src="<?= BASE_URL ?>/js/table-paginator.js" defer></script>

    <?php if (!empty($extraStyles)) foreach ($extraStyles as $s) echo $s . "\n"; ?>
</head>
<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="<?= BASE_URL ?>/dashboard" class="logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                </div>
                <span class="logo-text">TurnoFlow</span>
            </a>
            <button class="collapse-btn" id="collapseBtn" title="Colapsar">
                <svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
            </button>
        </div>

        <nav class="sidebar-menu">
            <a href="<?= BASE_URL ?>/dashboard" class="menu-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>" data-title="Dashboard">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                <span class="menu-item-text">Dashboard</span>
            </a>

            <?php if ($isAsesor): ?>
            <div class="menu-section">Mi Area</div>
            <a href="<?= BASE_URL ?>/my-schedule" class="menu-item <?= $currentPage === 'my-schedule' ? 'active' : '' ?>" data-title="Mi Horario">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                <span class="menu-item-text">Mi Horario</span>
            </a>
            <?php endif; ?>

            <?php if ($isSupervisor): ?>
            <div class="menu-section">Operaciónes</div>
            <a href="<?= BASE_URL ?>/schedules" class="menu-item <?= $currentPage === 'schedules' ? 'active' : '' ?>" data-title="Horarios">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                <span class="menu-item-text">Horarios</span>
            </a>
            <a href="<?= BASE_URL ?>/schedules/import" class="menu-item <?= $currentPage === 'schedules-import' ? 'active' : '' ?>" data-title="Importar">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                <span class="menu-item-text">Importar</span>
            </a>
            <?php endif; ?>

            <?php if ($isSupervisor): ?>
            <div class="menu-section">Gestión</div>
            <a href="<?= BASE_URL ?>/campaigns" class="menu-item <?= $currentPage === 'campaigns' ? 'active' : '' ?>" data-title="Campañas">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
                <span class="menu-item-text">Campañas</span>
            </a>
            <a href="<?= BASE_URL ?>/advisors" class="menu-item <?= $currentPage === 'advisors' ? 'active' : '' ?>" data-title="Asesores">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                <span class="menu-item-text">Asesores</span>
            </a>
            <a href="<?= BASE_URL ?>/users" class="menu-item <?= $currentPage === 'users' ? 'active' : '' ?>" data-title="Usuarios">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <span class="menu-item-text">Usuarios</span>
            </a>
            <div class="menu-section">Reportes</div>
            <a href="<?= BASE_URL ?>/reports" class="menu-item <?= $currentPage === 'reports' ? 'active' : '' ?>" data-title="Reportes">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                <span class="menu-item-text">Reportes</span>
            </a>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <div class="menu-section">Sistema</div>
            <a href="<?= BASE_URL ?>/roles" class="menu-item <?= $currentPage === 'roles' ? 'active' : '' ?>" data-title="Roles">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                <span class="menu-item-text">Roles</span>
            </a>
            <a href="<?= BASE_URL ?>/settings" class="menu-item <?= $currentPage === 'settings' ? 'active' : '' ?>" data-title="Configuración">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                <span class="menu-item-text">Configuración</span>
            </a>
            <a href="<?= BASE_URL ?>/changelog" class="menu-item <?= $currentPage === 'changelog' ? 'active' : '' ?>" data-title="Changelog">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
                <span class="menu-item-text">Changelog</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['nombre'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($user['nombre'] ?? '') ?></div>
                    <div class="user-role"><?= ucfirst($rol) ?></div>
                </div>
                <a href="<?= BASE_URL ?>/logout" class="logout-btn" title="Cerrar sesion">
                    <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main -->
    <main class="main">
        <header class="header">
            <div class="header-left">
                <button class="mobile-toggle" id="mobileToggle">
                    <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
                </button>
                <span class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
                <span class="role-badge" style="background: <?= $rolColor ?>"><?= ucfirst($rol) ?></span>
            </div>
            <div class="header-right">
                <button class="header-btn" title="Notificaciones">
                    <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                </button>
            </div>
        </header>

        <div class="content">
            <?= $content ?? '' ?>
        </div>

        <footer class="footer">
            &copy; <?= date('Y') ?> <strong>TurnoFlow</strong>.  Desarrollado por Ricardo.
        </footer>
    </main>

    <!-- Scripts -->
    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const mobileToggle = document.getElementById('mobileToggle');
        const collapseBtn = document.getElementById('collapseBtn');

        mobileToggle?.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay?.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });

        collapseBtn?.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });

        const logo = document.querySelector('.logo');
        logo?.addEventListener('click', (e) => {
            if (sidebar.classList.contains('collapsed')) {
                e.preventDefault();
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        });

        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
        }
    </script>
    <?php if (!empty($extraScripts)) foreach ($extraScripts as $s) echo $s . "\n"; ?>
</body>
</html>
