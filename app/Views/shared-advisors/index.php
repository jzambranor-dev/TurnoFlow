<?php
$pageTitle = 'Asesores Compartidos';
$currentPage = 'campaigns';

ob_start();
?>

<div class="page-container-md">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Asesores Compartidos</h1>
            <p class="page-header-subtitle"><?= htmlspecialchars($campaign['nombre']) ?></p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/edit" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Volver
            </a>
            <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/shared-advisors/create" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Compartir Asesores
            </a>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <!-- Incoming: Asesores prestados A esta campaña -->
    <div class="data-panel" style="margin-bottom: 24px;">
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.7 4.7L11.3 6.1 14.2 9H2v2h12.2l-2.9 2.9 1.4 1.4L17.7 10z"/></svg>
                Asesores Prestados a esta Campaña
            </div>
            <span class="badge" style="background: #dbeafe; color: #2563eb; padding: 4px 10px; border-radius: 12px; font-size: 0.85rem;">
                <?= count($incoming) ?>
            </span>
        </div>
        <div class="panel-body" style="padding: 0;">
            <?php if (empty($incoming)): ?>
            <div style="padding: 24px; text-align: center; color: #94a3b8;">
                No hay asesores prestados a esta campaña.
            </div>
            <?php else: ?>
            <table class="data-table" style="margin: 0;">
                <thead>
                    <tr>
                        <th>Asesor</th>
                        <th>Campaña Origen</th>
                        <th>Max Horas/Dia</th>
                        <th>Estado</th>
                        <th style="width: 100px;">Acciónes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incoming as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['apellidos'] . ' ' . $row['nombres']) ?></strong></td>
                        <td><?= htmlspecialchars($row['source_campaign_nombre']) ?></td>
                        <td><?= (int)$row['max_horas_dia'] ?>h</td>
                        <td>
                            <?php if ($row['estado'] === 'activo'): ?>
                            <span class="badge" style="background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 8px; font-size: 0.8rem;">Activo</span>
                            <?php else: ?>
                            <span class="badge" style="background: #f1f5f9; color: #64748b; padding: 3px 8px; border-radius: 8px; font-size: 0.8rem;">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="<?= BASE_URL ?>/shared-advisors/<?= $row['id'] ?>/toggle" style="display: inline;">
                                <?= \App\Services\CsrfService::field() ?>
                                <button type="submit" class="btn btn-sm <?= $row['estado'] === 'activo' ? 'btn-secondary' : 'btn-primary' ?>" title="<?= $row['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>">
                                    <?= $row['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Outgoing: Asesores prestados DESDE esta campaña -->
    <div class="data-panel">
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 12l1.41 1.41L11 7.83V20h2V7.83l5.58 5.59L20 12l-8-8-8 8z"/></svg>
                Asesores Prestados desde esta Campaña
            </div>
            <span class="badge" style="background: #fef3c7; color: #b45309; padding: 4px 10px; border-radius: 12px; font-size: 0.85rem;">
                <?= count($outgoing) ?>
            </span>
        </div>
        <div class="panel-body" style="padding: 0;">
            <?php if (empty($outgoing)): ?>
            <div style="padding: 24px; text-align: center; color: #94a3b8;">
                No hay asesores de esta campaña prestados a otras.
            </div>
            <?php else: ?>
            <table class="data-table" style="margin: 0;">
                <thead>
                    <tr>
                        <th>Asesor</th>
                        <th>Campaña Destino</th>
                        <th>Max Horas/Dia</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($outgoing as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['apellidos'] . ' ' . $row['nombres']) ?></strong></td>
                        <td><?= htmlspecialchars($row['target_campaign_nombre']) ?></td>
                        <td><?= (int)$row['max_horas_dia'] ?>h</td>
                        <td>
                            <?php if ($row['estado'] === 'activo'): ?>
                            <span class="badge" style="background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 8px; font-size: 0.8rem;">Activo</span>
                            <?php else: ?>
                            <span class="badge" style="background: #f1f5f9; color: #64748b; padding: 3px 8px; border-radius: 8px; font-size: 0.8rem;">Inactivo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include APP_PATH . '/Views/layouts/main.php';
?>
