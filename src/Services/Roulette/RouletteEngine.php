<?php

namespace App\Services\Roulette;

class RouletteEngine
{
    public function spin(): int
    {
        return random_int(0, 36);
    }

    public function buildBet(string $type, string $value, float $amount): BetInterface
    {
        return match ($type) {
            'number' => new NumberBet((int)$value, $amount),
            'color'  => new ColorBet($value, $amount),
            'parity' => new ParityBet($value, $amount),
            default  => throw new \InvalidArgumentException("Unknown bet type: {$type}"),
        };
    }

    /** Returns ['winning_number', 'is_win', 'payout', 'bet'] */
    public function play(string $betType, string $betValue, float $amount): array
    {
        $bet     = $this->buildBet($betType, $betValue, $amount);
        $winning = $this->spin();
        $payout  = $bet->calculatePayout($winning);

        return [
            'winning_number' => $winning,
            'is_win'         => $payout > 0,
            'payout'         => $payout,
            'bet'            => $bet,
        ];
    }
}
