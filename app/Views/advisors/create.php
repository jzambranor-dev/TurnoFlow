<?php
$pageTitle = 'Nuevo Asesor';
$currentPage = 'advisors';

ob_start();
?>

<div class="card card-custom">
    <div class="card-header">
        <h3 class="card-title">
            Crear Nuevo Asesor
        </h3>
        <div class="card-toolbar">
            <a href="<?= BASE_URL ?>/advisors" class="btn btn-light-primary font-weight-bolder">
                <i class="la la-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success mx-6 mt-6 mb-0"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
    <div class="alert alert-danger mx-6 mt-6 mb-0"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>/advisors" method="POST">
        <div class="card-body">
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Nombres <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <input type="text" name="nombres" class="form-control" placeholder="Ingrese nombres" required>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Apellidos <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <input type="text" name="apellidos" class="form-control" placeholder="Ingrese apellidos" required>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Cedula</label>
                <div class="col-lg-9">
                    <input type="text" name="cedula" class="form-control" placeholder="Ingrese cedula">
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Campana <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <select name="campaign_id" class="form-control" required>
                        <option value="">Seleccione una campana...</option>
                        <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?= $campaign['id'] ?>"><?= htmlspecialchars($campaign['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Tipo de Contrato</label>
                <div class="col-lg-9">
                    <select name="tipo_contrato" class="form-control">
                        <option value="completo">Tiempo Completo</option>
                        <option value="parcial">Tiempo Parcial</option>
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
                            <input type="checkbox" name="tiene_vpn" value="1">
                            <span></span>
                        </label>
                    </span>
                    <span class="form-text text-muted">Permite asignar turnos nocturnos que requieren VPN</span>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Permite Horas Extra</label>
                <div class="col-lg-9">
                    <span class="switch switch-outline switch-icon switch-success">
                        <label>
                            <input type="checkbox" name="permite_extras" value="1" checked>
                            <span></span>
                        </label>
                    </span>
                    <span class="form-text text-muted">El asesor puede trabajar horas adicionales a las 8h base</span>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Max. Horas por Dia</label>
                <div class="col-lg-9">
                    <input type="number" name="max_horas_dia" class="form-control" value="10" min="8" max="16">
                    <span class="form-text text-muted">Maximo de horas que puede trabajar en un dia (8-16)</span>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col-lg-3"></div>
                <div class="col-lg-9">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="la la-save"></i> Guardar
                    </button>
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
