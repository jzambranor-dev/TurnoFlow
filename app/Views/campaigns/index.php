<?php
/**
 * TurnoFlow - Vista de Campañas
 */

$pageTitle = 'Campañas';
$currentPage = 'campaigns';

ob_start();
?>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Campañas</h1>
            <p class="page-header-subtitle">Gestióna las campañas del call center</p>
        </div>
        <a href="<?= BASE_URL ?>/campaigns/create" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Nueva Campana
        </a>
    </div>

    <!-- Stats Summary -->
    <?php
    $totalCampaigns = count($campaigns);
    $activas = count(array_filter($campaigns, fn($c) => $c['estado'] === 'activa'));
    $inactivas = count(array_filter($campaigns, fn($c) => $c['estado'] === 'inactiva'));
    $pausadas = count(array_filter($campaigns, fn($c) => $c['estado'] === 'pausada'));
    $totalAsesores = array_sum(array_column($campaigns, 'total_asesores'));
    ?>
    <div class="stats-row">
        <div class="stat-mini">
            <span class="stat-value"><?= $totalCampaigns ?></span>
            <span class="stat-label">Total</span>
        </div>
        <div class="stat-mini accent-green">
            <span class="stat-value"><?= $activas ?></span>
            <span class="stat-label">Activas</span>
        </div>
        <div class="stat-mini accent-orange">
            <span class="stat-value"><?= $pausadas ?></span>
            <span class="stat-label">Pausadas</span>
        </div>
        <div class="stat-mini accent-red">
            <span class="stat-value"><?= $inactivas ?></span>
            <span class="stat-label">Inactivas</span>
        </div>
        <div class="stat-mini accent-blue">
            <span class="stat-value"><?= $totalAsesores ?></span>
            <span class="stat-label">Asesores</span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="data-panel">
        <?php if (empty($campaigns)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
            </div>
            <h5>No hay campañas registradas</h5>
            <p>Crea tu primera campaña para comenzar a gestiónar horarios.</p>
            <a href="<?= BASE_URL ?>/campaigns/create" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Crear Campana
            </a>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
                Listado de Campañas
            </div>
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="searchInput" placeholder="Buscar campaña...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="campaignsTable">
                <thead>
                    <tr>
                        <th>Campana</th>
                        <th>Cliente</th>
                        <th>Supervisor</th>
                        <th>Asesores</th>
                        <th>Velada</th>
                        <th>Horario</th>
                        <th>Estado</th>
                        <th class="text-right">Acciónes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign): ?>
                    <tr>
                        <td>
                            <div class="cell-stack">
                                <span class="cell-main"><?= htmlspecialchars($campaign['nombre']) ?></span>
                                <span class="cell-sub">#<?= $campaign['id'] ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($campaign['cliente'] ?? '-') ?></td>
                        <td>
                            <?php if ($campaign['supervisor_nombre']): ?>
                            <div class="cell-flex">
                                <div class="avatar avatar-blue" style="width: 28px; height: 28px; font-size: 0.65rem;">
                                    <?= strtoupper(substr($campaign['supervisor_nombre'], 0, 1)) ?>
                                </div>
                                <span><?= htmlspecialchars($campaign['supervisor_nombre']) ?></span>
                            </div>
                            <?php else: ?>
                            <span style="color: var(--corp-gray-400); font-style: italic;">Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-info" style="font-weight: 700;"><?= $campaign['total_asesores'] ?? 0 ?></span></td>
                        <td>
                            <?php if ($campaign['tiene_velada']): ?>
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
                            <span class="badge badge-neutral cell-mono">
                                <?= str_pad($campaign['hora_inicio_operacion'], 2, '0', STR_PAD_LEFT) ?>:00 - <?= str_pad($campaign['hora_fin_operacion'], 2, '0', STR_PAD_LEFT) ?>:00
                            </span>
                        </td>
                        <td>
                            <?php
                            $statusMap = [
                                'activa' => 'badge-success',
                                'inactiva' => 'badge-danger',
                                'pausada' => 'badge-warning'
                            ];
                            $statusClass = $statusMap[$campaign['estado']] ?? 'badge-neutral';
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst($campaign['estado']) ?></span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/activities" class="action-btn" title="Actividades" style="color: var(--corp-primary);">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                                </a>
                                <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/edit" class="action-btn edit" title="Editar">
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
    var table = document.getElementById('campaignsTable');

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
