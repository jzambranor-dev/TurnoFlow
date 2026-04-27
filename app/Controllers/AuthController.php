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

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 300; // 5 minutos

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $errors = [];

        // Rate limiting por IP usando sesión
        $attempts = $_SESSION['login_attempts'] ?? [];
        $now = time();

        // Limpiar intentos expirados
        $attempts = array_filter($attempts, fn($ts) => ($now - $ts) < self::LOCKOUT_SECONDS);

        if (count($attempts) >= self::MAX_LOGIN_ATTEMPTS) {
            $waitSeconds = self::LOCKOUT_SECONDS - ($now - min($attempts));
            $errors[] = "Demasiados intentos. Espera " . (int)ceil($waitSeconds / 60) . " minuto(s).";
            $_SESSION['login_attempts'] = $attempts;
            include APP_PATH . '/Views/auth/login.php';
            return;
        }

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
                    // Login exitoso — limpiar intentos y regenerar sesion
                    unset($_SESSION['login_attempts']);
                    session_regenerate_id(true);
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
                    // Registrar intento fallido
                    $attempts[] = $now;
                    $_SESSION['login_attempts'] = $attempts;
                    $remaining = self::MAX_LOGIN_ATTEMPTS - count($attempts);
                    $errors[] = 'Credenciales inválidas' . ($remaining <= 2 ? " ($remaining intentos restantes)" : '');
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
