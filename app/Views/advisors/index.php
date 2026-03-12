<?php
/**
 * TurnoFlow - Vista de Asesores
 */

$pageTitle = 'Asesores';
$currentPage = 'advisors';

ob_start();
?>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Asesores</h1>
            <p class="page-header-subtitle">Gestióna los asesores del call center</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?= BASE_URL ?>/advisors/bulk-config" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width: 18px; height: 18px;"><path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                Config. Masiva
            </a>
            <a href="<?= BASE_URL ?>/advisors/create" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Nuevo Asesor
            </a>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <!-- Stats Summary -->
    <?php
    $totalAdvisors = count($advisors);
    $activos = count(array_filter($advisors, fn($a) => $a['estado'] === 'activo'));
    $inactivos = count(array_filter($advisors, fn($a) => $a['estado'] === 'inactivo'));
    $licencia = count(array_filter($advisors, fn($a) => $a['estado'] === 'licencia'));
    $conVPN = count(array_filter($advisors, fn($a) => $a['tiene_vpn']));
    ?>
    <div class="stats-row">
        <div class="stat-mini">
            <span class="stat-value"><?= $totalAdvisors ?></span>
            <span class="stat-label">Total</span>
        </div>
        <div class="stat-mini accent-green">
            <span class="stat-value"><?= $activos ?></span>
            <span class="stat-label">Activos</span>
        </div>
        <div class="stat-mini accent-red">
            <span class="stat-value"><?= $inactivos ?></span>
            <span class="stat-label">Inactivos</span>
        </div>
        <div class="stat-mini accent-orange">
            <span class="stat-value"><?= $licencia ?></span>
            <span class="stat-label">Licencia</span>
        </div>
        <div class="stat-mini accent-purple">
            <span class="stat-value"><?= $conVPN ?></span>
            <span class="stat-label">Con VPN</span>
        </div>
    </div>

    <!-- Filtro por Campaña -->
    <div class="data-panel" style="margin-bottom: 16px; padding: 16px;">
        <form method="GET" action="<?= BASE_URL ?>/advisors" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <label for="campaign_filter" style="font-weight: 600; white-space: nowrap;">Filtrar por campaña:</label>
            <select name="campaign_id" id="campaign_filter" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; min-width: 220px; font-size: 14px; background: #fff;">
                <option value="">-- Todas las campañas --</option>
                <?php foreach ($campaignsForFilter as $cf): ?>
                <option value="<?= $cf['id'] ?>" <?= ($filterCampaignId ?? null) == $cf['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cf['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Filtrar</button>
            <?php if (!empty($filterCampaignId)): ?>
            <a href="<?= BASE_URL ?>/advisors" class="btn" style="padding: 8px 20px; background: #6b7280; color: #fff; border-radius: 6px; text-decoration: none;">Limpiar</a>
            <?php endif; ?>
            <span style="margin-left: auto; color: #6b7280; font-size: 13px;">
                <?= count($advisors) ?> asesor<?= count($advisors) !== 1 ? 'es' : '' ?> encontrado<?= count($advisors) !== 1 ? 's' : '' ?>
            </span>
        </form>
    </div>

    <!-- Main Content -->
    <div class="data-panel">
        <?php if (empty($advisors)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <h5>No hay asesores registrados</h5>
            <p>Agrega tu primer asesor para comenzar a asignar horarios.</p>
            <a href="<?= BASE_URL ?>/advisors/create" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Agregar Asesor
            </a>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                Listado de Asesores
            </div>
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="searchInput" placeholder="Buscar asesor...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="advisorsTable">
                <thead>
                    <tr>
                        <th>Asesor</th>
                        <th>Cedula</th>
                        <th>Campaña</th>
                        <th>Contrato</th>
                        <th>VPN</th>
                        <th>Extras</th>
                        <th>Max Horas</th>
                        <th>Estado</th>
                        <th class="text-right">Acciónes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advisors as $advisor): ?>
                    <tr>
                        <td>
                            <div class="cell-flex">
                                <div class="avatar avatar-primary">
                                    <?= strtoupper(substr($advisor['nombres'], 0, 1) . substr($advisor['apellidos'], 0, 1)) ?>
                                </div>
                                <div class="cell-stack">
                                    <span class="cell-main"><?= htmlspecialchars($advisor['apellidos'] . ', ' . $advisor['nombres']) ?></span>
                                    <span class="cell-sub">#<?= $advisor['id'] ?></span>
                                </div>
                            </div>
                        </td>
                        <td><span class="cell-mono"><?= htmlspecialchars($advisor['cedula'] ?? '-') ?></span></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($advisor['campaign_nombre']) ?></span></td>
                        <td>
                            <?php
                            $cType = $advisor['tipo_contrato'] === 'completo' ? 'badge-success' : 'badge-warning';
                            ?>
                            <span class="badge <?= $cType ?>"><?= ucfirst($advisor['tipo_contrato']) ?></span>
                        </td>
                        <td>
                            <?php if ($advisor['tiene_vpn']): ?>
                            <span class="toggle-yes">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            </span>
                            <?php else: ?>
                            <span class="toggle-no">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($advisor['permite_extras']): ?>
                            <span class="toggle-yes">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            </span>
                            <?php else: ?>
                            <span class="toggle-no">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-neutral" style="font-weight: 700;"><?= $advisor['constraint_max_horas'] ?? 10 ?>h</span>
                        </td>
                        <td>
                            <?php
                            $statusMap = [
                                'activo' => 'badge-success',
                                'inactivo' => 'badge-danger',
                                'licencia' => 'badge-warning'
                            ];
                            $statusClass = $statusMap[$advisor['estado']] ?? 'badge-neutral';
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst($advisor['estado']) ?></span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="<?= BASE_URL ?>/advisors/<?= $advisor['id'] ?>/edit" class="action-btn edit" title="Editar y Configurar">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('searchInput');
    var table = document.getElementById('advisorsTable');

    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            var filter = this.value.toLowerCase();
            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
