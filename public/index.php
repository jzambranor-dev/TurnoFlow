<?php

declare(strict_types=1);

/**
 * TurnoFlow - Punto de entrada principal
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Constantes de rutas
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/system-horario/TurnoFlow/public');
define('APP_PATH', BASE_PATH . '/app');

// Autoloader de Composer
require_once BASE_PATH . '/vendor/autoload.php';

// Configuración de base de datos
require_once BASE_PATH . '/config/database.php';

// Iniciar sesión con opciones seguras
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

// CSRF Service
require_once APP_PATH . '/Services/CsrfService.php';

// Obtener la URI
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/system-horario/TurnoFlow/public';

// Remover base path y query string
$uri = parse_url($requestUri, PHP_URL_PATH);
$uri = str_replace($basePath, '', $uri);
$uri = $uri === '' ? '/' : $uri;
$uri = rtrim($uri, '/') ?: '/';

$method = $_SERVER['REQUEST_METHOD'];
$routeKey = "{$method} {$uri}";

// =====================
// API REST (autenticacion por Bearer token, sin sesion)
// =====================

if (str_starts_with($uri, '/api/')) {
    header('Content-Type: application/json; charset=utf-8');
    require_once APP_PATH . '/Services/ApiAuthService.php';

    try {
        // API - Listar campanas
        if ($routeKey === 'GET /api/reports/campaigns') {
            require_once APP_PATH . '/Controllers/ApiReportController.php';
            $controller = new App\Controllers\ApiReportController();
            $controller->campaigns();
            exit;
        }

        // API - Reporte de horas por campana
        if ($method === 'GET' && preg_match('#^/api/reports/hours/(\d+)$#', $uri, $matches)) {
            require_once APP_PATH . '/Controllers/ApiReportController.php';
            $controller = new App\Controllers\ApiReportController();
            $controller->hours((int)$matches[1]);
            exit;
        }

        // API - Reporte unificado (todas las campanas)
        if ($routeKey === 'GET /api/reports/unified') {
            require_once APP_PATH . '/Controllers/ApiReportController.php';
            $controller = new App\Controllers\ApiReportController();
            $controller->unified();
            exit;
        }

        // API - Reporte de asistencia por campana
        if ($method === 'GET' && preg_match('#^/api/reports/attendance/(\d+)$#', $uri, $matches)) {
            require_once APP_PATH . '/Controllers/ApiReportController.php';
            $controller = new App\Controllers\ApiReportController();
            $controller->attendance((int)$matches[1]);
            exit;
        }

        // API - Ruta no encontrada
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint no encontrado']);
        exit;
    } catch (\Throwable $e) {
        error_log('API Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
        exit;
    }
}

// =====================
// RUTAS PUBLICAS
// =====================

if ($routeKey === 'GET /' || $routeKey === 'GET /login') {
    if (isset($_SESSION['user'])) {
        header('Location: ' . BASE_URL . '/dashboard');
        exit;
    }
    require_once APP_PATH . '/Controllers/AuthController.php';
    $controller = new App\Controllers\AuthController();
    $controller->showLogin();
    exit;
}

if ($routeKey === 'POST /' || $routeKey === 'POST /login') {
    \App\Services\CsrfService::validateOrFail();
    require_once APP_PATH . '/Controllers/AuthController.php';
    $controller = new App\Controllers\AuthController();
    $controller->login();
    exit;
}

if ($routeKey === 'GET /logout') {
    require_once APP_PATH . '/Controllers/AuthController.php';
    $controller = new App\Controllers\AuthController();
    $controller->logout();
    exit;
}

// =====================
// VERIFICAR AUTENTICACION
// =====================

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

// =====================
// CSRF — validar en todas las peticiones POST autenticadas
// (login ya se valida arriba; las APIs JSON usan header X-CSRF-TOKEN)
// =====================

if ($method === 'POST') {
    \App\Services\CsrfService::validateOrFail();
}

// =====================
// RUTAS PROTEGIDAS
// =====================

// Dashboard
if ($routeKey === 'GET /dashboard') {
    require_once APP_PATH . '/Controllers/DashboardController.php';
    $controller = new App\Controllers\DashboardController();
    $controller->index();
    exit;
}

// Campañas
if ($routeKey === 'GET /campaigns') {
    require_once APP_PATH . '/Controllers/CampaignController.php';
    $controller = new App\Controllers\CampaignController();
    $controller->index();
    exit;
}

if ($routeKey === 'GET /campaigns/create') {
    require_once APP_PATH . '/Controllers/CampaignController.php';
    $controller = new App\Controllers\CampaignController();
    $controller->create();
    exit;
}

if ($routeKey === 'POST /campaigns') {
    require_once APP_PATH . '/Controllers/CampaignController.php';
    $controller = new App\Controllers\CampaignController();
    $controller->store();
    exit;
}

// Asesores
if ($routeKey === 'GET /advisors') {
    require_once APP_PATH . '/Controllers/AdvisorController.php';
    $controller = new App\Controllers\AdvisorController();
    $controller->index();
    exit;
}

if ($routeKey === 'GET /advisors/bulk-config') {
    require_once APP_PATH . '/Controllers/AdvisorController.php';
    $controller = new App\Controllers\AdvisorController();
    $controller->bulkConfig();
    exit;
}

if ($routeKey === 'POST /advisors/bulk-config') {
    require_once APP_PATH . '/Controllers/AdvisorController.php';
    $controller = new App\Controllers\AdvisorController();
    $controller->bulkConfigStore();
    exit;
}

if ($routeKey === 'GET /advisors/create') {
    require_once APP_PATH . '/Controllers/AdvisorController.php';
    $controller = new App\Controllers\AdvisorController();
    $controller->create();
    exit;
}

if ($routeKey === 'POST /advisors') {
    require_once APP_PATH . '/Controllers/AdvisorController.php';
    $controller = new App\Controllers\AdvisorController();
    $controller->store();
    exit;
}

// Mi Horario (para asesores)
if ($routeKey === 'GET /my-schedule') {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->mySchedule();
    exit;
}

// Horarios
if ($routeKey === 'GET /schedules') {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->index();
    exit;
}

if ($routeKey === 'GET /schedules/generate') {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->showGenerate();
    exit;
}

if ($routeKey === 'POST /schedules/generate') {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->generate();
    exit;
}

if ($routeKey === 'POST /schedules/regenerate-partial') {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->regeneratePartial();
    exit;
}

if ($routeKey === 'GET /schedules/import') {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->showImport();
    exit;
}

if ($routeKey === 'POST /schedules/import') {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->import();
    exit;
}

if ($method === 'POST' && preg_match('#^/schedules/imports/(\d+)/delete$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->deleteImport((int)$matches[1]);
    exit;
}

// Reportes
if ($routeKey === 'GET /reports') {
    require_once APP_PATH . '/Controllers/ReportController.php';
    $controller = new App\Controllers\ReportController();
    $controller->index();
    exit;
}

if ($method === 'GET' && preg_match('#^/reports/hours/(\d+)$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ReportController.php';
    $controller = new App\Controllers\ReportController();
    $controller->hours((int)$matches[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/reports/hours/(\d+)/export$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ReportController.php';
    $controller = new App\Controllers\ReportController();
    $controller->exportHours((int)$matches[1]);
    exit;
}

if ($routeKey === 'GET /reports/export-unified') {
    require_once APP_PATH . '/Controllers/ReportController.php';
    $controller = new App\Controllers\ReportController();
    $controller->exportUnified();
    exit;
}

// Usuarios
if ($routeKey === 'GET /users') {
    require_once APP_PATH . '/Controllers/UserController.php';
    $controller = new App\Controllers\UserController();
    $controller->index();
    exit;
}

if ($routeKey === 'GET /users/create') {
    require_once APP_PATH . '/Controllers/UserController.php';
    $controller = new App\Controllers\UserController();
    $controller->create();
    exit;
}

if ($routeKey === 'POST /users') {
    require_once APP_PATH . '/Controllers/UserController.php';
    $controller = new App\Controllers\UserController();
    $controller->store();
    exit;
}

// Configuracion
if ($routeKey === 'GET /settings') {
    require_once APP_PATH . '/Controllers/SettingController.php';
    $controller = new App\Controllers\SettingController();
    $controller->index();
    exit;
}

if ($routeKey === 'POST /settings/monthly-hours') {
    require_once APP_PATH . '/Controllers/SettingController.php';
    $controller = new App\Controllers\SettingController();
    $controller->saveMonthlyHours();
    exit;
}

if ($routeKey === 'POST /settings/monthly-hours/delete') {
    require_once APP_PATH . '/Controllers/SettingController.php';
    $controller = new App\Controllers\SettingController();
    $controller->deleteMonthlyHours();
    exit;
}

if ($routeKey === 'POST /settings/holidays') {
    require_once APP_PATH . '/Controllers/SettingController.php';
    $controller = new App\Controllers\SettingController();
    $controller->saveHoliday();
    exit;
}

if ($routeKey === 'POST /settings/holidays/delete') {
    require_once APP_PATH . '/Controllers/SettingController.php';
    $controller = new App\Controllers\SettingController();
    $controller->deleteHoliday();
    exit;
}

if ($routeKey === 'POST /settings/params') {
    require_once APP_PATH . '/Controllers/SettingController.php';
    $controller = new App\Controllers\SettingController();
    $controller->saveParams();
    exit;
}

// API Tokens
if ($routeKey === 'POST /settings/api-tokens') {
    require_once APP_PATH . '/Controllers/SettingController.php';
    $controller = new App\Controllers\SettingController();
    $controller->createApiToken();
    exit;
}

if ($routeKey === 'POST /settings/api-tokens/revoke') {
    require_once APP_PATH . '/Controllers/SettingController.php';
    $controller = new App\Controllers\SettingController();
    $controller->revokeApiToken();
    exit;
}

// Changelog
if ($routeKey === 'GET /changelog') {
    require_once APP_PATH . '/Controllers/SettingController.php';
    $controller = new App\Controllers\SettingController();
    $controller->changelog();
    exit;
}

// Roles
if ($routeKey === 'GET /roles') {
    require_once APP_PATH . '/Controllers/RoleController.php';
    $controller = new App\Controllers\RoleController();
    $controller->index();
    exit;
}

if ($routeKey === 'GET /roles/create') {
    require_once APP_PATH . '/Controllers/RoleController.php';
    $controller = new App\Controllers\RoleController();
    $controller->create();
    exit;
}

if ($routeKey === 'POST /roles') {
    require_once APP_PATH . '/Controllers/RoleController.php';
    $controller = new App\Controllers\RoleController();
    $controller->store();
    exit;
}

// =====================
// RUTAS DINAMICAS
// =====================

// Actividades de Campaña
if ($method === 'GET' && preg_match('#^/campaigns/(\d+)/activities$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ActivityController.php';
    $controller = new App\Controllers\ActivityController();
    $controller->index((int)$matches[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/campaigns/(\d+)/activities/create$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ActivityController.php';
    $controller = new App\Controllers\ActivityController();
    $controller->create((int)$matches[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/campaigns/(\d+)/activities$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ActivityController.php';
    $controller = new App\Controllers\ActivityController();
    $controller->store((int)$matches[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/activities/(\d+)/edit$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ActivityController.php';
    $controller = new App\Controllers\ActivityController();
    $controller->edit((int)$matches[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/activities/(\d+)$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ActivityController.php';
    $controller = new App\Controllers\ActivityController();
    $controller->update((int)$matches[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/activities/(\d+)/assignments$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ActivityController.php';
    $controller = new App\Controllers\ActivityController();
    $controller->assignments((int)$matches[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/activities/(\d+)/assignments$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ActivityController.php';
    $controller = new App\Controllers\ActivityController();
    $controller->storeAssignment((int)$matches[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/activities/assignments/(\d+)/remove$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ActivityController.php';
    $controller = new App\Controllers\ActivityController();
    $controller->removeAssignment((int)$matches[1]);
    exit;
}

// Asesores compartidos
if ($method === 'GET' && preg_match('#^/campaigns/(\d+)/shared-advisors$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/SharedAdvisorController.php';
    $controller = new App\Controllers\SharedAdvisorController();
    $controller->index((int)$matches[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/campaigns/(\d+)/shared-advisors/create$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/SharedAdvisorController.php';
    $controller = new App\Controllers\SharedAdvisorController();
    $controller->create((int)$matches[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/campaigns/(\d+)/shared-advisors$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/SharedAdvisorController.php';
    $controller = new App\Controllers\SharedAdvisorController();
    $controller->store((int)$matches[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/shared-advisors/(\d+)/toggle$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/SharedAdvisorController.php';
    $controller = new App\Controllers\SharedAdvisorController();
    $controller->toggle((int)$matches[1]);
    exit;
}

// Campañas - editar
if ($method === 'GET' && preg_match('#^/campaigns/(\d+)/edit$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/CampaignController.php';
    $controller = new App\Controllers\CampaignController();
    $controller->edit((int)$matches[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/campaigns/(\d+)$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/CampaignController.php';
    $controller = new App\Controllers\CampaignController();
    $controller->update((int)$matches[1]);
    exit;
}

// Asesores - editar
if ($method === 'GET' && preg_match('#^/advisors/(\d+)/edit$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/AdvisorController.php';
    $controller = new App\Controllers\AdvisorController();
    $controller->edit((int)$matches[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/advisors/(\d+)$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/AdvisorController.php';
    $controller = new App\Controllers\AdvisorController();
    $controller->update((int)$matches[1]);
    exit;
}

// Usuarios - editar
if ($method === 'GET' && preg_match('#^/users/(\d+)/edit$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/UserController.php';
    $controller = new App\Controllers\UserController();
    $controller->edit((int)$matches[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/users/(\d+)$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/UserController.php';
    $controller = new App\Controllers\UserController();
    $controller->update((int)$matches[1]);
    exit;
}

// Usuarios - resetear contrasena
if ($method === 'POST' && preg_match('#^/users/(\d+)/reset-password$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/UserController.php';
    $controller = new App\Controllers\UserController();
    $controller->resetPassword((int)$matches[1]);
    exit;
}

// Usuarios - toggle status (POST)
if ($method === 'POST' && preg_match('#^/users/(\d+)/toggle-status$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/UserController.php';
    $controller = new App\Controllers\UserController();
    $controller->toggleStatus((int)$matches[1]);
    exit;
}

// Horarios - ver detalle
if ($method === 'GET' && preg_match('#^/schedules/(\d+)$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->show((int)$matches[1]);
    exit;
}

// Horarios - enviar para aprobacion (POST)
if ($method === 'POST' && preg_match('#^/schedules/(\d+)/submit$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->submit((int)$matches[1]);
    exit;
}

// Horarios - aprobar (POST)
if ($method === 'POST' && preg_match('#^/schedules/(\d+)/approve$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->approve((int)$matches[1]);
    exit;
}

// Horarios - rechazar (POST)
if ($method === 'POST' && preg_match('#^/schedules/(\d+)/reject$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->reject((int)$matches[1]);
    exit;
}

// Horarios - seguimiento diario
if ($method === 'GET' && preg_match('#^/schedules/(\d+)/tracking$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->dailyTracking((int)$matches[1]);
    exit;
}

// Horarios - check-in asesor (API JSON)
if ($method === 'POST' && preg_match('#^/schedules/(\d+)/checkin$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->toggleCheckin((int)$matches[1]);
    exit;
}

// Horarios - guardar asistencia (API JSON)
if ($method === 'POST' && preg_match('#^/schedules/(\d+)/attendance$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->saveAttendance((int)$matches[1]);
    exit;
}

// Horarios - actualizar asignaciones (API JSON)
if ($method === 'POST' && preg_match('#^/schedules/(\d+)/assignments$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/ScheduleController.php';
    $controller = new App\Controllers\ScheduleController();
    $controller->updateAssignments((int)$matches[1]);
    exit;
}

// Roles - editar
if ($method === 'GET' && preg_match('#^/roles/(\d+)/edit$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/RoleController.php';
    $controller = new App\Controllers\RoleController();
    $controller->edit((int)$matches[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/roles/(\d+)$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/RoleController.php';
    $controller = new App\Controllers\RoleController();
    $controller->update((int)$matches[1]);
    exit;
}

// Roles - eliminar (POST para seguridad)
if ($method === 'POST' && preg_match('#^/roles/(\d+)/delete$#', $uri, $matches)) {
    require_once APP_PATH . '/Controllers/RoleController.php';
    $controller = new App\Controllers\RoleController();
    $controller->delete((int)$matches[1]);
    exit;
}

// 404
http_response_code(404);
include APP_PATH . '/Views/errors/404.php';
