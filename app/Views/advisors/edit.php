<?php
$pageTitle = 'Editar Asesor';
$currentPage = 'advisors';

ob_start();
?>

<div class="card card-custom">
    <div class="card-header">
        <h3 class="card-title">
            Editar Asesor: <?= htmlspecialchars($advisor['nombres'] . ' ' . $advisor['apellidos']) ?>
        </h3>
        <div class="card-toolbar">
            <a href="<?= BASE_URL ?>/advisors" class="btn btn-light-primary font-weight-bolder">
                <i class="la la-arrow-left"></i> Volver
            </a>
        </div>
    </div>
    <form action="<?= BASE_URL ?>/advisors/<?= $advisor['id'] ?>" method="POST">
        <div class="card-body">
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Nombres <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <input type="text" name="nombres" class="form-control" value="<?= htmlspecialchars($advisor['nombres']) ?>" required>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Apellidos <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <input type="text" name="apellidos" class="form-control" value="<?= htmlspecialchars($advisor['apellidos']) ?>" required>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Cedula</label>
                <div class="col-lg-9">
                    <input type="text" name="cedula" class="form-control" value="<?= htmlspecialchars($advisor['cedula'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Campana <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <select name="campaign_id" class="form-control" required>
                        <option value="">Seleccione una campana...</option>
                        <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?= $campaign['id'] ?>" <?= $campaign['id'] == $advisor['campaign_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($campaign['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Tipo de Contrato</label>
                <div class="col-lg-9">
                    <select name="tipo_contrato" class="form-control">
                        <option value="completo" <?= $advisor['tipo_contrato'] === 'completo' ? 'selected' : '' ?>>Tiempo Completo</option>
                        <option value="parcial" <?= $advisor['tipo_contrato'] === 'parcial' ? 'selected' : '' ?>>Tiempo Parcial</option>
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Estado</label>
                <div class="col-lg-9">
                    <select name="estado" class="form-control">
                        <option value="activo" <?= $advisor['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= $advisor['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                        <option value="licencia" <?= $advisor['estado'] === 'licencia' ? 'selected' : '' ?>>En Licencia</option>
                    </select>
                </div>
            </div>

            <div class="separator separator-dashed my-8"></div>
            <h4 class="mb-6">Restricciones y Permisos</h4>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Tiene VPN</label>
                <div class="col-lg-9">
                    <span class="switch switch-outline switch-icon switch-success">
                        <label>
                            <input type="checkbox" name="tiene_vpn" value="1" <?= $advisor['tiene_vpn'] ? 'checked' : '' ?>>
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
                            <input type="checkbox" name="permite_extras" value="1" <?= $advisor['permite_extras'] ? 'checked' : '' ?>>
                            <span></span>
                        </label>
                    </span>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Max. Horas por Dia</label>
                <div class="col-lg-9">
                    <input type="number" name="max_horas_dia" class="form-control" value="<?= $advisor['constraint_max_horas'] ?? 10 ?>" min="8" max="16">
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
                    <a href="<?= BASE_URL ?>/advisors/<?= $advisor['id'] ?>/constraints" class="btn btn-warning mr-2">
                        <i class="la la-cog"></i> Configurar Restricciones
                    </a>
                    <a href="<?= BASE_URL ?>/advisors" class="btn btn-secondary">Cancelar</a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();

include APP_PATH . '/Views/layouts/main.php';
?>
