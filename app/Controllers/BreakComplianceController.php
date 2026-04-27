<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use PDO;
use Throwable;
use App\Services\AuthService;
use App\Traits\FlashMessageTrait;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

require_once APP_PATH . '/Services/AuthService.php';
require_once APP_PATH . '/Services/BreakImportService.php';
require_once APP_PATH . '/Traits/FlashMessageTrait.php';

class BreakComplianceController
{
    use FlashMessageTrait;

    /**
     * Main dashboard — cruce of planned vs actual break data.
     */
    public function index(): void
    {
        AuthService::requirePermission('breaks.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $campaigns = $this->loadCampaigns($pdo, $user);

        $campaignId = (int)($_GET['campaign_id'] ?? 0);
        $year = (int)($_GET['year'] ?? (int)date('Y'));
        $month = (int)($_GET['month'] ?? (int)date('n'));

        if ($month < 1 || $month > 12) {
            $month = (int)date('n');
        }
        if ($year < 2000) {
            $year = (int)date('Y');
        }

        $cruceData = [];
        $campaign = null;
        $breakImport = null;
        $duracionBreakMin = 0;

        if ($campaignId > 0) {
            // Verify campaign access
            $stmtCamp = $pdo->prepare("SELECT id, nombre, duracion_break_min FROM campaigns WHERE id = :id AND estado = 'activa'");
            $stmtCamp->execute([':id' => $campaignId]);
            $campaign = $stmtCamp->fetch(PDO::FETCH_ASSOC);

            if ($campaign) {
                $duracionBreakMin = (int)($campaign['duracion_break_min'] ?? 30);

                $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $fechaInicio = sprintf('%04d-%02d-01', $year, $month);
                $fechaFin = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

                // Check import status
                $stmtImport = $pdo->prepare("
                    SELECT * FROM break_imports
                    WHERE campaign_id = :cid AND periodo_anio = :y AND periodo_mes = :m
                ");
                $stmtImport->execute([':cid' => $campaignId, ':y' => $year, ':m' => $month]);
                $breakImport = $stmtImport->fetch(PDO::FETCH_ASSOC);

                // Cruce query
                $cruceData = $this->getCruceData($pdo, $campaignId, $fechaInicio, $fechaFin);
            }
        }

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $pageTitle = 'Cumplimiento Break';
        $currentPage = 'breaks';

        include APP_PATH . '/Views/breaks/index.php';
    }

    /**
     * Show the upload form.
     */
    public function showImport(): void
    {
        AuthService::requirePermission('breaks.import');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $campaigns = $this->loadCampaigns($pdo, $user);

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $importResults = $_SESSION['break_import_results'] ?? null;
        unset($_SESSION['break_import_results']);

        $pageTitle = 'Importar Breaks';
        $currentPage = 'breaks';

        include APP_PATH . '/Views/breaks/import.php';
    }

    /**
     * Process the uploaded Excel file.
     */
    public function import(): void
    {
        AuthService::requirePermission('breaks.import');

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $campaignId  = (int)($_POST['campaign_id'] ?? 0);
        $periodoMes  = (int)($_POST['periodo_mes'] ?? 0);
        $periodoAnio = (int)($_POST['periodo_anio'] ?? 0);

        if ($campaignId <= 0 || $periodoMes < 1 || $periodoMes > 12 || $periodoAnio < 2000) {
            $this->setFlash('error', 'Datos de importacion invalidos.');
            header('Location: ' . BASE_URL . '/breaks/import');
            exit;
        }

        if (empty($_FILES['excel_file']) || !is_array($_FILES['excel_file'])) {
            $this->setFlash('error', 'No se recibio ningun archivo.');
            header('Location: ' . BASE_URL . '/breaks/import');
            exit;
        }

        $file = $_FILES['excel_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->setFlash('error', 'Error al subir el archivo.');
            header('Location: ' . BASE_URL . '/breaks/import');
            exit;
        }

        $originalName = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            $this->setFlash('error', 'Formato no permitido. Usa .xlsx, .xls o .csv.');
            header('Location: ' . BASE_URL . '/breaks/import');
            exit;
        }

        // Validate campaign access
        $pdo = Database::getConnection();
        $campaign = $this->validateCampaignAccess($pdo, $user, $campaignId);
        if (!$campaign) {
            $this->setFlash('error', 'No tienes permisos sobre esa campana o no esta activa.');
            header('Location: ' . BASE_URL . '/breaks/import');
            exit;
        }

        // Move to uploads/
        $uploadPath = rtrim($_ENV['UPLOAD_PATH'] ?? (BASE_PATH . '/uploads'), "/\\");
        if (!is_dir($uploadPath) && !mkdir($uploadPath, 0777, true) && !is_dir($uploadPath)) {
            $this->setFlash('error', 'No se pudo preparar la carpeta de carga.');
            header('Location: ' . BASE_URL . '/breaks/import');
            exit;
        }

        $storedName = sprintf(
            'break_c%d_%04d_%02d_%s.%s',
            $campaignId,
            $periodoAnio,
            $periodoMes,
            date('Ymd_His'),
            $extension
        );
        $targetPath = $uploadPath . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
            $this->setFlash('error', 'No se pudo guardar el archivo cargado.');
            header('Location: ' . BASE_URL . '/breaks/import');
            exit;
        }

        try {
            $result = \App\Services\BreakImportService::processExcel(
                $targetPath,
                $campaignId,
                $periodoMes,
                $periodoAnio,
                (int)$user['id']
            );

            // Delete uploaded file after processing
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }

            $msg = sprintf(
                'Importacion completada para %s (%02d/%04d). %d asesores matched, %d sin match, %d fechas procesadas.',
                $campaign['nombre'],
                $periodoMes,
                $periodoAnio,
                $result['matched'],
                $result['unmatched'],
                $result['dates_found']
            );

            $this->setFlash('success', $msg);
            $_SESSION['break_import_results'] = $result;
        } catch (Throwable $e) {
            // Delete uploaded file on error too
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }

