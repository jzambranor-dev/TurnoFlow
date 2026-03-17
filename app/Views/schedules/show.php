<?php
/**
 * TurnoFlow - Detalle de horario mensual / diario
 * Vista mejorada con formato DESDE-HASTA para asesores
 */

$pageTitle = 'Ver Horario';
$currentPage = 'schedules';

$viewMode = strtolower((string)($_GET['view'] ?? 'monthly'));
$viewMode = in_array($viewMode, ['daily', 'advisor', 'edit']) ? $viewMode : 'monthly';

// Edicion libre en borrador/rechazado, edicion restringida (hoy+futuro) en aprobado
$canEditFull = in_array($schedule['status'], ['borrador', 'rechazado'], true);
$canEditApproved = $schedule['status'] === 'aprobado';
$canEdit = $canEditFull || $canEditApproved;

/**
 * Convierte array de horas [9,10,11,14,15,16] en bloques "09:00-12:00, 14:00-17:00"
 * Si se pasan tipos, marca las horas de break con (B)
 */
function hoursToBlocks(array $hours, array $types = []): string {
    if (empty($hours)) return '';
    sort($hours);

    $blocks = [];
    $start = $hours[0];
    $prev = $hours[0];
    $hasBreakInBlock = false;

    for ($i = 1; $i < count($hours); $i++) {
        if (($types[$prev] ?? '') === 'break') $hasBreakInBlock = true;
        if ($hours[$i] !== $prev + 1) {
            $label = sprintf('%02d:00-%02d:00', $start, $prev + 1);
            if ($hasBreakInBlock) $label .= ' <span style="color:#f59e0b;font-size:0.75em;">(B)</span>';
            $blocks[] = $label;
            $start = $hours[$i];
            $hasBreakInBlock = false;
        }
        $prev = $hours[$i];
    }
    if (($types[$prev] ?? '') === 'break') $hasBreakInBlock = true;
    $label = sprintf('%02d:00-%02d:00', $start, $prev + 1);
    if ($hasBreakInBlock) $label .= ' <span style="color:#f59e0b;font-size:0.75em;">(B)</span>';
    $blocks[] = $label;

    return implode(', ', $blocks);
}

$statusConfig = [
    'borrador' => ['bg' => '#f1f5f9', 'color' => '#64748b', 'label' => 'Borrador'],
    'enviado' => ['bg' => '#dbeafe', 'color' => '#2563eb', 'label' => 'Enviado'],
    'aprobado' => ['bg' => '#dcfce7', 'color' => '#15803d', 'label' => 'Aprobado'],
    'rechazado' => ['bg' => '#fee2e2', 'color' => '#b91c1c', 'label' => 'Rechazado'],
];
$statusInfo = $statusConfig[$schedule['status']] ?? $statusConfig['borrador'];

$startDate = new DateTimeImmutable((string)$schedule['fecha_inicio']);
$endDate = new DateTimeImmutable((string)$schedule['fecha_fin']);
$dates = [];
for ($cursor = $startDate; $cursor <= $endDate; $cursor = $cursor->modify('+1 day')) {
    $dates[] = $cursor->format('Y-m-d');
}

$hours = range(0, 23);
$weekDaysShort = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
$weekDaysLong = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
$monthsLong = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre',
];

// Mapear actividades por asesor: [advisor_id] => [{nombre, color, hora_inicio, hora_fin, dias_semana}]
$advisorActivities = [];
foreach (($activityAssignments ?? []) as $aa) {
    $advId = (int)$aa['advisor_id'];
    $diasRaw = trim($aa['dias_semana'] ?? '{}', '{}');
    $dias = !empty($diasRaw) ? array_map('intval', explode(',', $diasRaw)) : [];
    $advisorActivities[$advId][] = [
        'nombre' => $aa['activity_nombre'],
        'color' => $aa['activity_color'] ?? '#2563eb',
        'hora_inicio' => (int)$aa['hora_inicio'],
        'hora_fin' => (int)$aa['hora_fin'],
        'dias_semana' => $dias,
    ];
}

/**
 * Obtiene la actividad de un asesor para una hora y fecha específica
 */
function getActivityForHour(int $advisorId, int $hour, string $date, array $advisorActivities): ?array {
    if (empty($advisorActivities[$advisorId])) return null;
    $dow = (int)date('N', strtotime($date)) - 1; // 0=Lun, 6=Dom
    foreach ($advisorActivities[$advisorId] as $act) {
        if (in_array($dow, $act['dias_semana'], true) && $hour >= $act['hora_inicio'] && $hour < $act['hora_fin']) {
            return $act;
        }
    }
    return null;
}

$sharedAdvisorIdsList = array_map('intval', $sharedAdvisorIds ?? []);
$crossCampaignHoursMap = $crossCampaignHours ?? [];

$advisorsMap = [];
foreach ($campaignAdvisors as $advisor) {
    $advisorId = (int)$advisor['id'];
    $advisorsMap[$advisorId] = [
        'id' => $advisorId,
        'name' => trim((string)$advisor['apellidos'] . ' ' . (string)$advisor['nombres']),
        'is_shared' => in_array($advisorId, $sharedAdvisorIdsList, true),
    ];
}

foreach ($assignments as $assignment) {
    $advisorId = (int)$assignment['advisor_id'];
    if (!isset($advisorsMap[$advisorId])) {
        $advisorsMap[$advisorId] = [
            'id' => $advisorId,
            'name' => trim((string)$assignment['apellidos'] . ' ' . (string)$assignment['nombres']),
            'is_shared' => in_array($advisorId, $sharedAdvisorIdsList, true),
        ];
    }
}

uasort($advisorsMap, static function (array $a, array $b): int {
    return strcasecmp($a['name'], $b['name']);
});

$advisorIds = array_keys($advisorsMap);
$advisorMonthHours = [];
$advisorFreeDays = [];
foreach ($advisorIds as $advisorId) {
    $advisorMonthHours[$advisorId] = 0;
    $advisorFreeDays[$advisorId] = 0;
}

$assignmentsByDateAdvisor = [];
$assignmentTypeByDateAdvisorHour = [];
$coverageByDateHour = [];

foreach ($assignments as $assignment) {
    $date = (string)$assignment['fecha'];
    $advisorId = (int)$assignment['advisor_id'];
    $hour = (int)$assignment['hora'];

    if (!isset($assignmentsByDateAdvisor[$date])) {
        $assignmentsByDateAdvisor[$date] = [];
    }
    if (!isset($assignmentsByDateAdvisor[$date][$advisorId])) {
        $assignmentsByDateAdvisor[$date][$advisorId] = [];
    }
    $assignmentsByDateAdvisor[$date][$advisorId][] = $hour;

    $tipo = (string)($assignment['tipo'] ?? 'normal');
    $assignmentTypeByDateAdvisorHour[$date][$advisorId][$hour] = $tipo;
    $coverageByDateHour[$date][$hour] = ($coverageByDateHour[$date][$hour] ?? 0) + 1;
    // Breaks cuentan como 0.5h, el resto como 1h
    $advisorMonthHours[$advisorId] = ($advisorMonthHours[$advisorId] ?? 0) + ($tipo === 'break' ? 0.5 : 1);
}

foreach ($assignmentsByDateAdvisor as $date => $advisorRows) {
    foreach ($advisorRows as $advisorId => $assignedHours) {
        sort($assignedHours);
        $assignmentsByDateAdvisor[$date][$advisorId] = $assignedHours;
    }
}

$requirementsByDateHour = [];
foreach ($requirements as $requirement) {
    $date = (string)$requirement['fecha'];
    $hour = (int)$requirement['hora'];
    $required = (int)$requirement['asesores_requeridos'];
    $requirementsByDateHour[$date][$hour] = $required;
}

foreach ($advisorIds as $advisorId) {
    foreach ($dates as $date) {
        if (empty($assignmentsByDateAdvisor[$date][$advisorId])) {
            $advisorFreeDays[$advisorId]++;
        }
    }
}

// Si no se especifica fecha, usar hoy si esta dentro del rango, sino el primer dia
$todayStr = date('Y-m-d');
$defaultDate = in_array($todayStr, $dates, true) ? $todayStr : ($dates[0] ?? '');
$selectedDate = (string)($_GET['date'] ?? $defaultDate);
if ($selectedDate === '' || !in_array($selectedDate, $dates, true)) {
    $selectedDate = $dates[0] ?? '';
}

$selectedDateIndex = array_search($selectedDate, $dates, true);
if ($selectedDateIndex === false) {
    $selectedDateIndex = 0;
}
$prevDate = $selectedDateIndex > 0 ? $dates[$selectedDateIndex - 1] : null;
$nextDate = $selectedDateIndex < (count($dates) - 1) ? $dates[$selectedDateIndex + 1] : null;

$selectedDailyAssignments = $selectedDate !== '' ? ($assignmentsByDateAdvisor[$selectedDate] ?? []) : [];
$selectedDailyRequirements = $selectedDate !== '' ? ($requirementsByDateHour[$selectedDate] ?? []) : [];
$selectedDailyCoverage = $selectedDate !== '' ? ($coverageByDateHour[$selectedDate] ?? []) : [];

$totalRequiredDay = 0;
$totalCoverageDay = 0;
foreach ($hours as $hour) {
    $totalRequiredDay += (int)($selectedDailyRequirements[$hour] ?? 0);
    $totalCoverageDay += (int)($selectedDailyCoverage[$hour] ?? 0);
}

$totalFreeSlots = array_sum($advisorFreeDays);
$totalAdvisors = count($advisorIds);
$totalAssignments = count($assignments);

