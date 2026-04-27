<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Shared schedule utilities used by ScheduleController and ImportService.
 * Extracted to avoid code duplication.
 */
class ScheduleService
{
    public static function syncMonthlyScheduleHeader(
        PDO $pdo,
        int $campaignId,
        int $periodoAnio,
        int $periodoMes,
        string $fechaInicio,
        string $fechaFin,
        int $userId
    ): string {
        $stmtExisting = $pdo->prepare("
            SELECT id, status
            FROM schedules
            WHERE campaign_id = :campaign_id
              AND fecha_inicio = :fecha_inicio
              AND tipo = 'mensual'
            LIMIT 1
        ");
        $stmtExisting->execute([
            ':campaign_id' => $campaignId,
            ':fecha_inicio' => $fechaInicio,
        ]);
        $existing = $stmtExisting->fetch();

        if (!$existing) {
            $stmtInsertSchedule = $pdo->prepare("
                INSERT INTO schedules (
                    campaign_id, periodo_anio, periodo_mes, fecha_inicio, fecha_fin,
                    tipo, status, generado_por
                ) VALUES (
                    :campaign_id, :periodo_anio, :periodo_mes, :fecha_inicio, :fecha_fin,
                    'mensual', 'borrador', :generado_por
                )
            ");
            $stmtInsertSchedule->execute([
                ':campaign_id' => $campaignId,
                ':periodo_anio' => $periodoAnio,
                ':periodo_mes' => $periodoMes,
                ':fecha_inicio' => $fechaInicio,
                ':fecha_fin' => $fechaFin,
                ':generado_por' => $userId,
            ]);

            return 'creado';
        }

        if (in_array($existing['status'], ['aprobado', 'enviado'], true)) {
            return 'mantenido';
        }

        $stmtUpdateSchedule = $pdo->prepare("
            UPDATE schedules SET
                periodo_anio = :periodo_anio,
                periodo_mes = :periodo_mes,
                fecha_fin = :fecha_fin,
                tipo = 'mensual',
                status = 'borrador',
                generado_por = :generado_por,
                nota_rechazo = NULL
            WHERE id = :id
        ");
        $stmtUpdateSchedule->execute([
            ':periodo_anio' => $periodoAnio,
            ':periodo_mes' => $periodoMes,
            ':fecha_fin' => $fechaFin,
            ':generado_por' => $userId,
            ':id' => $existing['id'],
        ]);

        return 'actualizado';
    }

    public static function findMonthlySchedule(PDO $pdo, int $campaignId, string $fechaInicio): ?array
    {
        $stmt = $pdo->prepare("
            SELECT id, status
            FROM schedules
            WHERE campaign_id = :campaign_id
              AND fecha_inicio = :fecha_inicio
              AND tipo = 'mensual'
            LIMIT 1
        ");
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':fecha_inicio' => $fechaInicio,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function countScheduleAssignments(PDO $pdo, int $scheduleId): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM shift_assignments WHERE schedule_id = :schedule_id");
        $stmt->execute([':schedule_id' => $scheduleId]);
        return (int)$stmt->fetchColumn();
    }

    public static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 't', 'yes', 'y', 'on'], true);
    }

    public static function parseHourFromCell(string $cellValue): ?int
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
                return (int)round($floatVal * 24);
            }
            $intVal = (int)$floatVal;
            if ($intVal >= 0 && $intVal <= 23 && $floatVal == $intVal) {
                return $intVal;
            }
        }

        return null;
    }

    public static function normalizeRequiredAdvisors(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, (int)round((float)$value));
        }

        $normalized = str_replace([',', ' '], ['.', ''], trim((string)$value));
        if ($normalized === '' || !is_numeric($normalized)) {
            throw new \RuntimeException('Se encontro un valor no numerico en el archivo.');
        }

        return max(0, (int)round((float)$normalized));
    }
}
