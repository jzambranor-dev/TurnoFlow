# TurnoFlow (SGH) — Contexto del Proyecto

## Descripción
Sistema de gestión de horarios para call centers. Permite crear, aprobar y controlar
turnos de agentes, incluyendo turnos partidos, horas extra y restricciones operativas.

---

## Stack Tecnológico

- **Backend:** PHP 8.2 (sin framework, arquitectura MVC propia)
- **Base de datos:** PostgreSQL
- **Frontend:** Metronic 8 (Bootstrap 5 + jQuery)
- **Entorno local:** XAMPP — `C:\xampp\htdocs\system-horario\TurnoFlow`
- **Control de versiones:** GitHub

---

## Estructura de Directorios

```
TurnoFlow/
├── app/
│   ├── controllers/
│   ├── models/
│   └── views/
├── config/
│   └── database.php
├── public/
│   └── assets/         ← Metronic (CSS, JS, imágenes)
├── sql/
│   └── schema.sql      ← Schema PostgreSQL oficial
└── index.php
```

---

## Base de Datos

**13 tablas principales, 3 vistas, 3 ENUMs.**

Tablas clave:
- `campaigns` — Campañas del call center (ej: "Videollamada")
- `agents` — Agentes y sus datos
- `shifts` — Definición de tipos de turno
- `schedules` — Asignación de turno a agente por día
- `overtime_requests` — Solicitudes de horas extra
- `approvals` — Flujo de aprobación

Convenciones de BD:
- Nombres en **snake_case**
- PKs: `id SERIAL PRIMARY KEY`
- Timestamps: `created_at`, `updated_at` con `DEFAULT NOW()`
- Claves foráneas siempre con `ON DELETE RESTRICT` salvo excepción documentada

---

## Convenciones de Código PHP

- **Variables y métodos:** camelCase
- **Clases:** PascalCase
- **Siempre** usar prepared statements con PDO — nunca concatenar queries
- Validar y sanear toda entrada del usuario antes de procesar
- Manejar errores con bloques `try/catch` y loguear en archivo, nunca mostrar stack trace al usuario
- Separar lógica de negocio del controlador — usar modelos para queries

---

## Reglas de Negocio Críticas

1. **Turnos partidos:** Un agente puede tener hasta 2 bloques de trabajo en el mismo día.
   Validar que no se solapen y que el descanso entre bloques sea ≥ 30 min.

2. **Horas extra:** Requieren solicitud previa aprobada por supervisor.
   No registrar horas extra sin entrada en `overtime_requests` con estado `approved`.

3. **Restricción VPN en turnos nocturnos:** Los agentes en turno nocturno (22:00–06:00)
   deben trabajar bajo conexión VPN. Registrar flag `vpn_required = true` en el schedule.

4. **Flujo de aprobación:** Todo cambio en schedule pasa por estado `pending → approved/rejected`.
   Solo usuarios con rol `supervisor` o `admin` pueden aprobar.

5. **Campaña "Videollamada":** Campaña de referencia para datos reales. Usar sus reglas
   como base para validaciones operativas.

---

## Roles de Usuario

| Rol | Permisos |
|-----|----------|
| `admin` | Acceso total |
| `supervisor` | Aprobar turnos, ver reportes de su campaña |
| `agent` | Ver su propio horario, solicitar cambios |

---

## Lo que NO debes hacer

- No usar `mysqli_*` — solo PDO
- No mezclar lógica SQL en las vistas
- No aprobar horas extra directamente en BD sin pasar por el flujo de `approvals`
- No borrar registros de schedules — usar soft delete (`deleted_at`)
- No hardcodear credenciales — leer siempre desde `config/database.php`

---

## Comandos útiles

```bash
# Conectar a la BD local
psql -U postgres -d turnoflow

# Ver migraciones pendientes
ls sql/migrations/

# Iniciar servidor local (XAMPP)
# Acceder en: http://localhost/system-horario/TurnoFlow
```

---

## Contexto Adicional

- El sistema reemplaza control de turnos manual en Excel
- Los datos reales de la campaña "Videollamada" fueron analizados para definir las reglas
- Metronic 8 ya está configurado — mantener consistencia visual con los componentes existentes
- El repositorio está en GitHub; hacer commits descriptivos por feature
