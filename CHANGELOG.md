# Changelog - TurnoFlow (SGH)

Todos los cambios notables del proyecto se documentan en este archivo.

---

## [1.5.0] - 2026-04-15

### Fase 2 — Funcionalidades Core: Auditoria + Reemplazos Asistidos + Alertas Deficit

#### 2.1 Auditoria de Cambios (Audit Log)
- **Tabla `audit_log`**: Nueva tabla con indices compuestos para consultas por entidad y usuario (`sql/migrations/007_audit_log.sql`)
- **AuditService**: Servicio estatico para registrar acciones con datos antes/despues en formato JSONB, paginacion, filtros y exportacion CSV
- **AuditController**: Pagina `/audit-log` con filtros (usuario, entidad, fecha), paginacion 30/pagina, modal de detalle JSON lado a lado, exportacion CSV
- **Integracion en controladores existentes**:
  - `ScheduleController::submit()` — registra cambio a estado 'enviado'
  - `ScheduleController::approve()` — registra cambio a estado 'aprobado'
  - `ScheduleController::reject()` — registra cambio a estado 'rechazado' con nota
  - `ScheduleController::updateAssignments()` — registra ediciones de turnos
  - `ScheduleController::saveAttendance()` — registra cambios de asistencia
  - `AdvisorController::store()` — registra creacion de asesor
  - `AdvisorController::update()` — registra actualizacion de asesor
- **Item en sidebar**: 'Auditoria' visible solo para admin/gerente
- **Historial en vista de horario**: Panel colapsable con ultimos cambios del schedule via API `/api/audit/schedule/{id}`
- **3 rutas nuevas**: `GET /audit-log`, `GET /audit-log/export`, `GET /api/audit/schedule/{id}`

#### 2.2 Flujo de Ausencias y Reemplazos Asistido
- **suggestReplacements()**: Endpoint POST que busca candidatos elegibles para cubrir ausencia, usando logica del ScheduleBuilder (misma campana, activo, VPN si nocturno, max_horas_dia, sin restriccion medica), ordenados por menos horas acumuladas en el mes
- **applyReplacement()**: Endpoint POST que crea shift_assignments tipo 'replanif', registra en replanning_log y notifica a coordinadores
- **Modal de reemplazo en tracking**: Al marcar asesor como 'ausente' aparece boton 'R' que abre modal con horas afectadas y lista de candidatos sugeridos con estadisticas (horas mes, horas hoy, disponibles, badge VPN)
- **2 rutas nuevas**: `POST /schedules/suggest-replacements`, `POST /schedules/apply-replacement`

#### 2.3 Alertas de Deficit en Tiempo Real
- **Panel de cobertura en vista diaria**: Barras hora a hora con colores (verde=cubierto, rojo=deficit, amarillo=sobrecobertura) en la vista diaria y editor del horario
- **coverageCheck()**: Endpoint API GET que devuelve cobertura vs requerimiento hora a hora para un dia especifico
- **Actualizacion automatica**: El panel de cobertura se actualiza despues de cada edicion guardada en el editor
- **Validacion pre-envio**: Al hacer clic en 'Enviar a aprobacion' se verifica cobertura de todos los dias; si hay deficits muestra modal de confirmacion con resumen detallado antes de proceder
- **1 ruta nueva**: `GET /api/schedules/{id}/coverage-check`

---

## [1.4.0] - 2026-04-15

### Fase 1 — Notificaciones In-App + Dashboard con Graficas + Exportacion PDF

#### 1.1 Sistema de Notificaciones In-App
- **Tabla `notifications`**: Nueva tabla con indice compuesto para consultas rapidas (`sql/migrations/006_notifications.sql`)
- **NotificationService**: Servicio estatico para enviar notificaciones a usuarios individuales o por permiso, con paginacion y conteo de no leidas
- **NotificationController**: Endpoints para listar (paginado), marcar leida, marcar todas leidas, y API JSON de no leidas
- **Campana en header**: Badge con contador de notificaciones no leidas, dropdown con ultimas 5, actualizacion automatica cada 60 segundos
- **Vista `/notifications`**: Lista completa con iconos por tipo, destacado de no leidas, paginacion, boton marcar todas como leidas
- **Disparadores automaticos**:
  - `ScheduleController::submit()` → notifica a todos los usuarios con permiso `schedules.approve`
  - `ScheduleController::approve()` → notifica al supervisor que genero el horario
  - `ScheduleController::reject()` → notifica al supervisor con motivo de rechazo
- **4 rutas nuevas**: `GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all`, `GET /api/notifications/unread`
- **Item en sidebar**: Acceso directo a notificaciones para todos los roles
- **Meta CSRF**: Tag meta con token CSRF en layout principal para peticiones AJAX

#### 1.2 Dashboard con Graficas (Chart.js)
- **3 endpoints API JSON**: `/api/dashboard/coverage-trend`, `/api/dashboard/schedule-stats`, `/api/dashboard/advisor-hours`
- **Graficas admin/gerente/coordinador**: Barras de horarios por estado (ultimos 3 meses) + dona de asesores por campana
- **Grafica supervisor**: Barras horizontales de horas asignadas vs meta por asesor del mes actual
- **Chart.js via CDN**: Fallback automatico si no esta en dist/

#### 1.3 Exportacion PDF de Horarios
- **mPDF v8.3**: Instalado via Composer (`mpdf/mpdf`)
- **PdfService**: Genera PDF landscape A4 del horario mensual con tabla asesores x dias, colores por tipo de turno, leyenda y totales
- **Boton "Descargar PDF"**: En la vista de detalle del horario (`/schedules/{id}`)
- **Ruta**: `GET /schedules/{id}/export-pdf`

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
