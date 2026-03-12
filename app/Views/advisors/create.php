<?php
$pageTitle = 'Nuevo Asesor';
$currentPage = 'advisors';

ob_start();
?>

<div class="page-container-md">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Nuevo Asesor</h1>
            <p class="page-header-subtitle">Registra un nuevo asesor en el sistema</p>
        </div>
        <a href="<?= BASE_URL ?>/advisors" class="btn btn-secondary">
            <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Volver
        </a>
    </div>

    <?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>/advisors" method="POST">
        <!-- Datos Basicos -->
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    Datos Basicos
                </div>
            </div>
            <div class="panel-body">
                <div class="form-row">
                    <label class="form-label">Nombres <span class="required">*</span></label>
                    <div class="form-row-content">
                        <input type="text" name="nombres" class="form-control" placeholder="Ingrese nombres" required>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Apellidos <span class="required">*</span></label>
                    <div class="form-row-content">
                        <input type="text" name="apellidos" class="form-control" placeholder="Ingrese apellidos" required>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Cedula</label>
                    <div class="form-row-content">
                        <input type="text" name="cedula" class="form-control" placeholder="Ingrese cedula">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Campaña <span class="required">*</span></label>
                    <div class="form-row-content">
                        <select name="campaign_id" class="form-control" required>
                            <option value="">Seleccióne una campaña...</option>
                            <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?= $campaign['id'] ?>"><?= htmlspecialchars($campaign['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Tipo de Contrato</label>
                    <div class="form-row-content">
                        <select name="tipo_contrato" class="form-control" style="max-width: 250px;">
                            <option value="completo">Tiempo Completo</option>
                            <option value="parcial">Tiempo Parcial</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Restricciónes y Permisos -->
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                    Restricciónes y Permisos
                </div>
            </div>
            <div class="panel-body">
                <div class="form-row">
                    <label class="form-label">Tiene VPN</label>
                    <div class="form-row-content">
                        <label class="form-check">
                            <input type="checkbox" name="tiene_vpn" value="1">
                            <span>Si, puede cubrir turnos nocturnos</span>
                        </label>
                        <div class="form-hint">Permite asignar turnos nocturnos que requieren VPN</div>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Permite Horas Extra</label>
                    <div class="form-row-content">
                        <label class="form-check">
                            <input type="checkbox" name="permite_extras" value="1" checked>
                            <span>Si, puede trabajar mas de 8 horas</span>
                        </label>
                        <div class="form-hint">El asesor puede trabajar horas adicionales a las 8h base</div>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Max. Horas por Dia</label>
                    <div class="form-row-content">
                        <input type="number" name="max_horas_dia" class="form-control" value="10" min="8" max="16" style="max-width: 120px;">
                        <div class="form-hint">Maximo de horas que puede trabajar en un dia (8-16)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="<?= BASE_URL ?>/advisors" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                Guardar Asesor
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include APP_PATH . '/Views/layouts/main.php';
?>
