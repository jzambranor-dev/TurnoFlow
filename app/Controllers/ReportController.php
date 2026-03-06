<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;

require_once APP_PATH . '/Services/AuthService.php';

class ReportController
{
    public function index(): void
    {
        AuthService::requirePermission('reports.view');

        $pageTitle = 'Reportes';
        $currentPage = 'reports';

        include APP_PATH . '/Views/reports/index.php';
    }
}
