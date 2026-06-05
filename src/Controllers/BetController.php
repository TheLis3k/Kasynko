<?php

namespace App\Controllers;

use App\Helpers\{Database, Validation, FileUploader};
use App\Models\{AuditLog, Game, GameSession, User};
use App\Services\Export\{CsvExporter, JsonExporter, PdfExporter};
use App\Services\SlotMachine;

class BetController extends BaseController
{
    private GameSession $sessions;
    private Game        $games;
    private User        $users;
    private AuditLog    $audit;
    private const PER_PAGE = 15;

    public function __construct()
    {
        $pdo            = Database::getInstance();
        $this->sessions = new GameSession($pdo);
        $this->games    = new Game($pdo);
        $this->users    = new User($pdo);
        $this->audit    = new AuditLog($pdo);
    }

    public function index(): void
    {
        $this->requireLogin();

        $user       = $this->currentUser();
        $role       = $user['role'];
        $isAdmin    = $role === 'admin';
        $isCroupier = $role === 'croupier';
        $canSeeAll  = $isAdmin || $isCroupier;

        $filters = $this->parseFilters($canSeeAll ? null : (int)$user['id']);
        $sort    = $this->safeSort($_GET['sort'] ?? 'created_at');
        $dir     = (($_GET['dir'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';
        $page    = max(1, (int)($_GET['page'] ?? 1));

        $result     = $this->sessions->paginate($filters, $page, self::PER_PAGE, $sort, $dir);
        $aggregates = $this->sessions->aggregates($filters);
        $games      = $this->games->all();
        $users      = $canSeeAll ? $this->users->all() : [];

        $this->view('bets/index', compact('result', 'aggregates', 'games', 'users', 'filters', 'sort', 'dir', 'page', 'isAdmin', 'isCroupier'));
    }

    public function create(): void
    {
        $this->requireAdmin();
        $games   = $this->games->all();
        $symbols = SlotMachine::symbols();
        $users   = $this->users->all();
        $this->view('bets/create', compact('games', 'symbols', 'users'));
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $v = $this->validateBetForm();
        if (!$v->passes()) {
            $_SESSION['errors'] = $v->errors();
            $_SESSION['old']    = $v->all();
            redirect('/bets/create');
        }

        $documentPath = null;
        if (!empty($_FILES['document']['name'])) {
            try {
                $uploader     = new FileUploader('documents');
                $documentPath = $uploader->store($_FILES['document'], (int)$this->currentUser()['id']);
            } catch (\RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect('/bets/create');
            }
        }

        $this->sessions->create([
            'user_id'       => (int)$v->get('user_id'),
            'game_id'       => (int)$v->get('game_id'),
            'bet_type'      => $v->get('bet_type'),
            'bet_value'     => $v->get('bet_value'),
            'bet_amount'    => (float)$v->get('bet_amount'),
            'outcome'       => $v->get('outcome'),
            'payout'        => (float)$v->get('payout'),
            'note'          => $v->get('note'),
            'document_path' => $documentPath,
        ]);

        $actor = $this->currentUser();
        $this->audit->log(
            (int)$actor['id'],
            $actor['first_name'] . ' ' . $actor['last_name'],
            'create',
            'game_session',
            null,
            'Bet created manually: outcome=' . $v->get('outcome') . ', amount=' . $v->get('bet_amount')
        );

        flash('success', t('bets.created'));
        redirect('/bets');
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        $session = $this->loadSession($id);
        $games   = $this->games->all();
        $symbols = SlotMachine::symbols();
        $users   = $this->users->all();
        $this->view('bets/edit', compact('session', 'games', 'symbols', 'users'));
    }

    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $this->loadSession($id);

        $v = $this->validateBetForm();
        if (!$v->passes()) {
            $_SESSION['errors'] = $v->errors();
            $_SESSION['old']    = $v->all();
            redirect('/bets/' . $id . '/edit');
        }

        $this->sessions->update($id, [
            'user_id'    => (int)$v->get('user_id'),
            'game_id'    => (int)$v->get('game_id'),
            'bet_type'   => $v->get('bet_type'),
            'bet_value'  => $v->get('bet_value'),
            'bet_amount' => (float)$v->get('bet_amount'),
            'outcome'    => $v->get('outcome'),
            'payout'     => (float)$v->get('payout'),
            'note'       => $v->get('note'),
        ]);

        $actor = $this->currentUser();
        $this->audit->log(
            (int)$actor['id'],
            $actor['first_name'] . ' ' . $actor['last_name'],
            'update',
            'game_session',
            $id,
            'Bet updated: outcome=' . $v->get('outcome') . ', amount=' . $v->get('bet_amount')
        );

        flash('success', t('bets.updated'));
        redirect('/bets');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $session = $this->loadSession($id);

        if (!empty($session['document_path'])) {
            FileUploader::delete($session['document_path']);
        }

        $this->sessions->delete($id);

        $actor = $this->currentUser();
        $this->audit->log(
            (int)$actor['id'],
            $actor['first_name'] . ' ' . $actor['last_name'],
            'delete',
            'game_session',
            $id,
            'Bet deleted: game=' . ($session['game_name'] ?? '') . ', amount=' . $session['bet_amount']
        );

        flash('success', t('bets.deleted'));
        redirect('/bets');
    }

    public function export(): void
    {
        $this->requireLogin();

        $user      = $this->currentUser();
        $canSeeAll = in_array($user['role'], ['admin', 'croupier'], true);
        $filters   = $this->parseFilters($canSeeAll ? null : (int)$user['id']);
        $sort      = $this->safeSort($_GET['sort'] ?? 'created_at');
        $dir       = (($_GET['dir'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';

        $rows   = $this->sessions->findFiltered($filters, $sort, $dir);
        $format = $_GET['format'] ?? 'csv';

        match ($format) {
            'json' => (new JsonExporter())->export($rows, 'bets_export'),
            'pdf'  => (new PdfExporter())->export($rows, 'bets_export'),
            default => (new CsvExporter())->export($rows, 'bets_export'),
        };
    }

    // ---- Private helpers ----

    private function loadSession(int $id): array
    {
        $user      = $this->currentUser();
        $canSeeAll = in_array($user['role'], ['admin', 'croupier'], true);
        $session   = $canSeeAll
            ? $this->sessions->findById($id)
            : $this->sessions->findByIdForUser($id, (int)$user['id']);

        if (!$session) {
            flash('error', t('bets.not_found'));
            redirect('/bets');
        }

        return $session;
    }

    private function resolveBetValue(): void
    {
        $type = $_POST['bet_type'] ?? '';
        $_POST['bet_value'] = match ($type) {
            'number' => (string)(int)($_POST['bet_value_number'] ?? 0),
            'color'  => $_POST['bet_value_color']  ?? 'red',
            'parity' => $_POST['bet_value_parity'] ?? 'even',
            'bet'    => ($_POST['bet_reel_1'] ?? '') . ($_POST['bet_reel_2'] ?? '') . ($_POST['bet_reel_3'] ?? ''),
            default  => '',
        };
    }

    private function validateBetForm(): Validation
    {
        $this->resolveBetValue();
        return (new Validation($_POST))
            ->required('user_id', t('bets.user'))->numeric('user_id', t('bets.user'))
            ->required('game_id', t('bets.game'))->numeric('game_id', t('bets.game'))
            ->required('bet_type', t('bets.type'))->maxLength('bet_type', 50, t('bets.type'))
            ->required('bet_value', t('bets.value'))->maxLength('bet_value', 100, t('bets.value'))
            ->required('bet_amount', t('bets.amount'))->numeric('bet_amount', t('bets.amount'))->min('bet_amount', 0.01, t('bets.amount'))
            ->required('outcome', t('bets.outcome'))->inList('outcome', ['win', 'lose'], t('bets.outcome'))
            ->required('payout', t('bets.payout'))->numeric('payout', t('bets.payout'));
    }

    private function parseFilters(?int $userId): array
    {
        $f = [
            'search'     => $_GET['search']     ?? '',
            'game_type'  => $_GET['game_type']  ?? '',
            'outcome'    => $_GET['outcome']    ?? '',
            'date_from'  => $_GET['date_from']  ?? '',
            'date_to'    => $_GET['date_to']    ?? '',
            'min_amount' => $_GET['min_amount'] ?? '',
            'max_amount' => $_GET['max_amount'] ?? '',
        ];
        if ($userId !== null) {
            // regular user — always locked to their own records
            $f['user_id'] = $userId;
        } elseif (!empty($_GET['user_id'])) {
            // admin chose a specific user from the dropdown
            $f['user_id'] = (int)$_GET['user_id'];
        }
        return $f;
    }

    private function safeSort(string $col): string
    {
        $allowed = ['created_at', 'bet_amount', 'payout', 'outcome', 'bet_type'];
        return in_array($col, $allowed, true) ? $col : 'created_at';
    }
}
