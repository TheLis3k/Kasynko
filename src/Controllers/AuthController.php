<?php

namespace App\Controllers;

use App\Helpers\{Database, Validation};
use App\Models\User;

class AuthController extends BaseController
{
    private User $users;

    public function __construct()
    {
        $this->users = new User(Database::getInstance());
    }

    public function showLogin(): void
    {
        $this->view('auth/login');
    }

    public function login(): void
    {
        $this->requireCsrf();

        $v = (new Validation($_POST))
            ->required('email', t('auth.email'))
            ->email('email')
            ->required('password', t('auth.password'));

        if (!$v->passes()) {
            $_SESSION['errors'] = $v->errors();
            $_SESSION['old']    = ['email' => $v->get('email', '')];
            redirect('/login');
        }

        $user = $this->users->findByEmail($v->get('email'));
        if (!$user || !password_verify($v->get('password'), $user['password_hash'])) {
            flash('error', t('auth.invalid_credentials'));
            $_SESSION['old'] = ['email' => $v->get('email', '')];
            redirect('/login');
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'              => $user['id'],
            'first_name'      => $user['first_name'],
            'last_name'       => $user['last_name'],
            'email'           => $user['email'],
            'role'            => $user['role'],
            'balance'         => $user['balance'],
            'avatar_path'     => $user['avatar_path'],
            'lang_preference' => $user['lang_preference'],
        ];
        $_SESSION['lang'] = $user['lang_preference'];

        flash('success', t('auth.logged_in'));
        redirect('/bets');
    }

    public function showRegister(): void
    {
        $this->view('auth/register');
    }

    public function register(): void
    {
        $this->requireCsrf();

        $v = (new Validation($_POST))
            ->required('first_name', t('auth.first_name'))->maxLength('first_name', 100, t('auth.first_name'))
            ->required('last_name',  t('auth.last_name'))->maxLength('last_name',  100, t('auth.last_name'))
            ->required('email',      t('auth.email'))->email('email')->maxLength('email', 255, t('auth.email'))
            ->required('password',   t('auth.password'))->minLength('password', 8, t('auth.password'))
            ->required('lang_preference', t('auth.language'))->inList('lang_preference', ['pl', 'en'], t('auth.language'));

        $errors = $v->errors();
        if ($v->get('password') !== $v->get('password_confirm')) {
            $errors['password_confirm'][] = t('auth.passwords_mismatch');
        }

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old']    = array_diff_key($v->all(), array_flip(['password', 'password_confirm']));
            redirect('/register');
        }

        if ($this->users->findByEmail($v->get('email'))) {
            flash('error', t('auth.email_taken'));
            $_SESSION['old'] = array_diff_key($v->all(), array_flip(['password', 'password_confirm']));
            redirect('/register');
        }

        $this->users->create([
            'first_name' => $v->get('first_name'),
            'last_name'  => $v->get('last_name'),
            'email'      => $v->get('email'),
            'password'   => $v->get('password'),
            'balance'    => 1000.00,
            'lang'       => $v->get('lang_preference', 'pl'),
        ]);

        flash('success', t('auth.registered_ok'));
        redirect('/login');
    }

    public function logout(): void
    {
        $this->requireCsrf();
        session_destroy();
        session_start();
        flash('success', t('auth.logged_out'));
        redirect('/login');
    }
}
