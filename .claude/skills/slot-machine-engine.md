# Skill: Jednoręki Bandyta (Slot Machine Engine)
Implementuje klasę `SlotMachine` z logiką bębnów, zakładów i wypłat.

**Interface**:
```php
class SlotMachine {
    public function spin(int $betAmount, int $userId): array;
    private function generateReels(): array;
    private function calculatePayout(array $reels, int $betAmount): int;
    private function validateBet(int $betAmount, int $userId): void;
}
```

**Symbole i bębny**:
- Min. 3 bębny, każdy losuje symbol z zestawu: `['cherry', 'lemon', 'orange', 'plum', 'bell', 'bar', '7']`.
- Wynik `spin()` zwraca: `['reels' => [...], 'payout' => int, 'win' => bool, 'new_balance' => int]`.

**Tabela wypłat** (mnożnik × stawka):
| Kombinacja                         | Mnożnik |
|------------------------------------|---------|
| Trzy `7`                           | 50      |
| Trzy `bar`                         | 20      |
| Trzy `bell`                        | 10      |
| Trzy jednakowe (pozostałe)         | 5       |
| Dwie jednakowe na pozycjach 1 i 2  | 1       |
| Brak dopasowania                   | 0       |

**Walidacja**:
- `betAmount > 0` i `betAmount <= user.balance`; rzuć `InvalidArgumentException` przy naruszeniu.
- Po grze: UPDATE `users.balance` (+/- payout/bet) i INSERT do `game_sessions`.

**Integracja**:
- Kontroler `SlotController::spin()` odbiera POST `{bet_amount}`, wywołuje `SlotMachine::spin()`, robi PRG.
- Widok wyświetla bębny (emoji lub grafiki CSS) i komunikat o wygranej/przegranej przez flash message.
