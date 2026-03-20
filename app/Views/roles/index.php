<?php
/**
 * TurnoFlow - Vista de Roles
 */

ob_start();
?>

<div class="roles-page">
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="header-title">Roles y Permisos</h1>
                <p class="header-subtitle">Gestióna los roles y sus permisos en el sistema</p>
            </div>
            <div class="header-actions">
                <a href="<?= BASE_URL ?>/roles/create" class="btn-action btn-primary">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Nuevo Rol
                </a>
            </div>
        </div>
    </div>

    <div class="roles-grid">
        <?php foreach ($roles as $role): ?>
        <?php
        $isBase = in_array($role['nombre'], ['admin', 'gerente', 'coordinador', 'supervisor', 'asesor']);
        $colorMap = [
            'admin' => '#dc2626',
            'gerente' => '#7c3aed',
            'coordinador' => '#2563eb',
            'supervisor' => '#059669',
            'asesor' => '#d97706'
        ];
        $color = $colorMap[$role['nombre']] ?? '#2563eb';
        ?>
        <div class="role-card">
            <div class="role-header" style="border-left-color: <?= $color ?>">
                <div class="role-icon" style="background: <?= $color ?>">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                </div>
                <div class="role-info">
                    <h3 class="role-name"><?= ucfirst(htmlspecialchars($role['nombre'])) ?></h3>
                    <p class="role-desc"><?= htmlspecialchars($role['descripcion'] ?? 'Sin descripción') ?></p>
                </div>
                <?php if ($isBase): ?>
                <span class="badge-base">Base</span>
                <?php endif; ?>
            </div>
            <div class="role-stats">
                <div class="stat">
                    <span class="stat-value"><?= $role['total_users'] ?></span>
                    <span class="stat-label">Usuarios</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?= $role['total_permissions'] ?></span>
                    <span class="stat-label">Permisos</span>
                </div>
            </div>
            <div class="role-actions">
                <a href="<?= BASE_URL ?>/roles/<?= $role['id'] ?>/edit" class="btn-action btn-edit">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                    Editar Permisos
                </a>
                <?php if (!$isBase): ?>
                <form method="POST" action="<?= BASE_URL ?>/roles/<?= $role['id'] ?>/delete" style="display:inline" onsubmit="return confirm('¿Eliminar este rol?')">
                    <?= \App\Services\CsrfService::field() ?>
                    <button type="submit" class="btn-action btn-delete">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .roles-page { max-width: 1200px; margin: 0 auto; }

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
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .alert-success svg { fill: #16a34a; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .alert-error svg { fill: #dc2626; }

    .page-header { margin-bottom: 24px; }
    .header-content { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; }
    .header-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0 0 4px 0; }
    .header-subtitle { font-size: 0.875rem; color: #64748b; margin: 0; }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.15s ease;
        border: none;
        cursor: pointer;
    }
    .btn-action svg { width: 18px; height: 18px; }
    .btn-primary { background: #2563eb; color: #fff; }
    .btn-primary:hover { background: #1d4ed8; }

    .roles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }

    .role-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }

    .role-header {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 20px;
        border-left: 4px solid #2563eb;
        position: relative;
    }

    .role-icon {
        width: 44px;
        height: 44px;
        min-width: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .role-icon svg { width: 22px; height: 22px; fill: #fff; }

    .role-info { flex: 1; min-width: 0; }
    .role-name { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 4px 0; }
    .role-desc { font-size: 0.8rem; color: #64748b; margin: 0; line-height: 1.4; }

    .badge-base {
        position: absolute;
        top: 12px;
        right: 12px;
        padding: 3px 8px;
        background: #f1f5f9;
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 600;
        border-radius: 4px;
        text-transform: uppercase;
    }

    .role-stats {
        display: flex;
        border-top: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
    }

    .role-stats .stat {
        flex: 1;
        padding: 14px;
        text-align: center;
    }
    .role-stats .stat:first-child { border-right: 1px solid #f1f5f9; }
    .stat-value { display: block; font-size: 1.25rem; font-weight: 700; color: #0f172a; }
    .stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; }

    .role-actions {
        display: flex;
        gap: 8px;
        padding: 14px 20px;
    }

    .btn-edit {
        flex: 1;
        justify-content: center;
        background: #f1f5f9;
        color: #475569;
    }
    .btn-edit:hover { background: #e2e8f0; }
    .btn-edit svg { fill: currentColor; }

    .btn-delete {
        padding: 10px 14px;
        background: #fee2e2;
        color: #dc2626;
    }
    .btn-delete:hover { background: #fecaca; }
    .btn-delete svg { fill: currentColor; }
</style>
STYLE;

include APP_PATH . '/Views/layouts/main.php';
?>
