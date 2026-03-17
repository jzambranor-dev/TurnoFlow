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
                <div class="form-breadcrumb" style="margin-bottom:8px;">
                    <a href="<?= BASE_URL ?>/schedules" style="color:#2563eb;text-decoration:none;font-weight:500;">Horarios</a>
                    <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:#94a3b8;"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                    <span>Generar</span>
                </div>
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

    <?php if (!empty($imports)):
        $totalImports = count($imports);
        $withSchedule = 0;
        $withoutSchedule = 0;
        $lockedCount = 0;
        foreach ($imports as $imp) {
            if (!empty($imp['schedule_id'])) {
                $withSchedule++;
                if (in_array($imp['schedule_status'] ?? '', ['aprobado', 'enviado'], true)) $lockedCount++;
            } else {
                $withoutSchedule++;
            }
        }
    ?>
    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon-box" style="background:#eff6ff;">
                <svg viewBox="0 0 24 24" style="fill:#2563eb;"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?= $totalImports ?></span>
                <span class="stat-label">Importaciones</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-box" style="background:#dcfce7;">
                <svg viewBox="0 0 24 24" style="fill:#16a34a;"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?= $withSchedule ?></span>
                <span class="stat-label">Con horario</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-box" style="background:#fef3c7;">
                <svg viewBox="0 0 24 24" style="fill:#d97706;"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?= $withoutSchedule ?></span>
                <span class="stat-label">Pendientes</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-box" style="background:#f1f5f9;">
                <svg viewBox="0 0 24 24" style="fill:#64748b;"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?= $lockedCount ?></span>
                <span class="stat-label">Bloqueados</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
    <div class="flash-banner flash-success alert-dismissible">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;flex-shrink:0;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        <span><?= htmlspecialchars($flashSuccess) ?></span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove();">&times;</button>
    </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
    <div class="flash-banner flash-error alert-dismissible">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        <span><?= htmlspecialchars($flashError) ?></span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove();">&times;</button>
    </div>
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

    <?php
    $campColors = ['#2563eb', '#7c3aed', '#0891b2', '#059669', '#d97706', '#dc2626', '#be185d'];
    $campIdx = 0;
    ?>
    <?php foreach ($byCampaign as $campId => $campData): ?>
    <?php $campColor = $campColors[$campIdx % count($campColors)]; $campIdx++; ?>
    <div class="data-panel" style="margin-bottom: 20px;">
        <div class="panel-header">
            <div class="panel-title">
                <div class="campaign-badge" style="background:<?= $campColor ?>15; color:<?= $campColor ?>;">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg>
                </div>
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
                            <span class="status-dot" style="background:<?= $sc['color'] ?>;"></span>
                            <?= ucfirst($scheduleStatus) ?>
                        </span>
                    <?php else: ?>
                        <span class="schedule-status" style="background: #fef3c7; color: #d97706;">
                            <span class="status-dot" style="background:#d97706;"></span>
                            Sin horario
                        </span>
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
                            <?= \App\Services\CsrfService::field() ?>
                            <input type="hidden" name="import_id" value="<?= $imp['import_id'] ?>">
                            <button type="submit" class="btn-action btn-primary btn-generate">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm-8 4H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
                                <?= $hasSchedule ? 'Regenerar Horario' : 'Generar Horario' ?>
                            </button>
                        </form>
                        <?php if ($hasSchedule): ?>
                        <button type="button" class="btn-action btn-warning btn-partial-toggle" title="Ajustar desde una fecha" data-import="<?= $imp['import_id'] ?>" data-anio="<?= $imp['periodo_anio'] ?>" data-mes="<?= $imp['periodo_mes'] ?>">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                        </button>
                        <?php endif; ?>
                        <form method="POST" action="<?= BASE_URL ?>/schedules/imports/<?= $imp['import_id'] ?>/delete" onsubmit="return confirmDelete(this, '<?= $periodoLabel ?>');">
                            <?= \App\Services\CsrfService::field() ?>
                            <button type="submit" class="btn-action btn-danger btn-delete-import" title="Eliminar importación">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </button>
                        </form>
                    </div>
                    <?php if ($hasSchedule): ?>
                    <div class="partial-regen-panel" id="partial-panel-<?= $imp['import_id'] ?>" style="display:none;">
                        <form method="POST" action="<?= BASE_URL ?>/schedules/regenerate-partial" onsubmit="return confirmPartial(this);" class="partial-form">
                            <?= \App\Services\CsrfService::field() ?>
                            <input type="hidden" name="import_id" value="<?= $imp['import_id'] ?>">
                            <div class="partial-form-row">
                                <label class="partial-label">Ajustar desde:</label>
                                <input type="date" name="from_date" class="partial-date-input"
                                    min="<?= sprintf('%04d-%02d-02', $imp['periodo_anio'], $imp['periodo_mes']) ?>"
                                    max="<?= sprintf('%04d-%02d-%02d', $imp['periodo_anio'], $imp['periodo_mes'], cal_days_in_month(CAL_GREGORIAN, (int)$imp['periodo_mes'], (int)$imp['periodo_anio'])) ?>"
                                    required>
                                <button type="submit" class="btn-action btn-warning btn-partial-submit">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 8l-4 4h3c0 3.31-2.69 6-6 6-1.01 0-1.97-.25-2.8-.7l-1.46 1.46C8.97 19.54 10.43 20 12 20c4.42 0 8-3.58 8-8h3l-4-4zM6 12c0-3.31 2.69-6 6-6 1.01 0 1.97.25 2.8.7l1.46-1.46C15.03 4.46 13.57 4 12 4c-4.42 0-8 3.58-8 8H1l4 4 4-4H6z"/></svg>
                                    Ajustar
                                </button>
                            </div>
                            <p class="partial-hint">Las asignaciones anteriores a esta fecha se mantienen intactas.</p>
                        </form>
                    </div>
                    <?php endif; ?>
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
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .flash-banner.alert-dismissible { position: relative; padding-right: 40px; }

    .alert-close {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        color: inherit;
        opacity: 0.6;
        line-height: 1;
    }
    .alert-close:hover { opacity: 1; }

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

    /* Stats Row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .stat-icon-box {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .stat-icon-box svg { width: 22px; height: 22px; }

    .stat-info { display: flex; flex-direction: column; }
    .stat-value { font-size: 1.3rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .stat-label { font-size: 0.75rem; color: #64748b; font-weight: 500; }

    /* Campaign Badge */
    .campaign-badge {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .campaign-badge svg { width: 18px; height: 18px; }

    /* Status Dot */
    .status-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        margin-right: 2px;
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
        transition: all 0.2s ease;
        background: #fff;
        animation: fadeInCard 0.3s ease both;
    }

    .import-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transform: translateY(-2px);
    }

    .import-card.card-locked {
        opacity: 0.65;
        border-left: 3px solid #94a3b8;
    }

    .import-card.card-locked:hover {
        transform: none;
        box-shadow: none;
    }

    @keyframes fadeInCard {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
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
        display: inline-flex;
        align-items: center;
        gap: 5px;
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

    /* Partial regeneration */
    .btn-warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
        padding: 10px 12px;
    }

    .btn-warning:hover {
        background: #fde68a;
        color: #78350f;
    }

    .partial-regen-panel {
        padding: 12px 16px;
        border-top: 1px solid #fde68a;
        background: #fffbeb;
    }

    .partial-form-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .partial-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: #92400e;
        white-space: nowrap;
    }

    .partial-date-input {
        padding: 6px 10px;
        border: 1px solid #fde68a;
        border-radius: 6px;
        font-size: 0.82rem;
        background: #fff;
        color: #0f172a;
        flex: 1;
        min-width: 130px;
    }

    .partial-date-input:focus {
        outline: none;
        border-color: #f59e0b;
        box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
    }

    .btn-partial-submit {
        padding: 6px 14px !important;
        font-size: 0.8rem !important;
        white-space: nowrap;
    }

    .btn-partial-submit svg {
        width: 16px;
        height: 16px;
    }

    .partial-hint {
        font-size: 0.72rem;
        color: #b45309;
        margin: 6px 0 0 0;
        opacity: 0.8;
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

        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 480px) {
        .stats-row {
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

function confirmPartial(form) {
    var dateInput = form.querySelector('input[name="from_date"]');
    if (!dateInput.value) {
        alert('Selecciona una fecha desde la cual ajustar.');
        return false;
    }
    var parts = dateInput.value.split('-');
    var label = parts[2] + '/' + parts[1] + '/' + parts[0];
    return confirm('Se regenerarán las asignaciones desde el ' + label + ' en adelante. Las asignaciones anteriores NO se modificarán. ¿Continuar?');
}

// Auto-dismiss flash banners
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.flash-banner').forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-10px)';
            setTimeout(function() { el.remove(); }, 400);
        }, 5000);
    });

    // Stagger card animations
    document.querySelectorAll('.import-card').forEach(function(card, i) {
        card.style.animationDelay = (i * 0.05) + 's';
    });

    // Toggle partial regeneration panels
    document.querySelectorAll('.btn-partial-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var importId = this.getAttribute('data-import');
            var panel = document.getElementById('partial-panel-' + importId);
            if (panel) {
                var isVisible = panel.style.display !== 'none';
                panel.style.display = isVisible ? 'none' : 'block';
                if (!isVisible) {
                    panel.querySelector('input[name="from_date"]').focus();
                }
            }
        });
    });
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
