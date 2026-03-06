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

        // Supervisores solo ven asesores de sus campañas
        if ($this->canManageAllCampaigns($user)) {
            $stmt = $pdo->query("
                SELECT a.*, c.nombre as campaign_nombre,
                       ac.tiene_vpn, ac.permite_extras, ac.max_horas_dia as constraint_max_horas
                FROM advisors a
                JOIN campaigns c ON c.id = a.campaign_id
                LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
                ORDER BY a.apellidos, a.nombres
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT a.*, c.nombre as campaign_nombre,
                       ac.tiene_vpn, ac.permite_extras, ac.max_horas_dia as constraint_max_horas
                FROM advisors a
                JOIN campaigns c ON c.id = a.campaign_id
                LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
                WHERE c.supervisor_id = :supervisor_id
                ORDER BY a.apellidos, a.nombres
            ");
            $stmt->execute([':supervisor_id' => $user['id']]);
        }

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
                INSERT INTO advisor_constraints (advisor_id, tiene_vpn, permite_extras, max_horas_dia)
                VALUES (:advisor_id, :tiene_vpn, :permite_extras, :max_horas_dia)
            ");

            $stmt->execute([
                ':advisor_id' => $advisorId,
                ':tiene_vpn' => $tiene_vpn ? 'true' : 'false',
                ':permite_extras' => $permite_extras ? 'true' : 'false',
                ':max_horas_dia' => $max_horas_dia,
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

        $stmt = $pdo->prepare("
            SELECT a.*, ac.tiene_vpn, ac.permite_extras, ac.max_horas_dia as constraint_max_horas,
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

        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        $tipo_contrato = $_POST['tipo_contrato'] ?? 'completo';
        $estado = $_POST['estado'] ?? 'activo';
        $tiene_vpn = isset($_POST['tiene_vpn']);
        $permite_extras = isset($_POST['permite_extras']);
        $max_horas_dia = (int)($_POST['max_horas_dia'] ?? 10);
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                UPDATE advisors SET
                    nombres = :nombres,
                    apellidos = :apellidos,
                    cedula = :cedula,
                    campaign_id = :campaign_id,
                    tipo_contrato = :tipo_contrato,
                    estado = :estado
                WHERE id = :id
            ");

            $stmt->execute([
                ':nombres' => $nombres,
                ':apellidos' => $apellidos,
                ':cedula' => $cedula ?: null,
                ':campaign_id' => $campaign_id,
                ':tipo_contrato' => $tipo_contrato,
                ':estado' => $estado,
                ':id' => $id,
            ]);

            // Actualizar restricciones
            $stmt = $pdo->prepare("
                UPDATE advisor_constraints SET
                    tiene_vpn = :tiene_vpn,
                    permite_extras = :permite_extras,
                    max_horas_dia = :max_horas_dia,
                    updated_at = NOW()
                WHERE advisor_id = :advisor_id
            ");

            $stmt->execute([
                ':advisor_id' => $id,
                ':tiene_vpn' => $tiene_vpn ? 'true' : 'false',
                ':permite_extras' => $permite_extras ? 'true' : 'false',
                ':max_horas_dia' => $max_horas_dia,
            ]);

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        header('Location: ' . BASE_URL . '/advisors');
        exit;
    }

    public function constraints(int $id): void
    {
        AuthService::requirePermission('advisors.edit');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT a.*, ac.*, c.supervisor_id
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

        $pageTitle = 'Restricciones del Asesor';
        $currentPage = 'advisors';

        include APP_PATH . '/Views/advisors/constraints.php';
    }

    public function updateConstraints(int $id): void
    {
        AuthService::requirePermission('advisors.edit');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Verificar permisos sobre este asesor
        if (!$this->canAccessAdvisor($pdo, $id, $user)) {
            header('Location: ' . BASE_URL . '/advisors');
            exit;
        }

        $tiene_vpn = isset($_POST['tiene_vpn']);
        $permite_extras = isset($_POST['permite_extras']);
        $max_horas_dia = (int)($_POST['max_horas_dia'] ?? 10);
        $tiene_restriccion_medica = isset($_POST['tiene_restriccion_medica']);
        $descripcion_restriccion = trim($_POST['descripcion_restriccion'] ?? '');
        $restriccion_hora_inicio = $_POST['restriccion_hora_inicio'] !== '' ? (int)$_POST['restriccion_hora_inicio'] : null;
        $restriccion_hora_fin = $_POST['restriccion_hora_fin'] !== '' ? (int)$_POST['restriccion_hora_fin'] : null;
        $restriccion_fecha_hasta = trim($_POST['restriccion_fecha_hasta'] ?? '') ?: null;
        $dias_descanso = $_POST['dias_descanso'] ?? [];

        // Campos de horario fijo (van en tabla advisors)
        $hora_inicio_contrato = isset($_POST['hora_inicio_contrato']) && $_POST['hora_inicio_contrato'] !== '' ? (int)$_POST['hora_inicio_contrato'] : null;
        $hora_fin_contrato = isset($_POST['hora_fin_contrato']) && $_POST['hora_fin_contrato'] !== '' ? (int)$_POST['hora_fin_contrato'] : null;

        // Actualizar tabla advisors con horario de contrato
        $stmtAdvisor = $pdo->prepare("
            UPDATE advisors SET
                hora_inicio_contrato = :hora_inicio_contrato,
                hora_fin_contrato = :hora_fin_contrato
            WHERE id = :id
        ");
        $stmtAdvisor->execute([
            ':hora_inicio_contrato' => $hora_inicio_contrato,
            ':hora_fin_contrato' => $hora_fin_contrato,
            ':id' => $id,
        ]);

        $stmt = $pdo->prepare("
            UPDATE advisor_constraints SET
                tiene_vpn = :tiene_vpn,
                permite_extras = :permite_extras,
                max_horas_dia = :max_horas_dia,
                tiene_restriccion_medica = :tiene_restriccion_medica,
                descripcion_restriccion = :descripcion_restriccion,
                restriccion_hora_inicio = :restriccion_hora_inicio,
                restriccion_hora_fin = :restriccion_hora_fin,
                restriccion_fecha_hasta = :restriccion_fecha_hasta,
                dias_descanso = :dias_descanso,
                updated_at = NOW()
            WHERE advisor_id = :advisor_id
        ");

        $diasArray = '{' . implode(',', array_map('intval', $dias_descanso)) . '}';

        $stmt->execute([
            ':advisor_id' => $id,
            ':tiene_vpn' => $tiene_vpn ? 'true' : 'false',
            ':permite_extras' => $permite_extras ? 'true' : 'false',
            ':max_horas_dia' => $max_horas_dia,
            ':tiene_restriccion_medica' => $tiene_restriccion_medica ? 'true' : 'false',
            ':descripcion_restriccion' => $descripcion_restriccion ?: null,
            ':restriccion_hora_inicio' => $restriccion_hora_inicio,
            ':restriccion_hora_fin' => $restriccion_hora_fin,
            ':restriccion_fecha_hasta' => $restriccion_fecha_hasta,
            ':dias_descanso' => $diasArray,
        ]);

        header('Location: ' . BASE_URL . '/advisors');
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
        return in_array($user['rol'] ?? '', ['admin', 'coordinador'], true);
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
