-- 007_audit_log.sql
-- Fase 2.1: Tabla de auditoria de cambios

CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGSERIAL PRIMARY KEY,
    user_id     INTEGER REFERENCES users(id),
    accion      VARCHAR(100) NOT NULL,
    entidad     VARCHAR(50),
    entidad_id  INTEGER,
    datos_antes JSONB,
    datos_despues JSONB,
    ip          VARCHAR(45),
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_entidad ON audit_log(entidad, entidad_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_log(user_id, created_at DESC);
