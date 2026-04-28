-- ============================================================
-- Migration 011: Break Rules + Daily Break Data
--
-- 1. break_rules: table of hours→break_minutes (from lib sheet)
-- 2. break_daily: actual break usage per advisor per day
--    (from BREAK sheet: usuario, horas, break asignado/usado/disponible)
-- ============================================================

-- Break rules: how many minutes of break per hours worked
CREATE TABLE IF NOT EXISTS break_rules (
    id              SERIAL PRIMARY KEY,
    horas_trabajo   SMALLINT NOT NULL UNIQUE,
    break_minutes   SMALLINT NOT NULL,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Seed from lib sheet data
INSERT INTO break_rules (horas_trabajo, break_minutes) VALUES
    (4, 15), (5, 20), (6, 25), (7, 30),
    (8, 35), (9, 40), (10, 45), (11, 50),
    (12, 55), (13, 60), (14, 65), (15, 70), (16, 75)
ON CONFLICT (horas_trabajo) DO NOTHING;

-- Daily break data per advisor (from BREAK sheet in BREAK_POST.xlsx)
CREATE TABLE IF NOT EXISTS break_daily (
    id                  BIGSERIAL PRIMARY KEY,
    import_id           INTEGER REFERENCES break_imports(id) ON DELETE CASCADE,
    advisor_id          INTEGER NOT NULL REFERENCES advisors(id),
    campaign_id         INTEGER NOT NULL REFERENCES campaigns(id),
    fecha               DATE NOT NULL,
    usuario_excel       VARCHAR(100),
    horas_trabajadas    SMALLINT DEFAULT 0,
    horario_texto       VARCHAR(100),
    break_asignado_min  NUMERIC(6,2) DEFAULT 0,
    break_usado_min     NUMERIC(6,2) DEFAULT 0,
    break_disponible_min NUMERIC(6,2) DEFAULT 0,
    exceso_min          NUMERIC(6,2) DEFAULT 0,
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (advisor_id, fecha)
);

CREATE INDEX IF NOT EXISTS idx_break_daily_campaign_fecha
    ON break_daily(campaign_id, fecha);
CREATE INDEX IF NOT EXISTS idx_break_daily_advisor
    ON break_daily(advisor_id, fecha);
