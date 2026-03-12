<?php
/**
 * TurnoFlow - Detalle de horario mensual / diario
 * Vista mejorada con formato DESDE-HASTA para asesores
 */

$pageTitle = 'Ver Horario';
$currentPage = 'schedules';

$viewMode = strtolower((string)($_GET['view'] ?? 'monthly'));
$viewMode = in_array($viewMode, ['daily', 'advisor', 'edit']) ? $viewMode : 'monthly';

// Solo permitir edicion si el horario no esta aprobado
$canEdit = in_array($schedule['status'], ['borrador', 'rechazado'], true);

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

$selectedDate = (string)($_GET['date'] ?? ($dates[0] ?? ''));
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
            <a href="<?= BASE_URL ?>/schedules" class="btn-outline-link">Volver</a>
            <div class="header-actions">
                <?php if ($schedule['status'] === 'borrador'): ?>
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/submit" class="btn-solid btn-send">Enviar a aprobacion</a>
                <?php endif; ?>

                <?php if (in_array($_SESSION['user']['rol'] ?? '', ['coordinador', 'admin'], true) && $schedule['status'] === 'enviado'): ?>
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/approve" class="btn-solid btn-ok">Aprobar</a>
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/reject" class="btn-solid btn-bad">Rechazar</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="title-row">
            <div>
                <span class="status-pill" style="background: <?= $statusInfo['bg'] ?>; color: <?= $statusInfo['color'] ?>;">
                    <?= htmlspecialchars($statusInfo['label']) ?>
                </span>
                <h1><?= htmlspecialchars((string)$schedule['campaign_nombre']) ?></h1>
                <p>
                    Periodo <?= str_pad((string)$schedule['periodo_mes'], 2, '0', STR_PAD_LEFT) ?>/<?= htmlspecialchars((string)$schedule['periodo_anio']) ?>
                    | <?= htmlspecialchars((string)$schedule['fecha_inicio']) ?> al <?= htmlspecialchars((string)$schedule['fecha_fin']) ?>
                </p>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <span class="stat-title">Asesores</span>
            <span class="stat-value"><?= $totalAdvisors ?></span>
        </div>
        <div class="stat-box">
            <span class="stat-title">Asignaciónes</span>
            <span class="stat-value"><?= $totalAssignments ?></span>
        </div>
        <div class="stat-box">
            <span class="stat-title">Dias libres (acumulado)</span>
            <span class="stat-value"><?= $totalFreeSlots ?></span>
        </div>
        <div class="stat-box">
            <span class="stat-title">Estado</span>
            <span class="stat-value"><?= htmlspecialchars($statusInfo['label']) ?></span>
        </div>
    </div>

    <div class="mode-switch">
        <a href="<?= $monthlyLink ?>" class="switch-link <?= $viewMode === 'monthly' ? 'active' : '' ?>">Resumen Mensual</a>
        <a href="<?= $advisorLink ?>" class="switch-link <?= $viewMode === 'advisor' ? 'active' : '' ?>">Horario por Asesor</a>
        <a href="<?= $dailyLink ?>" class="switch-link <?= $viewMode === 'daily' ? 'active' : '' ?>">Matriz Diaria</a>
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
            <span class="legend-note">Click en celda para asignar/quitar</span>
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

        <div class="edit-legend">
            <span class="legend-item"><span class="cell-preview assigned"></span> Asignado</span>
            <span class="legend-item"><span class="cell-preview available"></span> Disponible</span>
            <span class="legend-item"><span class="cell-preview blocked"></span> No disponible</span>
        </div>

        <div class="table-wrap">
            <table class="edit-table" id="editTable" data-schedule="<?= $schedule['id'] ?>" data-date="<?= htmlspecialchars($selectedDate) ?>">
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
                        ?>
                        <td class="edit-cell <?= $isAssigned ? ($isBreak ? 'assigned is-break' : 'assigned') : 'available' ?>"
                            data-advisor="<?= $advisorId ?>"
                            data-hour="<?= $hour ?>"
                            data-assigned="<?= $isAssigned ? '1' : '0' ?>"
                            <?= $crossCampName ? 'title="Prestado a ' . htmlspecialchars($crossCampName) . '"' : '' ?>
                            <?= $crossCampName ? 'style="background:#ede9fe !important;border-bottom:2px solid #7c3aed;"' : '' ?>
                            onclick="toggleAssignment(this)"><?= $isBreak ? '<span class="break-label">0.5</span>' : ($crossCampName ? '<span style="color:#7c3aed;font-size:0.65em;">P</span>' : '') ?></td>
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
$extraScripts[] = <<<SCRIPT
<script>
const BASE_URL = '{$_ENV['APP_URL']}' || '/system-horario/TurnoFlow/public';
const pendingChanges = [];
let changesCounter = 0;

