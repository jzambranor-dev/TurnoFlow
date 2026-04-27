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
    <div class="alert alert-danger">
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
        $supervisores = count(array_filter($users, fn($u) => $u['rol_nombre'] === 'supervisor'));
        ?>
        <div class="stat-mini stat-clickable" data-filter="all" title="Ver todos">
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $total ?></span>
                <span class="stat-label">Total</span>
            </div>
        </div>
        <div class="stat-mini stat-active stat-clickable" data-filter="activo" title="Filtrar activos">
            <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $activos ?></span>
                <span class="stat-label">Activos</span>
            </div>
        </div>
        <div class="stat-mini stat-inactive stat-clickable" data-filter="inactivo" title="Filtrar inactivos">
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $inactivos ?></span>
                <span class="stat-label">Inactivos</span>
            </div>
        </div>
        <div class="stat-mini stat-admin">
            <div class="stat-icon" style="background: #f3e8ff; color: #7c3aed;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $admins ?></span>
                <span class="stat-label">Admins</span>
            </div>
        </div>
        <div class="stat-mini" style="border-left: 3px solid #059669;">
            <div class="stat-icon" style="background: #dcfce7; color: #059669;">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?= $supervisores ?></span>
                <span class="stat-label">Supervisores</span>
            </div>
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
            <p class="empty-text">Crea el primer usuario del sistema para comenzar.</p>
            <a href="<?= BASE_URL ?>/users/create" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Crear primer usuario
            </a>
        </div>
        <?php else: ?>
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Listado de Usuarios
                <span class="panel-counter"><?= $total ?></span>
            </div>
            <div class="panel-tools">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="searchInput" placeholder="Buscar usuario...">
                    <kbd class="search-kbd">Ctrl+K</kbd>
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
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr class="table-row-animated" data-activo="<?= $u['activo'] ? 'activo' : 'inactivo' ?>" style="animation-delay: <?= $i * 0.03 ?>s">
                        <td>
                            <div class="user-cell">
                                <?php
                                $avatarColors = ['#2563eb','#7c3aed','#0891b2','#059669','#d97706','#dc2626','#be185d'];
                                $avatarColor = $avatarColors[$u['id'] % count($avatarColors)];
                                ?>
                                <div class="user-avatar" style="background: <?= $avatarColor ?>;">
                                    <?= strtoupper(substr($u['nombre'], 0, 1) . substr($u['apellido'], 0, 1)) ?>
                                </div>
                                <div class="user-info">
                                    <span class="user-name"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></span>
                                    <span class="user-id">ID #<?= $u['id'] ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="user-email" title="<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['email']) ?></span>
                        </td>
                        <td>
                            <?php
                            $rolColors = [
                                'admin' => ['bg' => '#fee2e2', 'color' => '#dc2626', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>'],
                                'coordinador' => ['bg' => '#f3e8ff', 'color' => '#7c3aed', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;"><path d="M12 2l-5.5 9h11z"/></svg>'],
                                'supervisor' => ['bg' => '#dcfce7', 'color' => '#16a34a', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5z"/></svg>'],
                                'asesor' => ['bg' => '#fef3c7', 'color' => '#d97706', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4z"/></svg>']
                            ];
                            $rol = $rolColors[$u['rol_nombre'] ?? ''] ?? ['bg' => '#f1f5f9', 'color' => '#64748b', 'icon' => ''];
                            ?>
                            <span class="role-badge" style="background: <?= $rol['bg'] ?>; color: <?= $rol['color'] ?>">
                                <?= $rol['icon'] ?? '' ?>
                                <?= ucfirst($u['rol_nombre'] ?? 'N/A') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['activo']): ?>
                            <span class="status-badge status-active">
                                <svg viewBox="0 0 24 24" fill="currentColor" style="width:10px;height:10px;"><circle cx="12" cy="12" r="5"/></svg>
                                Activo
                            </span>
                            <?php else: ?>
                            <span class="status-badge status-inactive">
                                <svg viewBox="0 0 24 24" fill="currentColor" style="width:10px;height:10px;"><circle cx="12" cy="12" r="5"/></svg>
                                Inactivo
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $createdDate = strtotime($u['created_at']);
                            $daysAgo = floor((time() - $createdDate) / 86400);
                            ?>
                            <div style="display:flex;flex-direction:column;gap:2px;">
                                <span class="date-text"><?= date('d/m/Y', $createdDate) ?></span>
                                <span style="font-size:0.7rem;color:#94a3b8;">
                                    <?php if ($daysAgo === 0): ?>Hoy
                                    <?php elseif ($daysAgo === 1): ?>Ayer
                                    <?php elseif ($daysAgo < 30): ?>Hace <?= $daysAgo ?> dias
                                    <?php else: ?>Hace <?= floor($daysAgo / 30) ?> mes<?= floor($daysAgo / 30) > 1 ? 'es' : '' ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="<?= BASE_URL ?>/users/<?= $u['id'] ?>/edit" class="action-btn" title="Editar usuario">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                </a>
                                <form method="POST" action="<?= BASE_URL ?>/users/<?= $u['id'] ?>/toggle-status" style="display:inline;">
                                    <?= \App\Services\CsrfService::field() ?>
                                    <button type="submit" class="action-btn action-toggle" title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                                        <?php if ($u['activo']): ?>
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
                                        <?php else: ?>
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer de tabla -->
        <div class="table-footer">
            <div class="tf-page-size">
                Mostrar
                <select id="pageSize">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                registros
            </div>
            <span class="table-footer-info">
                Mostrando <strong id="visibleCount"><?= $total ?></strong> de <strong id="totalCount"><?= $total ?></strong> usuarios
            </span>
            <div class="tf-pagination" id="pagination"></div>
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
    .stat-active { border-left: 3px solid var(--corp-success); }
    .stat-inactive { border-left: 3px solid var(--corp-danger); }
    .stat-admin { border-left: 3px solid var(--corp-purple); }
    .action-toggle:hover { background: #fef3c7; color: var(--corp-warning); }
    .date-text { font-size: 0.85rem; color: var(--corp-gray-500); }
    .user-email { font-size: 0.85rem; color: var(--corp-gray-600); }
    .user-cell { display: flex; align-items: center; gap: 12px; }
    .user-info { display: flex; flex-direction: column; gap: 2px; }
    .user-name { font-weight: 600; color: var(--corp-gray-900); font-size: 0.9rem; }
    .user-id { font-size: 0.75rem; color: var(--corp-gray-400); }
    .user-avatar {
        width: 36px; height: 36px; border-radius: 8px; color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.7rem; font-weight: 700;
    }
    .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
    .status-active { background: #dcfce7; color: #16a34a; }
    .status-inactive { background: #fee2e2; color: #dc2626; }
    @media (max-width: 768px) {
        .header-content { flex-direction: column; align-items: stretch; }
    }
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('searchInput');
    var activeStatFilter = null;

    var pag = new TablePaginator({
        tableId: 'usersTable',
        pageSizeSelId: 'pageSize',
        paginationId: 'pagination',
        infoId: 'visibleCount',
        totalId: 'totalCount',
        defaultSize: 10
    });

    function applyFilters() {
        var search = (searchInput ? searchInput.value.toLowerCase() : '');
        pag.applyFilter(function(row) {
            var matchSearch = !search || row.textContent.toLowerCase().includes(search);
            var matchStat = !activeStatFilter || row.dataset.activo === activeStatFilter;
            return matchSearch && matchStat;
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    // Atajo Ctrl+K
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) searchInput.focus();
        }
    });

    // Filtro rapido por stats
    document.querySelectorAll('.stat-clickable').forEach(function(stat) {
        stat.style.cursor = 'pointer';
        stat.addEventListener('click', function() {
            document.querySelectorAll('.stat-clickable').forEach(function(s) { s.classList.remove('stat-active-filter'); });
            var filter = this.dataset.filter;
            if (filter === 'all') {
                activeStatFilter = null;
            } else {
                activeStatFilter = filter;
                this.classList.add('stat-active-filter');
            }
            applyFilters();
        });
    });

    // Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.4s, transform 0.4s';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function() { alert.style.display = 'none'; }, 400);
        }, 5000);
    });
});
</script>
SCRIPT;

include APP_PATH . '/Views/layouts/main.php';
?>
