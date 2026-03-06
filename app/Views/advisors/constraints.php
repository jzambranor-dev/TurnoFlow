<?php
$pageTitle = 'Restricciones del Asesor';
$currentPage = 'advisors';

// Parse dias_descanso array from PostgreSQL format
$diasDescanso = [];
if (!empty($advisor['dias_descanso'])) {
    $dias = trim($advisor['dias_descanso'], '{}');
    if ($dias !== '') {
        $diasDescanso = array_map('intval', explode(',', $dias));
    }
}

ob_start();
?>

<div class="card card-custom">
    <div class="card-header">
        <h3 class="card-title">
            <i class="la la-cog text-warning mr-2"></i>
            Restricciones: <?= htmlspecialchars($advisor['nombres'] . ' ' . $advisor['apellidos']) ?>
        </h3>
        <div class="card-toolbar">
            <a href="<?= BASE_URL ?>/advisors" class="btn btn-light-primary font-weight-bolder">
                <i class="la la-arrow-left"></i> Volver
            </a>
        </div>
    </div>
    <form action="<?= BASE_URL ?>/advisors/<?= $advisor['id'] ?>/constraints" method="POST">
        <div class="card-body">
            <div class="alert alert-custom alert-light-info fade show mb-8" role="alert">
                <div class="alert-icon"><i class="la la-info-circle"></i></div>
                <div class="alert-text">
                    Las restricciones configuradas aqui afectan como el motor de asignacion genera los horarios para este asesor.
                </div>
            </div>

            <h4 class="mb-6">Permisos de Trabajo</h4>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Tiene VPN</label>
                <div class="col-lg-9">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="tiene_vpn" value="1" <?= $advisor['tiene_vpn'] ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
                        <span>Si</span>
                    </label>
                    <span class="form-text text-muted">Puede cubrir turnos nocturnos que requieren VPN</span>
                </div>
            </div>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Permite Horas Extra</label>
                <div class="col-lg-9">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="permite_extras" value="1" <?= $advisor['permite_extras'] ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
                        <span>Si</span>
                    </label>
                    <span class="form-text text-muted">Puede trabajar mas de 8 horas diarias</span>
                </div>
            </div>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Max. Horas por Dia</label>
                <div class="col-lg-9">
                    <input type="number" name="max_horas_dia" class="form-control" value="<?= $advisor['max_horas_dia'] ?? 10 ?>" min="8" max="16">
                    <span class="form-text text-muted">Limite maximo de horas por dia (8-16)</span>
                </div>
            </div>

            <div class="separator separator-dashed my-8"></div>
            <h4 class="mb-6">Horario Fijo de Contrato</h4>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Rango de Horas Permitido</label>
                <div class="col-lg-9">
                    <div class="row">
                        <div class="col-6">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Desde</span>
                                </div>
                                <select name="hora_inicio_contrato" class="form-control">
                                    <option value="">Sin restriccion</option>
                                    <?php for ($h = 0; $h <= 23; $h++): ?>
                                    <option value="<?= $h ?>" <?= $advisor['hora_inicio_contrato'] !== null && (int)$advisor['hora_inicio_contrato'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Hasta</span>
                                </div>
                                <select name="hora_fin_contrato" class="form-control">
                                    <option value="">Sin restriccion</option>
                                    <?php for ($h = 0; $h <= 23; $h++): ?>
                                    <option value="<?= $h ?>" <?= $advisor['hora_fin_contrato'] !== null && (int)$advisor['hora_fin_contrato'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <span class="form-text text-muted">Horario en que PUEDE trabajar (ej: 09:00 a 18:00 para asesores de ventas). Dejar vacio para horario flexible.</span>
                </div>
            </div>

            <div class="separator separator-dashed my-8"></div>
            <h4 class="mb-6">Dias Libres Fijos</h4>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Dias de Descanso Fijos</label>
                <div class="col-lg-9">
                    <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                        <?php
                        $dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
                        foreach ($dias as $i => $dia):
                        ?>
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="checkbox" name="dias_descanso[]" value="<?= $i ?>" <?= in_array($i, $diasDescanso) ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
                            <?= $dia ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <span class="form-text text-muted">Dias que SIEMPRE libra este asesor. El motor reducira estos dias solo si es absolutamente necesario para cubrir dimensionamiento.</span>
                </div>
            </div>

            <div class="separator separator-dashed my-8"></div>
            <h4 class="mb-6">Restriccion Medica</h4>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Tiene Restriccion Medica</label>
                <div class="col-lg-9">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="tiene_restriccion_medica" value="1" id="tieneRestriccion" <?= $advisor['tiene_restriccion_medica'] ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
                        <span>Si</span>
                    </label>
                </div>
            </div>

            <div id="restriccionMedicaFields" style="<?= $advisor['tiene_restriccion_medica'] ? '' : 'display: none;' ?>">
                <div class="form-group row">
                    <label class="col-lg-3 col-form-label">Descripcion</label>
                    <div class="col-lg-9">
                        <textarea name="descripcion_restriccion" class="form-control" rows="3"><?= htmlspecialchars($advisor['descripcion_restriccion'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-lg-3 col-form-label">Horas Restringidas</label>
                    <div class="col-lg-9">
                        <div class="row">
                            <div class="col-6">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Desde</span>
                                    </div>
                                    <select name="restriccion_hora_inicio" class="form-control">
                                        <option value="">Sin restriccion</option>
                                        <?php for ($h = 0; $h <= 23; $h++): ?>
                                        <option value="<?= $h ?>" <?= $advisor['restriccion_hora_inicio'] !== null && (int)$advisor['restriccion_hora_inicio'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Hasta</span>
                                    </div>
                                    <select name="restriccion_hora_fin" class="form-control">
                                        <option value="">Sin restriccion</option>
                                        <?php for ($h = 0; $h <= 23; $h++): ?>
                                        <option value="<?= $h ?>" <?= $advisor['restriccion_hora_fin'] !== null && (int)$advisor['restriccion_hora_fin'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <span class="form-text text-muted">Rango de horas que NO puede trabajar</span>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-lg-3 col-form-label">Vigencia Hasta</label>
                    <div class="col-lg-9">
                        <input type="date" name="restriccion_fecha_hasta" class="form-control" value="<?= $advisor['restriccion_fecha_hasta'] ?? '' ?>">
                        <span class="form-text text-muted">Fecha hasta cuando aplica la restriccion (dejar vacio si es permanente)</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col-lg-3"></div>
                <div class="col-lg-9">
                    <button type="submit" class="btn btn-warning mr-2">
                        <i class="la la-save"></i> Guardar Restricciones
                    </button>
                    <a href="<?= BASE_URL ?>/advisors" class="btn btn-secondary">Cancelar</a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();

$extraScripts = ['
<script>
    document.getElementById("tieneRestriccion").addEventListener("change", function() {
        var fields = document.getElementById("restriccionMedicaFields");
        if (this.checked) {
            fields.style.display = "block";
        } else {
            fields.style.display = "none";
        }
    });
</script>
'];

include APP_PATH . '/Views/layouts/main.php';
?>
