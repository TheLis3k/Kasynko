<?php

namespace App\Controllers;

use App\Helpers\{Database, FileUploader, Validation};
use App\Models\{AuditLog, User, Promotion};
use App\Services\{KMeansService, PromotionService};

class ProfileController extends BaseController
{
    private User             $users;
    private PromotionService $promotions;
    private Promotion        $promotionModel;
    private KMeansService    $kmeans;
    private AuditLog         $audit;

    public function __construct()
    {
        $pdo                  = Database::getInstance();
        $this->users          = new User($pdo);
        $this->promotions     = new PromotionService($pdo);
        $this->promotionModel = new Promotion($pdo);
        $this->kmeans         = new KMeansService($pdo);
        $this->audit          = new AuditLog($pdo);
    }

    public function show(): void
    {
        $this->requireLogin();
        $userId  = (int)$_SESSION['user']['id'];
        $user    = $this->users->findById($userId);
        $segment = $this->kmeans->getSegmentForUser($userId);
        $this->view('profile/show', compact('user', 'segment'));
    }

    public function update(): void
    {
        $this->requireLogin();
        $this->requireCsrf();

        $v = (new Validation($_POST))
            ->required('first_name', t('auth.first_name'))->maxLength('first_name', 100, t('auth.first_name'))
            ->required('last_name',  t('auth.last_name'))->maxLength('last_name',  100, t('auth.last_name'))
            ->inList('lang_preference', ['pl', 'en'], t('auth.language'));

        if (!$v->passes()) {
            $_SESSION['errors'] = $v->errors();
            $_SESSION['old']    = $v->all();
            redirect('/profile');
        }

        $data = [
            'first_name'      => $v->get('first_name'),
            'last_name'       => $v->get('last_name'),
            'lang_preference' => $v->get('lang_preference', 'pl'),
        ];

        if (!empty($_FILES['avatar']['name'])) {
            try {
                $uploader = new FileUploader('avatars');
                $oldPath  = $_SESSION['user']['avatar_path'] ?? '';
                if ($oldPath) {
                    FileUploader::delete($oldPath);
                }
                $data['avatar_path'] = $uploader->store($_FILES['avatar'], (int)$_SESSION['user']['id']);
            } catch (\RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect('/profile');
            }
        }

        $this->users->updateProfile((int)$_SESSION['user']['id'], $data);

        // Refresh session
        $user = $this->users->findById((int)$_SESSION['user']['id']);
        $_SESSION['user'] = array_merge($_SESSION['user'], [
            'first_name'      => $user['first_name'],
            'last_name'       => $user['last_name'],
            'lang_preference' => $user['lang_preference'],
            'avatar_path'     => $user['avatar_path'],
        ]);
        $_SESSION['lang'] = $user['lang_preference'];

        $this->audit->log(
            $userId,
            $user['first_name'] . ' ' . $user['last_name'],
            'update_profile',
            'users',
            $userId,
            'Profile updated: lang=' . $data['lang_preference'] . (isset($data['avatar_path']) ? ', avatar changed' : '')
        );

        flash('success', t('auth.profile_updated'));
        redirect('/profile');
    }

    public function delete(): void
    {
        $this->requireLogin();
        $this->requireCsrf();

        $userId = (int)$_SESSION['user']['id'];
        $user   = $this->users->findById($userId);

        if ($user && $user['avatar_path']) {
            FileUploader::delete($user['avatar_path']);
        }

        $this->audit->log(
            $userId,
            ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''),
            'delete_account',
            'users',
            $userId,
            'User account deleted: ' . ($user['email'] ?? '')
        );

        $this->users->delete($userId);
        session_destroy();
        session_start();
        flash('success', t('profile.deleted'));
        redirect('/login');
    }

    public function showDeposit(): void
    {
        $this->requireLogin();
        $history = $this->promotionModel->userHistory((int)$_SESSION['user']['id']);
        $this->view('profile/deposit', ['history' => $history]);
    }

    public function deposit(): void
    {
        $this->requireLogin();
        $this->requireCsrf();

        $v = (new Validation($_POST))
            ->required('amount', t('deposit.amount'))
            ->numeric('amount', t('deposit.amount'))
            ->min('amount', 0.01, t('deposit.amount'));

        if (!$v->passes()) {
            $_SESSION['errors'] = $v->errors();
            redirect('/profile/deposit');
        }

        $amount = (float)$v->get('amount');
        $userId = (int)$_SESSION['user']['id'];

        $this->users->updateBalance($userId, $amount);
        $bonus = $this->promotions->grantDepositBonus($userId, $amount);

        // Refresh session balance
        $user = $this->users->findById($userId);
        $_SESSION['user']['balance'] = $user['balance'];

        $msg = sprintf(t('deposit.success'), number_format($amount, 2));
        if ($bonus > 0) {
            $msg .= ' ' . sprintf(t('promo.deposit_bonus_granted'), number_format($bonus, 2));
        }

        flash('success', $msg);
        redirect('/profile/deposit');
    }

    public function serveAvatar(string $filename): void
    {
        $this->requireLogin();

        $cfg  = require dirname(__DIR__, 2) . '/config/config.php';
        $path = $cfg['app']['upload_dir'] . '/avatars/' . basename($filename);

        if (!file_exists($path)) {
            http_response_code(404);
            exit;
        }

        $mime = mime_content_type($path);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }
}
