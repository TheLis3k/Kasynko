<?php

namespace App\Services\Roulette;

class ParityBet implements BetInterface
{
    public function __construct(private string $parity, private float $amount) {}

    public function isWin(int $winningNumber): bool
    {
        if ($winningNumber === 0) {
            return false;
        }
        return ($this->parity === 'even') ? ($winningNumber % 2 === 0) : ($winningNumber % 2 !== 0);
    }

    public function calculatePayout(int $winningNumber): float
    {
        return $this->isWin($winningNumber) ? $this->amount * 2 : 0.0;
    }

    public function getType(): string { return 'parity'; }
    public function getValue(): string { return $this->parity; }
}
