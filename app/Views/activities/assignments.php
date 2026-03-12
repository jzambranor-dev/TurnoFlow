<?php
/**
 * TurnoFlow - Asignaciónes de Asesores a Actividad
 */

$diasNombres = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];

ob_start();
?>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Asignar Asesores</h1>
            <p class="page-header-subtitle">
                Actividad: <strong style="color: <?= htmlspecialchars($activity['color']) ?>"><?= htmlspecialchars($activity['nombre']) ?></strong>
                &mdash; Campaña: <strong><?= htmlspecialchars($campaign['nombre']) ?></strong>
            </p>
        </div>
        <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/activities" class="btn btn-light">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Volver a Actividades
        </a>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= $_SESSION['flash_success'] ?></div>
    <?php unset($_SESSION['flash_success']); endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <!-- Panel: Formulario de asignación -->
        <div class="data-panel">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    Agregar Asesor
                </div>
            </div>
            <div class="panel-body">
                <?php if (empty($availableAdvisors)): ?>
                <p style="color: var(--corp-gray-400); text-align: center; padding: 2rem 0;">
                    Todos los asesores de esta campaña ya estan asignados a esta actividad.
                </p>
                <?php else: ?>
                <form method="POST" action="<?= BASE_URL ?>/activities/<?= $activity['id'] ?>/assignments">
                    <div class="form-group">
                        <label class="form-label required">Asesor</label>
                        <select name="advisor_id" class="form-control" required>
                            <option value="">-- Selecciónar asesor --</option>
                            <?php foreach ($availableAdvisors as $advisor): ?>
                            <option value="<?= $advisor['id'] ?>">
                                <?= htmlspecialchars($advisor['apellidos'] . ', ' . $advisor['nombres']) ?>
                                <?= $advisor['cedula'] ? ' (' . $advisor['cedula'] . ')' : '' ?>
                                <?= !empty($advisor['is_shared']) ? ' (P)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Hora Inicio</label>
                            <select name="hora_inicio" class="form-control" required>
                                <?php for ($h = 0; $h <= 23; $h++): ?>
                                <option value="<?= $h ?>" <?= $h === 8 ? 'selected' : '' ?>>
                                    <?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?>:00
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Hora Fin</label>
                            <select name="hora_fin" class="form-control" required>
                                <?php for ($h = 0; $h <= 23; $h++): ?>
                                <option value="<?= $h ?>" <?= $h === 16 ? 'selected' : '' ?>>
                                    <?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?>:00
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Dias de la semana</label>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php foreach ($diasNombres as $i => $dia): ?>
                            <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; padding: 0.4rem 0.75rem; border: 1px solid var(--corp-gray-200); border-radius: 6px; font-size: 0.85rem;">
                                <input type="checkbox" name="dias_semana[]" value="<?= $i ?>" <?= $i < 5 ? 'checked' : '' ?>>
                                <?= $dia ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            Asignar Asesor
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Panel: Asignaciónes actuales -->
        <div class="data-panel">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    Asesores Asignados (<?= count($currentAssignments) ?>)
                </div>
            </div>

            <?php if (empty($currentAssignments)): ?>
            <div class="panel-body">
                <p style="color: var(--corp-gray-400); text-align: center; padding: 2rem 0;">
                    No hay asesores asignados a esta actividad.
                </p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Asesor</th>
                            <th>Horario</th>
                            <th>Dias</th>
                            <th class="text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currentAssignments as $asg): ?>
                        <?php
                            $dias = [];
                            $diasRaw = trim($asg['dias_semana'] ?? '{}', '{}');
                            if (!empty($diasRaw)) {
                                foreach (explode(',', $diasRaw) as $d) {
                                    $d = (int)trim($d);
                                    $dias[] = $diasNombres[$d] ?? '?';
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <span class="cell-main"><?= htmlspecialchars($asg['apellidos'] . ', ' . $asg['nombres']) ?></span>
                                    <span class="cell-sub"><?= htmlspecialchars($asg['cedula'] ?? '') ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-neutral cell-mono">
                                    <?= str_pad((string)$asg['hora_inicio'], 2, '0', STR_PAD_LEFT) ?>:00 - <?= str_pad((string)$asg['hora_fin'], 2, '0', STR_PAD_LEFT) ?>:00
                                </span>
                                <span class="cell-sub"><?= (int)$asg['hora_fin'] - (int)$asg['hora_inicio'] ?>h</span>
                            </td>
                            <td>
                                <span style="font-size: 0.8rem;"><?= implode(', ', $dias) ?></span>
                            </td>
                            <td class="text-right">
                                <a href="<?= BASE_URL ?>/activities/assignments/<?= $asg['id'] ?>/remove"
                                   class="action-btn delete"
                                   title="Quitar"
                                   onclick="return confirm('Quitar este asesor de la actividad?')">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include APP_PATH . '/Views/layouts/main.php';
?>
