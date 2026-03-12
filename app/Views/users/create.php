<?php
/**
 * TurnoFlow - Crear Usuario
 * Diseno empresarial profesional
 */

ob_start();
?>

<div class="create-user-page">
    <!-- Mensajes de feedback -->
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
                <a href="<?= BASE_URL ?>/users" class="back-link">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver
                </a>
                <h1 class="header-title">Nuevo Usuario</h1>
                <p class="header-subtitle">Completa los datos del nuevo usuario</p>
            </div>
        </div>
    </div>

    <div class="form-card">
        <div class="card-header">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            <h2>Datos del Usuario</h2>
        </div>
        <form action="<?= BASE_URL ?>/users" method="POST" class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" required placeholder="Ej: Juan">
                </div>
                <div class="form-group">
                    <label for="apellido">Apellido *</label>
                    <input type="text" id="apellido" name="apellido" required placeholder="Ej: Perez">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required placeholder="usuario@empresa.com">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Contrasena *</label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="Minimo 6 caracteres">
                </div>
                <div class="form-group">
                    <label for="rol_id">Rol *</label>
                    <select id="rol_id" name="rol_id" required>
                        <option value="">Selecciónar rol...</option>
                        <?php foreach ($roles as $rol): ?>
                        <option value="<?= $rol['id'] ?>"><?= ucfirst(htmlspecialchars($rol['nombre'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <div class="toggle-container">
                    <label class="toggle">
                        <input type="checkbox" name="activo" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label">Usuario Activo</span>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?= BASE_URL ?>/users" class="btn-cancel">Cancelar</a>
                <button type="submit" class="btn-submit">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    Crear Usuario
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .create-user-page { max-width: 600px; margin: 0 auto; }

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

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }

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
    .card-header h2 { font-size: 0.95rem; font-weight: 600; color: #334155; margin: 0; }

    .card-body { padding: 24px; }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    @media (max-width: 500px) {
        .form-row { grid-template-columns: 1fr; }
    }

    .form-group { margin-bottom: 18px; }

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
    .form-group select {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.9rem;
        color: #0f172a;
        transition: border-color 0.15s, box-shadow 0.15s;
        background: #fff;
    }

    .form-group input::placeholder { color: #94a3b8; }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .toggle-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .toggle {
        position: relative;
        display: inline-block;
        width: 48px;
        height: 26px;
    }

    .toggle input { opacity: 0; width: 0; height: 0; }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        border-radius: 26px;
        transition: 0.3s;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        border-radius: 50%;
        transition: 0.3s;
    }

    .toggle input:checked + .toggle-slider { background-color: #16a34a; }
    .toggle input:checked + .toggle-slider:before { transform: translateX(22px); }

    .toggle-label { font-size: 0.9rem; color: #475569; font-weight: 500; }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid #f1f5f9;
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

include APP_PATH . '/Views/layouts/main.php';
?>
