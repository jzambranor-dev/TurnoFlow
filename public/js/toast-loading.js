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
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'currentColor');
    svg.innerHTML = icons[type] || icons.info;
    var span = document.createElement('span');
    span.textContent = message;
    var btn = document.createElement('button');
    btn.className = 'tf-toast-close';
    btn.textContent = '\u00D7';
    btn.onclick = function() { this.parentElement.remove(); };
    toast.appendChild(svg);
    toast.appendChild(span);
    toast.appendChild(btn);
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
    overlay.textContent = '';
    var box = document.createElement('div');
    box.className = 'tf-loading-box';
    var spinner = document.createElement('div');
    spinner.className = 'tf-loading-spinner';
    var text = document.createElement('div');
    text.className = 'tf-loading-text';
    text.textContent = msg;
    box.appendChild(spinner);
    box.appendChild(text);
    overlay.appendChild(box);
    overlay.style.display = 'flex';
}

function hideLoading() {
    var overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.style.display = 'none';
}
