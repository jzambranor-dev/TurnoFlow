<?php
$pageTitle = 'Mi Horario';
$currentPage = 'my-schedule';

// Organizar asignaciónes por fecha
$assignmentsByDate = [];
$totalHours = 0;
$totalDays = 0;

if (!empty($assignments)) {
    foreach ($assignments as $a) {
        $key = $a['fecha'];
        if (!isset($assignmentsByDate[$key])) {
            $assignmentsByDate[$key] = [];
            $totalDays++;
        }
        $assignmentsByDate[$key][] = $a['hora'];
        $totalHours++;
    }
}

// Calcular horas por semana
$weeklyHours = [];
foreach ($assignmentsByDate as $date => $hours) {
    $weekNum = date('W', strtotime($date));
    if (!isset($weeklyHours[$weekNum])) {
        $weeklyHours[$weekNum] = 0;
    }
    $weeklyHours[$weekNum] += count($hours);
}

$currentMonth = date('n');
$currentYear = date('Y');
$monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

ob_start();
?>

<!-- Header con info del usuario -->
<div class="card card-custom gutter-b">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <div class="symbol symbol-60 symbol-xxl-100 mr-5 align-self-start align-self-xxl-center">
                <div class="symbol-label" style="background-image:url('<?= BASE_URL ?>/../dist/assets/media/svg/avatars/001-boy.svg')"></div>
            </div>
            <div>
                <h3 class="font-weight-bolder font-size-h4 text-dark-75 mb-1">
                    <?= htmlspecialchars($_SESSION['user']['nombre'] . ' ' . $_SESSION['user']['apellido']) ?>
                </h3>
                <div class="text-muted font-weight-bold">
                    <?php if ($advisor): ?>
                        <span class="label label-lg label-light-primary label-inline mr-2">
                            <?= htmlspecialchars($advisor['campaign_id'] ? ($currentSchedule['campaign_nombre'] ?? 'Sin campaña') : 'Sin campaña') ?>
                        </span>
                    <?php endif; ?>
                    <span class="text-dark-50">Asesor de Call Center</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$advisor): ?>
<!-- Mensaje cuando no hay advisor asociado -->
<div class="card card-custom gutter-b">
    <div class="card-body">
        <div class="d-flex align-items-center bg-light-warning rounded p-5">
            <span class="svg-icon svg-icon-warning svg-icon-3x mr-5">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path opacity="0.3" d="M12 22C13.6569 22 15 20.6569 15 19C15 17.3431 13.6569 16 12 16C10.3431 16 9 17.3431 9 19C9 20.6569 10.3431 22 12 22Z" fill="currentColor"/>
                    <path d="M19 15V18C19 18.6 18.6 19 18 19H6C5.4 19 5 18.6 5 18V15C6.1 15 7 14.1 7 13V10C7 7.6 8.7 5.6 11 5.1V3C11 2.4 11.4 2 12 2C12.6 2 13 2.4 13 3V5.1C15.3 5.6 17 7.6 17 10V13C17 14.1 17.9 15 19 15ZM11 10C11 9.4 11.4 9 12 9C12.6 9 13 8.6 13 8C13 7.4 12.6 7 12 7C10.3 7 9 8.3 9 10C9 10.6 9.4 11 10 11C10.6 11 11 10.6 11 10Z" fill="currentColor"/>
                </svg>
            </span>
            <div class="d-flex flex-column flex-grow-1 mr-2">
                <span class="font-weight-bold text-dark-75 font-size-lg mb-1">
                    No hay horario asignado
                </span>
                <span class="text-muted font-weight-bold">
                    Tu cuenta de usuario aun no esta vinculada a un registro de asesor en el sistema.
                    Contacta al coordinador para que te asigne a una campaña.
                </span>
            </div>
        </div>
    </div>
</div>

