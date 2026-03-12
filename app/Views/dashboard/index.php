<?php
/**
 * TurnoFlow - Dashboard
 */
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$rol = $user['rol'] ?? '';

// Saludo personalizado
$hora = (int)date('H');
if ($hora < 12) $saludo = 'Buenos dias';
elseif ($hora < 19) $saludo = 'Buenas tardes';
else $saludo = 'Buenas noches';

$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$mesActual = $meses[(int)date('n') - 1];

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 28px;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--corp-gray-200);
    }
    .dashboard-header h1 { font-size: 1.75rem; font-weight: 700; color: var(--corp-gray-900); margin: 0 0 6px 0; letter-spacing: -0.025em; }
    .dashboard-header p { color: var(--corp-gray-500); margin: 0; font-size: 0.95rem; }
    .header-date {
        display: inline-flex; align-items: center; gap: 12px;
        background: var(--corp-gray-50); border: 1px solid var(--corp-gray-200);
        padding: 12px 20px; border-radius: 10px;
    }
    .header-date-day { font-size: 2rem; font-weight: 700; color: var(--corp-primary); line-height: 1; }
    .header-date-month { font-size: 0.9rem; font-weight: 600; color: var(--corp-gray-700); text-transform: uppercase; letter-spacing: 0.05em; }
    .header-date-year { font-size: 0.8rem; color: var(--corp-gray-500); }
    @media (max-width: 768px) {
        .dashboard-header { flex-direction: column; gap: 20px; }
    }
</style>
STYLE;

ob_start();
?>

<?php if (in_array($rol, ['admin', 'gerente', 'coordinador'])): ?>
<!-- ==================== DASHBOARD COORDINADOR/ADMIN ==================== -->

<div class="dashboard-header">
    <div>
        <h1><?= $saludo ?>, <?= htmlspecialchars($user['nombre'] ?? 'Usuario') ?></h1>
        <p>Bienvenido al panel de control. Tienes <?= $stats['pending_approvals'] ?? 0 ?> horarios pendientes de revision.</p>
    </div>
    <div class="header-date">
        <div class="header-date-day"><?= date('d') ?></div>
        <div>
            <div class="header-date-month"><?= $mesActual ?></div>
            <div class="header-date-year"><?= date('Y') ?></div>
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
            <div class="stat-label">Campañas Activas</div>
            <div class="stat-value"><?= $stats['campaigns'] ?? 0 ?></div>
            <span class="stat-desc positive">En operación</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Asesores Activos</div>
            <div class="stat-value"><?= $stats['advisors'] ?? 0 ?></div>
            <span class="stat-desc positive">Personal disponible</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Pendientes</div>
            <div class="stat-value"><?= $stats['pending_approvals'] ?? 0 ?></div>
            <span class="stat-desc">Requieren aprobacion</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Usuarios</div>
            <div class="stat-value"><?= $stats['users'] ?? 0 ?></div>
            <span class="stat-desc">En el sistema</span>
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
        <p>Ejecuta el motor de asignación automatica para crear los horarios del periodo.</p>
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
        <div class="panel-header">
            <div class="panel-title">Resumen del Mes</div>
        </div>
        <div class="panel-body">
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
        <div class="panel-header">
            <div class="panel-title">Horarios Pendientes de Aprobacion</div>
            <?php if (!empty($pendingSchedules)): ?>
            <span class="badge-count"><?= count($pendingSchedules) ?></span>
            <?php endif; ?>
        </div>
        <div class="panel-body flush">
            <?php if (empty($pendingSchedules)): ?>
            <div class="empty-state" style="padding: 40px;">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <h5>Todo al dia</h5>
                <p style="margin-bottom: 0;">No hay horarios pendientes de aprobacion</p>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Campaña</th>
                        <th>Periodo</th>
                        <th>Supervisor</th>
                        <th>Estado</th>
                        <th class="text-right">Acciónes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingSchedules as $schedule): ?>
                    <tr>
                        <td class="cell-main"><?= htmlspecialchars($schedule['campaign_name']) ?></td>
                        <td><?= $schedule['periodo_mes'] ?>/<?= $schedule['periodo_anio'] ?></td>
                        <td><?= htmlspecialchars($schedule['generado_por_nombre'] ?? '-') ?></td>
                        <td><span class="badge badge-warning">Pendiente</span></td>
                        <td class="text-right">
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
<div class="data-panel">
    <div class="panel-header">
        <div class="panel-title">Campañas Activas</div>
        <a href="<?= BASE_URL ?>/campaigns" class="action-link">Ver Todas</a>
    </div>
    <div class="panel-body">
        <div class="campaigns-grid">
            <?php foreach ($recentCampaigns as $campaign): ?>
            <div class="campaign-card">
                <div class="avatar avatar-blue" style="width: 44px; height: 44px;">
                    <svg viewBox="0 0 24 24" fill="#fff" style="width: 22px; height: 22px;"><path d="M12 7V3H2v18h20V7H12z"/></svg>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div class="cell-main" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($campaign['nombre']) ?></div>
                    <span class="cell-sub"><?= $campaign['total_asesores'] ?> asesores</span>
                </div>
                <?php if ($campaign['tiene_velada']): ?>
                <span class="badge badge-purple">24/7</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($rol === 'supervisor'): ?>
