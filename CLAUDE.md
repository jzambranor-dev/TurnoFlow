# TurnoFlow (SGH) — Contexto del Proyecto

## Descripcion
Sistema de gestion de horarios para call centers. Permite crear, aprobar y controlar
turnos de asesores, incluyendo turnos partidos, horas extra, asesores compartidos entre
campanas, actividades especiales y seguimiento de asistencia diaria.

---

## Stack Tecnologico

- **Backend:** PHP 8.2 (sin framework, arquitectura MVC propia)
- **Base de datos:** PostgreSQL 15+
- **Frontend:** Metronic 8 (Bootstrap 5 + jQuery)
- **Entorno local:** XAMPP — `C:\xampp\htdocs\system-horario\TurnoFlow`
- **Control de versiones:** GitHub
- **URL local:** `http://localhost/system-horario/TurnoFlow/public`

---

## Estructura de Directorios

```
TurnoFlow/
├── app/
│   ├── Controllers/       ← 11 controladores PHP
│   ├── Services/          ← AuthService (permisos)
│   └── Views/             ← Vistas PHP con layout Metronic
├── config/
│   └── database.php       ← Credenciales PDO (nunca hardcodear)
├── public/
│   ├── index.php          ← Router principal (~500 lineas, ~54 rutas)
│   └── assets/            ← Metronic (CSS, JS, imagenes)
├── sql/
│   ├── schema.sql         ← Schema PostgreSQL principal (14 tablas)
│   ├── permissions.sql    ← Tablas permissions + role_permissions
│   ├── migration_campaign_activities.sql
│   ├── migration_shared_advisors.sql
│   └── migrations/
└── vendor/                ← Composer autoload
```

---

## Base de Datos

**18 tablas, 3 ENUMs, 2 vistas.**

### Tablas principales
| Tabla | Descripcion |
|-------|-------------|
| `roles` | Roles del sistema (admin, gerente, coordinador, supervisor, asesor) |
| `users` | Usuarios con `rol_id` FK a roles, `activo` boolean |
| `permissions` | Permisos individuales con `codigo` y `modulo` |
| `role_permissions` | Pivote rol-permiso |
| `campaigns` | Campanas del call center (Videollamada, Kiosco, etc.) |
| `advisors` | Asesores con `campaign_id`, `tipo_contrato`, `estado` |
| `advisor_constraints` | Restricciones por asesor (VPN, extras, medicas, dias descanso) |
| `monthly_hours_config` | Horas requeridas por mes/anio |
| `staffing_imports` | Importaciones de dimensionamiento Excel |
| `staffing_requirements` | Requerimiento hora-a-hora por campana |
| `schedules` | Cabecera de horario (status: borrador/enviado/aprobado/rechazado) |
| `shift_assignments` | **Corazon del sistema** — asignaciones advisor+fecha+hora |
| `attendance` | Registro de asistencia diaria por asesor |
| `advisor_checkins` | Auto check-in de asesores (separado de attendance) |
| `replanning_log` | Log de replanificaciones por ausencia |
| `monthly_summary` | Resumen mensual de horas por asesor |
| `shared_advisors` | Asesores prestados entre campanas (source → target) |
| `campaign_activities` | Actividades por campana (ej: Kiosco) con color |
| `advisor_activity_assignments` | Asignacion de actividad a asesor |

### ENUMs
- `schedule_status`: borrador, enviado, aprobado, rechazado
- `shift_type`: normal, extra, nocturno, replanif
- `attendance_status`: presente, ausente, tardanza, salida_anticipada, licencia_medica, maternidad

### Convenciones de BD
- Nombres en **snake_case**
- PKs: `id SERIAL PRIMARY KEY` (o `BIGSERIAL` para tablas de alto volumen)
- Timestamps: `created_at` con `DEFAULT NOW()`
- Claves foraneas con `ON DELETE RESTRICT` salvo cascadas documentadas
- Constraints `UNIQUE` para evitar duplicados logicos (ej: `advisor_id, fecha, hora`)

---

## Controladores (11)

| Controlador | Responsabilidad |
|-------------|-----------------|
| `AuthController` | Login, logout, sesion |
| `DashboardController` | Stats por rol (admin/gerente/coordinador vs supervisor vs asesor) |
| `CampaignController` | CRUD campanas |
| `AdvisorController` | CRUD asesores, configuracion masiva de restricciones |
| `ScheduleController` | **El mas complejo** — generar, importar, ver, editar, aprobar, tracking, check-in, asistencia |
| `ReportController` | Reportes de horas por campana |
| `UserController` | CRUD usuarios, reset password, toggle status |
| `RoleController` | CRUD roles con permisos |
| `ActivityController` | CRUD actividades por campana, asignaciones |
| `SharedAdvisorController` | Asesores compartidos entre campanas |
| `SettingController` | Configuracion del sistema |

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

---

## Reglas de Negocio Criticas

1. **Turnos partidos:** Un asesor puede tener hasta 2 bloques de trabajo en el mismo dia.
   Validar que no se solapen y que el descanso entre bloques sea >= 30 min.

2. **Horas extra:** Requieren solicitud previa. Flag `es_extra = true` en `shift_assignments`.