// --- Calculo de cobertura global del dimensionamiento ---
$totalRequiredAll = 0;
$totalCoverageAll = 0;
$deficitHoursCount = 0;   // franjas con deficit
$surplusHoursCount = 0;   // franjas con superavit
$perfectHoursCount = 0;   // franjas perfectas
$totalDeficitSum = 0;     // suma total de asesor-hora faltantes
$totalSurplusSum = 0;     // suma total de asesor-hora sobrantes
$deficitByDate = [];       // deficit por dia para detalle

foreach ($dates as $date) {
    $dayDeficit = 0;
    $dayRequired = 0;
    $dayCoverage = 0;
    foreach ($hours as $hour) {
        $req = (int)($requirementsByDateHour[$date][$hour] ?? 0);
        $cov = (int)($coverageByDateHour[$date][$hour] ?? 0);
        $totalRequiredAll += $req;
        $totalCoverageAll += $cov;
        $gap = $cov - $req;
        if ($gap < 0) {
            $deficitHoursCount++;
            $totalDeficitSum += abs($gap);
            $dayDeficit += abs($gap);
        } elseif ($gap > 0) {
            $surplusHoursCount++;
            $totalSurplusSum += $gap;
        } else {
            $perfectHoursCount++;
        }
        $dayRequired += $req;
        $dayCoverage += $cov;
    }
    $deficitByDate[$date] = [
        'required' => $dayRequired,
        'coverage' => $dayCoverage,
        'deficit' => max(0, $dayRequired - $dayCoverage),
    ];
}
$coveragePercent = $totalRequiredAll > 0 ? round(($totalCoverageAll / $totalRequiredAll) * 100, 1) : 100;
$isFullyCovered = $totalDeficitSum === 0;
$totalHourSlots = count($dates) * 24;
$daysWithDeficit = 0;
foreach ($deficitByDate as $dd) {
    if ($dd['deficit'] > 0) $daysWithDeficit++;
}

$selectedDateLabel = '';
if ($selectedDate !== '') {
    $selectedTimestamp = strtotime($selectedDate);
    $selectedDateLabel = sprintf(
        '%s %s de %s %s',
        $weekDaysLong[(int)date('w', $selectedTimestamp)],
        date('d', $selectedTimestamp),
        $monthsLong[(int)date('n', $selectedTimestamp)],
        date('Y', $selectedTimestamp)
    );
}

$monthlyLink = BASE_URL . '/schedules/' . $schedule['id'] . '?view=monthly';
$dailyLink = BASE_URL . '/schedules/' . $schedule['id'] . '?view=daily&date=' . urlencode($selectedDate !== '' ? $selectedDate : ($dates[0] ?? ''));
$advisorLink = BASE_URL . '/schedules/' . $schedule['id'] . '?view=advisor';
$editLink = BASE_URL . '/schedules/' . $schedule['id'] . '?view=edit&date=' . urlencode($selectedDate !== '' ? $selectedDate : ($dates[0] ?? ''));

ob_start();
?>

<?php
// Alertas de cobertura
$scheduleAlertsSummary = $_SESSION['schedule_alerts_summary'] ?? null;
$scheduleAlerts = $_SESSION['schedule_alerts'] ?? [];
unset($_SESSION['schedule_alerts_summary'], $_SESSION['schedule_alerts']);
?>

