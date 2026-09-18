/**
 * assets/js/admin-enterprise.js
 * Centralized Enterprise Admin Framework JavaScript Utilities
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize Lucide Icons if available
    if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
        lucide.createIcons();
    }

    // 2. Auto-Dismiss Flash Alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-success, .alert-danger');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-6px)';
            setTimeout(function() {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 400);
        }, 5000);
    });

    // 3. Unsaved Changes Warning Detection
    let isFormDirty = false;
    const formsToTrack = document.querySelectorAll('form[data-track-unsaved="true"], form#settingsForm');
    formsToTrack.forEach(function(form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(function(input) {
            input.addEventListener('change', function() {
                isFormDirty = true;
            });
            input.addEventListener('input', function() {
                isFormDirty = true;
            });
        });

        form.addEventListener('submit', function() {
            isFormDirty = false;
        });
    });

    window.addEventListener('beforeunload', function(e) {
        if (isFormDirty) {
            const message = 'لديك تغييرات غير محفوظة، هل أنت متأكد من مغادرة الصفحة؟';
            e.preventDefault();
            e.returnValue = message;
            return message;
        }
    });
});

/**
 * Universal Action Confirmation Dialog
 * @param {Event} e 
 * @param {string} message 
 */
function confirmAction(e, message) {
    if (!confirm(message || 'هل أنت متأكد من تنفيذ هذا الإجراء؟')) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        return false;
    }
    return true;
}