<?php elseif (empty($assignments)): ?>
<!-- Mensaje cuando no hay horario aprobado -->
<div class="card card-custom gutter-b">
    <div class="card-body">
        <div class="d-flex align-items-center bg-light-info rounded p-5">
            <span class="svg-icon svg-icon-info svg-icon-3x mr-5">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M21 9V11C21 11.6 20.6 12 20 12H14V8H20C20.6 8 21 8.4 21 9ZM10 8H4C3.4 8 3 8.4 3 9V11C3 11.6 3.4 12 4 12H10V8Z" fill="currentColor"/>
                    <path d="M15 2C13.3 2 12 3.3 12 5V8H15C16.7 8 18 6.7 18 5C18 3.3 16.7 2 15 2Z" fill="currentColor"/>
                    <path opacity="0.3" d="M9 2C10.7 2 12 3.3 12 5V8H9C7.3 8 6 6.7 6 5C6 3.3 7.3 2 9 2ZM4 12V21C4 21.6 4.4 22 5 22H10V12H4ZM20 12V21C20 21.6 19.6 22 19 22H14V12H20Z" fill="currentColor"/>
                </svg>
            </span>
            <div class="d-flex flex-column flex-grow-1 mr-2">
                <span class="font-weight-bold text-dark-75 font-size-lg mb-1">
                    Sin horario para <?= $monthNames[$currentMonth] ?> <?= $currentYear ?>
                </span>
                <span class="text-muted font-weight-bold">
                    Aun no hay un horario aprobado para este mes. El supervisor debe generar y enviar
                    el horario para aprobacion del coordinador.
                </span>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<!-- Stats del mes -->
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="card card-custom bg-primary card-stretch gutter-b">
            <div class="card-body">
                <span class="svg-icon svg-icon-white svg-icon-3x">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="currentColor"/>
                        <path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="currentColor"/>
                        <path d="M8.8 13.1C9.2 13.1 9.5 13 9.7 12.8C9.9 12.6 10.1 12.3 10.1 11.9C10.1 11.6 10 11.3 9.8 11.1C9.6 10.9 9.3 10.8 9 10.8C8.8 10.8 8.6 10.8 8.4 10.9C8.2 11 8.1 11.1 8 11.2C7.9 11.3 7.9 11.5 7.9 11.7C7.9 12 8 12.2 8.2 12.4C8.3 12.9 8.5 13.1 8.8 13.1ZM13.7 13.1C14.1 13.1 14.4 13 14.6 12.8C14.8 12.6 15 12.3 15 11.9C15 11.6 14.9 11.3 14.7 11.1C14.5 10.9 14.2 10.8 13.9 10.8C13.7 10.8 13.5 10.8 13.3 10.9C13.1 11 13 11.1 12.9 11.2C12.8 11.3 12.8 11.5 12.8 11.7C12.8 12 12.9 12.2 13.1 12.4C13.2 12.9 13.4 13.1 13.7 13.1ZM8.8 18.1C9.2 18.1 9.5 18 9.7 17.8C9.9 17.6 10.1 17.3 10.1 16.9C10.1 16.6 10 16.3 9.8 16.1C9.6 15.9 9.3 15.8 9 15.8C8.8 15.8 8.6 15.8 8.4 15.9C8.2 16 8.1 16.1 8 16.2C7.9 16.3 7.9 16.5 7.9 16.7C7.9 17 8 17.2 8.2 17.4C8.3 17.9 8.5 18.1 8.8 18.1ZM13.7 18.1C14.1 18.1 14.4 18 14.6 17.8C14.8 17.6 15 17.3 15 16.9C15 16.6 14.9 16.3 14.7 16.1C14.5 15.9 14.2 15.8 13.9 15.8C13.7 15.8 13.5 15.8 13.3 15.9C13.1 16 13 16.1 12.9 16.2C12.8 16.3 12.8 16.5 12.8 16.7C12.8 17 12.9 17.2 13.1 17.4C13.2 17.9 13.4 18.1 13.7 18.1Z" fill="currentColor"/>
                    </svg>
                </span>
                <div class="text-inverse-primary font-weight-bolder font-size-h2 mt-3"><?= $totalHours ?></div>
                <span class="text-inverse-primary font-weight-bold font-size-sm">Horas Programadas</span>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-custom bg-success card-stretch gutter-b">
            <div class="card-body">
                <span class="svg-icon svg-icon-white svg-icon-3x">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M13 5.91517C15.8 6.41517 18 8.81519 18 11.8152C18 12.5152 17.9 13.2152 17.6 13.9152L21.1 16.6152C21.8 15.1152 22.2 13.5152 22.2 11.8152C22.2 6.91519 18.5 2.81519 13.7 2.01519C13.3 1.91519 13 2.21519 13 2.61519V5.51519C13 5.71519 13 5.81517 13 5.91517ZM11 5.91517V2.51519C11 2.11519 10.7 1.81519 10.3 1.91519C5.7 2.81519 2 6.91519 2 11.8152C2 16.2152 5.4 20.2152 9.8 21.4152C10.2 21.5152 10.5 21.2152 10.5 20.8152V17.3152C10.5 17.1152 10.4 16.9152 10.2 16.8152C7.7 15.5152 6 12.8152 6 9.81519C6 7.61519 7 5.61519 8.5 4.31519L11 5.91517Z" fill="currentColor"/>
                        <path opacity="0.3" d="M22 11.8V11.9C22 17.3 17.6 21.8 12.2 21.8C12 21.8 11.8 21.8 11.6 21.8C11.3 21.8 11 21.5 11 21.1V17.7C11 17.4 11.2 17.2 11.5 17.1C16.4 16.2 17.9 11.5 17.9 11.5C17.9 11.5 18 11.2 18.3 11.2H21.6C22 11.2 22.2 11.5 22 11.8Z" fill="currentColor"/>
                    </svg>
                </span>
                <div class="text-inverse-success font-weight-bolder font-size-h2 mt-3"><?= $totalDays ?></div>
                <span class="text-inverse-success font-weight-bold font-size-sm">Dias de Trabajo</span>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-custom bg-warning card-stretch gutter-b">
            <div class="card-body">
                <span class="svg-icon svg-icon-white svg-icon-3x">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path opacity="0.3" d="M20 15H4C2.9 15 2 14.1 2 13V7C2 6.4 2.4 6 3 6H21C21.6 6 22 6.4 22 7V13C22 14.1 21.1 15 20 15ZM13 12H11C10.5 12 10 12.4 10 13V16C10 16.5 10.4 17 11 17H13C13.6 17 14 16.6 14 16V13C14 12.4 13.6 12 13 12Z" fill="currentColor"/>
                        <path d="M14 6V5H10V6H8V5C8 3.9 8.9 3 10 3H14C15.1 3 16 3.9 16 5V6H14ZM20 15H14V16C14 16.6 13.5 17 13 17H11C10.5 17 10 16.6 10 16V15H4C3.6 15 3.3 14.9 3 14.7V18C3 19.1 3.9 20 5 20H19C20.1 20 21 19.1 21 18V14.7C20.7 14.9 20.4 15 20 15Z" fill="currentColor"/>
                    </svg>
                </span>
                <div class="text-inverse-warning font-weight-bolder font-size-h2 mt-3">
                    <?= $totalDays > 0 ? round($totalHours / $totalDays, 1) : 0 ?>
                </div>
                <span class="text-inverse-warning font-weight-bold font-size-sm">Promedio Horas/Dia</span>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-custom bg-info card-stretch gutter-b">
            <div class="card-body">
                <span class="svg-icon svg-icon-white svg-icon-3x">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M11.2929 2.70711C11.6834 2.31658 12.3166 2.31658 12.7071 2.70711L15.2929 5.29289C15.6834 5.68342 15.6834 6.31658 15.2929 6.70711L12.7071 9.29289C12.3166 9.68342 11.6834 9.68342 11.2929 9.29289L8.70711 6.70711C8.31658 6.31658 8.31658 5.68342 8.70711 5.29289L11.2929 2.70711Z" fill="currentColor"/>
                        <path d="M11.2929 14.7071C11.6834 14.3166 12.3166 14.3166 12.7071 14.7071L15.2929 17.2929C15.6834 17.6834 15.6834 18.3166 15.2929 18.7071L12.7071 21.2929C12.3166 21.6834 11.6834 21.6834 11.2929 21.2929L8.70711 18.7071C8.31658 18.3166 8.31658 17.6834 8.70711 17.2929L11.2929 14.7071Z" fill="currentColor"/>
                        <path opacity="0.3" d="M5.29289 8.70711C5.68342 8.31658 6.31658 8.31658 6.70711 8.70711L9.29289 11.2929C9.68342 11.6834 9.68342 12.3166 9.29289 12.7071L6.70711 15.2929C6.31658 15.6834 5.68342 15.6834 5.29289 15.2929L2.70711 12.7071C2.31658 12.3166 2.31658 11.6834 2.70711 11.2929L5.29289 8.70711ZM17.2929 8.70711C17.6834 8.31658 18.3166 8.31658 18.7071 8.70711L21.2929 11.2929C21.6834 11.6834 21.6834 12.3166 21.2929 12.7071L18.7071 15.2929C18.3166 15.6834 17.6834 15.6834 17.2929 15.2929L14.7071 12.7071C14.3166 12.3166 14.3166 11.6834 14.7071 11.2929L17.2929 8.70711Z" fill="currentColor"/>
                    </svg>
                </span>
                <div class="text-inverse-info font-weight-bolder font-size-h2 mt-3"><?= count($weeklyHours) ?></div>
                <span class="text-inverse-info font-weight-bold font-size-sm">Semanas de Trabajo</span>
            </div>
        </div>
    </div>
