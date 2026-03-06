<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;

class UserController
{
    public function index(): void
    {
        $this->checkAdmin();

        $pdo = Database::getConnection();

        $stmt = $pdo->query("
            SELECT u.*, r.nombre as rol_nombre
            FROM users u
            LEFT JOIN roles r ON r.id = u.rol_id
            ORDER BY u.nombre, u.apellido
        ");
        $users = $stmt->fetchAll();

        // Obtener roles para el modal
        $stmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
        $roles = $stmt->fetchAll();

        $pageTitle = 'Usuarios';
        $currentPage = 'users';

        include APP_PATH . '/Views/users/index.php';
    }

    public function create(): void
    {
        $this->checkAdmin();

        $pdo = Database::getConnection();

        $stmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
        $roles = $stmt->fetchAll();

        $pageTitle = 'Nuevo Usuario';
        $currentPage = 'users';

        include APP_PATH . '/Views/users/create.php';
    }

    public function store(): void
    {
        $this->checkAdmin();

        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol_id = (int)($_POST['rol_id'] ?? 0);
        $activo = isset($_POST['activo']);

        if (empty($nombre) || empty($apellido) || empty($email) || empty($password) || $rol_id === 0) {
            $_SESSION['error'] = 'Todos los campos son requeridos';
            header('Location: ' . BASE_URL . '/users/create');
            exit;
        }

        $pdo = Database::getConnection();

        // Verificar email unico
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'El email ya esta registrado';
            header('Location: ' . BASE_URL . '/users/create');
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (nombre, apellido, email, password_hash, rol_id, activo)
            VALUES (:nombre, :apellido, :email, :password_hash, :rol_id, :activo)
        ");

        $stmt->execute([
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':email' => $email,
            ':password_hash' => $password_hash,
            ':rol_id' => $rol_id,
            ':activo' => $activo ? 'true' : 'false',
        ]);

        $_SESSION['success'] = 'Usuario creado correctamente';
        header('Location: ' . BASE_URL . '/users');
        exit;
    }

    public function edit(int $id): void
    {
        $this->checkAdmin();

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        $stmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
        $roles = $stmt->fetchAll();

        $pageTitle = 'Editar Usuario';
        $currentPage = 'users';

        include APP_PATH . '/Views/users/edit.php';
    }

    public function update(int $id): void
    {
        $this->checkAdmin();

        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol_id = (int)($_POST['rol_id'] ?? 0);
        $activo = isset($_POST['activo']);

        if (empty($nombre) || empty($apellido) || empty($email) || $rol_id === 0) {
            $_SESSION['error'] = 'Todos los campos son requeridos';
            header('Location: ' . BASE_URL . '/users/' . $id . '/edit');
            exit;
        }

        $pdo = Database::getConnection();

        // Verificar email unico (excluyendo el usuario actual)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $stmt->execute([':email' => $email, ':id' => $id]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'El email ya esta registrado';
            header('Location: ' . BASE_URL . '/users/' . $id . '/edit');
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE users SET
                nombre = :nombre,
                apellido = :apellido,
                email = :email,
                rol_id = :rol_id,
                activo = :activo
            WHERE id = :id
        ");

        $stmt->execute([
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':email' => $email,
            ':rol_id' => $rol_id,
            ':activo' => $activo ? 'true' : 'false',
            ':id' => $id,
        ]);

        $_SESSION['success'] = 'Usuario actualizado correctamente';
        header('Location: ' . BASE_URL . '/users');
        exit;
    }

    public function resetPassword(int $id): void
    {
        $this->checkAdmin();

        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($password) || strlen($password) < 6) {
            $_SESSION['error'] = 'La contrasena debe tener al menos 6 caracteres';
            header('Location: ' . BASE_URL . '/users/' . $id . '/edit');
            exit;
        }

        if ($password !== $password_confirm) {
            $_SESSION['error'] = 'Las contrasenas no coinciden';
            header('Location: ' . BASE_URL . '/users/' . $id . '/edit');
            exit;
        }

        $pdo = Database::getConnection();

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
        $stmt->execute([
            ':password_hash' => $password_hash,
            ':id' => $id,
        ]);

        $_SESSION['success'] = 'Contrasena actualizada correctamente';
        header('Location: ' . BASE_URL . '/users');
        exit;
    }

    public function toggleStatus(int $id): void
    {
        $this->checkAdmin();

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE users SET activo = NOT activo WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $_SESSION['success'] = 'Estado actualizado correctamente';
        header('Location: ' . BASE_URL . '/users');
        exit;
    }

    private function checkAdmin(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || ($user['rol'] !== 'admin' && $user['rol'] !== 'coordinador')) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
    }
}
