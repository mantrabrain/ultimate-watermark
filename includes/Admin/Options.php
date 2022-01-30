<?php

namespace Ultimate_Watermark\Admin;

class Options
{
    private $options = array();

    public function __construct()
    {
        $this->options = $this->get_options();
    }

    private function default_options()
    {
        return array(
            'watermark_on' => array(),
            'watermark_cpt_on' => array('everywhere'),
            'watermark_image' => array(
                'extension' => '',
                'attachment_id' => 0,
                'width' => 80,
                'plugin_off' => 0,
                'frontend_active' => false,
                'manual_watermarking' => 0,
                'position' => 'bottom_right',
                'watermark_size_type' => 2,
                'offset_unit' => 'pixels',
                'offset_width' => 0,
                'offset_height' => 0,
                'absolute_width' => 0,
                'absolute_height' => 0,
                'transparent' => 50,
                'quality' => 90,
                'jpeg_format' => 'baseline',
                'deactivation_delete' => false,
                'media_library_notice' => true
            ),
            'image_protection' => array(
                'rightclick' => 0,
                'draganddrop' => 0,
                'forlogged' => 0
            ),
            'backup' => array(
                'backup_image' => true,
                'backup_quality' => 90
            )
        );
    }

    public function get($option_id, $option_id_child = null)
    {
        if (isset($this->options[$option_id])) {
            if (is_array($this->options[$option_id]) && !is_null($option_id_child)) {

                return isset($this->options[$option_id][$option_id_child]) ? $this->options[$option_id][$option_id_child] : null;
            }
            return $this->options[$option_id];
        }
        return null;
    }

    public function update($option_id, $option_value, $option_id_child = null)
    {

        if (isset($this->options[$option_id])) {

            if (is_null($option_id_child)) {
                $this->options[$option_id] = $option_value;
            } else if (isset($this->options[$option_id][$option_id_child])) {
                $this->options[$option_id][$option_id_child] = $option_value;

            }
        }
        update_option('ultimate_watermark_options', $this->options);
    }

    public function get_options()
    {
        return get_option('ultimate_watermark_options', $this->default_options());
    }

}