</div>

<!-- Calendario del mes -->
<div class="card card-custom gutter-b">
    <div class="card-header">
        <div class="card-title">
            <h3 class="card-label">
                <span class="svg-icon svg-icon-primary svg-icon-2x mr-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path opacity="0.3" d="M21 22H3C2.4 22 2 21.6 2 21V5C2 4.4 2.4 4 3 4H21C21.6 4 22 4.4 22 5V21C22 21.6 21.6 22 21 22Z" fill="currentColor"/>
                        <path d="M6 6C5.4 6 5 5.6 5 5V3C5 2.4 5.4 2 6 2C6.6 2 7 2.4 7 3V5C7 5.6 6.6 6 6 6ZM11 5V3C11 2.4 10.6 2 10 2C9.4 2 9 2.4 9 3V5C9 5.6 9.4 6 10 6C10.6 6 11 5.6 11 5ZM15 5V3C15 2.4 14.6 2 14 2C13.4 2 13 2.4 13 3V5C13 5.6 13.4 6 14 6C14.6 6 15 5.6 15 5ZM19 5V3C19 2.4 18.6 2 18 2C17.4 2 17 2.4 17 3V5C17 5.6 17.4 6 18 6C18.6 6 19 5.6 19 5Z" fill="currentColor"/>
                    </svg>
                </span>
                Horario de <?= $monthNames[$currentMonth] ?> <?= $currentYear ?>
                <span class="d-block text-muted pt-2 font-size-sm">
                    <?= $currentSchedule['campaign_nombre'] ?? 'Campana' ?>
                </span>
            </h3>
        </div>
        <div class="card-toolbar">
            <span class="label label-lg label-light-success label-inline">
                <i class="la la-check-circle mr-1"></i> Aprobado
            </span>
        </div>
    </div>
    <div class="card-body">
        <?php
        // Generar calendario
        $firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
        $daysInMonth = date('t', $firstDay);
        $startDayOfWeek = date('w', $firstDay); // 0 = domingo

        $dayNames = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
        ?>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr class="bg-gray-100">
                        <?php foreach ($dayNames as $dayName): ?>
                        <th class="text-center font-weight-bolder text-dark-75" style="width: 14.28%;">
                            <?= $dayName ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $day = 1;
                    $totalWeeks = ceil(($daysInMonth + $startDayOfWeek) / 7);

                    for ($week = 0; $week < $totalWeeks; $week++):
                    ?>
                    <tr>
                        <?php for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++):
                            $currentPosition = $week * 7 + $dayOfWeek;

                            if ($currentPosition < $startDayOfWeek || $day > $daysInMonth):
                        ?>
                            <td class="bg-light" style="height: 100px;"></td>
                        <?php else:
                            $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                            $dayHours = $assignmentsByDate[$dateStr] ?? [];
                            $hasWork = !empty($dayHours);
                            $isToday = ($dateStr === date('Y-m-d'));
                            $bgClass = $hasWork ? 'bg-light-primary' : '';
                            if ($isToday) $bgClass = 'bg-light-success';
                        ?>
                            <td class="<?= $bgClass ?> p-2" style="height: 100px; vertical-align: top;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="font-weight-bolder font-size-lg <?= $isToday ? 'text-success' : 'text-dark-75' ?>">
                                        <?= $day ?>
                                    </span>
                                    <?php if ($isToday): ?>
                                    <span class="label label-sm label-success">Hoy</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($hasWork):
                                    sort($dayHours);
                                    $hoursCount = count($dayHours);
                                    $firstHour = min($dayHours);
                                    $lastHour = max($dayHours);
                                ?>
                                <div class="d-flex flex-column">
                                    <span class="label label-lg label-primary label-inline mb-1">
                                        <?= $hoursCount ?>h
                                    </span>
                                    <small class="text-muted">
                                        <?= sprintf('%02d:00 - %02d:00', $firstHour, $lastHour + 1) ?>
                                    </small>
                                </div>
                                <?php else: ?>
                                <span class="text-muted font-size-sm">Descanso</span>
                                <?php endif; ?>
                            </td>
                        <?php
                            $day++;
                            endif;
                        endfor; ?>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detalle por semana -->
