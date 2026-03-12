<?php
/**
 * TurnoFlow - Vista de Generar Horario
 * Permite seleccionar campaña e importación antes de generar
 */

$pageTitle = 'Generar Horario';
$currentPage = 'schedules';

$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

ob_start();
?>

<div class="generate-page">
    <!-- Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="header-title">Generar Horario</h1>
                <p class="header-subtitle">Selecciona la importación de dimensionamiento para generar el horario</p>
            </div>
            <div class="header-actions">
                <a href="<?= BASE_URL ?>/schedules" class="btn-action btn-secondary">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver a Horarios
                </a>
                <a href="<?= BASE_URL ?>/schedules/import" class="btn-action btn-secondary">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41 1.41L11 14.84V19h2v-4.16l1.59 1.59L16 15.01 12.01 11 8 15.01z"/></svg>
                    Importar Nuevo
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
    <div class="flash-banner flash-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
    <div class="flash-banner flash-error"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <?php if (empty($imports)): ?>
    <div class="data-panel">
        <div class="empty-state">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
            </div>
            <h3 class="empty-title">No hay importaciones disponibles</h3>
            <p class="empty-text">Primero debes importar un archivo de dimensionamiento para poder generar un horario.</p>
            <a href="<?= BASE_URL ?>/schedules/import" class="btn-action btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41 1.41L11 14.84V19h2v-4.16l1.59 1.59L16 15.01 12.01 11 8 15.01z"/></svg>
                Importar Dimensionamiento
            </a>
        </div>
    </div>
    <?php else: ?>

    <?php
    // Agrupar importaciones por campaña
    $byCampaign = [];
    foreach ($imports as $imp) {
        $byCampaign[$imp['campaign_id']]['nombre'] = $imp['campaign_nombre'];
        $byCampaign[$imp['campaign_id']]['imports'][] = $imp;
    }
    ?>

    <?php foreach ($byCampaign as $campId => $campData): ?>
    <div class="data-panel" style="margin-bottom: 20px;">
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg>
                <?= htmlspecialchars($campData['nombre']) ?>
            </div>
            <span class="panel-count"><?= count($campData['imports']) ?> importación<?= count($campData['imports']) !== 1 ? 'es' : '' ?></span>
        </div>

        <div class="imports-grid">
            <?php foreach ($campData['imports'] as $imp): ?>
            <?php
                $hasSchedule = !empty($imp['schedule_id']);
                $scheduleStatus = $imp['schedule_status'] ?? null;
                $isLocked = in_array($scheduleStatus, ['aprobado', 'enviado'], true);
                $periodoLabel = ($meses[$imp['periodo_mes']] ?? $imp['periodo_mes']) . ' ' . $imp['periodo_anio'];
                $importDate = date('d/m/Y H:i', strtotime($imp['imported_at']));
            ?>
            <div class="import-card <?= $isLocked ? 'card-locked' : '' ?>">
                <div class="import-card-header">
                    <div class="import-period"><?= $periodoLabel ?></div>
                    <?php if ($hasSchedule): ?>
                        <?php
                        $statusColors = [
                            'borrador' => ['bg' => '#f1f5f9', 'color' => '#64748b'],
                            'enviado' => ['bg' => '#dbeafe', 'color' => '#2563eb'],
                            'aprobado' => ['bg' => '#dcfce7', 'color' => '#16a34a'],
                            'rechazado' => ['bg' => '#fee2e2', 'color' => '#dc2626'],
                        ];
                        $sc = $statusColors[$scheduleStatus] ?? ['bg' => '#f1f5f9', 'color' => '#64748b'];
                        ?>
                        <span class="schedule-status" style="background: <?= $sc['bg'] ?>; color: <?= $sc['color'] ?>;">
                            <?= ucfirst($scheduleStatus) ?>
                        </span>
                    <?php else: ?>
                        <span class="schedule-status" style="background: #fef3c7; color: #d97706;">Sin horario</span>
                    <?php endif; ?>
                </div>

                <div class="import-card-body">
                    <div class="import-detail">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg>
                        <span class="detail-text" title="<?= htmlspecialchars($imp['archivo_nombre'] ?? '-') ?>">
                            <?= htmlspecialchars($imp['archivo_nombre'] ?? 'Sin archivo') ?>
                        </span>
                    </div>
                    <div class="import-detail">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                        <span>Importado: <?= $importDate ?></span>
                    </div>
                    <div class="import-detail">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        <span><?= $imp['total_asesores'] ?> asesores activos</span>
                    </div>
                    <div class="import-detail">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                        <span><?= number_format($imp['total_asesor_hora'] ?? 0) ?> asesor-hora requeridas</span>
                    </div>
                    <?php if (!empty($imp['importado_por_nombre'])): ?>
                    <div class="import-detail">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        <span>Por: <?= htmlspecialchars($imp['importado_por_nombre']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="import-card-footer">
                    <?php if ($isLocked): ?>
                    <div class="locked-notice">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                        Horario <?= $scheduleStatus ?> - no se puede regenerar
                    </div>
                    <?php else: ?>
                    <div class="import-card-actions">
                        <form method="POST" action="<?= BASE_URL ?>/schedules/generate" onsubmit="return confirmGenerate(this);" style="flex: 1;">
                            <input type="hidden" name="import_id" value="<?= $imp['import_id'] ?>">
                            <button type="submit" class="btn-action btn-primary btn-generate">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm-8 4H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
                                <?= $hasSchedule ? 'Regenerar Horario' : 'Generar Horario' ?>
                            </button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/schedules/imports/<?= $imp['import_id'] ?>/delete" onsubmit="return confirmDelete(this, '<?= $periodoLabel ?>');">
                            <button type="submit" class="btn-action btn-danger btn-delete-import" title="Eliminar importación">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .generate-page {
        max-width: 1200px;
        margin: 0 auto;
    }

    .flash-banner {
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 16px;
        font-size: 0.875rem;
        font-weight: 600;
        border: 1px solid transparent;
    }

    .flash-success {
        background: #ecfdf5;
        color: #047857;
        border-color: #a7f3d0;
    }

    .flash-error {
        background: #fef2f2;
        color: #b91c1c;
        border-color: #fecaca;
    }

    /* Header */
    .page-header {
        margin-bottom: 24px;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        flex-wrap: wrap;
    }

    .header-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 4px 0;
    }

    .header-subtitle {
        font-size: 0.875rem;
        color: #64748b;
        margin: 0;
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.15s ease;
        border: none;
        cursor: pointer;
    }

    .btn-action svg {
        width: 18px;
        height: 18px;
    }

    .btn-primary {
        background: #2563eb;
        color: #fff;
    }

    .btn-primary:hover {
        background: #1d4ed8;
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
        color: #334155;
    }

    /* Data Panel */
    .data-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }

    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .panel-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }

    .panel-title svg {
        width: 20px;
        height: 20px;
        fill: #2563eb;
    }

    .panel-count {
        font-size: 0.8rem;
        color: #64748b;
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 500;
    }

    /* Imports Grid */
    .imports-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 16px;
        padding: 20px;
    }

    /* Import Card */
    .import-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        transition: all 0.15s ease;
        background: #fff;
    }

    .import-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .import-card.card-locked {
        opacity: 0.7;
    }

    .import-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 16px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .import-period {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }

    .schedule-status {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .import-card-body {
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .import-detail {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.82rem;
        color: #475569;
    }

    .import-detail svg {
        width: 16px;
        height: 16px;
        fill: #94a3b8;
        flex-shrink: 0;
    }

    .detail-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 260px;
    }

    .import-card-footer {
        padding: 12px 16px;
        border-top: 1px solid #f1f5f9;
        background: #fafbfc;
    }

    .import-card-footer form {
        margin: 0;
    }

    .import-card-actions {
        display: flex;
        gap: 8px;
        align-items: stretch;
    }

    .btn-generate {
        width: 100%;
        justify-content: center;
        padding: 10px 16px;
    }

    .btn-danger {
        background: #fee2e2;
        color: #dc2626;
        border: 1px solid #fecaca;
        padding: 10px 12px;
    }

    .btn-danger:hover {
        background: #dc2626;
        color: #fff;
    }

    .btn-delete-import svg {
        width: 18px;
        height: 18px;
    }

    .locked-notice {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.8rem;
        color: #94a3b8;
        font-weight: 500;
    }

    .locked-notice svg {
        width: 16px;
        height: 16px;
        fill: #cbd5e1;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }

    .empty-icon {
        width: 64px;
        height: 64px;
        background: #f1f5f9;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }

    .empty-icon svg {
        width: 32px;
        height: 32px;
        fill: #94a3b8;
    }

    .empty-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #334155;
        margin: 0 0 8px 0;
    }

    .empty-text {
        font-size: 0.9rem;
        color: #64748b;
        margin: 0 0 24px 0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
            align-items: stretch;
        }

        .header-actions {
            flex-direction: column;
        }

        .imports-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
function confirmGenerate(form) {
    var btn = form.querySelector('button[type="submit"]');
    var label = btn ? btn.textContent.trim() : 'Generar';
    var isRegenerate = label.indexOf('Regenerar') !== -1;

    if (isRegenerate) {
        return confirm('Este horario ya tiene asignaciónes. Se eliminaran y se generaran nuevas. ¿Continuar?');
    }
    return confirm('¿Generar el horario con esta importación?');
}

function confirmDelete(form, periodo) {
    return confirm('¿Eliminar la importación de ' + periodo + '? Se eliminara el dimensionamiento, las asignaciónes del horario y el archivo. Esta acción no se puede deshacer.');
}
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
