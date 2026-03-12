<?php
/**
 * TurnoFlow - Importar Dimensionamiento
 * Diseno empresarial profesional
 */

$pageTitle = 'Importar Dimensionamiento';
$currentPage = 'schedules';

ob_start();
?>

<div class="import-page">
    <!-- Header -->
    <div class="page-header">
        <div class="header-content">
            <a href="<?= BASE_URL ?>/schedules" class="back-link">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Volver a Horarios
            </a>
            <h1 class="header-title">Importar Dimensionamiento</h1>
            <p class="header-subtitle">Carga el archivo Excel con los requerimientos de personal por hora</p>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
    <div class="flash-banner flash-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
    <div class="flash-banner flash-error"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <div class="import-grid">
        <!-- Form Panel -->
        <div class="form-panel">
            <form action="<?= BASE_URL ?>/schedules/import" method="POST" enctype="multipart/form-data" id="importForm">
                <!-- Campaign Selection -->
                <div class="form-section">
                    <label class="form-label">
                        <span class="label-text">Campaña</span>
                        <span class="label-required">*</span>
                    </label>
                    <div class="select-wrapper">
                        <select name="campaign_id" class="form-select" required>
                            <option value="">Seleccióne una campaña...</option>
                            <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?= $campaign['id'] ?>"><?= htmlspecialchars($campaign['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="select-arrow" viewBox="0 0 24 24" fill="currentColor"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>
                    </div>
                </div>

                <!-- Period Selection -->
                <div class="form-section">
                    <label class="form-label">
                        <span class="label-text">Periodo</span>
                        <span class="label-required">*</span>
                    </label>
                    <div class="period-grid">
                        <div class="select-wrapper">
                            <select name="periodo_mes" class="form-select" required>
                                <?php
                                $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                                          'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                                foreach ($meses as $i => $mes): ?>
                                <option value="<?= $i + 1 ?>" <?= date('n') == ($i + 1) ? 'selected' : '' ?>><?= $mes ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="select-arrow" viewBox="0 0 24 24" fill="currentColor"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>
                        </div>
                        <div class="select-wrapper">
                            <select name="periodo_anio" class="form-select" required>
                                <?php for ($y = date('Y'); $y <= date('Y') + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= date('Y') == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <svg class="select-arrow" viewBox="0 0 24 24" fill="currentColor"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>
                        </div>
                    </div>
                </div>

                <!-- File Upload -->
                <div class="form-section">
                    <label class="form-label">
                        <span class="label-text">Archivo Excel</span>
                        <span class="label-required">*</span>
                    </label>
                    <div class="upload-zone" id="uploadZone">
                        <input type="file" name="excel_file" id="fileInput" accept=".xlsx,.xls,.csv" required hidden>
                        <div class="upload-content">
                            <div class="upload-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41 1.41L11 14.84V19h2v-4.16l1.59 1.59L16 15.01 12.01 11 8 15.01z"/></svg>
                            </div>
                            <div class="upload-text">
                                <span class="upload-title">Arrastra tu archivo aqui</span>
                                <span class="upload-subtitle">o <span class="upload-link">seleccióna un archivo</span></span>
                            </div>
                            <div class="upload-formats">
                                <span class="format-badge">.xlsx</span>
                                <span class="format-badge">.xls</span>
                                <span class="format-badge">.csv</span>
                            </div>
                        </div>
                        <div class="upload-selected" id="uploadSelected" style="display: none;">
                            <div class="file-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                            </div>
                            <div class="file-info">
                                <span class="file-name" id="fileName"></span>
                                <span class="file-size" id="fileSize"></span>
                            </div>
                            <button type="button" class="file-remove" id="fileRemove">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/schedules" class="btn-action btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-action btn-primary">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        Importar Archivo
                    </button>
                </div>
            </form>
        </div>

        <!-- Info Panel -->
        <div class="info-panel">
            <div class="info-card">
                <div class="info-header">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
                    <span>Formato del archivo</span>
                </div>
                <div class="info-content">
                    <p>El archivo Excel debe tener la siguiente estructura:</p>
                    <ul class="format-list">
                        <li><strong>Fila 1:</strong> "Horas ACD" seguido de las fechas del mes</li>
                        <li><strong>Fila 2:</strong> Dias de la semana (opcional)</li>
                        <li><strong>Filas 3-26:</strong> Horas (00:00 a 23:00) con cantidad de asesores requeridos</li>
                    </ul>
                </div>
            </div>

            <div class="info-card">
                <div class="info-header">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 6h2v4h-2z"/></svg>
                    <span>Ejemplo visual</span>
                </div>
                <div class="info-content">
                    <div class="example-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Horas ACD</th>
                                    <th>1</th>
                                    <th>2</th>
                                    <th>3</th>
                                    <th>...</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="row-days">
                                    <td></td>
                                    <td>Dom</td>
                                    <td>Lun</td>
                                    <td>Mar</td>
                                    <td>...</td>
                                </tr>
                                <tr>
                                    <td>00:00</td>
                                    <td>1</td>
                                    <td>1</td>
                                    <td>1</td>
                                    <td>...</td>
                                </tr>
                                <tr>
                                    <td>01:00</td>
                                    <td>1</td>
                                    <td>1</td>
                                    <td>1</td>
                                    <td>...</td>
                                </tr>
                                <tr>
                                    <td>...</td>
                                    <td>...</td>
                                    <td>...</td>
                                    <td>...</td>
                                    <td>...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
        margin-bottom: 28px;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        font-weight: 500;
        color: #64748b;
        text-decoration: none;
        margin-bottom: 12px;
        transition: color 0.15s;
    }

    .back-link:hover {
        color: #2563eb;
    }

    .back-link svg {
        width: 16px;
        height: 16px;
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

    /* Grid Layout */
    .import-grid {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 24px;
        align-items: start;
    }

    /* Form Panel */
    .form-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 28px;
    }

    .form-section {
        margin-bottom: 24px;
    }

    .form-label {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 8px;
    }

    .label-text {
        font-size: 0.875rem;
        font-weight: 600;
        color: #334155;
    }

    .label-required {
        color: #dc2626;
    }

    .select-wrapper {
        position: relative;
    }

    .form-select {
        width: 100%;
        padding: 12px 40px 12px 14px;
        font-size: 0.875rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        color: #334155;
        appearance: none;
        cursor: pointer;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .form-select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .select-arrow {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 20px;
        height: 20px;
        fill: #94a3b8;
        pointer-events: none;
    }

    .period-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    /* Upload Zone */
    .upload-zone {
        border: 2px dashed #e2e8f0;
        border-radius: 12px;
        padding: 32px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #f8fafc;
    }

    .upload-zone:hover,
    .upload-zone.dragover {
        border-color: #2563eb;
        background: #eff6ff;
    }

    .upload-zone.has-file {
        border-style: solid;
        border-color: #16a34a;
        background: #f0fdf4;
        cursor: default;
    }

    .upload-icon {
        width: 56px;
        height: 56px;
        background: #fff;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .upload-icon svg {
        width: 28px;
        height: 28px;
        fill: #2563eb;
    }

    .upload-title {
        display: block;
        font-size: 0.95rem;
        font-weight: 600;
        color: #334155;
        margin-bottom: 4px;
    }

    .upload-subtitle {
        font-size: 0.85rem;
        color: #64748b;
    }

    .upload-link {
        color: #2563eb;
        font-weight: 500;
        cursor: pointer;
    }

    .upload-link:hover {
        text-decoration: underline;
    }

    .upload-formats {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 16px;
    }

    .format-badge {
        padding: 4px 10px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        font-size: 0.75rem;
        font-weight: 500;
        color: #64748b;
    }

    /* Selected File */
    .upload-selected {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px;
        background: #fff;
        border-radius: 8px;
    }

    .file-icon {
        width: 44px;
        height: 44px;
        background: #dcfce7;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .file-icon svg {
        width: 22px;
        height: 22px;
        fill: #16a34a;
    }

    .file-info {
        flex: 1;
        text-align: left;
    }

    .file-name {
        display: block;
        font-size: 0.9rem;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 2px;
    }

    .file-size {
        font-size: 0.8rem;
        color: #64748b;
    }

    .file-remove {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: #fee2e2;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s;
    }

    .file-remove:hover {
        background: #fecaca;
    }

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
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid #f1f5f9;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
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
    }

    .btn-secondary:hover {
        background: #e2e8f0;
    }

    /* Info Panel */
    .info-panel {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .info-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }

    .info-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.85rem;
        font-weight: 600;
        color: #334155;
    }

    .info-header svg {
        width: 18px;
        height: 18px;
        fill: #2563eb;
    }

    .info-content {
        padding: 18px;
    }

    .info-content p {
        font-size: 0.85rem;
        color: #64748b;
        margin: 0 0 12px 0;
    }

    .format-list {
        margin: 0;
        padding-left: 18px;
    }

    .format-list li {
        font-size: 0.8rem;
        color: #475569;
        margin-bottom: 8px;
        line-height: 1.5;
    }

    .format-list li:last-child {
        margin-bottom: 0;
    }

    /* Example Table */
    .example-table {
        overflow-x: auto;
    }

    .example-table table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }

    .example-table th,
    .example-table td {
        padding: 8px 10px;
        border: 1px solid #e2e8f0;
        text-align: center;
    }

    .example-table th {
        background: #f1f5f9;
        font-weight: 600;
        color: #334155;
    }

    .example-table td {
        color: #64748b;
    }

    .example-table .row-days td {
        background: #f8fafc;
        font-weight: 500;
        color: #64748b;
        font-size: 0.7rem;
    }

    /* Responsive */
    @media (max-width: 900px) {
        .import-grid {
            grid-template-columns: 1fr;
        }

        .info-panel {
            flex-direction: row;
        }

        .info-card {
            flex: 1;
        }
    }

    @media (max-width: 600px) {
        .form-panel {
            padding: 20px;
        }

        .period-grid {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column;
        }

        .btn-action {
            justify-content: center;
        }

        .info-panel {
            flex-direction: column;
        }
    }
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('fileInput');
    const uploadContent = uploadZone.querySelector('.upload-content');
    const uploadSelected = document.getElementById('uploadSelected');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const fileRemove = document.getElementById('fileRemove');

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
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            showSelectedFile(files[0]);
        }
    });

    // File selected
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            showSelectedFile(this.files[0]);
        }
    });

    // Remove file
    fileRemove.addEventListener('click', function(e) {
        e.stopPropagation();
        fileInput.value = '';
        hideSelectedFile();
    });

    function showSelectedFile(file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        uploadContent.style.display = 'none';
        uploadSelected.style.display = 'flex';
        uploadZone.classList.add('has-file');
    }

    function hideSelectedFile() {
        uploadContent.style.display = 'block';
        uploadSelected.style.display = 'none';
        uploadZone.classList.remove('has-file');
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
