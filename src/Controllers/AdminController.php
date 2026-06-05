<?php

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\{ReportService, KMeansService, PromotionService};
use App\Models\{AuditLog, User};

class AdminController extends BaseController
{
    private ReportService    $reports;
    private KMeansService    $kmeans;
    private PromotionService $promotions;
    private User             $users;
    private AuditLog         $audit;

    public function __construct()
    {
        $pdo              = Database::getInstance();
        $this->reports    = new ReportService($pdo);
        $this->kmeans     = new KMeansService($pdo);
        $this->promotions = new PromotionService($pdo);
        $this->users      = new User($pdo);
        $this->audit      = new AuditLog($pdo);
    }

    public function dashboard(): void
    {
        $this->requireAdmin();
        $stats       = $this->reports->overallStats();
        $gameStats   = $this->reports->gameStats();
        $topSpenders = $this->reports->topSpenders();
        $auditLog    = $this->audit->recent(20);
        $this->view('admin/dashboard', compact('stats', 'gameStats', 'topSpenders', 'auditLog'));
    }

    public function reports(): void
    {
        $this->requireAdmin();
        $activity  = $this->reports->userActivity(20);
        $gameStats = $this->reports->gameStats();
        $stats     = $this->reports->overallStats();
        $this->view('admin/reports', compact('activity', 'gameStats', 'stats'));
    }

    public function segments(): void
    {
        $this->requireAdmin();
        $segments = $this->kmeans->getSegments();
        $this->view('admin/segments', compact('segments'));
    }

    public function runClustering(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $result = $this->kmeans->clusterAndPersist();
        if (empty($result)) {
            flash('error', t('kmeans.not_enough_players'));
        } else {
            flash('success', sprintf(t('kmeans.clustered'), count($result)));
            $actor = $this->currentUser();
            $this->audit->log(
                (int)$actor['id'],
                $actor['first_name'] . ' ' . $actor['last_name'],
                'kmeans_cluster',
                'player_segments',
                null,
                'K-means clustering executed: ' . count($result) . ' players classified'
            );
        }
        redirect('/admin/segments');
    }

    public function promotions(): void
    {
        $this->requireAdmin();
        $topSpenders = $this->promotions->getTopSpendersWithStatus(20);
        $this->view('admin/promotions', compact('topSpenders'));
    }

    public function awardReward(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $userId = (int)($_POST['user_id'] ?? 0);
        $note   = trim($_POST['note'] ?? '');

        if (!$userId || !$this->users->findById($userId)) {
            flash('error', t('promo.user_not_found'));
            redirect('/admin/promotions');
        }

        $amount = $this->promotions->grantReward($userId, $note);
        if ($amount > 0) {
            flash('success', sprintf(t('promo.reward_granted'), number_format($amount, 2)));
            $actor = $this->currentUser();
            $this->audit->log(
                (int)$actor['id'],
                $actor['first_name'] . ' ' . $actor['last_name'],
                'award_reward',
                'user_promotions',
                $userId,
                'Reward ' . number_format($amount, 2) . ' awarded to user #' . $userId . ($note ? ' — ' . $note : '')
            );
        } else {
            flash('error', t('promo.reward_not_configured'));
        }
        redirect('/admin/promotions');
    }
}
