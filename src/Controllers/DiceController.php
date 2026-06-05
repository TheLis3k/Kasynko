<?php

namespace App\Controllers;

use App\Helpers\{Database, Validation};
use App\Models\{AuditLog, User, Game, GameSession};
use App\Services\DiceGame;

class DiceController extends BaseController
{
    private User        $users;
    private Game        $games;
    private GameSession $sessions;
    private DiceGame    $dice;
    private AuditLog    $audit;

    public function __construct()
    {
        $pdo            = Database::getInstance();
        $this->users    = new User($pdo);
        $this->games    = new Game($pdo);
        $this->sessions = new GameSession($pdo);
        $this->dice     = new DiceGame();
        $this->audit    = new AuditLog($pdo);
    }

    public function show(): void
    {
        $this->requireLogin();
        $this->view('games/dice', [
            'result'       => $_SESSION['dice_result'] ?? null,
            'payout_table' => DiceGame::payoutTable(),
        ]);
        unset($_SESSION['dice_result']);
    }

    public function play(): void
    {
        $this->requireLogin();
        $this->requireCsrf();

        $userId = (int)$_SESSION['user']['id'];
        $user   = $this->users->findById($userId);

        $v = (new Validation($_POST))
            ->required('bet_type',   t('game.bet_type'))
            ->inList('bet_type',     ['total', 'high_low', 'parity'], t('game.bet_type'))
            ->required('bet_amount', t('game.bet_amount'))
            ->numeric('bet_amount',  t('game.bet_amount'))
            ->min('bet_amount',      0.01, t('game.bet_amount'));

        if (!$v->passes()) {
            flash('error', implode(' ', array_merge(...array_values($v->errors()))));
            redirect('/dice');
        }

        $amount   = (float)$v->get('bet_amount');
        $betType  = $v->get('bet_type');
        $betValue = $this->resolveBetValue($betType);

        if ((float)$user['balance'] < $amount) {
            flash('error', t('game.insufficient'));
            redirect('/dice');
        }

        $result  = $this->dice->play($betType, $betValue, $amount);
        $payout  = $result['payout'];
        $outcome = $result['is_win'] ? 'win' : 'lose';
        $delta   = $payout - $amount;

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $game = $this->games->findByType('dice');
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

        $updated = $this->users->findById($userId);
        $_SESSION['user']['balance'] = $updated['balance'];

        $this->audit->log(
            $userId,
            $_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name'],
            'play_dice',
            'game_session',
            null,
            ($outcome === 'win' ? 'WIN' : 'LOSE') . ' — bet=' . $amount . ' payout=' . $payout
                . ' type=' . $betType . '/' . $betValue . ' sum=' . $result['sum']
        );

        $_SESSION['dice_result'] = array_merge($result, ['bet_amount' => $amount]);
        redirect('/dice');
    }

    private function resolveBetValue(string $betType): string
    {
        return match ($betType) {
            'total'    => (string)(int)($_POST['bet_value_total'] ?? 7),
            'high_low' => in_array($_POST['bet_value_hl'] ?? '', ['high', 'low'], true)
                              ? $_POST['bet_value_hl']
                              : 'high',
            'parity'   => in_array($_POST['bet_value_parity'] ?? '', ['even', 'odd'], true)
                              ? $_POST['bet_value_parity']
                              : 'even',
            default    => '',
        };
    }
}
