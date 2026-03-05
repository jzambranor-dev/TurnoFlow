<?php
$pageTitle = 'Ver Horario';
$currentPage = 'schedules';

// Organizar asignaciones por fecha y asesor
$assignmentsByDate = [];
$advisorNames = [];
foreach ($assignments as $a) {
    $key = $a['fecha'];
    $advisorKey = $a['advisor_id'];
    if (!isset($assignmentsByDate[$key])) {
        $assignmentsByDate[$key] = [];
    }
    if (!isset($assignmentsByDate[$key][$advisorKey])) {
        $assignmentsByDate[$key][$advisorKey] = [];
    }
    $assignmentsByDate[$key][$advisorKey][] = $a['hora'];
    $advisorNames[$advisorKey] = $a['apellidos'] . ', ' . $a['nombres'];
}

ob_start();
?>

<div class="card card-custom gutter-b">
    <div class="card-header">
        <div class="card-title">
            <h3 class="card-label">
                Horario: <?= htmlspecialchars($schedule['campaign_nombre']) ?>
                <span class="d-block text-muted pt-2 font-size-sm">
                    Periodo: <?= $schedule['periodo_mes'] ?>/<?= $schedule['periodo_anio'] ?>
                </span>
            </h3>
        </div>
        <div class="card-toolbar">
            <?php
            $statusClass = [
                'borrador' => 'secondary',
                'enviado' => 'info',
                'aprobado' => 'success',
                'rechazado' => 'danger'
            ][$schedule['status']] ?? 'secondary';
            ?>
            <span class="label label-lg label-light-<?= $statusClass ?> label-inline mr-3">
                <?= ucfirst($schedule['status']) ?>
            </span>
            <?php if ($schedule['status'] === 'borrador'): ?>
            <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/submit" class="btn btn-primary font-weight-bolder mr-2">
                <i class="la la-paper-plane"></i> Enviar para Aprobacion
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/schedules" class="btn btn-light-primary font-weight-bolder">
                <i class="la la-arrow-left"></i> Volver
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-5">
            <div class="col-md-3">
                <div class="d-flex align-items-center bg-light-primary rounded p-5">
                    <span class="svg-icon svg-icon-3x svg-icon-primary d-block my-2 mr-4">
                        <i class="la la-calendar-check la-3x text-primary"></i>
                    </span>
                    <div>
                        <div class="text-primary font-weight-bolder font-size-h4">
                            <?= date('d/m/Y', strtotime($schedule['fecha_inicio'])) ?>
                        </div>
                        <div class="font-weight-bold text-muted">Fecha Inicio</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex align-items-center bg-light-warning rounded p-5">
                    <span class="svg-icon svg-icon-3x svg-icon-warning d-block my-2 mr-4">
                        <i class="la la-calendar-times la-3x text-warning"></i>
                    </span>
                    <div>
                        <div class="text-warning font-weight-bolder font-size-h4">
                            <?= date('d/m/Y', strtotime($schedule['fecha_fin'])) ?>
                        </div>
                        <div class="font-weight-bold text-muted">Fecha Fin</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex align-items-center bg-light-success rounded p-5">
                    <span class="svg-icon svg-icon-3x svg-icon-success d-block my-2 mr-4">
                        <i class="la la-clock la-3x text-success"></i>
                    </span>
                    <div>
                        <div class="text-success font-weight-bolder font-size-h4">
                            <?= count($assignments) ?>
                        </div>
                        <div class="font-weight-bold text-muted">Asignaciones</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex align-items-center bg-light-info rounded p-5">
                    <span class="svg-icon svg-icon-3x svg-icon-info d-block my-2 mr-4">
                        <i class="la la-users la-3x text-info"></i>
                    </span>
                    <div>
                        <div class="text-info font-weight-bolder font-size-h4">
                            <?= count($advisorNames) ?>
                        </div>
                        <div class="font-weight-bold text-muted">Asesores</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($assignments)): ?>
        <div class="alert alert-custom alert-light-warning fade show" role="alert">
            <div class="alert-icon"><i class="la la-exclamation-triangle"></i></div>
            <div class="alert-text">Este horario aun no tiene asignaciones generadas.</div>
        </div>
        <?php else: ?>

        <div class="table-responsive">
            <table class="table table-bordered table-head-custom table-vertical-center">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="pl-4">Asesor</th>
                        <?php
                        $dates = array_keys($assignmentsByDate);
                        sort($dates);
                        foreach ($dates as $date):
                            $dayName = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'][date('w', strtotime($date))];
                        ?>
                        <th class="text-center" style="min-width: 80px;">
                            <small class="d-block text-muted"><?= $dayName ?></small>
                            <?= date('d', strtotime($date)) ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advisorNames as $advisorId => $name): ?>
                    <tr>
                        <td class="pl-4">
                            <span class="font-weight-bolder"><?= htmlspecialchars($name) ?></span>
                        </td>
                        <?php foreach ($dates as $date): ?>
                        <td class="text-center">
                            <?php
                            $hours = $assignmentsByDate[$date][$advisorId] ?? [];
                            if (!empty($hours)):
                                sort($hours);
                                $hoursCount = count($hours);
                                $firstHour = min($hours);
                                $lastHour = max($hours);
                            ?>
                            <span class="label label-lg label-light-success label-inline" title="<?= implode(', ', array_map(fn($h) => sprintf('%02d:00', $h), $hours)) ?>">
                                <?= $hoursCount ?>h
                            </span>
                            <br>
                            <small class="text-muted"><?= sprintf('%02d-%02d', $firstHour, $lastHour + 1) ?></small>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
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

include APP_PATH . '/Views/layouts/main.php';
?>
