CREATE TABLE IF NOT EXISTS users (
    id              SERIAL PRIMARY KEY,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            VARCHAR(20)  NOT NULL DEFAULT 'user',
    balance         NUMERIC(12,2) NOT NULL DEFAULT 1000.00,
    avatar_path     VARCHAR(500),
    lang_preference VARCHAR(5)   NOT NULL DEFAULT 'pl',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS games (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    type       VARCHAR(50)  NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- game_sessions acts as the many-to-many junction between users and games
CREATE TABLE IF NOT EXISTS game_sessions (
    id            SERIAL PRIMARY KEY,
    user_id       INT          NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
    game_id       INT          NOT NULL REFERENCES games(id)  ON DELETE RESTRICT,
    bet_type      VARCHAR(50)  NOT NULL,
    bet_value     VARCHAR(100) NOT NULL,
    bet_amount    NUMERIC(12,2) NOT NULL,
    outcome       VARCHAR(50)  NOT NULL,
    payout        NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    note          TEXT,
    document_path VARCHAR(500),
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_game_sessions_user_id  ON game_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_game_sessions_game_id  ON game_sessions(game_id);
CREATE INDEX IF NOT EXISTS idx_game_sessions_created  ON game_sessions(created_at);
