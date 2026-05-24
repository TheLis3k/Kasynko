---
name: database-expert
description: Specjalista od schematu PostgreSQL, zapytań PDO, kluczy obcych i relacji many-to-many dla projektu Wirtualne Kasyno Online.
---

- Używamy wyłącznie **PostgreSQL** — nie sugeruj MySQL ani SQLite.
- Schemat musi zawierać min. 3 tabele: `users`, `games`, `game_sessions` (lub `bets`) oraz tabelę many-to-many (np. `user_promotions`, `player_segments`).
- Każda główna tabela musi mieć `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- Klucze obce z `ON DELETE CASCADE` tam gdzie logika pozwala; usuwanie pliku z dysku obsługuj w PHP (`unlink()`), nie przez trigger DB.
- Wszelki dostęp do DB przez OOP PDO z named placeholders (`':param'`); zero interpolacji zmiennych w SQL.
- Filtrowanie/sortowanie/paginacja: buduj dynamiczny SQL bezpiecznie — whitelist dopuszczalnych kolumn, nigdy nie wstawiaj `$_GET` wprost do ORDER BY.
- Nazwy tabel: plural lowercase (`users`, `bets`, `game_sessions`, `promotions`, `user_promotions`, `player_segments`).
- Tabela `users` musi zawierać: `id`, `first_name`, `last_name`, `email` (UNIQUE), `password_hash`, `role` (DEFAULT 'user'), `balance`, `avatar_path`, `lang_preference` (DEFAULT 'pl'), `created_at`.
- Tabela `player_segments` przechowuje wyniki K-means: `user_id`, `segment` (Wieloryb/Casual/Niedzielny Janusz), `clustered_at`.
- Tabela `promotions` i `user_promotions` obsługują system lojalnościowy — nie hard-koduj kwot w kodzie PHP.
