<?php
/**
 * TurnoFlow - Vista de Usuarios
 * Diseno empresarial profesional
 */

ob_start();
?>

<div class="users-page">
    <!-- Mensajes de feedback -->
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

    <!-- Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1 class="header-title">Usuarios</h1>
                <p class="header-subtitle">Gestióna los usuarios del sistema</p>
            </div>
            <div class="header-actions">
                <a href="<?= BASE_URL ?>/users/create" class="btn-action btn-primary">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    Nuevo Usuario
                </a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <?php
        $total = count($users);
        $activos = count(array_filter($users, fn($u) => $u['activo']));
        $inactivos = $total - $activos;
        $admins = count(array_filter($users, fn($u) => $u['rol_nombre'] === 'admin' || $u['rol_nombre'] === 'coordinador'));
        ?>
        <div class="stat-mini">
            <span class="stat-value"><?= $total ?></span>
            <span class="stat-label">Total</span>
        </div>
        <div class="stat-mini stat-active">
            <span class="stat-value"><?= $activos ?></span>
            <span class="stat-label">Activos</span>
        </div>
        <div class="stat-mini stat-inactive">
            <span class="stat-value"><?= $inactivos ?></span>
            <span class="stat-label">Inactivos</span>
        </div>
        <div class="stat-mini stat-admin">
            <span class="stat-value"><?= $admins ?></span>
            <span class="stat-label">Admins</span>
        </div>
    </div>

    <!-- Table -->
    <div class="data-panel">
        <?php if (empty($users)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <h3 class="empty-title">No hay usuarios registrados</h3>
            <p class="empty-text">Crea el primer usuario del sistema.</p>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Listado de Usuarios
            </div>
            <div class="panel-tools">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="searchInput" placeholder="Buscar usuario...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="usersTable">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th class="text-right">Acciónes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar">
                                    <?= strtoupper(substr($u['nombre'], 0, 1) . substr($u['apellido'], 0, 1)) ?>
                                </div>
                                <div class="user-info">
                                    <span class="user-name"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></span>
                                    <span class="user-id">#<?= $u['id'] ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="user-email"><?= htmlspecialchars($u['email']) ?></span>
                        </td>
                        <td>
                            <?php
                            $rolColors = [
                                'admin' => ['bg' => '#fee2e2', 'color' => '#dc2626'],
                                'coordinador' => ['bg' => '#f3e8ff', 'color' => '#7c3aed'],
                                'supervisor' => ['bg' => '#dcfce7', 'color' => '#16a34a'],
                                'asesor' => ['bg' => '#fef3c7', 'color' => '#d97706']
                            ];
                            $rol = $rolColors[$u['rol_nombre'] ?? ''] ?? ['bg' => '#f1f5f9', 'color' => '#64748b'];
                            ?>
                            <span class="role-badge" style="background: <?= $rol['bg'] ?>; color: <?= $rol['color'] ?>">
                                <?= ucfirst($u['rol_nombre'] ?? 'N/A') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['activo']): ?>
                            <span class="status-badge status-active">Activo</span>
                            <?php else: ?>
                            <span class="status-badge status-inactive">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="date-text"><?= date('d/m/Y', strtotime($u['created_at'])) ?></span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="<?= BASE_URL ?>/users/<?= $u['id'] ?>/edit" class="action-btn" title="Editar usuario">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                </a>
                                <a href="<?= BASE_URL ?>/users/<?= $u['id'] ?>/toggle-status" class="action-btn action-toggle" title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                                    <?php if ($u['activo']): ?>
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
                                    <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                    <?php endif; ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .users-page { max-width: 1200px; margin: 0 auto; }

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

    .alert-success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }

    .alert-success svg { fill: #16a34a; }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }

    .alert-error svg { fill: #dc2626; }

    .page-header { margin-bottom: 24px; }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        flex-wrap: wrap;
    }

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

    .stats-row { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }

    .stat-mini {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 120px;
    }

    .stat-mini .stat-value { font-size: 1.25rem; font-weight: 700; color: #0f172a; }
    .stat-mini .stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
    .stat-active { border-left: 3px solid #16a34a; }
    .stat-inactive { border-left: 3px solid #dc2626; }
    .stat-admin { border-left: 3px solid #7c3aed; }

    .data-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }

    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
        gap: 16px;
        flex-wrap: wrap;
    }

    .panel-title { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; font-weight: 600; color: #334155; }
    .panel-title svg { width: 18px; height: 18px; fill: #64748b; }

    .search-box {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        min-width: 250px;
    }

    .search-box svg { width: 18px; height: 18px; fill: #94a3b8; }
    .search-box input { border: none; outline: none; font-size: 0.875rem; width: 100%; color: #334155; }

    .table-responsive { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; }

    .data-table th {
        padding: 12px 16px;
        text-align: left;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #64748b;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .data-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .data-table tbody tr:hover { background: #f8fafc; }
    .text-right { text-align: right !important; }

    .user-cell { display: flex; align-items: center; gap: 12px; }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .user-info { display: flex; flex-direction: column; gap: 2px; }
    .user-name { font-weight: 600; color: #0f172a; font-size: 0.9rem; }
    .user-id { font-size: 0.75rem; color: #94a3b8; }
    .user-email { font-size: 0.85rem; color: #475569; }

    .role-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }

    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
    .status-active { background: #dcfce7; color: #16a34a; }
    .status-inactive { background: #fee2e2; color: #dc2626; }

    .date-text { font-size: 0.85rem; color: #64748b; }

    .actions-cell { display: flex; justify-content: flex-end; gap: 6px; }

    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        color: #64748b;
        transition: all 0.15s ease;
        text-decoration: none;
    }

    .action-btn svg { width: 16px; height: 16px; fill: currentColor; }
    .action-btn:hover { background: #dbeafe; color: #2563eb; }
    .action-toggle:hover { background: #fef3c7; color: #d97706; }

    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-icon { width: 64px; height: 64px; background: #f1f5f9; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
    .empty-icon svg { width: 32px; height: 32px; fill: #94a3b8; }
    .empty-title { font-size: 1.1rem; font-weight: 600; color: #334155; margin: 0 0 8px 0; }
    .empty-text { font-size: 0.9rem; color: #64748b; margin: 0; }

    @media (max-width: 768px) {
        .header-content { flex-direction: column; align-items: stretch; }
        .btn-action { justify-content: center; }
        .stats-row { display: grid; grid-template-columns: repeat(2, 1fr); }
        .stat-mini { min-width: auto; }
        .panel-header { flex-direction: column; align-items: stretch; }
        .search-box { min-width: auto; }
    }
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('usersTable');

    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
