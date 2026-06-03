<?php

namespace App\Services\Roulette;

class NumberBet implements BetInterface
{
    public function __construct(private int $betNumber, private float $amount) {}

    public function isWin(int $winningNumber): bool
    {
        return $this->betNumber === $winningNumber;
    }

    public function calculatePayout(int $winningNumber): float
    {
        return $this->isWin($winningNumber) ? $this->amount * 36 : 0.0;
    }

    public function getType(): string { return 'number'; }
    public function getValue(): string { return (string)$this->betNumber; }
}
