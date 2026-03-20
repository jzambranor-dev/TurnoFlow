<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use App\Services\AuthService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

require_once APP_PATH . '/Services/AuthService.php';

class ReportController
{
    /** Default targets by days in month (fallback when DB has no config) */
    private const DEFAULT_TARGETS = [
        28 => 168, 29 => 168, 30 => 170, 31 => 177,
    ];

    /**
     * Get monthly hour target from DB (monthly_hours_config) or fall back to defaults.
     */
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

    public function index(): void
    {
        AuthService::requirePermission('reports.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $isAdmin = AuthService::canManageAllCampaigns($user);

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
        $monthlyTarget = $this->getMonthlyTarget($pdo, $year, $month, $daysInMonth);

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
            // Separate own vs shared advisors
            $ownAdvisorIds = [];
            $sharedAdvisorIds = [];
            foreach ($advisors as $adv) {
                if ($adv['tipo'] === 'propio') {
                    $ownAdvisorIds[] = (int)$adv['id'];
                } else {
                    $sharedAdvisorIds[] = (int)$adv['id'];
                }
            }

            $hoursMap = [];        // [advisor_id][day] = total hours
            $ownCampHoursMap = []; // [advisor_id][day] = hours in this campaign only
            $lentHoursMap = [];    // [advisor_id][day] = hours in other campaigns

            // Own advisors: count ALL their hours across all campaigns
            if (!empty($ownAdvisorIds)) {
                $placeholders = implode(',', array_fill(0, count($ownAdvisorIds), '?'));
                $params = array_merge($ownAdvisorIds, [$fechaInicio, $fechaFin]);

                // Total hours (all campaigns)
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
            $reportData = [];
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

        $pageTitle = 'Reporte de Horas - ' . $campaign['nombre'];
        $currentPage = 'reports';

        include APP_PATH . '/Views/reports/hours.php';
    }

    /**
     * Load schedule ID mapping from HORARIOS.xlsx
     * Returns array: ['HH:MM - HH:MM' => id, ...]
     */
    private function loadScheduleIdMap(): array
    {
        $filePath = BASE_PATH . '/idHorario/HORARIOS.xlsx';
        if (!file_exists($filePath)) {
            return [];
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();
        $map = [];

        foreach ($data as $i => $row) {
            if ($i === 0) continue; // skip header
            $id = $row[0] ?? '';
            $horario = trim((string)($row[1] ?? ''));
            if ($horario === '' || $id === '') continue;

            // Normalize: "08:30 A 15:30" → "08:30 - 15:30"
            $normalized = str_ireplace(' A ', ' - ', $horario);
            $normalized = preg_replace('/\s+/', ' ', trim($normalized));
            $map[$normalized] = $id;
        }

        return $map;
    }

    /**
     * Nomenclature codes
     */
    private function getNomenclatureMap(): array
    {
        return [
            'presente' => 'A',
            'tardanza' => 'AT',
            'ausente' => 'FI',
            'licencia_medica' => 'PE',
            'maternidad' => 'LM',
            'salida_anticipada' => 'A',
        ];
    }

    /**
     * Export hours report as Excel in Asistencia format
     */
    public function exportHours(int $campaignId): void
    {
        AuthService::requirePermission('reports.view');

        try {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id");
        $stmt->execute([':id' => $campaignId]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            header('Location: ' . BASE_URL . '/reports');
            exit;
        }

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

        $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $fechaInicio = sprintf('%04d-%02d-01', $year, $month);
        $fechaFin = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        $monthlyTarget = $this->getMonthlyTarget($pdo, $year, $month, $daysInMonth);

        // Load schedule ID map
        $scheduleIdMap = $this->loadScheduleIdMap();
        $nomenclatureMap = $this->getNomenclatureMap();

        // Get advisors (own + shared)
        $stmt = $pdo->prepare("
            SELECT a.id, a.nombres, a.apellidos, a.cedula, a.tipo_contrato, 'propio' as tipo,
                   c.nombre as campaign_nombre
            FROM advisors a
            JOIN campaigns c ON c.id = a.campaign_id
            WHERE a.campaign_id = :cid AND a.estado = 'activo'
            UNION ALL
            SELECT a.id, a.nombres, a.apellidos, a.cedula, a.tipo_contrato, 'compartido' as tipo,
                   c.nombre as campaign_nombre
            FROM shared_advisors sa
            JOIN advisors a ON a.id = sa.advisor_id
            JOIN campaigns c ON c.id = a.campaign_id
            WHERE sa.target_campaign_id = :cid2 AND sa.estado = 'activo' AND a.estado = 'activo'
            ORDER BY tipo, apellidos, nombres
        ");
        $stmt->execute([':cid' => $campaignId, ':cid2' => $campaignId]);
        $advisors = $stmt->fetchAll();

        // Get shift assignments with hours for each advisor per day
        $advisorIds = array_column($advisors, 'id');
        $assignmentsMap = []; // [advisor_id][day] = ['start' => min_hour, 'end' => max_hour+1, 'hours' => count]

        if (!empty($advisorIds)) {
            $placeholders = implode(',', array_fill(0, count($advisorIds), '?'));
            $params = array_merge($advisorIds, [$campaignId, $fechaInicio, $fechaFin]);

            $stmt = $pdo->prepare("
                SELECT advisor_id, fecha::text, hora, tipo
                FROM shift_assignments
                WHERE advisor_id IN ($placeholders)
                  AND campaign_id = ?
                  AND fecha BETWEEN ? AND ?
                ORDER BY advisor_id, fecha, hora
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $advId = (int)$row['advisor_id'];
                $day = (int)substr($row['fecha'], -2);
                $hora = (int)$row['hora'];
                $tipo = $row['tipo'];

                if (!isset($assignmentsMap[$advId][$day])) {
                    $assignmentsMap[$advId][$day] = [
                        'hours' => [],
                        'work_hours' => 0,
                    ];
                }
                $assignmentsMap[$advId][$day]['hours'][] = $hora;
                if ($tipo !== 'break') {
                    $assignmentsMap[$advId][$day]['work_hours']++;
                }
            }
        }

        // Get attendance data
        $attendanceMap = []; // [advisor_id][day] = status
        if (!empty($advisorIds)) {
            $placeholders = implode(',', array_fill(0, count($advisorIds), '?'));
            $params = array_merge($advisorIds, [$fechaInicio, $fechaFin]);

            $stmt = $pdo->prepare("
                SELECT advisor_id, fecha::text, status
                FROM attendance
                WHERE advisor_id IN ($placeholders)
                  AND fecha BETWEEN ? AND ?
            ");
            $stmt->execute($params);
            $attRows = $stmt->fetchAll();

            foreach ($attRows as $row) {
                $advId = (int)$row['advisor_id'];
                $day = (int)substr($row['fecha'], -2);
                $attendanceMap[$advId][$day] = $row['status'];
            }
        }

        // Build Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CONTROL INBOUND');

        $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
        $monthNamesEs = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                          'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $dayNamesEn = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $campName = strtoupper($campaign['nombre']);

        // Column layout:
        // A=NOMBRE, B=CEDULA, C=CAMPAÑA, D=USUARIO CIC, E=CORREO, F=CARGO, G=MODALIDAD
        // Then per day: CAMPAÑA, HORARIO, N° HORAS TRABAJADAS, NOMENCLATURA (4 cols each)
        // Then: HORAS TOTALES, ATRASOS, FALTAS INJUST., VACACIONES, CUMPLIMIENTO, META, FALTAN, $$, ACTUAL

        $fixedCols = 7; // A-G
        $colsPerDay = 5; // CAMPAÑA, HORARIO, N° HORAS, NOMENCLATURA, ID HORARIO
        $totalDayCols = $daysInMonth * $colsPerDay;
        $summaryStartCol = $fixedCols + $totalDayCols; // 0-indexed

        // ---- Row 0: Campaign totals per day (row 1 in Excel) ----
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayColBase = $fixedCols + ($d - 1) * $colsPerDay;
            $colLetter = Coordinate::stringFromColumnIndex($dayColBase + 1);
            $sheet->setCellValue($colLetter . '1', $campName);
        }

        // ---- Row 1: Campaign name for videollamada row (skip for now, same campaign)

        // ---- Row 2: Day names ----
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $dow = (int)date('w', strtotime($dateStr)); // 0=Sun, 6=Sat
            $dayColBase = $fixedCols + ($d - 1) * $colsPerDay;
            $colLetter = Coordinate::stringFromColumnIndex($dayColBase + 1);
            $sheet->setCellValue($colLetter . '3', $dayNamesEn[$dow]);
        }

        // ---- Row 3: Dates ----
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%d de %s de %d', $d, $monthNames[$month], $year);
            $dayColBase = $fixedCols + ($d - 1) * $colsPerDay;
            $colLetter = Coordinate::stringFromColumnIndex($dayColBase + 1);
            $sheet->setCellValue($colLetter . '4', $dateStr);
        }

        // ---- Row 4: Headers (row 5 in Excel) ----
        $headerRow = 5;
        $headers = ['NOMBRE', 'CEDULA', 'CAMPAÑA', 'USUARIO CIC', 'CORREO', 'CARGO', 'MODALIDAD'];
        foreach ($headers as $i => $h) {
            $colLetter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($colLetter . $headerRow, $h);
        }

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayColBase = $fixedCols + ($d - 1) * $colsPerDay;
            $dayHeaders = ['CAMPAÑA', 'HORARIO', 'N° HORAS TRABAJADAS ', 'NOMENCLATURA', 'ID HORARIO'];
            foreach ($dayHeaders as $hi => $dh) {
                $colLetter = Coordinate::stringFromColumnIndex($dayColBase + $hi + 1);
                $sheet->setCellValue($colLetter . $headerRow, $dh);
            }
        }

        // Summary headers
        $summaryHeaders = ['HORAS TOTALES A LA FECHA', 'ATRASOS', 'FALTAS INJUSTIFICADAS',
                           'VACACIONES', 'CUMPLIMIENTO', 'META', 'FALTAN', '$$ Dolars', 'ACTUAL'];
        foreach ($summaryHeaders as $si => $sh) {
            $colLetter = Coordinate::stringFromColumnIndex($summaryStartCol + $si + 1);
            $sheet->setCellValue($colLetter . $headerRow, $sh);
        }

        // Style header row
        $lastColLetter = Coordinate::stringFromColumnIndex($summaryStartCol + count($summaryHeaders));
        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // ---- Data rows ----
        $dataRowStart = 6;
        foreach ($advisors as $ai => $adv) {
            $row = $dataRowStart + $ai;
            $advId = (int)$adv['id'];
            $fullName = $adv['apellidos'] . ' ' . $adv['nombres'];

            // Fixed columns
            $sheet->setCellValue('A' . $row, strtoupper($fullName));
            $sheet->setCellValue('B' . $row, $adv['cedula'] ?? '');
            $sheet->setCellValue('C' . $row, $campName);
            $sheet->setCellValue('D' . $row, ''); // USUARIO CIC
            $sheet->setCellValue('E' . $row, ''); // CORREO
            $sheet->setCellValue('F' . $row, 'AGENTE');
            $sheet->setCellValue('G' . $row, 'PRESENCIAL');

            $totalHours = 0;
            $totalAtrasos = 0;
            $totalFaltas = 0;
            $totalVacaciones = 0;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dayColBase = $fixedCols + ($d - 1) * $colsPerDay;
                $colCamp = Coordinate::stringFromColumnIndex($dayColBase + 1);
                $colHor = Coordinate::stringFromColumnIndex($dayColBase + 2);
                $colHrs = Coordinate::stringFromColumnIndex($dayColBase + 3);
                $colNom = Coordinate::stringFromColumnIndex($dayColBase + 4);
                $colIdH = Coordinate::stringFromColumnIndex($dayColBase + 5);

                $sheet->setCellValue($colCamp . $row, $campName);

                $dayData = $assignmentsMap[$advId][$d] ?? null;
                $attStatus = $attendanceMap[$advId][$d] ?? null;

                if ($dayData && !empty($dayData['hours'])) {
                    $allHours = $dayData['hours'];
                    sort($allHours);
                    $startHour = min($allHours);
                    $endHour = max($allHours) + 1; // +1 because hour 15 means 15:00-16:00

                    // Format: "HH:MM - HH:MM"
                    $startStr = sprintf('%02d:00', $startHour);
                    $endStr = sprintf('%02d:00', $endHour % 24);
                    $horarioStr = $startStr . ' - ' . $endStr;

                    // Find schedule ID from HORARIOS.xlsx map
                    $scheduleId = $this->findScheduleId($scheduleIdMap, $startHour, $endHour);

                    $workHours = $dayData['work_hours'];
                    $hoursDisplay = $this->formatHoursMinutes($workHours);

                    // HORARIO column: show time range + schedule ID
                    $horarioDisplay = $horarioStr;
                    $sheet->setCellValue($colHor . $row, $horarioDisplay);
                    $sheet->setCellValue($colHrs . $row, $hoursDisplay);

                    // Nomenclature
                    $nom = 'A'; // default: Asistencia
                    if ($attStatus) {
                        $nom = $nomenclatureMap[$attStatus] ?? 'A';
                        if ($attStatus === 'tardanza') $totalAtrasos++;
                        if ($attStatus === 'ausente') $totalFaltas++;
                    }
                    $sheet->setCellValue($colNom . $row, $nom);
                    $sheet->setCellValue($colIdH . $row, $scheduleId);
                    $totalHours += $workHours;
                } else {
                    // No assignment: check if rest day or absence
                    if ($attStatus === 'ausente') {
                        $sheet->setCellValue($colHor . $row, 'FI');
                        $sheet->setCellValue($colHrs . $row, '0:00');
                        $sheet->setCellValue($colNom . $row, 'FI');
                        $sheet->setCellValue($colIdH . $row, 'FI');
                        $totalFaltas++;
                    } elseif ($attStatus === 'licencia_medica') {
                        $sheet->setCellValue($colHor . $row, 'PE');
                        $sheet->setCellValue($colHrs . $row, '0:00');
                        $sheet->setCellValue($colNom . $row, 'PE');
                        $sheet->setCellValue($colIdH . $row, 'PE');
                    } elseif ($attStatus === 'maternidad') {
                        $sheet->setCellValue($colHor . $row, 'LM');
                        $sheet->setCellValue($colHrs . $row, '0:00');
                        $sheet->setCellValue($colNom . $row, 'LM');
                        $sheet->setCellValue($colIdH . $row, 'LM');
                    } else {
                        // Rest day
                        $sheet->setCellValue($colHor . $row, '0');
                        $sheet->setCellValue($colHrs . $row, '0:00');
                        $sheet->setCellValue($colNom . $row, '0');
                        $sheet->setCellValue($colIdH . $row, '0');
                    }
                }
            }

            // Summary columns
            $colIdx = $summaryStartCol;
            $totalHoursFormatted = $this->formatHoursMinutes($totalHours);
            $compliance = ($monthlyTarget > 0) ? round(($totalHours / $monthlyTarget) * 100) : 0;
            $deficit = $monthlyTarget - $totalHours;

            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 1) . $row, $totalHoursFormatted);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 2) . $row, $totalAtrasos);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 3) . $row, $totalFaltas);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 4) . $row, $totalVacaciones);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 5) . $row, $compliance . '%');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 6) . $row, $this->formatHoursMinutes($monthlyTarget));
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 7) . $row, $this->formatHoursMinutes(max(0, $deficit)));
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 8) . $row, ''); // $$ Dolars
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 9) . $row, $totalHoursFormatted);
        }

        // Style data area with borders
        $lastDataRow = $dataRowStart + count($advisors) - 1;
        if ($lastDataRow >= $dataRowStart) {
            $sheet->getStyle("A{$dataRowStart}:{$lastColLetter}{$lastDataRow}")->applyFromArray([
                'font' => ['size' => 9],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            // Left-align name column
            $sheet->getStyle("A{$dataRowStart}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(14);

        // ---- Create id_horario sheet ----
        $idSheet = $spreadsheet->createSheet();
        $idSheet->setTitle('id_horario');
        $idSheet->setCellValue('A1', 'Horario');
        $idSheet->setCellValue('B1', 'Id');
        $idSheet->setCellValue('C1', 'Hora Entrada');
        $idSheet->setCellValue('D1', 'Hora Salida');
        $idSheet->setCellValue('E1', 'Jornada(Horas)');
        $idSheet->getStyle('A1:E1')->getFont()->setBold(true);

        $idRow = 2;
        foreach ($scheduleIdMap as $horario => $id) {
            $parts = explode(' - ', $horario);
            if (count($parts) === 2) {
                $idSheet->setCellValue('A' . $idRow, $horario);
                $idSheet->setCellValue('B' . $idRow, $id);
                $idSheet->setCellValue('C' . $idRow, $parts[0]);
                $idSheet->setCellValue('D' . $idRow, $parts[1]);
                $idSheet->setCellValue('E' . $idRow, '');
                $idRow++;
            }
        }

        // ---- Create Nomenclatura sheet ----
        $nomSheet = $spreadsheet->createSheet();
        $nomSheet->setTitle('Nomenclatura');
        $nomSheet->setCellValue('B2', 'NOMENCLATURA');
        $nomSheet->getStyle('B2')->getFont()->setBold(true);
        $nomenclaturas = [
            ['A', 'ASISTENCIA'],
            ['AT', 'ATRASOS'],
            ['FI', 'FALTA INJUSTIFICADA'],
            ['FJ', 'FALTA JUSTIFICADA'],
            ['LM', 'LICENCIA MATERNIDAD'],
            ['LP', 'LICENCIA PATERNIDAD'],
            ['PE', 'PERMISO MEDICO I.E.S.S. EXTENDIDO'],
            ['PN', 'PERMISO MEDICO I.E.S.S NORMAL'],
            ['V', 'VACACIONES'],
            ['R', 'RENUNCIA'],
            ['LS', 'LICENCIA SIN SUELDO'],
            ['0', 'DIAS LIBRES'],
            ['F', 'FERIADO'],
        ];
        foreach ($nomenclaturas as $ni => $nom) {
            $nomSheet->setCellValue('B' . ($ni + 3), $nom[0]);
            $nomSheet->setCellValue('C' . ($ni + 3), $nom[1]);
        }

        // Set active sheet back to first
        $spreadsheet->setActiveSheetIndex(0);

        // Output
        $filename = sprintf('Asistencia_%s_%s_%d.xlsx',
            str_replace(' ', '_', $campaign['nombre']),
            $monthNamesEs[$month],
            $year
        );

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        } catch (\Throwable $e) {
            error_log('Error exportando reporte Excel: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Error al generar el archivo Excel. Intente nuevamente.';
            header('Location: ' . BASE_URL . '/reports');
            exit;
        }
    }

    /**
     * Export unified report (all campaigns) — admin/gerente/coordinador only
     */
    public function exportUnified(): void
    {
        AuthService::requirePermission('reports.view');

        $user = $_SESSION['user'];
        $isAdmin = AuthService::canManageAllCampaigns($user);
        if (!$isAdmin) {
            header('Location: ' . BASE_URL . '/reports');
            exit;
        }

        try {
        $pdo = Database::getConnection();

        $year = (int)($_GET['year'] ?? 0);
        $month = (int)($_GET['month'] ?? 0);

        if ($year === 0 || $month === 0) {
            $stmt = $pdo->query("
                SELECT periodo_anio, periodo_mes FROM schedules
                ORDER BY periodo_anio DESC, periodo_mes DESC LIMIT 1
            ");
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
        $monthlyTarget = $this->getMonthlyTarget($pdo, $year, $month, $daysInMonth);

        $scheduleIdMap = $this->loadScheduleIdMap();
        $nomenclatureMap = $this->getNomenclatureMap();

        // Get ALL active campaigns
        $stmt = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        $allCampaigns = $stmt->fetchAll();

        // Get ALL active advisors with their campaign
        $stmt = $pdo->query("
            SELECT a.id, a.nombres, a.apellidos, a.cedula, a.tipo_contrato,
                   c.id as campaign_id, c.nombre as campaign_nombre
            FROM advisors a
            JOIN campaigns c ON c.id = a.campaign_id
            WHERE a.estado = 'activo' AND c.estado = 'activa'
            ORDER BY c.nombre, a.apellidos, a.nombres
        ");
        $advisors = $stmt->fetchAll();

        if (empty($advisors)) {
            header('Location: ' . BASE_URL . '/reports');
            exit;
        }

        $advisorIds = array_column($advisors, 'id');

        // Get ALL shift assignments for the period
        $assignmentsMap = []; // [advisor_id][day] = ['hours' => [...], 'work_hours' => N, 'campaign_id' => X]
        if (!empty($advisorIds)) {
            $placeholders = implode(',', array_fill(0, count($advisorIds), '?'));
            $params = array_merge($advisorIds, [$fechaInicio, $fechaFin]);

            $stmt = $pdo->prepare("
                SELECT advisor_id, campaign_id, fecha::text, hora, tipo
                FROM shift_assignments
                WHERE advisor_id IN ($placeholders)
                  AND fecha BETWEEN ? AND ?
                ORDER BY advisor_id, fecha, hora
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $advId = (int)$row['advisor_id'];
                $day = (int)substr($row['fecha'], -2);
                $hora = (int)$row['hora'];
                $tipo = $row['tipo'];
                $cid = (int)$row['campaign_id'];

                if (!isset($assignmentsMap[$advId][$day])) {
                    $assignmentsMap[$advId][$day] = [
                        'hours' => [],
                        'work_hours' => 0,
                        'campaign_id' => $cid,
                    ];
                }
                $assignmentsMap[$advId][$day]['hours'][] = $hora;
                if ($tipo !== 'break') {
                    $assignmentsMap[$advId][$day]['work_hours']++;
                }
            }
        }

        // Get attendance data
        $attendanceMap = [];
        if (!empty($advisorIds)) {
            $placeholders = implode(',', array_fill(0, count($advisorIds), '?'));
            $params = array_merge($advisorIds, [$fechaInicio, $fechaFin]);

            $stmt = $pdo->prepare("
                SELECT advisor_id, fecha::text, status
                FROM attendance
                WHERE advisor_id IN ($placeholders)
                  AND fecha BETWEEN ? AND ?
            ");
            $stmt->execute($params);
            $attRows = $stmt->fetchAll();

            foreach ($attRows as $row) {
                $advId = (int)$row['advisor_id'];
                $day = (int)substr($row['fecha'], -2);
                $attendanceMap[$advId][$day] = $row['status'];
            }
        }

        // Build campaign name lookup
        $campNameMap = [];
        foreach ($allCampaigns as $c) {
            $campNameMap[(int)$c['id']] = strtoupper($c['nombre']);
        }

        // Build Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CONTROL INBOUND');

        $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
        $monthNamesEs = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                          'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $dayNamesEn = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        $fixedCols = 7;
        $colsPerDay = 5;
        $totalDayCols = $daysInMonth * $colsPerDay;
        $summaryStartCol = $fixedCols + $totalDayCols;

        // ---- Row 1: Campaign totals headers per day ----
        // Show all campaign names in rows 1-2
        $campNamesStr = implode(' / ', array_map(fn($c) => strtoupper($c['nombre']), $allCampaigns));
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayColBase = $fixedCols + ($d - 1) * $colsPerDay;
            $colLetter = Coordinate::stringFromColumnIndex($dayColBase + 1);
            $sheet->setCellValue($colLetter . '1', $campNamesStr);
        }

        // ---- Row 3: Day names ----
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $dow = (int)date('w', strtotime($dateStr));
            $dayColBase = $fixedCols + ($d - 1) * $colsPerDay;
            $colLetter = Coordinate::stringFromColumnIndex($dayColBase + 1);
            $sheet->setCellValue($colLetter . '3', $dayNamesEn[$dow]);
        }

        // ---- Row 4: Dates ----
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%d de %s de %d', $d, $monthNames[$month], $year);
            $dayColBase = $fixedCols + ($d - 1) * $colsPerDay;
            $colLetter = Coordinate::stringFromColumnIndex($dayColBase + 1);
            $sheet->setCellValue($colLetter . '4', $dateStr);
        }

        // ---- Row 5: Headers ----
        $headerRow = 5;
        $headers = ['NOMBRE', 'CEDULA', 'CAMPAÑA', 'USUARIO CIC', 'CORREO', 'CARGO', 'MODALIDAD'];
        foreach ($headers as $i => $h) {
            $colLetter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($colLetter . $headerRow, $h);
        }

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayColBase = $fixedCols + ($d - 1) * $colsPerDay;
            $dayHeaders = ['CAMPAÑA', 'HORARIO', 'N° HORAS TRABAJADAS ', 'NOMENCLATURA', 'ID HORARIO'];
            foreach ($dayHeaders as $hi => $dh) {
                $colLetter = Coordinate::stringFromColumnIndex($dayColBase + $hi + 1);
                $sheet->setCellValue($colLetter . $headerRow, $dh);
            }
        }

        $summaryHeaders = ['HORAS TOTALES A LA FECHA', 'ATRASOS', 'FALTAS INJUSTIFICADAS',
                           'VACACIONES', 'CUMPLIMIENTO', 'META', 'FALTAN', '$$ Dolars', 'ACTUAL'];
        foreach ($summaryHeaders as $si => $sh) {
            $colLetter = Coordinate::stringFromColumnIndex($summaryStartCol + $si + 1);
            $sheet->setCellValue($colLetter . $headerRow, $sh);
        }

        $lastColLetter = Coordinate::stringFromColumnIndex($summaryStartCol + count($summaryHeaders));
        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // ---- Data rows ----
        $dataRowStart = 6;
        $currentRow = $dataRowStart;
        $lastCampaign = '';

        foreach ($advisors as $adv) {
            $row = $currentRow;
            $advId = (int)$adv['id'];
            $advCampId = (int)$adv['campaign_id'];
            $campName = $campNameMap[$advCampId] ?? strtoupper($adv['campaign_nombre']);
            $fullName = strtoupper($adv['apellidos'] . ' ' . $adv['nombres']);

            // Insert campaign separator row if campaign changed
            if ($campName !== $lastCampaign && $lastCampaign !== '') {
                // Empty separator row
                $currentRow++;
                $row = $currentRow;
            }
            $lastCampaign = $campName;

            $sheet->setCellValue('A' . $row, $fullName);
            $sheet->setCellValue('B' . $row, $adv['cedula'] ?? '');
            $sheet->setCellValue('C' . $row, $campName);
            $sheet->setCellValue('D' . $row, '');
            $sheet->setCellValue('E' . $row, '');
            $sheet->setCellValue('F' . $row, 'AGENTE');
            $sheet->setCellValue('G' . $row, 'PRESENCIAL');

            $totalHours = 0;
            $totalAtrasos = 0;
            $totalFaltas = 0;
            $totalVacaciones = 0;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dayColBase = $fixedCols + ($d - 1) * $colsPerDay;
                $colCamp = Coordinate::stringFromColumnIndex($dayColBase + 1);
                $colHor = Coordinate::stringFromColumnIndex($dayColBase + 2);
                $colHrs = Coordinate::stringFromColumnIndex($dayColBase + 3);
                $colNom = Coordinate::stringFromColumnIndex($dayColBase + 4);
                $colIdH = Coordinate::stringFromColumnIndex($dayColBase + 5);

                $dayData = $assignmentsMap[$advId][$d] ?? null;
                $attStatus = $attendanceMap[$advId][$d] ?? null;

                // Campaign column: show which campaign this day was worked in
                $dayCampName = $campName;
                if ($dayData && isset($dayData['campaign_id'])) {
                    $dayCampName = $campNameMap[(int)$dayData['campaign_id']] ?? $campName;
                }
                $sheet->setCellValue($colCamp . $row, $dayCampName);

                if ($dayData && !empty($dayData['hours'])) {
                    $allHours = $dayData['hours'];
                    sort($allHours);
                    $startHour = min($allHours);
                    $endHour = max($allHours) + 1;

                    $startStr = sprintf('%02d:00', $startHour);
                    $endStr = sprintf('%02d:00', $endHour % 24);
                    $horarioStr = $startStr . ' - ' . $endStr;

                    $scheduleId = $this->findScheduleId($scheduleIdMap, $startHour, $endHour);

                    $workHours = $dayData['work_hours'];
                    $hoursDisplay = $this->formatHoursMinutes($workHours);

                    $sheet->setCellValue($colHor . $row, $horarioStr);
                    $sheet->setCellValue($colHrs . $row, $hoursDisplay);

                    $nom = 'A';
                    if ($attStatus) {
                        $nom = $nomenclatureMap[$attStatus] ?? 'A';
                        if ($attStatus === 'tardanza') $totalAtrasos++;
                        if ($attStatus === 'ausente') $totalFaltas++;
                    }
                    $sheet->setCellValue($colNom . $row, $nom);
                    $sheet->setCellValue($colIdH . $row, $scheduleId);
                    $totalHours += $workHours;
                } else {
                    if ($attStatus === 'ausente') {
                        $sheet->setCellValue($colHor . $row, 'FI');
                        $sheet->setCellValue($colHrs . $row, '0:00');
                        $sheet->setCellValue($colNom . $row, 'FI');
                        $sheet->setCellValue($colIdH . $row, 'FI');
                        $totalFaltas++;
                    } elseif ($attStatus === 'licencia_medica') {
                        $sheet->setCellValue($colHor . $row, 'PE');
                        $sheet->setCellValue($colHrs . $row, '0:00');
                        $sheet->setCellValue($colNom . $row, 'PE');
                        $sheet->setCellValue($colIdH . $row, 'PE');
                    } elseif ($attStatus === 'maternidad') {
                        $sheet->setCellValue($colHor . $row, 'LM');
                        $sheet->setCellValue($colHrs . $row, '0:00');
                        $sheet->setCellValue($colNom . $row, 'LM');
                        $sheet->setCellValue($colIdH . $row, 'LM');
                    } else {
                        $sheet->setCellValue($colHor . $row, '0');
                        $sheet->setCellValue($colHrs . $row, '0:00');
                        $sheet->setCellValue($colNom . $row, '0');
                        $sheet->setCellValue($colIdH . $row, '0');
                    }
                }
            }

            // Summary columns
            $colIdx = $summaryStartCol;
            $totalHoursFormatted = $this->formatHoursMinutes($totalHours);
            $compliance = ($monthlyTarget > 0) ? round(($totalHours / $monthlyTarget) * 100) : 0;
            $deficit = $monthlyTarget - $totalHours;

            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 1) . $row, $totalHoursFormatted);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 2) . $row, $totalAtrasos);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 3) . $row, $totalFaltas);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 4) . $row, $totalVacaciones);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 5) . $row, $compliance . '%');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 6) . $row, $this->formatHoursMinutes($monthlyTarget));
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 7) . $row, $this->formatHoursMinutes(max(0, $deficit)));
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 8) . $row, '');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 9) . $row, $totalHoursFormatted);

            $currentRow++;
        }

        // Style data area
        $lastDataRow = $currentRow - 1;
        if ($lastDataRow >= $dataRowStart) {
            $sheet->getStyle("A{$dataRowStart}:{$lastColLetter}{$lastDataRow}")->applyFromArray([
                'font' => ['size' => 9],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle("A{$dataRowStart}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(18);

        // ---- id_horario sheet ----
        $idSheet = $spreadsheet->createSheet();
        $idSheet->setTitle('id_horario');
        $idSheet->setCellValue('A1', 'Horario');
        $idSheet->setCellValue('B1', 'Id');
        $idSheet->setCellValue('C1', 'Hora Entrada');
        $idSheet->setCellValue('D1', 'Hora Salida');
        $idSheet->setCellValue('E1', 'Jornada(Horas)');
        $idSheet->getStyle('A1:E1')->getFont()->setBold(true);

        $idRow = 2;
        foreach ($scheduleIdMap as $horario => $id) {
            $parts = explode(' - ', $horario);
            if (count($parts) === 2) {
                $idSheet->setCellValue('A' . $idRow, $horario);
                $idSheet->setCellValue('B' . $idRow, $id);
                $idSheet->setCellValue('C' . $idRow, $parts[0]);
                $idSheet->setCellValue('D' . $idRow, $parts[1]);
                $idRow++;
            }
        }

        // ---- Nomenclatura sheet ----
        $nomSheet = $spreadsheet->createSheet();
        $nomSheet->setTitle('Nomenclatura');
        $nomSheet->setCellValue('B2', 'NOMENCLATURA');
        $nomSheet->getStyle('B2')->getFont()->setBold(true);
        $nomenclaturas = [
            ['A', 'ASISTENCIA'], ['AT', 'ATRASOS'], ['FI', 'FALTA INJUSTIFICADA'],
            ['FJ', 'FALTA JUSTIFICADA'], ['LM', 'LICENCIA MATERNIDAD'],
            ['LP', 'LICENCIA PATERNIDAD'], ['PE', 'PERMISO MEDICO I.E.S.S. EXTENDIDO'],
            ['PN', 'PERMISO MEDICO I.E.S.S NORMAL'], ['V', 'VACACIONES'],
            ['R', 'RENUNCIA'], ['LS', 'LICENCIA SIN SUELDO'],
            ['0', 'DIAS LIBRES'], ['F', 'FERIADO'],
        ];
        foreach ($nomenclaturas as $ni => $nom) {
            $nomSheet->setCellValue('B' . ($ni + 3), $nom[0]);
            $nomSheet->setCellValue('C' . ($ni + 3), $nom[1]);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = sprintf('Asistencia_Unificada_%s_%d.xlsx', $monthNamesEs[$month], $year);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        } catch (\Throwable $e) {
            error_log('Error exportando reporte unificado: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Error al generar el archivo Excel unificado. Intente nuevamente.';
            header('Location: ' . BASE_URL . '/reports');
            exit;
        }
    }

    /**
     * Find schedule ID from the map based on start/end hours
     */
    private function findScheduleId(array $map, int $startHour, int $endHour): string
    {
        // Try exact match with :00 format
        $startStr = sprintf('%02d:00', $startHour);
        $endStr = sprintf('%02d:00', $endHour % 24);
        $key = $startStr . ' - ' . $endStr;

        if (isset($map[$key])) {
            return (string)$map[$key];
        }

        // Try with :30 variations
        foreach ([0, 30] as $startMin) {
            foreach ([0, 30] as $endMin) {
                $s = sprintf('%02d:%02d', $startHour, $startMin);
                $e = sprintf('%02d:%02d', $endHour % 24, $endMin);
                $k = $s . ' - ' . $e;
                if (isset($map[$k])) {
                    return (string)$map[$k];
                }
            }
        }

        return 'ROTATIVO';
    }

    /**
     * Format hours as H:MM string
     */
    private function formatHoursMinutes(int $hours): string
    {
        return $hours . ':00';
    }
}
