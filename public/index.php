<?php

declare(strict_types=1);

/**
 * TurnoFlow - Router Principal
 * v1.9.0 — Tabla de rutas con dispatcher
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Guayaquil');

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/config/database.php';

// BASE_URL: lee de APP_URL en .env, extrae solo el path (sin dominio)
$appUrl = $_ENV['APP_URL'] ?? '/system-horario/TurnoFlow/public';
define('BASE_URL', rtrim(parse_url($appUrl, PHP_URL_PATH) ?: '', '/'));

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
    'use_strict_mode' => true,
]);

require_once APP_PATH . '/Services/CsrfService.php';

// Parse URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = BASE_URL !== '' ? str_replace(BASE_URL, '', $uri) : $uri;
$uri = $uri === '' ? '/' : (rtrim($uri, '/') ?: '/');
$method = $_SERVER['REQUEST_METHOD'];

// =============================================
// API ROUTES (Bearer token auth, no session)
// =============================================

if (str_starts_with($uri, '/api/')) {
    header('Content-Type: application/json; charset=utf-8');
    require_once APP_PATH . '/Services/ApiAuthService.php';

    $apiRoutes = [
        'GET /api/reports/campaigns'    => ['ApiReportController', 'campaigns'],
        'GET /api/reports/unified'      => ['ApiReportController', 'unified'],
    ];

    $apiRegexRoutes = [
        ['GET', '#^/api/reports/hours/(\d+)$#',       'ApiReportController', 'hours'],
        ['GET', '#^/api/reports/attendance/(\d+)$#',   'ApiReportController', 'attendance'],
        ['GET', '#^/api/schedules/(\d+)/coverage-check$#', 'ScheduleApiController', 'coverageCheck', 'session'],
        ['GET', '#^/api/audit/schedule/(\d+)$#',       'AuditController', 'scheduleHistory'],
    ];

    // Session-required API endpoints
    $apiSessionRoutes = [
        'GET /api/notifications/unread'        => ['NotificationController', 'getUnread'],
        'GET /api/dashboard/coverage-trend'     => ['DashboardController', 'coverageTrend'],
        'GET /api/dashboard/schedule-stats'     => ['DashboardController', 'scheduleStats'],
        'GET /api/dashboard/advisor-hours'      => ['DashboardController', 'advisorHours'],
        'GET /api/breaks/report'               => ['BreakComplianceController', 'reportData'],
    ];

    try {
        $routeKey = "{$method} {$uri}";

        // Exact match API routes
        if (isset($apiRoutes[$routeKey])) {
            [$ctrl, $action] = $apiRoutes[$routeKey];
            require_once APP_PATH . "/Controllers/{$ctrl}.php";
            $c = new ("App\\Controllers\\{$ctrl}")();
            $c->$action();
            exit;
        }

        // Session-required API routes
        if (isset($apiSessionRoutes[$routeKey])) {
            if (!isset($_SESSION['user'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                exit;
            }
            [$ctrl, $action] = $apiSessionRoutes[$routeKey];
            require_once APP_PATH . "/Controllers/{$ctrl}.php";
            $c = new ("App\\Controllers\\{$ctrl}")();
            $c->$action();
            exit;
        }

        // Regex API routes
        foreach ($apiRegexRoutes as $route) {
            $requireSession = ($route[4] ?? '') === 'session';
            if ($method === $route[0] && preg_match($route[1], $uri, $matches)) {
                if ($requireSession && !isset($_SESSION['user'])) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'No autenticado']);
                    exit;
                }
                $ctrl = $route[2];
                $action = $route[3];
                require_once APP_PATH . "/Controllers/{$ctrl}.php";
                $c = new ("App\\Controllers\\{$ctrl}")();
                $c->$action((int)$matches[1]);
                exit;
            }
        }

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

// =============================================
// PUBLIC ROUTES (no auth required)
// =============================================

$routeKey = "{$method} {$uri}";

if ($routeKey === 'GET /' || $routeKey === 'GET /login') {
    if (isset($_SESSION['user'])) {
        header('Location: ' . BASE_URL . '/dashboard');
        exit;
    }
    require_once APP_PATH . '/Controllers/AuthController.php';
    (new App\Controllers\AuthController())->showLogin();
    exit;
}

if ($routeKey === 'POST /' || $routeKey === 'POST /login') {
    \App\Services\CsrfService::validateOrFail();
    require_once APP_PATH . '/Controllers/AuthController.php';
    (new App\Controllers\AuthController())->login();
    exit;
}

if ($routeKey === 'POST /logout') {
    \App\Services\CsrfService::validateOrFail();
    require_once APP_PATH . '/Controllers/AuthController.php';
    (new App\Controllers\AuthController())->logout();
    exit;
}

// =============================================
// AUTH WALL
// =============================================

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

// CSRF on all authenticated POST
if ($method === 'POST') {
    \App\Services\CsrfService::validateOrFail();
}

// =============================================
// PROTECTED ROUTES — Exact Match
// =============================================

$exactRoutes = [
    // Dashboard
    'GET /dashboard'                    => ['DashboardController', 'index'],

    // Notifications
    'GET /notifications'                => ['NotificationController', 'index'],
    'POST /notifications/read-all'      => ['NotificationController', 'markAllRead'],

    // Campaigns
    'GET /campaigns'                    => ['CampaignController', 'index'],
    'GET /campaigns/create'             => ['CampaignController', 'create'],
    'POST /campaigns'                   => ['CampaignController', 'store'],

    // Advisors
    'GET /advisors'                     => ['AdvisorController', 'index'],
    'GET /advisors/create'              => ['AdvisorController', 'create'],
    'GET /advisors/import'              => ['AdvisorController', 'showImport'],
    'GET /advisors/import/template'     => ['AdvisorController', 'downloadTemplate'],
    'GET /advisors/import/credentials'  => ['AdvisorController', 'exportCredentials'],
    'POST /advisors/import'             => ['AdvisorController', 'import'],
    'GET /advisors/bulk-config'         => ['AdvisorController', 'bulkConfig'],
    'POST /advisors'                    => ['AdvisorController', 'store'],
    'POST /advisors/bulk-config'        => ['AdvisorController', 'bulkConfigStore'],

    // Schedules — core
    'GET /schedules'                    => ['ScheduleController', 'index'],
    'GET /my-schedule'                  => ['ScheduleController', 'mySchedule'],
    'GET /schedules/generate'           => ['ScheduleController', 'showGenerate'],
    'POST /schedules/generate'          => ['ScheduleController', 'generate'],
    'POST /schedules/regenerate-partial' => ['ScheduleController', 'regeneratePartial'],

    // Schedules — import
    'GET /schedules/import'             => ['ScheduleImportController', 'showImport'],
    'POST /schedules/import'            => ['ScheduleImportController', 'import'],

    // Schedules — API (replacements)
    'POST /schedules/suggest-replacements' => ['ScheduleApiController', 'suggestReplacements'],
    'POST /schedules/apply-replacement'    => ['ScheduleApiController', 'applyReplacement'],

    // Reports
    'GET /reports'                      => ['ReportController', 'index'],
    'GET /reports/export-unified'       => ['ReportController', 'exportUnified'],

    // Users
    'GET /users'                        => ['UserController', 'index'],
    'GET /users/create'                 => ['UserController', 'create'],
    'POST /users'                       => ['UserController', 'store'],

    // Settings
    'GET /settings'                     => ['SettingController', 'index'],
    'POST /settings/monthly-hours'      => ['SettingController', 'saveMonthlyHours'],
    'POST /settings/monthly-hours/delete' => ['SettingController', 'deleteMonthlyHours'],
    'POST /settings/holidays'           => ['SettingController', 'saveHoliday'],
    'POST /settings/holidays/delete'    => ['SettingController', 'deleteHoliday'],
    'POST /settings/params'             => ['SettingController', 'saveParams'],
    'POST /settings/api-tokens'         => ['SettingController', 'createApiToken'],
    'POST /settings/api-tokens/revoke'  => ['SettingController', 'revokeApiToken'],
    'GET /changelog'                    => ['SettingController', 'changelog'],

    // Break Compliance
    'GET /breaks'                       => ['BreakComplianceController', 'index'],
    'GET /breaks/import'                => ['BreakComplianceController', 'showImport'],
    'POST /breaks/import'               => ['BreakComplianceController', 'import'],
    'GET /breaks/export'                => ['BreakComplianceController', 'export'],

    // Audit
    'GET /audit-log'                    => ['AuditController', 'index'],
    'GET /audit-log/export'             => ['AuditController', 'export'],

    // Roles
    'GET /roles'                        => ['RoleController', 'index'],
    'GET /roles/create'                 => ['RoleController', 'create'],
    'POST /roles'                       => ['RoleController', 'store'],
];

if (isset($exactRoutes[$routeKey])) {
    [$ctrl, $action] = $exactRoutes[$routeKey];
    require_once APP_PATH . "/Controllers/{$ctrl}.php";
    $c = new ("App\\Controllers\\{$ctrl}")();
    $c->$action();
    exit;
}

// =============================================
// PROTECTED ROUTES — Regex (dynamic params)
// =============================================

$regexRoutes = [
    // Notifications
    ['POST', '#^/notifications/(\d+)/read$#',              'NotificationController', 'markRead'],

    // Campaigns
    ['GET',  '#^/campaigns/(\d+)/edit$#',                  'CampaignController', 'edit'],
    ['POST', '#^/campaigns/(\d+)$#',                       'CampaignController', 'update'],

    // Activities
    ['GET',  '#^/campaigns/(\d+)/activities$#',            'ActivityController', 'index'],
    ['GET',  '#^/campaigns/(\d+)/activities/create$#',     'ActivityController', 'create'],
    ['POST', '#^/campaigns/(\d+)/activities$#',            'ActivityController', 'store'],
    ['GET',  '#^/activities/(\d+)/edit$#',                 'ActivityController', 'edit'],
    ['POST', '#^/activities/(\d+)$#',                      'ActivityController', 'update'],
    ['GET',  '#^/activities/(\d+)/assignments$#',          'ActivityController', 'assignments'],
    ['POST', '#^/activities/(\d+)/assignments$#',          'ActivityController', 'storeAssignment'],
    ['POST', '#^/activities/assignments/(\d+)/remove$#',   'ActivityController', 'removeAssignment'],

    // Shared Advisors
    ['GET',  '#^/campaigns/(\d+)/shared-advisors$#',       'SharedAdvisorController', 'index'],
    ['GET',  '#^/campaigns/(\d+)/shared-advisors/create$#','SharedAdvisorController', 'create'],
    ['POST', '#^/campaigns/(\d+)/shared-advisors$#',       'SharedAdvisorController', 'store'],
    ['POST', '#^/shared-advisors/(\d+)/toggle$#',          'SharedAdvisorController', 'toggle'],

    // Advisors
    ['GET',  '#^/advisors/(\d+)/edit$#',                   'AdvisorController', 'edit'],
    ['POST', '#^/advisors/(\d+)$#',                        'AdvisorController', 'update'],

    // Schedules — import
    ['POST', '#^/schedules/imports/(\d+)/delete$#',        'ScheduleImportController', 'deleteImport'],

    // Schedules — core
    ['GET',  '#^/schedules/(\d+)/export-pdf$#',            'ScheduleController', 'exportPdf'],
    ['GET',  '#^/schedules/(\d+)$#',                       'ScheduleController', 'show'],
    ['POST', '#^/schedules/(\d+)/submit$#',                'ScheduleController', 'submit'],
    ['POST', '#^/schedules/(\d+)/approve$#',               'ScheduleController', 'approve'],
    ['POST', '#^/schedules/(\d+)/reject$#',                'ScheduleController', 'reject'],

    // Schedules — tracking
    ['GET',  '#^/schedules/(\d+)/tracking$#',              'ScheduleTrackingController', 'dailyTracking'],
    ['POST', '#^/schedules/(\d+)/checkin$#',               'ScheduleTrackingController', 'toggleCheckin'],
    ['POST', '#^/schedules/(\d+)/attendance$#',            'ScheduleTrackingController', 'saveAttendance'],
    ['POST', '#^/schedules/(\d+)/assignments$#',           'ScheduleTrackingController', 'updateAssignments'],

    // Reports
    ['GET',  '#^/reports/hours/(\d+)$#',                   'ReportController', 'hours'],
    ['GET',  '#^/reports/hours/(\d+)/export$#',            'ReportController', 'exportHours'],

    // Users
    ['GET',  '#^/users/(\d+)/edit$#',                      'UserController', 'edit'],
    ['POST', '#^/users/(\d+)$#',                           'UserController', 'update'],
    ['POST', '#^/users/(\d+)/reset-password$#',            'UserController', 'resetPassword'],
    ['POST', '#^/users/(\d+)/toggle-status$#',             'UserController', 'toggleStatus'],

    // Roles
    ['GET',  '#^/roles/(\d+)/edit$#',                      'RoleController', 'edit'],
    ['POST', '#^/roles/(\d+)$#',                           'RoleController', 'update'],
    ['POST', '#^/roles/(\d+)/delete$#',                    'RoleController', 'delete'],
];

foreach ($regexRoutes as [$routeMethod, $pattern, $ctrl, $action]) {
    if ($method === $routeMethod && preg_match($pattern, $uri, $matches)) {
        require_once APP_PATH . "/Controllers/{$ctrl}.php";
        $c = new ("App\\Controllers\\{$ctrl}")();
        $c->$action((int)$matches[1]);
        exit;
    }
}

// 404
http_response_code(404);
include APP_PATH . '/Views/errors/404.php';
