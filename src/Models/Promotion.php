<?php

namespace App\Models;

use PDO;

class Promotion
{
    public function __construct(private PDO $db) {}

    public function findByType(string $type): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM promotions WHERE type = :type AND is_active = TRUE LIMIT 1'
        );
        $stmt->execute([':type' => $type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function all(): array
    {
        return $this->db->query(
            'SELECT * FROM promotions ORDER BY type, id'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Check if user already received a given promotion type. */
    public function userHasPromotion(int $userId, string $type): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM user_promotions up
             JOIN promotions p ON p.id = up.promotion_id
             WHERE up.user_id = :uid AND p.type = :type'
        );
        $stmt->execute([':uid' => $userId, ':type' => $type]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function record(int $userId, int $promotionId, float $amount, string $note = ''): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_promotions (user_id, promotion_id, amount_awarded, note)
             VALUES (:uid, :pid, :amount, :note)'
        );
        $stmt->execute([
            ':uid'    => $userId,
            ':pid'    => $promotionId,
            ':amount' => $amount,
            ':note'   => $note,
        ]);
    }

    public function userHistory(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT up.*, p.name AS promotion_name, p.type AS promotion_type
             FROM user_promotions up
             JOIN promotions p ON p.id = up.promotion_id
             WHERE up.user_id = :uid
             ORDER BY up.created_at DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function topSpendersWithRewardStatus(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.balance,
                    COALESCE(SUM(gs.bet_amount), 0) AS total_staked,
                    EXISTS (
                        SELECT 1 FROM user_promotions up2
                        JOIN promotions p2 ON p2.id = up2.promotion_id
                        WHERE up2.user_id = u.id AND p2.type = \'reward\'
                    ) AS already_rewarded
             FROM users u
             LEFT JOIN game_sessions gs ON gs.user_id = u.id
             WHERE u.role = \'user\'
             GROUP BY u.id, u.first_name, u.last_name, u.email, u.balance
             ORDER BY total_staked DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
