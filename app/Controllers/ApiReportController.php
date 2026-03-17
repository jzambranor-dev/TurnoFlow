<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use App\Services\ApiAuthService;

require_once APP_PATH . '/Services/ApiAuthService.php';

class ApiReportController
{
    private const DEFAULT_TARGETS = [
        28 => 168, 29 => 168, 30 => 170, 31 => 177,
    ];

    /**
     * GET /api/reports/campaigns — List available campaigns
     */
    public function campaigns(): void
    {
        $token = $this->requireAuth('reports.view');
        $pdo = Database::getConnection();

        $isAdmin = in_array($token['user_rol'], ['admin', 'gerente', 'coordinador'], true);

        if ($isAdmin) {
            $stmt = $pdo->query("SELECT id, nombre, estado FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        } else {
            $stmt = $pdo->prepare("SELECT id, nombre, estado FROM campaigns WHERE supervisor_id = :uid AND estado = 'activa' ORDER BY nombre");
            $stmt->execute([':uid' => $token['user_id']]);
        }

        ApiAuthService::jsonSuccess(['campaigns' => $stmt->fetchAll()]);
    }

    /**
     * GET /api/reports/hours/{campaign_id}?year=2026&month=3 — Hours report by campaign
     */
    public function hours(int $campaignId): void
    {
        $token = $this->requireAuth('reports.view');
        $pdo = Database::getConnection();

        // Validate campaign
        $stmt = $pdo->prepare("SELECT id, nombre FROM campaigns WHERE id = :id");
        $stmt->execute([':id' => $campaignId]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            ApiAuthService::jsonError(404, 'Campana no encontrada');
        }

        // Determine period
        $year = (int)($_GET['year'] ?? 0);
        $month = (int)($_GET['month'] ?? 0);

        if ($year === 0 || $month === 0) {
            $stmt = $pdo->prepare("
                SELECT periodo_anio, periodo_mes FROM schedules
                WHERE campaign_id = :cid
                ORDER BY periodo_anio DESC, periodo_mes DESC LIMIT 1
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

        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2035) {
            ApiAuthService::jsonError(400, 'Periodo invalido');
        }

        $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $fechaInicio = sprintf('%04d-%02d-01', $year, $month);
        $fechaFin = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        $monthlyTarget = $this->getMonthlyTarget($pdo, $year, $month, $daysInMonth);

        // Get advisors (own + shared)
        $stmt = $pdo->prepare("
            SELECT a.id, a.nombres, a.apellidos, a.cedula, a.tipo_contrato, 'propio' as tipo
            FROM advisors a
            WHERE a.campaign_id = :cid AND a.estado = 'activo'
            UNION ALL
            SELECT a.id, a.nombres, a.apellidos, a.cedula, a.tipo_contrato, 'compartido' as tipo
            FROM shared_advisors sa
            JOIN advisors a ON a.id = sa.advisor_id
            WHERE sa.target_campaign_id = :cid2 AND sa.estado = 'activo' AND a.estado = 'activo'
            ORDER BY tipo, apellidos, nombres
        ");
        $stmt->execute([':cid' => $campaignId, ':cid2' => $campaignId]);
        $advisors = $stmt->fetchAll();

        $reportData = $this->buildReportData($pdo, $advisors, $campaignId, $fechaInicio, $fechaFin, $daysInMonth, $monthlyTarget);

        // Summary
        $totalHoras = array_sum(array_column($reportData, 'total'));
        $totalTarget = array_sum(array_filter(array_column($reportData, 'target')));
        $avgCompliance = $totalTarget > 0 ? round(($totalHoras / $totalTarget) * 100, 1) : null;

        ApiAuthService::jsonSuccess([
            'campaign' => $campaign,
            'period' => ['year' => $year, 'month' => $month, 'days_in_month' => $daysInMonth],
            'monthly_target' => $monthlyTarget,
            'summary' => [
                'total_advisors' => count($reportData),
                'total_hours' => $totalHoras,
                'total_target' => $totalTarget,
                'avg_compliance' => $avgCompliance,
            ],
            'advisors' => $reportData,
        ]);
    }

    /**
     * GET /api/reports/unified?year=2026&month=3 — All campaigns unified (admin only)
     */
    public function unified(): void
    {
        $token = $this->requireAuth('reports.view');

        if (!in_array($token['user_rol'], ['admin', 'gerente', 'coordinador'], true)) {
            ApiAuthService::jsonError(403, 'Solo admin, gerente o coordinador pueden acceder al reporte unificado');
        }

        $pdo = Database::getConnection();

        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));

        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2035) {
            ApiAuthService::jsonError(400, 'Periodo invalido');
        }

        $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $fechaInicio = sprintf('%04d-%02d-01', $year, $month);
        $fechaFin = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        $monthlyTarget = $this->getMonthlyTarget($pdo, $year, $month, $daysInMonth);

        // Get all active campaigns
        $stmt = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        $campaigns = $stmt->fetchAll();

        $result = [];
        foreach ($campaigns as $camp) {
            $cid = (int)$camp['id'];

            $stmt = $pdo->prepare("
                SELECT a.id, a.nombres, a.apellidos, a.cedula, a.tipo_contrato, 'propio' as tipo
                FROM advisors a
                WHERE a.campaign_id = :cid AND a.estado = 'activo'
                UNION ALL
                SELECT a.id, a.nombres, a.apellidos, a.cedula, a.tipo_contrato, 'compartido' as tipo
                FROM shared_advisors sa
                JOIN advisors a ON a.id = sa.advisor_id
                WHERE sa.target_campaign_id = :cid2 AND sa.estado = 'activo' AND a.estado = 'activo'
                ORDER BY tipo, apellidos, nombres
            ");
            $stmt->execute([':cid' => $cid, ':cid2' => $cid]);
            $advisors = $stmt->fetchAll();

            $reportData = $this->buildReportData($pdo, $advisors, $cid, $fechaInicio, $fechaFin, $daysInMonth, $monthlyTarget);

            $totalHoras = array_sum(array_column($reportData, 'total'));
            $totalTarget = array_sum(array_filter(array_column($reportData, 'target')));

            $result[] = [
                'campaign' => $camp,
                'summary' => [
                    'total_advisors' => count($reportData),
                    'total_hours' => $totalHoras,
                    'total_target' => $totalTarget,
                    'avg_compliance' => $totalTarget > 0 ? round(($totalHoras / $totalTarget) * 100, 1) : null,
                ],
                'advisors' => $reportData,
            ];
        }

        ApiAuthService::jsonSuccess([
            'period' => ['year' => $year, 'month' => $month, 'days_in_month' => $daysInMonth],
            'monthly_target' => $monthlyTarget,
            'campaigns' => $result,
        ]);
    }

    /**
     * GET /api/reports/attendance/{campaign_id}?year=2026&month=3 — Attendance report
     */
    public function attendance(int $campaignId): void
    {
        $token = $this->requireAuth('reports.view');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id, nombre FROM campaigns WHERE id = :id");
        $stmt->execute([':id' => $campaignId]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            ApiAuthService::jsonError(404, 'Campana no encontrada');
        }

        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));

        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2035) {
            ApiAuthService::jsonError(400, 'Periodo invalido');
        }

        $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $fechaInicio = sprintf('%04d-%02d-01', $year, $month);
        $fechaFin = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // Get advisors for this campaign
        $stmt = $pdo->prepare("
            SELECT a.id, a.nombres, a.apellidos, a.cedula
            FROM advisors a
            WHERE a.campaign_id = :cid AND a.estado = 'activo'
            ORDER BY a.apellidos, a.nombres
        ");
        $stmt->execute([':cid' => $campaignId]);
        $advisors = $stmt->fetchAll();

        $advisorIds = array_column($advisors, 'id');
        $attendanceData = [];

        if (!empty($advisorIds)) {
            $placeholders = implode(',', array_fill(0, count($advisorIds), '?'));
            $params = array_merge($advisorIds, [$fechaInicio, $fechaFin]);

            $stmt = $pdo->prepare("
                SELECT advisor_id, fecha::text, status, notas
                FROM attendance
                WHERE advisor_id IN ($placeholders)
                  AND fecha BETWEEN ? AND ?
                ORDER BY fecha
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $attendanceData[(int)$row['advisor_id']][] = [
                    'fecha' => $row['fecha'],
                    'status' => $row['status'],
                    'notas' => $row['notas'],
                ];
            }
        }

        $result = [];
        foreach ($advisors as $adv) {
            $result[] = [
                'id' => (int)$adv['id'],
                'nombre' => $adv['nombres'] . ' ' . $adv['apellidos'],
                'cedula' => $adv['cedula'],
                'attendance' => $attendanceData[(int)$adv['id']] ?? [],
            ];
        }

        ApiAuthService::jsonSuccess([
            'campaign' => $campaign,
            'period' => ['year' => $year, 'month' => $month],
            'advisors' => $result,
        ]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function requireAuth(string $permission): array
    {
        $token = ApiAuthService::authenticate();
        if (!$token) {
            ApiAuthService::jsonError(401, 'Token de API invalido o ausente. Use header: Authorization: Bearer <token>');
        }

        if (!ApiAuthService::hasPermission($token, $permission)) {
            ApiAuthService::jsonError(403, "Token sin permiso: $permission");
        }

        if (!ApiAuthService::checkRateLimit((int)$token['id'], $_SERVER['REQUEST_URI'])) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: 60');
            echo json_encode(['success' => false, 'error' => 'Limite de solicitudes excedido. Intente en 60 segundos.']);
            exit;
        }

        return $token;
    }

    private function getMonthlyTarget(\PDO $pdo, int $year, int $month, int $daysInMonth): int
    {
        $stmt = $pdo->prepare("SELECT horas_requeridas FROM monthly_hours_config WHERE anio = :y AND mes = :m");
        $stmt->execute([':y' => $year, ':m' => $month]);
        $row = $stmt->fetch();
        if ($row) {
            return (int)$row['horas_requeridas'];
        }
        return self::DEFAULT_TARGETS[$daysInMonth] ?? 170;
    }

    private function buildReportData(\PDO $pdo, array $advisors, int $campaignId, string $fechaInicio, string $fechaFin, int $daysInMonth, int $monthlyTarget): array
    {
        if (empty($advisors)) {
            return [];
        }

        $ownAdvisorIds = [];
        $sharedAdvisorIds = [];
        foreach ($advisors as $adv) {
            if ($adv['tipo'] === 'propio') {
                $ownAdvisorIds[] = (int)$adv['id'];
            } else {
                $sharedAdvisorIds[] = (int)$adv['id'];
            }
        }

        $hoursMap = [];
        $lentHoursMap = [];

        // Own advisors: all hours across campaigns
        if (!empty($ownAdvisorIds)) {
            $placeholders = implode(',', array_fill(0, count($ownAdvisorIds), '?'));
            $params = array_merge($ownAdvisorIds, [$fechaInicio, $fechaFin]);

            $stmt = $pdo->prepare("
                SELECT advisor_id, campaign_id, fecha::text, COUNT(*) as horas
                FROM shift_assignments
                WHERE advisor_id IN ($placeholders) AND fecha BETWEEN ? AND ? AND tipo <> 'break'
                GROUP BY advisor_id, campaign_id, fecha
            ");
            $stmt->execute($params);

            foreach ($stmt->fetchAll() as $row) {
                $advId = (int)$row['advisor_id'];
                $day = (int)substr($row['fecha'], -2);
                $h = (int)$row['horas'];
                $cid = (int)$row['campaign_id'];

                $hoursMap[$advId][$day] = ($hoursMap[$advId][$day] ?? 0) + $h;
                if ($cid !== $campaignId) {
                    $lentHoursMap[$advId][$day] = ($lentHoursMap[$advId][$day] ?? 0) + $h;
                }
            }
        }

        // Shared advisors: hours in THIS campaign only
        if (!empty($sharedAdvisorIds)) {
            $placeholders = implode(',', array_fill(0, count($sharedAdvisorIds), '?'));
            $params = array_merge($sharedAdvisorIds, [$campaignId, $fechaInicio, $fechaFin]);

            $stmt = $pdo->prepare("
                SELECT advisor_id, fecha::text, COUNT(*) as horas
                FROM shift_assignments
                WHERE advisor_id IN ($placeholders) AND campaign_id = ? AND fecha BETWEEN ? AND ? AND tipo <> 'break'
                GROUP BY advisor_id, fecha
            ");
            $stmt->execute($params);

            foreach ($stmt->fetchAll() as $row) {
                $hoursMap[(int)$row['advisor_id']][(int)substr($row['fecha'], -2)] = (int)$row['horas'];
            }
        }

        // Build response
        $reportData = [];
        foreach ($advisors as $adv) {
            $advId = (int)$adv['id'];
            $daily = [];
            $total = 0;
            $totalLent = 0;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $h = $hoursMap[$advId][$d] ?? 0;
                $l = $lentHoursMap[$advId][$d] ?? 0;
                $daily[$d] = ['hours' => $h, 'lent' => $l];
                $total += $h;
                $totalLent += $l;
            }

            $target = $adv['tipo'] === 'compartido' ? null : $monthlyTarget;
            $compliance = ($target && $target > 0) ? round(($total / $target) * 100, 1) : null;

            $reportData[] = [
                'id' => $advId,
                'nombre' => $adv['nombres'] . ' ' . $adv['apellidos'],
                'cedula' => $adv['cedula'] ?? null,
                'tipo_contrato' => $adv['tipo_contrato'] ?? null,
                'tipo' => $adv['tipo'],
                'daily' => $daily,
                'total_hours' => $total,
                'total_lent' => $totalLent,
                'target' => $target,
                'compliance_pct' => $compliance,
            ];
        }

        return $reportData;
    }
}
