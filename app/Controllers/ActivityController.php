<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';

class ActivityController
{
    /**
     * Lista actividades de una campaña
     */
    public function index(int $campaignId): void
    {
        AuthService::requirePermission('activities.view');

        $pdo = Database::getConnection();
        $user = $_SESSION['user'];

        // Verificar acceso a la campaña
        $campaign = $this->getCampaign($pdo, $campaignId, $user);
        if (!$campaign) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT ca.*,
                   (SELECT COUNT(*) FROM advisor_activity_assignments aaa
                    WHERE aaa.activity_id = ca.id AND aaa.activo = true) as total_asesores
            FROM campaign_activities ca
            WHERE ca.campaign_id = :campaign_id
            ORDER BY ca.nombre
        ");
        $stmt->execute([':campaign_id' => $campaignId]);
        $activities = $stmt->fetchAll();

        $pageTitle = 'Actividades - ' . $campaign['nombre'];
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/activities/index.php';
    }

    /**
     * Formulario para crear actividad
     */
    public function create(int $campaignId): void
    {
        AuthService::requirePermission('activities.create');

        $pdo = Database::getConnection();
        $user = $_SESSION['user'];

        $campaign = $this->getCampaign($pdo, $campaignId, $user);
        if (!$campaign) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $pageTitle = 'Nueva Actividad - ' . $campaign['nombre'];
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/activities/create.php';
    }

