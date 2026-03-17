<?php
/**
 * TurnoFlow - Seguimiento Diario del Horario
 * Permite al supervisor confirmar cumplimiento por asesor y dia
 * Incluye check-in de asesores y validacion antes de confirmar dia
 */

$pageTitle = 'Seguimiento Diario';
$currentPage = 'schedules';

$today = date('Y-m-d');
$weekDaysShort = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
$weekDaysLong = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];

$statusLabels = [
    'presente' => ['label' => 'Presente', 'color' => '#16a34a', 'bg' => '#dcfce7'],
    'ausente' => ['label' => 'Ausente', 'color' => '#dc2626', 'bg' => '#fee2e2'],
    'tardanza' => ['label' => 'Tardanza', 'color' => '#d97706', 'bg' => '#fef3c7'],
    'salida_anticipada' => ['label' => 'Salida Anticipada', 'color' => '#9333ea', 'bg' => '#f3e8ff'],
    'licencia_medica' => ['label' => 'Licencia Medica', 'color' => '#0891b2', 'bg' => '#cffafe'],
    'maternidad' => ['label' => 'Maternidad', 'color' => '#be185d', 'bg' => '#fce7f3'],
];

// Calcular stats
$totalAdvisorDays = 0;
$confirmedCount = 0;
$presenteCount = 0;
$ausenteCount = 0;
$tardanzaCount = 0;
$otherCount = 0;
$checkinTodayTotal = 0;
$checkinTodayDone = 0;

foreach ($dates as $date) {
    if ($date > $today) continue;
    foreach ($advisorsMap as $adv) {
        $advId = $adv['id'];
        if (!isset($dailyData[$date][$advId])) continue;
        $totalAdvisorDays++;
        $key = $advId . ':' . $date;
        if (isset($attendanceMap[$key])) {
            $confirmedCount++;
            $st = $attendanceMap[$key]['status'];
            if ($st === 'presente') $presenteCount++;
            elseif ($st === 'ausente') $ausenteCount++;
            elseif ($st === 'tardanza') $tardanzaCount++;
            else $otherCount++;
        }
        // Contar check-ins de hoy
        if ($date === $today) {
            $checkinTodayTotal++;
            if (isset($checkinMap[$key])) {
                $checkinTodayDone++;
            }
        }
    }
}
$pendingCount = $totalAdvisorDays - $confirmedCount;
$checkinTodayPending = $checkinTodayTotal - $checkinTodayDone;
$allCheckedInToday = $checkinTodayTotal > 0 && $checkinTodayPending === 0;

ob_start();
?>

