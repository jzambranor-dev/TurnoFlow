<?php
/**
 * TurnoFlow - Importar Asesores
 * Bulk import advisors from Excel/CSV file
 */

$pageTitle = 'Importar Asesores';
$currentPage = 'advisors';

ob_start();
?>

<div class="import-page">
    <!-- Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="form-breadcrumb" style="margin-bottom:8px;">
                <a href="<?= BASE_URL ?>/dashboard">Dashboard</a>
                <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:var(--corp-gray-400);"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                <a href="<?= BASE_URL ?>/advisors">Asesores</a>
                <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:var(--corp-gray-400);"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                <span>Importar</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                <div>
                    <h1 class="page-header-title">Importar Asesores</h1>
                    <p class="page-header-subtitle">Carga un archivo Excel o CSV para importar asesores de forma masiva</p>
                </div>
                <a href="<?= BASE_URL ?>/advisors/import/template" class="btn btn-secondary" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:var(--corp-gray-100);color:var(--corp-gray-600);border:1px solid var(--corp-gray-200);border-radius:6px;font-weight:600;font-size:0.85rem;white-space:nowrap;">
                    <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                    Descargar Plantilla
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success alert-dismissible">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;flex-shrink:0;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        <span><?= htmlspecialchars($flashSuccess) ?></span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove();">&times;</button>
    </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
    <div class="alert alert-danger alert-dismissible">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        <span><?= htmlspecialchars($flashError) ?></span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove();">&times;</button>
    </div>
    <?php endif; ?>

    <div class="import-grid">
        <!-- Upload Panel -->
        <div class="data-panel" style="padding:28px;">
            <form action="<?= BASE_URL ?>/advisors/import" method="POST" enctype="multipart/form-data" id="importForm">
                <?= \App\Services\CsrfService::field() ?>

                <!-- File Upload -->
                <div class="upload-zone" id="uploadZone">
                    <input type="file" name="excel_file" id="fileInput" accept=".xlsx,.xls,.csv" required hidden>
                    <div class="upload-content" id="uploadContent">
                        <div class="upload-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41 1.41L11 14.84V19h2v-4.16l1.59 1.59L16 15.01 12.01 11 8 15.01z"/></svg>
                        </div>
                        <div class="upload-text">
                            <span class="upload-title">Arrastra tu archivo aqui o haz click para seleccionar</span>
                            <span class="upload-subtitle">Archivos permitidos: Excel o CSV (max 10MB)</span>
                        </div>
                        <div class="upload-formats">
                            <span class="format-badge">.xlsx</span>
                            <span class="format-badge">.xls</span>
                            <span class="format-badge">.csv</span>
                        </div>
                    </div>
                    <div class="upload-selected" id="uploadSelected" style="display:none;">
                        <div class="file-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        </div>
                        <div class="file-info">
                            <span class="file-name" id="fileName"></span>
                            <span class="file-size" id="fileSize"></span>
                        </div>
                        <button type="button" class="file-remove" id="fileRemove" aria-label="Quitar archivo">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/advisors" class="btn-action btn-cancel">Cancelar</a>
                    <button type="submit" class="btn-action btn-submit" id="submitBtn" disabled>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        Importar
                    </button>
                </div>
            </form>
        </div>

        <!-- Info Panel -->
        <div class="info-panel">
            <!-- Expected Format -->
            <div class="info-card">
                <div class="info-header">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                    <span>Formato esperado</span>
                </div>
                <div class="info-content">
                    <p>El archivo debe contener las siguientes columnas en la primera fila:</p>
                    <div class="format-table-wrapper">
                        <table class="format-table">
                            <thead>
                                <tr>
                                    <th>Columna</th>
                                    <th>Req.</th>
                                    <th>Descripcion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>Nombres</code></td>
                                    <td><span class="req-badge req-yes">Si</span></td>
                                    <td>Nombre del asesor</td>
                                </tr>
                                <tr>
                                    <td><code>Apellidos</code></td>
                                    <td><span class="req-badge req-yes">Si</span></td>
                                    <td>Apellido del asesor</td>
                                </tr>
                                <tr>
                                    <td><code>Cedula</code></td>
                                    <td><span class="req-badge req-no">No</span></td>
                                    <td>Identificacion unica</td>
                                </tr>
                                <tr>
                                    <td><code>Email</code></td>
                                    <td><span class="req-badge req-no">No</span></td>
                                    <td>Si vacio, se genera automatico</td>
                                </tr>
                                <tr>
                                    <td><code>Campana</code></td>
                                    <td><span class="req-badge req-yes">Si</span></td>
                                    <td>Nombre exacto de la campana</td>
                                </tr>
                                <tr>
                                    <td><code>Contrato</code></td>
                                    <td><span class="req-badge req-no">No</span></td>
                                    <td>"completo" o "parcial"</td>
                                </tr>
                                <tr>
                                    <td><code>VPN</code></td>
                                    <td><span class="req-badge req-no">No</span></td>
                                    <td>"si" o "no" (default: no)</td>
                                </tr>
                                <tr>
                                    <td><code>Extras</code></td>
                                    <td><span class="req-badge req-no">No</span></td>
                                    <td>"si" o "no" (default: si)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="info-card">
                <div class="info-header">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
                    <span>Notas importantes</span>
                </div>
                <div class="info-content">
                    <ul class="tips-list">
                        <li>Si un asesor con la misma cedula ya existe, se <strong>actualizara</strong> en lugar de duplicarse.</li>
                        <li>La campana debe existir previamente en el sistema con el <strong>nombre exacto</strong>.</li>
                        <li>Las credenciales generadas se pueden descargar solo <strong>una vez</strong> despues de la importacion.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Results section — only shown after an import
    $importResults = $_SESSION['import_results'] ?? null;
    if ($importResults):
        $created = $importResults['created'] ?? 0;
        $updated = $importResults['updated'] ?? 0;
        $errors = $importResults['errors'] ?? [];
        $hasCredentials = !empty($importResults['has_credentials']);
    ?>
    <div class="results-section">
        <h3 class="results-title">Resultados de la importacion</h3>

        <!-- Stats -->
        <div class="results-stats">
            <div class="stat-mini accent-green">
                <div class="stat-icon" style="background:#dcfce7;color:#16a34a;">
                    <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= $created ?></span>
                    <span class="stat-label">Creados</span>
                </div>
            </div>
            <div class="stat-mini accent-blue">
                <div class="stat-icon" style="background:#eff6ff;color:#2563eb;">
                    <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M21 10.12h-6.78l2.74-2.82c-2.73-2.7-7.15-2.8-9.88-.1-2.73 2.71-2.73 7.08 0 9.79s7.15 2.71 9.88 0C18.32 15.65 19 14.08 19 12.1h2c0 1.98-.88 4.55-2.64 6.29-3.51 3.48-9.21 3.48-12.72 0-3.5-3.47-3.5-9.11 0-12.58 3.51-3.47 9.14-3.49 12.65 0L21 3v7.12z"/></svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= $updated ?></span>
                    <span class="stat-label">Actualizados</span>
                </div>
            </div>
            <div class="stat-mini accent-red">
                <div class="stat-icon" style="background:#fee2e2;color:#dc2626;">
                    <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= count($errors) ?></span>
                    <span class="stat-label">Errores</span>
                </div>
            </div>
        </div>

        <!-- Credentials download -->
        <?php if ($hasCredentials): ?>
        <div class="alert alert-info" style="margin-top:16px;">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;flex-shrink:0;"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
            <span>Se generaron credenciales para los nuevos asesores.</span>
            <a href="<?= BASE_URL ?>/advisors/import/credentials" class="btn btn-primary" style="margin-left:auto;padding:6px 14px;font-size:0.8rem;white-space:nowrap;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                Descargar Credenciales
            </a>
        </div>
        <?php endif; ?>

        <!-- Error list -->
        <?php if (!empty($errors)): ?>
        <div class="errors-panel" style="margin-top:16px;">
            <button type="button" class="errors-toggle" id="errorsToggle" aria-expanded="false" aria-controls="errorsList">
                <svg viewBox="0 0 24 24" fill="currentColor" class="toggle-chevron" style="width:18px;height:18px;transition:transform 0.2s;"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                <span>Ver <?= count($errors) ?> error<?= count($errors) !== 1 ? 'es' : '' ?></span>
            </button>
            <div class="errors-list" id="errorsList" style="display:none;">
                <?php foreach ($errors as $err): ?>
                <div class="error-row">
                    <span class="error-row-num">Fila <?= htmlspecialchars($err['row'] ?? '?') ?></span>
                    <span class="error-row-msg"><?= htmlspecialchars($err['message'] ?? 'Error desconocido') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
        // Clear session data after displaying
        unset($_SESSION['import_results']);
    endif;
    ?>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .import-page {
        max-width: 1100px;
        margin: 0 auto;
    }

    /* Grid Layout */
    .import-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 24px;
        align-items: start;
    }

    /* Upload Zone */
    .upload-zone {
        border: 2px dashed var(--corp-gray-200);
        border-radius: var(--card-radius);
        padding: 40px 32px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
        background: var(--corp-gray-50);
    }

    .upload-zone:hover,
    .upload-zone.dragover {
        border-color: var(--corp-primary);
        background: #eff6ff;
    }

    .upload-zone.has-file {
        border-style: solid;
        border-color: var(--corp-success);
        background: #f0fdf4;
        cursor: default;
    }

    .upload-icon {
        width: 64px;
        height: 64px;
        background: var(--corp-white, #fff);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        box-shadow: var(--card-shadow);
    }

    .upload-icon svg {
        width: 32px;
        height: 32px;
        fill: var(--corp-primary);
    }

    .upload-title {
        display: block;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--corp-gray-700);
        margin-bottom: 6px;
    }

    .upload-subtitle {
        display: block;
        font-size: 0.8rem;
        color: var(--corp-gray-500);
    }

    .upload-formats {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 20px;
    }

    .format-badge {
        padding: 4px 12px;
        background: var(--corp-white, #fff);
        border: 1px solid var(--corp-gray-200);
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--corp-gray-500);
    }

    /* Selected File */
    .upload-selected {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px;
        background: var(--corp-white, #fff);
        border-radius: 8px;
    }

    .file-icon {
        width: 48px;
        height: 48px;
        background: #dcfce7;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .file-icon svg {
        width: 24px;
        height: 24px;
        fill: #16a34a;
    }

    .file-info {
        flex: 1;
        text-align: left;
        min-width: 0;
    }

    .file-name {
        display: block;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--corp-gray-900);
        margin-bottom: 2px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .file-size {
        font-size: 0.8rem;
        color: var(--corp-gray-500);
    }

    .file-remove {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: none;
        background: #fee2e2;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s;
        flex-shrink: 0;
    }

    .file-remove:hover { background: #fecaca; }

    .file-remove svg {
        width: 16px;
        height: 16px;
        fill: #dc2626;
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 28px;
        padding-top: 20px;
        border-top: 1px solid var(--corp-gray-100);
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: var(--input-radius);
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.15s ease;
        border: none;
        cursor: pointer;
    }

    .btn-action svg { width: 18px; height: 18px; }

    .btn-submit {
        background: var(--corp-primary);
        color: #fff;
    }

    .btn-submit:hover:not(:disabled) { background: var(--corp-primary-dark); }
    .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

    .btn-cancel {
        background: var(--corp-gray-100);
        color: var(--corp-gray-600);
    }

    .btn-cancel:hover { background: var(--corp-gray-200); }

    /* Info Panel */
    .info-panel {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .info-card {
        background: var(--corp-white, #fff);
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--card-radius);
        overflow: hidden;
    }

    .info-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        background: var(--corp-gray-50);
        border-bottom: 1px solid var(--corp-gray-200);
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--corp-gray-700);
    }

    .info-header svg {
        width: 18px;
        height: 18px;
        fill: var(--corp-primary);
        flex-shrink: 0;
    }

    .info-content {
        padding: 18px;
    }

    .info-content p {
        font-size: 0.8rem;
        color: var(--corp-gray-500);
        margin: 0 0 14px 0;
        line-height: 1.5;
    }

    /* Format Table */
    .format-table-wrapper { overflow-x: auto; }

    .format-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.78rem;
    }

    .format-table th,
    .format-table td {
        padding: 8px 10px;
        border: 1px solid var(--corp-gray-200);
        text-align: left;
    }

    .format-table th {
        background: var(--corp-gray-100);
        font-weight: 600;
        color: var(--corp-gray-700);
        white-space: nowrap;
    }

    .format-table td { color: var(--corp-gray-600); }

    .format-table code {
        background: var(--corp-gray-100);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--corp-gray-700);
    }

    .req-badge {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .req-yes { background: #dcfce7; color: #15803d; }
    .req-no { background: var(--corp-gray-100); color: var(--corp-gray-500); }

    /* Tips */
    .tips-list {
        margin: 0;
        padding-left: 18px;
    }

    .tips-list li {
        font-size: 0.8rem;
        color: var(--corp-gray-600);
        margin-bottom: 10px;
        line-height: 1.5;
    }

    .tips-list li:last-child { margin-bottom: 0; }

    /* Results Section */
    .results-section { margin-top: 28px; }

    .results-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--corp-gray-900);
        margin: 0 0 16px 0;
    }

    .results-stats {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
    }

    .results-stats .stat-mini {
        flex: 1;
        min-width: 140px;
    }

    /* Errors Panel */
    .errors-panel {
        background: var(--corp-white, #fff);
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--card-radius);
        overflow: hidden;
    }

    .errors-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 14px 18px;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--corp-danger);
    }

    .errors-toggle:hover { background: var(--corp-gray-50); }

    .errors-toggle[aria-expanded="true"] .toggle-chevron {
        transform: rotate(90deg);
    }

    .errors-list {
        border-top: 1px solid var(--corp-gray-200);
        max-height: 300px;
        overflow-y: auto;
    }

    .error-row {
        display: flex;
        align-items: baseline;
        gap: 12px;
        padding: 10px 18px;
        font-size: 0.8rem;
        border-bottom: 1px solid var(--corp-gray-100);
    }

    .error-row:last-child { border-bottom: none; }

    .error-row-num {
        font-weight: 600;
        color: var(--corp-danger);
        white-space: nowrap;
        flex-shrink: 0;
    }

    .error-row-msg { color: var(--corp-gray-600); }

    /* Dark mode */
    [data-theme="dark"] .upload-zone {
        background: var(--corp-gray-800);
        border-color: var(--corp-gray-600);
    }

    [data-theme="dark"] .upload-zone:hover,
    [data-theme="dark"] .upload-zone.dragover {
        border-color: var(--corp-primary);
        background: rgba(37, 99, 235, 0.1);
    }

    [data-theme="dark"] .upload-zone.has-file {
        border-color: var(--corp-success);
        background: rgba(5, 150, 105, 0.1);
    }

    [data-theme="dark"] .upload-icon { background: var(--corp-gray-700); }
    [data-theme="dark"] .format-badge { background: var(--corp-gray-700); border-color: var(--corp-gray-600); }
    [data-theme="dark"] .upload-selected { background: var(--corp-gray-700); }
    [data-theme="dark"] .file-icon { background: rgba(22, 163, 106, 0.2); }
    [data-theme="dark"] .info-card { background: var(--corp-gray-800); border-color: var(--corp-gray-600); }
    [data-theme="dark"] .info-header { background: var(--corp-gray-700); border-color: var(--corp-gray-600); }
    [data-theme="dark"] .format-table th { background: var(--corp-gray-700); }
    [data-theme="dark"] .format-table th,
    [data-theme="dark"] .format-table td { border-color: var(--corp-gray-600); }
    [data-theme="dark"] .format-table code { background: var(--corp-gray-700); }
    [data-theme="dark"] .errors-panel { background: var(--corp-gray-800); border-color: var(--corp-gray-600); }
    [data-theme="dark"] .errors-list { border-color: var(--corp-gray-600); }
    [data-theme="dark"] .error-row { border-color: var(--corp-gray-700); }
    [data-theme="dark"] .req-yes { background: rgba(22, 163, 106, 0.2); color: #4ade80; }
    [data-theme="dark"] .req-no { background: var(--corp-gray-700); }
    [data-theme="dark"] .btn-cancel { background: var(--corp-gray-700); color: var(--corp-gray-300); }
    [data-theme="dark"] .btn-cancel:hover { background: var(--corp-gray-600); }

    /* Responsive */
    @media (max-width: 900px) {
        .import-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 600px) {
        .upload-zone { padding: 28px 16px; }
        .form-actions { flex-direction: column; }
        .btn-action { justify-content: center; }
        .results-stats { flex-direction: column; }
        .results-stats .stat-mini { min-width: auto; }
    }
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts
    document.querySelectorAll('.alert-dismissible').forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-10px)';
            setTimeout(function() { el.remove(); }, 400);
        }, 5000);
    });

    var uploadZone = document.getElementById('uploadZone');
    var fileInput = document.getElementById('fileInput');
    var uploadContent = document.getElementById('uploadContent');
    var uploadSelected = document.getElementById('uploadSelected');
    var fileName = document.getElementById('fileName');
    var fileSize = document.getElementById('fileSize');
    var fileRemove = document.getElementById('fileRemove');
    var submitBtn = document.getElementById('submitBtn');
    var maxSize = 10 * 1024 * 1024;

    // Click to select
    uploadZone.addEventListener('click', function(e) {
        if (e.target !== fileRemove && !fileRemove.contains(e.target)) {
            fileInput.click();
        }
    });

    // Drag & Drop
    uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });

    uploadZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
    });

    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        var files = e.dataTransfer.files;
        if (files.length > 0) {
            if (validateFile(files[0])) {
                fileInput.files = files;
                showSelectedFile(files[0]);
            }
        }
    });

    // File selected via input
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            if (validateFile(this.files[0])) {
                showSelectedFile(this.files[0]);
            } else {
                this.value = '';
            }
        }
    });

    // Remove file
    fileRemove.addEventListener('click', function(e) {
        e.stopPropagation();
        fileInput.value = '';
        hideSelectedFile();
    });

    function validateFile(file) {
        var allowedExtensions = ['.xlsx', '.xls', '.csv'];
        var ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
        if (allowedExtensions.indexOf(ext) === -1) {
            if (typeof showToast === 'function') {
                showToast('Formato no permitido. Usa .xlsx, .xls o .csv', 'error');
            }
            return false;
        }
        if (file.size > maxSize) {
            if (typeof showToast === 'function') {
                showToast('El archivo excede el limite de 10MB', 'error');
            }
            return false;
        }
        return true;
    }

    function showSelectedFile(file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        uploadContent.style.display = 'none';
        uploadSelected.style.display = 'flex';
        uploadZone.classList.add('has-file');
        submitBtn.disabled = false;
    }

    function hideSelectedFile() {
        uploadContent.style.display = 'block';
        uploadSelected.style.display = 'none';
        uploadZone.classList.remove('has-file');
        submitBtn.disabled = true;
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Error list toggle
    var errorsToggle = document.getElementById('errorsToggle');
    var errorsList = document.getElementById('errorsList');
    if (errorsToggle && errorsList) {
        errorsToggle.addEventListener('click', function() {
            var expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', String(!expanded));
            errorsList.style.display = expanded ? 'none' : 'block';
        });
    }
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
