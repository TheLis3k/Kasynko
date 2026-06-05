<?php

namespace App\Services;

use App\Models\{Promotion, User};
use PDO;

class PromotionService
{
    private Promotion $promotions;
    private User      $users;

    public function __construct(private PDO $db)
    {
        $this->promotions = new Promotion($db);
        $this->users      = new User($db);
    }

    /**
     * Grant the welcome bonus to a newly registered user (once only).
     * Returns the amount awarded, or 0 if not applicable.
     */
    public function grantWelcomeBonus(int $userId): float
    {
        $promo = $this->promotions->findByType('welcome');
        if (!$promo) {
            return 0.0;
        }

        if ($this->promotions->userHasPromotion($userId, 'welcome')) {
            return 0.0;
        }

        $amount = (float)$promo['amount'];
        $this->applyBonus($userId, (int)$promo['id'], $amount, 'Bonus powitalny');
        return $amount;
    }

    /**
     * Calculate and grant a deposit bonus.
     * Returns the bonus amount added on top of the deposit.
     */
    public function grantDepositBonus(int $userId, float $depositAmount): float
    {
        $promo = $this->promotions->findByType('deposit');
        if (!$promo || (float)$promo['rate'] <= 0) {
            return 0.0;
        }

        $bonus = round($depositAmount * (float)$promo['rate'], 2);
        if ($bonus <= 0) {
            return 0.0;
        }

        $this->applyBonus(
            $userId,
            (int)$promo['id'],
            $bonus,
            sprintf('Bonus od wpłaty %.2f (%.0f%%)', $depositAmount, (float)$promo['rate'] * 100)
        );
        return $bonus;
    }

    /**
     * Admin manually awards the reward promotion to a user.
     */
    public function grantReward(int $userId, string $adminNote = ''): float
    {
        $promo = $this->promotions->findByType('reward');
        if (!$promo) {
            return 0.0;
        }

        $amount = (float)$promo['amount'];
        $note   = $adminNote ?: 'Nagroda top-spender';
        $this->applyBonus($userId, (int)$promo['id'], $amount, $note);
        return $amount;
    }

    public function getTopSpendersWithStatus(int $limit = 10): array
    {
        return $this->promotions->topSpendersWithRewardStatus($limit);
    }

    // -----------------------------------------------------------------

    private function applyBonus(int $userId, int $promotionId, float $amount, string $note): void
    {
        $this->db->beginTransaction();
        try {
            $this->users->updateBalance($userId, $amount);
            $this->promotions->record($userId, $promotionId, $amount, $note);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