<div class="tracking-page">
    <!-- Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <div class="form-breadcrumb" style="margin-bottom:8px;">
                    <a href="<?= BASE_URL ?>/schedules" style="color:#2563eb;text-decoration:none;font-weight:500;">Horarios</a>
                    <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:#94a3b8;"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                    <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?= htmlspecialchars($schedule['campaign_nombre']) ?></a>
                    <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:#94a3b8;"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                    <span>Seguimiento</span>
                </div>
                <h1 class="header-title">Seguimiento Diario</h1>
                <p class="header-subtitle"><?= htmlspecialchars($schedule['campaign_nombre']) ?> — Periodo <?= str_pad((string)$schedule['periodo_mes'], 2, '0', STR_PAD_LEFT) ?>/<?= $schedule['periodo_anio'] ?></p>
            </div>
            <div class="header-actions">
                <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>" class="btn-action btn-secondary">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver al Horario
                </a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon-box" style="background:#eff6ff;">
                <svg viewBox="0 0 24 24" style="fill:#2563eb;"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?= $totalAdvisorDays ?></span>
                <span class="stat-label">Asesor-dias</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-box" style="background:#dcfce7;">
                <svg viewBox="0 0 24 24" style="fill:#16a34a;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?= $confirmedCount ?></span>
                <span class="stat-label">Confirmados</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-box" style="background:#fef3c7;">
                <svg viewBox="0 0 24 24" style="fill:#d97706;"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?= $pendingCount ?></span>
                <span class="stat-label">Pendientes</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-box" style="background:#fee2e2;">
                <svg viewBox="0 0 24 24" style="fill:#dc2626;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?= $ausenteCount ?></span>
                <span class="stat-label">Ausencias</span>
            </div>
        </div>
    </div>

    <!-- Progress -->
    <?php if ($totalAdvisorDays > 0): ?>
    <div class="progress-panel">
        <div class="progress-bar-wrap">
            <div class="progress-bar">
                <div class="progress-fill" style="width:<?= round($confirmedCount / $totalAdvisorDays * 100) ?>%;"></div>
            </div>
            <span class="progress-label"><?= round($confirmedCount / $totalAdvisorDays * 100) ?>% confirmado</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Check-in Panel (Hoy) -->
    <?php if ($checkinTodayTotal > 0): ?>
    <div class="checkin-panel">
        <div class="checkin-header">
            <div class="checkin-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                Check-in de Asesores (Hoy)
            </div>
            <div class="checkin-stats">
                <span class="checkin-badge <?= $allCheckedInToday ? 'badge-ok' : 'badge-pending' ?>">
                    <?= $checkinTodayDone ?>/<?= $checkinTodayTotal ?>
                    <?= $allCheckedInToday ? 'Completo' : 'check-ins' ?>
                </span>
            </div>
        </div>
        <div class="checkin-grid">
            <?php foreach ($advisorsMap as $adv):
                $advId = $adv['id'];
                if (!isset($dailyData[$today][$advId])) continue;
                $checkinKey = $advId . ':' . $today;
                $hasCheckin = isset($checkinMap[$checkinKey]);
                $checkinTime = $hasCheckin ? date('H:i', strtotime($checkinMap[$checkinKey])) : '';
            ?>
            <div class="checkin-item <?= $hasCheckin ? 'checked-in' : '' ?>"
                 data-advisor="<?= $advId ?>" data-date="<?= $today ?>"
                 onclick="toggleCheckin(this, <?= $advId ?>, '<?= $today ?>')">
                <span class="checkin-check">
                    <?php if ($hasCheckin): ?>
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                    <?php endif; ?>
                </span>
                <span class="checkin-name"><?= htmlspecialchars($adv['name']) ?></span>
                <?php if ($hasCheckin): ?>
                <span class="checkin-time"><?= $checkinTime ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$allCheckedInToday && !$canBypassCheckin): ?>
        <div class="checkin-warning">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
            Faltan <?= $checkinTodayPending ?> asesor(es) por hacer check-in. El boton "Confirmar todos presente" se habilitara cuando todos hayan hecho check-in.
        </div>
        <?php elseif (!$allCheckedInToday && $canBypassCheckin): ?>
        <div class="checkin-warning" style="background:#eff6ff;border-top-color:#bfdbfe;color:#1e40af;">
            <svg viewBox="0 0 24 24" fill="currentColor" style="fill:#2563eb;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            Faltan <?= $checkinTodayPending ?> asesor(es) por hacer check-in. Puedes confirmar asistencia sin esperar los check-ins.
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Tracking Table -->
    <div class="tracking-panel" id="trackingPanel" data-schedule="<?= $schedule['id'] ?>">
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                Registro de Asistencia
            </div>
            <div class="panel-actions">
                <?php $btnLocked = !$allCheckedInToday && !$canBypassCheckin; ?>
                <button type="button" class="btn-action btn-confirm-all <?= $btnLocked ? 'btn-locked' : '' ?>"
                        id="btnConfirmAllDay" onclick="confirmAllDay()"
                        <?= $btnLocked ? 'disabled' : '' ?>
                        title="<?= $btnLocked ? 'Todos los asesores deben hacer check-in primero' : 'Confirmar todos como presente hoy' ?>">
                    <?php if ($btnLocked): ?>
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/></svg>
                    <?php endif; ?>
                    Confirmar todos presente (hoy)
                </button>
            </div>
        </div>

        <div class="table-wrap" id="tableWrap">
            <table class="tracking-table">
                <thead>
                    <tr>
                        <th class="sticky-col">Asesor</th>
                        <?php foreach ($dates as $date): ?>
                        <?php
                            $stamp = strtotime($date);
                            $wd = (int)date('w', $stamp);
                            $isWeekend = in_array($wd, [0, 6], true);
                            $isPast = $date < $today;
                            $isToday = $date === $today;
                            $isFuture = $date > $today;
                        ?>
                        <th class="date-col <?= $isWeekend ? 'is-weekend' : '' ?> <?= $isToday ? 'is-today today-col' : '' ?> <?= $isFuture ? 'is-future' : '' ?>"
                            <?= $isToday ? 'id="todayCol"' : '' ?>>
                            <span class="th-day"><?= $weekDaysShort[$wd] ?></span>
                            <span class="th-num"><?= date('d', $stamp) ?></span>
                            <?php if ($isToday): ?>
                            <span class="th-today-badge">HOY</span>
                            <?php endif; ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advisorsMap as $adv): ?>
                    <?php $advId = $adv['id']; ?>
                    <tr>
                        <td class="sticky-col advisor-name"><?= htmlspecialchars($adv['name']) ?></td>
                        <?php foreach ($dates as $date): ?>
                        <?php
                            $stamp = strtotime($date);
                            $wd = (int)date('w', $stamp);
                            $isWeekend = in_array($wd, [0, 6], true);
                            $isPast = $date < $today;
                            $isToday = $date === $today;
                            $isFuture = $date > $today;
                            $hasAssignment = isset($dailyData[$date][$advId]);
                            $key = $advId . ':' . $date;
                            $att = $attendanceMap[$key] ?? null;
                            $attStatus = $att['status'] ?? '';
                            $attNotas = $att['notas'] ?? '';
                            $hasCheckin = isset($checkinMap[$key]);
                            $scheduledHours = 0;
                            if ($hasAssignment) {
                                $scheduledHours = ($dailyData[$date][$advId]['hours'] ?? 0) + ($dailyData[$date][$advId]['break_hours'] ?? 0);
                            }
                        ?>
                        <td class="track-cell <?= $isWeekend ? 'is-weekend' : '' ?> <?= $isToday ? 'is-today' : '' ?> <?= $isFuture ? 'is-future' : '' ?> <?= $hasCheckin && !$attStatus ? 'has-checkin' : '' ?>"
                            data-advisor="<?= $advId ?>"
                            data-date="<?= $date ?>"
                            data-has-assignment="<?= $hasAssignment ? '1' : '0' ?>"
                            data-status="<?= htmlspecialchars($attStatus) ?>"
                            data-checkin="<?= $hasCheckin ? '1' : '0' ?>"
                            <?= $attNotas ? 'title="' . htmlspecialchars($attNotas) . '"' : '' ?>>
                            <?php if (!$hasAssignment): ?>
                                <span class="cell-free">—</span>
                            <?php elseif ($isFuture): ?>
                                <span class="cell-scheduled"><?= $scheduledHours ?>h</span>
                            <?php elseif ($attStatus): ?>
                                <?php $si = $statusLabels[$attStatus] ?? ['label' => $attStatus, 'color' => '#64748b', 'bg' => '#f1f5f9']; ?>
                                <span class="cell-status" style="background:<?= $si['bg'] ?>;color:<?= $si['color'] ?>;" onclick="openStatusPicker(this, <?= $advId ?>, '<?= $date ?>')"><?= mb_substr($si['label'], 0, 3) ?></span>
                            <?php elseif ($hasCheckin): ?>
                                <span class="cell-checkin" onclick="openStatusPicker(this, <?= $advId ?>, '<?= $date ?>')" title="Check-in realizado, pendiente confirmacion">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                </span>
                            <?php else: ?>
                                <span class="cell-pending" onclick="openStatusPicker(this, <?= $advId ?>, '<?= $date ?>')">?</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tracking-footer">
            <div class="legend-row">
                <?php foreach ($statusLabels as $sk => $sv): ?>
                <span class="legend-item">
                    <span class="legend-dot" style="background:<?= $sv['color'] ?>;"></span>
                    <?= $sv['label'] ?>
                </span>
                <?php endforeach; ?>
                <span class="legend-item">
                    <span class="legend-dot" style="background:#3b82f6;"></span>
                    Check-in
                </span>
                <span class="legend-item">
                    <span class="legend-dot" style="background:#94a3b8;"></span>
                    Pendiente
                </span>
            </div>
            <button type="button" class="btn-action btn-save-tracking" id="btnSaveTracking" onclick="saveTracking()" disabled>
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                Guardar Cambios (<span id="trackingChangesCount">0</span>)
            </button>
        </div>
    </div>

    <!-- Status Picker Popup -->
    <div class="status-picker" id="statusPicker" style="display:none;">
        <div class="picker-title">Estado del asesor</div>
        <?php foreach ($statusLabels as $sk => $sv): ?>
        <button type="button" class="picker-option" data-status="<?= $sk ?>" onclick="selectStatus('<?= $sk ?>')" style="--dot-color:<?= $sv['color'] ?>;">
            <span class="picker-dot" style="background:<?= $sv['color'] ?>;"></span>
            <?= $sv['label'] ?>
        </button>
        <?php endforeach; ?>
        <div class="picker-notes">
            <input type="text" id="pickerNotas" placeholder="Notas (opcional)" maxlength="200">
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .tracking-page { max-width: 100%; }

    .page-header { margin-bottom: 20px; }
    .header-content { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; }
    .header-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0 0 4px 0; }
    .header-subtitle { font-size: 0.875rem; color: #64748b; margin: 0; }
    .header-actions { display: flex; gap: 10px; }
    .form-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #94a3b8; }

    .btn-action {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 10px 18px; border-radius: 8px; font-size: 0.875rem;
        font-weight: 600; text-decoration: none; border: none; cursor: pointer;
        transition: all 0.15s ease;
    }
    .btn-action svg { width: 18px; height: 18px; }
    .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .btn-secondary:hover { background: #e2e8f0; }

    /* Stats */
    .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
    .stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; display: flex; align-items: center; gap: 14px; }
    .stat-icon-box { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .stat-icon-box svg { width: 22px; height: 22px; }
    .stat-info { display: flex; flex-direction: column; }
    .stat-value { font-size: 1.3rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .stat-label { font-size: 0.75rem; color: #64748b; font-weight: 500; }

    /* Progress */
    .progress-panel { margin-bottom: 20px; }
    .progress-bar-wrap { display: flex; align-items: center; gap: 12px; }
    .progress-bar { flex: 1; height: 10px; background: #e2e8f0; border-radius: 10px; overflow: hidden; }
    .progress-fill { height: 100%; border-radius: 10px; background: linear-gradient(90deg, #16a34a, #22c55e); transition: width 0.6s; }
    .progress-label { font-size: 13px; font-weight: 600; color: #64748b; flex-shrink: 0; }

    /* Check-in Panel */
    .checkin-panel {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        margin-bottom: 20px; overflow: hidden;
    }
    .checkin-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 16px; background: #f0fdf4; border-bottom: 1px solid #bbf7d0;
        flex-wrap: wrap; gap: 10px;
    }
    .checkin-title {
        display: flex; align-items: center; gap: 8px;
        font-size: 14px; font-weight: 700; color: #166534;
    }
    .checkin-title svg { width: 20px; height: 20px; fill: #16a34a; }
    .checkin-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;
    }
    .badge-ok { background: #dcfce7; color: #16a34a; }
    .badge-pending { background: #fef3c7; color: #d97706; }

    .checkin-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 8px; padding: 14px 16px;
    }
    .checkin-item {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;
        background: #fff; cursor: pointer; transition: all 0.15s;
        user-select: none;
    }
    .checkin-item:hover { background: #f8fafc; border-color: #cbd5e1; }
    .checkin-item.checked-in { background: #f0fdf4; border-color: #86efac; }
    .checkin-check { display: flex; width: 22px; height: 22px; flex-shrink: 0; }
    .checkin-check svg { width: 22px; height: 22px; fill: #cbd5e1; }
    .checkin-item.checked-in .checkin-check svg { fill: #16a34a; }
    .checkin-name { font-size: 13px; font-weight: 500; color: #334155; flex: 1; }
    .checkin-time { font-size: 11px; color: #16a34a; font-weight: 600; }

    .checkin-warning {
        display: flex; align-items: center; gap: 8px;
        padding: 10px 16px; background: #fef3c7; border-top: 1px solid #fde68a;
        font-size: 12px; color: #92400e; font-weight: 500;
    }
    .checkin-warning svg { width: 16px; height: 16px; fill: #d97706; flex-shrink: 0; }

    /* Panel */
    .tracking-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .panel-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; gap: 10px; }
    .panel-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; color: #0f172a; }
    .panel-title svg { width: 20px; height: 20px; fill: #7c3aed; }

    .btn-confirm-all { background: #7c3aed; color: #fff; font-size: 12px; padding: 8px 14px; }
    .btn-confirm-all:hover:not(:disabled) { background: #6d28d9; }
    .btn-confirm-all.btn-locked { background: #94a3b8; cursor: not-allowed; opacity: 0.7; }
    .btn-confirm-all:disabled { cursor: not-allowed; }

    .table-wrap { overflow-x: auto; max-width: 100%; scroll-behavior: smooth; }

    /* Table */
    .tracking-table { width: max-content; min-width: 100%; border-collapse: collapse; font-size: 12px; }
    .tracking-table th, .tracking-table td { border: 1px solid #e2e8f0; text-align: center; padding: 4px; white-space: nowrap; }
    .tracking-table th { background: #f8fafc; font-size: 11px; color: #475569; padding: 6px 4px; }
    .tracking-table th.is-today { background: #eff6ff; border-bottom: 2px solid #2563eb; }
    .tracking-table th.is-future { color: #94a3b8; }
    .th-day { display: block; font-size: 10px; font-weight: 500; }
    .th-num { display: block; font-size: 12px; font-weight: 700; }
    .th-today-badge {
        display: block; font-size: 8px; font-weight: 800; color: #fff;
        background: #2563eb; border-radius: 3px; padding: 1px 4px;
        margin: 2px auto 0; letter-spacing: 0.5px;
    }

    .sticky-col {
        position: sticky; left: 0; z-index: 2;
        min-width: 180px; text-align: left; padding-left: 10px;
        background: #fff; font-weight: 600; color: #0f172a;
    }
    .tracking-table th.sticky-col { background: #f8fafc; z-index: 3; }

    /* Cells */
    .track-cell { width: 40px; height: 36px; position: relative; cursor: default; }
    .track-cell.is-today { background: #eff6ff; }
    .track-cell.is-weekend { background: #fef2f2; }
    .track-cell.is-future { background: #f8fafc; }
    .track-cell.has-checkin { background: #f0fdf4; }

    .cell-free { color: #cbd5e1; font-size: 14px; }
    .cell-scheduled { font-size: 10px; color: #94a3b8; font-weight: 500; }

    .cell-pending {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 6px;
        background: #f1f5f9; color: #94a3b8; font-weight: 700; font-size: 13px;
        cursor: pointer; transition: all 0.15s;
    }
    .cell-pending:hover { background: #e2e8f0; color: #475569; transform: scale(1.1); }

    .cell-checkin {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 6px;
        background: #dbeafe; cursor: pointer; transition: all 0.15s;
    }
    .cell-checkin svg { width: 16px; height: 16px; fill: #3b82f6; }
    .cell-checkin:hover { transform: scale(1.1); background: #bfdbfe; }

    .cell-status {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 6px;
        font-size: 9px; font-weight: 700; text-transform: uppercase;
        cursor: pointer; transition: all 0.15s;
    }
    .cell-status:hover { transform: scale(1.1); box-shadow: 0 2px 6px rgba(0,0,0,0.1); }

    /* Footer */
    .tracking-footer {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 16px; background: #f8fafc; border-top: 1px solid #e2e8f0;
        flex-wrap: wrap; gap: 10px;
    }
    .legend-row { display: flex; gap: 14px; flex-wrap: wrap; }
    .legend-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #64748b; }
    .legend-dot { width: 10px; height: 10px; border-radius: 50%; }

    .btn-save-tracking {
        background: #2563eb; color: #fff; font-size: 13px; padding: 10px 20px;
    }
    .btn-save-tracking:hover { background: #1d4ed8; }
    .btn-save-tracking:disabled { opacity: 0.5; cursor: not-allowed; }

    /* Status Picker */
    .status-picker {
        position: fixed; z-index: 1000;
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        padding: 8px; min-width: 200px;
    }
    .picker-title { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; padding: 6px 10px 4px; }
    .picker-option {
        display: flex; align-items: center; gap: 8px; width: 100%;
        padding: 8px 10px; border: none; background: transparent;
        font-size: 13px; font-weight: 500; color: #334155;
        cursor: pointer; border-radius: 6px; transition: background 0.1s;
    }
    .picker-option:hover { background: #f1f5f9; }
    .picker-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .picker-notes { padding: 4px 4px 4px; }
    .picker-notes input {
        width: 100%; padding: 8px 10px; border: 1px solid #e2e8f0;
        border-radius: 6px; font-size: 12px; color: #334155;
    }
    .picker-notes input:focus { outline: none; border-color: #2563eb; }

    @media (max-width: 768px) {
        .stats-row { grid-template-columns: repeat(2, 1fr); }
        .header-content { flex-direction: column; }
        .checkin-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        .stats-row { grid-template-columns: 1fr; }
    }
</style>
STYLE;

// Generar JSON del checkin map para JS
$checkinMapJson = json_encode(array_keys($checkinMap));
$appUrl = $_ENV['APP_URL'] ?? '/system-horario/TurnoFlow/public';
$csrfToken = \App\Services\CsrfService::token();
$canBypassCheckinJs = $canBypassCheckin ? 'true' : 'false';

$extraScripts = [];
$extraScripts[] = <<<'TOAST_SCRIPT'
<script>
function showToast(message, type, duration) {
    type = type || 'info';
    duration = duration || 3500;
    var container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;display:flex;flex-direction:column;gap:10px;pointer-events:none;max-width:420px;';
        document.body.appendChild(container);
    }
    var icons = {
        success: '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
        error: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>',
        warning: '<path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>',
        info: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>'
    };
    var colors = { success:'#16a34a', error:'#dc2626', warning:'#d97706', info:'#2563eb' };
    var c = colors[type] || colors.info;
    var toast = document.createElement('div');
    toast.style.cssText = 'display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:12px;background:#fff;border:1px solid #e2e8f0;border-left:4px solid '+c+';box-shadow:0 8px 30px rgba(0,0,0,0.12);font-size:0.85rem;font-weight:500;color:#334155;pointer-events:auto;opacity:0;transform:translateX(40px);transition:all 0.3s cubic-bezier(0.4,0,0.2,1);';
    toast.innerHTML = '<svg viewBox="0 0 24 24" fill="'+c+'" style="width:20px;height:20px;flex-shrink:0;">'+(icons[type]||icons.info)+'</svg><span style="flex:1;line-height:1.4;">'+message+'</span><button onclick="this.parentElement.remove()" style="background:none;border:none;font-size:18px;color:#94a3b8;cursor:pointer;padding:0 0 0 8px;">&times;</button>';
    container.appendChild(toast);
    requestAnimationFrame(function(){ toast.style.opacity='1'; toast.style.transform='translateX(0)'; });
    setTimeout(function(){
        toast.style.opacity='0'; toast.style.transform='translateX(40px)';
        setTimeout(function(){ toast.remove(); }, 300);
    }, duration);
}
function showLoading(msg) {
    msg = msg || 'Guardando...';
    var o = document.getElementById('loadingOverlay');
    if (!o) {
        o = document.createElement('div');
        o.id = 'loadingOverlay';
        o.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(15,23,42,0.35);backdrop-filter:blur(2px);display:none;align-items:center;justify-content:center;';
        document.body.appendChild(o);
    }
    o.innerHTML = '<div style="background:#fff;border-radius:16px;padding:32px 48px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.15);"><div style="width:40px;height:40px;margin:0 auto 16px;border:3px solid #e2e8f0;border-top-color:#2563eb;border-radius:50%;animation:tfspin 0.8s linear infinite;"></div><div style="font-size:0.9rem;font-weight:600;color:#475569;">'+msg+'</div></div><style>@keyframes tfspin{to{transform:rotate(360deg)}}</style>';
    o.style.display = 'flex';
}
function hideLoading() {
    var o = document.getElementById('loadingOverlay');
    if (o) o.style.display = 'none';
}
</script>
TOAST_SCRIPT;
$extraScripts[] = <<<SCRIPT
<script>
const TRACKING_BASE = '{$appUrl}';
const CSRF_TOKEN = '{$csrfToken}';
const CAN_BYPASS_CHECKIN = {$canBypassCheckinJs};
const scheduleId = document.getElementById('trackingPanel')?.dataset.schedule;
const pendingRecords = {};
const checkedInAdvisors = new Set({$checkinMapJson});
let pickerTarget = null;
let pickerAdvisor = null;
let pickerDate = null;

// Auto-scroll tabla al dia de hoy
document.addEventListener('DOMContentLoaded', function() {
    const todayCol = document.getElementById('todayCol');
    const tableWrap = document.getElementById('tableWrap');
    if (todayCol && tableWrap) {
        const stickyWidth = 190; // ancho de la columna sticky del asesor
        const colLeft = todayCol.offsetLeft;
        const wrapWidth = tableWrap.clientWidth;
        // Centrar la columna de hoy en la vista
        const scrollTo = colLeft - stickyWidth - (wrapWidth - stickyWidth) / 2 + todayCol.offsetWidth / 2;
        tableWrap.scrollLeft = Math.max(0, scrollTo);
    }
});

async function toggleCheckin(el, advisorId, date) {
    const item = el.closest('.checkin-item') || el;
    item.style.opacity = '0.5';

    try {
        const response = await fetch(TRACKING_BASE + '/schedules/' + scheduleId + '/checkin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: JSON.stringify({ advisor_id: advisorId, fecha: date })
        });

        const result = await response.json();

        if (result.success) {
            const key = advisorId + ':' + date;
            if (result.checked) {
                checkedInAdvisors.add(key);
                item.classList.add('checked-in');
                item.querySelector('.checkin-check').innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
                // Agregar hora
                const now = new Date();
                const timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
                let timeEl = item.querySelector('.checkin-time');
                if (!timeEl) {
                    timeEl = document.createElement('span');
                    timeEl.className = 'checkin-time';
                    item.appendChild(timeEl);
                }
                timeEl.textContent = timeStr;
            } else {
                checkedInAdvisors.delete(key);
                item.classList.remove('checked-in');
                item.querySelector('.checkin-check').innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>';
                const timeEl = item.querySelector('.checkin-time');
                if (timeEl) timeEl.remove();
            }

            // Actualizar tabla de tracking: marcar/desmarcar celda de check-in
            const cell = document.querySelector('.track-cell[data-advisor="' + advisorId + '"][data-date="' + date + '"]');
            if (cell && !cell.dataset.status) {
                cell.dataset.checkin = result.checked ? '1' : '0';
                if (result.checked) {
                    cell.classList.add('has-checkin');
                    cell.innerHTML = '<span class="cell-checkin" onclick="openStatusPicker(this,' + advisorId + ',&#39;' + date + '&#39;)" title="Check-in realizado, pendiente confirmacion"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></span>';
                } else {
                    cell.classList.remove('has-checkin');
                    cell.innerHTML = '<span class="cell-pending" onclick="openStatusPicker(this,' + advisorId + ',&#39;' + date + '&#39;)" >?</span>';
                }
            }

            updateConfirmAllButton();
        } else {
            showToast(result.error || 'Error desconocido', 'error', 4500);
        }
    } catch (err) {
        console.error(err);
        showToast('Error de conexion.', 'error', 4500);
    } finally {
        item.style.opacity = '1';
    }
}

function updateConfirmAllButton() {
    const today = new Date().toISOString().split('T')[0];
    const items = document.querySelectorAll('.checkin-item');
    const total = items.length;
    let checked = 0;
    items.forEach(function(item) {
        if (item.classList.contains('checked-in')) checked++;
    });

    const allChecked = total > 0 && checked === total;
    const canConfirm = allChecked || CAN_BYPASS_CHECKIN;
    const btn = document.getElementById('btnConfirmAllDay');
    const badge = document.querySelector('.checkin-badge');
    const warning = document.querySelector('.checkin-warning');

    if (btn) {
        btn.disabled = !canConfirm;
        if (canConfirm) {
            btn.classList.remove('btn-locked');
            btn.querySelector('svg').innerHTML = '<path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/>';
        } else {
            btn.classList.add('btn-locked');
            btn.querySelector('svg').innerHTML = '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>';
        }
    }

    if (badge) {
        badge.textContent = checked + '/' + total + (allChecked ? ' Completo' : ' check-ins');
        badge.className = 'checkin-badge ' + (allChecked ? 'badge-ok' : 'badge-pending');
    }

    if (warning) {
        if (allChecked) {
            warning.style.display = 'none';
        } else {
            warning.style.display = 'flex';
            const pending = total - checked;
            warning.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg> Faltan ' + pending + ' asesor(es) por hacer check-in. El boton "Confirmar todos presente" se habilitara cuando todos hayan hecho check-in.';
        }
    }
}

function openStatusPicker(el, advisorId, date) {
    const picker = document.getElementById('statusPicker');
    const rect = el.getBoundingClientRect();

    pickerTarget = el;
    pickerAdvisor = advisorId;
    pickerDate = date;

    const cell = el.closest('.track-cell');
    const existingKey = advisorId + ':' + date;
    document.getElementById('pickerNotas').value =
        pendingRecords[existingKey]?.notas || cell?.title || '';

    let top = rect.bottom + 4;
    let left = rect.left;
    if (left + 220 > window.innerWidth) left = window.innerWidth - 230;
    if (top + 300 > window.innerHeight) top = rect.top - 310;

    picker.style.top = top + 'px';
    picker.style.left = left + 'px';
    picker.style.display = 'block';
}

function selectStatus(status) {
    if (!pickerAdvisor || !pickerDate) return;

    const key = pickerAdvisor + ':' + pickerDate;
    const notas = document.getElementById('pickerNotas').value.trim();

    pendingRecords[key] = {
        advisor_id: pickerAdvisor,
        fecha: pickerDate,
        status: status,
        notas: notas
    };

    const cell = document.querySelector('.track-cell[data-advisor="' + pickerAdvisor + '"][data-date="' + pickerDate + '"]');
    if (cell) {
        const labels = {
            presente: { l: 'Pre', c: '#16a34a', bg: '#dcfce7' },
            ausente: { l: 'Aus', c: '#dc2626', bg: '#fee2e2' },
            tardanza: { l: 'Tar', c: '#d97706', bg: '#fef3c7' },
            salida_anticipada: { l: 'Sal', c: '#9333ea', bg: '#f3e8ff' },
            licencia_medica: { l: 'Lic', c: '#0891b2', bg: '#cffafe' },
            maternidad: { l: 'Mat', c: '#be185d', bg: '#fce7f3' }
        };
        const si = labels[status] || { l: '?', c: '#64748b', bg: '#f1f5f9' };
        cell.innerHTML = '<span class="cell-status" style="background:' + si.bg + ';color:' + si.c + ';" onclick="openStatusPicker(this,' + pickerAdvisor + ',\'' + pickerDate + '\')">' + si.l + '</span>';
        cell.dataset.status = status;
        cell.classList.remove('has-checkin');
        if (notas) cell.title = notas;
    }

    closePicker();
    updateTrackingCounter();
}

function closePicker() {
    document.getElementById('statusPicker').style.display = 'none';
    pickerTarget = null;
    pickerAdvisor = null;
    pickerDate = null;
}

document.addEventListener('click', function(e) {
    const picker = document.getElementById('statusPicker');
    if (!picker) return;
    if (picker.style.display === 'none') return;
    if (picker.contains(e.target)) return;
    if (e.target.closest('.cell-pending') || e.target.closest('.cell-status') || e.target.closest('.cell-checkin')) return;
    closePicker();
});

function updateTrackingCounter() {
    const count = Object.keys(pendingRecords).length;
    const el = document.getElementById('trackingChangesCount');
    const btn = document.getElementById('btnSaveTracking');
    if (el) el.textContent = count;
    if (btn) btn.disabled = count === 0;
}

function confirmAllDay() {
    const today = new Date().toISOString().split('T')[0];

    // Verificar que todos tienen check-in (bypass para admin/gerente/coordinador)
    if (!CAN_BYPASS_CHECKIN) {
        const items = document.querySelectorAll('.checkin-item');
        let allChecked = true;
        items.forEach(function(item) {
            if (!item.classList.contains('checked-in')) allChecked = false;
        });

        if (!allChecked) {
            showToast('No se puede confirmar: aun hay asesores sin check-in.', 'warning', 4500);
            return;
        }
    }

    const cells = document.querySelectorAll('.track-cell[data-date="' + today + '"][data-has-assignment="1"]');
    let count = 0;

    cells.forEach(function(cell) {
        const advId = parseInt(cell.dataset.advisor);
        const currentStatus = cell.dataset.status;
        if (currentStatus === 'presente') return;

        const key = advId + ':' + today;
        pendingRecords[key] = {
            advisor_id: advId,
            fecha: today,
            status: 'presente',
            notas: ''
        };

        cell.innerHTML = '<span class="cell-status" style="background:#dcfce7;color:#16a34a;" onclick="openStatusPicker(this,' + advId + ',\'' + today + '\')">Pre</span>';
        cell.dataset.status = 'presente';
        cell.classList.remove('has-checkin');
        count++;
    });

    updateTrackingCounter();
    if (count === 0) {
        showToast('Todos los asesores de hoy ya estan confirmados.', 'info');
    }
}

async function saveTracking() {
    const records = Object.values(pendingRecords);
    if (records.length === 0) return;

    const btn = document.getElementById('btnSaveTracking');
    btn.disabled = true;
    showLoading('Guardando ' + records.length + ' registro(s)...');

    try {
        const response = await fetch(TRACKING_BASE + '/schedules/' + scheduleId + '/attendance', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: JSON.stringify({ records: records })
        });

        const result = await response.json();

        if (result.success) {
            for (const key in pendingRecords) delete pendingRecords[key];
            updateTrackingCounter();
            showToast('Asistencia guardada: ' + result.saved + ' registros.', 'success', 4000);
        } else {
            showToast(result.error || 'Error desconocido', 'error', 4500);
        }
    } catch (err) {
        console.error(err);
        showToast('Error de conexion.', 'error', 4500);
    } finally {
        hideLoading();
        btn.disabled = Object.keys(pendingRecords).length === 0;
    }
}
</script>
<style>
.spinner {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 6px;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
