<?php
/**
 * TurnoFlow - Detalle de Horario
 * Diseno empresarial profesional
 */

$pageTitle = 'Ver Horario';
$currentPage = 'schedules';

// Organizar asignaciones por fecha y asesor
$assignmentsByDate = [];
$advisorNames = [];
$advisorHoursTotal = [];

foreach ($assignments as $a) {
    $key = $a['fecha'];
    $advisorKey = $a['advisor_id'];
    if (!isset($assignmentsByDate[$key])) {
        $assignmentsByDate[$key] = [];
    }
    if (!isset($assignmentsByDate[$key][$advisorKey])) {
        $assignmentsByDate[$key][$advisorKey] = [];
    }
    $assignmentsByDate[$key][$advisorKey][] = $a['hora'];
    $advisorNames[$advisorKey] = $a['apellidos'] . ', ' . $a['nombres'];

    // Total de horas por asesor
    if (!isset($advisorHoursTotal[$advisorKey])) {
        $advisorHoursTotal[$advisorKey] = 0;
    }
    $advisorHoursTotal[$advisorKey]++;
}

// Status config
$statusConfig = [
    'borrador' => ['bg' => '#f1f5f9', 'color' => '#64748b', 'label' => 'Borrador'],
    'enviado' => ['bg' => '#dbeafe', 'color' => '#2563eb', 'label' => 'Enviado'],
    'aprobado' => ['bg' => '#dcfce7', 'color' => '#16a34a', 'label' => 'Aprobado'],
    'rechazado' => ['bg' => '#fee2e2', 'color' => '#dc2626', 'label' => 'Rechazado']
];
$status = $statusConfig[$schedule['status']] ?? $statusConfig['borrador'];

ob_start();
?>

