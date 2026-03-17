<?php
/**
 * TurnoFlow - Vista de Configuracion
 */

$monthNamesConfig = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

ob_start();
?>

<div class="page-container">
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Configuracion</h1>
            <p class="page-header-subtitle">Parametros del sistema, horas mensuales y dias festivos</p>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="flash-msg flash-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="flash-msg flash-error"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); endif; ?>

    <div class="settings-grid">

        <!-- ===================== HORAS MENSUALES ===================== -->
        <div class="data-panel settings-panel">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                    Horas Mensuales Requeridas
                </div>
            </div>

            <div class="panel-body">
                <form method="POST" action="<?= BASE_URL ?>/settings/monthly-hours" class="settings-form-inline">
                    <?= \App\Services\CsrfService::field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Anio</label>
                            <select name="anio" required>
                                <?php for ($y = 2025; $y <= 2030; $y++): ?>
                                <option value="<?= $y ?>" <?= $y == (int)date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Mes</label>
                            <select name="mes" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m == (int)date('n') ? 'selected' : '' ?>><?= $monthNamesConfig[$m] ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Horas requeridas</label>
                            <input type="number" name="horas_requeridas" min="100" max="250" value="170" required>
                        </div>
                        <div class="form-group">
                            <label>Dias del mes</label>
                            <input type="number" name="dias_del_mes" min="28" max="31" value="30" required>
                        </div>
                        <div class="form-group form-group-btn">
                            <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($monthlyHours)): ?>
                <table class="data-table settings-table">
                    <thead>
                        <tr>
                            <th>Periodo</th>
                            <th>Horas</th>
                            <th>Dias</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyHours as $mh): ?>
                        <tr>
                            <td><?= $monthNamesConfig[(int)$mh['mes']] ?> <?= $mh['anio'] ?></td>
                            <td><strong><?= $mh['horas_requeridas'] ?>h</strong></td>
                            <td><?= $mh['dias_del_mes'] ?>d</td>
                            <td class="actions-cell">
                                <form method="POST" action="<?= BASE_URL ?>/settings/monthly-hours/delete" style="display:inline;">
                                    <?= \App\Services\CsrfService::field() ?>
                                    <input type="hidden" name="id" value="<?= $mh['id'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Eliminar" onclick="return confirm('Eliminar este registro?')">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="empty-hint">No hay configuraciones de horas mensuales. Se usaran valores por defecto (170h/30d).</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===================== DIAS FESTIVOS ===================== -->
        <div class="data-panel settings-panel">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
                    Dias Festivos
                </div>
            </div>

            <div class="panel-body">
                <form method="POST" action="<?= BASE_URL ?>/settings/holidays" class="settings-form-inline">
                    <?= \App\Services\CsrfService::field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha</label>
                            <input type="date" name="fecha" required>
                        </div>
                        <div class="form-group" style="flex: 2;">
                            <label>Nombre del feriado</label>
                            <input type="text" name="nombre" placeholder="Ej: Dia del Trabajo" required>
                        </div>
                        <div class="form-group form-group-btn">
                            <button type="submit" class="btn btn-primary btn-sm">Agregar</button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($holidays)): ?>
                <table class="data-table settings-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Nombre</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($holidays as $h): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($h['fecha'])) ?></td>
                            <td><?= htmlspecialchars($h['nombre']) ?></td>
                            <td class="actions-cell">
                                <form method="POST" action="<?= BASE_URL ?>/settings/holidays/delete" style="display:inline;">
                                    <?= \App\Services\CsrfService::field() ?>
                                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Eliminar" onclick="return confirm('Eliminar este feriado?')">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="empty-hint">No hay dias festivos configurados.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===================== PARAMETROS DEL SISTEMA ===================== -->
        <div class="data-panel settings-panel settings-panel-full">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                    Parametros del Sistema
                </div>
            </div>

            <div class="panel-body">
                <form method="POST" action="<?= BASE_URL ?>/settings/params">
                    <?= \App\Services\CsrfService::field() ?>
                    <div class="params-grid">
                        <?php foreach ($systemParams as $sp): ?>
                        <div class="param-item">
                            <label><?= htmlspecialchars($sp['descripcion'] ?: $sp['clave']) ?></label>
                            <div class="param-input-row">
                                <input type="text" name="params[<?= htmlspecialchars($sp['clave']) ?>]" value="<?= htmlspecialchars($sp['valor']) ?>">
                                <span class="param-key"><?= htmlspecialchars($sp['clave']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="param-actions">
                        <button type="submit" class="btn btn-primary btn-sm">Guardar Parametros</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===================== API TOKENS ===================== -->
        <div class="data-panel settings-panel settings-panel-full">
            <div class="panel-header">
                <div class="panel-title">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.65 10C11.83 7.67 9.61 6 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6c2.61 0 4.83-1.67 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
                    Tokens de API
                </div>
            </div>

            <div class="panel-body">
                <?php if (!empty($_SESSION['flash_new_token'])): ?>
                <div class="token-reveal">
                    <p><strong>Tu nuevo token (copialo ahora, no se mostrara de nuevo):</strong></p>
                    <div class="token-value" id="newTokenValue"><?= htmlspecialchars($_SESSION['flash_new_token']) ?></div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('newTokenValue').textContent).then(()=>this.textContent='Copiado!')">Copiar</button>
                </div>
                <?php unset($_SESSION['flash_new_token']); endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/settings/api-tokens" class="settings-form-inline">
                    <?= \App\Services\CsrfService::field() ?>
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label>Nombre del token</label>
                            <input type="text" name="token_nombre" placeholder="Ej: Integracion PowerBI" required>
                        </div>
                        <div class="form-group">
                            <label>Permisos</label>
                            <select name="token_permisos[]" multiple>
                                <option value="reports.view" selected>Ver reportes</option>
                                <option value="reports.export">Exportar reportes</option>
                                <option value="*">Todos</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Expira en (dias, 0=nunca)</label>
                            <input type="number" name="token_expira_dias" min="0" max="365" value="90">
                        </div>
                        <div class="form-group form-group-btn">
                            <button type="submit" class="btn btn-primary btn-sm">Crear Token</button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($apiTokens)): ?>
                <table class="data-table settings-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Prefijo</th>
                            <th>Usuario</th>
                            <th>Permisos</th>
                            <th>Ultimo uso</th>
                            <th>Expira</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiTokens as $tk): ?>
                        <tr<?= !$tk['activo'] ? ' style="opacity:0.5;"' : '' ?>>
                            <td><strong><?= htmlspecialchars($tk['nombre']) ?></strong></td>
                            <td><code><?= htmlspecialchars($tk['token_prefix']) ?>...</code></td>
                            <td><?= htmlspecialchars($tk['usuario']) ?></td>
                            <td><code><?= htmlspecialchars(trim($tk['permisos'], '{}')) ?></code></td>
                            <td><?= $tk['ultimo_uso'] ? date('d/m/Y H:i', strtotime($tk['ultimo_uso'])) : '<span style="color:#94a3b8;">Nunca</span>' ?></td>
                            <td><?= $tk['expira_en'] ? date('d/m/Y', strtotime($tk['expira_en'])) : '<span style="color:#94a3b8;">No expira</span>' ?></td>
                            <td>
                                <?php if ($tk['activo']): ?>
                                <span style="color:#059669;font-weight:600;">Activo</span>
                                <?php else: ?>
                                <span style="color:#991b1b;">Revocado</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <?php if ($tk['activo']): ?>
                                <form method="POST" action="<?= BASE_URL ?>/settings/api-tokens/revoke" style="display:inline;">
                                    <?= \App\Services\CsrfService::field() ?>
                                    <input type="hidden" name="id" value="<?= $tk['id'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Revocar" onclick="return confirm('Revocar este token? Las integraciones que lo usen dejaran de funcionar.')">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="empty-hint">No hay tokens de API creados.</p>
                <?php endif; ?>

                <div class="api-docs-hint">
                    <h4>Endpoints disponibles</h4>
                    <ul>
                        <li><code>GET /api/reports/campaigns</code> — Listar campanas</li>
                        <li><code>GET /api/reports/hours/{id}?year=2026&month=3</code> — Reporte de horas por campana</li>
                        <li><code>GET /api/reports/unified?year=2026&month=3</code> — Reporte unificado (admin)</li>
                        <li><code>GET /api/reports/attendance/{id}?year=2026&month=3</code> — Asistencia por campana</li>
                    </ul>
                    <p>Uso: <code>Authorization: Bearer tf_xxxxxx...</code></p>
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
    .settings-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    .settings-panel-full {
        grid-column: 1 / -1;
    }

    .panel-body {
        padding: 20px 24px;
    }

    .settings-form-inline {
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f1f5f9;
    }

    .form-row {
        display: flex;
        gap: 12px;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .form-group {
        flex: 1;
        min-width: 100px;
    }

    .form-group label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--corp-gray-500);
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--input-radius);
        font-size: 0.85rem;
        font-family: inherit;
        background: #fff;
        transition: border-color 0.15s;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--corp-primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .form-group-btn {
        flex: 0 0 auto;
        min-width: auto;
    }

    .btn-sm {
        padding: 8px 16px;
        font-size: 0.82rem;
    }

    .settings-table {
        font-size: 0.85rem;
    }

    .settings-table th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: var(--corp-gray-500);
        font-weight: 600;
    }

    .settings-table td,
    .settings-table th {
        padding: 10px 14px;
    }

    .actions-cell {
        text-align: right;
        width: 50px;
    }

    .btn-icon {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        border-radius: 6px;
        transition: background 0.15s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-icon svg {
        width: 18px;
        height: 18px;
    }

    .btn-icon-danger svg {
        fill: var(--corp-gray-400);
    }

    .btn-icon-danger:hover {
        background: #fee2e2;
    }

    .btn-icon-danger:hover svg {
        fill: var(--corp-danger);
    }

    .empty-hint {
        text-align: center;
        padding: 20px;
        color: var(--corp-gray-400);
        font-size: 0.85rem;
    }

    .flash-msg {
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 500;
        margin-bottom: 20px;
    }

    .flash-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .flash-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    /* Params */
    .params-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .param-item label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--corp-gray-600);
        margin-bottom: 4px;
    }

    .param-input-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .param-input-row input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid var(--corp-gray-200);
        border-radius: var(--input-radius);
        font-size: 0.85rem;
        font-family: inherit;
    }

    .param-input-row input:focus {
        outline: none;
        border-color: var(--corp-primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .param-key {
        font-size: 0.7rem;
        color: var(--corp-gray-400);
        font-family: 'SF Mono', 'Fira Code', monospace;
        white-space: nowrap;
    }

    .param-actions {
        padding-top: 16px;
        border-top: 1px solid #f1f5f9;
    }

    /* Token reveal */
    .token-reveal {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        padding: 16px 20px;
        margin-bottom: 20px;
    }

    .token-reveal p {
        margin: 0 0 8px;
        font-size: 0.85rem;
        color: #1e40af;
    }

    .token-value {
        font-family: 'SF Mono', 'Fira Code', monospace;
        font-size: 0.8rem;
        background: #fff;
        border: 1px solid #93c5fd;
        border-radius: 6px;
        padding: 10px 14px;
        margin-bottom: 10px;
        word-break: break-all;
        user-select: all;
    }

    /* API docs hint */
    .api-docs-hint {
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid #f1f5f9;
    }

    .api-docs-hint h4 {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--corp-gray-600);
        margin: 0 0 8px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .api-docs-hint ul {
        list-style: none;
        padding: 0;
        margin: 0 0 8px;
    }

    .api-docs-hint li {
        padding: 4px 0;
        font-size: 0.82rem;
        color: #475569;
    }

    .api-docs-hint code {
        background: #f1f5f9;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.78rem;
        font-family: 'SF Mono', 'Fira Code', monospace;
    }

    .api-docs-hint > p {
        font-size: 0.82rem;
        color: #64748b;
        margin: 0;
    }

    @media (max-width: 768px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }

        .form-row {
            flex-direction: column;
        }

        .form-group {
            min-width: auto;
        }
    }
</style>
STYLE;

$extraScripts = [];
include APP_PATH . '/Views/layouts/main.php';
?>
