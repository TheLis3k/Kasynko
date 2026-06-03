<?php

namespace App\Controllers;

use App\Helpers\{Database, FileUploader, Validation};
use App\Models\User;

class ProfileController extends BaseController
{
    private User $users;

    public function __construct()
    {
        $this->users = new User(Database::getInstance());
    }

    public function show(): void
    {
        $this->requireLogin();
        $user = $this->users->findById((int)$_SESSION['user']['id']);
        $this->view('profile/show', ['user' => $user]);
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

        $this->users->delete($userId);
        session_destroy();
        session_start();
        flash('success', 'Konto zostało usunięte.');
        redirect('/login');
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
