<?php

namespace MantraBrain\UltimateWatermark\Admin\Pages;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;
use MantraBrain\UltimateWatermark\Admin\Components\Layout;
use MantraBrain\UltimateWatermark\Utils\BackupManager;

/**
 * Backup Management Page
 * 
 * Displays and manages backup images
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class BackupPage
{
    use SingletonTrait;

    /**
     * Render backup management page
     */
    public function render(): void
    {
        $actions = '<a href="' . esc_url(admin_url('admin.php?page=ultimate-watermark-dashboard')) . '" class="btn btn-secondary">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            ' . esc_html__('Back to Dashboard', 'ultimate-watermark') . '
        </a>';

        Layout::render(
            __('Backup Management', 'ultimate-watermark'),
            [$this, 'renderBackupContent'],
            ['actions' => $actions]
        );
    }

    /**
     * Render backup content
     */
    public function renderBackupContent(): void
    {
        $stats = BackupManager::getBackupStats();
        
        echo '<div class="uw-backup-page">';
        
        // Header Section
        echo '<div class="uw-backup-header">';
        echo '<div class="uw-header-content">';
        echo '<div class="uw-header-info">';
        echo '<h2 class="uw-page-title">' . esc_html__('Backup Management', 'ultimate-watermark') . '</h2>';
        echo '<p class="uw-page-description">' . esc_html__('Manage your image backups created during watermarking', 'ultimate-watermark') . '</p>';
        echo '</div>';
        echo '<div class="uw-header-actions">';
        echo '<a href="' . esc_url(admin_url('upload.php')) . '" class="uw-btn uw-btn-secondary">';
        echo '<span class="dashicons dashicons-upload"></span>';
        echo esc_html__('Media Library', 'ultimate-watermark');
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Stats Cards
        echo '<div class="uw-stats-grid">';
        echo '<div class="uw-stat-card">';
        echo '<div class="uw-stat-icon">';
        echo '<span class="dashicons dashicons-images-alt2"></span>';
        echo '</div>';
        echo '<div class="uw-stat-content">';
        echo '<div class="uw-stat-number">' . esc_html($stats['total_backups']) . '</div>';
        echo '<div class="uw-stat-label">' . esc_html__('Total Backups', 'ultimate-watermark') . '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="uw-stat-card">';
        echo '<div class="uw-stat-icon">';
        echo '<span class="dashicons dashicons-database"></span>';
        echo '</div>';
        echo '<div class="uw-stat-content">';
        echo '<div class="uw-stat-number">' . esc_html(size_format($stats['total_size'])) . '</div>';
        echo '<div class="uw-stat-label">' . esc_html__('Storage Used', 'ultimate-watermark') . '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="uw-stat-card">';
        echo '<div class="uw-stat-icon">';
        echo '<span class="dashicons dashicons-shield-alt"></span>';
        echo '</div>';
        echo '<div class="uw-stat-content">';
        echo '<div class="uw-stat-number">' . esc_html($stats['backup_enabled_count']) . '</div>';
        echo '<div class="uw-stat-label">' . esc_html__('Protected Images', 'ultimate-watermark') . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Backups Section
        if (!empty($stats['recent_backups'])) {
            echo '<div class="uw-backups-section">';
            echo '<div class="uw-section-header">';
            echo '<h3 class="uw-section-title">' . esc_html__('Backup Files', 'ultimate-watermark') . '</h3>';
            echo '<div class="uw-section-actions">';
            echo '<span class="uw-backup-count">' . count($stats['recent_backups']) . ' ' . esc_html__('files', 'ultimate-watermark') . '</span>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="uw-backups-table-container">';
            echo '<div class="uw-bulk-actions">';
            echo '<div class="uw-bulk-controls">';
            echo '<div class="uw-bulk-buttons">';
            echo '<button type="button" id="bulk-restore-btn" class="uw-btn uw-btn-primary uw-btn-small" disabled>';
            echo '<span class="dashicons dashicons-undo"></span>';
            echo esc_html__('Restore Selected', 'ultimate-watermark');
            echo '</button>';
            echo '<button type="button" id="bulk-delete-btn" class="uw-btn uw-btn-danger uw-btn-small" disabled>';
            echo '<span class="dashicons dashicons-trash"></span>';
            echo esc_html__('Delete Selected', 'ultimate-watermark');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<table class="uw-backups-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th class="uw-col-checkbox">';
            echo '<input type="checkbox" id="select-all-header" class="uw-checkbox">';
            echo '</th>';
            echo '<th class="uw-col-name">' . esc_html__('File Name', 'ultimate-watermark') . '</th>';
            echo '<th class="uw-col-size">' . esc_html__('Size', 'ultimate-watermark') . '</th>';
            echo '<th class="uw-col-date">' . esc_html__('Created', 'ultimate-watermark') . '</th>';
            echo '<th class="uw-col-actions">' . esc_html__('Actions', 'ultimate-watermark') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            foreach ($stats['recent_backups'] as $backup) {
                $this->renderBackupTableRow($backup);
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="uw-empty-state">';
            echo '<div class="uw-empty-icon">';
            echo '<span class="dashicons dashicons-images-alt2"></span>';
            echo '</div>';
            echo '<h3 class="uw-empty-title">' . esc_html__('No Backups Yet', 'ultimate-watermark') . '</h3>';
            echo '<p class="uw-empty-description">' . esc_html__('Backups will appear here when you apply watermarks to your images.', 'ultimate-watermark') . '</p>';
            echo '<a href="' . esc_url(admin_url('upload.php')) . '" class="uw-btn uw-btn-primary">';
            echo '<span class="dashicons dashicons-upload"></span>';
            echo esc_html__('Upload Images', 'ultimate-watermark');
            echo '</a>';
            echo '</div>';
        }
        
        echo '</div>'; // Close uw-backup-page
    }

    /**
     * Render individual backup table row
     */
    private function renderBackupTableRow(array $backup): void
    {
        echo '<tr class="uw-backup-row" data-attachment-id="' . esc_attr($backup['id']) . '">';
        
        // Checkbox column
        echo '<td class="uw-col-checkbox">';
        echo '<input type="checkbox" class="uw-checkbox uw-backup-checkbox" value="' . esc_attr($backup['id']) . '">';
        echo '</td>';
        
        // File name column
        echo '<td class="uw-col-name">';
        echo '<div class="uw-file-info">';
        echo '<div class="uw-file-thumbnail">';
        echo '<img src="' . esc_url($backup['url']) . '" alt="' . esc_attr($backup['title']) . '" loading="lazy">';
        echo '</div>';
        echo '<div class="uw-file-details">';
        echo '<div class="uw-file-name">' . esc_html($backup['title']) . '</div>';
        echo '<div class="uw-file-path">' . esc_html(basename($backup['backup_path'])) . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</td>';
        
        // Size column
        echo '<td class="uw-col-size">';
        echo '<span class="uw-file-size">' . esc_html(size_format($backup['size'])) . '</span>';
        echo '</td>';
        
        // Date column
        echo '<td class="uw-col-date">';
        echo '<div class="uw-date-info">';
        echo '<div class="uw-date-relative">' . esc_html(human_time_diff(strtotime($backup['backup_created']))) . ' ' . esc_html__('ago', 'ultimate-watermark') . '</div>';
        echo '<div class="uw-date-absolute">' . esc_html(date('M j, Y', strtotime($backup['backup_created']))) . '</div>';
        echo '</div>';
        echo '</td>';
        
        // Actions column
        echo '<td class="uw-col-actions">';
        echo '<div class="uw-row-actions">';
        echo '<a href="' . esc_url($backup['url']) . '" class="uw-action-btn uw-action-view" target="_blank" title="' . esc_attr__('View backup', 'ultimate-watermark') . '">';
        echo '<span class="dashicons dashicons-visibility"></span>';
        echo '</a>';
        echo '<button type="button" class="uw-action-btn uw-action-restore restore-backup-btn" data-attachment-id="' . esc_attr($backup['id']) . '" title="' . esc_attr__('Restore from backup', 'ultimate-watermark') . '">';
        echo '<span class="dashicons dashicons-undo"></span>';
        echo '</button>';
        echo '<a href="' . esc_url(admin_url('post.php?post=' . $backup['id'] . '&action=edit')) . '" class="uw-action-btn uw-action-edit" title="' . esc_attr__('Edit original', 'ultimate-watermark') . '">';
        echo '<span class="dashicons dashicons-edit"></span>';
        echo '</a>';
        echo '<button type="button" class="uw-action-btn uw-action-delete delete-backup-btn" data-attachment-id="' . esc_attr($backup['id']) . '" title="' . esc_attr__('Delete backup', 'ultimate-watermark') . '">';
        echo '<span class="dashicons dashicons-trash"></span>';
        echo '</button>';
        echo '</div>';
        echo '</td>';
        
        echo '</tr>';
    }
}

