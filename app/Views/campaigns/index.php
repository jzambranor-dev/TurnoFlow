<?php
/**
 * TurnoFlow - Vista de Campañas
 */

$pageTitle = 'Campañas';
$currentPage = 'campaigns';

ob_start();
?>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Campañas</h1>
            <p class="page-header-subtitle">Gestiona las campañas del call center</p>
        </div>
        <a href="<?= BASE_URL ?>/campaigns/create" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Nueva Campaña
        </a>
    </div>

    <?php if (!empty($flashSuccess ?? null)): ?>
    <div class="alert alert-success alert-dismissible">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;flex-shrink:0;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        <span><?= htmlspecialchars($flashSuccess) ?></span>
        <button type="button" class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Stats Summary -->
    <?php
    $totalCampaigns = count($campaigns);
    $activas = count(array_filter($campaigns, fn($c) => $c['estado'] === 'activa'));
    $inactivas = count(array_filter($campaigns, fn($c) => $c['estado'] === 'inactiva'));
    $pausadas = count(array_filter($campaigns, fn($c) => $c['estado'] === 'pausada'));
    $totalAsesores = array_sum(array_column($campaigns, 'total_asesores'));
    $conVelada = count(array_filter($campaigns, fn($c) => $c['tiene_velada']));
    ?>
    <div class="stats-row">
        <div class="stat-mini stat-clickable" data-filter="all" title="Ver todas">
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $totalCampaigns ?></span>
                <span class="stat-label">Total</span>
            </div>
        </div>
        <div class="stat-mini accent-green stat-clickable" data-filter="activa" title="Filtrar activas">
            <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $activas ?></span>
                <span class="stat-label">Activas</span>
            </div>
        </div>
        <div class="stat-mini accent-orange stat-clickable" data-filter="pausada" title="Filtrar pausadas">
            <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $pausadas ?></span>
                <span class="stat-label">Pausadas</span>
            </div>
        </div>
        <div class="stat-mini accent-red stat-clickable" data-filter="inactiva" title="Filtrar inactivas">
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $inactivas ?></span>
                <span class="stat-label">Inactivas</span>
            </div>
        </div>
        <div class="stat-mini accent-blue">
            <div class="stat-icon" style="background: #dbeafe; color: #2563eb;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $totalAsesores ?></span>
                <span class="stat-label">Asesores</span>
            </div>
        </div>
        <div class="stat-mini accent-purple">
            <div class="stat-icon" style="background: #f3e8ff; color: #7c3aed;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M9 2c-1.05 0-2.05.16-3 .46 4.06 1.27 7 5.06 7 9.54 0 4.48-2.94 8.27-7 9.54.95.3 1.95.46 3 .46 5.52 0 10-4.48 10-10S14.52 2 9 2z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $conVelada ?></span>
                <span class="stat-label">Con Velada</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="data-panel">
        <?php if (empty($campaigns)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
            </div>
            <h5>No hay campañas registradas</h5>
            <p>Crea tu primera campaña para comenzar a gestionar horarios.</p>
            <a href="<?= BASE_URL ?>/campaigns/create" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Crear Campaña
            </a>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
                Listado de Campañas
                <span class="panel-counter"><?= $totalCampaigns ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="searchInput" placeholder="Buscar campaña...">
                    <kbd class="search-kbd">Ctrl+K</kbd>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="campaignsTable">
                <thead>
                    <tr>
                        <th>Campaña</th>
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
                    <?php foreach ($campaigns as $i => $campaign): ?>
                    <tr class="table-row-animated" data-estado="<?= $campaign['estado'] ?>" style="animation-delay: <?= $i * 0.03 ?>s">
                        <td>
                            <div class="cell-flex">
                                <?php
                                $campColors = ['#2563eb','#7c3aed','#0891b2','#059669','#d97706','#dc2626'];
                                $campColor = $campColors[$campaign['id'] % count($campColors)];
                                ?>
                                <div class="campaign-icon" style="background: <?= $campColor ?>15; color: <?= $campColor ?>; border: 1px solid <?= $campColor ?>30;">
                                    <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
                                </div>
                                <div class="cell-stack">
                                    <span class="cell-main"><?= htmlspecialchars($campaign['nombre']) ?></span>
                                    <span class="cell-sub">ID #<?= $campaign['id'] ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($campaign['cliente'])): ?>
                            <span class="client-name"><?= htmlspecialchars($campaign['cliente']) ?></span>
                            <?php else: ?>
                            <span style="color: #94a3b8; font-style: italic; font-size: 0.85rem;">Sin cliente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($campaign['supervisor_nombre']): ?>
                            <div class="cell-flex">
                                <?php
                                $supColor = $campColors[ord(substr($campaign['supervisor_nombre'], 0, 1)) % count($campColors)];
                                ?>
                                <div class="avatar" style="width: 28px; height: 28px; font-size: 0.65rem; background: <?= $supColor ?>;">
                                    <?= strtoupper(substr($campaign['supervisor_nombre'], 0, 1)) ?>
                                </div>
                                <span><?= htmlspecialchars($campaign['supervisor_nombre']) ?></span>
                            </div>
                            <?php else: ?>
                            <span class="missing-field">
                                <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                Sin asignar
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $ta = $campaign['total_asesores'] ?? 0; ?>
                            <span class="badge badge-info" style="font-weight: 700;" title="<?= $ta ?> asesor<?= $ta !== 1 ? 'es' : '' ?> asignados">
                                <svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;margin-right:4px;"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3z"/></svg>
                                <?= $ta ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($campaign['tiene_velada']): ?>
                            <span class="toggle-yes" title="Opera en horario nocturno">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            </span>
                            <?php else: ?>
                            <span class="toggle-no" title="Solo horario diurno">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $hInicio = (int)$campaign['hora_inicio_operacion'];
                            $hFin = (int)$campaign['hora_fin_operacion'];
                            $horasOp = $hFin > $hInicio ? $hFin - $hInicio : 24 - $hInicio + $hFin;
                            ?>
                            <span class="badge badge-neutral cell-mono" title="<?= $horasOp ?>h de operacion">
                                <svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;margin-right:4px;opacity:0.6;"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                                <?= str_pad($hInicio, 2, '0', STR_PAD_LEFT) ?>:00 - <?= str_pad($hFin, 2, '0', STR_PAD_LEFT) ?>:00
                            </span>
                        </td>
                        <td>
                            <?php
                            $statusMap = [
                                'activa' => ['class' => 'badge-success', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;"><circle cx="12" cy="12" r="5"/></svg>'],
                                'inactiva' => ['class' => 'badge-danger', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;"><circle cx="12" cy="12" r="5"/></svg>'],
                                'pausada' => ['class' => 'badge-warning', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;"><circle cx="12" cy="12" r="5"/></svg>']
                            ];
                            $status = $statusMap[$campaign['estado']] ?? ['class' => 'badge-neutral', 'icon' => ''];
                            ?>
                            <span class="badge <?= $status['class'] ?> badge-with-dot">
                                <?= $status['icon'] ?>
                                <?= ucfirst($campaign['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/activities" class="action-btn" title="Actividades" style="color: var(--corp-primary);">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                                </a>
                                <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/edit" class="action-btn edit" title="Editar">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer de tabla -->
        <div class="table-footer">
            <div class="tf-page-size">
                Mostrar
                <select id="pageSize">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                registros
            </div>
            <span class="table-footer-info">
                Mostrando <strong id="visibleCount"><?= $totalCampaigns ?></strong> de <strong id="totalCount"><?= $totalCampaigns ?></strong> campañas
            </span>
            <div class="tf-pagination" id="pagination"></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('searchInput');
    var activeStatFilter = null;

    var pag = new TablePaginator({
        tableId: 'campaignsTable',
        pageSizeSelId: 'pageSize',
        paginationId: 'pagination',
        infoId: 'visibleCount',
        totalId: 'totalCount',
        defaultSize: 10
    });

    function applyFilters() {
        var search = (searchInput ? searchInput.value.toLowerCase() : '');
        pag.applyFilter(function(row) {
            var matchSearch = !search || row.textContent.toLowerCase().includes(search);
            var matchStat = !activeStatFilter || row.dataset.estado === activeStatFilter;
            return matchSearch && matchStat;
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    // Atajo Ctrl+K
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) searchInput.focus();
        }
    });

    // Filtro rapido por stats
    document.querySelectorAll('.stat-clickable').forEach(function(stat) {
        stat.style.cursor = 'pointer';
        stat.addEventListener('click', function() {
            document.querySelectorAll('.stat-clickable').forEach(function(s) { s.classList.remove('stat-active-filter'); });
            var filter = this.dataset.filter;
            if (filter === 'all') {
                activeStatFilter = null;
            } else {
                activeStatFilter = filter;
                this.classList.add('stat-active-filter');
            }
            applyFilters();
        });
    });

    // Auto-dismiss alerts
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.4s, transform 0.4s';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function() { alert.style.display = 'none'; }, 400);
        }, 5000);
    });
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
