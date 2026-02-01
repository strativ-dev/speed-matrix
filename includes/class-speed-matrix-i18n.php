<?php
/**
 * Define the internationalization functionality
 *
 * WordPress automatically loads translations from WordPress.org since WP 4.6.
 * This class is kept for future extensibility.
 *
 * @package Speed_Matrix
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed Matrix Internationalization Class
 */
class Speed_Matrix_i18n {

	/**
	 * WordPress automatically loads translations from WordPress.org since WP 4.6.
	 * 
	 * Local translations should be placed in:
	 * /wp-content/languages/plugins/speed-matrix-{locale}.mo
	 *
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {
		// Intentionally empty.
		// WordPress.org handles translations automatically since WP 4.6.
		// For local translations, users should place .mo files in:
		// /wp-content/languages/plugins/speed-matrix-{locale}.mo
	}
}