<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';

class CampaignController
{
    public function index(): void
    {
        AuthService::requirePermission('campaigns.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Supervisores solo ven sus campañas
        if (AuthService::canManageAllCampaigns($user)) {
            $stmt = $pdo->query("
                SELECT c.*, u.nombre || ' ' || u.apellido as supervisor_nombre,
                       (SELECT COUNT(*) FROM advisors a WHERE a.campaign_id = c.id AND a.estado = 'activo') as total_asesores
                FROM campaigns c
                LEFT JOIN users u ON u.id = c.supervisor_id
                ORDER BY c.nombre
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT c.*, u.nombre || ' ' || u.apellido as supervisor_nombre,
                       (SELECT COUNT(*) FROM advisors a WHERE a.campaign_id = c.id AND a.estado = 'activo') as total_asesores
                FROM campaigns c
                LEFT JOIN users u ON u.id = c.supervisor_id
                WHERE c.supervisor_id = :supervisor_id
                ORDER BY c.nombre
            ");
            $stmt->execute([':supervisor_id' => $user['id']]);
        }

        $campaigns = $stmt->fetchAll();

        $pageTitle = 'Campanas';
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/campaigns/index.php';
    }

    public function create(): void
    {
        AuthService::requirePermission('campaigns.create');

        $pdo = Database::getConnection();

        // Obtener supervisores para el select
        $stmt = $pdo->query("
            SELECT u.id, u.nombre || ' ' || u.apellido as nombre_completo
            FROM users u
            JOIN roles r ON r.id = u.rol_id
            WHERE u.activo = true AND r.nombre = 'supervisor'
            ORDER BY u.nombre
        ");
        $supervisors = $stmt->fetchAll();

        $pageTitle = 'Nueva Campana';
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/campaigns/create.php';
    }

    public function store(): void
    {
        AuthService::requirePermission('campaigns.create');

        $nombre = trim($_POST['nombre'] ?? '');
        $cliente = trim($_POST['cliente'] ?? '');
        $supervisor_id = (int)($_POST['supervisor_id'] ?? 0);
        $tiene_velada = isset($_POST['tiene_velada']);
        $requiere_vpn_nocturno = isset($_POST['requiere_vpn_nocturno']);
        $permite_horas_extra = isset($_POST['permite_horas_extra']);
        $tiene_break = isset($_POST['tiene_break']);
        $duracion_break_min = max(15, min(60, (int)($_POST['duracion_break_min'] ?? 30)));
        $hora_inicio_operacion = (int)($_POST['hora_inicio_operacion'] ?? 0);
        $hora_fin_operacion = (int)($_POST['hora_fin_operacion'] ?? 23);
        $max_horas_dia = (int)($_POST['max_horas_dia'] ?? 10);

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO campaigns (nombre, cliente, supervisor_id, tiene_velada, requiere_vpn_nocturno,
                                   permite_horas_extra, hora_inicio_operacion, hora_fin_operacion, max_horas_dia,
                                   tiene_break, duracion_break_min)
            VALUES (:nombre, :cliente, :supervisor_id, :tiene_velada, :requiere_vpn_nocturno,
                    :permite_horas_extra, :hora_inicio_operacion, :hora_fin_operacion, :max_horas_dia,
                    :tiene_break, :duracion_break_min)
        ");

        $stmt->execute([
            ':nombre' => $nombre,
            ':cliente' => $cliente,
            ':supervisor_id' => $supervisor_id,
            ':tiene_velada' => $tiene_velada ? 'true' : 'false',
            ':requiere_vpn_nocturno' => $requiere_vpn_nocturno ? 'true' : 'false',
            ':permite_horas_extra' => $permite_horas_extra ? 'true' : 'false',
            ':tiene_break' => $tiene_break ? 'true' : 'false',
            ':duracion_break_min' => $duracion_break_min,
            ':hora_inicio_operacion' => $hora_inicio_operacion,
            ':hora_fin_operacion' => $hora_fin_operacion,
            ':max_horas_dia' => $max_horas_dia,
        ]);

        header('Location: ' . BASE_URL . '/campaigns');
        exit;
    }

    public function edit(int $id): void
    {
        AuthService::requirePermission('campaigns.edit');

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $stmt = $pdo->query("
            SELECT u.id, u.nombre || ' ' || u.apellido as nombre_completo
            FROM users u
            JOIN roles r ON r.id = u.rol_id
            WHERE u.activo = true AND r.nombre = 'supervisor'
            ORDER BY u.nombre
        ");
        $supervisors = $stmt->fetchAll();

        $pageTitle = 'Editar Campana';
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/campaigns/edit.php';
    }

    public function update(int $id): void
    {
        AuthService::requirePermission('campaigns.edit');

        $nombre = trim($_POST['nombre'] ?? '');
        $cliente = trim($_POST['cliente'] ?? '');
        $supervisor_id = (int)($_POST['supervisor_id'] ?? 0);
        $tiene_velada = isset($_POST['tiene_velada']);
        $requiere_vpn_nocturno = isset($_POST['requiere_vpn_nocturno']);
        $permite_horas_extra = isset($_POST['permite_horas_extra']);
        $tiene_break = isset($_POST['tiene_break']);
        $duracion_break_min = max(15, min(60, (int)($_POST['duracion_break_min'] ?? 30)));
        $hora_inicio_operacion = (int)($_POST['hora_inicio_operacion'] ?? 0);
        $hora_fin_operacion = (int)($_POST['hora_fin_operacion'] ?? 23);
        $max_horas_dia = (int)($_POST['max_horas_dia'] ?? 10);
        $estado = $_POST['estado'] ?? 'activa';

        // Configuración de velada
        $hora_fin_velada = (int)($_POST['hora_fin_velada'] ?? 8);
        $hora_inicio_teletrabajo = (int)($_POST['hora_inicio_teletrabajo'] ?? 0);
        $hora_fin_teletrabajo_manana = (int)($_POST['hora_fin_teletrabajo_manana'] ?? 9);
        $hora_inicio_presencial = (int)($_POST['hora_inicio_presencial'] ?? 9);
        $hora_fin_presencial = (int)($_POST['hora_fin_presencial'] ?? 19);

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE campaigns SET
                nombre = :nombre,
                cliente = :cliente,
                supervisor_id = :supervisor_id,
                tiene_velada = :tiene_velada,
                requiere_vpn_nocturno = :requiere_vpn_nocturno,
                permite_horas_extra = :permite_horas_extra,
                tiene_break = :tiene_break,
                duracion_break_min = :duracion_break_min,
                hora_inicio_operacion = :hora_inicio_operacion,
                hora_fin_operacion = :hora_fin_operacion,
                max_horas_dia = :max_horas_dia,
                estado = :estado,
                hora_fin_velada = :hora_fin_velada,
                hora_inicio_teletrabajo = :hora_inicio_teletrabajo,
                hora_fin_teletrabajo_manana = :hora_fin_teletrabajo_manana,
                hora_inicio_presencial = :hora_inicio_presencial,
                hora_fin_presencial = :hora_fin_presencial
            WHERE id = :id
        ");

        $stmt->execute([
            ':nombre' => $nombre,
            ':cliente' => $cliente,
            ':supervisor_id' => $supervisor_id,
            ':tiene_velada' => $tiene_velada ? 'true' : 'false',
            ':requiere_vpn_nocturno' => $requiere_vpn_nocturno ? 'true' : 'false',
            ':permite_horas_extra' => $permite_horas_extra ? 'true' : 'false',
            ':tiene_break' => $tiene_break ? 'true' : 'false',
            ':duracion_break_min' => $duracion_break_min,
            ':hora_inicio_operacion' => $hora_inicio_operacion,
            ':hora_fin_operacion' => $hora_fin_operacion,
            ':max_horas_dia' => $max_horas_dia,
            ':estado' => $estado,
            ':hora_fin_velada' => $hora_fin_velada,
            ':hora_inicio_teletrabajo' => $hora_inicio_teletrabajo,
            ':hora_fin_teletrabajo_manana' => $hora_fin_teletrabajo_manana,
            ':hora_inicio_presencial' => $hora_inicio_presencial,
            ':hora_fin_presencial' => $hora_fin_presencial,
            ':id' => $id,
        ]);

        header('Location: ' . BASE_URL . '/campaigns');
        exit;
    }
}
