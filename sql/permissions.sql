-- =============================================
-- TURNOFLOW - Sistema de Permisos
-- Ejecutar este script en PostgreSQL
-- =============================================

-- Tabla de permisos
CREATE TABLE IF NOT EXISTS permissions (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    modulo VARCHAR(50) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Relacion roles-permisos
CREATE TABLE IF NOT EXISTS role_permissions (
    id SERIAL PRIMARY KEY,
    rol_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id INTEGER NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (rol_id, permission_id)
);

-- Indices
CREATE INDEX IF NOT EXISTS idx_role_permissions_rol ON role_permissions(rol_id);
CREATE INDEX IF NOT EXISTS idx_role_permissions_perm ON role_permissions(permission_id);
CREATE INDEX IF NOT EXISTS idx_permissions_modulo ON permissions(modulo);

-- =============================================
-- PERMISOS DEL SISTEMA
-- =============================================

-- Modulo: Dashboard
INSERT INTO permissions (codigo, nombre, descripcion, modulo) VALUES
('dashboard.view', 'Ver Dashboard', 'Acceso al panel principal', 'dashboard')
ON CONFLICT (codigo) DO NOTHING;

-- Modulo: Usuarios
INSERT INTO permissions (codigo, nombre, descripcion, modulo) VALUES
('users.view', 'Ver Usuarios', 'Listar usuarios del sistema', 'users'),
('users.create', 'Crear Usuarios', 'Crear nuevos usuarios', 'users'),
('users.edit', 'Editar Usuarios', 'Modificar datos de usuarios', 'users'),
('users.delete', 'Eliminar Usuarios', 'Eliminar usuarios del sistema', 'users'),
('users.reset_password', 'Resetear Contrasenas', 'Cambiar contrasenas de usuarios', 'users')
ON CONFLICT (codigo) DO NOTHING;

-- Modulo: Roles
INSERT INTO permissions (codigo, nombre, descripcion, modulo) VALUES
('roles.view', 'Ver Roles', 'Listar roles del sistema', 'roles'),
('roles.create', 'Crear Roles', 'Crear nuevos roles', 'roles'),
('roles.edit', 'Editar Roles', 'Modificar roles y permisos', 'roles'),
('roles.delete', 'Eliminar Roles', 'Eliminar roles del sistema', 'roles')
ON CONFLICT (codigo) DO NOTHING;

-- Modulo: Campanas
INSERT INTO permissions (codigo, nombre, descripcion, modulo) VALUES
('campaigns.view', 'Ver Campanas', 'Listar campanas', 'campaigns'),
('campaigns.create', 'Crear Campanas', 'Crear nuevas campanas', 'campaigns'),
('campaigns.edit', 'Editar Campanas', 'Modificar campanas existentes', 'campaigns'),
('campaigns.delete', 'Eliminar Campanas', 'Eliminar campanas', 'campaigns')
ON CONFLICT (codigo) DO NOTHING;

-- Modulo: Asesores
INSERT INTO permissions (codigo, nombre, descripcion, modulo) VALUES
('advisors.view', 'Ver Asesores', 'Listar asesores', 'advisors'),
('advisors.create', 'Crear Asesores', 'Crear nuevos asesores', 'advisors'),
('advisors.edit', 'Editar Asesores', 'Modificar datos de asesores', 'advisors'),
('advisors.delete', 'Eliminar Asesores', 'Eliminar asesores', 'advisors'),
('advisors.constraints', 'Gestionar Restricciones', 'Configurar restricciones de asesores', 'advisors')
ON CONFLICT (codigo) DO NOTHING;

-- Modulo: Horarios
INSERT INTO permissions (codigo, nombre, descripcion, modulo) VALUES
('schedules.view', 'Ver Horarios', 'Listar horarios', 'schedules'),
('schedules.create', 'Crear Horarios', 'Generar nuevos horarios', 'schedules'),
('schedules.edit', 'Editar Horarios', 'Modificar horarios', 'schedules'),
('schedules.import', 'Importar Dimensionamiento', 'Cargar archivos Excel', 'schedules'),
('schedules.generate', 'Generar Horarios', 'Ejecutar generacion automatica', 'schedules'),
('schedules.submit', 'Enviar Horarios', 'Enviar horarios para aprobacion', 'schedules'),
('schedules.approve', 'Aprobar Horarios', 'Aprobar o rechazar horarios', 'schedules'),
('schedules.view_own', 'Ver Mi Horario', 'Ver horario propio', 'schedules')
ON CONFLICT (codigo) DO NOTHING;

-- Modulo: Reportes
INSERT INTO permissions (codigo, nombre, descripcion, modulo) VALUES
('reports.view', 'Ver Reportes', 'Acceso a reportes', 'reports'),
('reports.export', 'Exportar Reportes', 'Descargar reportes en Excel/PDF', 'reports')
ON CONFLICT (codigo) DO NOTHING;

-- Modulo: Configuracion
INSERT INTO permissions (codigo, nombre, descripcion, modulo) VALUES
('settings.view', 'Ver Configuracion', 'Acceso a configuracion', 'settings'),
('settings.edit', 'Editar Configuracion', 'Modificar parametros del sistema', 'settings')
ON CONFLICT (codigo) DO NOTHING;

-- =============================================
-- ROLES BASE
-- =============================================

-- Actualizar descripcion de roles existentes
UPDATE roles SET descripcion = 'Acceso total al sistema' WHERE nombre = 'admin';
UPDATE roles SET descripcion = 'Aprueba horarios, gestiona campanas y asesores' WHERE nombre = 'coordinador';
UPDATE roles SET descripcion = 'Gestiona horarios de sus campanas' WHERE nombre = 'supervisor';
UPDATE roles SET descripcion = 'Consulta su horario personal' WHERE nombre = 'asesor';

-- Insertar rol admin si no existe
INSERT INTO roles (nombre, descripcion) VALUES
('admin', 'Acceso total al sistema')
ON CONFLICT (nombre) DO NOTHING;

-- =============================================
-- ASIGNAR PERMISOS A ROLES
-- =============================================

-- Admin: TODOS los permisos
INSERT INTO role_permissions (rol_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.nombre = 'admin'
ON CONFLICT (rol_id, permission_id) DO NOTHING;

-- Coordinador: Campanas, Asesores, Horarios (aprobar), Reportes
INSERT INTO role_permissions (rol_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.nombre = 'coordinador'
AND p.codigo IN (
    'dashboard.view',
    'campaigns.view', 'campaigns.create', 'campaigns.edit',
    'advisors.view', 'advisors.create', 'advisors.edit', 'advisors.constraints',
    'schedules.view', 'schedules.approve',
    'reports.view', 'reports.export'
)
ON CONFLICT (rol_id, permission_id) DO NOTHING;

-- Supervisor: Horarios (crear, importar, generar, enviar), ver asesores
INSERT INTO role_permissions (rol_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.nombre = 'supervisor'
AND p.codigo IN (
    'dashboard.view',
    'advisors.view',
    'schedules.view', 'schedules.create', 'schedules.edit', 'schedules.import',
    'schedules.generate', 'schedules.submit',
    'reports.view'
)
ON CONFLICT (rol_id, permission_id) DO NOTHING;

-- Asesor: Solo ver su horario
INSERT INTO role_permissions (rol_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.nombre = 'asesor'
AND p.codigo IN (
    'dashboard.view',
    'schedules.view_own'
)
ON CONFLICT (rol_id, permission_id) DO NOTHING;
