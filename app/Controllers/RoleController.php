<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';

class RoleController
{
    public function index(): void
    {
        AuthService::requirePermission('roles.view');

        $pdo = Database::getConnection();

        $stmt = $pdo->query("
            SELECT r.*,
                   (SELECT COUNT(*) FROM users u WHERE u.rol_id = r.id) as total_users,
                   (SELECT COUNT(*) FROM role_permissions rp WHERE rp.rol_id = r.id) as total_permissions
            FROM roles r
            ORDER BY r.nombre
        ");
        $roles = $stmt->fetchAll();

        $pageTitle = 'Roles';
        $currentPage = 'roles';

        include APP_PATH . '/Views/roles/index.php';
    }

    public function create(): void
    {
        AuthService::requirePermission('roles.create');

        $pdo = Database::getConnection();

        $permissionsGrouped = AuthService::getAllPermissionsGrouped();

        $pageTitle = 'Nuevo Rol';
        $currentPage = 'roles';

        include APP_PATH . '/Views/roles/create.php';
    }

    public function store(): void
    {
        AuthService::requirePermission('roles.create');

        $nombre = trim($_POST['nombre'] ?? '');
        $descripción = trim($_POST['descripción'] ?? '');
        $permissions = $_POST['permissions'] ?? [];

        if (empty($nombre)) {
            $_SESSION['error'] = 'El nombre del rol es requerido';
            header('Location: ' . BASE_URL . '/roles/create');
            exit;
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // Verificar nombre unico
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE nombre = :nombre");
            $stmt->execute([':nombre' => strtolower($nombre)]);
            if ($stmt->fetch()) {
                throw new \Exception('Ya existe un rol con ese nombre');
            }

            // Crear rol
            $stmt = $pdo->prepare("
                INSERT INTO roles (nombre, descripción)
                VALUES (:nombre, :descripción)
                RETURNING id
            ");
            $stmt->execute([
                ':nombre' => strtolower($nombre),
                ':descripción' => $descripción,
            ]);
            $rolId = $stmt->fetchColumn();

            // Asignar permisos
            if (!empty($permissions)) {
                AuthService::updateRolePermissions((int)$rolId, $permissions);
            }

            $pdo->commit();
            $_SESSION['success'] = 'Rol creado correctamente';
        } catch (\Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = $e->getMessage();
            header('Location: ' . BASE_URL . '/roles/create');
            exit;
        }

        header('Location: ' . BASE_URL . '/roles');
        exit;
    }

    public function edit(int $id): void
    {
        AuthService::requirePermission('roles.edit');

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $role = $stmt->fetch();

        if (!$role) {
            header('Location: ' . BASE_URL . '/roles');
            exit;
        }

        $permissionsGrouped = AuthService::getAllPermissionsGrouped();
        $rolePermissions = AuthService::getRolePermissions($id);

        $pageTitle = 'Editar Rol';
        $currentPage = 'roles';

        include APP_PATH . '/Views/roles/edit.php';
    }

    public function update(int $id): void
    {
        AuthService::requirePermission('roles.edit');

        $descripción = trim($_POST['descripción'] ?? '');
        $permissions = $_POST['permissions'] ?? [];

        $pdo = Database::getConnection();

        // Verificar que el rol existe
        $stmt = $pdo->prepare("SELECT nombre FROM roles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $role = $stmt->fetch();

        if (!$role) {
            header('Location: ' . BASE_URL . '/roles');
            exit;
        }

        // No permitir editar nombre de roles base
        $rolesBase = ['admin', 'gerente', 'coordinador', 'supervisor', 'asesor'];
        $canEditName = !in_array($role['nombre'], $rolesBase);

        try {
            // Actualizar descripción
            $stmt = $pdo->prepare("UPDATE roles SET descripción = :descripción WHERE id = :id");
            $stmt->execute([
                ':descripción' => $descripción,
                ':id' => $id,
            ]);

            // Actualizar permisos (tiene su propia transaccion)
            AuthService::updateRolePermissions($id, $permissions);

            $_SESSION['success'] = 'Rol actualizado correctamente';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error al actualizar el rol: ' . $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/roles');
        exit;
    }

    public function delete(int $id): void
    {
        AuthService::requirePermission('roles.delete');

        $pdo = Database::getConnection();

        // Verificar que el rol existe
        $stmt = $pdo->prepare("SELECT nombre FROM roles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $role = $stmt->fetch();

        if (!$role) {
            header('Location: ' . BASE_URL . '/roles');
            exit;
        }

        // No permitir eliminar roles base
        $rolesBase = ['admin', 'gerente', 'coordinador', 'supervisor', 'asesor'];
        if (in_array($role['nombre'], $rolesBase)) {
            $_SESSION['error'] = 'No se pueden eliminar los roles base del sistema';
            header('Location: ' . BASE_URL . '/roles');
            exit;
        }

        // Verificar que no hay usuarios con este rol
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE rol_id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'No se puede eliminar el rol porque tiene usuarios asignados';
            header('Location: ' . BASE_URL . '/roles');
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM roles WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $_SESSION['success'] = 'Rol eliminado correctamente';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error al eliminar el rol';
        }

        header('Location: ' . BASE_URL . '/roles');
        exit;
    }
}
