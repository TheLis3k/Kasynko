<?php

namespace App\Controllers;

use App\Helpers\{Database, Validation, FileUploader};
use App\Models\{Game, GameSession};
use App\Services\Export\{CsvExporter, JsonExporter};

class BetController extends BaseController
{
    private GameSession $sessions;
    private Game        $games;
    private const PER_PAGE = 15;

    public function __construct()
    {
        $pdo            = Database::getInstance();
        $this->sessions = new GameSession($pdo);
        $this->games    = new Game($pdo);
    }

    public function index(): void
    {
        $this->requireLogin();

        $user    = $this->currentUser();
        $isAdmin = $user['role'] === 'admin';

        $filters = $this->parseFilters($isAdmin ? null : (int)$user['id']);
        $sort    = $this->safeSort($_GET['sort'] ?? 'created_at');
        $dir     = (($_GET['dir'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';
        $page    = max(1, (int)($_GET['page'] ?? 1));

        $result     = $this->sessions->paginate($filters, $page, self::PER_PAGE, $sort, $dir);
        $aggregates = $this->sessions->aggregates($filters);
        $games      = $this->games->all();

        $this->view('bets/index', compact('result', 'aggregates', 'games', 'filters', 'sort', 'dir', 'page'));
    }

    public function create(): void
    {
        $this->requireLogin();
        $games = $this->games->all();
        $this->view('bets/create', compact('games'));
    }

    public function store(): void
    {
        $this->requireLogin();
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
            'user_id'       => (int)$this->currentUser()['id'],
            'game_id'       => (int)$v->get('game_id'),
            'bet_type'      => $v->get('bet_type'),
            'bet_value'     => $v->get('bet_value'),
            'bet_amount'    => (float)$v->get('bet_amount'),
            'outcome'       => $v->get('outcome'),
            'payout'        => (float)$v->get('payout'),
            'note'          => $v->get('note'),
            'document_path' => $documentPath,
        ]);

        flash('success', t('bets.created'));
        redirect('/bets');
    }

    public function edit(int $id): void
    {
        $this->requireLogin();
        $session = $this->loadSession($id);
        $games   = $this->games->all();
        $this->view('bets/edit', compact('session', 'games'));
    }

    public function update(int $id): void
    {
        $this->requireLogin();
        $this->requireCsrf();
        $this->loadSession($id);

        $v = $this->validateBetForm();
        if (!$v->passes()) {
            $_SESSION['errors'] = $v->errors();
            $_SESSION['old']    = $v->all();
            redirect('/bets/' . $id . '/edit');
        }

        $this->sessions->update($id, [
            'game_id'    => (int)$v->get('game_id'),
            'bet_type'   => $v->get('bet_type'),
            'bet_value'  => $v->get('bet_value'),
            'bet_amount' => (float)$v->get('bet_amount'),
            'outcome'    => $v->get('outcome'),
            'payout'     => (float)$v->get('payout'),
            'note'       => $v->get('note'),
        ]);

        flash('success', t('bets.updated'));
        redirect('/bets');
    }

    public function delete(int $id): void
    {
        $this->requireLogin();
        $this->requireCsrf();
        $session = $this->loadSession($id);

        if (!empty($session['document_path'])) {
            FileUploader::delete($session['document_path']);
        }

        $this->sessions->delete($id);
        flash('success', t('bets.deleted'));
        redirect('/bets');
    }

    public function export(): void
    {
        $this->requireLogin();

        $user    = $this->currentUser();
        $isAdmin = $user['role'] === 'admin';
        $filters = $this->parseFilters($isAdmin ? null : (int)$user['id']);
        $sort    = $this->safeSort($_GET['sort'] ?? 'created_at');
        $dir     = (($_GET['dir'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';

        $rows   = $this->sessions->findFiltered($filters, $sort, $dir);
        $format = $_GET['format'] ?? 'csv';

        if ($format === 'json') {
            (new JsonExporter())->export($rows, 'bets_export');
        } else {
            (new CsvExporter())->export($rows, 'bets_export');
        }
    }

    // ---- Private helpers ----

    private function loadSession(int $id): array
    {
        $user    = $this->currentUser();
        $isAdmin = $user['role'] === 'admin';
        $session = $isAdmin
            ? $this->sessions->findById($id)
            : $this->sessions->findByIdForUser($id, (int)$user['id']);

        if (!$session) {
            flash('error', t('bets.not_found'));
            redirect('/bets');
        }

        return $session;
    }

    private function validateBetForm(): Validation
    {
        return (new Validation($_POST))
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
            $f['user_id'] = $userId;
        }
        return $f;
    }

    private function safeSort(string $col): string
    {
        $allowed = ['created_at', 'bet_amount', 'payout', 'outcome', 'bet_type'];
        return in_array($col, $allowed, true) ? $col : 'created_at';
    }
}
