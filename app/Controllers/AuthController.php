<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;

class AuthController
{
    public function showLogin(): void
    {
        // Si ya está autenticado, redirigir al dashboard
        if (isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        include APP_PATH . '/Views/auth/login.php';
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $errors = [];

        if (empty($email)) {
            $errors[] = 'El email es requerido';
        }

        if (empty($password)) {
            $errors[] = 'La contraseña es requerida';
        }

        if (empty($errors)) {
            try {
                $pdo = Database::getConnection();

                $stmt = $pdo->prepare("
                    SELECT u.*, r.nombre as rol
                    FROM users u
                    JOIN roles r ON r.id = u.rol_id
                    WHERE u.email = :email AND u.activo = true
                ");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Login exitoso
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'nombre' => $user['nombre'],
                        'apellido' => $user['apellido'],
                        'email' => $user['email'],
                        'rol' => $user['rol'],
                        'rol_id' => $user['rol_id'],
                    ];

                    header('Location: ' . BASE_URL . '/dashboard');
                    exit;
                } else {
                    $errors[] = 'Credenciales inválidas';
                }
            } catch (\Exception $e) {
                error_log("Error en login: " . $e->getMessage());
                $errors[] = 'Error de conexión. Intente nuevamente.';
            }
        }

        // Mostrar formulario con errores
        include APP_PATH . '/Views/auth/login.php';
    }

    public function logout(): void
    {
        session_destroy();
        header('Location: ' . BASE_URL . '/login');
        exit;
    }
}
