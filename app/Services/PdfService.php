<?php

declare(strict_types=1);

namespace App\Services;

use Database;
use Mpdf\Mpdf;
use PDO;

/**
 * Servicio para generar PDFs de horarios
 */
class PdfService
{
    /**
     * Generar PDF del horario mensual
     */
    public static function generateSchedulePdf(int $scheduleId): ?Mpdf
    {
        $pdo = Database::getConnection();

        // Obtener datos del horario
        $stmt = $pdo->prepare("
            SELECT s.*, c.nombre as campaign_nombre
            FROM schedules s
            JOIN campaigns c ON c.id = s.campaign_id
            WHERE s.id = :id
        ");
        $stmt->execute([':id' => $scheduleId]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            return null;
        }

        $year = (int)$schedule['periodo_anio'];
        $month = (int)$schedule['periodo_mes'];
        $campaignName = $schedule['campaign_nombre'];
        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

        // Obtener asesores con asignaciones
        $stmt = $pdo->prepare("
            SELECT DISTINCT a.id, a.nombres, a.apellidos
            FROM advisors a
            JOIN shift_assignments sa ON sa.advisor_id = a.id
            WHERE sa.schedule_id = :sid
            ORDER BY a.apellidos, a.nombres
        ");
        $stmt->execute([':sid' => $scheduleId]);
        $advisors = $stmt->fetchAll();

        // Obtener todas las asignaciones
        $stmt = $pdo->prepare("
            SELECT advisor_id, fecha, hora, tipo
            FROM shift_assignments
            WHERE schedule_id = :sid
            ORDER BY advisor_id, fecha, hora
        ");
        $stmt->execute([':sid' => $scheduleId]);
        $assignments = $stmt->fetchAll();

        // Organizar asignaciones por asesor y fecha
        $grid = [];
        foreach ($assignments as $a) {
            $advId = $a['advisor_id'];
            $day = (int)date('j', strtotime($a['fecha']));
            if (!isset($grid[$advId])) {
                $grid[$advId] = [];
            }
            if (!isset($grid[$advId][$day])) {
                $grid[$advId][$day] = [];
            }
            $grid[$advId][$day][] = $a;
        }

        // Calcular totales por asesor
        $totals = [];
        foreach ($grid as $advId => $days) {
            $total = 0;
            foreach ($days as $dayAssignments) {
                foreach ($dayAssignments as $a) {
                    if ($a['tipo'] !== 'break') {
                        $total++;
                    }
                }
            }
            $totals[$advId] = $total;
        }

        // Mes en español
        $monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                       'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $monthName = $monthNames[$month - 1];

        // Colores por tipo de turno
        $typeColors = [
            'normal' => '#e0f2fe',    // azul claro
            'extra' => '#fef3c7',     // amarillo
            'nocturno' => '#e0e7ff',  // indigo claro
            'break' => '#f3e8ff',     // morado claro
            'replanif' => '#fce7f3',  // rosa
        ];

        // Generar HTML
        $html = self::buildHtmlHeader($campaignName, $monthName, $year, $schedule['status']);
        $html .= self::buildHtmlTable($advisors, $grid, $totals, $daysInMonth, $year, $month, $typeColors);
        $html .= self::buildHtmlFooter();

        // Crear mPDF
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L', // Landscape
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'default_font_size' => 8,
            'default_font' => 'dejavusans',
        ]);

        $mpdf->SetTitle("Horario {$campaignName} - {$monthName} {$year}");
        $mpdf->SetAuthor('TurnoFlow');
        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    private static function buildHtmlHeader(string $campaign, string $month, int $year, string $status): string
    {
        $statusLabels = [
            'borrador' => ['Borrador', '#94a3b8'],
            'enviado' => ['Enviado', '#f59e0b'],
            'aprobado' => ['Aprobado', '#22c55e'],
            'rechazado' => ['Rechazado', '#ef4444'],
        ];
        $statusInfo = $statusLabels[$status] ?? ['Desconocido', '#94a3b8'];

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8pt; }
        .header { text-align: center; margin-bottom: 10px; }
        .header h1 { margin: 0 0 5px 0; font-size: 14pt; color: #1e40af; }
        .header p { margin: 0; color: #6b7280; font-size: 10pt; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 4px; color: #fff; font-weight: bold; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 3px 4px; text-align: center; font-size: 7pt; }
        th { background: #1e40af; color: #fff; font-weight: bold; }
        th.advisor { text-align: left; width: 120px; }
        th.total { width: 40px; background: #1e3a8a; }
        td.advisor { text-align: left; font-weight: 500; white-space: nowrap; overflow: hidden; }
        td.total { font-weight: bold; background: #f1f5f9; }
        .day-header { font-size: 6pt; }
        .day-name { font-size: 5pt; color: #93c5fd; }
        .cell-normal { background: #e0f2fe; }
        .cell-extra { background: #fef3c7; }
        .cell-nocturno { background: #e0e7ff; }
        .cell-break { background: #f3e8ff; font-size: 5pt; color: #7c3aed; }
        .cell-replanif { background: #fce7f3; }
        .footer { margin-top: 15px; font-size: 7pt; color: #6b7280; text-align: center; }
        .legend { margin-top: 10px; display: flex; gap: 15px; justify-content: center; font-size: 7pt; }
        .legend-item { display: inline-flex; align-items: center; gap: 4px; }
        .legend-box { width: 12px; height: 12px; border: 1px solid #d1d5db; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Horario - {$campaign}</h1>
        <p>{$month} {$year} &nbsp; <span class="status" style="background: {$statusInfo[1]};">{$statusInfo[0]}</span></p>
    </div>
HTML;
    }

    private static function buildHtmlTable(array $advisors, array $grid, array $totals, int $daysInMonth, int $year, int $month, array $typeColors): string
    {
        $dayNames = ['D', 'L', 'M', 'X', 'J', 'V', 'S'];

        // Header de la tabla
        $html = '<table><thead><tr><th class="advisor">Asesor</th>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dow = (int)date('w', mktime(0, 0, 0, $month, $d, $year));
            $dayName = $dayNames[$dow];
            $html .= "<th class=\"day-header\">{$d}<br><span class=\"day-name\">{$dayName}</span></th>";
        }
        $html .= '<th class="total">Total</th></tr></thead><tbody>';

        // Filas de asesores
        foreach ($advisors as $adv) {
            $name = mb_substr($adv['apellidos'] . ', ' . $adv['nombres'], 0, 25);
            $html .= "<tr><td class=\"advisor\">{$name}</td>";

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dayAssignments = $grid[$adv['id']][$d] ?? [];
                if (empty($dayAssignments)) {
                    $html .= '<td>-</td>';
                } else {
                    // Mostrar rango de horas
                    $hours = array_column($dayAssignments, 'hora');
                    $types = array_column($dayAssignments, 'tipo');
                    $mainType = in_array('nocturno', $types) ? 'nocturno' : (in_array('extra', $types) ? 'extra' : 'normal');

                    $minH = min($hours);
                    $maxH = max($hours) + 1;
                    $hasBreak = in_array('break', $types);
                    $cellClass = 'cell-' . $mainType;

                    $content = sprintf('%02d-%02d', $minH, $maxH);
                    if ($hasBreak) {
                        $content .= '<br><span style="font-size:5pt;color:#7c3aed;">B</span>';
                    }
                    $html .= "<td class=\"{$cellClass}\">{$content}</td>";
                }
            }

            $total = $totals[$adv['id']] ?? 0;
            $html .= "<td class=\"total\">{$total}h</td></tr>";
        }

        $html .= '</tbody></table>';

        // Leyenda
        $html .= <<<HTML
<div class="legend">
    <span class="legend-item"><span class="legend-box" style="background: #e0f2fe;"></span> Normal</span>
    <span class="legend-item"><span class="legend-box" style="background: #fef3c7;"></span> Extra</span>
    <span class="legend-item"><span class="legend-box" style="background: #e0e7ff;"></span> Nocturno</span>
    <span class="legend-item"><span class="legend-box" style="background: #f3e8ff;"></span> Break</span>
</div>
HTML;

        return $html;
    }

    private static function buildHtmlFooter(): string
    {
        $date = date('d/m/Y H:i');
        return <<<HTML
    <div class="footer">
        Generado por TurnoFlow el {$date}
    </div>
</body>
</html>
HTML;
    }
}
