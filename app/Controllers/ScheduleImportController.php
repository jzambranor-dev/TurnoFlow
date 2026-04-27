<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use PDO;
use Throwable;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';
require_once APP_PATH . '/Services/ImportService.php';

class ScheduleImportController
{
    public function showImport(): void
    {
        AuthService::requirePermission('schedules.import');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        if (AuthService::canManageAllCampaigns($user)) {
            $stmt = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        } else {
            $stmt = $pdo->prepare("
                SELECT id, nombre
                FROM campaigns
                WHERE supervisor_id = :uid
                  AND estado = 'activa'
                ORDER BY nombre
            ");
            $stmt->execute([':uid' => $user['id']]);
        }

        $campaigns = $stmt->fetchAll();
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $pageTitle = 'Importar Dimensionamiento';
        $currentPage = 'schedules';

        include APP_PATH . '/Views/schedules/import.php';
    }

    public function import(): void
    {
        AuthService::requirePermission('schedules.import');

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $campaignId = (int)($_POST['campaign_id'] ?? 0);
        $periodoMes = (int)($_POST['periodo_mes'] ?? 0);
        $periodoAnio = (int)($_POST['periodo_anio'] ?? 0);

        if ($campaignId <= 0 || $periodoMes < 1 || $periodoMes > 12 || $periodoAnio < 2000) {
            $this->setFlash('error', 'Datos de importación invalidos.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        if (empty($_FILES['excel_file']) || !is_array($_FILES['excel_file'])) {
            $this->setFlash('error', 'No se recibio ningun archivo.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        $file = $_FILES['excel_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->setFlash('error', 'Error al subir el archivo. Verifica e intenta nuevamente.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        $originalName = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            $this->setFlash('error', 'Formato no permitido. Usa .xlsx, .xls o .csv.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        $pdo = Database::getConnection();

        if (AuthService::canManageAllCampaigns($user)) {
            $stmtCampaign = $pdo->prepare("
                SELECT id, nombre
                FROM campaigns
                WHERE id = :id
                  AND estado = 'activa'
            ");
            $stmtCampaign->execute([':id' => $campaignId]);
        } else {
            $stmtCampaign = $pdo->prepare("
                SELECT id, nombre
                FROM campaigns
                WHERE id = :id
                  AND supervisor_id = :uid
                  AND estado = 'activa'
            ");
            $stmtCampaign->execute([
                ':id' => $campaignId,
                ':uid' => $user['id'],
            ]);
        }

        $campaign = $stmtCampaign->fetch();
        if (!$campaign) {
            $this->setFlash('error', 'No tienes permisos sobre esa campaña o no esta activa.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        $uploadPath = rtrim($_ENV['UPLOAD_PATH'] ?? (BASE_PATH . '/uploads'), "/\\");
        if (!is_dir($uploadPath) && !mkdir($uploadPath, 0777, true) && !is_dir($uploadPath)) {
            $this->setFlash('error', 'No se pudo preparar la carpeta de carga.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        $storedName = sprintf(
            'dimensionamiento_c%d_%04d_%02d_%s.%s',
            $campaignId,
            $periodoAnio,
            $periodoMes,
            date('Ymd_His'),
            $extension
        );
        $targetPath = $uploadPath . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
            $this->setFlash('error', 'No se pudo guardar el archivo cargado.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        try {
            $result = \App\Services\ImportService::processExcel(
                $targetPath, $campaignId, $periodoMes, $periodoAnio, (int)$user['id']
            );

            $stats = $result['stats'];
            $msg = sprintf(
                'Importación completada para %s. Se guardaron %d registros (%02d/%04d), el horario mensual fue %s y se generaron %d asignaciónes.',
                $campaign['nombre'],
                $stats['requirements_count'],
                $periodoMes,
                $periodoAnio,
                $stats['schedule_action'],
                $stats['generated_assignments']
            );
            if (!empty($stats['regenerated_campaigns'])) {
                $msg .= ' Se actualizaron horarios de: ' . implode(', ', $stats['regenerated_campaigns']) . '.';
            }
            $this->setFlash('success', $msg);
        } catch (Throwable $e) {
            \App\Services\ImportService::recordFailure(
                $campaignId, $periodoAnio, $periodoMes, $storedName,
                (int)$user['id'], $e->getMessage(), $originalName
            );

            error_log('Importación fallida: ' . $e->getMessage());
            $this->setFlash('error', 'No se pudo procesar el archivo: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function deleteImport(int $importId): void
    {
        AuthService::requirePermission('schedules.generate');

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $pdo = Database::getConnection();

        // Obtener la importación validando permisos
        if (AuthService::canManageAllCampaigns($user)) {
            $stmt = $pdo->prepare("
                SELECT si.*, c.nombre as campaign_nombre
                FROM staffing_imports si
                JOIN campaigns c ON c.id = si.campaign_id
                WHERE si.id = :id
            ");
            $stmt->execute([':id' => $importId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT si.*, c.nombre as campaign_nombre
                FROM staffing_imports si
                JOIN campaigns c ON c.id = si.campaign_id
                WHERE si.id = :id AND c.supervisor_id = :uid
            ");
            $stmt->execute([':id' => $importId, ':uid' => $user['id']]);
        }

        $importRow = $stmt->fetch();
        if (!$importRow) {
            $this->setFlash('error', 'Importación no encontrada o sin permisos.');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        // Verificar que el horario asociado no esté aprobado/enviado
        $periodoAnio = (int)$importRow['periodo_anio'];
        $periodoMes = (int)$importRow['periodo_mes'];
        $campaignId = (int)$importRow['campaign_id'];
        $fechaInicio = sprintf('%04d-%02d-01', $periodoAnio, $periodoMes);

        $scheduleRow = $this->findMonthlySchedule($pdo, $campaignId, $fechaInicio);
        if ($scheduleRow && in_array($scheduleRow['status'], ['aprobado', 'enviado'], true)) {
            $this->setFlash('error', 'No se puede eliminar: el horario esta en estado "' . $scheduleRow['status'] . '".');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Si hay un schedule en borrador/rechazado, eliminar sus asignaciónes
            if ($scheduleRow) {
                $stmtDel = $pdo->prepare("DELETE FROM shift_assignments WHERE schedule_id = :sid");
                $stmtDel->execute([':sid' => $scheduleRow['id']]);

                $stmtDel = $pdo->prepare("DELETE FROM schedules WHERE id = :sid");
                $stmtDel->execute([':sid' => $scheduleRow['id']]);
            }

            // Eliminar import (CASCADE borra staffing_requirements)
            $stmtDel = $pdo->prepare("DELETE FROM staffing_imports WHERE id = :id");
            $stmtDel->execute([':id' => $importId]);

            // Eliminar archivo físico
            $uploadPath = $_ENV['UPLOAD_PATH'] ?? (dirname(__DIR__, 2) . '/uploads');
            $archivo = $importRow['archivo_nombre'] ?? '';
            if ($archivo !== '') {
                $filePath = $uploadPath . '/' . $archivo;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $pdo->commit();

            $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $this->setFlash(
                'success',
                sprintf(
                    'Importación de %s - %s %d eliminada correctamente.',
                    $importRow['campaign_nombre'],
                    $meses[$periodoMes] ?? $periodoMes,
                    $periodoAnio
                )
            );
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->setFlash('error', 'Error al eliminar: ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '/schedules/generate');
        exit;
    }

    private function findMonthlySchedule(PDO $pdo, int $campaignId, string $fechaInicio): ?array
    {
        $stmt = $pdo->prepare("
            SELECT id, status
            FROM schedules
            WHERE campaign_id = :campaign_id
              AND fecha_inicio = :fecha_inicio
              AND tipo = 'mensual'
            LIMIT 1
        ");
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':fecha_inicio' => $fechaInicio,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function setFlash(string $type, string $message): void
    {
        if ($type === 'success') {
            $_SESSION['flash_success'] = $message;
            return;
        }

        $_SESSION['flash_error'] = $message;
    }
}
