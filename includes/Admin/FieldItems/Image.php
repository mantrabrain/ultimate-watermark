<?php

namespace Ultimate_Watermark\Admin\FieldItems;

class Image
{
    public static function render($field, $field_id, $value, $group_id = null)
    {
        $class = $field['class'] ?? '';

        $after = $field['after'] ?? '';

        $field_name = !(is_null($group_id)) ? $group_id . '[' . $field_id . ']' : $field_id;

        $image_id = is_array($value) ? 0 : $value;

        ?>
        <div class="ultimate-watermark-fieldset">
            <div class="ultimate-watermark-image-field-wrap">
                <a class="ultimate-watermark-image-field-add <?php echo $image_id > 1 ? 'ultimate-watermark-hide' : ''; ?>"
                   href="#"
                   data-uploader-title="Add new image"
                   data-uploader-button-text="Add new image">
                    <img src="<?php echo esc_url(ULTIMATE_WATERMARK_URI) ?>/assets/images/upload-image.png">
                    <h3>Drop your file here, or <span>browse</span></h3>
                    <p>Supports: JPG, JPEG, PNG</p>
                </a>
                <div class="image-container<?php echo $image_id < 1 ? ' ultimate-watermark-hide' : ''; ?>">
                    <input type="hidden" class="image-field" name="<?php echo esc_attr($field_name); ?>"
                           value="<?php echo absint($image_id) ?>"/>
                    <?php
                    if ($image_id > 0) {


                        $image_src = wp_get_attachment_image_url($image_id, 'full');

                        ?>
                        <div class="image-wrapper" data-url="<?php echo esc_url_raw($image_src) ?>">
                            <div class="image-content"><img
                                        src="<?php echo esc_url_raw($image_src) ?>"
                                        alt="">
                                <div class="image-overlay"><a
                                            class="ultimate-watermark-image-delete remove dashicons dashicons-trash"></a>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <?php
                if ($image_id > 0) {

                    $meta_data = wp_get_attachment_metadata($image_id);

                    $height = isset($meta_data['height']) ? absint($meta_data['height']) : 0;

                    $width = isset($meta_data['width']) ? absint($meta_data['width']) : 0;

                    echo '<p class="label">Original Size : '.esc_html($width).'px / '.esc_html($height).'px</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    public static function sanitize($field, $raw_value, $field_id)
    {
        return absint($raw_value);

    }

}
