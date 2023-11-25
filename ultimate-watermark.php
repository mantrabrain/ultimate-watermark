<?php
/*
Plugin Name: Ultimate Watermark
Description: Image Watermark plugin for WordPress media.
Version: 1.0.11
Author: MantraBrain
Author URI: https://mantrabrain.com/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Text Domain: ultimate-watermark
Domain Path: /languages
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

// exit if accessed directly
if (!defined('ABSPATH'))
    exit;

define('ULTIMATE_WATERMARK_FILE', __FILE__);
define('ULTIMATE_WATERMARK_VERSION', '1.0.11');
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