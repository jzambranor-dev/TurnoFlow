<?php
$pageTitle = 'Asesores';
$currentPage = 'advisors';

ob_start();
?>

<div class="card card-custom gutter-b">
    <div class="card-header flex-wrap border-0 pt-6 pb-0">
        <div class="card-title">
            <h3 class="card-label">
                Listado de Asesores
                <span class="d-block text-muted pt-2 font-size-sm">Gestiona los asesores del call center</span>
            </h3>
        </div>
        <div class="card-toolbar">
            <a href="<?= BASE_URL ?>/advisors/create" class="btn btn-primary font-weight-bolder">
                <i class="la la-plus"></i> Nuevo Asesor
            </a>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover table-checkable" id="kt_datatable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre Completo</th>
                    <th>Cedula</th>
                    <th>Campana</th>
                    <th>Tipo Contrato</th>
                    <th>VPN</th>
                    <th>Extras</th>
                    <th>Max Horas</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($advisors as $advisor): ?>
                <tr>
                    <td><?= $advisor['id'] ?></td>
                    <td>
                        <span class="font-weight-bolder"><?= htmlspecialchars($advisor['apellidos'] . ', ' . $advisor['nombres']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($advisor['cedula'] ?? '-') ?></td>
                    <td>
                        <span class="label label-lg label-light-info label-inline">
                            <?= htmlspecialchars($advisor['campaign_nombre']) ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $contratoClass = $advisor['tipo_contrato'] === 'completo' ? 'success' : 'warning';
                        ?>
                        <span class="label label-lg label-light-<?= $contratoClass ?> label-inline">
                            <?= ucfirst($advisor['tipo_contrato']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($advisor['tiene_vpn']): ?>
                            <span class="label label-success label-dot mr-2"></span>
                            <span class="font-weight-bold text-success">Si</span>
                        <?php else: ?>
                            <span class="label label-danger label-dot mr-2"></span>
                            <span class="font-weight-bold text-danger">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($advisor['permite_extras']): ?>
                            <span class="label label-success label-dot mr-2"></span>
                            <span class="font-weight-bold text-success">Si</span>
                        <?php else: ?>
                            <span class="label label-danger label-dot mr-2"></span>
                            <span class="font-weight-bold text-danger">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="font-weight-bolder"><?= $advisor['constraint_max_horas'] ?? 10 ?>h</span>
                    </td>
                    <td>
                        <?php
                        $estadoClass = [
                            'activo' => 'success',
                            'inactivo' => 'danger',
                            'licencia' => 'warning'
                        ][$advisor['estado']] ?? 'secondary';
                        ?>
                        <span class="label label-lg label-light-<?= $estadoClass ?> label-inline">
                            <?= ucfirst($advisor['estado']) ?>
                        </span>
                    </td>
                    <td nowrap="nowrap">
                        <a href="<?= BASE_URL ?>/advisors/<?= $advisor['id'] ?>/edit" class="btn btn-sm btn-clean btn-icon" title="Editar">
                            <i class="la la-edit text-primary"></i>
                        </a>
                        <a href="<?= BASE_URL ?>/advisors/<?= $advisor['id'] ?>/constraints" class="btn btn-sm btn-clean btn-icon" title="Restricciones">
                            <i class="la la-cog text-warning"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
        }
    });
</script>
'];

include APP_PATH . '/Views/layouts/main.php';
?>
