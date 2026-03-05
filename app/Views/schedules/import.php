<?php
$pageTitle = 'Importar Dimensionamiento';
$currentPage = 'schedules';

ob_start();
?>

<div class="card card-custom">
    <div class="card-header">
        <h3 class="card-title">
            <i class="la la-file-excel text-success mr-2"></i>
            Importar Dimensionamiento
        </h3>
        <div class="card-toolbar">
            <a href="<?= BASE_URL ?>/schedules" class="btn btn-light-primary font-weight-bolder">
                <i class="la la-arrow-left"></i> Volver
            </a>
        </div>
    </div>
    <form action="<?= BASE_URL ?>/schedules/import" method="POST" enctype="multipart/form-data">
        <div class="card-body">
            <div class="alert alert-custom alert-light-info fade show mb-8" role="alert">
                <div class="alert-icon"><i class="la la-info-circle"></i></div>
                <div class="alert-text">
                    <strong>Formato del archivo Excel:</strong><br>
                    - Fila 1: "Horas ACD" seguido de las fechas del mes<br>
                    - Fila 2: Dias de la semana (opcional)<br>
                    - Filas 3-26: Horas del dia (00:00 a 23:00) con la cantidad de asesores requeridos por hora
                </div>
            </div>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Campana <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <select name="campaign_id" class="form-control" required>
                        <option value="">Seleccione una campana...</option>
                        <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?= $campaign['id'] ?>"><?= htmlspecialchars($campaign['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Periodo <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <div class="row">
                        <div class="col-6">
                            <select name="periodo_mes" class="form-control" required>
                                <option value="">Mes...</option>
                                <?php
                                $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                                          'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                                foreach ($meses as $i => $mes): ?>
                                <option value="<?= $i + 1 ?>" <?= date('n') == ($i + 1) ? 'selected' : '' ?>><?= $mes ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <select name="periodo_anio" class="form-control" required>
                                <?php for ($y = date('Y'); $y <= date('Y') + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= date('Y') == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group row">
                <label class="col-lg-3 col-form-label">Archivo Excel <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <div class="custom-file">
                        <input type="file" name="excel_file" class="custom-file-input" id="excelFile"
                               accept=".xlsx,.xls,.csv" required>
                        <label class="custom-file-label" for="excelFile">Seleccionar archivo...</label>
                    </div>
                    <span class="form-text text-muted">Formatos permitidos: .xlsx, .xls, .csv (max 10MB)</span>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col-lg-3"></div>
                <div class="col-lg-9">
                    <button type="submit" class="btn btn-success mr-2">
                        <i class="la la-upload"></i> Importar
                    </button>
                    <a href="<?= BASE_URL ?>/schedules" class="btn btn-secondary">Cancelar</a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();

$extraScripts = ['
<script>
    // Mostrar nombre del archivo seleccionado
    $(".custom-file-input").on("change", function() {
        var fileName = $(this).val().split("\\\\").pop();
        $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
    });
</script>
'];

include APP_PATH . '/Views/layouts/main.php';
?>
