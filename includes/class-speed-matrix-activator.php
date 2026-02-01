<?php
/**
 * Fired during plugin activation
 *
 * @package Speed_Matrix
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed Matrix Activator Class
 */
class Speed_Matrix_Activator {

	/**
	 * Plugin activation handler
	 */
	public static function activate() {
		self::create_cache_directory();
		self::set_default_settings();
		self::write_core_htaccess_rules();
		flush_rewrite_rules();
		update_option( 'speed_matrix_activated', time() );

		// Show admin notice
		set_transient( 'speed_matrix_activation_notice', true, 30 );
	}

	/**
	 * Create cache directory structure.
	 */
	private static function create_cache_directory() {
		// Get upload directory for cache storage
		$upload_dir = wp_upload_dir();
		$speed_matrix_cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'speed-matrix-cache/';

		// Create main cache directory.
		if ( ! file_exists( $speed_matrix_cache_dir ) ) {
			wp_mkdir_p( $speed_matrix_cache_dir );
		}

		// Initialize WP_Filesystem 
		if ( ! self::initialize_filesystem() ) {
			return;
		}

		global $wp_filesystem;

		// Create subdirectories.
		$subdirs = array( 'css', 'js', 'html' );
		foreach ( $subdirs as $subdir ) {
			$dir = $speed_matrix_cache_dir . $subdir . '/';
			if ( ! $wp_filesystem->exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}
		}

		// Add index.php to prevent directory listing.
		$index_content = "<?php\n// Silence is golden.\n";

		$index_files = array(
			$speed_matrix_cache_dir . 'index.php',
			$speed_matrix_cache_dir . 'css/index.php',
			$speed_matrix_cache_dir . 'js/index.php',
			$speed_matrix_cache_dir . 'html/index.php',
		);

		foreach ( $index_files as $index_file ) {
			if ( ! $wp_filesystem->exists( $index_file ) ) {
				$wp_filesystem->put_contents(
					$index_file,
					$index_content,
					FS_CHMOD_FILE
				);
			}
		}

		// Set proper permissions.
		if ( $wp_filesystem->exists( $speed_matrix_cache_dir ) ) {
			$wp_filesystem->chmod( $speed_matrix_cache_dir, FS_CHMOD_DIR );
		}

		foreach ( $subdirs as $subdir ) {
			$subdir_path = $speed_matrix_cache_dir . $subdir . '/';
			if ( $wp_filesystem->exists( $subdir_path ) ) {
				$wp_filesystem->chmod( $subdir_path, FS_CHMOD_DIR );
			}
		}
	}

	/**
	 * Set default plugin settings
	 */
	private static function set_default_settings() {
		// Only set defaults if settings don't exist
		if ( false === get_option( 'speed_matrix_settings' ) ) {
			$speed_matrix_default_settings = array(
				'optimization_preset' => 'recommended',
				'enable_page_cache' => '0',
				'cache_mobile_separate' => '0',
				'enable_browser_cache' => '0',
				'cache_expiry' => '31536000',
				'minify_html' => '0',
				'minify_inline_css' => '0',
				'minify_inline_js' => '0',
				'minify_css' => '0',
				'combine_css' => '0',
				'async_css' => '0',
				'remove_unused_css' => '0',
				'inline_critical_css' => '0',
				'critical_css' => '',
				'exclude_css' => '',
				'minify_js' => '0',
				'combine_js' => '0',
				'defer_js' => '0',
				'exclude_jquery' => '1',
				'delay_js_execution' => '0',
				'delay_js_timeout' => '5',
				'delay_js_patterns' => "google-analytics\ngoogletagmanager\nfacebook.net\ngtag\nfbevents",
				'exclude_js' => "jquery.min.js\njquery.js",
				'lazy_load' => '0',
				'lazy_load_iframes' => '0',
				'exclude_first_images' => '2',
				'enable_webp' => '0',
				'preload_key_requests' => '0',
				'lcp_image_url' => '',
				'preload_fonts' => '0',
				'font_urls' => '',
				'optimize_google_fonts' => '0',
				'google_fonts_method' => 'combine',
				'font_display_swap' => '0',
				'dns_prefetch' => '0',
				'dns_prefetch_urls' => "//fonts.googleapis.com\n//fonts.gstatic.com",
				'remove_query_strings' => '0',
				'disable_emojis' => '0',
				'disable_embeds' => '0',
				'disable_dashicons' => '0',
				'disable_jquery_migrate' => '0',
				'cdn_url' => '',
				'exclude_urls' => "/cart\n/checkout\n/my-account",
				'auto_cleanup' => '0',
				'cleanup_frequency' => 'weekly',
			);

			add_option( 'speed_matrix_settings', $speed_matrix_default_settings );
		}
	}

