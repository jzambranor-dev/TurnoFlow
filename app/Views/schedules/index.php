<?php
$pageTitle = 'Horarios';
$currentPage = 'schedules';

ob_start();
?>

<div class="card card-custom gutter-b">
    <div class="card-header flex-wrap border-0 pt-6 pb-0">
        <div class="card-title">
            <h3 class="card-label">
                Horarios Generados
                <span class="d-block text-muted pt-2 font-size-sm">Gestiona los horarios de las campanas</span>
            </h3>
        </div>
        <div class="card-toolbar">
            <a href="<?= BASE_URL ?>/schedules/import" class="btn btn-success font-weight-bolder mr-2">
                <i class="la la-file-excel"></i> Importar Dimensionamiento
            </a>
            <a href="<?= BASE_URL ?>/schedules/generate" class="btn btn-primary font-weight-bolder">
                <i class="la la-calendar-plus"></i> Generar Horario
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($schedules)): ?>
        <div class="alert alert-custom alert-light-info fade show mb-5" role="alert">
            <div class="alert-icon"><i class="la la-info-circle"></i></div>
            <div class="alert-text">No hay horarios generados. Importa un dimensionamiento y genera el primer horario.</div>
        </div>
        <?php else: ?>
        <table class="table table-bordered table-hover table-checkable" id="kt_datatable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Campana</th>
                    <th>Periodo</th>
                    <th>Fecha Inicio</th>
                    <th>Fecha Fin</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Generado Por</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schedules as $schedule): ?>
                <tr>
                    <td><?= $schedule['id'] ?></td>
                    <td>
                        <span class="font-weight-bolder"><?= htmlspecialchars($schedule['campaign_nombre']) ?></span>
                    </td>
                    <td>
                        <span class="label label-lg label-light-primary label-inline">
                            <?= $schedule['periodo_mes'] ?>/<?= $schedule['periodo_anio'] ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y', strtotime($schedule['fecha_inicio'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($schedule['fecha_fin'])) ?></td>
                    <td>
                        <?php
                        $tipoClass = [
                            'mensual' => 'info',
                            'semanal' => 'primary',
                            'diario' => 'warning'
                        ][$schedule['tipo']] ?? 'secondary';
                        ?>
                        <span class="label label-lg label-light-<?= $tipoClass ?> label-inline">
                            <?= ucfirst($schedule['tipo']) ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $statusClass = [
                            'borrador' => 'secondary',
                            'enviado' => 'info',
                            'aprobado' => 'success',
                            'rechazado' => 'danger'
                        ][$schedule['status']] ?? 'secondary';
                        $statusIcon = [
                            'borrador' => 'la-edit',
                            'enviado' => 'la-paper-plane',
                            'aprobado' => 'la-check-circle',
                            'rechazado' => 'la-times-circle'
                        ][$schedule['status']] ?? 'la-question';
                        ?>
                        <span class="label label-lg label-light-<?= $statusClass ?> label-inline">
                            <i class="la <?= $statusIcon ?> mr-1"></i>
                            <?= ucfirst($schedule['status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($schedule['generado_por_nombre'] ?? '-') ?></td>
                    <td nowrap="nowrap">
                        <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>" class="btn btn-sm btn-clean btn-icon" title="Ver Horario">
                            <i class="la la-eye text-info"></i>
                        </a>
                        <?php if ($schedule['status'] === 'borrador'): ?>
                        <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/submit" class="btn btn-sm btn-clean btn-icon" title="Enviar para Aprobacion">
                            <i class="la la-paper-plane text-primary"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($_SESSION['user']['rol'] === 'coordinador' && $schedule['status'] === 'enviado'): ?>
                        <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/approve" class="btn btn-sm btn-clean btn-icon" title="Aprobar">
                            <i class="la la-check text-success"></i>
                        </a>
                        <a href="<?= BASE_URL ?>/schedules/<?= $schedule['id'] ?>/reject" class="btn btn-sm btn-clean btn-icon" title="Rechazar">
                            <i class="la la-times text-danger"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraStyles = ['<link href="/system-horario/TurnoFlow/dist/assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />'];
$extraScripts = ['
<script src="/system-horario/TurnoFlow/dist/assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script>
    $("#kt_datatable").DataTable({
        responsive: true,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
        },
        order: [[0, "desc"]]
    });
</script>
'];

include APP_PATH . '/Views/layouts/main.php';
?>
