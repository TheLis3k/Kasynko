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

-- Promotions catalogue (welcome bonus, deposit bonus, reward)
CREATE TABLE IF NOT EXISTS promotions (
    id          SERIAL PRIMARY KEY,
    type        VARCHAR(50)    NOT NULL, -- welcome | deposit | reward
    name        VARCHAR(200)   NOT NULL,
    description TEXT,
    amount      NUMERIC(12,2)  NOT NULL DEFAULT 0.00, -- fixed amount (welcome/reward)
    rate        NUMERIC(5,4)   NOT NULL DEFAULT 0.00, -- fractional rate for deposit bonus (e.g. 0.10 = 10%)
    is_active   BOOLEAN        NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Many-to-many: users ↔ promotions
CREATE TABLE IF NOT EXISTS user_promotions (
    id              SERIAL PRIMARY KEY,
    user_id         INT            NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    promotion_id    INT            NOT NULL REFERENCES promotions(id) ON DELETE CASCADE,
    amount_awarded  NUMERIC(12,2)  NOT NULL DEFAULT 0.00,
    note            TEXT,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_user_promotions_user       ON user_promotions(user_id);
CREATE INDEX IF NOT EXISTS idx_user_promotions_promotion  ON user_promotions(promotion_id);

-- Audit log — records who changed what and when
CREATE TABLE IF NOT EXISTS audit_log (
    id          SERIAL PRIMARY KEY,
    actor_id    INT           REFERENCES users(id) ON DELETE SET NULL,
    actor_name  VARCHAR(255)  NOT NULL DEFAULT '',
    action      VARCHAR(100)  NOT NULL,
    entity_type VARCHAR(100)  NOT NULL,
    entity_id   INT,
    description TEXT,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_audit_log_actor     ON audit_log(actor_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_created   ON audit_log(created_at);

-- K-means player segments
CREATE TABLE IF NOT EXISTS player_segments (
    id           SERIAL PRIMARY KEY,
    user_id      INT         NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    segment      VARCHAR(50) NOT NULL, -- Wieloryb | Casual | Niedzielny Janusz
    avg_bet      NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    total_games  INT           NOT NULL DEFAULT 0,
    win_loss_ratio NUMERIC(8,4) NOT NULL DEFAULT 0.00,
    clustered_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id)
);
