<?php
/**
 * TurnoFlow - Vista de Configuracion
 * Diseno empresarial profesional
 */

ob_start();
?>

<div class="coming-soon-page">
    <div class="coming-soon-card">
        <div class="icon-wrapper">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
        </div>
        <h1 class="title">Configuracion</h1>
        <p class="subtitle">Esta seccion estara disponible proximamente</p>
        <div class="features">
            <div class="feature">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                <span>Configuracion de horas mensuales</span>
            </div>
            <div class="feature">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                <span>Parametros del sistema</span>
            </div>
            <div class="feature">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                <span>Dias festivos y excepciones</span>
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
