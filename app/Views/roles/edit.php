<?php
/**
 * TurnoFlow - Editar Rol y Permisos
 */

$isBase = in_array($role['nombre'], ['admin', 'gerente', 'coordinador', 'supervisor', 'asesor']);

$moduloNames = [
    'dashboard' => 'Dashboard',
    'users' => 'Usuarios',
    'roles' => 'Roles',
    'campaigns' => 'Campanas',
    'advisors' => 'Asesores',
    'schedules' => 'Horarios',
    'reports' => 'Reportes',
    'settings' => 'Configuración'
];

ob_start();
?>

<div class="edit-role-page">
    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <a href="<?= BASE_URL ?>/roles" class="back-link">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver a Roles
                </a>
                <h1 class="header-title">Editar Rol: <?= ucfirst(htmlspecialchars($role['nombre'])) ?></h1>
                <p class="header-subtitle">Configura los permisos de este rol</p>
            </div>
        </div>
    </div>

    <form action="<?= BASE_URL ?>/roles/<?= $role['id'] ?>" method="POST">
        <?= \App\Services\CsrfService::field() ?>
        <div class="form-grid">
            <!-- Info del rol -->
            <div class="form-card">
                <div class="card-header">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                    <h2>Información del Rol</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Nombre del Rol</label>
                        <input type="text" value="<?= ucfirst(htmlspecialchars($role['nombre'])) ?>" disabled class="input-disabled">
                        <?php if ($isBase): ?>
                        <small class="input-hint">Los roles base no pueden renombrarse</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="3" placeholder="Describe las funciones de este rol..."><?= htmlspecialchars($role['descripcion'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Permisos -->
            <div class="form-card permissions-card">
                <div class="card-header">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    <h2>Permisos</h2>
                    <div class="card-toolbar">
                        <button type="button" class="btn-select-all" onclick="toggleAll(true)">Marcar todos</button>
                        <button type="button" class="btn-select-none" onclick="toggleAll(false)">Desmarcar todos</button>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach ($permissionsGrouped as $modulo => $permisos): ?>
                    <div class="permission-module">
                        <div class="module-header" onclick="toggleModule('<?= $modulo ?>')">
                            <div class="module-info">
                                <svg class="module-chevron" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                                <span class="module-name"><?= $moduloNames[$modulo] ?? ucfirst($modulo) ?></span>
                                <span class="module-count" id="count-<?= $modulo ?>">0/<?= count($permisos) ?></span>
                            </div>
                            <label class="module-toggle" onclick="event.stopPropagation()">
                                <input type="checkbox" class="module-checkbox" data-module="<?= $modulo ?>" onchange="toggleModulePerms('<?= $modulo ?>', this.checked)">
                                <span>Todos</span>
                            </label>
                        </div>
                        <div class="module-permissions" id="module-<?= $modulo ?>">
                            <?php foreach ($permisos as $perm): ?>
                            <label class="permission-item">
                                <input type="checkbox"
                                       name="permissions[]"
                                       value="<?= $perm['id'] ?>"
                                       data-module="<?= $modulo ?>"
                                       <?= in_array($perm['codigo'], $rolePermissions) ? 'checked' : '' ?>
                                       onchange="updateModuleCount('<?= $modulo ?>')">
                                <div class="permission-info">
                                    <span class="permission-name"><?= htmlspecialchars($perm['nombre']) ?></span>
                                    <span class="permission-desc"><?= htmlspecialchars($perm['descripcion'] ?? '') ?></span>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= BASE_URL ?>/roles" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-submit">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                Guardar Cambios
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .edit-role-page { max-width: 1000px; margin: 0 auto; }

    .alert {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .alert svg { width: 20px; height: 20px; flex-shrink: 0; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .alert-error svg { fill: #dc2626; }

    .page-header { margin-bottom: 24px; }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        color: #64748b;
        text-decoration: none;
        margin-bottom: 8px;
        transition: color 0.15s;
    }
    .back-link:hover { color: #2563eb; }
    .back-link svg { width: 18px; height: 18px; fill: currentColor; }

    .header-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0 0 4px 0; }
    .header-subtitle { font-size: 0.875rem; color: #64748b; margin: 0; }

    .form-grid { display: grid; gap: 24px; }

    .form-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 16px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .card-header svg { width: 20px; height: 20px; fill: #64748b; }
    .card-header h2 { font-size: 0.95rem; font-weight: 600; color: #334155; margin: 0; flex: 1; }

    .card-toolbar { display: flex; gap: 8px; }
    .btn-select-all, .btn-select-none {
        padding: 6px 12px;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: all 0.15s;
    }
    .btn-select-all { background: #dbeafe; color: #2563eb; }
    .btn-select-all:hover { background: #bfdbfe; }
    .btn-select-none { background: #f1f5f9; color: #64748b; }
    .btn-select-none:hover { background: #e2e8f0; }

    .card-body { padding: 20px; }

    .form-group { margin-bottom: 16px; }
    .form-group:last-child { margin-bottom: 0; }

    .form-group label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.9rem;
        color: #0f172a;
        transition: border-color 0.15s, box-shadow 0.15s;
        background: #fff;
        font-family: inherit;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .input-disabled { background: #f8fafc; color: #64748b; cursor: not-allowed; }
    .input-hint { font-size: 0.75rem; color: #94a3b8; margin-top: 4px; display: block; }

    /* Permissions */
    .permission-module {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 12px;
        overflow: hidden;
    }
    .permission-module:last-child { margin-bottom: 0; }

    .module-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: #f8fafc;
        cursor: pointer;
        transition: background 0.15s;
    }
    .module-header:hover { background: #f1f5f9; }

    .module-info { display: flex; align-items: center; gap: 8px; }

    .module-chevron {
        width: 20px;
        height: 20px;
        fill: #64748b;
        transition: transform 0.2s;
    }
    .permission-module.collapsed .module-chevron { transform: rotate(0deg); }
    .permission-module:not(.collapsed) .module-chevron { transform: rotate(90deg); }

    .module-name { font-weight: 600; color: #334155; font-size: 0.9rem; }

    .module-count {
        padding: 2px 8px;
        background: #e2e8f0;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
        color: #64748b;
    }

    .module-toggle {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        color: #64748b;
        cursor: pointer;
    }
    .module-toggle input { width: 16px; height: 16px; cursor: pointer; }

    .module-permissions {
        padding: 8px;
        display: grid;
        gap: 4px;
    }
    .permission-module.collapsed .module-permissions { display: none; }

    .permission-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s;
    }
    .permission-item:hover { background: #f8fafc; }

    .permission-item input {
        width: 18px;
        height: 18px;
        margin-top: 2px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .permission-info { flex: 1; }
    .permission-name { display: block; font-weight: 500; color: #0f172a; font-size: 0.875rem; }
    .permission-desc { font-size: 0.75rem; color: #64748b; line-height: 1.4; }

    /* Actions */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }

    .btn-cancel {
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        color: #64748b;
        background: #f1f5f9;
        transition: all 0.15s;
    }
    .btn-cancel:hover { background: #e2e8f0; }

    .btn-submit {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        color: #fff;
        background: #2563eb;
        border: none;
        cursor: pointer;
        transition: all 0.15s;
    }
    .btn-submit:hover { background: #1d4ed8; }
    .btn-submit svg { width: 18px; height: 18px; }
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Actualizar contadores iniciales
    document.querySelectorAll('.permission-module').forEach(module => {
        const modulo = module.querySelector('.module-checkbox').dataset.module;
        updateModuleCount(modulo);
    });
});

function toggleModule(modulo) {
    const module = document.querySelector(`[data-module="${modulo}"]`).closest('.permission-module');
    module.classList.toggle('collapsed');
}

function updateModuleCount(modulo) {
    const checkboxes = document.querySelectorAll(`input[data-module="${modulo}"][name="permissions[]"]`);
    const checked = document.querySelectorAll(`input[data-module="${modulo}"][name="permissions[]"]:checked`);
    const countEl = document.getElementById(`count-${modulo}`);
    const moduleCheckbox = document.querySelector(`.module-checkbox[data-module="${modulo}"]`);

    if (countEl) {
        countEl.textContent = `${checked.length}/${checkboxes.length}`;
    }

    if (moduleCheckbox) {
        moduleCheckbox.checked = checked.length === checkboxes.length && checkboxes.length > 0;
        moduleCheckbox.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
    }
}

function toggleModulePerms(modulo, checked) {
    const checkboxes = document.querySelectorAll(`input[data-module="${modulo}"][name="permissions[]"]`);
    checkboxes.forEach(cb => cb.checked = checked);
    updateModuleCount(modulo);
}

function toggleAll(checked) {
    document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = checked);
    document.querySelectorAll('.module-checkbox').forEach(cb => {
        cb.checked = checked;
        cb.indeterminate = false;
    });
    document.querySelectorAll('.permission-module').forEach(module => {
        const modulo = module.querySelector('.module-checkbox').dataset.module;
        updateModuleCount(modulo);
    });
}
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
