# Database & PDO Rules
- Używamy wyłącznie **PostgreSQL** — nie MySQL, nie SQLite.
- Jeden singleton `Database` (lub dependency injection) dla połączenia PDO w całej aplikacji.
- Wszystkie zapytania muszą używać prepared statements z named placeholders; zero interpolacji zmiennych w SQL.
- Nazwy tabel: plural lowercase (`users`, `games`, `game_sessions`, `promotions`, `user_promotions`, `player_segments`).
- Klucze obce: `user_id` → `users(id)`, `game_id` → `games(id)`, `promotion_id` → `promotions(id)`.
- Relacja many-to-many: `user_promotions (user_id, promotion_id, amount_awarded, created_at)`.
- `created_at` ustawiany automatycznie — `DEFAULT CURRENT_TIMESTAMP` w schemacie DB.
- Usuwanie rekordu: najpierw pobierz ścieżkę pliku, usuń przez `unlink()`, następnie DELETE z bazy.
- Filtrowanie/sortowanie: whitelist dopuszczalnych nazw kolumn — nigdy `$_GET['sort']` wprost do ORDER BY.
