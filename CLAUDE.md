# CLAUDE.md — TurnoFlow · Sistema de Gestión de Horarios
# Lee este archivo automáticamente al abrir Claude Code en el repositorio
# Ruta local: C:\xampp\htdocs\system-horario\TurnoFlow\CLAUDE.md

---

## INSTRUCCIÓN INICIAL PARA CLAUDE CODE

Este archivo es el contexto completo del proyecto. Léelo entero antes de escribir
cualquier línea de código. Cuando termines de leerlo responde con:
- Resumen de lo que vas a construir
- Qué archivos ya existen en el repositorio que debas tener en cuenta
- Primera pregunta o acción a ejecutar

---

## NOMBRE DEL PROYECTO

**TurnoFlow** — Sistema de Gestión de Horarios para call center

**Ruta XAMPP local**: `C:\xampp\htdocs\system-horario\TurnoFlow\`
**URL local de desarrollo**: `http://localhost/system-horario/TurnoFlow/public/`

---

## ESTRUCTURA DEL REPOSITORIO (ya existe)

```
TurnoFlow/
├── dist/                  ← ⚠ METRONIC AQUÍ — NO modificar, solo referenciar
│   ├── assets/
│   │   ├── css/           ← estilos compilados de Metronic
│   │   ├── js/            ← scripts de Metronic + plugins
│   │   ├── media/         ← íconos, imágenes, logos de Metronic
│   │   └── plugins/       ← librerías: Select2, Flatpickr, DataTables, etc.
│   └── index.html         ← demo principal de Metronic (referencia de componentes)
├── docs/                  ← documentación del proyecto (léela si existe contenido)
├── app/
│   ├── Controllers/       ← crear aquí
│   ├── Models/            ← crear aquí
│   ├── Views/             ← crear aquí (usando Metronic)
│   └── Services/          ← motor de asignación aquí
├── config/
│   └── database.php       ← crear aquí
├── public/
│   └── index.php          ← punto de entrada (crear)
├── sql/
│   └── schema.sql         ← esquema completo (más abajo)
├── uploads/               ← archivos CSV/Excel temporales
├── .env.example
├── .gitignore
├── CLAUDE.md              ← este archivo
└── README.md
```

**IMPORTANTE**: Lee primero los archivos existentes en `docs/` para entender
si hay decisiones de diseño o arquitectura ya tomadas antes de empezar.

---

## USO OBLIGATORIO DE METRONIC (carpeta `dist/`)

**Todo el frontend DEBE usar Metronic**. Nunca escribas CSS personalizado para
componentes que Metronic ya tiene. La carpeta `dist/` ya está compilada y lista.

### Cómo referenciar los assets desde las vistas PHP:

```php
<!-- En el <head> de cada vista -->
<link rel="stylesheet" href="/system-horario/TurnoFlow/dist/assets/css/style.bundle.css">

<!-- Antes del </body> -->
<script src="/system-horario/TurnoFlow/dist/assets/js/scripts.bundle.js"></script>
```

### Layout base de Metronic a usar (sidebar + topbar)

Todas las páginas autenticadas heredan de `app/Views/layouts/main.php`:

```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $pageTitle ?? 'TurnoFlow' ?></title>
    <!-- Google Fonts requeridos por Metronic -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <!-- Metronic CSS -->
    <link rel="stylesheet" href="/system-horario/TurnoFlow/dist/assets/css/style.bundle.css">
</head>
<body id="kt_app_body" data-kt-app-layout="dark-sidebar"
      data-kt-app-header-fixed="true"
      data-kt-app-sidebar-enabled="true"
      data-kt-app-sidebar-fixed="true">

    <!-- app-root wrapper obligatorio de Metronic -->
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <div class="app-page flex-column flex-column-fluid" id="kt_app_page">

            <!-- HEADER -->
            <?php include __DIR__ . '/partials/header.php'; ?>

            <!-- WRAPPER -->
            <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">

                <!-- SIDEBAR -->
                <?php include __DIR__ . '/partials/sidebar.php'; ?>

                <!-- MAIN -->
                <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                    <div class="d-flex flex-column flex-column-fluid">
                        <!-- TOOLBAR / BREADCRUMB -->
                        <?php include __DIR__ . '/partials/toolbar.php'; ?>
                        <!-- CONTENT -->
                        <div id="kt_app_content" class="app-content flex-column-fluid">
                            <div id="kt_app_content_container" class="app-container container-xxl">
                                <?= $content ?>
                            </div>
                        </div>
                    </div>
                    <?php include __DIR__ . '/partials/footer.php'; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Metronic JS -->
    <script src="/system-horario/TurnoFlow/dist/assets/js/scripts.bundle.js"></script>
    <?php if (!empty($extraScripts)) foreach ($extraScripts as $s) echo $s; ?>
</body>
</html>
```

