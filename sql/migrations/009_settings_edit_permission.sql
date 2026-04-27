-- Migration 009: Add settings.edit permission (separate read from write)
-- All write operations in SettingController now require settings.edit instead of settings.view

INSERT INTO permissions (codigo, nombre, descripcion, modulo)
VALUES ('settings.edit', 'Editar Configuracion', 'Editar configuracion del sistema (horas, feriados, parametros, tokens)', 'settings')
ON CONFLICT (codigo) DO NOTHING;

-- Grant settings.edit to admin and coordinador roles (they already have settings.view)
INSERT INTO role_permissions (rol_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.nombre IN ('admin', 'coordinador')
  AND p.codigo = 'settings.edit'
ON CONFLICT DO NOTHING;
