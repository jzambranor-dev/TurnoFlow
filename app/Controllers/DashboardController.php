<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';

class DashboardController
{
    /**
     * API JSON: Cobertura promedio vs requerimiento ultimos N dias
     */
    public function coverageTrend(): void
    {
        header('Content-Type: application/json');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();
        $days = max(1, min(90, (int)($_GET['days'] ?? 30)));
        $campaignId = !empty($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : null;

        $params = [':days' => $days];
        $campaignFilter = '';
        if ($campaignId) {
            $campaignFilter = 'AND s.campaign_id = :cid';
            $params[':cid'] = $campaignId;
        } elseif ($user['rol'] === 'supervisor') {
            $campaignFilter = 'AND c.supervisor_id = :uid';
            $params[':uid'] = $user['id'];
        }

        $stmt = $pdo->prepare("
            SELECT sa.fecha,
                   COUNT(DISTINCT sa.advisor_id) as cobertura_promedio
            FROM shift_assignments sa
            JOIN schedules s ON s.id = sa.schedule_id
            JOIN campaigns c ON c.id = s.campaign_id
            WHERE sa.fecha >= CURRENT_DATE - CAST(:days AS INTEGER) * INTERVAL '1 day'
              AND sa.tipo != 'break'
              {$campaignFilter}
            GROUP BY sa.fecha
            ORDER BY sa.fecha
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $rows]);
    }