<div class="card card-custom">
    <div class="card-header">
        <div class="card-title">
            <h3 class="card-label">
                <span class="svg-icon svg-icon-primary svg-icon-2x mr-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M17.5 11H6.5C4 11 2 9 2 6.5C2 4 4 2 6.5 2H17.5C20 2 22 4 22 6.5C22 9 20 11 17.5 11ZM15 6.5C15 7.9 16.1 9 17.5 9C18.9 9 20 7.9 20 6.5C20 5.1 18.9 4 17.5 4C16.1 4 15 5.1 15 6.5Z" fill="currentColor"/>
                        <path opacity="0.3" d="M17.5 22H6.5C4 22 2 20 2 17.5C2 15 4 13 6.5 13H17.5C20 13 22 15 22 17.5C22 20 20 22 17.5 22ZM4 17.5C4 18.9 5.1 20 6.5 20C7.9 20 9 18.9 9 17.5C9 16.1 7.9 15 6.5 15C5.1 15 4 16.1 4 17.5Z" fill="currentColor"/>
                    </svg>
                </span>
                Detalle de Turnos
            </h3>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-head-custom table-vertical-center">
                <thead>
                    <tr class="text-left">
                        <th class="pl-0">Fecha</th>
                        <th>Dia</th>
                        <th>Horario</th>
                        <th>Horas</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $dates = array_keys($assignmentsByDate);
                    sort($dates);
                    foreach ($dates as $dateStr):
                        $hours = $assignmentsByDate[$dateStr];
                        sort($hours);
                        $hoursCount = count($hours);
                        $firstHour = min($hours);
                        $lastHour = max($hours);
                        $dayOfWeek = date('w', strtotime($dateStr));
                        $dayNames2 = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
                        $isToday = ($dateStr === date('Y-m-d'));
                        $isNocturno = ($firstHour >= 22 || $lastHour <= 6);
                    ?>
                    <tr <?= $isToday ? 'class="bg-light-success"' : '' ?>>
                        <td class="pl-0">
                            <span class="text-dark-75 font-weight-bolder d-block font-size-lg">
                                <?= date('d/m/Y', strtotime($dateStr)) ?>
                            </span>
                            <?php if ($isToday): ?>
                            <span class="text-success font-weight-bold">Hoy</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="text-dark-75 font-weight-bolder">
                                <?= $dayNames2[$dayOfWeek] ?>
                            </span>
                        </td>
                        <td>
                            <span class="text-dark-75 font-weight-bolder">
                                <?= sprintf('%02d:00 - %02d:00', $firstHour, $lastHour + 1) ?>
                            </span>
                        </td>
                        <td>
                            <span class="label label-lg label-light-primary label-inline font-weight-bolder">
                                <?= $hoursCount ?>h
                            </span>
                        </td>
                        <td>
                            <?php if ($isNocturno): ?>
                            <span class="label label-lg label-light-dark label-inline">
                                <i class="la la-moon mr-1"></i> Nocturno
                            </span>
                            <?php elseif ($hoursCount > 8): ?>
                            <span class="label label-lg label-light-warning label-inline">
                                <i class="la la-plus-circle mr-1"></i> Extra
                            </span>
                            <?php else: ?>
                            <span class="label label-lg label-light-success label-inline">
                                <i class="la la-sun mr-1"></i> Normal
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();

include APP_PATH . '/Views/layouts/main.php';
?>
