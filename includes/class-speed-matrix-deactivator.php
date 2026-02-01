<?php
/**
 * Fired during plugin deactivation
 *
 * @package Speed_Matrix
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed Matrix Deactivator Class
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

		// 3. Clear any scheduled cron events
		self::clear_scheduled_events();
	}

	/**
	 * Remove htaccess rules added by the plugin
	 */
	private static function speed_matrix_remove_rules() {
		// Initialize WP_Filesystem
		if ( ! self::initialize_filesystem() ) {
			return;
		}

		global $wp_filesystem;

		// Check if required function exists
		if ( ! function_exists( 'insert_with_markers' ) ) {
			return;
		}

		// Get home path safely
		$home_path = ABSPATH;
		if ( function_exists( 'get_home_path' ) ) {
			$home_path = get_home_path();
		}

		$htaccess = $home_path . '.htaccess';

		// Use WP_Filesystem methods instead of direct PHP functions
		if ( $wp_filesystem->exists( $htaccess ) && $wp_filesystem->is_writable( $htaccess ) ) {
			// Remove SpeedMatrix-Core markers
			insert_with_markers( $htaccess, 'SpeedMatrix-Core', array() );
		}
	}

	/**
	 * Clear all scheduled cron events
	 */
	private static function clear_scheduled_events() {
		// Clear auto cleanup event
		$timestamp = wp_next_scheduled( 'speed_matrix_auto_cleanup_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'speed_matrix_auto_cleanup_event' );
		}

		// Clear all instances of the hook
		wp_clear_scheduled_hook( 'speed_matrix_auto_cleanup_event' );
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
			// Load file.php if needed (deactivation context)
			if ( file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			} else {
				return false;
			}
		}

		// Initialize filesystem.
		$result = WP_Filesystem();

		return ( $result && $wp_filesystem );
	}
}