<?php
/**
 * TurnoFlow - Auditoria de Cambios
 * Lista paginada con filtros y modal de detalle
 */

$pageTitle = 'Auditoria';
$currentPage = 'audit';

$accionLabels = [
    'schedule.submit' => 'Horario enviado',
    'schedule.approve' => 'Horario aprobado',
    'schedule.reject' => 'Horario rechazado',
    'schedule.edit_assignments' => 'Edicion de turnos',
    'advisor.create' => 'Asesor creado',
    'advisor.update' => 'Asesor actualizado',
    'attendance.update' => 'Asistencia registrada',
];

$entidadLabels = [
    'schedules' => 'Horarios',
    'advisors' => 'Asesores',
    'shift_assignments' => 'Turnos',
    'attendance' => 'Asistencia',
];

ob_start();
?>

<div class="audit-page">
    <div class="form-breadcrumb" style="margin-bottom:12px;">
        <a href="<?= BASE_URL ?>/dashboard" style="color:#2563eb;text-decoration:none;font-weight:500;">Dashboard</a>
        <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:#94a3b8;"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
        <span>Auditoria</span>
    </div>

    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="header-title">Auditoria de Cambios</h1>
                <p class="header-subtitle"><?= $total ?> registro(s) encontrados</p>
            </div>
            <div class="header-actions">
                <a href="<?= BASE_URL ?>/audit-log/export?<?= http_build_query(array_filter([
                    'user_id' => $filterUserId,
                    'entidad' => $filterEntidad,
                    'fecha_desde' => $filterFechaDesde,
                    'fecha_hasta' => $filterFechaHasta,
                ])) ?>" class="btn-action btn-secondary">
                    <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                    Exportar CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-bar">
        <form method="get" action="<?= BASE_URL ?>/audit-log" class="filters-form">
            <div class="filter-group">
                <label>Usuario</label>
                <select name="user_id">
                    <option value="">Todos</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filterUserId === (int)$u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(trim($u['nombre'] . ' ' . $u['apellido'])) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Entidad</label>
                <select name="entidad">
                    <option value="">Todas</option>
                    <?php foreach ($entities as $ent): ?>
                    <option value="<?= htmlspecialchars($ent) ?>" <?= $filterEntidad === $ent ? 'selected' : '' ?>>
                        <?= htmlspecialchars($entidadLabels[$ent] ?? $ent) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Desde</label>
                <input type="date" name="fecha_desde" value="<?= htmlspecialchars($filterFechaDesde ?? '') ?>">
            </div>
            <div class="filter-group">
                <label>Hasta</label>
                <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filterFechaHasta ?? '') ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter">Filtrar</button>
                <a href="<?= BASE_URL ?>/audit-log" class="btn-filter-clear">Limpiar</a>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="panel">
        <div class="table-wrap">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Accion</th>
                        <th>Entidad</th>
                        <th>ID</th>
                        <th>IP</th>
                        <th style="width:80px;">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditItems)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:32px;color:#94a3b8;">No se encontraron registros de auditoria.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($auditItems as $item): ?>
                    <tr>
                        <td class="col-date"><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></td>
                        <td><?= htmlspecialchars(trim(($item['user_nombre'] ?? '') . ' ' . ($item['user_apellido'] ?? ''))) ?: '<em>Sistema</em>' ?></td>
                        <td>
                            <span class="accion-badge"><?= htmlspecialchars($accionLabels[$item['accion']] ?? $item['accion']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($entidadLabels[$item['entidad'] ?? ''] ?? ($item['entidad'] ?? '-')) ?></td>
                        <td class="col-id"><?= (int)($item['entidad_id'] ?? 0) ?></td>
                        <td class="col-ip"><?= htmlspecialchars($item['ip'] ?? '-') ?></td>
                        <td>
                            <?php if (!empty($item['datos_antes']) || !empty($item['datos_despues'])): ?>
                            <button type="button" class="btn-detail" onclick='showAuditDetail(<?= json_encode([
                                "antes" => $item["datos_antes"] ? json_decode($item["datos_antes"], true) : null,
                                "despues" => $item["datos_despues"] ? json_decode($item["datos_despues"], true) : null,
                                "accion" => $item["accion"],
                                "fecha" => date("d/m/Y H:i", strtotime($item["created_at"])),
                            ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                Ver
                            </button>
                            <?php else: ?>
                            <span style="color:#cbd5e1;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="<?= BASE_URL ?>/audit-log?<?= http_build_query(array_merge(array_filter([
                'user_id' => $filterUserId,
                'entidad' => $filterEntidad,
                'fecha_desde' => $filterFechaDesde,
                'fecha_hasta' => $filterFechaHasta,
            ]), ['page' => $page - 1])) ?>" class="page-link">&laquo; Anterior</a>
            <?php endif; ?>

            <span class="page-info">Pagina <?= $page ?> de <?= $totalPages ?></span>

            <?php if ($page < $totalPages): ?>
            <a href="<?= BASE_URL ?>/audit-log?<?= http_build_query(array_merge(array_filter([
                'user_id' => $filterUserId,
                'entidad' => $filterEntidad,
                'fecha_desde' => $filterFechaDesde,
                'fecha_hasta' => $filterFechaHasta,
            ]), ['page' => $page + 1])) ?>" class="page-link">Siguiente &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal de detalle -->
    <div class="audit-modal-overlay" id="auditModal" style="display:none;" onclick="if(event.target===this)closeAuditModal()">
        <div class="audit-modal">
            <div class="modal-header">
                <h3 id="modalTitle">Detalle de Auditoria</h3>
                <button type="button" onclick="closeAuditModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="diff-container">
                    <div class="diff-panel">
                        <h4>Datos Antes</h4>
                        <pre id="modalAntes" class="json-view">—</pre>
                    </div>
                    <div class="diff-panel">
                        <h4>Datos Despues</h4>
                        <pre id="modalDespues" class="json-view">—</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
