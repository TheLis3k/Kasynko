# Skill: Raporty Administratora
Generuje zagregowane raporty dla panelu admina z możliwością eksportu.

**Klasa `ReportService`**:
```php
class ReportService {
    public function userActivity(array $filters): array;   // aktywność użytkowników
    public function gameStats(array $filters): array;      // statystyki gier
    public function revenue(array $filters): array;        // przychody kasyna
    public function topSpenders(int $limit = 10): array;   // ranking graczy
}
```

**Raport: Aktywność użytkowników** (`userActivity`):
```sql
SELECT u.id, u.first_name, u.last_name, u.email,
       COUNT(gs.id) AS total_games,
       SUM(gs.bet_amount) AS total_wagered,
       SUM(gs.payout) AS total_won,
       MAX(gs.created_at) AS last_played
FROM users u
LEFT JOIN game_sessions gs ON gs.user_id = u.id
WHERE gs.created_at BETWEEN :date_from AND :date_to  -- filtr opcjonalny
GROUP BY u.id
ORDER BY total_games DESC
```

**Raport: Statystyki gier** (`gameStats`):
```sql
SELECT g.name AS game_name,
       COUNT(gs.id) AS total_plays,
       SUM(gs.bet_amount) AS total_wagered,
       SUM(gs.payout) AS total_paid_out,
       SUM(gs.bet_amount) - SUM(gs.payout) AS house_profit,
       AVG(gs.bet_amount) AS avg_bet
FROM games g
LEFT JOIN game_sessions gs ON gs.game_id = g.id
GROUP BY g.id
```

**Raport: Przychody** (`revenue`):
```sql
SELECT DATE_TRUNC('day', gs.created_at) AS day,
       SUM(gs.bet_amount) AS total_bets,
       SUM(gs.payout) AS total_payouts,
       SUM(gs.bet_amount) - SUM(gs.payout) AS net_revenue
FROM game_sessions gs
WHERE gs.created_at BETWEEN :date_from AND :date_to
GROUP BY 1
ORDER BY 1
```

**Raport: Top spenderzy** (`topSpenders`):
```sql
SELECT u.id, u.first_name, u.last_name,
       SUM(gs.bet_amount) AS total_spent,
       ps.segment
FROM users u
JOIN game_sessions gs ON gs.user_id = u.id
LEFT JOIN player_segments ps ON ps.user_id = u.id
GROUP BY u.id, ps.segment
ORDER BY total_spent DESC
LIMIT :limit
```

**Filtry dla raportów**:
- Zakres dat (`date_from`, `date_to`)
- Typ gry (`game_id`)
- Użytkownik (`user_id`)

**Integracja**:
- `AdminController::reports()` wywołuje odpowiedni raport wg parametru GET `?report=activity|stats|revenue|top`.
- Widok: tabela z nagłówkami + podsumowanie (SUM/COUNT jako osobny wiersz `<tfoot>`).
- Przyciski eksportu CSV/PDF/JSON powyżej tabeli, uwzględniają aktywne filtry.
- Admin może z poziomu raportu top spenderów kliknąć "Przyznaj nagrodę" → POST → PRG.
