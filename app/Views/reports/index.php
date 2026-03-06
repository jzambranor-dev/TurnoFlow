<?php
/**
 * TurnoFlow - Vista de Reportes
 * Diseno empresarial profesional
 */

ob_start();
?>

<div class="coming-soon-page">
    <div class="coming-soon-card">
        <div class="icon-wrapper">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
        </div>
        <h1 class="title">Reportes</h1>
        <p class="subtitle">Esta seccion estara disponible proximamente</p>
        <div class="features">
            <div class="feature">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                <span>Reporte de horas por asesor</span>
            </div>
            <div class="feature">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                <span>Reporte de cobertura por campana</span>
            </div>
            <div class="feature">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                <span>Exportacion a Excel y PDF</span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/dashboard" class="btn-back">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Volver al Dashboard
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraStyles = [];
$extraStyles[] = <<<'STYLE'
<style>
    .coming-soon-page {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 60vh;
    }

    .coming-soon-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 48px;
        text-align: center;
        max-width: 480px;
        width: 100%;
    }

    .icon-wrapper {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px;
    }

    .icon-wrapper svg {
        width: 40px;
        height: 40px;
        fill: #fff;
    }

    .title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 8px 0;
    }

    .subtitle {
        font-size: 0.95rem;
        color: #64748b;
        margin: 0 0 32px 0;
    }

    .features {
        text-align: left;
        margin-bottom: 32px;
    }

    .feature {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        background: #f8fafc;
        border-radius: 8px;
        margin-bottom: 8px;
    }

    .feature:last-child {
        margin-bottom: 0;
    }

    .feature svg {
        width: 20px;
        height: 20px;
        fill: #16a34a;
        flex-shrink: 0;
    }

    .feature span {
        font-size: 0.875rem;
        color: #334155;
    }

    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: #2563eb;
        color: #fff;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.15s;
    }

    .btn-back:hover {
        background: #1d4ed8;
    }

    .btn-back svg {
        width: 18px;
        height: 18px;
    }
</style>
STYLE;

include APP_PATH . '/Views/layouts/main.php';
?>
