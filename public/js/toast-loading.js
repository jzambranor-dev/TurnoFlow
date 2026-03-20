/**
 * TurnoFlow - Toast & Loading Overlay System
 * Shared across all views that need notifications and loading states.
 */

// --- Toast / Snackbar ---
function showToast(message, type, duration) {
    type = type || 'info';
    duration = duration || 3500;
    var container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'tf-toast-container';
        document.body.appendChild(container);
    }
    var icons = {
        success: '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
        error: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>',
        warning: '<path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>',
        info: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>'
    };
    var toast = document.createElement('div');
    toast.className = 'tf-toast tf-toast-' + type;
    toast.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor">' + (icons[type] || icons.info) + '</svg><span>' + message + '</span><button class="tf-toast-close" onclick="this.parentElement.remove()">&times;</button>';
    container.appendChild(toast);
    requestAnimationFrame(function() { toast.classList.add('tf-toast-show'); });
    setTimeout(function() {
        toast.classList.remove('tf-toast-show');
        toast.classList.add('tf-toast-hide');
        setTimeout(function() { toast.remove(); }, 300);
    }, duration);
}

// --- Loading Overlay ---
function showLoading(msg) {
    msg = msg || 'Guardando...';
    var overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'tf-loading-overlay';
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = '<div class="tf-loading-box"><div class="tf-loading-spinner"></div><div class="tf-loading-text">' + msg + '</div></div>';
    overlay.style.display = 'flex';
}

function hideLoading() {
    var overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.style.display = 'none';
}
