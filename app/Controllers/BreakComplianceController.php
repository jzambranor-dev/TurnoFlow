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
     * Main dashboard -- cruce of planned vs actual break data.
     * Now date-based instead of month-based.
     */
    public function index(): void
    {
        AuthService::requirePermission('breaks.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $campaigns = $this->loadCampaigns($pdo, $user);

        $campaignId = (int)($_GET['campaign_id'] ?? 0);
        $fecha = $_GET['fecha'] ?? date('Y-m-d');

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }

        $cruceData = [];
        $campaign = null;
        $breakImport = null;

        if ($campaignId > 0) {
            $stmtCamp = $pdo->prepare("SELECT id, nombre FROM campaigns WHERE id = :id AND estado = 'activa'");
            $stmtCamp->execute([':id' => $campaignId]);
            $campaign = $stmtCamp->fetch(PDO::FETCH_ASSOC);

            if ($campaign) {
                // Check import status for this date
                $stmtImport = $pdo->prepare("
                    SELECT * FROM break_imports
                    WHERE campaign_id = :cid AND fecha = :fecha
                ");
                $stmtImport->execute([':cid' => $campaignId, ':fecha' => $fecha]);
                $breakImport = $stmtImport->fetch(PDO::FETCH_ASSOC);

                // Cruce query
                $cruceData = $this->getCruceData($pdo, $campaignId, $fecha);
            }
        }

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $selectedCampaign = $campaignId;
        $selectedDate = $fecha;
        $importInfo = $breakImport ? [
            'fecha' => $breakImport['imported_at'] ?? '-',
            'total_registros' => $breakImport['total_registros'] ?? 0,
            'asesores_matched' => $breakImport['asesores_matched'] ?? 0,
            'asesores_unmatched' => $breakImport['asesores_unmatched'] ?? 0,
        ] : null;

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
     * Now accepts a single date instead of month/year.
     */
    public function import(): void
    {
        AuthService::requirePermission('breaks.import');

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $campaignId = (int)($_POST['campaign_id'] ?? 0);
        $fecha = trim($_POST['fecha'] ?? '');

        // Validate campaign
        if ($campaignId <= 0) {
            $this->setFlash('error', 'Selecciona una campana valida.');
            header('Location: ' . BASE_URL . '/breaks/import');
            exit;
        }

        // Validate date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
            $this->setFlash('error', 'Fecha invalida. Usa el formato YYYY-MM-DD.');
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
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            $this->setFlash('error', 'Formato no permitido. Usa .xlsx o .xls.');
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
            'break_c%d_%s_%s.%s',
            $campaignId,
            str_replace('-', '', $fecha),
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
                $fecha,
                (int)$user['id']
            );

            // Delete uploaded file after processing
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }

            $msg = sprintf(
                'Importacion completada para %s (%s). %d asesores matched, %d sin match, %d omitidos (dia libre).',
                $campaign['nombre'],
                $fecha,
                $result['matched'],
                $result['unmatched'],
                $result['skipped']
            );

            $this->setFlash('success', $msg);
            $_SESSION['break_import_results'] = $result;
        } catch (Throwable $e) {
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
     * JSON API -- cruce data for AJAX table loading.
     */
    public function reportData(): void
    {
        AuthService::requirePermission('breaks.view');

        $pdo = Database::getConnection();

        $campaignId = (int)($_GET['campaign_id'] ?? 0);
        $fecha = $_GET['fecha'] ?? date('Y-m-d');

        if ($campaignId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Parametros invalidos']);
            exit;
        }

        $stmtCamp = $pdo->prepare("SELECT id, nombre FROM campaigns WHERE id = :id AND estado = 'activa'");
        $stmtCamp->execute([':id' => $campaignId]);
        $campaign = $stmtCamp->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Campana no encontrada']);
            exit;
        }

        $data = $this->getCruceData($pdo, $campaignId, $fecha);

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
        $fecha = $_GET['fecha'] ?? '';

        if ($campaignId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $this->setFlash('error', 'Parametros invalidos para exportacion.');
            header('Location: ' . BASE_URL . '/breaks');
            exit;
        }

        $stmtCamp = $pdo->prepare("SELECT id, nombre FROM campaigns WHERE id = :id AND estado = 'activa'");
        $stmtCamp->execute([':id' => $campaignId]);
        $campaign = $stmtCamp->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            $this->setFlash('error', 'Campana no encontrada.');
            header('Location: ' . BASE_URL . '/breaks');
            exit;
        }

        $data = $this->getCruceData($pdo, $campaignId, $fecha);

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Cumplimiento Break');

            // Headers
            $headers = [
                'Nombre', 'Cedula', 'Usuario', 'Horas Trab.',
                'Horario', 'Break Asignado (min)', 'Break Usado (min)',
                'Break Disponible (min)', 'Exceso (min)', 'Estado',
            ];
            foreach ($headers as $col => $header) {
                $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
                $cell->setValue($header);
                $cell->getStyle()->getFont()->setBold(true);
            }

            // Style header
            $lastCol = chr(64 + count($headers)); // A=65
            $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            // Data rows
            foreach ($data as $i => $row) {
                $r = $i + 2;
                $exceso = (float)($row['exceso_min'] ?? 0);

                $sheet->setCellValueByColumnAndRow(1, $r, ($row['apellidos'] ?? '') . ' ' . ($row['nombres'] ?? ''));
                $sheet->setCellValueByColumnAndRow(2, $r, $row['cedula'] ?? '');
                $sheet->setCellValueByColumnAndRow(3, $r, $row['usuario_excel'] ?? '');
                $sheet->setCellValueByColumnAndRow(4, $r, (int)($row['horas_trabajadas'] ?? 0));
                $sheet->setCellValueByColumnAndRow(5, $r, $row['horario_texto'] ?? '');
                $sheet->setCellValueByColumnAndRow(6, $r, round((float)($row['break_asignado_min'] ?? 0), 2));
                $sheet->setCellValueByColumnAndRow(7, $r, round((float)($row['break_usado_min'] ?? 0), 2));
                $sheet->setCellValueByColumnAndRow(8, $r, round((float)($row['break_disponible_min'] ?? 0), 2));
                $sheet->setCellValueByColumnAndRow(9, $r, round($exceso, 2));
                $sheet->setCellValueByColumnAndRow(10, $r, $exceso > 0 ? 'Exceso' : 'OK');
            }

            // Auto-size columns
            foreach (range('A', $lastCol) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $campName = str_replace(' ', '_', $campaign['nombre']);
            $filename = sprintf('Break_Compliance_%s_%s.xlsx', $campName, $fecha);

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
     * Execute the cruce query: break data from break_daily for a specific date.
     *
     * Joins with break_rules to compare assigned break vs rule-based break,
     * and optionally with shift_assignments for TurnoFlow planned hours.
     */
    private function getCruceData(PDO $pdo, int $campaignId, string $fecha): array
    {
        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.nombres,
                a.apellidos,
                a.cedula,
                bd.usuario_excel,
                bd.horas_trabajadas,
                bd.horario_texto,
                bd.break_asignado_min,
                bd.break_usado_min,
                bd.break_disponible_min,
                bd.exceso_min,
                COALESCE(br.break_minutes, 0) as regla_break_min,
                COALESCE(tf.tf_hours, 0) as tf_horas_planificadas
            FROM break_daily bd
            JOIN advisors a ON a.id = bd.advisor_id
            LEFT JOIN break_rules br ON br.horas_trabajo = bd.horas_trabajadas
            LEFT JOIN (
                SELECT advisor_id, COUNT(*) as tf_hours
                FROM shift_assignments
                WHERE campaign_id = :cid AND fecha = :fecha AND tipo <> 'break'
                GROUP BY advisor_id
            ) tf ON tf.advisor_id = a.id
            WHERE bd.campaign_id = :cid2 AND bd.fecha = :fecha2
            ORDER BY a.apellidos, a.nombres
        ");
        $stmt->execute([
            ':cid'    => $campaignId,
            ':fecha'  => $fecha,
            ':cid2'   => $campaignId,
            ':fecha2' => $fecha,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
