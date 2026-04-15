<?php

declare(strict_types=1);

namespace App\Services;

use Database;
use Throwable;

class AttendanceService
{
    private const VALID_STATUSES = [
        'presente', 'ausente', 'tardanza', 'salida_anticipada', 'licencia_medica', 'maternidad'
    ];

    /**
     * Save attendance records for a schedule.
     *
     * @param int   $scheduleId
     * @param array $records    Array of {advisor_id, fecha, status, notas}
     * @param int   $userId     ID of user recording attendance
     * @return array{success: bool, saved: int, error?: string}
     */
    public static function saveAttendance(int $scheduleId, array $records, int $userId): array
    {
        $pdo = Database::getConnection();

        $pdo->beginTransaction();
        try {
            $stmtUpsert = $pdo->prepare("
                INSERT INTO attendance (advisor_id, fecha, status, notas, registrado_por)
                VALUES (:advisor_id, :fecha, :status, :notas, :registrado_por)
                ON CONFLICT (advisor_id, fecha)
                DO UPDATE SET status = EXCLUDED.status,
                              notas = EXCLUDED.notas,
                              registrado_por = EXCLUDED.registrado_por
            ");

            $saved = 0;
            foreach ($records as $rec) {
                $advisorId = (int)($rec['advisor_id'] ?? 0);
                $fecha = (string)($rec['fecha'] ?? '');
                $status = (string)($rec['status'] ?? 'presente');
                $notas = trim((string)($rec['notas'] ?? ''));

                if ($advisorId <= 0 || $fecha === '') continue;
                if (!in_array($status, self::VALID_STATUSES, true)) $status = 'presente';

                // No permitir registrar asistencia en fechas futuras
                if ($fecha > date('Y-m-d')) continue;

                $stmtUpsert->execute([
                    ':advisor_id' => $advisorId,
                    ':fecha' => $fecha,
                    ':status' => $status,
                    ':notas' => $notas ?: null,
                    ':registrado_por' => $userId,
                ]);
                $saved++;
            }

            $pdo->commit();

            AuditService::log(
                'attendance.update',
                'attendance',
                $scheduleId,
                [],
                ['saved' => $saved, 'records_count' => count($records)]
            );

            return ['success' => true, 'saved' => $saved];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Error guardando asistencia: ' . $e->getMessage());
            return ['success' => false, 'saved' => 0, 'error' => 'Error al guardar'];
        }
    }

    /**
     * Toggle check-in for an advisor on a specific date.
     *
     * @return array{success: bool, checked?: bool, time?: string, error?: string}
     */
    public static function toggleCheckin(int $scheduleId, int $advisorId, string $fecha): array
    {
        $pdo = Database::getConnection();

        // Validate schedule exists and is approved
        $stmt = $pdo->prepare("SELECT id FROM schedules WHERE id = :id AND status = 'aprobado'");
        $stmt->execute([':id' => $scheduleId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Horario no encontrado o no aprobado'];
        }

        if ($advisorId <= 0 || $fecha === '') {
            return ['success' => false, 'error' => 'Datos invalidos'];
        }

        if ($fecha > date('Y-m-d')) {
            return ['success' => false, 'error' => 'No se puede hacer check-in en fechas futuras'];
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM advisor_checkins WHERE advisor_id = :aid AND schedule_id = :sid AND fecha = :fecha");
            $stmt->execute([':aid' => $advisorId, ':sid' => $scheduleId, ':fecha' => $fecha]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $pdo->prepare("DELETE FROM advisor_checkins WHERE id = :id");
                $stmt->execute([':id' => $existing['id']]);
                return ['success' => true, 'checked' => false];
            }

            $stmt = $pdo->prepare("
                INSERT INTO advisor_checkins (advisor_id, schedule_id, fecha)
                VALUES (:aid, :sid, :fecha)
            ");
            $stmt->execute([':aid' => $advisorId, ':sid' => $scheduleId, ':fecha' => $fecha]);
            return ['success' => true, 'checked' => true, 'time' => date('H:i')];
        } catch (Throwable $e) {
            error_log('Error en check-in: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al registrar check-in'];
        }
    }
}
