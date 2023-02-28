<?php
function ultimate_watermark_html($option)
{
    $type = isset($option['type']) ? sanitize_text_field($option['type']) : 'text';

    $name = isset($option['name']) ? sanitize_text_field($option['name']) : '';

    $value = isset($option['value']) ? sanitize_text_field($option['value']) : '';


    switch ($type) {
        case "text":
            break;
        case "switch":
            $on_string = $option['on_text'] ?? 'On';
            $off_string = $option['off_text'] ?? 'Off';
            ?>
            <div class="ultimate-watermark-switch-control-wrap">
                <label class="ultimate-watermark-switch-control">
                    <input id="<?php echo esc_attr($name) ?>"
                           name="<?php echo esc_attr($name) ?>"
                           type="checkbox" value="1" <?php checked(1, $value); ?>/>
                    <span class="slider round" data-on="<?php echo esc_attr($on_string); ?>"
                          data-off="<?php echo esc_attr($off_string) ?>"></span>
                </label>
            </div>
            <?php
            break;
    }
}