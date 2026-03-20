# TurnoFlow (SGH) — Contexto del Proyecto

## Descripcion
Sistema de gestion de horarios para call centers. Permite crear, aprobar y controlar
turnos de asesores, incluyendo turnos partidos, horas extra, breaks, asesores compartidos
entre campanas, actividades especiales, seguimiento de asistencia diaria, y API REST
para integraciones externas.

---

## Stack Tecnologico

- **Backend:** PHP 8.2 (sin framework, arquitectura MVC propia)
- **Base de datos:** PostgreSQL 15+
- **Frontend:** Metronic 8 (Bootstrap 5 + jQuery)
- **Dependencias PHP:** PhpSpreadsheet 5.5+ (import/export Excel)
- **Entorno local:** XAMPP — `C:\xampp\htdocs\system-horario\TurnoFlow`
- **Docker:** `docker-compose.yml` disponible como alternativa
- **Control de versiones:** GitHub
- **URL local:** `http://localhost/system-horario/TurnoFlow/public`

---

## Estructura de Directorios

```
TurnoFlow/
├── app/
│   ├── Controllers/       ← 12 controladores PHP
│   ├── Services/          ← 4 servicios (Auth, CSRF, API Auth, ScheduleBuilder)
│   └── Views/             ← 35 vistas PHP con layout Metronic (14 directorios)
├── config/
│   └── database.php       ← PDO singleton + .env loader (nunca hardcodear)
├── public/
│   ├── index.php          ← Router principal (~656 lineas, ~76 rutas)
│   └── assets/            ← Assets custom (CSS, JS)
├── dist/                  ← Metronic UI framework (CSS, JS, plugins, media)
├── sql/
│   ├── schema.sql         ← Schema PostgreSQL principal
│   ├── permissions.sql    ← Tablas permissions + role_permissions
│   ├── migration_break.sql
│   ├── migration_campaign_activities.sql
│   ├── migration_modalidad_advisor.sql
│   ├── migration_shared_advisors.sql
│   ├── seed_users.php
│   ├── seed_asesores_unificada.php
│   └── migrations/        ← 003_checkins, 004_holidays_params, 005_api_tokens
├── docs/                  ← Excel de referencia (dimensionamiento, horarios)
├── uploads/               ← Almacenamiento temporal de imports CSV/Excel
├── vendor/                ← Composer autoload
└── .env                   ← Variables de entorno (no commitear)
```

---

## Base de Datos

**22 tablas, 3 ENUMs (+1 opcional), 2 vistas.**

### Tablas principales
| Tabla | Descripcion |
|-------|-------------|
| `roles` | Roles del sistema (admin, gerente, coordinador, supervisor, asesor) |
| `users` | Usuarios con `rol_id` FK a roles, `activo` boolean |
| `permissions` | Permisos individuales con `codigo` y `modulo` |
| `role_permissions` | Pivote rol-permiso |
| `campaigns` | Campanas del call center con `tiene_break`, `duracion_break_min` |
| `advisors` | Asesores con `campaign_id`, `tipo_contrato`, `estado` |
| `advisor_constraints` | Restricciones por asesor (VPN, extras, medicas, dias descanso, modalidad) |
| `monthly_hours_config` | Horas requeridas por mes/anio |
| `staffing_imports` | Importaciones de dimensionamiento Excel |
| `staffing_requirements` | Requerimiento hora-a-hora por campana |
| `schedules` | Cabecera de horario (status: borrador/enviado/aprobado/rechazado) |
| `shift_assignments` | **Corazon del sistema** — asignaciones advisor+fecha+hora |
| `attendance` | Registro de asistencia diaria por asesor |
| `advisor_checkins` | Auto check-in de asesores (separado de attendance) |
| `replanning_log` | Log de replanificaciones por ausencia |
| `monthly_summary` | Resumen mensual de horas por asesor |
| `shared_advisors` | Asesores prestados entre campanas (source → target, max horas/dia) |
| `campaign_activities` | Actividades por campana (ej: Kiosco) con color |
| `advisor_activity_assignments` | Asignacion de actividad a asesor con horas y dias |
| `holidays` | Calendario de feriados (fecha, nombre) |
| `system_params` | Parametros globales (nocturno inicio/fin, break, max horas/dia) |
| `api_tokens` | Tokens API con hash, prefijo, permisos, expiracion |
| `api_rate_log` | Log de rate limiting para tokens API |

