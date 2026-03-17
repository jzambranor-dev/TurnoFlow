<?php
/**
 * TurnoFlow - Vista de Asesores
 */

$pageTitle = 'Asesores';
$currentPage = 'advisors';

ob_start();
?>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Asesores</h1>
            <p class="page-header-subtitle">Gestiona los asesores del call center</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?= BASE_URL ?>/advisors/bulk-config" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width: 18px; height: 18px;"><path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                Config. Masiva
            </a>
            <a href="<?= BASE_URL ?>/advisors/create" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Nuevo Asesor
            </a>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success alert-dismissible">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;flex-shrink:0;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        <span><?= htmlspecialchars($flashSuccess) ?></span>
        <button type="button" class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
    </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
    <div class="alert alert-danger alert-dismissible">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        <span><?= htmlspecialchars($flashError) ?></span>
        <button type="button" class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Stats Summary -->
    <?php
    $totalAdvisors = count($advisors);
    $activos = count(array_filter($advisors, fn($a) => $a['estado'] === 'activo'));
    $inactivos = count(array_filter($advisors, fn($a) => $a['estado'] === 'inactivo'));
    $licencia = count(array_filter($advisors, fn($a) => $a['estado'] === 'licencia'));
    $conVPN = count(array_filter($advisors, fn($a) => $a['tiene_vpn']));
    $conExtras = count(array_filter($advisors, fn($a) => $a['permite_extras']));
    ?>
    <div class="stats-row">
        <div class="stat-mini stat-clickable" data-filter="all" title="Ver todos">
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $totalAdvisors ?></span>
                <span class="stat-label">Total</span>
            </div>
        </div>
        <div class="stat-mini accent-green stat-clickable" data-filter="activo" title="Filtrar activos">
            <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $activos ?></span>
                <span class="stat-label">Activos</span>
            </div>
        </div>
        <div class="stat-mini accent-red stat-clickable" data-filter="inactivo" title="Filtrar inactivos">
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $inactivos ?></span>
                <span class="stat-label">Inactivos</span>
            </div>
        </div>
        <div class="stat-mini accent-orange stat-clickable" data-filter="licencia" title="Filtrar licencia">
            <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $licencia ?></span>
                <span class="stat-label">Licencia</span>
            </div>
        </div>
        <div class="stat-mini accent-purple">
            <div class="stat-icon" style="background: #f3e8ff; color: #7c3aed;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $conVPN ?></span>
                <span class="stat-label">Con VPN</span>
            </div>
        </div>
        <div class="stat-mini" style="border-left: 3px solid #0891b2;">
            <div class="stat-icon" style="background: #ecfeff; color: #0891b2;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $conExtras ?></span>
                <span class="stat-label">Extras</span>
            </div>
        </div>
    </div>

    <!-- Filtro por Campaña -->
    <div class="data-panel filter-panel">
        <form method="GET" action="<?= BASE_URL ?>/advisors" class="filter-form">
            <div class="filter-group">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;color:#64748b;flex-shrink:0;"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
                <label for="campaign_filter" style="font-weight: 600; white-space: nowrap;">Campaña:</label>
                <select name="campaign_id" id="campaign_filter" class="filter-select">
                    <option value="">-- Todas las campañas --</option>
                    <?php foreach ($campaignsForFilter as $cf): ?>
                    <option value="<?= $cf['id'] ?>" <?= ($filterCampaignId ?? null) == $cf['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cf['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Filtrar</button>
                <?php if (!empty($filterCampaignId)): ?>
                <a href="<?= BASE_URL ?>/advisors" class="btn" style="padding: 8px 20px; background: #6b7280; color: #fff; border-radius: 6px; text-decoration: none;">Limpiar</a>
                <?php endif; ?>
            </div>
            <div class="filter-results">
                <span class="results-count" id="resultsCount">
                    <?= count($advisors) ?> asesor<?= count($advisors) !== 1 ? 'es' : '' ?>
                </span>
                <?php if (!empty($filterCampaignId)): ?>
                <span class="filter-active-badge">Filtro activo</span>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Main Content -->
    <div class="data-panel">
        <?php if (empty($advisors)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <h5>No hay asesores registrados</h5>
            <p>Agrega tu primer asesor para comenzar a asignar horarios.</p>
            <a href="<?= BASE_URL ?>/advisors/create" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Agregar Asesor
            </a>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                Listado de Asesores
                <span class="panel-counter"><?= $totalAdvisors ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="searchInput" placeholder="Buscar asesor...">
                    <kbd class="search-kbd">Ctrl+K</kbd>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="advisorsTable">
                <thead>
                    <tr>
                        <th>Asesor</th>
                        <th>Cedula</th>
                        <th>Campaña</th>
                        <th>Contrato</th>
                        <th>VPN</th>
                        <th>Extras</th>
                        <th>Max Horas</th>
                        <th>Estado</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advisors as $i => $advisor): ?>
                    <tr class="table-row-animated" data-estado="<?= $advisor['estado'] ?>" style="animation-delay: <?= $i * 0.03 ?>s">
                        <td>
                            <div class="cell-flex">
                                <?php
                                $avatarColors = ['#2563eb','#7c3aed','#0891b2','#059669','#d97706','#dc2626','#be185d'];
                                $avatarColor = $avatarColors[$advisor['id'] % count($avatarColors)];
                                ?>
                                <div class="avatar" style="background: <?= $avatarColor ?>;">
                                    <?= strtoupper(substr($advisor['nombres'], 0, 1) . substr($advisor['apellidos'], 0, 1)) ?>
                                </div>
                                <div class="cell-stack">
                                    <span class="cell-main"><?= htmlspecialchars($advisor['apellidos'] . ', ' . $advisor['nombres']) ?></span>
                                    <span class="cell-sub">ID #<?= $advisor['id'] ?></span>
                                </div>
                            </div>
                        </td>
                        <td><span class="cell-mono"><?= htmlspecialchars($advisor['cedula'] ?? '-') ?></span></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($advisor['campaign_nombre']) ?></span></td>
                        <td>
                            <?php
                            $cType = $advisor['tipo_contrato'] === 'completo' ? 'badge-success' : 'badge-warning';
                            $cIcon = $advisor['tipo_contrato'] === 'completo' ? '8h' : '4h';
                            ?>
                            <span class="badge <?= $cType ?>" title="<?= $advisor['tipo_contrato'] === 'completo' ? 'Jornada completa (8h)' : 'Media jornada (4h)' ?>">
                                <?= ucfirst($advisor['tipo_contrato']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($advisor['tiene_vpn']): ?>
                            <span class="toggle-yes" title="VPN disponible">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            </span>
                            <?php else: ?>
                            <span class="toggle-no" title="Sin VPN">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($advisor['permite_extras']): ?>
                            <span class="toggle-yes" title="Permite horas extra">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            </span>
                            <?php else: ?>
                            <span class="toggle-no" title="No permite extras">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $maxH = $advisor['constraint_max_horas'] ?? 10;
                            $horasColor = $maxH >= 12 ? '#dc2626' : ($maxH >= 10 ? '#d97706' : '#16a34a');
                            ?>
                            <span class="badge badge-neutral" style="font-weight: 700; border-left: 3px solid <?= $horasColor ?>;" title="Maximo <?= $maxH ?> horas por dia"><?= $maxH ?>h</span>
                        </td>
                        <td>
                            <?php
                            $statusMap = [
                                'activo' => ['class' => 'badge-success', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;"><circle cx="12" cy="12" r="5"/></svg>'],
                                'inactivo' => ['class' => 'badge-danger', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;"><circle cx="12" cy="12" r="5"/></svg>'],
                                'licencia' => ['class' => 'badge-warning', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;"><circle cx="12" cy="12" r="5"/></svg>']
                            ];
                            $status = $statusMap[$advisor['estado']] ?? ['class' => 'badge-neutral', 'icon' => ''];
                            ?>
                            <span class="badge <?= $status['class'] ?> badge-with-dot">
                                <?= $status['icon'] ?>
                                <?= ucfirst($advisor['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="<?= BASE_URL ?>/advisors/<?= $advisor['id'] ?>/edit" class="action-btn edit" title="Editar y Configurar">
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
            <span class="table-footer-info" id="tableFooterInfo">
                Mostrando <strong id="visibleCount"><?= $totalAdvisors ?></strong> de <strong id="totalCount"><?= $totalAdvisors ?></strong> asesores
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
        tableId: 'advisorsTable',
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