.audit-page { max-width: 1400px; margin: 0 auto; padding: 20px; }
.page-header { margin-bottom: 20px; }
.header-content { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.header-title { font-size: 22px; font-weight: 700; color: #1e293b; margin: 0; }
.header-subtitle { font-size: 13px; color: #64748b; margin: 4px 0 0; }
.btn-action { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
.btn-secondary { background: #f1f5f9; color: #475569; }
.btn-secondary:hover { background: #e2e8f0; }

.filters-bar { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; }
.filters-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.filter-group select, .filter-group input { padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; min-width: 140px; }
.filter-actions { display: flex; gap: 8px; align-items: flex-end; }
.btn-filter { padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-filter:hover { background: #1d4ed8; }
.btn-filter-clear { padding: 8px 12px; color: #64748b; font-size: 13px; text-decoration: none; }
.btn-filter-clear:hover { color: #1e293b; }

.panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
.table-wrap { overflow-x: auto; }
.audit-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.audit-table th { background: #f8fafc; padding: 10px 14px; text-align: left; font-weight: 600; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
.audit-table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; }
.audit-table tbody tr:hover { background: #f8fafc; }
.col-date { white-space: nowrap; font-size: 12px; color: #64748b; }
.col-id { font-family: monospace; font-size: 12px; }
.col-ip { font-family: monospace; font-size: 11px; color: #94a3b8; }
.accion-badge { display: inline-block; padding: 3px 8px; background: #eff6ff; color: #2563eb; border-radius: 4px; font-size: 12px; font-weight: 500; }

.btn-detail { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 12px; cursor: pointer; }
.btn-detail:hover { background: #e2e8f0; }

.pagination { display: flex; justify-content: center; align-items: center; gap: 16px; padding: 16px; border-top: 1px solid #e2e8f0; }
.page-link { color: #2563eb; text-decoration: none; font-size: 13px; font-weight: 500; }
.page-link:hover { text-decoration: underline; }
.page-info { font-size: 13px; color: #64748b; }

/* Modal */
.audit-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
.audit-modal { background: #fff; border-radius: 12px; width: 90%; max-width: 900px; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; }
.modal-header h3 { margin: 0; font-size: 16px; color: #1e293b; }
.modal-close { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; padding: 0 4px; }
.modal-close:hover { color: #1e293b; }
.modal-body { padding: 20px; overflow-y: auto; }
.diff-container { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.diff-panel h4 { font-size: 12px; text-transform: uppercase; color: #64748b; margin: 0 0 8px; letter-spacing: 0.5px; }
.json-view { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; font-size: 12px; font-family: 'JetBrains Mono', monospace; white-space: pre-wrap; word-break: break-word; max-height: 400px; overflow-y: auto; margin: 0; color: #334155; }

@media (max-width: 768px) {
    .filters-form { flex-direction: column; }
    .diff-container { grid-template-columns: 1fr; }
    .header-content { flex-direction: column; }
}
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
function showAuditDetail(data) {
    document.getElementById('modalTitle').textContent = (data.accion || 'Detalle') + ' — ' + (data.fecha || '');
    document.getElementById('modalAntes').textContent = data.antes ? JSON.stringify(data.antes, null, 2) : '(sin datos)';
    document.getElementById('modalDespues').textContent = data.despues ? JSON.stringify(data.despues, null, 2) : '(sin datos)';
    document.getElementById('auditModal').style.display = 'flex';
}

function closeAuditModal() {
    document.getElementById('auditModal').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAuditModal();
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
