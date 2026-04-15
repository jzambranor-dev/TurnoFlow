<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\AuditService;
use Database;

require_once APP_PATH . '/Services/AuthService.php';
require_once APP_PATH . '/Services/AuditService.php';

class AuditController
{
    public function index(): void
    {
        $user = $_SESSION['user'];
        $userRole = $user['rol'] ?? '';

        // Solo admin y gerente
        if (!in_array($userRole, ['admin', 'gerente'], true)) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $pdo = Database::getConnection();

        // Filtros
        $filterUserId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
        $filterEntidad = isset($_GET['entidad']) && $_GET['entidad'] !== '' ? (string)$_GET['entidad'] : null;
        $filterFechaDesde = isset($_GET['fecha_desde']) && $_GET['fecha_desde'] !== '' ? (string)$_GET['fecha_desde'] : null;
        $filterFechaHasta = isset($_GET['fecha_hasta']) && $_GET['fecha_hasta'] !== '' ? (string)$_GET['fecha_hasta'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));

        $result = AuditService::getPaginated(
            $page,
            30,
            $filterUserId,
            $filterEntidad,
            $filterFechaDesde,
            $filterFechaHasta
        );

        // Datos para filtros
        $users = $pdo->query("SELECT id, nombre, apellido FROM users WHERE activo = true ORDER BY apellido, nombre")->fetchAll();
        $entities = AuditService::getDistinctEntities();

        $auditItems = $result['items'];
        $totalPages = $result['totalPages'];
        $total = $result['total'];

        $pageTitle = 'Auditoria';
        $currentPage = 'audit';

        include APP_PATH . '/Views/audit/index.php';
    }

    public function export(): void
    {
        $user = $_SESSION['user'];
        if (!in_array($user['rol'] ?? '', ['admin', 'gerente'], true)) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $filterUserId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
        $filterEntidad = isset($_GET['entidad']) && $_GET['entidad'] !== '' ? (string)$_GET['entidad'] : null;
        $filterFechaDesde = isset($_GET['fecha_desde']) && $_GET['fecha_desde'] !== '' ? (string)$_GET['fecha_desde'] : null;
        $filterFechaHasta = isset($_GET['fecha_hasta']) && $_GET['fecha_hasta'] !== '' ? (string)$_GET['fecha_hasta'] : null;

        $rows = AuditService::exportCsv($filterUserId, $filterEntidad, $filterFechaDesde, $filterFechaHasta);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_His') . '.csv"');

        $out = fopen('php://output', 'w');
        // BOM para Excel
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['ID', 'Fecha', 'Usuario', 'Accion', 'Entidad', 'ID Entidad', 'IP', 'Datos Antes', 'Datos Despues']);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'],
                $row['created_at'],
                trim(($row['user_nombre'] ?? '') . ' ' . ($row['user_apellido'] ?? '')),
                $row['accion'],
                $row['entidad'],
                $row['entidad_id'],
                $row['ip'],
                $row['datos_antes'] ?? '',
                $row['datos_despues'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    /**
     * API JSON: historial de auditoria para un schedule.
     */
    public function scheduleHistory(int $scheduleId): void
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'No autenticado']);
            exit;
        }

        $items = AuditService::getByEntity('schedules', $scheduleId, 50);

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id'         => (int)$item['id'],
                'fecha'      => $item['created_at'],
                'usuario'    => trim(($item['user_nombre'] ?? '') . ' ' . ($item['user_apellido'] ?? '')),
                'accion'     => $item['accion'],
                'ip'         => $item['ip'],
                'antes'      => $item['datos_antes'] ? json_decode($item['datos_antes'], true) : null,
                'despues'    => $item['datos_despues'] ? json_decode($item['datos_despues'], true) : null,
            ];
        }

        echo json_encode(['success' => true, 'items' => $result]);
        exit;
    }
}
