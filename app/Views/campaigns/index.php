<?php
/**
 * TurnoFlow - Vista de Campanas
 * Diseno empresarial profesional
 */

$pageTitle = 'Campanas';
$currentPage = 'campaigns';

ob_start();
?>

<div class="campaigns-page">
    <!-- Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="header-title">Campanas</h1>
                <p class="header-subtitle">Gestiona las campanas del call center</p>
            </div>
            <div class="header-actions">
                <a href="<?= BASE_URL ?>/campaigns/create" class="btn-action btn-primary">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Nueva Campana
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="stats-row">
        <?php
        $totalCampaigns = count($campaigns);
        $activas = count(array_filter($campaigns, fn($c) => $c['estado'] === 'activa'));
        $inactivas = count(array_filter($campaigns, fn($c) => $c['estado'] === 'inactiva'));
        $pausadas = count(array_filter($campaigns, fn($c) => $c['estado'] === 'pausada'));
        $totalAsesores = array_sum(array_column($campaigns, 'total_asesores'));
        ?>
        <div class="stat-mini">
            <span class="stat-value"><?= $totalCampaigns ?></span>
            <span class="stat-label">Total</span>
        </div>
        <div class="stat-mini stat-active">
            <span class="stat-value"><?= $activas ?></span>
            <span class="stat-label">Activas</span>
        </div>
        <div class="stat-mini stat-paused">
            <span class="stat-value"><?= $pausadas ?></span>
            <span class="stat-label">Pausadas</span>
        </div>
        <div class="stat-mini stat-inactive">
            <span class="stat-value"><?= $inactivas ?></span>
            <span class="stat-label">Inactivas</span>
        </div>
        <div class="stat-mini stat-advisors">
            <span class="stat-value"><?= $totalAsesores ?></span>
            <span class="stat-label">Asesores</span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="data-panel">
        <?php if (empty($campaigns)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
            </div>
            <h3 class="empty-title">No hay campanas registradas</h3>
            <p class="empty-text">Crea tu primera campana para comenzar a gestionar horarios.</p>
            <a href="<?= BASE_URL ?>/campaigns/create" class="btn-action btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Crear Campana
            </a>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
                Listado de Campanas
            </div>
            <div class="panel-tools">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="searchInput" placeholder="Buscar campana...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="campaignsTable">
                <thead>
                    <tr>
                        <th>Campana</th>
                        <th>Cliente</th>
                        <th>Supervisor</th>
                        <th>Asesores</th>
                        <th>Velada</th>
                        <th>Horario</th>
                        <th>Estado</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign): ?>
                    <tr>
                        <td>
                            <div class="cell-campaign">
                                <span class="campaign-name"><?= htmlspecialchars($campaign['nombre']) ?></span>
                                <span class="campaign-id">#<?= $campaign['id'] ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="client-name"><?= htmlspecialchars($campaign['cliente'] ?? '-') ?></span>
                        </td>
                        <td>
                            <?php if ($campaign['supervisor_nombre']): ?>
                            <div class="supervisor-cell">
                                <div class="supervisor-avatar">
                                    <?= strtoupper(substr($campaign['supervisor_nombre'], 0, 1)) ?>
                                </div>
                                <span class="supervisor-name"><?= htmlspecialchars($campaign['supervisor_nombre']) ?></span>
                            </div>
                            <?php else: ?>
                            <span class="no-data">Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="advisors-badge"><?= $campaign['total_asesores'] ?? 0 ?></span>
                        </td>
                        <td>
                            <?php if ($campaign['tiene_velada']): ?>
                            <span class="toggle-badge toggle-yes">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                Si
                            </span>
                            <?php else: ?>
                            <span class="toggle-badge toggle-no">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                                No
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="hours-range">
                                <?= str_pad($campaign['hora_inicio_operacion'], 2, '0', STR_PAD_LEFT) ?>:00 - <?= str_pad($campaign['hora_fin_operacion'], 2, '0', STR_PAD_LEFT) ?>:00
                            </span>
                        </td>
                        <td>
                            <?php
                            $statusConfig = [
                                'activa' => ['bg' => '#dcfce7', 'color' => '#16a34a'],
                                'inactiva' => ['bg' => '#fee2e2', 'color' => '#dc2626'],
                                'pausada' => ['bg' => '#fef3c7', 'color' => '#d97706']
                            ];
                            $status = $statusConfig[$campaign['estado']] ?? ['bg' => '#f1f5f9', 'color' => '#64748b'];
                            ?>
                            <span class="status-badge" style="background: <?= $status['bg'] ?>; color: <?= $status['color'] ?>">
                                <?= ucfirst($campaign['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/edit" class="action-btn" title="Editar">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                </a>
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
    .campaigns-page {
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

    .stat-active { border-left: 3px solid #16a34a; }
    .stat-paused { border-left: 3px solid #d97706; }
    .stat-inactive { border-left: 3px solid #dc2626; }
    .stat-advisors { border-left: 3px solid #2563eb; }

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

    .client-name {
        font-size: 0.85rem;
        color: #475569;
    }

    .supervisor-cell {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .supervisor-avatar {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        background: #2563eb;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .supervisor-name {
        font-size: 0.85rem;
        color: #334155;
    }

    .no-data {
        font-size: 0.85rem;
        color: #94a3b8;
        font-style: italic;
    }

    .advisors-badge {
        display: inline-block;
        padding: 4px 12px;
        background: #dbeafe;
        color: #2563eb;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .toggle-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .toggle-badge svg {
        width: 14px;
        height: 14px;
    }

    .toggle-yes {
        background: #dcfce7;
        color: #16a34a;
    }

    .toggle-no {
        background: #fee2e2;
        color: #dc2626;
    }

    .hours-range {
        font-size: 0.8rem;
        color: #475569;
        font-family: monospace;
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 4px;
    }

    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
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
        background: #dbeafe;
        color: #2563eb;
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
    const table = document.getElementById('campaignsTable');

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