### Componentes Metronic a usar por pantalla

**Cards** (en lugar de divs custom):
```html
<div class="card card-flush shadow-sm">
    <div class="card-header pt-5">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold text-gray-900">Título</span>
            <span class="text-muted mt-1 fw-semibold fs-7">Subtítulo</span>
        </h3>
        <div class="card-toolbar">
            <button class="btn btn-sm btn-primary">+ Nuevo</button>
        </div>
    </div>
    <div class="card-body py-3">
        <!-- contenido -->
    </div>
</div>
```

**Stat cards** (para KPIs del dashboard):
```html
<div class="card bg-primary hoverable card-xl-stretch mb-5">
    <div class="card-body">
        <span class="svg-icon svg-icon-white svg-icon-3x ms-n1"><!-- ícono --></span>
        <div class="text-white fw-bold fs-2 mb-2 mt-5">177h</div>
        <div class="fw-semibold text-white">Meta del mes</div>
    </div>
</div>
```

**Tablas** (usar DataTables que ya está en dist/plugins):
```html
<table id="kt_table_asesores" class="table align-middle table-row-dashed fs-6 gy-5">
    <thead>
        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
            <th>Nombre</th>
            <th>Campaña</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody class="text-gray-600 fw-semibold"></tbody>
</table>
<script>
$("#kt_table_asesores").DataTable({ language: { url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" } });
</script>
```

**Badges de estado**:
```html
<!-- Aprobado -->  <span class="badge badge-light-success">Aprobado</span>
<!-- Pendiente --> <span class="badge badge-light-warning">Pendiente</span>
<!-- Rechazado --> <span class="badge badge-light-danger">Rechazado</span>
<!-- Borrador -->  <span class="badge badge-light-secondary">Borrador</span>
<!-- Nocturno -->  <span class="badge badge-light-primary">Nocturno</span>
```

**Modales**:
```html
<div class="modal fade" id="modal_nuevo_asesor" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nuevo Asesor</h2>
                <button class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-outline ki-cross fs-1"></i>
                </button>
            </div>
            <div class="modal-body px-5 px-lg-10 py-10">
                <!-- form -->
            </div>
        </div>
    </div>
</div>
```

**Switches / Toggles** (para VPN, horas extra, velada):
```html
<div class="form-check form-switch form-check-custom form-check-solid">
    <input class="form-check-input" type="checkbox" id="toggle_vpn" <?= $advisor->tiene_vpn ? 'checked' : '' ?>>
    <label class="form-check-label fw-semibold text-gray-500" for="toggle_vpn">
        Tiene VPN — puede cubrir turno nocturno
    </label>
</div>
```

**Alerts / Notices**:
```html
<!-- Alerta crítica -->
<div class="notice d-flex bg-light-danger rounded border-danger border border-dashed p-6 mb-6">
    <i class="ki-outline ki-shield-cross fs-2tx text-danger me-4"></i>
    <div class="d-flex flex-stack flex-grow-1">
        <div class="fw-semibold">
            <h4 class="text-gray-900 fw-bold">Déficit de cobertura</h4>
            <div class="fs-6 text-gray-700">Descripción del problema</div>
        </div>
    </div>
</div>

<!-- Info -->
<div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-6 mb-6">
    <i class="ki-outline ki-information-5 fs-2tx text-primary me-4"></i>
    <div class="fw-semibold fs-6 text-gray-700">Mensaje informativo</div>
</div>
```

