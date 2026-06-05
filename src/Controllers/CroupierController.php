<?php

namespace App\Controllers;

use App\Helpers\Database;
use App\Models\AuditLog;
use App\Services\ReportService;

class CroupierController extends BaseController
{
    private ReportService $reports;
    private AuditLog      $audit;

    public function __construct()
    {
        $pdo           = Database::getInstance();
        $this->reports = new ReportService($pdo);
        $this->audit   = new AuditLog($pdo);
    }

    public function dashboard(): void
    {
        $this->requireCroupier();
        $gameStats = $this->reports->gameStats();
        $activity  = $this->reports->userActivity(10);
        $auditLog  = $this->audit->recent(30);
        $this->view('croupier/dashboard', compact('gameStats', 'activity', 'auditLog'));
    }
}
