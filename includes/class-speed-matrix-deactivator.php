<?php
/**
 * Fired during plugin deactivation.
 */
class Speed_Matrix_Deactivator {

	/**
	 * The primary deactivation method.
	 */
	public static function deactivate() {
		// 1. Clear permalinks
		flush_rewrite_rules();

		// 2. Clean up the .htaccess markers
		self::speed_matrix_remove_rules();
	}


	private static function speed_matrix_remove_rules() {
		// Initialize WP_Filesystem
		if ( ! self::initialize_filesystem() ) {
			return;
		}

		global $wp_filesystem;

		// Only proceed if we have the necessary functions
		if ( ! function_exists( 'insert_with_markers' ) ) {
			// Functions not available, skip cleanup
			return;
		}

		$home_path = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
		$htaccess = $home_path . '.htaccess';

		// Use WP_Filesystem methods instead of direct PHP functions
		if ( $wp_filesystem->exists( $htaccess ) && $wp_filesystem->is_writable( $htaccess ) ) {
			insert_with_markers( $htaccess, 'SpeedMatrix-Compression', array() );
		}
	}

	/**
	 * Helper function to initialize WP_Filesystem without direct require.
	 * 
	 * @return bool True if filesystem is ready, false otherwise.
	 */
	private static function initialize_filesystem() {
		global $wp_filesystem;

		// If already initialized, return true.
		if ( $wp_filesystem ) {
			return true;
		}

		// Check if WP_Filesystem function exists.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			return false;
		}

		// Initialize filesystem.
		$result = WP_Filesystem();

		return ( $result && $wp_filesystem );
	}

}