<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PDO;
use RuntimeException;
use Throwable;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';

class ScheduleController
{
    public function index(): void
    {
        AuthService::requirePermission('schedules.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();
        $role = $user['rol'] ?? '';
        $schedules = [];

        if ($this->canManageAllCampaigns($user)) {
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
            $advisor = $this->resolveAdvisorByUser($pdo, $user);
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

    public function showImport(): void
    {
        AuthService::requirePermission('schedules.import');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        if ($this->canManageAllCampaigns($user)) {
            $stmt = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        } else {
            $stmt = $pdo->prepare("
                SELECT id, nombre
                FROM campaigns
                WHERE supervisor_id = :uid
                  AND estado = 'activa'
                ORDER BY nombre
            ");
            $stmt->execute([':uid' => $user['id']]);
        }

        $campaigns = $stmt->fetchAll();
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $pageTitle = 'Importar Dimensionamiento';
        $currentPage = 'schedules';

        include APP_PATH . '/Views/schedules/import.php';
    }

    public function import(): void
    {
        AuthService::requirePermission('schedules.import');

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $campaignId = (int)($_POST['campaign_id'] ?? 0);
        $periodoMes = (int)($_POST['periodo_mes'] ?? 0);
        $periodoAnio = (int)($_POST['periodo_anio'] ?? 0);

        if ($campaignId <= 0 || $periodoMes < 1 || $periodoMes > 12 || $periodoAnio < 2000) {
            $this->setFlash('error', 'Datos de importacion invalidos.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        if (empty($_FILES['excel_file']) || !is_array($_FILES['excel_file'])) {
            $this->setFlash('error', 'No se recibio ningun archivo.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        $file = $_FILES['excel_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->setFlash('error', 'Error al subir el archivo. Verifica e intenta nuevamente.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        $originalName = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            $this->setFlash('error', 'Formato no permitido. Usa .xlsx, .xls o .csv.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        $pdo = Database::getConnection();

        if ($this->canManageAllCampaigns($user)) {
            $stmtCampaign = $pdo->prepare("
                SELECT id, nombre
                FROM campaigns
                WHERE id = :id
                  AND estado = 'activa'
            ");
            $stmtCampaign->execute([':id' => $campaignId]);
        } else {
            $stmtCampaign = $pdo->prepare("
                SELECT id, nombre
                FROM campaigns
                WHERE id = :id
                  AND supervisor_id = :uid
                  AND estado = 'activa'
            ");
            $stmtCampaign->execute([
                ':id' => $campaignId,
                ':uid' => $user['id'],
            ]);
        }

        $campaign = $stmtCampaign->fetch();
        if (!$campaign) {
            $this->setFlash('error', 'No tienes permisos sobre esa campana o no esta activa.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        $uploadPath = rtrim($_ENV['UPLOAD_PATH'] ?? (BASE_PATH . '/uploads'), "/\\");
        if (!is_dir($uploadPath) && !mkdir($uploadPath, 0777, true) && !is_dir($uploadPath)) {
            $this->setFlash('error', 'No se pudo preparar la carpeta de carga.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        $storedName = sprintf(
            'dimensionamiento_c%d_%04d_%02d_%s.%s',
            $campaignId,
            $periodoAnio,
            $periodoMes,
            date('Ymd_His'),
            $extension
        );
        $targetPath = $uploadPath . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
            $this->setFlash('error', 'No se pudo guardar el archivo cargado.');
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $periodoMes, $periodoAnio);
        $requirements = [];
        $totalAsesorHora = 0;

        try {
            $spreadsheet = IOFactory::load($targetPath);
            $sheet = $spreadsheet->getActiveSheet();

            $headerRef = Coordinate::stringFromColumnIndex(1) . '1';
            $headerValue = trim((string)$sheet->getCell($headerRef)->getFormattedValue());
            if ($headerValue === '' || stripos($headerValue, 'horas') === false) {
                throw new RuntimeException('Formato no reconocido: la celda A1 debe contener "Horas ACD".');
            }

            for ($hour = 0; $hour < 24; $hour++) {
                $row = 3 + $hour;

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $column = $day + 1;
                    $cellRef = Coordinate::stringFromColumnIndex($column) . $row;
                    $rawValue = $sheet->getCell($cellRef)->getCalculatedValue();
                    $asesores = $this->normalizeRequiredAdvisors($rawValue);

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
                ':importado_por' => $user['id'],
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

            $scheduleAction = $this->syncMonthlyScheduleHeader(
                $pdo,
                $campaignId,
                $periodoAnio,
                $periodoMes,
                $fechaInicio,
                $fechaFin,
                (int)$user['id']
            );

            $generatedAssignments = 0;
            $scheduleRow = $this->findMonthlySchedule($pdo, $campaignId, $fechaInicio);
            if ($scheduleRow) {
                $existingAssignments = $this->countScheduleAssignments($pdo, (int)$scheduleRow['id']);
                $isLockedStatus = in_array($scheduleRow['status'], ['aprobado', 'enviado'], true);
                $canRegenerate = !$isLockedStatus || ($isLockedStatus && $existingAssignments === 0);

                if ($canRegenerate) {
                    $generatedAssignments = $this->buildScheduleAssignments(
                        $pdo,
                        (int)$scheduleRow['id'],
                        $campaignId,
                        $fechaInicio,
                        $fechaFin
                    );
                }
            }

            $pdo->commit();

            $this->setFlash(
                'success',
                sprintf(
                    'Importacion completada para %s. Se guardaron %d registros (%02d/%04d), el horario mensual fue %s y se generaron %d asignaciones.',
                    $campaign['nombre'],
                    count($requirements),
                    $periodoMes,
                    $periodoAnio,
                    $scheduleAction,
                    $generatedAssignments
                )
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errorJson = json_encode([
                'message' => $e->getMessage(),
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
                    ':importado_por' => $user['id'],
                    ':errores_json' => $errorJson ?: '{}',
                ]);
            } catch (Throwable $dbError) {
                error_log('Error guardando detalle de importacion: ' . $dbError->getMessage());
            }

            error_log('Importacion fallida: ' . $e->getMessage());
            $this->setFlash('error', 'No se pudo procesar el archivo: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '/schedules/import');
            exit;
        }

        header('Location: ' . BASE_URL . '/schedules');
        exit;
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

        if (!$this->canManageAllCampaigns($user)) {
            if ($role === 'supervisor') {
                if ((int)$schedule['supervisor_id'] !== (int)$user['id']) {
                    header('Location: ' . BASE_URL . '/schedules');
                    exit;
                }
            } elseif ($role === 'asesor') {
                $advisor = $this->resolveAdvisorByUser($pdo, $user);
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

        $stmt = $pdo->prepare("
            SELECT id, nombres, apellidos
            FROM advisors
            WHERE campaign_id = :campaign_id
              AND estado = 'activo'
            ORDER BY apellidos, nombres
        ");
        $stmt->execute([':campaign_id' => $schedule['campaign_id']]);
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

        $pageTitle = 'Ver Horario';
        $currentPage = 'schedules';

        include APP_PATH . '/Views/schedules/show.php';
    }

    public function generate(): void
    {
        AuthService::requirePermission('schedules.generate');

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $pdo = Database::getConnection();

        if ($this->canManageAllCampaigns($user)) {
            $stmt = $pdo->query("
                SELECT si.campaign_id, si.periodo_anio, si.periodo_mes
                FROM staffing_imports si
                JOIN campaigns c ON c.id = si.campaign_id
                WHERE si.estado = 'procesado'
                  AND c.estado = 'activa'
                ORDER BY si.imported_at DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT si.campaign_id, si.periodo_anio, si.periodo_mes
                FROM staffing_imports si
                JOIN campaigns c ON c.id = si.campaign_id
                WHERE si.estado = 'procesado'
                  AND c.estado = 'activa'
                  AND c.supervisor_id = :uid
                ORDER BY si.imported_at DESC
            ");
            $stmt->execute([':uid' => $user['id']]);
        }

        $imports = $stmt->fetchAll();
        if (empty($imports)) {
            $this->setFlash('error', 'No hay importaciones procesadas para generar horarios.');
            header('Location: ' . BASE_URL . '/schedules');
            exit;
        }

        $created = 0;
        $updated = 0;
        $kept = 0;
        $generatedSchedules = 0;
        $generatedAssignments = 0;

        $pdo->beginTransaction();
        try {
            foreach ($imports as $importRow) {
                $campaignId = (int)$importRow['campaign_id'];
                $periodoAnio = (int)$importRow['periodo_anio'];
                $periodoMes = (int)$importRow['periodo_mes'];
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $periodoMes, $periodoAnio);

                $fechaInicio = sprintf('%04d-%02d-01', $periodoAnio, $periodoMes);
                $fechaFin = sprintf('%04d-%02d-%02d', $periodoAnio, $periodoMes, $daysInMonth);

                $action = $this->syncMonthlyScheduleHeader(
                    $pdo,
                    $campaignId,
                    $periodoAnio,
                    $periodoMes,
                    $fechaInicio,
                    $fechaFin,
                    (int)$user['id']
                );

                if ($action === 'creado') {
                    $created++;
                } elseif ($action === 'actualizado') {
                    $updated++;
                } else {
                    $kept++;
                }

                $scheduleRow = $this->findMonthlySchedule($pdo, $campaignId, $fechaInicio);
                if (!$scheduleRow) {
                    continue;
                }

                $existingAssignments = $this->countScheduleAssignments($pdo, (int)$scheduleRow['id']);
                $isLockedStatus = in_array($scheduleRow['status'], ['aprobado', 'enviado'], true);
                $canRegenerate = !$isLockedStatus || ($isLockedStatus && $existingAssignments === 0);
                if (!$canRegenerate) {
                    continue;
                }

                $inserted = $this->buildScheduleAssignments(
                    $pdo,
                    (int)$scheduleRow['id'],
                    $campaignId,
                    $fechaInicio,
                    $fechaFin
                );
                $generatedSchedules++;
                $generatedAssignments += $inserted;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->setFlash('error', 'No se pudo generar horarios: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '/schedules');
            exit;
        }

        $this->setFlash(
            'success',
            sprintf(
                'Generacion completada. Creados: %d, actualizados: %d, mantenidos: %d, horarios generados: %d, asignaciones: %d.',
                $created,
                $updated,
                $kept,
                $generatedSchedules,
                $generatedAssignments
            )
        );

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function submit(int $id): void
    {
        AuthService::requirePermission('schedules.submit');

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE schedules SET status = 'enviado'
            WHERE id = :id AND status = 'borrador'
        ");
        $stmt->execute([':id' => $id]);

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function approve(int $id): void
    {
        AuthService::requirePermission('schedules.approve');

        $user = $_SESSION['user'];

        $pdo = Database::getConnection();

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

        header('Location: ' . BASE_URL . '/schedules');
        exit;
    }

    public function reject(int $id): void
    {
        AuthService::requirePermission('schedules.approve');

        $user = $_SESSION['user'];

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE schedules SET status = 'rechazado'
            WHERE id = :id AND status = 'enviado'
        ");
        $stmt->execute([':id' => $id]);

        header('Location: ' . BASE_URL . '/schedules');
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

        // Solo editable en borrador o rechazado
        if (!in_array($schedule['status'], ['borrador', 'rechazado'], true)) {
            echo json_encode(['success' => false, 'error' => 'Este horario no se puede editar (estado: ' . $schedule['status'] . ')']);
            exit;
        }

        // Verificar permisos
        if (!$this->canManageAllCampaigns($user)) {
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

        $added = 0;
        $removed = 0;
        $campaignId = (int)$schedule['campaign_id'];

        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO shift_assignments (
                    schedule_id, advisor_id, campaign_id, fecha, hora, tipo, es_extra
                ) VALUES (
                    :schedule_id, :advisor_id, :campaign_id, :fecha, :hora, 'normal', false
                )
                ON CONFLICT (advisor_id, fecha, hora) DO NOTHING
            ");

            $stmtDelete = $pdo->prepare("
                DELETE FROM shift_assignments
                WHERE schedule_id = :schedule_id
                  AND advisor_id = :advisor_id
                  AND fecha = :fecha
                  AND hora = :hora
            ");

            foreach ($changes as $change) {
                $action = (string)($change['action'] ?? '');
                $advisorId = (int)($change['advisor_id'] ?? 0);
                $hour = (int)($change['hour'] ?? -1);

                if ($advisorId <= 0 || $hour < 0 || $hour > 23) {
                    continue;
                }

                if ($action === 'add') {
                    $stmtInsert->execute([
                        ':schedule_id' => $id,
                        ':advisor_id' => $advisorId,
                        ':campaign_id' => $campaignId,
                        ':fecha' => $date,
                        ':hora' => $hour,
                    ]);
                    if ($stmtInsert->rowCount() > 0) {
                        $added++;
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

            echo json_encode([
                'success' => true,
                'added' => $added,
                'removed' => $removed,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error actualizando asignaciones: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al guardar cambios']);
        }

        exit;
    }

    public function mySchedule(): void
    {
        AuthService::requirePermission('schedules.view_own');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();
        $advisor = $this->resolveAdvisorByUser($pdo, $user);

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
        }

        $pageTitle = 'Mi Horario';
        $currentPage = 'my-schedule';

        include APP_PATH . '/Views/schedules/my-schedule.php';
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

        $stmt = $pdo->prepare("
            SELECT a.* FROM advisors a
            WHERE LOWER(a.nombres) LIKE LOWER(:first_name_like)
              AND LOWER(a.apellidos) LIKE LOWER(:last_name_like)
            ORDER BY a.id ASC
            LIMIT 1
        ");
        $stmt->execute([
            ':first_name_like' => '%' . $firstName . '%',
            ':last_name_like' => '%' . $lastName . '%',
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function canManageAllCampaigns(array $user): bool
    {
        return in_array($user['rol'] ?? '', ['admin', 'coordinador'], true);
    }

    private function syncMonthlyScheduleHeader(
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

    private function findMonthlySchedule(PDO $pdo, int $campaignId, string $fechaInicio): ?array
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

    private function countScheduleAssignments(PDO $pdo, int $scheduleId): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM shift_assignments WHERE schedule_id = :schedule_id");
        $stmt->execute([':schedule_id' => $scheduleId]);
        return (int)$stmt->fetchColumn();
    }

    private function buildScheduleAssignments(
        PDO $pdo,
        int $scheduleId,
        int $campaignId,
        string $fechaInicio,
        string $fechaFin
    ): int {
        $stmtCampaign = $pdo->prepare("
            SELECT
                id,
                tiene_velada,
                hora_inicio_operacion,
                hora_fin_operacion,
                requiere_vpn_nocturno,
                hora_inicio_nocturno,
                hora_fin_nocturno,
                max_horas_dia
            FROM campaigns
            WHERE id = :id
            LIMIT 1
        ");
        $stmtCampaign->execute([':id' => $campaignId]);
        $campaign = $stmtCampaign->fetch();
        if (!$campaign) {
            return 0;
        }

        $stmtCleanupOtherDrafts = $pdo->prepare("
            DELETE FROM shift_assignments sa
            USING schedules s
            WHERE sa.schedule_id = s.id
              AND sa.campaign_id = :campaign_id
              AND sa.fecha BETWEEN :fecha_inicio AND :fecha_fin
              AND s.id <> :schedule_id
              AND s.status IN ('borrador', 'rechazado')
        ");
        $stmtCleanupOtherDrafts->execute([
            ':campaign_id' => $campaignId,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin,
            ':schedule_id' => $scheduleId,
        ]);

        $stmtDeleteCurrent = $pdo->prepare("DELETE FROM shift_assignments WHERE schedule_id = :schedule_id");
        $stmtDeleteCurrent->execute([':schedule_id' => $scheduleId]);

        $stmtRequirements = $pdo->prepare("
            SELECT fecha::text AS fecha, hora, asesores_requeridos
            FROM staffing_requirements
            WHERE campaign_id = :campaign_id
              AND fecha BETWEEN :fecha_inicio AND :fecha_fin
              AND asesores_requeridos > 0
            ORDER BY fecha ASC, hora ASC
        ");
        $stmtRequirements->execute([
            ':campaign_id' => $campaignId,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin,
        ]);
        $requirements = $stmtRequirements->fetchAll();
        if (empty($requirements)) {
            return 0;
        }

        $stmtAdvisors = $pdo->prepare("
            SELECT
                a.id,
                a.hora_inicio_contrato,
                a.hora_fin_contrato,
                COALESCE(ac.tiene_vpn, false) AS tiene_vpn,
                COALESCE(ac.permite_extras, true) AS permite_extras,
                COALESCE(ac.max_horas_dia, :campaign_max_horas) AS max_horas_dia,
                COALESCE(ac.tiene_restriccion_medica, false) AS tiene_restriccion_medica,
                ac.restriccion_hora_inicio,
                ac.restriccion_hora_fin,
                ac.restriccion_fecha_hasta,
                COALESCE(ac.dias_descanso::text, '{}') AS dias_descanso
            FROM advisors a
            LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
            WHERE a.campaign_id = :campaign_id
              AND a.estado = 'activo'
            ORDER BY a.id ASC
        ");
        $stmtAdvisors->execute([
            ':campaign_id' => $campaignId,
            ':campaign_max_horas' => (int)$campaign['max_horas_dia'],
        ]);
        $advisors = $stmtAdvisors->fetchAll();
        if (empty($advisors)) {
            return 0;
        }

        foreach ($advisors as &$advisor) {
            $advisor['dias_descanso_parsed'] = $this->parseSmallIntArray($advisor['dias_descanso'] ?? '{}');
            if (empty($advisor['dias_descanso_parsed'])) {
                // Si no hay descanso configurado, asigna un dia fijo por semana para evitar 31/31 dias trabajados.
                $advisor['dias_descanso_parsed'] = [((int)$advisor['id']) % 7];
            }
            $advisor['max_horas_dia'] = (int)$advisor['max_horas_dia'];
            $advisor['permite_extras'] = $this->toBool($advisor['permite_extras']);
            $advisor['tiene_vpn'] = $this->toBool($advisor['tiene_vpn']);
            $advisor['tiene_restriccion_medica'] = $this->toBool($advisor['tiene_restriccion_medica']);
            // Restriccion de horario de contrato (ej: solo trabaja 9-18)
            $advisor['hora_inicio_contrato'] = $advisor['hora_inicio_contrato'] !== null ? (int)$advisor['hora_inicio_contrato'] : null;
            $advisor['hora_fin_contrato'] = $advisor['hora_fin_contrato'] !== null ? (int)$advisor['hora_fin_contrato'] : null;
        }
        unset($advisor);

        $monthAssignedHours = [];
        $daysWorked = [];            // [advisorId] => count de dias distintos trabajados

        // Calcular total de dias del periodo
        $startDt = new \DateTime($fechaInicio);
        $endDt = new \DateTime($fechaFin);
        $totalDaysInPeriod = (int)$startDt->diff($endDt)->days + 1;

        // Target de dias libres: maximo 2 por mes, equitativo para todos
        $targetFreeDays = min(4, max(2, (int)floor($totalDaysInPeriod / 10)));

        $dayAssignedHours = [];      // [advisorId][fecha] => count
        $dayAssignedHoursList = [];  // [advisorId][fecha] => [hour1, hour2, ...]
        $nightHoursToday = [];       // [advisorId][fecha] => count de horas nocturnas
        $insertedAssignments = 0;
        $coverageAlerts = [];        // Alertas cuando no se puede cubrir

        // Inicializar contadores
        foreach ($advisors as $advisor) {
            $monthAssignedHours[(int)$advisor['id']] = 0;
            $daysWorked[(int)$advisor['id']] = 0;
        }
        $stmtInsert = $pdo->prepare("
            INSERT INTO shift_assignments (
                schedule_id, advisor_id, campaign_id, fecha, hora, tipo, es_extra
            ) VALUES (
                :schedule_id, :advisor_id, :campaign_id, :fecha, :hora, :tipo, :es_extra
            )
            ON CONFLICT (advisor_id, fecha, hora) DO NOTHING
        ");

        foreach ($requirements as $requirement) {
            $fecha = (string)$requirement['fecha'];
            $hora = (int)$requirement['hora'];
            $requeridos = (int)$requirement['asesores_requeridos'];
            if ($requeridos <= 0) {
                continue;
            }

            if (!$this->toBool($campaign['tiene_velada'])) {
                if (!$this->isHourInRangeWrap(
                    $hora,
                    (int)$campaign['hora_inicio_operacion'],
                    (int)$campaign['hora_fin_operacion']
                )) {
                    continue;
                }
            }

            $isNightHour = $this->isNightHour(
                $hora,
                (int)$campaign['hora_inicio_nocturno'],
                (int)$campaign['hora_fin_nocturno']
            );
            $dayOfWeek = ((int)date('N', strtotime($fecha)) + 6) % 7;

            $alreadySelected = [];
            $selectedCount = 0;

            // PASADA 1: Buscar asesores respetando dias de descanso
            // PASADA 2: Si faltan, relajar dias de descanso (prioridad: dimensionamiento)
            for ($pass = 1; $pass <= 2 && $selectedCount < $requeridos; $pass++) {
                while ($selectedCount < $requeridos) {
                    $eligible = [];

                    foreach ($advisors as $advisor) {
                        $advisorId = (int)$advisor['id'];
                        if (isset($alreadySelected[$advisorId])) {
                            continue;
                        }

                        // En pasada 1: respetar dias de descanso
                        // En pasada 2: ignorar dias de descanso para cubrir dimensionamiento
                        $isRestDay = in_array($dayOfWeek, $advisor['dias_descanso_parsed'], true);
                        if ($pass === 1 && $isRestDay) {
                            continue;
                        }

                        // En pasada 2: verificar que tenga al menos 2 dias libres en el MES
                        // Prioridad: dimensionamiento. Solo proteger minimo 2 dias libres mensuales.
                        if ($pass === 2 && $isRestDay) {
                            $currentDaysWorkedAdvisor = $daysWorked[$advisorId] ?? 0;
                            $currentFreeDaysMonth = $totalDaysInPeriod - $currentDaysWorkedAdvisor;
                            // Minimo 2 dias libres por mes
                            if ($currentFreeDaysMonth <= 2) {
                                continue;
                            }
                        }

                        $currentDayHours = $dayAssignedHours[$advisorId][$fecha] ?? 0;
                        if ($currentDayHours >= $advisor['max_horas_dia']) {
                            continue;
                        }

                        if (!$advisor['permite_extras'] && $currentDayHours >= 8) {
                            continue;
                        }

                        if ($isNightHour && $this->toBool($campaign['requiere_vpn_nocturno']) && !$advisor['tiene_vpn']) {
                            continue;
                        }

                        // Limitar horas nocturnas por dia (max 8h en velada)
                        $currentNightHours = $nightHoursToday[$advisorId][$fecha] ?? 0;
                        if ($isNightHour && $currentNightHours >= 8) {
                            continue;
                        }

                        // Verificar restriccion de horario de contrato (ej: solo trabaja 8-18)
                        // Solo aplicar si NO es horario completo (0-23)
                        $horaInicioContrato = $advisor['hora_inicio_contrato'];
                        $horaFinContrato = $advisor['hora_fin_contrato'];
                        $tieneRestriccionHorario = $horaInicioContrato !== null
                            && $horaFinContrato !== null
                            && !($horaInicioContrato === 0 && $horaFinContrato === 23);

                        if ($tieneRestriccionHorario) {
                            if ($hora < $horaInicioContrato || $hora >= $horaFinContrato) {
                                continue;
                            }
                        }

                        if ($this->isMedicalRestrictionBlocking($advisor, $fecha, $hora)) {
                            continue;
                        }

                        // Calcular bonus de continuidad: priorizar si ya trabaja hora adyacente
                        $assignedHoursList = $dayAssignedHoursList[$advisorId][$fecha] ?? [];
                        $hasContinuity = in_array($hora - 1, $assignedHoursList, true) ||
                                         in_array($hora + 1, $assignedHoursList, true);
                        // Penalizar si crearia un hueco (ya tiene horas pero esta no es adyacente)
                        $wouldCreateGap = !empty($assignedHoursList) && !$hasContinuity;

                        // Calcular dias libres actuales de este asesor
                        $currentDaysWorked = $daysWorked[$advisorId] ?? 0;
                        $currentFreeDays = $totalDaysInPeriod - $currentDaysWorked;

                        // Calcular si ya trabajo hoy (para priorizar extender jornada vs nuevo dia)
                        $alreadyWorkingToday = !empty($dayAssignedHours[$advisorId][$fecha]);

                        $eligible[] = [
                            'id' => $advisorId,
                            'month_hours' => $monthAssignedHours[$advisorId] ?? 0,
                            'day_hours' => $currentDayHours,
                            'days_worked' => $currentDaysWorked,
                            'free_days' => $currentFreeDays,
                            'is_rest_day' => $isRestDay ? 1 : 0,
                            'has_continuity' => $hasContinuity ? 1 : 0,
                            'would_create_gap' => $wouldCreateGap ? 1 : 0,
                            'already_working_today' => $alreadyWorkingToday ? 1 : 0,
                        ];
                    }

                    if (empty($eligible)) {
                        break; // Salir del while, pasar a siguiente pasada
                    }

                    // Ordenar: priorizar equidad de dias libres y turnos corridos
                    // 1. NO en dia de descanso configurado
                    // 2. Ya esta trabajando hoy (extender jornada en vez de nuevo dia)
                    // 3. Tiene continuidad (ya trabaja hora adyacente)
                    // 4. NO crearia hueco
                    // 5. MAS dias libres (para equilibrar - quien tiene mas libres trabaja primero)
                    // 6. Menos horas mensuales (balancear horas)
                    // 7. Menos horas diarias
                    usort($eligible, static function (array $a, array $b): int {
                        return [
                            $a['is_rest_day'],
                            -$a['already_working_today'],  // Priorizar quien ya trabaja hoy
                            -$a['has_continuity'],
                            $a['would_create_gap'],
                            -$a['free_days'],              // Mas dias libres = trabaja primero
                            $a['month_hours'],
                            $a['day_hours'],
                            $a['id']
                        ] <=> [
                            $b['is_rest_day'],
                            -$b['already_working_today'],
                            -$b['has_continuity'],
                            $b['would_create_gap'],
                            -$b['free_days'],
                            $b['month_hours'],
                            $b['day_hours'],
                            $b['id']
                        ];
                    });

                    $candidate = $eligible[0];
                    $advisorId = (int)$candidate['id'];
                    $hoursTodayBeforeInsert = (int)$candidate['day_hours'];
                    $isExtra = $hoursTodayBeforeInsert >= 8;
                    $shiftType = $isNightHour ? 'nocturno' : ($isExtra ? 'extra' : 'normal');

                    $stmtInsert->execute([
                        ':schedule_id' => $scheduleId,
                        ':advisor_id' => $advisorId,
                        ':campaign_id' => $campaignId,
                        ':fecha' => $fecha,
                        ':hora' => $hora,
                        ':tipo' => $shiftType,
                        ':es_extra' => $isExtra ? 'true' : 'false',
                    ]);

                    $alreadySelected[$advisorId] = true;

                    if ($stmtInsert->rowCount() > 0) {
                        $selectedCount++;
                        $insertedAssignments++;

                        // Es primer hora de este asesor en este dia? Incrementar dias trabajados
                        $wasFirstHourToday = empty($dayAssignedHours[$advisorId][$fecha]);
                        if ($wasFirstHourToday) {
                            $daysWorked[$advisorId] = ($daysWorked[$advisorId] ?? 0) + 1;
                        }

                        $dayAssignedHours[$advisorId][$fecha] = ($dayAssignedHours[$advisorId][$fecha] ?? 0) + 1;
                        $monthAssignedHours[$advisorId] = ($monthAssignedHours[$advisorId] ?? 0) + 1;
                        // Rastrear lista de horas para calcular continuidad
                        if (!isset($dayAssignedHoursList[$advisorId][$fecha])) {
                            $dayAssignedHoursList[$advisorId][$fecha] = [];
                        }
                        $dayAssignedHoursList[$advisorId][$fecha][] = $hora;
                        // Rastrear horas nocturnas
                        if ($isNightHour) {
                            $nightHoursToday[$advisorId][$fecha] = ($nightHoursToday[$advisorId][$fecha] ?? 0) + 1;
                        }
                    }
                }
            }

            // Registrar alerta si no se cubrió el dimensionamiento
            if ($selectedCount < $requeridos) {
                $coverageAlerts[] = [
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'requeridos' => $requeridos,
                    'asignados' => $selectedCount,
                    'deficit' => $requeridos - $selectedCount,
                ];
            }
        }

        // Guardar alertas de cobertura en sesion si hay deficit
        if (!empty($coverageAlerts)) {
            $_SESSION['schedule_alerts'] = $coverageAlerts;
            $_SESSION['schedule_alerts_summary'] = sprintf(
                'Atencion: No se pudo cubrir el dimensionamiento en %d franjas horarias. Deficit total: %d horas-asesor.',
                count($coverageAlerts),
                array_sum(array_column($coverageAlerts, 'deficit'))
            );
        } else {
            unset($_SESSION['schedule_alerts'], $_SESSION['schedule_alerts_summary']);
        }

        return $insertedAssignments;
    }

    private function parseSmallIntArray(string $pgArray): array
    {
        $trimmed = trim($pgArray, '{}');
        if ($trimmed === '') {
            return [];
        }

        $items = array_map('trim', explode(',', $trimmed));
        $result = [];
        foreach ($items as $item) {
            if ($item === '' || !is_numeric($item)) {
                continue;
            }
            $result[] = (int)$item;
        }

        return array_values(array_unique($result));
    }

    private function isNightHour(int $hour, int $nightStart, int $nightEnd): bool
    {
        return $this->isHourInRangeWrap($hour, $nightStart, $nightEnd);
    }

    private function isHourInRangeWrap(int $hour, int $start, int $end): bool
    {
        if ($start <= $end) {
            return $hour >= $start && $hour <= $end;
        }

        return $hour >= $start || $hour <= $end;
    }

    private function isMedicalRestrictionBlocking(array $advisor, string $fecha, int $hora): bool
    {
        if (!$this->toBool($advisor['tiene_restriccion_medica'] ?? false)) {
            return false;
        }

        $hasta = $advisor['restriccion_fecha_hasta'] ?? null;
        if (!empty($hasta) && $fecha > (string)$hasta) {
            return false;
        }

        $inicio = $advisor['restriccion_hora_inicio'];
        $fin = $advisor['restriccion_hora_fin'];

        if ($inicio === null && $fin === null) {
            return true;
        }

        if ($inicio !== null && $fin !== null) {
            return $this->isHourInRangeWrap($hora, (int)$inicio, (int)$fin);
        }

        if ($inicio !== null) {
            return $hora >= (int)$inicio;
        }

        return $hora <= (int)$fin;
    }

    private function toBool(mixed $value): bool
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

    private function normalizeRequiredAdvisors(mixed $value): int
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

    private function setFlash(string $type, string $message): void
    {
        if ($type === 'success') {
            $_SESSION['flash_success'] = $message;
            return;
        }

        $_SESSION['flash_error'] = $message;
    }
}
