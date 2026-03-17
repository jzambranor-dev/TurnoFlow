<?php
/**
 * TurnoFlow - Changelog / Historial de Cambios
 */

$extraStyles = [];
$extraScripts = [];

$extraStyles[] = <<<'STYLE'
<style>
.cl-container { max-width: 900px; margin: 0 auto; }

.cl-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 32px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 20px;
}
.cl-header-icon {
    width: 56px; height: 56px;
    background: rgba(255,255,255,.12);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.cl-header-icon svg { width: 28px; height: 28px; fill: #38bdf8; }
.cl-header h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 4px; }
.cl-header p { margin: 0; font-size: .875rem; color: rgba(255,255,255,.65); }

/* Timeline */
.cl-timeline { position: relative; padding-left: 32px; }
.cl-timeline::before {
    content: '';
    position: absolute;
    left: 11px; top: 0; bottom: 0;
    width: 2px;
    background: #e2e8f0;
    border-radius: 1px;
}

.cl-version {
    position: relative;
    margin-bottom: 36px;
}
.cl-version:last-child { margin-bottom: 0; }

.cl-dot {
    position: absolute;
    left: -32px; top: 4px;
    width: 24px; height: 24px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    z-index: 1;
}
.cl-dot svg { width: 14px; height: 14px; fill: #fff; }
.cl-dot.major { background: #2563eb; }
.cl-dot.minor { background: #059669; }
.cl-dot.fix   { background: #f59e0b; }
.cl-dot.init  { background: #6366f1; }

.cl-version-header {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 12px;
}
.cl-version-tag {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
}
.cl-version-date {
    font-size: .8rem;
    color: #94a3b8;
    font-weight: 500;
}

.cl-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}

.cl-card h3 {
    font-size: .85rem;
    font-weight: 600;
    color: #475569;
    margin: 0 0 10px;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.cl-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.cl-list li {
    padding: 6px 0;
    font-size: .875rem;
    color: #334155;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    line-height: 1.5;
}
.cl-list li::before {
    content: '';
    width: 6px; height: 6px;
    border-radius: 50%;
    margin-top: 7px;
    flex-shrink: 0;
}
.cl-list.feat li::before { background: #2563eb; }
.cl-list.fix li::before  { background: #f59e0b; }
.cl-list.imp li::before  { background: #059669; }
.cl-list.sec li::before  { background: #dc2626; }

.cl-section + .cl-section { margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9; }

.cl-tag {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .3px;
}
.cl-tag.new    { background: #dbeafe; color: #1e40af; }
.cl-tag.fix    { background: #fef3c7; color: #92400e; }
.cl-tag.imp    { background: #d1fae5; color: #065f46; }
.cl-tag.break  { background: #fee2e2; color: #991b1b; }

.cl-commit {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: .7rem;
    color: #94a3b8;
    margin-left: auto;
    flex-shrink: 0;
}
</style>
STYLE;

ob_start();
?>

<div class="cl-container">

    <div class="cl-header">
        <div class="cl-header-icon">
            <svg viewBox="0 0 24 24"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
        </div>
        <div>
            <h1>Changelog</h1>
            <p>Historial de cambios, nuevas funcionalidades y correcciones del sistema TurnoFlow</p>
        </div>
    </div>

    <div class="cl-timeline">

        <!-- v1.7.0 -->
        <div class="cl-version">
            <div class="cl-dot major"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></div>
            <div class="cl-version-header">
                <span class="cl-version-tag">v1.7.0</span>
                <span class="cl-version-date">16 Marzo 2026</span>
            </div>
            <div class="cl-card">
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Proteccion CSRF</h3>
                    <ul class="cl-list feat">
                        <li>Nuevo servicio <code>CsrfService</code> con generacion y validacion de tokens por sesion</li>
                        <li>Token CSRF agregado automaticamente a todos los formularios POST del sistema (25 formularios en 18 vistas)</li>
                        <li>Soporte para APIs AJAX via header <code>X-CSRF-TOKEN</code> (editor de horarios, tracking, check-in)</li>
                        <li>Middleware de validacion CSRF en el router para todas las peticiones POST</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag imp">Mejora</span> Endurecimiento de Seguridad</h3>
                    <ul class="cl-list imp">
                        <li>Errores PHP ya no se muestran al usuario — se registran en log del servidor</li>
                        <li>Regeneracion de ID de sesion tras login exitoso (prevencion de session fixation)</li>
                        <li>Cookies de sesion con <code>httponly</code>, <code>samesite=Lax</code> y <code>strict_mode</code></li>
                        <li>Credenciales de prueba eliminadas de la pagina de login</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> API REST para Reportes</h3>
                    <ul class="cl-list feat">
                        <li>4 endpoints JSON: campanas, horas por campana, reporte unificado y asistencia</li>
                        <li>Autenticacion segura via Bearer token (<code>Authorization: Bearer tf_xxx</code>)</li>
                        <li>Gestion de tokens desde Configuracion: crear, revocar, permisos granulares y expiracion</li>
                        <li>Rate limiting (60 req/min por token) y logging de uso</li>
                        <li>Error handling global con respuestas JSON consistentes (401, 403, 404, 429, 500)</li>
                        <li>Ideal para integraciones con PowerBI, Excel, sistemas externos</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag imp">Mejora</span> Migracion de Rutas GET a POST</h3>
                    <ul class="cl-list imp">
                        <li>Enviar, aprobar y rechazar horarios ahora usan formularios POST con CSRF (antes eran enlaces GET)</li>
                        <li>Toggle de estado de usuario migrado de GET a POST con CSRF</li>
                        <li>Previene ejecucion accidental por crawlers, precarga de navegador o enlaces maliciosos</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag fix">Fix</span> Correcciones</h3>
                    <ul class="cl-list fix">
                        <li>Horas mensuales requeridas ahora se leen de la configuracion en BD en vez de valores hardcodeados</li>
                        <li>Exportaciones Excel envueltas en try-catch con mensaje de error amigable al usuario</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- v1.6.0 -->
        <div class="cl-version">
            <div class="cl-dot major"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></div>
            <div class="cl-version-header">
                <span class="cl-version-tag">v1.6.0</span>
                <span class="cl-version-date">16 Marzo 2026</span>
            </div>
            <div class="cl-card">
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Exportacion Excel con Formato Asistencia</h3>
                    <ul class="cl-list feat">
                        <li>Exportacion de reporte de horas por campana en formato Excel (Asistencia)</li>
                        <li>Formato completo: nombre, cedula, campana, horario diario, horas trabajadas, nomenclatura y columnas de resumen</li>
                        <li>Mapeo automatico de ID de horario desde catalogo HORARIOS.xlsx — si no hay match se muestra "ROTATIVO"</li>
                        <li>Hojas adicionales: <code>id_horario</code> (catalogo completo) y <code>Nomenclatura</code> (codigos A, AT, FI, etc.)</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Reporte Unificado (Todas las Campanas)</h3>
                    <ul class="cl-list feat">
                        <li>Boton "Exportar Unificado" en la pagina de reportes (admin, gerente, coordinador)</li>
                        <li>Genera un solo Excel con todos los asesores de todas las campanas activas</li>
                        <li>Selector de periodo (mes/anio) antes de exportar</li>
                        <li>Columna CAMPANA por dia muestra la campana real donde trabajo cada asesor (util para compartidos)</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Pagina de Configuracion</h3>
                    <ul class="cl-list feat">
                        <li>Gestion de horas mensuales requeridas por anio/mes</li>
                        <li>Administracion de dias festivos con nombre y fecha</li>
                        <li>Configuracion de parametros del sistema (hora inicio/fin nocturno, break minimo)</li>
                        <li>Solo accesible para admin, gerente y coordinador</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag imp">Mejora</span> Mejoras</h3>
                    <ul class="cl-list imp">
                        <li>Boton de exportar Excel en la vista de reporte de horas por campana</li>
                        <li>Ruta <code>/reports/export-unified</code> para descarga directa del consolidado</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- v1.5.0 -->
        <div class="cl-version">
            <div class="cl-dot major"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></div>
            <div class="cl-version-header">
                <span class="cl-version-tag">v1.5.0</span>
                <span class="cl-version-date">13 Marzo 2026</span>
            </div>
            <div class="cl-card">
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Check-in de Asesores y Bypass por Rol</h3>
                    <ul class="cl-list feat">
                        <li>Auto check-in para asesores desde su vista "Mi Horario"</li>
                        <li>Panel de check-in en tiempo real en la vista de seguimiento (tracking)</li>
                        <li>Bypass de check-in para admin, gerente y coordinador — pueden confirmar asistencia sin esperar check-in</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Actividades en el Editor de Horarios</h3>
                    <ul class="cl-list feat">
                        <li>Asignacion de actividades (ej: Kiosco) directamente desde el editor drag-to-paint</li>
                        <li>Visualizacion de actividad con color y abreviatura en cada celda</li>
                        <li>Botones dinamicos de actividades segun la campana</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Deteccion de Conflictos Cross-Campana</h3>
                    <ul class="cl-list feat">
                        <li>Alerta en tiempo real al intentar asignar un asesor compartido a la misma hora en dos campanas</li>
                        <li>Mapa de horas cross-campana construido automaticamente desde asignaciones existentes</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag imp">Mejora</span> Sistema de Notificaciones</h3>
                    <ul class="cl-list imp">
                        <li>Reemplazo completo de <code>alert()</code> nativos por toasts animados (success, error, warning, info)</li>
                        <li>Loading overlay con spinner y backdrop blur al guardar cambios</li>
                        <li>Implementado en show.php, tracking.php y my-schedule.php</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag fix">Fix</span> Correcciones</h3>
                    <ul class="cl-list fix">
                        <li>Dashboard de asesor ahora muestra dias trabajados y horas correctamente (resolucion por nombre)</li>
                        <li>Error <code>BASE_URL is not defined</code> en my-schedule corregido (heredoc vs nowdoc)</li>
                        <li>Error de sintaxis JS en tracking.php por escape incorrecto de comillas en heredoc</li>
                        <li>Auditoria completa del proyecto con 15 hallazgos documentados</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- v1.4.0 -->
        <div class="cl-version">
            <div class="cl-dot major"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></div>
            <div class="cl-version-header">
                <span class="cl-version-tag">v1.4.0</span>
                <span class="cl-version-date">12 Marzo 2026</span>
            </div>
            <div class="cl-card">
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Reportes de Horas</h3>
                    <ul class="cl-list feat">
                        <li>Reporte detallado de horas por campana con desglose por asesor</li>
                        <li>Calculo de horas programadas, trabajadas, deficit y porcentaje de cumplimiento</li>
                        <li>Vista con filtros por mes/anio</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Asesores Compartidos</h3>
                    <ul class="cl-list feat">
                        <li>Modulo para prestar asesores entre campanas (source → target)</li>
                        <li>Gestion completa: crear, listar y activar/desactivar prestamos</li>
                        <li>Integracion con el editor de horarios para visualizar asesores prestados</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag fix">Fix</span> Correcciones</h3>
                    <ul class="cl-list fix">
                        <li>Fix deficit de horas en ScheduleBuilder — calculo correcto de horas meta</li>
                        <li>Limpieza general de codigo y vistas</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- v1.3.0 -->
        <div class="cl-version">
            <div class="cl-dot major"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></div>
            <div class="cl-version-header">
                <span class="cl-version-tag">v1.3.0</span>
                <span class="cl-version-date">09 Marzo 2026</span>
            </div>
            <div class="cl-card">
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Motor de Asignacion ScheduleBuilder</h3>
                    <ul class="cl-list feat">
                        <li>Nuevo motor de generacion automatica de horarios con algoritmo mejorado</li>
                        <li>Respeta restricciones por asesor: VPN, medicas, horarios de contrato, dias de descanso</li>
                        <li>Cobertura inteligente basada en dimensionamiento importado</li>
                        <li>Soporte para turnos nocturnos (velada) con validacion de elegibilidad</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- v1.2.0 -->
        <div class="cl-version">
            <div class="cl-dot minor"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></div>
            <div class="cl-version-header">
                <span class="cl-version-tag">v1.2.0</span>
                <span class="cl-version-date">06 Marzo 2026</span>
            </div>
            <div class="cl-card">
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Roles, Permisos y Mejoras</h3>
                    <ul class="cl-list feat">
                        <li>Sistema completo de roles y permisos con <code>AuthService</code></li>
                        <li>5 roles: admin, gerente, coordinador, supervisor, asesor</li>
                        <li>Gestion de permisos por modulo desde la interfaz</li>
                        <li>Vistas de CRUD para roles con asignacion de permisos</li>
                    </ul>
                </div>
                <div class="cl-section">
                    <h3><span class="cl-tag fix">Fix</span> Correcciones</h3>
                    <ul class="cl-list fix">
                        <li>Correcciones en importacion de dimensionamiento</li>
                        <li>Fix en campanas y gestion de usuarios</li>
                        <li>Mejoras en el frontend general</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- v1.1.0 -->
        <div class="cl-version">
            <div class="cl-dot minor"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></div>
            <div class="cl-version-header">
                <span class="cl-version-tag">v1.1.0</span>
                <span class="cl-version-date">05 Marzo 2026</span>
            </div>
            <div class="cl-card">
                <div class="cl-section">
                    <h3><span class="cl-tag new">Nuevo</span> Sistema Base Completo</h3>
                    <ul class="cl-list feat">
                        <li>Arquitectura MVC completa con router en <code>public/index.php</code></li>
                        <li>Modulos: Dashboard, Campanas, Asesores, Horarios, Usuarios</li>
                        <li>Importacion de dimensionamiento desde Excel</li>
                        <li>Vista de horario con modos diario, edicion y mensual</li>
                        <li>Editor drag-to-paint para asignaciones hora a hora</li>
                        <li>Seguimiento diario (tracking) con registro de asistencia</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- v1.0.0 -->
        <div class="cl-version">
            <div class="cl-dot init"><svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div>
            <div class="cl-version-header">
                <span class="cl-version-tag">v1.0.0</span>
                <span class="cl-version-date">05 Marzo 2026</span>
            </div>
            <div class="cl-card">
                <div class="cl-section">
                    <h3><span class="cl-tag new">Inicio</span> Primer Commit</h3>
                    <ul class="cl-list feat">
                        <li>Inicializacion del proyecto TurnoFlow</li>
                        <li>Stack: PHP 8.2, PostgreSQL 15+, Metronic 8</li>
                        <li>Schema de base de datos con 14 tablas principales</li>
                        <li>Configuracion de XAMPP y estructura de directorios</li>
                    </ul>
                </div>
            </div>
        </div>

    </div><!-- .cl-timeline -->
</div><!-- .cl-container -->

<?php
$content = ob_get_clean();
$pageTitle = 'Changelog';
$currentPage = 'changelog';
include APP_PATH . '/Views/layouts/main.php';
?>