### ENUMs
- `schedule_status`: borrador, enviado, aprobado, rechazado
- `shift_type`: normal, extra, nocturno, replanif, **break**
- `attendance_status`: presente, ausente, tardanza, salida_anticipada, licencia_medica, maternidad
- `modalidad_trabajo` (opcional): presencial, teletrabajo, mixto

### Vistas SQL
- `v_coverage_vs_required` — Cobertura vs requerimiento por hora (asignados, diferencia, %)
- `v_advisor_night_eligibility` — Elegibilidad de asesores para turnos nocturnos (VPN, medicas)

### Convenciones de BD
- Nombres en **snake_case**
- PKs: `id SERIAL PRIMARY KEY` (o `BIGSERIAL` para tablas de alto volumen)
- Timestamps: `created_at` con `DEFAULT NOW()`
- Claves foraneas con `ON DELETE RESTRICT` salvo cascadas documentadas
- Constraints `UNIQUE` para evitar duplicados logicos (ej: `advisor_id, fecha, hora`)

---

## Controladores (12)

| Controlador | Responsabilidad |
|-------------|-----------------|
| `AuthController` | Login, logout, sesion |
| `DashboardController` | Stats por rol (admin/gerente/coordinador vs supervisor vs asesor) |
| `CampaignController` | CRUD campanas |
| `AdvisorController` | CRUD asesores, configuracion masiva de restricciones |
| `ScheduleController` | **El mas complejo** — generar, importar, ver, editar, aprobar, tracking, check-in, asistencia, mi horario |
| `ReportController` | Reportes de horas por campana, exportacion Excel/PDF, reporte unificado |
| `UserController` | CRUD usuarios, reset password, toggle status |
| `RoleController` | CRUD roles con permisos |
| `ActivityController` | CRUD actividades por campana, asignaciones advisor-actividad |
| `SharedAdvisorController` | Asesores compartidos entre campanas, toggle estado |
| `SettingController` | Configuracion: horas mensuales, feriados, parametros sistema, tokens API, changelog |
| `ApiReportController` | **API REST** — endpoints JSON con autenticacion Bearer token |

---

## Servicios (4)

| Servicio | Responsabilidad |
|----------|-----------------|
| `AuthService` | Verificacion de permisos (`hasPermission`, `requirePermission`), cache en memoria |
| `CsrfService` | Generacion y validacion de tokens CSRF, metodo `field()` para formularios |
| `ApiAuthService` | Autenticacion Bearer token, rate limiting (60 req/60s), gestion de tokens |
| `ScheduleBuilder` | **Motor de generacion v3** — algoritmo de 9 fases para asignacion optima de turnos |

### ScheduleBuilder — Fases del algoritmo
1. Distribucion de dias libres
2. Rotacion de turnos nocturnos (velada)
3. Actividades fijas
4. Asignacion hora-a-hora con ratio de equidad
5. Consolidacion de bloques
6. Limpieza multi-gap (relajacion en pasada 4 si necesario)
7. Asignacion de breaks (fraccion configurable, default 30 min)
8. Asesores compartidos — cobertura residual via actividades fijas
9. Delegacion trivial — turnos de 1-2h delegados a asesores compartidos

---

## Roles de Usuario (5 niveles)

| Rol | Jerarquia | Permisos clave |
|-----|-----------|----------------|
| `admin` | Maximo | Acceso total a todo el sistema |
| `gerente` | Alto | Mismos poderes que admin en vistas; puede aprobar y bypass check-in |
| `coordinador` | Alto | Aprobar horarios, configurar horas mensuales, bypass check-in |
| `supervisor` | Medio | Generar/enviar horarios de SUS campanas, tracking diario |
| `asesor` | Basico | Ver su horario, hacer check-in, ver su dashboard |

### Patron de bypass por rol
Los roles admin, gerente y coordinador pueden:
- Confirmar asistencia sin esperar check-in de asesores
- Ver y gestionar todas las campanas (no solo las suyas)