**Stepper** (para flujo Importar → Generar → Enviar → Aprobar):
```html
<div class="stepper stepper-pills stepper-column d-flex flex-column" id="kt_stepper_workflow">
    <div class="d-flex flex-row-auto w-300px w-xl-400px">
        <div class="stepper-nav">
            <div class="stepper-item current" data-kt-stepper-element="nav">
                <div class="stepper-wrapper">
                    <div class="stepper-icon"><i class="ki-outline ki-check fs-2 stepper-check"></i><span class="stepper-number">1</span></div>
                    <div class="stepper-label"><h3 class="stepper-title">Importar</h3><div class="stepper-desc fw-semibold">Cargar CSV del jefe de contrato</div></div>
                </div>
                <div class="stepper-line h-40px"></div>
            </div>
            <!-- repetir para pasos 2, 3, 4 -->
        </div>
    </div>
</div>
```

**Progress / Horas acumuladas**:
```html
<div class="d-flex align-items-center">
    <span class="text-gray-700 fw-semibold fs-6 me-2">142 / 177h</span>
    <div class="progress h-6px w-150px bg-light-success">
        <div class="progress-bar bg-success" style="width: 80%"></div>
    </div>
</div>
```

**Sidebar — ítem de menú activo**:
```html
<!-- En sidebar.php — adaptar a la navegación del rol -->
<div class="menu-item <?= $currentPage === 'dashboard' ? 'here show' : '' ?>">
    <a class="menu-link" href="/system-horario/TurnoFlow/public/dashboard">
        <span class="menu-icon"><i class="ki-outline ki-home-2 fs-2"></i></span>
        <span class="menu-title">Dashboard</span>
    </a>
</div>
```

**Menú separado por rol** (sidebar.php debe renderizar ítems distintos):
```php
<?php if ($_SESSION['rol'] === 'coordinador'): ?>
    <!-- ítems del coordinador: Dashboard Global, Aprobaciones, Reportes, Nómina, Config -->
<?php else: ?>
    <!-- ítems del supervisor: Dashboard, Importar, Horarios, Vista Diaria, Mis Reportes -->
    <!-- ítem bloqueado: -->
    <div class="menu-item disabled" title="Solo Coordinador">
        <span class="menu-link">
            <span class="menu-icon"><i class="ki-outline ki-lock fs-2 text-muted"></i></span>
            <span class="menu-title text-muted">Nómina de Asesores</span>
            <span class="menu-badge"><i class="ki-outline ki-lock fs-6 text-muted"></i></span>
        </span>
    </div>
<?php endif; ?>
```

---

## CONTEXTO DEL NEGOCIO

### El problema que resuelve TurnoFlow

Un call center gestiona múltiples **campañas**. El jefe de cada contrato entrega
un archivo Excel con el **dimensionamiento**: cuántos asesores se necesitan por
hora, para cada día del mes. El supervisor carga ese archivo, el sistema genera
el horario distribuyendo asesoras automáticamente, y el coordinador lo aprueba.

### Jerarquía de usuarios

| Rol | Acceso |
|-----|--------|
| **Coordinador** | Total. Aprueba horarios, gestiona nómina, configura horas del mes, ve TODAS las campañas, gestiona restricciones por asesor |
| **Supervisor** | Operativo. Importa CSV, genera horarios, registra asistencia, ve SOLO sus campañas |
| **Jefe de contrato** | NO tiene acceso al sistema. Solo entrega el Excel externamente |

---

## STACK TECNOLÓGICO

- **Backend**: PHP 8.2+ · Arquitectura MVC · Sin framework (vanilla PHP)
- **Frontend**: Metronic 8 (carpeta `dist/`) + JavaScript vanilla + Chart.js
- **Base de datos**: PostgreSQL 15+
- **Servidor**: XAMPP (Apache + PHP) en Windows
- **Dependencia PHP**: `composer require phpoffice/phpspreadsheet` (parsear Excel)

---

## ESQUEMA DE BASE DE DATOS (PostgreSQL)

Archivo: `sql/schema.sql` — ejecutar completo en PostgreSQL antes de empezar.

