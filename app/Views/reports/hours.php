<?php
/**
 * TurnoFlow - Reporte de Horas por Asesor
 */

$currentPage = 'reports';
$monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

// Day of week headers
$dayNames = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

ob_start();
?>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title"><?= htmlspecialchars($campaign['nombre']) ?></h1>
            <p class="page-header-subtitle">Reporte de horas trabajadas — <?= $monthNames[$month] ?> <?= $year ?></p>
        </div>
        <a href="<?= BASE_URL ?>/reports" class="btn btn-secondary">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Volver
        </a>
    </div>

    <!-- Period selector -->
    <?php if (!empty($availablePeriods) && count($availablePeriods) > 1): ?>
    <div class="stats-row" style="margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 12px; width: 100%;">
            <label style="font-weight: 600; font-size: 0.85rem; color: var(--corp-gray-600);">Período:</label>
            <select id="periodSelector" class="form-select" style="max-width: 220px;">
                <?php foreach ($availablePeriods as $p): ?>
                <option value="<?= $p['periodo_anio'] ?>-<?= $p['periodo_mes'] ?>"
                    <?= ($p['periodo_anio'] == $year && $p['periodo_mes'] == $month) ? 'selected' : '' ?>>
                    <?= $monthNames[(int)$p['periodo_mes']] ?> <?= $p['periodo_anio'] ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <?php
    $totalAdvisors = count(array_filter($reportData, fn($r) => $r['tipo'] === 'propio'));
    $sharedAdvisors = count(array_filter($reportData, fn($r) => $r['tipo'] === 'compartido'));
    $totalHours = array_sum(array_column($reportData, 'total'));
    $ownData = array_filter($reportData, fn($r) => $r['tipo'] === 'propio' && $r['compliance'] !== null);
    $avgCompliance = !empty($ownData)
        ? round(array_sum(array_column($ownData, 'compliance')) / count($ownData), 1)
        : 0;
    ?>
    <div class="stats-row">
        <div class="stat-mini accent-blue">
            <span class="stat-value"><?= $totalAdvisors ?></span>
            <span class="stat-label">Asesores</span>
        </div>
        <?php if ($sharedAdvisors > 0): ?>
        <div class="stat-mini accent-purple" style="border-left-color: var(--corp-purple);">
            <span class="stat-value"><?= $sharedAdvisors ?></span>
            <span class="stat-label">Compartidos</span>
        </div>
        <?php endif; ?>
        <div class="stat-mini accent-green">
            <span class="stat-value"><?= number_format($totalHours) ?></span>
            <span class="stat-label">Horas Totales</span>
        </div>
        <div class="stat-mini">
            <span class="stat-value"><?= $monthlyTarget ?>h</span>
            <span class="stat-label">Meta/Asesor</span>
        </div>
        <div class="stat-mini <?= $avgCompliance >= 95 ? 'accent-green' : ($avgCompliance >= 80 ? 'accent-orange' : 'accent-red') ?>">
            <span class="stat-value"><?= $avgCompliance ?>%</span>
            <span class="stat-label">Cumplimiento</span>
        </div>
    </div>

    <!-- Report Table -->
    <div class="data-panel">
        <?php if (empty($reportData)): ?>
        <div class="empty-state">
            <h5>Sin datos</h5>
            <p>No hay asignaciones para este período.</p>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                Horas por Asesor — <?= $monthNames[$month] ?> <?= $year ?>
            </div>
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="searchInput" placeholder="Buscar asesor...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table report-hours-table" id="reportTable">
                <thead>
                    <tr>
                        <th class="sticky-col" style="min-width: 180px;">Asesor</th>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++):
                            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                            $dow = (int)date('N', strtotime($dateStr)); // 1=Mon, 7=Sun
                            $isWeekend = $dow >= 6;
                        ?>
                        <th class="day-col <?= $isWeekend ? 'weekend' : '' ?>">
                            <span class="day-name"><?= $dayNames[$dow - 1] ?></span>
                            <span class="day-num"><?= $d ?></span>
                        </th>
                        <?php endfor; ?>
                        <th class="total-col">Total</th>
                        <th class="total-col">Meta</th>
                        <th class="total-col">Cumpl.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $row): ?>
                    <tr class="<?= $row['tipo'] === 'compartido' ? 'shared-row' : '' ?>">
                        <td class="sticky-col advisor-name">
                            <span><?= htmlspecialchars($row['nombre']) ?></span>
                            <?php if ($row['tipo'] === 'compartido'): ?>
                            <span class="badge-shared">(P)</span>
                            <?php endif; ?>
                        </td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++):
                            $h = $row['daily'][$d] ?? 0;
                            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                            $dow = (int)date('N', strtotime($dateStr));
                            $isWeekend = $dow >= 6;
                            $cellClass = '';
                            if ($h === 0) $cellClass = 'cell-zero';
                            elseif ($h >= 8) $cellClass = 'cell-full';
                            elseif ($h >= 4) $cellClass = 'cell-mid';
                            else $cellClass = 'cell-low';
                        ?>
                        <td class="day-col <?= $isWeekend ? 'weekend' : '' ?> <?= $cellClass ?>">
                            <?= $h > 0 ? $h : '' ?>
                        </td>
                        <?php endfor; ?>
                        <td class="total-col total-hours"><?= $row['total'] ?></td>
                        <td class="total-col"><?= $row['target'] !== null ? $row['target'] : '-' ?></td>
                        <td class="total-col compliance-cell <?= $row['compliance'] !== null ? ($row['compliance'] >= 95 ? 'comp-ok' : ($row['compliance'] >= 80 ? 'comp-warn' : 'comp-low')) : '' ?>">
                            <?= $row['compliance'] !== null ? $row['compliance'] . '%' : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="sticky-col" style="font-weight: 700;">Total</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++):
                            $dayTotal = 0;
                            foreach ($reportData as $row) $dayTotal += ($row['daily'][$d] ?? 0);
                            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                            $dow = (int)date('N', strtotime($dateStr));
                            $isWeekend = $dow >= 6;
                        ?>
                        <td class="day-col <?= $isWeekend ? 'weekend' : '' ?>" style="font-weight: 700;"><?= $dayTotal > 0 ? $dayTotal : '' ?></td>
                        <?php endfor; ?>
                        <td class="total-col" style="font-weight: 700;"><?= $totalHours ?></td>
                        <td class="total-col" style="font-weight: 700;"><?= $monthlyTarget * $totalAdvisors ?></td>
                        <td class="total-col compliance-cell <?= $avgCompliance >= 95 ? 'comp-ok' : ($avgCompliance >= 80 ? 'comp-warn' : 'comp-low') ?>" style="font-weight: 700;"><?= $avgCompliance ?>%</td>
                    </tr>
                </tfoot>
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
    .report-hours-table {
        font-size: 0.78rem;
    }

    .report-hours-table th,
    .report-hours-table td {
        padding: 6px 4px;
        text-align: center;
        white-space: nowrap;
    }

    .report-hours-table .sticky-col {
        position: sticky;
        left: 0;
        background: #fff;
        z-index: 2;
        text-align: left;
        padding-left: 12px;
        min-width: 180px;
        border-right: 2px solid var(--corp-gray-200);
    }

    thead .sticky-col {
        background: var(--corp-gray-50) !important;
        z-index: 3;
    }

    tfoot .sticky-col {
        background: var(--corp-gray-50) !important;
    }

    .advisor-name {
        font-weight: 500;
        color: var(--corp-gray-800);
    }

    .badge-shared {
        font-size: 0.65rem;
        font-weight: 700;
        color: var(--corp-purple);
        margin-left: 4px;
    }

    .shared-row {
        background: rgba(124, 58, 237, 0.03) !important;
    }

    .day-col {
        min-width: 32px;
        max-width: 36px;
    }

    .day-col.weekend {
        background: rgba(0, 0, 0, 0.02);
    }

    .day-name {
        display: block;
        font-size: 0.65rem;
        color: var(--corp-gray-400);
        font-weight: 400;
    }

    .day-num {
        display: block;
        font-weight: 600;
    }

    .cell-zero {
        color: var(--corp-gray-300);
    }

    .cell-full {
        background: rgba(5, 150, 105, 0.08);
        color: var(--corp-success);
        font-weight: 600;
    }

    .cell-mid {
        background: rgba(217, 119, 6, 0.06);
        color: var(--corp-warning);
        font-weight: 500;
    }

    .cell-low {
        color: var(--corp-gray-500);
    }

    .total-col {
        min-width: 50px;
        border-left: 2px solid var(--corp-gray-200);
        font-weight: 600;
    }

    .total-hours {
        color: var(--corp-primary);
    }

    .compliance-cell.comp-ok {
        color: var(--corp-success);
        font-weight: 700;
    }

    .compliance-cell.comp-warn {
        color: var(--corp-warning);
        font-weight: 700;
    }

    .compliance-cell.comp-low {
        color: var(--corp-danger);
        font-weight: 700;
    }

    .report-hours-table tfoot td {
        background: var(--corp-gray-50);
        border-top: 2px solid var(--corp-gray-200);
    }

    .form-select {
        padding: 6px 12px;
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--input-radius);
        font-size: 0.85rem;
        font-family: inherit;
        background: #fff;
        cursor: pointer;
    }

    .form-select:focus {
        outline: none;
        border-color: var(--corp-primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: #fff;
        color: var(--corp-gray-700);
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--input-radius);
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.15s;
    }

    .btn-secondary:hover {
        background: var(--corp-gray-50);
        border-color: var(--corp-gray-300);
    }

    .btn-secondary svg {
        width: 16px;
        height: 16px;
    }
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search
    var searchInput = document.getElementById('searchInput');
    var table = document.getElementById('reportTable');
    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            var filter = this.value.toLowerCase();
            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                var name = row.querySelector('.advisor-name');
                if (name) {
                    row.style.display = name.textContent.toLowerCase().includes(filter) ? '' : 'none';
                }
            });
        });
    }

    // Period selector
    var periodSelector = document.getElementById('periodSelector');
    if (periodSelector) {
        periodSelector.addEventListener('change', function() {
            var parts = this.value.split('-');
            var url = window.location.pathname + '?year=' + parts[0] + '&month=' + parts[1];
            window.location.href = url;
        });
    }
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
