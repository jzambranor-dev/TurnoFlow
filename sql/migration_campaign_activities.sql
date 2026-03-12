-- Migration: Campaign Activities
-- Permite crear actividades dentro de campañas y asignar asesores con horarios fijos

-- ============================================
-- ACTIVIDADES DE CAMPAÑA
-- ============================================
CREATE TABLE IF NOT EXISTS campaign_activities (
    id              SERIAL PRIMARY KEY,
    campaign_id     INTEGER NOT NULL REFERENCES campaigns(id) ON DELETE RESTRICT,
    nombre          VARCHAR(100) NOT NULL,
    descripcion     TEXT,
    color           VARCHAR(7) DEFAULT '#2563eb',
    estado          VARCHAR(20) NOT NULL DEFAULT 'activa'
                    CHECK (estado IN ('activa','inactiva')),
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (campaign_id, nombre)
);

-- ============================================
-- ASIGNACIÓN DE ASESORES A ACTIVIDADES
-- ============================================
CREATE TABLE IF NOT EXISTS advisor_activity_assignments (
    id              SERIAL PRIMARY KEY,
    activity_id     INTEGER NOT NULL REFERENCES campaign_activities(id) ON DELETE CASCADE,
    advisor_id      INTEGER NOT NULL REFERENCES advisors(id) ON DELETE CASCADE,
    hora_inicio     SMALLINT NOT NULL CHECK (hora_inicio BETWEEN 0 AND 23),
    hora_fin        SMALLINT NOT NULL CHECK (hora_fin BETWEEN 0 AND 23),
    dias_semana     SMALLINT[] DEFAULT '{0,1,2,3,4}',
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (advisor_id, activity_id)
);

CREATE INDEX IF NOT EXISTS idx_aaa_activity ON advisor_activity_assignments(activity_id);
CREATE INDEX IF NOT EXISTS idx_aaa_advisor ON advisor_activity_assignments(advisor_id);

-- ============================================
-- PERMISOS
-- ============================================
INSERT INTO permissions (codigo, nombre, descripcion, modulo) VALUES
('activities.view', 'Ver Actividades', 'Listar actividades de campaña', 'activities'),
('activities.create', 'Crear Actividades', 'Crear nuevas actividades', 'activities'),
('activities.edit', 'Editar Actividades', 'Modificar actividades existentes', 'activities'),
('activities.delete', 'Eliminar Actividades', 'Eliminar actividades', 'activities'),
('activities.assign', 'Asignar Asesores', 'Gestionar asignaciones de asesores a actividades', 'activities')
ON CONFLICT (codigo) DO NOTHING;

-- Otorgar permisos a admin, coordinador y supervisor
INSERT INTO role_permissions (rol_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.nombre IN ('admin', 'coordinador', 'supervisor')
AND p.modulo = 'activities'
ON CONFLICT (rol_id, permission_id) DO NOTHING;
