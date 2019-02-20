<?php
/*
Plugin Name: Ultimate Watermark
Description: Watermark plugin for WordPress media.
Version: 1.0.1
Author: mantrabrain
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
if ( ! defined( 'ABSPATH' ) )
	exit;

define( 'ULTIMATE_WATERMARK_URL', plugins_url( '', __FILE__ ) );
define( 'ULTIMATE_WATERMARK_PATH', plugin_dir_path( __FILE__ ) );


include_once ULTIMATE_WATERMARK_PATH.'includes/class-ultimate-watermark.php';
/**
 * Get instance of main class.
 *
 * @return object Instance
 */
function Ultimate_Watermark() {
	static $instance;

	// first call to instance() initializes the plugin
	if ( $instance === null || ! ( $instance instanceof Ultimate_Watermark ) )
		$instance = Ultimate_Watermark::instance();

	return $instance;
}

$ultimate_watermark = Ultimate_Watermark();