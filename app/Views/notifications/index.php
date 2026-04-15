<?php
/**
 * TurnoFlow - Notificaciones
 */
$pageTitle = 'Notificaciones';
$currentPage = 'notifications';

$appUrl = BASE_URL;

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => BASE_URL . '/dashboard'],
    ['label' => 'Notificaciones'],
];

$tipoIconos = [
    'horario_enviado'    => ['icon' => 'M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z', 'color' => '#2563eb'],
    'horario_aprobado'   => ['icon' => 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z', 'color' => '#15803d'],
    'horario_rechazado'  => ['icon' => 'M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z', 'color' => '#b91c1c'],
    'ausencia_registrada' => ['icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z', 'color' => '#d97706'],
];

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .notif-list { display: flex; flex-direction: column; gap: 8px; }
    .notif-item {
        display: flex; align-items: flex-start; gap: 16px;
        padding: 16px 20px; border-radius: 10px;
        background: var(--corp-white); border: 1px solid var(--corp-gray-200);
        transition: all 0.15s;
    }
    .notif-item:hover { border-color: var(--corp-primary); }
    .notif-item.unread {
        background: #eff6ff; border-color: #bfdbfe;
    }
    .notif-icon {
        width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .notif-icon svg { width: 20px; height: 20px; }
    .notif-body { flex: 1; min-width: 0; }
    .notif-title { font-weight: 600; color: var(--corp-gray-900); font-size: 0.95rem; }
    .notif-msg { color: var(--corp-gray-500); font-size: 0.875rem; margin-top: 2px; }
    .notif-time { color: var(--corp-gray-400); font-size: 0.8rem; margin-top: 4px; }
    .notif-actions { flex-shrink: 0; display: flex; align-items: center; gap: 8px; }
    .notif-mark-btn {
        background: none; border: 1px solid var(--corp-gray-300); border-radius: 6px;
        padding: 4px 10px; font-size: 0.8rem; color: var(--corp-gray-600);
        cursor: pointer; transition: all 0.15s;
    }
    .notif-mark-btn:hover { background: var(--corp-gray-100); }
    .pagination-bar {
        display: flex; justify-content: center; gap: 6px; margin-top: 24px;
    }
    .pagination-bar a, .pagination-bar span {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 36px; height: 36px; border-radius: 8px;
        font-size: 0.875rem; font-weight: 500; text-decoration: none;
        border: 1px solid var(--corp-gray-200); color: var(--corp-gray-700);
        transition: all 0.15s;
    }
    .pagination-bar a:hover { background: var(--corp-gray-100); }
    .pagination-bar .current {
        background: var(--corp-primary); color: #fff; border-color: var(--corp-primary);
    }
    .mark-all-bar {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 16px;
    }
</style>
STYLE;

$extraScripts = [];
$extraScripts[] = "<script>const BASE_URL = '{$appUrl}';</script>";
$extraScripts[] = <<<'SCRIPT'
<script>
async function markRead(id, el) {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const token = csrfMeta ? csrfMeta.content : '';
    const res = await fetch(BASE_URL + '/notifications/' + id + '/read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }
    });
    const data = await res.json();
    if (data.success) {
        const item = el.closest('.notif-item');
        item.classList.remove('unread');
        el.remove();
        showToast('Notificacion marcada como leida', 'success');
    }
}

async function markAllRead() {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const token = csrfMeta ? csrfMeta.content : '';
    const res = await fetch(BASE_URL + '/notifications/read-all', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }
    });
    const data = await res.json();
    if (data.success) {
        document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
        document.querySelectorAll('.notif-mark-btn').forEach(el => el.remove());
        showToast('Todas las notificaciones marcadas como leidas', 'success');
    }
}
</script>
SCRIPT;

ob_start();
?>

<div class="form-breadcrumb" style="margin-bottom:16px;">
    <a href="<?= BASE_URL ?>/dashboard" style="color:#2563eb;text-decoration:none;font-weight:500;">Dashboard</a>
    <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:#94a3b8;"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
    <span>Notificaciones</span>
</div>

<div class="mark-all-bar">
    <div style="color: var(--corp-gray-500); font-size: 0.9rem;">
        <?= $total ?> notificacion<?= $total !== 1 ? 'es' : '' ?> en total
    </div>
    <button class="btn-action" onclick="markAllRead()" style="font-size: 0.85rem;">
        Marcar todas como leidas
    </button>
</div>

<?php if (empty($notifications)): ?>
<div class="data-panel">
    <div class="panel-body">
        <div class="empty-state" style="padding: 60px;">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
            </div>
            <h5>Sin notificaciones</h5>
            <p style="margin-bottom: 0;">No tienes notificaciones por el momento.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="notif-list">
    <?php foreach ($notifications as $notif):
        $tipo = $notif['tipo'] ?? 'horario_enviado';
        $iconData = $tipoIconos[$tipo] ?? $tipoIconos['horario_enviado'];
        $isUnread = !$notif['leida'];
        $timeAgo = date('d/m/Y H:i', strtotime($notif['created_at']));
    ?>
    <div class="notif-item <?= $isUnread ? 'unread' : '' ?>">
        <div class="notif-icon" style="background: <?= $iconData['color'] ?>20;">
            <svg viewBox="0 0 24 24" fill="<?= $iconData['color'] ?>"><path d="<?= $iconData['icon'] ?>"/></svg>
        </div>
        <div class="notif-body">
            <?php if (!empty($notif['url'])): ?>
            <a href="<?= BASE_URL . htmlspecialchars($notif['url']) ?>" class="notif-title" style="text-decoration: none; color: inherit;">
                <?= htmlspecialchars($notif['titulo']) ?>
            </a>
            <?php else: ?>
            <div class="notif-title"><?= htmlspecialchars($notif['titulo']) ?></div>
            <?php endif; ?>
            <?php if (!empty($notif['mensaje'])): ?>
            <div class="notif-msg"><?= htmlspecialchars($notif['mensaje']) ?></div>
            <?php endif; ?>
            <div class="notif-time"><?= $timeAgo ?></div>
        </div>
        <div class="notif-actions">
            <?php if ($isUnread): ?>
            <button class="notif-mark-btn" onclick="markRead(<?= $notif['id'] ?>, this)">Marcar leida</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination-bar">
    <?php if ($currentPageNum > 1): ?>
    <a href="<?= BASE_URL ?>/notifications?page=<?= $currentPageNum - 1 ?>">&#8249;</a>
    <?php endif; ?>
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php if ($p === $currentPageNum): ?>
        <span class="current"><?= $p ?></span>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/notifications?page=<?= $p ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($currentPageNum < $totalPages): ?>
    <a href="<?= BASE_URL ?>/notifications?page=<?= $currentPageNum + 1 ?>">&#8250;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
include APP_PATH . '/Views/layouts/main.php';
?>
