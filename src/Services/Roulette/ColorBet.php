<?php

namespace App\Services\Roulette;

class ColorBet implements BetInterface
{
    private const RED_NUMBERS = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];

    public function __construct(private string $color, private float $amount) {}

    public function isWin(int $winningNumber): bool
    {
        if ($winningNumber === 0) {
            return false;
        }
        $isRed = in_array($winningNumber, self::RED_NUMBERS, true);
        return ($this->color === 'red' && $isRed) || ($this->color === 'black' && !$isRed);
    }

    public function calculatePayout(int $winningNumber): float
    {
        return $this->isWin($winningNumber) ? $this->amount * 2 : 0.0;
    }

    public function getType(): string { return 'color'; }
    public function getValue(): string { return $this->color; }
}
