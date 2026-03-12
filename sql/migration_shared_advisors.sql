-- Migración: Tabla shared_advisors para asesores compartidos entre campañas
-- Permite "prestar" asesores de una campaña a otra con límite de horas/día

CREATE TABLE IF NOT EXISTS shared_advisors (
    id                  SERIAL PRIMARY KEY,
    advisor_id          INTEGER NOT NULL REFERENCES advisors(id) ON DELETE CASCADE,
    source_campaign_id  INTEGER NOT NULL REFERENCES campaigns(id),
    target_campaign_id  INTEGER NOT NULL REFERENCES campaigns(id),
    max_horas_dia       SMALLINT NOT NULL DEFAULT 3 CHECK (max_horas_dia BETWEEN 1 AND 8),
    estado              VARCHAR(20) NOT NULL DEFAULT 'activo',
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (advisor_id, target_campaign_id),
    CHECK (source_campaign_id <> target_campaign_id)
);

CREATE INDEX IF NOT EXISTS idx_shared_target ON shared_advisors(target_campaign_id, estado);
CREATE INDEX IF NOT EXISTS idx_shared_source ON shared_advisors(source_campaign_id, estado);
