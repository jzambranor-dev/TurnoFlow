-- ============================================================
-- TurnoFlow — Setup completo de base de datos
-- PostgreSQL 15+
--
-- Ejecutar UNA SOLA VEZ en un servidor nuevo:
--   psql -U turnoflow -d turnoflow -f setup_completo.sql
--
-- Despues de ejecutar, crear usuario admin:
--   php sql/crear_admin.php
-- ============================================================

\echo '>>> 1/13 Schema principal...'
\i schema.sql

\echo '>>> 2/13 Permisos y roles...'
\i permissions.sql

\echo '>>> 3/13 Migracion: breaks...'
\i migration_break.sql

\echo '>>> 4/13 Migracion: actividades de campana...'
\i migration_campaign_activities.sql

\echo '>>> 5/13 Migracion: modalidad de trabajo...'
\i migration_modalidad_advisor.sql

\echo '>>> 6/13 Migracion: asesores compartidos...'
\i migration_shared_advisors.sql

\echo '>>> 7/13 Migracion: check-ins de asesores...'
\i migrations/003_add_advisor_checkins.sql

\echo '>>> 8/13 Migracion: feriados y parametros...'
\i migrations/004_holidays_and_system_params.sql

\echo '>>> 9/13 Migracion: API tokens...'
\i migrations/005_api_tokens.sql

\echo '>>> 10/13 Migracion: notificaciones...'
\i migrations/006_notifications.sql

\echo '>>> 11/13 Migracion: audit log...'
\i migrations/007_audit_log.sql

\echo '>>> 12/13 Migracion: indices de rendimiento...'
\i migrations/008_performance_indexes.sql

\echo '>>> 13/14 Migracion: permiso settings.edit...'
\i migrations/009_settings_edit_permission.sql

\echo '>>> 14/16 Migracion: modulo cumplimiento breaks...'
\i migrations/010_break_compliance.sql

\echo '>>> 15/16 Migracion: reglas de break y datos diarios...'
\i migrations/011_break_rules_and_daily_data.sql

\echo '>>> 16/16 Migracion: break imports por fecha...'
\i migrations/012_break_imports_date_based.sql

\echo ''
\echo '============================================'
\echo '  Setup completo! Base de datos lista.'
\echo ''
\echo '  Siguiente paso: crear usuario admin'
\echo '    php sql/crear_admin.php'
\echo '============================================'