```php
$canBypassCheckin = in_array($userRole, ['admin', 'gerente', 'coordinador'], true);
```

### Sistema de permisos
- `AuthService::requirePermission('codigo_permiso')` en cada controlador
- Permisos almacenados en `permissions` con `codigo` (ej: `schedules.view`, `campaigns.create`)
- Relacion via `role_permissions` pivote
- Cache en memoria estatica durante la request

### Proteccion CSRF
- `CsrfService::field()` genera campo hidden en formularios
- `CsrfService::validateOrFail()` valida en cada POST
- Comparacion segura con `hash_equals()`

---

## Patrones de Codigo

### Vistas PHP (output buffering)
Las vistas usan `ob_start()` / `ob_get_clean()` con variables `$content`, `$extraStyles`, `$extraScripts`:
```php
$extraStyles = [];
$extraScripts = [];
ob_start();
// ... HTML de la vista ...
$content = ob_get_clean();
include APP_PATH . '/Views/layouts/main.php';
```

### Heredoc vs Nowdoc en vistas
- **Heredoc** (`<<<SCRIPT`): Interpola variables PHP (`$var`). Usar cuando necesitas inyectar valores PHP en JS.
- **Nowdoc** (`<<<'SCRIPT'`): NO interpola. Usar para JS puro sin variables PHP.
- **CRITICO:** En heredoc, las comillas simples en JS se escapan como `\'`. Si necesitas comillas en `innerHTML`, usar `&#39;` (entidad HTML) en lugar de `\\\''` que causa errores de sintaxis JS.

### Inyeccion de variables PHP → JS
Cuando JS necesita valores PHP, inyectarlos como constantes en un `<script>` separado ANTES del bloque nowdoc:
```php
$extraScripts[] = "<script>const BASE_URL = '{$appUrl}';</script>";
$extraScripts[] = <<<'SCRIPT'
<script>
// JS puro aqui, usa BASE_URL como constante global
fetch(BASE_URL + '/api/endpoint');
</script>
SCRIPT;
```

### Toasts y notificaciones (NO usar alert())
El sistema usa toasts personalizados en lugar de `alert()` nativo:
```javascript
showToast('Mensaje', 'success');  // success, error, warning, info
showToast('Error critico', 'error', 5000);  // duracion en ms
```

### Loading overlay
```javascript
showLoading('Guardando cambios...');
// ... operacion asincrona ...
hideLoading();
```

### Fetch API (no jQuery AJAX para nuevas features)
```javascript
const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
});
const data = await res.json();
```

### Respuestas API REST (ApiReportController)
```php
// Exito
ApiAuthService::jsonSuccess(['campaigns' => $data], 200);
// Error
ApiAuthService::jsonError('No autorizado', 401);
```

---

## Reglas de Negocio Criticas

1. **Turnos partidos:** Un asesor puede tener hasta 2 bloques de trabajo en el mismo dia.
   Validar que no se solapen y que el descanso entre bloques sea >= 30 min.

2. **Breaks:** Campanas con `tiene_break = true` asignan breaks automaticamente.
   El break se coloca en medio del bloque de trabajo mas largo. Fraccion configurable (default 0.5 = 30 min).

3. **Horas extra:** Requieren solicitud previa. Flag `es_extra = true` en `shift_assignments`.

4. **Restriccion VPN en turnos nocturnos:** Asesores en turno nocturno (22:00-06:00)
   necesitan VPN. Verificar `advisor_constraints.tiene_vpn` antes de asignar.

5. **Flujo de aprobacion:** Horario pasa por: `borrador → enviado → aprobado/rechazado`.
   Solo roles con permiso pueden aprobar (admin, gerente, coordinador).

6. **Asesores compartidos:** Un asesor puede prestarse a otra campana via `shared_advisors`.
   - Deteccion de conflictos: no puede estar en la misma hora en dos campanas.
   - Se usa `crossCampaignHoursMap` en el editor para validar en tiempo real.
   - Regenerar horario de campana target auto-regenera source si hay asesores compartidos.
   - Limite configurable: `max_horas_dia` por asesor compartido.

