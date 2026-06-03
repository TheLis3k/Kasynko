<?php

namespace App\Controllers;

use App\Helpers\{Database, Validation};
use App\Models\{User, Game, GameSession};
use App\Services\SlotMachine;

class SlotController extends BaseController
{
    private User        $users;
    private Game        $games;
    private GameSession $sessions;
    private SlotMachine $machine;

    public function __construct()
    {
        $pdo            = Database::getInstance();
        $this->users    = new User($pdo);
        $this->games    = new Game($pdo);
        $this->sessions = new GameSession($pdo);
        $this->machine  = new SlotMachine();
    }

    public function show(): void
    {
        $this->requireLogin();
        $this->view('games/slots', [
            'result'       => $_SESSION['slots_result'] ?? null,
            'payout_table' => SlotMachine::payoutTable(),
        ]);
        unset($_SESSION['slots_result']);
    }

    public function play(): void
    {
        $this->requireLogin();
        $this->requireCsrf();

        $userId = (int)$_SESSION['user']['id'];
        $user   = $this->users->findById($userId);

        $v = (new Validation($_POST))
            ->required('bet_amount', t('game.bet_amount'))
            ->numeric('bet_amount', t('game.bet_amount'))
            ->min('bet_amount', 0.01, t('game.bet_amount'));

        if (!$v->passes()) {
            flash('error', implode(' ', array_merge(...array_values($v->errors()))));
            redirect('/slots');
        }

        $amount = (float)$v->get('bet_amount');
        if ((float)$user['balance'] < $amount) {
            flash('error', t('game.insufficient'));
            redirect('/slots');
        }

        $result  = $this->machine->play($amount);
        $payout  = $result['payout'];
        $outcome = $result['is_win'] ? 'win' : 'lose';
        $delta   = $payout - $amount;

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $game = $this->games->findByType('slots');
            $this->sessions->create([
                'user_id'    => $userId,
                'game_id'    => $game['id'],
                'bet_type'   => 'bet',
                'bet_value'  => $result['combo'],
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

        $updated = $this->users->findById($userId);
        $_SESSION['user']['balance'] = $updated['balance'];

        $_SESSION['slots_result'] = array_merge($result, ['bet_amount' => $amount]);
        redirect('/slots');
    }
}
