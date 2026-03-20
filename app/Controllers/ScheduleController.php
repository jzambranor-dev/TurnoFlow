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
require_once APP_PATH . '/Services/ScheduleBuilder.php';

class ScheduleController
{
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

        if (AuthService::canManageAllCampaigns($user)) {
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
            $this->setFlash('error', 'Datos de importación invalidos.');
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

        if (AuthService::canManageAllCampaigns($user)) {
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
            $this->setFlash('error', 'No tienes permisos sobre esa campaña o no esta activa.');
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

            // Detectar formato: buscar la fila de encabezado y las filas de horas dinámicamente
            // Soporta tanto "0:00" (formato Videollamada) como "10:00 - 11:00" (formato Kiosko)
            $headerRow = null;
            $hourRows = []; // [fila_excel => hora_int]

            $maxRow = min($sheet->getHighestRow(), 50); // Buscar en las primeras 50 filas

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

                // Detectar "TOTAL" para parar
                if (stripos($formatted, 'total') !== false) break;

                // Intentar con el valor formateado primero, luego con el raw
                $hora = $this->parseHourFromCell($formatted);
                if ($hora === null && $raw !== null) {
                    $hora = $this->parseHourFromCell(trim((string)$raw));
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
                    // Usar el nuevo ScheduleBuilder
                    $builder = new \App\Services\ScheduleBuilder($pdo);
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
            $regeneratedCampaigns = $this->regenerarCampañasFuente(
                $pdo, $campaignId, $fechaInicio, $fechaFin, (int)$user['id']
            );

            $msg = sprintf(
                'Importación completada para %s. Se guardaron %d registros (%02d/%04d), el horario mensual fue %s y se generaron %d asignaciónes.',
                $campaign['nombre'],
                count($requirements),
                $periodoMes,
                $periodoAnio,
                $scheduleAction,
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
                error_log('Error guardando detalle de importación: ' . $dbError->getMessage());
            }

            error_log('Importación fallida: ' . $e->getMessage());
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

        if (!AuthService::canManageAllCampaigns($user)) {
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

    public function deleteImport(int $importId): void
    {
        AuthService::requirePermission('schedules.generate');

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL . '/login');
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
            ");
            $stmt->execute([':id' => $importId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT si.*, c.nombre as campaign_nombre
                FROM staffing_imports si
                JOIN campaigns c ON c.id = si.campaign_id
                WHERE si.id = :id AND c.supervisor_id = :uid
            ");
            $stmt->execute([':id' => $importId, ':uid' => $user['id']]);
        }

        $importRow = $stmt->fetch();
        if (!$importRow) {
            $this->setFlash('error', 'Importación no encontrada o sin permisos.');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        // Verificar que el horario asociado no esté aprobado/enviado
        $periodoAnio = (int)$importRow['periodo_anio'];
        $periodoMes = (int)$importRow['periodo_mes'];
        $campaignId = (int)$importRow['campaign_id'];
        $fechaInicio = sprintf('%04d-%02d-01', $periodoAnio, $periodoMes);

        $scheduleRow = $this->findMonthlySchedule($pdo, $campaignId, $fechaInicio);
        if ($scheduleRow && in_array($scheduleRow['status'], ['aprobado', 'enviado'], true)) {
            $this->setFlash('error', 'No se puede eliminar: el horario esta en estado "' . $scheduleRow['status'] . '".');
            header('Location: ' . BASE_URL . '/schedules/generate');
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Si hay un schedule en borrador/rechazado, eliminar sus asignaciónes
            if ($scheduleRow) {
                $stmtDel = $pdo->prepare("DELETE FROM shift_assignments WHERE schedule_id = :sid");
                $stmtDel->execute([':sid' => $scheduleRow['id']]);

                $stmtDel = $pdo->prepare("DELETE FROM schedules WHERE id = :sid");
                $stmtDel->execute([':sid' => $scheduleRow['id']]);
            }

            // Eliminar import (CASCADE borra staffing_requirements)
            $stmtDel = $pdo->prepare("DELETE FROM staffing_imports WHERE id = :id");
            $stmtDel->execute([':id' => $importId]);

            // Eliminar archivo físico
            $uploadPath = $_ENV['UPLOAD_PATH'] ?? (dirname(__DIR__, 2) . '/uploads');
            $archivo = $importRow['archivo_nombre'] ?? '';
            if ($archivo !== '') {
                $filePath = $uploadPath . '/' . $archivo;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $pdo->commit();

            $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $this->setFlash(
                'success',
                sprintf(
                    'Importación de %s - %s %d eliminada correctamente.',
                    $importRow['campaign_nombre'],
                    $meses[$periodoMes] ?? $periodoMes,
                    $periodoAnio
                )
            );
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->setFlash('error', 'Error al eliminar: ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '/schedules/generate');
        exit;
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
            $action = $this->syncMonthlyScheduleHeader(
                $pdo,
                $campaignId,
                $periodoAnio,
                $periodoMes,
                $fechaInicio,
                $fechaFin,
                (int)$user['id']
            );

            $scheduleRow = $this->findMonthlySchedule($pdo, $campaignId, $fechaInicio);
            if (!$scheduleRow) {
                throw new RuntimeException('No se pudo crear la cabecera del horario.');
            }

            $existingAssignments = $this->countScheduleAssignments($pdo, (int)$scheduleRow['id']);
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
        $scheduleRow = $this->findMonthlySchedule($pdo, $campaignId, $fechaInicio);
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

        // Validar ownership
        $schedule = $this->getScheduleWithOwnership($pdo, $id);
        if (!$schedule || (!AuthService::canManageAllCampaigns($user) && (int)$schedule['supervisor_id'] !== (int)$user['id'])) {
            $this->setFlash('error', 'Sin permisos para aprobar este horario');
            header('Location: ' . BASE_URL . '/schedules');
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

        $records = (array)$input['records'];
        $validStatuses = ['presente', 'ausente', 'tardanza', 'salida_anticipada', 'licencia_medica', 'maternidad'];
        $saved = 0;

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

            foreach ($records as $rec) {
                $advisorId = (int)($rec['advisor_id'] ?? 0);
                $fecha = (string)($rec['fecha'] ?? '');
                $status = (string)($rec['status'] ?? 'presente');
                $notas = trim((string)($rec['notas'] ?? ''));

                if ($advisorId <= 0 || $fecha === '') continue;
                if (!in_array($status, $validStatuses, true)) $status = 'presente';

                // Validar que la fecha no sea futura (no puedes confirmar el futuro)
                if ($fecha > date('Y-m-d')) continue;

                $stmtUpsert->execute([
                    ':advisor_id' => $advisorId,
                    ':fecha' => $fecha,
                    ':status' => $status,
                    ':notas' => $notas ?: null,
                    ':registrado_por' => $user['id'],
                ]);
                $saved++;
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'saved' => $saved]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Error guardando asistencia: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al guardar']);
        }

        exit;
    }

    /**
     * API: Toggle check-in de un asesor para un dia.
     */
    public function toggleCheckin(int $scheduleId): void
    {
        AuthService::requireAnyPermission(['schedules.view', 'schedules.view_own']);

        header('Content-Type: application/json');

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id FROM schedules WHERE id = :id AND status = 'aprobado'");
        $stmt->execute([':id' => $scheduleId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Horario no encontrado o no aprobado']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $advisorId = (int)($input['advisor_id'] ?? 0);
        $fecha = (string)($input['fecha'] ?? '');

        if ($advisorId <= 0 || $fecha === '') {
            echo json_encode(['success' => false, 'error' => 'Datos invalidos']);
            exit;
        }

        // No permitir check-in en fechas futuras
        if ($fecha > date('Y-m-d')) {
            echo json_encode(['success' => false, 'error' => 'No se puede hacer check-in en fechas futuras']);
            exit;
        }

        try {
            // Toggle: si existe eliminar, si no existe insertar
            $stmt = $pdo->prepare("SELECT id FROM advisor_checkins WHERE advisor_id = :aid AND schedule_id = :sid AND fecha = :fecha");
            $stmt->execute([':aid' => $advisorId, ':sid' => $scheduleId, ':fecha' => $fecha]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $pdo->prepare("DELETE FROM advisor_checkins WHERE id = :id");
                $stmt->execute([':id' => $existing['id']]);
                echo json_encode(['success' => true, 'checked' => false]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO advisor_checkins (advisor_id, schedule_id, fecha)
                    VALUES (:aid, :sid, :fecha)
                ");
                $stmt->execute([':aid' => $advisorId, ':sid' => $scheduleId, ':fecha' => $fecha]);
                echo json_encode(['success' => true, 'checked' => true, 'time' => date('H:i')]);
            }
        } catch (Throwable $e) {
            error_log('Error en check-in: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al registrar check-in']);
        }

        exit;
    }

    private function getScheduleWithOwnership(\PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare("
            SELECT s.*, c.supervisor_id
            FROM schedules s
            JOIN campaigns c ON c.id = s.campaign_id
            WHERE s.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
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
            $scheduleRow = $this->findMonthlySchedule($pdo, $sourceCampaignId, $fechaInicio);
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

    /**
     * Extrae la hora (0-23) de un texto de celda.
     * Soporta formatos:
     *   "10:00"           → 10
     *   "10:00 - 11:00"   → 10
     *   "0:00"            → 0
     *   "9:00 - 10:00"    → 9
     *   Excel time serial (0.416667 = 10:00)
     */
    private function parseHourFromCell(string $cellValue): ?int
    {
        $cellValue = trim($cellValue);

        // Formato rango: "10:00 - 11:00" → extraer la primera hora
        if (preg_match('/^(\d{1,2})\s*:\s*\d{2}\s*-/', $cellValue, $m)) {
            return (int)$m[1];
        }

        // Formato simple: "10:00" o "0:00"
        if (preg_match('/^(\d{1,2})\s*:\s*\d{2}$/', $cellValue, $m)) {
            return (int)$m[1];
        }

        // Excel time serial (float entre 0 y 1): 0.0 = 0:00, 0.416667 = 10:00
        if (is_numeric($cellValue)) {
            $floatVal = (float)$cellValue;
            if ($floatVal >= 0 && $floatVal < 1) {
                return (int)round($floatVal * 24);
            }
            // Podría ser hora entera sin minutos: "10" → 10
            $intVal = (int)$floatVal;
            if ($intVal >= 0 && $intVal <= 23 && $floatVal == $intVal) {
                return $intVal;
            }
        }

        return null;
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
