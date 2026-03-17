<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use PDO;
use PDOException;
use Throwable;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';

class AdvisorController
{
    public function index(): void
    {
        AuthService::requirePermission('advisors.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Filtro por campaña (desde GET)
        $filterCampaignId = isset($_GET['campaign_id']) && $_GET['campaign_id'] !== ''
            ? (int)$_GET['campaign_id'] : null;

        // Cargar lista de campañas para el filtro
        if ($this->canManageAllCampaigns($user)) {
            $campaignsForFilter = $pdo->query("SELECT id, nombre FROM campaigns ORDER BY nombre")->fetchAll();
        } else {
            $stmtCf = $pdo->prepare("SELECT id, nombre FROM campaigns WHERE supervisor_id = :sid ORDER BY nombre");
            $stmtCf->execute([':sid' => $user['id']]);
            $campaignsForFilter = $stmtCf->fetchAll();
        }

        // Construir query de asesores con filtro opcional
        $where = [];
        $params = [];

        if (!$this->canManageAllCampaigns($user)) {
            $where[] = "c.supervisor_id = :supervisor_id";
            $params[':supervisor_id'] = $user['id'];
        }

        if ($filterCampaignId) {
            $where[] = "a.campaign_id = :filter_campaign_id";
            $params[':filter_campaign_id'] = $filterCampaignId;
        }

        $sql = "
            SELECT a.*, c.nombre as campaign_nombre,
                   ac.tiene_vpn, ac.permite_extras, ac.max_horas_dia as constraint_max_horas
            FROM advisors a
            JOIN campaigns c ON c.id = a.campaign_id
            LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
        ";
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY a.apellidos, a.nombres";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $advisors = $stmt->fetchAll();

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $pageTitle = 'Asesores';
        $currentPage = 'advisors';

        include APP_PATH . '/Views/advisors/index.php';
    }

    public function create(): void
    {
        AuthService::requirePermission('advisors.create');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Supervisores solo ven sus campañas
        if ($this->canManageAllCampaigns($user)) {
            $stmt = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        } else {
            $stmt = $pdo->prepare("
                SELECT id, nombre FROM campaigns
                WHERE estado = 'activa' AND supervisor_id = :supervisor_id
                ORDER BY nombre
            ");
            $stmt->execute([':supervisor_id' => $user['id']]);
        }

        $campaigns = $stmt->fetchAll();
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $pageTitle = 'Nuevo Asesor';
        $currentPage = 'advisors';

        include APP_PATH . '/Views/advisors/create.php';
    }

    public function store(): void
    {
        AuthService::requirePermission('advisors.create');

        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        $tipo_contrato = $_POST['tipo_contrato'] ?? 'completo';
        $tiene_vpn = isset($_POST['tiene_vpn']);
        $permite_extras = isset($_POST['permite_extras']);
        $max_horas_dia = (int)($_POST['max_horas_dia'] ?? 10);
        $modalidad_trabajo = $_POST['modalidad_trabajo'] ?? 'mixto';
        if (!in_array($modalidad_trabajo, ['presencial', 'teletrabajo', 'mixto'], true)) {
            $modalidad_trabajo = 'mixto';
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO advisors (nombres, apellidos, cedula, campaign_id, tipo_contrato)
                VALUES (:nombres, :apellidos, :cedula, :campaign_id, :tipo_contrato)
                RETURNING id
            ");

            $stmt->execute([
                ':nombres' => $nombres,
                ':apellidos' => $apellidos,
                ':cedula' => $cedula ?: null,
                ':campaign_id' => $campaign_id,
                ':tipo_contrato' => $tipo_contrato,
            ]);

            $advisorId = $stmt->fetchColumn();

            // Crear restricciones
            $stmt = $pdo->prepare("
                INSERT INTO advisor_constraints (advisor_id, tiene_vpn, permite_extras, max_horas_dia, modalidad_trabajo)
                VALUES (:advisor_id, :tiene_vpn, :permite_extras, :max_horas_dia, :modalidad_trabajo)
            ");

            $stmt->execute([
                ':advisor_id' => $advisorId,
                ':tiene_vpn' => $tiene_vpn ? 'true' : 'false',
                ':permite_extras' => $permite_extras ? 'true' : 'false',
                ':max_horas_dia' => $max_horas_dia,
                ':modalidad_trabajo' => $modalidad_trabajo,
            ]);

            $account = $this->createAdvisorUserAccount(
                $pdo,
                (int)$advisorId,
                $nombres,
                $apellidos,
                $cedula
            );

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $this->setFlash('error', $this->buildAdvisorErrorMessage($e));
            header('Location: ' . BASE_URL . '/advisors/create');
            exit;
        }

        $message = 'Asesor creado correctamente.';
        if (($account['created'] ?? false) && !empty($account['email'])) {
            $message .= sprintf(
                ' Usuario creado: %s (clave temporal: %s).',
                $account['email'],
                $account['password']
            );
        } elseif (!empty($account['email'])) {
            $message .= sprintf(' Ya existia un usuario asociado: %s.', $account['email']);
        }

        $this->setFlash('success', $message);

        header('Location: ' . BASE_URL . '/advisors');
        exit;
    }

    public function edit(int $id): void
    {
        AuthService::requirePermission('advisors.edit');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Traer TODOS los campos de advisor y advisor_constraints
        $stmt = $pdo->prepare("
            SELECT a.*,
                   ac.tiene_vpn,
                   ac.disponible_velada,
                   ac.permite_extras,
                   ac.max_horas_dia,
                   ac.permite_horario_partido,
                   ac.tiene_restriccion_medica,
                   ac.descripcion_restriccion,
                   ac.restriccion_hora_inicio,
                   ac.restriccion_hora_fin,
                   ac.restriccion_fecha_hasta,
                   ac.dias_descanso,
                   ac.modalidad_trabajo,
                   c.supervisor_id
            FROM advisors a
            LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
            JOIN campaigns c ON c.id = a.campaign_id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $advisor = $stmt->fetch();

        if (!$advisor) {
            header('Location: ' . BASE_URL . '/advisors');
            exit;
        }

        // Supervisores solo pueden editar asesores de sus campañas
        if (!$this->canManageAllCampaigns($user) && (int)$advisor['supervisor_id'] !== (int)$user['id']) {
            header('Location: ' . BASE_URL . '/advisors');
            exit;
        }

        // Supervisores solo ven sus campañas
        if ($this->canManageAllCampaigns($user)) {
            $stmt = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        } else {
            $stmt = $pdo->prepare("
                SELECT id, nombre FROM campaigns
                WHERE estado = 'activa' AND supervisor_id = :supervisor_id
                ORDER BY nombre
            ");
            $stmt->execute([':supervisor_id' => $user['id']]);
        }

        $campaigns = $stmt->fetchAll();

        $pageTitle = 'Editar Asesor';
        $currentPage = 'advisors';

        include APP_PATH . '/Views/advisors/edit.php';
    }

    public function update(int $id): void
    {
        AuthService::requirePermission('advisors.edit');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Verificar permisos sobre este asesor
        if (!$this->canAccessAdvisor($pdo, $id, $user)) {
            header('Location: ' . BASE_URL . '/advisors');
            exit;
        }

        // Datos basicos del asesor
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        $tipo_contrato = $_POST['tipo_contrato'] ?? 'completo';
        $estado = $_POST['estado'] ?? 'activo';

        // Permisos de trabajo
        $tiene_vpn = isset($_POST['tiene_vpn']);
        $disponible_velada = isset($_POST['disponible_velada']);
        $permite_extras = isset($_POST['permite_extras']);
        $max_horas_dia = (int)($_POST['max_horas_dia'] ?? 10);
        $permite_horario_partido = ($_POST['permite_horario_partido'] ?? '1') === '1';
        $modalidad_trabajo = $_POST['modalidad_trabajo'] ?? 'mixto';
        if (!in_array($modalidad_trabajo, ['presencial', 'teletrabajo', 'mixto'], true)) {
            $modalidad_trabajo = 'mixto';
        }

        // Horario de contrato
        $hora_inicio_contrato = isset($_POST['hora_inicio_contrato']) && $_POST['hora_inicio_contrato'] !== ''
            ? (int)$_POST['hora_inicio_contrato'] : null;
        $hora_fin_contrato = isset($_POST['hora_fin_contrato']) && $_POST['hora_fin_contrato'] !== ''
            ? (int)$_POST['hora_fin_contrato'] : null;

        // Dias de descanso
        $dias_descanso = $_POST['dias_descanso'] ?? [];
        $diasArray = '{' . implode(',', array_map('intval', $dias_descanso)) . '}';

        // Restriccion medica
        $tiene_restriccion_medica = isset($_POST['tiene_restriccion_medica']);
        $descripcion_restriccion = trim($_POST['descripcion_restriccion'] ?? '');
        $restriccion_hora_inicio = isset($_POST['restriccion_hora_inicio']) && $_POST['restriccion_hora_inicio'] !== ''
            ? (int)$_POST['restriccion_hora_inicio'] : null;
        $restriccion_hora_fin = isset($_POST['restriccion_hora_fin']) && $_POST['restriccion_hora_fin'] !== ''
            ? (int)$_POST['restriccion_hora_fin'] : null;
        $restriccion_fecha_hasta = trim($_POST['restriccion_fecha_hasta'] ?? '') ?: null;

        $pdo->beginTransaction();

        try {
            // Actualizar tabla advisors (datos basicos + horario contrato)
            $stmt = $pdo->prepare("
                UPDATE advisors SET
                    nombres = :nombres,
                    apellidos = :apellidos,
                    cedula = :cedula,
                    campaign_id = :campaign_id,
                    tipo_contrato = :tipo_contrato,
                    estado = :estado,
                    hora_inicio_contrato = :hora_inicio_contrato,
                    hora_fin_contrato = :hora_fin_contrato
                WHERE id = :id
            ");

            $stmt->execute([
                ':nombres' => $nombres,
                ':apellidos' => $apellidos,
                ':cedula' => $cedula ?: null,
                ':campaign_id' => $campaign_id,
                ':tipo_contrato' => $tipo_contrato,
                ':estado' => $estado,
                ':hora_inicio_contrato' => $hora_inicio_contrato,
                ':hora_fin_contrato' => $hora_fin_contrato,
                ':id' => $id,
            ]);

            // Actualizar TODAS las restricciones en advisor_constraints
            $stmt = $pdo->prepare("
                UPDATE advisor_constraints SET
                    tiene_vpn = :tiene_vpn,
                    disponible_velada = :disponible_velada,
                    permite_extras = :permite_extras,
                    max_horas_dia = :max_horas_dia,
                    permite_horario_partido = :permite_horario_partido,
                    tiene_restriccion_medica = :tiene_restriccion_medica,
                    descripcion_restriccion = :descripcion_restriccion,
                    restriccion_hora_inicio = :restriccion_hora_inicio,
                    restriccion_hora_fin = :restriccion_hora_fin,
                    restriccion_fecha_hasta = :restriccion_fecha_hasta,
                    dias_descanso = :dias_descanso,
                    modalidad_trabajo = :modalidad_trabajo,
                    updated_at = NOW()
                WHERE advisor_id = :advisor_id
            ");

            $stmt->execute([
                ':advisor_id' => $id,
                ':tiene_vpn' => $tiene_vpn ? 'true' : 'false',
                ':disponible_velada' => $disponible_velada ? 'true' : 'false',
                ':permite_extras' => $permite_extras ? 'true' : 'false',
                ':max_horas_dia' => $max_horas_dia,
                ':permite_horario_partido' => $permite_horario_partido ? 'true' : 'false',
                ':tiene_restriccion_medica' => $tiene_restriccion_medica ? 'true' : 'false',
                ':descripcion_restriccion' => $descripcion_restriccion ?: null,
                ':restriccion_hora_inicio' => $restriccion_hora_inicio,
                ':restriccion_hora_fin' => $restriccion_hora_fin,
                ':restriccion_fecha_hasta' => $restriccion_fecha_hasta,
                ':dias_descanso' => $diasArray,
                ':modalidad_trabajo' => $modalidad_trabajo,
            ]);

            $pdo->commit();
            $this->setFlash('success', 'Asesor actualizado correctamente.');
        } catch (\Exception $e) {
            $pdo->rollBack();
            $this->setFlash('error', 'Error al guardar: ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '/advisors');
        exit;
    }

    public function bulkConfig(): void
    {
        AuthService::requirePermission('advisors.edit');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Cargar campañas según permisos
        if ($this->canManageAllCampaigns($user)) {
            $campaigns = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre")->fetchAll();
        } else {
            $stmt = $pdo->prepare("SELECT id, nombre FROM campaigns WHERE estado = 'activa' AND supervisor_id = :sid ORDER BY nombre");
            $stmt->execute([':sid' => $user['id']]);
            $campaigns = $stmt->fetchAll();
        }

        // Si se selecciónó una campaña, cargar sus asesores
        $selectedCampaignId = isset($_GET['campaign_id']) && $_GET['campaign_id'] !== '' ? (int)$_GET['campaign_id'] : null;
        $advisors = [];

        if ($selectedCampaignId) {
            // Verificar acceso a la campaña
            $canAccess = false;
            foreach ($campaigns as $c) {
                if ((int)$c['id'] === $selectedCampaignId) {
                    $canAccess = true;
                    break;
                }
            }

            if ($canAccess) {
                $stmt = $pdo->prepare("
                    SELECT a.id, a.nombres, a.apellidos, a.estado,
                           a.hora_inicio_contrato, a.hora_fin_contrato,
                           ac.tiene_vpn, ac.disponible_velada, ac.permite_extras,
                           ac.max_horas_dia, ac.permite_horario_partido, ac.dias_descanso,
                           ac.modalidad_trabajo
                    FROM advisors a
                    LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
                    WHERE a.campaign_id = :cid AND a.estado = 'activo'
                    ORDER BY a.apellidos, a.nombres
                ");
                $stmt->execute([':cid' => $selectedCampaignId]);
                $advisors = $stmt->fetchAll();
            }
        }

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $pageTitle = 'Configuración Masiva';
        $currentPage = 'advisors';

        include APP_PATH . '/Views/advisors/bulk-config.php';
    }

    public function bulkConfigStore(): void
    {
        AuthService::requirePermission('advisors.edit');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $campaignId = (int)($_POST['campaign_id'] ?? 0);
        $advisorIds = $_POST['advisor_ids'] ?? [];
        $fields = $_POST['fields'] ?? [];

        if ($campaignId <= 0 || empty($advisorIds) || empty($fields)) {
            $this->setFlash('error', 'Debes selecciónar una campaña, al menos un asesor y al menos un campo a modificar.');
            header('Location: ' . BASE_URL . '/advisors/bulk-config?campaign_id=' . $campaignId);
            exit;
        }

        // Verificar acceso a la campaña
        if (!$this->canManageAllCampaigns($user)) {
            $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = :id AND supervisor_id = :sid");
            $stmt->execute([':id' => $campaignId, ':sid' => $user['id']]);
            if (!$stmt->fetchColumn()) {
                $this->setFlash('error', 'No tienes permisos sobre esta campaña.');
                header('Location: ' . BASE_URL . '/advisors/bulk-config');
                exit;
            }
        }

        // Sanitizar IDs de asesores
        $advisorIds = array_map('intval', $advisorIds);
        $placeholders = implode(',', array_fill(0, count($advisorIds), '?'));

        // Verificar que los asesores pertenecen a la campaña
        $stmt = $pdo->prepare("SELECT id FROM advisors WHERE id IN ($placeholders) AND campaign_id = ?");
        $params = array_merge($advisorIds, [$campaignId]);
        $stmt->execute($params);
        $validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($validIds)) {
            $this->setFlash('error', 'Ninguno de los asesores selecciónados pertenece a esta campaña.');
            header('Location: ' . BASE_URL . '/advisors/bulk-config?campaign_id=' . $campaignId);
            exit;
        }

        // Construir SET dinámico para advisor_constraints
        $setClauses = [];
        $setParams = [];

        // Campos para tabla advisors (horario contrato)
        $advisorSetClauses = [];
        $advisorSetParams = [];

        if (in_array('tiene_vpn', $fields, true)) {
            $setClauses[] = "tiene_vpn = :tiene_vpn";
            $setParams[':tiene_vpn'] = isset($_POST['tiene_vpn']) ? 'true' : 'false';
        }
        if (in_array('disponible_velada', $fields, true)) {
            $setClauses[] = "disponible_velada = :disponible_velada";
            $setParams[':disponible_velada'] = isset($_POST['disponible_velada']) ? 'true' : 'false';
        }
        if (in_array('permite_extras', $fields, true)) {
            $setClauses[] = "permite_extras = :permite_extras";
            $setParams[':permite_extras'] = isset($_POST['permite_extras']) ? 'true' : 'false';
        }
        if (in_array('max_horas_dia', $fields, true)) {
            $setClauses[] = "max_horas_dia = :max_horas_dia";
            $setParams[':max_horas_dia'] = max(8, min(16, (int)($_POST['max_horas_dia'] ?? 10)));
        }
        if (in_array('permite_horario_partido', $fields, true)) {
            $setClauses[] = "permite_horario_partido = :permite_horario_partido";
            $setParams[':permite_horario_partido'] = ($_POST['permite_horario_partido'] ?? '1') === '1' ? 'true' : 'false';
        }
        if (in_array('dias_descanso', $fields, true)) {
            $dias = $_POST['dias_descanso'] ?? [];
            $diasArray = '{' . implode(',', array_map('intval', $dias)) . '}';
            $setClauses[] = "dias_descanso = :dias_descanso";
            $setParams[':dias_descanso'] = $diasArray;
        }
        if (in_array('modalidad_trabajo', $fields, true)) {
            $modalidad = $_POST['modalidad_trabajo'] ?? 'mixto';
            if (!in_array($modalidad, ['presencial', 'teletrabajo', 'mixto'], true)) {
                $modalidad = 'mixto';
            }
            $setClauses[] = "modalidad_trabajo = :modalidad_trabajo";
            $setParams[':modalidad_trabajo'] = $modalidad;
        }
        if (in_array('restriccion_medica', $fields, true)) {
            $tieneRestr = isset($_POST['tiene_restriccion_medica']);
            $setClauses[] = "tiene_restriccion_medica = :tiene_restriccion_medica";
            $setParams[':tiene_restriccion_medica'] = $tieneRestr ? 'true' : 'false';
            $setClauses[] = "descripcion_restriccion = :descripcion_restriccion";
            $setParams[':descripcion_restriccion'] = $tieneRestr ? (trim($_POST['descripcion_restriccion'] ?? '') ?: null) : null;
            $setClauses[] = "restriccion_hora_inicio = :restriccion_hora_inicio";
            $setParams[':restriccion_hora_inicio'] = ($tieneRestr && isset($_POST['restriccion_hora_inicio']) && $_POST['restriccion_hora_inicio'] !== '') ? (int)$_POST['restriccion_hora_inicio'] : null;
            $setClauses[] = "restriccion_hora_fin = :restriccion_hora_fin";
            $setParams[':restriccion_hora_fin'] = ($tieneRestr && isset($_POST['restriccion_hora_fin']) && $_POST['restriccion_hora_fin'] !== '') ? (int)$_POST['restriccion_hora_fin'] : null;
            $setClauses[] = "restriccion_fecha_hasta = :restriccion_fecha_hasta";
            $setParams[':restriccion_fecha_hasta'] = ($tieneRestr && !empty(trim($_POST['restriccion_fecha_hasta'] ?? ''))) ? trim($_POST['restriccion_fecha_hasta']) : null;
        }
        if (in_array('horario_contrato', $fields, true)) {
            $horaInicio = isset($_POST['hora_inicio_contrato']) ? (int)$_POST['hora_inicio_contrato'] : 0;
            $horaFin = isset($_POST['hora_fin_contrato']) ? (int)$_POST['hora_fin_contrato'] : 23;
            $advisorSetClauses[] = "hora_inicio_contrato = :hora_inicio_contrato";
            $advisorSetParams[':hora_inicio_contrato'] = max(0, min(23, $horaInicio));
            $advisorSetClauses[] = "hora_fin_contrato = :hora_fin_contrato";
            $advisorSetParams[':hora_fin_contrato'] = max(0, min(23, $horaFin));
        }

        if (empty($setClauses) && empty($advisorSetClauses)) {
            $this->setFlash('error', 'No hay campos validos para actualizar.');
            header('Location: ' . BASE_URL . '/advisors/bulk-config?campaign_id=' . $campaignId);
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Named placeholders para IN clause
            $idNamedPlaceholders = [];
            foreach ($validIds as $i => $vid) {
                $key = ':adv_id_' . $i;
                $idNamedPlaceholders[] = $key;
            }
            $inClause = implode(',', $idNamedPlaceholders);

            // Actualizar advisor_constraints
            $affected = 0;
            if (!empty($setClauses)) {
                $setClauses[] = "updated_at = NOW()";
                foreach ($validIds as $i => $vid) {
                    $setParams[':adv_id_' . $i] = $vid;
                }
                $sql = "UPDATE advisor_constraints SET " . implode(', ', $setClauses) . " WHERE advisor_id IN ($inClause)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($setParams);
                $affected = $stmt->rowCount();
            }

            // Actualizar tabla advisors (horario contrato)
            if (!empty($advisorSetClauses)) {
                foreach ($validIds as $i => $vid) {
                    $advisorSetParams[':adv_id_' . $i] = $vid;
                }
                $sql = "UPDATE advisors SET " . implode(', ', $advisorSetClauses) . " WHERE id IN ($inClause)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($advisorSetParams);
                $affected = max($affected, $stmt->rowCount());
            }

            $pdo->commit();
            $this->setFlash('success', "Configuración masiva aplicada a {$affected} asesores correctamente.");
        } catch (\Exception $e) {
            $pdo->rollBack();
            $this->setFlash('error', 'Error al aplicar configuración masiva: ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '/advisors/bulk-config?campaign_id=' . $campaignId);
        exit;
    }

    private function createAdvisorUserAccount(
        PDO $pdo,
        int $advisorId,
        string $nombres,
        string $apellidos,
        string $cedula
    ): array {
        $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE nombre = 'asesor' LIMIT 1");
        $stmtRole->execute();
        $asesorRoleId = $stmtRole->fetchColumn();

        if (!$asesorRoleId) {
            return ['created' => false, 'email' => null, 'password' => null];
        }

        $email = $this->buildAdvisorEmail($pdo, $advisorId, $cedula);

        $stmtExists = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmtExists->execute([':email' => $email]);
        if ($stmtExists->fetchColumn()) {
            return ['created' => false, 'email' => $email, 'password' => null];
        }

        $passwordPlain = $cedula !== '' ? $cedula : 'asesor123';
        $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);

        $stmtInsert = $pdo->prepare("
            INSERT INTO users (nombre, apellido, email, password_hash, rol_id, activo)
            VALUES (:nombre, :apellido, :email, :password_hash, :rol_id, true)
        ");
        $stmtInsert->execute([
            ':nombre' => $nombres,
            ':apellido' => $apellidos,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':rol_id' => (int)$asesorRoleId,
        ]);

        return ['created' => true, 'email' => $email, 'password' => $passwordPlain];
    }

    private function buildAdvisorEmail(PDO $pdo, int $advisorId, string $cedula): string
    {
        $baseLocal = $cedula !== ''
            ? 'asesor' . preg_replace('/[^0-9a-zA-Z]/', '', $cedula)
            : 'asesor' . $advisorId;

        $baseLocal = strtolower($baseLocal ?: ('asesor' . $advisorId));
        $email = $baseLocal . '@turnoflow.local';
        $suffix = 1;

        while (true) {
            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            if (!$stmt->fetchColumn()) {
                return $email;
            }

            $email = $baseLocal . $suffix . '@turnoflow.local';
            $suffix++;
        }
    }

    private function buildAdvisorErrorMessage(Throwable $e): string
    {
        if ($e instanceof PDOException && (($e->errorInfo[0] ?? '') === '23505')) {
            $detail = $e->errorInfo[2] ?? '';
            if (strpos($detail, 'advisors_cedula_key') !== false) {
                return 'La cedula ya existe en otro asesor.';
            }
            if (strpos($detail, 'users_email_key') !== false) {
                return 'El usuario no pudo crearse porque el email ya existe.';
            }
            return 'No se pudo guardar porque existe un dato duplicado.';
        }

        return 'No se pudo crear el asesor: ' . $e->getMessage();
    }

    private function setFlash(string $type, string $message): void
    {
        if ($type === 'success') {
            $_SESSION['flash_success'] = $message;
            return;
        }

        $_SESSION['flash_error'] = $message;
    }

    private function canManageAllCampaigns(array $user): bool
    {
        return in_array($user['rol'] ?? '', ['admin', 'gerente', 'coordinador'], true);
    }

    private function canAccessAdvisor(\PDO $pdo, int $advisorId, array $user): bool
    {
        if ($this->canManageAllCampaigns($user)) {
            return true;
        }

        $stmt = $pdo->prepare("
            SELECT 1 FROM advisors a
            JOIN campaigns c ON c.id = a.campaign_id
            WHERE a.id = :advisor_id AND c.supervisor_id = :supervisor_id
        ");
        $stmt->execute([
            ':advisor_id' => $advisorId,
            ':supervisor_id' => $user['id'],
        ]);

        return (bool)$stmt->fetchColumn();
    }
}
