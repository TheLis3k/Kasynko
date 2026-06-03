<?php

namespace App\Services;

use PDO;

class ReportService
{
    public function __construct(private PDO $db) {}

    public function overallStats(): array
    {
        return $this->db->query(
            "SELECT
                (SELECT COUNT(*) FROM users WHERE role = 'user') AS total_users,
                (SELECT COUNT(*) FROM game_sessions)              AS total_sessions,
                COALESCE((SELECT SUM(bet_amount) FROM game_sessions), 0) AS total_staked,
                COALESCE((SELECT SUM(bet_amount) - SUM(payout) FROM game_sessions), 0) AS net_revenue"
        )->fetch();
    }

    public function gameStats(): array
    {
        return $this->db->query(
            "SELECT g.name, g.type,
                    COUNT(gs.id)                AS sessions,
                    COALESCE(SUM(gs.bet_amount), 0) AS total_staked,
                    COALESCE(SUM(gs.payout), 0)     AS total_payout,
                    COALESCE(SUM(gs.bet_amount) - SUM(gs.payout), 0) AS revenue
             FROM games g
             LEFT JOIN game_sessions gs ON gs.game_id = g.id
             GROUP BY g.id, g.name, g.type
             ORDER BY sessions DESC"
        )->fetchAll();
    }

    public function userActivity(int $limit = 10): array
    {
        return $this->fetchWithLimit($limit);
    }

    public function topSpenders(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.email,
                    COALESCE(SUM(gs.bet_amount), 0) AS total_staked
             FROM users u
             LEFT JOIN game_sessions gs ON gs.user_id = u.id
             WHERE u.role = 'user'
             GROUP BY u.id, u.first_name, u.last_name, u.email
             ORDER BY total_staked DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function fetchWithLimit(int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.email,
                    COUNT(gs.id)                AS sessions,
                    COALESCE(SUM(gs.bet_amount), 0) AS total_staked,
                    COALESCE(SUM(gs.payout), 0)     AS total_payout,
                    MAX(gs.created_at)              AS last_played
             FROM users u
             LEFT JOIN game_sessions gs ON gs.user_id = u.id
             WHERE u.role = 'user'
             GROUP BY u.id, u.first_name, u.last_name, u.email
             ORDER BY sessions DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
