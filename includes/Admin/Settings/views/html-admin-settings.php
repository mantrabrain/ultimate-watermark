<?php
/**
 * Admin View: Settings
 *
 * @package Ultimate_Watermark
 */

if (!defined('ABSPATH')) {
    exit;
}

$tab_exists = isset($tabs[$current_tab]) || has_action('ultimate_watermark_sections_' . $current_tab) || has_action('ultimate_watermark_settings_' . $current_tab) || has_action('ultimate_watermark_settings_tabs_' . $current_tab);
$current_tab_label = isset($tabs[$current_tab]) ? $tabs[$current_tab] : '';

if (!$tab_exists) {
    wp_safe_redirect(admin_url('admin.php?page=ultimate-watermark'));
    exit;
}
?>
<div class="wrap ultimate-watermark-admin-setting-page-wrap">
    <!-- Header Section -->
    <div class="ultimate-watermark-header">
        <div class="ultimate-watermark-header-content">
            <h1 class="ultimate-watermark-title">
                <span class="dashicons dashicons-images-alt"></span>
                <?php echo esc_html__('Ultimate Watermark', 'ultimate-watermark'); ?>
            </h1>
            <p class="ultimate-watermark-subtitle">
                <?php echo esc_html__('Professional image watermarking for WordPress', 'ultimate-watermark'); ?>
            </p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="ultimate-watermark-content">
        <form method="<?php echo esc_attr(apply_filters('ultimate_watermark_settings_form_method_tab_' . $current_tab, 'post')); ?>"
              id="mainform" action="" enctype="multipart/form-data">
            
           

            <!-- Settings Content -->
            <div class="ultimate-watermark-settings-container">
                <div class="ultimate-watermark-settings-content">
                    <h1 class="screen-reader-text"><?php echo esc_html($current_tab_label); ?></h1>
                    
                    <!-- Sub Navigation -->
                    <div class="ultimate-watermark-sub-nav">
                        <?php
                        global $current_section;
                        $sections = apply_filters('ultimate_watermark_get_sections_image-watermark', array(
                            '' => __('General Settings', 'ultimate-watermark'),
                            'watermark-image' => __('Watermark Image', 'ultimate-watermark'),
                            'watermark-position' => __('Watermark Position', 'ultimate-watermark'),
                            'image-protection' => __('Image Protection & Backup', 'ultimate-watermark'),
                        ));
                        
                        if (!empty($sections)) {
                            echo '<div class="ultimate-watermark-sub-nav-tabs">';
                            foreach ($sections as $section_slug => $section_label) {
                                $section_url = admin_url('admin.php?page=ultimate-watermark&tab=image-watermark' . ($section_slug ? '&section=' . $section_slug : ''));
                                $active_class = ($current_section === $section_slug) ? 'ultimate-watermark-sub-nav-active' : '';
                                echo '<a href="' . esc_url($section_url) . '" class="ultimate-watermark-sub-nav-tab ' . $active_class . '">' . esc_html($section_label) . '</a>';
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <?php
                    do_action('ultimate_watermark_sections_' . $current_tab);
                    self::show_messages();
                    do_action('ultimate_watermark_settings_' . $current_tab);
                    do_action('ultimate_watermark_settings_tabs_' . $current_tab);
                    ?>
                    
                    <!-- Save Button Section -->
                    <?php if (empty($GLOBALS['hide_save_button'])) : ?>
                        <button name="save" class="ultimate-watermark-save-button" type="submit"
                                value="<?php esc_attr_e('Save changes', 'ultimate-watermark'); ?>">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e('Save changes', 'ultimate-watermark'); ?>
                        </button>
                    <?php endif; ?>
                    <?php wp_nonce_field('ultimate-watermark-settings'); ?>
                </div>
            </div>
        </form>
    </div>
</div>
