<?php

declare(strict_types=1);

namespace App\Services;

use Database;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PDO;
use RuntimeException;
use Throwable;

class BreakImportService
{
    /**
     * Process an uploaded BREAK_POST.xlsx file (sheet "EXCESOS BREAK").
     *
     * @return array{matched: int, unmatched: int, rows_processed: int, dates_found: int, errors: array, unmatched_names: array}
     */
    public static function processExcel(
        string $filePath,
        int $campaignId,
        int $periodoMes,
        int $periodoAnio,
        int $userId
    ): array {
        $pdo = Database::getConnection();

        $spreadsheet = IOFactory::load($filePath);

        // Find sheet "EXCESOS BREAK" by name (case-insensitive)
        $sheet = null;
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (mb_strtolower($name) === 'excesos break') {
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }

        if ($sheet === null) {
            throw new RuntimeException('No se encontro la hoja "EXCESOS BREAK" en el archivo.');
        }

        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestDataColumn();
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        // Step 1: Detect date columns from row 1 (index 0)
        // Scan columns starting from E (index 4) for numeric values > 40000 (Excel serial dates)
        $dateColumns = []; // [col_index => 'Y-m-d']

        for ($col = 4; $col < $highestColIndex; $col++) {
            $cellValue = $sheet->getCellByColumnAndRow($col + 1, 1)->getValue();
            if ($cellValue !== null && is_numeric($cellValue) && (float)$cellValue > 40000) {
                $dateObj = ExcelDate::excelToDateTimeObject((float)$cellValue);
                $dateColumns[$col] = $dateObj->format('Y-m-d');
            }
        }

        if (empty($dateColumns)) {
            throw new RuntimeException('No se encontraron fechas validas en la fila 1 del archivo.');
        }

        // Step 2: Group dates into 4-column sets starting from column 4
        // Each date occupies columns: col+0=HT, col+1=BK NORMAL, col+2=BREAK ICBM, col+3=EXCESO
        $dateGroups = []; // [['date' => 'Y-m-d', 'col_ht' => int, 'col_bk' => int, 'col_icbm' => int, 'col_exceso' => int]]
        foreach ($dateColumns as $colIndex => $dateStr) {
            $dateGroups[] = [
                'date'       => $dateStr,
                'col_ht'     => $colIndex,
                'col_bk'     => $colIndex + 1,
                'col_icbm'   => $colIndex + 2,
                'col_exceso' => $colIndex + 3,
            ];
        }

        // Step 3: Load advisors for matching
        $stmtAdvisors = $pdo->prepare("
            SELECT id, cedula, nombres, apellidos, campaign_id
            FROM advisors
            WHERE estado = 'activo'
        ");
        $stmtAdvisors->execute();
        $allAdvisors = $stmtAdvisors->fetchAll(PDO::FETCH_ASSOC);

        $lookupByCedula = [];
        $lookupByName = [];
        foreach ($allAdvisors as $adv) {
            if (!empty($adv['cedula'])) {
                $lookupByCedula[mb_strtolower(trim($adv['cedula']))] = $adv;
            }
            $fullName = mb_strtolower(trim($adv['nombres'] . ' ' . $adv['apellidos']));
            $lookupByName[$fullName] = $adv;
        }

        // Step 4: Create/update break_imports record (UPSERT)
        $storedName = basename($filePath);

        $pdo->beginTransaction();

        try {
            $stmtImport = $pdo->prepare("
                INSERT INTO break_imports (
                    campaign_id, periodo_anio, periodo_mes, archivo_nombre,
                    importado_por, estado
                ) VALUES (
                    :campaign_id, :periodo_anio, :periodo_mes, :archivo_nombre,
                    :importado_por, 'pendiente'
                )
                ON CONFLICT (campaign_id, periodo_anio, periodo_mes)
                DO UPDATE SET
                    archivo_nombre = EXCLUDED.archivo_nombre,
                    importado_por = EXCLUDED.importado_por,
                    estado = 'pendiente',
                    errores_json = NULL,
                    imported_at = NOW()
                RETURNING id
            ");
            $stmtImport->execute([
                ':campaign_id'  => $campaignId,
                ':periodo_anio' => $periodoAnio,
                ':periodo_mes'  => $periodoMes,
                ':archivo_nombre' => $storedName,
                ':importado_por'  => $userId,
            ]);
            $importId = (int)$stmtImport->fetchColumn();

            // Step 5: Delete existing break_snapshots for this import (clean re-import)
            $stmtDelete = $pdo->prepare("DELETE FROM break_snapshots WHERE import_id = :import_id");
            $stmtDelete->execute([':import_id' => $importId]);

            // Prepare insert for break_snapshots
            $stmtInsert = $pdo->prepare("
                INSERT INTO break_snapshots (
                    import_id, advisor_id, campaign_id, fecha,
                    horas_trabajadas, bk_normal_minutes, break_icbm_seconds,
                    exceso_minutes, usuario_excel
                ) VALUES (
                    :import_id, :advisor_id, :campaign_id, :fecha,
                    :horas_trabajadas, :bk_normal_minutes, :break_icbm_seconds,
                    :exceso_minutes, :usuario_excel
                )
                ON CONFLICT (advisor_id, fecha)
                DO UPDATE SET
                    import_id = EXCLUDED.import_id,
                    campaign_id = EXCLUDED.campaign_id,
                    horas_trabajadas = EXCLUDED.horas_trabajadas,
                    bk_normal_minutes = EXCLUDED.bk_normal_minutes,
                    break_icbm_seconds = EXCLUDED.break_icbm_seconds,
                    exceso_minutes = EXCLUDED.exceso_minutes,
                    usuario_excel = EXCLUDED.usuario_excel
            ");

            $matched = 0;
            $unmatched = 0;
            $rowsProcessed = 0;
            $errors = [];
            $unmatchedNames = [];

            // Step 6: Process advisor rows (starting from row 3, index 2 in 0-based = row 3 in 1-based)
            for ($row = 3; $row <= $highestRow; $row++) {
                $usuario = trim((string)$sheet->getCellByColumnAndRow(1, $row)->getValue());
                $nombre  = trim((string)$sheet->getCellByColumnAndRow(2, $row)->getValue());
                $cedula  = trim((string)$sheet->getCellByColumnAndRow(3, $row)->getValue());

                // Skip empty rows
                if ($usuario === '' && $nombre === '' && $cedula === '') {
                    continue;
                }

                $rowsProcessed++;

                // Match advisor: by cedula first, then by name
                $advisor = null;
                if ($cedula !== '') {
                    $cedulaKey = mb_strtolower($cedula);
                    if (isset($lookupByCedula[$cedulaKey])) {
                        $advisor = $lookupByCedula[$cedulaKey];
                    }
                }

                if ($advisor === null && $nombre !== '') {
                    $nameKey = mb_strtolower($nombre);
                    if (isset($lookupByName[$nameKey])) {
                        $advisor = $lookupByName[$nameKey];
                    }
                }

                if ($advisor === null) {
                    $unmatched++;
                    $unmatchedNames[] = [
                        'row'     => $row,
                        'usuario' => $usuario,
                        'nombre'  => $nombre,
                        'cedula'  => $cedula,
                    ];
                    continue;
                }

                $matched++;
                $advisorId = (int)$advisor['id'];

                // Process each date group
                foreach ($dateGroups as $group) {
                    $rawHt     = $sheet->getCellByColumnAndRow($group['col_ht'] + 1, $row)->getValue();
                    $rawBk     = $sheet->getCellByColumnAndRow($group['col_bk'] + 1, $row)->getValue();
                    $rawIcbm   = $sheet->getCellByColumnAndRow($group['col_icbm'] + 1, $row)->getValue();
                    $rawExceso = $sheet->getCellByColumnAndRow($group['col_exceso'] + 1, $row)->getValue();

                    // Skip dates with no data (all zeros/nulls)
                    if (self::isEmptyValue($rawHt) && self::isEmptyValue($rawBk)
                        && self::isEmptyValue($rawIcbm) && self::isEmptyValue($rawExceso)) {
                        continue;
                    }

                    // Convert values
                    $ht = self::parseHorasTrabajadas($rawHt);
                    $bkNormalMinutes = self::fractionToMinutes($rawBk);
                    $icbmSeconds = self::fractionToSeconds($rawIcbm);
                    $excesoMinutes = self::fractionToMinutes($rawExceso);

                    try {
                        $stmtInsert->execute([
                            ':import_id'          => $importId,
                            ':advisor_id'         => $advisorId,
                            ':campaign_id'        => $campaignId,
                            ':fecha'              => $group['date'],
                            ':horas_trabajadas'   => $ht,
                            ':bk_normal_minutes'  => $bkNormalMinutes,
                            ':break_icbm_seconds' => $icbmSeconds,
                            ':exceso_minutes'     => $excesoMinutes,
                            ':usuario_excel'      => $usuario,
                        ]);
                    } catch (Throwable $e) {
                        $errors[] = [
                            'row'     => $row,
                            'date'    => $group['date'],
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            }

            // Step 7: Update break_imports with totals
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
                ':total'     => $rowsProcessed,
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
            'rows_processed'  => $rowsProcessed,
            'dates_found'     => count($dateGroups),
            'errors'          => $errors,
            'unmatched_names' => $unmatchedNames,
        ];
    }

    /**
     * Check if a cell value is effectively empty (null, empty string, or zero).
     */
    private static function isEmptyValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (is_numeric($value) && (float)$value == 0) {
            return true;
        }
        return false;
    }

    /**
     * Parse HT (hours worked). Can be an integer or a day fraction (×24).
     */
    private static function parseHorasTrabajadas(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        $num = (float)$value;
        // If value is a small fraction (< 1), it's likely a day fraction
        if ($num > 0 && $num < 1) {
            return round($num * 24, 2);
        }
        return round($num, 2);
    }

    /**
     * Convert day fraction to minutes (×1440).
     */
    private static function fractionToMinutes(mixed $value): float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return 0.0;
        }
        return round((float)$value * 1440, 2);
    }

    /**
     * Convert day fraction to seconds (×86400).
     */
    private static function fractionToSeconds(mixed $value): float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return 0.0;
        }
        return round((float)$value * 86400, 2);
    }
}
