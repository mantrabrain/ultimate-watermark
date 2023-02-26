<div class="postbox">
    <div class="postbox-header"><h2><?php echo esc_html__('Watermark Settings', 'ultimate-watermark') ?></h2>
    </div>
    <div class="inside">
        <div class="ultimate-watermark-metabox">
            <div class="ultimate-watermark-tabs">
                <div class="ultimate-watermark-tab-nav">
                    <ul>
                        <?php


                        foreach ($setting_tabs

                        as $tab_id => $tab_label) {
                        $tab_item_class = $tab_id === $active_tab ? 'ultimate-watermark-tab-nav-item item-active' : 'ultimate-watermark-tab-nav-item';
                        ?>
                        <li><a href="#" class="<?php echo esc_attr($tab_item_class) ?>"
                               data-tab="<?php echo esc_attr($tab_id) ?>"
                               id="<?php echo 'ultimate_watermark_' . esc_attr($tab_id) . '_tab' ?>">
                                <?php echo esc_html($tab_label); ?>
                            </a>
                            <?php } ?>
                        </li>


                    </ul>
                </div>
                <div class="ultimate-watermark-tab-content">
                    <div class="ultimate-watermark-tab-sections">
                        <?php foreach ($setting_tabs as $tab_id_for_content => $tab_label_for_content) {
                            $tab_content_class = $tab_id_for_content === $active_tab ? 'ultimate-watermark-tab-section' : 'ultimate-watermark-tab-section ultimate-watermark-hide';
                            $tab_content_class .= ' ultimate_watermark_' . esc_attr($tab_id_for_content) . '_tab_content';
                            ?>
                            <div class="<?php echo esc_attr($tab_content_class) ?>">
                                <?php
                                do_action('ultimate_watermark_meta_tab_content_' . $tab_id_for_content);
                                ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="ultimate-watermark-nav-background"></div>
                <div class="clear"></div>
                <input type="hidden" name="ultimate_watermark_meta_active_tab"
                       value="<?php echo esc_attr($active_tab) ?>"/>
            </div>
        </div>
    </div>
</div>
