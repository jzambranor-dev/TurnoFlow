<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';

class ReportController
{
    private const MONTHLY_TARGETS = [
        28 => 168,
        29 => 168,
        30 => 170,
        31 => 177,
    ];

    public function index(): void
    {
        AuthService::requirePermission('reports.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $isAdmin = in_array($user['rol'] ?? '', ['admin', 'coordinador'], true);

        if ($isAdmin) {
            $stmt = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        } else {
            $stmt = $pdo->prepare("SELECT id, nombre FROM campaigns WHERE supervisor_id = :uid AND estado = 'activa' ORDER BY nombre");
            $stmt->execute([':uid' => $user['id']]);
        }
        $campaigns = $stmt->fetchAll();

        $pageTitle = 'Reportes';
        $currentPage = 'reports';

        include APP_PATH . '/Views/reports/index.php';
    }

    public function hours(int $campaignId): void
    {
        AuthService::requirePermission('reports.view');

        $pdo = Database::getConnection();

        // Get campaign info
        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id");
        $stmt->execute([':id' => $campaignId]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            header('Location: ' . BASE_URL . '/reports');
            exit;
        }

        // Determine period from query params or latest schedule
        $year = (int)($_GET['year'] ?? 0);
        $month = (int)($_GET['month'] ?? 0);

        if ($year === 0 || $month === 0) {
            $stmt = $pdo->prepare("
                SELECT periodo_anio, periodo_mes FROM schedules
                WHERE campaign_id = :cid
                ORDER BY periodo_anio DESC, periodo_mes DESC
                LIMIT 1
            ");
            $stmt->execute([':cid' => $campaignId]);
            $latest = $stmt->fetch();
            if ($latest) {
                $year = (int)$latest['periodo_anio'];
                $month = (int)$latest['periodo_mes'];
            } else {
                $year = (int)date('Y');
                $month = (int)date('n');
            }
        }

        $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $fechaInicio = sprintf('%04d-%02d-01', $year, $month);
        $fechaFin = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // Monthly hour target
        $monthlyTarget = self::MONTHLY_TARGETS[$daysInMonth] ?? 170;

        // Get all advisors for this campaign (own + shared incoming)
        $stmt = $pdo->prepare("
            SELECT a.id, a.nombres, a.apellidos, 'propio' as tipo
            FROM advisors a
            WHERE a.campaign_id = :cid AND a.estado = 'activo'
            UNION ALL
            SELECT a.id, a.nombres, a.apellidos, 'compartido' as tipo
            FROM shared_advisors sa
            JOIN advisors a ON a.id = sa.advisor_id
            WHERE sa.target_campaign_id = :cid2 AND sa.estado = 'activo' AND a.estado = 'activo'
            ORDER BY tipo, apellidos, nombres
        ");
        $stmt->execute([':cid' => $campaignId, ':cid2' => $campaignId]);
        $advisors = $stmt->fetchAll();

        if (empty($advisors)) {
            $reportData = [];
        } else {
            // Get all shift assignments for this campaign in the period
            $advisorIds = array_column($advisors, 'id');
            $placeholders = implode(',', array_fill(0, count($advisorIds), '?'));

            $params = array_merge(
                array_map('intval', $advisorIds),
                [$campaignId, $fechaInicio, $fechaFin]
            );

            $stmt = $pdo->prepare("
                SELECT advisor_id, fecha::text, COUNT(*) as horas
                FROM shift_assignments
                WHERE advisor_id IN ($placeholders)
                  AND campaign_id = ?
                  AND fecha BETWEEN ? AND ?
                  AND tipo <> 'break'
                GROUP BY advisor_id, fecha
            ");
            $stmt->execute($params);
            $assignments = $stmt->fetchAll();

            // Build lookup: [advisor_id][day] = hours
            $hoursMap = [];
            foreach ($assignments as $row) {
                $day = (int)substr($row['fecha'], -2);
                $hoursMap[(int)$row['advisor_id']][$day] = (int)$row['horas'];
            }

            // Build report data
            $reportData = [];
            foreach ($advisors as $adv) {
                $advId = (int)$adv['id'];
                $dailyHours = [];
                $total = 0;
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $h = $hoursMap[$advId][$d] ?? 0;
                    $dailyHours[$d] = $h;
                    $total += $h;
                }

                // For shared advisors, target is proportional (use their shared max)
                $target = $monthlyTarget;
                if ($adv['tipo'] === 'compartido') {
                    $target = null; // No monthly target for shared advisors
                }

                $compliance = ($target && $target > 0) ? round(($total / $target) * 100, 1) : null;

                $reportData[] = [
                    'id' => $advId,
                    'nombre' => $adv['nombres'] . ' ' . $adv['apellidos'],
                    'tipo' => $adv['tipo'],
                    'daily' => $dailyHours,
                    'total' => $total,
                    'target' => $target,
                    'compliance' => $compliance,
                ];
            }
        }

        // Available periods for selector
        $stmt = $pdo->prepare("
            SELECT DISTINCT periodo_anio, periodo_mes
            FROM schedules WHERE campaign_id = :cid
            ORDER BY periodo_anio DESC, periodo_mes DESC
        ");
        $stmt->execute([':cid' => $campaignId]);
        $availablePeriods = $stmt->fetchAll();

        $pageTitle = 'Reporte de Horas - ' . $campaign['nombre'];
        $currentPage = 'reports';

        include APP_PATH . '/Views/reports/hours.php';
    }
}
