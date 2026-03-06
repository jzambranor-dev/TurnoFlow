<?php
/**
 * TurnoFlow - Dashboard
 * Diseño empresarial profesional
 */
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$rol = $user['rol'] ?? '';

// Obtener hora para saludo personalizado
$hora = (int)date('H');
if ($hora < 12) {
    $saludo = 'Buenos dias';
} elseif ($hora < 19) {
    $saludo = 'Buenas tardes';
} else {
    $saludo = 'Buenas noches';
}

// Meses en español
$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$mesActual = $meses[(int)date('n') - 1];

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    /* ========================================
       DISEÑO EMPRESARIAL - TURNOFLOW
       ======================================== */

    /* Variables corporativas */
    :root {
        --corp-primary: #2563eb;
        --corp-primary-light: #3b82f6;
        --corp-primary-dark: #1d4ed8;
        --corp-secondary: #0f172a;
        --corp-success: #059669;
        --corp-warning: #d97706;
        --corp-danger: #dc2626;
        --corp-info: #0891b2;
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
        --card-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
        --card-shadow-hover: 0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.05);
    }

    /* ===== HEADER SECTION ===== */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--corp-gray-200);
    }

    .dashboard-header-left h1 {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--corp-gray-900);
        margin: 0 0 6px 0;
        letter-spacing: -0.025em;
    }

    .dashboard-header-left p {
        color: var(--corp-gray-500);
        margin: 0;
        font-size: 0.95rem;
    }

    .dashboard-header-right {
        text-align: right;
    }

    .header-date {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        background: var(--corp-gray-50);
        border: 1px solid var(--corp-gray-200);
        padding: 12px 20px;
        border-radius: 10px;
    }

    .header-date-day {
        font-size: 2rem;
        font-weight: 700;
        color: var(--corp-primary);
        line-height: 1;
    }

    .header-date-info {
        text-align: left;
        line-height: 1.3;
    }

    .header-date-month {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--corp-gray-700);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .header-date-year {
        font-size: 0.8rem;
        color: var(--corp-gray-500);
    }

    /* ===== STAT CARDS ===== */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 24px;
        margin-bottom: 32px;
    }

    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 576px) {
        .stats-grid { grid-template-columns: 1fr; }
    }

    .stat-card {
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: 12px;
        padding: 24px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        transition: all 0.2s ease;
    }

    .stat-card:hover {
        border-color: var(--corp-gray-300);
        box-shadow: var(--card-shadow-hover);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .stat-icon svg {
        width: 24px;
        height: 24px;
    }

    .stat-icon.blue {
        background: rgba(37, 99, 235, 0.1);
    }
    .stat-icon.blue svg { fill: var(--corp-primary); }

    .stat-icon.green {
        background: rgba(5, 150, 105, 0.1);
    }
    .stat-icon.green svg { fill: var(--corp-success); }

    .stat-icon.orange {
        background: rgba(217, 119, 6, 0.1);
    }
    .stat-icon.orange svg { fill: var(--corp-warning); }

    .stat-icon.purple {
        background: rgba(124, 58, 237, 0.1);
    }
    .stat-icon.purple svg { fill: #7c3aed; }

    .stat-content {
        flex: 1;
        min-width: 0;
    }

    .stat-label {
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--corp-gray-500);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 6px;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--corp-gray-900);
        line-height: 1;
        margin-bottom: 4px;
    }

    .stat-change {
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .stat-change.positive { color: var(--corp-success); }
    .stat-change.negative { color: var(--corp-danger); }

    /* ===== QUICK ACTIONS ===== */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
        margin-bottom: 32px;
    }

    @media (max-width: 992px) {
        .quick-actions { grid-template-columns: 1fr; }
    }

    .action-card {
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: 12px;
        padding: 28px;
        display: flex;
        flex-direction: column;
        transition: all 0.2s ease;
    }

    .action-card:hover {
        border-color: var(--corp-primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .action-card-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }

    .action-card-icon svg {
        width: 22px;
        height: 22px;
    }

    .action-card-icon.blue {
        background: var(--corp-primary);
    }
    .action-card-icon.blue svg { fill: #fff; }

    .action-card-icon.green {
        background: var(--corp-success);
    }
    .action-card-icon.green svg { fill: #fff; }

    .action-card-icon.orange {
        background: var(--corp-warning);
    }
    .action-card-icon.orange svg { fill: #fff; }

    .action-card h3 {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--corp-gray-900);
        margin: 0 0 8px 0;
    }

    .action-card p {
        font-size: 0.9rem;
        color: var(--corp-gray-500);
        margin: 0 0 20px 0;
        line-height: 1.5;
        flex: 1;
    }

    .action-card .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        background: var(--corp-gray-100);
        color: var(--corp-gray-700);
        border: 1px solid var(--corp-gray-200);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.15s ease;
        align-self: flex-start;
    }

    .action-card .btn-action:hover {
        background: var(--corp-primary);
        color: #fff;
        border-color: var(--corp-primary);
    }

    .action-card .btn-action svg {
        width: 16px;
        height: 16px;
        fill: currentColor;
    }

    /* ===== DATA PANELS ===== */
    .data-grid {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 24px;
        margin-bottom: 32px;
    }

    @media (max-width: 1200px) {
        .data-grid { grid-template-columns: 1fr; }
    }

    .data-panel {
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: 12px;
        overflow: hidden;
    }

    .data-panel-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--corp-gray-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--corp-gray-50);
    }

    .data-panel-header h4 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--corp-gray-800);
        margin: 0;
    }

    .data-panel-header .badge-count {
        background: var(--corp-primary);
        color: #fff;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .data-panel-body {
        padding: 24px;
    }

    /* Summary Stats */
    .summary-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .summary-stat {
        background: var(--corp-gray-50);
        border: 1px solid var(--corp-gray-100);
        border-radius: 10px;
        padding: 20px;
        text-align: center;
    }

    .summary-stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--corp-gray-900);
        margin-bottom: 4px;
    }

    .summary-stat-value.success { color: var(--corp-success); }
    .summary-stat-value.danger { color: var(--corp-danger); }
    .summary-stat-value.warning { color: var(--corp-warning); }

    .summary-stat-label {
        font-size: 0.75rem;
        color: var(--corp-gray-500);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Data Table */
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        text-align: left;
        padding: 12px 16px;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--corp-gray-500);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--corp-gray-200);
        background: var(--corp-gray-50);
    }

    .data-table td {
        padding: 16px;
        font-size: 0.9rem;
        color: var(--corp-gray-700);
        border-bottom: 1px solid var(--corp-gray-100);
        vertical-align: middle;
    }

    .data-table tr:last-child td {
        border-bottom: none;
    }

    .data-table tr:hover td {
        background: var(--corp-gray-50);
    }

    .data-table .cell-main {
        font-weight: 600;
        color: var(--corp-gray-900);
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-badge.pending {
        background: rgba(217, 119, 6, 0.1);
        color: var(--corp-warning);
    }

    .status-badge.approved {
        background: rgba(5, 150, 105, 0.1);
        color: var(--corp-success);
    }

    .status-badge.rejected {
        background: rgba(220, 38, 38, 0.1);
        color: var(--corp-danger);
    }

    /* Action Links */
    .action-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: var(--corp-gray-100);
        color: var(--corp-gray-700);
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .action-link:hover {
        background: var(--corp-primary);
        color: #fff;
    }

    .action-link.success:hover {
        background: var(--corp-success);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 48px 24px;
    }

    .empty-state-icon {
        width: 64px;
        height: 64px;
        background: var(--corp-gray-100);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }

    .empty-state-icon svg {
        width: 28px;
        height: 28px;
        fill: var(--corp-gray-400);
    }

    .empty-state h5 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--corp-gray-700);
        margin: 0 0 8px 0;
    }

    .empty-state p {
        font-size: 0.9rem;
        color: var(--corp-gray-500);
        margin: 0;
    }

    /* ===== CAMPAIGNS SECTION ===== */
    .campaigns-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    @media (max-width: 992px) {
        .campaigns-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 576px) {
        .campaigns-grid { grid-template-columns: 1fr; }
    }

    .campaign-card {
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: 10px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.15s ease;
    }

    .campaign-card:hover {
        border-color: var(--corp-primary);
        box-shadow: var(--card-shadow-hover);
    }

    .campaign-avatar {
        width: 44px;
        height: 44px;
        background: var(--corp-primary);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .campaign-avatar svg {
        width: 22px;
        height: 22px;
        fill: #fff;
    }

    .campaign-info {
        flex: 1;
        min-width: 0;
    }

    .campaign-info h5 {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--corp-gray-900);
        margin: 0 0 4px 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .campaign-info span {
        font-size: 0.8rem;
        color: var(--corp-gray-500);
    }

    .campaign-badge {
        background: rgba(124, 58, 237, 0.1);
        color: #7c3aed;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    /* ===== ASESOR DASHBOARD ===== */
    .progress-card {
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: 12px;
        padding: 32px;
        text-align: center;
    }

    .progress-ring {
        position: relative;
        width: 140px;
        height: 140px;
        margin: 0 auto 24px;
    }

    .progress-ring svg {
        transform: rotate(-90deg);
    }

    .progress-ring .ring-bg {
        fill: none;
        stroke: var(--corp-gray-200);
        stroke-width: 10;
    }

    .progress-ring .ring-progress {
        fill: none;
        stroke: var(--corp-primary);
        stroke-width: 10;
        stroke-linecap: round;
        stroke-dasharray: 377;
        stroke-dashoffset: calc(377 - (377 * var(--progress)) / 100);
        transition: stroke-dashoffset 0.5s ease;
    }

    .progress-ring .ring-value {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }

    .progress-ring .ring-percent {
        font-size: 2rem;
        font-weight: 700;
        color: var(--corp-gray-900);
    }

    .progress-ring .ring-label {
        font-size: 0.8rem;
        color: var(--corp-gray-500);
    }

    .progress-info {
        margin-top: 16px;
    }

    .progress-hours {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--corp-gray-900);
    }

    .progress-bar-container {
        margin-top: 12px;
        max-width: 200px;
        margin-left: auto;
        margin-right: auto;
    }

    .progress-bar-track {
        height: 6px;
        background: var(--corp-gray-200);
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        background: var(--corp-primary);
        border-radius: 3px;
    }

    /* ===== RESPONSIVE SPACING ===== */
    @media (max-width: 768px) {
        .dashboard-header {
            flex-direction: column;
            gap: 20px;
        }

        .dashboard-header-right {
            text-align: left;
        }
    }
</style>
STYLE;

ob_start();
?>

<?php if (in_array($rol, ['admin', 'gerente', 'coordinador'])): ?>
<!-- ==================== DASHBOARD COORDINADOR/ADMIN ==================== -->

<!-- Header Section -->
<div class="dashboard-header">
    <div class="dashboard-header-left">
        <h1><?= $saludo ?>, <?= htmlspecialchars($user['nombre'] ?? 'Usuario') ?></h1>
        <p>Bienvenido al panel de control. Tienes <?= $stats['pending_approvals'] ?? 0 ?> horarios pendientes de revision.</p>
    </div>
    <div class="dashboard-header-right">
        <div class="header-date">
            <div class="header-date-day"><?= date('d') ?></div>
            <div class="header-date-info">
                <div class="header-date-month"><?= $mesActual ?></div>
                <div class="header-date-year"><?= date('Y') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Campanas Activas</div>
            <div class="stat-value"><?= $stats['campaigns'] ?? 0 ?></div>
            <span class="stat-change positive">En operacion</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Asesores Activos</div>
            <div class="stat-value"><?= $stats['advisors'] ?? 0 ?></div>
            <span class="stat-change positive">Personal disponible</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Pendientes</div>
            <div class="stat-value"><?= $stats['pending_approvals'] ?? 0 ?></div>
            <span class="stat-change">Requieren aprobacion</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Usuarios</div>
            <div class="stat-value"><?= $stats['users'] ?? 0 ?></div>
            <span class="stat-change">En el sistema</span>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <div class="action-card">
        <div class="action-card-icon blue">
            <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
        </div>
        <h3>Importar Dimensionamiento</h3>
        <p>Carga el archivo Excel con los requerimientos de personal por hora del jefe de contrato.</p>
        <a href="<?= BASE_URL ?>/schedules/import" class="btn-action">
            Cargar Archivo
            <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
        </a>
    </div>

    <div class="action-card">
        <div class="action-card-icon green">
            <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm-8 4H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
        </div>
        <h3>Generar Horarios</h3>
        <p>Ejecuta el motor de asignacion automatica para crear los horarios del periodo.</p>
        <a href="<?= BASE_URL ?>/schedules/generate" class="btn-action">
            Crear Horario
            <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
        </a>
    </div>

    <div class="action-card">
        <div class="action-card-icon orange">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
        </div>
        <h3>Ver Reportes</h3>
        <p>Analiza las metricas de cobertura, asistencia y rendimiento del equipo.</p>
        <a href="<?= BASE_URL ?>/reports" class="btn-action">
            Ver Reportes
            <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
        </a>
    </div>
</div>

<!-- Data Grid -->
<div class="data-grid">
    <!-- Summary Panel -->
    <div class="data-panel">
        <div class="data-panel-header">
            <h4>Resumen del Mes</h4>
        </div>
        <div class="data-panel-body">
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="summary-stat-value"><?= $stats['total_schedules'] ?? 0 ?></div>
                    <div class="summary-stat-label">Horarios</div>
                </div>
                <div class="summary-stat">
                    <div class="summary-stat-value success"><?= $stats['approved_schedules'] ?? 0 ?></div>
                    <div class="summary-stat-label">Aprobados</div>
                </div>
                <div class="summary-stat">
                    <div class="summary-stat-value danger"><?= $stats['rejected_schedules'] ?? 0 ?></div>
                    <div class="summary-stat-label">Rechazados</div>
                </div>
                <div class="summary-stat">
                    <div class="summary-stat-value warning"><?= $stats['pending_approvals'] ?? 0 ?></div>
                    <div class="summary-stat-label">En Revision</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Schedules -->
    <div class="data-panel">
        <div class="data-panel-header">
            <h4>Horarios Pendientes de Aprobacion</h4>
            <?php if (!empty($pendingSchedules)): ?>
            <span class="badge-count"><?= count($pendingSchedules) ?></span>
            <?php endif; ?>
        </div>
        <div class="data-panel-body" style="padding: 0;">
            <?php if (empty($pendingSchedules)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <h5>Todo al dia</h5>
                <p>No hay horarios pendientes de aprobacion</p>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Campana</th>
                        <th>Periodo</th>
                        <th>Supervisor</th>
                        <th>Estado</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingSchedules as $schedule): ?>
                    <tr>
                        <td class="cell-main"><?= htmlspecialchars($schedule['campaign_name']) ?></td>
                        <td><?= $schedule['periodo_mes'] ?>/<?= $schedule['periodo_anio'] ?></td>
                        <td><?= htmlspecialchars($schedule['generado_por_nombre'] ?? '-') ?></td>
                        <td><span class="status-badge pending">Pendiente</span></td>
                        <td style="text-align: right;">
                            <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>" class="action-link">Ver</a>
                            <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/approve" class="action-link success">Aprobar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($recentCampaigns)): ?>
<!-- Campaigns Section -->
<div class="data-panel">
    <div class="data-panel-header">
        <h4>Campanas Activas</h4>
        <a href="<?= BASE_URL ?>/campaigns" class="action-link">Ver Todas</a>
    </div>
    <div class="data-panel-body">
        <div class="campaigns-grid">
            <?php foreach ($recentCampaigns as $campaign): ?>
            <div class="campaign-card">
                <div class="campaign-avatar">
                    <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12z"/></svg>
                </div>
                <div class="campaign-info">
                    <h5><?= htmlspecialchars($campaign['nombre']) ?></h5>
                    <span><?= $campaign['total_asesores'] ?> asesores</span>
                </div>
                <?php if ($campaign['tiene_velada']): ?>
                <span class="campaign-badge">24/7</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($rol === 'supervisor'): ?>
<!-- ==================== DASHBOARD SUPERVISOR ==================== -->

<!-- Header Section -->
<div class="dashboard-header">
    <div class="dashboard-header-left">
        <h1><?= $saludo ?>, <?= htmlspecialchars($user['nombre'] ?? 'Supervisor') ?></h1>
        <p>Gestiona los horarios de tus campanas asignadas.</p>
    </div>
    <div class="dashboard-header-right">
        <div class="header-date">
            <div class="header-date-day"><?= date('d') ?></div>
            <div class="header-date-info">
                <div class="header-date-month"><?= $mesActual ?></div>
                <div class="header-date-year"><?= date('Y') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Mis Campanas</div>
            <div class="stat-value"><?= $stats['campaigns'] ?? 0 ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Mis Asesores</div>
            <div class="stat-value"><?= $stats['advisors'] ?? 0 ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">
            <svg viewBox="0 0 24 24"><path d="M6 2v6h.01L6 8.01 10 12l-4 4 .01.01H6V22h12v-5.99h-.01L18 16l-4-4 4-3.99-.01-.01H18V2H6z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">En Borrador</div>
            <div class="stat-value"><?= $stats['draft_schedules'] ?? 0 ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Aprobados</div>
            <div class="stat-value"><?= $stats['approved_schedules'] ?? 0 ?></div>
        </div>
    </div>
</div>

<!-- Action + Campaigns Grid -->
<div class="data-grid">
    <div class="action-card" style="height: auto;">
        <div class="action-card-icon blue">
            <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
        </div>
        <h3>Importar Dimensionamiento</h3>
        <p>Carga el archivo Excel del jefe de contrato con los requerimientos por hora.</p>
        <a href="<?= BASE_URL ?>/schedules/import" class="btn-action">
            Cargar Archivo
            <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
        </a>
    </div>

    <div class="data-panel">
        <div class="data-panel-header">
            <h4>Mis Campanas</h4>
        </div>
        <div class="data-panel-body">
            <?php if (empty($recentCampaigns)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12z"/></svg>
                </div>
                <h5>Sin campanas asignadas</h5>
                <p>Contacta al coordinador para asignacion</p>
            </div>
            <?php else: ?>
            <?php foreach ($recentCampaigns as $campaign): ?>
            <div class="campaign-card" style="margin-bottom: 12px;">
                <div class="campaign-avatar">
                    <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12z"/></svg>
                </div>
                <div class="campaign-info">
                    <h5><?= htmlspecialchars($campaign['nombre']) ?></h5>
                    <span><?= $campaign['total_asesores'] ?> asesores</span>
                </div>
                <a href="<?= BASE_URL ?>/schedules?campaign=<?= $campaign['id'] ?>" class="action-link">Ver</a>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($rol === 'asesor'): ?>
<!-- ==================== DASHBOARD ASESOR ==================== -->

<?php
$horasTrabajadas = $stats['hours_this_month'] ?? 0;
$horasMeta = 177;
$porcentaje = min(100, round(($horasTrabajadas / $horasMeta) * 100));
?>

<!-- Header Section -->
<div class="dashboard-header">
    <div class="dashboard-header-left">
        <h1><?= $saludo ?>, <?= htmlspecialchars($user['nombre'] ?? 'Asesor') ?></h1>
        <p>Consulta tu horario y progreso mensual.</p>
    </div>
    <div class="dashboard-header-right">
        <div class="header-date">
            <div class="header-date-day"><?= date('d') ?></div>
            <div class="header-date-info">
                <div class="header-date-month"><?= $mesActual ?></div>
                <div class="header-date-year"><?= date('Y') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Asesor Stats Grid -->
<div class="data-grid" style="grid-template-columns: 1fr 1fr 1fr;">
    <!-- Progress Card -->
    <div class="progress-card">
        <div class="progress-ring" style="--progress: <?= $porcentaje ?>">
            <svg width="140" height="140" viewBox="0 0 140 140">
                <circle class="ring-bg" cx="70" cy="70" r="60"/>
                <circle class="ring-progress" cx="70" cy="70" r="60"/>
            </svg>
            <div class="ring-value">
                <div class="ring-percent"><?= $porcentaje ?>%</div>
                <div class="ring-label">completado</div>
            </div>
        </div>
        <div class="progress-info">
            <div class="progress-hours"><?= $horasTrabajadas ?>h de <?= $horasMeta ?>h</div>
            <div class="progress-bar-container">
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" style="width: <?= $porcentaje ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div>
        <div class="stat-card" style="margin-bottom: 16px;">
            <div class="stat-icon green">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.5 2 2 6.5 2 12C2 17.5 6.5 22 12 22C17.5 22 22 17.5 22 12C22 6.5 17.5 2 12 2ZM16.2 16.2L11 13V7H13V11.8L17 14.4L16.2 16.2Z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Turnos Proximos</div>
                <div class="stat-value"><?= $stats['upcoming_shifts'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">
                <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Dias Trabajados</div>
                <div class="stat-value"><?= $stats['days_worked'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <!-- Action Card -->
    <div class="action-card" style="height: auto;">
        <div class="action-card-icon blue">
            <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
        </div>
        <h3>Mi Horario</h3>
        <p>Consulta todos tus turnos asignados para este mes.</p>
        <a href="<?= BASE_URL ?>/my-schedule" class="btn-action">
            Ver Horario
            <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
        </a>
    </div>
</div>

<?php else: ?>
<!-- Sin rol definido -->
<div class="data-panel">
    <div class="data-panel-body">
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            </div>
            <h5>Bienvenido a TurnoFlow</h5>
            <p>Tu cuenta no tiene un rol asignado. Contacta al administrador.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include APP_PATH . '/Views/layouts/main.php';
?>
