<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use PDO;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';

class SharedAdvisorController
{
    public function index(int $campaignId): void
    {
        AuthService::requirePermission('advisors.view');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $campaign = $this->loadCampaign($pdo, $campaignId);
        if (!$campaign || !$this->canAccessCampaign($pdo, $campaign, $user)) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        // Asesores prestados A esta campaña (incoming)
        $stmt = $pdo->prepare("
            SELECT sa.id, sa.max_horas_dia, sa.estado,
                   a.id AS advisor_id, a.nombres, a.apellidos,
                   c.nombre AS source_campaign_nombre
            FROM shared_advisors sa
            JOIN advisors a ON a.id = sa.advisor_id
            JOIN campaigns c ON c.id = sa.source_campaign_id
            WHERE sa.target_campaign_id = :cid
            ORDER BY a.apellidos, a.nombres
        ");
        $stmt->execute([':cid' => $campaignId]);
        $incoming = $stmt->fetchAll();

        // Asesores prestados DESDE esta campaña (outgoing)
        $stmt = $pdo->prepare("
            SELECT sa.id, sa.max_horas_dia, sa.estado,
                   a.id AS advisor_id, a.nombres, a.apellidos,
                   c.nombre AS target_campaign_nombre
            FROM shared_advisors sa
            JOIN advisors a ON a.id = sa.advisor_id
            JOIN campaigns c ON c.id = sa.target_campaign_id
            WHERE sa.source_campaign_id = :cid
            ORDER BY a.apellidos, a.nombres
        ");
        $stmt->execute([':cid' => $campaignId]);
        $outgoing = $stmt->fetchAll();

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $pageTitle = 'Asesores Compartidos';
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/shared-advisors/index.php';
    }

    public function create(int $campaignId): void
    {
        AuthService::requirePermission('advisors.edit');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $campaign = $this->loadCampaign($pdo, $campaignId);
        if (!$campaign || !$this->canAccessCampaign($pdo, $campaign, $user)) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        // Cargar otras campañas accesibles por el usuario
        if (AuthService::canManageAllCampaigns($user)) {
            $stmt = $pdo->prepare("
                SELECT id, nombre FROM campaigns
                WHERE estado = 'activa' AND id <> :cid
                ORDER BY nombre
            ");
            $stmt->execute([':cid' => $campaignId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, nombre FROM campaigns
                WHERE estado = 'activa' AND supervisor_id = :sid AND id <> :cid
                ORDER BY nombre
            ");
            $stmt->execute([':sid' => $user['id'], ':cid' => $campaignId]);
        }
        $otherCampaigns = $stmt->fetchAll();

        // Si se selecciónó una campaña origen, cargar sus asesores activos
        $sourceCampaignId = isset($_GET['source_campaign_id']) && $_GET['source_campaign_id'] !== ''
            ? (int)$_GET['source_campaign_id'] : null;
        $sourceAdvisors = [];

        if ($sourceCampaignId) {
            // Verificar que la campaña origen es accesible
            $canAccess = false;
            foreach ($otherCampaigns as $c) {
                if ((int)$c['id'] === $sourceCampaignId) {
                    $canAccess = true;
                    break;
                }
            }

            if ($canAccess) {
                // Cargar asesores de la campaña origen que NO están ya compartidos a esta campaña
                $stmt = $pdo->prepare("
                    SELECT a.id, a.nombres, a.apellidos
                    FROM advisors a
                    WHERE a.campaign_id = :source_cid AND a.estado = 'activo'
                      AND a.id NOT IN (
                          SELECT sa.advisor_id FROM shared_advisors sa
                          WHERE sa.target_campaign_id = :target_cid
                      )
                    ORDER BY a.apellidos, a.nombres
                ");
                $stmt->execute([':source_cid' => $sourceCampaignId, ':target_cid' => $campaignId]);
                $sourceAdvisors = $stmt->fetchAll();
            }
        }

        $pageTitle = 'Compartir Asesores';
        $currentPage = 'campaigns';

        include APP_PATH . '/Views/shared-advisors/create.php';
    }

    public function store(int $campaignId): void
    {
        AuthService::requirePermission('advisors.edit');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        $campaign = $this->loadCampaign($pdo, $campaignId);
        if (!$campaign || !$this->canAccessCampaign($pdo, $campaign, $user)) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $sourceCampaignId = (int)($_POST['source_campaign_id'] ?? 0);
        $advisorIds = $_POST['advisor_ids'] ?? [];
        $maxHorasDia = max(1, min(8, (int)($_POST['max_horas_dia'] ?? 3)));

        if ($sourceCampaignId <= 0 || empty($advisorIds)) {
            $_SESSION['flash_error'] = 'Debes selecciónar una campaña origen y al menos un asesor.';
            header('Location: ' . BASE_URL . '/campaigns/' . $campaignId . '/shared-advisors/create');
            exit;
        }

        // Validar que source != target
        if ($sourceCampaignId === $campaignId) {
            $_SESSION['flash_error'] = 'No puedes compartir asesores con la misma campaña.';
            header('Location: ' . BASE_URL . '/campaigns/' . $campaignId . '/shared-advisors/create');
            exit;
        }

        // Validar acceso a campaña origen
        $sourceCampaign = $this->loadCampaign($pdo, $sourceCampaignId);
        if (!$sourceCampaign || !$this->canAccessCampaign($pdo, $sourceCampaign, $user)) {
            $_SESSION['flash_error'] = 'No tienes acceso a la campaña origen.';
            header('Location: ' . BASE_URL . '/campaigns/' . $campaignId . '/shared-advisors/create');
            exit;
        }

        // Validar que los asesores pertenecen a la campaña origen
        $advisorIds = array_map('intval', $advisorIds);
        $placeholders = implode(',', array_fill(0, count($advisorIds), '?'));
        $stmt = $pdo->prepare("SELECT id FROM advisors WHERE id IN ($placeholders) AND campaign_id = ?");
        $params = array_merge($advisorIds, [$sourceCampaignId]);
        $stmt->execute($params);
        $validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($validIds)) {
            $_SESSION['flash_error'] = 'Ninguno de los asesores selecciónados pertenece a la campaña origen.';
            header('Location: ' . BASE_URL . '/campaigns/' . $campaignId . '/shared-advisors/create');
            exit;
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO shared_advisors (advisor_id, source_campaign_id, target_campaign_id, max_horas_dia)
            VALUES (:aid, :source, :target, :max_h)
            ON CONFLICT (advisor_id, target_campaign_id) DO NOTHING
        ");

        $inserted = 0;
        foreach ($validIds as $advId) {
            $insertStmt->execute([
                ':aid' => $advId,
                ':source' => $sourceCampaignId,
                ':target' => $campaignId,
                ':max_h' => $maxHorasDia,
            ]);
            $inserted += $insertStmt->rowCount();
        }

        $_SESSION['flash_success'] = "Se compartieron {$inserted} asesores correctamente.";
        header('Location: ' . BASE_URL . '/campaigns/' . $campaignId . '/shared-advisors');
        exit;
    }

    public function toggle(int $id): void
    {
        AuthService::requirePermission('advisors.edit');

        $user = $_SESSION['user'];
        $pdo = Database::getConnection();

        // Cargar el shared_advisor
        $stmt = $pdo->prepare("
            SELECT sa.*, c.supervisor_id
            FROM shared_advisors sa
            JOIN campaigns c ON c.id = sa.target_campaign_id
            WHERE sa.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $shared = $stmt->fetch();

        if (!$shared) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        // Verificar permisos
        if (!AuthService::canManageAllCampaigns($user) && (int)$shared['supervisor_id'] !== (int)$user['id']) {
            header('Location: ' . BASE_URL . '/campaigns');
            exit;
        }

        $newEstado = $shared['estado'] === 'activo' ? 'inactivo' : 'activo';
        $stmt = $pdo->prepare("UPDATE shared_advisors SET estado = :estado WHERE id = :id");
        $stmt->execute([':estado' => $newEstado, ':id' => $id]);

        $_SESSION['flash_success'] = 'Estado actualizado correctamente.';
        header('Location: ' . BASE_URL . '/campaigns/' . $shared['target_campaign_id'] . '/shared-advisors');
        exit;
    }

    private function loadCampaign(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $campaign = $stmt->fetch();
        return $campaign ?: null;
    }

    private function canAccessCampaign(PDO $pdo, array $campaign, array $user): bool
    {
        if (AuthService::canManageAllCampaigns($user)) return true;
        return (int)$campaign['supervisor_id'] === (int)$user['id'];
    }

}
