/**
 * Ultimate Watermark - Backup Management Page JavaScript
 * 
 * @package UltimateWatermark
 * @since 2.0.0
 */
/* i18n: falls back to the original text when wp.i18n is unavailable. */
window.wp = window.wp || {};
window.wp.i18n = window.wp.i18n || {
    __: function (text) { return text; },
    _n: function (single, plural, number) { return number === 1 ? single : plural; },
    sprintf: function (format) {
        var args = Array.prototype.slice.call(arguments, 1), i = 0;
        return String(format).replace(/%(\d+\$)?[sd]/g, function (match, position) {
            return String(position ? args[parseInt(position, 10) - 1] : args[i++]);
        });
    }
};

class BackupPageManager {
    constructor() {
        this.perPage = 10;
        this.totalPages = 0;
        this.total = 0;
        // Get initial page from URL parameter
        this.currentPage = this.getPageFromUrl();
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadBackups(this.currentPage);
    }

    getPageFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        // Use 'paged' instead of 'page' to avoid conflict with WordPress admin page parameter
        const page = parseInt(urlParams.get('paged')) || 1;
        return Math.max(1, page);
    }

    updateUrlParameter(page) {
        const url = new URL(window.location);
        // Use 'paged' instead of 'page' to avoid conflict with WordPress admin page parameter
        if (page > 1) {
            url.searchParams.set('paged', page);
        } else {
            url.searchParams.delete('paged');
        }
        window.history.pushState({ paged: page }, '', url);
    }

    bindEvents() {
        // Delete backup button
        document.addEventListener('click', (e) => {
            if (e.target.closest('.delete-backup-btn')) {
                e.preventDefault();
                this.handleDeleteBackup(e.target.closest('.delete-backup-btn'));
            }
        });

        // Restore backup button
        document.addEventListener('click', (e) => {
            if (e.target.closest('.restore-backup-btn')) {
                e.preventDefault();
                this.handleRestoreBackup(e.target.closest('.restore-backup-btn'));
            }
        });

        // Toggle children button
        document.addEventListener('click', (e) => {
            if (e.target.closest('.uw-toggle-children')) {
                e.preventDefault();
                this.handleToggleChildren(e.target.closest('.uw-toggle-children'));
            }
        });

        // Bulk actions
        document.addEventListener('click', (e) => {
            if (e.target.id === 'select-all-header') {
                this.handleSelectAll(e.target.checked);
            }
            
            if (e.target.closest && e.target.closest('#bulk-restore-btn')) {
                e.preventDefault();
                this.handleBulkRestore();
            }
            
            if (e.target.closest && e.target.closest('#bulk-delete-btn')) {
                e.preventDefault();
                this.handleBulkDelete();
            }
        });

        // Individual checkbox changes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('uw-backup-checkbox')) {
                this.updateBulkButtons();
            }
        });

        // Pagination clicks
        document.addEventListener('click', (e) => {
            if (e.target.closest('.uw-pagination-prev')) {
                e.preventDefault();
                if (this.currentPage > 1) {
                    this.loadBackups(this.currentPage - 1);
                }
            }
            
            if (e.target.closest('.uw-pagination-next')) {
                e.preventDefault();
                if (this.currentPage < this.totalPages) {
                    this.loadBackups(this.currentPage + 1);
                }
            }
            
            if (e.target.closest('.uw-pagination-number')) {
                e.preventDefault();
                const page = parseInt(e.target.closest('.uw-pagination-number').dataset.page);
                if (page && page !== this.currentPage) {
                    this.loadBackups(page);
                }
            }
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', (e) => {
            const page = this.getPageFromUrl();
            if (page !== this.currentPage) {
                this.loadBackups(page);
            }
        });
    }

    loadBackups(page) {
        const tbody = document.getElementById('uw-backups-tbody');
        const paginationContainer = document.getElementById('uw-backups-pagination');
        const paginationContainerTop = document.getElementById('uw-backups-pagination-top');
        const emptyState = document.getElementById('uw-empty-backups');
        const loadingRow = tbody.querySelector('.uw-loading-row');
        
        if (!loadingRow) {
            // Show loading state
            tbody.innerHTML = '<tr class="uw-loading-row"><td colspan="5" class="uw-loading-cell"><div class="uw-loading-spinner"><span class="dashicons dashicons-update"></span><span>Loading backups...</span></div></td></tr>';
        }
        
        // Hide pagination and empty state
        if (paginationContainer) paginationContainer.style.display = 'none';
        if (paginationContainerTop) paginationContainerTop.style.display = 'none';
        if (emptyState) emptyState.style.display = 'none';
        
        // Update URL parameter (without triggering popstate)
        this.updateUrlParameter(page);
        
        // Make AJAX request
        const formData = new FormData();
        formData.append('action', 'ultimate_watermark_get_paginated_backups');
        formData.append('page', page);
        formData.append('per_page', this.perPage);
        formData.append('nonce', ultimateWatermarkBackup.nonce);
        
        fetch(ultimateWatermarkBackup.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.renderBackups(data.data.backups);
                this.currentPage = data.data.current_page;
                this.totalPages = data.data.total_pages;
                this.total = data.data.total;
                this.renderPagination();
                this.updateBackupCount();
                
                // Scroll to top of table when page changes
                if (paginationContainerTop) {
                    paginationContainerTop.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="uw-error-cell">Error loading backups.</td></tr>';
            }
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="5" class="uw-error-cell">Error loading backups.</td></tr>';
        });
    }

    renderBackups(backups) {
        const tbody = document.getElementById('uw-backups-tbody');
        const emptyState = document.getElementById('uw-empty-backups');
        
        if (!backups || backups.length === 0) {
            tbody.innerHTML = '';
            if (emptyState) emptyState.style.display = 'block';
            return;
        }
        
        // Hide empty state
        if (emptyState) emptyState.style.display = 'none';
        
        // Render backup rows
        let html = '';
        backups.forEach(backup => {
            html += this.renderBackupRow(backup);
        });
        tbody.innerHTML = html;
        
        // Re-bind events for new rows
        this.updateBulkButtons();
    }

    renderBackupRow(backup) {
        const additionalSizes = backup.additional_sizes || 0;
        const totalSize = backup.total_size || backup.size;
        
        let html = '<tr class="uw-backup-row" data-attachment-id="' + backup.id + '">';
        
        // Checkbox column
        html += '<td class="uw-col-checkbox">';
        html += '<input type="checkbox" class="uw-checkbox uw-backup-checkbox" value="' + backup.id + '">';
        html += '</td>';
        
        // File name column
        html += '<td class="uw-col-name">';
        html += '<div class="uw-file-info">';
        html += '<div class="uw-file-thumbnail">';
        html += '<img src="' + this.escapeHtml(backup.url + (backup.url.indexOf('?') === -1 ? '?' : '&') + 'size=thumb') + '" alt="' + this.escapeHtml(backup.title) + '" loading="lazy" class="uw-thumbnail-image" data-image-url="' + this.escapeHtml(backup.url) + '" data-image-title="' + this.escapeHtml(backup.title) + '">';
        html += '</div>';
        html += '<div class="uw-file-details">';
        html += '<div class="uw-file-name">' + this.escapeHtml(backup.title) + '</div>';
        html += '<div class="uw-file-path">' + this.escapeHtml(backup.backup_path.split('/').pop()) + '</div>';
        
        if (additionalSizes > 0) {
            html += '<div class="uw-additional-sizes">';
            html += '<span class="uw-size-count">+' + additionalSizes + ' ' + (additionalSizes === 1 ? 'additional size' : 'additional sizes') + '</span>';
            html += '<button type="button" class="uw-toggle-children" data-attachment-id="' + backup.id + '">';
            html += '<span class="dashicons dashicons-arrow-down-alt2"></span>';
            html += '</button>';
            html += '</div>';
        }
        
        html += '</div>';
        html += '</div>';
        html += '</td>';
        
        // Size column
        html += '<td class="uw-col-size">';
        html += '<span class="uw-file-size">' + this.formatSize(totalSize) + '</span>';
        if (additionalSizes > 0) {
            html += '<div class="uw-size-breakdown">';
            html += '<span class="uw-main-size">' + this.formatSize(backup.size) + ' main</span>';
            html += '<span class="uw-additional-size">+' + this.formatSize(totalSize - backup.size) + ' additional</span>';
            html += '</div>';
        }
        html += '</td>';
        
        // Date column
        html += '<td class="uw-col-date">';
        html += '<div class="uw-date-info">';
        html += '<div class="uw-date-relative">' + this.getRelativeTime(backup.backup_created) + ' ago</div>';
        html += '<div class="uw-date-absolute">' + this.formatDate(backup.backup_created) + '</div>';
        html += '</div>';
        html += '</td>';
        
        // Actions column
        html += '<td class="uw-col-actions">';
        html += '<div class="uw-row-actions">';
        html += '<a href="' + this.escapeHtml(backup.url) + '" class="uw-action-btn uw-action-view" target="_blank" title="View backup">';
        html += '<span class="dashicons dashicons-visibility"></span>';
        html += '</a>';
        html += '<button type="button" class="uw-action-btn uw-action-restore restore-backup-btn" data-attachment-id="' + backup.id + '" title="Restore from backup">';
        html += '<span class="dashicons dashicons-undo"></span>';
        html += '</button>';
        html += '<a href="' + ultimateWatermarkBackup.adminUrl + 'post.php?post=' + backup.id + '&action=edit" class="uw-action-btn uw-action-edit" title="Edit original">';
        html += '<span class="dashicons dashicons-edit"></span>';
        html += '</a>';
        html += '<button type="button" class="uw-action-btn uw-action-delete delete-backup-btn" data-attachment-id="' + backup.id + '" title="Delete backup">';
        html += '<span class="dashicons dashicons-trash"></span>';
        html += '</button>';
        html += '</div>';
        html += '</td>';
        
        html += '</tr>';
        
        return html;
    }

    renderPagination() {
        const paginationContainer = document.getElementById('uw-backups-pagination');
        const paginationContainerTop = document.getElementById('uw-backups-pagination-top');
        
        // Render both top and bottom pagination
        [paginationContainer, paginationContainerTop].forEach(container => {
            if (!container) return;
            
            if (this.totalPages <= 1) {
                container.style.display = 'none';
                return;
            }
            
            container.style.display = 'flex';
            
            // Update pagination info
            const infoText = container.querySelector('.uw-pagination-text');
            if (infoText) {
                const start = (this.currentPage - 1) * this.perPage + 1;
                const end = Math.min(this.currentPage * this.perPage, this.total);
                infoText.textContent = `Showing ${start}-${end} of ${this.total} backups`;
            }
            
            // Update prev/next buttons
            const prevBtn = container.querySelector('.uw-pagination-prev');
            const nextBtn = container.querySelector('.uw-pagination-next');
            if (prevBtn) prevBtn.disabled = this.currentPage === 1;
            if (nextBtn) nextBtn.disabled = this.currentPage >= this.totalPages;
            
            // Render page numbers
            const numbersContainer = container.querySelector('.uw-pagination-numbers');
            if (numbersContainer) {
                let numbersHtml = '';
                const maxVisible = 7;
                let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
                let endPage = Math.min(this.totalPages, startPage + maxVisible - 1);
                
                if (endPage - startPage < maxVisible - 1) {
                    startPage = Math.max(1, endPage - maxVisible + 1);
                }
                
                if (startPage > 1) {
                    numbersHtml += '<button type="button" class="uw-pagination-number" data-page="1">1</button>';
                    if (startPage > 2) {
                        numbersHtml += '<span class="uw-pagination-ellipsis">...</span>';
                    }
                }
                
                for (let i = startPage; i <= endPage; i++) {
                    const isActive = i === this.currentPage ? ' active' : '';
                    numbersHtml += '<button type="button" class="uw-pagination-number' + isActive + '" data-page="' + i + '">' + i + '</button>';
                }
                
                if (endPage < this.totalPages) {
                    if (endPage < this.totalPages - 1) {
                        numbersHtml += '<span class="uw-pagination-ellipsis">...</span>';
                    }
                    numbersHtml += '<button type="button" class="uw-pagination-number" data-page="' + this.totalPages + '">' + this.totalPages + '</button>';
                }
                
                numbersContainer.innerHTML = numbersHtml;
            }
        });
    }

    updateBackupCount() {
        const countElement = document.querySelector('.uw-backup-count');
        if (countElement) {
            countElement.textContent = this.total + ' ' + (this.total === 1 ? 'file' : 'files');
        }
    }

    formatSize(bytes) {
        if (bytes >= 1024 * 1024) {
            const mb = bytes / (1024 * 1024);
            return mb >= 100 ? Math.round(mb) + ' MB' : mb.toFixed(2) + ' MB';
        } else if (bytes >= 1024) {
            const kb = bytes / 1024;
            return kb >= 100 ? Math.round(kb) + ' KB' : kb.toFixed(2) + ' KB';
        } else {
            return bytes + ' B';
        }
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return months[date.getMonth()] + ' ' + date.getDate() + ', ' + date.getFullYear();
    }

    getRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return diff + ' second' + (diff !== 1 ? 's' : '');
        if (diff < 3600) return Math.floor(diff / 60) + ' minute' + (Math.floor(diff / 60) !== 1 ? 's' : '');
        if (diff < 86400) return Math.floor(diff / 3600) + ' hour' + (Math.floor(diff / 3600) !== 1 ? 's' : '');
        if (diff < 604800) return Math.floor(diff / 86400) + ' day' + (Math.floor(diff / 86400) !== 1 ? 's' : '');
        if (diff < 2592000) return Math.floor(diff / 604800) + ' week' + (Math.floor(diff / 604800) !== 1 ? 's' : '');
        if (diff < 31536000) return Math.floor(diff / 2592000) + ' month' + (Math.floor(diff / 2592000) !== 1 ? 's' : '');
        return Math.floor(diff / 31536000) + ' year' + (Math.floor(diff / 31536000) !== 1 ? 's' : '');
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    handleDeleteBackup(button) {
        const attachmentId = button.getAttribute('data-attachment-id');
        const backupRow = button.closest('.uw-backup-row');
        
        if (!attachmentId) {
            if (typeof UWNotifications !== 'undefined') {
                UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), wp.i18n.__('Invalid backup item.', 'ultimate-watermark'));
            } else {
                alert(wp.i18n.__('Invalid backup item.', 'ultimate-watermark'));
            }
            return;
        }

        // Check if UWNotifications is available
        if (typeof UWNotifications === 'undefined') {
            if (confirm(wp.i18n.__('Are you sure you want to delete this backup? This action cannot be undone.', 'ultimate-watermark'))) {
                this.confirmDeleteBackup(attachmentId, backupRow);
            }
            return;
        }

        // Show custom confirmation
        try {
            UWNotifications.confirm({
                title: wp.i18n.__('Delete Backup', 'ultimate-watermark'),
                message: wp.i18n.__('Are you sure you want to delete this backup? This action cannot be undone.', 'ultimate-watermark'),
                type: 'error',
                confirmText: wp.i18n.__('Delete', 'ultimate-watermark'),
                cancelText: wp.i18n.__('Cancel', 'ultimate-watermark'),
                confirmButtonType: 'danger'
            }).then(action => {
                if (action === 'confirm') {
                    this.confirmDeleteBackup(attachmentId, backupRow);
                }
            }).catch(() => {
                // Fallback to native confirm
                if (confirm(wp.i18n.__('Are you sure you want to delete this backup? This action cannot be undone.', 'ultimate-watermark'))) {
                    this.confirmDeleteBackup(attachmentId, backupRow);
                }
            });
        } catch {
            // Fallback to native confirm
            if (confirm(wp.i18n.__('Are you sure you want to delete this backup? This action cannot be undone.', 'ultimate-watermark'))) {
                this.confirmDeleteBackup(attachmentId, backupRow);
            }
        }
    }

    confirmDeleteBackup(attachmentId, backupRow) {
        
        // Show loading state
        this.showLoadingState(backupRow);

        // Make AJAX request
        const formData = new FormData();
        formData.append('action', 'ultimate_watermark_delete_backup');
        formData.append('attachment_id', attachmentId);
        formData.append('nonce', ultimateWatermarkBackup.nonce);

        fetch(ultimateWatermarkBackup.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            this.hideLoadingState(backupRow);
            
            if (data.success) {
                if (typeof UWNotifications !== 'undefined') {
                    UWNotifications.success(wp.i18n.__('Success', 'ultimate-watermark'), data.data.message || wp.i18n.__('Backup deleted successfully.', 'ultimate-watermark'));
                } else {
                    alert(data.data.message || wp.i18n.__('Backup deleted successfully.', 'ultimate-watermark'));
                }
                // Remove the backup row with animation
                this.removeBackupRow(backupRow);
            } else {
                if (typeof UWNotifications !== 'undefined') {
                    UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), data.data.message || wp.i18n.__('Failed to delete backup.', 'ultimate-watermark'));
                } else {
                    alert(data.data.message || wp.i18n.__('Failed to delete backup.', 'ultimate-watermark'));
                }
            }
        })
        .catch(() => {
            this.hideLoadingState(backupRow);
            if (typeof UWNotifications !== 'undefined') {
                UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), wp.i18n.__('An error occurred while deleting the backup.', 'ultimate-watermark'));
            } else {
                alert(wp.i18n.__('An error occurred while deleting the backup.', 'ultimate-watermark'));
            }
        });
    }

    handleRestoreBackup(button) {
        const attachmentId = button.getAttribute('data-attachment-id');
        const backupRow = button.closest('.uw-backup-row');
        
        if (!attachmentId) {
            if (typeof UWNotifications !== 'undefined') {
                UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), wp.i18n.__('Invalid backup item.', 'ultimate-watermark'));
            } else {
                alert(wp.i18n.__('Invalid backup item.', 'ultimate-watermark'));
            }
            return;
        }

        // Check if UWNotifications is available
        if (typeof UWNotifications === 'undefined') {
            if (confirm(wp.i18n.__('Are you sure you want to restore this image from backup? This will replace the current watermarked image with the original backup.', 'ultimate-watermark'))) {
                this.confirmRestoreBackup(attachmentId, backupRow, false);
            }
            return;
        }

        // Show custom confirmation with 3 buttons using UWNotifications
        UWNotifications.confirm({
            title: wp.i18n.__('Restore Image', 'ultimate-watermark'),
            message: wp.i18n.__('What would you like to do with this backup?', 'ultimate-watermark'),
            type: 'warning',
            confirmText: wp.i18n.__('Restore Only', 'ultimate-watermark'),
            cancelText: wp.i18n.__('Cancel', 'ultimate-watermark'),
            confirmButtonType: 'primary',
            thirdButtonText: wp.i18n.__('Restore & Delete', 'ultimate-watermark'),
            thirdButtonType: 'danger',
            thirdButtonAction: 'restore_and_delete'
        }).then(action => {
            if (action === 'confirm') {
                this.confirmRestoreBackup(attachmentId, backupRow, false);
            } else if (action === 'restore_and_delete') {
                this.confirmRestoreBackup(attachmentId, backupRow, true);
            }
        });
    }


    confirmRestoreBackup(attachmentId, backupRow, deleteAfterRestore = false) {
        // Show loading state
        this.showLoadingState(backupRow);

        // Make AJAX request
        const formData = new FormData();
        formData.append('action', 'ultimate_watermark_restore_backup');
        formData.append('attachment_id', attachmentId);
        formData.append('delete_after_restore', deleteAfterRestore ? '1' : '0');
        formData.append('nonce', ultimateWatermarkBackup.nonce);

        fetch(ultimateWatermarkBackup.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            this.hideLoadingState(backupRow);
            
            if (data.success) {
                const message = deleteAfterRestore 
                    ? wp.i18n.__('Image restored successfully and backup deleted.', 'ultimate-watermark') 
                    : wp.i18n.__('Image restored successfully from backup.', 'ultimate-watermark');
                UWNotifications.success(wp.i18n.__('Success', 'ultimate-watermark'), data.data.message || message);
                
                // Remove the backup row since it's no longer needed
                this.removeBackupRow(backupRow);
            } else {
                UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), data.data.message || wp.i18n.__('Failed to restore image from backup.', 'ultimate-watermark'));
            }
        })
        .catch(() => {
            this.hideLoadingState(backupRow);
            UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), wp.i18n.__('An error occurred while restoring the backup.', 'ultimate-watermark'));
        });
    }

    handleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.uw-backup-checkbox');
        const headerCheckbox = document.getElementById('select-all-header');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
        });
        
        if (headerCheckbox) headerCheckbox.checked = checked;
        
        this.updateBulkButtons();
    }

    handleToggleChildren(button) {
        const attachmentId = button.dataset.attachmentId;
        const childRows = document.querySelectorAll(`.uw-backup-child-row[data-parent-id="${attachmentId}"]`);
        const isExpanded = button.classList.contains('expanded');
        
        if (isExpanded) {
            // Collapse
            childRows.forEach(row => row.style.display = 'none');
            button.classList.remove('expanded');
        } else {
            // Expand
            childRows.forEach(row => row.style.display = 'table-row');
            button.classList.add('expanded');
        }
    }

    updateBulkButtons() {
        const checkedBoxes = document.querySelectorAll('.uw-backup-checkbox:checked');
        const bulkRestoreBtn = document.getElementById('bulk-restore-btn');
        const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
        
        const hasSelection = checkedBoxes.length > 0;
        
        if (bulkRestoreBtn) bulkRestoreBtn.disabled = !hasSelection;
        if (bulkDeleteBtn) bulkDeleteBtn.disabled = !hasSelection;
        
        // Update select all checkbox
        const allCheckboxes = document.querySelectorAll('.uw-backup-checkbox');
        const headerCheckbox = document.getElementById('select-all-header');
        
        const allChecked = allCheckboxes.length > 0 && checkedBoxes.length === allCheckboxes.length;
        const someChecked = checkedBoxes.length > 0;
        
        if (headerCheckbox) {
            headerCheckbox.checked = allChecked;
            headerCheckbox.indeterminate = someChecked && !allChecked;
        }
    }

    handleBulkRestore() {
        const checkedBoxes = document.querySelectorAll('.uw-backup-checkbox:checked');
        const attachmentIds = Array.from(checkedBoxes).map(cb => cb.value);
        
        if (attachmentIds.length === 0) {
            if (typeof UWNotifications !== 'undefined') {
                UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), wp.i18n.__('Please select at least one backup to restore.', 'ultimate-watermark'));
            } else {
                alert(wp.i18n.__('Please select at least one backup to restore.', 'ultimate-watermark'));
            }
            return;
        }

        // Check if UWNotifications is available
        if (typeof UWNotifications === 'undefined') {
            if (confirm(`Are you sure you want to restore ${attachmentIds.length} image(s) from backup? This will replace the current watermarked images with the original backups.`)) {
                this.confirmBulkRestore(attachmentIds, checkedBoxes);
            }
            return;
        }

        // Show custom confirmation
        UWNotifications.confirm({
            title: wp.i18n.__('Restore Multiple Images', 'ultimate-watermark'),
            message: `Are you sure you want to restore ${attachmentIds.length} image(s) from backup? This will replace the current watermarked images with the original backups.`,
            type: 'warning',
            confirmText: wp.i18n.__('Restore All', 'ultimate-watermark'),
            cancelText: wp.i18n.__('Cancel', 'ultimate-watermark'),
            confirmButtonType: 'primary'
        }).then(action => {
            if (action === 'confirm') {
                this.confirmBulkRestore(attachmentIds, checkedBoxes);
            }
        });
    }

    confirmBulkRestore(attachmentIds, checkedBoxes) {
        // Show loading state for all selected rows
        checkedBoxes.forEach(checkbox => {
            const row = checkbox.closest('.uw-backup-row');
            this.showLoadingState(row);
        });

        // Make AJAX request
        const formData = new FormData();
        formData.append('action', 'ultimate_watermark_bulk_restore_backup');
        formData.append('attachment_ids', JSON.stringify(attachmentIds));
        formData.append('nonce', ultimateWatermarkBackup.nonce);

        fetch(ultimateWatermarkBackup.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                UWNotifications.success(wp.i18n.__('Success', 'ultimate-watermark'), data.data.message || `${attachmentIds.length} image(s) restored successfully from backup.`);
                // Remove all restored backup rows
                checkedBoxes.forEach(checkbox => {
                    const row = checkbox.closest('.uw-backup-row');
                    this.removeBackupRow(row);
                });
            } else {
                UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), data.data.message || wp.i18n.__('Failed to restore images from backup.', 'ultimate-watermark'));
                // Hide loading state for all rows
                checkedBoxes.forEach(checkbox => {
                    const row = checkbox.closest('.uw-backup-row');
                    this.hideLoadingState(row);
                });
            }
        })
        .catch(() => {
            UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), wp.i18n.__('An error occurred while restoring the backups.', 'ultimate-watermark'));
            // Hide loading state for all rows
            checkedBoxes.forEach(checkbox => {
                const row = checkbox.closest('.uw-backup-row');
                this.hideLoadingState(row);
            });
        });
    }

    handleBulkDelete() {
        const checkedBoxes = document.querySelectorAll('.uw-backup-checkbox:checked');
        const attachmentIds = Array.from(checkedBoxes).map(cb => cb.value);
        
        if (attachmentIds.length === 0) {
            if (typeof UWNotifications !== 'undefined') {
                UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), wp.i18n.__('Please select at least one backup to delete.', 'ultimate-watermark'));
            } else {
                alert(wp.i18n.__('Please select at least one backup to delete.', 'ultimate-watermark'));
            }
            return;
        }

        // Check if UWNotifications is available
        if (typeof UWNotifications === 'undefined') {
            if (confirm(`Are you sure you want to delete ${attachmentIds.length} backup(s)? This action cannot be undone.`)) {
                this.confirmBulkDelete(attachmentIds, checkedBoxes);
            }
            return;
        }

        // Show custom confirmation
        UWNotifications.confirm({
            title: wp.i18n.__('Delete Multiple Backups', 'ultimate-watermark'),
            message: `Are you sure you want to delete ${attachmentIds.length} backup(s)? This action cannot be undone.`,
            type: 'error',
            confirmText: wp.i18n.__('Delete All', 'ultimate-watermark'),
            cancelText: wp.i18n.__('Cancel', 'ultimate-watermark'),
            confirmButtonType: 'danger'
        }).then(action => {
            if (action === 'confirm') {
                this.confirmBulkDelete(attachmentIds, checkedBoxes);
            }
        });
    }

    confirmBulkDelete(attachmentIds, checkedBoxes) {
        // Show loading state for all selected rows
        checkedBoxes.forEach(checkbox => {
            const row = checkbox.closest('.uw-backup-row');
            this.showLoadingState(row);
        });

        // Make AJAX request - send IDs as array fields to avoid JSON parsing issues
        const formData = new FormData();
        formData.append('action', 'ultimate_watermark_bulk_delete_backup');
        attachmentIds.forEach(id => formData.append('attachment_ids[]', id));
        formData.append('nonce', ultimateWatermarkBackup.nonce);

        fetch(ultimateWatermarkBackup.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                UWNotifications.success(wp.i18n.__('Success', 'ultimate-watermark'), data.data.message || `${attachmentIds.length} backup(s) deleted successfully.`);
                // Remove all deleted backup rows
                checkedBoxes.forEach(checkbox => {
                    const row = checkbox.closest('.uw-backup-row');
                    this.removeBackupRow(row);
                });
            } else {
                UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), data.data.message || wp.i18n.__('Failed to delete backups.', 'ultimate-watermark'));
                // Hide loading state for all rows
                checkedBoxes.forEach(checkbox => {
                    const row = checkbox.closest('.uw-backup-row');
                    this.hideLoadingState(row);
                });
            }
        })
        .catch(() => {
            UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), wp.i18n.__('An error occurred while deleting the backups.', 'ultimate-watermark'));
            // Hide loading state for all rows
            checkedBoxes.forEach(checkbox => {
                const row = checkbox.closest('.uw-backup-row');
                this.hideLoadingState(row);
            });
        });
    }

    showLoadingState(backupRow) {
        backupRow.classList.add('loading');
        const buttons = backupRow.querySelectorAll('.uw-action-btn');
        buttons.forEach(button => {
            button.disabled = true;
        });
    }

    hideLoadingState(backupRow) {
        backupRow.classList.remove('loading');
        const buttons = backupRow.querySelectorAll('.uw-action-btn');
        buttons.forEach(button => {
            button.disabled = false;
        });
    }

    removeBackupRow(backupRow) {
        // Add fade out animation
        backupRow.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        backupRow.style.opacity = '0';
        backupRow.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            backupRow.remove();
            // Reload current page after deletion
            this.loadBackups(this.currentPage);
        }, 300);
    }

    updateBackupStats() {
        // Update the backup statistics
        const remainingRows = document.querySelectorAll('.uw-backup-row');
        const totalBackups = remainingRows.length;
        
        // Update total backups count
        const totalBackupsElement = document.querySelector('.uw-stat-number');
        if (totalBackupsElement) {
            totalBackupsElement.textContent = totalBackups;
        }
        
        // Update protected images count
        const protectedImagesElement = document.querySelectorAll('.uw-stat-number')[2];
        if (protectedImagesElement) {
            protectedImagesElement.textContent = totalBackups;
        }

        // Update backup count in section header
        const backupCountElement = document.querySelector('.uw-backup-count');
        if (backupCountElement) {
            backupCountElement.textContent = totalBackups + ' ' + (totalBackups === 1 ? 'file' : 'files');
        }

        // Show no backups message if no backups left
        if (totalBackups === 0) {
            this.showNoBackupsMessage();
        }
    }

    showNoBackupsMessage() {
        const backupsSection = document.querySelector('.uw-backups-section');
        if (backupsSection) {
            backupsSection.innerHTML = `
                <div class="uw-empty-state">
                    <div class="uw-empty-icon">
                        <span class="dashicons dashicons-images-alt2"></span>
                    </div>
                    <h3 class="uw-empty-title">No Backups Yet</h3>
                    <p class="uw-empty-description">Backups will appear here when you apply watermarks to your images.</p>
                    <a href="${ultimateWatermarkBackup.mediaLibraryUrl}" class="uw-btn uw-btn-primary">
                        <span class="dashicons dashicons-upload"></span>
                        Upload Images
                    </a>
                </div>
            `;
        }
    }

    showSuccessMessage(message) {
        this.showNotice(message, 'success');
    }

    showErrorMessage(message) {
        this.showNotice(message, 'error');
    }

    showNotice(message, type = 'info') {
        // Remove existing notices
        const existingNotices = document.querySelectorAll('.ultimate-watermark-notice');
        existingNotices.forEach(notice => notice.remove());

        // Create new notice
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible ultimate-watermark-notice`;
        notice.style.margin = '20px 0';
        notice.innerHTML = `
            <p></p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text"></span>
            </button>
        `;
        // Text only: messages may contain image titles.
        notice.querySelector('p').textContent = message;
        notice.querySelector('.screen-reader-text').textContent = wp.i18n.__('Dismiss this notice.', 'ultimate-watermark');

        // Insert notice at the top of the page
        const content = document.querySelector('.ultimate-watermark-content');
        if (content) {
            content.insertBefore(notice, content.firstChild);
        }

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (notice.parentNode) {
                notice.remove();
            }
        }, 5000);

        // Handle manual dismiss
        const dismissButton = notice.querySelector('.notice-dismiss');
        if (dismissButton) {
            dismissButton.addEventListener('click', () => {
                notice.remove();
            });
        }
    }

    // Custom confirmation modals
    showDeleteConfirmation(attachmentId, backupRow) {
        const fileName = backupRow.querySelector('.uw-file-name').textContent;
        this.showConfirmationModal(
            wp.i18n.__('Delete Backup', 'ultimate-watermark'),
            /* translators: %s: image title */
            wp.i18n.sprintf(wp.i18n.__('Are you sure you want to delete the backup for "%s"? This action cannot be undone.', 'ultimate-watermark'), fileName),
            wp.i18n.__('Delete', 'ultimate-watermark'),
            wp.i18n.__('Cancel', 'ultimate-watermark'),
            () => this.confirmDeleteBackup(attachmentId, backupRow)
        );
    }

    showRestoreConfirmation(attachmentId, backupRow) {
        const fileName = backupRow.querySelector('.uw-file-name').textContent;
        this.showConfirmationModal(
            wp.i18n.__('Restore from Backup', 'ultimate-watermark'),
            /* translators: %s: image title */
            wp.i18n.sprintf(wp.i18n.__('Are you sure you want to restore "%s" from backup? This will replace the current watermarked image with the original backup.', 'ultimate-watermark'), fileName),
            wp.i18n.__('Restore', 'ultimate-watermark'),
            wp.i18n.__('Cancel', 'ultimate-watermark'),
            () => this.confirmRestoreBackup(attachmentId, backupRow)
        );
    }

    showBulkDeleteConfirmation(attachmentIds, checkedBoxes) {
        this.showConfirmationModal(
            wp.i18n.__('Delete Selected Backups', 'ultimate-watermark'),
            /* translators: %d: number of backups */
            wp.i18n.sprintf(wp.i18n._n('Are you sure you want to delete %d backup? This action cannot be undone.', 'Are you sure you want to delete %d backups? This action cannot be undone.', attachmentIds.length, 'ultimate-watermark'), attachmentIds.length),
            wp.i18n.__('Delete All', 'ultimate-watermark'),
            wp.i18n.__('Cancel', 'ultimate-watermark'),
            () => this.confirmBulkDelete(attachmentIds, checkedBoxes)
        );
    }

    showBulkRestoreConfirmation(attachmentIds, checkedBoxes) {
        this.showConfirmationModal(
            wp.i18n.__('Restore Selected Images', 'ultimate-watermark'),
            /* translators: %d: number of images */
            wp.i18n.sprintf(wp.i18n._n('Are you sure you want to restore %d image from backup? This will replace the current watermarked image with the original backup.', 'Are you sure you want to restore %d images from backup? This will replace the current watermarked images with the original backups.', attachmentIds.length, 'ultimate-watermark'), attachmentIds.length),
            wp.i18n.__('Restore All', 'ultimate-watermark'),
            wp.i18n.__('Cancel', 'ultimate-watermark'),
            () => this.confirmBulkRestore(attachmentIds, checkedBoxes)
        );
    }

    showConfirmationModal(title, message, confirmText, cancelText, onConfirm) {
        // Remove existing modal if any
        const existingModal = document.querySelector('.uw-confirmation-modal');
        if (existingModal) {
            existingModal.remove();
        }

        // Create modal
        const modal = document.createElement('div');
        modal.className = 'uw-confirmation-modal';
        modal.innerHTML = `
            <div class="uw-modal-overlay"></div>
            <div class="uw-modal-content">
                <div class="uw-modal-header">
                    <h3 class="uw-modal-title"></h3>
                    <button type="button" class="uw-modal-close">&times;</button>
                </div>
                <div class="uw-modal-body">
                    <p class="uw-modal-message"></p>
                </div>
                <div class="uw-modal-footer">
                    <button type="button" class="uw-btn uw-btn-secondary uw-modal-cancel"></button>
                    <button type="button" class="uw-btn uw-btn-danger uw-modal-confirm"></button>
                </div>
            </div>
        `;
        // Text only: the message contains the image title.
        modal.querySelector('.uw-modal-title').textContent = title;
        modal.querySelector('.uw-modal-message').textContent = message;
        modal.querySelector('.uw-modal-cancel').textContent = cancelText;
        modal.querySelector('.uw-modal-confirm').textContent = confirmText;

        // Add to page
        document.body.appendChild(modal);

        // Show modal with animation
        setTimeout(() => {
            modal.classList.add('uw-modal-show');
        }, 10);

        // Event listeners
        const closeModal = () => {
            modal.classList.remove('uw-modal-show');
            setTimeout(() => {
                if (modal.parentNode) {
                    modal.remove();
                }
            }, 200);
        };

        modal.querySelector('.uw-modal-close').addEventListener('click', closeModal);
        modal.querySelector('.uw-modal-cancel').addEventListener('click', closeModal);
        modal.querySelector('.uw-modal-overlay').addEventListener('click', closeModal);
        modal.querySelector('.uw-modal-confirm').addEventListener('click', () => {
            closeModal();
            onConfirm();
        });

        // ESC key to close
        const handleEsc = (e) => {
            if (e.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', handleEsc);
            }
        };
        document.addEventListener('keydown', handleEsc);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Wait for notification system to be available
    const waitForNotifications = () => {
        if (typeof UWNotifications !== 'undefined') {
            new BackupPageManager();
        } else {
            setTimeout(waitForNotifications, 100);
        }
    };
    
    waitForNotifications();
});