```sql
-- ROLES Y USUARIOS
CREATE TABLE roles (
    id          SERIAL PRIMARY KEY,
    nombre      VARCHAR(30) NOT NULL UNIQUE,
    descripcion TEXT
);

CREATE TABLE users (
    id             SERIAL PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL,
    apellido       VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    rol_id         INTEGER NOT NULL REFERENCES roles(id),
    activo         BOOLEAN DEFAULT TRUE,
    created_at     TIMESTAMPTZ DEFAULT NOW()
);

-- CAMPAÑAS
CREATE TABLE campaigns (
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

-- CONFIGURACIÓN MENSUAL DE HORAS (solo coordinador)
CREATE TABLE monthly_hours_config (
    id                  SERIAL PRIMARY KEY,
    anio                SMALLINT NOT NULL,
    mes                 SMALLINT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    horas_requeridas    SMALLINT NOT NULL,   -- 177 (31d), 170 (30d), 168 (feb)
    dias_del_mes        SMALLINT NOT NULL,
    configurado_por     INTEGER NOT NULL REFERENCES users(id),
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (anio, mes)
);

-- ASESORES — solo coordinador puede crear/eliminar
CREATE TABLE advisors (
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

-- RESTRICCIONES POR ASESOR — solo coordinador puede editar
CREATE TABLE advisor_constraints (
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

-- IMPORTACIONES DE DIMENSIONAMIENTO
CREATE TABLE staffing_imports (
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

-- DIMENSIONAMIENTO HORA A HORA
CREATE TABLE staffing_requirements (
    id                      BIGSERIAL PRIMARY KEY,
    import_id               INTEGER NOT NULL REFERENCES staffing_imports(id) ON DELETE CASCADE,
    campaign_id             INTEGER NOT NULL REFERENCES campaigns(id),
    fecha                   DATE NOT NULL,
    hora                    SMALLINT NOT NULL CHECK (hora BETWEEN 0 AND 23),
    asesores_requeridos     SMALLINT NOT NULL CHECK (asesores_requeridos >= 0),
    UNIQUE (campaign_id, fecha, hora)
);
CREATE INDEX idx_staffing_req_fecha ON staffing_requirements(campaign_id, fecha);

-- HORARIOS (cabecera del horario generado)
CREATE TYPE schedule_status AS ENUM ('borrador','enviado','aprobado','rechazado');

CREATE TABLE schedules (
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

-- ASIGNACIONES HORA A HORA (corazón del sistema)
CREATE TYPE shift_type AS ENUM ('normal','extra','nocturno','replanif');

CREATE TABLE shift_assignments (
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
CREATE INDEX idx_shift_fecha   ON shift_assignments(campaign_id, fecha);
CREATE INDEX idx_shift_advisor ON shift_assignments(advisor_id, fecha);

-- ASISTENCIA
CREATE TYPE attendance_status AS ENUM
    ('presente','ausente','tardanza','salida_anticipada','licencia_medica','maternidad');

CREATE TABLE attendance (
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

-- REPLANIFICACIONES
CREATE TABLE replanning_log (
    id                  SERIAL PRIMARY KEY,
    campaign_id         INTEGER NOT NULL REFERENCES campaigns(id),
    fecha               DATE NOT NULL,
    advisor_ausente_id  INTEGER NOT NULL REFERENCES advisors(id),
    motivo              attendance_status NOT NULL,
    nuevas_asignaciones BIGINT[],
    ejecutado_por       INTEGER REFERENCES users(id),
    created_at          TIMESTAMPTZ DEFAULT NOW()
);

-- RESUMEN MENSUAL
CREATE TABLE monthly_summary (
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

-- VISTAS ÚTILES
CREATE VIEW v_coverage_vs_required AS
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

CREATE VIEW v_advisor_night_eligibility AS
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

-- DATOS INICIALES
INSERT INTO roles (nombre, descripcion) VALUES
    ('coordinador', 'Acceso total: aprueba horarios, configura sistema, ve todas las campañas'),
    ('supervisor',  'Gestión operativa: importa dimensionamiento, genera y envía horarios');
```

---

## REGLAS DE NEGOCIO CRÍTICAS

### Formato real del Excel de dimensionamiento

El archivo tiene exactamente esta estructura (validado con archivo real del cliente):
```
Fila 1: "Horas ACD" | fecha1 | fecha2 | ... | fecha31 | (total)
Fila 2: (vacío)     | "Domingo" | "Lunes" | ... | "Martes" | (vacío)
Fila 3: 00:00       |  1  |  1  | ... → asesores requeridos a las 00h cada día
Fila 4: 01:00       |  1  |  1  | ...
...
Fila 26: 23:00      |  2  |  2  | ...
Fila 27: "TOTAL"    | 36  | 77  | ... → suma del día
```
Al importar con PhpSpreadsheet: leer desde fila 3, columna 1 = hora, columnas 2..32 = días 1..31.

### Motor de asignación (`app/Services/ScheduleEngine.php`)