function toggleAssignment(cell) {
    if (cell.classList.contains('blocked')) {
        return;
    }

    const advisorId = parseInt(cell.dataset.advisor, 10);
    const hour = parseInt(cell.dataset.hour, 10);
    const isAssigned = cell.dataset.assigned === '1';

    // Toggle visual state
    if (isAssigned) {
        cell.classList.remove('assigned', 'pending-add');
        cell.classList.add('available', 'pending-remove');
        cell.dataset.assigned = '0';
        addChange('remove', advisorId, hour);
    } else {
        cell.classList.remove('available', 'pending-remove');
        cell.classList.add('assigned', 'pending-add');
        cell.dataset.assigned = '1';
        addChange('add', advisorId, hour);
    }

    // Update coverage counter for that hour
    updateCoverage(hour, isAssigned ? -1 : 1);
}

function addChange(action, advisorId, hour) {
    // Check if there's an opposite change that cancels this one
    const existingIndex = pendingChanges.findIndex(
        c => c.advisor_id === advisorId && c.hour === hour
    );

    if (existingIndex !== -1) {
        const existing = pendingChanges[existingIndex];
        if (existing.action !== action) {
            // Cancel out
            pendingChanges.splice(existingIndex, 1);
            changesCounter--;
        }
    } else {
        pendingChanges.push({ action, advisor_id: advisorId, hour });
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
        alert('No hay cambios para guardar.');
        return;
    }

    const table = document.getElementById('editTable');
    const scheduleId = table.dataset.schedule;
    const date = table.dataset.date;

    const btn = document.querySelector('.edit-actions .btn-send');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span> Guardando...';
    btn.disabled = true;

    try {
        const response = await fetch(BASE_URL + '/schedules/' + scheduleId + '/assignments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                date: date,
                changes: pendingChanges
            })
        });

        const result = await response.json();

        if (result.success) {
            // Clear pending changes
            pendingChanges.length = 0;
            changesCounter = 0;
            updateChangesCounter();

            // Remove pending classes
            document.querySelectorAll('.pending-add, .pending-remove').forEach(cell => {
                cell.classList.remove('pending-add', 'pending-remove');
            });

            // Show success
            alert('Cambios guardados correctamente: ' + result.added + ' agregados, ' + result.removed + ' eliminados.');
        } else {
            alert('Error al guardar: ' + (result.error || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexion al guardar los cambios.');
    } finally {
        btn.innerHTML = originalText;
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
.spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-right: 6px;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
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
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid #cbd5e1;
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
        display: inline-block;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
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
        padding: 12px;
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
        font-size: 20px;
        font-weight: 700;
        margin-top: 2px;
    }

    .mode-switch {
        display: flex;
        gap: 8px;
        margin-bottom: 14px;
        flex-wrap: wrap;
    }

    .switch-link {
        border-radius: 999px;
        padding: 7px 12px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
    }

    .switch-link.active {
        border-color: #2563eb;
        background: #2563eb;
        color: #fff;
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

    .edit-legend {
        display: flex;
        gap: 16px;
        padding: 12px 14px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
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
    }

    @media (max-width: 700px) {
        .stats-grid {
            grid-template-columns: 1fr;
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
