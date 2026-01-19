<?php
/**
 * The core plugin class
 *
 * @package Speed_Matrix
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed Matrix Main Class
 */
class Speed_Matrix {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @var Speed_Matrix_Loader
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Cache instance
	 *
	 * @var Speed_Matrix_Cache
	 */
	protected $cache;

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		$this->version = SPEED_MATRIX_VERSION;
		$this->plugin_name = 'speed-matrix';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		// Initialize cache EARLY - must be done in constructor before WordPress loads
		$this->init_cache_early();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		require_once SPEED_MATRIX_PLUGIN_DIR . 'includes/class-speed-matrix-loader.php';
		require_once SPEED_MATRIX_PLUGIN_DIR . 'includes/class-speed-matrix-i18n.php';
		require_once SPEED_MATRIX_PLUGIN_DIR . 'admin/class-speed-matrix-admin.php';
		require_once SPEED_MATRIX_PLUGIN_DIR . 'includes/class-speed-matrix-optimizer.php';
		require_once SPEED_MATRIX_PLUGIN_DIR . 'includes/class-speed-matrix-cache.php';

		$this->loader = new Speed_Matrix_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {
		$plugin_i18n = new Speed_Matrix_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Speed_Matrix_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
		$this->loader->add_action( 'admin_bar_menu', $plugin_admin, 'add_admin_bar_menu', 999 );
		$this->loader->add_filter(
			'plugin_action_links_' . plugin_basename( SPEED_MATRIX_PLUGIN_FILE ),
			$plugin_admin,
			'add_action_links'
		);
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		// Initialize optimization early
		$this->loader->add_action( 'init', $this, 'init_optimization', 1 );

		// Browser cache headers (only for static assets, not HTML pages)
		$this->loader->add_action( 'send_headers', $this, 'add_browser_cache_headers' );
	}

	/**
	 * Initialize cache system early (in constructor)
	 * This ensures cache hooks are registered before WordPress fully loads
	 */

	private function init_cache_early() {
		$settings = get_option( 'speed_matrix_settings', array() );

		// Initialize cache if enabled
		if ( ! empty( $settings['enable_page_cache'] ) && '1' === $settings['enable_page_cache'] ) {
			$this->cache = new Speed_Matrix_Cache();

			// Only call init() if the method exists
			if ( method_exists( $this->cache, 'init' ) ) {
				$this->cache->init();
			}
		}
	}


	/**
	 * Initialize optimization on 'init' hook
	 * Runs for asset optimization (CSS/JS/Images)
	 */
	public function init_optimization() {
		// Optimizer for CSS/JS/Images
		$optimizer = new Speed_Matrix_Optimizer();
		$optimizer->init();
	}

	/**
	 * Add browser cache headers for static assets only
	 * Does NOT add headers to HTML pages (cache handles that)
	 */
	public function add_browser_cache_headers() {
		// Don't add headers for admin
		if ( is_admin() ) {
			return;
		}

		// Don't add if headers already sent
		if ( headers_sent() ) {
			return;
		}

		// Check if browser cache is enabled
		$settings = get_option( 'speed_matrix_settings', array() );
		if ( empty( $settings['enable_browser_cache'] ) || '1' !== $settings['enable_browser_cache'] ) {
			return;
		}

		// Only add cache headers for static assets, NOT for HTML pages
		// This prevents conflict with page cache headers
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$is_static_asset = preg_match( '/\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|webp)$/i', $request_uri );

		// Skip if this is an HTML page (let cache class handle it)
		if ( ! $is_static_asset ) {
			return;
		}

		// Set long cache for static assets
		$cache_expiry = ! empty( $settings['cache_expiry'] )
			? absint( $settings['cache_expiry'] )
			: YEAR_IN_SECONDS;

		header( 'Cache-Control: public, max-age=' . $cache_expiry );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $cache_expiry ) . ' GMT' );
	}

	/**
	 * Check if GZIP is enabled
	 *
	 * @return bool
	 */
	public static function is_gzip_enabled() {
		// Server already handles this (Apache/Nginx/Cloudflare)
		if ( headers_sent() ) {
			return false;
		}

		// Fully WordPress-compliant: unslash and sanitize
		$accept_encoding = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_ENCODING'] ) )
			: '';

		return ( false !== strpos( $accept_encoding, 'gzip' ) );
	}



	/**
	 * Get cache instance
	 *
	 * @return Speed_Matrix_Cache|null
	 */
	public function get_cache() {
		return $this->cache;
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return Speed_Matrix_Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}