```
PARA CADA fecha en el período:
  PARA CADA hora de 0 a 23:
    requeridos = staffing_requirements[campaña][fecha][hora]
    SI requeridos == 0: continuar

    SI campaña.tiene_velada = FALSE:
      SI hora < hora_inicio_operacion O hora > hora_fin_operacion: continuar

    elegibles = SELECT asesoras WHERE:
      1. campaign_id = campaña
      2. estado = 'activo'
      3. dia_semana(fecha) NO está en advisor_constraints.dias_descanso
      4. SI hora en rango nocturno (22-6): advisor_constraints.tiene_vpn = TRUE
      5. horas_asignadas_ese_dia < advisor_constraints.max_horas_dia
      6. SI permite_extras = FALSE: horas_asignadas_ese_dia < 8
      7. Sin restricción médica activa que bloquee esa hora
    ORDENAR POR: horas_acumuladas_en_mes ASC (distribuir equitativamente)

    TOMAR las primeras N asesoras (N = requeridos)
    PARA CADA asesora seleccionada:
      horas_hoy = contar shift_assignments de esa asesora ese día
      es_extra = (horas_hoy >= 8)
      tipo = 'nocturno' si hora en rango nocturno, sino 'normal' o 'extra'
      INSERT INTO shift_assignments(...)

    SI COUNT(elegibles) < requeridos:
      GUARDAR alerta en tabla o log (no lanzar excepción)
```

### Flujo de aprobación de horarios

```
borrador → (supervisor envía) → enviado → (coordinador aprueba) → aprobado
                                       → (coordinador rechaza) → rechazado → (supervisor corrige) → borrador
```
Las asesoras solo ven el horario cuando `status = 'aprobado'`.

### Restricciones de permisos por rol

**Solo Coordinador**:
- Crear / editar / eliminar asesores (`advisors`)
- Editar `advisor_constraints` (VPN, extras, descansos, restricciones médicas)
- Aprobar o rechazar horarios
- Ver reportes de TODAS las campañas
- Configurar `monthly_hours_config` (horas del mes)
- Crear / editar campañas
- Asignar supervisores a campañas

**Supervisor** (solo sus campañas asignadas):
- Importar CSV/Excel de dimensionamiento
- Generar horario (ejecutar motor)
- Enviar horario al coordinador para aprobación
- Registrar asistencia y ausencias
- Ejecutar replanificación por ausencia
- Ver reportes de SUS campañas únicamente

### Replanificación por ausencia

1. Supervisor registra ausencia en `attendance`
2. Sistema identifica qué horas cubre esa asesora ese día
3. Busca reemplazos: misma campaña + VPN si turno nocturno + no supera max_horas_dia
4. Prioriza asesoras con menos horas acumuladas
5. Crea nuevos `shift_assignments` con `tipo='replanif'`
6. Registra en `replanning_log`

---

## MÓDULOS A DESARROLLAR (en este orden)

### Fase 1 — Base
1. `config/database.php` — conexión PDO a PostgreSQL
2. `.env` / `.env.example` con variables DB
3. `public/index.php` — router simple (parse URL → Controller)
4. Login / Logout / Sesión con rol
5. Middleware de permisos (`app/Middleware/AuthMiddleware.php`)
6. Layout Metronic con sidebar diferenciado por rol

### Fase 2 — CRUD básico (Coordinador)
7. Campañas: listar, crear, editar (con toggles de velada, VPN, extras)
8. Asesores: listar, crear, editar + gestión de `advisor_constraints`
9. Configuración de horas mensuales (177/170/168h)

### Fase 3 — Importación y motor
10. `app/Services/ImportService.php` — parsear Excel con PhpSpreadsheet
11. Vista drag & drop de importación con previsualización heatmap
12. `app/Services/ScheduleEngine.php` — motor de asignación
13. Endpoint `POST /schedules/generate`
14. Vista matriz de horario (asesora × hora × día)

### Fase 4 — Flujo de aprobación
15. Enviar horario (`POST /schedules/{id}/submit`)
16. Aprobar / rechazar (`POST /schedules/{id}/approve|reject`)
17. Dashboard coordinador con cola de aprobaciones + badges de estado

### Fase 5 — Operación diaria
18. Registro de asistencia por día (vista timeline)
19. Motor de replanificación por ausencia
20. Vista diaria: cobertura hora a hora vs requerido

