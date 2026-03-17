<?php
/**
 * TurnoFlow - Mi Horario
 * Vista personal del asesor con su horario mensual aprobado
 */

$monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$dayNamesShort = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];
$dayNamesLong  = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];

$userName = htmlspecialchars(($_SESSION['user']['nombre'] ?? '') . ' ' . ($_SESSION['user']['apellido'] ?? ''));
$userEmail = htmlspecialchars($_SESSION['user']['email'] ?? '');

// Greeting
$hora = (int)date('H');
if ($hora < 12) $saludo = 'Buenos dias';
elseif ($hora < 19) $saludo = 'Buenas tardes';
else $saludo = 'Buenas noches';

// Schedule period
if ($currentSchedule) {
    $scheduleMonth = (int)$currentSchedule['periodo_mes'];
    $scheduleYear = (int)$currentSchedule['periodo_anio'];
    $campaignName = htmlspecialchars($currentSchedule['campaign_nombre'] ?? 'Sin campana');
} else {
    $scheduleMonth = (int)date('n');
    $scheduleYear = (int)date('Y');
    $campaignName = $advisor ? htmlspecialchars($currentSchedule['campaign_nombre'] ?? 'Sin campana') : '';
}

// Organize assignments by date with type support
$assignmentsByDate = [];
$totalHours = 0;
$totalBreakHours = 0;
$workDays = 0;
$extraHours = 0;

if (!empty($assignments)) {
    foreach ($assignments as $a) {
        $key = $a['fecha'];
        if (!isset($assignmentsByDate[$key])) {
            $assignmentsByDate[$key] = ['hours' => [], 'types' => []];
            $workDays++;
        }
        $assignmentsByDate[$key]['hours'][] = (int)$a['hora'];
        $assignmentsByDate[$key]['types'][(int)$a['hora']] = $a['tipo'] ?? 'normal';

        if (($a['tipo'] ?? 'normal') === 'break') {
            $totalBreakHours += 0.5;
        } else {
            $totalHours++;
        }
        if (!empty($a['es_extra'])) {
            $extraHours++;
        }
    }
}

$effectiveHours = $totalHours + $totalBreakHours;
$avgHoursPerDay = $workDays > 0 ? round($effectiveHours / $workDays, 1) : 0;

// Days in month for rest day calculation
$daysInMonth = (int)date('t', mktime(0, 0, 0, $scheduleMonth, 1, $scheduleYear));
$restDays = $daysInMonth - $workDays;

// Today's shift
$today = date('Y-m-d');
$todayShift = $assignmentsByDate[$today] ?? null;
$todayHours = [];
$todayTypes = [];
if ($todayShift) {
    $todayHours = $todayShift['hours'];
    $todayTypes = $todayShift['types'];
    sort($todayHours);
}

// Current week (Mon-Sun)
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime($weekStart . " +{$i} days"));
    $weekDays[] = $d;
}

/**
 * Convert hour array to block ranges like "09:00-13:00, 14:00-18:00"
 */