<div class="schedule-detail">
    <?php if ($scheduleAlertsSummary): ?>
    <div class="coverage-alert">
        <div class="alert-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
        </div>
        <div class="alert-content">
            <strong>Deficit de Cobertura</strong>
            <p><?= htmlspecialchars($scheduleAlertsSummary) ?></p>
            <?php if (count($scheduleAlerts) <= 10): ?>
            <details>
                <summary>Ver detalle (<?= count($scheduleAlerts) ?> franjas)</summary>
                <ul class="alert-list">
                    <?php foreach ($scheduleAlerts as $alert): ?>
                    <li><?= htmlspecialchars($alert['fecha']) ?> <?= sprintf('%02d:00', $alert['hora']) ?>: necesarios <?= $alert['requeridos'] ?>, asignados <?= $alert['asignados'] ?> (faltan <?= $alert['deficit'] ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="detail-header">
        <div class="header-row">
            <div class="form-breadcrumb">
                <a href="<?= BASE_URL ?>/schedules" style="color:#2563eb;text-decoration:none;font-weight:500;">Horarios</a>
                <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:#94a3b8;"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                <span><?= htmlspecialchars((string)$schedule['campaign_nombre']) ?></span>
            </div>
            <div class="header-actions">
                <?php if ($schedule['status'] === 'borrador'): ?>
                <form method="POST" action="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/submit" style="display:inline;">
                    <?= \App\Services\CsrfService::field() ?>
                    <button type="submit" class="btn-solid btn-send">
                        <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                        Enviar a aprobacion
                    </button>
                </form>
                <?php endif; ?>

                <?php if (in_array($_SESSION['user']['rol'] ?? '', ['coordinador', 'admin', 'gerente'], true) && $schedule['status'] === 'enviado'): ?>
                <form method="POST" action="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/approve" style="display:inline;">
                    <?= \App\Services\CsrfService::field() ?>
                    <button type="submit" class="btn-solid btn-ok">
                        <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        Aprobar
                    </button>
                </form>
                <form method="POST" action="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/reject" style="display:inline;">
                    <?= \App\Services\CsrfService::field() ?>
                    <button type="submit" class="btn-solid btn-bad" onclick="return confirm('Rechazar este horario?')">
                        <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                        Rechazar
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($schedule['status'] === 'aprobado'): ?>
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/tracking" class="btn-solid btn-tracking">
                    <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    Seguimiento Diario
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="title-row">
            <div>
                <span class="status-pill" style="background: <?= $statusInfo['bg'] ?>; color: <?= $statusInfo['color'] ?>;">
                    <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?= $statusInfo['color'] ?>;margin-right:4px;"></span>
                    <?= htmlspecialchars($statusInfo['label']) ?>
                </span>
                <h1><?= htmlspecialchars((string)$schedule['campaign_nombre']) ?></h1>
                <p>
                    <svg viewBox="0 0 24 24" fill="currentColor" style="width:15px;height:15px;vertical-align:-3px;fill:#94a3b8;margin-right:3px;"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                    Periodo <?= str_pad((string)$schedule['periodo_mes'], 2, '0', STR_PAD_LEFT) ?>/<?= htmlspecialchars((string)$schedule['periodo_anio']) ?>
                    &nbsp;|&nbsp; <?= htmlspecialchars((string)$schedule['fecha_inicio']) ?> al <?= htmlspecialchars((string)$schedule['fecha_fin']) ?>
                    <span style="color:#94a3b8;margin-left:4px;">(<?= count($dates) ?> dias)</span>
                </p>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-icon-box" style="background:#eff6ff;">
                <svg viewBox="0 0 24 24" style="fill:#2563eb;width:20px;height:20px;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <div>
                <span class="stat-value"><?= $totalAdvisors ?></span>
                <span class="stat-title">Asesores</span>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon-box" style="background:#dcfce7;">
                <svg viewBox="0 0 24 24" style="fill:#16a34a;width:20px;height:20px;"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
            </div>
            <div>
                <span class="stat-value"><?= number_format($totalAssignments) ?></span>
                <span class="stat-title">Asignaciones</span>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon-box" style="background:#fef3c7;">
                <svg viewBox="0 0 24 24" style="fill:#d97706;width:20px;height:20px;"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
            </div>
            <div>
                <span class="stat-value"><?= $totalFreeSlots ?></span>
                <span class="stat-title">Dias libres</span>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon-box" style="background:<?= $statusInfo['bg'] ?>;">
                <svg viewBox="0 0 24 24" style="fill:<?= $statusInfo['color'] ?>;width:20px;height:20px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            </div>
            <div>
                <span class="stat-value"><?= htmlspecialchars($statusInfo['label']) ?></span>
                <span class="stat-title">Estado</span>
            </div>
        </div>
    </div>

    <!-- Panel de Cobertura del Dimensionamiento -->
    <div class="coverage-panel <?= $isFullyCovered ? 'coverage-complete' : 'coverage-deficit' ?>">
        <div class="coverage-header">
            <div class="coverage-header-left">
                <?php if ($isFullyCovered): ?>
                <div class="coverage-icon coverage-icon-ok">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <div>
                    <h3 class="coverage-title">Dimensionamiento Completo</h3>
                    <p class="coverage-subtitle">Todas las franjas horarias estan cubiertas segun el requerimiento</p>
                </div>
                <?php else: ?>
                <div class="coverage-icon coverage-icon-warn">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                </div>
                <div>
                    <h3 class="coverage-title">Dimensionamiento Incompleto</h3>
                    <p class="coverage-subtitle">Faltan <strong><?= number_format($totalDeficitSum) ?></strong> asesor-hora por cubrir en <strong><?= $deficitHoursCount ?></strong> franjas (<?= $daysWithDeficit ?> dias)</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="coverage-percent-badge <?= $isFullyCovered ? 'percent-ok' : ($coveragePercent >= 90 ? 'percent-warn' : 'percent-bad') ?>">
                <?= $coveragePercent ?>%
            </div>
        </div>

        <div class="coverage-metrics">
            <div class="coverage-metric">
                <span class="metric-label">Requerido</span>
                <span class="metric-value"><?= number_format($totalRequiredAll) ?></span>
                <span class="metric-unit">asesor-hora</span>
            </div>
            <div class="coverage-metric">
                <span class="metric-label">Asignado</span>
                <span class="metric-value"><?= number_format($totalCoverageAll) ?></span>
                <span class="metric-unit">asesor-hora</span>
            </div>
            <div class="coverage-metric">
                <span class="metric-label">Diferencia</span>
                <span class="metric-value <?= $totalCoverageAll - $totalRequiredAll < 0 ? 'metric-neg' : 'metric-pos' ?>"><?= ($totalCoverageAll - $totalRequiredAll >= 0 ? '+' : '') . number_format($totalCoverageAll - $totalRequiredAll) ?></span>
                <span class="metric-unit">asesor-hora</span>
            </div>
            <div class="coverage-metric">
                <span class="metric-label">Franjas OK</span>
                <span class="metric-value"><?= $perfectHoursCount + $surplusHoursCount ?></span>
                <span class="metric-unit">de <?= $totalHourSlots ?></span>
            </div>
        </div>

        <?php if (!$isFullyCovered): ?>
        <div class="coverage-progress-wrap">
            <div class="coverage-progress-bar">
                <div class="coverage-progress-fill" style="width: <?= min($coveragePercent, 100) ?>%;"></div>
            </div>
            <span class="coverage-progress-label"><?= $coveragePercent ?>% cubierto</span>
        </div>

        <?php if ($daysWithDeficit <= 15): ?>
        <details class="coverage-details">
            <summary>Ver detalle por dia (<?= $daysWithDeficit ?> dias con deficit)</summary>
            <div class="coverage-detail-grid">
                <?php foreach ($deficitByDate as $date => $dd): ?>
                <?php if ($dd['deficit'] > 0): ?>
                <?php
                    $stamp = strtotime($date);
                    $dayPct = $dd['required'] > 0 ? round(($dd['coverage'] / $dd['required']) * 100) : 100;
                ?>
                <div class="coverage-detail-row">
                    <span class="detail-date"><?= $weekDaysShort[(int)date('w', $stamp)] ?> <?= date('d/m', $stamp) ?></span>
                    <div class="detail-bar-wrap">
                        <div class="detail-bar">
                            <div class="detail-bar-fill" style="width:<?= min($dayPct, 100) ?>%;"></div>
                        </div>
                    </div>
                    <span class="detail-nums"><?= $dd['coverage'] ?>/<?= $dd['required'] ?></span>
                    <span class="detail-deficit">-<?= $dd['deficit'] ?></span>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="mode-switch">
        <a href="<?= $monthlyLink ?>" class="switch-link <?= $viewMode === 'monthly' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:15px;height:15px;"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
            Resumen Mensual
        </a>
        <a href="<?= $advisorLink ?>" class="switch-link <?= $viewMode === 'advisor' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:15px;height:15px;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            Horario por Asesor
        </a>
        <a href="<?= $dailyLink ?>" class="switch-link <?= $viewMode === 'daily' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:15px;height:15px;"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
            Matriz Diaria
        </a>
        <?php if ($canEdit): ?>
        <a href="<?= $editLink ?>" class="switch-link switch-edit <?= $viewMode === 'edit' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;margin-right:4px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
            Editar Horario
        </a>
        <?php endif; ?>
    </div>

    <?php if (empty($advisorsMap)): ?>
    <div class="empty-box">
        No hay asesores activos en esta campaña para mostrar el horario.
    </div>
    <?php elseif ($viewMode === 'advisor'): ?>
    <!-- VISTA POR ASESOR - Formato que entienden los asesores -->
    <div class="panel">
        <div class="panel-head">
            <h2>Horario Detallado por Asesor</h2>
            <span class="legend-note">Formato: Entrada - Salida</span>
        </div>
        <div class="advisor-schedule-list">
            <?php foreach ($advisorsMap as $advisor): ?>
            <?php $advisorId = (int)$advisor['id']; ?>
            <div class="advisor-card">
                <div class="advisor-card-header">
                    <div class="advisor-info">
                        <span class="advisor-avatar"<?= !empty($advisor['is_shared']) ? ' style="background:#ede9fe;color:#7c3aed;"' : '' ?>><?= strtoupper(substr($advisor['name'], 0, 2)) ?></span>
                        <div>
                            <h3><?= htmlspecialchars($advisor['name']) ?><?= !empty($advisor['is_shared']) ? ' <span style="color:#7c3aed;font-size:0.8em;font-weight:normal;">(P)</span>' : '' ?></h3>
                            <span class="advisor-stats">
                                <?= (int)($advisorMonthHours[$advisorId] ?? 0) ?>h programadas |
                                <?= (int)($advisorFreeDays[$advisorId] ?? 0) ?> dias libres
                            </span>
                        </div>
                    </div>
                </div>
                <div class="advisor-schedule-grid">
                    <?php foreach ($dates as $date): ?>
                    <?php
                        $dayHours = $assignmentsByDateAdvisor[$date][$advisorId] ?? [];
                        $weekday = (int)date('w', strtotime($date));
                        $dayNum = date('d', strtotime($date));
                        $isWeekend = in_array($weekday, [0, 6], true);
                    ?>
                    <div class="schedule-day <?= $isWeekend ? 'is-weekend' : '' ?> <?= empty($dayHours) ? 'is-free' : '' ?>">
                        <div class="day-header">
                            <span class="day-name"><?= $weekDaysShort[$weekday] ?></span>
                            <span class="day-num"><?= $dayNum ?></span>
                        </div>
                        <div class="day-content">
                            <?php if (!empty($dayHours)): ?>
                            <?php
                                $cardBreaks = 0;
                                $cardTypes = $assignmentTypeByDateAdvisorHour[$date][$advisorId] ?? [];
                                foreach ($dayHours as $dh) {
                                    if (($cardTypes[$dh] ?? '') === 'break') $cardBreaks++;
                                }
                                $cardHoursDisplay = count($dayHours) - $cardBreaks + ($cardBreaks * 0.5);
                            ?>
                            <div class="time-blocks">
                                <?= hoursToBlocks($dayHours, $cardTypes) ?>
                            </div>
                            <span class="hours-badge"><?= $cardHoursDisplay == (int)$cardHoursDisplay ? (int)$cardHoursDisplay : $cardHoursDisplay ?>h</span>
                            <?php else: ?>
                            <span class="free-label">LIBRE</span>
                            <?php endif; ?>
                            <?php
                                $crossHours = $crossCampaignHoursMap[$advisorId][$date] ?? [];
                                if (!empty($crossHours)):
                                    $crossHourNums = array_keys($crossHours);
                                    $crossCampName = reset($crossHours);
                            ?>
                            <div style="margin-top:3px;font-size:0.7em;color:#7c3aed;line-height:1.2;">
                                <?= htmlspecialchars($crossCampName) ?>: <?= hoursToBlocks($crossHourNums) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif ($viewMode === 'edit'): ?>
    <!-- VISTA DE EDICION - Matriz clickeable para agregar/quitar asignaciónes -->
    <div class="panel">
        <div class="panel-head panel-head-daily">
            <h2>Editar Horario - <?= htmlspecialchars($selectedDateLabel) ?></h2>
            <?php if ($canEditApproved && $selectedDate < date('Y-m-d')): ?>
            <span class="legend-note" style="color:#dc2626;font-weight:600;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;fill:#dc2626;vertical-align:-2px;"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                Dia pasado — solo lectura
            </span>
            <?php else: ?>
            <span class="legend-note">Arrastra para asignar/quitar</span>
            <?php endif; ?>
        </div>

        <div class="daily-toolbar">
            <div class="nav-links">
                <?php if ($prevDate !== null): ?>
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>?view=edit&date=<?= urlencode($prevDate) ?>" class="btn-outline-link">Dia anterior</a>
                <?php endif; ?>
                <?php if ($nextDate !== null): ?>
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>?view=edit&date=<?= urlencode($nextDate) ?>" class="btn-outline-link">Dia siguiente</a>
                <?php endif; ?>
            </div>
            <form method="get" class="date-picker-form">
                <input type="hidden" name="view" value="edit">
                <select name="date" onchange="this.form.submit()">
                    <?php foreach ($dates as $date): ?>
                    <?php $stamp = strtotime($date); ?>
                    <option value="<?= htmlspecialchars($date) ?>" <?= $date === $selectedDate ? 'selected' : '' ?>>
                        <?= htmlspecialchars(sprintf('%s %s/%s', $weekDaysShort[(int)date('w', $stamp)], date('d', $stamp), date('m', $stamp))) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="edit-toolbar">
            <div class="edit-mode-selector">
                <span class="edit-mode-label">Modo:</span>
                <button type="button" class="mode-btn mode-btn-active" id="modeNormal" onclick="setEditMode('normal')">
                    <span class="mode-dot" style="background:#22c55e;"></span> Hora
                </button>
                <button type="button" class="mode-btn" id="modeBreak" onclick="setEditMode('break')">
                    <span class="mode-dot" style="background:#f59e0b;"></span> Break
                </button>
                <?php foreach ($campaignActivities as $act): ?>
                <button type="button" class="mode-btn" id="modeActivity<?= $act['id'] ?>" onclick="setEditMode('activity_<?= $act['id'] ?>')">
                    <span class="mode-dot" style="background:<?= htmlspecialchars($act['color']) ?>;"></span> <?= htmlspecialchars($act['nombre']) ?>
                </button>
                <?php endforeach; ?>
                <button type="button" class="mode-btn" id="modeRemove" onclick="setEditMode('remove')">
                    <span class="mode-dot" style="background:#ef4444;"></span> Eliminar
                </button>
            </div>
            <div class="edit-legend">
                <span class="legend-item"><span class="cell-preview assigned"></span> Hora</span>
                <span class="legend-item"><span class="cell-preview is-break-preview"></span> Break</span>
                <?php foreach ($campaignActivities as $act): ?>
                <span class="legend-item"><span class="cell-preview" style="background:<?= htmlspecialchars($act['color']) ?>20;border:1px solid <?= htmlspecialchars($act['color']) ?>;"></span> <?= htmlspecialchars($act['nombre']) ?></span>
                <?php endforeach; ?>
                <span class="legend-item"><span class="cell-preview available"></span> Vacio</span>
            </div>
        </div>
        <div class="edit-hint">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:15px;height:15px;fill:#94a3b8;flex-shrink:0;"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
            <span>Manten presionado el clic y arrastra para pintar varias celdas. Selecciona el modo antes de pintar.</span>
        </div>
        <?php if ($canEditApproved): ?>
        <div class="edit-hint" style="background:#fefce8;border-bottom:1px solid #fde68a;">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:15px;height:15px;fill:#d97706;flex-shrink:0;"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
            <span>Horario aprobado — solo puedes modificar el dia de <strong>hoy (<?= date('d/m/Y') ?>)</strong> y dias futuros. Los dias pasados estan bloqueados.</span>
        </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="edit-table" id="editTable" data-schedule="<?= $schedule['id'] ?>" data-date="<?= htmlspecialchars($selectedDate) ?>" data-approved="<?= $canEditApproved ? '1' : '0' ?>" data-today="<?= date('Y-m-d') ?>">
                <thead>
                    <tr>
                        <th class="sticky-col">Asesor</th>
                        <?php foreach ($hours as $hour): ?>
                        <th><?= sprintf('%02d', $hour) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-metric row-required">
                        <td class="sticky-col"><b>Requerido</b></td>
                        <?php foreach ($hours as $hour): ?>
                        <td class="req-cell"><?= (int)($selectedDailyRequirements[$hour] ?? 0) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="row-metric row-coverage">
                        <td class="sticky-col"><b>Asignados</b></td>
                        <?php foreach ($hours as $hour): ?>
                        <td class="cov-cell" data-hour="<?= $hour ?>"><?= (int)($selectedDailyCoverage[$hour] ?? 0) ?></td>
                        <?php endforeach; ?>
                    </tr>

                    <?php foreach ($advisorsMap as $advisor): ?>
                    <?php
                        $advisorId = (int)$advisor['id'];
                        $dayHours = $selectedDailyAssignments[$advisorId] ?? [];
                        $hourSet = array_flip($dayHours);
                    ?>
                    <tr data-advisor="<?= $advisorId ?>"<?= !empty($advisor['is_shared']) ? ' style="background:#faf5ff;"' : '' ?>>
                        <td class="sticky-col"><?= htmlspecialchars($advisor['name']) ?><?= !empty($advisor['is_shared']) ? ' <span style="color:#7c3aed;font-size:0.8em;">(P)</span>' : '' ?></td>
                        <?php foreach ($hours as $hour): ?>
                        <?php
                            $isAssigned = isset($hourSet[$hour]);
                            $cellType = $assignmentTypeByDateAdvisorHour[$selectedDate][$advisorId][$hour] ?? 'normal';
                            $isBreak = $isAssigned && $cellType === 'break';
                            $crossCampName = $crossCampaignHoursMap[$advisorId][$selectedDate][$hour] ?? '';
                            $editActivity = $isAssigned ? getActivityForHour($advisorId, $hour, $selectedDate, $advisorActivities) : null;
                        ?>
                        <td class="edit-cell <?= $isAssigned ? ($isBreak ? 'assigned is-break' : 'assigned') : 'available' ?> <?= $editActivity ? 'has-activity' : '' ?>"
                            data-advisor="<?= $advisorId ?>"
                            data-hour="<?= $hour ?>"
                            data-assigned="<?= $isAssigned ? '1' : '0' ?>"
                            data-type="<?= $isBreak ? 'break' : ($isAssigned ? 'normal' : '') ?>"
                            <?php if ($editActivity): ?>
                            data-activity="<?= htmlspecialchars($editActivity['nombre']) ?>"
                            style="background:<?= htmlspecialchars($editActivity['color']) ?>20 !important;border-bottom:2px solid <?= htmlspecialchars($editActivity['color']) ?>;"
                            title="<?= htmlspecialchars($editActivity['nombre']) ?>"
                            <?php elseif ($crossCampName): ?>
                            title="Prestado a <?= htmlspecialchars($crossCampName) ?>"
                            style="background:#ede9fe !important;border-bottom:2px solid #7c3aed;"
                            <?php endif; ?>><?php if ($isBreak): ?><span class="break-label">B</span><?php elseif ($editActivity): ?><span style="color:<?= htmlspecialchars($editActivity['color']) ?>;font-size:0.6em;font-weight:700;"><?= htmlspecialchars(mb_substr($editActivity['nombre'], 0, 3)) ?></span><?php elseif ($crossCampName): ?><span style="color:#7c3aed;font-size:0.65em;">P</span><?php endif; ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="edit-actions">
            <button type="button" class="btn-solid btn-send" onclick="saveChanges()">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;margin-right:6px;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                Guardar Cambios
            </button>
            <span class="changes-counter" id="changesCounter">0 cambios pendientes</span>
        </div>
    </div>
    <?php elseif ($viewMode === 'monthly'): ?>
    <div class="panel">
        <div class="panel-head">
            <h2>Horario mensual por asesor</h2>
            <span class="legend-note">Verde = asignado | Gris = libre</span>
        </div>
        <div class="table-wrap">
            <table class="monthly-table">
                <thead>
                    <tr>
                        <th class="col-advisor">Asesor</th>
                        <?php foreach ($dates as $date): ?>
                        <?php $weekday = (int)date('w', strtotime($date)); ?>
                        <th class="<?= in_array($weekday, [0, 6], true) ? 'is-weekend' : '' ?>">
                            <span class="head-day"><?= $weekDaysShort[$weekday] ?></span>
                            <span class="head-num"><?= date('d', strtotime($date)) ?></span>
                        </th>
                        <?php endforeach; ?>
                        <th>HT Mes</th>
                        <th>Libres</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advisorsMap as $advisor): ?>
                    <?php $advisorId = (int)$advisor['id']; ?>
                    <tr<?= !empty($advisor['is_shared']) ? ' style="background:#faf5ff;"' : '' ?>>
                        <td class="col-advisor"><?= htmlspecialchars($advisor['name']) ?><?= !empty($advisor['is_shared']) ? ' <span style="color:#7c3aed;font-size:0.8em;">(P)</span>' : '' ?></td>
                        <?php foreach ($dates as $date): ?>
                        <?php
                            $dayHours = $assignmentsByDateAdvisor[$date][$advisorId] ?? [];
                            $weekday = (int)date('w', strtotime($date));
                        ?>
                        <td class="<?= in_array($weekday, [0, 6], true) ? 'is-weekend' : '' ?>">
                            <?php if (!empty($dayHours)): ?>
                            <?php
                                $dayBreaks = 0;
                                $types = $assignmentTypeByDateAdvisorHour[$date][$advisorId] ?? [];
                                foreach ($dayHours as $dh) {
                                    if (($types[$dh] ?? '') === 'break') $dayBreaks++;
                                }
                                $dayHoursDisplay = count($dayHours) - $dayBreaks + ($dayBreaks * 0.5);
                            ?>
                            <span class="tag tag-hours"><?= $dayHoursDisplay == (int)$dayHoursDisplay ? (int)$dayHoursDisplay : $dayHoursDisplay ?>h</span>
                            <small class="range"><?= hoursToBlocks($dayHours) ?></small>
                            <?php else: ?>
                            <span class="tag tag-free">Libre</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <?php $monthH = $advisorMonthHours[$advisorId] ?? 0; ?>
                        <td class="cell-bold"><?= $monthH == (int)$monthH ? (int)$monthH : $monthH ?>h</td>
                        <td class="cell-bold"><?= (int)($advisorFreeDays[$advisorId] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="panel">
        <div class="panel-head panel-head-daily">
            <h2>Horario diario tipo matriz</h2>
            <?php if ($selectedDateLabel !== ''): ?>
            <span class="legend-note"><?= htmlspecialchars($selectedDateLabel) ?></span>
            <?php endif; ?>
        </div>

        <div class="daily-toolbar">
            <div class="nav-links">
                <?php if ($prevDate !== null): ?>
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>?view=daily&date=<?= urlencode($prevDate) ?>" class="btn-outline-link">Dia anterior</a>
                <?php else: ?>
                <span class="btn-outline-link btn-disabled">Dia anterior</span>
                <?php endif; ?>

                <?php if ($nextDate !== null): ?>
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>?view=daily&date=<?= urlencode($nextDate) ?>" class="btn-outline-link">Dia siguiente</a>
                <?php else: ?>
                <span class="btn-outline-link btn-disabled">Dia siguiente</span>
                <?php endif; ?>
            </div>

            <form method="get" class="date-picker-form">
                <input type="hidden" name="view" value="daily">
                <select name="date" onchange="this.form.submit()">
                    <?php foreach ($dates as $date): ?>
                    <?php $stamp = strtotime($date); ?>
                    <option value="<?= htmlspecialchars($date) ?>" <?= $date === $selectedDate ? 'selected' : '' ?>>
                        <?= htmlspecialchars(sprintf('%s %s/%s', $weekDaysShort[(int)date('w', $stamp)], date('d', $stamp), date('m', $stamp))) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="legend-row">
            <span><b>1</b> = hora asignada</span>
            <span><b>0.5</b> = break (descanso)</span>
            <span><b>LIBRE</b> = asesor sin horas ese dia</span>
            <?php foreach (($campaignActivities ?? []) as $act): ?>
            <span>
                <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:<?= htmlspecialchars($act['color']) ?>;vertical-align:middle;margin-right:2px;"></span>
                <?= htmlspecialchars($act['nombre']) ?>
            </span>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <table class="daily-table">
                <thead>
                    <tr>
                        <th class="sticky-col">Asesor</th>
                        <th class="sticky-col-2">HT</th>
                        <?php foreach ($hours as $hour): ?>
                        <th><?= sprintf('%02d', $hour) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-metric row-required">
                        <td class="sticky-col"><b>Dimensionamiento</b></td>
                        <td class="sticky-col-2"><b><?= $totalRequiredDay ?></b></td>
                        <?php foreach ($hours as $hour): ?>
                        <td><?= (int)($selectedDailyRequirements[$hour] ?? 0) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="row-metric row-coverage">
                        <td class="sticky-col"><b>Cobertura</b></td>
                        <td class="sticky-col-2"><b><?= $totalCoverageDay ?></b></td>
                        <?php foreach ($hours as $hour): ?>
                        <td><?= (int)($selectedDailyCoverage[$hour] ?? 0) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="row-metric row-gap">
                        <td class="sticky-col"><b>Diferencia</b></td>
                        <td class="sticky-col-2"><b><?= $totalCoverageDay - $totalRequiredDay ?></b></td>
                        <?php foreach ($hours as $hour): ?>
                        <?php $gap = (int)($selectedDailyCoverage[$hour] ?? 0) - (int)($selectedDailyRequirements[$hour] ?? 0); ?>
                        <td class="<?= $gap < 0 ? 'neg' : 'pos' ?>"><?= $gap ?></td>
                        <?php endforeach; ?>
                    </tr>

                    <?php foreach ($advisorsMap as $advisor): ?>
                    <?php
                        $advisorId = (int)$advisor['id'];
                        $dayHours = $selectedDailyAssignments[$advisorId] ?? [];
                        sort($dayHours);
                        $hourSet = array_flip($dayHours);
                        // Calculate day total counting breaks as 0.5
                        $dayBreakCount = 0;
                        $dayTypes = $assignmentTypeByDateAdvisorHour[$selectedDate][$advisorId] ?? [];
                        foreach ($dayHours as $dh) {
                            if (($dayTypes[$dh] ?? '') === 'break') $dayBreakCount++;
                        }
                        $dayTotal = count($dayHours) - $dayBreakCount + ($dayBreakCount * 0.5);
                    ?>
                    <tr class="<?= count($dayHours) === 0 ? 'row-free-advisor' : '' ?>"<?= !empty($advisor['is_shared']) ? ' style="background:#faf5ff;"' : '' ?>>
                        <td class="sticky-col"><?= htmlspecialchars($advisor['name']) ?><?= !empty($advisor['is_shared']) ? ' <span style="color:#7c3aed;font-size:0.8em;">(P)</span>' : '' ?></td>
                        <td class="sticky-col-2">
                            <?php if (count($dayHours) === 0): ?>
                            <span class="tag tag-free">LIBRE</span>
                            <?php else: ?>
                            <span class="tag tag-hours"><?= $dayTotal == (int)$dayTotal ? (int)$dayTotal : $dayTotal ?></span>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($hours as $hour): ?>
                        <?php
                            $isAssigned = isset($hourSet[$hour]);
                            $type = $assignmentTypeByDateAdvisorHour[$selectedDate][$advisorId][$hour] ?? 'normal';
                            $activity = $isAssigned ? getActivityForHour($advisorId, $hour, $selectedDate, $advisorActivities) : null;
                            $crossCampName = $crossCampaignHoursMap[$advisorId][$selectedDate][$hour] ?? '';
                        ?>
                        <?php if ($crossCampName && !$isAssigned): ?>
                        <td style="background:#ede9fe;color:#7c3aed;font-size:0.55rem;font-weight:700;border-bottom:2px solid #7c3aed;" title="Prestado a <?= htmlspecialchars($crossCampName) ?>">
                            <?= htmlspecialchars(mb_substr($crossCampName, 0, 4)) ?>
                        </td>
                        <?php elseif ($activity): ?>
                        <td class="assigned" style="background:<?= htmlspecialchars($activity['color']) ?>20;color:<?= htmlspecialchars($activity['color']) ?>;font-size:0.6rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:40px;" title="<?= htmlspecialchars($activity['nombre']) ?>">
                            <?= htmlspecialchars(mb_substr($activity['nombre'], 0, 4)) ?>
                        </td>
                        <?php elseif ($isAssigned && $type === 'break'): ?>
                        <td class="assigned type-break" title="Break">
                            0.5
                        </td>
                        <?php else: ?>
                        <td class="<?= $isAssigned ? 'assigned type-' . htmlspecialchars($type) : '' ?><?= $crossCampName ? ' cross-campaign' : '' ?>"
                            <?= $crossCampName ? 'style="border-bottom:2px solid #7c3aed;" title="Tambien prestado a ' . htmlspecialchars($crossCampName) . '"' : '' ?>>
                            <?= $isAssigned ? '1' : '' ?>
                        </td>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraScripts = [];

// Build activities JSON for JS
$activitiesMap = [];
foreach ($campaignActivities as $act) {
    $activitiesMap[$act['id']] = [
        'id' => (int)$act['id'],
        'nombre' => $act['nombre'],
        'color' => $act['color'] ?? '#2563eb',
    ];
}
$activitiesJson = json_encode((object)$activitiesMap);

// Build cross-campaign hours JSON for conflict detection
$crossHoursJs = [];
foreach ($crossCampaignHoursMap as $advId => $dateMap) {
    foreach ($dateMap as $fecha => $hourMap) {
        foreach ($hourMap as $hora => $campNombre) {
            $crossHoursJs[(int)$advId][$fecha][(int)$hora] = $campNombre;
        }
    }
}
$crossHoursJson = json_encode((object)$crossHoursJs);
$sharedIdsJson = json_encode(array_values($sharedAdvisorIdsList));
$csrfToken = \App\Services\CsrfService::token();

$extraScripts[] = <<<SCRIPT
<script>
const BASE_URL = '{$_ENV['APP_URL']}' || '/system-horario/TurnoFlow/public';
const CSRF_TOKEN = '{$csrfToken}';
const pendingChanges = [];
let changesCounter = 0;

// --- Activities Map ---
const ACTIVITIES = {$activitiesJson};

// --- Shared advisors & cross-campaign conflict data ---
const SHARED_ADVISORS = new Set({$sharedIdsJson});
const CROSS_HOURS = {$crossHoursJson};

// --- Edit Mode ---
let editMode = 'normal'; // 'normal', 'break', 'remove', 'activity_N'

function setEditMode(mode) {
    editMode = mode;
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('mode-btn-active'));
    let btnId;
    if (mode === 'normal') btnId = 'modeNormal';
    else if (mode === 'break') btnId = 'modeBreak';
    else if (mode === 'remove') btnId = 'modeRemove';
    else if (mode.startsWith('activity_')) btnId = 'modeActivity' + mode.split('_')[1];
    const btn = document.getElementById(btnId);
    if (btn) btn.classList.add('mode-btn-active');
}

