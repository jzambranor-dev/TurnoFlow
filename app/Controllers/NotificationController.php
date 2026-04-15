<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\NotificationService;

require_once APP_PATH . '/Services/AuthService.php';
require_once APP_PATH . '/Services/NotificationService.php';

class NotificationController
{
    /**
     * Lista paginada de notificaciones del usuario actual
     */
    public function index(): void
    {
        $user = $_SESSION['user'];
        $page = max(1, (int)($_GET['page'] ?? 1));

        $result = NotificationService::getPaginated($user['id'], $page, 20);

        $notifications = $result['items'];
        $totalPages = $result['totalPages'];
        $currentPageNum = $result['page'];
        $total = $result['total'];

        $pageTitle = 'Notificaciones';
        $currentPage = 'notifications';

        include APP_PATH . '/Views/notifications/index.php';
    }

    /**
     * Marcar una notificacion como leida (POST, JSON)
     */
    public function markRead(int $id): void
    {
        header('Content-Type: application/json');

        $user = $_SESSION['user'];
        $success = NotificationService::markRead($id, $user['id']);

        echo json_encode(['success' => $success]);
    }

    /**
     * Marcar todas como leidas (POST, JSON)
     */
    public function markAllRead(): void
    {
        header('Content-Type: application/json');

        $user = $_SESSION['user'];
        $count = NotificationService::markAllRead($user['id']);

        echo json_encode(['success' => true, 'count' => $count]);
    }

    /**
     * Endpoint JSON: count no leidas + ultimas 5
     */
    public function getUnread(): void
    {
        header('Content-Type: application/json');

        $user = $_SESSION['user'];
        $count = NotificationService::getUnreadCount($user['id']);
        $latest = NotificationService::getLatestUnread($user['id'], 5);

        echo json_encode([
            'success' => true,
            'count'   => $count,
            'items'   => $latest,
        ]);
    }
}
