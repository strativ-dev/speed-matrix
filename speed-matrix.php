<?php
/**
 * Speed Matrix
 *
 * @package           Speed_Matrix
 * @author            strativ
 * @copyright         2025 strativ
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Speed Matrix
 * Plugin URI:        https://github.com/yourusername/speed-matrix
 * Description:       Ultimate WordPress performance optimizer.PageSpeed scores with advanced caching, CSS/JS optimization, lazy loading, and more.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            strativ
 * Author URI:        https://strativ.se/
 * Text Domain:       speed-matrix
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'SPEED_MATRIX_VERSION', '1.0.0' );
define( 'SPEED_MATRIX_PLUGIN_FILE', __FILE__ );
define( 'SPEED_MATRIX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPEED_MATRIX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPEED_MATRIX_CACHE_DIR', WP_CONTENT_DIR . '/cache/speed-matrix/' );
define( 'SPEED_MATRIX_CACHE_URL', content_url( 'cache/speed-matrix/' ) );

function speed_matrix_activate() {
	require_once SPEED_MATRIX_PLUGIN_DIR . 'includes/class-speed-matrix-activator.php';
	Speed_Matrix_Activator::activate();
}

function speed_matrix_deactivate() {
	require_once SPEED_MATRIX_PLUGIN_DIR . 'includes/class-speed-matrix-deactivator.php';
	Speed_Matrix_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'speed_matrix_activate' );
register_deactivation_hook( __FILE__, 'speed_matrix_deactivate' );

require_once SPEED_MATRIX_PLUGIN_DIR . 'includes/class-speed-matrix.php';

function speed_matrix_run() {
	$plugin = new Speed_Matrix();
	$plugin->run();
}
speed_matrix_run();
