<?php
/**
 * TurnoFlow - Editar Actividad
 */

ob_start();
?>

<div class="page-container-md">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Editar Actividad</h1>
            <p class="page-header-subtitle">
                Campaña: <strong><?= htmlspecialchars($campaign['nombre']) ?></strong>
            </p>
        </div>
        <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/activities" class="btn btn-light">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Volver
        </a>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['flash_error'] ?></div>
    <?php unset($_SESSION['flash_error']); endif; ?>

    <div class="data-panel">
        <div class="panel-header">
            <div class="panel-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                Editar: <?= htmlspecialchars($activity['nombre']) ?>
            </div>
        </div>
        <div class="panel-body">
            <form method="POST" action="<?= BASE_URL ?>/activities/<?= $activity['id'] ?>">
                <div class="form-group">
                    <label class="form-label required">Nombre</label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($activity['nombre']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($activity['descripcion'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Color identificador</label>
                        <input type="color" name="color" class="form-control" value="<?= htmlspecialchars($activity['color'] ?? '#2563eb') ?>" style="width: 80px; height: 40px; padding: 2px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-control">
                            <option value="activa" <?= $activity['estado'] === 'activa' ? 'selected' : '' ?>>Activa</option>
                            <option value="inactiva" <?= $activity['estado'] === 'inactiva' ? 'selected' : '' ?>>Inactiva</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="<?= BASE_URL ?>/campaigns/<?= $campaign['id'] ?>/activities" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include APP_PATH . '/Views/layouts/main.php';
?>
