<?php

namespace MantraBrain\UltimateWatermark\Admin\Components;

/**
 * Confirmation Modal Component
 * 
 * Provides a reusable confirmation modal for delete and other destructive actions
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class ConfirmationModal
{
    /**
     * Render confirmation modal
     *
     * @param string $id Modal ID
     * @param string $title Modal title
     * @param string $message Modal message
     * @param string $confirmText Confirm button text
     * @param string $cancelText Cancel button text
     * @param string $confirmClass Confirm button CSS class
     */
    public static function render(
        string $id = 'confirmation-modal',
        string $title = 'Confirm Action',
        string $message = 'Are you sure you want to proceed?',
        string $confirmText = 'Confirm',
        string $cancelText = 'Cancel',
        string $confirmClass = 'btn-danger'
    ): void {
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="confirmation-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php echo esc_html($title); ?></h3>
                    <button type="button" class="modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">
                        <?php echo esc_html($cancelText); ?>
                    </button>
                    <button type="button" class="btn <?php echo esc_attr($confirmClass); ?> modal-confirm">
                        <?php echo esc_html($confirmText); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}