// --- Toast / Snackbar System ---
function showToast(message, type, duration) {
    type = type || 'info';
    duration = duration || 3500;
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'tf-toast-container';
        document.body.appendChild(container);
    }
    const icons = {
        success: '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
        error: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>',
        warning: '<path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>',
        info: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>'
    };
    const toast = document.createElement('div');
    toast.className = 'tf-toast tf-toast-' + type;
    toast.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor">' + (icons[type] || icons.info) + '</svg><span>' + message + '</span><button class="tf-toast-close" onclick="this.parentElement.remove()">&times;</button>';
    container.appendChild(toast);
    requestAnimationFrame(function() { toast.classList.add('tf-toast-show'); });
    setTimeout(function() {
        toast.classList.remove('tf-toast-show');
        toast.classList.add('tf-toast-hide');
        setTimeout(function() { toast.remove(); }, 300);
    }, duration);
}

// --- Loading Overlay ---
function showLoading(msg) {
    msg = msg || 'Guardando...';
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'tf-loading-overlay';
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = '<div class="tf-loading-box"><div class="tf-loading-spinner"></div><div class="tf-loading-text">' + msg + '</div></div>';
    overlay.style.display = 'flex';
}
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.style.display = 'none';
}

// --- Conflict Detection ---
function checkConflict(advisorId, hour) {
    const table = document.getElementById('editTable');
    if (!table) return null;
    const editDate = table.dataset.date;
    const advKey = String(advisorId);
    if (CROSS_HOURS[advKey] && CROSS_HOURS[advKey][editDate] && CROSS_HOURS[advKey][editDate][String(hour)]) {
        return CROSS_HOURS[advKey][editDate][String(hour)];
    }
    return null;
}

