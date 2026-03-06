<?php

declare(strict_types=1);

namespace App\Controllers;

class ReportController
{
    public function index(): void
    {
        $pageTitle = 'Reportes';
        $currentPage = 'reports';

        include APP_PATH . '/Views/reports/index.php';
    }
}
