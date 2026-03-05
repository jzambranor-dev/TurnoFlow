<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;

class DashboardController
{
    public function index(): void
    {
        $user = $_SESSION['user'];
        $isCoordinador = $user['rol'] === 'coordinador';

        $pdo = Database::getConnection();

        // Estadísticas según rol
        $stats = [];

        if ($isCoordinador) {
            // Coordinador ve todas las campañas
            $stmt = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE estado = 'activa'");
            $stats['campaigns'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM advisors WHERE estado = 'activo'");
            $stats['advisors'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'enviado'");
            $stats['pending_approvals'] = $stmt->fetchColumn();

            // Horarios pendientes de aprobación
            $stmt = $pdo->query("
                SELECT s.*, c.nombre as campaign_name,
                       u.nombre || ' ' || u.apellido as generado_por_nombre
                FROM schedules s
                JOIN campaigns c ON c.id = s.campaign_id
                LEFT JOIN users u ON u.id = s.generado_por
                WHERE s.status = 'enviado'
                ORDER BY s.created_at DESC
                LIMIT 5
            ");
            $pendingSchedules = $stmt->fetchAll();
        } else {
            // Supervisor ve solo sus campañas
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE supervisor_id = :uid AND estado = 'activa'");
            $stmt->execute([':uid' => $user['id']]);
            $stats['campaigns'] = $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM advisors a
                JOIN campaigns c ON c.id = a.campaign_id
                WHERE c.supervisor_id = :uid AND a.estado = 'activo'
            ");
            $stmt->execute([':uid' => $user['id']]);
            $stats['advisors'] = $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM schedules s
                JOIN campaigns c ON c.id = s.campaign_id
                WHERE c.supervisor_id = :uid AND s.status = 'borrador'
            ");
            $stmt->execute([':uid' => $user['id']]);
            $stats['draft_schedules'] = $stmt->fetchColumn();

            $pendingSchedules = [];
        }

        $pageTitle = 'Dashboard';
        $currentPage = 'dashboard';

        include APP_PATH . '/Views/dashboard/index.php';
    }
}
