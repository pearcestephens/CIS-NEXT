/**
 * UI Helper Module
 * File: assets/js/modules/ui.js
 * Purpose: Toast notifications, modals, focus traps, and UI utilities
 */

class UIHelper {
    constructor() {
        this.toastContainer = null;
        this.initializeToastContainer();
    }

    /**
     * Initialize toast container
     */
    initializeToastContainer() {
        this.toastContainer = document.getElementById('adminToastContainer');
        if (!this.toastContainer) {
            this.toastContainer = document.createElement('div');
            this.toastContainer.id = 'adminToastContainer';
            this.toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            this.toastContainer.style.zIndex = '1055';
            document.body.appendChild(this.toastContainer);
        }
    }

    /**
     * Show alert/toast notification
     */
    showAlert(message, type = 'info', ttl = 5000) {
        const toastId = `toast-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.id = toastId;

        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${this.escapeHtml(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                        data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        this.toastContainer.appendChild(toast);

        const bsToast = new window.bootstrap.Toast(toast, {
            autohide: ttl > 0,
            delay: ttl
        });

        bsToast.show();

        // Clean up after toast is hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });

        return toastId;
    }

    /**
     * Show success notification
     */
    showSuccess(message, ttl = 4000) {
        return this.showAlert(message, 'success', ttl);
    }

    /**
     * Show error notification
     */
    showError(message, ttl = 8000) {
        return this.showAlert(message, 'danger', ttl);
    }

    /**
     * Show warning notification
     */
    showWarning(message, ttl = 6000) {
        return this.showAlert(message, 'warning', ttl);
    }

    /**
     * Show info notification
     */
    showInfo(message, ttl = 5000) {
        return this.showAlert(message, 'info', ttl);
    }

    /**
     * Show confirmation modal
     */
    showConfirm(title, message, options = {}) {
        return new Promise((resolve) => {
            const modalId = `confirm-modal-${Date.now()}`;
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = modalId;
            modal.setAttribute('tabindex', '-1');
            modal.setAttribute('aria-labelledby', `${modalId}-title`);
            modal.setAttribute('aria-hidden', 'true');

            const confirmText = options.confirmText || 'Confirm';
            const cancelText = options.cancelText || 'Cancel';
            const confirmClass = options.confirmClass || 'btn-primary';
            const cancelClass = options.cancelClass || 'btn-secondary';

            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="${modalId}-title">${this.escapeHtml(title)}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            ${this.escapeHtml(message)}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn ${cancelClass}" data-bs-dismiss="modal">${this.escapeHtml(cancelText)}</button>
                            <button type="button" class="btn ${confirmClass}" id="${modalId}-confirm">${this.escapeHtml(confirmText)}</button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            const bsModal = new window.bootstrap.Modal(modal);
            
            // Focus management
            modal.addEventListener('shown.bs.modal', () => {
                const confirmBtn = modal.querySelector(`#${modalId}-confirm`);
                confirmBtn?.focus();
            });

            // Handle confirm action
            modal.querySelector(`#${modalId}-confirm`).addEventListener('click', () => {
                bsModal.hide();
                resolve(true);
            });

            // Handle dismiss/cancel
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
                resolve(false);
            });

            bsModal.show();
        });
    }

    /**
     * Show loading indicator
     */
    showLoading(element, text = 'Loading...') {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }

        if (!element) return null;

        element.classList.add('loading');
        
        const originalContent = element.innerHTML;
        element.innerHTML = `
            <div class="d-flex align-items-center justify-content-center">
                <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                <span>${this.escapeHtml(text)}</span>
            </div>
        `;

        return {
            hide: () => {
                element.classList.remove('loading');
                element.innerHTML = originalContent;
            }
        };
    }

    /**
     * Create focus trap for accessibility
     */
    createFocusTrap(element) {
        const focusableElements = element.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        const trapFocus = (e) => {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        lastElement.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        firstElement.focus();
                        e.preventDefault();
                    }
                }
            }
        };

        element.addEventListener('keydown', trapFocus);

        return {
            activate: () => {
                firstElement?.focus();
                document.body.classList.add('focus-trap-active');
            },
            deactivate: () => {
                element.removeEventListener('keydown', trapFocus);
                document.body.classList.remove('focus-trap-active');
            }
        };
    }

    /**
     * Debounce function calls
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Throttle function calls
     */
    throttle(func, limit) {
        let inThrottle;
        return function executedFunction(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Announce to screen readers
     */
    announce(message, priority = 'polite') {
        const announcer = document.createElement('div');
        announcer.setAttribute('aria-live', priority);
        announcer.setAttribute('aria-atomic', 'true');
        announcer.className = 'sr-only';
        announcer.textContent = message;

        document.body.appendChild(announcer);

        // Remove after announcement
        setTimeout(() => {
            announcer.remove();
        }, 1000);
    }

    /**
     * Format file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Format date/time
     */
    formatDateTime(date, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            ...options
        };
        
        return new Intl.DateTimeFormat('en-NZ', defaultOptions).format(new Date(date));
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, (m) => map[m]);
    }

    /**
     * Copy text to clipboard
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showSuccess('Copied to clipboard');
        } catch (err) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                this.showSuccess('Copied to clipboard');
            } catch (fallbackErr) {
                this.showError('Failed to copy to clipboard');
            }
            document.body.removeChild(textArea);
        }
    }

    /**
     * Initialize tooltips and popovers
     */
    initializeTooltips() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new window.bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize Bootstrap popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new window.bootstrap.Popover(popoverTriggerEl);
        });
    }
}

export default new UIHelper();
