<?php
/**
 * Uninstall Speed Matrix
 *
 * Fired when the plugin is uninstalled.
 *
 * @package Speed_Matrix
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete cache directory and all files
 */
function speed_matrix_delete_cache_directory() {
	// Get upload directory for cache
	$upload_dir = wp_upload_dir();
	$speed_matrix_cache_dir = $upload_dir['basedir'] . '/speed-matrix-cache/';

	// Check if directory exists
	if ( ! is_dir( $speed_matrix_cache_dir ) ) {
		return;
	}

	// Initialize WP_Filesystem
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	WP_Filesystem();

	global $wp_filesystem;

	// Verify filesystem is available
	if ( ! $wp_filesystem ) {
		// Fallback to direct deletion if WP_Filesystem fails
		speed_matrix_recursive_delete( $speed_matrix_cache_dir );
		return;
	}

	// Delete cache directory recursively
	if ( $wp_filesystem->exists( $speed_matrix_cache_dir ) ) {
		$wp_filesystem->delete( $speed_matrix_cache_dir, true );
	}
}

/**
 * Fallback recursive delete function using WP_Filesystem
 *
 * @param string $dir Directory path to delete.
 */
function speed_matrix_recursive_delete( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	global $wp_filesystem;

	// Try to initialize WP_Filesystem if not already done
	if ( ! $wp_filesystem ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
	}

	// If WP_Filesystem still not available, abort
	if ( ! $wp_filesystem ) {
		return;
	}

	// Use WP_Filesystem to delete directory recursively
	$wp_filesystem->delete( $dir, true );
}

/**
 * Remove .htaccess rules
 */
function speed_matrix_remove_htaccess_rules() {
	// Initialize WP_Filesystem
	global $wp_filesystem;

	if ( ! $wp_filesystem ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
	}

	// Check if WP_Filesystem is available
	if ( ! $wp_filesystem ) {
		return;
	}

	// Check if required functions exist
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}

	if ( ! function_exists( 'insert_with_markers' ) ) {
		return; // Function still not available, skip
	}

	// Get home path
	$home_path = ABSPATH;
	if ( function_exists( 'get_home_path' ) ) {
		$home_path = get_home_path();
	}

	$htaccess = $home_path . '.htaccess';

	// Check if .htaccess exists and is writable using WP_Filesystem
	if ( $wp_filesystem->exists( $htaccess ) && $wp_filesystem->is_writable( $htaccess ) ) {
		// Remove SpeedMatrix-Core markers
		insert_with_markers( $htaccess, 'SpeedMatrix-Core', array() );
	}
}

/**
 * Delete all plugin options
 */
function speed_matrix_delete_options() {
	// Delete main settings
	delete_option( 'speed_matrix_settings' );
	delete_option( 'speed_matrix_version' );
	delete_option( 'speed_matrix_activated' );
	delete_option( 'speed_matrix_last_cleanup' );

	// Delete transients
	delete_transient( 'speed_matrix_cache_stats' );
	delete_transient( 'speed_matrix_activation_notice' );
	delete_transient( 'speed_matrix_cleanup_running' );
	delete_transient( 'speed_matrix_transients_count' );
}

/**
 * Clear scheduled cron events
 */
function speed_matrix_clear_scheduled_events() {
	// Clear auto cleanup event
	$timestamp = wp_next_scheduled( 'speed_matrix_auto_cleanup_event' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'speed_matrix_auto_cleanup_event' );
	}

	// Clear all instances of the hook
	wp_clear_scheduled_hook( 'speed_matrix_auto_cleanup_event' );
}

/**
 * Main uninstall routine
 */
function speed_matrix_uninstall() {
	// 1. Delete cache directory
	speed_matrix_delete_cache_directory();

	// 2. Remove .htaccess rules
	speed_matrix_remove_htaccess_rules();

	// 3. Delete all options
	speed_matrix_delete_options();

	// 4. Clear scheduled events
	speed_matrix_clear_scheduled_events();

	// 5. Clear object cache (if available)
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}

// Run uninstall
speed_matrix_uninstall();