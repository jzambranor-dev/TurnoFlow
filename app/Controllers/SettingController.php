<?php

declare(strict_types=1);

namespace App\Controllers;

use Database;
use App\Services\AuthService;
use App\Services\ApiAuthService;

require_once APP_PATH . '/Services/AuthService.php';
require_once APP_PATH . '/Services/ApiAuthService.php';

class SettingController
{
    public function index(): void
    {
        AuthService::requirePermission('settings.view');

        $pdo = Database::getConnection();

        // Monthly hours config
        $stmt = $pdo->query("SELECT * FROM monthly_hours_config ORDER BY anio DESC, mes DESC");
        $monthlyHours = $stmt->fetchAll();

        // Holidays
        $stmt = $pdo->query("SELECT * FROM holidays ORDER BY fecha");
        $holidays = $stmt->fetchAll();

        // System params
        $stmt = $pdo->query("SELECT * FROM system_params ORDER BY id");
        $systemParams = $stmt->fetchAll();

        // API Tokens
        $apiTokens = ApiAuthService::listTokens();

        $pageTitle = 'Configuracion';
        $currentPage = 'settings';

        include APP_PATH . '/Views/settings/index.php';
    }

    public function saveMonthlyHours(): void
    {
        AuthService::requirePermission('settings.view');

        $pdo = Database::getConnection();
        $anio = (int)($_POST['anio'] ?? 0);
        $mes = (int)($_POST['mes'] ?? 0);
        $horas = (int)($_POST['horas_requeridas'] ?? 0);
        $dias = (int)($_POST['dias_del_mes'] ?? 0);
        $userId = (int)$_SESSION['user']['id'];

        if ($anio < 2024 || $anio > 2035 || $mes < 1 || $mes > 12 || $horas < 100 || $horas > 250 || $dias < 28 || $dias > 31) {
            $_SESSION['flash_error'] = 'Datos invalidos';
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO monthly_hours_config (anio, mes, horas_requeridas, dias_del_mes, configurado_por)
            VALUES (:anio, :mes, :horas, :dias, :uid)
            ON CONFLICT (anio, mes) DO UPDATE SET
                horas_requeridas = :horas2,
                dias_del_mes = :dias2,
                configurado_por = :uid2
        ");
        $stmt->execute([
            ':anio' => $anio, ':mes' => $mes, ':horas' => $horas, ':dias' => $dias, ':uid' => $userId,
            ':horas2' => $horas, ':dias2' => $dias, ':uid2' => $userId,
        ]);

        $_SESSION['flash_success'] = "Horas de $mes/$anio actualizadas correctamente";
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function deleteMonthlyHours(): void
    {
        AuthService::requirePermission('settings.view');

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM monthly_hours_config WHERE id = :id");
            $stmt->execute([':id' => $id]);
        }

        $_SESSION['flash_success'] = 'Registro eliminado';
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function saveHoliday(): void
    {
        AuthService::requirePermission('settings.view');

        $pdo = Database::getConnection();
        $fecha = trim($_POST['fecha'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');

        if ($fecha === '' || $nombre === '') {
            $_SESSION['flash_error'] = 'Fecha y nombre son requeridos';
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO holidays (fecha, nombre) VALUES (:fecha, :nombre)
            ON CONFLICT (fecha) DO UPDATE SET nombre = :nombre2
        ");
        $stmt->execute([':fecha' => $fecha, ':nombre' => $nombre, ':nombre2' => $nombre]);

        $_SESSION['flash_success'] = "Dia festivo '$nombre' guardado";
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function deleteHoliday(): void
    {
        AuthService::requirePermission('settings.view');

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM holidays WHERE id = :id");
            $stmt->execute([':id' => $id]);
        }

        $_SESSION['flash_success'] = 'Dia festivo eliminado';
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function saveParams(): void
    {
        AuthService::requirePermission('settings.view');

        $pdo = Database::getConnection();
        $params = $_POST['params'] ?? [];

        if (is_array($params)) {
            $stmt = $pdo->prepare("UPDATE system_params SET valor = :valor, updated_at = NOW() WHERE clave = :clave");
            foreach ($params as $clave => $valor) {
                $stmt->execute([':clave' => $clave, ':valor' => trim($valor)]);
            }
        }

        $_SESSION['flash_success'] = 'Parametros actualizados';
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function createApiToken(): void
    {
        AuthService::requirePermission('settings.view');

        $nombre = trim($_POST['token_nombre'] ?? '');
        $permisosRaw = $_POST['token_permisos'] ?? [];
        $expiraDias = (int)($_POST['token_expira_dias'] ?? 0);

        if ($nombre === '') {
            $_SESSION['flash_error'] = 'El nombre del token es requerido';
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }

        $permisosValidos = ['reports.view', 'reports.export', '*'];
        $permisos = array_intersect((array)$permisosRaw, $permisosValidos);
        if (empty($permisos)) {
            $permisos = ['reports.view'];
        }

        $expiraEn = null;
        if ($expiraDias > 0) {
            $expiraEn = date('Y-m-d H:i:s', strtotime("+{$expiraDias} days"));
        }

        $userId = (int)$_SESSION['user']['id'];
        $plainToken = ApiAuthService::createToken($userId, $nombre, array_values($permisos), $expiraEn);

        $_SESSION['flash_new_token'] = $plainToken;
        $_SESSION['flash_success'] = "Token '$nombre' creado exitosamente. Copialo ahora, no se mostrara de nuevo.";
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function revokeApiToken(): void
    {
        AuthService::requirePermission('settings.view');

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            ApiAuthService::revokeToken($id);
        }

        $_SESSION['flash_success'] = 'Token revocado';
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function changelog(): void
    {
        include APP_PATH . '/Views/settings/changelog.php';
    }
}
