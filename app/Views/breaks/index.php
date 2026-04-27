<?php
/**
 * TurnoFlow - Cumplimiento de Breaks
 * Dashboard del cruce: break planificado vs break real
 */

$pageTitle = 'Cumplimiento de Breaks';
$currentPage = 'breaks';

$monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$selectedCampaign = $selectedCampaign ?? 0;
$selectedYear = $selectedYear ?? (int)date('Y');
$selectedMonth = $selectedMonth ?? (int)date('n');
$cruceData = $cruceData ?? [];
$duracionBreakMin = $duracionBreakMin ?? 0;
$importInfo = $importInfo ?? null;

ob_start();
?>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <div class="form-breadcrumb" style="margin-bottom:8px;">
                <a href="<?= BASE_URL ?>/dashboard">Dashboard</a>
                <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:var(--corp-gray-400);"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                <span>Breaks</span>
            </div>
            <h1 class="page-header-title">Cumplimiento de Breaks</h1>
            <p class="page-header-subtitle">Cruce entre break planificado y break real por asesor</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= BASE_URL ?>/breaks/import" class="btn btn-secondary">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
                Importar Excel
            </a>
            <?php if ($selectedCampaign > 0 && !empty($cruceData)): ?>
            <a href="<?= BASE_URL ?>/breaks/export?campaign_id=<?= $selectedCampaign ?>&year=<?= $selectedYear ?>&month=<?= $selectedMonth ?>" class="btn btn-success">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                Exportar
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="data-panel filter-panel">
        <form method="GET" action="<?= BASE_URL ?>/breaks" class="filter-form">
            <div class="filter-group">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;color:#64748b;flex-shrink:0;"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
                <label for="campaign_id" style="font-weight: 600; white-space: nowrap;">Campana:</label>
                <select name="campaign_id" id="campaign_id" class="filter-select" style="min-width:200px;">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($campaigns as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedCampaign == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label for="month" style="font-weight: 600; white-space: nowrap;">Mes:</label>
                <select name="month" id="month" class="filter-select" style="min-width:140px;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $selectedMonth === $m ? 'selected' : '' ?>><?= $monthNames[$m] ?></option>
                    <?php endfor; ?>
                </select>
                <label for="year" style="font-weight: 600; white-space: nowrap;">Ano:</label>
                <select name="year" id="year" class="filter-select" style="min-width:100px;">
                    <?php for ($y = (int)date('Y') - 1; $y <= (int)date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $selectedYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Consultar</button>
            </div>
        </form>
    </div>

    <?php if ($selectedCampaign === 0): ?>
    <!-- Empty state: no campaign selected -->
    <div class="data-panel">
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            </div>
            <h5>Selecciona una campana y periodo</h5>
            <p>Elige una campana y el mes/ano para ver el reporte de cumplimiento de breaks.</p>
        </div>
    </div>

    <?php elseif (empty($cruceData)): ?>
    <!-- Empty state: no data -->
    <div class="data-panel">
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
            </div>
            <h5>No hay datos de break para este periodo</h5>
            <p>Importa el Excel desde el boton Importar para cargar los datos de break real.</p>
            <a href="<?= BASE_URL ?>/breaks/import" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
                Importar Excel
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- Import status banner -->
    <?php if ($importInfo): ?>
    <div class="alert alert-info" style="margin-bottom:16px;">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;flex-shrink:0;"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
        <span>Ultima importacion: <strong><?= htmlspecialchars($importInfo['fecha'] ?? '-') ?></strong> &mdash; <?= (int)($importInfo['total_registros'] ?? 0) ?> registros</span>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <?php
    $totalAsesores = count($cruceData);
    $totalPlanned = 0;
    $totalActual = 0;
    $totalExceso = 0;
    foreach ($cruceData as $row) {
        $totalPlanned += ($row['planned_slots'] ?? 0) * $duracionBreakMin;
        $totalActual += ($row['actual_minutes'] ?? 0);
        $totalExceso += ($row['exceso_excel'] ?? 0);
    }
    $formatTime = function($min) {
        $h = floor(abs($min) / 60);
        $m = abs($min) % 60;
        $sign = $min < 0 ? '-' : '';
        return $h > 0 ? "{$sign}{$h}h {$m}m" : "{$sign}{$m}m";
    };
    ?>
    <div class="stats-row">
        <div class="stat-mini accent-blue">
            <div class="stat-icon" style="background:#eff6ff;color:#2563eb;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $totalAsesores ?></span>
                <span class="stat-label">Total Asesores</span>
            </div>
        </div>
        <div class="stat-mini accent-purple">
            <div class="stat-icon" style="background:#f3e8ff;color:#7c3aed;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $formatTime($totalPlanned) ?></span>
                <span class="stat-label">Break Planificado</span>
            </div>
        </div>
        <div class="stat-mini accent-orange">
            <div class="stat-icon" style="background:#fef3c7;color:#d97706;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $formatTime($totalActual) ?></span>
                <span class="stat-label">Break Real</span>
            </div>
        </div>
        <div class="stat-mini <?= $totalExceso > 0 ? 'accent-red' : 'accent-green' ?>">
            <div class="stat-icon" style="background:<?= $totalExceso > 0 ? '#fee2e2' : '#dcfce7' ?>;color:<?= $totalExceso > 0 ? '#dc2626' : '#16a34a' ?>;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;">
                    <?php if ($totalExceso > 0): ?>
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    <?php else: ?>
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    <?php endif; ?>
                </svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $formatTime($totalExceso) ?></span>
                <span class="stat-label">Exceso Total</span>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="data-panel">
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                Detalle por Asesor &mdash; <?= $monthNames[$selectedMonth] ?> <?= $selectedYear ?>
                <span class="panel-counter"><?= $totalAsesores ?></span>
            </div>
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="searchInput" placeholder="Buscar asesor...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="breaksTable">
                <thead>
                    <tr>
                        <th>Asesor</th>
                        <th>Cedula</th>
                        <th class="text-center">Break Plan.</th>
                        <th class="text-center">Break Real</th>
                        <th class="text-center">Exceso</th>
                        <th class="text-center">Horas Trab.</th>
                        <th class="text-center">Dias</th>
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sumPlanned = 0;
                    $sumActual = 0;
                    $sumExceso = 0;
                    $sumHoras = 0;
                    $sumDias = 0;
                    foreach ($cruceData as $i => $row):
                        $breakPlan = ($row['planned_slots'] ?? 0) * $duracionBreakMin;
                        $breakReal = $row['actual_minutes'] ?? 0;
                        $exceso = $row['exceso_excel'] ?? 0;
                        $horasTrab = $row['horas_trabajadas'] ?? 0;
                        $dias = $row['dias_con_datos'] ?? 0;
                        $sumPlanned += $breakPlan;
                        $sumActual += $breakReal;
                        $sumExceso += $exceso;
                        $sumHoras += $horasTrab;
                        $sumDias += $dias;

                        // Status badge
                        if ($exceso <= 0) {
                            $badgeClass = 'badge-success';
                            $badgeText = 'OK';
                        } elseif ($exceso <= 5) {
                            $badgeClass = 'badge-warning';
                            $badgeText = 'Leve';
                        } else {
                            $badgeClass = 'badge-danger';
                            $badgeText = 'Exceso';
                        }
                    ?>
                    <tr class="table-row-animated" style="animation-delay: <?= $i * 0.02 ?>s">
                        <td>
                            <div class="cell-flex">
                                <?php
                                $avatarColors = ['#2563eb','#7c3aed','#0891b2','#059669','#d97706','#dc2626','#be185d'];
                                $avatarColor = $avatarColors[($row['id'] ?? $i) % count($avatarColors)];
                                $initials = strtoupper(substr($row['nombres'] ?? '', 0, 1) . substr($row['apellidos'] ?? '', 0, 1));
                                ?>
                                <div class="avatar" style="background: <?= $avatarColor ?>;"><?= $initials ?></div>
                                <div class="cell-stack">
                                    <span class="cell-main"><?= htmlspecialchars(($row['apellidos'] ?? '') . ', ' . ($row['nombres'] ?? '')) ?></span>
                                </div>
                            </div>
                        </td>
                        <td><span class="cell-mono"><?= htmlspecialchars($row['cedula'] ?? '-') ?></span></td>
                        <td class="text-center"><?= $breakPlan ?> min</td>
                        <td class="text-center"><?= $breakReal ?> min</td>
                        <td class="text-center" style="font-weight:600;color:<?= $exceso > 0 ? 'var(--corp-danger)' : 'var(--corp-success)' ?>;">
                            <?= $exceso > 0 ? '+' : '' ?><?= $exceso ?> min
                        </td>
                        <td class="text-center"><?= number_format($horasTrab, 1) ?>h</td>
                        <td class="text-center"><?= $dias ?></td>
                        <td class="text-center">
                            <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td style="font-weight:700;">Total</td>
                        <td></td>
                        <td class="text-center" style="font-weight:700;"><?= $sumPlanned ?> min</td>
                        <td class="text-center" style="font-weight:700;"><?= $sumActual ?> min</td>
                        <td class="text-center" style="font-weight:700;color:<?= $sumExceso > 0 ? 'var(--corp-danger)' : 'var(--corp-success)' ?>;">
                            <?= $sumExceso > 0 ? '+' : '' ?><?= $sumExceso ?> min
                        </td>
                        <td class="text-center" style="font-weight:700;"><?= number_format($sumHoras, 1) ?>h</td>
                        <td class="text-center" style="font-weight:700;"><?= $sumDias ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="table-footer" id="tableFooter">
            <span class="table-footer-info" id="footerInfo">
                Mostrando <strong><?= $totalAsesores ?></strong> asesores
            </span>
            <div class="tf-pagination" id="pagination"></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .data-table tfoot td {
        background: var(--corp-gray-50);
        border-top: 2px solid var(--corp-gray-200);
    }

    [data-theme="dark"] .data-table tfoot td {
        background: var(--corp-gray-100);
    }
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search filter
    var searchInput = document.getElementById('searchInput');
    var table = document.getElementById('breaksTable');
    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            var filter = this.value.toLowerCase();
            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                var cells = row.querySelectorAll('td');
                var text = '';
                if (cells.length >= 2) {
                    text = cells[0].textContent.toLowerCase() + ' ' + cells[1].textContent.toLowerCase();
                }
                row.style.display = text.includes(filter) ? '' : 'none';
            });
            // Re-trigger pagination after filter
            if (typeof window._breaksPaginator !== 'undefined') {
                window._breaksPaginator.reset();
            }
        });
    }

    // Client-side pagination
    if (typeof TablePaginator === 'function' && table) {
        window._breaksPaginator = new TablePaginator({
            tableId: 'breaksTable',
            footerId: 'tableFooter',
            paginationId: 'pagination',
            infoId: 'footerInfo',
            perPage: 25
        });
    }
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
