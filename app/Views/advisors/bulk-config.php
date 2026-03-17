<?php
/**
 * TurnoFlow - Configuración Masiva de Asesores por Campaña
 */

$pageTitle = 'Configuración Masiva';
$currentPage = 'advisors';

ob_start();
?>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Configuración Masiva</h1>
            <p class="page-header-subtitle">Aplica configuraciónes a multiples asesores de una campaña</p>
        </div>
        <a href="<?= BASE_URL ?>/advisors" class="btn btn-secondary">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Volver a Asesores
        </a>
    </div>

    <?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <!-- Paso 1: Selecciónar Campaña -->
    <div class="data-panel" style="margin-bottom: 20px; padding: 20px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
            <span style="background: #2563eb; color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">1</span>
            <h3 style="margin: 0; font-size: 1rem; font-weight: 600; color: #0f172a;">Selecciónar Campaña</h3>
        </div>
        <form method="GET" action="<?= BASE_URL ?>/advisors/bulk-config" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <select name="campaign_id" id="campaign_select" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; min-width: 280px; font-size: 14px; background: #fff;">
                <option value="">-- Seleccióna una campaña --</option>
                <?php foreach ($campaigns as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($selectedCampaignId ?? null) == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Cargar Asesores</button>
        </form>
    </div>

    <?php if ($selectedCampaignId && !empty($advisors)): ?>
    <form method="POST" action="<?= BASE_URL ?>/advisors/bulk-config" id="bulkForm">
        <?= \App\Services\CsrfService::field() ?>
        <input type="hidden" name="campaign_id" value="<?= $selectedCampaignId ?>">

        <!-- Paso 2: Selecciónar Asesores -->
        <div class="data-panel" style="margin-bottom: 20px;">
            <div style="padding: 20px; border-bottom: 1px solid #e2e8f0;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                    <span style="background: #2563eb; color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">2</span>
                    <h3 style="margin: 0; font-size: 1rem; font-weight: 600; color: #0f172a;">Selecciónar Asesores</h3>
                    <span style="margin-left: auto; font-size: 0.8rem; color: #64748b;" id="selectedCount">0 selecciónados</span>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 8px;">
                    <button type="button" id="btnSelectAll" style="padding: 5px 14px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; cursor: pointer; font-size: 0.8rem; font-weight: 500; color: #475569;">Selecciónar todos</button>
                    <button type="button" id="btnDeselectAll" style="padding: 5px 14px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; cursor: pointer; font-size: 0.8rem; font-weight: 500; color: #475569;">Deselecciónar todos</button>
                </div>
            </div>
            <div style="padding: 16px; max-height: 350px; overflow-y: auto;">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px;">
                    <?php foreach ($advisors as $adv):
                        $diasRaw = trim($adv['dias_descanso'] ?? '{}', '{}');
                        $diasArr = $diasRaw !== '' ? explode(',', $diasRaw) : [];
                        $diasLabels = ['L','M','X','J','V','S','D'];
                        $diasText = [];
                        foreach ($diasArr as $d) {
                            $diasText[] = $diasLabels[(int)$d] ?? '?';
                        }
                        $hIni = $adv['hora_inicio_contrato'] ?? 0;
                        $hFin = $adv['hora_fin_contrato'] ?? 23;
                        $mod = $adv['modalidad_trabajo'] ?? 'mixto';
                    ?>
                    <label class="advisor-check-card" style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.12s ease;">
                        <input type="checkbox" name="advisor_ids[]" value="<?= $adv['id'] ?>" class="advisor-cb" style="width: 18px; height: 18px; accent-color: #2563eb; flex-shrink: 0;">
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600; font-size: 0.85rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($adv['apellidos'] . ', ' . $adv['nombres']) ?>
                            </div>
                            <div style="font-size: 0.72rem; color: #94a3b8; display: flex; gap: 8px; flex-wrap: wrap; margin-top: 2px;">
                                <span>H<?= $hIni ?>-H<?= $hFin ?></span>
                                <span><?= ucfirst($mod) ?></span>
                                <span><?= $adv['tiene_vpn'] ? 'VPN' : 'Sin VPN' ?></span>
                                <span><?= $adv['max_horas_dia'] ?? 10 ?>h max</span>
                                <?php if (!empty($diasText)): ?>
                                <span>Libre: <?= implode(',', $diasText) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Paso 3: Configuración a aplicar -->
        <div class="data-panel" style="margin-bottom: 20px;">
            <div style="padding: 20px; border-bottom: 1px solid #e2e8f0;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="background: #2563eb; color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">3</span>
                    <h3 style="margin: 0; font-size: 1rem; font-weight: 600; color: #0f172a;">Configuración a Aplicar</h3>
                </div>
                <p style="margin: 8px 0 0 36px; font-size: 0.82rem; color: #64748b;">Marca los campos que deseas modificar y establece el nuevo valor. Solo se actualizaran los campos marcados.</p>
            </div>
            <div style="padding: 20px;">
                <!-- VPN -->
                <div class="bulk-field" style="display: flex; align-items: flex-start; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f1f5f9;">
                    <label style="display: flex; align-items: center; gap: 6px; min-width: 180px; cursor: pointer;">
                        <input type="checkbox" name="fields[]" value="tiene_vpn" class="field-toggle" data-target="field_vpn" style="width: 18px; height: 18px; accent-color: #2563eb;">
                        <span style="font-weight: 600; font-size: 0.88rem; color: #334155;">Tiene VPN</span>
                    </label>
                    <div class="field-value" id="field_vpn" style="opacity: 0.4; pointer-events: none;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="tiene_vpn" value="1" style="width: 18px; height: 18px; accent-color: #16a34a;">
                            <span style="font-size: 0.85rem; color: #475569;">Si, puede cubrir turnos nocturnos</span>
                        </label>
                    </div>
                </div>

                <!-- Disponible Velada -->
                <div class="bulk-field" style="display: flex; align-items: flex-start; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f1f5f9;">
                    <label style="display: flex; align-items: center; gap: 6px; min-width: 180px; cursor: pointer;">
                        <input type="checkbox" name="fields[]" value="disponible_velada" class="field-toggle" data-target="field_velada" style="width: 18px; height: 18px; accent-color: #2563eb;">
                        <span style="font-weight: 600; font-size: 0.88rem; color: #334155;">Disponible Velada</span>
                    </label>
                    <div class="field-value" id="field_velada" style="opacity: 0.4; pointer-events: none;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="disponible_velada" value="1" style="width: 18px; height: 18px; accent-color: #16a34a;">
                            <span style="font-size: 0.85rem; color: #475569;">Si, puede hacer turno de velada (00:00-08:00)</span>
                        </label>
                    </div>
                </div>

                <!-- Permite Extras -->
                <div class="bulk-field" style="display: flex; align-items: flex-start; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f1f5f9;">
                    <label style="display: flex; align-items: center; gap: 6px; min-width: 180px; cursor: pointer;">
                        <input type="checkbox" name="fields[]" value="permite_extras" class="field-toggle" data-target="field_extras" style="width: 18px; height: 18px; accent-color: #2563eb;">
                        <span style="font-weight: 600; font-size: 0.88rem; color: #334155;">Permite Extras</span>
                    </label>
                    <div class="field-value" id="field_extras" style="opacity: 0.4; pointer-events: none;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="permite_extras" value="1" style="width: 18px; height: 18px; accent-color: #16a34a;">
                            <span style="font-size: 0.85rem; color: #475569;">Si, puede trabajar mas de 8 horas diarias</span>
                        </label>
                    </div>
                </div>

                <!-- Max Horas Dia -->
                <div class="bulk-field" style="display: flex; align-items: flex-start; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f1f5f9;">
                    <label style="display: flex; align-items: center; gap: 6px; min-width: 180px; cursor: pointer;">
                        <input type="checkbox" name="fields[]" value="max_horas_dia" class="field-toggle" data-target="field_maxhoras" style="width: 18px; height: 18px; accent-color: #2563eb;">
                        <span style="font-weight: 600; font-size: 0.88rem; color: #334155;">Max Horas/Dia</span>
                    </label>
                    <div class="field-value" id="field_maxhoras" style="opacity: 0.4; pointer-events: none;">
                        <input type="number" name="max_horas_dia" value="10" min="8" max="16" style="width: 80px; padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem;">
                        <span style="font-size: 0.82rem; color: #94a3b8; margin-left: 6px;">horas (8-16)</span>
                    </div>
                </div>

                <!-- Tipo Horario -->
                <div class="bulk-field" style="display: flex; align-items: flex-start; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f1f5f9;">
                    <label style="display: flex; align-items: center; gap: 6px; min-width: 180px; cursor: pointer;">
                        <input type="checkbox" name="fields[]" value="permite_horario_partido" class="field-toggle" data-target="field_horario" style="width: 18px; height: 18px; accent-color: #2563eb;">
                        <span style="font-weight: 600; font-size: 0.88rem; color: #334155;">Tipo Horario</span>
                    </label>
                    <div class="field-value" id="field_horario" style="opacity: 0.4; pointer-events: none; display: flex; gap: 16px;">
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio" name="permite_horario_partido" value="1" checked style="width: 18px; height: 18px; accent-color: #2563eb;">
                            <span style="font-size: 0.85rem; color: #475569;">Flexible (partido)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio" name="permite_horario_partido" value="0" style="width: 18px; height: 18px; accent-color: #2563eb;">
                            <span style="font-size: 0.85rem; color: #475569;">Corrido</span>
                        </label>
                    </div>
                </div>

                <!-- Horario de Contrato -->
                <div class="bulk-field" style="display: flex; align-items: flex-start; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f1f5f9;">
                    <label style="display: flex; align-items: center; gap: 6px; min-width: 180px; cursor: pointer;">
                        <input type="checkbox" name="fields[]" value="horario_contrato" class="field-toggle" data-target="field_horario_contrato" style="width: 18px; height: 18px; accent-color: #2563eb;">
                        <span style="font-weight: 600; font-size: 0.88rem; color: #334155;">Horario Contrato</span>
                    </label>
                    <div class="field-value" id="field_horario_contrato" style="opacity: 0.4; pointer-events: none; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 0.85rem; color: #475569;">Desde</span>
                            <select name="hora_inicio_contrato" style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem;">
                                <?php for ($h = 0; $h <= 23; $h++): ?>
                                <option value="<?= $h ?>"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 0.85rem; color: #475569;">Hasta</span>
                            <select name="hora_fin_contrato" style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem;">
                                <?php for ($h = 0; $h <= 23; $h++): ?>
                                <option value="<?= $h ?>" <?= $h === 23 ? 'selected' : '' ?>><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <span style="font-size: 0.75rem; color: #94a3b8;">Ventana horaria en la que pueden trabajar</span>
                    </div>
                </div>

                <!-- Modalidad de Trabajo -->
                <div class="bulk-field" style="display: flex; align-items: flex-start; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f1f5f9;">
                    <label style="display: flex; align-items: center; gap: 6px; min-width: 180px; cursor: pointer;">
                        <input type="checkbox" name="fields[]" value="modalidad_trabajo" class="field-toggle" data-target="field_modalidad" style="width: 18px; height: 18px; accent-color: #2563eb;">
                        <span style="font-weight: 600; font-size: 0.88rem; color: #334155;">Modalidad Trabajo</span>
                    </label>
                    <div class="field-value" id="field_modalidad" style="opacity: 0.4; pointer-events: none; display: flex; gap: 16px;">
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio" name="modalidad_trabajo" value="presencial" style="width: 18px; height: 18px; accent-color: #2563eb;">
                            <span style="font-size: 0.85rem; color: #475569;">Presencial</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio" name="modalidad_trabajo" value="teletrabajo" style="width: 18px; height: 18px; accent-color: #2563eb;">
                            <span style="font-size: 0.85rem; color: #475569;">Teletrabajo</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio" name="modalidad_trabajo" value="mixto" checked style="width: 18px; height: 18px; accent-color: #2563eb;">
                            <span style="font-size: 0.85rem; color: #475569;">Mixto</span>
                        </label>
                    </div>
                </div>

                <!-- Dias de Descanso -->
                <div class="bulk-field" style="display: flex; align-items: flex-start; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f1f5f9;">
                    <label style="display: flex; align-items: center; gap: 6px; min-width: 180px; cursor: pointer;">
                        <input type="checkbox" name="fields[]" value="dias_descanso" class="field-toggle" data-target="field_dias" style="width: 18px; height: 18px; accent-color: #2563eb;">
                        <span style="font-weight: 600; font-size: 0.88rem; color: #334155;">Dias de Descanso</span>
                    </label>
                    <div class="field-value" id="field_dias" style="opacity: 0.4; pointer-events: none;">
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php
                            $diasSemana = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];
                            foreach ($diasSemana as $i => $dia):
                            ?>
                            <label style="display: flex; align-items: center; gap: 4px; padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; font-size: 0.82rem; color: #475569;">
                                <input type="checkbox" name="dias_descanso[]" value="<?= $i ?>" style="width: 16px; height: 16px; accent-color: #2563eb;">
                                <?= $dia ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 6px;">Reemplazara los dias de descanso actuales de todos los selecciónados</div>
                    </div>
                </div>

                <!-- Restricción Médica -->
                <div class="bulk-field" style="display: flex; align-items: flex-start; gap: 14px; padding: 14px 0;">
                    <label style="display: flex; align-items: center; gap: 6px; min-width: 180px; cursor: pointer;">
                        <input type="checkbox" name="fields[]" value="restriccion_medica" class="field-toggle" data-target="field_restriccion" style="width: 18px; height: 18px; accent-color: #2563eb;">
                        <span style="font-weight: 600; font-size: 0.88rem; color: #334155;">Restricción Médica</span>
                    </label>
                    <div class="field-value" id="field_restriccion" style="opacity: 0.4; pointer-events: none;">
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="checkbox" name="tiene_restriccion_medica" value="1" id="chkRestriccion" style="width: 18px; height: 18px; accent-color: #f59e0b;">
                                <span style="font-size: 0.85rem; color: #475569;">Tiene restriccion medica activa</span>
                            </label>
                            <div id="restriccionDetalle" style="display: none; padding-left: 24px; display: flex; flex-direction: column; gap: 8px;">
                                <div>
                                    <label style="font-size: 0.8rem; color: #64748b; display: block; margin-bottom: 3px;">Descripción</label>
                                    <input type="text" name="descripcion_restriccion" placeholder="Ej: No puede cubrir turnos nocturnos" style="width: 100%; max-width: 400px; padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem;">
                                </div>
                                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                    <div>
                                        <label style="font-size: 0.8rem; color: #64748b; display: block; margin-bottom: 3px;">Hora inicio restriccion</label>
                                        <select name="restriccion_hora_inicio" style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem;">
                                            <option value="">-- Sin limite --</option>
                                            <?php for ($h = 0; $h <= 23; $h++): ?>
                                            <option value="<?= $h ?>"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00</option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size: 0.8rem; color: #64748b; display: block; margin-bottom: 3px;">Hora fin restriccion</label>
                                        <select name="restriccion_hora_fin" style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem;">
                                            <option value="">-- Sin limite --</option>
                                            <?php for ($h = 0; $h <= 23; $h++): ?>
                                            <option value="<?= $h ?>"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00</option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size: 0.8rem; color: #64748b; display: block; margin-bottom: 3px;">Vigente hasta</label>
                                        <input type="date" name="restriccion_fecha_hasta" style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div style="display: flex; gap: 12px; justify-content: flex-end; margin-bottom: 40px;">
            <a href="<?= BASE_URL ?>/advisors" class="btn btn-secondary" style="padding: 10px 24px;">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px;" id="btnSubmit" onclick="return confirmBulk();">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width: 18px; height: 18px;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                Aplicar Configuración Masiva
            </button>
        </div>
    </form>

    <?php elseif ($selectedCampaignId && empty($advisors)): ?>
    <div class="data-panel" style="padding: 40px; text-align: center;">
        <p style="color: #64748b; font-size: 0.95rem;">No hay asesores activos en esta campaña.</p>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle field enable/disable
    document.querySelectorAll('.field-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var target = document.getElementById(this.dataset.target);
            if (target) {
                target.style.opacity = this.checked ? '1' : '0.4';
                target.style.pointerEvents = this.checked ? 'auto' : 'none';
            }
        });
    });

    // Select/deselect all advisors
    var cbAll = document.querySelectorAll('.advisor-cb');

    document.getElementById('btnSelectAll').addEventListener('click', function() {
        cbAll.forEach(function(cb) { cb.checked = true; cb.closest('label').style.borderColor = '#2563eb'; cb.closest('label').style.background = '#eff6ff'; });
        updateCount();
    });

    document.getElementById('btnDeselectAll').addEventListener('click', function() {
        cbAll.forEach(function(cb) { cb.checked = false; cb.closest('label').style.borderColor = '#e2e8f0'; cb.closest('label').style.background = ''; });
        updateCount();
    });

    // Visual feedback on check
    cbAll.forEach(function(cb) {
        cb.addEventListener('change', function() {
            this.closest('label').style.borderColor = this.checked ? '#2563eb' : '#e2e8f0';
            this.closest('label').style.background = this.checked ? '#eff6ff' : '';
            updateCount();
        });
    });

    function updateCount() {
        var checked = document.querySelectorAll('.advisor-cb:checked').length;
        document.getElementById('selectedCount').textContent = checked + ' selecciónado' + (checked !== 1 ? 's' : '');
    }

    // Toggle restriccion medica detalle
    var chkRestr = document.getElementById('chkRestriccion');
    var detalleRestr = document.getElementById('restriccionDetalle');
    if (chkRestr && detalleRestr) {
        chkRestr.addEventListener('change', function() {
            detalleRestr.style.display = this.checked ? 'flex' : 'none';
        });
        detalleRestr.style.display = chkRestr.checked ? 'flex' : 'none';
    }
});

function confirmBulk() {
    var checkedAdvisors = document.querySelectorAll('.advisor-cb:checked').length;
    var checkedFields = document.querySelectorAll('.field-toggle:checked').length;

    if (checkedAdvisors === 0) {
        alert('Seleccióna al menos un asesor.');
        return false;
    }
    if (checkedFields === 0) {
        alert('Marca al menos un campo a modificar.');
        return false;
    }
    return confirm('Se aplicara la configuración a ' + checkedAdvisors + ' asesor' + (checkedAdvisors !== 1 ? 'es' : '') + '. ¿Continuar?');
}
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
