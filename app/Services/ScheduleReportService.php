<?php

declare(strict_types=1);

namespace App\Services;

use Database;
use PDO;

class ScheduleReportService
{
    /** Default targets by days in month */
    private const DEFAULT_TARGETS = [
        28 => 168, 29 => 168, 30 => 170, 31 => 177,
    ];

    /**
     * Get monthly hour target from DB or fall back to defaults.
     */
    public static function getMonthlyTarget(PDO $pdo, int $year, int $month, int $daysInMonth): int
    {
        $stmt = $pdo->prepare("SELECT horas_requeridas FROM monthly_hours_config WHERE anio = :y AND mes = :m");
        $stmt->execute([':y' => $year, ':m' => $month]);
        $row = $stmt->fetch();
        if ($row) {
            return (int)$row['horas_requeridas'];
        }
        return self::DEFAULT_TARGETS[$daysInMonth] ?? 170;
    }

    /**
     * Get hours report data for a campaign in a given period.
     *
     * @return array{reportData: array, availablePeriods: array, campaign: array, year: int, month: int, monthlyTarget: int, daysInMonth: int}
     */
    public static function getHoursReport(int $campaignId, int $year, int $month): array
    {
        $pdo = Database::getConnection();

        // Get campaign info
        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id");
        $stmt->execute([':id' => $campaignId]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            return ['campaign' => null];
        }

        // Determine period from params or latest schedule
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
        $monthlyTarget = self::getMonthlyTarget($pdo, $year, $month, $daysInMonth);

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

        $reportData = [];

        if (!empty($advisors)) {
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
            $ownCampHoursMap = [];
            $lentHoursMap = [];

            // Own advisors: count ALL their hours across all campaigns
            if (!empty($ownAdvisorIds)) {
                $placeholders = implode(',', array_fill(0, count($ownAdvisorIds), '?'));
                $params = array_merge($ownAdvisorIds, [$fechaInicio, $fechaFin]);

                $stmt = $pdo->prepare("
                    SELECT advisor_id, campaign_id, fecha::text, COUNT(*) as horas
                    FROM shift_assignments
                    WHERE advisor_id IN ($placeholders)
                      AND fecha BETWEEN ? AND ?
                      AND tipo <> 'break'
                    GROUP BY advisor_id, campaign_id, fecha
                ");
                $stmt->execute($params);
                $rows = $stmt->fetchAll();

                foreach ($rows as $row) {
                    $advId = (int)$row['advisor_id'];
                    $day = (int)substr($row['fecha'], -2);
                    $h = (int)$row['horas'];
                    $cid = (int)$row['campaign_id'];

                    $hoursMap[$advId][$day] = ($hoursMap[$advId][$day] ?? 0) + $h;

                    if ($cid === $campaignId) {
                        $ownCampHoursMap[$advId][$day] = ($ownCampHoursMap[$advId][$day] ?? 0) + $h;
                    } else {
                        $lentHoursMap[$advId][$day] = ($lentHoursMap[$advId][$day] ?? 0) + $h;
                    }
                }
            }

            // Shared (incoming) advisors: only count hours in THIS campaign
            if (!empty($sharedAdvisorIds)) {
                $placeholders = implode(',', array_fill(0, count($sharedAdvisorIds), '?'));
                $params = array_merge($sharedAdvisorIds, [$campaignId, $fechaInicio, $fechaFin]);

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
                $rows = $stmt->fetchAll();

                foreach ($rows as $row) {
                    $day = (int)substr($row['fecha'], -2);
                    $hoursMap[(int)$row['advisor_id']][$day] = (int)$row['horas'];
                }
            }

            // Build report data
            foreach ($advisors as $adv) {
                $advId = (int)$adv['id'];
                $dailyHours = [];
                $dailyLent = [];
                $total = 0;
                $totalLent = 0;
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $h = $hoursMap[$advId][$d] ?? 0;
                    $l = $lentHoursMap[$advId][$d] ?? 0;
                    $dailyHours[$d] = $h;
                    $dailyLent[$d] = $l;
                    $total += $h;
                    $totalLent += $l;
                }

                $target = $monthlyTarget;
                if ($adv['tipo'] === 'compartido') {
                    $target = null;
                }

                $compliance = ($target && $target > 0) ? round(($total / $target) * 100, 1) : null;

                $reportData[] = [
                    'id' => $advId,
                    'nombre' => $adv['nombres'] . ' ' . $adv['apellidos'],
                    'tipo' => $adv['tipo'],
                    'daily' => $dailyHours,
                    'dailyLent' => $dailyLent,
                    'total' => $total,
                    'totalLent' => $totalLent,
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

        return [
            'reportData' => $reportData,
            'availablePeriods' => $availablePeriods,
            'campaign' => $campaign,
            'year' => $year,
            'month' => $month,
            'monthlyTarget' => $monthlyTarget,
            'daysInMonth' => $daysInMonth,
        ];
    }

    /**
     * Resolve period (year, month) from GET params or latest schedule.
     *
     * @return array{year: int, month: int}
     */
    public static function resolvePeriod(?int $year, ?int $month, ?int $campaignId = null): array
    {
        if ($year && $month) {
            return ['year' => $year, 'month' => $month];
        }

        $pdo = Database::getConnection();

        if ($campaignId) {
            $stmt = $pdo->prepare("
                SELECT periodo_anio, periodo_mes FROM schedules
                WHERE campaign_id = :cid
                ORDER BY periodo_anio DESC, periodo_mes DESC LIMIT 1
            ");
            $stmt->execute([':cid' => $campaignId]);
        } else {
            $stmt = $pdo->query("
                SELECT periodo_anio, periodo_mes FROM schedules
                ORDER BY periodo_anio DESC, periodo_mes DESC LIMIT 1
            ");
        }

        $latest = $stmt->fetch();
        if ($latest) {
            return ['year' => (int)$latest['periodo_anio'], 'month' => (int)$latest['periodo_mes']];
        }

        return ['year' => (int)date('Y'), 'month' => (int)date('n')];
    }
}
