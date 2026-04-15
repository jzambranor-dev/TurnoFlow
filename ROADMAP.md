# TurnoFlow — Roadmap de Mejoras v2.0

## Estado actual: v1.3.0
Sistema funcional con motor de horarios ScheduleBuilder v3, asesores compartidos,
flujo de aprobación, API REST, check-in, asistencia y reportes básicos.

---

## Fase 1 — Quick Wins (alto impacto, bajo esfuerzo)

### 1.1 Sistema de Notificaciones In-App
**Objetivo:** Que los usuarios sepan cuándo cambia el estado de un horario sin tener que entrar al sistema.

**Tablas nuevas:**
```sql
CREATE TABLE notifications (
    id          BIGSERIAL PRIMARY KEY,
    user_id     INTEGER NOT NULL REFERENCES users(id),
    tipo        VARCHAR(50) NOT NULL, -- 'horario_enviado', 'horario_aprobado', 'horario_rechazado', 'ausencia_registrada'
    titulo      VARCHAR(200) NOT NULL,
    mensaje     TEXT,
    url         VARCHAR(500),
    leida       BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_notif_user ON notifications(user_id, leida, created_at DESC);
```

**Disparadores:**
- Supervisor envía horario → notificar a coordinadores con `schedules.approve`
- Coordinador aprueba/rechaza → notificar al supervisor dueño del horario
- Ausencia registrada → notificar a coordinadores
- Asesor compartido activo/inactivo → notificar supervisor afectado

**UI:**
- Badge con contador en el header (campana 🔔)
- Dropdown con últimas 10 notificaciones
- Página `/notifications` con historial completo
- Marcar como leída al hacer clic

**Archivos a crear/modificar:**
- `sql/migrations/006_notifications.sql`
- `app/Controllers/NotificationController.php`
- `app/Views/layouts/partials/header.php` (badge + dropdown)
- `app/Views/notifications/index.php`
- Disparar desde: `ScheduleController::submit()`, `approve()`, `reject()`, `attendance()`

---

### 1.2 Dashboard con Gráficas Reales (Chart.js)
**Objetivo:** Mostrar tendencias visuales de cobertura, horas y aprobaciones.

Chart.js ya está disponible en `dist/`. Solo usarlo correctamente.

**Gráficas a agregar:**

**Para admin/gerente/coordinador:**
- Gráfica de barras: Horarios por estado (borrador, enviado, aprobado, rechazado) — últimos 3 meses
- Gráfica de línea: Cobertura promedio vs requerimiento — últimos 30 días (top 3 campañas)
- Gráfica de dona: Distribución de asesores por campaña

**Para supervisor:**
- Gráfica de línea: Cobertura de su campaña esta semana (hora por hora del día actual)
- Gráfica de barras: Horas asignadas por asesor este mes vs meta

**API endpoints nuevos (JSON):**
- `GET /api/dashboard/coverage-trend?campaign_id=X&days=30`
- `GET /api/dashboard/schedule-stats?months=3`
- `GET /api/dashboard/advisor-hours?campaign_id=X&year=Y&month=M`

**Archivos a modificar:**
- `app/Controllers/DashboardController.php` (agregar métodos API)
- `app/Views/dashboard/index.php` (agregar secciones de gráficas)
- `public/index.php` (nuevas rutas API dashboard)

---

### 1.3 Exportación PDF de Horarios
**Objetivo:** Generar PDF del horario mensual/diario directamente desde el sistema.

**Librería:** Usar mPDF o TCPDF via Composer (elegir mPDF por mejor soporte HTML).
```bash
composer require mpdf/mpdf
```

**Formato del PDF:**
- Header con logo/nombre empresa, campaña, período
- Tabla de horario mensual: asesores × días con bloques de trabajo
- Colores por tipo de turno (normal, extra, nocturno, break)
- Footer con total de horas por asesor
- Firma/sello para aprobación

**Rutas nuevas:**
- `GET /schedules/{id}/export-pdf` — PDF horario mensual
- `GET /schedules/{id}/export-pdf-daily?date=YYYY-MM-DD` — PDF día específico
- `GET /reports/hours/{id}/export-pdf` — PDF reporte de horas

**Archivos a crear:**
- `app/Services/PdfService.php`
- Modificar `ScheduleController` y `ReportController`
- Modificar vistas show/reports para agregar botón "Descargar PDF"

---

## Fase 2 — Funcionalidades Core

### 2.1 Auditoría de Cambios (Audit Log)
**Objetivo:** Registrar quién modificó qué y cuándo en horarios y configuraciones.

