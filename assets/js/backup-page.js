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
    }

    handleDeleteBackup(button) {
        const attachmentId = button.getAttribute('data-attachment-id');
        const listItem = button.closest('.backup-list-item');
        
        if (!attachmentId) {
            this.showErrorMessage('Invalid backup item.');
            return;
        }

        // Show confirmation
        if (!confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
            return;
        }

        // Show loading state
        this.showLoadingState(listItem);

        // Make AJAX request
        const formData = new FormData();
        formData.append('action', 'ultimate_watermark_delete_backup');
        formData.append('attachment_id', attachmentId);
        formData.append('nonce', ultimateWatermarkBackup.nonce);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            this.hideLoadingState(listItem);
            
            if (data.success) {
                this.showSuccessMessage(data.data.message || 'Backup deleted successfully.');
                // Remove the list item with animation
                this.removeListItem(listItem);
            } else {
                this.showErrorMessage(data.data.message || 'Failed to delete backup.');
            }
        })
        .catch(error => {
            this.hideLoadingState(listItem);
            console.error('Error deleting backup:', error);
            this.showErrorMessage('An error occurred while deleting the backup.');
        });
    }

    showLoadingState(listItem) {
        listItem.classList.add('loading');
        const buttons = listItem.querySelectorAll('.backup-actions .button');
        buttons.forEach(button => {
            button.disabled = true;
        });
    }

    hideLoadingState(listItem) {
        listItem.classList.remove('loading');
        const buttons = listItem.querySelectorAll('.backup-actions .button');
        buttons.forEach(button => {
            button.disabled = false;
        });
    }

    removeListItem(listItem) {
        // Add fade out animation
        listItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        listItem.style.opacity = '0';
        listItem.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            listItem.remove();
            this.updateBackupStats();
        }, 300);
    }

    updateBackupStats() {
        // Update the backup statistics
        const remainingItems = document.querySelectorAll('.backup-list-item');
        const totalBackups = remainingItems.length;
        
        // Update total backups count
        const totalBackupsElement = document.querySelector('.stat-item .stat-number');
        if (totalBackupsElement) {
            totalBackupsElement.textContent = totalBackups;
        }
        
        // Update images with backups count
        const imagesWithBackupsElement = document.querySelectorAll('.stat-item .stat-number')[2];
        if (imagesWithBackupsElement) {
            imagesWithBackupsElement.textContent = totalBackups;
        }

        // Show no backups message if no backups left
        if (totalBackups === 0) {
            this.showNoBackupsMessage();
        }
    }

    showNoBackupsMessage() {
        const backupsList = document.querySelector('.backups-list-view');
        if (backupsList) {
            backupsList.innerHTML = `
                <div class="no-backups">
                    <div class="no-backups-content">
                        <span class="dashicons dashicons-images-alt2"></span>
                        <h3>No Backups Found</h3>
                        <p>Backups will appear here once you start applying watermarks to your images.</p>
                        <a href="${ultimateWatermarkBackup.mediaLibraryUrl}" class="button button-primary">
                            Go to Media Library
                        </a>
                    </div>
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
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new BackupPageManager();
});
