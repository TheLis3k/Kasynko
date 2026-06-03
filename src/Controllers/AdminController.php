<?php

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\ReportService;
use App\Models\User;

class AdminController extends BaseController
{
    private ReportService $reports;
    private User          $users;

    public function __construct()
    {
        $pdo           = Database::getInstance();
        $this->reports = new ReportService($pdo);
        $this->users   = new User($pdo);
    }

    public function dashboard(): void
    {
        $this->requireAdmin();
        $stats      = $this->reports->overallStats();
        $gameStats  = $this->reports->gameStats();
        $topSpenders = $this->reports->topSpenders();
        $this->view('admin/dashboard', compact('stats', 'gameStats', 'topSpenders'));
    }

    public function reports(): void
    {
        $this->requireAdmin();
        $activity   = $this->reports->userActivity(20);
        $gameStats  = $this->reports->gameStats();
        $stats      = $this->reports->overallStats();
        $this->view('admin/reports', compact('activity', 'gameStats', 'stats'));
    }
}
