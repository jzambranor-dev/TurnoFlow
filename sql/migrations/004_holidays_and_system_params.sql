-- Migration 004: Dias festivos y parametros del sistema
-- Fecha: 2026-03-16

CREATE TABLE IF NOT EXISTS holidays (
    id          SERIAL PRIMARY KEY,
    fecha       DATE NOT NULL UNIQUE,
    nombre      VARCHAR(100) NOT NULL,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS system_params (
    id          SERIAL PRIMARY KEY,
    clave       VARCHAR(50) NOT NULL UNIQUE,
    valor       VARCHAR(255) NOT NULL,
    descripcion VARCHAR(255),
    updated_at  TIMESTAMPTZ DEFAULT NOW()
);

-- Parametros por defecto
INSERT INTO system_params (clave, valor, descripcion) VALUES
    ('hora_inicio_nocturno', '22', 'Hora de inicio del turno nocturno (0-23)'),
    ('hora_fin_nocturno', '6', 'Hora de fin del turno nocturno (0-23)'),
    ('break_minimo_minutos', '30', 'Duracion minima del break en minutos'),
    ('max_horas_dia_default', '10', 'Maximo de horas por dia por defecto'),
    ('dias_descanso_semana', '1', 'Dias de descanso minimos por semana')
ON CONFLICT (clave) DO NOTHING;
