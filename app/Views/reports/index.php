<?php
/**
 * TurnoFlow - Vista de Reportes (selector de campaña)
 */

$pageTitle = 'Reportes';
$currentPage = 'reports';

ob_start();
?>

<div class="page-container">
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Reportes</h1>
            <p class="page-header-subtitle">Selecciona una campaña para ver el reporte de horas</p>
        </div>
    </div>

    <div class="data-panel">
        <?php if (empty($campaigns)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
            </div>
            <h5>No hay campañas disponibles</h5>
            <p>No tienes campañas activas para generar reportes.</p>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                Campañas Disponibles
            </div>
        </div>

        <div style="padding: 24px; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
            <?php foreach ($campaigns as $c): ?>
            <a href="<?= BASE_URL ?>/reports/hours/<?= $c['id'] ?>" class="report-campaign-card">
                <div class="report-card-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
                </div>
                <div>
                    <div class="report-card-title"><?= htmlspecialchars($c['nombre']) ?></div>
                    <div class="report-card-sub">Reporte de horas por asesor</div>
                </div>
                <svg class="report-card-arrow" viewBox="0 0 24 24" fill="currentColor"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .report-campaign-card {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: #fff;
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--card-radius);
        text-decoration: none;
        color: inherit;
        transition: all 0.15s;
    }

    .report-campaign-card:hover {
        border-color: var(--corp-primary);
        box-shadow: var(--card-shadow-hover);
        transform: translateY(-1px);
    }

    .report-card-icon {
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, var(--corp-primary), var(--corp-purple));
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .report-card-icon svg {
        width: 22px;
        height: 22px;
        fill: #fff;
    }

    .report-card-title {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--corp-gray-800);
    }

    .report-card-sub {
        font-size: 0.8rem;
        color: var(--corp-gray-400);
        margin-top: 2px;
    }

    .report-card-arrow {
        width: 20px;
        height: 20px;
        fill: var(--corp-gray-300);
        margin-left: auto;
        flex-shrink: 0;
    }

    .report-campaign-card:hover .report-card-arrow {
        fill: var(--corp-primary);
    }
</style>
STYLE;

include APP_PATH . '/Views/layouts/main.php';
?>