7. **Actividades:** Asesores compartidos pueden tener actividades asignadas (ej: Kiosco).
   Se visualizan con color y abreviatura en la grilla del editor.

8. **Check-in de asesores:** Los asesores hacen auto check-in diario (tabla `advisor_checkins`).
   Es independiente de `attendance` (que es gestionada por supervisor).
   - Admin/gerente/coordinador pueden bypass el requisito de check-in.

9. **Resolucion asesor ↔ usuario:** No existe FK `user_id` en `advisors`. Se resuelve
   por coincidencia de nombre: `LOWER(nombres || ' ' || apellidos) = LOWER(:full_name)`.

10. **Feriados:** Tabla `holidays` con fechas que el ScheduleBuilder puede considerar.
    Gestion via Settings.

11. **Parametros del sistema:** Tabla `system_params` almacena configuracion global
    (hora inicio/fin nocturno, duracion break, max horas/dia, dias descanso semanal).

---

## Lo que NO debes hacer

### Codigo
- No usar `mysqli_*` — solo PDO con prepared statements
- No concatenar variables en queries SQL — siempre parametros `:`
- No mezclar logica SQL en las vistas
- No usar `alert()` — usar `showToast()` siempre
- No usar heredoc cuando el JS no necesita variables PHP — usar nowdoc
- No escapar comillas con `\\\''` en heredoc — usar `&#39;`
- No hardcodear credenciales — leer desde `config/database.php` via `.env`

### Base de datos
- No borrar registros de schedules — usar soft delete o cambio de status
- No hacer INSERT/UPDATE directo a attendance sin validar advisor_id
- No asumir que `advisors` tiene columna `user_id` — NO EXISTE

### Arquitectura
- No usar GET para operaciones que modifican estado (submit, approve, reject, toggle)
  - Ya migrado a POST en la mayoria de rutas; continuar migrando las restantes
- No olvidar verificar permisos con `AuthService::requirePermission()` en cada metodo publico
- No olvidar que `$_SESSION['user']['rol']` es el nombre del rol (string), no el ID
- No olvidar validar CSRF con `CsrfService::validateOrFail()` en POSTs de formularios
- No exponer tokens API en texto plano despues de la creacion inicial

---

## Rutas Principales (~76 rutas)

### Publicas
- `GET /`, `GET /login`, `POST /login`, `GET /logout`

### Dashboard
- `GET /dashboard` — Dashboard adaptado al rol del usuario

### Campanas
- `GET /campaigns`, `GET /campaigns/create`, `POST /campaigns`
- `GET /campaigns/{id}/edit`, `POST /campaigns/{id}`

### Actividades (por campana)
- `GET /campaigns/{id}/activities`, `GET /campaigns/{id}/activities/create`, `POST /campaigns/{id}/activities`
- `GET /activities/{id}/edit`, `POST /activities/{id}`
- `GET /activities/{id}/assignments`, `POST /activities/{id}/assignments`
- `GET /activities/assignments/{id}/remove`

### Asesores Compartidos
- `GET /campaigns/{id}/shared-advisors`, `GET /campaigns/{id}/shared-advisors/create`
- `POST /campaigns/{id}/shared-advisors`, `POST /shared-advisors/{id}/toggle`

### Horarios (ScheduleController — el mas extenso)
- `GET /schedules` — Lista de horarios
- `GET /my-schedule` — Horario personal del asesor
- `GET /schedules/generate`, `POST /schedules/generate` — Motor de generacion (ScheduleBuilder)
- `POST /schedules/regenerate-partial` — Regeneracion parcial
- `GET /schedules/import`, `POST /schedules/import` — Importar dimensionamiento Excel
- `POST /schedules/imports/{id}/delete` — Eliminar importacion
- `GET /schedules/{id}` — Ver horario (diario/editar/mensual)
- `POST /schedules/{id}/submit` — Enviar para aprobacion
- `POST /schedules/{id}/approve` — Aprobar
- `POST /schedules/{id}/reject` — Rechazar
- `GET /schedules/{id}/tracking` — Seguimiento diario + asistencia
- `POST /schedules/{id}/checkin` — API JSON: toggle check-in asesor
- `POST /schedules/{id}/attendance` — API JSON: guardar asistencia
- `POST /schedules/{id}/assignments` — API JSON: guardar ediciones de grilla

