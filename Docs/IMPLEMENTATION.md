# Mapa Implementacji — Wirtualne Kasyno Online

> Żywy dokument utrzymywany przez agenta `doc-maintainer`. Pokazuje **co jest zrobione, gdzie i z jakim statusem**, aby uniknąć duplikacji pracy między agentami. Aktualizuj po każdej znaczącej zmianie w kodzie.
>
> Legenda statusu: `gotowe` ✅ · `w toku` 🔨 · `planowane` ⬜

## Mapa funkcji → pliki

| Funkcjonalność | Klasy / Pliki | Status | Uwagi |
|----------------|---------------|--------|-------|
| Połączenie z DB (PDO) | `src/Helpers/Database.php` | ⬜ planowane | singleton / DI |
| Rejestracja i logowanie | `src/Controllers/AuthController.php`, `src/Helpers/AuthMiddleware.php` | ⬜ planowane | Argon2ID, regeneracja sesji |
| Ruletka | `src/Models/Roulette.php`, `src/Controllers/RouletteController.php` | ⬜ planowane | numer/kolor/parzystość |
| Jednoręki Bandyta | `src/Models/SlotMachine.php`, `src/Controllers/SlotController.php` | ⬜ planowane | 3 bębny, tabela wypłat |
| CRUD zakładów / sesji gier | `src/Models/...`, `src/Controllers/BetController.php` | ⬜ planowane | główny zasób CRUD |
| Wyszukiwanie / filtrowanie / paginacja | (kontroler listy + model) | ⬜ planowane | ≥3 kryteria, server-side |
| Eksport CSV/PDF/JSON | `src/Services/CsvExporter.php`, `PdfExporter.php`, `JsonExporter.php` | ⬜ planowane | wspólny `ExporterInterface` |
| Raporty admina | `src/Services/ReportService.php`, `src/Controllers/AdminController.php` | ⬜ planowane | aktywność/gry/przychody/top |
| K-means (segmentacja) | `src/Services/KMeansService.php` | ⬜ planowane | k=3, Wieloryb/Casual/Niedzielny Janusz |
| Promocje i lojalność | `src/Services/PromotionService.php` | ⬜ planowane | bonus powitalny/od wpłaty/nagrody |
| Upload plików (avatar/KYC) | (helper uploadu + integracja w kontrolerach) | ⬜ planowane | poza web root, unlink przy delete |
| i18n (pl/en) | `lang/pl.php`, `lang/en.php`, helper `t()` w `src/Helpers/helpers.php` | ⬜ planowane | pl domyślny |
| Strony błędów | `views/errors/404.phtml`, `500.phtml`, `403.phtml` | ⬜ planowane | bez stack trace |

## Rejestr klas i odpowiedzialności

| Klasa | Publiczne metody | Odpowiedzialność (SRP) |
|-------|------------------|------------------------|
| _(brak — uzupełniaj w miarę implementacji)_ | | |

## Schemat bazy

> Synchronizuj z `database/schema.sql` po każdej zmianie schematu.

| Tabela | Kluczowe kolumny | Relacje |
|--------|------------------|---------|
| `users` | id, email (UNIQUE), password_hash, role, balance, avatar_path, lang_preference, created_at | — |
| `games` | id, name | — |
| `game_sessions` | id, user_id, game_id, bet_amount, payout, created_at | FK → users, games (many-to-many) |
| `promotions` | id, type, amount, rate, is_active, created_at | — |
| `user_promotions` | id, user_id, promotion_id, amount_awarded, note, created_at | FK → users, promotions (many-to-many) |
| `player_segments` | id, user_id, segment, clustered_at | FK → users |

## Konwencje już ustalone

- Helper tłumaczeń: `t('namespace.klucz')` — klucze typu `auth.login`, `game.bet`, `error.404`.
- Flash messages: typy `success` / `error` / `info`; PRG po każdym POST.
- Upload: `storage/uploads/{kategoria}/{id}_{uniqid()}.ext`, poza web root.
- Whitelist kolumn dla ORDER BY / filtrów — nigdy `$_GET` wprost do SQL.

## Definition of Done — status

> Lustro checklisty z `CLAUDE.md`. Odhaczaj wyłącznie z linkiem do dowodu (plik/linia).

_(uzupełniaj w miarę postępu — patrz `CLAUDE.md` → sekcja Definition of Done)_

## TODO / W toku

- _(pusto — odnotowuj tu zadania zaczęte, by uniknąć dublowania)_

## ⚠️ Do refaktoryzacji (DRY / SOLID)

- _(pusto — wpisuj wykrytą duplikację logiki z konkretnymi ścieżkami)_
