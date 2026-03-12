<?php
$pageTitle = 'Compartir Asesores';
$currentPage = 'campaigns';

ob_start();
?>

<div class="page-container-md">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Compartir Asesores</h1>
            <p class="page-header-subtitle">Prestar asesores de otra campaña a <strong><?= htmlspecialchars($campaign['nombre']) ?></strong></p>
        </div>
        <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/shared-advisors" class="btn btn-secondary">
            <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Volver
        </a>
    </div>

    <form action="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/shared-advisors" method="POST">
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
                    Selecciónar Campaña Origen
                </div>
            </div>
            <div class="panel-body">
                <div class="form-row">
                    <label class="form-label">Campaña Origen <span class="required">*</span></label>
                    <div class="form-row-content">
                        <select name="source_campaign_id" id="sourceCampaign" class="form-control" style="max-width: 400px;" required>
                            <option value="">Seleccióne una campaña...</option>
                            <?php foreach ($otherCampaigns as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $sourceCampaignId === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Seleccióna la campaña de donde se prestaran los asesores</div>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Max Horas/Dia <span class="required">*</span></label>
                    <div class="form-row-content">
                        <input type="number" name="max_horas_dia" class="form-control" value="3" min="1" max="8" style="max-width: 120px;" required>
                        <div class="form-hint">Maximo de horas por dia que cada asesor puede trabajar en esta campaña</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($sourceCampaignId && !empty($sourceAdvisors)): ?>
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    Selecciónar Asesores
                </div>
                <div>
                    <label class="form-check" style="cursor: pointer;">
                        <input type="checkbox" id="selectAll">
                        <span>Selecciónar todos</span>
                    </label>
                </div>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Asesor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sourceAdvisors as $adv): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="advisor_ids[]" value="<?= $adv['id'] ?>" class="advisor-checkbox">
                            </td>
                            <td><?= htmlspecialchars($adv['apellidos'] . ' ' . $adv['nombres']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/shared-advisors" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                Compartir Selecciónados
            </button>
        </div>
        <?php elseif ($sourceCampaignId && empty($sourceAdvisors)): ?>
        <div class="data-panel">
            <div class="panel-body" style="text-align: center; color: #94a3b8; padding: 24px;">
                No hay asesores disponibles en la campaña selecciónada (ya estan compartidos o no hay asesores activos).
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php
$content = ob_get_clean();

$extraScripts = ['
<script>
    document.getElementById("sourceCampaign").addEventListener("change", function() {
        var campaignId = ' . $campaign['id'] . ';
        var sourceId = this.value;
        if (sourceId) {
            window.location.href = "' . BASE_URL . '/campaigns/' . $campaign['id'] . '/shared-advisors/create?source_campaign_id=" + sourceId;
        }
    });

    var selectAll = document.getElementById("selectAll");
    if (selectAll) {
        selectAll.addEventListener("change", function() {
            var checkboxes = document.querySelectorAll(".advisor-checkbox");
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
        });
    }
</script>
'];

include APP_PATH . '/Views/layouts/main.php';
?>
