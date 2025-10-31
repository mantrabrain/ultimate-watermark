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
        echo '<div class="uw-stat-number">' . esc_html($this->formatSizePrecise($stats['total_size'])) . '</div>';
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
        
        // Backups Section - Load initial page via AJAX
        echo '<div class="uw-backups-section">';
        echo '<div class="uw-section-header">';
        echo '<h3 class="uw-section-title">' . esc_html__('Backup Files', 'ultimate-watermark') . '</h3>';
        echo '<div class="uw-section-actions">';
        echo '<span class="uw-backup-count">' . esc_html__('Loading...', 'ultimate-watermark') . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="uw-backups-table-container">';
        
        // Top pagination
        echo '<div class="uw-pagination-container uw-pagination-top" id="uw-backups-pagination-top" style="display: none;">';
        echo '<div class="uw-pagination-info">';
        echo '<span class="uw-pagination-text"></span>';
        echo '</div>';
        echo '<div class="uw-pagination-nav">';
        echo '<button type="button" class="uw-pagination-btn uw-pagination-prev" data-page="prev" data-position="top" disabled>';
        echo '<span class="dashicons dashicons-arrow-left-alt2"></span>';
        echo esc_html__('Previous', 'ultimate-watermark');
        echo '</button>';
        echo '<div class="uw-pagination-numbers"></div>';
        echo '<button type="button" class="uw-pagination-btn uw-pagination-next" data-page="next" data-position="top" disabled>';
        echo esc_html__('Next', 'ultimate-watermark');
        echo '<span class="dashicons dashicons-arrow-right-alt2"></span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
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
        echo '<tbody id="uw-backups-tbody">';
        echo '<tr class="uw-loading-row">';
        echo '<td colspan="5" class="uw-loading-cell">';
        echo '<div class="uw-loading-spinner">';
        echo '<span class="dashicons dashicons-update"></span>';
        echo '<span>' . esc_html__('Loading backups...', 'ultimate-watermark') . '</span>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
        
        // Bottom pagination controls
        echo '<div class="uw-pagination-container uw-pagination-bottom" id="uw-backups-pagination" style="display: none;">';
        echo '<div class="uw-pagination-info">';
        echo '<span class="uw-pagination-text"></span>';
        echo '</div>';
        echo '<div class="uw-pagination-nav">';
        echo '<button type="button" class="uw-pagination-btn uw-pagination-prev" data-page="prev" data-position="bottom" disabled>';
        echo '<span class="dashicons dashicons-arrow-left-alt2"></span>';
        echo esc_html__('Previous', 'ultimate-watermark');
        echo '</button>';
        echo '<div class="uw-pagination-numbers"></div>';
        echo '<button type="button" class="uw-pagination-btn uw-pagination-next" data-page="next" data-position="bottom" disabled>';
        echo esc_html__('Next', 'ultimate-watermark');
        echo '<span class="dashicons dashicons-arrow-right-alt2"></span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Empty state (hidden initially, shown via JS if no backups)
        echo '<div class="uw-empty-state" id="uw-empty-backups" style="display: none;">';
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
        
        
        echo '</div>'; // Close uw-backup-page
        
        // Image Modal
        echo '<div id="uw-image-modal" class="uw-image-modal" style="display: none;">';
        echo '<div class="uw-modal-overlay"></div>';
        echo '<div class="uw-modal-content">';
        echo '<div class="uw-modal-header">';
        echo '<h3 class="uw-modal-title">Image Preview</h3>';
        echo '<button type="button" class="uw-modal-close" id="uw-close-modal">';
        echo '<span class="dashicons dashicons-no-alt"></span>';
        echo '</button>';
        echo '</div>';
        echo '<div class="uw-modal-body">';
        echo '<img id="uw-modal-image" src="" alt="" class="uw-modal-image">';
        echo '</div>';
        echo '<div class="uw-modal-footer">';
        echo '<button type="button" class="uw-btn uw-btn-secondary" id="uw-download-image">';
        echo '<span class="dashicons dashicons-download"></span>';
        echo 'Download';
        echo '</button>';
        echo '<button type="button" class="uw-btn uw-btn-primary" id="uw-view-full">';
        echo '<span class="dashicons dashicons-external"></span>';
        echo 'View Full Size';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render individual backup table row
     */
    private function renderBackupTableRow(array $backup): void
    {
        // Get additional backup sizes for this attachment
        $additional_sizes = $this->getAdditionalBackupSizes($backup['id']);
        $total_size = $backup['size'];
        
        // Calculate total size including additional sizes
        foreach ($additional_sizes as $size) {
            $total_size += $size['size'];
        }
        
        echo '<tr class="uw-backup-row" data-attachment-id="' . esc_attr($backup['id']) . '">';
        
        // Checkbox column
        echo '<td class="uw-col-checkbox">';
        echo '<input type="checkbox" class="uw-checkbox uw-backup-checkbox" value="' . esc_attr($backup['id']) . '">';
        echo '</td>';
        
        // File name column
        echo '<td class="uw-col-name">';
        echo '<div class="uw-file-info">';
        echo '<div class="uw-file-thumbnail">';
        echo '<img src="' . esc_url($backup['url']) . '" alt="' . esc_attr($backup['title']) . '" loading="lazy" class="uw-thumbnail-image" data-image-url="' . esc_url($backup['url']) . '" data-image-title="' . esc_attr($backup['title']) . '">';
        echo '</div>';
        echo '<div class="uw-file-details">';
        echo '<div class="uw-file-name">' . esc_html($backup['title']) . '</div>';
        echo '<div class="uw-file-path">' . esc_html(basename($backup['backup_path'])) . '</div>';
        
        // Show additional sizes count if any
        if (!empty($additional_sizes)) {
            $count = count($additional_sizes);
            echo '<div class="uw-additional-sizes">';
            echo '<span class="uw-size-count">+' . $count . ' ' . esc_html(_n('additional size', 'additional sizes', $count, 'ultimate-watermark')) . '</span>';
            echo '<button type="button" class="uw-toggle-children" data-attachment-id="' . esc_attr($backup['id']) . '">';
            echo '<span class="dashicons dashicons-arrow-down-alt2"></span>';
            echo '</button>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        echo '</td>';
        
        // Size column
        echo '<td class="uw-col-size">';
        echo '<span class="uw-file-size">' . esc_html($this->formatSizePrecise($total_size)) . '</span>';
        if (!empty($additional_sizes)) {
            echo '<div class="uw-size-breakdown">';
            echo '<span class="uw-main-size">' . esc_html($this->formatSizePrecise($backup['size'])) . ' ' . esc_html__('main', 'ultimate-watermark') . '</span>';
            echo '<span class="uw-additional-size">+' . esc_html($this->formatSizePrecise($total_size - $backup['size'])) . ' ' . esc_html__('additional', 'ultimate-watermark') . '</span>';
            echo '</div>';
        }
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
        
        // Add child rows for additional sizes
        if (!empty($additional_sizes)) {
            foreach ($additional_sizes as $size) {
                echo '<tr class="uw-backup-child-row" data-parent-id="' . esc_attr($backup['id']) . '" style="display: none;">';
                
                // Empty checkbox column for child rows
                echo '<td class="uw-col-checkbox"></td>';
                
                // File name column with indentation
                echo '<td class="uw-col-name">';
                echo '<div class="uw-file-info uw-child-file">';
                echo '<div class="uw-file-thumbnail uw-child-thumbnail">';
                echo '<img src="' . esc_url($size['url']) . '" alt="' . esc_attr($size['title']) . '" loading="lazy" class="uw-thumbnail-image" data-image-url="' . esc_url($size['url']) . '" data-image-title="' . esc_attr($size['title']) . '">';
                echo '</div>';
                echo '<div class="uw-file-details">';
                echo '<div class="uw-file-name">' . esc_html($size['title']) . '</div>';
                echo '<div class="uw-file-path">' . esc_html(basename($size['path'])) . '</div>';
                echo '<div class="uw-size-type">' . esc_html(ucfirst($size['type'])) . ' Size</div>';
                echo '</div>';
                echo '</div>';
                echo '</td>';
                
                // Size column
                echo '<td class="uw-col-size">';
                echo '<span class="uw-file-size">' . esc_html($this->formatSizePrecise($size['size'])) . '</span>';
                echo '</td>';
                
                // Date column
                echo '<td class="uw-col-date">';
                echo '<span class="uw-file-date">' . esc_html(date('M j, Y', $size['created'])) . '</span>';
                echo '</td>';
                
                // Actions column (empty for child rows)
                echo '<td class="uw-col-actions"></td>';
                
                echo '</tr>';
            }
        }
    }
    
    /**
     * Get additional backup sizes for an attachment
     * 
     * @param int $attachment_id WordPress attachment ID
     * @return array Array of additional backup sizes
     */
    private function getAdditionalBackupSizes(int $attachment_id): array
    {
        $backup_paths = get_post_meta($attachment_id, '_ultimate_watermark_backup_paths', true);
        $additional_sizes = [];
        
        if (is_array($backup_paths) && !empty($backup_paths)) {
            foreach ($backup_paths as $size_name => $backup_path) {
                if ($size_name !== 'original' && file_exists($backup_path)) {
                    $attachment = get_post($attachment_id);
                    $additional_sizes[] = [
                        'name' => $size_name,
                        'title' => $attachment ? $attachment->post_title . ' (' . ucfirst($size_name) . ')' : 'Unknown Image',
                        'type' => $size_name,
                        'path' => $backup_path,
                        'size' => filesize($backup_path),
                        'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $backup_path),
                        'created' => filemtime($backup_path)
                    ];
                }
            }
        }
        
        return $additional_sizes;
    }
    
    /**
     * Format file size with more precision than WordPress default
     * 
     * @param int $bytes File size in bytes
     * @return string Formatted size string
     */
    private function formatSizePrecise(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            $mb = $bytes / (1024 * 1024);
            if ($mb >= 100) {
                return round($mb) . ' MB';
            } else {
                return number_format($mb, 2) . ' MB';
            }
        } elseif ($bytes >= 1024) {
            $kb = $bytes / 1024;
            if ($kb >= 100) {
                return round($kb) . ' KB';
            } else {
                return number_format($kb, 2) . ' KB';
            }
        } else {
            return $bytes . ' B';
        }
    }
}

