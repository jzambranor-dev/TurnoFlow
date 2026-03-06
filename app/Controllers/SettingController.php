<?php

declare(strict_types=1);

namespace App\Controllers;

class SettingController
{
    public function index(): void
    {
        $pageTitle = 'Configuracion';
        $currentPage = 'settings';

        include APP_PATH . '/Views/settings/index.php';
    }
}
