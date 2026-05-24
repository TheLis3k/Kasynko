# Skill: Roulette Engine
Implements a Roulette class with betting logic.

**Methods**:
- `spin()` → returns winning number (0-36) and color.
- `calculatePayout($betType, $betValue, $winningNumber)` → returns winnings.
Supported bet types:
  - Number (0-36) → pays 35:1
  - Color ('red' or 'black') → pays 1:1 (red numbers: 1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36)
  - Even/Odd → pays 1:1
- Must validate bet amount against user balance (balance stored in `users` table).