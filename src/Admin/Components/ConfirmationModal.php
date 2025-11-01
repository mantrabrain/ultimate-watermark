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
        string $title = '',
        string $message = '',
        string $confirmText = '',
        string $cancelText = '',
        string $confirmClass = 'btn-danger'
    ): void {
        // Set defaults with translations if not provided
        if (empty($title)) {
            $title = __('Confirm Action', 'ultimate-watermark');
        }
        if (empty($message)) {
            $message = __('Are you sure you want to proceed?', 'ultimate-watermark');
        }
        if (empty($confirmText)) {
            $confirmText = __('Confirm', 'ultimate-watermark');
        }
        if (empty($cancelText)) {
            $cancelText = __('Cancel', 'ultimate-watermark');
        }
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