**Tabla:**
```sql
CREATE TABLE audit_log (
    id          BIGSERIAL PRIMARY KEY,
    user_id     INTEGER REFERENCES users(id),
    accion      VARCHAR(100) NOT NULL, -- 'schedule.edit', 'advisor.create', 'attendance.update'
    entidad     VARCHAR(50),           -- 'schedules', 'advisors', 'shift_assignments'
    entidad_id  INTEGER,
    datos_antes JSONB,
    datos_despues JSONB,
    ip          VARCHAR(45),
    created_at  TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_audit_entidad ON audit_log(entidad, entidad_id, created_at DESC);
CREATE INDEX idx_audit_user ON audit_log(user_id, created_at DESC);
```

**Qué auditar:**
- Cambios en shift_assignments (editor de horario)
- Cambios de status en schedules (submit, approve, reject)
- Creación/edición de asesores
- Cambios en advisor_constraints
- Registro de asistencia
- Creación/revocación de tokens API

**UI:**
- Página `/audit-log` (solo admin/gerente)
- Filtros: por usuario, por entidad, por fecha
- En la vista de horario: pestaña "Historial de cambios"

**Archivos a crear:**
- `sql/migrations/007_audit_log.sql`
- `app/Services/AuditService.php` (helper estático `AuditService::log(...)`)
- `app/Controllers/AuditController.php`
- `app/Views/audit/index.php`

---

### 2.2 Flujo de Ausencias y Reemplazos Asistido
**Objetivo:** Cuando un asesor falta, el sistema sugiere automáticamente quién puede cubrir basándose en la lógica del ScheduleBuilder.

**Flujo mejorado:**
1. Supervisor registra ausencia (ya existe en `attendance`)
2. Sistema detecta las horas huérfanas automáticamente
3. Busca candidatos elegibles con la misma lógica del ScheduleBuilder:
   - Mismo campaña (y compartidos disponibles)
   - No supera max_horas_dia
   - VPN si turno nocturno
   - Sin restricción médica activa
4. Muestra modal con lista de candidatos ordenados por menos horas acumuladas
5. Supervisor selecciona y confirma
6. Sistema crea nuevas `shift_assignments` con `tipo='replanif'`
7. Registra en `replanning_log`
8. Dispara notificación al coordinador

**UI:**
- En vista tracking: botón "Gestionar Ausencia" al marcar asesor como ausente
- Modal con horas afectadas + candidatos sugeridos (con chips de disponibilidad)
- Confirmación y aplicación con feedback toast

**Archivos a modificar:**
- `app/Controllers/ScheduleController.php` — agregar `suggestReplacements()` y `applyReplacement()`
- `app/Views/schedules/tracking.php` — botón + modal
- `public/index.php` — rutas nuevas

---

### 2.3 Alertas de Déficit en Tiempo Real (editor)
**Objetivo:** En el editor de horario, mostrar alerta cuando la cobertura cae por debajo del requerimiento ANTES de guardar.

**Implementación:**
- Panel lateral en el editor con barra de cobertura por hora del día seleccionado
- Si cobertura < requerido: barra roja + alerta flotante
- Si cobertura == requerido: verde
- Si cobertura > requerido: amarillo (sobrecobertura)
- Al intentar enviar a aprobación: validación server-side con resumen de déficits

**API:**
- `GET /api/schedules/{id}/coverage-check?date=YYYY-MM-DD` → JSON con cobertura hora a hora

**Archivos a modificar:**
- `app/Views/schedules/show.php` — panel de cobertura en tiempo real
- `app/Controllers/ScheduleController.php` — endpoint coverage-check
- `public/index.php` — ruta nueva

---

## Fase 3 — Optimizaciones Técnicas

### 3.1 Refactorización ScheduleController
- Extraer lógica de importación a `ImportService.php`
- Extraer lógica de reporting a `ScheduleReportService.php`
- Reducir de ~73KB a máx 30KB por archivo

### 3.2 Índices y Queries
- Revisar queries N+1 en vistas de horario
- Agregar índices compuestos faltantes
- Memoización de queries costosas dentro de la request

### 3.3 Paginación en tablas grandes
- `shift_assignments` puede tener miles de registros
- Agregar paginación server-side en vistas de horario con muchos asesores

---

## Orden de implementación sugerido

1. **Notificaciones** (Fase 1.1) — 2-3 horas
2. **Dashboard con gráficas** (Fase 1.2) — 3-4 horas
3. **Exportación PDF** (Fase 1.3) — 2-3 horas
4. **Audit Log** (Fase 2.1) — 2-3 horas
5. **Flujo de ausencias asistido** (Fase 2.2) — 4-5 horas
6. **Alertas de déficit** (Fase 2.3) — 2-3 horas
7. **Refactorización** (Fase 3) — 3-4 horas

**Total estimado: ~20-25 horas de desarrollo**

---

## Convenciones a mantener

- Sin frameworks — PHP 8.2 vanilla MVC
- PDO con prepared statements siempre
- showToast() en lugar de alert()
- Fetch API para AJAX (no jQuery.ajax)
- CSRF en todos los POST de formularios
- Metronic 8 para todos los componentes UI
- Commits descriptivos en español por feature
- Actualizar CHANGELOG.md al terminar cada fase
