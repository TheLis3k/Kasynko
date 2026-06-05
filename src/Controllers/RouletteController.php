<?php

namespace App\Controllers;

use App\Helpers\{Database, Validation};
use App\Models\{AuditLog, User, Game, GameSession};
use App\Services\Roulette\RouletteEngine;

class RouletteController extends BaseController
{
    private User           $users;
    private Game           $games;
    private GameSession    $sessions;
    private RouletteEngine $engine;
    private AuditLog       $audit;

    public function __construct()
    {
        $pdo            = Database::getInstance();
        $this->users    = new User($pdo);
        $this->games    = new Game($pdo);
        $this->sessions = new GameSession($pdo);
        $this->engine   = new RouletteEngine();
        $this->audit    = new AuditLog($pdo);
    }

    public function show(): void
    {
        $this->requireLogin();
        $this->view('games/roulette', ['result' => $_SESSION['roulette_result'] ?? null]);
        unset($_SESSION['roulette_result']);
    }

    public function play(): void
    {
        $this->requireLogin();
        $this->requireCsrf();

        $userId = (int)$_SESSION['user']['id'];
        $user   = $this->users->findById($userId);

        $v = (new Validation($_POST))
            ->required('bet_type', t('game.bet_type'))
            ->inList('bet_type', ['number', 'color', 'parity'], t('game.bet_type'))
            ->required('bet_amount', t('game.bet_amount'))
            ->numeric('bet_amount', t('game.bet_amount'))
            ->min('bet_amount', 0.01, t('game.bet_amount'));

        if (!$v->passes()) {
            flash('error', implode(' ', array_merge(...array_values($v->errors()))));
            redirect('/roulette');
        }

        $amount = (float)$v->get('bet_amount');
        if ((float)$user['balance'] < $amount) {
            flash('error', t('game.insufficient'));
            redirect('/roulette');
        }

        $betType  = $v->get('bet_type');
        $betValue = match ($betType) {
            'number' => (string)(int)($_POST['bet_value_number'] ?? 0),
            'color'  => $_POST['bet_value_color']  ?? 'red',
            'parity' => $_POST['bet_value_parity'] ?? 'even',
            default  => '',
        };

        try {
            $result = $this->engine->play($betType, $betValue, $amount);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/roulette');
        }

        $payout  = $result['payout'];
        $outcome = $result['is_win'] ? 'win' : 'lose';
        $delta   = $payout - $amount;

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $game = $this->games->findByType('roulette');
            $this->sessions->create([
                'user_id'    => $userId,
                'game_id'    => $game['id'],
                'bet_type'   => $betType,
                'bet_value'  => $betValue,
                'bet_amount' => $amount,
                'outcome'    => $outcome,
                'payout'     => $payout,
            ]);
            $this->users->updateBalance($userId, $delta);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Refresh session balance
        $updated = $this->users->findById($userId);
        $_SESSION['user']['balance'] = $updated['balance'];

        $this->audit->log(
            $userId,
            $_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name'],
            'play_roulette',
            'game_session',
            null,
            ($outcome === 'win' ? 'WIN' : 'LOSE') . ' — bet=' . $amount . ' payout=' . $payout . ' type=' . $betType . '/' . $betValue
        );

        $_SESSION['roulette_result'] = [
            'winning_number' => $result['winning_number'],
            'is_win'         => $result['is_win'],
            'payout'         => $payout,
            'bet_type'       => $betType,
            'bet_value'      => $betValue,
            'bet_amount'     => $amount,
        ];

        redirect('/roulette');
    }
}
