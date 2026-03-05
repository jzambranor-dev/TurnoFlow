<?php
$pageTitle = 'Campanas';
$currentPage = 'campaigns';

ob_start();
?>

<div class="card card-custom gutter-b">
    <div class="card-header flex-wrap border-0 pt-6 pb-0">
        <div class="card-title">
            <h3 class="card-label">
                Listado de Campanas
                <span class="d-block text-muted pt-2 font-size-sm">Gestiona las campanas del call center</span>
            </h3>
        </div>
        <div class="card-toolbar">
            <a href="<?= BASE_URL ?>/campaigns/create" class="btn btn-primary font-weight-bolder">
                <i class="la la-plus"></i> Nueva Campana
            </a>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover table-checkable" id="kt_datatable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Cliente</th>
                    <th>Supervisor</th>
                    <th>Asesores</th>
                    <th>Velada</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                <tr>
                    <td><?= $campaign['id'] ?></td>
                    <td>
                        <span class="font-weight-bolder"><?= htmlspecialchars($campaign['nombre']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($campaign['cliente'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($campaign['supervisor_nombre'] ?? '-') ?></td>
                    <td>
                        <span class="label label-lg label-light-primary label-inline">
                            <?= $campaign['total_asesores'] ?? 0 ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($campaign['tiene_velada']): ?>
                            <span class="label label-success label-dot mr-2"></span>
                            <span class="font-weight-bold text-success">Si</span>
                        <?php else: ?>
                            <span class="label label-danger label-dot mr-2"></span>
                            <span class="font-weight-bold text-danger">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $estadoClass = [
                            'activa' => 'success',
                            'inactiva' => 'danger',
                            'pausada' => 'warning'
                        ][$campaign['estado']] ?? 'secondary';
                        ?>
                        <span class="label label-lg label-light-<?= $estadoClass ?> label-inline">
                            <?= ucfirst($campaign['estado']) ?>
                        </span>
                    </td>
                    <td nowrap="nowrap">
                        <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/edit" class="btn btn-sm btn-clean btn-icon" title="Editar">
                            <i class="la la-edit text-primary"></i>
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

// Scripts adicionales para DataTables
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