<div class="schedule-detail-page">
    <!-- Header -->
    <div class="page-header">
        <div class="header-top">
            <a href="<?= BASE_URL ?>/schedules" class="back-link">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Volver a Horarios
            </a>
            <div class="header-actions">
                <?php if ($schedule['status'] === 'borrador'): ?>
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/submit" class="btn-action btn-primary">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    Enviar para Aprobacion
                </a>
                <?php endif; ?>
                <?php if ($_SESSION['user']['rol'] === 'coordinador' && $schedule['status'] === 'enviado'): ?>
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/approve" class="btn-action btn-success">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    Aprobar
                </a>
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/reject" class="btn-action btn-danger">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    Rechazar
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-main">
            <div class="header-info">
                <div class="schedule-badge" style="background: <?= $status['bg'] ?>; color: <?= $status['color'] ?>">
                    <?= $status['label'] ?>
                </div>
                <h1 class="header-title"><?= htmlspecialchars($schedule['campaign_nombre']) ?></h1>
                <p class="header-subtitle">
                    Periodo <?= str_pad($schedule['periodo_mes'], 2, '0', STR_PAD_LEFT) ?>/<?= $schedule['periodo_anio'] ?>
                    &bull; <?= ucfirst($schedule['tipo']) ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-blue">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= date('d M', strtotime($schedule['fecha_inicio'])) ?></span>
                <span class="stat-label">Fecha Inicio</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-orange">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= date('d M', strtotime($schedule['fecha_fin'])) ?></span>
                <span class="stat-label">Fecha Fin</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-green">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= count($assignments) ?></span>
                <span class="stat-label">Asignaciones</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-purple">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= count($advisorNames) ?></span>
                <span class="stat-label">Asesores</span>
            </div>
        </div>
    </div>

    <!-- Schedule Table -->
    <div class="data-panel">
        <?php if (empty($assignments)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
            </div>
            <h3 class="empty-title">Sin asignaciones</h3>
            <p class="empty-text">Este horario aun no tiene asignaciones generadas.</p>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                Matriz de Horarios
            </div>
            <div class="panel-legend">
                <span class="legend-item"><span class="legend-dot dot-normal"></span> Horas asignadas</span>
            </div>
        </div>

        <div class="schedule-table-wrapper">
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th class="col-advisor">Asesor</th>
                        <?php
                        $dates = array_keys($assignmentsByDate);
                        sort($dates);
                        $dayNames = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
                        foreach ($dates as $date):
                            $dayName = $dayNames[date('w', strtotime($date))];
                            $isWeekend = in_array(date('w', strtotime($date)), [0, 6]);
                        ?>
                        <th class="col-day <?= $isWeekend ? 'weekend' : '' ?>">
                            <span class="day-name"><?= $dayName ?></span>
                            <span class="day-num"><?= date('d', strtotime($date)) ?></span>
                        </th>
                        <?php endforeach; ?>
                        <th class="col-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advisorNames as $advisorId => $name): ?>
                    <tr>
                        <td class="col-advisor">
                            <div class="advisor-cell">
                                <div class="advisor-avatar">
                                    <?= strtoupper(substr($name, 0, 1)) ?>
                                </div>
                                <span class="advisor-name"><?= htmlspecialchars($name) ?></span>
                            </div>
                        </td>
                        <?php foreach ($dates as $date):
                            $hours = $assignmentsByDate[$date][$advisorId] ?? [];
                            $isWeekend = in_array(date('w', strtotime($date)), [0, 6]);
                        ?>
                        <td class="col-day <?= $isWeekend ? 'weekend' : '' ?>">
                            <?php if (!empty($hours)):
                                sort($hours);
                                $hoursCount = count($hours);
                                $firstHour = min($hours);
                                $lastHour = max($hours);
                            ?>
                            <div class="hours-cell" title="<?= implode(', ', array_map(fn($h) => sprintf('%02d:00', $h), $hours)) ?>">
                                <span class="hours-badge"><?= $hoursCount ?>h</span>
                                <span class="hours-range"><?= sprintf('%02d-%02d', $firstHour, $lastHour + 1) ?></span>
                            </div>
                            <?php else: ?>
                            <span class="no-hours">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="col-total">
                            <span class="total-hours"><?= $advisorHoursTotal[$advisorId] ?? 0 ?>h</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .schedule-detail-page {
        max-width: 100%;
    }

    /* Header */
    .page-header {
        margin-bottom: 24px;
    }

    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        font-weight: 500;
        color: #64748b;
        text-decoration: none;
        transition: color 0.15s;
    }

    .back-link:hover {
        color: #2563eb;
    }

    .back-link svg {
        width: 16px;
        height: 16px;
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.15s ease;
        border: none;
        cursor: pointer;
    }

    .btn-action svg {
        width: 18px;
        height: 18px;
    }

    .btn-primary {
        background: #2563eb;
        color: #fff;
    }

    .btn-primary:hover {
        background: #1d4ed8;
    }

    .btn-success {
        background: #16a34a;
        color: #fff;
    }

    .btn-success:hover {
        background: #15803d;
    }

    .btn-danger {
        background: #dc2626;
        color: #fff;
    }

    .btn-danger:hover {
        background: #b91c1c;
    }

    .header-main {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .schedule-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 8px;
    }

    .header-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 4px 0;
    }

    .header-subtitle {
        font-size: 0.875rem;
        color: #64748b;
        margin: 0;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px;
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .stat-icon svg {
        width: 24px;
        height: 24px;
    }

    .stat-icon-blue {
        background: #dbeafe;
    }
    .stat-icon-blue svg { fill: #2563eb; }

    .stat-icon-orange {
        background: #ffedd5;
    }
    .stat-icon-orange svg { fill: #ea580c; }

    .stat-icon-green {
        background: #dcfce7;
    }
    .stat-icon-green svg { fill: #16a34a; }

    .stat-icon-purple {
        background: #f3e8ff;
    }
    .stat-icon-purple svg { fill: #9333ea; }

    .stat-content {
        display: flex;
        flex-direction: column;
    }

    .stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
    }

    .stat-label {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        font-weight: 500;
    }

    /* Data Panel */
    .data-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }

    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .panel-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        font-weight: 600;
        color: #334155;
    }

    .panel-title svg {
        width: 18px;
        height: 18px;
        fill: #64748b;
    }

    .panel-legend {
        display: flex;
        gap: 16px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        color: #64748b;
    }

    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 3px;
    }

    .dot-normal {
        background: #dcfce7;
        border: 1px solid #16a34a;
    }

    /* Schedule Table */
    .schedule-table-wrapper {
        overflow-x: auto;
    }

    .schedule-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }

    .schedule-table th,
    .schedule-table td {
        border-bottom: 1px solid #f1f5f9;
        padding: 0;
    }

    .schedule-table th {
        background: #f8fafc;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #64748b;
        padding: 12px 8px;
        text-align: center;
    }

    .col-advisor {
        text-align: left !important;
        padding-left: 16px !important;
        min-width: 180px;
        position: sticky;
        left: 0;
        background: #fff;
        z-index: 10;
    }

    th.col-advisor {
        background: #f8fafc;
    }

    .col-day {
        min-width: 65px;
        text-align: center;
    }

    .col-day.weekend {
        background: #fef2f2;
    }

    th.col-day.weekend {
        background: #fee2e2;
    }

    .day-name {
        display: block;
        font-size: 0.65rem;
        color: #94a3b8;
        margin-bottom: 2px;
    }

    .day-num {
        font-size: 0.8rem;
        color: #334155;
    }

    .col-total {
        min-width: 70px;
        background: #f8fafc;
        font-weight: 600;
    }

    .schedule-table tbody tr:hover td {
        background: #f8fafc;
    }

    .schedule-table tbody tr:hover .col-advisor {
        background: #f8fafc;
    }

    .schedule-table tbody tr:hover .col-day.weekend {
        background: #fef2f2;
    }

    /* Advisor Cell */
    .advisor-cell {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 0;
    }

    .advisor-avatar {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #2563eb;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 600;
        flex-shrink: 0;
    }

    .advisor-name {
        font-size: 0.85rem;
        font-weight: 500;
        color: #0f172a;
        white-space: nowrap;
    }

    /* Hours Cell */
    .hours-cell {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 8px 4px;
        cursor: default;
    }

    .hours-badge {
        display: inline-block;
        padding: 4px 8px;
        background: #dcfce7;
        color: #16a34a;
        border-radius: 5px;
        font-size: 0.75rem;
        font-weight: 700;
        margin-bottom: 2px;
    }

    .hours-range {
        font-size: 0.65rem;
        color: #94a3b8;
    }

    .no-hours {
        color: #cbd5e1;
        padding: 12px;
        display: block;
    }

    .total-hours {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
        padding: 12px;
        display: block;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }

    .empty-icon {
        width: 64px;
        height: 64px;
        background: #f1f5f9;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }

    .empty-icon svg {
        width: 32px;
        height: 32px;
        fill: #94a3b8;
    }

    .empty-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #334155;
        margin: 0 0 8px 0;
    }

    .empty-text {
        font-size: 0.9rem;
        color: #64748b;
        margin: 0;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .header-top {
            flex-direction: column;
            align-items: flex-start;
        }

        .header-actions {
            width: 100%;
            flex-direction: column;
        }

        .btn-action {
            justify-content: center;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .col-advisor {
            position: relative;
        }
    }
</style>
STYLE;

include APP_PATH . '/Views/layouts/main.php';
?>
