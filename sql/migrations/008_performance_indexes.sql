-- =============================================
-- Migración 008: Índices de rendimiento
-- Fecha: 2026-04-15
-- Descripción: Índices compuestos para las queries más frecuentes
-- =============================================

-- Notificaciones por usuario no leídas (query más frecuente del badge)
CREATE INDEX IF NOT EXISTS idx_notif_user_unread
    ON notifications(user_id, leida, created_at DESC)
    WHERE leida = FALSE;

-- Audit log por entidad+id (para historial de un horario específico)
CREATE INDEX IF NOT EXISTS idx_audit_entidad_id
    ON audit_log(entidad, entidad_id, created_at DESC);

-- Attendance por fecha (para tracking diario)
CREATE INDEX IF NOT EXISTS idx_attendance_fecha
    ON attendance(fecha, advisor_id);

-- Shift assignments por schedule+fecha (para vistas diarias)
CREATE INDEX IF NOT EXISTS idx_shift_schedule_fecha
    ON shift_assignments(schedule_id, fecha);

-- Advisor checkins por fecha
CREATE INDEX IF NOT EXISTS idx_checkins_fecha
    ON advisor_checkins(fecha, advisor_id);

-- Schedules por campaign+status (para listas filtradas)
CREATE INDEX IF NOT EXISTS idx_schedules_campaign_status
    ON schedules(campaign_id, status, periodo_anio, periodo_mes);