	/**
	 * Write core htaccess rules.
	 */
	private static function write_core_htaccess_rules() {
		// Initialize WP_Filesystem
		if ( ! self::initialize_filesystem() ) {
			return false;
		}

		global $wp_filesystem;

		// Check if required functions are available.
		if ( ! function_exists( 'insert_with_markers' ) ) {
			return false;
		}

		// Get home path - use uploads directory as fallback
		$home_path = ABSPATH;
		if ( function_exists( 'get_home_path' ) ) {
			$home_path = get_home_path();
		}

		$htaccess = $home_path . '.htaccess';

		// Check if .htaccess exists and is writable
		if ( ! $wp_filesystem->exists( $htaccess ) || ! $wp_filesystem->is_writable( $htaccess ) ) {
			return false;
		}

		$core_rules = array(
			'# =====================================',
			'# SpeedMatrix Core + Compression',
			'# =====================================',

			'# Disable directory browsing',
			'Options -Indexes',
			'',

			'# -------------------------------------',
			'# Browser caching (WordPress standard)',
			'# -------------------------------------',
			'<IfModule mod_headers.c>',
			'  # Cache CSS & JS for 1 year (versioned files)',
			'  <FilesMatch "\.(css|js)$">',
			'    Header set Cache-Control "public, max-age=31536000, immutable"',
			'  </FilesMatch>',

			'  # Cache images, fonts, media for 1 year',
			'  <FilesMatch "\.(jpg|jpeg|png|gif|webp|avif|svg|ico|woff|woff2|ttf|eot|mp4)$">',
			'    Header set Cache-Control "public, max-age=31536000, immutable"',
			'  </FilesMatch>',

			'  # Cache HTML for 1 hour (safe default)',
			'  <FilesMatch "\.html$">',
			'    Header set Cache-Control "public, max-age=3600"',
			'  </FilesMatch>',

			'  # Prevent font CORS issues',
			'  <FilesMatch "\.(woff|woff2)$">',
			'    Header set Access-Control-Allow-Origin "*"',
			'  </FilesMatch>',
			'</IfModule>',
			'',

			'# -------------------------------------',
			'# Expires headers (legacy support)',
			'# -------------------------------------',
			'<IfModule mod_expires.c>',
			'  ExpiresActive On',
			'  ExpiresDefault "access plus 1 month"',
			'  ExpiresByType text/css "access plus 1 year"',
			'  ExpiresByType application/javascript "access plus 1 year"',
			'  ExpiresByType image/webp "access plus 1 year"',
			'  ExpiresByType image/avif "access plus 1 year"',
			'</IfModule>',
			'',

			'# -------------------------------------',
			'# Compression (Brotli preferred, GZIP fallback)',
			'# -------------------------------------',

			'<IfModule mod_brotli.c>',
			'  AddOutputFilterByType BROTLI_COMPRESS \\',
			'    text/html text/plain text/xml text/css \\',
			'    text/javascript application/javascript application/json \\',
			'    application/xml application/rss+xml \\',
			'    image/svg+xml application/font-woff2',
			'</IfModule>',

			'<IfModule mod_deflate.c>',
			'  AddOutputFilterByType DEFLATE \\',
			'    text/html text/plain text/xml text/css \\',
			'    text/javascript application/javascript application/json \\',
			'    application/xml application/rss+xml \\',
			'    image/svg+xml application/font-woff2',
			'</IfModule>',
		);

		return insert_with_markers( $htaccess, 'SpeedMatrix-Core', $core_rules );
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
			// Load file.php if needed (activation context)
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

	/**
	 * Show activation notice with quick start instructions
	 */
	public static function activation_notice() {
		if ( ! get_transient( 'speed_matrix_activation_notice' ) ) {
			return;
		}

		delete_transient( 'speed_matrix_activation_notice' );
		?>
		<div class="notice notice-success is-dismissible">
			<h3>
				<?php esc_html_e( 'Speed Matrix Activated!', 'speed-matrix' ); ?>
			</h3>
			<p>
				<?php esc_html_e( 'Thank you for installing Speed Matrix. To get started:', 'speed-matrix' ); ?>
			</p>
			<ol style="margin-left: 20px;">
				<li>
					<?php esc_html_e( 'Go to Settings → Speed Matrix', 'speed-matrix' ); ?>
				</li>
				<li>
					<?php esc_html_e( 'Select a preset (Recommended for best results)', 'speed-matrix' ); ?>
				</li>
				<li>
					<?php esc_html_e( 'Enable Page Caching', 'speed-matrix' ); ?>
				</li>
				<li>
					<?php esc_html_e( 'Save your settings and test your site', 'speed-matrix' ); ?>
				</li>
			</ol>
			<p>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=speed-matrix' ) ); ?>"
					class="button button-primary">
					<?php esc_html_e( 'Configure Speed Matrix', 'speed-matrix' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

// Hook for activation notice
add_action( 'admin_notices', array( 'Speed_Matrix_Activator', 'activation_notice' ) );