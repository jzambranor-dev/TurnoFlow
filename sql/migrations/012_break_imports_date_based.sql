-- ============================================================
-- Migration 012: Break Imports — Date-based instead of Month-based
--
-- The BREAK_POST.xlsx "BREAK" sheet contains daily data, not monthly.
-- We need to track imports per date, not per month.
-- ============================================================

-- Add fecha column to break_imports
ALTER TABLE break_imports ADD COLUMN IF NOT EXISTS fecha DATE;

-- Drop old unique constraint (campaign_id, periodo_anio, periodo_mes)
ALTER TABLE break_imports DROP CONSTRAINT IF EXISTS break_imports_campaign_id_periodo_anio_periodo_mes_key;

-- Add new unique constraint (campaign_id, fecha)
-- Allow NULLs in fecha for legacy rows
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'break_imports_campaign_id_fecha_key'
    ) THEN
        ALTER TABLE break_imports ADD CONSTRAINT break_imports_campaign_id_fecha_key
            UNIQUE (campaign_id, fecha);
    END IF;
END $$;

-- Backfill fecha from periodo_anio + periodo_mes for existing rows
UPDATE break_imports
SET fecha = make_date(periodo_anio, periodo_mes, 1)
WHERE fecha IS NULL AND periodo_anio IS NOT NULL AND periodo_mes IS NOT NULL;
