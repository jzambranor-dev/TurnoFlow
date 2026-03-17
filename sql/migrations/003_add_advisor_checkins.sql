-- Tabla de check-in de asesores (auto-reporte de asistencia)
CREATE TABLE IF NOT EXISTS advisor_checkins (
    id              BIGSERIAL PRIMARY KEY,
    advisor_id      INTEGER NOT NULL REFERENCES advisors(id),
    schedule_id     INTEGER NOT NULL REFERENCES schedules(id),
    fecha           DATE NOT NULL,
    checkin_at      TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (advisor_id, schedule_id, fecha)
);
