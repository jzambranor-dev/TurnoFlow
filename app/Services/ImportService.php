<?php

declare(strict_types=1);

namespace App\Services;

use Database;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;
use PDO;

class ImportService
{
    /**
     * Process an uploaded Excel/CSV staffing file.
     *
     * @return array{success: bool, import_id: int, errors: array, stats: array}
     */
    public static function processExcel(
        string $filePath,
        int $campaignId,
        int $periodoMes,
        int $periodoAnio,
        int $userId
    ): array {
        $pdo = Database::getConnection();

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $periodoMes, $periodoAnio);
        $requirements = [];
        $totalAsesorHora = 0;

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Detectar formato: buscar la fila de encabezado y las filas de horas dinámicamente
        $headerRow = null;
        $hourRows = []; // [fila_excel => hora_int]

        $maxRow = min($sheet->getHighestRow(), 50);

        // Paso 1: Encontrar la fila de encabezado ("Horas ACD" o similar)
        for ($r = 1; $r <= $maxRow; $r++) {
            $cellA = trim((string)$sheet->getCell('A' . $r)->getFormattedValue());
            if ($cellA !== '' && stripos($cellA, 'horas') !== false) {
                $headerRow = $r;
                break;
            }
        }

        if ($headerRow === null) {
            throw new RuntimeException('Formato no reconocido: no se encontro una celda con "Horas" en la columna A.');
        }

        // Paso 2: Recorrer filas después del header para detectar horas
        for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
            $cell = $sheet->getCell('A' . $r);
            $formatted = trim((string)$cell->getFormattedValue());
            $raw = $cell->getCalculatedValue();

            if ($formatted === '' && ($raw === null || $raw === '')) continue;

            if (stripos($formatted, 'total') !== false) break;

            $hora = self::parseHourFromCell($formatted);
            if ($hora === null && $raw !== null) {
                $hora = self::parseHourFromCell(trim((string)$raw));
            }