    /**
     * Guardar nueva actividad
     */
    public function store(int $campaignId): void
    {
        AuthService::requirePermission('activities.create');

        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $color = trim($_POST['color'] ?? '#2563eb');

        if (empty($nombre)) {
            $_SESSION['flash_error'] = 'El nombre de la actividad es obligatorio.';
            header('Location: ' . BASE_URL . '/campaigns/' . $campaignId . '/activities/create');
            exit;
        }

        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO campaign_activities (campaign_id, nombre, descripcion, color)
                VALUES (:campaign_id, :nombre, :descripcion, :color)
            ");
            $stmt->execute([
                ':campaign_id' => $campaignId,
                ':nombre' => $nombre,
                ':descripcion' => $descripcion,
                ':color' => $color,
            ]);

            $_SESSION['flash_success'] = 'Actividad creada exitosamente.';
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'unique') !== false || strpos($e->getMessage(), 'duplicate') !== false) {
                $_SESSION['flash_error'] = 'Ya existe una actividad con ese nombre en esta campaña.';
            } else {
                $_SESSION['flash_error'] = 'Error al crear la actividad.';
            }
        }

        header('Location: ' . BASE_URL . '/campaigns/' . $campaignId . '/activities');
        exit;
    }

    /**
     * Editar actividad
     */
    public function edit(int $activityId): void
    {
        AuthService::requirePermission('activities.edit');

        $pdo = Database::getConnection();
        $user = $_SESSION['user'];

        $activity = $this->getActivity($pdo, $activityId);
        if (!$activity) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $campaign = $this->getCampaign($pdo, (int)$activity['campaign_id'], $user);
        if (!$campaign) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $pageTitle = 'Editar Actividad - ' . $activity['nombre'];
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/activities/edit.php';
    }

    /**
     * Actualizar actividad
     */
    public function update(int $activityId): void
    {
        AuthService::requirePermission('activities.edit');

        $pdo = Database::getConnection();
        $activity = $this->getActivity($pdo, $activityId);
        if (!$activity) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $color = trim($_POST['color'] ?? '#2563eb');
        $estado = $_POST['estado'] ?? 'activa';

        try {
            $stmt = $pdo->prepare("
                UPDATE campaign_activities
                SET nombre = :nombre, descripcion = :descripcion, color = :color, estado = :estado
                WHERE id = :id
            ");
            $stmt->execute([
                ':nombre' => $nombre,
                ':descripcion' => $descripcion,
                ':color' => $color,
                ':estado' => $estado,
                ':id' => $activityId,
            ]);

            $_SESSION['flash_success'] = 'Actividad actualizada.';
        } catch (\PDOException $e) {
            $_SESSION['flash_error'] = 'Error al actualizar la actividad.';
        }

        header('Location: ' . BASE_URL . '/campaigns/' . $activity['campaign_id'] . '/activities');
        exit;
    }

    /**
     * Gestionar asignaciones de asesores a una actividad
     */
    public function assignments(int $activityId): void
    {
        AuthService::requirePermission('activities.assign');

        $pdo = Database::getConnection();
        $user = $_SESSION['user'];

        $activity = $this->getActivity($pdo, $activityId);
        if (!$activity) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $campaign = $this->getCampaign($pdo, (int)$activity['campaign_id'], $user);
        if (!$campaign) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        // Asignaciones actuales
        $stmt = $pdo->prepare("
            SELECT aaa.*, a.nombres, a.apellidos, a.cedula
            FROM advisor_activity_assignments aaa
            JOIN advisors a ON a.id = aaa.advisor_id
            WHERE aaa.activity_id = :activity_id
            ORDER BY a.apellidos, a.nombres
        ");
        $stmt->execute([':activity_id' => $activityId]);
        $currentAssignments = $stmt->fetchAll();

        // Asesores disponibles (activos en la campaña + prestados, no asignados a esta actividad)
        $stmt = $pdo->prepare("
            SELECT a.id, a.nombres, a.apellidos, a.cedula, FALSE AS is_shared
            FROM advisors a
            WHERE a.campaign_id = :campaign_id
              AND a.estado = 'activo'
              AND a.id NOT IN (
                  SELECT aaa.advisor_id FROM advisor_activity_assignments aaa
                  WHERE aaa.activity_id = :activity_id
              )
            UNION
            SELECT a.id, a.nombres, a.apellidos, a.cedula, TRUE AS is_shared
            FROM shared_advisors sa
            JOIN advisors a ON a.id = sa.advisor_id
            WHERE sa.target_campaign_id = :campaign_id2
              AND sa.estado = 'activo'
              AND a.estado = 'activo'
              AND a.id NOT IN (
                  SELECT aaa.advisor_id FROM advisor_activity_assignments aaa
                  WHERE aaa.activity_id = :activity_id2
              )
            ORDER BY apellidos, nombres
        ");
        $stmt->execute([
            ':campaign_id' => $activity['campaign_id'],
            ':activity_id' => $activityId,
            ':campaign_id2' => $activity['campaign_id'],
            ':activity_id2' => $activityId,
        ]);
        $availableAdvisors = $stmt->fetchAll();

        $pageTitle = 'Asignaciones - ' . $activity['nombre'];
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/activities/assignments.php';
    }

    /**
     * Guardar asignación de asesor a actividad
     */
    public function storeAssignment(int $activityId): void
    {
        AuthService::requirePermission('activities.assign');

        $pdo = Database::getConnection();
        $activity = $this->getActivity($pdo, $activityId);
        if (!$activity) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $advisorId = (int)($_POST['advisor_id'] ?? 0);
        $horaInicio = (int)($_POST['hora_inicio'] ?? 0);
        $horaFin = (int)($_POST['hora_fin'] ?? 0);
        $diasSemana = $_POST['dias_semana'] ?? [0,1,2,3,4];

        if ($advisorId <= 0) {
            $_SESSION['flash_error'] = 'Seleccióna un asesor.';
            header('Location: ' . BASE_URL . '/activities/' . $activityId . '/assignments');
            exit;
        }

        if ($horaInicio >= $horaFin) {
            $_SESSION['flash_error'] = 'La hora de inicio debe ser menor que la hora de fin.';
            header('Location: ' . BASE_URL . '/activities/' . $activityId . '/assignments');
            exit;
        }

        // Verificar que el asesor pertenece a la campaña o está prestado a ella
        $stmt = $pdo->prepare("
            SELECT id FROM advisors
            WHERE id = :advisor_id AND campaign_id = :campaign_id AND estado = 'activo'
            UNION
            SELECT a.id FROM shared_advisors sa
            JOIN advisors a ON a.id = sa.advisor_id
            WHERE sa.advisor_id = :advisor_id2 AND sa.target_campaign_id = :campaign_id2
              AND sa.estado = 'activo' AND a.estado = 'activo'
            LIMIT 1
        ");
        $stmt->execute([
            ':advisor_id' => $advisorId,
            ':campaign_id' => $activity['campaign_id'],
            ':advisor_id2' => $advisorId,
            ':campaign_id2' => $activity['campaign_id'],
        ]);
        if (!$stmt->fetch()) {
            $_SESSION['flash_error'] = 'El asesor no pertenece a esta campaña.';
            header('Location: ' . BASE_URL . '/activities/' . $activityId . '/assignments');
            exit;
        }

        // Formatear dias_semana como array PostgreSQL
        $diasArray = array_map('intval', (array)$diasSemana);
        $diasPg = '{' . implode(',', $diasArray) . '}';

        try {
            $stmt = $pdo->prepare("
                INSERT INTO advisor_activity_assignments (activity_id, advisor_id, hora_inicio, hora_fin, dias_semana)
                VALUES (:activity_id, :advisor_id, :hora_inicio, :hora_fin, :dias_semana)
                ON CONFLICT (advisor_id, activity_id) DO UPDATE
                SET hora_inicio = :hora_inicio2, hora_fin = :hora_fin2, dias_semana = :dias_semana2, activo = true
            ");
            $stmt->execute([
                ':activity_id' => $activityId,
                ':advisor_id' => $advisorId,
                ':hora_inicio' => $horaInicio,
                ':hora_fin' => $horaFin,
                ':dias_semana' => $diasPg,
                ':hora_inicio2' => $horaInicio,
                ':hora_fin2' => $horaFin,
                ':dias_semana2' => $diasPg,
            ]);

            $_SESSION['flash_success'] = 'Asesor asignado a la actividad.';
        } catch (\PDOException $e) {
            $_SESSION['flash_error'] = 'Error al asignar el asesor: ' . $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/activities/' . $activityId . '/assignments');
        exit;
    }

    /**
     * Eliminar asignación
     */
    public function removeAssignment(int $assignmentId): void
    {
        AuthService::requirePermission('activities.assign');

        $pdo = Database::getConnection();

        // Obtener la asignación para redirigir después
        $stmt = $pdo->prepare("SELECT activity_id FROM advisor_activity_assignments WHERE id = :id");
        $stmt->execute([':id' => $assignmentId]);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM advisor_activity_assignments WHERE id = :id");
        $stmt->execute([':id' => $assignmentId]);

        $_SESSION['flash_success'] = 'Asignación eliminada.';
        header('Location: ' . BASE_URL . '/activities/' . $assignment['activity_id'] . '/assignments');
        exit;
    }

    // ============ Helpers ============

    private function getCampaign(\PDO $pdo, int $campaignId, array $user): ?array
    {
        $canManageAll = in_array($user['rol'] ?? '', ['admin', 'gerente', 'coordinador'], true);

        if ($canManageAll) {
            $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id");
            $stmt->execute([':id' => $campaignId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id AND supervisor_id = :supervisor_id");
            $stmt->execute([':id' => $campaignId, ':supervisor_id' => $user['id']]);
        }

        $result = $stmt->fetch();
        return $result ?: null;
    }

    private function getActivity(\PDO $pdo, int $activityId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM campaign_activities WHERE id = :id");
        $stmt->execute([':id' => $activityId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
