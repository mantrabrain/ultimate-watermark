/**
 * Ultimate Watermark - Watermarks Page JavaScript
 * 
 * Handles the watermarks table functionality, modal interactions, and form management
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */

(function($) {
    'use strict';

    const WatermarksPage = {
        
        /**
         * Initialize the watermarks page
         */
        init: function() {
            this.bindEvents();
            this.initTable();
            this.initModal();
            this.initForm();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add watermark buttons
            $(document).on('click', '#ultimate-watermark-add-watermark, #ultimate-watermark-add-watermark-empty', this.openModal);
            
            // Modal controls
            $(document).on('click', '#modal-close, #modal-cancel', this.closeModal);
            $(document).on('click', '.modal-overlay', this.closeModal);
            
            // Table interactions
            $(document).on('change', '#select-all-watermarks', this.toggleSelectAll);
            $(document).on('change', '.watermark-checkbox', this.updateBulkActions);
            $(document).on('click', '.watermark-edit', this.editWatermark);
            $(document).on('click', '.watermark-duplicate', this.duplicateWatermark.bind(this));
            $(document).on('click', '.watermark-toggle', this.toggleWatermark.bind(this));
            $(document).on('click', '.watermark-delete', (e) => {
                // Get the watermark ID from the clicked element
                const watermarkId = $(e.target).data('id') || $(e.target).attr('data-id');
                
                // Store watermark ID for confirmation
                WatermarksPage.pendingDeleteId = watermarkId;
                
                // Show confirmation modal
                UWNotifications.confirm({
                    title: 'Delete Watermark',
                    message: 'Are you sure you want to delete this watermark? This action cannot be undone.',
                    type: 'error',
                    confirmText: 'Delete',
                    cancelText: 'Cancel',
                    confirmButtonType: 'danger'
                }).then(function(action) {
                    // confirm() resolves with the action string, not a boolean
                    if (action === 'confirm') {
                        this.confirmDelete(watermarkId);
                    }
                }.bind(this));
            });
            
            // WordPress-style bulk actions
            $(document).on('click', '#doaction, #doaction2', this.handleBulkAction.bind(this));
            
            // Legacy bulk actions (keep for backward compatibility)
            $(document).on('click', '#bulk-activate', this.bulkActivate);
            $(document).on('click', '#bulk-deactivate', this.bulkDeactivate);
            $(document).on('click', '#bulk-delete', this.bulkDelete);
            
            // Search functionality
            $(document).on('input', '#watermark-search', this.searchWatermarks);
            
            // Confirmation modal events
            $(document).on('click', '.modal-close, .modal-cancel', this.closeConfirmationModal.bind(this));
            $(document).on('click', '.modal-confirm', this.confirmDelete.bind(this));
            $(document).on('click', '.modal-overlay', this.closeConfirmationModal.bind(this));
            
            // Form tabs
            $(document).on('click', '.form-tab', this.switchTab);
            
            // Form interactions
            $(document).on('change', 'input[name="watermark_type"]', this.toggleWatermarkType);
            $(document).on('input', '#watermark_opacity', this.updateOpacityValue);
            $(document).on('change', '#watermark_image', this.handleImageUpload);
            
            // Form submission
            $(document).on('submit', '#ultimate-watermark-form', this.handleFormSubmit);
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts);
        },

        /**
         * Initialize table functionality
         */
        initTable: function() {
            // Initialize sorting (if needed)
            this.initSorting();
        },

        /**
         * Initialize modal functionality
         */
        initModal: function() {
            // Modal is hidden by default, no initialization needed
        },

        /**
         * Initialize form functionality
         */
        initForm: function() {
            // Initialize color picker
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker({
                    change: function(event, ui) {
                        WatermarksPage.updatePreview();
                    }
                });
            }
            
            // Initialize file upload
            this.initFileUpload();
        },

        /**
         * Open the watermark modal (deprecated - now using direct links)
         */
        openModal: function(e) {
            // Modal functionality removed - now using direct page navigation
        },

        /**
         * Close the watermark modal
         */
        closeModal: function(e) {
            e.preventDefault();
            $('#watermark-modal').fadeOut(300);
            $('body').removeClass('modal-open');
        },

        /**
         * Reset the form to default values
         */
        resetForm: function() {
            $('#ultimate-watermark-form')[0].reset();
            $('input[name="watermark_type"][value="text"]').prop('checked', true);
            $('#watermark_text').val('© ' + $('body').data('site-name') || 'Your Site');
            $('#watermark_opacity').val(50);
            $('.range-value').text('50%');
            $('.form-tab').removeClass('active');
            $('.form-tab-content').removeClass('active');
            $('.form-tab[data-tab="basic"]').addClass('active');
            $('#tab-basic').addClass('active');
            this.toggleWatermarkType();
            this.updatePreview();
        },

        /**
         * Toggle select all checkboxes
         */
        toggleSelectAll: function() {
            const isChecked = $('#select-all-watermarks').is(':checked');
            $('.watermark-checkbox').prop('checked', isChecked);
            WatermarksPage.updateBulkActions();
        },

        /**
         * Update bulk actions visibility
         */
        updateBulkActions: function() {
            const selectedCount = $('.watermark-checkbox:checked').length;
            const bulkActions = $('#bulk-actions');
            
            if (selectedCount > 0) {
                bulkActions.show();
                $('.bulk-selected-count').text(selectedCount + ' selected');
            } else {
                bulkActions.hide();
            }
        },

        /**
         * Edit watermark
         */
        editWatermark: function(e) {
            // No need to prevent default or open modal since we're using direct links
            // The link will navigate to the edit page directly
        },

        /**
         * Duplicate watermark
         */
        duplicateWatermark: function(e) {
            e.preventDefault();
            const watermarkId = $(e.target).data('id') || $(e.target).attr('data-id');
            
            // Show loading state
            const $button = $(e.target);
            const originalText = $button.text();
            $button.text('Duplicating...').prop('disabled', true);
            
            // AJAX request to duplicate watermark
            
            $.ajax({
                url: ultimate_watermark_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ultimate_watermark_duplicate',
                    watermark_id: watermarkId,
                    nonce: ultimate_watermark_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show the duplicated watermark
                        location.reload();
                    } else {
                        // Check if upgrade is required
                        if (response.data && response.data.upgrade_required) {
                            // Show upgrade modal with nice UI
                            WatermarksPage.showUpgradeModal(response.data.message, response.data.upgrade_url);
                        } else {
                            UWNotifications.error('Error', response.data.message || 'Error duplicating watermark');
                        }
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    UWNotifications.error('Error', 'Error duplicating watermark. Please try again.');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Toggle watermark status
         */
        toggleWatermark: function(e) {
            e.preventDefault();
            const watermarkId = $(e.target).data('id') || $(e.target).attr('data-id');
            const currentStatus = $(e.target).data('status') || $(e.target).attr('data-status');
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            // Show loading state
            const $button = $(e.target);
            const originalText = $button.text();
            $button.text('Updating...').prop('disabled', true);
            
            // Toggle watermark status
            
            // AJAX request to toggle watermark status
            $.ajax({
                url: ultimate_watermark_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ultimate_watermark_toggle',
                    watermark_id: watermarkId,
                    status: newStatus,
                    nonce: ultimate_watermark_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update the button text and status
                        const newButtonText = newStatus === 'active' ? 'Deactivate' : 'Activate';
                        $button.text(newButtonText).data('status', newStatus).prop('disabled', false);
                        
                        // Update status badge
                        const $row = $button.closest('tr');
                        const $statusBadge = $row.find('.status-badge');
                        
                        if (newStatus === 'active') {
                            $statusBadge.removeClass('status-inactive').addClass('status-active');
                            $statusBadge.html('<span class="status-dot"></span>Active');
                        } else {
                            $statusBadge.removeClass('status-active').addClass('status-inactive');
                            $statusBadge.html('<span class="status-dot"></span>Inactive');
                        }
                    } else {
                        UWNotifications.error('Error', 'Error updating watermark status: ' + (response.data || 'Unknown error'));
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    UWNotifications.error('Error', 'Error updating watermark status: ' + error);
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Delete watermark
         */
        deleteWatermark: function(e) {
            e.preventDefault();
            
            const watermarkId = $(this).data('id') || $(this).attr('data-id');
            
            // Store watermark ID for confirmation
            this.pendingDeleteId = watermarkId;
            
            // Show confirmation modal
            UWNotifications.confirm({
                title: 'Delete Watermark',
                message: 'Are you sure you want to delete this watermark? This action cannot be undone.',
                type: 'error',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                confirmButtonType: 'danger'
            }).then(function(action) {
                // confirm() resolves with the action string, not a boolean
                if (action === 'confirm') {
                    this.confirmDelete(watermarkId);
                }
            }.bind(this));
        },

        /**
         * Close confirmation modal
         */
        closeConfirmationModal: function(e) {
            e.preventDefault();
            $('.confirmation-modal').hide();
            this.pendingDeleteId = null;
        },

        /**
         * Confirm delete action
         */
        confirmDelete: function(watermarkId) {
            // Handle single delete
            if (watermarkId) {
                
                $.ajax({
                    url: ultimate_watermark_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ultimate_watermark_delete',
                        watermark_id: watermarkId,
                        nonce: ultimate_watermark_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Remove the row from table
                            $(`tr[data-id="${watermarkId}"]`).fadeOut(300, function() {
                                $(this).remove();
                            });
                            
                            // Close modal
                            $('.confirmation-modal').hide();
                            
                            // Reset pending delete ID
                            WatermarksPage.pendingDeleteId = null;
                            
                            // Show success message
                            UWNotifications.success('Success', 'Watermark deleted successfully');
                        } else {
                            UWNotifications.error('Error', 'Error deleting watermark: ' + (response.data || 'Unknown error'));
                            $confirmBtn.text(originalText).prop('disabled', false);
                            WatermarksPage.pendingDeleteId = null;
                        }
                    },
                    error: function(xhr, status, error) {
                        UWNotifications.error('Error', 'Error deleting watermark. Please try again.');
                    }
                });
                
                this.pendingDeleteId = null;
            }
            // Handle bulk delete
            else if (this.pendingBulkDeleteIds && this.pendingBulkDeleteIds.length > 0) {
                const selectedIds = this.pendingBulkDeleteIds;
                
                $.ajax({
                    url: ultimate_watermark_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ultimate_watermark_bulk_delete',
                        watermark_ids: selectedIds,
                        nonce: ultimate_watermark_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Close modal and hide bulk actions
                            $('.confirmation-modal').hide();
                            $('#bulk-actions').hide();
                            
                            // Show success message
                            WatermarksPage.showSuccessMessage(`${response.data.deleted_count || selectedIds.length} watermark(s) deleted successfully.`);
                            
                            // Clear selections
                            WatermarksPage.clearSelections();
                            
                            // Refresh the table after a short delay to show updated data
                            setTimeout(() => {
                                WatermarksPage.refreshTable();
                            }, 1500);
                        } else {
                            WatermarksPage.showErrorMessage('Error deleting watermarks: ' + (response.data || 'Unknown error'));
                            $confirmBtn.text(originalText).prop('disabled', false);
                        }
                    },
                    error: function() {
                        WatermarksPage.showErrorMessage('Error deleting watermarks. Please try again.');
                        $confirmBtn.text(originalText).prop('disabled', false);
                    }
                });
                
                this.pendingBulkDeleteIds = null;
            }
        },

        /**
         * Handle WordPress-style bulk actions
         */
        handleBulkAction: function(e) {
            e.preventDefault();
            
            // Get the selected action from the dropdown
            const $button = $(e.target);
            const $form = $button.closest('.tablenav');
            const $select = $form.find('select[name="action"], select[name="action2"]');
            const action = $select.val();
            
            if (action === '-1' || !action) {
                UWNotifications.error('Error', 'Please select a bulk action.');
                return;
            }
            
            // Get selected watermarks
            const selectedIds = $('.watermark-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                UWNotifications.error('Error', 'Please select watermarks to perform the action on.');
                return;
            }
            
            // Route to appropriate handler
            switch (action) {
                case 'activate':
                    this.performBulkAction('activate', selectedIds);
                    break;
                case 'deactivate':
                    this.performBulkAction('deactivate', selectedIds);
                    break;
                case 'delete':
                    this.handleBulkDelete(selectedIds);
                    break;
                default:
                    UWNotifications.error('Error', 'Unknown bulk action: ' + action);
            }
        },

        /**
         * Handle bulk delete with confirmation
         */
        handleBulkDelete: function(selectedIds) {
            // Show confirmation modal
            UWNotifications.confirm({
                title: 'Delete Multiple Watermarks',
                message: `Are you sure you want to delete ${selectedIds.length} watermark(s)? This action cannot be undone.`,
                type: 'error',
                confirmText: 'Delete All',
                cancelText: 'Cancel',
                confirmButtonType: 'danger'
            }).then(function(action) {
                // confirm() resolves with the action string, not a boolean
                if (action === 'confirm') {
                    this.confirmBulkDelete(selectedIds);
                }
            }.bind(this));
        },

        /**
         * Bulk activate watermarks
         */
        bulkActivate: function(e) {
            e.preventDefault();
            const selectedIds = $('.watermark-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                UWNotifications.error('Error', 'Please select watermarks to activate.');
                return;
            }

            this.performBulkAction('activate', selectedIds);
        },

        /**
         * Bulk deactivate watermarks
         */
        bulkDeactivate: function(e) {
            e.preventDefault();
            const selectedIds = $('.watermark-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                UWNotifications.error('Error', 'Please select watermarks to deactivate.');
                return;
            }

            this.performBulkAction('deactivate', selectedIds);
        },

        /**
         * Bulk delete watermarks
         */
        bulkDelete: function(e) {
            e.preventDefault();
            
            const selectedIds = $('.watermark-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                if (typeof UWNotifications !== 'undefined') {
                    UWNotifications.error('Error', 'Please select watermarks to delete.');
                } else {
                    alert('Please select watermarks to delete.');
                }
                return;
            }

            // Check if UWNotifications is available
            if (typeof UWNotifications === 'undefined') {
                if (confirm(`Are you sure you want to delete ${selectedIds.length} watermark(s)? This action cannot be undone.`)) {
                    this.confirmBulkDelete(selectedIds);
                }
                return;
            }

            // Show confirmation modal
            UWNotifications.confirm({
                title: 'Delete Multiple Watermarks',
                message: `Are you sure you want to delete ${selectedIds.length} watermark(s)? This action cannot be undone.`,
                type: 'error',
                confirmText: 'Delete All',
                cancelText: 'Cancel',
                confirmButtonType: 'danger'
            }).then(function(action) {
                // confirm() resolves with the action string, not a boolean
                if (action === 'confirm') {
                    this.confirmBulkDelete(selectedIds);
                }
            }.bind(this));
        },

        /**
         * Perform bulk action (activate/deactivate)
         */
        performBulkAction: function(action, selectedIds) {
            const actionText = action === 'activate' ? 'activate' : 'deactivate';
            const actionTextPast = action === 'activate' ? 'activated' : 'deactivated';
            
            // Show loading state
            this.showBulkActionLoading(actionText);
            
            // Prepare AJAX data
            const ajaxData = {
                action: `ultimate_watermark_bulk_${action}`,
                watermark_ids: selectedIds,
                nonce: ultimate_watermark_ajax.nonce
            };
            
            // Make AJAX request
            $.ajax({
                url: ultimate_watermark_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    this.hideBulkActionLoading();
                    
                    if (response.success) {
                        this.showSuccessMessage(`${response.data.updated_count} watermark(s) ${actionTextPast} successfully.`);
                        
                        // Update the table to reflect changes
                        this.updateTableAfterBulkAction(selectedIds, action);
                        
                        // Clear selections
                        this.clearSelections();
                        
                        // Refresh the table after a short delay to show updated data
                        setTimeout(() => {
                            this.refreshTable();
                        }, 1500);
                    } else {
                        this.showErrorMessage(response.data || `Failed to ${actionText} watermarks.`);
                    }
                },
                error: (xhr, status, error) => {
                    this.hideBulkActionLoading();
                    this.showErrorMessage(`Failed to ${actionText} watermarks. Please try again.`);
                }
            });
        },

        /**
         * Show bulk action loading state
         */
        showBulkActionLoading: function(action) {
            const $buttons = $('.tablenav .bulkactions .button');
            $buttons.prop('disabled', true);
            $buttons.addClass('loading');
            
            // Update button text to show loading
            $(`#bulk-${action}`).html(`
                <span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span>
                ${action === 'activate' ? 'Activating...' : 'Deactivating...'}
            `);
        },

        /**
         * Hide bulk action loading state
         */
        hideBulkActionLoading: function() {
            const $buttons = $('.tablenav .bulkactions .button');
            $buttons.prop('disabled', false);
            $buttons.removeClass('loading');
            
            // Restore original button text
            $('#bulk-activate').html(`
                <span class="dashicons dashicons-yes"></span>
                Activate
            `);
            $('#bulk-deactivate').html(`
                <span class="dashicons dashicons-no-alt"></span>
                Deactivate
            `);
        },

        /**
         * Update table after bulk action
         */
        updateTableAfterBulkAction: function(selectedIds, action) {
            selectedIds.forEach(id => {
                const $row = $(`.watermark-checkbox[value="${id}"]`).closest('tr');
                const $statusToggle = $row.find('.watermark-toggle');
                const $statusBadge = $row.find('.status-badge');
                
                if (action === 'activate') {
                    $statusToggle.attr('data-status', 'active');
                    $statusToggle.find('.toggle-slider').addClass('active');
                    $statusBadge.removeClass('status-inactive').addClass('status-active').text('Active');
                } else {
                    $statusToggle.attr('data-status', 'inactive');
                    $statusToggle.find('.toggle-slider').removeClass('active');
                    $statusBadge.removeClass('status-active').addClass('status-inactive').text('Inactive');
                }
            });
        },

        /**
         * Confirm and execute bulk delete
         */
        confirmBulkDelete: function(selectedIds) {
            
            // Show loading state
            this.showBulkDeleteLoading();
            
            // Prepare AJAX data
            const ajaxData = {
                action: 'ultimate_watermark_bulk_delete',
                watermark_ids: selectedIds,
                nonce: ultimate_watermark_ajax.nonce
            };
            
            
            // Make AJAX request
            $.ajax({
                url: ultimate_watermark_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    this.hideBulkDeleteLoading();
                    
                    if (response.success) {
                        // Remove deleted rows from table
                        selectedIds.forEach(id => {
                            $(`.watermark-checkbox[value="${id}"]`).closest('tr').fadeOut(300, function() {
                                $(this).remove();
                            });
                        });
                        
                        // Show success message
                        if (typeof UWNotifications !== 'undefined') {
                            UWNotifications.success('Success', response.data.message);
                        } else {
                            alert('Success: ' + response.data.message);
                        }
                        
                        // Update table count if needed
                        this.updateTableCount();
                    } else {
                        if (typeof UWNotifications !== 'undefined') {
                            UWNotifications.error('Error', response.data.message || 'Failed to delete watermarks');
                        } else {
                            alert('Error: ' + (response.data.message || 'Failed to delete watermarks'));
                        }
                    }
                },
                error: (xhr, status, error) => {
                    this.hideBulkDeleteLoading();
                    
                    if (typeof UWNotifications !== 'undefined') {
                        UWNotifications.error('Error', 'Failed to delete watermarks. Please try again.');
                    } else {
                        alert('Error: Failed to delete watermarks. Please try again.');
                    }
                }
            });
        },

        /**
         * Show bulk delete loading state
         */
        showBulkDeleteLoading: function() {
            const $buttons = $('.tablenav .bulkactions .button');
            $buttons.prop('disabled', true);
            $buttons.addClass('loading');
            
            // Update delete button text to show loading
            $('#bulk-delete').html(`
                <span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span>
                Deleting...
            `);
        },

        /**
         * Hide bulk delete loading state
         */
        hideBulkDeleteLoading: function() {
            const $buttons = $('.tablenav .bulkactions .button');
            $buttons.prop('disabled', false);
            $buttons.removeClass('loading');
            
            // Restore original delete button text
            $('#bulk-delete').html(`
                <span class="dashicons dashicons-trash"></span>
                Delete
            `);
        },

        /**
         * Update table count after deletion
         */
        updateTableCount: function() {
            const remainingRows = $('.watermark-checkbox').length;
            const $countElement = $('.displaying-num');
            if ($countElement.length) {
                $countElement.text(`${remainingRows} items`);
            }
        },

        /**
         * Clear all selections
         */
        clearSelections: function() {
            $('.watermark-checkbox:checked').prop('checked', false);
            $('#select-all-watermarks').prop('checked', false);
            this.updateBulkActions();
        },

        /**
         * Show success toast message
         */
        showSuccessMessage: function(message) {
            this.showToast(message, 'success');
        },

        /**
         * Show error toast message
         */
        showErrorMessage: function(message) {
            this.showToast(message, 'error');
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type = 'info') {
            // Remove existing toasts
            $('.ultimate-watermark-toast').remove();
            
            const toastClass = type === 'success' ? 'toast-success' : type === 'error' ? 'toast-error' : 'toast-info';
            const icon = type === 'success' ? 'dashicons-yes-alt' : type === 'error' ? 'dashicons-warning' : 'dashicons-info';
            
            const toast = $(`
                <div class="ultimate-watermark-toast ${toastClass}">
                    <span class="dashicons ${icon}"></span>
                    <span class="toast-message">${message}</span>
                    <button class="toast-close" type="button">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `);
            
            // Add to page
            $('body').append(toast);
            
            // Show with animation
            setTimeout(() => {
                toast.addClass('show');
            }, 100);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                this.hideToast(toast);
            }, 5000);
            
            // Close button handler
            toast.find('.toast-close').on('click', () => {
                this.hideToast(toast);
            });
        },

        /**
         * Hide toast notification
         */
        hideToast: function(toast) {
            toast.removeClass('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        },

        /**
         * Refresh the watermark table
         */
        refreshTable: function() {
            // Show loading state
            this.showTableLoading();
            
            // Reload the page to get fresh data
            // In a real implementation, you might want to use AJAX to refresh just the table content
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        },

        /**
         * Show table loading state
         */
        showTableLoading: function() {
            const $table = $('.watermarks-table-container');
            const loadingOverlay = $(`
                <div class="table-loading-overlay">
                    <div class="loading-spinner">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <span class="loading-text">Refreshing table...</span>
                </div>
            `);
            
            $table.css('position', 'relative');
            $table.append(loadingOverlay);
        },


        /**
         * Search watermarks
         */
        searchWatermarks: function() {
            const searchTerm = $(this).val().toLowerCase();
            const rows = $('.watermark-row');
            
            rows.each(function() {
                const row = $(this);
                const name = row.find('.watermark-name strong').text().toLowerCase();
                const description = row.find('.watermark-description').text().toLowerCase();
                const type = row.find('.watermark-type span').text().toLowerCase();
                
                if (name.includes(searchTerm) || description.includes(searchTerm) || type.includes(searchTerm)) {
                    row.show();
                } else {
                    row.hide();
                }
            });
        },

        /**
         * Switch form tab
         */
        switchTab: function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            
            $('.form-tab').removeClass('active');
            $('.form-tab-content').removeClass('active');
            
            $(this).addClass('active');
            $('#tab-' + tab).addClass('active');
        },

        /**
         * Toggle watermark type sections
         */
        toggleWatermarkType: function() {
            const selectedType = $('input[name="watermark_type"]:checked').val();
            
            if (selectedType === 'text') {
                $('#text-settings').show();
                $('#image-settings').hide();
            } else {
                $('#text-settings').hide();
                $('#image-settings').show();
            }
            
            this.updatePreview();
        },

        /**
         * Update opacity value display
         */
        updateOpacityValue: function() {
            const opacity = $(this).val();
            $('.range-value').text(opacity + '%');
            this.updatePreview();
        },

        /**
         * Handle image upload
         */
        handleImageUpload: function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#watermark-image-preview').html('<img src="' + e.target.result + '" alt="Preview" style="max-width: 200px; height: auto;">').show();
                };
                reader.readAsDataURL(file);
            }
        },

        /**
         * Initialize file upload
         */
        initFileUpload: function() {
            const uploadArea = $('.upload-area');
            const fileInput = $('#watermark_image');
            
            uploadArea.on('click', function() {
                fileInput.click();
            });
            
            uploadArea.on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });
            
            uploadArea.on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });
            
            uploadArea.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    fileInput[0].files = files;
                    WatermarksPage.handleImageUpload({ target: { files: files } });
                }
            });
        },

        /**
         * Update preview
         */
        updatePreview: function() {
            // TODO: Implement live preview update
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            // TODO: Implement form submission via AJAX
            
            // For now, just close the modal
            WatermarksPage.closeModal();
        },


        /**
         * Initialize sorting
         */
        initSorting: function() {
            // TODO: Implement table sorting if needed
        },

        /**
         * Show upgrade modal for Pro features
         */
        showUpgradeModal: function(message, upgradeUrl) {
            // Create modal HTML
            const modalHtml = `
                <div class="uw-upgrade-modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 999999; display: flex; align-items: center; justify-content: center;">
                    <div class="uw-upgrade-modal" style="background: #fff; border-radius: 12px; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideIn 0.3s ease;">
                        <div class="uw-upgrade-header" style="background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); color: white; padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
                            <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-bottom: 15px;">
                                <circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.2)"/>
                                <path d="M32 16L36 28L48 32L36 36L32 48L28 36L16 32L28 28L32 16Z" fill="white"/>
                            </svg>
                            <h2 style="margin: 0; font-size: 24px; font-weight: 700;">Upgrade to Pro</h2>
                        </div>
                        <div class="uw-upgrade-body" style="padding: 30px; text-align: center;">
                            <p style="font-size: 16px; color: #374151; margin: 0 0 20px 0; line-height: 1.6;">${message}</p>
                            <div style="background: #f9fafb; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                                <h3 style="margin: 0 0 15px 0; font-size: 18px; color: #1d2327;">Pro Features Include:</h3>
                                <ul style="list-style: none; padding: 0; margin: 0; text-align: left;">
                                    <li style="padding: 8px 0; color: #374151; display: flex; align-items: center;">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 10px; flex-shrink: 0;">
                                            <circle cx="10" cy="10" r="9" fill="#10b981"/>
                                            <path d="M6 10L9 13L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        Unlimited Watermarks
                                    </li>
                                    <li style="padding: 8px 0; color: #374151; display: flex; align-items: center;">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 10px; flex-shrink: 0;">
                                            <circle cx="10" cy="10" r="9" fill="#10b981"/>
                                            <path d="M6 10L9 13L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        Dynamic Content Watermarks
                                    </li>
                                    <li style="padding: 8px 0; color: #374151; display: flex; align-items: center;">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 10px; flex-shrink: 0;">
                                            <circle cx="10" cy="10" r="9" fill="#10b981"/>
                                            <path d="M6 10L9 13L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        WooCommerce Integration
                                    </li>
                                    <li style="padding: 8px 0; color: #374151; display: flex; align-items: center;">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 10px; flex-shrink: 0;">
                                            <circle cx="10" cy="10" r="9" fill="#10b981"/>
                                            <path d="M6 10L9 13L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        Advanced Rules Engine
                                    </li>
                                </ul>
                            </div>
                            <div style="display: flex; gap: 10px; justify-content: center;">
                                <a href="${upgradeUrl}" target="_blank" class="uw-upgrade-btn uw-pro-cta" style="background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; transition: transform 0.2s;">
                                    Upgrade Now - $79/year
                                </a>
                                <button class="uw-upgrade-close" style="background: #f3f4f6; color: #374151; padding: 12px 30px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                                    Maybe Later
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes slideIn {
                        from { transform: translateY(-50px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }
                    .uw-upgrade-btn:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 10px 20px rgba(245, 158, 11, 0.45);
                    }
                    .uw-upgrade-close:hover {
                        background: #e5e7eb;
                    }
                </style>
            `;
            
            // Append to body
            $('body').append(modalHtml);
            
            // Close modal on button click or overlay click
            $('.uw-upgrade-close, .uw-upgrade-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    $('.uw-upgrade-modal-overlay').fadeOut(200, function() {
                        $(this).remove();
                    });
                }
            });
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            // Escape key to close modal
            if (e.keyCode === 27 && $('#watermark-modal').is(':visible')) {
                WatermarksPage.closeModal();
            }
            
            // Escape key to close upgrade modal
            if (e.keyCode === 27 && $('.uw-upgrade-modal-overlay').is(':visible')) {
                $('.uw-upgrade-modal-overlay').fadeOut(200, function() {
                    $(this).remove();
                });
            }
            
            // Ctrl/Cmd + N to add new watermark
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 78) {
                e.preventDefault();
                WatermarksPage.openModal();
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WatermarksPage.init();
    });

    // Make WatermarksPage available globally
    window.UltimateWatermarkPage = WatermarksPage;

})(jQuery);
