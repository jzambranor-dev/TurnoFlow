-- TurnoFlow - Sistema de Gestión de Horarios
-- Schema PostgreSQL 15+
-- Ejecutar completo antes de iniciar la aplicación

-- ============================================
-- ROLES Y USUARIOS
-- ============================================
CREATE TABLE IF NOT EXISTS roles (
    id          SERIAL PRIMARY KEY,
    nombre      VARCHAR(30) NOT NULL UNIQUE,
    descripcion TEXT
);

CREATE TABLE IF NOT EXISTS users (
    id             SERIAL PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL,
    apellido       VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    rol_id         INTEGER NOT NULL REFERENCES roles(id),
    activo         BOOLEAN DEFAULT TRUE,
    created_at     TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================
-- CAMPAÑAS
-- ============================================
CREATE TABLE IF NOT EXISTS campaigns (
    id                      SERIAL PRIMARY KEY,
    nombre                  VARCHAR(100) NOT NULL,
    cliente                 VARCHAR(100),
    supervisor_id           INTEGER NOT NULL REFERENCES users(id),
    tiene_velada            BOOLEAN NOT NULL DEFAULT FALSE,
    hora_inicio_operacion   SMALLINT NOT NULL DEFAULT 0  CHECK (hora_inicio_operacion BETWEEN 0 AND 23),
    hora_fin_operacion      SMALLINT NOT NULL DEFAULT 23 CHECK (hora_fin_operacion BETWEEN 0 AND 23),
    permite_horas_extra     BOOLEAN NOT NULL DEFAULT TRUE,
    max_horas_dia           SMALLINT NOT NULL DEFAULT 10 CHECK (max_horas_dia BETWEEN 8 AND 16),
    requiere_vpn_nocturno   BOOLEAN NOT NULL DEFAULT FALSE,
    hora_inicio_nocturno    SMALLINT DEFAULT 22,
    hora_fin_nocturno       SMALLINT DEFAULT 6,
    estado                  VARCHAR(20) NOT NULL DEFAULT 'activa'
                            CHECK (estado IN ('activa','inactiva','pausada')),
    created_at              TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================
-- CONFIGURACIÓN MENSUAL DE HORAS (solo coordinador)
-- ============================================
CREATE TABLE IF NOT EXISTS monthly_hours_config (
    id                  SERIAL PRIMARY KEY,
    anio                SMALLINT NOT NULL,
    mes                 SMALLINT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    horas_requeridas    SMALLINT NOT NULL,   -- 177 (31d), 170 (30d), 168 (feb)
    dias_del_mes        SMALLINT NOT NULL,
    configurado_por     INTEGER NOT NULL REFERENCES users(id),
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (anio, mes)
);

-- ============================================
-- ASESORES — solo coordinador puede crear/eliminar
-- ============================================
CREATE TABLE IF NOT EXISTS advisors (
    id                      SERIAL PRIMARY KEY,
    nombres                 VARCHAR(100) NOT NULL,
    apellidos               VARCHAR(100) NOT NULL,
    cedula                  VARCHAR(20) UNIQUE,
    campaign_id             INTEGER NOT NULL REFERENCES campaigns(id),
    tipo_contrato           VARCHAR(20) NOT NULL DEFAULT 'completo'
                            CHECK (tipo_contrato IN ('completo','parcial')),
    hora_inicio_contrato    SMALLINT DEFAULT 0,
    hora_fin_contrato       SMALLINT DEFAULT 23,
    estado                  VARCHAR(20) NOT NULL DEFAULT 'activo'
                            CHECK (estado IN ('activo','inactivo','licencia')),
    fecha_ingreso           DATE,
    created_at              TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================
-- RESTRICCIONES POR ASESOR — solo coordinador puede editar
-- ============================================
CREATE TABLE IF NOT EXISTS advisor_constraints (
    id                          SERIAL PRIMARY KEY,
    advisor_id                  INTEGER NOT NULL UNIQUE REFERENCES advisors(id) ON DELETE CASCADE,
    tiene_vpn                   BOOLEAN NOT NULL DEFAULT FALSE,
    permite_extras              BOOLEAN NOT NULL DEFAULT TRUE,
    max_horas_dia               SMALLINT NOT NULL DEFAULT 10 CHECK (max_horas_dia BETWEEN 8 AND 16),
    tiene_restriccion_medica    BOOLEAN NOT NULL DEFAULT FALSE,
    descripcion_restriccion     TEXT,
    restriccion_hora_inicio     SMALLINT DEFAULT NULL,
    restriccion_hora_fin        SMALLINT DEFAULT NULL,
    restriccion_fecha_hasta     DATE DEFAULT NULL,
    dias_descanso               SMALLINT[] DEFAULT '{}',
    -- 0=lun, 1=mar, 2=mie, 3=jue, 4=vie, 5=sab, 6=dom
    updated_at                  TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================
-- IMPORTACIONES DE DIMENSIONAMIENTO
-- ============================================
CREATE TABLE IF NOT EXISTS staffing_imports (
    id              SERIAL PRIMARY KEY,
    campaign_id     INTEGER NOT NULL REFERENCES campaigns(id),
    periodo_anio    SMALLINT NOT NULL,
    periodo_mes     SMALLINT NOT NULL CHECK (periodo_mes BETWEEN 1 AND 12),
    archivo_nombre  VARCHAR(255),
    importado_por   INTEGER NOT NULL REFERENCES users(id),
    total_asesor_hora INTEGER,
    estado          VARCHAR(20) DEFAULT 'pendiente'
                    CHECK (estado IN ('pendiente','procesado','error')),
    errores_json    JSONB,
    imported_at     TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (campaign_id, periodo_anio, periodo_mes)
);

-- ============================================
-- DIMENSIONAMIENTO HORA A HORA
-- ============================================
CREATE TABLE IF NOT EXISTS staffing_requirements (
    id                      BIGSERIAL PRIMARY KEY,
    import_id               INTEGER NOT NULL REFERENCES staffing_imports(id) ON DELETE CASCADE,
    campaign_id             INTEGER NOT NULL REFERENCES campaigns(id),
    fecha                   DATE NOT NULL,
    hora                    SMALLINT NOT NULL CHECK (hora BETWEEN 0 AND 23),
    asesores_requeridos     SMALLINT NOT NULL CHECK (asesores_requeridos >= 0),
    UNIQUE (campaign_id, fecha, hora)
);
CREATE INDEX IF NOT EXISTS idx_staffing_req_fecha ON staffing_requirements(campaign_id, fecha);

-- ============================================
-- HORARIOS (cabecera del horario generado)
-- ============================================
DO $$ BEGIN
    CREATE TYPE schedule_status AS ENUM ('borrador','enviado','aprobado','rechazado');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

CREATE TABLE IF NOT EXISTS schedules (
    id              SERIAL PRIMARY KEY,
    campaign_id     INTEGER NOT NULL REFERENCES campaigns(id),
    periodo_anio    SMALLINT NOT NULL,
    periodo_mes     SMALLINT NOT NULL,
    fecha_inicio    DATE NOT NULL,
    fecha_fin       DATE NOT NULL,
    tipo            VARCHAR(20) NOT NULL DEFAULT 'mensual'
                    CHECK (tipo IN ('mensual','semanal','diario')),
    status          schedule_status NOT NULL DEFAULT 'borrador',
    generado_por    INTEGER REFERENCES users(id),
    aprobado_por    INTEGER REFERENCES users(id),
    aprobado_at     TIMESTAMPTZ,
    nota_rechazo    TEXT,
    nota_supervisor TEXT,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (campaign_id, fecha_inicio, tipo)
);

-- ============================================
-- ASIGNACIONES HORA A HORA (corazón del sistema)
-- ============================================
DO $$ BEGIN
    CREATE TYPE shift_type AS ENUM ('normal','extra','nocturno','replanif');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

CREATE TABLE IF NOT EXISTS shift_assignments (
    id              BIGSERIAL PRIMARY KEY,
    schedule_id     INTEGER NOT NULL REFERENCES schedules(id) ON DELETE CASCADE,
    advisor_id      INTEGER NOT NULL REFERENCES advisors(id),
    campaign_id     INTEGER NOT NULL REFERENCES campaigns(id),
    fecha           DATE NOT NULL,
    hora            SMALLINT NOT NULL CHECK (hora BETWEEN 0 AND 23),
    tipo            shift_type NOT NULL DEFAULT 'normal',
    es_extra        BOOLEAN NOT NULL DEFAULT FALSE,
    UNIQUE (advisor_id, fecha, hora)
);
CREATE INDEX IF NOT EXISTS idx_shift_fecha ON shift_assignments(campaign_id, fecha);
CREATE INDEX IF NOT EXISTS idx_shift_advisor ON shift_assignments(advisor_id, fecha);

-- ============================================
-- ASISTENCIA
-- ============================================
DO $$ BEGIN
    CREATE TYPE attendance_status AS ENUM
        ('presente','ausente','tardanza','salida_anticipada','licencia_medica','maternidad');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

CREATE TABLE IF NOT EXISTS attendance (
    id                  BIGSERIAL PRIMARY KEY,
    advisor_id          INTEGER NOT NULL REFERENCES advisors(id),
    fecha               DATE NOT NULL,
    status              attendance_status NOT NULL DEFAULT 'presente',
    hora_real_inicio    SMALLINT,
    hora_real_fin       SMALLINT,
    horas_trabajadas    NUMERIC(4,1),
    notas               TEXT,
    registrado_por      INTEGER REFERENCES users(id),
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (advisor_id, fecha)
);

-- ============================================
-- REPLANIFICACIONES
-- ============================================
CREATE TABLE IF NOT EXISTS replanning_log (
    id                  SERIAL PRIMARY KEY,
    campaign_id         INTEGER NOT NULL REFERENCES campaigns(id),
    fecha               DATE NOT NULL,
    advisor_ausente_id  INTEGER NOT NULL REFERENCES advisors(id),
    motivo              attendance_status NOT NULL,
    nuevas_asignaciones BIGINT[],
    ejecutado_por       INTEGER REFERENCES users(id),
    created_at          TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================
-- RESUMEN MENSUAL
-- ============================================
CREATE TABLE IF NOT EXISTS monthly_summary (
    id                      SERIAL PRIMARY KEY,
    advisor_id              INTEGER NOT NULL REFERENCES advisors(id),
    campaign_id             INTEGER NOT NULL REFERENCES campaigns(id),
    anio                    SMALLINT NOT NULL,
    mes                     SMALLINT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    horas_meta              NUMERIC(5,1) NOT NULL,
    horas_programadas       NUMERIC(5,1) DEFAULT 0,
    horas_trabajadas        NUMERIC(5,1) DEFAULT 0,
    horas_base              NUMERIC(5,1) DEFAULT 0,
    horas_extra             NUMERIC(5,1) DEFAULT 0,
    horas_nocturnas         NUMERIC(5,1) DEFAULT 0,
    ausencias_dias          SMALLINT DEFAULT 0,
    tardanzas               SMALLINT DEFAULT 0,
    replanificaciones       SMALLINT DEFAULT 0,
    porcentaje_adherencia   NUMERIC(5,2),
    cerrado                 BOOLEAN DEFAULT FALSE,
    cerrado_por             INTEGER REFERENCES users(id),
    cerrado_at              TIMESTAMPTZ,
    UNIQUE (advisor_id, anio, mes)
);

-- ============================================
-- VISTAS ÚTILES
-- ============================================
CREATE OR REPLACE VIEW v_coverage_vs_required AS
SELECT
    sr.campaign_id, sr.fecha, sr.hora,
    sr.asesores_requeridos                          AS requeridos,
    COUNT(sa.id)                                    AS asignados,
    COUNT(sa.id) - sr.asesores_requeridos           AS diferencia,
    COUNT(sa.id) >= sr.asesores_requeridos          AS cubierto,
    COUNT(sa.id) FILTER (WHERE sa.es_extra)         AS horas_extra_en_franja
FROM staffing_requirements sr
LEFT JOIN shift_assignments sa
    ON sa.campaign_id = sr.campaign_id
    AND sa.fecha = sr.fecha AND sa.hora = sr.hora
GROUP BY sr.campaign_id, sr.fecha, sr.hora, sr.asesores_requeridos;

CREATE OR REPLACE VIEW v_advisor_night_eligibility AS
SELECT
    a.id AS advisor_id,
    a.nombres || ' ' || a.apellidos AS nombre_completo,
    a.campaign_id,
    c.nombre AS campana,
    c.tiene_velada,
    c.requiere_vpn_nocturno,
    ac.tiene_vpn,
    (
        c.tiene_velada
        AND (NOT c.requiere_vpn_nocturno OR ac.tiene_vpn)
        AND NOT (
            ac.tiene_restriccion_medica
            AND (ac.restriccion_fecha_hasta IS NULL OR ac.restriccion_fecha_hasta >= CURRENT_DATE)
        )
    ) AS elegible_nocturno
FROM advisors a
JOIN campaigns c ON c.id = a.campaign_id
LEFT JOIN advisor_constraints ac ON ac.advisor_id = a.id
WHERE a.estado = 'activo';

-- ============================================
-- DATOS INICIALES
-- ============================================
INSERT INTO roles (nombre, descripcion) VALUES
    ('coordinador', 'Acceso total: aprueba horarios, configura sistema, ve todas las campañas'),
    ('supervisor',  'Gestión operativa: importa dimensionamiento, genera y envía horarios')
ON CONFLICT (nombre) DO NOTHING;

-- Usuario admin por defecto (password: admin123)
-- Hash generado con password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO users (nombre, apellido, email, password_hash, rol_id) VALUES
    ('Admin', 'Sistema', 'admin@turnoflow.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1)
ON CONFLICT (email) DO NOTHING;
