-- Passwords: admin123 / krupier123 / gracz123 — Argon2ID hashes
INSERT INTO users (first_name, last_name, email, password_hash, role, balance)
VALUES
  ('Admin', 'Kasyno', 'admin@kasynko.pl',
   '$argon2id$v=19$m=65536,t=4,p=1$d0lYYUpQRHk5clNRcDBZRg$VT695TqeHS8SUDy+WYHs9ez304HD8K3zaPyrbO0HmNY',
   'admin', 99999.00),
  ('Krupier', 'Stołowy', 'krupier@kasynko.pl',
   '$argon2id$v=19$m=65536,t=4,p=1$V2dubTkvS2FieXdaTFBBYw$R0miEQj9cMFkG/FmpErIHBDyRvR88P2n+EaXentZFkw',
   'croupier', 0.00),
  ('Jan', 'Gracz', 'gracz@kasynko.pl',
   '$argon2id$v=19$m=65536,t=4,p=1$V2dubTkvS2FieXdaTFBBYw$R0miEQj9cMFkG/FmpErIHBDyRvR88P2n+EaXentZFkw',
   'user', 1500.00)
ON CONFLICT (email) DO NOTHING;

INSERT INTO games (name, type) VALUES
  ('Ruletka', 'roulette'),
  ('Jednoręki Bandyta', 'slots'),
  ('Kości', 'dice')
ON CONFLICT DO NOTHING;

-- Sample sessions for gracz@kasynko.pl (user id=2) so reports are non-empty
INSERT INTO game_sessions (user_id, game_id, bet_type, bet_value, bet_amount, outcome, payout)
SELECT 2, g.id, 'color', 'red', 50.00, 'win', 100.00
FROM games g WHERE g.type = 'roulette';

INSERT INTO game_sessions (user_id, game_id, bet_type, bet_value, bet_amount, outcome, payout)
SELECT 2, g.id, 'parity', 'even', 25.00, 'lose', 0.00
FROM games g WHERE g.type = 'roulette';

INSERT INTO game_sessions (user_id, game_id, bet_type, bet_value, bet_amount, outcome, payout)
SELECT 2, g.id, 'bet', '10', 10.00, 'win', 30.00
FROM games g WHERE g.type = 'slots';

-- Seed promotions catalogue
INSERT INTO promotions (type, name, description, amount, rate) VALUES
  ('welcome', 'Bonus powitalny', 'Jednorazowy bonus dla nowych graczy.', 500.00, 0.00),
  ('deposit', 'Bonus od wpłaty 10%', 'Dodatkowe 10% do każdego doładowania.', 0.00, 0.10),
  ('reward',  'Nagroda top spender', 'Ręczna nagroda dla najlepszych graczy.', 200.00, 0.00)
ON CONFLICT DO NOTHING;
