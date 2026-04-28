<?php

declare(strict_types=1);

namespace App\Services;

use Database;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PDO;
use RuntimeException;
use Throwable;

class BreakImportService
{
    /**
     * Process an uploaded BREAK_POST.xlsx file (sheet "BREAK").
     *
     * The BREAK sheet has daily summary per advisor:
     *   A: USUARIO | B: AGENTES | C: TOTAL HORAS | D: HORARIO
     *   E: BREAK | F: BREAK USADO | G: BREAK DISPONIBLE
     *
     * Columns E-G contain Excel time fractions (day fractions).
     * Multiply by 1440 to get minutes.
     *
     * @return array{matched: int, unmatched: int, skipped: int, errors: array, unmatched_names: array}
     */
    public static function processExcel(
        string $filePath,
        int $campaignId,
        string $fecha,
        int $userId
    ): array {
        $pdo = Database::getConnection();

        $spreadsheet = IOFactory::load($filePath);

        // Find sheet "BREAK" by name (case-insensitive)
        $sheet = null;
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (mb_strtolower(trim($name)) === 'break') {
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }

        if ($sheet === null) {
            throw new RuntimeException('No se encontro la hoja "BREAK" en el archivo.');
        }

        $highestRow = $sheet->getHighestRow();

        // Load active advisors for this campaign
        $stmtAdvisors = $pdo->prepare("
            SELECT id, cedula, nombres, apellidos
            FROM advisors
            WHERE campaign_id = :cid AND estado = 'activo'
        ");
        $stmtAdvisors->execute([':cid' => $campaignId]);
        $allAdvisors = $stmtAdvisors->fetchAll(PDO::FETCH_ASSOC);

        // Build lookup maps for name matching
        $advisorByFullName = [];
        $advisorWords = []; // advisor_id => array of normalized words
        foreach ($allAdvisors as $adv) {
            // Primary: "apellidos nombres" (normalized)
            $fullAp = mb_strtolower(trim($adv['apellidos'] . ' ' . $adv['nombres']));
            $advisorByFullName[$fullAp] = $adv;

            // Also reversed: "nombres apellidos"
            $fullNa = mb_strtolower(trim($adv['nombres'] . ' ' . $adv['apellidos']));
            $advisorByFullName[$fullNa] = $adv;

            // Store individual words for fuzzy matching
            $words = preg_split('/\s+/', mb_strtolower(trim($adv['apellidos'] . ' ' . $adv['nombres'])));
            $words = array_filter($words, fn(string $w) => mb_strlen($w) > 1);
            $advisorWords[$adv['id']] = [
                'advisor' => $adv,
                'words'   => $words,
            ];
        }

        // Create/update break_imports record (UPSERT by campaign_id + fecha)
        $storedName = basename($filePath);

        $pdo->beginTransaction();

        try {
            $stmtImport = $pdo->prepare("
                INSERT INTO break_imports (
                    campaign_id, fecha, periodo_anio, periodo_mes,
                    archivo_nombre, importado_por, estado
                ) VALUES (
                    :campaign_id, :fecha, :periodo_anio, :periodo_mes,
                    :archivo_nombre, :importado_por, 'pendiente'
                )
                ON CONFLICT (campaign_id, fecha)
                DO UPDATE SET
                    archivo_nombre = EXCLUDED.archivo_nombre,
                    importado_por = EXCLUDED.importado_por,
                    estado = 'pendiente',
                    errores_json = NULL,
                    imported_at = NOW()
                RETURNING id
            ");

            $fechaParts = explode('-', $fecha);
            $stmtImport->execute([
                ':campaign_id'    => $campaignId,
                ':fecha'          => $fecha,
                ':periodo_anio'   => (int)$fechaParts[0],
                ':periodo_mes'    => (int)$fechaParts[1],
                ':archivo_nombre' => $storedName,
                ':importado_por'  => $userId,
            ]);
            $importId = (int)$stmtImport->fetchColumn();

            // Delete existing break_daily for this campaign + fecha (clean re-import)
            $stmtDelete = $pdo->prepare("
                DELETE FROM break_daily
                WHERE campaign_id = :cid AND fecha = :fecha
            ");
            $stmtDelete->execute([':cid' => $campaignId, ':fecha' => $fecha]);

            // Prepare upsert for break_daily
            $stmtInsert = $pdo->prepare("
                INSERT INTO break_daily (
                    import_id, advisor_id, campaign_id, fecha,
                    usuario_excel, horas_trabajadas, horario_texto,
                    break_asignado_min, break_usado_min,
                    break_disponible_min, exceso_min
                ) VALUES (
                    :import_id, :advisor_id, :campaign_id, :fecha,
                    :usuario_excel, :horas_trabajadas, :horario_texto,
                    :break_asignado_min, :break_usado_min,
                    :break_disponible_min, :exceso_min
                )
                ON CONFLICT (advisor_id, fecha)
                DO UPDATE SET
                    import_id = EXCLUDED.import_id,
                    campaign_id = EXCLUDED.campaign_id,
                    usuario_excel = EXCLUDED.usuario_excel,
                    horas_trabajadas = EXCLUDED.horas_trabajadas,
                    horario_texto = EXCLUDED.horario_texto,
                    break_asignado_min = EXCLUDED.break_asignado_min,
                    break_usado_min = EXCLUDED.break_usado_min,
                    break_disponible_min = EXCLUDED.break_disponible_min,
                    exceso_min = EXCLUDED.exceso_min
            ");

            $matched = 0;
            $unmatched = 0;
            $skipped = 0;
            $errors = [];
            $unmatchedNames = [];

            // Process rows starting from row 2 (row 1 = headers)
            for ($row = 2; $row <= $highestRow; $row++) {
                $usuario    = trim((string)$sheet->getCellByColumnAndRow(1, $row)->getValue());
                $nombre     = trim((string)$sheet->getCellByColumnAndRow(2, $row)->getValue());
                $totalHoras = $sheet->getCellByColumnAndRow(3, $row)->getValue();
                $horario    = trim((string)$sheet->getCellByColumnAndRow(4, $row)->getValue());

                // Skip completely empty rows
                if ($usuario === '' && $nombre === '') {
                    continue;
                }

                // Parse total horas
                $horasNum = is_numeric($totalHoras) ? (int)round((float)$totalHoras) : 0;

                // Skip day-off advisors: TOTAL HORAS = 0 or HORARIO = "LIBRE"/"Libre"
                if ($horasNum === 0 || mb_strtolower(trim($horario)) === 'libre') {
                    $skipped++;
                    continue;
                }

                // Read break columns (Excel time fractions)
                $rawBreakAsignado  = $sheet->getCellByColumnAndRow(5, $row)->getValue();
                $rawBreakUsado     = $sheet->getCellByColumnAndRow(6, $row)->getValue();
                $rawBreakDisponible = $sheet->getCellByColumnAndRow(7, $row)->getValue();

                // Convert from day fraction to minutes
                $breakAsignadoMin  = self::timeFractionToMinutes($rawBreakAsignado);
                $breakUsadoMin     = self::timeFractionToMinutes($rawBreakUsado);
                $breakDisponibleMin = self::timeFractionToMinutes($rawBreakDisponible);

                // Calculate exceso: used - assigned (positive = over-break)
                $excesoMin = round($breakUsadoMin - $breakAsignadoMin, 2);

                // Match advisor by name
                $advisor = self::matchAdvisor(
                    $nombre,
                    $advisorByFullName,
                    $advisorWords
                );

                if ($advisor === null) {
                    $unmatched++;
                    $unmatchedNames[] = [
                        'row'     => $row,
                        'usuario' => $usuario,
                        'nombre'  => $nombre,
                    ];
                    continue;
                }

                $matched++;

                try {
                    $stmtInsert->execute([
                        ':import_id'          => $importId,
                        ':advisor_id'         => (int)$advisor['id'],
                        ':campaign_id'        => $campaignId,
                        ':fecha'              => $fecha,
                        ':usuario_excel'      => $usuario,
                        ':horas_trabajadas'   => $horasNum,
                        ':horario_texto'      => $horario,
                        ':break_asignado_min' => $breakAsignadoMin,
                        ':break_usado_min'    => $breakUsadoMin,
                        ':break_disponible_min' => $breakDisponibleMin,
                        ':exceso_min'         => $excesoMin,
                    ]);
                } catch (Throwable $e) {
                    $errors[] = [
                        'row'     => $row,
                        'usuario' => $usuario,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            // Update break_imports with totals
            $stmtUpdate = $pdo->prepare("
                UPDATE break_imports SET
                    total_registros = :total,
                    asesores_matched = :matched,
                    asesores_unmatched = :unmatched,
                    estado = 'procesado',
                    errores_json = :errores
                WHERE id = :id
            ");
            $erroresJson = !empty($errors) ? json_encode($errors, JSON_UNESCAPED_UNICODE) : null;
            $stmtUpdate->execute([
                ':total'     => $matched + $unmatched,
                ':matched'   => $matched,
                ':unmatched' => $unmatched,
                ':errores'   => $erroresJson,
                ':id'        => $importId,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'matched'         => $matched,
            'unmatched'       => $unmatched,
            'skipped'         => $skipped,
            'errors'          => $errors,
            'unmatched_names' => $unmatchedNames,
        ];
    }

    /**
     * Convert an Excel time fraction to minutes.
     *
     * Excel stores time as a fraction of a day (0.0 = midnight, 1.0 = 24h).
     * Values like "12:50:00 AM" (50 min) are stored as ~0.034722.
     * Multiply by 1440 (minutes/day) to get actual minutes.
     *
     * Also handles string values like "Libre" (return 0).
     */
    private static function timeFractionToMinutes(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        // Handle non-numeric strings like "Libre"
        if (!is_numeric($value)) {
            return 0.0;
        }

        $fraction = (float)$value;

        // If the value is already clearly in minutes (> 2 means > 48 hours as fraction)
        // This shouldn't happen with proper Excel time values, but guard against it
        if ($fraction >= 1.0) {
            // Could be a raw minute value already (e.g., someone entered 50 instead of time)
            // Excel time fractions are always < 1.0 for times under 24h
            // For safety, if > 1 treat as minutes directly
            return round($fraction, 2);
        }

        return round($fraction * 1440, 2);
    }

    /**
     * Match an Excel name (e.g., "ALENCASTRO DELGADO BRIGITTE SCARLET")
     * against the advisor lookup maps.
     *
     * Strategy:
     * 1. Exact match on normalized full name
     * 2. Fuzzy match: all words of the advisor appear in the Excel name
     */
    private static function matchAdvisor(
        string $excelName,
        array $advisorByFullName,
        array $advisorWords
    ): ?array {
        $normalized = mb_strtolower(trim($excelName));

        if ($normalized === '') {
            return null;
        }

        // 1. Exact match
        if (isset($advisorByFullName[$normalized])) {
            return $advisorByFullName[$normalized];
        }

        // 2. Fuzzy: all words of an advisor's name appear in the Excel name
        $excelWords = preg_split('/\s+/', $normalized);
        $excelWords = array_filter($excelWords, fn(string $w) => mb_strlen($w) > 1);

        $bestMatch = null;
        $bestScore = 0;

        foreach ($advisorWords as $entry) {
            $advWords = $entry['words'];
            if (empty($advWords)) {
                continue;
            }

            // Check how many advisor words appear in Excel name
            $matchCount = 0;
            foreach ($advWords as $word) {
                if (in_array($word, $excelWords, true)) {
                    $matchCount++;
                }
            }

            // All advisor words must appear in Excel name
            if ($matchCount === count($advWords) && $matchCount > $bestScore) {
                $bestScore = $matchCount;
                $bestMatch = $entry['advisor'];
            }
        }

        return $bestMatch;
    }
}
