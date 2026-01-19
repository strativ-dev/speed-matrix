<?php
/**
 * Plugin Name:       Speed Matrix
 * Plugin URI:        https://wordpress.org/plugins/speed-matrix/
 * Description:       Ultimate WordPress performance optimizer with improved PageSpeed scores. Features advanced caching, CSS/JS optimization, lazy loading, and more.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Strativ
 * Author URI:        https://strativ.se/
 * Text Domain:       speed-matrix
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Speed_Matrix
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'SPEED_MATRIX_VERSION', '1.0.0' );

/**
 * Plugin file path.
 */
define( 'SPEED_MATRIX_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path.
 */
define( 'SPEED_MATRIX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'SPEED_MATRIX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Cache directory path.
 */
define( 'SPEED_MATRIX_CACHE_DIR', WP_CONTENT_DIR . '/cache/speed-matrix/' );

/**
 * Cache directory URL.
 */
define( 'SPEED_MATRIX_CACHE_URL', content_url( 'cache/speed-matrix/' ) );

/**
 * The code that runs during plugin activation.
 */
function speed_matrix_activate() {
	require_once SPEED_MATRIX_PLUGIN_DIR . 'includes/class-speed-matrix-activator.php';
	Speed_Matrix_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function speed_matrix_deactivate() {
	require_once SPEED_MATRIX_PLUGIN_DIR . 'includes/class-speed-matrix-deactivator.php';
	Speed_Matrix_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'speed_matrix_activate' );
register_deactivation_hook( __FILE__, 'speed_matrix_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once SPEED_MATRIX_PLUGIN_DIR . 'includes/class-speed-matrix.php';

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function speed_matrix_run() {
	$plugin = new Speed_Matrix();
	$plugin->run();
}
speed_matrix_run();