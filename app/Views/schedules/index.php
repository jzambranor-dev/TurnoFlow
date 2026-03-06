<?php
/**
 * TurnoFlow - Vista de Horarios
 * Diseno empresarial profesional
 */

$pageTitle = 'Horarios';
$currentPage = 'schedules';

ob_start();
?>

<div class="schedules-page">
    <!-- Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="header-title">Horarios</h1>
                <p class="header-subtitle">Gestiona los horarios de las campanas</p>
            </div>
            <div class="header-actions">
                <a href="<?= BASE_URL ?>/schedules/import" class="btn-action btn-secondary">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41 1.41L11 14.84V19h2v-4.16l1.59 1.59L16 15.01 12.01 11 8 15.01z"/></svg>
                    Importar
                </a>
                <a href="<?= BASE_URL ?>/schedules/generate" class="btn-action btn-primary">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm-8 4H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
                    Generar Horario
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="stats-row">
        <?php
        $totalSchedules = count($schedules);
        $borradores = count(array_filter($schedules, fn($s) => $s['status'] === 'borrador'));
        $enviados = count(array_filter($schedules, fn($s) => $s['status'] === 'enviado'));
        $aprobados = count(array_filter($schedules, fn($s) => $s['status'] === 'aprobado'));
        $rechazados = count(array_filter($schedules, fn($s) => $s['status'] === 'rechazado'));
        ?>
        <div class="stat-mini">
            <span class="stat-value"><?= $totalSchedules ?></span>
            <span class="stat-label">Total</span>
        </div>
        <div class="stat-mini stat-draft">
            <span class="stat-value"><?= $borradores ?></span>
            <span class="stat-label">Borradores</span>
        </div>
        <div class="stat-mini stat-pending">
            <span class="stat-value"><?= $enviados ?></span>
            <span class="stat-label">Pendientes</span>
        </div>
        <div class="stat-mini stat-approved">
            <span class="stat-value"><?= $aprobados ?></span>
            <span class="stat-label">Aprobados</span>
        </div>
        <div class="stat-mini stat-rejected">
            <span class="stat-value"><?= $rechazados ?></span>
            <span class="stat-label">Rechazados</span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="data-panel">
        <?php if (empty($schedules)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
            </div>
            <h3 class="empty-title">No hay horarios generados</h3>
            <p class="empty-text">Importa un dimensionamiento y genera el primer horario para tu campana.</p>
            <a href="<?= BASE_URL ?>/schedules/import" class="btn-action btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41 1.41L11 14.84V19h2v-4.16l1.59 1.59L16 15.01 12.01 11 8 15.01z"/></svg>
                Importar Dimensionamiento
            </a>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                Listado de Horarios
            </div>
            <div class="panel-tools">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="searchInput" placeholder="Buscar horario...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="schedulesTable">
                <thead>
                    <tr>
                        <th>Campana</th>
                        <th>Periodo</th>
                        <th>Rango de Fechas</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Generado Por</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                    <tr>
                        <td>
                            <div class="cell-campaign">
                                <span class="campaign-name"><?= htmlspecialchars($schedule['campaign_nombre']) ?></span>
                                <span class="campaign-id">#<?= $schedule['id'] ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="period-badge">
                                <?= str_pad($schedule['periodo_mes'], 2, '0', STR_PAD_LEFT) ?>/<?= $schedule['periodo_anio'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="date-range">
                                <span><?= date('d M', strtotime($schedule['fecha_inicio'])) ?></span>
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
                                <span><?= date('d M Y', strtotime($schedule['fecha_fin'])) ?></span>
                            </div>
                        </td>
                        <td>
                            <?php
                            $tipoColors = [
                                'mensual' => ['bg' => '#dbeafe', 'color' => '#1d4ed8'],
                                'semanal' => ['bg' => '#f3e8ff', 'color' => '#7c3aed'],
                                'diario' => ['bg' => '#fef3c7', 'color' => '#d97706']
                            ];
                            $tipo = $tipoColors[$schedule['tipo']] ?? ['bg' => '#f1f5f9', 'color' => '#64748b'];
                            ?>
                            <span class="type-badge" style="background: <?= $tipo['bg'] ?>; color: <?= $tipo['color'] ?>">
                                <?= ucfirst($schedule['tipo']) ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $statusConfig = [
                                'borrador' => ['bg' => '#f1f5f9', 'color' => '#64748b', 'icon' => 'M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z'],
                                'enviado' => ['bg' => '#dbeafe', 'color' => '#2563eb', 'icon' => 'M2.01 21L23 12 2.01 3 2 10l15 2-15 2z'],
                                'aprobado' => ['bg' => '#dcfce7', 'color' => '#16a34a', 'icon' => 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'],
                                'rechazado' => ['bg' => '#fee2e2', 'color' => '#dc2626', 'icon' => 'M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z']
                            ];
                            $status = $statusConfig[$schedule['status']] ?? $statusConfig['borrador'];
                            ?>
                            <span class="status-badge" style="background: <?= $status['bg'] ?>; color: <?= $status['color'] ?>">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="<?= $status['icon'] ?>"/></svg>
                                <?= ucfirst($schedule['status']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="generated-by"><?= htmlspecialchars($schedule['generado_por_nombre'] ?? '-') ?></span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>" class="action-btn" title="Ver Horario">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                </a>
                                <?php if ($schedule['status'] === 'borrador'): ?>
                                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/submit" class="action-btn action-send" title="Enviar para Aprobacion">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                                </a>
                                <?php endif; ?>
                                <?php if ($_SESSION['user']['rol'] === 'coordinador' && $schedule['status'] === 'enviado'): ?>
                                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/approve" class="action-btn action-approve" title="Aprobar">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                </a>
                                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/reject" class="action-btn action-reject" title="Rechazar">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                                </a>
                                <?php endif; ?>
                            </div>
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
    .schedules-page {
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Header */
    .page-header {
        margin-bottom: 24px;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        flex-wrap: wrap;
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

    .btn-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
        color: #334155;
    }

    /* Stats Row */
    .stats-row {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .stat-mini {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 120px;
    }

    .stat-mini .stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
    }

    .stat-mini .stat-label {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        font-weight: 500;
    }

    .stat-draft { border-left: 3px solid #94a3b8; }
    .stat-pending { border-left: 3px solid #2563eb; }
    .stat-approved { border-left: 3px solid #16a34a; }
    .stat-rejected { border-left: 3px solid #dc2626; }

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
        gap: 16px;
        flex-wrap: wrap;
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

    .search-box {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        min-width: 250px;
    }

    .search-box svg {
        width: 18px;
        height: 18px;
        fill: #94a3b8;
    }

    .search-box input {
        border: none;
        outline: none;
        font-size: 0.875rem;
        width: 100%;
        color: #334155;
    }

    .search-box input::placeholder {
        color: #94a3b8;
    }

    /* Table */
    .table-responsive {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        padding: 12px 16px;
        text-align: left;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .data-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    .text-right {
        text-align: right !important;
    }

    /* Cell Styles */
    .cell-campaign {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .campaign-name {
        font-weight: 600;
        color: #0f172a;
        font-size: 0.9rem;
    }

    .campaign-id {
        font-size: 0.75rem;
        color: #94a3b8;
    }

    .period-badge {
        display: inline-block;
        padding: 4px 10px;
        background: #f1f5f9;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
    }

    .date-range {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        color: #475569;
    }

    .date-range svg {
        width: 14px;
        height: 14px;
        fill: #cbd5e1;
    }

    .type-badge,
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-badge svg {
        flex-shrink: 0;
    }

    .generated-by {
        font-size: 0.85rem;
        color: #64748b;
    }

    /* Actions */
    .actions-cell {
        display: flex;
        justify-content: flex-end;
        gap: 6px;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        color: #64748b;
        transition: all 0.15s ease;
        text-decoration: none;
    }

    .action-btn svg {
        width: 16px;
        height: 16px;
        fill: currentColor;
    }

    .action-btn:hover {
        background: #e2e8f0;
        color: #334155;
    }

    .action-send:hover {
        background: #dbeafe;
        color: #2563eb;
    }

    .action-approve:hover {
        background: #dcfce7;
        color: #16a34a;
    }

    .action-reject:hover {
        background: #fee2e2;
        color: #dc2626;
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
        margin: 0 0 24px 0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
            align-items: stretch;
        }

        .header-actions {
            flex-direction: column;
        }

        .btn-action {
            justify-content: center;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
        }

        .stat-mini {
            min-width: auto;
        }

        .panel-header {
            flex-direction: column;
            align-items: stretch;
        }

        .search-box {
            min-width: auto;
        }
    }
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('schedulesTable');

    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