<!-- ==================== DASHBOARD SUPERVISOR ==================== -->

<div class="dashboard-header">
    <div>
        <h1><?= $saludo ?>, <?= htmlspecialchars($user['nombre'] ?? 'Supervisor') ?></h1>
        <p>Gestióna los horarios de tus campañas asignadas.</p>
    </div>
    <div class="header-date">
        <div class="header-date-day"><?= date('d') ?></div>
        <div>
            <div class="header-date-month"><?= $mesActual ?></div>
            <div class="header-date-year"><?= date('Y') ?></div>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12z"/></svg></div>
        <div class="stat-content">
            <div class="stat-label">Mis Campañas</div>
            <div class="stat-value"><?= $stats['campaigns'] ?? 0 ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3z"/></svg></div>
        <div class="stat-content">
            <div class="stat-label">Mis Asesores</div>
            <div class="stat-value"><?= $stats['advisors'] ?? 0 ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><svg viewBox="0 0 24 24"><path d="M6 2v6h.01L6 8.01 10 12l-4 4 .01.01H6V22h12v-5.99h-.01L18 16l-4-4 4-3.99-.01-.01H18V2H6z"/></svg></div>
        <div class="stat-content">
            <div class="stat-label">En Borrador</div>
            <div class="stat-value"><?= $stats['draft_schedules'] ?? 0 ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></div>
        <div class="stat-content">
            <div class="stat-label">Aprobados</div>
            <div class="stat-value"><?= $stats['approved_schedules'] ?? 0 ?></div>
        </div>
    </div>
</div>

<div class="data-grid">
    <div class="action-card" style="height: auto;">
        <div class="action-card-icon blue"><svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg></div>
        <h3>Importar Dimensionamiento</h3>
        <p>Carga el archivo Excel del jefe de contrato con los requerimientos por hora.</p>
        <a href="<?= BASE_URL ?>/schedules/import" class="btn-action">
            Cargar Archivo
            <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
        </a>
    </div>
    <div class="data-panel">
        <div class="panel-header"><div class="panel-title">Mis Campañas</div></div>
        <div class="panel-body">
            <?php if (empty($recentCampaigns)): ?>
            <div class="empty-state" style="padding: 40px;">
                <div class="empty-state-icon"><svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12z"/></svg></div>
                <h5>Sin campañas asignadas</h5>
                <p style="margin-bottom: 0;">Contacta al coordinador para asignación</p>
            </div>
            <?php else: ?>
            <?php foreach ($recentCampaigns as $campaign): ?>
            <div class="campaign-card" style="margin-bottom: 12px;">
                <div class="avatar avatar-blue" style="width: 44px; height: 44px;">
                    <svg viewBox="0 0 24 24" fill="#fff" style="width: 22px; height: 22px;"><path d="M12 7V3H2v18h20V7H12z"/></svg>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div class="cell-main"><?= htmlspecialchars($campaign['nombre']) ?></div>
                    <span class="cell-sub"><?= $campaign['total_asesores'] ?> asesores</span>
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

<div class="dashboard-header">
    <div>
        <h1><?= $saludo ?>, <?= htmlspecialchars($user['nombre'] ?? 'Asesor') ?></h1>
        <p>Consulta tu horario y progreso mensual.</p>
    </div>
    <div class="header-date">
        <div class="header-date-day"><?= date('d') ?></div>
        <div>
            <div class="header-date-month"><?= $mesActual ?></div>
            <div class="header-date-year"><?= date('Y') ?></div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px;">
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
        <div style="margin-top: 16px;">
            <div style="font-size: 1.1rem; font-weight: 600; color: var(--corp-gray-900);"><?= $horasTrabajadas ?>h de <?= $horasMeta ?>h</div>
            <div style="margin-top: 12px; max-width: 200px; margin-left: auto; margin-right: auto;">
                <div style="height: 6px; background: var(--corp-gray-200); border-radius: 3px; overflow: hidden;">
                    <div style="height: 100%; background: var(--corp-primary); border-radius: 3px; width: <?= $porcentaje ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="stat-card" style="margin-bottom: 16px;">
            <div class="stat-icon green"><svg viewBox="0 0 24 24"><path d="M12 2C6.5 2 2 6.5 2 12C2 17.5 6.5 22 12 22C17.5 22 22 17.5 22 12C22 6.5 17.5 2 12 2ZM16.2 16.2L11 13V7H13V11.8L17 14.4L16.2 16.2Z"/></svg></div>
            <div class="stat-content">
                <div class="stat-label">Turnos Proximos</div>
                <div class="stat-value"><?= $stats['upcoming_shifts'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg></div>
            <div class="stat-content">
                <div class="stat-label">Dias Trabajados</div>
                <div class="stat-value"><?= $stats['days_worked'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <div class="action-card" style="height: auto;">
        <div class="action-card-icon blue"><svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg></div>
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
    <div class="panel-body">
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