            error_log('Break import failed: ' . $e->getMessage());
            $this->setFlash('error', 'No se pudo procesar el archivo: ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '/breaks/import');
        exit;
    }

    /**
     * JSON API — cruce data for AJAX table loading.
     */
    public function reportData(): void
    {
        AuthService::requirePermission('breaks.view');

        $pdo = Database::getConnection();

        $campaignId = (int)($_GET['campaign_id'] ?? 0);
        $year = (int)($_GET['year'] ?? (int)date('Y'));
        $month = (int)($_GET['month'] ?? (int)date('n'));

        if ($campaignId <= 0 || $month < 1 || $month > 12 || $year < 2000) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Parametros invalidos']);
            exit;
        }

        // Get campaign info for duracion_break_min
        $stmtCamp = $pdo->prepare("SELECT id, duracion_break_min FROM campaigns WHERE id = :id AND estado = 'activa'");
        $stmtCamp->execute([':id' => $campaignId]);
        $campaign = $stmtCamp->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Campana no encontrada']);
            exit;
        }

        $duracionBreakMin = (int)($campaign['duracion_break_min'] ?? 30);
        $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $fechaInicio = sprintf('%04d-%02d-01', $year, $month);
        $fechaFin = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $data = $this->getCruceData($pdo, $campaignId, $fechaInicio, $fechaFin);

        // Add computed fields
        foreach ($data as &$row) {
            $plannedMinutes = (int)$row['planned_slots'] * $duracionBreakMin;
            $row['planned_minutes'] = $plannedMinutes;
            $row['exceso_computed'] = max(0, round((float)$row['actual_minutes'] - $plannedMinutes, 2));
        }
        unset($row);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    /**
     * Export cruce data as Excel.
     */
    public function export(): void
    {
        AuthService::requirePermission('breaks.export');

        $pdo = Database::getConnection();

        $campaignId = (int)($_GET['campaign_id'] ?? 0);
        $year = (int)($_GET['year'] ?? (int)date('Y'));
        $month = (int)($_GET['month'] ?? (int)date('n'));

        if ($campaignId <= 0 || $month < 1 || $month > 12 || $year < 2000) {
            $this->setFlash('error', 'Parametros invalidos para exportacion.');
            header('Location: ' . BASE_URL . '/breaks');
            exit;
        }

        $stmtCamp = $pdo->prepare("SELECT id, nombre, duracion_break_min FROM campaigns WHERE id = :id AND estado = 'activa'");
        $stmtCamp->execute([':id' => $campaignId]);
        $campaign = $stmtCamp->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            $this->setFlash('error', 'Campana no encontrada.');
            header('Location: ' . BASE_URL . '/breaks');
            exit;
        }

        $duracionBreakMin = (int)($campaign['duracion_break_min'] ?? 30);
        $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $fechaInicio = sprintf('%04d-%02d-01', $year, $month);
        $fechaFin = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $data = $this->getCruceData($pdo, $campaignId, $fechaInicio, $fechaFin);

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Cumplimiento Break');

            // Headers
            $headers = [
                'Nombre', 'Cedula', 'Break Planificado (min)',
                'Break Real (min)', 'Exceso Excel (min)',
                'Exceso Computado (min)', 'Horas Trabajadas', 'Dias con Datos',
            ];
            foreach ($headers as $col => $header) {
                $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
                $cell->setValue($header);
                $cell->getStyle()->getFont()->setBold(true);
            }

            // Style header
            $sheet->getStyle('A1:H1')->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            // Data rows
            foreach ($data as $i => $row) {
                $r = $i + 2;
                $plannedMinutes = (int)$row['planned_slots'] * $duracionBreakMin;
                $excesoComputed = max(0, round((float)$row['actual_minutes'] - $plannedMinutes, 2));

                $sheet->setCellValueByColumnAndRow(1, $r, $row['apellidos'] . ' ' . $row['nombres']);
                $sheet->setCellValueByColumnAndRow(2, $r, $row['cedula'] ?? '');
                $sheet->setCellValueByColumnAndRow(3, $r, $plannedMinutes);
                $sheet->setCellValueByColumnAndRow(4, $r, round((float)$row['actual_minutes'], 2));
                $sheet->setCellValueByColumnAndRow(5, $r, round((float)$row['exceso_excel'], 2));
                $sheet->setCellValueByColumnAndRow(6, $r, $excesoComputed);
                $sheet->setCellValueByColumnAndRow(7, $r, round((float)$row['horas_trabajadas'], 2));
                $sheet->setCellValueByColumnAndRow(8, $r, (int)$row['dias_con_datos']);
            }

            // Auto-size columns
            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            $campName = str_replace(' ', '_', $campaign['nombre']);
            $filename = sprintf('Break_Compliance_%s_%s_%d.xlsx', $campName, $monthNames[$month], $year);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Throwable $e) {
            error_log('Break export error: ' . $e->getMessage());
            $this->setFlash('error', 'Error al generar el archivo Excel.');
            header('Location: ' . BASE_URL . '/breaks');
            exit;
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Load campaigns filtered by user role.
     */
    private function loadCampaigns(PDO $pdo, array $user): array
    {
        if (AuthService::canManageAllCampaigns($user)) {
            $stmt = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        } else {
            $stmt = $pdo->prepare("
                SELECT id, nombre FROM campaigns
                WHERE supervisor_id = :uid AND estado = 'activa'
                ORDER BY nombre
            ");
            $stmt->execute([':uid' => $user['id']]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Validate that the current user can access the given campaign.
     */
    private function validateCampaignAccess(PDO $pdo, array $user, int $campaignId): array|false
    {
        if (AuthService::canManageAllCampaigns($user)) {
            $stmt = $pdo->prepare("SELECT id, nombre FROM campaigns WHERE id = :id AND estado = 'activa'");
            $stmt->execute([':id' => $campaignId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, nombre FROM campaigns
                WHERE id = :id AND supervisor_id = :uid AND estado = 'activa'
            ");
            $stmt->execute([':id' => $campaignId, ':uid' => $user['id']]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Execute the cruce query: planned breaks vs actual break data.
     */
    private function getCruceData(PDO $pdo, int $campaignId, string $fechaInicio, string $fechaFin): array
    {
        $stmt = $pdo->prepare("
            SELECT
                a.id, a.nombres, a.apellidos, a.cedula,
                COALESCE(planned.break_slots, 0) as planned_slots,
                COALESCE(actual.total_bk_normal, 0) as actual_minutes,
                COALESCE(actual.total_exceso, 0) as exceso_excel,
                COALESCE(actual.total_horas, 0) as horas_trabajadas,
                COALESCE(actual.dias_con_datos, 0) as dias_con_datos
            FROM advisors a
            LEFT JOIN (
                SELECT advisor_id, COUNT(*) as break_slots
                FROM shift_assignments
                WHERE campaign_id = :cid AND tipo = 'break'
                  AND fecha BETWEEN :ini AND :fin
                GROUP BY advisor_id
            ) planned ON planned.advisor_id = a.id
            LEFT JOIN (
                SELECT advisor_id,
                       SUM(bk_normal_minutes) as total_bk_normal,
                       SUM(exceso_minutes) as total_exceso,
                       SUM(horas_trabajadas) as total_horas,
                       COUNT(*) as dias_con_datos
                FROM break_snapshots
                WHERE campaign_id = :cid2 AND fecha BETWEEN :ini2 AND :fin2
                GROUP BY advisor_id
            ) actual ON actual.advisor_id = a.id
            WHERE a.campaign_id = :cid3 AND a.estado = 'activo'
            ORDER BY a.apellidos, a.nombres
        ");
        $stmt->execute([
            ':cid'  => $campaignId,
            ':ini'  => $fechaInicio,
            ':fin'  => $fechaFin,
            ':cid2' => $campaignId,
            ':ini2' => $fechaInicio,
            ':fin2' => $fechaFin,
            ':cid3' => $campaignId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
