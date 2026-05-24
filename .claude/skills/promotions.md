# Skill: Promocje i Program Lojalnościowy
Implementuje bonus powitalny, bonus od wpłaty i nagrody dla top spenderów.

**Klasa `PromotionService`**:
```php
class PromotionService {
    public function applyWelcomeBonus(int $userId): void;
    public function applyDepositBonus(int $userId, float $depositAmount): float;
    public function awardTopSpender(int $userId, float $rewardAmount, string $reason): void;
}
```

**1. Bonus powitalny**:
- Jednorazowy, naliczany automatycznie zaraz po rejestracji.
- Kwota konfigurowana w tabeli `promotions` (type=`welcome`, amount=np. 50.00 PLN).
- `applyWelcomeBonus()`: INSERT do `user_promotions` + UPDATE `users.balance`.
- Idempotentny: sprawdź najpierw, czy gracz ma już rekord `type=welcome` → jeśli tak, pomiń.

**2. Bonus od wpłaty**:
- Procentowy (np. 10%), naliczany przy każdym doładowaniu konta.
- Konfigurowany w `promotions` (type=`deposit`, rate=0.10).
- `applyDepositBonus($userId, $depositAmount)` zwraca kwotę bonusu → dodaj do `users.balance` razem z wpłatą.
- INSERT do `user_promotions` z kwotą i referencją do wpłaty.

**3. Nagrody dla top spenderów**:
- Admin ręcznie przyznaje nagrodę z panelu (np. z widoku raportu Top Spenderów).
- `awardTopSpender($userId, $rewardAmount, $reason)`: INSERT do `user_promotions` (type=`reward`) + UPDATE `users.balance`.
- Wpis w `user_promotions` z polem `note` zawierającym powód nagrody.

**Schemat tabel**:
```sql
CREATE TABLE promotions (
    id          SERIAL PRIMARY KEY,
    type        VARCHAR(50) NOT NULL,   -- 'welcome' | 'deposit' | 'reward'
    amount      NUMERIC(10,2),          -- stała kwota (welcome/reward)
    rate        NUMERIC(5,4),           -- stawka procentowa (deposit)
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_promotions (
    id             SERIAL PRIMARY KEY,
    user_id        INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    promotion_id   INT NOT NULL REFERENCES promotions(id),
    amount_awarded NUMERIC(10,2) NOT NULL,
    note           TEXT,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

**Integracja**:
- `UserController::deposit()` (POST): UPDATE balance + wywołaj `applyDepositBonus()` + PRG + flash z kwotą bonusu.
- `AuthController::register()`: po zapisaniu usera wywołaj `applyWelcomeBonus()`.
- `AdminController::awardReward()` (POST): wywołaj `awardTopSpender()` + PRG + flash.
- Widok profilu użytkownika: sekcja "Moje bonusy" z listą `user_promotions`.
