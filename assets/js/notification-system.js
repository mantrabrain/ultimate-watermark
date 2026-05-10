/**
 * Ultimate Watermark - Custom Notification System
 * 
 * Centralized toast notifications and confirmation modals
 * 
 * @package UltimateWatermark
 * @since 2.0.0
 */

class NotificationSystem {
    constructor() {
        this.toastContainer = null;
        this.modalContainer = null;
        this.init();
    }

    /**
     * Escape HTML to prevent XSS attacks
     * @param {string} text - Text to escape
     * @returns {string} Escaped HTML
     */
    escapeHtml(text) {
        if (typeof text !== 'string') {
            return '';
        }
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    init() {
        this.createToastContainer();
        this.createModalContainer();
    }

    /**
     * Create toast notification container
     */
    createToastContainer() {
        this.toastContainer = document.createElement('div');
        this.toastContainer.className = 'uw-toast-container';
        this.toastContainer.innerHTML = `
            <style>
                .uw-toast-container {
                    position: fixed;
                    top: 32px;
                    right: 20px;
                    z-index: 999999;
                    max-width: 400px;
                    pointer-events: none;
                }
                
                .uw-toast {
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    margin-bottom: 12px;
                    padding: 16px 20px;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    pointer-events: auto;
                    transform: translateX(100%);
                    transition: all 0.3s ease;
                    border-left: 4px solid;
                    min-width: 300px;
                }
                
                .uw-toast.show {
                    transform: translateX(0);
                }
                
                .uw-toast.success {
                    border-left-color: #10b981;
                }
                
                .uw-toast.error {
                    border-left-color: #ef4444;
                }
                
                .uw-toast.warning {
                    border-left-color: #f59e0b;
                }
                
                .uw-toast.info {
                    border-left-color: #3b82f6;
                }
                
                .uw-toast-icon {
                    width: 20px;
                    height: 20px;
                    flex-shrink: 0;
                }
                
                .uw-toast-content {
                    flex: 1;
                }
                
                .uw-toast-title {
                    font-weight: 600;
                    color: #1f2937;
                    margin: 0 0 4px 0;
                    font-size: 14px;
                }
                
                .uw-toast-message {
                    color: #6b7280;
                    margin: 0;
                    font-size: 13px;
                    line-height: 1.4;
                }
                
                .uw-toast-close {
                    background: none;
                    border: none;
                    color: #9ca3af;
                    cursor: pointer;
                    padding: 4px;
                    border-radius: 4px;
                    transition: all 0.2s ease;
                    flex-shrink: 0;
                }
                
                .uw-toast-close:hover {
                    background: #f3f4f6;
                    color: #6b7280;
                }
                
                .uw-toast-close .dashicons {
                    font-size: 16px;
                    width: 16px;
                    height: 16px;
                }
            </style>
        `;
        document.body.appendChild(this.toastContainer);
    }

    /**
     * Create modal container
     */
    createModalContainer() {
        // Add CSS to document head if not already added
        if (!document.getElementById('uw-notification-styles')) {
            const style = document.createElement('style');
            style.id = 'uw-notification-styles';
            style.textContent = `
                .uw-modal-container {
                    position: fixed;
                    inset: 0;
                    background: rgba(15, 23, 42, 0.55);
                    backdrop-filter: blur(2px);
                    -webkit-backdrop-filter: blur(2px);
                    z-index: 999999;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    opacity: 0;
                    transition: opacity 0.18s ease;
                }
                .uw-modal-container.show {
                    display: flex;
                    opacity: 1;
                }

                .uw-modal {
                    background: #ffffff;
                    border-radius: 14px;
                    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
                    width: 100%;
                    max-width: 460px;
                    max-height: 90vh;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    transform: translateY(8px) scale(0.98);
                    opacity: 0;
                    transition: transform 0.2s ease, opacity 0.2s ease;
                }
                .uw-modal-container.show .uw-modal {
                    transform: translateY(0) scale(1);
                    opacity: 1;
                }

                .uw-modal-header {
                    padding: 20px 24px;
                    display: flex;
                    align-items: center;
                    gap: 14px;
                    border-bottom: 1px solid #e5e7eb;
                    background: #ffffff;
                }

                .uw-modal-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 10px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }
                .uw-modal-icon.success { background: #d1fae5; color: #047857; }
                .uw-modal-icon.error   { background: #fee2e2; color: #b91c1c; }
                .uw-modal-icon.warning { background: #fef3c7; color: #b45309; }
                .uw-modal-icon.info    { background: #dbeafe; color: #1d4ed8; }
                .uw-modal-icon .dashicons {
                    font-size: 20px;
                    width: 20px;
                    height: 20px;
                }

                .uw-modal-title {
                    flex: 1;
                    font-size: 16px;
                    font-weight: 700;
                    color: #111827;
                    margin: 0;
                    line-height: 1.3;
                    letter-spacing: -0.005em;
                }

                .uw-modal-body {
                    padding: 20px 24px;
                    overflow-y: auto;
                    flex: 1;
                }

                .uw-modal-message {
                    color: #4b5563;
                    font-size: 14px;
                    line-height: 1.55;
                    margin: 0;
                }

                /* Action row pinned to a footer with a soft separator. */
                .uw-modal-actions {
                    padding: 14px 20px;
                    background: #f9fafb;
                    border-top: 1px solid #e5e7eb;
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 8px;
                    flex-wrap: wrap;
                    margin: 16px -24px -20px;
                }

                .uw-modal-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                    height: 36px;
                    padding: 0 16px;
                    font-size: 13px;
                    font-weight: 600;
                    line-height: 1;
                    border: 1px solid transparent;
                    border-radius: 8px;
                    cursor: pointer;
                    transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, transform 0.05s ease;
                    text-decoration: none;
                    white-space: nowrap;
                }

                .uw-modal-btn:focus-visible {
                    outline: none;
                    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
                }

                /* Cancel / secondary — sits on the LEFT visually because of order:0 */
                .uw-modal-btn-secondary {
                    order: 0;
                    background: #ffffff;
                    color: #374151;
                    border-color: #d1d5db;
                }
                .uw-modal-btn-secondary:hover {
                    background: #f3f4f6;
                    border-color: #9ca3af;
                    color: #111827;
                }

                /* Optional third action — sits between cancel and confirm */
                .uw-modal-btn[data-action="third"] {
                    order: 1;
                    background: #ffffff;
                    color: #b91c1c;
                    border-color: #fecaca;
                }
                .uw-modal-btn[data-action="third"]:hover {
                    background: #fef2f2;
                    border-color: #fca5a5;
                }

                /* Primary / confirm — sits on the FAR RIGHT */
                .uw-modal-btn-primary {
                    order: 2;
                    background: #2563eb;
                    color: #ffffff;
                    border-color: #2563eb;
                    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.25);
                }
                .uw-modal-btn-primary:hover {
                    background: #1d4ed8;
                    border-color: #1d4ed8;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.32);
                }

                .uw-modal-btn-danger {
                    order: 2;
                    background: #dc2626;
                    color: #ffffff;
                    border-color: #dc2626;
                    box-shadow: 0 1px 3px rgba(220, 38, 38, 0.28);
                }
                .uw-modal-btn-danger:hover {
                    background: #b91c1c;
                    border-color: #b91c1c;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.32);
                }

                .uw-modal-btn .dashicons {
                    font-size: 14px;
                    width: 14px;
                    height: 14px;
                    line-height: 1;
                }

                @media (max-width: 480px) {
                    .uw-modal-actions {
                        flex-direction: column-reverse;
                        align-items: stretch;
                    }
                    .uw-modal-btn { width: 100%; }
                }
            `;
            document.head.appendChild(style);
        }

        this.modalContainer = document.createElement('div');
        this.modalContainer.className = 'uw-modal-container';
        document.body.appendChild(this.modalContainer);
    }

    /**
     * Show toast notification
     * @param {string} type - success, error, warning, info
     * @param {string} title - Toast title
     * @param {string} message - Toast message
     * @param {number} duration - Auto-hide duration in ms (0 = no auto-hide)
     * @param {object} options - Additional options
     */
    showToast(type, title, message, duration = 5000, options = {}) {
        const toast = document.createElement('div');
        toast.className = `uw-toast ${type}`;
        
        const iconMap = {
            success: 'dashicons-yes-alt',
            error: 'dashicons-dismiss',
            warning: 'dashicons-warning',
            info: 'dashicons-info'
        };
        
        // Allow custom icon override
        const icon = options.icon || iconMap[type] || iconMap.info;
        
        // Security: Escape HTML to prevent XSS
        toast.innerHTML = `
            <div class="uw-toast-icon">
                <span class="dashicons ${this.escapeHtml(icon)}"></span>
            </div>
            <div class="uw-toast-content">
                <div class="uw-toast-title">${this.escapeHtml(title)}</div>
                <div class="uw-toast-message">${this.escapeHtml(message)}</div>
            </div>
            <button class="uw-toast-close" onclick="this.parentElement.remove()">
                <span class="dashicons dashicons-dismiss"></span>
            </button>
        `;
        
        this.toastContainer.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        return toast;
    }

    /**
     * Show success toast
     * @param {string} title - Toast title
     * @param {string} message - Toast message
     * @param {number} duration - Auto-hide duration in ms
     * @param {object} options - Additional options
     */
    success(title, message, duration = 5000, options = {}) {
        return this.showToast('success', title, message, duration, options);
    }

    /**
     * Show error toast
     * @param {string} title - Toast title
     * @param {string} message - Toast message
     * @param {number} duration - Auto-hide duration in ms
     * @param {object} options - Additional options
     */
    error(title, message, duration = 7000, options = {}) {
        return this.showToast('error', title, message, duration, options);
    }

    /**
     * Show warning toast
     * @param {string} title - Toast title
     * @param {string} message - Toast message
     * @param {number} duration - Auto-hide duration in ms
     * @param {object} options - Additional options
     */
    warning(title, message, duration = 6000, options = {}) {
        return this.showToast('warning', title, message, duration, options);
    }

    /**
     * Show info toast
     * @param {string} title - Toast title
     * @param {string} message - Toast message
     * @param {number} duration - Auto-hide duration in ms
     * @param {object} options - Additional options
     */
    info(title, message, duration = 5000, options = {}) {
        return this.showToast('info', title, message, duration, options);
    }

    /**
     * Show confirmation modal
     */
    confirm(options) {
        return new Promise((resolve) => {
            const {
                title = 'Confirm Action',
                message = 'Are you sure you want to proceed?',
                type = 'warning',
                confirmText = 'Confirm',
                cancelText = 'Cancel',
                confirmButtonType = 'primary',
                // Support for third button
                thirdButtonText = null,
                thirdButtonType = 'secondary',
                thirdButtonAction = 'third'
            } = options;

            const iconMap = {
                success: 'dashicons-yes-alt',
                error: 'dashicons-dismiss',
                warning: 'dashicons-warning',
                info: 'dashicons-info'
            };

            // Build buttons HTML - Security: Escape HTML to prevent XSS
            let buttonsHTML = `
                <button class="uw-modal-btn uw-modal-btn-secondary" data-action="cancel">
                    <span class="dashicons dashicons-no-alt"></span>
                    ${this.escapeHtml(cancelText)}
                </button>
                <button class="uw-modal-btn uw-modal-btn-${this.escapeHtml(confirmButtonType)}" data-action="confirm">
                    <span class="dashicons dashicons-yes-alt"></span>
                    ${this.escapeHtml(confirmText)}
                </button>
            `;

            // Add third button if specified
            if (thirdButtonText) {
                buttonsHTML += `
                    <button class="uw-modal-btn uw-modal-btn-${this.escapeHtml(thirdButtonType)}" data-action="${this.escapeHtml(thirdButtonAction)}">
                        <span class="dashicons dashicons-trash"></span>
                        ${this.escapeHtml(thirdButtonText)}
                    </button>
                `;
            }

            const modal = document.createElement('div');
            modal.className = 'uw-modal';
            // Security: Escape HTML to prevent XSS
            modal.innerHTML = `
                <div class="uw-modal-header">
                    <div class="uw-modal-icon ${this.escapeHtml(type)}">
                        <span class="dashicons ${this.escapeHtml(iconMap[type] || '')}"></span>
                    </div>
                    <h3 class="uw-modal-title">${this.escapeHtml(title)}</h3>
                </div>
                <div class="uw-modal-body">
                    <p class="uw-modal-message">${this.escapeHtml(message)}</p>
                    <div class="uw-modal-actions">
                        ${buttonsHTML}
                    </div>
                </div>
            `;

            // Clear existing modal content
            this.modalContainer.innerHTML = '';
            this.modalContainer.appendChild(modal);
            this.modalContainer.classList.add('show');

            // Handle button clicks
            const handleClick = (e) => {
                const action = e.target.closest('[data-action]')?.dataset.action;
                if (action) {
                    this.modalContainer.classList.remove('show');
                    setTimeout(() => {
                        this.modalContainer.innerHTML = '';
                    }, 200);
                    resolve(action);
                }
            };

            // Handle escape key
            const handleKeydown = (e) => {
                if (e.key === 'Escape') {
                    this.modalContainer.classList.remove('show');
                    setTimeout(() => {
                        this.modalContainer.innerHTML = '';
                    }, 200);
                    resolve('cancel');
                    document.removeEventListener('keydown', handleKeydown);
                }
            };

            modal.addEventListener('click', handleClick);
            document.addEventListener('keydown', handleKeydown);
        });
    }

    /**
     * Show alert modal
     */
    alert(options) {
        return new Promise((resolve) => {
            const {
                title = 'Alert',
                message = 'Please take note of this information.',
                type = 'info',
                buttonText = 'OK'
            } = options;

            const iconMap = {
                success: 'dashicons-yes-alt',
                error: 'dashicons-dismiss',
                warning: 'dashicons-warning',
                info: 'dashicons-info'
            };

            const modal = document.createElement('div');
            modal.className = 'uw-modal';
            // Security: Escape HTML to prevent XSS
            modal.innerHTML = `
                <div class="uw-modal-header">
                    <div class="uw-modal-icon ${this.escapeHtml(type)}">
                        <span class="dashicons ${this.escapeHtml(iconMap[type] || '')}"></span>
                    </div>
                    <h3 class="uw-modal-title">${this.escapeHtml(title)}</h3>
                </div>
                <div class="uw-modal-body">
                    <p class="uw-modal-message">${this.escapeHtml(message)}</p>
                    <div class="uw-modal-actions">
                        <button class="uw-modal-btn uw-modal-btn-primary" data-action="ok">
                            <span class="dashicons dashicons-yes-alt"></span>
                            ${this.escapeHtml(buttonText)}
                        </button>
                    </div>
                </div>
            `;

            // Clear existing modal content
            this.modalContainer.innerHTML = '';
            this.modalContainer.appendChild(modal);
            this.modalContainer.classList.add('show');

            // Handle button click
            const handleClick = (e) => {
                if (e.target.closest('[data-action="ok"]')) {
                    this.modalContainer.classList.remove('show');
                    setTimeout(() => {
                        this.modalContainer.innerHTML = '';
                    }, 200);
                    resolve(true);
                }
            };

            // Handle escape key
            const handleKeydown = (e) => {
                if (e.key === 'Escape') {
                    this.modalContainer.classList.remove('show');
                    setTimeout(() => {
                        this.modalContainer.innerHTML = '';
                    }, 200);
                    resolve(true);
                    document.removeEventListener('keydown', handleKeydown);
                }
            };

            modal.addEventListener('click', handleClick);
            document.addEventListener('keydown', handleKeydown);
        });
    }
}

// Create global instance
window.UWNotifications = new NotificationSystem();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationSystem;
}