            if ($hora !== null && $hora >= 0 && $hora <= 23) {
                $hourRows[$r] = $hora;
            }
        }

        if (empty($hourRows)) {
            throw new RuntimeException('No se encontraron filas con horas validas en la columna A.');
        }

        // Paso 3: Leer los datos de cada fila de hora detectada
        foreach ($hourRows as $row => $hour) {
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $column = $day + 1;
                $cellRef = Coordinate::stringFromColumnIndex($column) . $row;
                $rawValue = $sheet->getCell($cellRef)->getCalculatedValue();
                $asesores = self::normalizeRequiredAdvisors($rawValue);

                $requirements[] = [
                    'fecha' => sprintf('%04d-%02d-%02d', $periodoAnio, $periodoMes, $day),
                    'hora' => $hour,
                    'asesores_requeridos' => $asesores,
                ];
                $totalAsesorHora += $asesores;
            }
        }

        $fechaInicio = sprintf('%04d-%02d-01', $periodoAnio, $periodoMes);
        $fechaFin = sprintf('%04d-%02d-%02d', $periodoAnio, $periodoMes, $daysInMonth);
        $storedName = basename($filePath);

        $pdo->beginTransaction();

        $stmtImport = $pdo->prepare("
            INSERT INTO staffing_imports (
                campaign_id, periodo_anio, periodo_mes, archivo_nombre,
                importado_por, total_asesor_hora, estado, errores_json
            ) VALUES (
                :campaign_id, :periodo_anio, :periodo_mes, :archivo_nombre,
                :importado_por, :total_asesor_hora, 'procesado', NULL
            )
            ON CONFLICT (campaign_id, periodo_anio, periodo_mes)
            DO UPDATE SET
                archivo_nombre = EXCLUDED.archivo_nombre,
                importado_por = EXCLUDED.importado_por,
                total_asesor_hora = EXCLUDED.total_asesor_hora,
                estado = 'procesado',
                errores_json = NULL,
                imported_at = NOW()
            RETURNING id
        ");
        $stmtImport->execute([
            ':campaign_id' => $campaignId,
            ':periodo_anio' => $periodoAnio,
            ':periodo_mes' => $periodoMes,
            ':archivo_nombre' => $storedName,
            ':importado_por' => $userId,
            ':total_asesor_hora' => $totalAsesorHora,
        ]);
        $importId = (int)$stmtImport->fetchColumn();

        $stmtDelete = $pdo->prepare("
            DELETE FROM staffing_requirements
            WHERE campaign_id = :campaign_id
              AND fecha BETWEEN :fecha_inicio AND :fecha_fin
        ");
        $stmtDelete->execute([
            ':campaign_id' => $campaignId,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin,
        ]);

        $stmtInsert = $pdo->prepare("
            INSERT INTO staffing_requirements (
                import_id, campaign_id, fecha, hora, asesores_requeridos
            ) VALUES (
                :import_id, :campaign_id, :fecha, :hora, :asesores_requeridos
            )
            ON CONFLICT (campaign_id, fecha, hora)
            DO UPDATE SET
                import_id = EXCLUDED.import_id,
                asesores_requeridos = EXCLUDED.asesores_requeridos
        ");

        foreach ($requirements as $requirement) {
            $stmtInsert->execute([
                ':import_id' => $importId,
                ':campaign_id' => $campaignId,
                ':fecha' => $requirement['fecha'],
                ':hora' => $requirement['hora'],
                ':asesores_requeridos' => $requirement['asesores_requeridos'],
            ]);
        }

        // Sync schedule header
        $scheduleAction = ScheduleService::syncMonthlyScheduleHeader(
            $pdo, $campaignId, $periodoAnio, $periodoMes, $fechaInicio, $fechaFin, $userId
        );

        // Auto-generate assignments if possible
        $generatedAssignments = 0;
        $scheduleRow = ScheduleService::findMonthlySchedule($pdo, $campaignId, $fechaInicio);
        if ($scheduleRow) {
            $existingAssignments = ScheduleService::countScheduleAssignments($pdo, (int)$scheduleRow['id']);
            $isLockedStatus = in_array($scheduleRow['status'], ['aprobado', 'enviado'], true);
            $canRegenerate = !$isLockedStatus || ($isLockedStatus && $existingAssignments === 0);

            if ($canRegenerate) {
                $builder = new ScheduleBuilder($pdo);
                $generatedAssignments = $builder->build(
                    (int)$scheduleRow['id'],
                    $campaignId,
                    $fechaInicio,
                    $fechaFin
                );
            }
        }

        $pdo->commit();

        // Regenerar campañas fuente si hay asesores compartidos
        $regeneratedCampaigns = self::regenerarCampañasFuente(
            $pdo, $campaignId, $fechaInicio, $fechaFin, $userId
        );

        return [
            'success' => true,
            'import_id' => $importId,
            'errors' => [],
            'stats' => [
                'requirements_count' => count($requirements),
                'schedule_action' => $scheduleAction,
                'generated_assignments' => $generatedAssignments,
                'regenerated_campaigns' => $regeneratedCampaigns,
            ],
        ];
    }

    /**
     * Record a failed import attempt in the database.
     */
    public static function recordFailure(
        int $campaignId,
        int $periodoAnio,
        int $periodoMes,
        string $storedName,
        int $userId,
        string $errorMessage,
        string $originalName
    ): void {
        $pdo = Database::getConnection();
        $errorJson = json_encode([
            'message' => $errorMessage,
            'file' => $originalName,
            'at' => date('c'),
        ], JSON_UNESCAPED_UNICODE);

        try {
            $stmtError = $pdo->prepare("
                INSERT INTO staffing_imports (
                    campaign_id, periodo_anio, periodo_mes, archivo_nombre,
                    importado_por, total_asesor_hora, estado, errores_json
                ) VALUES (
                    :campaign_id, :periodo_anio, :periodo_mes, :archivo_nombre,
                    :importado_por, 0, 'error', CAST(:errores_json AS jsonb)
                )
                ON CONFLICT (campaign_id, periodo_anio, periodo_mes)
                DO UPDATE SET
                    archivo_nombre = EXCLUDED.archivo_nombre,
                    importado_por = EXCLUDED.importado_por,
                    total_asesor_hora = 0,
                    estado = 'error',
                    errores_json = EXCLUDED.errores_json,
                    imported_at = NOW()
            ");
            $stmtError->execute([
                ':campaign_id' => $campaignId,
                ':periodo_anio' => $periodoAnio,
                ':periodo_mes' => $periodoMes,
                ':archivo_nombre' => $storedName,
                ':importado_por' => $userId,
                ':errores_json' => $errorJson ?: '{}',
            ]);
        } catch (Throwable $dbError) {
            error_log('Error guardando detalle de importación: ' . $dbError->getMessage());
        }
    }

    private static function parseHourFromCell(string $cellValue): ?int
    {
        $cellValue = trim($cellValue);

        if (preg_match('/^(\d{1,2})\s*:\s*\d{2}\s*-/', $cellValue, $m)) {
            return (int)$m[1];
        }

        if (preg_match('/^(\d{1,2})\s*:\s*\d{2}$/', $cellValue, $m)) {
            return (int)$m[1];
        }

        if (is_numeric($cellValue)) {
            $floatVal = (float)$cellValue;
            if ($floatVal >= 0 && $floatVal < 1) {
                return min(23, (int)floor($floatVal * 24));
            }
            $intVal = (int)$floatVal;
            if ($intVal >= 0 && $intVal <= 23 && $floatVal == $intVal) {
                return $intVal;
            }
        }

        return null;
    }

    private static function normalizeRequiredAdvisors(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, (int)round((float)$value));
        }

        $normalized = str_replace([',', ' '], ['.', ''], trim((string)$value));
        if ($normalized === '' || !is_numeric($normalized)) {
            throw new RuntimeException('Se encontro un valor no numerico en el archivo.');
        }

        return max(0, (int)round((float)$normalized));
    }

    private static function regenerarCampañasFuente(
        PDO $pdo,
        int $targetCampaignId,
        string $fechaInicio,
        string $fechaFin,
        int $userId
    ): array {
        $stmt = $pdo->prepare("
            SELECT DISTINCT sa.source_campaign_id, c.nombre
            FROM shared_advisors sa
            JOIN campaigns c ON c.id = sa.source_campaign_id
            WHERE sa.target_campaign_id = :target_id AND sa.estado = 'activo' AND c.estado = 'activa'
        ");
        $stmt->execute([':target_id' => $targetCampaignId]);
        $sourceCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($sourceCampaigns)) return [];

        $regenerated = [];

        foreach ($sourceCampaigns as $sc) {
            $sourceCampaignId = (int)$sc['source_campaign_id'];

            $scheduleRow = ScheduleService::findMonthlySchedule($pdo, $sourceCampaignId, $fechaInicio);
            if (!$scheduleRow) continue;
            if ($scheduleRow['status'] !== 'borrador') continue;

            $scheduleId = (int)$scheduleRow['id'];

            $builder = new ScheduleBuilder($pdo);
            $builder->build($scheduleId, $sourceCampaignId, $fechaInicio, $fechaFin);

            $regenerated[] = $sc['nombre'];
        }

        return $regenerated;
    }
}
