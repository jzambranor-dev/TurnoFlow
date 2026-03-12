<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';

class SettingController
{
    public function index(): void
    {
        AuthService::requirePermission('settings.view');

        $pageTitle = 'Configuración';
        $currentPage = 'settings';

        include APP_PATH . '/Views/settings/index.php';
    }
}
