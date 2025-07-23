<?php
/*
Plugin Name: Ultimate Watermark
Description: Image Watermark plugin for WordPress media.
Version: 1.1
Author: MantraBrain
Author URI: https://mantrabrain.com/
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: ultimate-watermark
Domain Path: /languages

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// exit if accessed directly
if (!defined('ABSPATH'))
    exit;

define('ULTIMATE_WATERMARK_FILE', __FILE__);
define('ULTIMATE_WATERMARK_VERSION', '1.1');
define('ULTIMATE_WATERMARK_URI', plugins_url('', ULTIMATE_WATERMARK_FILE));
define('ULTIMATE_WATERMARK_DIR', plugin_dir_path(ULTIMATE_WATERMARK_FILE));

include_once plugin_dir_path(ULTIMATE_WATERMARK_FILE) . 'vendor/autoload.php';

/**
 * Get instance of main class.
 *
 * @return object Instance
 */

use Ultimate_Watermark\Init;

function ultimate_watermark()
{
    static $instance;

    // first call to instance() initializes the plugin
    if ($instance === null || !($instance instanceof Init))
        $instance = Init::instance();

    return $instance;
}

ultimate_watermark();