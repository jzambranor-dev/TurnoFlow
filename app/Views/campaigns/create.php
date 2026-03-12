<?php
$pageTitle = 'Nueva Campana';
$currentPage = 'campaigns';

ob_start();
?>

<div class="page-container-md">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Nueva Campana</h1>
            <p class="page-header-subtitle">Configura una nueva campaña de operacion</p>
        </div>
        <a href="<?= BASE_URL ?>/campaigns" class="btn btn-secondary">
            <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Volver
        </a>
    </div>

    <form action="<?= BASE_URL ?>/campaigns" method="POST">
        <!-- Datos Basicos -->
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
                    Información General
                </div>
            </div>
            <div class="panel-body">
                <div class="form-row">
                    <label class="form-label">Nombre <span class="required">*</span></label>
                    <div class="form-row-content">
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Videollamada, Soporte Tecnico" required>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Cliente</label>
                    <div class="form-row-content">
                        <input type="text" name="cliente" class="form-control" placeholder="Nombre del cliente o contratante">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Supervisor <span class="required">*</span></label>
                    <div class="form-row-content">
                        <select name="supervisor_id" class="form-control" required>
                            <option value="">Seleccióne un supervisor...</option>
                            <?php foreach ($supervisors as $supervisor): ?>
                            <option value="<?= $supervisor['id'] ?>"><?= htmlspecialchars($supervisor['nombre_completo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuración de Operación -->
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                    Configuración de Operación
                </div>
            </div>
            <div class="panel-body">
                <div class="form-row">
                    <label class="form-label">Horario de Operación</label>
                    <div class="form-row-content">
                        <div class="form-grid-2">
                            <div class="input-group">
                                <span class="input-prepend">Desde</span>
                                <select name="hora_inicio_operacion" class="form-control">
                                    <?php for ($h = 0; $h <= 23; $h++): ?>
                                    <option value="<?= $h ?>" <?= $h == 0 ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="input-group">
                                <span class="input-prepend">Hasta</span>
                                <select name="hora_fin_operacion" class="form-control">
                                    <?php for ($h = 0; $h <= 23; $h++): ?>
                                    <option value="<?= $h ?>" <?= $h == 23 ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-hint">Rango de horas en que opera la campaña (0:00 a 23:00 para 24h)</div>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Max. Horas por Dia</label>
                    <div class="form-row-content">
                        <input type="number" name="max_horas_dia" class="form-control" value="10" min="8" max="16" style="max-width: 120px;">
                        <div class="form-hint">Maximo de horas que un asesor puede trabajar por dia en esta campaña</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Opciones Especiales -->
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58a.49.49 0 00.12-.61l-1.92-3.32a.49.49 0 00-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 00-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58a.49.49 0 00-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6A3.6 3.6 0 1115.6 12 3.611 3.611 0 0112 15.6z"/></svg>
                    Opciones Especiales
                </div>
            </div>
            <div class="panel-body">
                <div class="form-row">
                    <label class="form-label">Velada (Turno Nocturno)</label>
                    <div class="form-row-content">
                        <label class="form-check">
                            <input type="checkbox" name="tiene_velada" value="1" id="tieneVelada">
                            <span>Si, opera en horario nocturno</span>
                        </label>
                        <div class="form-hint">La campaña opera en horario nocturno (22:00 - 06:00)</div>
                    </div>
                </div>

                <div id="vpnNocturnoRow" style="display: none;">
                    <div class="form-row">
                        <label class="form-label">VPN para Nocturno</label>
                        <div class="form-row-content">
                            <label class="form-check">
                                <input type="checkbox" name="requiere_vpn_nocturno" value="1">
                                <span>Si, requiere VPN para turnos nocturnos</span>
                            </label>
                            <div class="form-hint">Solo asesores con VPN pueden cubrir turnos nocturnos</div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Break (Descanso)</label>
                    <div class="form-row-content">
                        <label class="form-check">
                            <input type="checkbox" name="tiene_break" value="1" id="tieneBreak">
                            <span>Si, los asesores tienen break dentro del turno</span>
                        </label>
                        <div id="breakDuracionRow" style="display: none; margin-top: 10px;">
                            <div class="input-group" style="max-width: 200px;">
                                <span class="input-prepend">Duracion</span>
                                <select name="duracion_break_min" class="form-control">
                                    <option value="15">15 min</option>
                                    <option value="30" selected>30 min</option>
                                    <option value="45">45 min</option>
                                    <option value="60">60 min</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-hint">El break se asigna dentro de la jornada y cuenta para el dimensionamiento (se muestra como 0.5 en el horario)</div>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Permite Horas Extra</label>
                    <div class="form-row-content">
                        <label class="form-check">
                            <input type="checkbox" name="permite_horas_extra" value="1" checked>
                            <span>Si, permite horas extra</span>
                        </label>
                        <div class="form-hint">Los asesores pueden trabajar mas de 8 horas diarias</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="<?= BASE_URL ?>/campaigns" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                Crear Campana
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();

$extraScripts = ['
<script>
    document.getElementById("tieneVelada").addEventListener("change", function() {
        document.getElementById("vpnNocturnoRow").style.display = this.checked ? "block" : "none";
    });
    document.getElementById("tieneBreak").addEventListener("change", function() {
        document.getElementById("breakDuracionRow").style.display = this.checked ? "block" : "none";
    });
</script>
'];

include APP_PATH . '/Views/layouts/main.php';
?>