// --- Drag Painting ---
let isDragging = false;
let dragAction = null; // 'add', 'add-break', 'remove'
let paintedCells = new Set();

function getCellKey(cell) {
    return cell.dataset.advisor + ':' + cell.dataset.hour;
}

function applyToCell(cell) {
    if (!cell || !cell.classList.contains('edit-cell') || cell.classList.contains('blocked')) return;

    // Block editing past dates on approved schedules
    const table = document.getElementById('editTable');
    if (table && table.dataset.approved === '1') {
        const editDate = table.dataset.date;
        const today = table.dataset.today;
        if (editDate < today) return;
    }

    const key = getCellKey(cell);
    if (paintedCells.has(key)) return;
    paintedCells.add(key);

    const advisorId = parseInt(cell.dataset.advisor, 10);
    const hour = parseInt(cell.dataset.hour, 10);
    const isAssigned = cell.dataset.assigned === '1';
    const currentType = cell.dataset.type || '';

    // Conflict detection: block assigning if advisor has hours in another campaign
    if (editMode !== 'remove' && !isAssigned) {
        const conflictCamp = checkConflict(advisorId, hour);
        if (conflictCamp) {
            showToast('Conflicto: este asesor ya esta asignado a las ' + String(hour).padStart(2,'0') + ':00 en <b>' + conflictCamp + '</b>', 'error', 4500);
            return;
        }
    }

    if (editMode === 'remove') {
        // Only remove if currently assigned
        if (isAssigned) {
            cell.classList.remove('assigned', 'is-break', 'pending-add');
            cell.classList.add('available', 'pending-remove');
            cell.dataset.assigned = '0';
            cell.dataset.type = '';
            cell.innerHTML = '';
            addChange('remove', advisorId, hour);
            updateCoverage(hour, -1);
        }
    } else if (editMode === 'break') {
        if (isAssigned && currentType === 'break') return; // already break
        if (!isAssigned) {
            // Add as break
            cell.classList.remove('available', 'pending-remove');
            cell.classList.add('assigned', 'is-break', 'pending-add');
            cell.dataset.assigned = '1';
            cell.dataset.type = 'break';
            cell.innerHTML = '<span class="break-label">B</span>';
            addChange('add', advisorId, hour, 'break');
            updateCoverage(hour, 1);
        } else {
            // Convert normal -> break (remove then add as break)
            addChange('remove', advisorId, hour);
            addChange('add', advisorId, hour, 'break');
            cell.classList.add('is-break', 'pending-add');
            cell.dataset.type = 'break';
            cell.innerHTML = '<span class="break-label">B</span>';
        }
    } else if (editMode.startsWith('activity_')) {
        // Activity mode — assign hour + mark with activity
        const actId = editMode.split('_')[1];
        const act = ACTIVITIES[actId];
        if (!act) return;

        if (!isAssigned) {
            cell.classList.remove('available', 'pending-remove');
            cell.classList.add('assigned', 'has-activity', 'pending-add');
            cell.dataset.assigned = '1';
            cell.dataset.type = 'normal';
            cell.dataset.activity = act.nombre;
            cell.style.background = act.color + '20';
            cell.style.borderBottom = '2px solid ' + act.color;
            cell.innerHTML = '<span style="color:' + act.color + ';font-size:0.6em;font-weight:700;">' + act.nombre.substring(0, 3) + '</span>';
            addChange('add', advisorId, hour, 'normal', parseInt(actId));
            updateCoverage(hour, 1);
        } else {
            // Convert existing to activity
            if (currentType === 'break') {
                addChange('remove', advisorId, hour);
            }
            cell.classList.remove('is-break');
            cell.classList.add('has-activity', 'pending-add');
            cell.dataset.type = 'normal';
            cell.dataset.activity = act.nombre;
            cell.style.background = act.color + '20';
            cell.style.borderBottom = '2px solid ' + act.color;
            cell.innerHTML = '<span style="color:' + act.color + ';font-size:0.6em;font-weight:700;">' + act.nombre.substring(0, 3) + '</span>';
            addChange('add', advisorId, hour, 'normal', parseInt(actId));
        }
    } else {
        // Normal mode
        if (isAssigned && currentType !== 'break' && !cell.classList.contains('has-activity')) return; // already normal
        if (!isAssigned) {
            // Add as normal
            cell.classList.remove('available', 'pending-remove');
            cell.classList.add('assigned', 'pending-add');
            cell.dataset.assigned = '1';
            cell.dataset.type = 'normal';
            cell.innerHTML = '';
            addChange('add', advisorId, hour, 'normal');
            updateCoverage(hour, 1);
        } else {
            // Convert break/activity -> normal
            if (currentType === 'break') {
                addChange('remove', advisorId, hour);
                addChange('add', advisorId, hour, 'normal');
            } else if (cell.classList.contains('has-activity')) {
                addChange('add', advisorId, hour, 'normal');
            }
            cell.classList.remove('is-break', 'has-activity');
            cell.classList.add('pending-add');
            cell.dataset.type = 'normal';
            delete cell.dataset.activity;
            cell.style.background = '';
            cell.style.borderBottom = '';
            cell.innerHTML = '';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('editTable');
    if (!table) return;

    // Prevent text selection while dragging
    table.addEventListener('selectstart', function(e) {
        if (isDragging) e.preventDefault();
    });

    table.addEventListener('mousedown', function(e) {
        const cell = e.target.closest('.edit-cell');
        if (!cell) return;
        e.preventDefault();
        isDragging = true;
        paintedCells = new Set();
        applyToCell(cell);
    });

    table.addEventListener('mouseover', function(e) {
        if (!isDragging) return;
        const cell = e.target.closest('.edit-cell');
        if (cell) applyToCell(cell);
    });

    document.addEventListener('mouseup', function() {
        if (isDragging) {
            isDragging = false;
            paintedCells = new Set();
        }
    });

    // Touch support for mobile
    table.addEventListener('touchstart', function(e) {
        const cell = e.target.closest('.edit-cell');
        if (!cell) return;
        e.preventDefault();
        isDragging = true;
        paintedCells = new Set();
        applyToCell(cell);
    }, { passive: false });

    table.addEventListener('touchmove', function(e) {
        if (!isDragging) return;
        e.preventDefault();
        const touch = e.touches[0];
        const el = document.elementFromPoint(touch.clientX, touch.clientY);
        const cell = el ? el.closest('.edit-cell') : null;
        if (cell) applyToCell(cell);
    }, { passive: false });

    document.addEventListener('touchend', function() {
        if (isDragging) {
            isDragging = false;
            paintedCells = new Set();
        }
    });

    // Keyboard shortcuts for modes
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') return;
        if (e.key === '1') setEditMode('normal');
        if (e.key === '2') setEditMode('break');
        if (e.key === '3') setEditMode('remove');
    });
});

