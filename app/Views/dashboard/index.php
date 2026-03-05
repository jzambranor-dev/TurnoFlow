<?php
$user = $_SESSION['user'];
$isCoordinador = $user['rol'] === 'coordinador';
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

ob_start();
?>

<div class="row">
    <!-- Stats Cards -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
        <div class="card card-custom bg-primary gutter-b" style="height: 150px">
            <div class="card-body">
                <span class="svg-icon svg-icon-3x svg-icon-white ml-n2">
                    <i class="flaticon2-group icon-3x text-white"></i>
                </span>
                <div class="text-white font-weight-bolder font-size-h2 mt-3"><?= $stats['campaigns'] ?? 0 ?></div>
                <span class="text-white font-weight-bold font-size-lg mt-1">
                    <?= $isCoordinador ? 'Campanas Activas' : 'Mis Campanas' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
        <div class="card card-custom bg-success gutter-b" style="height: 150px">
            <div class="card-body">
                <span class="svg-icon svg-icon-3x svg-icon-white ml-n2">
                    <i class="flaticon-users icon-3x text-white"></i>
                </span>
                <div class="text-white font-weight-bolder font-size-h2 mt-3"><?= $stats['advisors'] ?? 0 ?></div>
                <span class="text-white font-weight-bold font-size-lg mt-1">Asesores Activos</span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
        <div class="card card-custom bg-danger gutter-b" style="height: 150px">
            <div class="card-body">
                <span class="svg-icon svg-icon-3x svg-icon-white ml-n2">
                    <i class="flaticon-time icon-3x text-white"></i>
                </span>
                <div class="text-white font-weight-bolder font-size-h2 mt-3">
                    <?= $isCoordinador ? ($stats['pending_approvals'] ?? 0) : ($stats['draft_schedules'] ?? 0) ?>
                </div>
                <span class="text-white font-weight-bold font-size-lg mt-1">
                    <?= $isCoordinador ? 'Pendientes Aprobar' : 'Borradores' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
        <div class="card card-custom bg-info gutter-b" style="height: 150px">
            <div class="card-body">
                <span class="svg-icon svg-icon-3x svg-icon-white ml-n2">
                    <i class="flaticon-calendar-1 icon-3x text-white"></i>
                </span>
                <div class="text-white font-weight-bolder font-size-h2 mt-3"><?= date('M') ?></div>
                <span class="text-white font-weight-bold font-size-lg mt-1">Periodo <?= date('Y') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Acciones Rapidas -->
<div class="row">
    <div class="col-lg-12">
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Acciones Rapidas</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap">
                    <a href="<?= BASE_URL ?>/schedules/import" class="btn btn-light-primary font-weight-bold mr-3 mb-3">
                        <i class="flaticon-upload mr-2"></i> Importar Dimensionamiento
                    </a>
                    <a href="<?= BASE_URL ?>/schedules" class="btn btn-light-success font-weight-bold mr-3 mb-3">
                        <i class="flaticon-calendar-1 mr-2"></i> Ver Horarios
                    </a>
                    <?php if ($isCoordinador): ?>
                    <a href="<?= BASE_URL ?>/advisors" class="btn btn-light-info font-weight-bold mr-3 mb-3">
                        <i class="flaticon-users mr-2"></i> Gestionar Asesores
                    </a>
                    <a href="<?= BASE_URL ?>/campaigns" class="btn btn-light-warning font-weight-bold mr-3 mb-3">
                        <i class="flaticon2-group mr-2"></i> Gestionar Campanas
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isCoordinador && !empty($pendingSchedules)): ?>
<!-- Horarios Pendientes -->
<div class="row">
    <div class="col-lg-12">
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Horarios Pendientes de Aprobacion</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-head-custom table-vertical-center">
                        <thead>
                            <tr>
                                <th>Campana</th>
                                <th>Periodo</th>
                                <th>Enviado por</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingSchedules as $schedule): ?>
                            <tr>
                                <td>
                                    <span class="text-dark-75 font-weight-bolder d-block font-size-lg">
                                        <?= htmlspecialchars($schedule['campaign_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="label label-lg label-light-primary label-inline">
                                        <?= $schedule['periodo_mes'] ?>/<?= $schedule['periodo_anio'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($schedule['generado_por_nombre'] ?? 'N/A') ?>
                                </td>
                                <td class="text-right">
                                    <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>" class="btn btn-sm btn-light-primary font-weight-bolder">
                                        Revisar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include APP_PATH . '/Views/layouts/main.php';
?>
