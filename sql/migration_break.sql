-- Migración: Soporte de break por campaña
-- Ejecutar como superusuario (postgres):
--   psql -U postgres -d turnoflow -f sql/migration_break.sql

-- 1. Agregar columnas de break a campaigns
ALTER TABLE campaigns ADD COLUMN IF NOT EXISTS tiene_break BOOLEAN DEFAULT false;
ALTER TABLE campaigns ADD COLUMN IF NOT EXISTS duracion_break_min SMALLINT DEFAULT 30;

-- 2. Agregar 'break' al enum shift_type
ALTER TYPE shift_type ADD VALUE IF NOT EXISTS 'break';

-- 3. Configurar Kiosko con break de 30 min
UPDATE campaigns SET tiene_break = true, duracion_break_min = 30 WHERE nombre = 'Kiosko';

-- 4. Dar permisos al usuario turnoflow sobre las nuevas columnas
GRANT ALL ON campaigns TO turnoflow;
