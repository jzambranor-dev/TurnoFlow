-- Migration 005: Tokens de API para acceso externo seguro
-- Fecha: 2026-03-16

CREATE TABLE IF NOT EXISTS api_tokens (
    id              SERIAL PRIMARY KEY,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    nombre          VARCHAR(100) NOT NULL,
    token_hash      VARCHAR(255) NOT NULL UNIQUE,
    token_prefix    VARCHAR(8) NOT NULL,
    permisos        TEXT[] NOT NULL DEFAULT '{}',
    ultimo_uso      TIMESTAMPTZ,
    expira_en       TIMESTAMPTZ,
    activo          BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_api_tokens_hash ON api_tokens (token_hash);
CREATE INDEX IF NOT EXISTS idx_api_tokens_user ON api_tokens (user_id);

-- Rate limiting log
CREATE TABLE IF NOT EXISTS api_rate_log (
    id              SERIAL PRIMARY KEY,
    token_id        INTEGER NOT NULL REFERENCES api_tokens(id) ON DELETE CASCADE,
    endpoint        VARCHAR(150) NOT NULL,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_api_rate_log_token_time ON api_rate_log (token_id, created_at);
