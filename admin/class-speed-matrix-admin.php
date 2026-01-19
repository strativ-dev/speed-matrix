<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Speed_Matrix_Admin {
	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Enqueue admin CSS
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			SPEED_MATRIX_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$this->version,
			'all'
		);
	}



	/**
	 * Enqueue admin JS
	 */
	public function enqueue_scripts() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && strpos( $screen->id, 'speed-matrix' ) !== false ) {
				wp_enqueue_script(
					$this->plugin_name,
					SPEED_MATRIX_PLUGIN_URL . 'assets/js/admin.js',
					array( 'jquery' ),
					$this->version,
					false
				);
			}
		}
	}


	/**
	 * Add plugin menu
	 */
	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'Speed Matrix Settings', 'speed-matrix' ),
			__( 'Speed Matrix', 'speed-matrix' ),
			'manage_options',
			'speed-matrix',
			array( $this, 'display_plugin_admin_page' ),
			SPEED_MATRIX_PLUGIN_URL . 'assets/images/speed-matrix-logo.png'
		);
	}

	/**
	 * Add admin bar menu with cache stats
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$stats = $this->get_cache_count();
		$cache_count = $stats['count'] . ' | ' . $this->format_bytes( $stats['size'] );

		$wp_admin_bar->add_node( array(
			'id' => 'speed-matrix',
			'title' => __( 'Speed Matrix', 'speed-matrix' ) . ' (' . $cache_count . ')',
			'href' => admin_url( 'admin.php?page=speed-matrix' ),
		) );
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Array with 'count' and 'size'
	 */
	private function get_cache_count() {
		$cache_dir = defined( 'SPEED_MATRIX_CACHE_DIR' )
			? SPEED_MATRIX_CACHE_DIR
			: WP_CONTENT_DIR . '/cache/speed-matrix/';

		$stats = array(
			'count' => 0,
			'size' => 0,
		);

		if ( ! is_dir( $cache_dir ) ) {
			return $stats;
		}

		try {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $cache_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ( $files as $file ) {
				if ( $file->isFile() ) {
					$stats['count']++;
					$stats['size'] += $file->getSize();
				}
			}
		} catch (Exception $e) {
			// Fallback: simple glob
			$patterns = array(
				$cache_dir . '*.html',
				$cache_dir . 'html/*.html',
				$cache_dir . 'css/*.css',
				$cache_dir . 'js/*.js',
			);

			foreach ( $patterns as $pattern ) {
				foreach ( glob( $pattern ) as $file ) {
					if ( is_file( $file ) ) {
						$stats['count']++;
						$stats['size'] += filesize( $file );
					}
				}
			}
		}

		return $stats;
	}

	/**
	 * Format bytes to human-readable string
	 *
	 * @param int $bytes
	 * @param int $precision
	 * @return string
	 */
	private function format_bytes( $bytes, $precision = 2 ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes = max( $bytes, 0 );
		$pow = $bytes ? floor( log( $bytes, 1024 ) ) : 0;
		$pow = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );

		return round( $bytes, $precision ) . ' ' . $units[ $pow ];
	}

	/**
	 * Display plugin settings page
	 */
	public function display_plugin_admin_page() {
		$settings_file = SPEED_MATRIX_PLUGIN_DIR . 'admin/admin-settings.php';
		if ( file_exists( $settings_file ) ) {
			include_once $settings_file;
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Settings file not found.', 'speed-matrix' ) . '</p></div>';
		}
	}

	/**
	 * Add "Settings" link on Plugins page
	 */
	public function add_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=speed-matrix' ),
			__( 'Settings', 'speed-matrix' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}