function addChange(action, advisorId, hour, tipo, activityId) {
    // Remove any existing change for same advisor+hour
    const existingIndex = pendingChanges.findIndex(
        c => c.advisor_id === advisorId && c.hour === hour && c.action === (action === 'add' ? 'add' : 'remove')
    );

    // Check for opposite that cancels
    const oppositeIndex = pendingChanges.findIndex(
        c => c.advisor_id === advisorId && c.hour === hour && c.action !== action
    );

    if (oppositeIndex !== -1 && !tipo) {
        pendingChanges.splice(oppositeIndex, 1);
        changesCounter--;
    } else if (existingIndex !== -1) {
        // Update existing
        pendingChanges[existingIndex].tipo = tipo || 'normal';
        pendingChanges[existingIndex].activity_id = activityId || null;
    } else {
        pendingChanges.push({ action, advisor_id: advisorId, hour, tipo: tipo || 'normal', activity_id: activityId || null });
        changesCounter++;
    }

    updateChangesCounter();
}

function updateCoverage(hour, delta) {
    const covCell = document.querySelector('.cov-cell[data-hour="' + hour + '"]');
    if (covCell) {
        const current = parseInt(covCell.textContent, 10) || 0;
        covCell.textContent = current + delta;
    }
}

function updateChangesCounter() {
    const counter = document.getElementById('changesCounter');
    if (counter) {
        counter.textContent = changesCounter + ' cambio' + (changesCounter !== 1 ? 's' : '') + ' pendiente' + (changesCounter !== 1 ? 's' : '');
        if (changesCounter > 0) {
            counter.classList.add('has-changes');
        } else {
            counter.classList.remove('has-changes');
        }
    }
}

async function saveChanges() {
    if (pendingChanges.length === 0) {
        showToast('No hay cambios para guardar.', 'info');
        return;
    }

    const table = document.getElementById('editTable');
    const scheduleId = table.dataset.schedule;
    const date = table.dataset.date;

    const btn = document.querySelector('.edit-actions .btn-send');
    btn.disabled = true;
    showLoading('Guardando ' + pendingChanges.length + ' cambio(s)...');

    try {
        const response = await fetch(BASE_URL + '/schedules/' + scheduleId + '/assignments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
            },
            body: JSON.stringify({
                date: date,
                changes: pendingChanges
            })
        });

        const result = await response.json();

        if (result.success) {
            pendingChanges.length = 0;
            changesCounter = 0;
            updateChangesCounter();

            document.querySelectorAll('.pending-add, .pending-remove').forEach(cell => {
                cell.classList.remove('pending-add', 'pending-remove');
            });

            let msg = result.added + ' agregados, ' + result.removed + ' eliminados';
            if (result.breaks > 0) msg += ', ' + result.breaks + ' breaks';
            if (result.activities > 0) msg += ', ' + result.activities + ' actividades';
            showToast(msg, 'success', 4000);
        } else {
            showToast(result.error || 'Error desconocido al guardar', 'error', 5000);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error de conexion al guardar los cambios.', 'error', 5000);
    } finally {
        hideLoading();
        btn.disabled = false;
    }
}

