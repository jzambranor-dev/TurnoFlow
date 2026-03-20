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
        // No requiere permiso especial — todo usuario autenticado accede al dashboard

        $user = $_SESSION['user'];
        $rol = $user['rol'];
        $pdo = Database::getConnection();

        $stats = [];
        $pendingSchedules = [];
        $recentActivities = [];

        // Stats comunes para admin, gerente, coordinador (jerarquia alta)
        if (in_array($rol, ['admin', 'gerente', 'coordinador'])) {
            // Una sola query para todos los contadores principales
            $stmt = $pdo->query("
                SELECT
                    (SELECT COUNT(*) FROM campaigns WHERE estado = 'activa') as campaigns,
                    (SELECT COUNT(*) FROM advisors WHERE estado = 'activo') as advisors,
                    (SELECT COUNT(*) FROM users WHERE activo = true) as users,
                    (SELECT COUNT(*) FROM schedules WHERE status = 'enviado') as pending_approvals,
                    (SELECT COUNT(*) FROM schedules WHERE status = 'aprobado') as approved_schedules,
                    (SELECT COUNT(*) FROM schedules WHERE status = 'rechazado') as rejected_schedules,
                    (SELECT COUNT(*) FROM schedules) as total_schedules
            ");
            $counts = $stmt->fetch();
            $stats = array_merge($stats, $counts);

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

            // Campañas recientes con conteo via LEFT JOIN + GROUP BY
            $stmt = $pdo->query("
                SELECT c.*, u.nombre || ' ' || u.apellido as supervisor_nombre,
                       COUNT(a.id) as total_asesores
                FROM campaigns c
                LEFT JOIN users u ON u.id = c.supervisor_id
                LEFT JOIN advisors a ON a.campaign_id = c.id AND a.estado = 'activo'
                WHERE c.estado = 'activa'
                GROUP BY c.id, u.nombre, u.apellido
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

            // Mis campañas con conteo via LEFT JOIN + GROUP BY
            $stmt = $pdo->prepare("
                SELECT c.*, COUNT(a.id) as total_asesores
                FROM campaigns c
                LEFT JOIN advisors a ON a.campaign_id = c.id AND a.estado = 'activo'
                WHERE c.supervisor_id = :uid AND c.estado = 'activa'
                GROUP BY c.id
                ORDER BY c.nombre
            ");
            $stmt->execute([':uid' => $user['id']]);
            $recentCampaigns = $stmt->fetchAll();
        }

        // Stats para asesor
        if ($rol === 'asesor') {
            // Buscar advisor vinculado al usuario (por nombre)
            $advisorId = null;
            $firstName = trim((string)($user['nombre'] ?? ''));
            $lastName = trim((string)($user['apellido'] ?? ''));

            if ($firstName !== '' && $lastName !== '') {
                $stmt = $pdo->prepare("
                    SELECT id FROM advisors
                    WHERE LOWER(nombres || ' ' || apellidos) = LOWER(:full_name)
                       OR LOWER(apellidos || ' ' || nombres) = LOWER(:full_name)
                    LIMIT 1
                ");
                $stmt->execute([':full_name' => $firstName . ' ' . $lastName]);
                $advisorId = $stmt->fetchColumn() ?: null;

                if (!$advisorId) {
                    $stmt = $pdo->prepare("
                        SELECT id FROM advisors
                        WHERE LOWER(nombres) LIKE LOWER(:first) AND LOWER(apellidos) LIKE LOWER(:last)
                        LIMIT 1
                    ");
                    $stmt->execute([':first' => '%' . $firstName . '%', ':last' => '%' . $lastName . '%']);
                    $advisorId = $stmt->fetchColumn() ?: null;
                }
            }

            if ($advisorId) {
                $mesActualNum = (int)date('n');
                $anioActual = (int)date('Y');

                // Turnos próximos
                $stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT sa.fecha)
                    FROM shift_assignments sa
                    JOIN schedules s ON s.id = sa.schedule_id AND s.status = 'aprobado'
                    WHERE sa.advisor_id = :aid AND sa.fecha >= CURRENT_DATE
                ");
                $stmt->execute([':aid' => $advisorId]);
                $stats['upcoming_shifts'] = $stmt->fetchColumn();

                // Días trabajados este mes (con asistencia confirmada: presente o tardanza)
                $stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT att.fecha)
                    FROM attendance att
                    WHERE att.advisor_id = :aid
                      AND EXTRACT(MONTH FROM att.fecha) = :mes
                      AND EXTRACT(YEAR FROM att.fecha) = :anio
                      AND att.status IN ('presente', 'tardanza', 'salida_anticipada')
                ");
                $stmt->execute([':aid' => $advisorId, ':mes' => $mesActualNum, ':anio' => $anioActual]);
                $stats['days_worked'] = (int)$stmt->fetchColumn();

                // Horas trabajadas este mes (sumar horas de shift_assignments en días con asistencia confirmada)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM shift_assignments sa
                    JOIN schedules s ON s.id = sa.schedule_id AND s.status = 'aprobado'
                    WHERE sa.advisor_id = :aid
                      AND EXTRACT(MONTH FROM sa.fecha) = :mes
                      AND EXTRACT(YEAR FROM sa.fecha) = :anio
                      AND sa.tipo != 'break'
                      AND EXISTS (
                          SELECT 1 FROM attendance att
                          WHERE att.advisor_id = sa.advisor_id
                            AND att.fecha = sa.fecha
                            AND att.status IN ('presente', 'tardanza', 'salida_anticipada')
                      )
                ");
                $stmt->execute([':aid' => $advisorId, ':mes' => $mesActualNum, ':anio' => $anioActual]);
                $stats['hours_this_month'] = (int)$stmt->fetchColumn();
            } else {
                $stats['upcoming_shifts'] = 0;
                $stats['hours_this_month'] = 0;
                $stats['days_worked'] = 0;
            }
        }

        $pageTitle = 'Dashboard';
        $currentPage = 'dashboard';

        include APP_PATH . '/Views/dashboard/index.php';
    }
}
