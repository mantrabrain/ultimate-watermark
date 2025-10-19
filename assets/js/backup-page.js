/**
 * Ultimate Watermark - Backup Management Page JavaScript
 * 
 * @package UltimateWatermark
 * @since 2.0.0
 */

class BackupPageManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
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

        // Bulk actions
        document.addEventListener('click', (e) => {
            if (e.target.id === 'select-all-header') {
                this.handleSelectAll(e.target.checked);
            }
            
            if (e.target.id === 'bulk-restore-btn') {
                e.preventDefault();
                this.handleBulkRestore();
            }
            
            if (e.target.id === 'bulk-delete-btn') {
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
    }

    handleDeleteBackup(button) {
        console.log('Ultimate Watermark: Delete backup clicked');
        const attachmentId = button.getAttribute('data-attachment-id');
        const backupRow = button.closest('.uw-backup-row');
        
        console.log('Ultimate Watermark: Attachment ID:', attachmentId);
        console.log('Ultimate Watermark: UWNotifications available:', typeof UWNotifications !== 'undefined');
        
        if (!attachmentId) {
            if (typeof UWNotifications !== 'undefined') {
                UWNotifications.error('Error', 'Invalid backup item.');
            } else {
                alert('Invalid backup item.');
            }
            return;
        }

        // Check if UWNotifications is available
        if (typeof UWNotifications === 'undefined') {
            console.error('Ultimate Watermark: UWNotifications not available, using fallback');
            if (confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
                this.confirmDeleteBackup(attachmentId, backupRow);
            }
            return;
        }

        // Show custom confirmation
        console.log('Ultimate Watermark: About to show confirmation dialog');
        try {
            UWNotifications.confirm({
                title: 'Delete Backup',
                message: 'Are you sure you want to delete this backup? This action cannot be undone.',
                type: 'error',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                confirmButtonType: 'danger'
            }).then(confirmed => {
                console.log('Ultimate Watermark: Confirmation result:', confirmed);
                if (confirmed) {
                    this.confirmDeleteBackup(attachmentId, backupRow);
                }
            }).catch(error => {
                console.error('Ultimate Watermark: Confirmation error:', error);
                // Fallback to native confirm
                if (confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
                    this.confirmDeleteBackup(attachmentId, backupRow);
                }
            });
        } catch (error) {
            console.error('Ultimate Watermark: Error calling UWNotifications.confirm:', error);
            // Fallback to native confirm
            if (confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
                this.confirmDeleteBackup(attachmentId, backupRow);
            }
        }
    }

    confirmDeleteBackup(attachmentId, backupRow) {
        console.log('Ultimate Watermark: Confirming delete backup for ID:', attachmentId);
        console.log('Ultimate Watermark: Nonce:', ultimateWatermarkBackup.nonce);
        
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
            console.log('Ultimate Watermark: Delete backup response:', data);
            this.hideLoadingState(backupRow);
            
            if (data.success) {
                if (typeof UWNotifications !== 'undefined') {
                    UWNotifications.success('Success', data.data.message || 'Backup deleted successfully.');
                } else {
                    alert(data.data.message || 'Backup deleted successfully.');
                }
                // Remove the backup row with animation
                this.removeBackupRow(backupRow);
            } else {
                if (typeof UWNotifications !== 'undefined') {
                    UWNotifications.error('Error', data.data.message || 'Failed to delete backup.');
                } else {
                    alert(data.data.message || 'Failed to delete backup.');
                }
            }
        })
        .catch(error => {
            this.hideLoadingState(backupRow);
            console.error('Error deleting backup:', error);
            if (typeof UWNotifications !== 'undefined') {
                UWNotifications.error('Error', 'An error occurred while deleting the backup.');
            } else {
                alert('An error occurred while deleting the backup.');
            }
        });
    }

    handleRestoreBackup(button) {
        console.log('Ultimate Watermark: Restore backup clicked');
        const attachmentId = button.getAttribute('data-attachment-id');
        const backupRow = button.closest('.uw-backup-row');
        
        console.log('Ultimate Watermark: Attachment ID:', attachmentId);
        console.log('Ultimate Watermark: UWNotifications available:', typeof UWNotifications !== 'undefined');
        
        if (!attachmentId) {
            if (typeof UWNotifications !== 'undefined') {
                UWNotifications.error('Error', 'Invalid backup item.');
            } else {
                alert('Invalid backup item.');
            }
            return;
        }

        // Check if UWNotifications is available
        if (typeof UWNotifications === 'undefined') {
            console.error('Ultimate Watermark: UWNotifications not available, using fallback');
            if (confirm('Are you sure you want to restore this image from backup? This will replace the current watermarked image with the original backup.')) {
                this.confirmRestoreBackup(attachmentId, backupRow);
            }
            return;
        }

        // Show custom confirmation
        UWNotifications.confirm({
            title: 'Restore Image',
            message: 'Are you sure you want to restore this image from backup? This will replace the current watermarked image with the original backup.',
            type: 'warning',
            confirmText: 'Restore',
            cancelText: 'Cancel',
            confirmButtonType: 'primary'
        }).then(confirmed => {
            if (confirmed) {
                this.confirmRestoreBackup(attachmentId, backupRow);
            }
        });
    }

    confirmRestoreBackup(attachmentId, backupRow) {
        // Show loading state
        this.showLoadingState(backupRow);

        // Make AJAX request
        const formData = new FormData();
        formData.append('action', 'ultimate_watermark_restore_backup');
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
                UWNotifications.success('Success', data.data.message || 'Image restored successfully from backup.');
                // Remove the backup row since it's no longer needed
                this.removeBackupRow(backupRow);
            } else {
                UWNotifications.error('Error', data.data.message || 'Failed to restore image from backup.');
            }
        })
        .catch(error => {
            this.hideLoadingState(backupRow);
            console.error('Error restoring backup:', error);
            UWNotifications.error('Error', 'An error occurred while restoring the backup.');
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
                UWNotifications.error('Error', 'Please select at least one backup to restore.');
            } else {
                alert('Please select at least one backup to restore.');
            }
            return;
        }

        // Check if UWNotifications is available
        if (typeof UWNotifications === 'undefined') {
            console.error('Ultimate Watermark: UWNotifications not available, using fallback');
            if (confirm(`Are you sure you want to restore ${attachmentIds.length} image(s) from backup? This will replace the current watermarked images with the original backups.`)) {
                this.confirmBulkRestore(attachmentIds, checkedBoxes);
            }
            return;
        }

        // Show custom confirmation
        UWNotifications.confirm({
            title: 'Restore Multiple Images',
            message: `Are you sure you want to restore ${attachmentIds.length} image(s) from backup? This will replace the current watermarked images with the original backups.`,
            type: 'warning',
            confirmText: 'Restore All',
            cancelText: 'Cancel',
            confirmButtonType: 'primary'
        }).then(confirmed => {
            if (confirmed) {
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
                UWNotifications.success('Success', data.data.message || `${attachmentIds.length} image(s) restored successfully from backup.`);
                // Remove all restored backup rows
                checkedBoxes.forEach(checkbox => {
                    const row = checkbox.closest('.uw-backup-row');
                    this.removeBackupRow(row);
                });
            } else {
                UWNotifications.error('Error', data.data.message || 'Failed to restore images from backup.');
                // Hide loading state for all rows
                checkedBoxes.forEach(checkbox => {
                    const row = checkbox.closest('.uw-backup-row');
                    this.hideLoadingState(row);
                });
            }
        })
        .catch(error => {
            console.error('Error bulk restoring backups:', error);
            UWNotifications.error('Error', 'An error occurred while restoring the backups.');
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
                UWNotifications.error('Error', 'Please select at least one backup to delete.');
            } else {
                alert('Please select at least one backup to delete.');
            }
            return;
        }

        // Check if UWNotifications is available
        if (typeof UWNotifications === 'undefined') {
            console.error('Ultimate Watermark: UWNotifications not available, using fallback');
            if (confirm(`Are you sure you want to delete ${attachmentIds.length} backup(s)? This action cannot be undone.`)) {
                this.confirmBulkDelete(attachmentIds, checkedBoxes);
            }
            return;
        }

        // Show custom confirmation
        UWNotifications.confirm({
            title: 'Delete Multiple Backups',
            message: `Are you sure you want to delete ${attachmentIds.length} backup(s)? This action cannot be undone.`,
            type: 'error',
            confirmText: 'Delete All',
            cancelText: 'Cancel',
            confirmButtonType: 'danger'
        }).then(confirmed => {
            if (confirmed) {
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

        // Make AJAX request
        const formData = new FormData();
        formData.append('action', 'ultimate_watermark_bulk_delete_backup');
        formData.append('attachment_ids', JSON.stringify(attachmentIds));
        formData.append('nonce', ultimateWatermarkBackup.nonce);

        fetch(ultimateWatermarkBackup.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                UWNotifications.success('Success', data.data.message || `${attachmentIds.length} backup(s) deleted successfully.`);
                // Remove all deleted backup rows
                checkedBoxes.forEach(checkbox => {
                    const row = checkbox.closest('.uw-backup-row');
                    this.removeBackupRow(row);
                });
            } else {
                UWNotifications.error('Error', data.data.message || 'Failed to delete backups.');
                // Hide loading state for all rows
                checkedBoxes.forEach(checkbox => {
                    const row = checkbox.closest('.uw-backup-row');
                    this.hideLoadingState(row);
                });
            }
        })
        .catch(error => {
            console.error('Error bulk deleting backups:', error);
            UWNotifications.error('Error', 'An error occurred while deleting the backups.');
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
            this.updateBackupStats();
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
            <p>${message}</p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text">Dismiss this notice.</span>
            </button>
        `;

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
            'Delete Backup',
            `Are you sure you want to delete the backup for "${fileName}"? This action cannot be undone.`,
            'Delete',
            'Cancel',
            () => this.confirmDeleteBackup(attachmentId, backupRow)
        );
    }

    showRestoreConfirmation(attachmentId, backupRow) {
        const fileName = backupRow.querySelector('.uw-file-name').textContent;
        this.showConfirmationModal(
            'Restore from Backup',
            `Are you sure you want to restore "${fileName}" from backup? This will replace the current watermarked image with the original backup.`,
            'Restore',
            'Cancel',
            () => this.confirmRestoreBackup(attachmentId, backupRow)
        );
    }

    showBulkDeleteConfirmation(attachmentIds, checkedBoxes) {
        this.showConfirmationModal(
            'Delete Selected Backups',
            `Are you sure you want to delete ${attachmentIds.length} backup(s)? This action cannot be undone.`,
            'Delete All',
            'Cancel',
            () => this.confirmBulkDelete(attachmentIds, checkedBoxes)
        );
    }

    showBulkRestoreConfirmation(attachmentIds, checkedBoxes) {
        this.showConfirmationModal(
            'Restore Selected Images',
            `Are you sure you want to restore ${attachmentIds.length} image(s) from backup? This will replace the current watermarked images with the original backups.`,
            'Restore All',
            'Cancel',
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
                    <h3 class="uw-modal-title">${title}</h3>
                    <button type="button" class="uw-modal-close">&times;</button>
                </div>
                <div class="uw-modal-body">
                    <p class="uw-modal-message">${message}</p>
                </div>
                <div class="uw-modal-footer">
                    <button type="button" class="uw-btn uw-btn-secondary uw-modal-cancel">${cancelText}</button>
                    <button type="button" class="uw-btn uw-btn-danger uw-modal-confirm">${confirmText}</button>
                </div>
            </div>
        `;

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
    console.log('Ultimate Watermark: Backup page DOM ready');
    
    // Wait for notification system to be available
    const waitForNotifications = () => {
        if (typeof UWNotifications !== 'undefined') {
            console.log('Ultimate Watermark: Notification system is available');
            new BackupPageManager();
        } else {
            console.log('Ultimate Watermark: Waiting for notification system...');
            setTimeout(waitForNotifications, 100);
        }
    };
    
    waitForNotifications();
});
