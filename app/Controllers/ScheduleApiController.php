<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use PDO;
use Throwable;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';
require_once APP_PATH . '/Services/NotificationService.php';
require_once APP_PATH . '/Services/AuditService.php';

class ScheduleApiController
{
    /**
     * API JSON: Sugerir candidatos de reemplazo para un asesor ausente.
     */
    public function suggestReplacements(): void
    {
        AuthService::requirePermission('schedules.edit');

        header('Content-Type: application/json');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $input = json_decode(file_get_contents('php://input'), true);
        $scheduleId = (int)($input['schedule_id'] ?? 0);
        $advisorId = (int)($input['advisor_id'] ?? 0);
        $fecha = (string)($input['fecha'] ?? '');

        if ($scheduleId <= 0 || $advisorId <= 0 || $fecha === '') {
            echo json_encode(['success' => false, 'error' => 'Datos invalidos']);
            exit;
        }

        // Obtener schedule con campaign_id
        $stmt = $pdo->prepare("
            SELECT s.*, c.id as campaign_id, c.supervisor_id
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

        // Verificar permisos
        if (!AuthService::canManageAllCampaigns($user)) {
            if ((int)$schedule['supervisor_id'] !== (int)$user['id']) {
                echo json_encode(['success' => false, 'error' => 'Sin permisos']);
                exit;
            }
        }

        $campaignId = (int)$schedule['campaign_id'];

        // Horas afectadas del asesor ausente en esa fecha
        $stmt = $pdo->prepare("
            SELECT hora, tipo FROM shift_assignments
            WHERE schedule_id = :schedule_id AND advisor_id = :advisor_id AND fecha = :fecha
            ORDER BY hora
        ");
        $stmt->execute([':schedule_id' => $scheduleId, ':advisor_id' => $advisorId, ':fecha' => $fecha]);
        $affectedHours = $stmt->fetchAll();

        if (empty($affectedHours)) {
            echo json_encode(['success' => false, 'error' => 'El asesor no tiene horas asignadas en esa fecha']);
            exit;
        }

        // Determinar si hay horas nocturnas (22-06)
        $hasNocturno = false;
        foreach ($affectedHours as $ah) {
            $h = (int)$ah['hora'];
            if ($h >= 22 || $h < 6) {
                $hasNocturno = true;
                break;
            }
        }

        // Obtener parametros del sistema
        $stmtParams = $pdo->query("SELECT clave, valor FROM system_params");
        $sysParams = [];
        foreach ($stmtParams->fetchAll() as $p) {
            $sysParams[$p['clave']] = $p['valor'];
        }
        $maxHorasDiaDefault = (int)($sysParams['max_horas_dia'] ?? 10);

        // Buscar candidatos elegibles de la misma campana
        $stmt = $pdo->prepare("
            SELECT a.id, a.nombres, a.apellidos,
                   ac.tiene_vpn, ac.tiene_restriccion_medica, ac.max_horas_dia
            FROM advisors a
            LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
            WHERE a.campaign_id = :campaign_id
              AND a.estado = 'activo'
              AND a.id != :excluded_id
        ");
        $stmt->execute([':campaign_id' => $campaignId, ':excluded_id' => $advisorId]);
        $candidates = $stmt->fetchAll();

        // Calcular horas acumuladas en el mes para cada candidato
        $mesInicio = date('Y-m-01', strtotime($fecha));
        $mesFin = date('Y-m-t', strtotime($fecha));

        $stmt = $pdo->prepare("
            SELECT advisor_id, COUNT(*) as total_horas
            FROM shift_assignments
            WHERE campaign_id = :campaign_id
              AND fecha BETWEEN :mes_inicio AND :mes_fin
              AND tipo != 'break'
            GROUP BY advisor_id
        ");
        $stmt->execute([':campaign_id' => $campaignId, ':mes_inicio' => $mesInicio, ':mes_fin' => $mesFin]);
        $monthlyHoursMap = [];
        foreach ($stmt->fetchAll() as $r) {
            $monthlyHoursMap[(int)$r['advisor_id']] = (int)$r['total_horas'];
        }

        // Horas ya asignadas hoy por candidato
        $stmt = $pdo->prepare("
            SELECT advisor_id, COUNT(*) as horas_hoy
            FROM shift_assignments
            WHERE campaign_id = :campaign_id
              AND fecha = :fecha
              AND tipo != 'break'
            GROUP BY advisor_id
        ");
        $stmt->execute([':campaign_id' => $campaignId, ':fecha' => $fecha]);
        $todayHoursMap = [];
        foreach ($stmt->fetchAll() as $r) {
            $todayHoursMap[(int)$r['advisor_id']] = (int)$r['horas_hoy'];
        }

        $horasNecesarias = count(array_filter($affectedHours, fn($h) => $h['tipo'] !== 'break'));

        $eligibleCandidates = [];
        foreach ($candidates as $c) {
            $cId = (int)$c['id'];
            $maxHoras = (int)($c['max_horas_dia'] ?: $maxHorasDiaDefault);
            $horasHoy = $todayHoursMap[$cId] ?? 0;
            $horasDisponiblesHoy = max(0, $maxHoras - $horasHoy);
            $horasMes = $monthlyHoursMap[$cId] ?? 0;

            // Filtrar: restriccion medica activa
            if ($c['tiene_restriccion_medica'] === 'true') {
                continue;
            }

            // Si nocturno, requiere VPN
            if ($hasNocturno && $c['tiene_vpn'] !== 'true') {
                continue;
            }

            // Debe tener al menos 1 hora disponible hoy
            if ($horasDisponiblesHoy <= 0) {
                continue;
            }

            $eligibleCandidates[] = [
                'id' => $cId,
                'nombre' => trim($c['nombres'] . ' ' . $c['apellidos']),
                'horas_mes' => $horasMes,
                'horas_hoy' => $horasHoy,
                'horas_disponibles_hoy' => $horasDisponiblesHoy,
                'tiene_vpn' => $c['tiene_vpn'] === 'true',
            ];
        }

        // Ordenar por menos horas acumuladas en el mes
        usort($eligibleCandidates, fn($a, $b) => $a['horas_mes'] <=> $b['horas_mes']);

        echo json_encode([
            'success' => true,
            'horas_afectadas' => array_map(fn($h) => ['hora' => (int)$h['hora'], 'tipo' => $h['tipo']], $affectedHours),
            'horas_necesarias' => $horasNecesarias,
            'candidatos' => $eligibleCandidates,
        ]);
        exit;
    }

    /**
     * API JSON: Aplicar reemplazo de un asesor ausente.
     */
    public function applyReplacement(): void
    {
        AuthService::requirePermission('schedules.edit');

        header('Content-Type: application/json');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $input = json_decode(file_get_contents('php://input'), true);
        $scheduleId = (int)($input['schedule_id'] ?? 0);
        $advisorAusenteId = (int)($input['advisor_ausente_id'] ?? 0);
        $advisorReemplazoId = (int)($input['advisor_reemplazo_id'] ?? 0);
        $fecha = (string)($input['fecha'] ?? '');

        if ($scheduleId <= 0 || $advisorAusenteId <= 0 || $advisorReemplazoId <= 0 || $fecha === '') {
            echo json_encode(['success' => false, 'error' => 'Datos invalidos']);
            exit;
        }

        // Obtener schedule
        $stmt = $pdo->prepare("
            SELECT s.*, c.id as campaign_id, c.supervisor_id, c.nombre as campaign_nombre
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

        // Verificar permisos
        if (!AuthService::canManageAllCampaigns($user)) {
            if ((int)$schedule['supervisor_id'] !== (int)$user['id']) {
                echo json_encode(['success' => false, 'error' => 'Sin permisos']);
                exit;
            }
        }

        $campaignId = (int)$schedule['campaign_id'];

        // Obtener horas del asesor ausente
        $stmt = $pdo->prepare("
            SELECT hora, tipo FROM shift_assignments
            WHERE schedule_id = :schedule_id AND advisor_id = :advisor_id AND fecha = :fecha
              AND tipo != 'break'
            ORDER BY hora
        ");
        $stmt->execute([':schedule_id' => $scheduleId, ':advisor_id' => $advisorAusenteId, ':fecha' => $fecha]);
        $horasAusente = $stmt->fetchAll();

        if (empty($horasAusente)) {
            echo json_encode(['success' => false, 'error' => 'No hay horas para reemplazar']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO shift_assignments (schedule_id, advisor_id, campaign_id, fecha, hora, tipo, es_extra)
                VALUES (:schedule_id, :advisor_id, :campaign_id, :fecha, :hora, 'replanif', false)
                ON CONFLICT (advisor_id, fecha, hora) DO UPDATE SET tipo = 'replanif'
            ");

            $assigned = 0;
            foreach ($horasAusente as $h) {
                $stmtInsert->execute([
                    ':schedule_id' => $scheduleId,
                    ':advisor_id' => $advisorReemplazoId,
                    ':campaign_id' => $campaignId,
                    ':fecha' => $fecha,
                    ':hora' => (int)$h['hora'],
                ]);
                $assigned++;
            }

            // Registrar en replanning_log
            $stmt = $pdo->prepare("
                INSERT INTO replanning_log (campaign_id, fecha, advisor_ausente_id, motivo, nuevas_asignaciones, ejecutado_por)
                VALUES (:campaign_id, :fecha, :ausente_id, 'ausente', :nuevas_asignaciones, :ejecutado_por)
            ");
            $stmt->execute([
                ':campaign_id' => $campaignId,
                ':fecha' => $fecha,
                ':ausente_id' => $advisorAusenteId,
                ':nuevas_asignaciones' => '{' . $advisorReemplazoId . '}',
                ':ejecutado_por' => $user['id'],
            ]);

            $pdo->commit();

            // Notificar a coordinadores
            $advAusenteStmt = $pdo->prepare("SELECT nombres, apellidos FROM advisors WHERE id = :id");
            $advAusenteStmt->execute([':id' => $advisorAusenteId]);
            $advAusente = $advAusenteStmt->fetch();

            $advReemplazoStmt = $pdo->prepare("SELECT nombres, apellidos FROM advisors WHERE id = :id");
            $advReemplazoStmt->execute([':id' => $advisorReemplazoId]);
            $advReemplazo = $advReemplazoStmt->fetch();

            $nombreAusente = trim(($advAusente['nombres'] ?? '') . ' ' . ($advAusente['apellidos'] ?? ''));
            $nombreReemplazo = trim(($advReemplazo['nombres'] ?? '') . ' ' . ($advReemplazo['apellidos'] ?? ''));

            \App\Services\NotificationService::sendToPermission(
                'schedules.approve',
                'reemplazo_aplicado',
                'Reemplazo aplicado',
                "Reemplazo en {$schedule['campaign_nombre']}: {$nombreReemplazo} cubre a {$nombreAusente} el {$fecha} ({$assigned}h)",
                "/schedules/{$scheduleId}/tracking"
            );

            \App\Services\AuditService::log(
                'schedule.replacement',
                'schedules',
                $scheduleId,
                ['advisor_ausente' => $advisorAusenteId, 'fecha' => $fecha],
                ['advisor_reemplazo' => $advisorReemplazoId, 'horas' => $assigned]
            );

            echo json_encode([
                'success' => true,
                'assigned' => $assigned,
                'nombre_reemplazo' => $nombreReemplazo,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Error aplicando reemplazo: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al aplicar reemplazo']);
        }

        exit;
    }

    /**
     * API JSON: Coverage check hora a hora para un dia.
     */
    public function coverageCheck(int $scheduleId): void
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'No autenticado']);
            exit;
        }

        $pdo = Database::getConnection();
        $date = (string)($_GET['date'] ?? '');

        if ($date === '') {
            echo json_encode(['success' => false, 'error' => 'Fecha requerida']);
            exit;
        }

        // Obtener schedule y campaign_id
        $stmt = $pdo->prepare("
            SELECT s.campaign_id FROM schedules s WHERE s.id = :id
        ");
        $stmt->execute([':id' => $scheduleId]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            echo json_encode(['success' => false, 'error' => 'Horario no encontrado']);
            exit;
        }

        $campaignId = (int)$schedule['campaign_id'];

        // Requerimientos para esa fecha
        $stmt = $pdo->prepare("
            SELECT hora, asesores_requeridos
            FROM staffing_requirements
            WHERE campaign_id = :campaign_id AND fecha = :fecha
            ORDER BY hora
        ");
        $stmt->execute([':campaign_id' => $campaignId, ':fecha' => $date]);
        $reqRows = $stmt->fetchAll();

        $requirements = [];
        foreach ($reqRows as $r) {
            $requirements[(int)$r['hora']] = (int)$r['asesores_requeridos'];
        }

        // Cobertura actual (asesores asignados por hora, excluyendo breaks)
        $stmt = $pdo->prepare("
            SELECT hora, COUNT(DISTINCT advisor_id) as asignados
            FROM shift_assignments
            WHERE schedule_id = :schedule_id AND fecha = :fecha AND tipo != 'break'
            GROUP BY hora
            ORDER BY hora
        ");
        $stmt->execute([':schedule_id' => $scheduleId, ':fecha' => $date]);
        $covRows = $stmt->fetchAll();

        $coverage = [];
        foreach ($covRows as $c) {
            $coverage[(int)$c['hora']] = (int)$c['asignados'];
        }

        // Construir resultado hora a hora (0-23)
        $hourly = [];
        $totalDeficit = 0;
        for ($h = 0; $h <= 23; $h++) {
            $req = $requirements[$h] ?? 0;
            $cov = $coverage[$h] ?? 0;
            $diff = $cov - $req;
            if ($diff < 0) $totalDeficit += abs($diff);

            $hourly[] = [
                'hora' => $h,
                'requeridos' => $req,
                'asignados' => $cov,
                'diferencia' => $diff,
            ];
        }

        echo json_encode([
            'success' => true,
            'date' => $date,
            'hourly' => $hourly,
            'total_deficit' => $totalDeficit,
        ]);
        exit;
    }
}