    /**
     * API JSON: Horarios por estado ultimos N meses
     */
    public function scheduleStats(): void
    {
        header('Content-Type: application/json');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();
        $months = max(1, min(12, (int)($_GET['months'] ?? 3)));

        $roleFilter = '';
        $params = [':months' => $months];
        if ($user['rol'] === 'supervisor') {
            $roleFilter = 'AND c.supervisor_id = :uid';
            $params[':uid'] = $user['id'];
        }

        $stmt = $pdo->prepare("
            SELECT s.status,
                   TO_CHAR(s.created_at, 'YYYY-MM') as mes,
                   COUNT(*) as total
            FROM schedules s
            JOIN campaigns c ON c.id = s.campaign_id
            WHERE s.created_at >= NOW() - CAST(:months AS INTEGER) * INTERVAL '1 month'
              {$roleFilter}
            GROUP BY s.status, TO_CHAR(s.created_at, 'YYYY-MM')
            ORDER BY mes
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $rows]);
    }

    /**
     * API JSON: Horas asignadas por asesor vs meta del mes
     */
    public function advisorHours(): void
    {
        header('Content-Type: application/json');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();
        $campaignId = !empty($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : null;
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));

        $params = [':year' => $year, ':month' => $month];
        $campaignFilter = '';
        if ($campaignId) {
            $campaignFilter = 'AND s.campaign_id = :cid';
            $params[':cid'] = $campaignId;
        } elseif ($user['rol'] === 'supervisor') {
            $campaignFilter = 'AND c.supervisor_id = :uid';
            $params[':uid'] = $user['id'];
        }

        $stmt = $pdo->prepare("
            SELECT a.id, a.nombres || ' ' || a.apellidos as nombre,
                   COUNT(sa.id) as horas_asignadas
            FROM advisors a
            JOIN shift_assignments sa ON sa.advisor_id = a.id
            JOIN schedules s ON s.id = sa.schedule_id
            JOIN campaigns c ON c.id = s.campaign_id
            WHERE EXTRACT(YEAR FROM sa.fecha) = :year
              AND EXTRACT(MONTH FROM sa.fecha) = :month
              AND sa.tipo != 'break'
              AND a.estado = 'activo'
              {$campaignFilter}
            GROUP BY a.id, a.nombres, a.apellidos
            ORDER BY horas_asignadas DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Obtener meta mensual
        $stmtMeta = $pdo->prepare("SELECT horas_requeridas FROM monthly_hours_config WHERE anio = :y AND mes = :m LIMIT 1");
        $stmtMeta->execute([':y' => $year, ':m' => $month]);
        $meta = (int)($stmtMeta->fetchColumn() ?: 177);

        echo json_encode(['success' => true, 'data' => $rows, 'meta' => $meta]);
    }

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

            // Tasa de asistencia del mes actual (presentes / total registros)
            $stmt = $pdo->prepare("
                SELECT
                    CASE WHEN COUNT(*) > 0
                         THEN ROUND(COUNT(*) FILTER (WHERE status = 'presente')::numeric / COUNT(*) * 100, 1)
                         ELSE 0
                    END as rate
                FROM attendance
                WHERE EXTRACT(MONTH FROM fecha) = :mes
                  AND EXTRACT(YEAR FROM fecha) = :anio
            ");
            $stmt->execute([':mes' => (int)date('n'), ':anio' => (int)date('Y')]);
            $attendanceRate = (float)$stmt->fetchColumn();

            // Cobertura promedio del mes actual (desde vista v_coverage_vs_required)
            $stmt = $pdo->prepare("
                SELECT
                    CASE WHEN SUM(requeridos) > 0
                         THEN ROUND(SUM(asignados)::numeric / SUM(requeridos) * 100, 1)
                         ELSE 0
                    END as rate
                FROM v_coverage_vs_required
                WHERE EXTRACT(MONTH FROM fecha) = :mes
                  AND EXTRACT(YEAR FROM fecha) = :anio
            ");
            $stmt->execute([':mes' => (int)date('n'), ':anio' => (int)date('Y')]);
            $coverageRate = (float)$stmt->fetchColumn();
        }

        // Stats para supervisor — una sola query para todos los contadores
        if ($rol === 'supervisor') {
            $stmt = $pdo->prepare("
                SELECT
                    (SELECT COUNT(*) FROM campaigns WHERE supervisor_id = :uid1 AND estado = 'activa') as campaigns,
                    (SELECT COUNT(*) FROM advisors a JOIN campaigns c ON c.id = a.campaign_id
                     WHERE c.supervisor_id = :uid2 AND a.estado = 'activo') as advisors,
                    (SELECT COUNT(*) FROM schedules s JOIN campaigns c ON c.id = s.campaign_id
                     WHERE c.supervisor_id = :uid3 AND s.status = 'borrador') as draft_schedules,
                    (SELECT COUNT(*) FROM schedules s JOIN campaigns c ON c.id = s.campaign_id
                     WHERE c.supervisor_id = :uid4 AND s.status = 'aprobado') as approved_schedules
            ");
            $stmt->execute([
                ':uid1' => $user['id'], ':uid2' => $user['id'],
                ':uid3' => $user['id'], ':uid4' => $user['id'],
            ]);
            $counts = $stmt->fetch();
            $stats = array_merge($stats, $counts);

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

                // Turnos próximos (count)
                $stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT sa.fecha)
                    FROM shift_assignments sa
                    JOIN schedules s ON s.id = sa.schedule_id AND s.status = 'aprobado'
                    WHERE sa.advisor_id = :aid AND sa.fecha >= CURRENT_DATE
                ");
                $stmt->execute([':aid' => $advisorId]);
                $stats['upcoming_shifts'] = $stmt->fetchColumn();

                // Proximos turnos detallados (3 dias)
                $stmt = $pdo->prepare("
                    SELECT sa.fecha, sa.hora, sa.tipo
                    FROM shift_assignments sa
                    JOIN schedules s ON s.id = sa.schedule_id AND s.status = 'aprobado'
                    WHERE sa.advisor_id = :aid
                      AND sa.fecha >= CURRENT_DATE
                      AND sa.fecha <= CURRENT_DATE + INTERVAL '3 days'
                      AND sa.tipo != 'break'
                    ORDER BY sa.fecha, sa.hora
                ");
                $stmt->execute([':aid' => $advisorId]);
                $upcomingShifts = $stmt->fetchAll();

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
                $upcomingShifts = [];
            }
        }

        $pageTitle = 'Dashboard';
        $currentPage = 'dashboard';

        include APP_PATH . '/Views/dashboard/index.php';
    }
}
