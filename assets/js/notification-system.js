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
                    position: fixed !important;
                    top: 0 !important;
                    left: 0 !important;
                    right: 0 !important;
                    bottom: 0 !important;
                    background: rgba(0, 0, 0, 0.5) !important;
                    z-index: 999999 !important;
                    display: none !important;
                    align-items: center !important;
                    justify-content: center !important;
                    padding: 20px !important;
                }
                
                .uw-modal-container.show {
                    display: flex !important;
                }
                
                .uw-modal {
                    background: #fff !important;
                    border-radius: 12px !important;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
                    max-width: 500px !important;
                    width: 100% !important;
                    max-height: 90vh !important;
                    overflow-y: auto !important;
                    transform: scale(0.95) !important;
                    transition: transform 0.2s ease !important;
                }
                
                .uw-modal-container.show .uw-modal {
                    transform: scale(1) !important;
                }
                
                .uw-modal-header {
                    padding: 24px 24px 10px 24px !important;
                    display: flex !important;
                    align-items: center !important;
                    gap: 12px !important;
                    border-bottom:1px solid #ddd;
                }
                
                .uw-modal-icon {
                    width: 40px !important;
                    height: 40px !important;
                    border-radius: 8px !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    flex-shrink: 0 !important;
                }
                
                .uw-modal-icon.success {
                    background: #d1fae5 !important;
                    color: #065f46 !important;
                }
                
                .uw-modal-icon.error {
                    background: #fee2e2 !important;
                    color: #dc2626 !important;
                }
                
                .uw-modal-icon.warning {
                    background: #fef3c7 !important;
                    color: #d97706 !important;
                }
                
                .uw-modal-icon.info {
                    background: #dbeafe !important;
                    color: #1d4ed8 !important;
                }
                
                .uw-modal-icon .dashicons {
                    font-size: 20px !important;
                    width: 20px !important;
                    height: 20px !important;
                }
                
                .uw-modal-title {
                    font-size: 18px !important;
                    font-weight: 600 !important;
                    color: #1f2937 !important;
                    margin: 0 !important;
                }
                
                .uw-modal-body {
                    padding: 16px 24px 24px 24px !important;
                }
                
                .uw-modal-message {
                    color: #6b7280 !important;
                    line-height: 1.5 !important;
                    margin: 0 0 20px 0 !important;
                }
                
                .uw-modal-actions {
                    display: flex !important;
                    gap: 12px !important;
                
                }
                
                .uw-modal-btn {
                    padding: 10px 20px !important;
                    border-radius: 6px !important;
                    font-size: 14px !important;
                    font-weight: 500 !important;
                    border: none !important;
                    cursor: pointer !important;
                    transition: all 0.2s ease !important;
                    display: flex !important;
                    align-items: center !important;
                    gap: 8px !important;
                }
                
                .uw-modal-btn-secondary {
                    background: #f3f4f6 !important;
                    color: #374151 !important;
                }
                
                .uw-modal-btn-secondary:hover {
                    background: #e5e7eb !important;
                }
                
                .uw-modal-btn-primary {
                    background: #3b82f6 !important;
                    color: #fff !important;
                }
                
                .uw-modal-btn-primary:hover {
                    background: #2563eb !important;
                }
                
                .uw-modal-btn-danger {
                    background: #ef4444 !important;
                    color: #fff !important;
                }
                
                .uw-modal-btn-danger:hover {
                    background: #dc2626 !important;
                }
                
                .uw-modal-btn .dashicons {
                    font-size: 16px !important;
                    width: 16px !important;
                    height: 16px !important;
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
