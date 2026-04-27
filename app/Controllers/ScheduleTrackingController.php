<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use PDO;
use Throwable;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';
require_once APP_PATH . '/Services/AttendanceService.php';
require_once APP_PATH . '/Services/AuditService.php';
require_once APP_PATH . '/Services/ScheduleService.php';
require_once APP_PATH . '/Services/NotificationService.php';

class ScheduleTrackingController
{
    /**
     * Vista de seguimiento diario del horario aprobado.
     * Permite al supervisor confirmar cumplimiento por asesor/dia.
     */
    public function dailyTracking(int $id): void
    {
        AuthService::requirePermission('schedules.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT s.*, c.nombre as campaign_nombre, c.supervisor_id, c.id as campaign_id
            FROM schedules s
            JOIN campaigns c ON c.id = s.campaign_id
            WHERE s.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            header('Location: ' . BASE_URL . '/schedules');
            exit;
        }

        // Verificar permisos
        if (!AuthService::canManageAllCampaigns($user)) {
            $role = $user['rol'] ?? '';
            if ($role === 'supervisor' && (int)$schedule['supervisor_id'] !== (int)$user['id']) {
                header('Location: ' . BASE_URL . '/schedules');
                exit;
            }
        }

        $today = date('Y-m-d');

        // Asignaciones del horario
        $stmt = $pdo->prepare("
            SELECT sa.advisor_id, sa.fecha, sa.hora, sa.tipo,
                   a.nombres, a.apellidos
            FROM shift_assignments sa
            JOIN advisors a ON a.id = sa.advisor_id
            WHERE sa.schedule_id = :schedule_id
            ORDER BY sa.fecha, a.apellidos, sa.hora
        ");
        $stmt->execute([':schedule_id' => $id]);
        $assignments = $stmt->fetchAll();

        // Registros de asistencia existentes
        $stmt = $pdo->prepare("
            SELECT att.advisor_id, att.fecha, att.status, att.notas,
                   att.hora_real_inicio, att.hora_real_fin, att.horas_trabajadas
            FROM attendance att
            JOIN shift_assignments sa ON sa.advisor_id = att.advisor_id
                AND sa.fecha = att.fecha
                AND sa.schedule_id = :schedule_id
            GROUP BY att.id, att.advisor_id, att.fecha, att.status, att.notas,
                     att.hora_real_inicio, att.hora_real_fin, att.horas_trabajadas
        ");
        $stmt->execute([':schedule_id' => $id]);
        $attendanceRows = $stmt->fetchAll();

        $attendanceMap = [];
        foreach ($attendanceRows as $row) {
            $attendanceMap[$row['advisor_id'] . ':' . $row['fecha']] = $row;
        }

        // Construir estructura por fecha => advisors
        $dates = [];
        $advisorsMap = [];
        $dailyData = []; // [fecha][advisor_id] => { hours, attendance }

        foreach ($assignments as $a) {
            $fecha = (string)$a['fecha'];
            $advId = (int)$a['advisor_id'];

            if (!in_array($fecha, $dates, true)) {
                $dates[] = $fecha;
            }

            if (!isset($advisorsMap[$advId])) {
                $advisorsMap[$advId] = [
                    'id' => $advId,
                    'name' => trim($a['apellidos'] . ' ' . $a['nombres']),
                ];
            }

            if (!isset($dailyData[$fecha][$advId])) {
                $dailyData[$fecha][$advId] = [
                    'hours' => 0,
                    'break_hours' => 0,
                ];
            }
            if ($a['tipo'] === 'break') {
                $dailyData[$fecha][$advId]['break_hours'] += 0.5;
            } else {
                $dailyData[$fecha][$advId]['hours']++;
            }
        }

        sort($dates);
        uasort($advisorsMap, static fn($a, $b) => strcasecmp($a['name'], $b['name']));

        // Cargar check-ins de asesores
        $stmt = $pdo->prepare("
            SELECT advisor_id, fecha, checkin_at
            FROM advisor_checkins
            WHERE schedule_id = :schedule_id
        ");
        $stmt->execute([':schedule_id' => $id]);
        $checkinRows = $stmt->fetchAll();

        $checkinMap = []; // "advisorId:fecha" => checkin_at
        foreach ($checkinRows as $row) {
            $checkinMap[$row['advisor_id'] . ':' . $row['fecha']] = $row['checkin_at'];
        }

        $userRole = $user['rol'] ?? '';
        $canBypassCheckin = in_array($userRole, ['admin', 'gerente', 'coordinador'], true);

        $pageTitle = 'Seguimiento Diario';
        $currentPage = 'schedules';

        include APP_PATH . '/Views/schedules/tracking.php';
    }

    /**
     * API: Toggle check-in de un asesor para un dia.
     */
    public function toggleCheckin(int $scheduleId): void
    {
        AuthService::requireAnyPermission(['schedules.view', 'schedules.view_own']);

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $advisorId = (int)($input['advisor_id'] ?? 0);
        $fecha = (string)($input['fecha'] ?? '');

        // Asesores solo pueden hacer check-in de sí mismos
        $user = $_SESSION['user'];
        if (($user['rol'] ?? '') === 'asesor') {
            $pdo = \Database::getConnection();
            $ownAdvisor = $this->resolveAdvisorByUser($pdo, $user);
            if (!$ownAdvisor || (int)$ownAdvisor['id'] !== $advisorId) {
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit;
            }
        }

        $result = \App\Services\AttendanceService::toggleCheckin($scheduleId, $advisorId, $fecha);

        echo json_encode($result);
        exit;
    }

    /**
     * API: Registrar/actualizar asistencia de un asesor en un dia.
     */
    public function saveAttendance(int $scheduleId): void
    {
        AuthService::requirePermission('schedules.edit');

        header('Content-Type: application/json');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT s.*, c.supervisor_id
            FROM schedules s
            JOIN campaigns c ON c.id = s.campaign_id
            WHERE s.id = :id
        ");
        $stmt->execute([':id' => $scheduleId]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            echo json_encode(['success' => false, 'error' => 'Horario no encontrado']);
            exit;
        }

        if (!AuthService::canManageAllCampaigns($user)) {
            if ((int)$schedule['supervisor_id'] !== (int)$user['id']) {
                echo json_encode(['success' => false, 'error' => 'Sin permisos']);
                exit;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['records'])) {
            echo json_encode(['success' => false, 'error' => 'Datos invalidos']);
            exit;
        }

        $result = \App\Services\AttendanceService::saveAttendance(
            $scheduleId, (array)$input['records'], (int)$user['id']
        );

        echo json_encode($result);
        exit;
    }

    public function updateAssignments(int $id): void
    {
        AuthService::requirePermission('schedules.edit');

        header('Content-Type: application/json');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Verificar que el horario existe y se puede editar
        $stmt = $pdo->prepare("
            SELECT s.*, c.supervisor_id, c.id as campaign_id
            FROM schedules s
            JOIN campaigns c ON c.id = s.campaign_id
            WHERE s.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            echo json_encode(['success' => false, 'error' => 'Horario no encontrado']);
            exit;
        }

        // Editable en borrador/rechazado (cualquier fecha) o aprobado (solo hoy y futuro)
        $isApproved = $schedule['status'] === 'aprobado';
        if (!in_array($schedule['status'], ['borrador', 'rechazado', 'aprobado'], true)) {
            echo json_encode(['success' => false, 'error' => 'Este horario no se puede editar (estado: ' . $schedule['status'] . ')']);
            exit;
        }

        // Verificar permisos
        if (!AuthService::canManageAllCampaigns($user)) {
            if ((int)$schedule['supervisor_id'] !== (int)$user['id']) {
                echo json_encode(['success' => false, 'error' => 'Sin permisos para editar este horario']);
                exit;
            }
        }

        // Leer body JSON
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['date']) || empty($input['changes'])) {
            echo json_encode(['success' => false, 'error' => 'Datos invalidos']);
            exit;
        }

        $date = (string)$input['date'];
        $changes = (array)$input['changes'];

        // Validar fecha dentro del rango del horario
        if ($date < $schedule['fecha_inicio'] || $date > $schedule['fecha_fin']) {
            echo json_encode(['success' => false, 'error' => 'Fecha fuera del rango del horario']);
            exit;
        }

        // Si el horario esta aprobado, solo permitir editar hoy y dias futuros
        if ($isApproved) {
            $today = date('Y-m-d');
            if ($date < $today) {
                echo json_encode(['success' => false, 'error' => 'No se pueden modificar dias pasados en un horario aprobado']);
                exit;
            }
        }

        $added = 0;
        $removed = 0;
        $breaks = 0;
        $activities = 0;
        $campaignId = (int)$schedule['campaign_id'];

        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO shift_assignments (
                    schedule_id, advisor_id, campaign_id, fecha, hora, tipo, es_extra
                ) VALUES (
                    :schedule_id, :advisor_id, :campaign_id, :fecha, :hora, :tipo, false
                )
                ON CONFLICT (advisor_id, fecha, hora) DO UPDATE SET tipo = EXCLUDED.tipo
            ");

            $stmtDelete = $pdo->prepare("
                DELETE FROM shift_assignments
                WHERE schedule_id = :schedule_id
                  AND advisor_id = :advisor_id
                  AND fecha = :fecha
                  AND hora = :hora
            ");

            // Para asignar actividades
            $stmtActivityUpsert = $pdo->prepare("
                INSERT INTO advisor_activity_assignments (activity_id, advisor_id, hora_inicio, hora_fin, dias_semana, activo)
                VALUES (:activity_id, :advisor_id, :hora_inicio, :hora_fin, :dias_semana, true)
                ON CONFLICT (advisor_id, activity_id) DO UPDATE SET
                    hora_inicio = LEAST(advisor_activity_assignments.hora_inicio, EXCLUDED.hora_inicio),
                    hora_fin = GREATEST(advisor_activity_assignments.hora_fin, EXCLUDED.hora_fin),
                    activo = true
            ");

            // Calcular dia de la semana para la actividad (0=Lun, 6=Dom)
            $dow = (int)date('N', strtotime($date)) - 1;

            foreach ($changes as $change) {
                $action = (string)($change['action'] ?? '');
                $advisorId = (int)($change['advisor_id'] ?? 0);
                $hour = (int)($change['hour'] ?? -1);
                $tipo = (string)($change['tipo'] ?? 'normal');
                $activityId = !empty($change['activity_id']) ? (int)$change['activity_id'] : null;

                if ($advisorId <= 0 || $hour < 0 || $hour > 23) {
                    continue;
                }

                // Validar tipo permitido
                if (!in_array($tipo, ['normal', 'break'], true)) {
                    $tipo = 'normal';
                }

                if ($action === 'add') {
                    $stmtInsert->execute([
                        ':schedule_id' => $id,
                        ':advisor_id' => $advisorId,
                        ':campaign_id' => $campaignId,
                        ':fecha' => $date,
                        ':hora' => $hour,
                        ':tipo' => $tipo,
                    ]);
                    if ($stmtInsert->rowCount() > 0) {
                        $added++;
                        if ($tipo === 'break') {
                            $breaks++;
                        }
                    }

                    // Si tiene actividad, crear/actualizar la asignación
                    if ($activityId) {
                        $stmtActivityUpsert->execute([
                            ':activity_id' => $activityId,
                            ':advisor_id' => $advisorId,
                            ':hora_inicio' => $hour,
                            ':hora_fin' => $hour + 1,
                            ':dias_semana' => '{' . $dow . '}',
                        ]);
                        $activities++;
                    }
                } elseif ($action === 'remove') {
                    $stmtDelete->execute([
                        ':schedule_id' => $id,
                        ':advisor_id' => $advisorId,
                        ':fecha' => $date,
                        ':hora' => $hour,
                    ]);
                    if ($stmtDelete->rowCount() > 0) {
                        $removed++;
                    }
                }
            }

            $pdo->commit();

            \App\Services\AuditService::log(
                'schedule.edit_assignments',
                'schedules',
                $id,
                ['date' => $date],
                ['added' => $added, 'removed' => $removed, 'breaks' => $breaks, 'activities' => $activities]
            );

            echo json_encode([
                'success' => true,
                'added' => $added,
                'removed' => $removed,
                'breaks' => $breaks,
                'activities' => $activities,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error actualizando asignaciónes: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al guardar cambios']);
        }

        exit;
    }

    private function resolveAdvisorByUser(PDO $pdo, array $user): ?array
    {
        $stmt = $pdo->prepare("
            SELECT a.* FROM advisors a
            WHERE LOWER(a.cedula) = LOWER(:email) OR EXISTS (
                SELECT 1 FROM users u WHERE u.id = :user_id AND
                (LOWER(u.email) LIKE LOWER(CONCAT('%', a.cedula, '%')) OR
                 LOWER(a.nombres || ' ' || a.apellidos) = LOWER(u.nombre || ' ' || u.apellido))
            )
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => (string)($user['email'] ?? ''),
            ':user_id' => (int)($user['id'] ?? 0),
        ]);
        $advisor = $stmt->fetch();

        if ($advisor) {
            return $advisor;
        }

        $stmt = $pdo->prepare("
            SELECT a.* FROM advisors a
            WHERE LOWER(a.nombres || ' ' || a.apellidos) = LOWER(:nombre)
               OR LOWER(a.apellidos || ' ' || a.nombres) = LOWER(:nombre)
            LIMIT 1
        ");
        $stmt->execute([
            ':nombre' => trim((string)($user['nombre'] ?? '') . ' ' . (string)($user['apellido'] ?? '')),
        ]);

        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }

        $firstName = trim((string)($user['nombre'] ?? ''));
        $lastName = trim((string)($user['apellido'] ?? ''));
        if ($firstName === '' || $lastName === '') {
            return null;
        }

        // No usar LIKE — match parcial puede resolver al advisor equivocado
        return null;
    }
}
