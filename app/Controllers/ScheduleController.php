<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use PDO;
use RuntimeException;
use Throwable;
use App\Services\AuthService;
use App\Services\ScheduleService;
use App\Traits\FlashMessageTrait;

require_once APP_PATH . '/Services/AuthService.php';
require_once APP_PATH . '/Services/ScheduleService.php';
require_once APP_PATH . '/Services/ScheduleBuilder.php';
require_once APP_PATH . '/Services/NotificationService.php';
require_once APP_PATH . '/Services/PdfService.php';
require_once APP_PATH . '/Services/AuditService.php';
require_once APP_PATH . '/Traits/FlashMessageTrait.php';

class ScheduleController
{
    use FlashMessageTrait;

    public function index(): void
    {
        AuthService::requirePermission('schedules.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();
        $role = $user['rol'] ?? '';
        $schedules = [];

        if (AuthService::canManageAllCampaigns($user)) {
            $stmt = $pdo->query("
                SELECT s.*, c.nombre as campaign_nombre,
                       u.nombre || ' ' || u.apellido as generado_por_nombre
                FROM schedules s
                JOIN campaigns c ON c.id = s.campaign_id
                LEFT JOIN users u ON u.id = s.generado_por
                ORDER BY s.created_at DESC
            ");
            $schedules = $stmt->fetchAll();
        } elseif ($role === 'supervisor') {
            $stmt = $pdo->prepare("
                SELECT s.*, c.nombre as campaign_nombre,
                       u.nombre || ' ' || u.apellido as generado_por_nombre
                FROM schedules s
                JOIN campaigns c ON c.id = s.campaign_id
                LEFT JOIN users u ON u.id = s.generado_por
                WHERE c.supervisor_id = :uid
                ORDER BY s.created_at DESC
            ");
            $stmt->execute([':uid' => $user['id']]);
            $schedules = $stmt->fetchAll();
        } elseif ($role === 'asesor') {
            $advisor = ScheduleService::resolveAdvisorByUser($pdo, $user);
            if ($advisor) {
                $stmt = $pdo->prepare("
                    SELECT s.*, c.nombre as campaign_nombre,
                           u.nombre || ' ' || u.apellido as generado_por_nombre
                    FROM schedules s
                    JOIN campaigns c ON c.id = s.campaign_id
                    LEFT JOIN users u ON u.id = s.generado_por
                    WHERE s.campaign_id = :campaign_id
                      AND s.status = 'aprobado'
                    ORDER BY s.periodo_anio DESC, s.periodo_mes DESC, s.created_at DESC
                ");
                $stmt->execute([':campaign_id' => $advisor['campaign_id']]);
                $schedules = $stmt->fetchAll();
            }
        }
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $pageTitle = 'Horarios';
        $currentPage = 'schedules';

        include APP_PATH . '/Views/schedules/index.php';
    }

    public function show(int $id): void
    {
        AuthService::requirePermission('schedules.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();
        $role = $user['rol'] ?? '';

        $stmt = $pdo->prepare("
            SELECT s.*, c.nombre as campaign_nombre, c.supervisor_id
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

        if (!AuthService::canManageAllCampaigns($user)) {
            if ($role === 'supervisor') {
                if ((int)$schedule['supervisor_id'] !== (int)$user['id']) {
                    header('Location: ' . BASE_URL . '/schedules');
                    exit;
                }
            } elseif ($role === 'asesor') {
                $advisor = ScheduleService::resolveAdvisorByUser($pdo, $user);
                $canViewApproved = $advisor
                    && (int)$advisor['campaign_id'] === (int)$schedule['campaign_id']
                    && (string)$schedule['status'] === 'aprobado';

                if (!$canViewApproved) {
                    header('Location: ' . BASE_URL . '/my-schedule');
                    exit;
                }
            } else {
                header('Location: ' . BASE_URL . '/dashboard');
                exit;
            }
        }

        $stmt = $pdo->prepare("
            SELECT sa.*, a.nombres, a.apellidos
            FROM shift_assignments sa
            JOIN advisors a ON a.id = sa.advisor_id
            WHERE sa.schedule_id = :schedule_id
            ORDER BY sa.fecha, sa.hora, a.apellidos
        ");
        $stmt->execute([':schedule_id' => $id]);
        $assignments = $stmt->fetchAll();

        // Asesores directos de la campaña + compartidos (prestados a esta campaña)
        $stmt = $pdo->prepare("
            SELECT a.id, a.nombres, a.apellidos
            FROM advisors a
            WHERE a.campaign_id = :campaign_id AND a.estado = 'activo'
            UNION
            SELECT a.id, a.nombres, a.apellidos
            FROM shared_advisors sa
            JOIN advisors a ON a.id = sa.advisor_id
            WHERE sa.target_campaign_id = :campaign_id2 AND sa.estado = 'activo' AND a.estado = 'activo'
            ORDER BY apellidos, nombres
        ");
        $stmt->execute([':campaign_id' => $schedule['campaign_id'], ':campaign_id2' => $schedule['campaign_id']]);
        $campaignAdvisors = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT fecha::text AS fecha, hora, asesores_requeridos
            FROM staffing_requirements
            WHERE campaign_id = :campaign_id
              AND fecha BETWEEN :fecha_inicio AND :fecha_fin
            ORDER BY fecha ASC, hora ASC
        ");
        $stmt->execute([
            ':campaign_id' => $schedule['campaign_id'],
            ':fecha_inicio' => $schedule['fecha_inicio'],
            ':fecha_fin' => $schedule['fecha_fin'],
        ]);
        $requirements = $stmt->fetchAll();

        // Cargar actividades y asignaciónes de asesores a actividades
        $stmt = $pdo->prepare("
            SELECT ca.id, ca.nombre, ca.color
            FROM campaign_activities ca
            WHERE ca.campaign_id = :campaign_id AND ca.estado = 'activa'
            ORDER BY ca.nombre
        ");
        $stmt->execute([':campaign_id' => $schedule['campaign_id']]);
        $campaignActivities = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT aaa.advisor_id, ca.nombre AS activity_nombre, ca.color AS activity_color,
                   aaa.hora_inicio, aaa.hora_fin, aaa.dias_semana
            FROM advisor_activity_assignments aaa
            JOIN campaign_activities ca ON ca.id = aaa.activity_id
            WHERE ca.campaign_id = :campaign_id
              AND ca.estado = 'activa'
              AND aaa.activo = true
        ");
        $stmt->execute([':campaign_id' => $schedule['campaign_id']]);
        $activityAssignments = $stmt->fetchAll();

        // Cargar IDs de asesores compartidos (prestados a esta campaña)
        $stmt = $pdo->prepare("
            SELECT sa.advisor_id
            FROM shared_advisors sa
            WHERE sa.target_campaign_id = :campaign_id AND sa.estado = 'activo'
        ");
        $stmt->execute([':campaign_id' => $schedule['campaign_id']]);
        $sharedAdvisorIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Cargar horas que asesores propios tienen comprometidas en OTRAS campañas
        // (para mostrar marca visual "prestado a X" en el horario)
        $crossCampaignHours = [];
        $stmt = $pdo->prepare("
            SELECT sa2.advisor_id
            FROM shared_advisors sa2
            WHERE sa2.source_campaign_id = :campaign_id AND sa2.estado = 'activo'
        ");
        $stmt->execute([':campaign_id' => $schedule['campaign_id']]);
        $outgoingAdvisorIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($outgoingAdvisorIds)) {
            $phOuts = implode(',', array_fill(0, count($outgoingAdvisorIds), '?'));
            $paramsOut = array_merge(
                array_map('intval', $outgoingAdvisorIds),
                [$schedule['fecha_inicio'], $schedule['fecha_fin'], $schedule['campaign_id']]
            );
            $stmt = $pdo->prepare("
                SELECT sa_ext.advisor_id, sa_ext.fecha::text AS fecha, sa_ext.hora, c.nombre AS campaign_nombre
                FROM shift_assignments sa_ext
                JOIN campaigns c ON c.id = sa_ext.campaign_id
                WHERE sa_ext.advisor_id IN ($phOuts)
                  AND sa_ext.fecha BETWEEN ? AND ?
                  AND sa_ext.campaign_id <> ?
                ORDER BY sa_ext.advisor_id, sa_ext.fecha, sa_ext.hora
            ");
            $stmt->execute($paramsOut);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $crossCampaignHours[(int)$row['advisor_id']][$row['fecha']][(int)$row['hora']] = $row['campaign_nombre'];
            }
        }

        $pageTitle = 'Ver Horario';
        $currentPage = 'schedules';

        include APP_PATH . '/Views/schedules/show.php';
    }

    public function showGenerate(): void
    {
        AuthService::requirePermission('schedules.generate');

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $pdo = Database::getConnection();

        // Cargar importaciónes disponibles con info de campaña y asesores
        if (AuthService::canManageAllCampaigns($user)) {
            $stmt = $pdo->query("
                SELECT si.id as import_id, si.campaign_id, si.periodo_anio, si.periodo_mes,
                       si.archivo_nombre, si.total_asesor_hora, si.imported_at,
                       c.nombre as campaign_nombre,
                       u.nombre || ' ' || u.apellido as importado_por_nombre,
                       (SELECT COUNT(*) FROM advisors a WHERE a.campaign_id = c.id AND a.estado = 'activo') as total_asesores,
                       s.id as schedule_id, s.status as schedule_status
                FROM staffing_imports si
                JOIN campaigns c ON c.id = si.campaign_id
                LEFT JOIN users u ON u.id = si.importado_por
                LEFT JOIN schedules s ON s.campaign_id = si.campaign_id
                    AND s.periodo_anio = si.periodo_anio
                    AND s.periodo_mes = si.periodo_mes
                WHERE si.estado = 'procesado'
                  AND c.estado = 'activa'
                ORDER BY c.nombre, si.periodo_anio DESC, si.periodo_mes DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT si.id as import_id, si.campaign_id, si.periodo_anio, si.periodo_mes,
                       si.archivo_nombre, si.total_asesor_hora, si.imported_at,
                       c.nombre as campaign_nombre,
                       u.nombre || ' ' || u.apellido as importado_por_nombre,
                       (SELECT COUNT(*) FROM advisors a WHERE a.campaign_id = c.id AND a.estado = 'activo') as total_asesores,
                       s.id as schedule_id, s.status as schedule_status
                FROM staffing_imports si
                JOIN campaigns c ON c.id = si.campaign_id
                LEFT JOIN users u ON u.id = si.importado_por
                LEFT JOIN schedules s ON s.campaign_id = si.campaign_id
                    AND s.periodo_anio = si.periodo_anio
                    AND s.periodo_mes = si.periodo_mes
                WHERE si.estado = 'procesado'
                  AND c.estado = 'activa'
                  AND c.supervisor_id = :uid
                ORDER BY c.nombre, si.periodo_anio DESC, si.periodo_mes DESC
            ");
            $stmt->execute([':uid' => $user['id']]);
        }

        $imports = $stmt->fetchAll();

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $pageTitle = 'Generar Horario';
        $currentPage = 'schedules';

        include APP_PATH . '/Views/schedules/generate.php';
    }

    public function generate(): void
    {
        AuthService::requirePermission('schedules.generate');

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $importId = (int)($_POST['import_id'] ?? 0);
        if ($importId <= 0) {
            $this->setFlash('error', 'Debes selecciónar una importación.');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        $pdo = Database::getConnection();

        // Obtener la importación selecciónada validando permisos
        if (AuthService::canManageAllCampaigns($user)) {
            $stmt = $pdo->prepare("
                SELECT si.*, c.nombre as campaign_nombre
                FROM staffing_imports si
                JOIN campaigns c ON c.id = si.campaign_id
                WHERE si.id = :id
                  AND si.estado = 'procesado'
                  AND c.estado = 'activa'
            ");
            $stmt->execute([':id' => $importId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT si.*, c.nombre as campaign_nombre
                FROM staffing_imports si
                JOIN campaigns c ON c.id = si.campaign_id
                WHERE si.id = :id
                  AND si.estado = 'procesado'
                  AND c.estado = 'activa'
                  AND c.supervisor_id = :uid
            ");
            $stmt->execute([':id' => $importId, ':uid' => $user['id']]);
        }

        $importRow = $stmt->fetch();
        if (!$importRow) {
            $this->setFlash('error', 'Importación no encontrada o sin permisos.');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        $campaignId = (int)$importRow['campaign_id'];
        $periodoAnio = (int)$importRow['periodo_anio'];
        $periodoMes = (int)$importRow['periodo_mes'];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $periodoMes, $periodoAnio);

        $fechaInicio = sprintf('%04d-%02d-01', $periodoAnio, $periodoMes);
        $fechaFin = sprintf('%04d-%02d-%02d', $periodoAnio, $periodoMes, $daysInMonth);

        $pdo->beginTransaction();
        try {
            $action = ScheduleService::syncMonthlyScheduleHeader(
                $pdo,
                $campaignId,
                $periodoAnio,
                $periodoMes,
                $fechaInicio,
                $fechaFin,
                (int)$user['id']
            );

            $scheduleRow = ScheduleService::findMonthlySchedule($pdo, $campaignId, $fechaInicio);
            if (!$scheduleRow) {
                throw new RuntimeException('No se pudo crear la cabecera del horario.');
            }

            $existingAssignments = ScheduleService::countScheduleAssignments($pdo, (int)$scheduleRow['id']);
            $isLockedStatus = in_array($scheduleRow['status'], ['aprobado', 'enviado'], true);

            if ($isLockedStatus && $existingAssignments > 0) {
                throw new RuntimeException(
                    'El horario esta en estado "' . $scheduleRow['status'] . '" y ya tiene asignaciónes. No se puede regenerar.'
                );
            }

            $builder = new \App\Services\ScheduleBuilder($pdo);
            $generatedAssignments = $builder->build(
                (int)$scheduleRow['id'],
                $campaignId,
                $fechaInicio,
                $fechaFin
            );

            $pdo->commit();

            // Regenerar horarios de campañas fuente que tienen asesores prestados a esta campaña
            // para que reflejen las horas comprometidas en esta campaña
            $regeneratedCampaigns = $this->regenerarCampañasFuente(
                $pdo, $campaignId, $fechaInicio, $fechaFin, (int)$user['id']
            );

            $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $msg = sprintf(
                'Horario generado para %s - %s %d. Se crearon %d asignaciónes.',
                $importRow['campaign_nombre'],
                $meses[$periodoMes] ?? $periodoMes,
                $periodoAnio,
                $generatedAssignments
            );
            if (!empty($regeneratedCampaigns)) {
                $msg .= ' Se actualizaron horarios de: ' . implode(', ', $regeneratedCampaigns) . '.';
            }
            $this->setFlash('success', $msg);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->setFlash('error', 'Error al generar horario: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function regeneratePartial(): void
    {
        AuthService::requirePermission('schedules.generate');

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $importId = (int)($_POST['import_id'] ?? 0);
        $fromDate = trim($_POST['from_date'] ?? '');

        if ($importId <= 0 || $fromDate === '') {
            $this->setFlash('error', 'Debes seleccionar una importación y una fecha de inicio.');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        // Validar formato de fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            $this->setFlash('error', 'Formato de fecha inválido.');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        $pdo = Database::getConnection();

        // Obtener la importación validando permisos
        if (AuthService::canManageAllCampaigns($user)) {
            $stmt = $pdo->prepare("
                SELECT si.*, c.nombre as campaign_nombre
                FROM staffing_imports si
                JOIN campaigns c ON c.id = si.campaign_id
                WHERE si.id = :id
                  AND si.estado = 'procesado'
                  AND c.estado = 'activa'
            ");
            $stmt->execute([':id' => $importId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT si.*, c.nombre as campaign_nombre
                FROM staffing_imports si
                JOIN campaigns c ON c.id = si.campaign_id
                WHERE si.id = :id
                  AND si.estado = 'procesado'
                  AND c.estado = 'activa'
                  AND c.supervisor_id = :uid
            ");
            $stmt->execute([':id' => $importId, ':uid' => $user['id']]);
        }

        $importRow = $stmt->fetch();
        if (!$importRow) {
            $this->setFlash('error', 'Importación no encontrada o sin permisos.');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        $campaignId = (int)$importRow['campaign_id'];
        $periodoAnio = (int)$importRow['periodo_anio'];
        $periodoMes = (int)$importRow['periodo_mes'];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $periodoMes, $periodoAnio);

        $fechaInicio = sprintf('%04d-%02d-01', $periodoAnio, $periodoMes);
        $fechaFin = sprintf('%04d-%02d-%02d', $periodoAnio, $periodoMes, $daysInMonth);

        // Validar que fromDate esté dentro del rango del periodo
        if ($fromDate < $fechaInicio || $fromDate > $fechaFin) {
            $this->setFlash('error', 'La fecha debe estar dentro del periodo del horario.');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        // Buscar schedule existente
        $scheduleRow = ScheduleService::findMonthlySchedule($pdo, $campaignId, $fechaInicio);
        if (!$scheduleRow) {
            $this->setFlash('error', 'No existe un horario para este periodo. Usa "Generar Horario" primero.');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        $isLocked = in_array($scheduleRow['status'], ['aprobado', 'enviado'], true);
        if ($isLocked) {
            $this->setFlash('error', 'El horario está en estado "' . $scheduleRow['status'] . '" y no se puede modificar.');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $builder = new \App\Services\ScheduleBuilder($pdo);
            $generatedAssignments = $builder->buildPartial(
                (int)$scheduleRow['id'],
                $campaignId,
                $fechaInicio,
                $fechaFin,
                $fromDate
            );

            $pdo->commit();

            // Regenerar campañas fuente
            $regeneratedCampaigns = $this->regenerarCampañasFuente(
                $pdo, $campaignId, $fechaInicio, $fechaFin, (int)$user['id']
            );

            $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $msg = sprintf(
                'Horario ajustado desde %s para %s - %s %d. Se crearon %d asignaciones nuevas.',
                date('d/m/Y', strtotime($fromDate)),
                $importRow['campaign_nombre'],
                $meses[$periodoMes] ?? $periodoMes,
                $periodoAnio,
                $generatedAssignments
            );
            if (!empty($regeneratedCampaigns)) {
                $msg .= ' Se actualizaron horarios de: ' . implode(', ', $regeneratedCampaigns) . '.';
            }
            $this->setFlash('success', $msg);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->setFlash('error', 'Error al ajustar horario: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function submit(int $id): void
    {
        AuthService::requirePermission('schedules.submit');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Validar ownership: supervisor solo puede enviar horarios de sus campañas
        $schedule = $this->getScheduleWithOwnership($pdo, $id);
        if (!$schedule || (!AuthService::canManageAllCampaigns($user) && (int)$schedule['supervisor_id'] !== (int)$user['id'])) {
            $this->setFlash('error', 'Sin permisos para enviar este horario');
            header('Location: ' . BASE_URL . '/schedules');
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE schedules SET status = 'enviado'
            WHERE id = :id AND status IN ('borrador', 'rechazado')
        ");
        $stmt->execute([':id' => $id]);

        // Notificar a usuarios con permiso de aprobar
        $campaignName = '';
        $stmtC = $pdo->prepare("SELECT c.nombre FROM campaigns c JOIN schedules s ON s.campaign_id = c.id WHERE s.id = :id");
        $stmtC->execute([':id' => $id]);
        $campaignName = $stmtC->fetchColumn() ?: 'Desconocida';

        \App\Services\NotificationService::sendToPermission(
            'schedules.approve',
            'horario_enviado',
            'Horario enviado para aprobacion',
            "El horario de {$campaignName} ({$schedule['periodo_mes']}/{$schedule['periodo_anio']}) fue enviado por " . ($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? ''),
            "/schedules/{$id}"
        );

        \App\Services\AuditService::log('schedule.submit', 'schedules', $id, ['status' => 'borrador'], ['status' => 'enviado']);

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function approve(int $id): void
    {
        AuthService::requirePermission('schedules.approve');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Validar ownership
        $schedule = $this->getScheduleWithOwnership($pdo, $id);
        if (!$schedule || (!AuthService::canManageAllCampaigns($user) && (int)$schedule['supervisor_id'] !== (int)$user['id'])) {
            $this->setFlash('error', 'Sin permisos para aprobar este horario');
            header('Location: ' . BASE_URL . '/schedules');
            exit;
        }

        // Separation of duties: cannot approve own schedule (except admin/gerente/coordinador)
        if (!AuthService::canManageAllCampaigns($user) && (int)($schedule['generado_por'] ?? 0) === (int)$user['id']) {
            $this->setFlash('error', 'No puedes aprobar un horario que tú generaste.');
            header('Location: ' . BASE_URL . '/schedules/' . $id);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE schedules SET
                status = 'aprobado',
                aprobado_por = :aprobado_por,
                aprobado_at = NOW()
            WHERE id = :id AND status = 'enviado'
        ");
        $stmt->execute([
            ':id' => $id,
            ':aprobado_por' => $user['id']
        ]);

        // Notificar al supervisor dueno del horario
        if (!empty($schedule['generado_por'])) {
            $campaignName = '';
            $stmtC = $pdo->prepare("SELECT c.nombre FROM campaigns c JOIN schedules s ON s.campaign_id = c.id WHERE s.id = :id");
            $stmtC->execute([':id' => $id]);
            $campaignName = $stmtC->fetchColumn() ?: 'Desconocida';

            \App\Services\NotificationService::send(
                (int)$schedule['generado_por'],
                'horario_aprobado',
                'Horario aprobado',
                "El horario de {$campaignName} fue aprobado por " . ($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? ''),
                "/schedules/{$id}"
            );
        }

        \App\Services\AuditService::log('schedule.approve', 'schedules', $id, ['status' => 'enviado'], ['status' => 'aprobado']);

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function reject(int $id): void
    {
        AuthService::requirePermission('schedules.approve');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Validar ownership
        $schedule = $this->getScheduleWithOwnership($pdo, $id);
        if (!$schedule || (!AuthService::canManageAllCampaigns($user) && (int)$schedule['supervisor_id'] !== (int)$user['id'])) {
            $this->setFlash('error', 'Sin permisos para rechazar este horario');
            header('Location: ' . BASE_URL . '/schedules');
            exit;
        }

        $nota = trim((string)($_POST['nota_rechazo'] ?? ''));

        $stmt = $pdo->prepare("
            UPDATE schedules SET status = 'rechazado'
            WHERE id = :id AND status = 'enviado'
        ");
        $stmt->execute([':id' => $id]);

        // Notificar al supervisor dueno del horario
        if (!empty($schedule['generado_por'])) {
            $campaignName = '';
            $stmtC = $pdo->prepare("SELECT c.nombre FROM campaigns c JOIN schedules s ON s.campaign_id = c.id WHERE s.id = :id");
            $stmtC->execute([':id' => $id]);
            $campaignName = $stmtC->fetchColumn() ?: 'Desconocida';

            $mensaje = "El horario de {$campaignName} fue rechazado por " . ($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '');
            if ($nota !== '') {
                $mensaje .= ". Motivo: {$nota}";
            }

            \App\Services\NotificationService::send(
                (int)$schedule['generado_por'],
                'horario_rechazado',
                'Horario rechazado',
                $mensaje,
                "/schedules/{$id}"
            );
        }

        \App\Services\AuditService::log('schedule.reject', 'schedules', $id, ['status' => 'enviado'], ['status' => 'rechazado', 'nota' => $nota]);

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function mySchedule(): void
    {
        AuthService::requirePermission('schedules.view_own');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();
        $advisor = ScheduleService::resolveAdvisorByUser($pdo, $user);

        $assignments = [];
        $currentSchedule = null;

        if ($advisor) {
            $currentMonth = date('n');
            $currentYear = date('Y');

            $stmt = $pdo->prepare("
                SELECT sa.*, s.periodo_mes, s.periodo_anio, s.status,
                       c.nombre as campaign_nombre
                FROM shift_assignments sa
                JOIN schedules s ON s.id = sa.schedule_id
                JOIN campaigns c ON c.id = sa.campaign_id
                WHERE sa.advisor_id = :advisor_id
                  AND s.status = 'aprobado'
                  AND s.periodo_mes = :mes
                  AND s.periodo_anio = :anio
                ORDER BY sa.fecha, sa.hora
            ");
            $stmt->execute([
                ':advisor_id' => $advisor['id'],
                ':mes' => $currentMonth,
                ':anio' => $currentYear
            ]);
            $assignments = $stmt->fetchAll();

            $stmt = $pdo->prepare("
                SELECT s.*, c.nombre as campaign_nombre
                FROM schedules s
                JOIN campaigns c ON c.id = s.campaign_id
                WHERE s.campaign_id = :campaign_id
                  AND s.status = 'aprobado'
                  AND s.periodo_mes = :mes
                  AND s.periodo_anio = :anio
                LIMIT 1
            ");
            $stmt->execute([
                ':campaign_id' => $advisor['campaign_id'],
                ':mes' => $currentMonth,
                ':anio' => $currentYear
            ]);
            $currentSchedule = $stmt->fetch();

            // Cargar check-in de hoy del asesor
            if ($currentSchedule) {
                $stmt = $pdo->prepare("
                    SELECT checkin_at FROM advisor_checkins
                    WHERE advisor_id = :aid AND schedule_id = :sid AND fecha = :fecha
                ");
                $stmt->execute([
                    ':aid' => $advisor['id'],
                    ':sid' => $currentSchedule['id'],
                    ':fecha' => date('Y-m-d'),
                ]);
                $todayCheckin = $stmt->fetchColumn();
            }
        }

        $todayCheckin = $todayCheckin ?? false;

        $pageTitle = 'Mi Horario';
        $currentPage = 'my-schedule';

        include APP_PATH . '/Views/schedules/my-schedule.php';
    }

    /**
     * Exportar horario mensual a PDF
     */
    public function exportPdf(int $id): void
    {
        AuthService::requirePermission('schedules.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $schedule = $this->getScheduleWithOwnership($pdo, $id);
        if (!$schedule || (!AuthService::canManageAllCampaigns($user) && (int)$schedule['supervisor_id'] !== (int)$user['id'])) {
            $this->setFlash('error', 'Sin permisos para exportar este horario');
            header('Location: ' . BASE_URL . '/schedules');
            exit;
        }

        require_once APP_PATH . '/Services/PdfService.php';

        $mpdf = \App\Services\PdfService::generateSchedulePdf($id);
        if (!$mpdf) {
            $this->setFlash('error', 'No se pudo generar el PDF');
            header('Location: ' . BASE_URL . '/schedules/' . $id);
            exit;
        }

        $filename = 'Horario_' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string)($schedule['campaign_nombre'] ?? 'export'))
            . '_' . $schedule['periodo_mes'] . '_' . $schedule['periodo_anio'] . '.pdf';

        $mpdf->Output($filename, 'D');
        exit;
    }

    private function getScheduleWithOwnership(\PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare("
            SELECT s.*, c.supervisor_id, c.nombre as campaign_nombre
            FROM schedules s
            JOIN campaigns c ON c.id = s.campaign_id
            WHERE s.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Regenera horarios de campañas fuente que tienen asesores prestados a $targetCampaignId.
     * Esto asegura que el horario de la campaña fuente refleje las horas comprometidas
     * por sus asesores en la campaña destino.
     *
     * Solo regenera si la campaña fuente ya tiene un horario en estado 'borrador' para el mismo período.
     *
     * @return string[] Nombres de campañas regeneradas
     */
    private function regenerarCampañasFuente(
        PDO $pdo,
        int $targetCampaignId,
        string $fechaInicio,
        string $fechaFin,
        int $userId
    ): array {
        // Buscar campañas fuente que prestan asesores a esta campaña
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

            // Buscar horario existente en borrador para este período
            $scheduleRow = ScheduleService::findMonthlySchedule($pdo, $sourceCampaignId, $fechaInicio);
            if (!$scheduleRow) continue;

            // Solo regenerar si está en borrador (no tocar aprobados/enviados)
            if ($scheduleRow['status'] !== 'borrador') continue;

            $scheduleId = (int)$scheduleRow['id'];

            // Regenerar con ScheduleBuilder (cleanupAssignments dentro de build() limpia las previas)
            $builder = new \App\Services\ScheduleBuilder($pdo);
            $builder->build($scheduleId, $sourceCampaignId, $fechaInicio, $fechaFin);

            $regenerated[] = $sc['nombre'];
        }

        return $regenerated;
    }
}