// Warn before leaving with unsaved changes
window.addEventListener('beforeunload', function(e) {
    if (pendingChanges.length > 0) {
        e.preventDefault();
        e.returnValue = 'Tienes cambios sin guardar. ¿Seguro que quieres salir?';
    }
});
</script>
<style>
/* Toast System */
.tf-toast-container {
    position: fixed; top: 20px; right: 20px; z-index: 10000;
    display: flex; flex-direction: column; gap: 10px;
    pointer-events: none; max-width: 420px;
}
.tf-toast {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 18px; border-radius: 12px;
    background: #fff; border: 1px solid #e2e8f0;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    font-size: 0.85rem; font-weight: 500; color: #334155;
    pointer-events: auto; opacity: 0;
    transform: translateX(40px);
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
}
.tf-toast-show { opacity: 1; transform: translateX(0); }
.tf-toast-hide { opacity: 0; transform: translateX(40px); }
.tf-toast svg { width: 20px; height: 20px; flex-shrink: 0; }
.tf-toast span { flex: 1; line-height: 1.4; }
.tf-toast-close {
    background: none; border: none; font-size: 18px; color: #94a3b8;
    cursor: pointer; padding: 0 0 0 8px; line-height: 1;
}
.tf-toast-close:hover { color: #475569; }
.tf-toast-success { border-left: 4px solid #16a34a; }
.tf-toast-success svg { fill: #16a34a; }
.tf-toast-error { border-left: 4px solid #dc2626; }
.tf-toast-error svg { fill: #dc2626; }
.tf-toast-warning { border-left: 4px solid #d97706; }
.tf-toast-warning svg { fill: #d97706; }
.tf-toast-info { border-left: 4px solid #2563eb; }
.tf-toast-info svg { fill: #2563eb; }

/* Loading Overlay */
.tf-loading-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    z-index: 9999; background: rgba(15,23,42,0.35);
    backdrop-filter: blur(2px);
    display: none; align-items: center; justify-content: center;
}
.tf-loading-box {
    background: #fff; border-radius: 16px;
    padding: 32px 48px; text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}
.tf-loading-spinner {
    width: 40px; height: 40px; margin: 0 auto 16px;
    border: 3px solid #e2e8f0; border-top-color: #2563eb;
    border-radius: 50%; animation: tf-spin 0.8s linear infinite;
}
.tf-loading-text {
    font-size: 0.9rem; font-weight: 600; color: #475569;
}
@keyframes tf-spin { to { transform: rotate(360deg); } }
</style>
SCRIPT;

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .schedule-detail {
        max-width: 100%;
    }

    /* Alerta de cobertura */
    .coverage-alert {
        display: flex;
        gap: 14px;
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 1px solid #f59e0b;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 20px;
    }

    .coverage-alert .alert-icon {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        background: #f59e0b;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .coverage-alert .alert-icon svg {
        width: 24px;
        height: 24px;
        fill: #fff;
    }

    .coverage-alert .alert-content {
        flex: 1;
    }

    .coverage-alert strong {
        display: block;
        color: #92400e;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .coverage-alert p {
        margin: 0;
        color: #78350f;
        font-size: 13px;
    }

    .coverage-alert details {
        margin-top: 10px;
    }

    .coverage-alert summary {
        cursor: pointer;
        font-size: 12px;
        color: #92400e;
        font-weight: 600;
    }

    .coverage-alert .alert-list {
        margin: 8px 0 0 0;
        padding-left: 20px;
        font-size: 11px;
        color: #78350f;
        max-height: 150px;
        overflow-y: auto;
    }

    .coverage-alert .alert-list li {
        margin-bottom: 2px;
    }

    .form-breadcrumb {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        color: #94a3b8;
    }

    .detail-header {
        margin-bottom: 16px;
    }

    .header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-outline-link,
    .btn-solid {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid #cbd5e1;
        transition: all 0.15s ease;
    }

    .btn-outline-link {
        background: #fff;
        color: #334155;
    }

    .btn-outline-link:hover {
        background: #f8fafc;
    }

    .btn-disabled {
        opacity: 0.45;
        pointer-events: none;
    }

    .btn-solid {
        border-color: transparent;
        color: #fff;
    }

    .btn-send {
        background: #1d4ed8;
    }

    .btn-send:hover {
        background: #1e40af;
    }

    .btn-ok {
        background: #15803d;
    }

    .btn-ok:hover {
        background: #166534;
    }

    .btn-bad {
        background: #b91c1c;
    }

    .btn-bad:hover {
        background: #991b1b;
    }

    .btn-tracking {
        background: #7c3aed;
    }

    .btn-tracking:hover {
        background: #6d28d9;
    }

    .title-row h1 {
        margin: 6px 0 2px;
        font-size: 24px;
        color: #0f172a;
    }

    .title-row p {
        margin: 0;
        font-size: 13px;
        color: #64748b;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        padding: 5px 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .stat-icon-box {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }

    .stat-box {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: box-shadow 0.15s ease;
    }

    .stat-box:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .stat-title {
        display: block;
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .stat-value {
        display: block;
        color: #0f172a;
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .mode-switch {
        display: flex;
        gap: 6px;
        margin-bottom: 14px;
        flex-wrap: wrap;
        background: #f8fafc;
        padding: 4px;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        width: fit-content;
    }

    .switch-link {
        border-radius: 999px;
        padding: 7px 14px;
        border: none;
        background: transparent;
        color: #64748b;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.15s ease;
    }

    .switch-link:hover {
        color: #334155;
        background: #fff;
    }

    .switch-link.active {
        background: #2563eb;
        color: #fff;
        box-shadow: 0 1px 3px rgba(37,99,235,0.3);
    }

    /* Coverage Panel */
    .coverage-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 14px;
    }

    .coverage-panel.coverage-complete {
        border-color: #86efac;
        background: linear-gradient(135deg, #f0fdf4 0%, #fff 60%);
    }

    .coverage-panel.coverage-deficit {
        border-color: #fed7aa;
        background: linear-gradient(135deg, #fffbeb 0%, #fff 60%);
    }

    .coverage-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
    }

    .coverage-header-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .coverage-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .coverage-icon svg { width: 24px; height: 24px; }

    .coverage-icon-ok {
        background: #dcfce7;
        color: #16a34a;
    }
    .coverage-icon-ok svg { fill: #16a34a; }

    .coverage-icon-warn {
        background: #fef3c7;
        color: #d97706;
    }
    .coverage-icon-warn svg { fill: #d97706; }

    .coverage-title {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
    }

    .coverage-subtitle {
        margin: 2px 0 0;
        font-size: 12px;
        color: #64748b;
    }

    .coverage-subtitle strong {
        color: #0f172a;
    }

    .coverage-percent-badge {
        font-size: 1.4rem;
        font-weight: 800;
        padding: 8px 16px;
        border-radius: 10px;
        flex-shrink: 0;
    }

    .percent-ok { background: #dcfce7; color: #15803d; }
    .percent-warn { background: #fef3c7; color: #b45309; }
    .percent-bad { background: #fee2e2; color: #b91c1c; }

    .coverage-metrics {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 14px;
    }

    .coverage-metric {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 12px;
        text-align: center;
    }

    .coverage-metric .metric-label {
        display: block;
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 2px;
    }

    .coverage-metric .metric-value {
        display: block;
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
    }

    .coverage-metric .metric-value.metric-neg { color: #dc2626; }
    .coverage-metric .metric-value.metric-pos { color: #16a34a; }

    .coverage-metric .metric-unit {
        display: block;
        font-size: 10px;
        color: #94a3b8;
    }

    .coverage-progress-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .coverage-progress-bar {
        flex: 1;
        height: 8px;
        background: #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
    }

    .coverage-progress-fill {
        height: 100%;
        border-radius: 8px;
        background: linear-gradient(90deg, #f59e0b, #eab308);
        transition: width 0.6s ease;
    }

    .coverage-complete .coverage-progress-fill {
        background: linear-gradient(90deg, #16a34a, #22c55e);
    }

    .coverage-progress-label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        flex-shrink: 0;
    }

    .coverage-details {
        margin-top: 4px;
    }

    .coverage-details summary {
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        padding: 6px 0;
    }

    .coverage-details summary:hover { color: #334155; }

    .coverage-detail-grid {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-top: 10px;
        max-height: 250px;
        overflow-y: auto;
    }

    .coverage-detail-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 5px 8px;
        border-radius: 6px;
        background: #fff;
        border: 1px solid #f1f5f9;
    }

    .coverage-detail-row .detail-date {
        font-size: 12px;
        font-weight: 600;
        color: #334155;
        min-width: 65px;
    }

    .coverage-detail-row .detail-bar-wrap {
        flex: 1;
    }

    .coverage-detail-row .detail-bar {
        height: 6px;
        background: #f1f5f9;
        border-radius: 6px;
        overflow: hidden;
    }

    .coverage-detail-row .detail-bar-fill {
        height: 100%;
        border-radius: 6px;
        background: #f59e0b;
    }

    .coverage-detail-row .detail-nums {
        font-size: 11px;
        color: #64748b;
        min-width: 55px;
        text-align: right;
    }

    .coverage-detail-row .detail-deficit {
        font-size: 11px;
        font-weight: 700;
        color: #dc2626;
        min-width: 30px;
        text-align: right;
    }

    .panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }

    .panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
        flex-wrap: wrap;
    }

    .panel-head h2 {
        margin: 0;
        font-size: 14px;
        color: #0f172a;
    }

    .legend-note {
        font-size: 12px;
        color: #64748b;
    }

    .table-wrap {
        overflow-x: auto;
        max-width: 100%;
    }

    .monthly-table,
    .daily-table {
        width: max-content;
        min-width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .monthly-table th,
    .monthly-table td,
    .daily-table th,
    .daily-table td {
        border: 1px solid #e2e8f0;
        text-align: center;
        padding: 6px 4px;
        white-space: nowrap;
    }

    .monthly-table th,
    .daily-table th {
        background: #f8fafc;
        font-size: 11px;
        color: #475569;
    }

    .monthly-table .col-advisor,
    .daily-table .sticky-col {
        position: sticky;
        left: 0;
        z-index: 2;
        min-width: 220px;
        text-align: left;
        padding-left: 10px;
        background: #fff;
        font-weight: 600;
        color: #0f172a;
    }

    .daily-table th.sticky-col,
    .monthly-table th.col-advisor {
        background: #f8fafc;
        z-index: 3;
    }

    .daily-table .sticky-col-2 {
        position: sticky;
        left: 220px;
        z-index: 2;
        min-width: 70px;
        background: #fff;
    }

    .daily-table th.sticky-col-2 {
        background: #f8fafc;
        z-index: 3;
    }

    .monthly-table th:not(.col-advisor),
    .monthly-table td:not(.col-advisor) {
        min-width: 56px;
    }

    .monthly-table .is-weekend {
        background: #fef2f2;
    }

    .head-day,
    .head-num {
        display: block;
        line-height: 1.2;
    }

    .head-day {
        font-size: 10px;
        color: #94a3b8;
    }

    .tag {
        display: inline-block;
        border-radius: 6px;
        padding: 2px 7px;
        font-size: 11px;
        font-weight: 700;
    }

    .tag-hours {
        background: #dcfce7;
        color: #166534;
    }

    .tag-free {
        background: #f1f5f9;
        color: #475569;
    }

    .range {
        display: block;
        font-size: 9px;
        color: #1e40af;
        margin-top: 2px;
        line-height: 1.3;
        font-weight: 500;
    }

    .cell-bold {
        font-weight: 700;
        color: #0f172a;
    }

    .daily-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: wrap;
    }

    .nav-links {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .date-picker-form select {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 8px 10px;
        min-width: 180px;
        font-size: 12px;
        color: #334155;
        background: #fff;
    }

    .legend-row {
        display: flex;
        gap: 14px;
        padding: 10px 14px;
        font-size: 12px;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: wrap;
        background: #f8fafc;
    }

    .daily-table td {
        min-width: 36px;
    }

    .row-metric td {
        font-weight: 600;
    }

    .row-required td {
        background: #eff6ff;
    }

    .row-coverage td {
        background: #ecfdf5;
    }

    .row-gap td {
        background: #fffbeb;
    }

    .row-gap td.pos {
        color: #166534;
    }

    .row-gap td.neg {
        color: #b91c1c;
    }

    .daily-table td.assigned {
        background: #dcfce7;
        color: #166534;
        font-weight: 700;
    }

    .daily-table td.type-extra {
        background: #fef3c7;
        color: #92400e;
    }

    .daily-table td.type-nocturno {
        background: #e0e7ff;
        color: #3730a3;
    }

    .daily-table td.type-replanif {
        background: #fee2e2;
        color: #9f1239;
    }

    .daily-table td.type-break {
        background: #fef9c3;
        color: #a16207;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .edit-cell.is-break {
        background: #fef9c3 !important;
        position: relative;
    }

    .break-label {
        font-size: 0.6rem;
        font-weight: 700;
        color: #a16207;
    }

    .row-free-advisor td {
        background: #f8fafc;
    }

    .empty-box {
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
        padding: 24px;
        background: #fff;
        color: #475569;
        font-size: 13px;
    }

    /* Vista por Asesor */
    .advisor-schedule-list {
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .advisor-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
    }

    .advisor-card-header {
        padding: 16px 20px;
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        color: #fff;
    }

    .advisor-info {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .advisor-avatar {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 16px;
    }

    .advisor-info h3 {
        margin: 0 0 4px 0;
        font-size: 16px;
        font-weight: 600;
    }

    .advisor-stats {
        font-size: 12px;
        opacity: 0.85;
    }

    .advisor-schedule-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap: 1px;
        background: #e2e8f0;
        padding: 1px;
    }

    .schedule-day {
        background: #fff;
        padding: 10px 8px;
        text-align: center;
        min-height: 80px;
        display: flex;
        flex-direction: column;
    }

    .schedule-day.is-weekend {
        background: #fef2f2;
    }

    .schedule-day.is-free {
        background: #f8fafc;
    }

    .schedule-day.is-weekend.is-free {
        background: #fef2f2;
    }

    .day-header {
        display: flex;
        flex-direction: column;
        margin-bottom: 6px;
    }

    .day-name {
        font-size: 10px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
    }

    .day-num {
        font-size: 14px;
        font-weight: 700;
        color: #1e293b;
    }

    .day-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }

    .time-blocks {
        font-size: 10px;
        color: #1e40af;
        font-weight: 600;
        line-height: 1.4;
    }

    .hours-badge {
        display: inline-block;
        background: #dcfce7;
        color: #166534;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 700;
    }

    .free-label {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 600;
    }

    /* Vista de edicion */
    .switch-edit {
        background: #fef3c7;
        border-color: #f59e0b;
        color: #92400e;
    }

    .switch-edit.active {
        background: #f59e0b;
        border-color: #d97706;
        color: #fff;
    }

    .edit-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
        flex-wrap: wrap;
    }

    .edit-mode-selector {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .edit-mode-label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        margin-right: 4px;
    }

    .mode-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        background: #fff;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s ease;
        user-select: none;
    }

    .mode-btn:hover {
        border-color: #cbd5e1;
        background: #f1f5f9;
    }

    .mode-btn.mode-btn-active {
        border-color: #2563eb;
        background: #eff6ff;
        color: #1d4ed8;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
    }

    .mode-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }

    .edit-hint {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        font-size: 12px;
        color: #94a3b8;
        background: #fafbfc;
        border-bottom: 1px solid #e2e8f0;
    }

    .is-break-preview {
        background: #fef3c7 !important;
        border-color: #f59e0b !important;
    }

    .edit-legend {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: #475569;
    }

    .cell-preview {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
    }

    .cell-preview.assigned {
        background: #22c55e;
        border-color: #16a34a;
    }

    .cell-preview.available {
        background: #fff;
        border-color: #cbd5e1;
    }

    .cell-preview.blocked {
        background: #f1f5f9;
        border-color: #cbd5e1;
        position: relative;
    }

    .cell-preview.blocked::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        height: 2px;
        background: #dc2626;
        transform: rotate(-45deg);
    }

    .edit-table {
        width: max-content;
        min-width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .edit-table th,
    .edit-table td {
        border: 1px solid #e2e8f0;
        text-align: center;
        padding: 0;
    }

    .edit-table th {
        background: #f8fafc;
        font-size: 11px;
        color: #475569;
        padding: 8px 4px;
    }

    .edit-table th.sticky-col {
        position: sticky;
        left: 0;
        z-index: 3;
        background: #f8fafc;
        min-width: 180px;
        text-align: left;
        padding-left: 10px;
    }

    .edit-table td.sticky-col {
        position: sticky;
        left: 0;
        z-index: 2;
        background: #fff;
        min-width: 180px;
        text-align: left;
        padding: 6px 10px;
        font-weight: 600;
        color: #0f172a;
    }

    .edit-table .row-metric td.sticky-col {
        background: #f8fafc;
    }

    .edit-table .row-required td {
        background: #eff6ff;
        padding: 6px 4px;
    }

    .edit-table .row-coverage td {
        background: #ecfdf5;
        padding: 6px 4px;
    }

    .edit-table .req-cell,
    .edit-table .cov-cell {
        font-weight: 600;
        font-size: 11px;
    }

    .edit-cell {
        width: 32px;
        height: 32px;
        user-select: none;
        -webkit-user-select: none;
        cursor: pointer;
        transition: all 0.15s ease;
        position: relative;
    }

    .edit-cell:hover {
        transform: scale(1.1);
        z-index: 1;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .edit-cell.assigned {
        background: #22c55e;
    }

    .edit-cell.assigned:hover {
        background: #16a34a;
    }

    .edit-cell.available {
        background: #fff;
    }

    .edit-cell.available:hover {
        background: #dcfce7;
    }

    .edit-cell.blocked {
        background: #f1f5f9;
        cursor: not-allowed;
    }

    .edit-cell.pending-add {
        background: #86efac;
        animation: pulse-add 0.5s ease;
    }

    .edit-cell.pending-remove {
        background: #fca5a5;
        animation: pulse-remove 0.5s ease;
    }

    @keyframes pulse-add {
        0% { transform: scale(1); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }

    @keyframes pulse-remove {
        0% { transform: scale(1); }
        50% { transform: scale(0.9); }
        100% { transform: scale(1); }
    }

    .edit-actions {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .changes-counter {
        font-size: 13px;
        color: #64748b;
    }

    .changes-counter.has-changes {
        color: #f59e0b;
        font-weight: 600;
    }

    /* Mejoras responsive */
    @media (max-width: 768px) {
        .advisor-schedule-grid {
            grid-template-columns: repeat(7, 1fr);
        }

        .time-blocks {
            font-size: 9px;
        }
    }

    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .coverage-metrics {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 700px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .coverage-metrics {
            grid-template-columns: repeat(2, 1fr);
        }

        .coverage-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .mode-switch {
            width: 100%;
            border-radius: 12px;
        }

        .switch-link {
            flex: 1;
            justify-content: center;
            font-size: 11px;
            padding: 7px 8px;
        }

        .daily-table .sticky-col,
        .monthly-table .col-advisor,
        .edit-table .sticky-col {
            min-width: 170px;
        }

        .daily-table .sticky-col-2 {
            left: 170px;
        }
    }
</style>
STYLE;

include APP_PATH . '/Views/layouts/main.php';
?>
