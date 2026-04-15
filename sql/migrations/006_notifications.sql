-- 006_notifications.sql
-- Sistema de notificaciones in-app para TurnoFlow

CREATE TABLE IF NOT EXISTS notifications (
    id          BIGSERIAL PRIMARY KEY,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tipo        VARCHAR(50) NOT NULL,
    titulo      VARCHAR(200) NOT NULL,
    mensaje     TEXT,
    url         VARCHAR(500),
    leida       BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id, leida, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_notif_created ON notifications(created_at DESC);