### Asesores
- `GET /advisors`, `GET /advisors/create`, `POST /advisors`
- `GET /advisors/{id}/edit`, `POST /advisors/{id}`
- `GET /advisors/bulk-config`, `POST /advisors/bulk-config`

### Usuarios
- `GET /users`, `GET /users/create`, `POST /users`
- `GET /users/{id}/edit`, `POST /users/{id}`
- `POST /users/{id}/reset-password`, `POST /users/{id}/toggle-status`

### Reportes
- `GET /reports` — Lista de reportes
- `GET /reports/hours/{id}` — Reporte de horas por campana
- `GET /reports/hours/{id}/export` — Exportar reporte a Excel/PDF
- `GET /reports/export-unified` — Reporte unificado todas las campanas

### Roles
- `GET /roles`, `GET /roles/create`, `POST /roles`
- `GET /roles/{id}/edit`, `POST /roles/{id}`, `GET /roles/{id}/delete`

### Configuracion
- `GET /settings` — Pagina de configuracion
- `POST /settings/monthly-hours`, `POST /settings/monthly-hours/delete`
- `POST /settings/holidays`, `POST /settings/holidays/delete`
- `POST /settings/params` — Parametros del sistema
- `POST /settings/api-tokens`, `POST /settings/api-tokens/revoke`
- `GET /changelog` — Visor de changelog

### API REST (Bearer token, JSON)
- `GET /api/reports/campaigns` — Listar campanas (paginado, role-aware)
- `GET /api/reports/hours/{id}` — Reporte horas `?year=2026&month=3`
- `GET /api/reports/unified` — Reporte unificado
- `GET /api/reports/attendance/{id}` — Reporte asistencia por campana

---

## Migraciones

### En `/sql/` (raiz)
| Archivo | Proposito |
|---------|-----------|
| `schema.sql` | Schema principal |
| `permissions.sql` | Permisos y roles base |
| `migration_break.sql` | Soporte breaks en campanas + enum `break` en shift_type |
| `migration_campaign_activities.sql` | Actividades y asignaciones + 5 permisos |
| `migration_modalidad_advisor.sql` | Modalidad trabajo (presencial/teletrabajo/mixto) |
| `migration_shared_advisors.sql` | Asesores compartidos entre campanas |

### En `/sql/migrations/`
| Archivo | Proposito |
|---------|-----------|
| `003_add_advisor_checkins.sql` | Tabla check-in de asesores |
| `004_holidays_and_system_params.sql` | Feriados + parametros sistema |
| `005_api_tokens.sql` | Tokens API + rate limiting |

---

## Vistas (35 archivos, 14 directorios)

```
Views/
├── layouts/main.php + partials/ (header, sidebar, toolbar, footer)
├── auth/login.php
├── dashboard/index.php
├── campaigns/ (index, create, edit)
├── advisors/ (index, create, edit, bulk-config)
├── schedules/ (index, generate, import, show, tracking, my-schedule)
├── activities/ (index, create, edit, assignments)
├── shared-advisors/ (index, create)
├── reports/ (index, hours)
├── users/ (index, create, edit)
├── roles/ (index, create, edit)
├── settings/ (index, changelog)
└── errors/404.php
```

---

## Comandos utiles

```bash
# Conectar a la BD local
psql -U postgres -d turnoflow

# Ver migraciones
ls sql/migrations/

# Acceso local
# http://localhost/system-horario/TurnoFlow/public

# Docker (alternativa a XAMPP)
docker-compose up -d
```

---

## Contexto Adicional

- El sistema reemplaza control de turnos manual en Excel
- Campana "Videollamada" es la referencia principal con datos reales
- Metronic 8 ya esta configurado — mantener consistencia visual
- El editor de horarios usa sistema drag-to-paint con modos (normal, break, extra, nocturno, remove, activity_X)
- Repositorio en GitHub; commits descriptivos por feature
- Archivos Excel de referencia en `/docs/` para pruebas de importacion
- `CHANGELOG.md` documenta versiones 1.0 a 1.3.0
- `AGENTS.md` contiene contexto extendido para agentes IA (37KB)
