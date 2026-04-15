<?php

declare(strict_types=1);

namespace App\Services;

use Database;
use PDO;

class NotificationService
{
    /**
     * Enviar una notificacion a un usuario
     */
    public static function send(
        int $userId,
        string $tipo,
        string $titulo,
        string $mensaje,
        string $url = ''
    ): void {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, tipo, titulo, mensaje, url)
            VALUES (:user_id, :tipo, :titulo, :mensaje, :url)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':tipo'    => $tipo,
            ':titulo'  => $titulo,
            ':mensaje' => $mensaje,
            ':url'     => $url,
        ]);
    }

    /**
     * Enviar notificacion a todos los usuarios que tengan un permiso especifico
     */
    public static function sendToPermission(
        string $permission,
        string $tipo,
        string $titulo,
        string $mensaje,
        string $url = ''
    ): void {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id
            FROM users u
            JOIN roles r ON r.id = u.rol_id
            JOIN role_permissions rp ON rp.rol_id = r.id
            JOIN permissions p ON p.id = rp.permission_id
            WHERE p.codigo = :permission
              AND u.activo = true
        ");
        $stmt->execute([':permission' => $permission]);
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($userIds as $uid) {
            self::send((int)$uid, $tipo, $titulo, $mensaje, $url);
        }
    }

    /**
     * Obtener cantidad de notificaciones no leidas
     */
    public static function getUnreadCount(int $userId): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = :user_id AND leida = false
        ");
        $stmt->execute([':user_id' => $userId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Obtener las ultimas N notificaciones no leidas
     */
    public static function getLatestUnread(int $userId, int $limit = 5): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT id, tipo, titulo, mensaje, url, leida, created_at
            FROM notifications
            WHERE user_id = :user_id AND leida = false
            ORDER BY created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Marcar una notificacion como leida
     */
    public static function markRead(int $notificationId, int $userId): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE notifications SET leida = true
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Marcar todas las notificaciones como leidas
     */
    public static function markAllRead(int $userId): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE notifications SET leida = true
            WHERE user_id = :user_id AND leida = false
        ");
        $stmt->execute([':user_id' => $userId]);

        return $stmt->rowCount();
    }

    /**
     * Obtener notificaciones paginadas
     */
    public static function getPaginated(int $userId, int $page = 1, int $perPage = 20): array
    {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $perPage;

        // Total
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $total = (int)$stmt->fetchColumn();

        // Items
        $stmt = $pdo->prepare("
            SELECT id, tipo, titulo, mensaje, url, leida, created_at
            FROM notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT :lim OFFSET :off
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'totalPages'  => (int)ceil($total / $perPage),
        ];
    }
}
