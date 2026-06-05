<?php

namespace App\Models;

use PDO;

class GameSession
{
    private const SORTABLE = ['created_at', 'bet_amount', 'payout', 'outcome', 'bet_type'];

    public function __construct(private PDO $db) {}

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO game_sessions (user_id, game_id, bet_type, bet_value, bet_amount, outcome, payout, note, document_path)
             VALUES (:user_id, :game_id, :bet_type, :bet_value, :bet_amount, :outcome, :payout, :note, :document_path)
             RETURNING id'
        );
        $stmt->execute([
            ':user_id'       => $data['user_id'],
            ':game_id'       => $data['game_id'],
            ':bet_type'      => $data['bet_type'],
            ':bet_value'     => $data['bet_value'],
            ':bet_amount'    => $data['bet_amount'],
            ':outcome'       => $data['outcome'],
            ':payout'        => $data['payout'],
            ':note'          => $data['note'] ?? null,
            ':document_path' => $data['document_path'] ?? null,
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT gs.*, g.name AS game_name, g.type AS game_type
             FROM game_sessions gs
             JOIN games g ON g.id = gs.game_id
             WHERE gs.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByIdForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT gs.*, g.name AS game_name, g.type AS game_type
             FROM game_sessions gs
             JOIN games g ON g.id = gs.game_id
             WHERE gs.id = :id AND gs.user_id = :user_id LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE game_sessions
             SET user_id = :user_id, game_id = :game_id, bet_type = :bet_type, bet_value = :bet_value,
                 bet_amount = :bet_amount, outcome = :outcome, payout = :payout, note = :note
             WHERE id = :id'
        );
        $stmt->execute([
            ':id'         => $id,
            ':user_id'    => $data['user_id'],
            ':game_id'    => $data['game_id'],
            ':bet_type'   => $data['bet_type'],
            ':bet_value'  => $data['bet_value'],
            ':bet_amount' => $data['bet_amount'],
            ':outcome'    => $data['outcome'],
            ':payout'     => $data['payout'],
            ':note'       => $data['note'] ?? null,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM game_sessions WHERE id = :id')->execute([':id' => $id]);
    }

    /** Returns ['rows', 'total', 'pages'] */
    public function paginate(array $filters, int $page, int $perPage, string $sort, string $dir): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $dir   = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $sort  = in_array($sort, self::SORTABLE, true) ? $sort : 'created_at';
        $order = "gs.{$sort} {$dir}";

        $countSql = "SELECT COUNT(*) FROM game_sessions gs JOIN games g ON g.id = gs.game_id {$where}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $pages  = max(1, (int)ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT gs.*, g.name AS game_name, g.type AS game_type,
                       u.first_name, u.last_name
                FROM game_sessions gs
                JOIN games g ON g.id = gs.game_id
                JOIN users u ON u.id = gs.user_id
                {$where}
                ORDER BY {$order}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages];
    }

    /** Like paginate but returns ALL matching rows (for export) */
    public function findFiltered(array $filters, string $sort, string $dir): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $dir  = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'created_at';

        $sql = "SELECT gs.*, g.name AS game_name, g.type AS game_type
                FROM game_sessions gs
                JOIN games g ON g.id = gs.game_id
                {$where}
                ORDER BY gs.{$sort} {$dir}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Aggregation totals for summary panel */
    public function aggregates(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT COUNT(*)            AS total_count,
                       COALESCE(SUM(gs.bet_amount), 0) AS total_staked,
                       COALESCE(SUM(gs.payout), 0)     AS total_payout,
                       COALESCE(SUM(gs.bet_amount) - SUM(gs.payout), 0) AS net_revenue
                FROM game_sessions gs
                JOIN games g ON g.id = gs.game_id
                {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params  = [];

        if (!empty($filters['user_id'])) {
            $clauses[] = 'gs.user_id = :user_id';
            $params[':user_id'] = (int)$filters['user_id'];
        }
        if (!empty($filters['game_type'])) {
            $clauses[] = 'g.type = :game_type';
            $params[':game_type'] = $filters['game_type'];
        }
        if (!empty($filters['outcome'])) {
            $clauses[] = "gs.outcome = :outcome";
            $params[':outcome'] = $filters['outcome'];
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = 'gs.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'gs.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (isset($filters['min_amount']) && $filters['min_amount'] !== '') {
            $clauses[] = 'gs.bet_amount >= :min_amount';
            $params[':min_amount'] = (float)$filters['min_amount'];
        }
        if (isset($filters['max_amount']) && $filters['max_amount'] !== '') {
            $clauses[] = 'gs.bet_amount <= :max_amount';
            $params[':max_amount'] = (float)$filters['max_amount'];
        }
        if (!empty($filters['search'])) {
            $clauses[] = "(gs.bet_type ILIKE :search OR gs.bet_value ILIKE :search OR gs.note ILIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        return [$where, $params];
    }
}
