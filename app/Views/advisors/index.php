<?php
/**
 * TurnoFlow - Vista de Asesores
 * Diseno empresarial profesional
 */

$pageTitle = 'Asesores';
$currentPage = 'advisors';

ob_start();
?>

<div class="advisors-page">
    <!-- Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="header-title">Asesores</h1>
                <p class="header-subtitle">Gestiona los asesores del call center</p>
            </div>
            <div class="header-actions">
                <a href="<?= BASE_URL ?>/advisors/create" class="btn-action btn-primary">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    Nuevo Asesor
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
    <div class="flash-banner flash-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
    <div class="flash-banner flash-error"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <!-- Stats Summary -->
    <div class="stats-row">
        <?php
        $totalAdvisors = count($advisors);
        $activos = count(array_filter($advisors, fn($a) => $a['estado'] === 'activo'));
        $inactivos = count(array_filter($advisors, fn($a) => $a['estado'] === 'inactivo'));
        $licencia = count(array_filter($advisors, fn($a) => $a['estado'] === 'licencia'));
        $conVPN = count(array_filter($advisors, fn($a) => $a['tiene_vpn']));
        ?>
        <div class="stat-mini">
            <span class="stat-value"><?= $totalAdvisors ?></span>
            <span class="stat-label">Total</span>
        </div>
        <div class="stat-mini stat-active">
            <span class="stat-value"><?= $activos ?></span>
            <span class="stat-label">Activos</span>
        </div>
        <div class="stat-mini stat-inactive">
            <span class="stat-value"><?= $inactivos ?></span>
            <span class="stat-label">Inactivos</span>
        </div>
        <div class="stat-mini stat-license">
            <span class="stat-value"><?= $licencia ?></span>
            <span class="stat-label">Licencia</span>
        </div>
        <div class="stat-mini stat-vpn">
            <span class="stat-value"><?= $conVPN ?></span>
            <span class="stat-label">Con VPN</span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="data-panel">
        <?php if (empty($advisors)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <h3 class="empty-title">No hay asesores registrados</h3>
            <p class="empty-text">Agrega tu primer asesor para comenzar a asignar horarios.</p>
            <a href="<?= BASE_URL ?>/advisors/create" class="btn-action btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Agregar Asesor
            </a>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                Listado de Asesores
            </div>
            <div class="panel-tools">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="searchInput" placeholder="Buscar asesor...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="advisorsTable">
                <thead>
                    <tr>
                        <th>Asesor</th>
                        <th>Cedula</th>
                        <th>Campana</th>
                        <th>Contrato</th>
                        <th>VPN</th>
                        <th>Extras</th>
                        <th>Max Horas</th>
                        <th>Estado</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advisors as $advisor): ?>
                    <tr>
                        <td>
                            <div class="advisor-cell">
                                <div class="advisor-avatar">
                                    <?= strtoupper(substr($advisor['nombres'], 0, 1) . substr($advisor['apellidos'], 0, 1)) ?>
                                </div>
                                <div class="advisor-info">
                                    <span class="advisor-name"><?= htmlspecialchars($advisor['apellidos'] . ', ' . $advisor['nombres']) ?></span>
                                    <span class="advisor-id">#<?= $advisor['id'] ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="cedula"><?= htmlspecialchars($advisor['cedula'] ?? '-') ?></span>
                        </td>
                        <td>
                            <span class="campaign-badge"><?= htmlspecialchars($advisor['campaign_nombre']) ?></span>
                        </td>
                        <td>
                            <?php
                            $contratoConfig = [
                                'completo' => ['bg' => '#dcfce7', 'color' => '#16a34a'],
                                'parcial' => ['bg' => '#fef3c7', 'color' => '#d97706']
                            ];
                            $contrato = $contratoConfig[$advisor['tipo_contrato']] ?? ['bg' => '#f1f5f9', 'color' => '#64748b'];
                            ?>
                            <span class="contract-badge" style="background: <?= $contrato['bg'] ?>; color: <?= $contrato['color'] ?>">
                                <?= ucfirst($advisor['tipo_contrato']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($advisor['tiene_vpn']): ?>
                            <span class="toggle-badge toggle-yes">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            </span>
                            <?php else: ?>
                            <span class="toggle-badge toggle-no">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($advisor['permite_extras']): ?>
                            <span class="toggle-badge toggle-yes">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            </span>
                            <?php else: ?>
                            <span class="toggle-badge toggle-no">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="max-hours"><?= $advisor['constraint_max_horas'] ?? 10 ?>h</span>
                        </td>
                        <td>
                            <?php
                            $statusConfig = [
                                'activo' => ['bg' => '#dcfce7', 'color' => '#16a34a'],
                                'inactivo' => ['bg' => '#fee2e2', 'color' => '#dc2626'],
                                'licencia' => ['bg' => '#fef3c7', 'color' => '#d97706']
                            ];
                            $status = $statusConfig[$advisor['estado']] ?? ['bg' => '#f1f5f9', 'color' => '#64748b'];
                            ?>
                            <span class="status-badge" style="background: <?= $status['bg'] ?>; color: <?= $status['color'] ?>">
                                <?= ucfirst($advisor['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="<?= BASE_URL ?>/advisors/<?= $advisor['id'] ?>/edit" class="action-btn" title="Editar">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                </a>
                                <a href="<?= BASE_URL ?>/advisors/<?= $advisor['id'] ?>/constraints" class="action-btn action-settings" title="Restricciones">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
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
    .advisors-page {
        max-width: 1400px;
        margin: 0 auto;
    }

    .flash-banner {
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 16px;
        font-size: 0.875rem;
        font-weight: 600;
        border: 1px solid transparent;
    }

    .flash-success {
        background: #ecfdf5;
        color: #047857;
        border-color: #a7f3d0;
    }

    .flash-error {
        background: #fef2f2;
        color: #b91c1c;
        border-color: #fecaca;
    }

    .page-header { margin-bottom: 24px; }

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

    .btn-action svg { width: 18px; height: 18px; }
    .btn-primary { background: #2563eb; color: #fff; }
    .btn-primary:hover { background: #1d4ed8; }

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

    .stat-mini .stat-value { font-size: 1.25rem; font-weight: 700; color: #0f172a; }
    .stat-mini .stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; font-weight: 500; }

    .stat-active { border-left: 3px solid #16a34a; }
    .stat-inactive { border-left: 3px solid #dc2626; }
    .stat-license { border-left: 3px solid #d97706; }
    .stat-vpn { border-left: 3px solid #9333ea; }

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

    .panel-title svg { width: 18px; height: 18px; fill: #64748b; }

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

    .search-box svg { width: 18px; height: 18px; fill: #94a3b8; }
    .search-box input { border: none; outline: none; font-size: 0.875rem; width: 100%; color: #334155; }

    .table-responsive { overflow-x: auto; }

    .data-table { width: 100%; border-collapse: collapse; }

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

    .data-table tbody tr:hover { background: #f8fafc; }
    .text-right { text-align: right !important; }

    .advisor-cell { display: flex; align-items: center; gap: 12px; }

    .advisor-avatar {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .advisor-info { display: flex; flex-direction: column; gap: 2px; }
    .advisor-name { font-weight: 600; color: #0f172a; font-size: 0.9rem; }
    .advisor-id { font-size: 0.75rem; color: #94a3b8; }

    .cedula { font-size: 0.85rem; color: #475569; font-family: monospace; }

    .campaign-badge {
        display: inline-block;
        padding: 4px 10px;
        background: #dbeafe;
        color: #2563eb;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .contract-badge, .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .toggle-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 6px;
    }

    .toggle-badge svg { width: 16px; height: 16px; }
    .toggle-yes { background: #dcfce7; color: #16a34a; }
    .toggle-yes svg { fill: #16a34a; }
    .toggle-no { background: #fee2e2; color: #dc2626; }
    .toggle-no svg { fill: #dc2626; }

    .max-hours {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 6px;
    }

    .actions-cell { display: flex; justify-content: flex-end; gap: 6px; }

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

    .action-btn svg { width: 16px; height: 16px; fill: currentColor; }
    .action-btn:hover { background: #dbeafe; color: #2563eb; }
    .action-settings:hover { background: #fef3c7; color: #d97706; }

    .empty-state { text-align: center; padding: 60px 20px; }

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

    .empty-icon svg { width: 32px; height: 32px; fill: #94a3b8; }
    .empty-title { font-size: 1.1rem; font-weight: 600; color: #334155; margin: 0 0 8px 0; }
    .empty-text { font-size: 0.9rem; color: #64748b; margin: 0 0 24px 0; }

    @media (max-width: 768px) {
        .header-content { flex-direction: column; align-items: stretch; }
        .btn-action { justify-content: center; }
        .stats-row { display: grid; grid-template-columns: repeat(2, 1fr); }
        .stat-mini { min-width: auto; }
        .panel-header { flex-direction: column; align-items: stretch; }
        .search-box { min-width: auto; }
    }
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('advisorsTable');

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
