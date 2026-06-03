<?php

namespace App\Services;

class SlotMachine
{
    private const SYMBOLS = ['🍒', '🍋', '🍊', '🍇', '⭐', '🔔', '💎'];

    private const PAYOUTS = [
        '💎💎💎' => 50,
        '⭐⭐⭐' => 20,
        '🔔🔔🔔' => 15,
        '🍇🍇🍇' => 10,
        '🍊🍊🍊' => 8,
        '🍋🍋🍋' => 5,
        '🍒🍒🍒' => 3,
    ];

    public function spin(): array
    {
        return [
            random_int(0, count(self::SYMBOLS) - 1),
            random_int(0, count(self::SYMBOLS) - 1),
            random_int(0, count(self::SYMBOLS) - 1),
        ];
    }

    public function play(float $amount): array
    {
        $reelIndexes = $this->spin();
        $reels = array_map(fn(int $i) => self::SYMBOLS[$i], $reelIndexes);
        $combo = implode('', $reels);

        $multiplier = self::PAYOUTS[$combo] ?? 0;
        $payout     = $amount * $multiplier;
        $isWin      = $multiplier > 0;

        return [
            'reels'      => $reels,
            'combo'      => $combo,
            'multiplier' => $multiplier,
            'payout'     => $payout,
            'is_win'     => $isWin,
        ];
    }

    public static function payoutTable(): array
    {
        return self::PAYOUTS;
    }

    public static function symbols(): array
    {
        return self::SYMBOLS;
    }
}
