<?php
$pageTitle = 'Editar Asesor';
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

<div class="page-container-md">
    <!-- Header -->
    <div class="page-header">
        <div>
            <div class="form-breadcrumb">
                <a href="<?= BASE_URL ?>/advisors">Asesores</a>
                <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                <span>Editar #<?= $advisor['id'] ?></span>
            </div>
            <h1 class="page-header-title">Editar Asesor</h1>
            <p class="page-header-subtitle"><?= htmlspecialchars($advisor['nombres'] . ' ' . $advisor['apellidos']) ?></p>
        </div>
        <a href="<?= BASE_URL ?>/advisors" class="btn btn-secondary">
            <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Volver
        </a>
    </div>

    <form action="<?= BASE_URL ?>/advisors/<?= $advisor['id'] ?>" method="POST">
        <?= \App\Services\CsrfService::field() ?>
        <!-- Datos Basicos -->
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <span class="form-step-number">1</span>
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    Datos Basicos
                </div>
            </div>
            <div class="panel-body">
                <div class="form-row">
                    <label class="form-label">Nombres <span class="required">*</span></label>
                    <div class="form-row-content">
                        <input type="text" name="nombres" class="form-control" value="<?= htmlspecialchars($advisor['nombres']) ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Apellidos <span class="required">*</span></label>
                    <div class="form-row-content">
                        <input type="text" name="apellidos" class="form-control" value="<?= htmlspecialchars($advisor['apellidos']) ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Cedula</label>
                    <div class="form-row-content">
                        <input type="text" name="cedula" class="form-control" value="<?= htmlspecialchars($advisor['cedula'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Campaña <span class="required">*</span></label>
                    <div class="form-row-content">
                        <select name="campaign_id" class="form-control" required>
                            <option value="">Seleccióne una campaña...</option>
                            <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?= $campaign['id'] ?>" <?= $campaign['id'] == $advisor['campaign_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($campaign['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Tipo de Contrato</label>
                    <div class="form-row-content">
                        <select name="tipo_contrato" class="form-control" style="max-width: 250px;">
                            <option value="completo" <?= $advisor['tipo_contrato'] === 'completo' ? 'selected' : '' ?>>Tiempo Completo</option>
                            <option value="parcial" <?= $advisor['tipo_contrato'] === 'parcial' ? 'selected' : '' ?>>Tiempo Parcial</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Estado</label>
                    <div class="form-row-content">
                        <select name="estado" class="form-control" style="max-width: 250px;">
                            <option value="activo" <?= $advisor['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="inactivo" <?= $advisor['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                            <option value="licencia" <?= $advisor['estado'] === 'licencia' ? 'selected' : '' ?>>En Licencia</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Permisos de Trabajo -->
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <span class="form-step-number">2</span>
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                    Permisos de Trabajo
                </div>
            </div>
            <div class="panel-body">
                <div class="info-box">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                    <div class="info-box-text">Estas configuraciónes afectan como el motor de asignación genera los horarios para este asesor.</div>
                </div>

                <div class="form-row">
                    <label class="form-label">Tiene VPN</label>
                    <div class="form-row-content">
                        <label class="form-check">
                            <input type="checkbox" name="tiene_vpn" value="1" <?= !empty($advisor['tiene_vpn']) ? 'checked' : '' ?>>
                            <span>Si, puede cubrir turnos nocturnos que requieren VPN</span>
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Disponible para Velada</label>
                    <div class="form-row-content">
                        <label class="form-check">
                            <input type="checkbox" name="disponible_velada" value="1" <?= !empty($advisor['disponible_velada']) ? 'checked' : '' ?>>
                            <span>Si, puede ser asignado a turnos de velada (00:00-08:00)</span>
                        </label>
                        <div class="form-hint">Los asesores de velada rotan semanalmente. Requiere VPN si la campaña lo exige.</div>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Modalidad de Trabajo</label>
                    <div class="form-row-content">
                        <div class="form-inline">
                            <label class="toggle-card <?= ($advisor['modalidad_trabajo'] ?? 'mixto') === 'presencial' ? 'selected' : '' ?>">
                                <input type="radio" name="modalidad_trabajo" value="presencial" <?= ($advisor['modalidad_trabajo'] ?? 'mixto') === 'presencial' ? 'checked' : '' ?>>
                                <div>
                                    <div class="toggle-card-title">Presencial</div>
                                    <div class="toggle-card-desc">Solo horario presencial de la campaña</div>
                                </div>
                            </label>
                            <label class="toggle-card <?= ($advisor['modalidad_trabajo'] ?? 'mixto') === 'teletrabajo' ? 'selected' : '' ?>">
                                <input type="radio" name="modalidad_trabajo" value="teletrabajo" <?= ($advisor['modalidad_trabajo'] ?? 'mixto') === 'teletrabajo' ? 'checked' : '' ?>>
                                <div>
                                    <div class="toggle-card-title">Teletrabajo</div>
                                    <div class="toggle-card-desc">Cualquier hora dentro de su contrato</div>
                                </div>
                            </label>
                            <label class="toggle-card <?= ($advisor['modalidad_trabajo'] ?? 'mixto') === 'mixto' ? 'selected' : '' ?>">
                                <input type="radio" name="modalidad_trabajo" value="mixto" <?= ($advisor['modalidad_trabajo'] ?? 'mixto') === 'mixto' ? 'checked' : '' ?>>
                                <div>
                                    <div class="toggle-card-title">Mixto</div>
                                    <div class="toggle-card-desc">Presencial + puede extender con teletrabajo</div>
                                </div>
                            </label>
                        </div>
                        <div class="form-hint">Define en que horarios puede trabajar segun la configuración de la campaña</div>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Permite Horas Extra</label>
                    <div class="form-row-content">
                        <label class="form-check">
                            <input type="checkbox" name="permite_extras" value="1" <?= !empty($advisor['permite_extras']) ? 'checked' : '' ?>>
                            <span>Si, puede trabajar mas de 8 horas diarias</span>
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Max. Horas por Dia</label>
                    <div class="form-row-content">
                        <input type="number" name="max_horas_dia" class="form-control" value="<?= $advisor['max_horas_dia'] ?? 10 ?>" min="8" max="16" style="max-width: 120px;">
                        <div class="form-hint">Limite maximo de horas por dia (8-16)</div>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Tipo de Horario</label>
                    <div class="form-row-content">
                        <div class="form-inline">
                            <label class="toggle-card <?= !isset($advisor['permite_horario_partido']) || $advisor['permite_horario_partido'] ? 'selected' : '' ?>" id="toggleFlexible">
                                <input type="radio" name="permite_horario_partido" value="1" <?= !isset($advisor['permite_horario_partido']) || $advisor['permite_horario_partido'] ? 'checked' : '' ?>>
                                <div>
                                    <div class="toggle-card-title">Horario Flexible (Partido)</div>
                                    <div class="toggle-card-desc">Puede tener turnos en 2 o mas bloques</div>
                                </div>
                            </label>
                            <label class="toggle-card <?= isset($advisor['permite_horario_partido']) && !$advisor['permite_horario_partido'] ? 'selected' : '' ?>" id="toggleCorrido">
                                <input type="radio" name="permite_horario_partido" value="0" <?= isset($advisor['permite_horario_partido']) && !$advisor['permite_horario_partido'] ? 'checked' : '' ?>>
                                <div>
                                    <div class="toggle-card-title">Horario Corrido</div>
                                    <div class="toggle-card-desc">Solo turnos continuos sin interrupciones</div>
                                </div>
                            </label>
                        </div>
                        <div class="form-hint">Define si el asesor puede tener huecos en su jornada o debe trabajar de corrido</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Horario de Contrato -->
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <span class="form-step-number">3</span>
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
                    Horario Fijo de Contrato
                </div>
            </div>
            <div class="panel-body">
                <div class="form-row">
                    <label class="form-label">Rango de Horas</label>
                    <div class="form-row-content">
                        <div class="form-grid-2">
                            <div class="input-group">
                                <span class="input-prepend">Desde</span>
                                <select name="hora_inicio_contrato" class="form-control">
                                    <option value="">Sin restriccion</option>
                                    <?php for ($h = 0; $h <= 23; $h++): ?>
                                    <option value="<?= $h ?>" <?= $advisor['hora_inicio_contrato'] !== null && (int)$advisor['hora_inicio_contrato'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="input-group">
                                <span class="input-prepend">Hasta</span>
                                <select name="hora_fin_contrato" class="form-control">
                                    <option value="">Sin restriccion</option>
                                    <?php for ($h = 0; $h <= 23; $h++): ?>
                                    <option value="<?= $h ?>" <?= $advisor['hora_fin_contrato'] !== null && (int)$advisor['hora_fin_contrato'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-hint">Horario en que PUEDE trabajar (ej: 09:00 a 18:00). Dejar vacio para horario flexible.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dias Libres Fijos -->
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <span class="form-step-number">4</span>
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
                    Dias Libres Fijos
                </div>
            </div>
            <div class="panel-body">
                <div class="form-row">
                    <label class="form-label">Dias de Descanso</label>
                    <div class="form-row-content">
                        <div class="form-inline" style="gap: 10px;">
                            <?php
                            $diasSemana = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
                            foreach ($diasSemana as $i => $dia):
                            ?>
                            <label class="toggle-card <?= in_array($i, $diasDescanso) ? 'selected' : '' ?>" style="padding: 8px 14px;">
                                <input type="checkbox" name="dias_descanso[]" value="<?= $i ?>" <?= in_array($i, $diasDescanso) ? 'checked' : '' ?> style="width: 16px; height: 16px; accent-color: var(--corp-primary);">
                                <span style="font-size: 0.85rem;"><?= $dia ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-hint">Dias que SIEMPRE libra este asesor. El motor respetara estos dias a menos que sea absolutamente necesario.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Restricción Médica -->
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <span class="form-step-number">5</span>
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-1.99.9-1.99 2L3 19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-1 11h-4v4h-4v-4H6v-4h4V6h4v4h4v4z"/></svg>
                    Restriccion Medica
                </div>
            </div>
            <div class="panel-body">
                <div class="form-row">
                    <label class="form-label">Tiene Restricción</label>
                    <div class="form-row-content">
                        <label class="form-check">
                            <input type="checkbox" name="tiene_restriccion_medica" value="1" id="tieneRestriccion" <?= !empty($advisor['tiene_restriccion_medica']) ? 'checked' : '' ?>>
                            <span>Si, tiene restriccion medica activa</span>
                        </label>
                    </div>
                </div>

                <div id="restriccionMedicaFields" style="<?= !empty($advisor['tiene_restriccion_medica']) ? '' : 'display: none;' ?>">
                    <div class="form-row">
                        <label class="form-label">Descripción</label>
                        <div class="form-row-content">
                            <textarea name="descripcion_restriccion" class="form-control" rows="3" placeholder="Ej: No puede trabajar turnos nocturnos por indicacion medica"><?= htmlspecialchars($advisor['descripcion_restriccion'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Horas Restringidas</label>
                        <div class="form-row-content">
                            <div class="form-grid-2">
                                <div class="input-group">
                                    <span class="input-prepend">Desde</span>
                                    <select name="restriccion_hora_inicio" class="form-control">
                                        <option value="">Sin restriccion</option>
                                        <?php for ($h = 0; $h <= 23; $h++): ?>
                                        <option value="<?= $h ?>" <?= isset($advisor['restriccion_hora_inicio']) && $advisor['restriccion_hora_inicio'] !== null && (int)$advisor['restriccion_hora_inicio'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <span class="input-prepend">Hasta</span>
                                    <select name="restriccion_hora_fin" class="form-control">
                                        <option value="">Sin restriccion</option>
                                        <?php for ($h = 0; $h <= 23; $h++): ?>
                                        <option value="<?= $h ?>" <?= isset($advisor['restriccion_hora_fin']) && $advisor['restriccion_hora_fin'] !== null && (int)$advisor['restriccion_hora_fin'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-hint">Rango de horas que NO puede trabajar por restriccion medica</div>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Vigencia Hasta</label>
                        <div class="form-row-content">
                            <input type="date" name="restriccion_fecha_hasta" class="form-control" value="<?= $advisor['restriccion_fecha_hasta'] ?? '' ?>" style="max-width: 200px;">
                            <div class="form-hint">Fecha hasta cuando aplica la restriccion (dejar vacio si es permanente)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="<?= BASE_URL ?>/advisors" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                Guardar Cambios
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();

$extraScripts = ['
<script>
    document.getElementById("tieneRestriccion").addEventListener("change", function() {
        document.getElementById("restriccionMedicaFields").style.display = this.checked ? "block" : "none";
    });

    // Toggle card selection visual
    document.querySelectorAll(".toggle-card input[type=radio]").forEach(function(radio) {
        radio.addEventListener("change", function() {
            var name = this.name;
            document.querySelectorAll(".toggle-card input[name=\'" + name + "\']").forEach(function(r) {
                r.closest(".toggle-card").classList.toggle("selected", r.checked);
            });
        });
    });

    document.querySelectorAll(".toggle-card input[type=checkbox]").forEach(function(cb) {
        cb.addEventListener("change", function() {
            this.closest(".toggle-card").classList.toggle("selected", this.checked);
        });
    });
</script>
'];

include APP_PATH . '/Views/layouts/main.php';
?>