### Fase 6 — Reportes y cierre
21. Reporte por asesor: horas base + extras + nocturnas + adherencia
22. Reporte global por campaña (solo coordinador)
23. Cierre mensual: genera `monthly_summary`

---

## DATOS DE PRUEBA REALES (para seeding)

**Campaña Videollamada** (24/7, tiene_velada=TRUE, requiere_vpn_nocturno=TRUE):
- Pico L–V 10:00–17:00 = 5 asesoras simultáneas
- Madrugada 00:00–06:00 = 1 asesora (Mary cubre este turno)
- 8 asesoras en nómina:

| Nombre | VPN | Extras | Max/día | Descansa |
|--------|-----|--------|---------|----------|
| Diana Valero | ✓ | ✓ | 12h | Sáb |
| Ana León | ✓ | ✓ | 12h | Dom |
| Aracely Amagua | ✓ | ✓ | 10h | Sáb+Dom |
| Mary Rodríguez | ✓ | ✓ | 12h | — (7 días) |
| Génesis Casa | ✗ | ✓ | 10h | Dom |
| Andrea Quijije | ✓ | ✓ | 12h | Sáb |
| Vanessa García | ✗ | ✗ | 8h | Dom |
| Daniela Moreno | ✗ | ✓ | 10h | Lun |

**Ejemplo real de un día (Jueves 5 Marzo)**:
- Diana: 10:00–14:00 + 16:00–22:00 (10h, turno partido)
- Ana: 07:00–16:00 + 21:00–22:00 (10h, turno partido)
- Aracely: 09:00–19:00 (10h, continuo)
- Mary: 00:00–07:00 + 22:00–24:00 (9h, nocturno)
- Génesis: 10:00–12:00 + 15:00–18:00 + 19:00–24:00 (10h, 3 bloques)
- Andrea: 07:00–15:00 + 18:00–21:00 (11h, EXTRA)
- Vanessa: 09:00–18:00 (9h, continuo)
- Daniela: 13:00–21:00 (8h, continuo)

Nota: los **turnos partidos son válidos** y las **horas extras están permitidas**.
El sistema no impone turnos de 8h fijos.

---

## CONVENCIONES DE CÓDIGO

```php
// PDO — siempre prepared statements
$stmt = $pdo->prepare("SELECT * FROM advisors WHERE campaign_id = :cid AND estado = 'activo'");
$stmt->execute([':cid' => $campaignId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar rol en cada controller sensible
if ($_SESSION['user']['rol'] !== 'coordinador') {
    http_response_code(403);
    exit(json_encode(['error' => 'Sin permisos']));
}

// Respuestas JSON estándar para endpoints AJAX
header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $result]);

// Ruta base (usar constante para evitar hardcodeo)
define('BASE_URL', '/system-horario/TurnoFlow/public');
define('BASE_PATH', 'C:/xampp/htdocs/system-horario/TurnoFlow');
```

```javascript
// Helper para llamadas AJAX al backend
async function api(endpoint, method = 'GET', body = null) {
    const res = await fetch(BASE_URL + '/api/' + endpoint, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : null
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}
// BASE_URL se pasa desde PHP a JS en el layout:
// <script>const BASE_URL = '<?= BASE_URL ?>';</script>
```

---

## CONFIGURACIÓN `.env.example`

```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=turnoflow
DB_USER=postgres
DB_PASS=

APP_ENV=local
APP_URL=http://localhost/system-horario/TurnoFlow/public
APP_SECRET=cambia_esto_por_un_string_aleatorio_largo

UPLOAD_MAX_MB=10
UPLOAD_PATH=C:/xampp/htdocs/system-horario/TurnoFlow/uploads
```

---

## CHECKLIST ANTES DE EMPEZAR

Antes de escribir código, verifica y respóndeme:

- [ ] ¿Existe ya algún archivo PHP en `app/` o `public/`?
- [ ] ¿Hay algo en `docs/` que deba leer?
- [ ] ¿Está Composer instalado? (`composer --version`)
- [ ] ¿PostgreSQL está corriendo localmente?
- [ ] ¿Existe ya la base de datos `turnoflow` o debo crearla?
- [ ] ¿El archivo `.env` ya existe con credenciales reales?

Cuando tengas las respuestas, empieza por la **Fase 1** completa.
Commitea al final de cada módulo con mensaje descriptivo en español.
