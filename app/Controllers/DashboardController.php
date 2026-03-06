<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';

class DashboardController
{
    public function index(): void
    {
        AuthService::requirePermission('dashboard.view');

        $user = $_SESSION['user'];
        $rol = $user['rol'];
        $pdo = Database::getConnection();

        $stats = [];
        $pendingSchedules = [];
        $recentActivities = [];

        // Stats comunes para admin, gerente, coordinador
        if (in_array($rol, ['admin', 'gerente', 'coordinador'])) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE estado = 'activa'");
            $stats['campaigns'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM advisors WHERE estado = 'activo'");
            $stats['advisors'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE activo = true");
            $stats['users'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'enviado'");
            $stats['pending_approvals'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'aprobado'");
            $stats['approved_schedules'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'rechazado'");
            $stats['rejected_schedules'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM schedules");
            $stats['total_schedules'] = $stmt->fetchColumn();

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

            // Campañas recientes
            $stmt = $pdo->query("
                SELECT c.*, u.nombre || ' ' || u.apellido as supervisor_nombre,
                       (SELECT COUNT(*) FROM advisors WHERE campaign_id = c.id AND estado = 'activo') as total_asesores
                FROM campaigns c
                LEFT JOIN users u ON u.id = c.supervisor_id
                WHERE c.estado = 'activa'
                ORDER BY c.created_at DESC
                LIMIT 5
            ");
            $recentCampaigns = $stmt->fetchAll();
        }

        // Stats para supervisor
        if ($rol === 'supervisor') {
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

            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM schedules s
                JOIN campaigns c ON c.id = s.campaign_id
                WHERE c.supervisor_id = :uid AND s.status = 'aprobado'
            ");
            $stmt->execute([':uid' => $user['id']]);
            $stats['approved_schedules'] = $stmt->fetchColumn();

            // Mis campañas
            $stmt = $pdo->prepare("
                SELECT c.*,
                       (SELECT COUNT(*) FROM advisors WHERE campaign_id = c.id AND estado = 'activo') as total_asesores
                FROM campaigns c
                WHERE c.supervisor_id = :uid AND c.estado = 'activa'
                ORDER BY c.nombre
            ");
            $stmt->execute([':uid' => $user['id']]);
            $recentCampaigns = $stmt->fetchAll();
        }

        // Stats para asesor
        if ($rol === 'asesor') {
            // Buscar si el usuario tiene un advisor asociado (por email o crear relación)
            $stmt = $pdo->query("SELECT COUNT(*) FROM shift_assignments WHERE fecha >= CURRENT_DATE");
            $stats['upcoming_shifts'] = $stmt->fetchColumn();

            $stats['hours_this_month'] = 0;
            $stats['days_worked'] = 0;
        }

        $pageTitle = 'Dashboard';
        $currentPage = 'dashboard';

        include APP_PATH . '/Views/dashboard/index.php';
    }
}
