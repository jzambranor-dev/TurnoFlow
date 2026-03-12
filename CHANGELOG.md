# Changelog - TurnoFlow (SGH)

Todos los cambios notables del proyecto se documentan en este archivo.

---

## [1.3.0] - 2026-03-12

### Asesores Compartidos Multi-Campaña (Back)

#### Nuevo
- **Tabla `shared_advisors`**: Tabla puente para prestar asesores entre campañas con límite de horas/día (`sql/migration_shared_advisors.sql`)
- **SharedAdvisorController**: CRUD completo para gestionar asesores compartidos (prestar, activar/desactivar)
- **Vistas shared-advisors**: Listado de asesores prestados (entrantes/salientes) y formulario para compartir
- **ActivityController**: Soporte para asignar asesores compartidos a actividades de la campaña destino, marcados con "(P)"
- **Rutas**: 4 nuevas rutas para gestión de compartidos (`/campaigns/{id}/shared-advisors/*`)

#### Motor de Horarios (ScheduleBuilder)
- **FASE 7 - Asesores compartidos**: Los asesores prestados solo cubren déficit residual via actividades fijas, nunca se mezclan con el pool general
- **FASE 7b - Delegación de jornadas triviales**: Si un asesor propio tiene 1-2 horas en un día y un compartido puede cubrirlas, se delega al compartido y el propio descansa
- **Regeneración cruzada**: Al generar un horario que usa compartidos, se regeneran automáticamente los horarios de las campañas fuente (solo borradores)
- **Protección contra doble-booking**: `registrarAsignacion()` verifica compromisos externos en `advisorSchedule` antes de asignar
- **Limpieza pre-generación**: `cleanupAssignments()` limpia asignaciones de compartidos en campañas fuente para evitar conflictos de UNIQUE constraint
- **Pasada 4 de reparación**: Relaja restricción de multi-gap cuando es la única forma de cubrir el dimensionamiento al 100%

#### Visualización
- Asesores prestados marcados con "(P)" en púrpura en todas las vistas del horario
- Horas comprometidas en otra campaña visibles en el horario de la campaña fuente (cross-campaign hours)
- Panel de asesores compartidos en la vista de edición de campaña

---

## [1.2.0] - 2026-03-12

### Motor de Asignación ScheduleBuilder v3

#### Nuevo
- **ScheduleBuilder**: Nuevo motor de generación de horarios con algoritmo multi-fase
  - FASE 1: Distribución equitativa de días libres con targets individuales
  - FASE 2: Velada rotativa semanal
  - FASE 3: Actividades fijas (asignaciones por actividad de campaña)
  - FASE 4: Asignación principal hora por hora con equidad (fairness ratio)
  - FASE 5: Consolidación iterativa de jornadas cortas + reparación
  - FASE 6: Limpieza de multi-gaps residuales
  - FASE 8: Asignación automática de breaks
  - FASE 9: Inserción con ON CONFLICT DO NOTHING
- **Capacidad individual**: Cada asesor tiene capacidad diaria y mensual calculada según su contrato
- **Fairness ratio**: Sistema de equidad que distribuye horas proporcionalmente a la capacidad de cada asesor
- **Soporte de breaks**: Asignación automática de descansos en el medio del bloque de trabajo más largo

#### Cambios
- Reemplaza el motor de asignación anterior (`buildScheduleAssignments`) que estaba embebido en ScheduleController

---

## [1.1.0] - 2026-03-10

### Actividades de Campaña

#### Nuevo
- **Tabla `campaign_activities`**: Definición de actividades por campaña (ej: "Back", "Capacitación")
- **Tabla `advisor_activity_assignments`**: Asignación de asesores a actividades con horario y días
- **ActivityController**: CRUD de actividades + gestión de asignaciones de asesores
- **Vistas de actividades**: Listado, creación, edición y asignaciones
- **Migración**: `sql/migration_campaign_activities.sql`

---

## [1.0.0] - 2026-03-08

### Sistema Base

#### Nuevo
- **Autenticación**: Login/logout con roles (admin, coordinador, supervisor, asesor)
- **Campañas**: CRUD de campañas del call center
- **Asesores**: CRUD de asesores con configuración bulk de restricciones (VPN, velada, modalidad, horario partido)
- **Horarios**: Importación de dimensionamiento desde Excel, generación automática, flujo de aprobación (borrador → enviado → aprobado/rechazado)
- **Vista de horario**: Grilla diaria con dimensionamiento vs cobertura, resumen mensual por asesor, detalle por día
- **Roles y permisos**: Sistema granular de permisos por módulo
- **Dashboard**: Panel con métricas de campañas, asesores y horarios
- **Reportes y Configuración**: Módulos base (stub)

#### Infraestructura
- PHP 8.2, PostgreSQL, arquitectura MVC propia
- Frontend con estilos corporativos personalizados
- Routing centralizado en `public/index.php`
- Prepared statements PDO en todas las queries

---

## Limpieza - 2026-03-12

### Eliminado
- 16 scripts de test/diagnóstico en la raíz (`test_*.php`, `fix_*.php`, `run_migration.php`)
- Método muerto `buildScheduleAssignments()` + 4 helpers privados no utilizados (~580 líneas) de ScheduleController
- 6 llamadas `error_log()` de diagnóstico temporal en ScheduleBuilder
- Archivos temporales de Excel (`~$*.xlsx`)
- Vista eliminada `advisors/constraints.php` (reemplazada por bulk-config)
