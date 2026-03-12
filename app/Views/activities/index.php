<?php
/**
 * TurnoFlow - Vista de Actividades de Campaña
 */

ob_start();
?>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Actividades</h1>
            <p class="page-header-subtitle">
                Campaña: <strong><?= htmlspecialchars($campaign['nombre']) ?></strong>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="<?= BASE_URL ?>/campaigns" class="btn btn-light">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Volver
            </a>
            <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/activities/create" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Nueva Actividad
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= $_SESSION['flash_success'] ?></div>
    <?php unset($_SESSION['flash_success']); endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); endif; ?>

    <!-- Stats -->
    <?php
    $totalActivities = count($activities);
    $activas = count(array_filter($activities, fn($a) => $a['estado'] === 'activa'));
    $totalAsignados = array_sum(array_column($activities, 'total_asesores'));
    ?>
    <div class="stats-row">
        <div class="stat-mini">
            <span class="stat-value"><?= $totalActivities ?></span>
            <span class="stat-label">Actividades</span>
        </div>
        <div class="stat-mini accent-green">
            <span class="stat-value"><?= $activas ?></span>
            <span class="stat-label">Activas</span>
        </div>
        <div class="stat-mini accent-blue">
            <span class="stat-value"><?= $totalAsignados ?></span>
            <span class="stat-label">Asesores Asignados</span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="data-panel">
        <?php if (empty($activities)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
            </div>
            <h5>No hay actividades</h5>
            <p>Crea actividades para asignar asesores con horarios fijos dentro de esta campaña.</p>
            <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/activities/create" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Crear Actividad
            </a>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                Actividades de <?= htmlspecialchars($campaign['nombre']) ?>
            </div>
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="searchInput" placeholder="Buscar actividad...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="activitiesTable">
                <thead>
                    <tr>
                        <th>Actividad</th>
                        <th>Descripción</th>
                        <th>Color</th>
                        <th>Asesores</th>
                        <th>Estado</th>
                        <th class="text-right">Acciónes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td>
                            <div class="cell-stack">
                                <span class="cell-main"><?= htmlspecialchars($activity['nombre']) ?></span>
                                <span class="cell-sub">#<?= $activity['id'] ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($activity['descripcion'] ?? '-') ?></td>
                        <td>
                            <span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?= htmlspecialchars($activity['color']) ?>;vertical-align:middle;"></span>
                        </td>
                        <td>
                            <span class="badge badge-info" style="font-weight: 700;"><?= $activity['total_asesores'] ?></span>
                        </td>
                        <td>
                            <?php
                            $statusClass = $activity['estado'] === 'activa' ? 'badge-success' : 'badge-danger';
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst($activity['estado']) ?></span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="<?= BASE_URL ?>/activities/<?= $activity['id'] ?>/assignments" class="action-btn" title="Asignar Asesores" style="color: var(--corp-primary);">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                </a>
                                <a href="<?= BASE_URL ?>/activities/<?= $activity['id'] ?>/edit" class="action-btn edit" title="Editar">
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
    var table = document.getElementById('activitiesTable');
    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            var filter = this.value.toLowerCase();
            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
