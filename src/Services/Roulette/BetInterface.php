<?php

namespace App\Services\Roulette;

interface BetInterface
{
    public function calculatePayout(int $winningNumber): float;
    public function isWin(int $winningNumber): bool;
    public function getType(): string;
    public function getValue(): string;
}
