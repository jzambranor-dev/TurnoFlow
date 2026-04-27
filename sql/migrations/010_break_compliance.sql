-- ============================================================
-- Migration 010: Break Compliance Module
-- Tracks actual break usage from Excel imports (BREAK_POST.xlsx)
-- and compares against planned breaks in shift_assignments
-- ============================================================

-- Import metadata (mirrors staffing_imports pattern)
CREATE TABLE IF NOT EXISTS break_imports (
    id              SERIAL PRIMARY KEY,
    campaign_id     INTEGER NOT NULL REFERENCES campaigns(id),
    periodo_anio    SMALLINT NOT NULL,
    periodo_mes     SMALLINT NOT NULL CHECK (periodo_mes BETWEEN 1 AND 12),
    archivo_nombre  VARCHAR(255),
    importado_por   INTEGER NOT NULL REFERENCES users(id),
    total_registros INTEGER DEFAULT 0,
    asesores_matched INTEGER DEFAULT 0,
    asesores_unmatched INTEGER DEFAULT 0,
    estado          VARCHAR(20) DEFAULT 'pendiente'
                    CHECK (estado IN ('pendiente','procesado','error')),
    errores_json    JSONB,
    imported_at     TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (campaign_id, periodo_anio, periodo_mes)
);

-- Daily break snapshots per advisor (from Excel EXCESOS BREAK sheet)
CREATE TABLE IF NOT EXISTS break_snapshots (
    id                  BIGSERIAL PRIMARY KEY,
    import_id           INTEGER NOT NULL REFERENCES break_imports(id) ON DELETE CASCADE,
    advisor_id          INTEGER NOT NULL REFERENCES advisors(id),
    campaign_id         INTEGER NOT NULL REFERENCES campaigns(id),
    fecha               DATE NOT NULL,
    horas_trabajadas    NUMERIC(6,2) DEFAULT 0,
    bk_normal_minutes   NUMERIC(6,2) DEFAULT 0,
    break_icbm_seconds  NUMERIC(8,2) DEFAULT 0,
    exceso_minutes      NUMERIC(6,2) DEFAULT 0,
    usuario_excel       VARCHAR(100),
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (advisor_id, fecha)
);

CREATE INDEX IF NOT EXISTS idx_break_snapshots_campaign_fecha
    ON break_snapshots(campaign_id, fecha);
CREATE INDEX IF NOT EXISTS idx_break_snapshots_advisor
    ON break_snapshots(advisor_id, fecha);

-- Permissions
INSERT INTO permissions (codigo, nombre, descripcion, modulo) VALUES
    ('breaks.view', 'Ver Cumplimiento Breaks', 'Acceder al reporte de cumplimiento de breaks', 'breaks'),
    ('breaks.import', 'Importar Datos Break', 'Cargar archivo Excel de breaks reales', 'breaks'),
    ('breaks.export', 'Exportar Reporte Break', 'Descargar reporte de cruce de breaks', 'breaks')
ON CONFLICT (codigo) DO NOTHING;

-- Grant to admin, gerente, coordinador, supervisor
INSERT INTO role_permissions (rol_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.nombre IN ('admin', 'gerente', 'coordinador', 'supervisor')
  AND p.codigo IN ('breaks.view', 'breaks.import', 'breaks.export')
ON CONFLICT (rol_id, permission_id) DO NOTHING;
