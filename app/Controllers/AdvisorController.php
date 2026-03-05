<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;

class AdvisorController
{
    public function index(): void
    {
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->query("
            SELECT a.*, c.nombre as campaign_nombre,
                   ac.tiene_vpn, ac.permite_extras, ac.max_horas_dia as constraint_max_horas
            FROM advisors a
            JOIN campaigns c ON c.id = a.campaign_id
            LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
            ORDER BY a.apellidos, a.nombres
        ");
        $advisors = $stmt->fetchAll();

        $pageTitle = 'Asesores';
        $currentPage = 'advisors';

        include APP_PATH . '/Views/advisors/index.php';
    }

    public function create(): void
    {
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        $campaigns = $stmt->fetchAll();

        $pageTitle = 'Nuevo Asesor';
        $currentPage = 'advisors';

        include APP_PATH . '/Views/advisors/create.php';
    }

    public function store(): void
    {
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

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

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        header('Location: ' . BASE_URL . '/advisors');
        exit;
    }

    public function edit(int $id): void
    {
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT a.*, ac.tiene_vpn, ac.permite_extras, ac.max_horas_dia as constraint_max_horas
            FROM advisors a
            LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $advisor = $stmt->fetch();

        if (!$advisor) {
            header('Location: ' . BASE_URL . '/advisors');
            exit;
        }

        $stmt = $pdo->query("SELECT id, nombre FROM campaigns WHERE estado = 'activa' ORDER BY nombre");
        $campaigns = $stmt->fetchAll();

        $pageTitle = 'Editar Asesor';
        $currentPage = 'advisors';

        include APP_PATH . '/Views/advisors/edit.php';
    }

    public function update(int $id): void
    {
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/dashboard');
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

        $pdo = Database::getConnection();
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
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT a.*, ac.*
            FROM advisors a
            LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $advisor = $stmt->fetch();

        if (!$advisor) {
            header('Location: ' . BASE_URL . '/advisors');
            exit;
        }

        $pageTitle = 'Restricciones del Asesor';
        $currentPage = 'advisors';

        include APP_PATH . '/Views/advisors/constraints.php';
    }

    public function updateConstraints(int $id): void
    {
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/dashboard');
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

        $pdo = Database::getConnection();

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
}
