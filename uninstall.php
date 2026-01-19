<?php
/**
 * Uninstall Speed Matrix
 *
 * @package Speed_Matrix
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all cache files.
$speed_matrix_cache_dir = WP_CONTENT_DIR . '/cache/speed-matrix/';

if ( ! is_dir( $speed_matrix_cache_dir ) ) {
	return;
}

require_once ABSPATH . 'wp-admin/includes/file.php';

WP_Filesystem();

global $wp_filesystem;

if ( ! $wp_filesystem ) {
	return;
}

try {
	$wp_filesystem->delete( $speed_matrix_cache_dir, true );
} catch (Exception $e) {
	// Silent fail.
}

// Delete plugin options.
delete_option( 'speed_matrix_settings' );
delete_option( 'speed_matrix_version' );

// Clear transients.
delete_transient( 'speed_matrix_cache_stats' );
