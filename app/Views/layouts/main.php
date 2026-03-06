<?php
/**
 * TurnoFlow - Layout Principal
 * Diseño empresarial profesional
 */

$user = $_SESSION['user'] ?? null;
$rol = $user['rol'] ?? '';
$currentPage = $currentPage ?? 'dashboard';

// Permisos por rol
$isAdmin = $rol === 'admin';
$isCoordinador = in_array($rol, ['admin', 'coordinador']);
$isSupervisor = in_array($rol, ['admin', 'coordinador', 'supervisor']);
$isAsesor = $rol === 'asesor';

// Color del rol para badges
$rolColors = [
    'admin' => '#dc2626',
    'coordinador' => '#7c3aed',
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

    <!-- Metronic CSS (utilities) - Comentado porque no es necesario para el diseño actual -->
    <!-- <link href="/system-horario/TurnoFlow/dist/assets/plugins/global/plugins.bundle.css" rel="stylesheet"> -->

    <style>
        /* ========================================
           TURNOFLOW - DISEÑO EMPRESARIAL
           ======================================== */

        :root {
            --sidebar-width: 240px;
            --sidebar-collapsed-width: 70px;
            --header-height: 56px;
            --content-padding: 24px;

            /* Colores corporativos */
            --corp-primary: #2563eb;
            --corp-primary-dark: #1d4ed8;
            --corp-sidebar-bg: #0f172a;
            --corp-sidebar-hover: rgba(255,255,255,0.05);
            --corp-sidebar-active: rgba(37, 99, 235, 0.15);
            --corp-gray-50: #f8fafc;
            --corp-gray-100: #f1f5f9;
            --corp-gray-200: #e2e8f0;
            --corp-gray-300: #cbd5e1;
            --corp-gray-400: #94a3b8;
            --corp-gray-500: #64748b;
            --corp-gray-600: #475569;
            --corp-gray-700: #334155;
            --corp-gray-800: #1e293b;
            --corp-gray-900: #0f172a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--corp-gray-100);
            font-size: 14px;
            line-height: 1.5;
            color: var(--corp-gray-700);
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--corp-sidebar-bg);
            z-index: 1000;
            transition: width 0.2s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        /* Logo */
        .sidebar-header {
            height: var(--header-height);
            padding: 0 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            overflow: hidden;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            min-width: 32px;
            background: var(--corp-primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon svg {
            width: 18px;
            height: 18px;
            fill: white;
        }

        .logo-text {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            margin-left: 10px;
            white-space: nowrap;
            transition: opacity 0.15s;
        }

        .sidebar.collapsed .logo-text {
            opacity: 0;
            width: 0;
        }

        /* Collapse Button */
        .collapse-btn {
            width: 28px;
            height: 28px;
            min-width: 28px;
            border-radius: 6px;
            background: rgba(255,255,255,0.08);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }

        .collapse-btn:hover {
            background: rgba(255,255,255,0.12);
        }

        .collapse-btn svg {
            width: 16px;
            height: 16px;
            fill: var(--corp-gray-400);
            transition: transform 0.2s;
        }

        .sidebar.collapsed .collapse-btn svg {
            transform: rotate(180deg);
        }

        .sidebar.collapsed .collapse-btn {
            display: none;
        }

        .sidebar.collapsed .logo {
            cursor: pointer;
        }

        .sidebar.collapsed .logo-icon {
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .sidebar.collapsed .logo-icon:hover {
            transform: scale(1.08);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.3);
        }

        /* Menu */
        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 12px 0;
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 3px;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
        }

        .menu-section {
            padding: 16px 16px 6px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--corp-gray-500);
            white-space: nowrap;
        }

        .sidebar.collapsed .menu-section {
            padding: 16px 8px 6px;
            text-align: center;
            font-size: 0;
        }

        .sidebar.collapsed .menu-section::after {
            content: '•••';
            font-size: 8px;
            letter-spacing: 1px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            margin: 2px 8px;
            color: var(--corp-gray-400);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.15s ease;
            white-space: nowrap;
            position: relative;
        }

        .menu-item:hover {
            color: #fff;
            background: var(--corp-sidebar-hover);
        }

        .menu-item.active {
            color: #fff;
            background: var(--corp-sidebar-active);
        }

        .menu-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: var(--corp-primary);
            border-radius: 0 2px 2px 0;
        }

        .menu-item svg {
            width: 18px;
            height: 18px;
            min-width: 18px;
            margin-right: 10px;
            opacity: 0.7;
        }

        .menu-item:hover svg,
        .menu-item.active svg {
            opacity: 1;
        }

        .menu-item-text {
            transition: opacity 0.15s;
        }

        .sidebar.collapsed .menu-item {
            padding: 10px;
            margin: 2px 8px;
            justify-content: center;
        }

        .sidebar.collapsed .menu-item svg {
            margin-right: 0;
        }

        .sidebar.collapsed .menu-item-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar.collapsed .menu-item.active::before {
            display: none;
        }

        /* Sidebar User */
        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }

        .user-card {
            display: flex;
            align-items: center;
            padding: 8px;
            border-radius: 8px;
            background: rgba(255,255,255,0.04);
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            min-width: 34px;
            border-radius: 8px;
            background: var(--corp-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .user-info {
            flex: 1;
            min-width: 0;
            margin-left: 10px;
            transition: opacity 0.15s;
        }

        .sidebar.collapsed .user-info {
            opacity: 0;
            width: 0;
            margin-left: 0;
        }

        .user-name {
            color: #fff;
            font-weight: 600;
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.7rem;
            color: var(--corp-primary);
            font-weight: 500;
        }

        .logout-btn {
            width: 30px;
            height: 30px;
            min-width: 30px;
            border-radius: 6px;
            background: rgba(220, 38, 38, 0.1);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
            margin-left: 8px;
        }

        .sidebar.collapsed .logout-btn {
            display: none;
        }

        .logout-btn:hover {
            background: rgba(220, 38, 38, 0.2);
        }

        .logout-btn svg {
            width: 16px;
            height: 16px;
            fill: #dc2626;
        }

        /* ===== MAIN CONTENT ===== */
        .main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.2s ease;
        }

        body.sidebar-collapsed .main {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Header/Navbar */
        .header {
            height: var(--header-height);
            background: #fff;
            border-bottom: 1px solid var(--corp-gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 var(--content-padding);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .mobile-toggle {
            display: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--corp-gray-100);
            border: none;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        .mobile-toggle svg {
            width: 20px;
            height: 20px;
            fill: var(--corp-gray-600);
        }

        .page-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--corp-gray-900);
        }

        .role-badge {
            padding: 4px 10px;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: 600;
            color: #fff;
            background: <?= $rolColor ?>;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--corp-gray-100);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
            position: relative;
        }

        .header-btn:hover {
            background: var(--corp-gray-200);
        }

        .header-btn svg {
            width: 18px;
            height: 18px;
            fill: var(--corp-gray-600);
        }

        /* Content */
        .content {
            flex: 1;
            padding: var(--content-padding);
        }

        /* Footer */
        .footer {
            padding: 16px var(--content-padding);
            background: #fff;
            border-top: 1px solid var(--corp-gray-200);
            text-align: center;
            color: var(--corp-gray-500);
            font-size: 0.8rem;
        }

        /* ===== MOBILE ===== */
        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .overlay.show {
            display: block;
            opacity: 1;
        }

        @media (max-width: 991px) {
            :root {
                --content-padding: 16px;
            }

            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width) !important;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main {
                margin-left: 0 !important;
            }

            .mobile-toggle {
                display: flex;
            }

            .collapse-btn {
                display: none;
            }

            .sidebar.collapsed .logo-text,
            .sidebar.collapsed .menu-item-text,
            .sidebar.collapsed .user-info {
                opacity: 1;
                width: auto;
            }

            .sidebar.collapsed .menu-item {
                justify-content: flex-start;
                padding: 10px 16px;
            }

            .sidebar.collapsed .menu-item svg {
                margin-right: 10px;
            }

            .sidebar.collapsed .user-info {
                margin-left: 10px;
            }

            .sidebar.collapsed .logout-btn {
                display: flex;
            }
        }

        @media (max-width: 576px) {
            .role-badge {
                display: none;
            }

            .page-title {
                font-size: 0.95rem;
            }
        }

        /* ===== TOOLTIP (collapsed sidebar) ===== */
        .sidebar.collapsed .menu-item::after {
            content: attr(data-title);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--corp-gray-800);
            color: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.15s;
            margin-left: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1001;
        }

        .sidebar.collapsed .menu-item:hover::after {
            opacity: 1;
            visibility: visible;
        }

        @media (max-width: 991px) {
            .sidebar.collapsed .menu-item::after {
                display: none;
            }
        }

        /* ===== UTILITIES ===== */
        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-primary {
            background: var(--corp-primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--corp-primary-dark);
        }

        .btn-light {
            background: var(--corp-gray-100);
            color: var(--corp-gray-700);
        }

        .btn-light:hover {
            background: var(--corp-gray-200);
        }

        .card {
            background: #fff;
            border: 1px solid var(--corp-gray-200);
            border-radius: 10px;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--corp-gray-200);
            background: var(--corp-gray-50);
        }

        .card-body {
            padding: 20px;
        }
    </style>
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
            <div class="menu-section">Operaciones</div>
            <a href="<?= BASE_URL ?>/schedules" class="menu-item <?= $currentPage === 'schedules' ? 'active' : '' ?>" data-title="Horarios">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                <span class="menu-item-text">Horarios</span>
            </a>
            <?php endif; ?>

            <?php if ($isCoordinador): ?>
            <div class="menu-section">Gestion</div>
            <a href="<?= BASE_URL ?>/campaigns" class="menu-item <?= $currentPage === 'campaigns' ? 'active' : '' ?>" data-title="Campanas">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
                <span class="menu-item-text">Campanas</span>
            </a>
            <a href="<?= BASE_URL ?>/advisors" class="menu-item <?= $currentPage === 'advisors' ? 'active' : '' ?>" data-title="Asesores">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                <span class="menu-item-text">Asesores</span>
            </a>
            <div class="menu-section">Reportes</div>
            <a href="<?= BASE_URL ?>/reports" class="menu-item <?= $currentPage === 'reports' ? 'active' : '' ?>" data-title="Reportes">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                <span class="menu-item-text">Reportes</span>
            </a>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <div class="menu-section">Sistema</div>
            <a href="<?= BASE_URL ?>/users" class="menu-item <?= $currentPage === 'users' ? 'active' : '' ?>" data-title="Usuarios">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <span class="menu-item-text">Usuarios</span>
            </a>
            <a href="<?= BASE_URL ?>/roles" class="menu-item <?= $currentPage === 'roles' ? 'active' : '' ?>" data-title="Roles">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                <span class="menu-item-text">Roles</span>
            </a>
            <a href="<?= BASE_URL ?>/settings" class="menu-item <?= $currentPage === 'settings' ? 'active' : '' ?>" data-title="Configuracion">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                <span class="menu-item-text">Configuracion</span>
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
                <span class="role-badge"><?= ucfirst($rol) ?></span>
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
    &copy; <?= date('Y') ?> <strong>TurnoFlow</strong>. Plataforma de Gestión de Horarios. Desarrollado por Ricardo Romero.
</footer>
    </main>

    <!-- Scripts -->
    <!-- Metronic JS comentado - no necesario para el diseño actual -->
    <!-- <script src="/system-horario/TurnoFlow/dist/assets/plugins/global/plugins.bundle.js"></script> -->
    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const mobileToggle = document.getElementById('mobileToggle');
        const collapseBtn = document.getElementById('collapseBtn');

        // Mobile toggle
        mobileToggle?.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay?.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });

        // Desktop collapse
        collapseBtn?.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });

        // Expand on logo click when collapsed
        const logo = document.querySelector('.logo');
        logo?.addEventListener('click', (e) => {
            if (sidebar.classList.contains('collapsed')) {
                e.preventDefault();
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        });

        // Restore state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
        }
    </script>
    <?php if (!empty($extraScripts)) foreach ($extraScripts as $s) echo $s . "\n"; ?>
</body>
</html>
