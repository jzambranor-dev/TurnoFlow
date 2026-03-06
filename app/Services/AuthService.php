<?php

declare(strict_types=1);

namespace App\Services;

use Database;

class AuthService
{
    private static ?array $permissions = null;

    /**
     * Verificar si el usuario tiene un permiso especifico
     */
    public static function hasPermission(string $permission): bool
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            return false;
        }

        // Cargar permisos si no estan en cache
        if (self::$permissions === null) {
            self::loadPermissions($user['rol_id']);
        }

        return in_array($permission, self::$permissions, true);
    }

    /**
     * Verificar si el usuario tiene ALGUNO de los permisos
     */
    public static function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar si el usuario tiene TODOS los permisos
     */
    public static function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!self::hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Requerir permiso o redirigir
     */
    public static function requirePermission(string $permission, string $redirect = '/dashboard'): void
    {
        if (!self::hasPermission($permission)) {
            $_SESSION['error'] = 'No tienes permiso para acceder a esta seccion.';
            header('Location: ' . BASE_URL . $redirect);
            exit;
        }
    }

    /**
     * Requerir al menos uno de los permisos
     */
    public static function requireAnyPermission(array $permissions, string $redirect = '/dashboard'): void
    {
        if (!self::hasAnyPermission($permissions)) {
            $_SESSION['error'] = 'No tienes permiso para acceder a esta seccion.';
            header('Location: ' . BASE_URL . $redirect);
            exit;
        }
    }

    /**
     * Cargar permisos del usuario desde la base de datos
     */
    private static function loadPermissions(int $rolId): void
    {
        try {
            $pdo = Database::getConnection();

            $stmt = $pdo->prepare("
                SELECT p.codigo
                FROM permissions p
                JOIN role_permissions rp ON rp.permission_id = p.id
                WHERE rp.rol_id = :rol_id
            ");
            $stmt->execute([':rol_id' => $rolId]);

            self::$permissions = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            self::$permissions = [];
        }
    }

    /**
     * Obtener todos los permisos del usuario actual
     */
    public static function getPermissions(): array
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            return [];
        }

        if (self::$permissions === null) {
            self::loadPermissions($user['rol_id']);
        }

        return self::$permissions;
    }

    /**
     * Limpiar cache de permisos (usar despues de cambiar rol)
     */
    public static function clearCache(): void
    {
        self::$permissions = null;
    }

    /**
     * Obtener todos los permisos agrupados por modulo
     */
    public static function getAllPermissionsGrouped(): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->query("
            SELECT id, codigo, nombre, descripcion, modulo
            FROM permissions
            ORDER BY modulo, nombre
        ");

        $permissions = $stmt->fetchAll();
        $grouped = [];

        foreach ($permissions as $perm) {
            $modulo = $perm['modulo'];
            if (!isset($grouped[$modulo])) {
                $grouped[$modulo] = [];
            }
            $grouped[$modulo][] = $perm;
        }

        return $grouped;
    }

    /**
     * Obtener permisos de un rol especifico
     */
    public static function getRolePermissions(int $rolId): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT p.codigo
            FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.rol_id = :rol_id
        ");
        $stmt->execute([':rol_id' => $rolId]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Actualizar permisos de un rol
     */
    public static function updateRolePermissions(int $rolId, array $permissionIds): void
    {
        $pdo = Database::getConnection();

        $pdo->beginTransaction();

        try {
            // Eliminar permisos actuales
            $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE rol_id = :rol_id");
            $stmt->execute([':rol_id' => $rolId]);

            // Insertar nuevos permisos
            if (!empty($permissionIds)) {
                $stmt = $pdo->prepare("
                    INSERT INTO role_permissions (rol_id, permission_id)
                    VALUES (:rol_id, :permission_id)
                ");

                foreach ($permissionIds as $permId) {
                    $stmt->execute([
                        ':rol_id' => $rolId,
                        ':permission_id' => (int)$permId,
                    ]);
                }
            }

            $pdo->commit();
            self::clearCache();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Verificar si es el usuario actual
     */
    public static function isCurrentUser(int $userId): bool
    {
        return ($_SESSION['user']['id'] ?? 0) === $userId;
    }

    /**
     * Obtener usuario actual
     */
    public static function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Verificar si el usuario esta autenticado
     */
    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }
}
