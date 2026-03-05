<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;

class CampaignController
{
    public function index(): void
    {
        $user = $_SESSION['user'];

        // Solo coordinador puede ver todas las campañas
        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->query("
            SELECT c.*, u.nombre || ' ' || u.apellido as supervisor_nombre,
                   (SELECT COUNT(*) FROM advisors a WHERE a.campaign_id = c.id AND a.estado = 'activo') as total_asesores
            FROM campaigns c
            LEFT JOIN users u ON u.id = c.supervisor_id
            ORDER BY c.nombre
        ");
        $campaigns = $stmt->fetchAll();

        $pageTitle = 'Campanas';
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/campaigns/index.php';
    }

    public function create(): void
    {
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $pdo = Database::getConnection();

        // Obtener supervisores para el select
        $stmt = $pdo->query("
            SELECT id, nombre || ' ' || apellido as nombre_completo
            FROM users
            WHERE activo = true
            ORDER BY nombre
        ");
        $supervisors = $stmt->fetchAll();

        $pageTitle = 'Nueva Campana';
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/campaigns/create.php';
    }

    public function store(): void
    {
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $cliente = trim($_POST['cliente'] ?? '');
        $supervisor_id = (int)($_POST['supervisor_id'] ?? 0);
        $tiene_velada = isset($_POST['tiene_velada']) ? true : false;
        $requiere_vpn_nocturno = isset($_POST['requiere_vpn_nocturno']) ? true : false;
        $permite_horas_extra = isset($_POST['permite_horas_extra']) ? true : false;
        $hora_inicio_operacion = (int)($_POST['hora_inicio_operacion'] ?? 0);
        $hora_fin_operacion = (int)($_POST['hora_fin_operacion'] ?? 23);
        $max_horas_dia = (int)($_POST['max_horas_dia'] ?? 10);

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO campaigns (nombre, cliente, supervisor_id, tiene_velada, requiere_vpn_nocturno,
                                   permite_horas_extra, hora_inicio_operacion, hora_fin_operacion, max_horas_dia)
            VALUES (:nombre, :cliente, :supervisor_id, :tiene_velada, :requiere_vpn_nocturno,
                    :permite_horas_extra, :hora_inicio_operacion, :hora_fin_operacion, :max_horas_dia)
        ");

        $stmt->execute([
            ':nombre' => $nombre,
            ':cliente' => $cliente,
            ':supervisor_id' => $supervisor_id,
            ':tiene_velada' => $tiene_velada ? 'true' : 'false',
            ':requiere_vpn_nocturno' => $requiere_vpn_nocturno ? 'true' : 'false',
            ':permite_horas_extra' => $permite_horas_extra ? 'true' : 'false',
            ':hora_inicio_operacion' => $hora_inicio_operacion,
            ':hora_fin_operacion' => $hora_fin_operacion,
            ':max_horas_dia' => $max_horas_dia,
        ]);

        header('Location: ' . BASE_URL . '/campaigns');
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

        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $stmt = $pdo->query("
            SELECT id, nombre || ' ' || apellido as nombre_completo
            FROM users
            WHERE activo = true
            ORDER BY nombre
        ");
        $supervisors = $stmt->fetchAll();

        $pageTitle = 'Editar Campana';
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/campaigns/edit.php';
    }

    public function update(int $id): void
    {
        $user = $_SESSION['user'];

        if ($user['rol'] !== 'coordinador') {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $cliente = trim($_POST['cliente'] ?? '');
        $supervisor_id = (int)($_POST['supervisor_id'] ?? 0);
        $tiene_velada = isset($_POST['tiene_velada']) ? true : false;
        $requiere_vpn_nocturno = isset($_POST['requiere_vpn_nocturno']) ? true : false;
        $permite_horas_extra = isset($_POST['permite_horas_extra']) ? true : false;
        $hora_inicio_operacion = (int)($_POST['hora_inicio_operacion'] ?? 0);
        $hora_fin_operacion = (int)($_POST['hora_fin_operacion'] ?? 23);
        $max_horas_dia = (int)($_POST['max_horas_dia'] ?? 10);
        $estado = $_POST['estado'] ?? 'activa';

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE campaigns SET
                nombre = :nombre,
                cliente = :cliente,
                supervisor_id = :supervisor_id,
                tiene_velada = :tiene_velada,
                requiere_vpn_nocturno = :requiere_vpn_nocturno,
                permite_horas_extra = :permite_horas_extra,
                hora_inicio_operacion = :hora_inicio_operacion,
                hora_fin_operacion = :hora_fin_operacion,
                max_horas_dia = :max_horas_dia,
                estado = :estado
            WHERE id = :id
        ");

        $stmt->execute([
            ':nombre' => $nombre,
            ':cliente' => $cliente,
            ':supervisor_id' => $supervisor_id,
            ':tiene_velada' => $tiene_velada ? 'true' : 'false',
            ':requiere_vpn_nocturno' => $requiere_vpn_nocturno ? 'true' : 'false',
            ':permite_horas_extra' => $permite_horas_extra ? 'true' : 'false',
            ':hora_inicio_operacion' => $hora_inicio_operacion,
            ':hora_fin_operacion' => $hora_fin_operacion,
            ':max_horas_dia' => $max_horas_dia,
            ':estado' => $estado,
            ':id' => $id,
        ]);

        header('Location: ' . BASE_URL . '/campaigns');
        exit;
    }
}
