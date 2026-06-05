<?php

namespace App\Services;

class DiceGame
{
    // Exact-sum payouts (multiplier applied to bet; net gain = payout - bet)
    private const TOTAL_PAYOUTS = [
        2  => 30, 12 => 30,
        3  => 15, 11 => 15,
        4  => 10, 10 => 10,
        5  =>  7,  9 =>  7,
        6  =>  5,  8 =>  5,
        7  =>  4,
    ];

    public function roll(): array
    {
        return [random_int(1, 6), random_int(1, 6)];
    }

    public function play(string $betType, string $betValue, float $amount): array
    {
        [$d1, $d2] = $this->roll();
        $sum = $d1 + $d2;

        [$isWin, $multiplier] = $this->resolve($betType, $betValue, $sum);

        $payout = $isWin ? $amount * $multiplier : 0.0;

        return [
            'die1'       => $d1,
            'die2'       => $d2,
            'sum'        => $sum,
            'is_win'     => $isWin,
            'payout'     => $payout,
            'multiplier' => $multiplier,
            'bet_type'   => $betType,
            'bet_value'  => $betValue,
        ];
    }

    public static function payoutTable(): array
    {
        return self::TOTAL_PAYOUTS;
    }

    private function resolve(string $betType, string $betValue, int $sum): array
    {
        return match ($betType) {
            'total'    => $this->resolveTotal((int)$betValue, $sum),
            'high_low' => $this->resolveHighLow($betValue, $sum),
            'parity'   => $this->resolveParity($betValue, $sum),
            default    => [false, 0],
        };
    }

    private function resolveTotal(int $target, int $sum): array
    {
        $mult = self::TOTAL_PAYOUTS[$target] ?? 0;
        return [$sum === $target, $mult];
    }

    private function resolveHighLow(string $value, int $sum): array
    {
        // 7 always loses on high/low
        if ($sum === 7) {
            return [false, 0];
        }
        $isHigh = $sum >= 8 && $sum <= 12;
        $isLow  = $sum >= 2 && $sum <= 6;
        $win    = ($value === 'high' && $isHigh) || ($value === 'low' && $isLow);
        return [$win, 2];
    }

    private function resolveParity(string $value, int $sum): array
    {
        $win = ($value === 'even' && $sum % 2 === 0) || ($value === 'odd' && $sum % 2 !== 0);
        return [$win, 2];
    }
}