function myScheduleHoursToBlocks(array $hours, array $types = []): string {
    if (empty($hours)) return '';
    sort($hours);
    $blocks = [];
    $start = $hours[0];
    $prev = $hours[0];

    for ($i = 1; $i < count($hours); $i++) {
        if ($hours[$i] !== $prev + 1) {
            $blocks[] = sprintf('%02d:00-%02d:00', $start, $prev + 1);
            $start = $hours[$i];
        }
        $prev = $hours[$i];
    }
    $blocks[] = sprintf('%02d:00-%02d:00', $start, $prev + 1);
    return implode(', ', $blocks);
}

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    /* My Schedule Page Styles */
    .ms-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 28px;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--corp-gray-200);
    }
    .ms-header h1 {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--corp-gray-900);
        margin: 0 0 6px 0;
        letter-spacing: -0.025em;
    }
    .ms-header p {
        color: var(--corp-gray-500);
        margin: 0;
        font-size: 0.95rem;
    }
    .ms-campaign-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        background: #eff6ff;
        color: #2563eb;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-top: 8px;
    }
    .ms-campaign-badge svg { width: 14px; height: 14px; fill: #2563eb; }
    .ms-period-box {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        background: var(--corp-gray-50);
        border: 1px solid var(--corp-gray-200);
        padding: 12px 20px;
        border-radius: 10px;
        flex-shrink: 0;
    }
    .ms-period-month {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--corp-gray-900);
    }
    .ms-period-year {
        font-size: 0.8rem;
        color: var(--corp-gray-500);
    }
    .ms-period-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        margin-top: 4px;
    }

    /* Error/Empty States */
    .ms-alert {
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--card-radius);
        padding: 32px;
        display: flex;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 24px;
    }
    .ms-alert-icon {
        width: 48px;
        height: 48px;
        min-width: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .ms-alert-icon svg { width: 24px; height: 24px; }
    .ms-alert-icon.warning { background: #fef3c7; }
    .ms-alert-icon.warning svg { fill: #d97706; }
    .ms-alert-icon.info { background: #dbeafe; }
    .ms-alert-icon.info svg { fill: #2563eb; }
    .ms-alert-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--corp-gray-900);
        margin-bottom: 4px;
    }
    .ms-alert-desc {
        font-size: 0.9rem;
        color: var(--corp-gray-500);
        line-height: 1.6;
    }

    /* Stats grid */
    .ms-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .ms-stat {
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--card-radius);
        padding: 20px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        transition: all 0.2s ease;
    }
    .ms-stat:hover {
        border-color: var(--corp-gray-300);
        box-shadow: var(--card-shadow-hover);
    }
    .ms-stat-icon {
        width: 44px;
        height: 44px;
        min-width: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .ms-stat-icon svg { width: 22px; height: 22px; }
    .ms-stat-icon.blue { background: #eff6ff; }
    .ms-stat-icon.blue svg { fill: #2563eb; }
    .ms-stat-icon.green { background: #dcfce7; }
    .ms-stat-icon.green svg { fill: #16a34a; }
    .ms-stat-icon.amber { background: #fef3c7; }
    .ms-stat-icon.amber svg { fill: #d97706; }
    .ms-stat-icon.slate { background: #f1f5f9; }
    .ms-stat-icon.slate svg { fill: #475569; }
    .ms-stat-label {
        font-size: 0.72rem;
        font-weight: 500;
        color: var(--corp-gray-500);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    }
    .ms-stat-value {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--corp-gray-900);
        line-height: 1;
    }

    /* Today card */
    .ms-today {
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--card-radius);
        padding: 24px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        position: relative;
        overflow: hidden;
    }
    .ms-today::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
    }
    .ms-today.working::before { background: #2563eb; }
    .ms-today.resting::before { background: #16a34a; }
    .ms-today-icon {
        width: 56px;
        height: 56px;
        min-width: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .ms-today-icon svg { width: 28px; height: 28px; }
    .ms-today-icon.working { background: #eff6ff; }
    .ms-today-icon.working svg { fill: #2563eb; }
    .ms-today-icon.resting { background: #dcfce7; }
    .ms-today-icon.resting svg { fill: #16a34a; }
    .ms-today-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    }
    .ms-today-label.working { color: #2563eb; }
    .ms-today-label.resting { color: #16a34a; }
    .ms-today-main {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--corp-gray-900);
    }
    .ms-today-sub {
        font-size: 0.85rem;
        color: var(--corp-gray-500);
        margin-top: 2px;
    }

    /* Weekly timeline */
    .ms-week {
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--card-radius);
        padding: 24px;
        margin-bottom: 24px;
    }
    .ms-week-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--corp-gray-900);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ms-week-title svg { width: 18px; height: 18px; fill: var(--corp-gray-400); }
    .ms-week-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 8px;
    }
    .ms-week-day {
        border-radius: 10px;
        padding: 12px 8px;
        text-align: center;
        border: 1px solid var(--corp-gray-200);
        transition: all 0.15s ease;
    }
    .ms-week-day.has-work {
        background: #eff6ff;
        border-color: #bfdbfe;
    }
    .ms-week-day.is-rest {
        background: var(--corp-gray-50);
        border-color: var(--corp-gray-200);
    }
    .ms-week-day.is-today {
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
    }
    .ms-week-day.is-today.has-work {
        background: #dbeafe;
    }
    .ms-week-day-name {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--corp-gray-500);
        margin-bottom: 4px;
    }
    .ms-week-day-num {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--corp-gray-800);
        margin-bottom: 6px;
    }
    .ms-week-day.is-today .ms-week-day-num { color: #2563eb; }
    .ms-week-day-hours {
        font-size: 0.72rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 10px;
        display: inline-block;
    }
    .ms-week-day.has-work .ms-week-day-hours {
        background: #2563eb;
        color: #fff;
    }
    .ms-week-day.is-rest .ms-week-day-hours {
        background: var(--corp-gray-200);
        color: var(--corp-gray-600);
    }
    .ms-week-day-range {
        font-size: 0.65rem;
        color: var(--corp-gray-500);
        margin-top: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Calendar */
    .ms-calendar {
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--card-radius);
        margin-bottom: 24px;
        overflow: hidden;
    }
    .ms-calendar-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--corp-gray-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .ms-calendar-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--corp-gray-900);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ms-calendar-title svg { width: 18px; height: 18px; fill: var(--corp-gray-400); }
    .ms-calendar-legend {
        display: flex;
        gap: 16px;
        align-items: center;
    }
    .ms-legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        color: var(--corp-gray-500);
    }
    .ms-legend-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    .ms-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }
    .ms-cal-head {
        padding: 10px 4px;
        text-align: center;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--corp-gray-500);
        background: var(--corp-gray-50);
        border-bottom: 1px solid var(--corp-gray-200);
    }
    .ms-cal-day {
        padding: 10px 6px;
        min-height: 80px;
        border-bottom: 1px solid var(--corp-gray-100);
        border-right: 1px solid var(--corp-gray-100);
        position: relative;
        transition: background 0.15s;
    }
    .ms-cal-day:nth-child(7n) { border-right: none; }
    .ms-cal-day:hover { background: var(--corp-gray-50); }
    .ms-cal-day.empty {
        background: var(--corp-gray-50);
        min-height: 40px;
    }
    .ms-cal-day.today {
        background: #eff6ff;
    }
    .ms-cal-day-num {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--corp-gray-700);
        margin-bottom: 4px;
    }
    .ms-cal-day.today .ms-cal-day-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        background: #2563eb;
        color: #fff;
        border-radius: 50%;
        font-size: 0.72rem;
    }
    .ms-cal-day-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .ms-cal-hours-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.68rem;
        font-weight: 600;
    }
    .ms-cal-hours-badge.work {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .ms-cal-hours-badge.rest {
        color: var(--corp-gray-400);
        font-weight: 500;
    }
    .ms-cal-hours-badge.extra {
        background: #fef3c7;
        color: #92400e;
    }
    .ms-cal-time {
        font-size: 0.62rem;
        color: var(--corp-gray-400);
        margin-top: 2px;
    }
    .ms-cal-dot {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }
    .ms-cal-dot.work { background: #2563eb; }
    .ms-cal-dot.nocturno { background: #475569; }
    .ms-cal-dot.extra { background: #d97706; }

    /* Detail Table */
    .ms-detail {
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--card-radius);
        overflow: hidden;
    }
    .ms-detail-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--corp-gray-200);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ms-detail-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--corp-gray-900);
    }
    .ms-detail-title svg { width: 18px; height: 18px; fill: var(--corp-gray-400); }
    .ms-table {
        width: 100%;
        border-collapse: collapse;
    }
    .ms-table th {
        padding: 10px 16px;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--corp-gray-500);
        background: var(--corp-gray-50);
        border-bottom: 1px solid var(--corp-gray-200);
        text-align: left;
    }
    .ms-table td {
        padding: 12px 16px;
        font-size: 0.85rem;
        color: var(--corp-gray-700);
        border-bottom: 1px solid var(--corp-gray-100);
    }
    .ms-table tr:last-child td { border-bottom: none; }
    .ms-table tbody tr:nth-child(even) {
        background: var(--corp-gray-50);
    }
    .ms-table tbody tr:hover {
        background: #f8fafc;
    }
    .ms-table tbody tr.row-today {
        background: #eff6ff;
    }
    .ms-table tbody tr.row-today:hover {
        background: #dbeafe;
    }
    .ms-table .cell-date {
        font-weight: 600;
        color: var(--corp-gray-900);
    }
    .ms-table .cell-today-tag {
        display: inline-block;
        font-size: 0.65rem;
        font-weight: 700;
        color: #2563eb;
        margin-left: 6px;
        padding: 1px 6px;
        background: #dbeafe;
        border-radius: 4px;
    }
    .ms-table .cell-hours {
        font-weight: 700;
        color: var(--corp-primary);
    }
    .ms-type-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
    }
    .ms-type-badge.normal { background: #dcfce7; color: #15803d; }
    .ms-type-badge.nocturno { background: #f1f5f9; color: #334155; }
    .ms-type-badge.extra { background: #fef3c7; color: #92400e; }
    .ms-type-badge svg { width: 12px; height: 12px; fill: currentColor; }

    /* Check-in */
    .ms-checkin-area {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }
    .ms-checkin-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 10px;
        border: 2px solid #2563eb;
        background: #fff;
        color: #2563eb;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .ms-checkin-btn:hover {
        background: #eff6ff;
    }
    .ms-checkin-btn svg { width: 18px; height: 18px; }
    .ms-checkin-btn.checked {
        background: #16a34a;
        border-color: #16a34a;
        color: #fff;
    }
    .ms-checkin-btn.checked:hover {
        background: #15803d;
        border-color: #15803d;
    }
    .ms-checkin-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    .ms-checkin-time {
        font-size: 0.72rem;
        color: var(--corp-gray-500);
        text-align: center;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .ms-stats { grid-template-columns: repeat(2, 1fr); }
        .ms-week-grid { grid-template-columns: repeat(4, 1fr); }
    }
    @media (max-width: 768px) {
        .ms-header { flex-direction: column; gap: 16px; }
        .ms-stats { grid-template-columns: 1fr; }
        .ms-week-grid { grid-template-columns: repeat(3, 1fr); }
        .ms-today { flex-direction: column; text-align: center; padding-left: 16px; }
        .ms-today::before { width: 100%; height: 4px; bottom: auto; }
        .ms-calendar-header { flex-direction: column; gap: 12px; align-items: flex-start; }
        .ms-cal-day { min-height: 60px; padding: 6px 4px; }
    }
    @media (max-width: 480px) {
        .ms-week-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>
STYLE;

ob_start();
?>

<!-- Breadcrumb -->
<div class="form-breadcrumb" style="margin-bottom: 12px;">
    <a href="<?= BASE_URL ?>/dashboard">Inicio</a>
    <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
    <span>Mi Horario</span>
</div>

<!-- Header -->
<div class="ms-header">
    <div>
        <h1><?= $saludo ?>, <?= $userName ?></h1>
        <p>Consulta tu horario y turnos asignados para este periodo.</p>
        <?php if ($advisor && $campaignName): ?>
        <div class="ms-campaign-badge">
            <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
            <?= $campaignName ?>
        </div>
        <?php endif; ?>
    </div>
    <div style="text-align: right;">
        <div class="ms-period-box">
            <div>
                <div class="ms-period-month"><?= $monthNames[$scheduleMonth] ?> <?= $scheduleYear ?></div>
                <div class="ms-period-year"><?= date('d/m/Y') ?></div>
            </div>
        </div>
        <?php if ($currentSchedule): ?>
        <div style="margin-top: 8px;">
            <span class="ms-period-status" style="background: #dcfce7; color: #15803d;">
                <svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:currentColor;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                Aprobado
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$advisor): ?>
<!-- No advisor linked -->
<div class="ms-alert">
    <div class="ms-alert-icon warning">
        <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
    </div>
    <div>
        <div class="ms-alert-title">No hay horario asignado</div>
        <div class="ms-alert-desc">
            Tu cuenta de usuario aun no esta vinculada a un registro de asesor en el sistema.
            Contacta al coordinador para que te asigne a una campana y puedas consultar tu horario.
        </div>
    </div>
</div>

<?php elseif (empty($assignments)): ?>
<!-- No approved schedule -->
<div class="ms-alert">
    <div class="ms-alert-icon info">
        <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
    </div>
    <div>
        <div class="ms-alert-title">Sin horario para <?= $monthNames[$scheduleMonth] ?> <?= $scheduleYear ?></div>
        <div class="ms-alert-desc">
            Aun no hay un horario aprobado para este mes. El supervisor debe generar y enviar
            el horario para aprobacion del coordinador. Vuelve a consultar mas tarde.
        </div>
    </div>
</div>

<?php else: ?>

<!-- Stats Row -->
<div class="ms-stats">
    <div class="ms-stat">
        <div class="ms-stat-icon blue">
            <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
        </div>
        <div>
            <div class="ms-stat-label">Horas Programadas</div>
            <div class="ms-stat-value"><?= $effectiveHours ?></div>
        </div>
    </div>
    <div class="ms-stat">
        <div class="ms-stat-icon green">
            <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
        </div>
        <div>
            <div class="ms-stat-label">Dias de Trabajo</div>
            <div class="ms-stat-value"><?= $workDays ?></div>
        </div>
    </div>
    <div class="ms-stat">
        <div class="ms-stat-icon amber">
            <svg viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
        </div>
        <div>
            <div class="ms-stat-label">Promedio Horas/Dia</div>
            <div class="ms-stat-value"><?= $avgHoursPerDay ?></div>
        </div>
    </div>
    <div class="ms-stat">
        <div class="ms-stat-icon slate">
            <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/></svg>
        </div>
        <div>
            <div class="ms-stat-label">Dias de Descanso</div>
            <div class="ms-stat-value"><?= $restDays ?></div>
        </div>
    </div>
</div>

<!-- Today's Shift Card -->
<?php
$todayInRange = ($today >= sprintf('%04d-%02d-01', $scheduleYear, $scheduleMonth)
    && $today <= sprintf('%04d-%02d-%02d', $scheduleYear, $scheduleMonth, $daysInMonth));
$myScheduleId = $currentSchedule['id'] ?? 0;
$myAdvisorId = $advisor['id'] ?? 0;
$hasCheckedIn = !empty($todayCheckin);
$checkinTimeStr = $hasCheckedIn ? date('H:i', strtotime($todayCheckin)) : '';
?>
<?php if ($todayInRange): ?>
<div class="ms-today <?= $todayShift ? 'working' : 'resting' ?>">
    <div class="ms-today-icon <?= $todayShift ? 'working' : 'resting' ?>">
        <?php if ($todayShift): ?>
        <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
        <?php else: ?>
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        <?php endif; ?>
    </div>
    <div style="flex:1;">
        <div class="ms-today-label <?= $todayShift ? 'working' : 'resting' ?>">
            Hoy, <?= $dayNamesLong[(int)date('w')] ?> <?= date('d') ?>
        </div>
        <?php if ($todayShift): ?>
            <?php
            $todayRange = myScheduleHoursToBlocks($todayHours, $todayTypes);
            $todayWorkHours = 0;
            $todayBreakHours = 0;
            foreach ($todayTypes as $h => $t) {
                if ($t === 'break') $todayBreakHours += 0.5;
                else $todayWorkHours++;
            }
            $todayTotalH = $todayWorkHours + $todayBreakHours;
            ?>
            <div class="ms-today-main">Trabajas de <?= $todayRange ?> (<?= $todayTotalH ?>h)</div>
            <?php if ($todayBreakHours > 0): ?>
            <div class="ms-today-sub">Incluye <?= $todayBreakHours ?>h de break</div>
            <?php endif; ?>
        <?php else: ?>
            <div class="ms-today-main">Hoy es tu dia de descanso</div>
            <div class="ms-today-sub">Disfruta tu tiempo libre y recarga energias</div>
        <?php endif; ?>
    </div>
    <?php if ($todayShift && $myScheduleId): ?>
    <div class="ms-checkin-area">
        <button type="button" class="ms-checkin-btn <?= $hasCheckedIn ? 'checked' : '' ?>"
                id="btnMyCheckin"
                data-schedule="<?= $myScheduleId ?>"
                data-advisor="<?= $myAdvisorId ?>"
                onclick="doMyCheckin()">
            <?php if ($hasCheckedIn): ?>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            <span>Check-in hecho</span>
            <?php else: ?>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            <span>Hacer Check-in</span>
            <?php endif; ?>
        </button>
        <?php if ($hasCheckedIn): ?>
        <div class="ms-checkin-time" id="checkinTime">Registrado a las <?= $checkinTimeStr ?></div>
        <?php else: ?>
        <div class="ms-checkin-time" id="checkinTime">Confirma tu asistencia de hoy</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Weekly Timeline -->
<div class="ms-week">
    <div class="ms-week-title">
        <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
        Esta Semana
    </div>
    <div class="ms-week-grid">
        <?php foreach ($weekDays as $idx => $wd):
            $wdShift = $assignmentsByDate[$wd] ?? null;
            $isToday = ($wd === $today);
            $hasWork = !empty($wdShift);
            $dayNum = date('d', strtotime($wd));
            $cssClass = 'ms-week-day';
            if ($hasWork) $cssClass .= ' has-work';
            else $cssClass .= ' is-rest';
            if ($isToday) $cssClass .= ' is-today';
        ?>
        <div class="<?= $cssClass ?>">
            <div class="ms-week-day-name"><?= $dayNamesShort[$idx] ?></div>
            <div class="ms-week-day-num"><?= $dayNum ?></div>
            <?php if ($hasWork):
                $wdHours = $wdShift['hours'];
                sort($wdHours);
                $wdCount = 0;
                foreach ($wdShift['types'] as $t) {
                    $wdCount += ($t === 'break') ? 0.5 : 1;
                }
            ?>
                <div class="ms-week-day-hours"><?= $wdCount ?>h</div>
                <div class="ms-week-day-range"><?= sprintf('%02d-%02d', min($wdHours), max($wdHours) + 1) ?></div>
            <?php else: ?>
                <div class="ms-week-day-hours">Libre</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Monthly Calendar -->
<?php
$firstDay = mktime(0, 0, 0, $scheduleMonth, 1, $scheduleYear);
$startDow = (int)date('N', $firstDay); // 1=Mon, 7=Sun
$calDayNames = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];
?>
<div class="ms-calendar">
    <div class="ms-calendar-header">
        <div class="ms-calendar-title">
            <svg viewBox="0 0 24 24"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V10h16v11zm0-13H4V5h16v3z"/></svg>
            Calendario de <?= $monthNames[$scheduleMonth] ?> <?= $scheduleYear ?>
        </div>
        <div class="ms-calendar-legend">
            <div class="ms-legend-item">
                <div class="ms-legend-dot" style="background: #2563eb;"></div> Trabajo
            </div>
            <div class="ms-legend-item">
                <div class="ms-legend-dot" style="background: #475569;"></div> Nocturno
            </div>
            <div class="ms-legend-item">
                <div class="ms-legend-dot" style="background: #d97706;"></div> Extra
            </div>
        </div>
    </div>
    <div class="ms-calendar-grid">
        <?php foreach ($calDayNames as $dn): ?>
        <div class="ms-cal-head"><?= $dn ?></div>
        <?php endforeach; ?>

        <?php
        // Empty cells before first day
        for ($e = 1; $e < $startDow; $e++):
        ?>
        <div class="ms-cal-day empty"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $dateStr = sprintf('%04d-%02d-%02d', $scheduleYear, $scheduleMonth, $day);
            $dayData = $assignmentsByDate[$dateStr] ?? null;
            $isToday = ($dateStr === $today);
            $hasWork = !empty($dayData);
            $cellClass = 'ms-cal-day';
            if ($isToday) $cellClass .= ' today';

            $dayHCount = 0;
            $isNocturno = false;
            $hasExtra = false;
            $timeRange = '';

            if ($hasWork) {
                $dHours = $dayData['hours'];
                sort($dHours);
                foreach ($dayData['types'] as $t) {
                    $dayHCount += ($t === 'break') ? 0.5 : 1;
                }
                $isNocturno = (min($dHours) >= 22 || max($dHours) <= 6);
                // Check for extra hours in assignments
                foreach ($assignments as $a) {
                    if ($a['fecha'] === $dateStr && !empty($a['es_extra'])) {
                        $hasExtra = true;
                        break;
                    }
                }
                $timeRange = sprintf('%02d:00-%02d:00', min($dHours), max($dHours) + 1);
            }
        ?>
        <div class="<?= $cellClass ?>">
            <div class="ms-cal-day-num"><?= $day ?></div>
            <?php if ($hasWork): ?>
                <?php if ($isNocturno): ?>
                    <div class="ms-cal-dot nocturno"></div>
                <?php elseif ($hasExtra): ?>
                    <div class="ms-cal-dot extra"></div>
                <?php else: ?>
                    <div class="ms-cal-dot work"></div>
                <?php endif; ?>
                <div class="ms-cal-day-info">
                    <span class="ms-cal-hours-badge <?= $hasExtra ? 'extra' : 'work' ?>"><?= $dayHCount ?>h</span>
                    <span class="ms-cal-time"><?= $timeRange ?></span>
                </div>
            <?php else: ?>
                <span class="ms-cal-hours-badge rest">Descanso</span>
            <?php endif; ?>
        </div>
        <?php endfor; ?>

        <?php
        // Trailing empty cells
        $lastDow = (int)date('N', mktime(0, 0, 0, $scheduleMonth, $daysInMonth, $scheduleYear));
        for ($e = $lastDow + 1; $e <= 7; $e++):
        ?>
        <div class="ms-cal-day empty"></div>
        <?php endfor; ?>
    </div>
</div>

<!-- Shift Detail Table -->
<div class="ms-detail">
    <div class="ms-detail-header">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#94a3b8;"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
        <span class="ms-detail-title">Detalle de Turnos</span>
    </div>
    <div style="overflow-x: auto;">
        <table class="ms-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Dia</th>
                    <th>Horario</th>
                    <th>Horas</th>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sortedDates = array_keys($assignmentsByDate);
                sort($sortedDates);
                foreach ($sortedDates as $dateStr):
                    $dayData = $assignmentsByDate[$dateStr];
                    $dHours = $dayData['hours'];
                    $dTypes = $dayData['types'];
                    sort($dHours);

                    $hCount = 0;
                    foreach ($dTypes as $t) {
                        $hCount += ($t === 'break') ? 0.5 : 1;
                    }
                    $firstH = min($dHours);
                    $lastH = max($dHours);
                    $isToday = ($dateStr === $today);
                    $isNocturno = ($firstH >= 22 || $lastH <= 6);
                    $hasExtraRow = false;
                    foreach ($assignments as $a) {
                        if ($a['fecha'] === $dateStr && !empty($a['es_extra'])) {
                            $hasExtraRow = true;
                            break;
                        }
                    }
                    $dow = (int)date('w', strtotime($dateStr));
                    $timeRange = myScheduleHoursToBlocks($dHours, $dTypes);
                ?>
                <tr class="<?= $isToday ? 'row-today' : '' ?>">
                    <td class="cell-date">
                        <?= date('d/m/Y', strtotime($dateStr)) ?>
                        <?php if ($isToday): ?>
                        <span class="cell-today-tag">HOY</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $dayNamesLong[$dow] ?></td>
                    <td><?= $timeRange ?></td>
                    <td class="cell-hours"><?= $hCount ?>h</td>
                    <td>
                        <?php if ($hasExtraRow): ?>
                        <span class="ms-type-badge extra">
                            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                            Extra
                        </span>
                        <?php elseif ($isNocturno): ?>
                        <span class="ms-type-badge nocturno">
                            <svg viewBox="0 0 24 24"><path d="M9.37 5.51A7.35 7.35 0 0 0 9.1 7.5c0 4.08 3.32 7.4 7.4 7.4.68 0 1.35-.09 1.99-.27A7.014 7.014 0 0 1 12 19c-3.86 0-7-3.14-7-7 0-2.93 1.81-5.45 4.37-6.49zM12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
                            Nocturno
                        </span>
                        <?php else: ?>
                        <span class="ms-type-badge normal">
                            <svg viewBox="0 0 24 24"><path d="M6.76 4.84l-1.8-1.79-1.41 1.41 1.79 1.79 1.42-1.41zM4 10.5H1v2h3v-2zm9-9.95h-2V3.5h2V.55zm7.45 3.91l-1.41-1.41-1.79 1.79 1.41 1.41 1.79-1.79zm-3.21 13.7l1.79 1.8 1.41-1.41-1.8-1.79-1.4 1.4zM20 10.5v2h3v-2h-3zm-8-5c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm-1 16.95h2V19.5h-2v2.95zm-7.45-3.91l1.41 1.41 1.79-1.8-1.41-1.41-1.79 1.8z"/></svg>
                            Normal
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();

$appUrl = $_ENV['APP_URL'] ?? '/system-horario/TurnoFlow/public';

$extraScripts = [];
$csrfToken = \App\Services\CsrfService::token();
$extraScripts[] = "<script>const BASE_URL = '{$appUrl}'; const CSRF_TOKEN = '{$csrfToken}';</script>";
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Scroll today's row into view in the detail table
    var todayRow = document.querySelector('.row-today');
    if (todayRow) {
        todayRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    // Scroll today into view in the calendar
    var todayCell = document.querySelector('.ms-cal-day.today');
    if (todayCell) {
        todayCell.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
});

function doMyCheckin() {
    var btn = document.getElementById('btnMyCheckin');
    if (!btn || btn.disabled) return;

    var scheduleId = btn.dataset.schedule;
    var advisorId = btn.dataset.advisor;
    var fecha = new Date().toISOString().slice(0, 10);

    btn.disabled = true;

    fetch(BASE_URL + '/schedules/' + scheduleId + '/checkin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify({ advisor_id: advisorId, fecha: fecha })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        var timeEl = document.getElementById('checkinTime');

        if (data.checked) {
            btn.classList.add('checked');
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg><span>Check-in hecho</span>';
            if (timeEl) timeEl.textContent = 'Registrado a las ' + data.time;
        } else {
            btn.classList.remove('checked');
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg><span>Hacer Check-in</span>';
            if (timeEl) timeEl.textContent = 'Confirma tu asistencia de hoy';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        console.error('Error en check-in:', err);
        if (typeof showToast === 'function') showToast('Error al registrar el check-in. Intenta de nuevo.', 'error');
        else alert('Error al registrar el check-in. Intenta de nuevo.');
    });
}
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
