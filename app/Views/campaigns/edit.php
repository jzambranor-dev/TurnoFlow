<?php
$pageTitle = 'Editar Campana';
$currentPage = 'campaigns';

ob_start();
?>

<div class="card card-custom">
    <div class="card-header">
        <h3 class="card-title">
            Editar Campana: <?= htmlspecialchars($campaign['nombre']) ?>
        </h3>
        <div class="card-toolbar">
            <a href="<?= BASE_URL ?>/campaigns" class="btn btn-light-primary font-weight-bolder">
                <i class="la la-arrow-left"></i> Volver
            </a>
        </div>
    </div>
    <form action="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>" method="POST">
        <div class="card-body">
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Nombre <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($campaign['nombre']) ?>" required>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Cliente</label>
                <div class="col-lg-9">
                    <input type="text" name="cliente" class="form-control" value="<?= htmlspecialchars($campaign['cliente'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Supervisor <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <select name="supervisor_id" class="form-control" required>
                        <option value="">Seleccione un supervisor...</option>
                        <?php foreach ($supervisors as $supervisor): ?>
                        <option value="<?= $supervisor['id'] ?>" <?= $supervisor['id'] == $campaign['supervisor_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($supervisor['nombre_completo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Estado</label>
                <div class="col-lg-9">
                    <select name="estado" class="form-control">
                        <option value="activa" <?= $campaign['estado'] === 'activa' ? 'selected' : '' ?>>Activa</option>
                        <option value="inactiva" <?= $campaign['estado'] === 'inactiva' ? 'selected' : '' ?>>Inactiva</option>
                        <option value="pausada" <?= $campaign['estado'] === 'pausada' ? 'selected' : '' ?>>Pausada</option>
                    </select>
                </div>
            </div>

            <div class="separator separator-dashed my-8"></div>
            <h4 class="mb-6">Configuracion de Operacion</h4>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Horario de Operacion</label>
                <div class="col-lg-9">
                    <div class="row">
                        <div class="col-6">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Desde</span>
                                </div>
                                <select name="hora_inicio_operacion" class="form-control">
                                    <?php for ($h = 0; $h <= 23; $h++): ?>
                                    <option value="<?= $h ?>" <?= $h == $campaign['hora_inicio_operacion'] ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Hasta</span>
                                </div>
                                <select name="hora_fin_operacion" class="form-control">
                                    <?php for ($h = 0; $h <= 23; $h++): ?>
                                    <option value="<?= $h ?>" <?= $h == $campaign['hora_fin_operacion'] ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Max. Horas por Dia</label>
                <div class="col-lg-9">
                    <input type="number" name="max_horas_dia" class="form-control" value="<?= $campaign['max_horas_dia'] ?>" min="8" max="16">
                </div>
            </div>

            <div class="separator separator-dashed my-8"></div>
            <h4 class="mb-6">Opciones Especiales</h4>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Tiene Velada (Turno Nocturno)</label>
                <div class="col-lg-9">
                    <span class="switch switch-outline switch-icon switch-success">
                        <label>
                            <input type="checkbox" name="tiene_velada" value="1" id="tieneVelada" <?= $campaign['tiene_velada'] ? 'checked' : '' ?>>
                            <span></span>
                        </label>
                    </span>
                </div>
            </div>

            <div class="form-group row" id="vpnNocturnoRow" style="<?= $campaign['tiene_velada'] ? '' : 'display: none;' ?>">
                <label class="col-lg-3 col-form-label">Requiere VPN para Nocturno</label>
                <div class="col-lg-9">
                    <span class="switch switch-outline switch-icon switch-warning">
                        <label>
                            <input type="checkbox" name="requiere_vpn_nocturno" value="1" <?= $campaign['requiere_vpn_nocturno'] ? 'checked' : '' ?>>
                            <span></span>
                        </label>
                    </span>
                </div>
            </div>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Permite Horas Extra</label>
                <div class="col-lg-9">
                    <span class="switch switch-outline switch-icon switch-success">
                        <label>
                            <input type="checkbox" name="permite_horas_extra" value="1" <?= $campaign['permite_horas_extra'] ? 'checked' : '' ?>>
                            <span></span>
                        </label>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col-lg-3"></div>
                <div class="col-lg-9">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="la la-save"></i> Guardar Cambios
                    </button>
                    <a href="<?= BASE_URL ?>/campaigns" class="btn btn-secondary">Cancelar</a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();

$extraScripts = ['
<script>
    $("#tieneVelada").on("change", function() {
        if ($(this).is(":checked")) {
            $("#vpnNocturnoRow").slideDown();
        } else {
            $("#vpnNocturnoRow").slideUp();
        }
    });
</script>
'];

include APP_PATH . '/Views/layouts/main.php';
?>