3. **Restriccion VPN en turnos nocturnos:** Asesores en turno nocturno (22:00-06:00)
   necesitan VPN. Verificar `advisor_constraints.tiene_vpn` antes de asignar.

4. **Flujo de aprobacion:** Horario pasa por: `borrador → enviado → aprobado/rechazado`.
   Solo roles con permiso pueden aprobar (admin, gerente, coordinador).

5. **Asesores compartidos:** Un asesor puede prestarse a otra campana via `shared_advisors`.
   - Deteccion de conflictos: no puede estar en la misma hora en dos campanas.
   - Se usa `crossCampaignHoursMap` en el editor para validar en tiempo real.

6. **Actividades:** Asesores compartidos pueden tener actividades asignadas (ej: Kiosco).
   Se visualizan con color y abreviatura en la grilla del editor.

7. **Check-in de asesores:** Los asesores hacen auto check-in diario (tabla `advisor_checkins`).
   Es independiente de `attendance` (que es gestionada por supervisor).
   - Admin/gerente/coordinador pueden bypass el requisito de check-in.

8. **Resolucion asesor ↔ usuario:** No existe FK `user_id` en `advisors`. Se resuelve
   por coincidencia de nombre: `LOWER(nombres || ' ' || apellidos) = LOWER(:full_name)`.

---

## Lo que NO debes hacer

### Codigo
- No usar `mysqli_*` — solo PDO con prepared statements
- No concatenar variables en queries SQL — siempre parametros `:`
- No mezclar logica SQL en las vistas
- No usar `alert()` — usar `showToast()` siempre
- No usar heredoc cuando el JS no necesita variables PHP — usar nowdoc
- No escapar comillas con `\\\''` en heredoc — usar `&#39;`
- No hardcodear credenciales — leer desde `config/database.php`

### Base de datos
- No borrar registros de schedules — usar soft delete o cambio de status
- No hacer INSERT/UPDATE directo a attendance sin validar advisor_id
- No asumir que `advisors` tiene columna `user_id` — NO EXISTE

### Arquitectura
- No usar GET para operaciones que modifican estado (submit, approve, reject, toggle)
  - Actualmente algunos usan GET (deuda tecnica conocida, migrar a POST progresivamente)
- No olvidar verificar permisos con `AuthService::requirePermission()` en cada metodo publico
- No olvidar que `$_SESSION['user']['rol']` es el nombre del rol (string), no el ID

---

## Rutas Principales

### Publicas
- `GET /login`, `POST /login`, `GET /logout`

### Dashboard
- `GET /dashboard` — Dashboard adaptado al rol del usuario

### Campanas
- `GET /campaigns`, `GET /campaigns/create`, `POST /campaigns`
- `GET /campaigns/{id}/edit`, `POST /campaigns/{id}`
- `GET /campaigns/{id}/activities`, `GET /campaigns/{id}/activities/create`, `POST /campaigns/{id}/activities`
- `GET /campaigns/{id}/shared-advisors`, `GET /campaigns/{id}/shared-advisors/create`, `POST /campaigns/{id}/shared-advisors`

### Horarios (ScheduleController — el mas extenso)
- `GET /schedules` — Lista de horarios
- `GET /schedules/generate`, `POST /schedules/generate` — Motor de generacion (ScheduleBuilder)
- `GET /schedules/import`, `POST /schedules/import` — Importar dimensionamiento Excel
- `GET /schedules/{id}` — Ver horario (diario/editar/mensual)
- `GET /schedules/{id}/submit` — Enviar para aprobacion
- `GET /schedules/{id}/approve` — Aprobar
- `GET /schedules/{id}/reject` — Rechazar
- `GET /schedules/{id}/tracking` — Seguimiento diario + asistencia
- `POST /schedules/{id}/checkin` — API JSON: toggle check-in asesor
- `POST /schedules/{id}/attendance` — API JSON: guardar asistencia
- `POST /schedules/{id}/assignments` — API JSON: guardar ediciones de grilla

### Otros
- `GET /advisors`, `GET /advisors/create`, `POST /advisors`, `GET /advisors/{id}/edit`, `POST /advisors/{id}`
- `GET /advisors/bulk-config`, `POST /advisors/bulk-config`
- `GET /users`, `GET /users/create`, `POST /users`, `GET /users/{id}/edit`, `POST /users/{id}`
- `POST /users/{id}/reset-password`, `GET /users/{id}/toggle-status`
- `GET /reports`, `GET /reports/hours/{id}`
- `GET /roles`, `GET /roles/create`, `POST /roles`, `GET /roles/{id}/edit`, `POST /roles/{id}`, `GET /roles/{id}/delete`
- `GET /settings`

---

## Comandos utiles

```bash
# Conectar a la BD local
psql -U postgres -d turnoflow

# Ver migraciones
ls sql/migrations/

# Acceso local
# http://localhost/system-horario/TurnoFlow/public
```

---

## Contexto Adicional

- El sistema reemplaza control de turnos manual en Excel
- Campana "Videollamada" es la referencia principal con datos reales
- Metronic 8 ya esta configurado — mantener consistencia visual
- El editor de horarios usa sistema drag-to-paint con modos (normal, break, extra, nocturno, remove, activity_X)
- Repositorio en GitHub; commits descriptivos por feature
